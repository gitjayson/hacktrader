# HackTrader Dashboard

- **Version:** v0.7.7
- **Status:** Active
- **Codebase:** HackTrader FUI dashboard

HackTrader is a market dashboard for tracking a focus ticker, breakout probabilities, support/resistance ladders, volume context, and correlated indicators in a sci-fi control-panel interface.

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