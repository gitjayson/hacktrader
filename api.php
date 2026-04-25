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

function update_health_status($path, $ticker, $period, $liveStatus, $provider = null, $errorSummary = null, $cacheAgeSeconds = null) {
    $state = load_json_file($path, [
        'meta' => ['updated_at' => null, 'version' => 'v0.7.7'],
        'counters' => [
            'total_requests' => 0,
            'live_successes' => 0,
            'cache_hits' => 0,
            'stale_serves' => 0,
            'errors' => 0,
            'consecutive_failures' => 0,
            'consecutive_stale_serves' => 0,
        ],
        'last' => [],
        'recent_events' => [],
    ]);

    $timestamp = gmdate('c');
    $liveStatus = $liveStatus ?: 'unknown';
    $provider = $provider ?: 'unknown';
    $cacheAgeSeconds = is_numeric($cacheAgeSeconds) ? (int) $cacheAgeSeconds : null;

    $state['meta'] = is_array($state['meta'] ?? null) ? $state['meta'] : [];
    $state['counters'] = is_array($state['counters'] ?? null) ? $state['counters'] : [];
    $state['last'] = is_array($state['last'] ?? null) ? $state['last'] : [];
    $state['recent_events'] = is_array($state['recent_events'] ?? null) ? $state['recent_events'] : [];

    $counters = array_merge([
        'total_requests' => 0,
        'live_successes' => 0,
        'cache_hits' => 0,
        'stale_serves' => 0,
        'errors' => 0,
        'consecutive_failures' => 0,
        'consecutive_stale_serves' => 0,
    ], $state['counters']);

    $counters['total_requests']++;
    if ($liveStatus === 'live') {
        $counters['live_successes']++;
        $counters['consecutive_failures'] = 0;
        $counters['consecutive_stale_serves'] = 0;
    } elseif ($liveStatus === 'cache_hit') {
        $counters['cache_hits']++;
        $counters['consecutive_failures'] = 0;
        $counters['consecutive_stale_serves'] = 0;
    } elseif ($liveStatus === 'stale_fallback') {
        $counters['stale_serves']++;
        $counters['consecutive_failures']++;
        $counters['consecutive_stale_serves']++;
    } elseif ($liveStatus === 'error') {
        $counters['errors']++;
        $counters['consecutive_failures']++;
        $counters['consecutive_stale_serves'] = 0;
    }

    $state['counters'] = $counters;
    $state['last'] = [
        'request_at' => $timestamp,
        'ticker' => $ticker,
        'period' => $period,
        'provider' => $provider,
        'live_status' => $liveStatus,
        'error_summary' => $errorSummary,
        'cache_age_seconds' => $cacheAgeSeconds,
        'last_success_at' => in_array($liveStatus, ['live', 'cache_hit'], true) ? $timestamp : ($state['last']['last_success_at'] ?? null),
        'last_failure_at' => in_array($liveStatus, ['stale_fallback', 'error'], true) ? $timestamp : ($state['last']['last_failure_at'] ?? null),
    ];

    $state['recent_events'][] = [
        'timestamp' => $timestamp,
        'ticker' => $ticker,
        'period' => $period,
        'provider' => $provider,
        'live_status' => $liveStatus,
        'error_summary' => $errorSummary,
        'cache_age_seconds' => $cacheAgeSeconds,
    ];
    $state['recent_events'] = array_slice($state['recent_events'], -100);
    $state['meta']['updated_at'] = $timestamp;
    $state['meta']['version'] = 'v0.7.7';

    save_json_file($path, $state);
}

function summarize_live_error($details): ?string {
    if (is_array($details)) {
        foreach (['message', 'error', 'warning', 'raw_output'] as $key) {
            if (!empty($details[$key]) && is_scalar($details[$key])) {
                return substr(trim((string) $details[$key]), 0, 240);
            }
        }
        foreach ($details as $value) {
            $summary = summarize_live_error($value);
            if ($summary) {
                return $summary;
            }
        }
        return null;
    }
    if (is_scalar($details) && trim((string) $details) !== '') {
        return substr(trim((string) $details), 0, 240);
    }
    return null;
}

