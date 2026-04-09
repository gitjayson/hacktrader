<?php
session_start();

function slugify_identity(string $value): string {
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '_', $value);
    $value = trim($value, '_');
    return $value ?: 'user';
}

function resolve_persistent_session_user_name(string $email, string $displayName): string {
    $identityPath = __DIR__ . '/state/user-identities.json';
    $identityDir = dirname($identityPath);
    if (!is_dir($identityDir)) {
        mkdir($identityDir, 0755, true);
    }

    $state = [
        'email_to_user_name' => [],
        'updated_at' => null,
    ];

    if (file_exists($identityPath)) {
        $decoded = json_decode(file_get_contents($identityPath), true);
        if (is_array($decoded)) {
            $state['email_to_user_name'] = is_array($decoded['email_to_user_name'] ?? null)
                ? $decoded['email_to_user_name']
                : [];
            $state['updated_at'] = $decoded['updated_at'] ?? null;
        }
    }

    $emailKey = strtolower(trim($email));
    if (isset($state['email_to_user_name'][$emailKey])) {
        return $state['email_to_user_name'][$emailKey];
    }

    $base = slugify_identity($displayName !== '' ? $displayName : explode('@', $emailKey)[0]);
    $candidate = $base;
    $claimed = array_flip($state['email_to_user_name']);
    if (isset($claimed[$candidate])) {
        $candidate = $base . '_' . substr(sha1($emailKey), 0, 6);
    }

    $state['email_to_user_name'][$emailKey] = $candidate;
    $state['updated_at'] = gmdate('c');
    file_put_contents($identityPath, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n", LOCK_EX);

    return $candidate;
}

$autoloadPath = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    http_response_code(500);
    die('Missing vendor autoload at expected path: ' . $autoloadPath);
}
require_once $autoloadPath;

$secretsPath = __DIR__ . '/../../secrets.json';
if (!file_exists($secretsPath)) {
    http_response_code(500);
    die('Missing secrets.json at expected path: ' . $secretsPath);
}

$secrets = json_decode(file_get_contents($secretsPath), true);
if (!is_array($secrets)) {
    http_response_code(500);
    die('Invalid secrets.json payload.');
}

$client = new Google\Client();
$client->setClientId($secrets['GOOGLE_CLIENT_ID']);
$client->setClientSecret($secrets['GOOGLE_CLIENT_SECRET']);

$scheme = (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) ? $_SERVER['HTTP_X_FORWARDED_PROTO'] : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http'));
$host = $_SERVER['HTTP_HOST'] ?? 'hacktrader.com';
$client->setRedirectUri($scheme . '://' . $host . '/callback.php');
$client->addScope('email');
$client->addScope('profile');

if (!isset($_GET['code'])) {
    header('Location: ' . $client->createAuthUrl());
    exit;
} else {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    if (isset($token['error'])) {
        die('Error fetching access token: ' . $token['error']);
    }
    $client->setAccessToken($token['access_token']);
    $oauth2 = new Google\Service\Oauth2($client);
    $userinfo = $oauth2->userinfo->get();

    $displayName = trim((string) ($userinfo->name ?? 'Trader'));
    $email = strtolower(trim((string) ($userinfo->email ?? '')));
    $sessionUserName = resolve_persistent_session_user_name($email, $displayName);

    $_SESSION['user_name'] = $displayName;
    $_SESSION['user_display_name'] = $displayName;
    $_SESSION['user_email'] = $email;
    $_SESSION['session_user_name'] = $sessionUserName;
    $_SESSION['session_identity'] = 'session:' . $sessionUserName;
    $_SESSION['login_time'] = time();

    header('Location: disclaimer.php');
    exit;
}
