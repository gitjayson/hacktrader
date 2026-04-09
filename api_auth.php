<?php
$dbFile = __DIR__ . '/api-accounts.json';
$usageFile = __DIR__ . '/api-usage.json';

function authenticate_api_key($apiKey) {
    global $dbFile;
    if (!$apiKey) {
        return false;
    }

    if (file_exists($dbFile)) {
        $accounts = json_decode(file_get_contents($dbFile), true);
        if (isset($accounts[$apiKey])) {
            return $accounts[$apiKey];
        }
    }
    return false;
}

function log_api_usage($apiKey, $endpoint, $ticker = '') {
    global $usageFile;
    try {
        $usage = [];
        if (file_exists($usageFile)) {
            $usage = json_decode(file_get_contents($usageFile), true) ?: [];
        }
        $usage[] = [
            'api_key' => $apiKey,
            'endpoint' => $endpoint,
            'ticker' => $ticker,
            'timestamp' => date('c'),
        ];
        if (count($usage) > 1000) {
            $usage = array_slice($usage, -1000);
        }
        file_put_contents($usageFile, json_encode($usage));
    } catch (Exception $e) {
        // silently fail logging
    }
}
