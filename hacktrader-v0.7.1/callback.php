<?php
session_start();

$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    http_response_code(500);
    die('Missing vendor autoload at expected path: ' . $autoloadPath);
}
require_once $autoloadPath;

$secretsPath = dirname(__DIR__) . '/secrets.json';
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
    
    $_SESSION['user_name'] = $userinfo->name;
    $_SESSION['user_email'] = $userinfo->email;
    $_SESSION['login_time'] = time();
    
    header('Location: disclaimer.php');
    exit;
}
