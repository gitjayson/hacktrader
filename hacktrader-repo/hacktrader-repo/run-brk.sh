#!/bin/bash
# brk - Run breakout analysis for a stock (C-Turbo Edition)

TICKER=${1:-TSLA}
INTERVAL=${2:-5min}
DISPLAY=${3:-5-min}
PERIODS=${4:-100}
OUTPUT_JSON=${5:-false}

# Execute the compiled C binary for maximum speed
./run-brk "$TICKER" "$INTERVAL" "$DISPLAY" "$PERIODS" "$OUTPUT_JSON"
