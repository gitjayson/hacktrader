# HackTrader Local Dev Webserver

Run a simple no-cache webserver from this folder:

```bash
cd /Users/agent/.openclaw/workspace/hacktrader-v0.5.0
python3 devserver.py --port 8000
```

Then open:

- http://127.0.0.1:8000/
- http://127.0.0.1:8000/dashboard.php
- http://127.0.0.1:8000/api.php

Notes:
- Serves the local v0.5.0 files directly
- Adds no-cache headers so data updates show immediately
- Good for local dev data and UI tuning
