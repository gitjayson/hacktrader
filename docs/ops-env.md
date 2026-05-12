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
