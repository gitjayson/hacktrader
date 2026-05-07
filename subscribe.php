<?php
/**
 * subscribe.php — start a Stripe Checkout session for the requested plan.
 *
 * Flow:
 *   1. Auth gate (must be Google-logged-in)
 *   2. Read ?plan=plus|pro from query string
 *   3. Look up the user's Stripe customer ID (create on Stripe if missing)
 *   4. Create a Checkout session in subscription mode
 *   5. Redirect the browser to the hosted Checkout URL
 *
 * STATUS: active. Stripe SDK is wired in, keys are read from secrets.json,
 * Checkout sessions are created server-side and the user gets a 302 to the
 * hosted Stripe Checkout URL.
 */

declare(strict_types=1);

session_start();
require_once __DIR__ . '/lib/subscription.php';

if (empty($_SESSION['user_email'])) {
    header('Location: index.php');
    exit;
}

$user = current_user_record();
if (!$user) {
    header('Location: index.php');
    exit;
}

$plan = $_GET['plan'] ?? '';
if (!in_array($plan, ['plus', 'pro'], true)) {
    http_response_code(400);
    echo 'Unknown plan';
    exit;
}

$config = hacktrader_stripe_config();
if (!$config['secret_key']) {
    http_response_code(503);
    echo "Subscriptions aren't wired up yet — Stripe keys haven't been configured. Check back shortly.";
    exit;
}

$priceId = $plan === 'plus' ? $config['price_plus'] : $config['price_pro'];
if (!$priceId) {
    http_response_code(503);
    echo "Plan price not configured for {$plan}.";
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';
\Stripe\Stripe::setApiKey($config['secret_key']);

try {
    // Reuse the user's Stripe customer if they have one (e.g. cancelled and
    // resubscribing). Otherwise create one and persist the ID before kicking
    // off Checkout — that way the webhook can resolve the user even if the
    // user closes the browser mid-flow.
    $customerId = $user['stripe_customer_id'] ?? null;
    if (!$customerId) {
        $customer = \Stripe\Customer::create([
            'email' => $user['email'],
            'name'  => $user['name'] ?? null,
            'metadata' => [
                'user_id' => (string) $user['id'],
                'google_sub' => $user['google_sub'] ?? '',
            ],
        ]);
        $customerId = $customer->id;
        $db = hacktrader_db();
        $db->prepare('UPDATE users SET stripe_customer_id = ?, updated_at = ? WHERE id = ?')
           ->execute([$customerId, time(), (int) $user['id']]);
    }

    $session = \Stripe\Checkout\Session::create([
        'mode'                  => 'subscription',
        'customer'              => $customerId,
        'line_items'            => [['price' => $priceId, 'quantity' => 1]],
        'success_url'           => 'https://dev.hacktrader.com/dashboard.php?subscribed=1',
        'cancel_url'            => 'https://dev.hacktrader.com/index.php?canceled=1',
        'allow_promotion_codes' => true,
        // user_id propagates onto the subscription object so the webhook
        // can resolve the local user even if the customer email differs.
        'subscription_data'     => [
            'metadata' => ['user_id' => (string) $user['id']],
        ],
    ]);

    header('Location: ' . $session->url);
    exit;
} catch (\Throwable $e) {
    error_log('subscribe.php: Stripe error for user ' . ($user['id'] ?? '?') . ' plan ' . $plan . ': ' . $e->getMessage());
    http_response_code(502);
    echo 'Could not start checkout. Please try again, or contact support if this keeps happening.';
    exit;
}
