# HackTrader Dashboard

- **Version:** v0.14.0
- **Status:** Active
- **Codebase:** HackTrader FUI dashboard

HackTrader is a market structure visualization tool. It surfaces correlation geometry, support/resistance ladders, channel bands, and volume context for a focus ticker and its peers — a way to *see* the chart faster, not a forecast or signal service.

## Highlights in v0.14.0

- **Channel structure chart (Phase 1).** New centerpiece visualization below the correlation radar. The last 15 minutes of focus-ticker price renders inside three vertically stacked horizontal bands: the upper breakout channel at top, the current channel in the middle (with the price line wobbling inside it), the lower breakout channel at bottom. The bands themselves come from `run-brk.py::build_channels()` — they're the actual S/R-defined channels the dashboard already computes for the levels ladder and microchart, now promoted to first-class visual objects. The 15-minute window matches Massive's 15-min delay envelope, so the chart shows exactly the data a trader can act on. Current price renders as a cyan dot at the right edge (T=0); if price is currently outside the current channel, the dot floats into the upper or lower band as a visual cue that a breakout is in progress. Same brand register as the rest of the product — describes the channel structure price is operating inside, not a prediction of where it goes next.
- **Disclaimer page scrolling regression fixed (v0.13.9 hotfix).** `disclaimer.php` had a stray `overflow: hidden` on `body` that locked the page to the viewport height. Combined with `display: grid; place-items: center` it broke scrolling on shorter windows and the mobile viewport. Removed; other glass pages already use the same `body { padding: 24px; }` pattern without overflow:hidden so disclaimer.php is now consistent.

### Coming in v0.14.1+

- **Phase 2 — channel shunt animation.** When price closes outside the current channel for K consecutive bars (default K=2), the whole chart animate-translates up or down so the previous breakout channel becomes the new "current," a new breakout channel appears beyond it, and the previous current becomes the new opposite-side breakout. Always re-centers on the channel price is actually operating in.
- **Phase 3 — infinite-stack hint.** Faint hint of additional channels above and below the visible three, so the viewer reads the chart as a slice of a continuous vertical stack rather than a closed box.

## Highlights in v0.13.8

- **Singleflight final-cycle stampede closed: stale-fallback on contention.** v0.13.7 added the retry-acquire loop, but at the end of the loop only *one* waiter wins the acquire race; the rest fell through to an uncoordinated fetch and stampeded Massive anyway. `api.php` now distinguishes two failure modes after retries exhaust: (a) Redis is down → fail-open already set `$gotLock = true` upstream, we never reach this branch; (b) Redis is up but another waiter just won → return the file-cache stale data if present with a `singleflight_contention` reason, or HTTP 503 with the same reason if no stale data exists. The acquire-race winner will populate Redis within a couple of seconds; a client retry hits the warm cache and gets fresh data. Net: no more than one upstream fetch in flight per ticker, full stop, even under aggressive concurrent demand.

## Highlights in v0.13.7

- **Singleflight: retry-acquire after wait timeout (stop the slow-fetch stampede).** v0.13.4's `try/finally` fixed the release-before-cache-write window, but waiters that hit the 12s wait timeout still fell straight through and stampeded the upstream call when the original owner was genuinely slow (e.g., Massive itself slow for that ticker). `api.php` now retries acquiring the lock after each wait timeout. Since the lock TTL is 15s and the wait window is 12s, after a wait timeout the lock has typically either expired (we take over) or been released (we'd have seen the value during the wait). Bounded at 2 cycles (~24s total) before falling through uncoordinated — same worst-case as before, but coordinated waiting gets two real attempts first.
- **/healthz.php malformed-state response now includes config.** v0.13.6 attached the `$configBlock` (quota mode, forwarded-proto trust) to the normal and starting responses, but the malformed-state error branch returned without it. Tiny ops polish: ops can now read quota/proxy config off `/healthz.php` regardless of state-file state.

## Highlights in v0.13.6