function record_usage_event($trackerPath, $sessionId, $provider, $ticker, $interval, $periods, $outcome, $cacheState = null) {
    $tracker = load_json_file($trackerPath, [
        'meta' => ['updated_at' => null, 'version' => 'v0.7.7'],
        'sessions' => [],
        'recent_events' => [],
    ]);

    $tracker['meta'] = is_array($tracker['meta'] ?? null) ? $tracker['meta'] : [];
    $tracker['sessions'] = is_array($tracker['sessions'] ?? null) ? $tracker['sessions'] : [];
    $tracker['recent_events'] = is_array($tracker['recent_events'] ?? null) ? $tracker['recent_events'] : [];

    $timestamp = gmdate('c');
    $sessionId = sanitize_session_label($sessionId ?: 'session:anonymous');
    $provider = $provider ?: 'unknown';
    $cacheState = $cacheState ?: 'live';
    $periods = (int) $periods;

    $sessionEntry = $tracker['sessions'][$sessionId] ?? [
        'request_count' => 0,
        'stale_serves' => 0,
        'providers' => [],
        'last_request_at' => null,
        'last_success_at' => null,
        'last_failure_at' => null,
        'last_ticker' => null,
        'last_interval' => null,
        'last_periods' => null,
        'last_outcome' => null,
        'last_provider' => null,
        'last_cache_state' => null,
    ];

    $sessionEntry['request_count'] = (int) ($sessionEntry['request_count'] ?? ($sessionEntry['total_attempts'] ?? 0)) + 1;
    if ($cacheState === 'stale') {
        $sessionEntry['stale_serves'] = (int) ($sessionEntry['stale_serves'] ?? 0) + 1;
    }

    $providers = is_array($sessionEntry['providers'] ?? null) ? $sessionEntry['providers'] : [];
    $providerEntry = $providers[$provider] ?? [
        'attempts' => 0,
        'successes' => 0,
        'errors' => 0,
        'stale_serves' => 0,
    ];
    $providerEntry['attempts'] = (int) ($providerEntry['attempts'] ?? 0) + 1;
    if ($outcome === 'success' || $outcome === 'stale_fallback') {
        $providerEntry['successes'] = (int) ($providerEntry['successes'] ?? 0) + 1;
    } else {
        $providerEntry['errors'] = (int) ($providerEntry['errors'] ?? 0) + 1;
    }
    if ($cacheState === 'stale') {
        $providerEntry['stale_serves'] = (int) ($providerEntry['stale_serves'] ?? 0) + 1;
    }
    $providers[$provider] = $providerEntry;
    $sessionEntry['providers'] = $providers;

    $sessionEntry['last_request_at'] = $timestamp;
    $sessionEntry['last_ticker'] = $ticker;
    $sessionEntry['last_interval'] = $interval;
    $sessionEntry['last_periods'] = $periods;
    $sessionEntry['last_outcome'] = $outcome;
    $sessionEntry['last_provider'] = $provider;
    $sessionEntry['last_cache_state'] = $cacheState;
    if ($outcome === 'success' || $outcome === 'stale_fallback') {
        $sessionEntry['last_success_at'] = $timestamp;
    } else {
        $sessionEntry['last_failure_at'] = $timestamp;
    }

    $tracker['sessions'][$sessionId] = $sessionEntry;
    $tracker['recent_events'][] = [
        'timestamp' => $timestamp,
        'session_id' => $sessionId,
        'provider' => $provider,
        'ticker' => $ticker,
        'interval' => $interval,
        'periods' => $periods,
        'outcome' => $outcome,
        'cache_state' => $cacheState,
    ];
    $tracker['recent_events'] = array_slice($tracker['recent_events'], -200);
    $tracker['meta']['updated_at'] = $timestamp;
    $tracker['meta']['version'] = 'v0.7.7';

    save_json_file($trackerPath, $tracker);
}

