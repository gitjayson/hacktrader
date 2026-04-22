#!/bin/bash
# brk - Run breakout analysis for a stock (C-Turbo Edition v0.7.4)

TICKER=${1:-TSLA}
INTERVAL=${2:-5min}
DISPLAY=${3:-5-min}
PERIODS=${4:-100}
OUTPUT_JSON=${5:-false}

# Use absolute path for the binary to prevent "market data unavailable" errors on Linux
# The binary is expected to be in the same directory as the script
BINARY_PATH="$(dirname "$0")/run-brk"

if [ ! -f "$BINARY_PATH" ]; then
    echo "Error: Binary not found at $BINARY_PATH" >&2
    exit 1
fi

$BINARY_PATH "$TICKER" "$INTERVAL" "$DISPLAY" "$PERIODS" "$OUTPUT_JSON"
