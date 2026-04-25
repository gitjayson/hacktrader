<?php
/**
 * lib/plans.php — entitlement matrix for HackTrader subscription plans.
 *
 * Single source of truth for ticker / API-call quotas per plan. The names
 * match the Stripe Product slugs and the values are checked by the gate
 * functions in lib/subscription.php.
 *
 * v0.9.0 — Reading A:
 *   free   : 5 tickers   /  1,000 calls / month
 *   plus   : 25 tickers  / 25,000 calls / month  ($29)
 *   pro    : unlimited  / unlimited                ($99)
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
                'ticker_limit'   => 5,
                'api_call_limit' => 1000,
                'stripe_price_id' => null,
                'tagline'        => 'Try it on a focused watchlist',
                'features'       => [
                    '5 focus tickers',
                    '1,000 API calls / month',
                    'Full breakout radar',
                    'Levels + channels',
                ],
            ],
            'plus' => [
                'slug'           => 'plus',
                'display_name'   => 'HackTrader Plus',
                'price_monthly'  => 29,
                'ticker_limit'   => 25,
                'api_call_limit' => 25000,
                // Filled in by lib/subscription.php from secrets.json STRIPE_PRICE_PLUS
                'stripe_price_id' => null,
                'tagline'        => 'For serious traders running a real watchlist',
                'features'       => [
                    '25 focus tickers',
                    '25,000 API calls / month',
                    'Everything in Free',
                    'Email support',
                ],
            ],
            'pro' => [
                'slug'           => 'pro',
                'display_name'   => 'HackTrader Pro',
                'price_monthly'  => 99,
                'ticker_limit'   => HACKTRADER_UNLIMITED,
                'api_call_limit' => HACKTRADER_UNLIMITED,
                'stripe_price_id' => null,
                'tagline'        => 'Unlimited tickers and calls for power users',
                'features'       => [
                    'Unlimited focus tickers',
                    'Unlimited API calls',
                    'Everything in Plus',
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
