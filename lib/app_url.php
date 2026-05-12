<?php
/**
 * lib/app_url.php — canonical URL builder for HackTrader.
 *
 * Single source of truth for "absolute URL back into this app, for the
 * host the request came from." Used by:
 *   - subscribe.php / billing.php — Stripe success/cancel/return URLs
 *   - callback.php — Google OAuth redirect_uri
 *   - anywhere else that needs to hand an external service a URL that
 *     points back at us.
 *
 * Extracted to its own file in v0.13.4 so callback.php can use it
 * without dragging in lib/subscription.php's DB/plans dependencies.
 *
 * Security model — host header trust boundary:
 *   - HTTP_HOST is attacker-controlled. We compare it to an allowlist
 *     of known deploy hostnames; anything else falls back to the
 *     canonical production domain so a forged Host header can't
 *     redirect users (e.g. after a Stripe checkout, or to an
 *     attacker-controlled OAuth redirect URI).
 *   - HTTP_X_FORWARDED_PROTO is also attacker-controlled and is only
 *     honored when the host was on the whitelist. Otherwise scheme is
 *     forced to https.
 *
 * Always falls back to https://hacktrader.com when no usable host
 * information is available (e.g. CLI execution).
 */

declare(strict_types=1);

// v0.13.5 — Dropped the `if (!function_exists(...))` guard. require_once
// is already idempotent, and the conditional definition made phpstan
// treat the function as "might not exist," failing every caller in
// billing.php / subscribe.php / callback.php. Defined unconditionally
// at the top level lets static analysis follow the contract.
function hacktrader_app_url(string $path = ''): string {
    $allowedHosts = [
        'hacktrader.com',
        'dev.hacktrader.com',
        'www.hacktrader.com',
    ];
    $defaultHost = 'hacktrader.com';

    $rawHost = (string) ($_SERVER['HTTP_HOST'] ?? '');
    // Strip any port suffix before whitelist check.
    $hostNoPort = strtolower((string) preg_replace('/:\d+$/', '', $rawHost));
    $hostAllowed = in_array($hostNoPort, $allowedHosts, true);
    $host = $hostAllowed ? $hostNoPort : $defaultHost;

    // v0.13.6 — scheme is now https by default. HTTP_X_FORWARDED_PROTO is
    // only honored when HACKTRADER_TRUST_FORWARDED_PROTO=1 in the
    // environment, i.e., ops has explicitly said "the proxy in front of
    // this PHP-FPM strips client-supplied X-Forwarded-Proto and sets its
    // own based on what TLS terminated." Without that flag we treat the
    // header as client-controlled and ignore it.
    //
    // Why the v0.13.4 rule (honor it when host is whitelisted) wasn't
    // enough: HTTP_HOST is also client-controlled unless nginx
    // normalizes it via server_name and `proxy_set_header Host $host;`
    // boundaries. A whitelist match doesn't prove the proxy is in front;
    // it just proves the client typed (or forged) a recognized hostname.
    //
    // Operational note: prod nginx terminates TLS and sets
    // X-Forwarded-Proto: https from $scheme, which is the proxy's view,
    // not the client's — so setting the env flag is correct there. If
    // the dev box serves plain http directly without a proxy, leave the
    // env flag unset and Stripe/OAuth URLs will still come out https
    // (which is what those services require anyway).
    $trustForwardedProto = filter_var(
        getenv('HACKTRADER_TRUST_FORWARDED_PROTO') ?: '0',
        FILTER_VALIDATE_BOOLEAN
    );
    if ($trustForwardedProto && !empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $scheme = strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']);
    } else {
        $scheme = 'https';
    }
    if (!in_array($scheme, ['http', 'https'], true)) {
        $scheme = 'https';
    }

    $path = $path === '' ? '' : '/' . ltrim($path, '/');
    return $scheme . '://' . $host . $path;
}
