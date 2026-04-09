import json
import os
import sys
import subprocess
import random
from pathlib import Path
from datetime import datetime, timezone, timedelta
from zoneinfo import ZoneInfo

try:
    import fcntl
except ImportError:
    fcntl = None

script_dir = os.path.dirname(os.path.abspath(__file__))
secrets_candidates = [
    os.path.join(script_dir, 'secrets.json'),
    os.path.join(os.path.dirname(script_dir), 'secrets.json'),
    '/var/www/secrets.json',
]
secrets_path = next((path for path in secrets_candidates if os.path.exists(path)), secrets_candidates[0])
cache_path = os.path.join(script_dir, 'market-data-cache.json')
usage_tracker_path = os.path.join(script_dir, 'api_usage_tracker.json')

home = Path.home()
user_site = home / '.local' / 'lib' / f'python{sys.version_info.major}.{sys.version_info.minor}' / 'site-packages'
if user_site.exists() and str(user_site) not in sys.path:
    sys.path.insert(0, str(user_site))

MARKET_TZ = ZoneInfo('America/New_York')
UTC_TZ = ZoneInfo('UTC')


def load_api_keys():
    keys = {'massive': None, 'twelvedata': []}
    twelvedata_fallback_keys = [
        '1dbc799fe411479686be512ee797a777',
        '8e13917d80264a63935d62aa2c448076',
    ]
    if os.path.exists(secrets_path):
        try:
            with open(secrets_path) as f:
                secrets = json.load(f)
            massive_key = secrets.get('MASSIVE_API_KEY')
            td_key = secrets.get('TWELVEDATA_API_KEY')
            if massive_key:
                keys['massive'] = massive_key
            if td_key:
                keys['twelvedata'].append(td_key)
        except Exception:
            pass
    seen = set()
    deduped_td = []
    for key in keys['twelvedata'] + twelvedata_fallback_keys:
        if key and key not in seen:
            deduped_td.append(key)
            seen.add(key)
    keys['twelvedata'] = deduped_td
    return keys


API_KEYS = load_api_keys()


def load_market_cache():
    try:
        if not os.path.exists(cache_path):
            return None
        with open(cache_path) as f:
            return json.load(f)
    except Exception:
        return None


def increment_api_usage(session_id, provider='twelvedata', ticker=None, interval=None, periods=None, outcome='success'):
    session_id = (session_id or os.environ.get('HACKTRADER_SESSION_ID') or 'session:anonymous').strip() or 'session:anonymous'
    event = {
        'timestamp': datetime.now(timezone.utc).isoformat(),
        'session_id': session_id,
        'provider': provider,
        'ticker': ticker,
        'interval': interval,
        'periods': int(periods) if str(periods).isdigit() else periods,
        'outcome': outcome,
    }
    os.makedirs(os.path.dirname(usage_tracker_path) or '.', exist_ok=True)
    with open(usage_tracker_path, 'a+', encoding='utf-8') as tracker_file:
        if fcntl is not None:
            fcntl.flock(tracker_file.fileno(), fcntl.LOCK_EX)
        tracker_file.seek(0)
        raw = tracker_file.read().strip()
        tracker = {
            'meta': {
                'updated_at': None,
                'version': 'v0.7.2.4',
            },
            'sessions': {},
            'recent_events': [],
        }
        if raw:
            try:
                loaded = json.loads(raw)
                if isinstance(loaded, dict):
                    tracker.update(loaded)
            except Exception:
                pass
        tracker.setdefault('meta', {})
        tracker.setdefault('sessions', {})
        tracker.setdefault('recent_events', [])

        session_entry = tracker['sessions'].setdefault(session_id, {
            'total_attempts': 0,
            'providers': {},
            'last_request_at': None,
            'last_ticker': None,
            'last_interval': None,
            'last_periods': None,
            'last_outcome': None,
        })
        session_entry['total_attempts'] = int(session_entry.get('total_attempts', 0)) + 1
        provider_entry = session_entry['providers'].setdefault(provider, {
            'attempts': 0,
            'successes': 0,
            'errors': 0,
        })
        provider_entry['attempts'] = int(provider_entry.get('attempts', 0)) + 1
        if outcome == 'success':
            provider_entry['successes'] = int(provider_entry.get('successes', 0)) + 1
        else:
            provider_entry['errors'] = int(provider_entry.get('errors', 0)) + 1

        session_entry['last_request_at'] = event['timestamp']
        session_entry['last_ticker'] = ticker
        session_entry['last_interval'] = interval
        session_entry['last_periods'] = event['periods']
        session_entry['last_outcome'] = outcome

        tracker['recent_events'].append(event)
        tracker['recent_events'] = tracker['recent_events'][-200:]
        tracker['meta']['updated_at'] = event['timestamp']
        tracker['meta']['version'] = 'v0.7.2.4'

        tracker_file.seek(0)
        tracker_file.truncate()
        json.dump(tracker, tracker_file, indent=2)
        tracker_file.write('\n')
        tracker_file.flush()
        os.fsync(tracker_file.fileno())
        if fcntl is not None:
            fcntl.flock(tracker_file.fileno(), fcntl.LOCK_UN)


