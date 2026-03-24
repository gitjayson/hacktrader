import json
import sys
from datetime import datetime

import yfinance as yf


INTERVAL_CONFIG = {
    "1min": {"yf_interval": "1m", "period": "7d", "display": "1-min"},
    "5min": {"yf_interval": "5m", "period": "60d", "display": "5-min"},
    "1h": {"yf_interval": "60m", "period": "730d", "display": "1-hour"},
    "1day": {"yf_interval": "1d", "period": "10y", "display": "1-day"},
}


def emit_error(message, output_json="false"):
    if output_json == "true":
        print(json.dumps({"error": message}))
    else:
        print(f"Error: {message}")


def fetch_history(ticker, interval, periods):
    config = INTERVAL_CONFIG.get(interval)
    if not config:
        raise ValueError(f"Unsupported interval: {interval}")

    hist = yf.Ticker(ticker).history(
        period=config["period"],
        interval=config["yf_interval"],
        auto_adjust=False,
        prepost=False,
    )

    if hist is None or hist.empty:
        raise ValueError("No data returned from yfinance")

    hist = hist.dropna(subset=["Close", "High", "Low"])
    if hist.empty:
        raise ValueError("No usable OHLC data returned from yfinance")

    hist = hist.tail(int(periods))
    if hist.empty:
        raise ValueError("Not enough historical data for requested lookback")

    return hist


def run_breakout(ticker="TSLA", interval="1day", display="1-day", periods="100", output_json="false"):
    try:
        periods = int(periods)
        if periods <= 0:
            raise ValueError("periods must be > 0")
    except Exception:
        emit_error("Invalid periods value", output_json)
        return

    ticker = str(ticker).upper().strip()

    try:
        hist = fetch_history(ticker, interval, periods)
    except Exception as e:
        emit_error(str(e), output_json)
        return

    current_raw = float(hist["Close"].iloc[-1])
    highs = [float(v) for v in hist["High"].tolist()]
    lows = [float(v) for v in hist["Low"].tolist()]

    current = round(current_raw, 2)

    highs_unique = sorted(set(highs), reverse=True)
    upper_resistances = [round(h, 2) for h in highs_unique if h > current_raw][:2]

    lows_unique = sorted(set(lows))
    lower_supports = [round(l, 2) for l in lows_unique if l < current_raw][-2:][::-1]

    dist_to_upper = upper_resistances[0] - current if upper_resistances else float("inf")
    dist_to_lower = current - lower_supports[0] if lower_supports else float("inf")

    inv_upper = 1 / dist_to_upper if dist_to_upper > 0 else float("inf")
    inv_lower = 1 / dist_to_lower if dist_to_lower > 0 else float("inf")

    total_inv = inv_upper + inv_lower
    up_prob = round((inv_upper / total_inv) * 100, 1) if total_inv > 0 else 0
    down_prob = round((inv_lower / total_inv) * 100, 1) if total_inv > 0 else 0

    as_of = hist.index[-1]
    if hasattr(as_of, "to_pydatetime"):
        as_of = as_of.to_pydatetime()
    if isinstance(as_of, datetime):
        as_of = as_of.isoformat()
    else:
        as_of = str(as_of)

    if output_json == "true":
        output = {
            "ticker": ticker,
            "data_source": "yfinance",
            "display": display,
            "periods": periods,
            "as_of": as_of,
            "current_price": current,
            "upper_resistances": [
                {"price": r, "diff": round(r - current, 2)} for r in upper_resistances
            ],
            "lower_supports": [
                {"price": r, "diff": round(current - r, 2)} for r in lower_supports
            ],
            "probabilities": {
                "up": up_prob,
                "down": down_prob,
            },
        }
        print(json.dumps(output, indent=2))
        return

    print(f"=== {ticker} Breakout Analysis ({display}, {periods} periods) ===")
    print("Source: yfinance")
    print(f"As of: {as_of}")
    print(f"Last Known Market Price: ${current:.2f}")
    print()

    print("## Upper Resistance Levels")
    for i, r in enumerate(upper_resistances, 1):
        diff = round(r - current, 2)
        print(f"  {i}. ${r:.2f} (+${diff})")

    print()
    print("## Lower Support Levels")
    for i, r in enumerate(lower_supports, 1):
        diff = round(current - r, 2)
        print(f"  {i}. ${r:.2f} (-${diff})")

    print()
    print("## Breakout Probability")
    print(f"- UP: {up_prob}%")
    print(f"- DOWN: {down_prob}%")


if __name__ == "__main__":
    ticker = sys.argv[1] if len(sys.argv) > 1 else "TSLA"
    interval = sys.argv[2] if len(sys.argv) > 2 else "1day"
    display = sys.argv[3] if len(sys.argv) > 3 else "1-day"
    periods = sys.argv[4] if len(sys.argv) > 4 else "100"
    output_json = sys.argv[5] if len(sys.argv) > 5 else "false"
    run_breakout(ticker, interval, display, periods, output_json)
