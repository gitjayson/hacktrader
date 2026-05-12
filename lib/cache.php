<?php
/**
 * lib/cache.php — Redis-backed subscription cache for HackTrader.
 *
 * v0.13.0 — turns the per-user API cost problem into a per-ticker
 * cost problem by sharing market-data fetches across all users
 * monitoring the same ticker.
 *
 * Architecture:
 *   - Cache keys are namespaced: "bars:{ticker}:{period}", "score:{ticker}:{period}"
 *   - Each access updates a sorted-set "subscriptions" with the current
 *     timestamp, so the refresher daemon knows which entries are "hot"
 *     and need to be kept fresh.
 *   - TTL on the data keys is 1 hour (matches the daemon's
 *     dormant-subscription cutoff), so if the daemon dies entries
 *     don't go stale forever.
 *
 * The interface here is deliberately small. Three functions:
 *   ht_cache_get($key)                — read cached value, or null
 *   ht_cache_set($key, $value, $ttl)  — write value with TTL
 *   ht_cache_touch_subscription($key) — mark key as recently accessed
 *
 * Plus one convenience helper for the common pattern:
 *   ht_cache_remember($key, $ttl, $computeFn)
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

const HT_CACHE_REDIS_HOST = '127.0.0.1';
const HT_CACHE_REDIS_PORT = 6379;
const HT_CACHE_SUBSCRIPTION_KEY = 'ht:subscriptions';
const HT_CACHE_DEFAULT_TTL = 3600; // 1 hour — matches refresher dormant cutoff

function ht_cache_client(): ?\Predis\Client {
    // v0.13.x security-review fix: memoize BOTH success and failure for
    // the lifetime of the PHP request. Before this fix, $client stayed
    // null after a failed connection, so each subsequent ht_cache_*
    // call would retry the connection — touch, get, backfill set, and
    // last-ref set could each attempt a fresh connect against a dead
    // Redis, adding seconds of avoidable latency per request and
    // flooding the error log. Now the first failure short-circuits all
    // subsequent calls in the same request.
    static $client = null;
    static $tried = false;
    if ($tried) return $client;
    $tried = true;

    try {
        $client = new \Predis\Client([
            'scheme' => 'tcp',
            'host'   => HT_CACHE_REDIS_HOST,
            'port'   => HT_CACHE_REDIS_PORT,
            'read_write_timeout' => 1.0, // fail fast if Redis is down
        ]);
        // Touch the server to confirm connectivity. If Redis isn't up
        // we return null and callers gracefully degrade to direct fetches.
        $client->ping();
        return $client;
    } catch (\Throwable $e) {
        error_log('lib/cache.php: Redis unavailable, falling back to no-cache: ' . $e->getMessage());
        $client = null;
        return null;
    }
}

/**
 * Read a cached JSON-encoded value. Returns null on miss or on Redis
 * unavailability (callers should treat both the same way).
 */
function ht_cache_get(string $key) {
    $r = ht_cache_client();
    if (!$r) return null;
    try {
        $raw = $r->get($key);
        if ($raw === null) return null;
        $decoded = json_decode($raw, true);
        return $decoded === null ? null : $decoded;
    } catch (\Throwable $e) {
        error_log('lib/cache.php: get failed for ' . $key . ': ' . $e->getMessage());
        return null;
    }
}

/**
 * Write a JSON-encoded value with a TTL in seconds. Silently no-ops
 * if Redis isn't available.
 */
function ht_cache_set(string $key, $value, int $ttlSeconds = HT_CACHE_DEFAULT_TTL): void {
    $r = ht_cache_client();
    if (!$r) return;
    try {
        $r->setex($key, $ttlSeconds, json_encode($value));
    } catch (\Throwable $e) {
        error_log('lib/cache.php: set failed for ' . $key . ': ' . $e->getMessage());
    }
}

/**
 * Mark a (ticker, period) key as recently accessed by a user, so the
 * background refresher daemon keeps it warm. The subscriptions are
 * stored in a Redis sorted set with score = unix timestamp; the
 * daemon scans this set on each cycle and drops entries that haven't
 * been accessed within an hour.
 */
function ht_cache_touch_subscription(string $subscriptionKey): void {
    $r = ht_cache_client();
    if (!$r) return;
    try {
        $r->zadd(HT_CACHE_SUBSCRIPTION_KEY, [$subscriptionKey => time()]);
    } catch (\Throwable $e) {
        error_log('lib/cache.php: touch failed for ' . $subscriptionKey . ': ' . $e->getMessage());
    }
}

/**
 * The common pattern: try to read the cache; if miss, compute the
 * value and write it. Also touches the subscription so the daemon
 * keeps it fresh.
 *
 * The subscription key is the "logical" identity of what's being
 * cached (e.g., "TSLA:5m"), independent of the actual cache key
 * structure. This lets us cache multiple derived values (bars, score,
 * etc.) under the same subscription.
 *
 * @param string   $cacheKey         The actual Redis key to read/write
 * @param string   $subscriptionKey  The logical subscription identity (for the daemon)
 * @param int      $ttlSeconds       How long the value stays valid
 * @param callable $computeFn        Called on cache miss; returns the value to cache
 * @return mixed The cached or computed value
 */
function ht_cache_remember(string $cacheKey, string $subscriptionKey, int $ttlSeconds, callable $computeFn) {
    // Always mark the subscription as touched — even on cache hit —
    // so the daemon knows users are still interested in this ticker.
    ht_cache_touch_subscription($subscriptionKey);

    $cached = ht_cache_get($cacheKey);
    if ($cached !== null) return $cached;

    $value = $computeFn();
    if ($value !== null) {
        ht_cache_set($cacheKey, $value, $ttlSeconds);
    }
    return $value;
}

