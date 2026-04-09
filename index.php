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
                    <div class='metric-value'>v0.7.2.4</div>
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
            <div class='signin-note'>Protected dev environment · visual refresh enabled</div>
        </section>
    </main>
    <footer>HackTrader · v0.7.2.4</footer>
</body>
</html>
