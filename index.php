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
            display: grid;
            place-items: center;
            overflow: hidden;
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
        }
        .metric-label {
            font-size: 11px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.14em;
            margin-bottom: 8px;
        }
        .metric-value {
            font-family: 'JetBrains Mono', monospace;
            font-weight: 700;
            font-size: 19px;
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
            position: fixed;
            bottom: 12px;
            width: 100%;
            text-align: center;
            font-size: 11px;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: rgba(229, 238, 251, 0.7);
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
        /* v0.9.0 pricing section */
        .pricing-section {
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
                <div class='eyebrow'>Realtime market intelligence</div>
                <h1><span class='gradient hero-title-main'>HackTrader</span><span class='hero-title-sub'>Signal cockpit</span></h1>
                <p>
                    Monitor breakout pressure, channel structure, sector correlation, and focus-symbol momentum in one modern command view.
                    Built for fast scans, cleaner reads, and fewer fishy false signals.
                </p>
            </div>
            <div class='hero-metrics'>
                <div class='metric'>
                    <div class='metric-label'>Breakout engine</div>
                    <div class='metric-value'>v0.9.0</div>
                </div>
                <div class='metric'>
                    <div class='metric-label'>Visual system</div>
                    <div class='metric-value'>Modern glass UI</div>
                </div>
                <div class='metric'>
                    <div class='metric-label'>Focus</div>
                    <div class='metric-value'>Bias + channels</div>
                </div>
            </div>
        </section>

        <section class='signin-container'>
            <div class='signin-card-label'>Secure access</div>
            <h2>Sign in to launch the dashboard</h2>
            <p class='signin-copy'>
                Authenticate with Google to access the HackTrader workspace, watch the live correlation ring, and inspect support/resistance channel behavior in real time.
            </p>
            <a href='callback.php' class='signin-button'>
                <span>Continue with Google</span>
            </a>
            <div class='signin-note'>Free tier included. New users start with a 7-day Plus trial.</div>
        </section>

        <?php
            // v0.9.0 pricing block. Reads the entitlement matrix from
            // lib/plans.php so a price change in one place flows through.
            require_once __DIR__ . '/lib/plans.php';
            $allPlans = hacktrader_plans();
        ?>
        <section id='pricing' class='pricing-section'>
            <div class='pricing-eyebrow'>Pricing</div>
            <h2 class='pricing-title'>Pick the plan that fits how you trade</h2>
            <div class='pricing-grid'>
                <?php foreach ($allPlans as $slug => $plan): ?>
                    <?php $isPro = $slug === 'plus'; ?>
                    <article class='pricing-card<?= $isPro ? ' featured' : '' ?>'>
                        <?php if ($isPro): ?><div class='pricing-flag'>Most popular</div><?php endif; ?>
                        <div class='pricing-name'><?= htmlspecialchars($plan['display_name'], ENT_QUOTES) ?></div>
                        <div class='pricing-price'>
                            <?php if ($plan['price_monthly'] === 0): ?>
                                <span class='pricing-amount'>$0</span>
                                <span class='pricing-cadence'>forever</span>
                            <?php else: ?>
                                <span class='pricing-amount'>$<?= (int) $plan['price_monthly'] ?></span>
                                <span class='pricing-cadence'>/ month</span>
                            <?php endif; ?>
                        </div>
                        <div class='pricing-tagline'><?= htmlspecialchars($plan['tagline'], ENT_QUOTES) ?></div>
                        <ul class='pricing-features'>
                            <?php foreach ($plan['features'] as $f): ?>
                                <li><?= htmlspecialchars($f, ENT_QUOTES) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if ($slug === 'free'): ?>
                            <a href='callback.php' class='pricing-cta ghost'>Start free</a>
                        <?php else: ?>
                            <a href='subscribe.php?plan=<?= htmlspecialchars($slug, ENT_QUOTES) ?>' class='pricing-cta<?= $isPro ? ' primary' : '' ?>'>Subscribe</a>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
            <div class='pricing-fineprint'>
                Cancel anytime from your billing portal. Prices in USD. New users start on a 7-day Plus trial — no card required.
            </div>
        </section>
    </main>
    <footer>HackTrader v0.9.0 · © 2026 Jayson Hawley · All rights reserved.</footer>
</body>
</html>