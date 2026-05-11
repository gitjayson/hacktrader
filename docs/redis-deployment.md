# Redis Subscription Cache — Deployment Runbook (v0.13.0)

This runbook walks through bringing up the Redis-backed subscription
cache architecture on `dev.hacktrader.com`. Total time: ~15 minutes.

## Architecture summary

```
[users] → nginx → api.php  ─── Redis cache hit (~99% of requests after warm-up)
                       │
                       └── miss → run-brk.sh → Massive API (rare path)

[market_data_refresher.py] — daemon, sole owner of Massive API for warm tickers
        │
        └── refreshes Redis cache every 30-300s per subscription
```

## Step 1 — Install Redis on dev

```bash
ssh -i ~/.ssh/pengo agent@dev.hacktrader.com '
  sudo apt update
  sudo apt install -y redis-server
  sudo systemctl enable redis-server
  sudo systemctl start redis-server
'
```

Verify:

```bash
ssh -i ~/.ssh/pengo agent@dev.hacktrader.com 'redis-cli ping'
```

Expected: `PONG`.

## Step 2 — Configure Redis for our use case

```bash
ssh -i ~/.ssh/pengo agent@dev.hacktrader.com '
  sudo sed -i "s/^# maxmemory <bytes>/maxmemory 256mb/" /etc/redis/redis.conf
  sudo sed -i "s/^# maxmemory-policy noeviction/maxmemory-policy allkeys-lru/" /etc/redis/redis.conf
  sudo systemctl restart redis-server
  redis-cli config get maxmemory
  redis-cli config get maxmemory-policy
'
```

Expected output:

```
1) "maxmemory"
2) "268435456"
1) "maxmemory-policy"
2) "allkeys-lru"
```

## Step 3 — Install Python redis library

```bash
ssh -i ~/.ssh/pengo agent@dev.hacktrader.com '
  sudo pip install --break-system-packages redis
'
```

Verify:

```bash
ssh -i ~/.ssh/pengo agent@dev.hacktrader.com 'python3 -c "import redis; print(redis.__version__)"'
```

## Step 4 — Deploy the v0.13.0 code

```bash
cd /Users/jay/Documents/claude/hacktrader
make lint && make test && make deploy MSG="v0.13.0: Redis subscription cache + market data refresher daemon"
```

This rsyncs the new files:

- `lib/cache.php` — PHP Redis wrapper
- `market_data_refresher.py` — the refresher daemon
- `hacktrader-refresher.service` — systemd unit
- Updates `api.php` to read from Redis first

## Step 5 — Install predis (composer dependency)

```bash
ssh -i ~/.ssh/pengo agent@dev.hacktrader.com '
  cd /var/www/html && composer install --no-dev --optimize-autoloader
'
```

Verify predis is in vendor:

```bash
ssh -i ~/.ssh/pengo agent@dev.hacktrader.com 'ls /var/www/html/vendor/predis/predis/src/Client.php'
```

## Step 6 — Install the systemd service

```bash
ssh -i ~/.ssh/pengo agent@dev.hacktrader.com '
  sudo cp /var/www/html/hacktrader-refresher.service /etc/systemd/system/
  sudo systemctl daemon-reload
  sudo systemctl enable hacktrader-refresher
  sudo systemctl start hacktrader-refresher
  sleep 2
  sudo systemctl status hacktrader-refresher --no-pager
'
```

Expected: `Active: active (running)`.

## Step 7 — Verify the cache is populating

Wait ~60 seconds for the pre-warm sweep to complete, then:

```bash
ssh -i ~/.ssh/pengo agent@dev.hacktrader.com '
  echo "=== active subscriptions ==="
  redis-cli zrange ht:subscriptions 0 -1
  echo
  echo "=== cached scores ==="
  redis-cli keys "score:*" | head -20
  echo
  echo "=== sample score (SPY 5m 100) ==="
  redis-cli get "score:SPY:5m:100" | head -c 200
'
```

