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
SERVER_SECRETS_PATH = Path('/var/www/secrets.json')
LOCAL_SECRETS_PATH = ROOT / 'secrets.json'

DEFAULT_SYMBOLS = ['SPY', 'QQQ', 'XLK', 'XLF', 'XLY', 'IWM', 'TLT', 'GLD', 'SLV', 'XOP', 'WTI', 'UNG', 'UUP', 'SMH']
MAX_RELATIONSHIPS = 12
MIN_SERIES_POINTS = 25
LOCK_STALE_SECONDS = 60 * 20
WINDOW_SPECS = [(20, 0.45), (60, 0.35), (120, 0.15), (252, 0.05)]
MIN_ABS_SCORE = 0.18
CROSS_ASSET_MIN_SCORE = 0.34

NEGATIVE_MACRO = {'UUP', 'TLT'}
NOISY_SINGLE_NAME_EXCLUSIONS = {'RIVN', 'LCID', 'NIO', 'NKLA'}
GROUPS = {
    'market': {'SPY', 'QQQ', 'IWM', 'DIA'},
    'tech': {'AAPL', 'MSFT', 'GOOGL', 'META', 'AMZN', 'NFLX', 'SHOP', 'TSLA', 'XLC', 'XLY', 'QQQ'},
    'semis': {'NVDA', 'AMD', 'AVGO', 'INTC', 'SMH', 'SOXX', 'XLK'},
    'finance': {'XLF', 'KBE', 'KRE', 'JPM', 'BAC', 'GS', 'MS', 'WFC', 'SCHW'},
    'energy': {'WTI', 'UNG', 'XLE', 'XOP', 'XOM', 'CVX', 'SLB', 'OXY', 'HAL'},
    'rates': {'TLT', 'IEF', 'SHY', 'LQD', 'HYG'},
    'metals': {'GLD', 'SLV', 'GDX', 'NEM', 'AEM'},
    'fx': {'UUP', 'FXE', 'FXY'},
    'consumer': {'XLY', 'XLP', 'COST', 'WMT', 'HD', 'LOW', 'NKE', 'SBUX', 'AMZN'},
    'communication': {'XLC', 'META', 'GOOGL', 'NFLX', 'T'},
}
SYMBOL_TO_GROUP = {}
for group, symbols in GROUPS.items():
    for symbol in symbols:
        SYMBOL_TO_GROUP[symbol] = group

GROUP_FALLBACKS = {
    'market': ['SPY', 'QQQ', 'IWM', 'DIA', 'XLK', 'XLF', 'XLY', 'SMH'],
    'tech': ['QQQ', 'XLK', 'XLC', 'SPY', 'AMZN', 'META', 'GOOGL', 'SHOP'],
    'semis': ['SMH', 'XLK', 'QQQ', 'NVDA', 'AMD', 'AVGO', 'INTC', 'SPY'],
    'finance': ['XLF', 'KBE', 'KRE', 'SPY', 'DIA', 'JPM', 'BAC', 'GS'],
    'energy': ['WTI', 'UNG', 'XOP', 'XLE', 'SPY', 'SLV', 'GLD', 'UUP'],
    'rates': ['TLT', 'IEF', 'SHY', 'LQD', 'HYG', 'UUP', 'GLD', 'SPY'],
    'metals': ['GLD', 'SLV', 'GDX', 'TLT', 'UUP', 'SPY', 'WTI'],
    'fx': ['UUP', 'TLT', 'GLD', 'SPY', 'QQQ'],
    'consumer': ['XLY', 'XLP', 'SPY', 'QQQ', 'AMZN', 'COST', 'WMT', 'HD'],
    'communication': ['XLC', 'QQQ', 'SPY', 'META', 'GOOGL', 'NFLX', 'T'],
}
BASELINE = ['SPY', 'QQQ', 'IWM', 'DIA', 'XLK', 'XLF', 'XLY', 'SMH', 'TLT', 'GLD', 'UUP']
CURATED_PEERS = {
    'TSLA': [('QQQ','positive'),('XLK','positive'),('XLY','positive'),('NVDA','positive'),('AMD','positive'),('SPY','positive'),('IWM','positive'),('SMH','positive'),('UUP','negative'),('TLT','negative')],
    'NVDA': [('SMH','positive'),('XLK','positive'),('QQQ','positive'),('AMD','positive'),('AVGO','positive'),('SPY','positive'),('SOXX','positive'),('META','positive'),('UUP','negative'),('TLT','negative')],
    'AMD': [('SMH','positive'),('NVDA','positive'),('AVGO','positive'),('XLK','positive'),('QQQ','positive'),('SPY','positive'),('INTC','positive'),('SOXX','positive'),('UUP','negative'),('TLT','negative')],
    'SHOP': [('QQQ','positive'),('XLY','positive'),('SPY','positive'),('AMZN','positive'),('META','positive'),('GOOGL','positive'),('IWM','positive'),('XLC','positive'),('UUP','negative')],
    'SPY': [('QQQ','positive'),('IWM','positive'),('DIA','positive'),('XLK','positive'),('XLF','positive'),('XLY','positive'),('SMH','positive'),('TLT','negative'),('UUP','negative'),('GLD','negative')],
    'QQQ': [('SPY','positive'),('XLK','positive'),('SMH','positive'),('XLC','positive'),('NVDA','positive'),('AMD','positive'),('META','positive'),('GOOGL','positive'),('TLT','negative'),('UUP','negative')],
    'XLC': [('QQQ','positive'),('META','positive'),('GOOGL','positive'),('NFLX','positive'),('SPY','positive'),('XLY','positive'),('SHOP','positive'),('TLT','negative'),('UUP','negative')],
    'WTI': [('XOP','positive'),('UNG','positive'),('GLD','positive'),('SLV','positive'),('SPY','positive'),('XLE','positive'),('UUP','negative'),('TLT','negative')],
    'UNG': [('WTI','positive'),('XOP','positive'),('GLD','positive'),('UUP','negative'),('TLT','negative'),('SPY','negative')],
    'TLT': [('IEF','positive'),('SHY','positive'),('LQD','positive'),('GLD','positive'),('UUP','positive'),('SPY','negative'),('QQQ','negative'),('IWM','negative')],
    'GLD': [('SLV','positive'),('GDX','positive'),('TLT','positive'),('UUP','negative'),('SPY','negative'),('WTI','positive')],
}


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


