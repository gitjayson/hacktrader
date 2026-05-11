<?php
session_start();

function generate_oauth_state(): string {
    return bin2hex(random_bytes(16));
}

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

$autoloadCandidates = [
    __DIR__ . '/vendor/autoload.php',
    dirname(__DIR__) . '/vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
];
$autoloadPath = null;
foreach ($autoloadCandidates as $candidate) {
    if (file_exists($candidate)) {
        $autoloadPath = $candidate;
        break;
    }
}
if ($autoloadPath === null) {
    http_response_code(500);
    die('Missing vendor autoload in expected locations.');
}
require_once $autoloadPath;

$secretsCandidates = [
    __DIR__ . '/secrets.json',
    dirname(__DIR__) . '/secrets.json',
    __DIR__ . '/../../secrets.json',
];
$secretsPath = null;
foreach ($secretsCandidates as $candidate) {
    if (file_exists($candidate)) {
        $secretsPath = $candidate;
        break;
    }
}
if ($secretsPath === null) {
    http_response_code(500);
    die('Missing secrets.json in expected locations.');
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
    $oauthState = generate_oauth_state();
    $_SESSION['oauth_state'] = $oauthState;
    $client->setState($oauthState);
    header('Location: ' . $client->createAuthUrl());
    exit;
} else {
    $returnedState = (string) ($_GET['state'] ?? '');
    $expectedState = (string) ($_SESSION['oauth_state'] ?? '');
    if ($expectedState === '' || $returnedState === '' || !hash_equals($expectedState, $returnedState)) {
        unset($_SESSION['oauth_state']);
        http_response_code(400);
        die('Invalid OAuth state.');
    }
    unset($_SESSION['oauth_state']);

    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    if (isset($token['error'])) {
        die('Error fetching access token: ' . $token['error']);
    }
    $client->setAccessToken($token['access_token']);
    $oauth2 = new Google\Service\Oauth2($client);
    $userinfo = $oauth2->userinfo->get();

    $displayName = trim((string) ($userinfo->name ?? 'Trader'));
    $email = strtolower(trim((string) ($userinfo->email ?? '')));
    $googleSub = (string) ($userinfo->id ?? $userinfo->sub ?? '');
    $sessionUserName = resolve_persistent_session_user_name($email, $displayName);

    // v0.9.0: Upsert into the subscription DB so a user row exists before
    // any Stripe webhook references this email. The first call seeds a
    // 7-day Plus trial; subsequent logins just refresh the row. The whole
    // require + call is try/wrapped so any DB / extension failure cannot
    // block login.
    // v0.13.0 — verbose diagnostic logging. Once we confirm the upsert is
    // healthy in production this can drop back to error-only.
    error_log("callback.php upsert attempt: email='$email' googleSub='$googleSub' name='$displayName' libExists=" . (file_exists(__DIR__ . '/lib/subscription.php') ? '1' : '0'));
    try {
        $libPath = __DIR__ . '/lib/subscription.php';
        if (file_exists($libPath) && $email !== '' && $googleSub !== '') {
            require_once $libPath;
            $upsertResult = upsert_user_from_oauth($email, $googleSub, $displayName);
            error_log('callback.php upsert succeeded for ' . $email . ' user_id=' . ($upsertResult['id'] ?? '?'));
        } else {
            error_log("callback.php upsert SKIPPED: libExists=" . (file_exists($libPath) ? '1' : '0') . " email_empty=" . ($email === '' ? '1' : '0') . " sub_empty=" . ($googleSub === '' ? '1' : '0'));
        }
    } catch (Throwable $e) {
        error_log('callback.php upsert FAILED: ' . get_class($e) . ': ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
    }

    session_regenerate_id(true);
    $_SESSION['oauth_authenticated_at'] = time();
    $_SESSION['user_name'] = $displayName;
    $_SESSION['user_display_name'] = $displayName;
    $_SESSION['user_email'] = $email;
    $_SESSION['google_sub'] = $googleSub;
    $_SESSION['session_user_name'] = $sessionUserName;
    $_SESSION['session_identity'] = 'session:' . $sessionUserName;
    $_SESSION['login_time'] = time();

    // v0.13.0 — honor post_login_redirect for flows that bounced through
    // OAuth from a destination page (e.g., subscribe.php?plan=starter
    // clicked by an anonymous visitor from the landing page). Validate
    // that the path is local (starts with /) to prevent open-redirect.
    $postLogin = $_SESSION['post_login_redirect'] ?? null;
    unset($_SESSION['post_login_redirect']);
    if ($postLogin && is_string($postLogin) && strncmp($postLogin, '/', 1) === 0) {
        header('Location: ' . $postLogin);
        exit;
    }

    header('Location: disclaimer.php');
    exit;
}