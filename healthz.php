<?php
function respond_json($payload, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// v0.13.6 — compute config block up front so it appears in every
// response path, including the "starting" early return below.
// Ops is most likely to check /healthz.php right after a deploy,
// before any user traffic has populated health-status.json — that's
// exactly when knowing quota_mode matters.
$quotaHardGate = filter_var(
    getenv('HACKTRADER_QUOTA_HARD_GATE') ?: '0',
    FILTER_VALIDATE_BOOLEAN
);
$trustForwardedProto = filter_var(
    getenv('HACKTRADER_TRUST_FORWARDED_PROTO') ?: '0',
    FILTER_VALIDATE_BOOLEAN
);
$configBlock = [
    'quota_hard_gate' => $quotaHardGate,
    'quota_mode' => $quotaHardGate ? 'hard' : 'soft',
    'trust_forwarded_proto' => $trustForwardedProto,
];

$statePath = __DIR__ . '/state/health-status.json';
if (!file_exists($statePath)) {
    respond_json([
        'status' => 'starting',
        'app' => 'hacktrader',
        'reason' => 'Health state not initialized yet.',
        'config' => $configBlock,
    ], 200);
}

$state = json_decode(file_get_contents($statePath), true);
if (!is_array($state)) {
    respond_json([
        'status' => 'error',
        'app' => 'hacktrader',
        'reason' => 'Health state is malformed.',
    ], 500);
}

$counters = is_array($state['counters'] ?? null) ? $state['counters'] : [];
$last = is_array($state['last'] ?? null) ? $state['last'] : [];
$recentEvents = is_array($state['recent_events'] ?? null) ? $state['recent_events'] : [];
$window = array_slice($recentEvents, -20);
$windowCount = count($window);
$staleCount = 0;
$errorCount = 0;
foreach ($window as $event) {
    $status = $event['live_status'] ?? null;
    if ($status === 'stale_fallback') {
        $staleCount++;
    } elseif ($status === 'error') {
        $errorCount++;
    }
}
$staleRatio = $windowCount > 0 ? round($staleCount / $windowCount, 3) : 0.0;
$errorRatio = $windowCount > 0 ? round($errorCount / $windowCount, 3) : 0.0;

$status = 'ok';
$http = 200;
$reason = 'Live feed healthy.';
if (($counters['consecutive_failures'] ?? 0) >= 3 || $errorRatio >= 0.5) {
    $status = 'degraded';
    $reason = 'Recent live failures exceed threshold.';
} elseif (($counters['consecutive_stale_serves'] ?? 0) >= 2 || $staleRatio >= 0.5) {
    $status = 'degraded';
    $reason = 'Recent stale fallback rate exceeds threshold.';
}

// v0.13.4 — surface the quota-enforcement state so ops can verify
// at a glance whether the box is counting-only ("soft") or enforcing
// ("hard"). v0.13.6 — same $configBlock used in the "starting" early
// return above; we just attach it here too for the populated path.
respond_json([
    'status' => $status,
    'app' => 'hacktrader',
    'reason' => $reason,
    'updated_at' => $state['meta']['updated_at'] ?? null,
    'counters' => $counters,
    'last' => $last,
    'recent_window' => [
        'events' => $windowCount,
        'stale_count' => $staleCount,
        'error_count' => $errorCount,
        'stale_ratio' => $staleRatio,
        'error_ratio' => $errorRatio,
    ],
    'config' => $configBlock,
], $http);
