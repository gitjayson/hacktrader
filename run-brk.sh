#!/bin/bash
# brk - Run breakout analysis for a stock (Python production path)

set -euo pipefail

TICKER=${1:-TSLA}
INTERVAL=${2:-5min}
DISPLAY=${3:-5-min}
PERIODS=${4:-100}
OUTPUT_JSON=${5:-false}
SESSION_ID=${6:-${HACKTRADER_SESSION_ID:-}}
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

exec python3 "$SCRIPT_DIR/run-brk.py" "$TICKER" "$INTERVAL" "$DISPLAY" "$PERIODS" "$OUTPUT_JSON" "$SESSION_ID"