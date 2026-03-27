<?php
header('Content-Type: application/json');

$ticker = strtoupper(trim($_GET['ticker'] ?? 'TSLA'));
if ($ticker === '') {
    $ticker = 'TSLA';
}

$correlationsFile = __DIR__ . '/correlations.json';
$statusFile = __DIR__ . '/correlation-status.json';
$locksDir = __DIR__ . '/correlation-locks';
$focusUniverseFile = __DIR__ . '/focus-universe.json';
$marketWatchlistFile = __DIR__ . '/market-watchlist.json';
$correlationGenerator = __DIR__ . '/generate-correlations.py';
$lockTtlSeconds = 1200;

$defaults = [
    ['symbol' => 'WTI', 'relation' => 'positive'],
    ['symbol' => 'UNG', 'relation' => 'positive'],
    ['symbol' => 'UUP', 'relation' => 'negative'],
    ['symbol' => 'SPY', 'relation' => 'positive'],
    ['symbol' => 'QQQ', 'relation' => 'positive'],
    ['symbol' => 'XLK', 'relation' => 'positive'],
    ['symbol' => 'XLY', 'relation' => 'positive'],
    ['symbol' => 'IWM', 'relation' => 'positive'],
    ['symbol' => 'TLT', 'relation' => 'negative'],
    ['symbol' => 'SMH', 'relation' => 'positive'],
    ['symbol' => 'GLD', 'relation' => 'positive'],
    ['symbol' => 'XOP', 'relation' => 'positive']
];

$fallback = [
    ['symbol' => 'SPY', 'relation' => 'positive'],
    ['symbol' => 'QQQ', 'relation' => 'positive'],
    ['symbol' => 'XLK', 'relation' => 'positive']
];

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

function normalize_symbol($value) {
    $value = strtoupper(trim((string)$value));
    $value = preg_replace('/[^A-Z0-9._-]/', '', $value);
    return substr($value, 0, 16);
}

function utc_now() {
    return gmdate('c');
}

function sanitize_relationships($ticker, $list) {
    $clean = [];
    $seen = [$ticker => true];
    foreach ($list as $item) {
        $symbol = normalize_symbol($item['symbol'] ?? '');
        if ($symbol === '' || isset($seen[$symbol])) {
            continue;
        }
        $relation = strtolower((string)($item['relation'] ?? 'positive')) === 'negative' ? 'negative' : 'positive';
        $clean[] = ['symbol' => $symbol, 'relation' => $relation];
        $seen[$symbol] = true;
        if (count($clean) >= 12) {
            break;
        }
    }
    return $clean;
}

function merge_relationships($ticker, $defaults, $specific) {
    $finalList = array_merge($defaults, $specific);
    return sanitize_relationships($ticker, $finalList);
}

function remember_focus_symbol($ticker, $focusUniverseFile, $marketWatchlistFile) {
    $ticker = normalize_symbol($ticker);
    if ($ticker === '') {
        return;
    }

    $universe = load_json_file($focusUniverseFile, ['symbols' => [], 'seen' => []]);
    $symbols = $universe['symbols'] ?? [];
    $seen = $universe['seen'] ?? [];
    if (!in_array($ticker, $symbols, true)) {
        $symbols[] = $ticker;
        sort($symbols);
    }
    $seen[$ticker] = utc_now();
    $universe['symbols'] = array_values($symbols);
    $universe['seen'] = $seen;
    save_json_file($focusUniverseFile, $universe);

    $watchlist = load_json_file($marketWatchlistFile, []);
    if (!in_array($ticker, $watchlist, true)) {
        $watchlist[] = $ticker;
        sort($watchlist);
        save_json_file($marketWatchlistFile, array_values($watchlist));
    }
}

function load_status_map($statusFile) {
    return load_json_file($statusFile, []);
}

function save_status_map($statusFile, $statusMap) {
    ksort($statusMap);
    save_json_file($statusFile, $statusMap);
}

function get_lock_path($locksDir, $ticker) {
    return rtrim($locksDir, '/') . '/' . $ticker . '.lock';
}

function lock_is_active($lockPath, $ttlSeconds) {
    return file_exists($lockPath) && (time() - filemtime($lockPath) < $ttlSeconds);
}

function clear_stale_lock($lockPath, $ttlSeconds) {
    if (file_exists($lockPath) && (time() - filemtime($lockPath) >= $ttlSeconds)) {
        @unlink($lockPath);
    }
}

function set_status($statusFile, $ticker, $fields) {
    $statusMap = load_status_map($statusFile);
    $current = $statusMap[$ticker] ?? [];
    foreach ($fields as $key => $value) {
        $current[$key] = $value;
    }
    $statusMap[$ticker] = $current;
    save_status_map($statusFile, $statusMap);
    return $current;
}

function queue_symbol_research($ticker, $locksDir, $statusFile, $correlationGenerator, $lockTtlSeconds) {
    if (!file_exists($correlationGenerator)) {
        return ['queued' => false, 'reason' => 'missing-generator'];
    }

    if (!is_dir($locksDir)) {
        @mkdir($locksDir, 0755, true);
    }

    $lockPath = get_lock_path($locksDir, $ticker);
    clear_stale_lock($lockPath, $lockTtlSeconds);

    if (lock_is_active($lockPath, $lockTtlSeconds)) {
        $status = set_status($statusFile, $ticker, [
            'status' => 'pending',
            'updated_at' => utc_now(),
            'source' => 'correlation-research'
        ]);
        return ['queued' => false, 'reason' => 'already-pending', 'status' => $status];
    }

    set_status($statusFile, $ticker, [
        'status' => 'pending',
        'requested_at' => utc_now(),
        'updated_at' => utc_now(),
        'source' => 'correlation-research'
    ]);

    $cmd = 'python3 ' . escapeshellarg($correlationGenerator) . ' ' . escapeshellarg($ticker) . ' > /dev/null 2>&1 &';
    @exec($cmd);

    $status = load_status_map($statusFile)[$ticker] ?? ['status' => 'pending'];
    return ['queued' => true, 'reason' => 'started', 'status' => $status];
}

remember_focus_symbol($ticker, $focusUniverseFile, $marketWatchlistFile);

$allCorrelations = load_json_file($correlationsFile, []);
$tickerSpecific = sanitize_relationships($ticker, $allCorrelations[$ticker] ?? []);
$hasReadyCorrelations = !empty($tickerSpecific);
$statusMap = load_status_map($statusFile);
$status = $statusMap[$ticker] ?? null;
$research = ['queued' => false, 'reason' => null];

if (!$hasReadyCorrelations) {
    $research = queue_symbol_research($ticker, $locksDir, $statusFile, $correlationGenerator, $lockTtlSeconds);
    $statusMap = load_status_map($statusFile);
    $status = $statusMap[$ticker] ?? ($research['status'] ?? ['status' => 'pending']);
    $tickerSpecific = $fallback;
}

$correlations = merge_relationships($ticker, $defaults, $tickerSpecific);

echo json_encode([
    'ticker' => $ticker,
    'indicators' => $correlations,
    'status' => $status ?: ['status' => ($hasReadyCorrelations ? 'ready' : 'pending')],
    'used_fallback' => !$hasReadyCorrelations,
    'research' => $research
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