/**
 * Refresh-interval table per period. The refresher daemon uses this
 * to decide how often to re-fetch each (ticker, period) subscription.
 *
 * Rule: refresh slightly more often than the bar-period itself, so
 * users always see the current bar within seconds of its formation.
 * For a 15-minute-delayed feed, "current bar" really means "the bar
 * that ended 15 minutes ago" — but the refresh cadence still matters
 * because the upstream feed updates on its own schedule.
 *
 * When upgrading to a real-time feed, these intervals should tighten.
 */
/**
 * Singleflight lock for cache misses. When Redis is cold (e.g., just
 * restarted) and multiple concurrent requests miss the cache for the
 * same key, they would all fall through to fetch from upstream — wasting
 * API calls and undercutting the "one upstream call per ticker" goal.
 *
 * This function acquires a short-lived Redis lock for the given key. The
 * caller treats it like a mutex around the expensive fetch path:
 *
 *   - If acquired: returns a unique ownership token. Caller proceeds
 *     with the live fetch, then releases by passing the token back.
 *   - If not acquired: returns null. Another request owns the lock;
 *     caller should wait on the cache instead.
 *
 * Lock auto-expires via TTL so a crashed winner doesn't permanently
 * block others.
 *
 * v0.13.3 — Switched return type from bool to ?string (ownership
 * token) so release can verify ownership before deleting. The old
 * blind-delete contract had a race: if the wait timeout was shorter
 * than the TTL, a waiter could give up, fall through to its own fetch,
 * then on the way out call release() and delete the *original*
 * owner's still-valid lock. With token-based compare-and-delete the
 * release is a no-op unless we still own it.
 *
 * Failure-mode sentinels:
 *   - Redis unavailable → return '' (empty string). Treat empty token
 *     as "no-coordination mode, proceed but don't release" — that
 *     way the caller still gets through cleanly without us pretending
 *     it owns a lock that doesn't exist.
 *   - Redis threw on the SET → also return '', same fail-open story.
 *   - Acquired → return random hex token (must be passed to release).
 *   - Did not acquire (someone else holds the lock) → return null.
 */
function ht_cache_acquire_singleflight(string $lockKey, int $lockTtlSeconds = 15): ?string {
    $r = ht_cache_client();
    if (!$r) return ''; // No Redis: fail-open sentinel.
    try {
        $token = bin2hex(random_bytes(16));
        // SET key value NX EX ttl — atomic set-if-not-exists with expiry.
        $result = $r->set('lock:' . $lockKey, $token, 'EX', $lockTtlSeconds, 'NX');
        $acquired = $result === true || (is_object($result) && (string) $result === 'OK');
        return $acquired ? $token : null;
    } catch (\Throwable $e) {
        error_log('lib/cache.php: singleflight acquire failed for ' . $lockKey . ': ' . $e->getMessage());
        return ''; // Fail-open — caller proceeds with fetch, but won't release.
    }
}

/**
 * Release a singleflight lock if (and only if) we still own it.
 *
 * The compare-and-delete is done atomically server-side via a Lua
 * script so a slow original owner doesn't get its lock yanked by an
 * unrelated request that timed out waiting and is now cleaning up.
 *
 * Pass the token returned by ht_cache_acquire_singleflight. An empty
 * token means "no lock to release" (fail-open path); we no-op so the
 * caller doesn't accidentally blast someone else's lock.
 */
function ht_cache_release_singleflight(string $lockKey, string $token): void {
    if ($token === '') return; // Fail-open sentinel — nothing to release.
    $r = ht_cache_client();
    if (!$r) return;
    try {
        // Compare-and-delete via Lua. KEYS[1] = lock name, ARGV[1] = our token.
        // If the lock still holds our token, delete it. Otherwise leave it alone
        // so the legitimate owner (or its TTL) can deal with it.
        $script = "if redis.call('GET', KEYS[1]) == ARGV[1] then return redis.call('DEL', KEYS[1]) else return 0 end";
        $r->eval($script, 1, 'lock:' . $lockKey, $token);
    } catch (\Throwable $e) {
        // Lock will TTL out anyway; nothing to do.
    }
}

/**
 * Wait briefly for another request to populate the cache for $cacheKey.
 * Returns the cached value if it appears within the wait window, or null
 * if the wait timed out (caller should fall through to its own fetch).
 *
 * Polls every 100ms up to $maxWaitMs total.
 */
function ht_cache_wait_for_value(string $cacheKey, int $maxWaitMs = 2000) {
    $steps = max(1, intdiv($maxWaitMs, 100));
    for ($i = 0; $i < $steps; $i++) {
        usleep(100000); // 100ms
        $val = ht_cache_get($cacheKey);
        if ($val !== null) return $val;
    }
    return null;
}

function ht_cache_refresh_interval_seconds(string $period): int {
    // v0.13.0 — intervals doubled from initial (15/60/300/1800) since the
    // 15-min upstream delay caps how fresh data can ever be anyway. Halves
    // Massive load with no perceptible user-facing change. Keep in sync
    // with market_data_refresher.py's REFRESH_INTERVAL_BY_PERIOD.
    switch ($period) {
        case '1m':  return 30;
        case '5m':  return 120;   // 2 minutes
        case '1h':  return 600;   // 10 minutes
        case '1d':  return 3600;  // 1 hour
        default:    return 120;
    }
}
