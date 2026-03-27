<?php
$ticker = strtoupper($_GET['ticker'] ?? 'TSLA');
$period = $_GET['period'] ?? '5m';
$lookback = $_GET['lookback'] ?? '100';

$pipelineDir = 'pipelines';
if (!is_dir($pipelineDir)) {
    mkdir($pipelineDir, 0755, true);
}
$cacheKey = preg_replace('/[^A-Z0-9_-]/', '_', strtoupper($ticker) . '_' . strtolower($period) . '_' . (int)$lookback);
$pipeline = "$pipelineDir/$cacheKey.json";
$maxCacheAgeSeconds = 30;

function respond_json($payload, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_PRETTY_PRINT);
    exit;
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

$freshCache = read_cached_payload($pipeline, $maxCacheAgeSeconds);
if ($freshCache !== null) {
    $freshCache['cache'] = [
        'hit' => true,
        'stale' => false,
        'age_seconds' => time() - filemtime($pipeline)
    ];
    respond_json($freshCache, 200);
}

$cmd = __DIR__ . "/run-brk.sh " . escapeshellarg($period) . " " . escapeshellarg($ticker) . " " . escapeshellarg($lookback) . " --json";
$output = shell_exec($cmd);
$decoded = is_string($output) ? json_decode($output, true) : null;

$isValidJson = is_array($decoded);
$hasError = $isValidJson && isset($decoded['error']);

if ($isValidJson && !$hasError) {
    file_put_contents($pipeline, json_encode($decoded, JSON_PRETTY_PRINT));
    $decoded['cache'] = [
        'hit' => false,
        'stale' => false,
        'age_seconds' => 0
    ];

    $status = "Success via " . ($decoded['source'] ?? 'unknown');
    $logEntry = "[" . date('Y-m-d H:i:s') . "] Ticker: $ticker, Period: $period, Lookback: $lookback. Status: $status\n";
    file_put_contents('api.log', $logEntry, FILE_APPEND);
    respond_json($decoded, 200);
}

$staleCache = read_cached_payload($pipeline);
if ($staleCache !== null) {
    $staleCache['cache'] = [
        'hit' => true,
        'stale' => true,
        'age_seconds' => time() - filemtime($pipeline)
    ];
    $staleCache['warning'] = 'Live fetch failed; serving cached data.';
    if ($hasError) {
        $staleCache['live_error'] = $decoded;
    }

    $status = "Served stale cache after fetch failure";
    $logEntry = "[" . date('Y-m-d H:i:s') . "] Ticker: $ticker, Period: $period, Lookback: $lookback. Status: $status\n";
    file_put_contents('api.log', $logEntry, FILE_APPEND);
    respond_json($staleCache, 200);
}

$errorPayload = [
    'error' => 'Market data unavailable',
    'ticker' => $ticker,
    'period' => $period,
    'lookback' => (int)$lookback,
    'details' => $decoded ?: ['raw_output' => trim((string)$output)]
];

$status = "Error: " . json_encode($errorPayload);
$logEntry = "[" . date('Y-m-d H:i:s') . "] Ticker: $ticker, Period: $period, Lookback: $lookback. Status: $status\n";
file_put_contents('api.log', $logEntry, FILE_APPEND);
respond_json($errorPayload, 503);
