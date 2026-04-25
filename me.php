<?php
/**
 * me.php — JSON snapshot of the current user's subscription state.
 *
 * Used by the dashboard's Subscription panel to render plan, usage, and
 * limits. Returns 401 if no session, 200 with payload otherwise. Pure read,
 * no side effects.
 */

declare(strict_types=1);

session_start();
require_once __DIR__ . '/lib/subscription.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_email'])) {
    http_response_code(401);
    echo json_encode(['error' => 'not_signed_in']);
    exit;
}

$user = current_user_record();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'no_user_record']);
    exit;
}

$summary = user_usage_summary($user);
$summary['user'] = [
    'email' => $user['email'],
    'name' => $user['name'] ?? null,
];
echo json_encode($summary);
