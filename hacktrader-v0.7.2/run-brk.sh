#!/bin/bash
# brk - Run breakout analysis for a stock

OUTPUT_JSON="false"
args=()

for arg in "$@"; do
    if [[ "$arg" == "-h" || "$arg" == "--help" ]]; then
        echo "brk - Stock Breakout Calculator 🐧"
        echo "Usage: brk [period_length] [stock_ticker] [periods_to_use] [--json]"
        echo ""
        echo "Arguments:"
        echo "  period_length   Time period for each candle (1m, 5m, 1h, 1d). Default: 5m"
        echo "  stock_ticker    Stock symbol to analyze (e.g., TSLA, NVDA). Default: TSLA"
        echo "  periods_to_use  Number of historical periods to analyze. Default: 100"
        echo "  --json          Output the analysis as a JSON blob"
        echo ""
        echo "Examples:"
        echo "  brk                    # Defaults: 5m TSLA 100"
        echo "  brk 1h AAPL 50         # Analyze Apple using 50 1-hour periods"
        echo "  brk 1d TSLA 200 --json # JSON output"
        exit 0
    elif [[ "$arg" == "--json" ]]; then
        OUTPUT_JSON="true"
    else
        args+=("$arg")
    fi
done

TIME_PARAM="${args[0]:-5m}"
TICKER="${args[1]:-TSLA}"
PERIODS="${args[2]:-100}"

case "$TIME_PARAM" in
    1m) INTERVAL="1min"; DISPLAY="1-min" ;;
    5m) INTERVAL="5min"; DISPLAY="5-min" ;;
    1h) INTERVAL="1h"; DISPLAY="1-hour" ;;
    1d) INTERVAL="1day"; DISPLAY="1-day" ;;
    *) INTERVAL="5min"; DISPLAY="5-min" ;;
esac

python3 "$(dirname "$0")/run-brk.py" "$TICKER" "$INTERVAL" "$DISPLAY" "$PERIODS" "$OUTPUT_JSON"
