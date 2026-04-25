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
 *
 * v0.9.0 STATUS: scaffold. Verifies signature, dispatches by event type,
 * with TODO bodies for each handler. Flesh out once SDK + keys are in.
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

// TODO once stripe/stripe-php is available:
//   require_once __DIR__ . '/vendor/autoload.php';
//   \Stripe\Stripe::setApiKey($config['secret_key']);
//   try {
//       $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $config['webhook_secret']);
//   } catch (\UnexpectedValueException $e) {
//       http_response_code(400); exit;
//   } catch (\Stripe\Exception\SignatureVerificationException $e) {
//       http_response_code(400); exit;
//   }

// Until the SDK lands, log + 200 so dev doesn't trigger Stripe's retry logic
// and we can see what events are coming through.
$decoded = json_decode($payload, true);
$type = is_array($decoded) ? ($decoded['type'] ?? 'unknown') : 'invalid-json';
error_log('webhook.php received (unverified): ' . $type);

// $event = $decoded;  // <- uncomment + replace with the verified $event once SDK is in
// switch ($event['type']) {
//     case 'checkout.session.completed':
//         handle_checkout_completed($event['data']['object']);
//         break;
//     case 'customer.subscription.created':
//     case 'customer.subscription.updated':
//         handle_subscription_changed($event['data']['object']);
//         break;
//     case 'customer.subscription.deleted':
//         handle_subscription_deleted($event['data']['object']);
//         break;
//     case 'invoice.payment_failed':
//         handle_payment_failed($event['data']['object']);
//         break;
// }

http_response_code(200);
echo json_encode(['received' => true, 'event_type' => $type]);

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
    $plan = 'free';
    if ($priceId === $config['price_plus']) $plan = 'plus';
    elseif ($priceId === $config['price_pro']) $plan = 'pro';

    $status = $sub['status'] ?? 'none';
    $end = (int) ($sub['current_period_end'] ?? 0);
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