def cache_quote_to_value(quote):
    if not isinstance(quote, dict):
        return None
    price = quote.get('close') or quote.get('price') or quote.get('last')
    if price is None:
        return None
    timestamp = quote.get('datetime') or quote.get('timestamp') or quote.get('time')
    volume = quote.get('volume')
    try:
        return {
            'high': float(quote.get('high') or price),
            'low': float(quote.get('low') or price),
            'close': float(price),
            'volume': float(volume) if volume is not None else None,
            'datetime': timestamp or datetime.now(timezone.utc).isoformat(),
        }
    except Exception:
        return None


def interval_to_yfinance(interval):
    return {
        '1min': '1m',
        '5min': '5m',
        '1h': '60m',
        '1day': '1d',
    }.get(interval, '1d')


def interval_to_minutes(interval):
    return {
        '1min': 1,
        '5min': 5,
        '1h': 60,
        '1day': 390,
    }.get(interval, 5)


def yfinance_range_for_interval(interval, periods):
    try:
        periods = int(periods)
    except Exception:
        periods = 100
    if interval == '1min':
        return '7d' if periods <= 390 * 7 else '30d'
    if interval == '5min':
        return '60d'
    if interval == '1h':
        return '730d'
    return '5y'


def safe_float(value):
    try:
        return None if value is None else float(value)
    except Exception:
        return None


def parse_market_datetime(raw):
    if not raw:
        return None
    try:
        if isinstance(raw, (int, float)):
            dt_utc = datetime.fromtimestamp(raw, tz=UTC_TZ)
        else:
            parsed = str(raw).replace('Z', '+00:00')
            dt = datetime.fromisoformat(parsed)
            dt_utc = dt if dt.tzinfo else dt.replace(tzinfo=UTC_TZ)
        return dt_utc.astimezone(MARKET_TZ)
    except Exception:
        return None


def round_price(price):
    return None if price is None else round(float(price), 2)


def round_maybe(value, digits=2):
    try:
        return None if value is None else round(float(value), digits)
    except Exception:
        return None


def normalize_rows(values):
    rows = []
    for row in values or []:
        close = safe_float(row.get('close'))
        high = safe_float(row.get('high'))
        low = safe_float(row.get('low'))
        if close is None or high is None or low is None:
            continue
        rows.append({
            'high': high,
            'low': low,
            'close': close,
            'volume': safe_float(row.get('volume')),
            'dt': parse_market_datetime(row.get('datetime') or row.get('timestamp')),
            'raw_dt': row.get('datetime') or row.get('timestamp'),
        })
    rows.reverse()
    return rows


def true_range(curr, prev_close=None):
    high = curr['high']
    low = curr['low']
    if prev_close is None:
        return high - low
    return max(high - low, abs(high - prev_close), abs(low - prev_close))


def average_true_range(rows, lookback=14):
    if not rows:
        return None
    trs = []
    prev_close = None
    for row in rows[-lookback:]:
        trs.append(true_range(row, prev_close))
        prev_close = row['close']
    return sum(trs) / len(trs) if trs else None


def bucket_width_for_rows(rows, current_price, interval):
    atr = average_true_range(rows, min(14, len(rows))) or max(current_price * 0.0035, 0.25)
    interval_minutes = interval_to_minutes(interval)
    min_tick = 0.1 if current_price >= 100 else 0.05
    width = max(min_tick, atr * (0.35 if interval_minutes <= 5 else 0.45))
    return max(width, current_price * 0.0008)


