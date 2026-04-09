<?php
session_start();

$apiAuthPath = __DIR__ . '/api_auth.php';
if (file_exists($apiAuthPath)) {
    require_once $apiAuthPath;
}

$ticker = strtoupper($_GET['ticker'] ?? 'TSLA');
$period = $_GET['period'] ?? '5m';
$lookback = $_GET['lookback'] ?? '100';

$pipelineDir = 'pipelines';
$focusUniverseFile = __DIR__ . '/focus-universe.json';
$marketWatchlistFile = __DIR__ . '/market-watchlist.json';
$correlationGenerator = __DIR__ . '/generate-correlations.py';
if (!is_dir($pipelineDir)) {
    mkdir($pipelineDir, 0755, true);
}
$cacheKey = preg_replace('/[^A-Z0-9_-]/', '_', strtoupper($ticker) . '_' . strtolower($period) . '_' . (int) $lookback);
$pipeline = "$pipelineDir/$cacheKey.json";
$maxCacheAgeSeconds = 30;

function respond_json($payload, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_PRETTY_PRINT);
    exit;
}

function sanitize_session_label($value) {
    $value = strtolower(trim((string) $value));
    $value = preg_replace('/[^a-z0-9:_-]+/', '_', $value);
    $value = trim($value, '_');
    return $value ?: 'anonymous';
}

function current_requester_identity() {
    if (!empty($_SESSION['session_identity'])) {
        return sanitize_session_label($_SESSION['session_identity']);
    }
    if (!empty($_SESSION['session_user_name'])) {
        return 'session:' . sanitize_session_label($_SESSION['session_user_name']);
    }
    if (!empty($_SESSION['user_email'])) {
        return 'session:' . sanitize_session_label($_SESSION['user_email']);
    }
    if (!empty($_SESSION['user_name'])) {
        return 'session:' . sanitize_session_label($_SESSION['user_name']);
    }
    return 'session:anonymous';
}

function read_cached_payload($pipeline, $maxAgeSeconds = null) {
    if (!file_exists($pipeline)) {
        return null;
    }
    if ($maxAgeSeconds !== null && (time() - filemtime($pipeline) >= $maxAgeSeconds)) {
        return null;
    }

    $contents = file_get_contents($pipeline);
    if ($contents === false || trim($contents) === '') {
        return null;
    }

    $decoded = json_decode($contents, true);
    return is_array($decoded) ? $decoded : null;
}

function load_json_file($path, $default) {
    if (!file_exists($path)) {
        return $default;
    }
    $contents = file_get_contents($path);
    if ($contents === false || trim($contents) === '') {
        return $default;
    }
    $decoded = json_decode($contents, true);
    return is_array($decoded) ? $decoded : $default;
}

