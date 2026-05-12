<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>HackTrader | Sign In</title>
    <link rel='preconnect' href='https://fonts.googleapis.com'>
    <link rel='preconnect' href='https://fonts.gstatic.com' crossorigin>
    <link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600;700&display=swap' rel='stylesheet'>
    <style>
        :root {
            --bg: #06111f;
            --bg2: #0c1727;
            --panel: rgba(10, 22, 37, 0.76);
            --panel-border: rgba(148, 163, 184, 0.18);
            --text: #e5eefb;
            --muted: #9fb1cb;
            --accent: #5eead4;
            --accent-2: #60a5fa;
            --accent-3: #22c55e;
            --shadow: 0 30px 80px rgba(0, 0, 0, 0.42);
        }
        * { box-sizing: border-box; }
        html, body { min-height: 100%; }
        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at 20% 20%, rgba(96,165,250,0.16), transparent 32%),
                radial-gradient(circle at 80% 30%, rgba(94,234,212,0.14), transparent 28%),
                linear-gradient(135deg, rgba(6,17,31,0.82), rgba(9,14,24,0.94)),
                url('https://images.unsplash.com/photo-1449824913935-59a10b8d2000?q=80&w=2070&auto=format&fit=crop') center center / cover no-repeat fixed;
            /* v0.13.2 — page is no longer a single-screen layout. Hero shot
               + pricing grid push content past the viewport. Switch from
               grid-centered + overflow:hidden to a normal flow that scrolls.
               Top-padded so the hero isn't stuck to the very top edge. */
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 36px 0 96px;
            min-height: 100vh;
        }
        .grid {
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 40px 40px;
            mask-image: radial-gradient(circle at center, black 48%, transparent 100%);
            pointer-events: none;
        }
        .shell {
            position: relative;
            width: min(960px, calc(100vw - 32px));
            display: grid;
            grid-template-columns: 1.2fr 0.95fr;
            gap: 24px;
            align-items: stretch;
        }
        .hero, .signin-container {
            background: var(--panel);
            border: 1px solid var(--panel-border);
            border-radius: 28px;
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            box-shadow: var(--shadow);
        }
        .hero {
            padding: 36px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 540px;
        }
        .eyebrow {
            display: inline-flex;
            gap: 10px;
            align-items: center;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: var(--accent);
            margin-bottom: 18px;
        }
        .eyebrow::before {
            content: '';
            width: 12px;
            height: 12px;
            border-radius: 999px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            box-shadow: 0 0 16px rgba(94,234,212,0.6);
        }
        h1 {
            margin: 0 0 16px;
            letter-spacing: -0.045em;
        }
        .hero-title-main,
        .hero-title-sub {
            display: block;
            font-weight: 800;
            line-height: 0.9;
        }
        .hero-title-main {
            font-size: clamp(44px, 6.4vw, 76px);
        }
        .hero-title-sub {
            font-size: clamp(46px, 6.55vw, 78px);
        }
        .gradient {
            background: linear-gradient(135deg, #ffffff, #9bd6ff 48%, #5eead4 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .hero p {
            margin: 0;
            color: var(--muted);
            max-width: 48ch;
            font-size: 16px;
            line-height: 1.7;
        }
        /* v0.13.2 — small italic note under the metrics row, low visual
           weight so it doesn't fight the hero title but visible enough that
           a careful reader can't miss it before clicking sign-in. */
        .hero-note {
            margin: 18px 0 0;
            font-size: 12px;
            font-style: italic;
            color: rgba(159, 177, 203, 0.78);
            letter-spacing: 0.02em;
        }
        .hero-metrics {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            margin-top: 28px;
        }
        .metric {
            padding: 16px 18px;
            border-radius: 18px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            /* min-width: 0 lets grid items shrink below their content width
               so long text wraps inside the card instead of bleeding past the
               column boundary and overlapping the neighbor. */
            min-width: 0;
        }
        .metric-label {
            font-size: 11px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.14em;
            margin-bottom: 8px;
        }
        .metric-value {
            /* v0.13.2 — these are brand sentences (Visualization aid /
               Correlation radar / No signals, no advice), not data. Switch
               from JetBrains Mono 19px to inherited Inter at 15px so they
               read as prose AND fit inside the narrow grid cells without
               overflowing into the neighbor card. */
            font-family: inherit;
            font-weight: 700;
            font-size: 15px;
            line-height: 1.3;
            letter-spacing: -0.005em;
            word-break: break-word;
            overflow-wrap: anywhere;
        }
        /* v0.13.2 — hero screenshot section. Spans the full shell width
           (escapes the 2-column hero/signin grid by being its own row).
           Glass border + faint outer glow so it reads as a polished
           product surface rather than a bare image. */
        .hero-shot {
            grid-column: 1 / -1;
            display: flex;
            justify-content: center;
            margin: 8px 0 0;
        }
        .hero-shot figure {
            margin: 0;
            max-width: 1100px;
            width: 100%;
        }
        .hero-shot img {
            display: block;
            width: 100%;
            height: auto;
            border-radius: 22px;
            border: 1px solid rgba(148, 163, 184, 0.18);
            background: rgba(0, 0, 0, 0.32);
            box-shadow: 0 32px 80px rgba(0, 0, 0, 0.55), 0 0 0 1px rgba(94, 234, 212, 0.06);
            transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
        }
        .hero-shot img:hover {
            transform: translateY(-3px);
            border-color: rgba(94, 234, 212, 0.28);
            box-shadow: 0 36px 92px rgba(0, 0, 0, 0.6), 0 0 0 1px rgba(94, 234, 212, 0.18);
        }
        .hero-shot figcaption {
            margin-top: 14px;
            text-align: center;
            font-size: 12px;
            color: var(--muted);
            letter-spacing: 0.06em;
            font-style: italic;
        }

        .signin-container {
            padding: 34px 28px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 540px;
        }
        .signin-card-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.18em;
            color: var(--accent-2);
            margin-bottom: 18px;
            font-weight: 700;
        }
        .signin-container h2 {
            margin: 0 0 12px;
            font-size: 34px;
            letter-spacing: -0.03em;
        }
        .signin-copy {
            color: var(--muted);
            line-height: 1.7;
            margin-bottom: 28px;
        }
        .signin-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 16px 20px;
            font-size: 16px;
            font-weight: 700;
            color: #07111d;
            background: linear-gradient(135deg, #ffffff, #dbeafe 42%, #5eead4 100%);
            border: none;
            border-radius: 16px;
            cursor: pointer;
            text-decoration: none;
            box-shadow: 0 16px 36px rgba(96,165,250,0.22);
            transition: transform 0.18s ease, box-shadow 0.18s ease;
        }
        .signin-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 22px 46px rgba(96,165,250,0.28);
        }
        .signin-note {
            margin-top: 18px;
            font-size: 13px;
            color: var(--muted);
        }
        footer {
            /* v0.13.2 — footer was position:fixed when the page was a
               single-screen layout. With scroll-flow it lives at the
               bottom of content like a normal block. */
            margin-top: 56px;
            width: min(960px, calc(100vw - 32px));
            text-align: center;
            font-size: 11px;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: rgba(229, 238, 251, 0.7);
        }
        /* v0.13.2 — footer links to privacy / tos / disclaimer pages.
           Co-Claude UI review flagged that these existed in the repo but
           weren't reachable from the public landing page. */
        footer.site-footer {
            display: flex;
            flex-direction: column;
            gap: 8px;
            align-items: center;
        }
        .footer-links {
            display: flex;
            gap: 14px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.12em;
        }
        .footer-links a {
            color: rgba(229, 238, 251, 0.78);
            text-decoration: none;
            transition: color 0.16s ease;
        }
        .footer-links a:hover {
            color: var(--accent);
        }
        .footer-copy {
            color: rgba(229, 238, 251, 0.52);
            line-height: 1.8;
        }
        .footer-brand,
        .footer-brand-link {
            color: inherit;
            text-decoration: none;
            transition: color 0.16s ease;
        }
        .footer-brand:hover,
        .footer-brand-link:hover {
            color: var(--accent);
        }
        .footer-brand-link {
            font-size: 10px;
            opacity: 0.82;
        }
        @media (max-width: 880px) {
            body { overflow: auto; }
            .shell {
                grid-template-columns: 1fr;
                width: min(680px, calc(100vw - 24px));
                margin: 24px 0 56px;
            }
            .hero, .signin-container { min-height: auto; }
            .hero-metrics { grid-template-columns: 1fr; }
        }
        /* v0.13.2 pricing section */
        .pricing-section {
            /* The .shell is a 2-col grid; without this the pricing card would
               render in col 1 only and look squashed. Span the full width. */
            grid-column: 1 / -1;
            margin-top: 48px;
            padding: 32px 24px 40px;
            border-radius: 24px;
            background: rgba(9, 22, 35, 0.55);
            border: 1px solid rgba(148, 163, 184, 0.14);
        }
        .pricing-eyebrow {
            font-size: 10px;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--cyan, #5eead4);
            font-weight: 600;
            text-align: center;
        }
        .pricing-title {
            margin: 6px 0 28px;
            font-size: 24px;
            font-weight: 600;
            letter-spacing: -0.02em;
            text-align: center;
        }
        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            max-width: 980px;
            margin: 0 auto;
        }
        .pricing-card {
            position: relative;
            padding: 24px 22px;
            border-radius: 16px;
            background: rgba(5, 12, 21, 0.7);
            border: 1px solid rgba(148, 163, 184, 0.16);
            display: flex;
            flex-direction: column;
        }
        .pricing-card.featured {
            border-color: rgba(94, 234, 212, 0.45);
            box-shadow: 0 0 0 1px rgba(94, 234, 212, 0.15) inset;
        }
        .pricing-flag {
            position: absolute;
            top: -10px;
            right: 14px;
            background: rgba(94, 234, 212, 0.92);
            color: #06111d;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.06em;
            padding: 4px 10px;
            border-radius: 999px;
            text-transform: uppercase;
        }
        .pricing-name {
            font-size: 14px;
            font-weight: 600;
            letter-spacing: -0.01em;
        }
        .pricing-price {
            margin: 12px 0 4px;
            display: flex;
            align-items: baseline;
            gap: 6px;
        }
        .pricing-amount {
            font-family: 'JetBrains Mono', ui-monospace, monospace;
            font-size: 36px;
            font-weight: 600;
            letter-spacing: -0.03em;
            font-variant-numeric: tabular-nums;
        }
        .pricing-cadence {
            font-size: 12px;
            color: rgba(232, 241, 255, 0.6);
        }
        .pricing-tagline {
            font-size: 12px;
            color: rgba(232, 241, 255, 0.7);
            margin-bottom: 16px;
            line-height: 1.5;
        }
        .pricing-features {
            list-style: none;
            padding: 0;
            margin: 0 0 20px;
            display: grid;
            gap: 8px;
            flex: 1;
        }
        .pricing-features li {
            position: relative;
            padding-left: 18px;
            font-size: 13px;
            color: rgba(232, 241, 255, 0.85);
        }
        .pricing-features li::before {
            content: '✓';
            position: absolute;
            left: 0;
            color: #5eead4;
            font-size: 11px;
        }
        .pricing-cta {
            display: block;
            text-align: center;
            text-decoration: none;
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid rgba(148, 163, 184, 0.3);
            color: #e8f1ff;
            transition: background 0.15s ease, border-color 0.15s ease;
        }
        .pricing-cta:hover { background: rgba(255, 255, 255, 0.04); }
        .pricing-cta.ghost { color: rgba(232, 241, 255, 0.85); }
        .pricing-cta.primary {
            background: rgba(94, 234, 212, 0.92);
            color: #06111d;
            border-color: transparent;
        }
        .pricing-cta.primary:hover { background: rgba(94, 234, 212, 1); }
        /* v0.13.2 — coming-soon tier styles. Card opacity reads as
           "available later" without removing the info; CTA replaced
           with a non-interactive label. */
        .pricing-card.coming-soon {
            opacity: 0.62;
            filter: saturate(0.7);
        }
        .pricing-card.coming-soon:hover {
            opacity: 0.78;
            transition: opacity 0.18s ease, filter 0.18s ease;
        }
        .pricing-flag-soon {
            background: rgba(251, 191, 36, 0.18);
            color: #fbbf24;
            border-color: rgba(251, 191, 36, 0.32);
        }
        .pricing-cta.disabled {
            display: block;
            text-align: center;
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 600;
            border: 1px dashed rgba(148, 163, 184, 0.32);
            color: rgba(232, 241, 255, 0.55);
            font-style: italic;
            cursor: not-allowed;
        }
        .pricing-cta.disabled:hover {
            background: transparent;
        }
        .pricing-fineprint {
            margin-top: 24px;
            font-size: 11px;
            text-align: center;
            color: rgba(232, 241, 255, 0.5);
        }
        @media (max-width: 720px) {
            .pricing-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class='grid'></div>
    <main class='shell'>
        <section class='hero'>
            <div>
                <div class='eyebrow'>Market structure visualization</div>
                <h1><span class='gradient hero-title-main'>HackTrader</span><span class='hero-title-sub'>See the structure, faster</span></h1>
                <p>
                    A focused visualization of correlation structure, support and resistance levels, channel geometry, and recent volume context — for one ticker and the basket that moves with it.
                    Built for traders who already have a thesis and want to see the chart, the basket, and the levels at a glance instead of clicking between five windows.
                </p>
            </div>
            <div class='hero-metrics'>
                <div class='metric'>
                    <div class='metric-label'>What it is</div>
                    <div class='metric-value'>Visualization aid</div>
                </div>
                <div class='metric'>
                    <div class='metric-label'>Centerpiece</div>
                    <div class='metric-value'>Correlation radar</div>
                </div>
                <div class='metric'>
                    <div class='metric-label'>Honest stance</div>
                    <div class='metric-value'>No signals, no advice</div>
                </div>
            </div>
            <!-- v0.13.2 — disclose 15-minute data delay up front. The platform
                 uses Massive's delayed feed (not real-time), and traders
                 deciding whether to authenticate with Google deserve to know
                 that before they hit the OAuth button. -->
            <p class='hero-note'>Quotes delayed 15 minutes (data via Massive). Not for time-sensitive trading.</p>
        </section>

        <section class='signin-container'>
            <div class='signin-card-label'>Secure access</div>
            <h2>Sign in to launch the dashboard</h2>
            <p class='signin-copy'>
                Authenticate with Google to access the HackTrader workspace. See your focus ticker, its correlated basket, and structural levels rendered in a single view — built to support your own analysis, not replace it.
            </p>
            <a href='callback.php' class='signin-button'>
                <span>Continue with Google</span>
            </a>
            <div class='signin-note'>Free tier included. New users start with a 7-day Plus trial. No credit card required to try.</div>
        </section>

        <!-- v0.13.2 — hero screenshot of the live radar. Co-Claude UI review
             flagged the absence of a product visual as the single biggest
             conversion leak on the page. Placed after the hero+signin row
             (full-width second row) so the visual lives between the pitch
             above and the pricing grid below. NVDA up 69% with only 1 of
             12 indicators confirming — the divergence is the story. -->
        <section class='hero-shot'>
            <figure>
                <img src='hero-radar.png' alt='HackTrader correlation radar — NVDA leaning up while most of its basket sits neutral, illustrating divergence at a glance' loading='eager'>
                <figcaption>NVDA leading 12 correlated peers — basket disposition at a glance.</figcaption>
            </figure>
        </section>

        <?php
            // v0.13.2 pricing block. Reads the entitlement matrix from
            // lib/plans.php so a price change in one place flows through.
            require_once __DIR__ . '/lib/plans.php';
            $allPlans = hacktrader_plans();
        ?>
        <section id='pricing' class='pricing-section'>
            <div class='pricing-eyebrow'>Pricing</div>
            <h2 class='pricing-title'>Pick the plan that fits how you trade</h2>
            <div class='pricing-grid'>
                <?php foreach ($allPlans as $slug => $plan): ?>
                    <?php
                        // v0.13.2 — Starter is the featured (most popular) tier
                        // during the delayed-feed phase. Plus/Pro are reserved
                        // for live-data and render with a "Coming soon" flag
                        // plus reduced opacity to read as forthcoming.
                        $isFeatured  = $slug === 'starter';
                        $isComingSoon = ($plan['status'] ?? 'active') === 'coming_soon';
                        $priceDisplay = $plan['price_display'] ?? ('$' . (int) $plan['price_monthly']);
                        $cadence      = $plan['cadence']       ?? '/ month';
                    ?>
                    <article class='pricing-card<?= $isFeatured ? ' featured' : '' ?><?= $isComingSoon ? ' coming-soon' : '' ?>'>
                        <?php if ($isFeatured): ?>
                            <div class='pricing-flag'>Most popular</div>
                        <?php elseif ($isComingSoon): ?>
                            <div class='pricing-flag pricing-flag-soon'>Live data — coming soon</div>
                        <?php endif; ?>
                        <div class='pricing-name'><?= htmlspecialchars($plan['display_name'], ENT_QUOTES) ?></div>
                        <div class='pricing-price'>
                            <?php if ($plan['price_monthly'] === 0): ?>
                                <span class='pricing-amount'>$0</span>
                                <span class='pricing-cadence'>forever</span>
                            <?php else: ?>
                                <span class='pricing-amount'><?= htmlspecialchars($priceDisplay, ENT_QUOTES) ?></span>
                                <span class='pricing-cadence'><?= htmlspecialchars($cadence, ENT_QUOTES) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class='pricing-tagline'><?= htmlspecialchars($plan['tagline'], ENT_QUOTES) ?></div>
                        <ul class='pricing-features'>
                            <?php foreach ($plan['features'] as $f): ?>
                                <li><?= htmlspecialchars($f, ENT_QUOTES) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if ($isComingSoon): ?>
                            <span class='pricing-cta disabled' aria-disabled='true'>Available with live data</span>
                        <?php elseif ($slug === 'free'): ?>
                            <a href='callback.php' class='pricing-cta ghost'>Start free</a>
                        <?php else: ?>
                            <a href='subscribe.php?plan=<?= htmlspecialchars($slug, ENT_QUOTES) ?>' class='pricing-cta<?= $isFeatured ? ' primary' : '' ?>'>Subscribe</a>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
            <div class='pricing-fineprint'>
                Cancel anytime from your billing portal. Prices in USD. New users start with a 7-day trial of Starter — no card required. Plus and Pro tiers unlock when the real-time market feed launches.
            </div>
        </section>
    </main>
    <footer class='site-footer'>
        <nav class='footer-links' aria-label='Legal'>
            <a href='privacy.html'>Privacy</a>
            <span aria-hidden='true'>·</span>
            <a href='tos.html'>Terms</a>
            <span aria-hidden='true'>·</span>
            <a href='disclaimer.php'>Disclaimer</a>
        </nav>
        <div class='footer-copy'>
            HackTrader v0.13.5 · © 2026 <a href='https://pngs.us' target='_blank' rel='noopener' class='footer-brand'>PENGUINS LLC</a> · All rights reserved.<br>
            <a href='https://pngs.us' target='_blank' rel='noopener' class='footer-brand-link'>pngs.us</a>
        </div>
    </footer>
</body>
</html>