#!/usr/bin/env python3
import json
import re
from datetime import datetime, timezone
from pathlib import Path

try:
    import yfinance as yf
except ImportError:
    yf = None

BASE_DIR = Path(__file__).resolve().parent
TICKERS_PATH = BASE_DIR / "tickers.json"
OUTPUT_PATH = BASE_DIR / "mdata.json"
VERSION = "0.1.0"
PROJECT = "reflector"
SOURCE = "yfinance"


def now_iso() -> str:
    return datetime.now(timezone.utc).replace(microsecond=0).isoformat().replace("+00:00", "Z")


def load_indicator_ids() -> list[str]:
    with TICKERS_PATH.open() as f:
        return json.load(f)


def base_symbol(indicator_id: str) -> str:
    return re.sub(r"_(?:\d+__\d+|\d+____\d+)$", "", indicator_id)


def fetch_symbol(symbol: str) -> dict:
    if yf is None:
        raise RuntimeError("yfinance is not installed")

    ticker = yf.Ticker(symbol)
    info = {}
    fast = getattr(ticker, "fast_info", None)

    if fast:
        def get_fast(name):
            try:
                return fast.get(name)
            except Exception:
                return None

        info = {
            "currency": get_fast("currency"),
            "exchange": get_fast("exchange"),
            "last_price": get_fast("lastPrice"),
            "open": get_fast("open"),
            "day_high": get_fast("dayHigh"),
            "day_low": get_fast("dayLow"),
            "previous_close": get_fast("previousClose"),
            "volume": get_fast("lastVolume"),
            "market_cap": get_fast("marketCap"),
        }

    if not info.get("last_price"):
        hist = ticker.history(period="2d", interval="1m")
        if hist is not None and not hist.empty:
            row = hist.tail(1).iloc[0]
            ts = hist.tail(1).index[0]
            info.update({
                "last_price": None if row.get("Close") is None else float(row.get("Close")),
                "open": None if row.get("Open") is None else float(row.get("Open")),
                "day_high": None if row.get("High") is None else float(row.get("High")),
                "day_low": None if row.get("Low") is None else float(row.get("Low")),
                "volume": None if row.get("Volume") is None else float(row.get("Volume")),
                "asof": str(ts),
            })

    return info


def build_snapshot() -> dict:
    indicator_ids = load_indicator_ids()
    base_symbols = []
    seen = set()
    for indicator_id in indicator_ids:
        symbol = base_symbol(indicator_id)
        if symbol not in seen:
            seen.add(symbol)
            base_symbols.append(symbol)

    fetched = {}
    errors = {}
    for symbol in base_symbols:
        try:
            fetched[symbol] = fetch_symbol(symbol)
        except Exception as e:
            errors[symbol] = str(e)
            fetched[symbol] = {
                "error": str(e)
            }

    data = {}
    for indicator_id in indicator_ids:
        symbol = base_symbol(indicator_id)
        data[indicator_id] = {
            "indicator_id": indicator_id,
            "symbol": symbol,
            "market": fetched.get(symbol, {}),
        }

    return {
        "project": PROJECT,
        "version": VERSION,
        "updated_at": now_iso(),
        "source": SOURCE,
        "count": len(indicator_ids),
        "base_symbol_count": len(base_symbols),
        "indicator_ids": indicator_ids,
        "data": data,
        "errors": errors,
    }


def write_snapshot(snapshot: dict) -> None:
    OUTPUT_PATH.write_text(json.dumps(snapshot, indent=2, sort_keys=False) + "\n")


if __name__ == "__main__":
    snapshot = build_snapshot()
    write_snapshot(snapshot)
    print(f"wrote {OUTPUT_PATH}")