function build_usage_summary($trackerPath, $sessionId) {
    $tracker = load_json_file($trackerPath, [
        'meta' => [],
        'sessions' => [],
        'recent_events' => [],
    ]);

    $sessionEntry = $tracker['sessions'][$sessionId] ?? [];
    $providers = is_array($sessionEntry['providers'] ?? null) ? $sessionEntry['providers'] : [];
    $attempts = (int) ($sessionEntry['request_count'] ?? ($sessionEntry['total_attempts'] ?? 0));
    $successes = 0;
    $errors = 0;
    $staleServes = 0;
    foreach ($providers as $providerEntry) {
        if (!is_array($providerEntry)) {
            continue;
        }
        $successes += (int) ($providerEntry['successes'] ?? 0);
        $errors += (int) ($providerEntry['errors'] ?? 0);
        $staleServes += (int) ($providerEntry['stale_serves'] ?? 0);
    }

    return [
        'session_id' => $sessionId,
        'provider' => $sessionEntry['last_provider'] ?? (array_key_first($providers) ?: 'unknown'),
        'attempts' => $attempts,
        'successes' => $successes,
        'errors' => $errors,
        'stale_serves' => $staleServes,
        'success_rate' => $attempts > 0 ? round(($successes / $attempts) * 100, 1) : null,
        'last_request_at' => $sessionEntry['last_request_at'] ?? null,
        'last_success_at' => $sessionEntry['last_success_at'] ?? null,
        'last_failure_at' => $sessionEntry['last_failure_at'] ?? null,
        'last_ticker' => $sessionEntry['last_ticker'] ?? null,
        'last_interval' => $sessionEntry['last_interval'] ?? null,
        'last_periods' => $sessionEntry['last_periods'] ?? null,
        'last_outcome' => $sessionEntry['last_outcome'] ?? null,
        'last_cache_state' => $sessionEntry['last_cache_state'] ?? null,
        'providers' => $providers,
        'updated_at' => $tracker['meta']['updated_at'] ?? null,
    ];
}

function normalize_period_for_backend(string $period): array {
    $map = [
        '1m' => ['interval' => '1min', 'display' => '1-min'],
        '5m' => ['interval' => '5min', 'display' => '5-min'],
        '1h' => ['interval' => '1h', 'display' => '1-hour'],
        '1d' => ['interval' => '1day', 'display' => '1-day'],
    ];
    return $map[$period] ?? $map['5m'];
}

function with_usage_summary($payload, $sessionId) {
    if (!is_array($payload)) {
        return $payload;
    }
    $payload['usage'] = build_usage_summary(__DIR__ . '/api_usage_tracker.json', $sessionId);
    return $payload;
}

