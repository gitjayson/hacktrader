"""
Tests for the level/pivot/cluster algorithms in run-brk.py.
These power the support/resistance ladder on the dashboard.
"""

from __future__ import annotations

# ---- find_pivots ------------------------------------------------------------


def _bar(high: float, low: float, close: float | None = None) -> dict:
    return {"high": high, "low": low, "close": close if close is not None else (high + low) / 2}


def test_find_pivots_too_few_rows(run_brk):
    """With left=right=2 we need at least 5 rows to detect anything."""
    rows = [_bar(1, 0), _bar(2, 1), _bar(1, 0)]  # only 3 rows
    highs, lows = run_brk.find_pivots(rows, left=2, right=2)
    assert highs == []
    assert lows == []


def test_find_pivots_detects_local_high(run_brk):
    """A clear local high in the middle of the window should be detected."""
    # bars: low low low HIGH low low low
    rows = [_bar(1, 0), _bar(1, 0), _bar(1, 0), _bar(5, 4), _bar(1, 0), _bar(1, 0), _bar(1, 0)]
    highs, _ = run_brk.find_pivots(rows, left=2, right=2)
    assert 5 in highs


def test_find_pivots_detects_local_low(run_brk):
    rows = [_bar(2, 1), _bar(2, 1), _bar(2, 1), _bar(2, 0.5), _bar(2, 1), _bar(2, 1), _bar(2, 1)]
    _, lows = run_brk.find_pivots(rows, left=2, right=2)
    assert 0.5 in lows


def test_find_pivots_flat_window_treated_as_pivot(run_brk):
    """Implementation uses >= and <= comparisons, so flat bars are pivots."""
    rows = [_bar(5, 4)] * 7
    highs, lows = run_brk.find_pivots(rows, left=2, right=2)
    # Every interior bar qualifies — first 2 and last 2 are excluded by the window
    assert len(highs) == 3
    assert len(lows) == 3


# ---- cluster_levels ---------------------------------------------------------


def test_cluster_levels_groups_nearby_prices(run_brk):
    """Three prices within bucket_width get clustered into one level."""
    levels = [100.0, 100.2, 100.4]
    out = run_brk.cluster_levels(levels, bucket_width=0.5, min_touches=2)
    assert len(out) == 1
    assert out[0]["touches"] == 3
    assert out[0]["price"] == (100.0 + 100.2 + 100.4) / 3


def test_cluster_levels_separates_distant_prices(run_brk):
    """Prices farther apart than bucket_width should stay separate clusters."""
    levels = [50.0, 50.05, 60.0, 60.05]
    out = run_brk.cluster_levels(levels, bucket_width=0.5, min_touches=2)
    prices = sorted(item["price"] for item in out)
    assert len(prices) == 2
    assert prices[0] == 50.025
    assert prices[1] == 60.025


def test_cluster_levels_drops_clusters_below_min_touches(run_brk):
    """Singletons (and below min_touches) are filtered out."""
    levels = [100.0, 100.1, 100.2, 200.0]  # 200 is alone
    out = run_brk.cluster_levels(levels, bucket_width=0.5, min_touches=2)
    prices = [item["price"] for item in out]
    assert all(99 < p < 101 for p in prices)


def test_cluster_levels_empty(run_brk):
    assert run_brk.cluster_levels([], bucket_width=1.0) == []


def test_cluster_levels_sorted_by_touches_desc(run_brk):
    """Most-touched levels should rank first."""
    # cluster A: 5 touches around 50, cluster B: 2 touches around 60
    levels = [50.0, 50.1, 50.2, 50.3, 50.4, 60.0, 60.1]
    out = run_brk.cluster_levels(levels, bucket_width=1.0, min_touches=2)
    assert out[0]["touches"] == 5
    assert out[1]["touches"] == 2


def test_cluster_levels_top_n_caps_output(run_brk):
    levels = []
    # Build 5 distinct clusters of 2 touches each, well-separated
    for i in range(5):
        base = 100 + i * 10
        levels.extend([base, base + 0.05])
    out = run_brk.cluster_levels(levels, bucket_width=0.5, min_touches=2, top_n=3)
    assert len(out) == 3


# ---- nearest_levels ---------------------------------------------------------


def test_nearest_levels_above(run_brk):
    levels = [
        {"price": 90.0},
        {"price": 95.0},
        {"price": 105.0},
        {"price": 110.0},
        {"price": 120.0},
    ]
    out = run_brk.nearest_levels(levels, current=100.0, direction="above", count=2)
    assert [lvl["price"] for lvl in out] == [105.0, 110.0]


def test_nearest_levels_below(run_brk):
    levels = [
        {"price": 90.0},
        {"price": 95.0},
        {"price": 105.0},
        {"price": 110.0},
    ]
    out = run_brk.nearest_levels(levels, current=100.0, direction="below", count=2)
    assert [lvl["price"] for lvl in out] == [95.0, 90.0]


def test_nearest_levels_handles_no_match(run_brk):
    levels = [{"price": 50.0}]
    assert run_brk.nearest_levels(levels, current=100.0, direction="above", count=2) == []


# ---- recent_extremes --------------------------------------------------------


def test_recent_extremes_uses_window(run_brk):
    rows = [_bar(i + 1, i) for i in range(50)]  # 50 bars: highs 1..50, lows 0..49
    out = run_brk.recent_extremes(rows, window=20)
    # last 20 bars are bars 31..50 (highs 31..50, lows 30..49)
    assert out["window_bars"] == 20
    assert out["recent_high"] == 50.0
    assert out["recent_low"] == 30.0


def test_recent_extremes_short_input(run_brk):
    """If there are fewer rows than window, use all of them."""
    rows = [_bar(5, 1), _bar(7, 2)]
    out = run_brk.recent_extremes(rows, window=20)
    assert out["window_bars"] == 2
    assert out["recent_high"] == 7.0
    assert out["recent_low"] == 1.0


def test_recent_extremes_empty(run_brk):
    assert run_brk.recent_extremes([], window=20) is None


# ---- count_failed_attempts --------------------------------------------------


def test_count_failed_attempts_up_direction(run_brk):
    """Bars that touch a level from below but close back below count as failed attempts."""
    level = 100.0
    bucket = 0.5
    # Bar 1: pokes above the level, closes well below = failed attempt
    # Bar 2: stays away
    # Bar 3: another poke, closes below = second failed attempt (separated by a non-touch bar)
    rows = [
        {"high": 100.4, "low": 99.0, "close": 99.0},
        {"high": 99.0, "low": 98.0, "close": 98.5},
        {"high": 100.6, "low": 99.5, "close": 99.0},
    ]
    n = run_brk.count_failed_attempts(rows, level, "up", bucket)
    assert n == 2


def test_count_failed_attempts_no_level(run_brk):
    rows = [{"high": 1, "low": 0, "close": 0.5}]
    assert run_brk.count_failed_attempts(rows, None, "up", 0.5) == 0


def test_count_failed_attempts_empty(run_brk):
    assert run_brk.count_failed_attempts([], 100.0, "up", 0.5) == 0
