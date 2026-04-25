<?php
$dbFile = __DIR__ . '/api-accounts.json';
$usageFile = __DIR__ . '/api-usage.json';

/**
 * Look up an API key in the accounts JSON file.
 * Returns the matching account record (array) or false on miss / bad input.
 *
 * The accounts file path defaults to $dbFile (set above); tests inject an
 * explicit path via the optional second argument.
 */
function authenticate_api_key($apiKey, $accountsPath = null) {
    global $dbFile;
    $path = $accountsPath ?? $dbFile;

    if (!$apiKey) {
        return false;
    }

    if (file_exists($path)) {
        $contents = file_get_contents($path);
        if ($contents === false) {
            return false;
        }
        $accounts = json_decode($contents, true);
        if (is_array($accounts) && isset($accounts[$apiKey])) {
            return $accounts[$apiKey];
        }
    }
    return false;
}

/**
 * Append a usage entry to the usage JSON file. Capped at the most recent
 * 1000 entries. Silently no-ops on any failure.
 *
 * Path defaults to $usageFile; tests inject an explicit path via the optional
 * fourth argument.
 */
function log_api_usage($apiKey, $endpoint, $ticker = '', $usagePath = null) {
    global $usageFile;
    $path = $usagePath ?? $usageFile;

    try {
        $usage = [];
        if (file_exists($path)) {
            $existing = json_decode(file_get_contents($path), true);
            $usage = is_array($existing) ? $existing : [];
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
        file_put_contents($path, json_encode($usage));
    } catch (Exception $e) {
        // silently fail logging
    }
}
