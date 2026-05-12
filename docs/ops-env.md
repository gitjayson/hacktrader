# Ops environment variables

Single page documenting every environment variable that changes HackTrader's
runtime behavior in production. Set these in the PHP-FPM systemd unit (or
`/etc/php/*/fpm/pool.d/*.conf` with `env[NAME] = value`) — `getenv()` will
read them on each request.

## `HACKTRADER_QUOTA_HARD_GATE`

Controls whether the subscription-quota gate in `api.php` *enforces* limits or
just counts and logs them.

- **Unset / `0` / `false` → soft mode (default).** Over-quota requests are
  logged via `error_log()` but still receive data. This is the soft-launch
  posture; safe to leave on while Stripe billing flows are still being
  validated end-to-end.
- **`1` / `true` → hard mode.** Over-quota requests get HTTP 402 with a
  `quota_exceeded` payload pointing at the pricing section. Use this once
  Stripe is fully live and at least one paid trial has been validated end
  to end.

The active mode is surfaced at `/healthz.php` under `config.quota_mode`
(`"soft"` or `"hard"`) so you can check at a glance which side a box is on.

**Dev-box override during E2E tests:** if hard-gate is on in prod and you
want to run quota-related QA without hitting the wall, leave the env var
unset on dev — both boxes deploy the same code, but the FPM env decides
which mode each runs.

History: introduced in v0.13.4 after a security review flagged that
`$hardGate` was hard-coded `false`, which left Free/expired users
effectively unmetered now that Starter is live as a paid tier.

## `HACKTRADER_TRUST_FORWARDED_PROTO`

Controls whether `lib/app_url.php` honors `HTTP_X_FORWARDED_PROTO` when
building absolute URLs back into the app (Stripe success/cancel URLs,
Google OAuth `redirect_uri`, etc.).

- **Unset / `0` / `false` → forwarded-proto ignored (default).** Scheme
  is always `https`. Correct when nginx terminates TLS in front of
  PHP-FPM but ops hasn't yet committed to a trust contract on the
  proxy headers, or when there's no proxy at all and we want callbacks
  to point at https even if the local request was http.
- **`1` / `true` → honor `HTTP_X_FORWARDED_PROTO`.** Set this once
  you've verified the nginx config strips/overrides any client-supplied
  `X-Forwarded-Proto` and sets its own based on what TLS the proxy
  actually terminated. The verifying nginx directive is roughly
  `proxy_set_header X-Forwarded-Proto $scheme;` — meaning nginx sets
  the header from its own `$scheme` view, not from anything the client
  sent.

The active state is surfaced at `/healthz.php` under
`config.trust_forwarded_proto` so ops can verify which side a box is on.

History: tightened in v0.13.6 after a security reviewer pointed out that
trusting the forwarded-proto header on a whitelisted host wasn't enough
— `Host:` is also client-controlled unless nginx normalizes it, so a
whitelist match doesn't prove the request actually came through the
trusted proxy. The trust contract is now explicit and env-driven.
