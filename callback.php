<?php
session_start();
require_once 'vendor/autoload.php';

$secrets = json_decode(file_get_contents('secrets.json'), true);

$client = new Google\Client();
$client->setClientId($secrets['GOOGLE_CLIENT_ID']);
$client->setClientSecret($secrets['GOOGLE_CLIENT_SECRET']);
$client->setRedirectUri('https://hacktrader.com/callback.php');
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
