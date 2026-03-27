<?php
// Returns an array of ticker objects (symbol, relation)
$ticker = strtoupper($_GET['ticker'] ?? 'TSLA');
$correlationsFile = __DIR__ . '/correlations.json';
$focusUniverseFile = __DIR__ . '/focus-universe.json';
$marketWatchlistFile = __DIR__ . '/market-watchlist.json';
$correlationGenerator = __DIR__ . '/generate-correlations.py';

// Global defaults to include for every ticker
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

function queue_symbol($ticker, $focusUniverseFile, $marketWatchlistFile, $correlationGenerator) {
    $ticker = strtoupper(trim((string)$ticker));
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
    $seen[$ticker] = gmdate('c');
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

$allCorrelations = load_json_file($correlationsFile, []);
$tickerSpecific = $allCorrelations[$ticker] ?? [];
if (empty($tickerSpecific)) {
    queue_symbol($ticker, $focusUniverseFile, $marketWatchlistFile, $correlationGenerator);
    $tickerSpecific = [
        ['symbol' => 'SPY', 'relation' => 'positive'],
        ['symbol' => 'QQQ', 'relation' => 'positive'],
        ['symbol' => 'XLK', 'relation' => 'positive']
    ];
}

// Merge defaults and ticker-specific
$finalList = array_merge($defaults, $tickerSpecific);
// Uniquify by symbol
$unique = [];
foreach ($finalList as $item) {
    $symbol = strtoupper($item['symbol'] ?? '');
    if ($symbol === '') {
        continue;
    }
    if (!isset($unique[$symbol])) {
        $unique[$symbol] = ['symbol' => $symbol, 'relation' => $item['relation'] ?? 'positive'];
    }
}
$correlations = array_slice(array_values($unique), 0, 12);

header('Content-Type: application/json');
echo json_encode($correlations);