def cluster_levels(levels, bucket_width, min_touches=2, top_n=10):
    if not levels:
        return []
    levels = sorted(float(x) for x in levels)
    clusters = []
    for price in levels:
        if not clusters or abs(price - clusters[-1]['center']) > bucket_width:
            clusters.append({'prices': [price], 'center': price})
        else:
            clusters[-1]['prices'].append(price)
            clusters[-1]['center'] = sum(clusters[-1]['prices']) / len(clusters[-1]['prices'])
    ranked = []
    for cluster in clusters:
        touches = len(cluster['prices'])
        if touches < min_touches:
            continue
        ranked.append({
            'price': cluster['center'],
            'touches': touches,
            'min': min(cluster['prices']),
            'max': max(cluster['prices']),
            'width': max(cluster['prices']) - min(cluster['prices']),
        })
    ranked.sort(key=lambda item: (-item['touches'], item['width'], item['price']))
    return ranked[:top_n]


def find_pivots(rows, left=2, right=2):
    pivot_highs, pivot_lows = [], []
    total = len(rows)
    if total < left + right + 1:
        return pivot_highs, pivot_lows
    for i in range(left, total - right):
        window = rows[i - left:i + right + 1]
        current = rows[i]
        if all(current['high'] >= candle['high'] for candle in window):
            pivot_highs.append(current['high'])
        if all(current['low'] <= candle['low'] for candle in window):
            pivot_lows.append(current['low'])
    return pivot_highs, pivot_lows


def split_days(rows):
    days = {}
    for row in rows:
        if row['dt'] is None:
            continue
        days.setdefault(row['dt'].date().isoformat(), []).append(row)
    return days


def summarize_day(day_rows):
    if not day_rows:
        return None
    return {
        'date': day_rows[0]['dt'].date().isoformat() if day_rows[0]['dt'] else None,
        'high': max(r['high'] for r in day_rows),
        'low': min(r['low'] for r in day_rows),
        'open': day_rows[0]['close'],
        'close': day_rows[-1]['close'],
        'volume': sum((r['volume'] or 0) for r in day_rows),
        'bars': len(day_rows),
    }


def nearest_levels(levels, current, direction, count=2):
    if direction == 'above':
        filtered = sorted([lvl for lvl in levels if lvl['price'] > current], key=lambda lvl: lvl['price'])
    else:
        filtered = sorted([lvl for lvl in levels if lvl['price'] < current], key=lambda lvl: lvl['price'], reverse=True)
    return filtered[:count]


def as_level_payload(level, current, kind):
    if not level:
        return None
    diff = level['price'] - current if kind == 'resistance' else current - level['price']
    return {
        'price': round_price(level['price']),
        'diff': round_maybe(diff, 2),
        'touches': level.get('touches'),
        'range': [round_price(level.get('min')), round_price(level.get('max'))],
    }


def build_channels(current, support1, support2, resistance1, resistance2):
    channels = []
    if support1 and resistance1 and resistance1['price'] > support1['price']:
        channels.append({
            'name': 'current',
            'lower': round_price(support1['price']),
            'upper': round_price(resistance1['price']),
            'width': round_maybe(resistance1['price'] - support1['price'], 2),
            'location': 'inside' if support1['price'] <= current <= resistance1['price'] else ('above' if current > resistance1['price'] else 'below'),
        })
    if resistance1 and resistance2 and resistance2['price'] > resistance1['price']:
        channels.append({
            'name': 'above_resistance',
            'lower': round_price(resistance1['price']),
            'upper': round_price(resistance2['price']),
            'width': round_maybe(resistance2['price'] - resistance1['price'], 2),
            'location': 'potential_upside_channel',
        })
    if support1 and support2 and support1['price'] > support2['price']:
        channels.append({
            'name': 'below_support',
            'lower': round_price(support2['price']),
            'upper': round_price(support1['price']),
            'width': round_maybe(support1['price'] - support2['price'], 2),
            'location': 'potential_downside_channel',
        })
    return channels


def count_failed_attempts(day_rows, level_price, direction, bucket_width, close_buffer=None):
    if not day_rows or level_price is None:
        return 0
    close_buffer = close_buffer or max(bucket_width * 0.45, 0.08)
    attempts = 0
    in_attempt = False
    for row in day_rows:
        if direction == 'up':
            touched = row['high'] >= level_price - bucket_width
            failed = row['close'] < level_price - close_buffer
        else:
            touched = row['low'] <= level_price + bucket_width
            failed = row['close'] > level_price + close_buffer
        if touched and not in_attempt:
            in_attempt = True
            if failed:
                attempts += 1
        elif not touched:
            in_attempt = False
    return attempts


def recent_extremes(rows, window=20):
    sample = rows[-window:] if len(rows) >= window else rows[:]
    if not sample:
        return None
    return {
        'window_bars': len(sample),
        'recent_high': round_price(max(r['high'] for r in sample)),
        'recent_low': round_price(min(r['low'] for r in sample)),
    }


