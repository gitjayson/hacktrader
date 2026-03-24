<?php
$ticker = strtoupper($_GET['ticker'] ?? 'TSLA');
$period = $_GET['period'] ?? '5m';
$lookback = $_GET['lookback'] ?? '100';

$pipelineDir = 'pipelines';
if (!is_dir($pipelineDir)) mkdir($pipelineDir, 0755);
$pipeline = "$pipelineDir/$ticker.json";

// Always update if pipeline is older than 30s, otherwise serve cached
if (file_exists($pipeline) && (time() - filemtime($pipeline) < 30)) {
    header('Content-Type: application/json');
    echo file_get_contents($pipeline);
    exit;
}

// Create/Update pipeline
$cmd = "/home/hacktrader/sub/hacktrader.com/run-brk.sh $period $lookback $ticker --json";
$output = shell_exec($cmd);

$isError = (strpos($output, '"error"') !== false || strpos($output, 'Error:') !== false);
$status = $isError ? "Error: " . trim($output) : "Success";

if (!$isError && $output) {
    file_put_contents($pipeline, $output);
}

$logEntry = "[" . date('Y-m-d H:i:s') . "] Source: yfinance, Ticker: $ticker, Period: $period, Lookback: $lookback. Status: $status\n";
file_put_contents('api.log', $logEntry, FILE_APPEND);

header('Content-Type: application/json');
echo $output;
