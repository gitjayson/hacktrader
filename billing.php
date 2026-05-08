<?php
/**
 * billing.php — redirect the user to their Stripe Customer Portal.
 *
 * Customer Portal handles card updates, plan changes, cancellations, and
 * invoice history. We don't render any billing UI ourselves; Stripe owns
 * that surface.
 *
 * STATUS: active. Creates a Customer Portal session via the Stripe SDK and
 * 302s the user there.
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

$config = hacktrader_stripe_config();
if (!$config['secret_key']) {
    http_response_code(503);
    echo "Billing portal isn't wired up yet — Stripe keys haven't been configured.";
    exit;
}

if (empty($user['stripe_customer_id'])) {
    // No Stripe customer yet — they're free-tier and have never subscribed.
    // Send them to the pricing page so they can subscribe first.
    header('Location: index.php#pricing');
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';
\Stripe\Stripe::setApiKey($config['secret_key']);

try {
    $session = \Stripe\BillingPortal\Session::create([
        'customer'   => $user['stripe_customer_id'],
        // Return the user to the same host they came from so dev/prod
        // each bounce back to themselves.
        'return_url' => hacktrader_app_url('dashboard.php'),
    ]);
    header('Location: ' . $session->url);
    exit;
} catch (\Throwable $e) {
    error_log('billing.php: Stripe error for user ' . ($user['id'] ?? '?') . ': ' . $e->getMessage());
    http_response_code(502);
    echo 'Could not open the billing portal. Please try again later.';
    exit;
}