def compute_volume_profile(values, latest_dt_et, current_volume, interval):
    expected_volume = None
    volume_ratio = None
    expected_day_volume = None
    current_day_volume = None
    day_volume_ratio = None
    if interval != '1day':
        current_slot = (latest_dt_et.hour, latest_dt_et.minute) if latest_dt_et else None
        same_slot_volumes = []
        daily_totals = {}
        for row in values:
            row_dt = parse_market_datetime(row.get('datetime') or row.get('timestamp'))
            row_volume = safe_float(row.get('volume'))
            if row_dt is None or row_volume is None:
                continue
            day_key = row_dt.date().isoformat()
            daily_totals[day_key] = daily_totals.get(day_key, 0.0) + row_volume
            if current_slot and (row_dt.hour, row_dt.minute) == current_slot:
                if latest_dt_et and row_dt.date() == latest_dt_et.date():
                    continue
                same_slot_volumes.append(row_volume)
        if daily_totals:
            sorted_days = sorted(daily_totals.items(), reverse=True)
            if latest_dt_et:
                current_day_key = latest_dt_et.date().isoformat()
                current_day_volume = daily_totals.get(current_day_key)
                prior_day_totals = [total for day, total in sorted_days if day != current_day_key]
            else:
                current_day_volume = sorted_days[0][1]
                prior_day_totals = [total for _, total in sorted_days[1:]]
            if prior_day_totals:
                expected_day_volume = sum(prior_day_totals[:5]) / min(len(prior_day_totals), 5)
        if current_volume is not None and same_slot_volumes:
            expected_volume = sum(same_slot_volumes[:5]) / min(len(same_slot_volumes), 5)
            if expected_volume > 0:
                volume_ratio = current_volume / expected_volume
        if current_day_volume is not None and expected_day_volume and expected_day_volume > 0:
            day_volume_ratio = current_day_volume / expected_day_volume
    else:
        volumes = [safe_float(v.get('volume')) for v in values]
        volumes = [v for v in volumes if v is not None]
        current_day_volume = current_volume
        sample = volumes[1:] if len(volumes) > 1 else []
        if sample:
            expected_day_volume = sum(sample[:20]) / min(len(sample), 20)
            if expected_day_volume > 0 and current_day_volume is not None:
                day_volume_ratio = current_day_volume / expected_day_volume
    return {
        'current_bar': round_maybe(current_volume, 2),
        'expected_bar': round_maybe(expected_volume, 2),
        'bar_ratio': round_maybe(volume_ratio, 2),
        'current_day': round_maybe(current_day_volume, 2),
        'expected_day': round_maybe(expected_day_volume, 2),
        'day_ratio': round_maybe(day_volume_ratio, 2),
    }


def clamp(value, low=0.0, high=100.0):
    return max(low, min(high, value))


