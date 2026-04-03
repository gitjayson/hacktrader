<?php
session_start();
if (!isset($_SESSION['user_name'])) {
    header('Location: index.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept'])) {
    $_SESSION['agreed'] = true;
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>HackTrader | Disclaimer</title>
    <link rel='preconnect' href='https://fonts.googleapis.com'>
    <link rel='preconnect' href='https://fonts.gstatic.com' crossorigin>
    <link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600;700&display=swap' rel='stylesheet'>
    <style>
        :root {
            --bg: #06111d;
            --panel: rgba(9, 22, 35, 0.8);
            --panel-2: rgba(14, 26, 42, 0.72);
            --border: rgba(148, 163, 184, 0.16);
            --text: #e8f1ff;
            --muted: #9cb0ca;
            --cyan: #5eead4;
            --blue: #60a5fa;
            --amber: #fbbf24;
            --red: #f87171;
            --shadow: 0 28px 80px rgba(0,0,0,0.42);
        }
        * { box-sizing: border-box; }
        html, body { min-height: 100%; }
        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at top left, rgba(96,165,250,0.14), transparent 28%),
                radial-gradient(circle at top right, rgba(94,234,212,0.08), transparent 24%),
                linear-gradient(180deg, #06111d 0%, #081420 100%);
            display: grid;
            place-items: center;
            padding: 24px;
            overflow: hidden;
        }
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 36px 36px;
            pointer-events: none;
            mask-image: radial-gradient(circle at center, black 42%, transparent 100%);
        }
        .shell {
            width: min(980px, 100%);
            display: grid;
            grid-template-columns: 1.05fr 0.95fr;
            gap: 20px;
            position: relative;
            z-index: 1;
        }
        .glass {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 28px;
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            box-shadow: var(--shadow);
        }
        .hero {
            padding: 34px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 560px;
        }
        .eyebrow {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.18em;
            color: var(--cyan);
            font-weight: 700;
            margin-bottom: 16px;
        }
        h1 {
            margin: 0 0 14px;
            font-size: clamp(34px, 5vw, 58px);
            line-height: 0.98;
            letter-spacing: -0.04em;
        }
        .hero p {
            margin: 0;
            color: var(--muted);
            line-height: 1.75;
            max-width: 48ch;
        }
        .callouts {
            display: grid;
            gap: 12px;
            margin-top: 28px;
        }
        .callout {
            padding: 16px 18px;
            border-radius: 18px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(148,163,184,0.12);
        }
        .callout-title {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.14em;
            color: var(--muted);
            font-weight: 700;
            margin-bottom: 8px;
        }
        .callout-value {
            font-size: 18px;
            font-weight: 700;
        }
        .modal {
            padding: 30px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 560px;
        }
        .panel-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.18em;
            color: var(--blue);
            font-weight: 700;
            margin-bottom: 18px;
        }
        .modal h2 {
            margin: 0 0 12px;
            font-size: 34px;
            letter-spacing: -0.03em;
        }
        .warning {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            padding: 16px 18px;
            border-radius: 18px;
            background: rgba(251,191,36,0.08);
            border: 1px solid rgba(251,191,36,0.22);
            color: #fde68a;
            margin-bottom: 18px;
        }
        .warning-icon {
            width: 28px;
            height: 28px;
            border-radius: 999px;
            background: rgba(251,191,36,0.18);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            flex: 0 0 auto;
        }
        .copy {
            color: var(--muted);
            line-height: 1.7;
            margin-bottom: 24px;
        }
        .terms-list {
            margin: 0 0 26px;
            padding-left: 18px;
            color: var(--muted);
            line-height: 1.7;
        }
        .terms-list li + li { margin-top: 10px; }
        .actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        button, .secondary-link {
            width: 100%;
            padding: 16px 18px;
            border-radius: 16px;
            border: 1px solid rgba(148,163,184,0.18);
            font: inherit;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.16s ease, border-color 0.16s ease, box-shadow 0.16s ease;
        }
        button:hover, .secondary-link:hover {
            transform: translateY(-1px);
        }
        .primary-btn {
            background: linear-gradient(135deg, rgba(96,165,250,0.96), rgba(94,234,212,0.96));
            color: #05101a;
            border: none;
            box-shadow: 0 18px 44px rgba(96,165,250,0.22);
        }
        .secondary-link {
            background: rgba(255,255,255,0.03);
            color: var(--text);
        }
        .footnote {
            margin-top: 16px;
            font-size: 12px;
            color: var(--muted);
            text-align: center;
        }
        footer {
            position: fixed;
            bottom: 12px;
            width: 100%;
            text-align: center;
            font-size: 11px;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: rgba(156,176,202,0.72);
        }
        @media (max-width: 900px) {
            body { overflow: auto; }
            .shell {
                grid-template-columns: 1fr;
                width: min(700px, 100%);
                margin-bottom: 48px;
            }
            .hero, .modal { min-height: auto; }
            .actions { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <main class='shell'>
        <section class='hero glass'>
            <div>
                <div class='eyebrow'>HackTrader legal gate</div>
                <h1>Read the risk before you launch the cockpit.</h1>
                <p>
                    HackTrader surfaces market structure, breakout levels, correlation data, and trading context. It is a research interface — not a promise, signal service, or investment recommendation engine.
                </p>
            </div>
            <div class='callouts'>
                <div class='callout'>
                    <div class='callout-title'>Purpose</div>
                    <div class='callout-value'>Decision support, not financial advice</div>
                </div>
                <div class='callout'>
                    <div class='callout-title'>Risk posture</div>
                    <div class='callout-value'>You remain fully responsible for every trade</div>
                </div>
                <div class='callout'>
                    <div class='callout-title'>Market reality</div>
                    <div class='callout-value'>Breakout models can fail, reverse, or overfit noisy conditions</div>
                </div>
            </div>
        </section>

        <section class='modal glass'>
            <div class='panel-label'>Legal disclaimer</div>
            <h2>Before continuing, confirm you understand the limits.</h2>
            <div class='warning'>
                <div class='warning-icon'>!</div>
                <div>
                    This platform does <strong>not</strong> provide trading, financial, tax, or investment advice. All analytics are informational and may be incomplete, delayed, or wrong.
                </div>
            </div>
            <div class='copy'>
                By continuing, you acknowledge that HackTrader is a research tool and that any action you take based on its output is entirely your own responsibility.
            </div>
            <ul class='terms-list'>
                <li>Analytics, probabilities, channels, and breakout signals are informational only.</li>
                <li>No displayed data should be treated as a recommendation to buy, sell, or hold any asset.</li>
                <li>You accept full responsibility for losses, execution errors, and decisions made using this interface.</li>
            </ul>
            <form method='post'>
                <div class='actions'>
                    <button class='primary-btn' name='accept' value='1'>Accept and continue</button>
                    <a class='secondary-link' href='logout.php'>Go back</a>
                </div>
            </form>
            <div class='footnote'>Proceed only if you understand and accept the above limitations.</div>
        </section>
    </main>
    <footer>HackTrader · disclaimer · v0.7.2</footer>
</body>
</html>
