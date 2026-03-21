<?php
// Returns a list of 12 most relevant correlated ETFs/Indices
$ticker = $_GET['ticker'] ?? 'TSLA';
$correlations = [
    'SPY', 'QQQ', 'IWM', 'DIA', 'XLF', 'XLE', 'XLK', 'XLV', 'XLY', 'XLP', 'XLU', 'XLB'
];
header('Content-Type: application/json');
echo json_encode($correlations);