def score_breakout(current, support1, resistance1, previous_day, failed_up, failed_down, volume_profile, channels, recent, rows):
    if not support1 or not resistance1:
        return {'up': 50.0, 'down': 50.0, 'bias': 'neutral', 'confidence': 'low', 'drivers': []}
    width = max(resistance1['price'] - support1['price'], 0.01)
    position = clamp((current - support1['price']) / width, 0.0, 1.0)
    atr = average_true_range(rows, min(14, len(rows))) or (width / 3)
    atr = max(atr, 0.01)
    score = 0.0
    drivers = []
    position_impact = (position - 0.5) * 26.0
    score += position_impact
    drivers.append({'factor': 'channel_position', 'value': round_maybe(position, 3), 'impact': round_maybe(position_impact, 2)})
    if previous_day:
        pdh = previous_day.get('high')
        pdl = previous_day.get('low')
        if pdh is not None:
            impact = clamp((current - pdh) / atr, -1.5, 1.5) * 8.0
            score += impact
            drivers.append({'factor': 'previous_day_high', 'value': round_price(pdh), 'impact': round_maybe(impact, 2)})
        if pdl is not None:
            impact = clamp((current - pdl) / atr, -1.5, 1.5) * 5.0
            score += impact
            drivers.append({'factor': 'previous_day_low', 'value': round_price(pdl), 'impact': round_maybe(impact, 2)})
    if recent:
        if recent.get('recent_high') is not None and current >= recent['recent_high'] - atr * 0.15:
            score += 6.0
            drivers.append({'factor': 'near_recent_high', 'value': recent['recent_high'], 'impact': 6.0})
        if recent.get('recent_low') is not None and current <= recent['recent_low'] + atr * 0.15:
            score -= 6.0
            drivers.append({'factor': 'near_recent_low', 'value': recent['recent_low'], 'impact': -6.0})
    bar_ratio = volume_profile.get('bar_ratio')
    day_ratio = volume_profile.get('day_ratio')
    if bar_ratio is not None:
        impact = clamp(bar_ratio - 1.0, -0.75, 1.5) * 8.0
        score += impact
        drivers.append({'factor': 'bar_volume_ratio', 'value': round_maybe(bar_ratio, 2), 'impact': round_maybe(impact, 2)})
    if day_ratio is not None:
        impact = clamp(day_ratio - 1.0, -0.75, 1.5) * 4.0
        score += impact
        drivers.append({'factor': 'day_volume_ratio', 'value': round_maybe(day_ratio, 2), 'impact': round_maybe(impact, 2)})
    up_fail_impact = -min(failed_up, 3) * 7.5
    down_fail_impact = min(failed_down, 3) * 7.5
    score += up_fail_impact + down_fail_impact
    drivers.append({'factor': 'failed_up_attempts', 'value': failed_up, 'impact': round_maybe(up_fail_impact, 2)})
    drivers.append({'factor': 'failed_down_attempts', 'value': failed_down, 'impact': round_maybe(down_fail_impact, 2)})
    if failed_up >= 3:
        score -= 8.0
        drivers.append({'factor': 'triple_failed_up_cap', 'value': failed_up, 'impact': -8.0})
    if failed_down >= 3:
        score += 8.0
        drivers.append({'factor': 'triple_failed_down_cap', 'value': failed_down, 'impact': 8.0})
    current_channel = next((c for c in channels if c['name'] == 'current'), None)
    if current_channel and current_channel['width'] is not None and current_channel['width'] < atr * 1.2:
        score -= 4.0
        drivers.append({'factor': 'tight_channel', 'value': current_channel['width'], 'impact': -4.0})
    up = round(clamp(50.0 + score, 1.0, 99.0), 1)
    down = round(100.0 - up, 1)
    bias = 'up' if up > 55 else ('down' if up < 45 else 'neutral')
    confidence = 'high' if abs(up - 50) >= 20 else ('medium' if abs(up - 50) >= 10 else 'low')
    return {'up': up, 'down': down, 'bias': bias, 'confidence': confidence, 'drivers': drivers}


