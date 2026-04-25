<?php
/**
 * test-login.php — programmatic sign-in for QA / automated test agents.
 *
 * SECURITY MODEL
 * --------------
 * This endpoint bypasses Google OAuth, so it's gated two ways:
 *
 *   1. A shared secret from secrets.json (TEST_LOGIN_KEY). Without the
 *      correct key, any request fails with 401. Use a high-entropy value;
 *      if the key leaks, rotate it.
 *
 *   2. A hardcoded allow-list of test email addresses. Even with a valid
 *      key, only addresses in TEST_LOGIN_ALLOWED_EMAILS can be impersonated.
 *      This means a leaked key can only access dummy accounts, never a
 *      real user.
 *
 * USAGE
 * -----
 *   GET /test-login.php?key=<secret>&email=dummy123@hacktrader.com
 *   GET /test-login.php?key=<secret>&email=dummy123@hacktrader.com&plan=plus
 *   GET /test-login.php?key=<secret>&email=dummy123@hacktrader.com&plan=pro&status=active
 *
 * Query parameters:
 *   key     (required) — shared secret matching secrets.json TEST_LOGIN_KEY
 *   email   (required) — must be in the allow-list below
 *   plan    (optional) — free | plus | pro    (default: free)
 *   status  (optional) — none | trialing | active | past_due  (default: trialing)
 *
 * On success, sets the same session variables callback.php does and
 * redirects to the dashboard. On failure, returns a 4xx with a message.
 *
 * To DISABLE this endpoint entirely in production, just remove
 * TEST_LOGIN_KEY from secrets.json (or set it to empty). With no key
 * configured, every request returns 503.
 */

declare(strict_types=1);

session_start();
require_once __DIR__ . '/lib/subscription.php';

const TEST_LOGIN_ALLOWED_EMAILS = [
    'dummy123@hacktrader.com',
    'qa@hacktrader.com',
    'agent@hacktrader.com',
];

// ---- Secret check ----------------------------------------------------------
// Use the shared loader so we honor /var/www/secrets.json (above webroot)
// just like callback.php does, instead of hardcoding /var/www/html/.
$secrets = hacktrader_load_secrets();
$expectedKey = $secrets['TEST_LOGIN_KEY'] ?? null;
if (!$expectedKey) {
    http_response_code(503);
    echo 'test-login disabled (TEST_LOGIN_KEY not configured in secrets.json)';
    exit;
}

$providedKey = (string) ($_GET['key'] ?? '');
if (!hash_equals((string) $expectedKey, $providedKey)) {
    http_response_code(401);
    error_log('test-login.php: bad key from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    echo 'invalid key';
    exit;
}

// ---- Email allow-list ------------------------------------------------------
$email = strtolower(trim((string) ($_GET['email'] ?? '')));
if (!in_array($email, TEST_LOGIN_ALLOWED_EMAILS, true)) {
    http_response_code(403);
    error_log("test-login.php: email '{$email}' not in allow-list");
    echo 'email not in allow-list';
    exit;
}

// ---- Optional plan / status overrides -------------------------------------
$plan = strtolower(trim((string) ($_GET['plan'] ?? 'free')));
if (!in_array($plan, ['free', 'plus', 'pro'], true)) {
    $plan = 'free';
}
$status = strtolower(trim((string) ($_GET['status'] ?? 'trialing')));
if (!in_array($status, ['none', 'trialing', 'active', 'past_due', 'canceled'], true)) {
    $status = 'trialing';
}

// ---- Upsert + plan override -----------------------------------------------
// Test endpoint — surface the actual exception in the response so QA can
// see what's wrong without needing server log access. (Don't do this in
// real-user-facing endpoints.)
try {
    $googleSub = 'test-login:' . substr(sha1($email), 0, 16);
    $displayName = ucfirst(explode('@', $email)[0]) . ' (test)';
    upsert_user_from_oauth($email, $googleSub, $displayName);

    // Override the plan / status fields after upsert so QA can exercise
    // any tier without waiting for a real Stripe event.
    $db = hacktrader_db();
    $now = time();
    $periodEnd = $now + (30 * 86400);  // 30 days out
    $stmt = $db->prepare(
        'UPDATE users SET plan = ?, subscription_status = ?, current_period_end = ?, trial_end = ?, updated_at = ? WHERE email = ?'
    );
    $stmt->execute([$plan, $status, $periodEnd, $periodEnd, $now, $email]);
} catch (Throwable $e) {
    http_response_code(500);
    $msg = 'test-login.php upsert failed: ' . $e->getMessage()
        . ' (file: ' . $e->getFile() . ':' . $e->getLine() . ')';
    error_log($msg);
    header('Content-Type: text/plain');
    echo $msg . "\n\n";
    echo $e->getTraceAsString();
    exit;
}

// ---- Build the same session shape callback.php does ----------------------
session_regenerate_id(true);
$_SESSION['oauth_authenticated_at'] = time();
$_SESSION['user_name'] = $displayName;
$_SESSION['user_display_name'] = $displayName;
$_SESSION['user_email'] = $email;
$_SESSION['google_sub'] = $googleSub;
$_SESSION['session_user_name'] = preg_replace('/[^a-z0-9_]/', '_', explode('@', $email)[0]);
$_SESSION['session_identity'] = 'session:test:' . $_SESSION['session_user_name'];
$_SESSION['login_time'] = time();
// Skip the disclaimer flow for test users — they're not real users
$_SESSION['agreed'] = true;

error_log("test-login.php: signed in {$email} (plan={$plan} status={$status}) from " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

header('Location: dashboard.php');
exit;
