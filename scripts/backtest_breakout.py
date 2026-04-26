#!/usr/bin/env python3
"""
backtest_breakout.py — measure whether HackTrader's breakout signal generates
positive expected value after costs.

Reuses the analysis pipeline directly from run-brk.py (compute_output) so we're
testing the production algorithm, not a reimplementation.

Walking-forward methodology:
  For each bar i in the historical series (after warmup):
    - Take only bars[0..i] (no look-ahead)
    - Run compute_output → up_prob, down_prob
    - If up_prob >= threshold: simulate long entry at bar[i+1].close,
      exit at bar[i+1+holding].close, apply round-trip cost.
    - If down_prob >= threshold: same, short side.
    - Bars where neither side triggers: no trade.

Outputs hit rate, avg return per trade, Sharpe-ish ratio, total return,
vs buy-and-hold of the same series.

Two data modes:
  --mode synthetic : generate a random-walk for sanity-checking the framework
  --mode real      : pull bars from Massive via run-brk.fetch_massive

Synthetic mode runs anywhere (no network, no API key). Real mode needs
secrets.json on the box you're running it on.
"""

from __future__ import annotations

import argparse
import importlib.util
import json
import math
import random
import statistics
import sys
from datetime import datetime, timedelta
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent

# Load run-brk.py as a module under the name 'run_brk'
spec = importlib.util.spec_from_file_location("run_brk", ROOT / "run-brk.py")
run_brk = importlib.util.module_from_spec(spec)
sys.modules["run_brk"] = run_brk
spec.loader.exec_module(run_brk)


# ---------------------------------------------------------------------------
# Data sources
# ---------------------------------------------------------------------------


def synthetic_random_walk(
    n_bars: int = 500,
    start: float = 100.0,
    daily_vol: float = 0.012,
    seed: int = 42,
) -> list[dict]:
    """Generate a synthetic OHLC series with returns drawn from N(0, daily_vol).

    Returns chronological order (oldest first). A correct backtest framework
    should show ZERO mean return after costs on this data — random walks have
    no edge by construction.
    """
    rng = random.Random(seed)
    bars: list[dict] = []
    price = start
    for i in range(n_bars):
        ret = rng.gauss(0, daily_vol)
        new_price = max(0.01, price * (1 + ret))
        intraday = abs(rng.gauss(0, daily_vol * 0.6))
        h = max(price, new_price) * (1 + intraday)
        l = min(price, new_price) * (1 - intraday)
        bars.append({
            "high": h,
            "low": l,
            "close": new_price,
            "volume": rng.uniform(1e6, 5e6),
            "datetime": (datetime(2024, 1, 1) + timedelta(days=i)).isoformat(),
        })
        price = new_price
    return bars


def fetch_real_bars(symbol: str, periods: int = 500, interval: str = "1day") -> list[dict]:
    """Pull historical bars from Massive. Requires secrets.json on the host."""
    values, source, err = run_brk.fetch_massive(symbol, interval, str(periods))
    if err or not values:
        raise RuntimeError(f"fetch_massive({symbol}, {interval}) failed: {err}")
    # fetch_massive returns newest-first; we want chronological (oldest-first)
    return list(reversed(values))


# ---------------------------------------------------------------------------
# Backtest core
# ---------------------------------------------------------------------------


def signal_for_window(bars_chrono: list[dict], ticker: str = "TEST",
                      interval: str = "1day", display: str = "1-day") -> tuple[float, float]:
    """Run compute_output on a chronological bar window and return (up%, down%)."""
    # compute_output expects newest-first
    values = list(reversed(bars_chrono))
    try:
        out = run_brk.compute_output(
            ticker, interval, display, str(len(values)), values, source="backtest"
        )
        probs = out.get("probabilities", {})
        return float(probs.get("up", 0)), float(probs.get("down", 0))
    except Exception as e:
        return 0.0, 0.0


def backtest_series(
    bars_chrono: list[dict],
    *,
    threshold: float = 60.0,
    holding_period: int = 5,
    warmup: int = 100,
    cost_per_side: float = 0.001,  # 10 bps per side, 20 bps round-trip
    label: str = "TEST",
) -> dict:
    """Walk forward through bars_chrono, take signals at threshold, simulate trades."""
    trades: list[dict] = []
    for i in range(warmup, len(bars_chrono) - holding_period - 1):
        window = bars_chrono[: i + 1]  # all bars up to and including i
        up, down = signal_for_window(window, ticker=label)

        if up < threshold and down < threshold:
            continue  # no signal

        entry_bar = bars_chrono[i + 1]
        exit_bar = bars_chrono[i + 1 + holding_period]
        entry_px = float(entry_bar["close"])
        exit_px = float(exit_bar["close"])

        if up >= threshold and up >= down:
            # Long: profit when exit > entry
            gross = (exit_px - entry_px) / entry_px
            side = "long"
            signal_strength = up
        else:
            # Short: profit when exit < entry
            gross = (entry_px - exit_px) / entry_px
            side = "short"
            signal_strength = down

        net = gross - 2 * cost_per_side
        trades.append({
            "i": i,
            "side": side,
            "signal": signal_strength,
            "entry": entry_px,
            "exit": exit_px,
            "gross_return": gross,
            "net_return": net,
        })

    if not trades:
        return {
            "label": label,
            "n_trades": 0,
            "summary": "no signals exceeded threshold",
        }

    nets = [t["net_return"] for t in trades]
    grosses = [t["gross_return"] for t in trades]
    wins = sum(1 for n in nets if n > 0)
    avg_net = statistics.mean(nets)
    stdev_net = statistics.pstdev(nets) if len(nets) > 1 else 0.0
    sharpe_ish = (avg_net / stdev_net) if stdev_net > 0 else 0.0
    cumulative = 1.0
    for n in nets:
        cumulative *= 1 + n
    cumulative_return = cumulative - 1.0

    # Buy-and-hold over the same window
    bh_entry = float(bars_chrono[warmup]["close"])
    bh_exit = float(bars_chrono[-1]["close"])
    bh_return = (bh_exit - bh_entry) / bh_entry

    return {
        "label": label,
        "n_trades": len(trades),
        "n_long": sum(1 for t in trades if t["side"] == "long"),
        "n_short": sum(1 for t in trades if t["side"] == "short"),
        "hit_rate": wins / len(trades),
        "avg_gross_return_per_trade": statistics.mean(grosses),
        "avg_net_return_per_trade": avg_net,
        "stdev_per_trade": stdev_net,
        "sharpe_ish": sharpe_ish,
        "cumulative_return": cumulative_return,
        "buy_and_hold_return": bh_return,
        "edge_vs_bh": cumulative_return - bh_return,
    }