- **Forwarded-proto behind an explicit trust contract.** v0.13.4 honored `HTTP_X_FORWARDED_PROTO` whenever the host was on the whitelist, but `Host:` is also client-controlled unless nginx normalizes it via `server_name` boundaries — so a whitelist match doesn't actually prove the request came through the trusted proxy. `lib/app_url.php` now defaults to `https` for every callback URL it builds. The forwarded-proto header is honored only when `HACKTRADER_TRUST_FORWARDED_PROTO=1` is set in the environment — i.e., ops has explicitly committed that the proxy strips/overrides any client-supplied `X-Forwarded-Proto`. State is exposed at `/healthz.php` under `config.trust_forwarded_proto`. Documented in `docs/ops-env.md`.
- **phpstan now lints clean without `composer install`.** The `Google\` and `Predis\` class-not-found ignores were already in place; added `Stripe\` for parity, since `subscribe.php`, `billing.php`, and `webhook.php` all reach into the Stripe SDK classes. CI and fresh-clone lint runs now report `[OK] No errors` without needing the vendor tree present.
- **/healthz.php starting response includes the config block.** Previously the early-return path (when `state/health-status.json` doesn't exist yet, i.e. fresh box right after deploy) dropped the new `config.quota_mode` field — which is exactly when ops most wants to verify it. Both `config.quota_mode` and the new `config.trust_forwarded_proto` are now in the starting response too.

## Highlights in v0.13.5

- **phpstan happy with the new URL helper.** v0.13.4 introduced `lib/app_url.php` with `hacktrader_app_url()` wrapped in `if (!function_exists(...))`. phpstan static-analysis treats conditionally-defined functions as "may not exist," so every caller (billing.php, subscribe.php, callback.php) flagged "Function hacktrader_app_url not found." Dropped the guard (require_once is already idempotent — the guard was load-bearing only when the function lived inside subscription.php's `if (!defined(...))` block) and added `lib/app_url.php` to phpstan.neon's path list. Lint clean again. No behavior change at runtime.

## Highlights in v0.13.4

- **Quota enforcement is now wired and switchable.** `api.php` previously set `$hardGate = false` as a hard-coded constant, leaving Free and expired users effectively unmetered now that Starter is live as a paid tier. The gate is now driven by the `HACKTRADER_QUOTA_HARD_GATE` environment variable — leave unset for soft mode (count + log, default), set to `1` for hard mode (HTTP 402 with `quota_exceeded` payload). The active mode is exposed at `/healthz.php` under `config.quota_mode` so ops can verify which side a box is on. Documented in `docs/ops-env.md`.
- **OAuth redirect URI uses the whitelisted URL builder.** `callback.php` was still building the Google OAuth `redirect_uri` from raw `HTTP_HOST` + `HTTP_X_FORWARDED_PROTO` — the same host-header trust boundary that was fixed for Stripe URLs in v0.13.2/v0.13.3. Now centralized in `lib/app_url.php`, called from both callback.php and (via the require chain) from subscribe.php / billing.php. Single source of truth for "absolute URL back into this app, for whichever host the request actually came in on, with a fallback to the canonical production domain."
- **Singleflight lock now held through the cache write.** The v0.13.3 release call sat between `shell_exec()` and the `ht_cache_set()` writes, opening a small window where a follow-up request could win a *new* lock and start a duplicate upstream fetch even though the cache was about to be populated. The release is now inside a `try/finally` wrapping the entire post-fetch block, so the lock is held through the JSON decode and Redis/file cache writes on the success path, and only released after the response is composed on the stale/error paths. Combined with v0.13.3's ownership-token compare-and-delete, the lock now does what its name promises across the full lifecycle.

## Highlights in v0.13.3

- **Singleflight lock is now correct under slow fetches.** The v0.13.2 stampede protection had a race: the lock TTL was 5 seconds, but waiters gave up after 2. A `run-brk.sh` call slower than 2s would hand control to a waiter, which then deleted the original owner's still-valid lock when it finished. With many concurrent waiters this devolved back into a stampede. Three changes close it: (1) `ht_cache_acquire_singleflight` now returns a unique ownership token instead of a bool; (2) `ht_cache_release_singleflight` does a Lua compare-and-delete so it only deletes the lock if we still own it; (3) TTL bumped to 15s and wait window to 12s, so a normal Massive fetch finishes well inside both. Late waiters that time out fall through to their own fetch but no longer torch the original lock on the way past.
- **Stripe URLs no longer downgradable via forwarded-proto.** `lib/subscription.php::hacktrader_app_url()` used to honor `HTTP_X_FORWARDED_PROTO` unconditionally. Even after the host whitelist was added in v0.13.2, a forged or oddly-configured proxy header could still produce `http://hacktrader.com/...` for Stripe success/cancel/return URLs. The header is now only trusted when the request host is already on the whitelist; otherwise scheme is forced to https. Not an attacker-controlled redirect (host is locked), but it closes the matching downgrade vector on the same code path.
- **Defensive `.gitignore` entry for a stray nested clone.** A code reviewer flagged that a nested `hacktrader/` directory had appeared in the working tree with its own `.git`, ~6.9MB. Added a `/hacktrader/` ignore rule so a future `git add .` can't accidentally stage it as a nested-repo entry.

