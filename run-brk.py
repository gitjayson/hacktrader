import json
import os
import sys
import subprocess
import random
import math
from pathlib import Path
from datetime import datetime
from zoneinfo import ZoneInfo

script_dir = os.path.dirname(os.path.abspath(__file__))
secrets_path = os.path.join(script_dir, 'secrets.json')

home = Path.home()
user_site = home / '.local' / 'lib' / f'python{sys.version_info.major}.{sys.version_info.minor}' / 'site-packages'
if user_site.exists() and str(user_site) not in sys.path:
    sys.path.insert(0, str(user_site))


def load_api_keys():
    keys = [
        "1dbc799fe411479686be512ee797a777",
        "8e13917d80264a63935d62aa2c448076",
    ]

    if os.path.exists(secrets_path):
        try:
            with open(secrets_path) as f:
                secrets = json.load(f)
            secret_key = secrets.get('TWELVEDATA_API_KEY')
            if secret_key:
                keys.insert(0, secret_key)
        except Exception:
            pass

    seen = set()
    deduped = []
    for key in keys:
        if key and key not in seen:
            deduped.append(key)
            seen.add(key)
    return deduped


API_KEYS = load_api_keys()


def interval_to_yfinance(interval):
    mapping = {
        '1min': '1m',
        '5min': '5m',
        '1h': '60m',
        '1day': '1d',
    }
    return mapping.get(interval, '1d')


def interval_to_minutes(interval):
    mapping = {
        '1min': 1,
        '5min': 5,
        '1h': 60,
        '1day': 390,
    }
    return mapping.get(interval, 5)


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
        if value is None:
            return None
        return float(value)
    except Exception:
        return None


def parse_market_datetime(raw):
    if not raw:
        return None
    try:
        if isinstance(raw, (int, float)):
            dt_utc = datetime.fromtimestamp(raw, tz=ZoneInfo('UTC'))
        else:
            parsed = str(raw).replace('Z', '+00:00')
            dt = datetime.fromisoformat(parsed)
            dt_utc = dt if dt.tzinfo else dt.replace(tzinfo=ZoneInfo('UTC'))
        return dt_utc.astimezone(ZoneInfo('America/New_York'))
    except Exception:
        return None


def compute_output(ticker, interval, display, periods, values, source):
    current = float(values[0]['close'])
    highs = [float(d.get('high', 0)) for d in values if d.get('high') is not None]
    lows = [float(d.get('low', 0)) for d in values if d.get('low') is not None]

    highs_unique = sorted(set(highs))
    upper_resistances = [h for h in highs_unique if h > current][:2]

    lows_unique = sorted(set(lows))
    lower_resistances = [l for l in lows_unique if l < current][-2:][::-1]

    dist_to_upper = upper_resistances[0] - current if upper_resistances else float('inf')
    dist_to_lower = current - lower_resistances[0] if lower_resistances else float('inf')

    inv_upper = 1 / dist_to_upper if dist_to_upper > 0 else float('inf')
    inv_lower = 1 / dist_to_lower if dist_to_lower > 0 else float('inf')

    total_inv = inv_upper + inv_lower
    up_prob = round((inv_upper / total_inv) * 100, 1) if total_inv > 0 else 0
    down_prob = round((inv_lower / total_inv) * 100, 1) if total_inv > 0 else 0

    latest = values[0] if values else {}
    timestamp_raw = latest.get('datetime') or latest.get('timestamp')
    latest_dt_et = parse_market_datetime(timestamp_raw)
    eastern_time = latest_dt_et.strftime('%Y-%m-%d %I:%M %p') if latest_dt_et else (str(timestamp_raw) if timestamp_raw else None)
    eastern_label = latest_dt_et.tzname() if latest_dt_et else 'US/Eastern'

    current_volume = safe_float(latest.get('volume'))
    expected_volume = None
    volume_ratio = None

    interval_minutes = interval_to_minutes(interval)
    expected_day_volume = None
    current_day_volume = None
    day_volume_ratio = None

    if interval != '1day':
        current_slot = None
        if latest_dt_et:
            current_slot = (latest_dt_et.hour, latest_dt_et.minute)

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
        'ticker': ticker,
        'display': display,
        'periods': int(periods),
        'interval': interval,
        'current_price': current,
        'focus_price': current,
        'quote_time_eastern': eastern_time,
        'quote_timezone': eastern_label,
        'upper_resistances': [{'price': r, 'diff': round(r - current, 2)} for r in upper_resistances],
        'lower_supports': [{'price': r, 'diff': round(current - r, 2)} for r in lower_resistances],
        'probabilities': {
            'up': up_prob,
            'down': down_prob
        },
        'volume': {
            'current_bar': round(current_volume, 2) if current_volume is not None else None,
            'expected_bar': round(expected_volume, 2) if expected_volume is not None else None,
            'bar_ratio': round(volume_ratio, 2) if volume_ratio is not None else None,
            'current_day': round(current_day_volume, 2) if current_day_volume is not None else None,
            'expected_day': round(expected_day_volume, 2) if expected_day_volume is not None else None,
            'day_ratio': round(day_volume_ratio, 2) if day_volume_ratio is not None else None,
        },
        'source': source,
    }