def load_twelvedata_api_key():
    for path in (SERVER_SECRETS_PATH, LOCAL_SECRETS_PATH):
        secrets = load_json(path, {})
        api_key = secrets.get('TWELVEDATA_API_KEY')
        if api_key:
            return api_key
    return None


def fetch_series(symbol):
    api_key = load_twelvedata_api_key()
    if not api_key:
        return None
    url = f'https://api.twelvedata.com/time_series?symbol={symbol}&interval=1day&apikey={api_key}&outputsize=260'
    proc = subprocess.run(['curl', '-s', url], capture_output=True, text=True)
    try:
        payload = json.loads(proc.stdout or '{}')
    except Exception:
        return None
    values = payload.get('values')
    if not isinstance(values, list):
        return None
    closes = []
    for row in reversed(values):
        try:
            closes.append(float(row.get('close')))
        except Exception:
            continue
    return closes if len(closes) >= MIN_SERIES_POINTS else None


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


def group_for(symbol):
    return SYMBOL_TO_GROUP.get(symbol)


def relation_for(symbol, corr_value):
    if symbol in NEGATIVE_MACRO:
        return 'negative'
    return 'negative' if corr_value is not None and corr_value < 0 else 'positive'


def blended_score(a, b, target, other):
    weighted = 0.0
    used = 0.0
    for window, weight in WINDOW_SPECS:
        corr = pearson(a[-window:], b[-window:])
        if corr is None:
            continue
        weighted += abs(corr) * weight
        used += weight
    if used == 0:
        return None
    score = weighted / used

    target_group = group_for(target)
    other_group = group_for(other)
    if target_group and target_group == other_group:
        score += 0.10
    elif target_group and other_group and target_group != other_group:
        score -= 0.06

    if other in NEGATIVE_MACRO:
        if target_group in {'energy', 'rates', 'metals', 'fx'}:
            score += 0.03
        else:
            score -= 0.05

    if other in {'WTI', 'UNG'}:
        if target_group == 'energy':
            score += 0.10
        else:
            score -= 0.12

    return round(score, 6)


def candidate_payload(target, other, score, corr_sign):
    return {'symbol': other, 'relation': relation_for(other, corr_sign), 'score': score}


def fallback_relationships(target):
    curated = CURATED_PEERS.get(target, [])
    selected = []
    seen = {target}
    for symbol, relation in curated:
        sym = normalize_symbol(symbol)
        if sym and sym not in seen:
            selected.append({'symbol': sym, 'relation': relation})
            seen.add(sym)
            if len(selected) >= MAX_RELATIONSHIPS:
                return sanitize_relationships(target, selected)

    target_group = group_for(target)
    for symbol in GROUP_FALLBACKS.get(target_group, []):
        sym = normalize_symbol(symbol)
        if not sym or sym == target or sym in seen:
            continue
        selected.append({'symbol': sym, 'relation': 'negative' if sym in NEGATIVE_MACRO else 'positive'})
        seen.add(sym)
        if len(selected) >= MAX_RELATIONSHIPS:
            return sanitize_relationships(target, selected)

    for symbol in BASELINE:
        sym = normalize_symbol(symbol)
        if not sym or sym == target or sym in seen:
            continue
        selected.append({'symbol': sym, 'relation': 'negative' if sym in NEGATIVE_MACRO else 'positive'})
        seen.add(sym)
        if len(selected) >= MAX_RELATIONSHIPS:
            break

    return sanitize_relationships(target, selected)


