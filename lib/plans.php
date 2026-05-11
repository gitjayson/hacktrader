<?php
/**
 * lib/plans.php — entitlement matrix for HackTrader subscription plans.
 *
 * Single source of truth for ticker / API-call quotas per plan. The names
 * match the Stripe Product slugs and the values are checked by the gate
 * functions in lib/subscription.php.
 *
 * v0.13.0 — Reading B (delayed-feed phase):
 *   free    : 5 tickers   /  1,000 calls / month
 *   starter : 25 tickers  / 25,000 calls / month  ($9.99)   ← active
 *   plus    : 25 tickers  / 25,000 calls / month  ($29)     ← coming soon w/ live data
 *   pro     : unlimited   / unlimited              ($99)    ← coming soon w/ live data + multi-window
 *
 * Pricing rationale: while the upstream feed is 15-minute delayed, the
 * value ceiling is too low to justify $29 or $99. Those tiers exist as
 * placeholders for the post-real-time-feed product; they're marked
 * status='coming_soon' so the pricing UI grays them out and prevents
 * Stripe Checkout from accepting subscriptions for them.
 */

declare(strict_types=1);

if (!defined('HACKTRADER_PLANS_LOADED')) {
    define('HACKTRADER_PLANS_LOADED', true);

    /** Sentinel for "no limit". Any limit field set to PHP_INT_MAX means unlimited. */
    define('HACKTRADER_UNLIMITED', PHP_INT_MAX);

    function hacktrader_plans(): array {
        return [
            'free' => [
                'slug'           => 'free',
                'display_name'   => 'Free',
                'price_monthly'  => 0,
                'price_display'  => '$0',
                'cadence'        => 'forever',
                'status'         => 'active',
                'ticker_limit'   => 5,
                'api_call_limit' => 1000,
                'stripe_price_id' => null,
                'tagline'        => 'Enough to track one idea.',
                'features'       => [
                    '5 focus tickers',
                    '1,000 API calls / month',
                    'Full correlation radar',
                    'Levels + channels',
                    '15-minute delayed feed',
                ],
            ],
            // v0.13.0 — Starter is the active paid tier for the delayed-feed
            // phase. Same quotas as the old Plus tier ($29) at a third the
            // price, reflecting that the data is 15-min delayed and the
            // value proposition is the visualization, not real-time
            // actionability.
            'starter' => [
                'slug'           => 'starter',
                'display_name'   => 'HackTrader Starter',
                'price_monthly'  => 10,        // billed at $9.99 by Stripe
                'price_display'  => '$9.99',
                'cadence'        => '/ month',
                'status'         => 'active',
                'ticker_limit'   => 25,
                'api_call_limit' => 25000,
                // Filled in by lib/subscription.php from secrets.json STRIPE_PRICE_STARTER
                'stripe_price_id' => null,
                'tagline'        => 'Cover a full watchlist.',
                'features'       => [
                    '25 focus tickers',
                    '25,000 API calls / month',
                    'Everything in Free',
                    'Email support',
                    '15-minute delayed feed',
                ],
            ],
            // v0.13.0 — Plus is reserved for the live-data tier when the
            // real-time market feed lands. Marked coming_soon so the pricing
            // UI grays it out. Stripe price ID is intentionally null until
            // that tier is wired up against a real subscription product.
            'plus' => [
                'slug'           => 'plus',
                'display_name'   => 'HackTrader Plus',
                'price_monthly'  => 29,
                'price_display'  => '$29',
                'cadence'        => '/ month',
                'status'         => 'coming_soon',
                'ticker_limit'   => 25,
                'api_call_limit' => 25000,
                'stripe_price_id' => null,
                'tagline'        => 'Cover a full watchlist with live data.',
                'features'       => [
                    '25 focus tickers',
                    '25,000 API calls / month',
                    'Everything in Starter',
                    'Real-time market feed',
                    'Email support',
                ],
            ],
            // v0.13.0 — Pro is reserved for live data + power-user features
            // (multi-window monitoring, alerts, custom baskets). Coming soon
            // until real-time launches.
            'pro' => [
                'slug'           => 'pro',
                'display_name'   => 'HackTrader Pro',
                'price_monthly'  => 99,
                'price_display'  => '$99',
                'cadence'        => '/ month',
                'status'         => 'coming_soon',
                'ticker_limit'   => HACKTRADER_UNLIMITED,
                'api_call_limit' => HACKTRADER_UNLIMITED,
                'stripe_price_id' => null,
                'tagline'        => 'Run the whole tape.',
                'features'       => [
                    'Unlimited focus tickers',
                    'Unlimited API calls',
                    'Everything in Plus',
                    'Multi-window dashboard',
                    'Real-time market feed',
                    'Priority support',
                ],
            ],
        ];
    }

    function hacktrader_plan(string $slug): ?array {
        $plans = hacktrader_plans();
        return $plans[$slug] ?? null;
    }
}
