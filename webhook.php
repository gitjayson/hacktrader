<?php
/**
 * webhook.php — Stripe event receiver. The single source of truth for
 * subscription state in our DB.
 *
 * Subscribed events:
 *   - checkout.session.completed     → first-time signup, set stripe_customer_id
 *   - customer.subscription.created  → set plan + status + current_period_end
 *   - customer.subscription.updated  → plan changes, renewals, status flips
 *   - customer.subscription.deleted  → cancellation
 *   - invoice.payment_failed         → flip to past_due
 *   - invoice.payment_succeeded      → audit-trail logging
 *
 * STATUS: active. Stripe SDK is wired in, signature is verified against the
 * configured webhook signing secret, events dispatch to handler functions
 * below. Handler errors are caught + logged so we always 200-ack to Stripe
 * (a 500 would trigger their retry logic and re-fire the same event).
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/subscription.php';

$config = hacktrader_stripe_config();
if (!$config['secret_key'] || !$config['webhook_secret']) {
    http_response_code(503);
    error_log('webhook.php hit before Stripe keys configured');
    exit;
}

$payload = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

// SDK is now live. composer require stripe/stripe-php has been run on the
// server, vendor/autoload.php exists, keys are in secrets.json. Verify the
// signature with the webhook signing secret before trusting any payload —
// without this anyone could POST fake subscription events at /webhook.php
// and flip our DB rows.
require_once __DIR__ . '/vendor/autoload.php';
\Stripe\Stripe::setApiKey($config['secret_key']);

try {
    $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $config['webhook_secret']);
} catch (\UnexpectedValueException $e) {
    http_response_code(400);
    error_log('webhook.php: invalid payload: ' . $e->getMessage());
    exit;
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400);
    error_log('webhook.php: signature verification failed: ' . $e->getMessage());
    exit;
}

$eventType = $event->type;
// Convert the Stripe object graph into an associative array so the existing
// handler functions (which take arrays) keep working unchanged.
$eventData = $event->data->object->toArray();

error_log('webhook.php verified: ' . $eventType);

try {
    switch ($eventType) {
        case 'checkout.session.completed':
            handle_checkout_completed($eventData);
            break;
        case 'customer.subscription.created':
        case 'customer.subscription.updated':
            handle_subscription_changed($eventData);
            break;
        case 'customer.subscription.deleted':
            handle_subscription_deleted($eventData);
            break;
        case 'invoice.payment_failed':
            handle_payment_failed($eventData);
            break;
        case 'invoice.payment_succeeded':
            // Renewals re-emit subscription.updated separately, so this is
            // mostly informational. Logged for the audit trail.
            error_log('webhook.php: invoice paid for customer ' . ($eventData['customer'] ?? '?'));
            break;
        default:
            // Acknowledge unhandled events with 200 so Stripe doesn't retry
            // them. If we ever care about them, add a case above.
            break;
    }
} catch (\Throwable $e) {
    // v0.13.x security review fix: fail closed. Earlier this catch
    // returned 200 to avoid Stripe retries, but the cost was that
    // genuinely failed state writes (DB locked, schema migration error,
    // bug in a handler) silently dropped on the floor with users stuck
    // in trial/free/canceled states they shouldn't be in. Stripe will
    // retry on 5xx with exponential backoff (~3 days), which is exactly
    // what we want for transient errors. The handlers are idempotent
    // (all keyed by customer_id lookup), so re-running them is safe.
    error_log('webhook.php: handler error for ' . $eventType . ': '
        . get_class($e) . ': ' . $e->getMessage()
        . ' at ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode([
        'received' => true,
        'event_type' => $eventType,
        'error' => 'handler_failed',
    ]);
    exit;
}

http_response_code(200);
echo json_encode(['received' => true, 'event_type' => $eventType]);

// ---- Handler stubs (filled in alongside the SDK wiring) ---------------------

function handle_checkout_completed(array $session): void {
    $customerId = $session['customer'] ?? null;
    $userId = $session['metadata']['user_id'] ?? ($session['subscription_data']['metadata']['user_id'] ?? null);
    if (!$customerId || !$userId) return;
    $db = hacktrader_db();
    $stmt = $db->prepare('UPDATE users SET stripe_customer_id = ?, updated_at = ? WHERE id = ?');
    $stmt->execute([$customerId, time(), (int) $userId]);
}

function handle_subscription_changed(array $sub): void {
    $customerId = $sub['customer'] ?? null;
    if (!$customerId) return;
    $user = user_by_stripe_customer($customerId);
    if (!$user) return;

    // Resolve which of our plans this subscription is on by matching the
    // first item's price ID against the configured Plus/Pro IDs.
    $config = hacktrader_stripe_config();
    $priceId = $sub['items']['data'][0]['price']['id'] ?? null;
    // v0.13.0 — map Stripe's price ID back to our plan slug. Starter is
    // the active $9.99 tier; plus/pro remain in the map for when those
    // tiers go live with the real-time feed.
    $plan = 'free';
    if ($priceId === $config['price_starter']) $plan = 'starter';
    elseif ($priceId === $config['price_plus']) $plan = 'plus';
    elseif ($priceId === $config['price_pro']) $plan = 'pro';

    $status = $sub['status'] ?? 'none';
    // Stripe moved current_period_end off the subscription root and onto each
    // subscription item in newer API versions (2025+). Try the item-level
    // location first, fall back to the legacy root location for older APIs.
    $end = (int) (
        $sub['items']['data'][0]['current_period_end']
        ?? $sub['current_period_end']
        ?? 0
    );
    $subId = $sub['id'] ?? null;

    $db = hacktrader_db();
    $stmt = $db->prepare(
        'UPDATE users SET plan = ?, subscription_status = ?, current_period_end = ?, stripe_subscription_id = ?, updated_at = ? WHERE id = ?'
    );
    $stmt->execute([$plan, $status, $end, $subId, time(), $user['id']]);
}

function handle_subscription_deleted(array $sub): void {
    $customerId = $sub['customer'] ?? null;
    if (!$customerId) return;
    $user = user_by_stripe_customer($customerId);
    if (!$user) return;
    $db = hacktrader_db();
    $stmt = $db->prepare(
        'UPDATE users SET plan = "free", subscription_status = "canceled", stripe_subscription_id = NULL, updated_at = ? WHERE id = ?'
    );
    $stmt->execute([time(), $user['id']]);
}

function handle_payment_failed(array $invoice): void {
    $customerId = $invoice['customer'] ?? null;
    if (!$customerId) return;
    $user = user_by_stripe_customer($customerId);
    if (!$user) return;
    $db = hacktrader_db();
    $stmt = $db->prepare('UPDATE users SET subscription_status = "past_due", updated_at = ? WHERE id = ?');
    $stmt->execute([time(), $user['id']]);
}
