<?php
/**
 * lib/subscription.php — user lookups, plan resolution, gate functions.
 *
 * Public API:
 *   upsert_user_from_oauth(email, google_sub, name)  → user row
 *   current_user()                                    → user row (or null)
 *   user_plan(user)                                   → plan slug ('free'|'plus'|'pro')
 *   user_has_active_subscription(user)                → bool
 *   user_can_add_ticker(user)                         → bool
 *   user_can_make_api_call(user)                      → bool
 *   record_api_call(user)                             → void  (increments monthly counter)
 *   user_usage_summary(user)                          → array  (for dashboard display)
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/plans.php';

if (!defined('HACKTRADER_SUBSCRIPTION_LOADED')) {
    define('HACKTRADER_SUBSCRIPTION_LOADED', true);

    /**
     * Upsert a user row keyed on google_sub. Called from callback.php after
     * a successful Google OAuth handshake. Returns the resulting row.
     */
    function upsert_user_from_oauth(string $email, string $google_sub, ?string $name = null): array {
        $db = hacktrader_db();
        $now = time();
        $existing = $db->prepare('SELECT * FROM users WHERE google_sub = ? OR email = ? LIMIT 1');
        $existing->execute([$google_sub, $email]);
        $row = $existing->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            // Update mutable fields. Don't overwrite plan/subscription_status —
            // those are owned by the Stripe webhook handler.
            $update = $db->prepare(
                'UPDATE users SET email = ?, google_sub = ?, name = ?, updated_at = ? WHERE id = ?'
            );
            $update->execute([$email, $google_sub, $name, $now, $row['id']]);
            return user_by_id((int) $row['id']);
        }

        // v0.13.0 — First-time login → seed with a 7-day Starter trial so
        // the user gets the actual paid-tier experience (25 tickers,
        // 25k calls/mo) for their first session. After the trial expires,
        // user_plan() falls back to 'free' automatically (via the
        // user_has_active_subscription gate), no separate downgrade job.
        // Trial end stamps current_period_end so the standard "is active"
        // check works for the trial too; webhook overwrites this if/when
        // the user actually subscribes.
        $trialEnd = $now + (7 * 86400);
        $insert = $db->prepare(
            'INSERT INTO users(email, google_sub, name, plan, subscription_status, current_period_end, trial_end, created_at, updated_at)
             VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([$email, $google_sub, $name, 'starter', 'trialing', $trialEnd, $trialEnd, $now, $now]);
        $userId = (int) $db->lastInsertId();
        return user_by_id($userId);
    }

    function user_by_id(int $id): ?array {
        $db = hacktrader_db();
        $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    function user_by_email(string $email): ?array {
        $db = hacktrader_db();
        $stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    function user_by_stripe_customer(string $customerId): ?array {
        $db = hacktrader_db();
        $stmt = $db->prepare('SELECT * FROM users WHERE stripe_customer_id = ?');
        $stmt->execute([$customerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Returns the currently logged-in user row, or null. */
    function current_user_record(): ?array {
        if (empty($_SESSION['user_email'])) {
            return null;
        }
        return user_by_email((string) $_SESSION['user_email']);
    }

    /**
     * Returns the user's effective plan slug. Falls back to 'free' if:
     *   - the slug is unknown (defensive), OR
     *   - the user's subscription isn't currently active (expired trial,
     *     cancelled subscription past period end, etc.).
     *
     * The 'plan' column in the users table is the *intended* tier (what
     * Stripe is billing, or what the trial seeded). Combining it with
     * user_has_active_subscription() gives us the *effective* tier — what
     * the user actually gets to use right now. This is what the quota
     * gates should consult.
     */
    function user_plan(array $user): string {
        if (!user_has_active_subscription($user)) {
            return 'free';
        }
        $slug = (string) ($user['plan'] ?? 'free');
        return hacktrader_plan($slug) ? $slug : 'free';
    }

    function user_has_active_subscription(array $user): bool {
        $status = (string) ($user['subscription_status'] ?? 'none');
        if (!in_array($status, ['active', 'trialing'], true)) {
            return false;
        }
        $end = (int) ($user['current_period_end'] ?? 0);
        return $end > time();
    }

    /**
     * Quota gates. These take a user row and return a boolean. The free tier
     * always gets THROUGH the gate up to the quota — paid plans get more.
     */
    function user_ticker_count(array $user): int {
        $db = hacktrader_db();
        $stmt = $db->prepare('SELECT COUNT(*) FROM user_tickers WHERE user_id = ?');
        $stmt->execute([$user['id']]);
        return (int) $stmt->fetchColumn();
    }

    function user_can_add_ticker(array $user): bool {
        $plan = hacktrader_plan(user_plan($user));
        $limit = (int) $plan['ticker_limit'];
        if ($limit >= HACKTRADER_UNLIMITED) return true;
        return user_ticker_count($user) < $limit;
    }

    function current_billing_window_start(array $user): int {
        // For paying users we anchor on current_period_end (so the call counter
        // resets exactly when Stripe rolls). For free users we anchor on the
        // 1st of the current calendar month.
        $end = (int) ($user['current_period_end'] ?? 0);
        if ($end > time()) {
            // Roughly a month before period end (Stripe uses calendar months
            // but the exact boundary doesn't matter for quota purposes).
            return $end - (30 * 86400);
        }
        return strtotime(date('Y-m-01 00:00:00'));
    }

    function user_api_call_count(array $user): int {
        $db = hacktrader_db();
        $stmt = $db->prepare('SELECT call_count FROM api_usage WHERE user_id = ? AND window_start = ?');
        $stmt->execute([$user['id'], current_billing_window_start($user)]);
        $count = $stmt->fetchColumn();
        return $count !== false ? (int) $count : 0;
    }

    function user_can_make_api_call(array $user): bool {
        $plan = hacktrader_plan(user_plan($user));
        $limit = (int) $plan['api_call_limit'];
        if ($limit >= HACKTRADER_UNLIMITED) return true;
        return user_api_call_count($user) < $limit;
    }

    /**
     * Increment the user's monthly API call counter. Called once per
     * billable api.php request, AFTER the gate decision.
     */
    function record_api_call(array $user): void {
        $db = hacktrader_db();
        $window = current_billing_window_start($user);
        // INSERT OR IGNORE then UPDATE — sqlite-friendly upsert without the
        // 3.24+ ON CONFLICT syntax (Ubuntu LTS sqlite versions vary).
        $insert = $db->prepare('INSERT OR IGNORE INTO api_usage(user_id, window_start, call_count) VALUES(?, ?, 0)');
        $insert->execute([$user['id'], $window]);
        $update = $db->prepare('UPDATE api_usage SET call_count = call_count + 1 WHERE user_id = ? AND window_start = ?');
        $update->execute([$user['id'], $window]);
    }

    /** Compact summary for the dashboard's Subscription panel. */
    function user_usage_summary(array $user): array {
        $plan = hacktrader_plan(user_plan($user));
        $callsUsed = user_api_call_count($user);
        $tickerCount = user_ticker_count($user);
        $callLimit = (int) $plan['api_call_limit'];
        $tickerLimit = (int) $plan['ticker_limit'];
        return [
            'plan' => $plan['slug'],
            'plan_name' => $plan['display_name'],
            'price_monthly' => $plan['price_monthly'],
            'subscription_status' => $user['subscription_status'] ?? 'none',
            'current_period_end' => $user['current_period_end'] ?? null,
            'trial_end' => $user['trial_end'] ?? null,
            'tickers' => [
                'used' => $tickerCount,
                'limit' => $tickerLimit >= HACKTRADER_UNLIMITED ? null : $tickerLimit,
            ],
            'api_calls' => [
                'used' => $callsUsed,
                'limit' => $callLimit >= HACKTRADER_UNLIMITED ? null : $callLimit,
            ],
        ];
    }

    /**
     * Locate and parse secrets.json. Mirrors the candidate-chain pattern
     * callback.php uses so both files agree on where the file lives:
     *
     *   /var/www/secrets.json     (preferred — above webroot, safer)
     *   /var/www/html/secrets.json (fallback — same dir as the PHP files)
     *
     * Returns the decoded array, or [] if no secrets file is found.
     */
    function hacktrader_load_secrets(): array {
        $candidates = [
            __DIR__ . '/../secrets.json',     // /var/www/html/secrets.json
            dirname(__DIR__, 2) . '/secrets.json', // /var/www/secrets.json (one above webroot)
            '/var/www/secrets.json',          // explicit fallback
        ];
        foreach ($candidates as $path) {
            if (is_file($path) && is_readable($path)) {
                $raw = file_get_contents($path);
                $json = $raw !== false ? json_decode($raw, true) : null;
                if (is_array($json)) return $json;
            }
        }
        return [];
    }

    /**
     * Load Stripe-related secrets from secrets.json. Returns null fields if
     * not yet configured — calling code should treat that as "Stripe not
     * wired up yet" and emit a friendly message instead of erroring.
     */
    function hacktrader_stripe_config(): array {
        $json = hacktrader_load_secrets();
        return [
            'publishable_key' => $json['STRIPE_PUBLISHABLE_KEY'] ?? null,
            'secret_key' => $json['STRIPE_SECRET_KEY'] ?? null,
            'webhook_secret' => $json['STRIPE_WEBHOOK_SECRET'] ?? null,
            // v0.13.0 — Starter is the active $9.99/mo tier for the
            // delayed-feed phase. Plus and Pro remain in the config so
            // the wiring is ready when those tiers go live.
            'price_starter' => $json['STRIPE_PRICE_STARTER'] ?? null,
            'price_plus' => $json['STRIPE_PRICE_PLUS'] ?? null,
            'price_pro' => $json['STRIPE_PRICE_PRO'] ?? null,
        ];
    }

    /**
     * Build an absolute URL back into this app for the host the request
     * came from. Used so success/cancel/return URLs given to Stripe match
     * whatever environment is running — dev.hacktrader.com on the dev box,
     * hacktrader.com in production, etc. Same code, different deploy hosts.
     *
     * Falls back to hacktrader.com if no Host header is available (e.g. CLI).
     */
    function hacktrader_app_url(string $path = ''): string {
        $scheme = !empty($_SERVER['HTTP_X_FORWARDED_PROTO'])
            ? $_SERVER['HTTP_X_FORWARDED_PROTO']
            : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
        $host = $_SERVER['HTTP_HOST'] ?? 'hacktrader.com';
        $path = $path === '' ? '' : '/' . ltrim($path, '/');
        return $scheme . '://' . $host . $path;
    }
}
