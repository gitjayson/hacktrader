# reflector v0.1.0

A market-data reflector sidecar for Hacktrader.

## Goal

Reduce upstream API fan-out by collecting a static set of market symbols once per minute
and writing a single consolidated JSON snapshot to `mdata.json`.

Hacktrader can later consume this single file (or a future hosted JSON endpoint)
instead of opening many live market-feed connections.

## Current scope

- Frozen static list of 50 currently used indicator tickers
- Uses yfinance-compatible fetch logic
- Writes latest consolidated snapshot to `mdata.json`
- Does **not** cut Hacktrader over yet

## Files

- `tickers.json` — static frozen symbol list for v0.1.0
- `reflector.py` — collector + JSON writer
- `run_loop.py` — once-per-minute refresh loop with logging
- `serve_mdata.py` — tiny local HTTP server for `mdata.json`
- `reflector.sh` — simple runner helper
- `mdata.json` — latest output snapshot
- `reflector.log` — loop activity log

## Notes

Some current pipeline names include suffixes like `_5__100` or `_1__100`. For v0.1.0,
these are preserved in the static list as logical indicator ids, while the collector maps
those ids to a base market symbol for yfinance lookup.

## Run locally

```bash
cd projects/reflector-v0.1.0
. .venv/bin/activate
python reflector.py
python run_loop.py
python serve_mdata.py
```

Then inspect:

- `mdata.json`
- `reflector.log`
- `http://127.0.0.1:8787/mdata.json`