def fetch_twelvedata(ticker, interval, periods):
    api_keys = API_KEYS[:]
    random.shuffle(api_keys)

    last_error = 'No Twelve Data API keys configured'
    for apikey in api_keys:
        url = f'https://api.twelvedata.com/time_series?symbol={ticker}&interval={interval}&apikey={apikey}&outputsize={periods}'
        response = subprocess.run(['curl', '-s', url], capture_output=True, text=True)

        try:
            data = json.loads(response.stdout)
        except Exception:
            last_error = 'Could not fetch data from Twelve Data'
            continue

        if 'code' in data:
            last_error = data.get('message', 'Twelve Data API Error')
            continue

        if 'values' not in data or not data['values']:
            last_error = 'No values in Twelve Data response'
            continue

        values = [
            {
                'high': entry.get('high'),
                'low': entry.get('low'),
                'close': entry.get('close'),
                'volume': entry.get('volume'),
                'datetime': entry.get('datetime'),
            }
            for entry in data['values']
        ]
        return values, 'twelvedata', None

    return None, None, last_error


def fetch_yfinance(ticker, interval, periods):
    yf_interval = interval_to_yfinance(interval)
    yf_range = yfinance_range_for_interval(interval, periods)

    pycode = r'''
import json
import sys

try:
    import yfinance as yf
except Exception as e:
    print(json.dumps({'error': f'yfinance import failed: {e}'}))
    raise SystemExit(0)

symbol, interval, period = sys.argv[1], sys.argv[2], sys.argv[3]
try:
    try:
        df = yf.Ticker(symbol).history(interval=interval, period=period, auto_adjust=False, prepost=False)
    except Exception as e:
        print(json.dumps({'error': f'yfinance history failed: {e}'}))
        raise SystemExit(0)

    if df is None or df.empty:
        print(json.dumps({'error': 'No values in yfinance response'}))
        raise SystemExit(0)

    rows = []
    for idx, row in df.iterrows():
        high = row.get('High')
        low = row.get('Low')
        close = row.get('Close')
        if high is None or low is None or close is None:
            continue
        volume = row.get('Volume')
        dt_value = None
        try:
            dt_value = idx.isoformat()
        except Exception:
            dt_value = str(idx)
        rows.append({
            'high': float(high),
            'low': float(low),
            'close': float(close),
            'volume': float(volume) if volume is not None else None,
            'datetime': dt_value,
        })

    if not rows:
        print(json.dumps({'error': 'No usable yfinance rows'}))
        raise SystemExit(0)

    rows.reverse()
    print(json.dumps({'values': rows}))
except Exception as e:
    print(json.dumps({'error': str(e)}))
'''

    response = subprocess.run(
        ['python3', '-c', pycode, ticker, yf_interval, yf_range],
        capture_output=True,
        text=True,
    )

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

    if not trimmed:
        return None, None, 'No values in yfinance response'

    return trimmed, 'yfinance', None


def run_breakout(ticker='TSLA', interval='1day', display='1-day', periods='100', output_json='false'):
    values, source, yf_error = fetch_yfinance(ticker, interval, periods)
    td_error = None
    backend_error = None

    if values is None:
        values, source, td_error = fetch_twelvedata(ticker, interval, periods)
        if values is None:
            backend_error = {
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

    print('## Upper Resistance Levels')
    for i, r in enumerate(output['upper_resistances'], 1):
        print(f"  {i}. ${r['price']:.2f} (+${r['diff']})")

    print()
    print('## Lower Support Levels')
    for i, r in enumerate(output['lower_supports'], 1):
        print(f"  {i}. ${r['price']:.2f} (-${r['diff']})")

    print()
    print('## Breakout Probability')
    print(f"- UP: {output['probabilities']['up']}%")
    print(f"- DOWN: {output['probabilities']['down']}%")


if __name__ == '__main__':
    ticker = sys.argv[1] if len(sys.argv) > 1 else 'TSLA'
    interval = sys.argv[2] if len(sys.argv) > 2 else '1day'
    display = sys.argv[3] if len(sys.argv) > 3 else '1-day'
    periods = sys.argv[4] if len(sys.argv) > 4 else '100'
    output_json = sys.argv[5] if len(sys.argv) > 5 else 'false'
    run_breakout(ticker, interval, display, periods, output_json)
