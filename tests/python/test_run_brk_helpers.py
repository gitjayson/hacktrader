"""
Tests for the small pure helpers in run-brk.py.
These are the easy ones that catch dumb regressions cheaply.
"""

from __future__ import annotations


# ---- safe_float -------------------------------------------------------------


def test_safe_float_passes_through_numbers(run_brk):
    assert run_brk.safe_float(1.5) == 1.5
    assert run_brk.safe_float(0) == 0.0
    assert run_brk.safe_float(-3) == -3.0


def test_safe_float_parses_numeric_strings(run_brk):
    assert run_brk.safe_float("42") == 42.0
    assert run_brk.safe_float("3.14") == 3.14


def test_safe_float_returns_none_for_none(run_brk):
    assert run_brk.safe_float(None) is None


def test_safe_float_returns_none_for_garbage(run_brk):
    assert run_brk.safe_float("abc") is None
    assert run_brk.safe_float({}) is None
    assert run_brk.safe_float([]) is None


# ---- round_price / round_maybe ---------------------------------------------


def test_round_price_two_decimals(run_brk):
    assert run_brk.round_price(123.456) == 123.46
    assert run_brk.round_price(100) == 100.00
    assert run_brk.round_price(0.005) in (0.00, 0.01)  # banker's rounding allowed


def test_round_price_none(run_brk):
    assert run_brk.round_price(None) is None


def test_round_maybe_with_digits(run_brk):
    assert run_brk.round_maybe(1.23456, 3) == 1.235
    assert run_brk.round_maybe(1.23456, 0) == 1.0


def test_round_maybe_handles_none_and_garbage(run_brk):
    assert run_brk.round_maybe(None) is None
    assert run_brk.round_maybe("not a number") is None


# ---- clamp ------------------------------------------------------------------


def test_clamp_default_bounds(run_brk):
    assert run_brk.clamp(50.0) == 50.0
    assert run_brk.clamp(-10) == 0.0
    assert run_brk.clamp(150) == 100.0


def test_clamp_custom_bounds(run_brk):
    assert run_brk.clamp(0.5, low=0.0, high=1.0) == 0.5
    assert run_brk.clamp(-1, low=0.0, high=1.0) == 0.0
    assert run_brk.clamp(2, low=0.0, high=1.0) == 1.0


# ---- interval_to_minutes ----------------------------------------------------


def test_interval_to_minutes_known_intervals(run_brk):
    """Note the keys are backend names ('1min', '5min'), not display names."""
    assert run_brk.interval_to_minutes("1min") == 1
    assert run_brk.interval_to_minutes("5min") == 5
    assert run_brk.interval_to_minutes("1h") == 60
    # 1day = one US trading session = 390 minutes (6.5 hours)
    assert run_brk.interval_to_minutes("1day") == 390


def test_interval_to_minutes_unknown_falls_back_to_5(run_brk):
    assert run_brk.interval_to_minutes("nonsense") == 5
    assert run_brk.interval_to_minutes("") == 5


# ---- interval_to_massive_params --------------------------------------------


def test_interval_to_massive_params_known(run_brk):
    """Massive (polygon-style) takes a (multiplier, timespan) pair."""
    assert run_brk.interval_to_massive_params("1min")  == (1, "minute")
    assert run_brk.interval_to_massive_params("5min")  == (5, "minute")
    assert run_brk.interval_to_massive_params("1h")    == (1, "hour")
    assert run_brk.interval_to_massive_params("1day")  == (1, "day")
