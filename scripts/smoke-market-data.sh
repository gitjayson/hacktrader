#!/bin/bash
set -euo pipefail

ROOT="/var/www/html"
TMP_DIR="/tmp/hacktrader-smoke"
mkdir -p "$TMP_DIR"

run_case() {
  local ticker="$1"
  local period="$2"
  local lookback="$3"
  local outfile="$TMP_DIR/${ticker}_${period}_${lookback}.json"

  php -d display_errors=0 -d error_reporting=0 -r 'session_start(); $_GET=["ticker"=>getenv("HT_TICKER"),"period"=>getenv("HT_PERIOD"),"lookback"=>getenv("HT_LOOKBACK")]; $_SESSION["user_name"]="Smoke"; $_SESSION["agreed"]=1; $_SESSION["login_time"]=time(); $_SESSION["session_identity"]="session:smoke"; include "/var/www/html/api.php";' > "$outfile"

  python3 - <<'PY' "$outfile" "$ticker" "$period"
import json, sys
path, ticker, period = sys.argv[1:4]
obj = json.load(open(path))
assert not obj.get('error'), f"error field present: {obj.get('error')}"
assert obj.get('ticker') == ticker, f"ticker mismatch: {obj.get('ticker')} != {ticker}"
assert obj.get('interval') in {'1min','5min','1h','1day'}, f"unexpected interval: {obj.get('interval')}"
assert isinstance(obj.get('current_price'), (int, float)), 'current_price not numeric'
assert obj.get('source'), 'source missing'
assert obj.get('live_status') in {'live','cache_hit','stale_fallback'}, f"unexpected live_status: {obj.get('live_status')}"
print(f"PASS {ticker} {period} -> source={obj.get('source')} live_status={obj.get('live_status')} price={obj.get('current_price')}")
PY
}

HT_TICKER="TSLA" HT_PERIOD="5m" HT_LOOKBACK="100" run_case "TSLA" "5m" "100"
HT_TICKER="NVDA" HT_PERIOD="5m" HT_LOOKBACK="100" run_case "NVDA" "5m" "100"
HT_TICKER="SPY" HT_PERIOD="1d" HT_LOOKBACK="100" run_case "SPY" "1d" "100"

echo "Smoke tests passed."