You should see ~21 pre-warmed subscriptions (the popular tickers list in `market_data_refresher.py`'s `PRE_WARM_TICKERS`), corresponding `score:*` keys, and JSON output for SPY.

## Step 8 — Watch the daemon logs

```bash
ssh -i ~/.ssh/pengo agent@dev.hacktrader.com 'sudo journalctl -u hacktrader-refresher -f --no-pager'
```

You should see lines like:

```
[INFO] refresher: HackTrader market data refresher starting
[INFO] refresher: Redis connected; entering sweep loop
[INFO] refresher: Refreshing 21 of 21 active subscriptions
```

Press Ctrl+C to exit.

## Step 9 — Hit the dashboard, observe cache hits

Load `https://dev.hacktrader.com/dashboard.php` in your browser. Refresh
a few times. Then check nginx error log for the `cache_hit` source tier:

```bash
ssh -i ~/.ssh/pengo agent@dev.hacktrader.com 'sudo tail -20 /var/www/html/api.log'
```

You should see entries with `Status: Success via redis_cache` (or similar
indicating Redis hits). If everything's working, the *first* request
for a ticker hits Massive (live), and every subsequent request within
the refresh window comes from Redis.

## Step 10 — Stress test (optional)

Curl the dashboard API endpoint repeatedly to confirm cache hits:

```bash
for i in {1..20}; do
  time curl -s "https://dev.hacktrader.com/api.php?ticker=SPY&period=5m&lookback=100" -H "Cookie: PHPSESSID=..." > /dev/null
done
```

After the first request (which may take 500-1500ms for the live fetch),
subsequent requests should consistently return in under 50ms. That's
the Redis cache doing its job.

---

## Troubleshooting

### Redis is down / unreachable

`lib/cache.php` gracefully degrades to no-cache mode — every request
falls through to the file cache or live Massive fetch. Symptoms:

- No `redis` source_tier entries in api.log
- `journalctl -u hacktrader-refresher` shows reconnection errors
- The site keeps working but loses the API-cost reduction

Restart Redis:

```bash
sudo systemctl restart redis-server
```

### Refresher daemon won't start

Check the logs:

```bash
sudo journalctl -u hacktrader-refresher --no-pager | tail -50
```

Common issues:

- Python redis library not installed: `sudo pip install --break-system-packages redis`
- run-brk.py import error: confirm `fetch_massive` and `compute_output` are exposed
- Permission denied on `/var/www/html`: confirm service `User=agent`

### Cache hit rate is low

After 5-10 minutes of warm-up, you should see >80% hit rate. If lower:

- Confirm the refresher is running: `sudo systemctl status hacktrader-refresher`
- Confirm subscriptions are being touched: `redis-cli zcard ht:subscriptions` should be > 0
- Confirm the refresh interval matches the request cadence

### Massive API costs aren't dropping

The cache hit rate is the proxy for API cost reduction. Watch the
`api_usage_tracker.json` to confirm `source: cache` (Redis or file) is
dominating over `source: massive` (live fetches).

If you're seeing too many live fetches:

- Increase the refresh interval (less frequent refresh = fewer API calls)
- Increase the dormant cutoff (DORMANT_SECONDS env var)
- Increase the pre-warm ticker list to cover more of your active basket

## Performance metrics (target after warm-up)

| Metric | Target |
|---|---|
| Cache hit rate | >95% |
| p50 api.php latency | <50ms (cache hit) |
| p99 api.php latency | <500ms (cache miss path) |
| Massive API calls per day | <5K (scales with unique tickers, not users) |
| Redis memory usage | <50MB at 100 subscriptions |

## Cost summary

| Item | Cost |
|---|---|
| Redis server on dev (already paid for VM) | $0 |
| Refresher daemon (Python on existing VM) | $0 |
| Bandwidth (Redis is localhost) | $0 |
| **Marginal cost of architecture** | **$0/month** |
| **API call savings at 1000 users** | **~$120/mo** (Massive tier downgrade or call reduction) |
