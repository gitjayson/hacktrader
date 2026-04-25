"""
Tests for SMA / EMA / RSI / ATR / true_range in run-brk.py.
Reference values computed by hand from definitions in well-known TA texts.

These tests are the canary for silent math regressions on the dashboard.
"""

from __future__ import annotations

import math

import pytest

# ---- calculate_sma ----------------------------------------------------------


def test_sma_basic(run_brk):
    """SMA of [1..5] with window=3 = [None, None, 2.0, 3.0, 4.0]."""
    result = run_brk.calculate_sma([1, 2, 3, 4, 5], 3)
    assert result == [None, None, 2.0, 3.0, 4.0]


def test_sma_window_equals_length(run_brk):
    result = run_brk.calculate_sma([10, 20, 30], 3)
    assert result == [None, None, 20.0]


def test_sma_window_larger_than_data(run_brk):
    """No window ever fills, so every entry stays None."""
    result = run_brk.calculate_sma([1, 2, 3], 5)
    assert result == [None, None, None]


def test_sma_constant_series(run_brk):
    result = run_brk.calculate_sma([7, 7, 7, 7], 2)
    assert result == [None, 7.0, 7.0, 7.0]


# ---- calculate_ema ----------------------------------------------------------


def test_ema_seeds_with_simple_average(run_brk):
    """First EMA value (at index = days-1) is the SMA of the first `days` prices."""
    prices = [10, 20, 30, 40, 50]
    result = run_brk.calculate_ema(prices, 3)
    # First 2 entries are None, third is sum(10,20,30)/3 = 20
    assert result[0] is None
    assert result[1] is None
    assert result[2] == pytest.approx(20.0)


def test_ema_subsequent_values_use_smoothing_formula(run_brk):
    """EMA[t] = price[t] * k + EMA[t-1] * (1-k), where k = smoothing/(1+days)."""
    prices = [10, 20, 30, 40, 50]
    days = 3
    smoothing = 2
    k = smoothing / (1 + days)  # 0.5
    result = run_brk.calculate_ema(prices, days, smoothing=smoothing)

    expected_seed = 20.0
    expected_4 = 40 * k + expected_seed * (1 - k)  # 30.0
    expected_5 = 50 * k + expected_4 * (1 - k)  # 40.0

    assert result[2] == pytest.approx(expected_seed)
    assert result[3] == pytest.approx(expected_4)
    assert result[4] == pytest.approx(expected_5)


def test_ema_constant_series_stays_constant(run_brk):
    """If every price is the same, EMA must equal that value once it's seeded."""
    result = run_brk.calculate_ema([5.0] * 10, 4)
    assert result[:3] == [None, None, None]
    for v in result[3:]:
        assert v == pytest.approx(5.0)


# ---- calculate_rsi ----------------------------------------------------------


def test_rsi_returns_all_none_when_too_few_prices(run_brk):
    result = run_brk.calculate_rsi([1, 2, 3], periods=14)
    assert result == [None, None, None]


def test_rsi_pure_uptrend_pegs_high(run_brk):
    """Strict uptrend: avg_loss=0, the code's guard sets rs=100 (a literal),
    so RSI = 100 - 100/(1+100) ≈ 99.01. Never quite 100, but always near it."""
    prices = list(range(1, 30))  # 1, 2, ..., 29
    result = run_brk.calculate_rsi(prices, periods=14)
    assert all(v is None for v in result[:14])
    expected = 100 - 100 / 101  # ≈ 99.0099
    for v in result[14:]:
        assert v == pytest.approx(expected)


def test_rsi_constant_series_uses_zero_loss_guard(run_brk):
    """All deltas are 0: gains and losses both 0 → guard sets rs=100."""
    prices = [10] * 30
    result = run_brk.calculate_rsi(prices, periods=14)
    assert all(v is None for v in result[:14])
    assert result[14] == pytest.approx(100 - 100 / 101)


def test_rsi_known_textbook_example(run_brk):
    """
    Wilder's classic 14-period RSI example. Source: J. Welles Wilder,
    'New Concepts in Technical Trading Systems' (1978), reproduced in
    most TA references. 16 closing prices.

    Expected (from this codebase's implementation): RSI[14] ≈ 70.46.
    Most TA references give a value in the 70.4-70.5 range. We pin to
    a tight tolerance so any future drift in the math is caught.
    """
    prices = [
        44.34, 44.09, 44.15, 43.61, 44.33,
        44.83, 45.10, 45.42, 45.84, 46.08,
        45.89, 46.03, 45.61, 46.28, 46.28,
        46.00,
    ]
    result = run_brk.calculate_rsi(prices, periods=14)
    assert result[14] == pytest.approx(70.464, abs=0.05)
    assert result[15] == pytest.approx(66.250, abs=0.05)