def sanitize_relationships(target, relationships):
    clean = []
    seen = {target}
    for item in relationships:
        sym = normalize_symbol(item.get('symbol', ''))
        if not sym or sym in seen or sym in NOISY_SINGLE_NAME_EXCLUSIONS:
            continue
        rel = 'negative' if str(item.get('relation', 'positive')).lower() == 'negative' else 'positive'
        clean.append({'symbol': sym, 'relation': rel})
        seen.add(sym)
        if len(clean) >= MAX_RELATIONSHIPS:
            break
    return clean


def allowed_candidate_symbols(target, available_symbols):
    target_group = group_for(target)
    allowed = set(BASELINE)
    allowed.update(NEGATIVE_MACRO)
    allowed.update(sym for sym, _ in CURATED_PEERS.get(target, []))
    allowed.update(GROUP_FALLBACKS.get(target_group, []))
    allowed.update(GROUPS.get(target_group, set()))

    normalized = set()
    for symbol in allowed:
        sym = normalize_symbol(symbol)
        if not sym or sym == target or sym in NOISY_SINGLE_NAME_EXCLUSIONS:
            continue
        if sym in available_symbols:
            normalized.add(sym)
    return normalized


def build_relationships_for_symbol(target, return_map):
    base = return_map.get(target)
    if not base:
        return fallback_relationships(target)

    target_group = group_for(target)
    available_symbols = set(return_map.keys())
    candidate_universe = allowed_candidate_symbols(target, available_symbols)
    candidates = []
    for other, series in return_map.items():
        if other == target or other not in candidate_universe:
            continue
        score = blended_score(base, series, target, other)
        if score is None:
            continue
        corr_sign = pearson(base[-60:], series[-60:])
        if score < MIN_ABS_SCORE:
            continue
        other_group = group_for(other)
        if target_group and other_group and target_group != other_group and score < CROSS_ASSET_MIN_SCORE and other not in NEGATIVE_MACRO:
            continue
        if other in {'WTI', 'UNG'} and target_group != 'energy' and score < 0.40:
            continue
        candidates.append(candidate_payload(target, other, score, corr_sign))

    candidates.sort(key=lambda item: item['score'], reverse=True)

    selected = []
    seen = {target}
    for item in candidates:
        symbol = normalize_symbol(item['symbol'])
        if not symbol or symbol in seen:
            continue
        selected.append({'symbol': symbol, 'relation': item['relation']})
        seen.add(symbol)
        if len(selected) >= MAX_RELATIONSHIPS:
            break

    for symbol in GROUP_FALLBACKS.get(target_group, []):
        sym = normalize_symbol(symbol)
        if not sym or sym == target or sym in seen:
            continue
        selected.append({'symbol': sym, 'relation': 'negative' if sym in NEGATIVE_MACRO else 'positive'})
        seen.add(sym)
        if len(selected) >= MAX_RELATIONSHIPS:
            break

    for symbol in BASELINE:
        sym = normalize_symbol(symbol)
        if not sym or sym == target or sym in seen:
            continue
        selected.append({'symbol': sym, 'relation': 'negative' if sym in NEGATIVE_MACRO else 'positive'})
        seen.add(sym)
        if len(selected) >= MAX_RELATIONSHIPS:
            break

    final = sanitize_relationships(target, selected)
    return final if final else fallback_relationships(target)


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

    return_map = {}
    # Keep provider load low: fetch only the requested symbols and curated/group peers, not the whole watchlist.
    fetch_symbols = set(requested)
    for symbol in requested:
        fetch_symbols.update(sym for sym, _ in CURATED_PEERS.get(symbol, []))
        fetch_symbols.update(GROUP_FALLBACKS.get(group_for(symbol), []))
    fetch_symbols.update(BASELINE)
    fetch_symbols = sorted(normalize_symbol(s) for s in fetch_symbols if normalize_symbol(s))

    for symbol in fetch_symbols:
        closes = fetch_series(symbol)
        if not closes:
            continue
        returns = returns_from_closes(closes)
        if len(returns) < MIN_SERIES_POINTS:
            continue
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