function save_json_file($path, $payload) {
    $tmp = $path . '.tmp';
    file_put_contents($tmp, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    rename($tmp, $path);
}

function build_usage_summary($trackerPath, $sessionId) {
    $tracker = load_json_file($trackerPath, [
        'meta' => [],
        'sessions' => [],
        'recent_events' => [],
    ]);

    $sessionEntry = $tracker['sessions'][$sessionId] ?? [];
    $providerEntry = $sessionEntry['providers']['twelvedata'] ?? [];
    $attempts = (int) ($providerEntry['attempts'] ?? 0);
    $successes = (int) ($providerEntry['successes'] ?? 0);
    $errors = (int) ($providerEntry['errors'] ?? 0);

    return [
        'session_id' => $sessionId,
        'provider' => 'twelvedata',
        'attempts' => $attempts,
        'successes' => $successes,
        'errors' => $errors,
        'success_rate' => $attempts > 0 ? round(($successes / $attempts) * 100, 1) : null,
        'last_request_at' => $sessionEntry['last_request_at'] ?? null,
        'last_ticker' => $sessionEntry['last_ticker'] ?? null,
        'last_interval' => $sessionEntry['last_interval'] ?? null,
        'last_periods' => $sessionEntry['last_periods'] ?? null,
        'last_outcome' => $sessionEntry['last_outcome'] ?? null,
        'updated_at' => $tracker['meta']['updated_at'] ?? null,
    ];
}

function with_usage_summary($payload, $sessionId) {
    if (!is_array($payload)) {
        return $payload;
    }
    $payload['usage'] = build_usage_summary(__DIR__ . '/api_usage_tracker.json', $sessionId);
    return $payload;
}

function remember_focus_symbol($ticker, $focusUniverseFile, $marketWatchlistFile, $correlationGenerator) {
    $ticker = strtoupper(trim((string) $ticker));
    if ($ticker === '') {
        return;
    }

    $now = gmdate('c');
    $universe = load_json_file($focusUniverseFile, ['symbols' => [], 'seen' => []]);
    $symbols = $universe['symbols'] ?? [];
    $seen = $universe['seen'] ?? [];

    if (!in_array($ticker, $symbols, true)) {
        $symbols[] = $ticker;
        sort($symbols);
    }
    $seen[$ticker] = $now;
    $universe['symbols'] = array_values($symbols);
    $universe['seen'] = $seen;
    save_json_file($focusUniverseFile, $universe);

    $watchlist = load_json_file($marketWatchlistFile, []);
    if (!in_array($ticker, $watchlist, true)) {
        $watchlist[] = $ticker;
        sort($watchlist);
        save_json_file($marketWatchlistFile, array_values($watchlist));
    }

    if (file_exists($correlationGenerator)) {
        $cmd = 'python3 ' . escapeshellarg($correlationGenerator) . ' ' . escapeshellarg($ticker) . ' > /dev/null 2>&1 &';
        @exec($cmd);
    }
}

$apiKey = $_GET['api_key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? null;
$sessionAuthorized = !empty($_SESSION['user_name']) && !empty($_SESSION['agreed']);
$requesterIdentity = current_requester_identity();

if ($sessionAuthorized && !$apiKey) {
    $account = ['owner' => $_SESSION['user_email'] ?? ($_SESSION['user_name'] ?? 'browser-session'), 'tier' => 'session'];
    $usageActor = $requesterIdentity;
} else {
    $account = function_exists('authenticate_api_key') ? authenticate_api_key($apiKey) : false;
    if (!$account) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Unauthorized. Invalid or missing API key.']);
        exit;
    }
    $usageActor = $apiKey;
}

if (function_exists('log_api_usage')) {
    log_api_usage($usageActor, '/api.php', $ticker);
}

$freshCache = read_cached_payload($pipeline, $maxCacheAgeSeconds);
if ($freshCache !== null) {
    $freshCache['cache'] = [
        'hit' => true,
        'stale' => false,
        'age_seconds' => time() - filemtime($pipeline),
    ];
    respond_json(with_usage_summary($freshCache, $requesterIdentity), 200);
}

$encodedRequesterIdentity = str_replace(["\n", "\r"], '', $requesterIdentity);
putenv('HACKTRADER_SESSION_ID=' . $encodedRequesterIdentity);
session_write_close();
$cmd = __DIR__ . '/run-brk.sh ' . escapeshellarg($period) . ' ' . escapeshellarg($ticker) . ' ' . escapeshellarg($lookback) . ' --json';
$output = shell_exec($cmd);
putenv('HACKTRADER_SESSION_ID');
$decoded = is_string($output) ? json_decode($output, true) : null;

$isValidJson = is_array($decoded);
$hasError = $isValidJson && isset($decoded['error']);

if ($isValidJson && !$hasError) {
    file_put_contents($pipeline, json_encode($decoded, JSON_PRETTY_PRINT));
    remember_focus_symbol($ticker, $focusUniverseFile, $marketWatchlistFile, $correlationGenerator);
    $decoded['cache'] = [
        'hit' => false,
        'stale' => false,
        'age_seconds' => 0,
    ];

    $status = 'Success via ' . ($decoded['source'] ?? 'unknown');
    $logEntry = '[' . date('Y-m-d H:i:s') . "] Requester: $usageActor, Ticker: $ticker, Period: $period, Lookback: $lookback. Status: $status\n";
    file_put_contents('api.log', $logEntry, FILE_APPEND);
    respond_json(with_usage_summary($decoded, $requesterIdentity), 200);
}

$staleCache = read_cached_payload($pipeline);
if ($staleCache !== null) {
    $staleCache['cache'] = [
        'hit' => true,
        'stale' => true,
        'age_seconds' => time() - filemtime($pipeline),
    ];
    $staleCache['warning'] = 'Live fetch failed; serving cached data.';
    if ($hasError) {
        $staleCache['live_error'] = $decoded;
    }

    $status = 'Served stale cache after fetch failure';
    $logEntry = '[' . date('Y-m-d H:i:s') . "] Requester: $usageActor, Ticker: $ticker, Period: $period, Lookback: $lookback. Status: $status\n";
    file_put_contents('api.log', $logEntry, FILE_APPEND);
    respond_json(with_usage_summary($staleCache, $requesterIdentity), 200);
}

$errorPayload = [
    'error' => 'Market data unavailable',
    'ticker' => $ticker,
    'period' => $period,
    'lookback' => (int) $lookback,
    'details' => $decoded ?: ['raw_output' => trim((string) $output)],
];

$status = 'Error: ' . json_encode($errorPayload);
$logEntry = '[' . date('Y-m-d H:i:s') . "] Requester: $usageActor, Ticker: $ticker, Period: $period, Lookback: $lookback. Status: $status\n";
file_put_contents('api.log', $logEntry, FILE_APPEND);
respond_json(with_usage_summary($errorPayload, $requesterIdentity), 503);
