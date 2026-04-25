<?php
/**
 * lib/db.php — SQLite handle + schema migrations for the users database.
 *
 * Pragmatically uses one DB at __DIR__/../users.sqlite. Pure-PHP, no
 * Composer migrations framework. The migration runner just stamps a
 * version row in `schema_version` and applies forward steps in order.
 */

declare(strict_types=1);

if (!defined('HACKTRADER_DB_LOADED')) {
    define('HACKTRADER_DB_LOADED', true);

    function hacktrader_db(): PDO {
        static $pdo = null;
        if ($pdo === null) {
            $path = __DIR__ . '/../users.sqlite';
            $pdo = new PDO('sqlite:' . $path);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec('PRAGMA journal_mode=WAL');
            $pdo->exec('PRAGMA foreign_keys=ON');
            hacktrader_db_migrate($pdo);
        }
        return $pdo;
    }

    function hacktrader_db_migrate(PDO $pdo): void {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_version (
                version INTEGER PRIMARY KEY,
                applied_at INTEGER NOT NULL
            )'
        );
        $current = (int) ($pdo->query('SELECT MAX(version) FROM schema_version')->fetchColumn() ?: 0);

        $migrations = [
            1 => [
                // users: a row per Google-authenticated user, with subscription
                // state mirrored from Stripe webhooks.
                'CREATE TABLE IF NOT EXISTS users (
                    id INTEGER PRIMARY KEY,
                    email TEXT UNIQUE NOT NULL,
                    google_sub TEXT UNIQUE NOT NULL,
                    name TEXT,
                    stripe_customer_id TEXT,
                    stripe_subscription_id TEXT,
                    plan TEXT NOT NULL DEFAULT "free",
                    subscription_status TEXT NOT NULL DEFAULT "none",
                    current_period_end INTEGER,
                    trial_end INTEGER,
                    created_at INTEGER NOT NULL,
                    updated_at INTEGER NOT NULL
                )',
                'CREATE INDEX IF NOT EXISTS idx_users_email ON users(email)',
                'CREATE INDEX IF NOT EXISTS idx_users_stripe_customer ON users(stripe_customer_id)',

                // tickers: per-user watched symbols. Composite unique on
                // (user_id, symbol) so adding the same ticker twice is a no-op.
                'CREATE TABLE IF NOT EXISTS user_tickers (
                    id INTEGER PRIMARY KEY,
                    user_id INTEGER NOT NULL,
                    symbol TEXT NOT NULL,
                    added_at INTEGER NOT NULL,
                    UNIQUE(user_id, symbol),
                    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
                )',

                // api_usage: monthly call counter per user. window_start is
                // the first day of the current billing month (unix ts).
                // We reset by inserting a new row when the current month rolls.
                'CREATE TABLE IF NOT EXISTS api_usage (
                    id INTEGER PRIMARY KEY,
                    user_id INTEGER NOT NULL,
                    window_start INTEGER NOT NULL,
                    call_count INTEGER NOT NULL DEFAULT 0,
                    UNIQUE(user_id, window_start),
                    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
                )',
                'CREATE INDEX IF NOT EXISTS idx_api_usage_user_window ON api_usage(user_id, window_start)',
            ],
        ];

        foreach ($migrations as $v => $stmts) {
            if ($v <= $current) continue;
            $pdo->beginTransaction();
            try {
                foreach ($stmts as $sql) {
                    $pdo->exec($sql);
                }
                $stmt = $pdo->prepare('INSERT INTO schema_version(version, applied_at) VALUES(?, ?)');
                $stmt->execute([$v, time()]);
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
        }
    }
}