function should_skip_correlation_spawn($ticker) {
    // Three reasons to skip spawning generate-correlations.py for $ticker:
    //   1. A non-stale lock exists in correlation-locks/$ticker.lock — another
    //      worker is currently generating this symbol's correlations.
    //   2. correlation-status.json says this symbol is "pending" — same idea,
    //      a generation is in flight.
    //   3. correlation-status.json says this symbol is "ready" and its
    //      updated_at is within CORRELATION_REFRESH_MIN_SECONDS — recent enough
    //      that we shouldn't refresh on every dashboard scan.
    $sanitized = preg_replace('/[^A-Z0-9_-]/', '_', strtoupper($ticker));
    if ($sanitized === '') {
        return false;
    }

    $lockPath = __DIR__ . '/correlation-locks/' . $sanitized . '.lock';
    $lockStaleSeconds = 20 * 60;  // matches LOCK_STALE_SECONDS in generate-correlations.py
    if (file_exists($lockPath)) {
        $lockAge = time() - filemtime($lockPath);
        if ($lockAge < $lockStaleSeconds) {
            return true;
        }
    }

    $statusPath = __DIR__ . '/correlation-status.json';
    if (file_exists($statusPath)) {
        $status = json_decode(file_get_contents($statusPath), true);
        if (is_array($status) && isset($status[$ticker]) && is_array($status[$ticker])) {
            $entry = $status[$ticker];
            $entryStatus = $entry['status'] ?? null;
            if ($entryStatus === 'pending') {
                return true;
            }
            if ($entryStatus === 'ready') {
                $updatedAt = strtotime($entry['updated_at'] ?? '');
                $refreshMinSeconds = 60 * 60;  // don't refresh more than once an hour
                if ($updatedAt && (time() - $updatedAt) < $refreshMinSeconds) {
                    return true;
                }
            }
        }
    }

    return false;
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

    if (file_exists($correlationGenerator) && !should_skip_correlation_spawn($ticker)) {
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
    $freshCache['live_status'] = 'cache_hit';
    $freshCache['live_error_summary'] = null;
    update_health_status(__DIR__ . '/state/health-status.json', $ticker, $period, 'cache_hit', $freshCache['source'] ?? 'cache', null, $freshCache['cache']['age_seconds'] ?? null);
    record_usage_event(__DIR__ . '/api_usage_tracker.json', $requesterIdentity, 'cache', $ticker, $period, $lookback, 'success', 'cache');
    respond_json(with_usage_summary($freshCache, $requesterIdentity), 200);
}

$encodedRequesterIdentity = str_replace(["\n", "\r"], '', $requesterIdentity);
putenv('HACKTRADER_SESSION_ID=' . $encodedRequesterIdentity);
session_write_close();
$normalizedPeriod = normalize_period_for_backend($period);
$normalizedInterval = $normalizedPeriod['interval'];
$displayPeriod = $normalizedPeriod['display'];
$cmd = __DIR__ . '/run-brk.sh '
    . escapeshellarg($ticker) . ' '
    . escapeshellarg($normalizedInterval) . ' '
    . escapeshellarg($displayPeriod) . ' '
    . escapeshellarg((string) ((int) $lookback)) . ' '
    . escapeshellarg('true') . ' '
    . escapeshellarg($encodedRequesterIdentity);
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
    $decoded['live_status'] = 'live';
    $decoded['live_error_summary'] = null;

    update_health_status(__DIR__ . '/state/health-status.json', $ticker, $period, 'live', $decoded['source'] ?? 'unknown', null, 0);
    record_usage_event(__DIR__ . '/api_usage_tracker.json', $requesterIdentity, $decoded['source'] ?? 'unknown', $ticker, $period, $lookback, 'success', 'live');
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
    $staleCache['live_status'] = 'stale_fallback';
    $staleCache['live_error_summary'] = summarize_live_error($decoded ?: null) ?? 'Live fetch failed; serving cached data.';

    update_health_status(__DIR__ . '/state/health-status.json', $ticker, $period, 'stale_fallback', $staleCache['source'] ?? 'cache', $staleCache['live_error_summary'], $staleCache['cache']['age_seconds'] ?? null);
    record_usage_event(__DIR__ . '/api_usage_tracker.json', $requesterIdentity, $staleCache['source'] ?? 'cache', $ticker, $period, $lookback, 'stale_fallback', 'stale');
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
    'live_status' => 'error',
    'live_error_summary' => summarize_live_error($decoded ?: ['raw_output' => trim((string) $output)]) ?? 'Market data unavailable',
];

update_health_status(__DIR__ . '/state/health-status.json', $ticker, $period, 'error', 'unavailable', $errorPayload['live_error_summary'] ?? null, null);
record_usage_event(__DIR__ . '/api_usage_tracker.json', $requesterIdentity, 'unavailable', $ticker, $period, $lookback, 'error', 'live');
$status = 'Error: ' . json_encode($errorPayload);
$logEntry = '[' . date('Y-m-d H:i:s') . "] Requester: $usageActor, Ticker: $ticker, Period: $period, Lookback: $lookback. Status: $status\n";
file_put_contents('api.log', $logEntry, FILE_APPEND);
respond_json(with_usage_summary($errorPayload, $requesterIdentity), 503);