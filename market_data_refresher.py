#!/usr/bin/env python3
"""
market_data_refresher.py — long-running daemon that keeps the shared
market-data cache hot for tickers actively being monitored by HackTrader
users.

v0.13.0 architecture:

  - The PHP layer (lib/cache.php) writes a "subscription" entry to the
    Redis sorted set "ht:subscriptions" every time a user touches a
    (ticker, period) pair. The score is the unix timestamp.

  - This daemon scans that set on each cycle:
      1. Drops subscriptions not accessed within the last DORMANT_SECONDS
         (default 1 hour). Their cache entries will TTL out naturally.
      2. For each active subscription, checks how long since its
         cache was last refreshed. If older than the period's refresh
         interval, fetches fresh bars from Massive and overwrites
         the cache.

  - The result: ONE Massive API call per active (ticker, period) per
    refresh cycle, regardless of how many users are viewing that ticker.
    Per-user cost decouples from per-user traffic.

Run as a systemd service (see hacktrader-refresher.service in the repo).
Sole owner of Massive API calls for live user traffic.

Cache hit rate, refresh durations, and Massive error counts are logged
to syslog (journald via systemd).
"""

from __future__ import annotations

import json
import logging
import os
import sys
import time
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path

import redis  # pip install redis

# Import the existing Massive fetcher + scorer from run-brk.py so we
# don't duplicate provider logic. run-brk.py is structured with pure
# functions (fetch_massive, compute_output) that we can call directly.
sys.path.insert(0, str(Path(__file__).resolve().parent))
try:
    # run-brk.py uses a hyphen in its name, so we need importlib to load it.
    import importlib.util

    _spec = importlib.util.spec_from_file_location(
        "run_brk",
        str(Path(__file__).resolve().parent / "run-brk.py"),
    )
    run_brk = importlib.util.module_from_spec(_spec)
    _spec.loader.exec_module(run_brk)
except Exception as e:  # pragma: no cover
    print(f"FATAL: could not import run-brk.py: {e}", file=sys.stderr)
    sys.exit(1)


# ---- Configuration ---------------------------------------------------------

REDIS_HOST = os.environ.get("HT_REDIS_HOST", "127.0.0.1")
REDIS_PORT = int(os.environ.get("HT_REDIS_PORT", "6379"))

# How long without access before a subscription is considered dormant
# and removed from the refresh set. After this, its cache entries TTL
# out naturally and the next user request will be a cold-start fetch.
DORMANT_SECONDS = int(os.environ.get("HT_DORMANT_SECONDS", "3600"))  # 1 hour

# How often this daemon does a full sweep of subscriptions. The actual
# per-subscription refresh interval is determined by its period (see
# REFRESH_INTERVAL_BY_PERIOD below); this is just how often we check
# whether any subscription is due for a refresh.
SWEEP_INTERVAL_SECONDS = int(os.environ.get("HT_SWEEP_INTERVAL", "10"))

# Max parallel Massive fetches per sweep. Stay polite — Massive will
# throttle if hammered.
MAX_PARALLEL_FETCHES = int(os.environ.get("HT_MAX_PARALLEL", "8"))

# Tickers to always keep warm regardless of user activity. The most
# popular focus tickers benefit from being pre-refreshed so the first
# user of the morning doesn't see a cold-start latency.
#
# v0.13.0 — expanded from 22 → 40. Added the most commonly-queried
# retail tickers: a couple more mega-caps, popular volatility names,
# the bank bellwethers, semiconductors, and a few more macro ETFs.
# Memory impact is trivial (40 cached scores at ~5KB each = ~200KB).
PRE_WARM_TICKERS = [
    # Broad-market ETFs
    "SPY", "QQQ", "IWM", "DIA", "VTI",
    # Mega-cap tech (FAANG-era + recent additions)
    "NVDA", "TSLA", "AAPL", "MSFT", "AMZN", "GOOGL", "META", "AVGO",
    # Popular volatility / momentum names
    "AMD", "NFLX", "SHOP", "COIN", "MSTR",
    # Bank bellwethers
    "JPM", "BAC", "WFC",
    # Sector ETFs
    "XLK", "XLF", "XLE", "XLV", "XLY", "XLI", "XLP", "XLC",
    # Semiconductors (oversampled because the basket strongly correlates here)
    "SMH", "SOXX",
    # Macro / cross-asset
    "GLD", "SLV", "TLT", "UUP", "HYG", "LQD", "IEF",
    # International (low-cost defensive include — they show up in macro baskets)
    "EEM", "FXI",
]
PRE_WARM_PERIOD = "5m"  # 5m is the default dashboard period
PRE_WARM_LOOKBACK = 100

