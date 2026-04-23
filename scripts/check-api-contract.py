#!/usr/bin/env python3
import re
from pathlib import Path

ROOT = Path('/var/www/html')
api = (ROOT / 'api.php').read_text()
dash = (ROOT / 'dashboard.php').read_text()
wrapper = (ROOT / 'run-brk.sh').read_text()

required_pairs = {
    "'1m' => ['interval' => '1min'": '1m -> 1min',
    "'5m' => ['interval' => '5min'": '5m -> 5min',
    "'1h' => ['interval' => '1h'": '1h -> 1h',
    "'1d' => ['interval' => '1day'": '1d -> 1day',
}
for needle, label in required_pairs.items():
    assert needle in api, f'missing api mapping: {label}'

assert "<select id='period'><option selected>5m</option><option>1m</option><option>1h</option><option>1d</option></select>" in dash, 'dashboard period selector mismatch'
assert 'exec python3 "$SCRIPT_DIR/run-brk.py"' in wrapper, 'wrapper is not using python production runner'
assert 'TICKER=${1:-TSLA}' in wrapper, 'wrapper ticker argv missing'
assert 'INTERVAL=${2:-5min}' in wrapper, 'wrapper interval argv missing'
assert 'DISPLAY=${3:-5-min}' in wrapper, 'wrapper display argv missing'
assert 'PERIODS=${4:-100}' in wrapper, 'wrapper periods argv missing'
assert 'OUTPUT_JSON=${5:-false}' in wrapper, 'wrapper output argv missing'

print('Contract checks passed: dashboard periods, api normalization, and wrapper argv contract are aligned.')