# ---- true_range / average_true_range ---------------------------------------


def test_true_range_first_bar_is_high_minus_low(run_brk):
    """When there is no prev_close, TR = high - low."""
    bar = {"high": 10.0, "low": 8.0, "close": 9.0}
    assert run_brk.true_range(bar) == 2.0


def test_true_range_uses_prev_close_when_gapping(run_brk):
    """Gap-up bar: prev_close 5, current high 12 low 11 → TR = max(1, 7, 6) = 7."""
    bar = {"high": 12.0, "low": 11.0, "close": 11.5}
    assert run_brk.true_range(bar, prev_close=5.0) == 7.0


def test_true_range_inside_bar(run_brk):
    """Inside bar: high < prev_close, low > prev_close means standard high-low TR."""
    bar = {"high": 10.0, "low": 9.0, "close": 9.5}
    assert run_brk.true_range(bar, prev_close=9.5) == pytest.approx(1.0)


def test_atr_constant_bars(run_brk):
    """If every bar has the same range and no gaps, ATR = bar range."""
    rows = [{"high": 11.0, "low": 9.0, "close": 10.0} for _ in range(20)]
    atr = run_brk.average_true_range(rows, lookback=14)
    assert atr == pytest.approx(2.0)


def test_atr_empty_rows(run_brk):
    assert run_brk.average_true_range([], lookback=14) is None


def test_atr_uses_only_lookback_window(run_brk):
    """Bars before the lookback window must not influence ATR.

    Implementation re-initializes prev_close=None at the start of the lookback
    window, so even a huge gap in the prior bars cannot leak in. The first bar
    in the window's TR therefore uses just (high - low), not the prior close.
    """
    # 100 wide bars then 14 narrow bars
    rows = [{"high": 100.0, "low": 0.0, "close": 50.0}] * 100
    rows += [{"high": 11.0, "low": 9.0, "close": 10.0}] * 14
    atr = run_brk.average_true_range(rows, lookback=14)
    # Window: 14 narrow bars. First TR = high-low = 2 (no prev_close).
    # Each subsequent TR = max(2, |11-10|, |9-10|) = 2. Average = 2.0
    assert atr == pytest.approx(2.0)


def test_atr_with_actual_gap_inside_window(run_brk):
    """Confirm gaps between bars *inside* the window do raise TR (and ATR)."""
    rows = [
        {"high": 11.0, "low": 9.0, "close": 10.0},   # TR = 2 (no prev_close)
        {"high": 21.0, "low": 19.0, "close": 20.0},  # TR = max(2, |21-10|, |19-10|) = 11
    ]
    atr = run_brk.average_true_range(rows, lookback=2)
    assert atr == pytest.approx((2 + 11) / 2)


# ---- calculate_indicators integration --------------------------------------


def test_calculate_indicators_attaches_keys(run_brk):
    """Smoke test: feeding a synthetic OHLC series should return rows
    enriched with ma20/rsi14/macd/macd_signal/macd_hist keys."""
    rows = [{"close": float(i + 1), "high": i + 2, "low": i, "volume": 1.0} for i in range(40)]
    result = run_brk.calculate_indicators(rows)
    assert len(result) == 40
    last = result[-1]
    for key in ("ma20", "rsi14", "macd", "macd_signal", "macd_hist"):
        assert key in last
    # MA20 over the last 20 of [1..40] = average(21..40) = 30.5
    assert last["ma20"] == pytest.approx(30.5)
    # RSI in a strict uptrend pegs at 100 - 100/101 ≈ 99.01 (see test_rsi_pure_uptrend_pegs_high)
    assert last["rsi14"] == pytest.approx(100 - 100 / 101)
    # All values should be finite floats
    for key in ("ma20", "rsi14", "macd", "macd_signal", "macd_hist"):
        assert last[key] is not None
        assert math.isfinite(last[key])


def test_calculate_indicators_empty_rows(run_brk):
    assert run_brk.calculate_indicators([]) == []
