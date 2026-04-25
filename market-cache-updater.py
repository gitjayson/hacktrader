#!/usr/bin/env python3
"""Populate a local market data cache for HackTrader.

Runs on a 1-minute cadence (cron / launchd / systemd timer). Fetches the
most-recent daily aggregate for each symbol in the watchlist and writes the
results to market-data-cache.json so run-brk and the dashboard can serve
fast lookups without a live fetch.

Provider: Massive only (api.massive.com). Same endpoint family that
run-brk.fetch_massive uses, just with timespan=day and limit=1.
"""

from __future__ import annotations

import json
import os
import sys
import time
from datetime import datetime, timedelta, timezone
from pathlib import Path
from urllib.request import Request, urlopen

BASE_DIR = Path(__file__).resolve().parent
CACHE_PATH = BASE_DIR / "market-data-cache.json"
WATCHLIST_PATH = BASE_DIR / "market-watchlist.json"
SECRETS_PATH = BASE_DIR / "secrets.json"

DEFAULT_SYMBOLS = [
    "TSLA", "NVDA", "AAPL", "AMZN", "MSFT", "META", "GOOGL", "NFLX", "AMD",
    "QQQ", "SPY", "IWM", "XLY", "XLC", "XLK", "XLF",
    "WTI", "UNG", "UUP", "TLT", "GLD", "SLV", "XOP", "SMH",
]


def load_api_key() -> str | None:
    env_key = os.getenv("MASSIVE_API_KEY")
    if env_key:
        return env_key
    if SECRETS_PATH.exists():
        try:
            with SECRETS_PATH.open() as f:
                data = json.load(f)
            return data.get("MASSIVE_API_KEY")
        except Exception:
            return None
    return None


def load_symbols() -> list[str]:
    symbols = set(DEFAULT_SYMBOLS)
    if WATCHLIST_PATH.exists():
        try:
            with WATCHLIST_PATH.open() as f:
                data = json.load(f)
            if isinstance(data, list):
                for item in data:
                    if isinstance(item, str) and item.strip():
                        symbols.add(item.strip().upper())
                    elif isinstance(item, dict):
                        sym = str(item.get("symbol", "")).strip().upper()
                        if sym:
                            symbols.add(sym)
        except Exception:
            pass
    return sorted(symbols)


def fetch_quote(symbol: str, api_key: str) -> dict | None:
    """Fetch the most recent daily bar for `symbol` from Massive.

    Returns a dict with the shape run-brk.cache_quote_to_value expects:
    {high, low, close, volume, datetime}. Returns None on any failure
    (caller logs the symbol and moves on).
    """
    today = datetime.now(timezone.utc).date()
    # Look back a few days so non-trading days (weekends/holidays) still
    # return the most recent close.
    from_date = (today - timedelta(days=5)).isoformat()
    to_date = today.isoformat()
    url = (
        f"https://api.massive.com/v2/aggs/ticker/{symbol}/range/1/day/"
        f"{from_date}/{to_date}?adjusted=true&sort=desc&limit=1&apiKey={api_key}"
    )
    req = Request(url, headers={"User-Agent": "HackTrader/0.7.7"})
    try:
        with urlopen(req, timeout=20) as resp:
            payload = json.loads(resp.read().decode("utf-8", "replace"))
    except Exception:
        return None

    results = payload.get("results")
    if not isinstance(results, list) or not results:
        return None
    row = results[0]
    try:
        ts_ms = int(row.get("t", 0))
        return {
            "high": float(row.get("h", 0)),
            "low":  float(row.get("l", 0)),
            "close": float(row.get("c", 0)),
            "volume": float(row.get("v", 0)) if row.get("v") is not None else None,
            "datetime": datetime.fromtimestamp(ts_ms / 1000.0, tz=timezone.utc).isoformat(),
        }
    except Exception:
        return None


def main() -> int:
    api_key = load_api_key()
    if not api_key:
        print("Missing MASSIVE_API_KEY", file=sys.stderr)
        return 1

    symbols = load_symbols()
    quotes: dict[str, dict] = {}
    errors: list[dict] = []

    for sym in symbols:
        quote = fetch_quote(sym, api_key)
        if quote is None:
            errors.append({"symbol": sym, "error": "fetch failed or empty result"})
        else:
            quotes[sym] = quote
        # Be polite even with a paid plan — keep the per-tick rate moderate.
        time.sleep(0.1)

    payload = {
        "updated_at": datetime.now(timezone.utc).isoformat(),
        "symbols": symbols,
        "quotes": quotes,
        "errors": errors,
        "provider": "massive",
    }

    tmp_path = CACHE_PATH.with_suffix(".json.tmp")
    tmp_path.write_text(json.dumps(payload, indent=2, sort_keys=True))
    tmp_path.replace(CACHE_PATH)
    print(f"Wrote {CACHE_PATH} with {len(quotes)} quote entries ({len(errors)} errors)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
