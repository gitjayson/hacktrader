#!/usr/bin/env python3
import json
import math
import os
import statistics
import subprocess
import sys
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parent
UNIVERSE_PATH = ROOT / 'focus-universe.json'
WATCHLIST_PATH = ROOT / 'market-watchlist.json'
CORRELATIONS_PATH = ROOT / 'correlations.json'
RUN_BRK_PATH = ROOT / 'run-brk.py'
OUTPUT_TMP = ROOT / 'correlations.json.tmp'

DEFAULT_SYMBOLS = ['SPY', 'QQQ', 'XLK', 'XLF', 'XLY', 'IWM', 'TLT', 'GLD', 'SLV', 'XOP', 'WTI', 'UNG', 'UUP', 'SMH']
ALWAYS_INCLUDE = [
    {'symbol': 'WTI', 'relation': 'positive'},
    {'symbol': 'UNG', 'relation': 'positive'},
    {'symbol': 'UUP', 'relation': 'negative'},
]
NEGATIVE_MACRO = {'UUP', 'TLT'}
POSITIVE_THEMATIC = {'SPY', 'QQQ', 'IWM', 'SMH', 'XLK', 'XLY', 'XLF', 'XOP', 'GLD', 'SLV', 'WTI', 'UNG'}
MAX_RELATIONSHIPS = 12
LOOKBACK = '90'
INTERVAL = '1day'
DISPLAY = '1-day'
MIN_SERIES_POINTS = 25


def load_json(path, default):
    if not path.exists():
        return default
    try:
        return json.loads(path.read_text())
    except Exception:
        return default


def save_json_atomic(path, payload):
    tmp = path.with_suffix(path.suffix + '.tmp')
    tmp.write_text(json.dumps(payload, indent=2) + '\n')
    tmp.replace(path)


def load_universe_symbols():
    universe = load_json(UNIVERSE_PATH, {'symbols': [], 'seen': {}})
    watchlist = load_json(WATCHLIST_PATH, [])
    symbols = set(DEFAULT_SYMBOLS)
    for symbol in universe.get('symbols', []):
        if isinstance(symbol, str) and symbol.strip():
            symbols.add(symbol.strip().upper())
    for symbol in watchlist:
        if isinstance(symbol, str) and symbol.strip():
            symbols.add(symbol.strip().upper())
    return sorted(symbols)


def fetch_series(symbol):
    pycode = r'''
import json
import sys
try:
    import yfinance as yf
except Exception as e:
    print(json.dumps({'error': f'yfinance import failed: {e}'}))
    raise SystemExit(0)

symbol = sys.argv[1]
try:
    df = yf.Ticker(symbol).history(interval='1d', period='1y', auto_adjust=False, prepost=False)
    if df is None or df.empty:
        print(json.dumps({'error': 'No price history'}))
        raise SystemExit(0)
    closes = []
    for idx, row in df.iterrows():
        close = row.get('Close')
        if close is None:
            continue
        try:
            closes.append(float(close))
        except Exception:
            continue
    print(json.dumps({'closes': closes}))
except Exception as e:
    print(json.dumps({'error': str(e)}))
'''
    proc = subprocess.run(['python3', '-c', pycode, symbol], capture_output=True, text=True)
    try:
        payload = json.loads(proc.stdout or '{}')
    except Exception:
        return None
    closes = payload.get('closes')
    if not isinstance(closes, list) or len(closes) < MIN_SERIES_POINTS:
        return None
    return closes


def returns_from_closes(closes):
    out = []
    for i in range(1, len(closes)):
        prev = closes[i - 1]
        cur = closes[i]
        if prev in (None, 0):
            continue
        try:
            out.append((float(cur) / float(prev)) - 1.0)
        except Exception:
            continue
    return out


def pearson(a, b):
    n = min(len(a), len(b))
    if n < MIN_SERIES_POINTS:
        return None
    a = a[-n:]
    b = b[-n:]
    ma = statistics.mean(a)
    mb = statistics.mean(b)
    num = sum((x - ma) * (y - mb) for x, y in zip(a, b))
    da = math.sqrt(sum((x - ma) ** 2 for x in a))
    db = math.sqrt(sum((y - mb) ** 2 for y in b))
    if da == 0 or db == 0:
        return None
    return num / (da * db)


def heuristic_relation(symbol, corr_value):
    if symbol in NEGATIVE_MACRO:
        return 'negative'
    if corr_value is not None:
        return 'negative' if corr_value < 0 else 'positive'
    return 'positive' if symbol in POSITIVE_THEMATIC else 'negative'


def candidate_payload(symbol, corr_value):
    rel = heuristic_relation(symbol, corr_value)
    score = abs(corr_value) if corr_value is not None else 0.0
    if symbol in POSITIVE_THEMATIC:
        score += 0.03
    if symbol in NEGATIVE_MACRO:
        score += 0.03
    return {'symbol': symbol, 'relation': rel, 'score': score, 'corr': corr_value}


def build_relationships_for_symbol(target, return_map):
    base = return_map.get(target)
    if not base:
        return [dict(item) for item in ALWAYS_INCLUDE]

    candidates = []
    for other, series in return_map.items():
        if other == target:
            continue
        corr = pearson(base, series)
        if corr is None:
            continue
        if abs(corr) < 0.15 and other not in POSITIVE_THEMATIC and other not in NEGATIVE_MACRO:
            continue
        candidates.append(candidate_payload(other, corr))

    candidates.sort(key=lambda item: item['score'], reverse=True)

    selected = []
    seen = set()
    for item in ALWAYS_INCLUDE:
        selected.append({'symbol': item['symbol'], 'relation': item['relation']})
        seen.add(item['symbol'])

    for item in candidates:
        symbol = item['symbol']
        if symbol in seen:
            continue
        selected.append({'symbol': symbol, 'relation': item['relation']})
        seen.add(symbol)
        if len(selected) >= MAX_RELATIONSHIPS:
            break

    if len(selected) < MAX_RELATIONSHIPS:
        for symbol in DEFAULT_SYMBOLS:
            if symbol == target or symbol in seen:
                continue
            selected.append({'symbol': symbol, 'relation': heuristic_relation(symbol, None)})
            seen.add(symbol)
            if len(selected) >= MAX_RELATIONSHIPS:
                break

    return selected[:MAX_RELATIONSHIPS]


def main(argv):
    requested = [arg.upper() for arg in argv[1:] if arg.strip()]
    universe = load_universe_symbols()
    if requested:
        for symbol in requested:
            if symbol not in universe:
                universe.append(symbol)
        universe = sorted(set(universe))

    closes_map = {}
    return_map = {}
    for symbol in universe:
        closes = fetch_series(symbol)
        if not closes:
            continue
        returns = returns_from_closes(closes)
        if len(returns) < MIN_SERIES_POINTS:
            continue
        closes_map[symbol] = closes
        return_map[symbol] = returns

    targets = requested if requested else sorted(return_map.keys())
    existing = load_json(CORRELATIONS_PATH, {})
    correlations = dict(existing)
    for symbol in targets:
        correlations[symbol] = build_relationships_for_symbol(symbol, return_map)

    save_json_atomic(CORRELATIONS_PATH, dict(sorted(correlations.items())))
    print(f'Updated correlations for {len(targets)} symbol(s)')


if __name__ == '__main__':
    main(sys.argv)
