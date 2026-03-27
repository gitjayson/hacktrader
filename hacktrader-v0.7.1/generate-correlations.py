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
LOCKS_DIR = ROOT / 'correlation-locks'
STATUS_PATH = ROOT / 'correlation-status.json'

DEFAULT_SYMBOLS = ['SPY', 'QQQ', 'XLK', 'XLF', 'XLY', 'IWM', 'TLT', 'GLD', 'SLV', 'XOP', 'WTI', 'UNG', 'UUP', 'SMH']
ALWAYS_INCLUDE = [
    {'symbol': 'WTI', 'relation': 'positive'},
    {'symbol': 'UNG', 'relation': 'positive'},
    {'symbol': 'UUP', 'relation': 'negative'},
]
NEGATIVE_MACRO = {'UUP', 'TLT'}
POSITIVE_THEMATIC = {'SPY', 'QQQ', 'IWM', 'SMH', 'XLK', 'XLY', 'XLF', 'XOP', 'GLD', 'SLV', 'WTI', 'UNG'}
MAX_RELATIONSHIPS = 12
MIN_SERIES_POINTS = 25
LOCK_STALE_SECONDS = 60 * 20


def utc_now_iso():
    return datetime.now(timezone.utc).replace(microsecond=0).isoformat().replace('+00:00', 'Z')


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


def normalize_symbol(symbol):
    symbol = ''.join(ch for ch in str(symbol).upper().strip() if ch.isalnum() or ch in '._-')
    return symbol[:16]


def load_universe_symbols():
    universe = load_json(UNIVERSE_PATH, {'symbols': [], 'seen': {}})
    watchlist = load_json(WATCHLIST_PATH, [])
    symbols = set(DEFAULT_SYMBOLS)
    for symbol in universe.get('symbols', []):
        sym = normalize_symbol(symbol)
        if sym:
            symbols.add(sym)
    for symbol in watchlist:
        sym = normalize_symbol(symbol)
        if sym:
            symbols.add(sym)
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


def sanitize_relationships(target, relationships):
    clean = []
    seen = {target}
    for item in relationships:
        sym = normalize_symbol(item.get('symbol', ''))
        if not sym or sym in seen:
            continue
        rel = 'negative' if str(item.get('relation', 'positive')).lower() == 'negative' else 'positive'
        clean.append({'symbol': sym, 'relation': rel})
        seen.add(sym)
        if len(clean) >= MAX_RELATIONSHIPS:
            break
    return clean


def build_relationships_for_symbol(target, return_map):
    base = return_map.get(target)
    if not base:
        return sanitize_relationships(target, [dict(item) for item in ALWAYS_INCLUDE])

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
    seen = {target}
    for item in ALWAYS_INCLUDE:
        sym = normalize_symbol(item['symbol'])
        if sym and sym not in seen:
            selected.append({'symbol': sym, 'relation': item['relation']})
            seen.add(sym)

    for item in candidates:
        symbol = normalize_symbol(item['symbol'])
        if not symbol or symbol in seen:
            continue
        selected.append({'symbol': symbol, 'relation': item['relation']})
        seen.add(symbol)
        if len(selected) >= MAX_RELATIONSHIPS:
            break

    if len(selected) < MAX_RELATIONSHIPS:
        for symbol in DEFAULT_SYMBOLS:
            sym = normalize_symbol(symbol)
            if not sym or sym == target or sym in seen:
                continue
            selected.append({'symbol': sym, 'relation': heuristic_relation(sym, None)})
            seen.add(sym)
            if len(selected) >= MAX_RELATIONSHIPS:
                break

    return sanitize_relationships(target, selected[:MAX_RELATIONSHIPS])


def load_status_map():
    return load_json(STATUS_PATH, {})


def save_status_map(status_map):
    save_json_atomic(STATUS_PATH, dict(sorted(status_map.items())))


def set_status(symbol, **fields):
    status_map = load_status_map()
    current = status_map.get(symbol, {})
    current.update(fields)
    status_map[symbol] = current
    save_status_map(status_map)


def remove_stale_lock(symbol):
    lock_path = LOCKS_DIR / f'{symbol}.lock'
    if lock_path.exists() and (datetime.now().timestamp() - lock_path.stat().st_mtime) > LOCK_STALE_SECONDS:
        try:
            lock_path.unlink()
        except Exception:
            pass


def acquire_lock(symbol):
    LOCKS_DIR.mkdir(exist_ok=True)
    remove_stale_lock(symbol)
    lock_path = LOCKS_DIR / f'{symbol}.lock'
    try:
        fd = os.open(lock_path, os.O_CREAT | os.O_EXCL | os.O_WRONLY)
    except FileExistsError:
        return None, lock_path
    with os.fdopen(fd, 'w') as f:
        f.write(json.dumps({'symbol': symbol, 'started_at': utc_now_iso(), 'pid': os.getpid()}) + '\n')
    return lock_path, lock_path


def release_lock(lock_path):
    try:
        Path(lock_path).unlink(missing_ok=True)
    except Exception:
        pass


def update_correlations(symbol, relationships):
    correlations = load_json(CORRELATIONS_PATH, {})
    correlations[symbol] = relationships
    save_json_atomic(CORRELATIONS_PATH, dict(sorted(correlations.items())))


def main(argv):
    requested = [normalize_symbol(arg) for arg in argv[1:] if str(arg).strip()]
    requested = [sym for sym in requested if sym]
    universe = load_universe_symbols()
    if requested:
        universe = sorted(set(universe).union(requested))

    if not requested:
        requested = sorted(universe)

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

    updated = []
    for symbol in requested:
        lock_path, _ = acquire_lock(symbol)
        if lock_path is None:
            continue
        try:
            set_status(symbol, status='pending', requested_at=utc_now_iso(), updated_at=utc_now_iso(), source='correlation-research')
            relationships = build_relationships_for_symbol(symbol, return_map)
            update_correlations(symbol, relationships)
            set_status(symbol, status='ready', updated_at=utc_now_iso(), source='correlation-research', count=len(relationships), error=None)
            updated.append(symbol)
        except Exception as exc:
            set_status(symbol, status='failed', updated_at=utc_now_iso(), source='correlation-research', error=str(exc))
        finally:
            release_lock(lock_path)

    print(f'Updated correlations for {len(updated)} symbol(s): {", ".join(updated)}')


if __name__ == '__main__':
    main(sys.argv)
