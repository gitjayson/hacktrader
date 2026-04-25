# HackTrader Dashboard

- **Version:** v0.8.1
- **Status:** Active
- **Codebase:** HackTrader FUI dashboard

HackTrader is a market dashboard for tracking a focus ticker, breakout probabilities, support/resistance ladders, volume context, and correlated indicators in a sci-fi control-panel interface.

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