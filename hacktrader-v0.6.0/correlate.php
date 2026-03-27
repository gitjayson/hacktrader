<?php
// Returns an array of ticker objects (symbol, relation)
$ticker = strtoupper($_GET['ticker'] ?? 'TSLA');
$correlationsFile = 'correlations.json';

// Global defaults to include for every ticker
$defaults = [['symbol' => 'WTI', 'relation' => 'positive'], ['symbol' => 'UNG', 'relation' => 'positive'], ['symbol' => 'UUP', 'relation' => 'negative']];

if (file_exists($correlationsFile)) {
    $allCorrelations = json_decode(file_get_contents($correlationsFile), true);
    // Get ticker-specific list or fallback to standard ETFs
    $tickerSpecific = $allCorrelations[$ticker] ?? [['symbol' => 'SPY', 'relation' => 'positive']];
} else {
    $tickerSpecific = [['symbol' => 'SPY', 'relation' => 'positive']];
}

// Merge defaults and ticker-specific
$finalList = array_merge($defaults, $tickerSpecific);
// Uniquify by symbol
$unique = [];
foreach($finalList as $item) {
    if (!isset($unique[$item['symbol']])) $unique[$item['symbol']] = $item;
}
$correlations = array_slice(array_values($unique), 0, 12);

header('Content-Type: application/json');
echo json_encode($correlations);