# ---------------------------------------------------------------------------
# CLI
# ---------------------------------------------------------------------------


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--mode", choices=["synthetic", "real"], default="synthetic")
    parser.add_argument("--symbols", nargs="+", default=["TSLA", "NVDA", "AAPL", "AMZN", "MSFT"])
    parser.add_argument("--bars", type=int, default=500)
    parser.add_argument("--threshold", type=float, default=60.0)
    parser.add_argument("--holding", type=int, default=5)
    parser.add_argument("--cost-bps", type=float, default=10.0,
                        help="cost per side in basis points (default 10 = 0.1%)")
    parser.add_argument("--seed", type=int, default=42)
    parser.add_argument("--interval", default="1day")
    args = parser.parse_args()

    cost_per_side = args.cost_bps / 10000.0
    print(f"\n=== HackTrader breakout backtest ===")
    print(f"Mode: {args.mode}")
    print(f"Threshold: {args.threshold}%")
    print(f"Holding period: {args.holding} bars")
    print(f"Cost: {args.cost_bps} bps/side ({2*args.cost_bps} bps round-trip)")
    print(f"Bars per series: {args.bars}")
    print()

    results: list[dict] = []
    if args.mode == "synthetic":
        # Multiple random-walk series with different seeds
        for i in range(5):
            bars = synthetic_random_walk(n_bars=args.bars, seed=args.seed + i)
            r = backtest_series(
                bars,
                threshold=args.threshold,
                holding_period=args.holding,
                cost_per_side=cost_per_side,
                label=f"SYNTH-{i}",
            )
            results.append(r)
    else:
        for symbol in args.symbols:
            try:
                bars = fetch_real_bars(symbol, periods=args.bars, interval=args.interval)
            except Exception as e:
                print(f"  {symbol}: SKIPPED ({e})")
                continue
            r = backtest_series(
                bars,
                threshold=args.threshold,
                holding_period=args.holding,
                cost_per_side=cost_per_side,
                label=symbol,
            )
            results.append(r)

    # Print individual results
    for r in results:
        print(f"--- {r['label']} ---")
        if r["n_trades"] == 0:
            print(f"  no signals at threshold {args.threshold}%")
            continue
        print(f"  trades:    {r['n_trades']} ({r['n_long']} long / {r['n_short']} short)")
        print(f"  hit rate:  {r['hit_rate']*100:.1f}%")
        print(f"  avg gross: {r['avg_gross_return_per_trade']*100:+.3f}%")
        print(f"  avg net:   {r['avg_net_return_per_trade']*100:+.3f}%")
        print(f"  stdev:     {r['stdev_per_trade']*100:.3f}%")
        print(f"  sharpe-ish: {r['sharpe_ish']:.3f}")
        print(f"  cumulative: {r['cumulative_return']*100:+.2f}%")
        print(f"  buy-and-hold: {r['buy_and_hold_return']*100:+.2f}%")
        print(f"  edge vs B&H: {r['edge_vs_bh']*100:+.2f}%")
        print()

    # Aggregate
    valid = [r for r in results if r["n_trades"] > 0]
    if valid:
        all_nets = []
        for r in valid:
            # weight by n_trades for fairness
            all_nets.extend([r["avg_net_return_per_trade"]] * r["n_trades"])
        agg_avg = statistics.mean([r["avg_net_return_per_trade"] for r in valid])
        agg_hit = statistics.mean([r["hit_rate"] for r in valid])
        agg_edge = statistics.mean([r["edge_vs_bh"] for r in valid])
        total_trades = sum(r["n_trades"] for r in valid)
        print("=== Aggregate across series ===")
        print(f"  total trades:     {total_trades}")
        print(f"  mean hit rate:    {agg_hit*100:.1f}%")
        print(f"  mean net return/trade: {agg_avg*100:+.3f}%")
        print(f"  mean edge vs B&H: {agg_edge*100:+.2f}%")
        print()

        # Verdict
        print("=== Read ===")
        if abs(agg_avg) < 0.0005:
            print("  Net return/trade is ~zero → consistent with no edge.")
        elif agg_avg > 0:
            print(f"  Positive expectancy of {agg_avg*100:.3f}% per trade after costs.")
            print("  This is a signal worth investigating further.")
        else:
            print(f"  NEGATIVE expectancy of {agg_avg*100:.3f}% per trade after costs.")
            print("  Following these signals lost money on this dataset.")

        if args.mode == "synthetic":
            print()
            print("  (Synthetic = pure random walks. Any nonzero result here is")
            print("   a framework artifact, not a real edge. Use real mode to test")
            print("   the actual signal.)")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
