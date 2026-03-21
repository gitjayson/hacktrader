<?php
$ticker = $_GET['ticker'] ?? 'TSLA';
$period = $_GET['period'] ?? '5m';
$lookback = $_GET['lookback'] ?? '100';
$tolerance = $_GET['tolerance'] ?? '90';

// Command: run-brk.sh <period> <lookback> <ticker> --json
$cmd = "./run-brk.sh $period $lookback $ticker --json";
$output = shell_exec($cmd);

header('Content-Type: application/json');
echo $output;
