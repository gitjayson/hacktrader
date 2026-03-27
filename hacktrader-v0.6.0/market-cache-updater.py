#!/usr/bin/env python3
"""Populate a local market data cache for HackTrader.

This script is intended to run on a 1-minute cadence.
It fetches quote snapshots for a deduped watchlist and stores them in
market-data-cache.json for fast lookup by run-brk and the dashboard.
"""

from __future__ import annotations

import json
import os
import sys
import time
from datetime import datetime, timezone
from pathlib import Path
from urllib.parse import urlencode
from urllib.request import urlopen, Request

BASE_DIR = Path(__file__).resolve().parent
CACHE_PATH = BASE_DIR / "market-data-cache.json"
WATCHLIST_PATH = BASE_DIR / "market-watchlist.json"
SECRETS_PATH = BASE_DIR / "secrets.json"

DEFAULT_SYMBOLS = [
    "TSLA", "NVDA", "AAPL", "AMZN", "MSFT", "META", "GOOGL", "NFLX",
    "AMD", "QQQ", "SPY", "IWM", "XLY", "XLC", "XLK", "XLF",
    "WTI", "UNG", "UUP", "TLT", "GLD", "SLV", "XOP", "SMH",
]


def load_api_key() -> str | None:
    env_key = os.getenv("TWELVEDATA_API_KEY")
    if env_key:
        return env_key
    if SECRETS_PATH.exists():
        try:
            with SECRETS_PATH.open() as f:
                data = json.load(f)
            return data.get("TWELVEDATA_API_KEY")
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


def fetch_quotes(symbols: list[str], api_key: str) -> dict:
    # Twelve Data can accept a comma-separated symbol list for quote snapshots.
    params = {
        "symbol": ",".join(symbols),
        "apikey": api_key,
    }
    url = "https://api.twelvedata.com/quote?" + urlencode(params)
    req = Request(url, headers={"User-Agent": "HackTrader/0.5.0"})
    with urlopen(req, timeout=20) as resp:
        payload = json.loads(resp.read().decode("utf-8", "replace"))
    return payload


def chunked(seq, size):
    for i in range(0, len(seq), size):
        yield seq[i:i + size]


def main() -> int:
    api_key = load_api_key()
    if not api_key:
        print("Missing Twelve Data API key", file=sys.stderr)
        return 1

    symbols = load_symbols()
    all_data = {}
    errors = []

    for chunk in chunked(symbols, 8):
        try:
            data = fetch_quotes(chunk, api_key)
            if isinstance(data, dict):
                all_data.update(data)
            else:
                errors.append({"chunk": chunk, "error": "unexpected response"})
        except Exception as exc:
            errors.append({"chunk": chunk, "error": str(exc)})
        time.sleep(1)

    payload = {
        "updated_at": datetime.now(timezone.utc).isoformat(),
        "symbols": symbols,
        "quotes": all_data,
        "errors": errors,
    }

    tmp_path = CACHE_PATH.with_suffix(".json.tmp")
    tmp_path.write_text(json.dumps(payload, indent=2, sort_keys=True))
    tmp_path.replace(CACHE_PATH)
    print(f"Wrote {CACHE_PATH} with {len(all_data)} quote entries")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
