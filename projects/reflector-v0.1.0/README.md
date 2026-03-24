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
- `reflector.sh` — simple once-per-minute runner helper
- `mdata.json` — latest output snapshot

## Notes

Some current pipeline names include suffixes like `_5__100` or `_1__100`. For v0.1.0,
these are preserved in the static list as logical indicator ids, while the collector maps
those ids to a base market symbol for yfinance lookup.
