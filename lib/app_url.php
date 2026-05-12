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

if (!function_exists('hacktrader_app_url')) {
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

        // Rules:
        //   - Host not on whitelist → force https on the default host.
        //     (We don't know the deploy environment, so don't honor any
        //     forwarded-proto hint from an unrecognized caller.)
        //   - Host on whitelist + HTTP_X_FORWARDED_PROTO set → honor it
        //     (the dev box does serve plain http on the host, the
        //     production proxy terminates TLS and forwards "https").
        //   - Host on whitelist + no forwarded header → fall back to
        //     SERVER['HTTPS'] direct check, defaulting to https.
        if (!$hostAllowed) {
            $scheme = 'https';
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            $scheme = strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']);
        } else {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                ? 'https'
                : 'https'; // Default-deny http on the whitelisted hosts.
        }
        if (!in_array($scheme, ['http', 'https'], true)) {
            $scheme = 'https';
        }

        $path = $path === '' ? '' : '/' . ltrim($path, '/');
        return $scheme . '://' . $host . $path;
    }
}
