# HackTrader Dashboard

- **Version:** v0.5.1
- **Status:** Active
- **Codebase:** HackTrader FUI dashboard

HackTrader is a market dashboard for tracking a focus ticker, breakout probabilities, support/resistance ladders, volume context, and correlated indicators in a sci-fi control-panel interface.

## Highlights in v0.5.1
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
- `callback.php` — OAuth callback handling

## Notes
- The v0.5.1 release preserves the new radial visual language and locks in the DreamHost deployment.
- Logo rendering currently uses a lightweight web logo approach with graceful fallback.
- A future polish pass could add true curved arc panels and more refined animation.