## Highlights in v0.13.2

- **Webhook handler fails closed on errors.** `webhook.php` previously caught handler exceptions and returned 200, which told Stripe "received and processed" even when a downstream write (Stripe customer create, DB update, plan transition) had thrown. Stripe never retried, and the user's subscription state could silently desync from the truth at Stripe. The handler now logs the exception and returns **500**, which is Stripe's documented retry trigger. Idempotency keys on the Stripe side mean retries are safe even when the original handler partially succeeded.
- **Host-header whitelist on Stripe return URLs.** `lib/subscription.php::hacktrader_app_url()` used to build success/cancel/return URLs from `$_SERVER['HTTP_HOST']` verbatim, which is attacker-controllable on the request line. A forged `Host:` header could route the Stripe Checkout return through an attacker's domain. The host is now compared against an allowlist (`hacktrader.com`, `dev.hacktrader.com`, `www.hacktrader.com`) and falls back to `hacktrader.com` if it doesn't match. Same code still serves dev and prod; the failure mode is closed.
- **Cache stampede protection (singleflight).** When the Redis subscription cache misses for a ticker that 50 users want at the same moment (e.g., a popular ticker right at the open), `api.php` was shelling out to `run-brk.sh` per request and burning Massive API budget. New helpers in `lib/cache.php` (`ht_cache_acquire_singleflight`, `ht_cache_release_singleflight`, `ht_cache_wait_for_value`) use a Redis `SET NX EX` lock so only one process fetches and the rest wait for the cache to populate. Massive cost per stampede now scales with unique tickers, not unique requests.
- **Redis-failure memoization within a request.** If the Redis box is down, `ht_cache_client()` used to attempt reconnection on every cache read inside a single PHP request — turning a Redis outage into a many-times-slower request rather than a one-time slow path. A static `$tried` flag now memoizes the failure so subsequent reads in the same request return null immediately. Each fresh request still gets a fresh attempt, so recovery is automatic when Redis comes back.

## Highlights in v0.13.1

- **Adaptive refresh intervals by market state.** The v0.13.0 refresher daemon refreshed all 40 warm tickers on a fixed schedule regardless of clock — which meant burning Massive API calls all weekend and overnight when the underlying prices weren't moving. The refresher now classifies US market state into *regular* (9:30am–4pm ET weekdays), *extended* (4am–9:30am and 4pm–8pm ET weekdays), and *closed* (everything else), and applies a multiplier per state: 1× during regular hours, 5× during extended (refresh five times less often), and 60× when closed (effectively dormant). Same freshness at the open; ~95% reduction in upstream calls outside trading hours.
- **Force refresh on market open transitions.** When the daemon's classified state transitions from `closed` or `extended` into `regular`, it forces a one-shot refresh across all warm tickers before resuming the adaptive cadence. First-of-the-morning users see fresh data immediately at 9:30, not at the next scheduled tick.

## Highlights in v0.13.0