def compute_output(ticker, interval, display, periods, values, source):
    latest = values[0] if values else {}
    current = float(latest['close'])
    timestamp_raw = latest.get('datetime') or latest.get('timestamp')
    latest_dt_et = parse_market_datetime(timestamp_raw)
    eastern_time = latest_dt_et.strftime('%Y-%m-%d %I:%M %p') if latest_dt_et else (str(timestamp_raw) if timestamp_raw else None)
    eastern_label = latest_dt_et.tzname() if latest_dt_et else 'US/Eastern'
    rows = normalize_rows(values)
    if not rows:
        raise ValueError('No usable rows for breakout analysis')
    bucket_width = bucket_width_for_rows(rows, current, interval)
    pivot_highs, pivot_lows = find_pivots(rows, left=2, right=2)
    raw_high_levels = cluster_levels(pivot_highs + [r['high'] for r in rows], bucket_width, min_touches=2, top_n=10)
    raw_low_levels = cluster_levels(pivot_lows + [r['low'] for r in rows], bucket_width, min_touches=2, top_n=10)
    resistance_candidates = sorted(raw_high_levels, key=lambda lvl: lvl['price'])
    support_candidates = sorted(raw_low_levels, key=lambda lvl: lvl['price'])
    upper_levels = nearest_levels(resistance_candidates, current, 'above', count=2)
    lower_levels = nearest_levels(support_candidates, current, 'below', count=2)
    if len(upper_levels) < 2:
        fallback_above = [lvl for lvl in resistance_candidates if lvl['price'] >= current and lvl not in upper_levels]
        upper_levels.extend(fallback_above[:2 - len(upper_levels)])
    if len(lower_levels) < 2:
        fallback_below = [lvl for lvl in sorted(support_candidates, key=lambda lvl: lvl['price'], reverse=True) if lvl['price'] <= current and lvl not in lower_levels]
        lower_levels.extend(fallback_below[:2 - len(lower_levels)])
    resistance1 = upper_levels[0] if len(upper_levels) > 0 else None
    resistance2 = upper_levels[1] if len(upper_levels) > 1 else None
    support1 = lower_levels[0] if len(lower_levels) > 0 else None
    support2 = lower_levels[1] if len(lower_levels) > 1 else None
    days = split_days(rows)
    ordered_days = sorted(days.keys())
    current_day_key = latest_dt_et.date().isoformat() if latest_dt_et else (ordered_days[-1] if ordered_days else None)
    previous_day_summary = None
    current_day_rows = []
    if current_day_key and current_day_key in days:
        current_day_rows = days[current_day_key]
        idx = ordered_days.index(current_day_key)
        if idx > 0:
            previous_day_summary = summarize_day(days[ordered_days[idx - 1]])
    elif ordered_days:
        current_day_rows = days[ordered_days[-1]]
        if len(ordered_days) > 1:
            previous_day_summary = summarize_day(days[ordered_days[-2]])
    failed_up = count_failed_attempts(current_day_rows, resistance1['price'] if resistance1 else None, 'up', bucket_width)
    failed_down = count_failed_attempts(current_day_rows, support1['price'] if support1 else None, 'down', bucket_width)
    recent = recent_extremes(rows, window=min(20, len(rows)))
    volume_profile = compute_volume_profile(values, latest_dt_et, safe_float(latest.get('volume')), interval)
    channels = build_channels(current, support1, support2, resistance1, resistance2)
    probabilities = score_breakout(current, support1, resistance1, previous_day_summary, failed_up, failed_down, volume_profile, channels, recent, rows)
    resistance_payloads = [as_level_payload(level, current, 'resistance') for level in [resistance1, resistance2] if level]
    support_payloads = [as_level_payload(level, current, 'support') for level in [support1, support2] if level]
    return {
        'ticker': ticker,
        'display': display,
        'periods': int(periods),
        'interval': interval,
        'current_price': round_price(current),
        'focus_price': round_price(current),
        'quote_time_eastern': eastern_time,
        'quote_timezone': eastern_label,
        'resistance_1': resistance_payloads[0] if resistance_payloads else None,
        'resistance_2': resistance_payloads[1] if len(resistance_payloads) > 1 else None,
        'support_1': support_payloads[0] if support_payloads else None,
        'support_2': support_payloads[1] if len(support_payloads) > 1 else None,
        'upper_resistances': resistance_payloads,
        'lower_supports': support_payloads,
        'channels': channels,
        'previous_day': {
            'date': previous_day_summary.get('date') if previous_day_summary else None,
            'high': round_price(previous_day_summary.get('high')) if previous_day_summary else None,
            'low': round_price(previous_day_summary.get('low')) if previous_day_summary else None,
            'open': round_price(previous_day_summary.get('open')) if previous_day_summary else None,
            'close': round_price(previous_day_summary.get('close')) if previous_day_summary else None,
            'volume': round_maybe(previous_day_summary.get('volume'), 2) if previous_day_summary else None,
        },
        'recent_extremes': recent,
        'attempts': {
            'failed_up_today': failed_up,
            'failed_down_today': failed_down,
            'rule_of_three_block_up': failed_up >= 3,
            'rule_of_three_block_down': failed_down >= 3,
        },
        'probabilities': {
            'up': probabilities['up'],
            'down': probabilities['down'],
            'bias': probabilities['bias'],
            'confidence': probabilities['confidence'],
        },
        'score_drivers': probabilities['drivers'],
        'analysis_parameters': {
            'bucket_width': round_maybe(bucket_width, 4),
            'atr': round_maybe(average_true_range(rows, min(14, len(rows))), 4),
            'rows_used': len(rows),
        },
        'volume': volume_profile,
        'source': source,
    }


def fetch_from_cache(ticker, periods):
    cache = load_market_cache()
    if not isinstance(cache, dict):
        return None, None, 'miss'
    quote = (cache.get('quotes') or {}).get(ticker.upper())
    value = cache_quote_to_value(quote)
    if not value:
        return None, None, 'miss'
    return [value], 'cache', None


def interval_to_massive_params(interval):
    return {
        '1min': (1, 'minute'),
        '5min': (5, 'minute'),
        '1h': (1, 'hour'),
        '1day': (1, 'day'),
    }.get(interval, (1, 'day'))


def massive_range_for_interval(interval, periods):
    try:
        periods = int(periods)
    except Exception:
        periods = 100
    if interval == '1min':
        return '10d'
    if interval == '5min':
        return '60d'
    if interval == '1h':
        return '730d'
    return '5y'