# Refresh-interval table — how often we recompute each period's cache.
# These match lib/cache.php's ht_cache_refresh_interval_seconds().
#
# v0.13.0 — relaxed from initial values (15s/60s/300s/1800s) after
# verifying the architecture works. The 15-minute upstream delay from
# Massive means more-aggressive refresh isn't gaining the user
# anything; doubling these intervals halves the Massive load with
# zero perceptible impact. Tune further once we observe real usage
# patterns. With a real-time data feed in the future, drop these.
REFRESH_INTERVAL_BY_PERIOD = {
    "1m": 30,      # API endpoint still works even though UI no longer offers 1m
    "5m": 120,     # 2 min — still 2.5 refreshes per 5-min bar cycle
    "1h": 600,     # 10 min — 6 refreshes per 1-hour bar
    "1d": 3600,    # 1 hour — 24 refreshes per daily bar
}
DEFAULT_REFRESH_INTERVAL = 120

SUBSCRIPTION_KEY = "ht:subscriptions"
BARS_KEY_PREFIX = "bars"
SCORE_KEY_PREFIX = "score"
LAST_REFRESH_KEY_PREFIX = "lastref"

# ---- Logging ---------------------------------------------------------------

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] refresher: %(message)s",
    datefmt="%Y-%m-%d %H:%M:%S",
)
log = logging.getLogger("refresher")


# ---- Redis connection ------------------------------------------------------

def make_redis_client() -> redis.Redis:
    """Connect to Redis. Retries with backoff if Redis is not yet up
    (matters during boot when redis-server and our daemon start in
    parallel via systemd)."""
    for attempt in range(10):
        try:
            client = redis.Redis(
                host=REDIS_HOST,
                port=REDIS_PORT,
                decode_responses=True,
                socket_connect_timeout=2,
                socket_timeout=2,
            )
            client.ping()
            return client
        except redis.RedisError as e:
            wait = min(2 ** attempt, 30)
            log.warning("Redis not ready (attempt %d): %s; sleeping %ds", attempt + 1, e, wait)
            time.sleep(wait)
    raise RuntimeError("Could not connect to Redis after 10 attempts")


# ---- Subscription management -----------------------------------------------

def parse_subscription_key(key: str):
    """Subscription keys look like 'TSLA:5m:100' — ticker:period:lookback."""
    parts = key.split(":")
    if len(parts) != 3:
        return None
    ticker, period, lookback = parts
    try:
        lookback_int = int(lookback)
    except ValueError:
        return None
    return ticker, period, lookback_int


def drop_dormant_subscriptions(r: redis.Redis) -> int:
    """Remove subscriptions not accessed within DORMANT_SECONDS.
    Returns the number dropped."""
    cutoff = time.time() - DORMANT_SECONDS
    return r.zremrangebyscore(SUBSCRIPTION_KEY, "-inf", cutoff)


def list_active_subscriptions(r: redis.Redis):
    """Return all subscription keys currently considered active (still
    in the sorted set after dormant ones were pruned)."""
    return r.zrange(SUBSCRIPTION_KEY, 0, -1)


def ensure_prewarm_subscriptions(r: redis.Redis) -> None:
    """Insert pre-warm subscriptions if they're not already there. We
    write with a slightly-stale timestamp so user-touched subscriptions
    are still scanned before our defaults — order doesn't actually
    matter but it's polite."""
    now = time.time()
    seed_ts = now - 60  # 1 minute ago, so user-touched entries sort later
    mapping = {}
    for ticker in PRE_WARM_TICKERS:
        key = f"{ticker}:{PRE_WARM_PERIOD}:{PRE_WARM_LOOKBACK}"
        mapping[key] = seed_ts
    # ZADD GT (greater than) so we don't overwrite a more-recent user touch.
    # Predis equivalent in PHP land is the same; redis-py 4.x supports gt=True.
    try:
        r.zadd(SUBSCRIPTION_KEY, mapping, gt=True)
    except TypeError:
        # Older redis-py without gt parameter — fall back to set-if-absent.
        for k, score in mapping.items():
            if r.zscore(SUBSCRIPTION_KEY, k) is None:
                r.zadd(SUBSCRIPTION_KEY, {k: score})


# ---- Refresh logic ---------------------------------------------------------

