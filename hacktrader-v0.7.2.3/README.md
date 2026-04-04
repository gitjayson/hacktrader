# HackTrader Dashboard

- **Version:** v0.7.2.3.3.2
- **Status:** Active
- **Codebase:** HackTrader FUI dashboard

HackTrader is a market dashboard for tracking a focus ticker, breakout probabilities, support/resistance ladders, volume context, and correlated indicators in a sci-fi control-panel interface.

## Highlights in v0.7.2.3.3.2
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
- `api.php` — ticker data API endpoint
- `run-brk.py` / `run-brk.sh` — breakout calculations and CLI wrapper
- `correlate.php` / `correlations.json` — correlated symbol mapping
- `generate-correlations.py` — auto-builds ticker relationship baskets from market history
- `focus-universe.json` — persisted set of known/auto-learned focus tickers
- `callback.php` — OAuth callback handling
- `market-cache-updater.py` — minute-cadence market quote cache builder
- `market-watchlist.json` — deduped watchlist for cache population

## Auto-generated correlation flow
- Valid focus ticker requests are persisted into `focus-universe.json`.
- Newly seen valid focus symbols are also appended into `market-watchlist.json`.
- `generate-correlations.py` can build/update `correlations.json` from recent daily market history.
- If a requested ticker has no generated basket yet, `correlate.php` returns a macro/thematic fallback set immediately while a background generation task is queued.

## Notes
- The v0.7.2.3.3.2 release adds dynamic correlation generation, learned focus symbols, and fallback indicator baskets.
- Logo rendering currently uses a lightweight web logo approach with graceful fallback.
- A future polish pass could add true curved arc panels and more refined animation.