def fetch_massive(ticker, interval, periods):
    api_key = API_KEYS.get('massive')
    if not api_key:
        return None, None, 'No Massive API key configured'
    multiplier, timespan = interval_to_massive_params(interval)
    lookback_range = massive_range_for_interval(interval, periods)
    end = datetime.now(timezone.utc)
    if lookback_range.endswith('d'):
        start = end - timedelta(days=int(lookback_range[:-1]))
    elif lookback_range.endswith('y'):
        start = end - timedelta(days=365 * int(lookback_range[:-1]))
    else:
        start = end - timedelta(days=60)
    from_date = start.strftime('%Y-%m-%d')
    to_date = end.strftime('%Y-%m-%d')
    url = f'https://api.massive.com/v2/aggs/ticker/{ticker}/range/{multiplier}/{timespan}/{from_date}/{to_date}?adjusted=true&sort=desc&limit=50000&apiKey={api_key}'
    response = subprocess.run(['curl', '-s', url], capture_output=True, text=True)
    try:
        data = json.loads(response.stdout)
    except Exception:
        return None, None, 'Could not parse Massive response'
    if data.get('status') == 'ERROR' or data.get('error'):
        return None, None, data.get('error') or data.get('message') or 'Massive API Error'
    results = data.get('results') or []
    if not results:
        return None, None, 'No values in Massive response'
    values = []
    for entry in results:
        timestamp_ms = entry.get('t')
        iso_ts = None
        if timestamp_ms is not None:
            try:
                iso_ts = datetime.fromtimestamp(timestamp_ms / 1000, tz=timezone.utc).isoformat()
            except Exception:
                iso_ts = None
        values.append({
            'high': entry.get('h'),
            'low': entry.get('l'),
            'close': entry.get('c'),
            'volume': entry.get('v'),
            'datetime': iso_ts,
        })
    try:
        periods_int = int(periods)
    except Exception:
        periods_int = 100
    trimmed = values[:periods_int] if periods_int > 0 else values
    return (trimmed, 'massive', None) if trimmed else (None, None, 'No usable Massive rows')


def fetch_twelvedata(ticker, interval, periods, session_id=None):
    api_keys = API_KEYS.get('twelvedata', [])[:]
    random.shuffle(api_keys)
    last_error = 'No Twelve Data API keys configured'
    for apikey in api_keys:
        url = f'https://api.twelvedata.com/time_series?symbol={ticker}&interval={interval}&apikey={apikey}&outputsize={periods}'
        response = subprocess.run(['curl', '-s', url], capture_output=True, text=True)
        try:
            data = json.loads(response.stdout)
        except Exception:
            increment_api_usage(session_id, provider='twelvedata', ticker=ticker, interval=interval, periods=periods, outcome='parse_error')
            last_error = 'Could not fetch data from Twelve Data'
            continue
        if 'code' in data:
            increment_api_usage(session_id, provider='twelvedata', ticker=ticker, interval=interval, periods=periods, outcome='api_error')
            last_error = data.get('message', 'Twelve Data API Error')
            continue
        if 'values' not in data or not data['values']:
            increment_api_usage(session_id, provider='twelvedata', ticker=ticker, interval=interval, periods=periods, outcome='empty')
            last_error = 'No values in Twelve Data response'
            continue
        increment_api_usage(session_id, provider='twelvedata', ticker=ticker, interval=interval, periods=periods, outcome='success')
        return [
            {
                'high': entry.get('high'),
                'low': entry.get('low'),
                'close': entry.get('close'),
                'volume': entry.get('volume'),
                'datetime': entry.get('datetime'),
            }
            for entry in data['values']
        ], 'twelvedata', None
    return None, None, last_error