- **Redis subscription cache + market data refresher daemon.** The biggest architectural change since the v0.10.0 visualization pivot. Per-user API cost is now decoupled from user growth: a long-running daemon owns all upstream calls to Massive, refreshes ~40 popular tickers on a schedule, and serves every user request from Redis in microseconds. Adding 1000 users adds ~zero marginal Massive cost; the limit is the number of *unique* tickers actively queried, not the number of users querying them. Theoretical 10-100× scaling headroom on the existing GCE box.
- **The cache architecture in three files:** `lib/cache.php` (PHP read-through + graceful degradation), `market_data_refresher.py` (long-running daemon, sole owner of Massive calls for the warm set), and `hacktrader-refresher.service` (systemd unit with sandboxing). `api.php` reads Redis first, falls back to the legacy file cache, then to a live Massive fetch on miss.
- **40 pre-warmed tickers** keep the most-queried instruments permanently in cache: broad-market ETFs, mega-cap tech, popular volatility names, banks, semis, sector ETFs, and macro instruments. First-of-the-morning users hit instantly even for cold-start scenarios.
- **Tunable refresh intervals per period:** 30s for 1m, 120s for 5m, 600s for 1h, 3600s for 1d. Bounded by the 15-min upstream delay; aggressive enough to keep data fresh, conservative enough to be cost-efficient.
- **`docs/redis-deployment.md`** — full deployment runbook including Redis install, predis composer add, systemd service setup, verification commands, and troubleshooting. Reproducible if you ever stand up a second region or replace the box.
- **`correlate.php`** — filters out the focus ticker from its own basket (a stock can't correlate with itself), and expanded the baseline candidate pool from 12 to 16 unique tickers so the basket can always fill 12 peer slots even when the focus is one of the baseline names.

## Highlights in v0.12.0

- **Colorblind-safe direction encoding.** Indicator nodes (and the focus node) now display ▲/▼/▪ glyphs alongside the green/red fill. A deuteranopic user can read direction by shape alone if green and red collapse to a similar hue. Same data, two redundant channels.
- **Persistent honesty stance in the UI.** A small "Describing current structure — not a forecast" tagline now sits below the focus narrative line. Always visible at low visual weight. Counters the user's prior expectation (set by every other trading product) that we're forecasting.
- **Lite toggle relocated to the radar corner.** Moved out of the topbar and into the bottom-right of the radar card. Discovered through spatial proximity rather than label scanning. Frees a topbar slot.
- **Pricing tagline rewrite to workflow language.** Free → "Enough to track one idea." Plus → "Cover a full watchlist." Pro → "Run the whole tape." Reframes abstract limits in trader vocabulary.
- **Default ticker → SPY.** First-time users land on a populated radar (broad market index, universally recognized) instead of an empty input. Last-used ticker remembered in localStorage for return visits.
- **Stripe subscription pipeline live.** `subscribe.php` / `billing.php` / `webhook.php` all activated against `stripe/stripe-php`. Hosted Checkout, Customer Portal, signature-verified webhooks, full DB write-back on every event. Test charge proven end-to-end.
- **Host-aware redirect URLs.** `subscribe.php` and `billing.php` build success/cancel/return URLs from the request host, so the same code serves `dev.hacktrader.com` (sandbox keys) and `hacktrader.com` (live keys) without per-host config.
- **Polish.** Bumped contrast on small uppercase labels (the `--muted` color was AA-passing but effortful at 10–11px). Added footer links to privacy/tos/disclaimer on the landing page.

## Highlights in v0.11.0

- **HackTrader Lite — radar-only render mode.** Strips the dashboard down to the topbar, focus header, correlation radar, and footer. The radar grows to fill the viewport and becomes the unambiguous visual centerpiece. Useful for distraction-free monitoring, screenshots, demo-ing the radial concept, and (eventually) embedding.
- **Three ways to enter Lite.** `?lite=1` URL param for shareable / linkable Lite views; a "Lite" toggle button in the topbar for daily-use; and `localStorage` persistence so the preference sticks across visits without dirtying the URL.
- **Pure render-mode pivot — zero data path changes.** Same fetch, same `run-brk.py`, same correlation feed, same radar layout code. Lite is a `body.lite` CSS class plus a few `display: none` rules. The supporting data is always there — just hidden until you want it.
- **Patent-relevant simplification.** The novel claim — indicator nodes plotting at radius proportional to live correlation around a focus node — now has its own dedicated render. The cleanest possible expression of the IP, useful for demos and corp-dev conversations.

## Highlights in v0.10.0

- **Reframed as a visualization tool, not a prediction engine.** A walking-forward backtest of the v0.9.x scoring (1,365 daily trades on real Massive bars; ~47.3% hit rate; ~-0.48% per trade after costs; 2,747 5-minute trades at ~17.1% hit rate) confirmed the score has no predictive edge after costs. Rather than continue to imply one, the product now describes only what it observes in current price, level, and correlation structure.
- **Language sweep across the UI.** "Breakout probability" → *directional pressure*. "Confidence" → *alignment*. "Bias" → *leaning*. "Predicted bounds" → *next channel band where price would sit if the current level breaks*. The numbers, meters, and radar haven't changed; the claims they make about the future have.
- **Honest disclaimer gate.** `disclaimer.php` no longer hedges with "informational only" — it states explicitly that scores describe what is currently visible in chart structure, are not forecasts or probabilities of future moves, and that most retail technical analysis loses money to costs and behavioral error.
- **Landing page repositioning.** `index.php` hero now leads with "Market structure visualization" and "See the structure, faster." Three-tile callout grid: *what it is* (visualization aid), *centerpiece* (correlation radar), *honest stance* (no signals, no advice).
- **Score symmetrization (v0.9.x carryover).** PDH/PDL weights equalized at 6.5, volume clamp made symmetric at ±1.0, tight-channel penalty zeroed out. Backtests after the fix showed mean signal centered closer to neutral but still no edge — confirming that visual framing, not parameter tuning, is the right next step.
- **Backtest harness shipped.** `scripts/backtest_breakout.py` is a walking-forward signal evaluator with cost-per-side and synthetic random-walk control. Anyone forking the repo can re-run the honesty check.

## Highlights in v0.9.0

- **Subscription tiers introduced.** Free (5 tickers, 1k API calls/mo) · HackTrader Plus $29/mo (25 tickers, 25k calls) · HackTrader Pro $99/mo (unlimited). New users get a 7-day Plus-tier trial automatically on first Google login.
- **Per-user persistence.** SQLite-backed `users.sqlite` with auto-migrating schema. Tracks Google OAuth identity, Stripe customer + subscription IDs, plan, status, and a per-user monthly API-call counter that resets on the billing cycle boundary.
- **Stripe wiring (scaffold).** `subscribe.php` (hosted Checkout redirect), `billing.php` (Customer Portal redirect), `webhook.php` (signature-verified event receiver, plan updates, payment-failed → past_due transitions). All three return informative 503 messages until Stripe keys land in `secrets.json`; once they do, flipping the switch is trivial.
- **Entitlement matrix as data.** `lib/plans.php` is the single source of truth for plan limits — change a number there and the gates, the pricing page, and the dashboard usage panel all reflect it.
- **Gate helpers.** `user_can_add_ticker()`, `user_can_make_api_call()`, `record_api_call()`, `user_usage_summary()` — small, pure-ish functions that wrap the plan + DB lookups so call sites stay readable.

## Highlights in v0.8.2

- **Focus node shows the headline number.** Direction line now reads `↑ 82.3%` — direction glyph plus dominant breakout probability — so the most important focus-ticker fact is always visible at a glance.
- **Breakout target replaces channel width.** The middle microchart now shows the predicted bounds of the new trading channel: `↑ $375.92 – $379.50` with subtext like `if breaks up · width $3.58 · ATR 1.42 · $0.66 away`. Concrete take-profit/exit prices for the planned trade.
- **Copyright notice in the footer** plus version alignment across `dashboard.php`, `index.php`, `disclaimer.php`, `privacy.html`, `tos.html`, and the `api.php` health-status / usage-tracker meta stamps.

## Highlights in v0.8.1

- **Indicator nodes are circles.** Roughly 60% smaller than the old rectangles, with a dot-on-radar feel that matches the radar metaphor. Inverse-correlation indicators get a dashed circular border. Per-node price display dropped — hover any indicator for a tooltip with ticker, price, bias, breakout %, and correlation score.
- **Animated repositioning.** Indicators "swim" to new radii when their breakout strength changes between refreshes. The connecting lines follow smoothly. Combined with the in-place refresh, the radar reads as a continuous-momentum visual rather than a tick-tock snap.
- **Channel width microchart shows the breakout target.** Reads `$3.48 ↑ $5.92` — current channel width, arrow tracking bias, projected width if price breaks the current channel boundary.
- **Distance now encodes breakout strength.** `radius = max(upProb, downProb)/100` instead of static correlation. The basket visibly compresses when a setup tightens.

## Highlights in v0.8.0

- **Single-column layout.** Right rail eliminated. The correlation radar earns the full content width and is the unambiguous visual centerpiece. Activity / Context / Usage tabs collapse into a single intel card below the chart.
- **Score-driven correlation radar.** Indicators plot at radius proportional to their Pearson coefficient — strong correlations cluster near focus, weak ones drift to the edge. Concentric labeled rings (0.5 / 0.7 / 0.9) make the encoding readable. Inverse-correlation indicators render with a dashed border.
- **Basket verdict in the focus node.** The center of the radar shows "N/M ↑" — at-a-glance confirmation of how many indicators agree with the breakout bias.
- **Hero rebalance.** The 56px display H1 is gone. TSLA + bias pill share one line; price + delta + time live in a horizontal stat block. A single muted subtitle ("9 of 12 indicators confirm · 2 failed upside probes") replaces the multi-line narrative.
- **Color separation.** Bias colors (green/red) are now reserved strictly for market direction. System status (live / stale / error / cached) is encoded as a leading dot color (cyan / amber / slate / blue), so a "stale-but-up" state reads as a green chip with an amber dot rather than fighting itself.
- **Topbar status pill.** Replaces the old full-width yellow source banner. Lives next to the action buttons because it's a system-level fact, not page content.
- **Massive-only data path.** TwelveData and yfinance fallbacks removed. `run-brk.py` calls Massive directly; on failure it surfaces an error rather than silently degrading.
- **Spawn-flood fix.** `api.php` checks correlation-locks and `correlation-status.json` before spawning `generate-correlations.py`. The generator acquires per-symbol locks before any HTTP work, so duplicate spawns exit fast instead of each blowing ~50 Massive requests.

## Highlights in v0.7.7
- **Tabbed right rail:** The top-right card now uses tabs for **Probes**, **Context**, and **Usage**, with **Probes** selected by default for better visibility.
- **Attempt detail rows:** Attempt monitor continues to show the failed upside and downside probe counts with block-state detail underneath the higher-level probe graph.
- **Integrated API usage strip:** Attributed per-user API usage now lives inside the right-rail **Usage** tab instead of fighting for space in the default view.
- **Persistent session identity:** Google-authenticated users now get a stable internal `session:user_name` derived from their account and reused across logins.
- **Independent API accounting:** API request usage is tracked in `api_usage_tracker.json` per persistent session identity, including request counts, provider outcomes, stale serves, and recent event history.
- **Requester-aware logging:** `api.php` now preserves existing auth behavior while tagging live API activity with the resolved requester identity.
- **Centralized market data cache:** A minute-cadence updater can populate a shared lookup table for reuse across the system.
- **Reduced Twelve Data pressure:** Breakout analysis can prefer cached quotes before falling back to live fetches.
- **Concurrency guard:** Duplicate BRK jobs for the same ticker/interval now fail fast instead of piling up CGI workers.
- **Radial dashboard redesign:** Focus layout now flows around the main circle instead of using a disconnected side ladder.
- **Focus logo badge:** The center ticker now shows a company logo when available, with a styled ticker fallback when not.
- **Improved support/resistance semantics:** Resistance 1 is now the nearest resistance above price, and Resistance 2 is the next higher level.
- **Indicator bias clarity:** Up counts are green and down counts are red for faster scanning.
- **Parallel indicator updates:** Correlated indicators continue to load in parallel for responsiveness.
- **Volume and breakout context:** Focus panel shows day volume, bar-vs-expected volume, and directional probabilities.
- **Session-aware access controls:** Dashboard access remains protected by login/session checks.

## Core files
- `dashboard.php` — main UI and client-side dashboard logic
- `api.php` — ticker data API endpoint and request/usage orchestration
- `run-brk.py` / `run-brk.sh` — production breakout engine and wrapper
- `run-brk.c` — legacy/experimental C runner; not the active production path
- `correlate.php` / `correlations.json` — correlated symbol mapping
- `generate-correlations.py` — auto-builds ticker relationship baskets from market history
- `focus-universe.json` — persisted set of known/auto-learned focus tickers
- `callback.php` — OAuth callback handling
- `market-cache-updater.py` — minute-cadence market quote cache builder
- `market-watchlist.json` — deduped watchlist for cache population

## Production runner
- Production market-data execution now flows through `run-brk.sh` -> `run-brk.py`.
- `run-brk.py` is the sole supported production breakout engine for live requests.
- `run-brk.c` remains on disk only as a legacy/experimental artifact and should not be used for production traffic until parity testing exists.

## Auto-generated correlation flow
- Valid focus ticker requests are persisted into `focus-universe.json`.
- Newly seen valid focus symbols are also appended into `market-watchlist.json`.
- `generate-correlations.py` can build/update `correlations.json` from recent daily market history.
- If a requested ticker has no generated basket yet, `correlate.php` returns a macro/thematic fallback set immediately while a background generation task is queued.

## Verification scripts
- `scripts/check-api-contract.py` — verifies dashboard period options, API normalization, and wrapper argv contract stay aligned.
- `scripts/smoke-market-data.sh` — runs authenticated smoke checks for TSLA 5m, NVDA 5m, and SPY 1d through `api.php`.

### Run verification
```bash
python3 /var/www/html/scripts/check-api-contract.py
/var/www/html/scripts/smoke-market-data.sh
```

## Notes
- The v0.7.7 release stabilizes the live market-data path by fixing the dashboard-to-runner contract, standardizing interval normalization, and promoting `run-brk.py` as the sole production runner.
- API responses now expose explicit live/cache/stale/error state, the dashboard shows degraded mode, and `healthz.php` tracks consecutive failures and stale ratios.
- Verification scripts now cover both contract drift and live smoke checks for key symbols.