def refresh_one(r: redis.Redis, subscription_key: str) -> None:
    """Fetch fresh bars and compute scored output for one subscription.
    Writes to the bars and score caches, updates the last-refreshed
    timestamp. Logs and swallows errors so one bad ticker doesn't
    bring down the sweep."""
    parsed = parse_subscription_key(subscription_key)
    if parsed is None:
        log.warning("Bad subscription key, dropping: %s", subscription_key)
        r.zrem(SUBSCRIPTION_KEY, subscription_key)
        return
    ticker, period, lookback = parsed

    # Map our period token (5m) to Massive's interval string (5min) and
    # display string (5-min) — same translation table api.php uses.
    interval_map = {
        "1m":  ("1min",  "1-min"),
        "5m":  ("5min",  "5-min"),
        "1h":  ("1h",    "1-hour"),
        "1d":  ("1day",  "1-day"),
    }
    if period not in interval_map:
        log.warning("Unknown period %s for %s, dropping", period, ticker)
        r.zrem(SUBSCRIPTION_KEY, subscription_key)
        return
    interval, display = interval_map[period]

    start = time.time()
    try:
        # fetch_massive returns a 3-tuple (values, source, error). When
        # error is non-null or values is empty, the fetch failed and we
        # skip caching this cycle.
        result = run_brk.fetch_massive(ticker, interval, lookback)
        if not result or len(result) != 3:
            log.warning("Unexpected fetch_massive return for %s %s", ticker, period)
            return
        values, source_name, fetch_error = result
        if fetch_error or not values:
            log.warning("Fetch failed for %s %s: %s",
                        ticker, period, fetch_error or "empty values")
            return
        scored = run_brk.compute_output(
            ticker, interval, display, str(lookback), values,
            source=source_name or "massive",
        )
    except Exception as e:
        log.warning("Refresh failed for %s %s: %s", ticker, period, e)
        return
    elapsed_ms = (time.time() - start) * 1000

    # Write to cache. TTL is 1 hour so if the daemon dies, entries don't
    # become permanently stale — they expire on their own.
    bars_key = f"{BARS_KEY_PREFIX}:{subscription_key}"
    score_key = f"{SCORE_KEY_PREFIX}:{subscription_key}"
    last_ref_key = f"{LAST_REFRESH_KEY_PREFIX}:{subscription_key}"
    try:
        pipe = r.pipeline()
        pipe.setex(bars_key, DORMANT_SECONDS, json.dumps(values))
        pipe.setex(score_key, DORMANT_SECONDS, json.dumps(scored))
        pipe.setex(last_ref_key, DORMANT_SECONDS, str(int(time.time())))
        pipe.execute()
    except redis.RedisError as e:
        log.error("Redis write failed for %s: %s", subscription_key, e)
        return

    log.debug("Refreshed %s in %.0fms", subscription_key, elapsed_ms)


def is_due_for_refresh(r: redis.Redis, subscription_key: str) -> bool:
    """Has enough time elapsed since the last refresh of this
    subscription? If we've never refreshed it, yes."""
    parsed = parse_subscription_key(subscription_key)
    if parsed is None:
        return False
    _, period, _ = parsed
    interval = REFRESH_INTERVAL_BY_PERIOD.get(period, DEFAULT_REFRESH_INTERVAL)

    last_ref_key = f"{LAST_REFRESH_KEY_PREFIX}:{subscription_key}"
    last = r.get(last_ref_key)
    if last is None:
        return True
    try:
        return (time.time() - int(last)) >= interval
    except ValueError:
        return True


# ---- Main loop -------------------------------------------------------------

def sweep_once(r: redis.Redis) -> None:
    """One full pass: prune dormants, ensure prewarm, refresh due."""
    dropped = drop_dormant_subscriptions(r)
    if dropped:
        log.info("Dropped %d dormant subscription(s)", dropped)
    ensure_prewarm_subscriptions(r)

    active = list_active_subscriptions(r)
    due = [k for k in active if is_due_for_refresh(r, k)]
    if not due:
        return

    log.info("Refreshing %d of %d active subscriptions", len(due), len(active))
    with ThreadPoolExecutor(max_workers=MAX_PARALLEL_FETCHES) as pool:
        list(pool.map(lambda k: refresh_one(r, k), due))


def main() -> int:
    log.info("HackTrader market data refresher starting")
    log.info("Redis: %s:%d  sweep=%ds  dormant=%ds  parallel=%d",
             REDIS_HOST, REDIS_PORT, SWEEP_INTERVAL_SECONDS,
             DORMANT_SECONDS, MAX_PARALLEL_FETCHES)

    r = make_redis_client()
    log.info("Redis connected; entering sweep loop")

    while True:
        try:
            sweep_once(r)
        except redis.ConnectionError as e:
            log.error("Redis connection lost: %s; reconnecting", e)
            time.sleep(5)
            try:
                r = make_redis_client()
            except Exception as conn_err:
                log.error("Reconnect failed: %s", conn_err)
                time.sleep(10)
        except Exception as e:
            log.exception("Unhandled sweep error: %s", e)
        time.sleep(SWEEP_INTERVAL_SECONDS)


if __name__ == "__main__":
    sys.exit(main())