def fetch_yfinance(ticker, interval, periods):
    yf_interval = interval_to_yfinance(interval)
    yf_range = yfinance_range_for_interval(interval, periods)
    pycode = r'''
import json, sys
try:
    import yfinance as yf
except Exception as e:
    print(json.dumps({'error': f'yfinance import failed: {e}'}))
    raise SystemExit(0)
symbol, interval, period = sys.argv[1], sys.argv[2], sys.argv[3]
try:
    df = yf.Ticker(symbol).history(interval=interval, period=period, auto_adjust=False, prepost=False)
    if df is None or df.empty:
        print(json.dumps({'error': 'No values in yfinance response'}))
        raise SystemExit(0)
    rows = []
    for idx, row in df.iterrows():
        high = row.get('High'); low = row.get('Low'); close = row.get('Close')
        if high is None or low is None or close is None:
            continue
        volume = row.get('Volume')
        try:
            dt_value = idx.isoformat()
        except Exception:
            dt_value = str(idx)
        rows.append({'high': float(high), 'low': float(low), 'close': float(close), 'volume': float(volume) if volume is not None else None, 'datetime': dt_value})
    if not rows:
        print(json.dumps({'error': 'No usable yfinance rows'}))
        raise SystemExit(0)
    rows.reverse()
    print(json.dumps({'values': rows}))
except Exception as e:
    print(json.dumps({'error': str(e)}))
'''
    response = subprocess.run([sys.executable, '-c', pycode, ticker, yf_interval, yf_range], capture_output=True, text=True)
    try:
        data = json.loads(response.stdout)
    except Exception:
        return None, None, 'Could not parse yfinance response'
    if data.get('error'):
        return None, None, data['error']
    values = data.get('values', [])
    try:
        periods_int = int(periods)
    except Exception:
        periods_int = 100
    trimmed = values[:periods_int] if periods_int > 0 else values
    return (trimmed, 'yfinance', None) if trimmed else (None, None, 'No values in yfinance response')


def run_breakout(ticker='TSLA', interval='1day', display='1-day', periods='100', output_json='false', session_id=None):
    values, source, cache_error = fetch_from_cache(ticker, periods)
    massive_error = None
    yf_error = None
    td_error = None
    backend_error = None
    if values is None:
        values, source, massive_error = fetch_massive(ticker, interval, periods)
    if values is None:
        values, source, yf_error = fetch_yfinance(ticker, interval, periods)
    if values is None:
        values, source, td_error = fetch_twelvedata(ticker, interval, periods, session_id=session_id)
        if values is None:
            backend_error = {
                'cache': cache_error,
                'massive': massive_error,
                'yfinance': yf_error,
                'twelvedata': td_error,
            }
    if values is None:
        error_payload = {
            'error': 'Unable to fetch market data',
            'details': backend_error,
            'ticker': ticker,
            'display': display,
            'periods': int(periods),
        }
        if output_json == 'true':
            print(json.dumps(error_payload, indent=2))
        else:
            print('Error: Unable to fetch market data')
        return
    output = compute_output(ticker, interval, display, periods, values, source)
    if yf_error and source == 'twelvedata':
        output['fallback_reason'] = yf_error
    if output_json == 'true':
        print(json.dumps(output, indent=2))
        return
    print(f"=== {ticker} Breakout Analysis ({display}, {periods} periods) ===")
    print(f"Last Known Market Price: ${output['current_price']:.2f}")
    print(f"Source: {source}")
    print()
    print('## Resistance Levels')
    for idx, key in enumerate(['resistance_1', 'resistance_2'], 1):
        level = output.get(key)
        if level:
            print(f"  R{idx}. ${level['price']:.2f} (+${level['diff']}) touches={level['touches']}")
    print()
    print('## Support Levels')
    for idx, key in enumerate(['support_1', 'support_2'], 1):
        level = output.get(key)
        if level:
            print(f"  S{idx}. ${level['price']:.2f} (-${level['diff']}) touches={level['touches']}")
    print()
    print('## Channels')
    for channel in output.get('channels', []):
        print(f"- {channel['name']}: ${channel['lower']:.2f} -> ${channel['upper']:.2f} ({channel['location']})")
    print()
    print('## Breakout Probability')
    print(f"- UP: {output['probabilities']['up']}% ({output['probabilities']['confidence']})")
    print(f"- DOWN: {output['probabilities']['down']}%")
    print(f"- BIAS: {output['probabilities']['bias']}")
    print()
    print('## Attempt Count')
    print(f"- Failed up attempts today: {output['attempts']['failed_up_today']}")
    print(f"- Failed down attempts today: {output['attempts']['failed_down_today']}")


if __name__ == '__main__':
    ticker = sys.argv[1] if len(sys.argv) > 1 else 'TSLA'
    interval = sys.argv[2] if len(sys.argv) > 2 else '1day'
    display = sys.argv[3] if len(sys.argv) > 3 else '1-day'
    periods = sys.argv[4] if len(sys.argv) > 4 else '100'
    output_json = sys.argv[5] if len(sys.argv) > 5 else 'false'
    session_id = sys.argv[6] if len(sys.argv) > 6 else os.environ.get('HACKTRADER_SESSION_ID')
    run_breakout(ticker, interval, display, periods, output_json, session_id=session_id)
