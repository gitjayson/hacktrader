import json
import os
import sys
import subprocess
import random

script_dir = os.path.dirname(os.path.abspath(__file__))
secrets_path = os.path.join(script_dir, 'secrets.json')


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


def compute_output(ticker, display, periods, values, source):
    current = float(values[0]['close'])
    highs = [float(d.get('high', 0)) for d in values if d.get('high') is not None]
    lows = [float(d.get('low', 0)) for d in values if d.get('low') is not None]

    highs_unique = sorted(set(highs), reverse=True)
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

    return {
        'ticker': ticker,
        'display': display,
        'periods': int(periods),
        'current_price': current,
        'upper_resistances': [{'price': r, 'diff': round(r - current, 2)} for r in upper_resistances],
        'lower_supports': [{'price': r, 'diff': round(current - r, 2)} for r in lower_resistances],
        'probabilities': {
            'up': up_prob,
            'down': down_prob
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
    df = yf.Ticker(symbol).history(interval=interval, period=period, auto_adjust=False, prepost=False)
    if df is None or df.empty:
        print(json.dumps({'error': 'No values in yfinance response'}))
        raise SystemExit(0)

    rows = []
    for _, row in df.iterrows():
        high = row.get('High')
        low = row.get('Low')
        close = row.get('Close')
        if high is None or low is None or close is None:
            continue
        rows.append({
            'high': float(high),
            'low': float(low),
            'close': float(close),
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
    values, source, td_error = fetch_twelvedata(ticker, interval, periods)
    backend_error = None

    if values is None:
        values, source, yf_error = fetch_yfinance(ticker, interval, periods)
        if values is None:
            backend_error = {
                'twelvedata': td_error,
                'yfinance': yf_error,
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

    output = compute_output(ticker, display, periods, values, source)
    if td_error and source == 'yfinance':
        output['fallback_reason'] = td_error

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
