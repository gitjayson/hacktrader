<?php http_response_code(200); ?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HackTrader — Coming Soon</title>
    <style>
        :root {
            color-scheme: dark;
            --bg1: #061018;
            --bg2: #0b1f2b;
            --panel: rgba(8, 18, 28, 0.78);
            --line: rgba(79, 195, 247, 0.28);
            --text: #e8f6ff;
            --muted: #9fc3d6;
            --accent: #56d4ff;
            --accent2: #7cffc4;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at top, rgba(86, 212, 255, 0.12), transparent 35%),
                linear-gradient(160deg, var(--bg1), var(--bg2));
            overflow: hidden;
        }
        .grid {
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(rgba(86, 212, 255, 0.06) 1px, transparent 1px),
                linear-gradient(90deg, rgba(86, 212, 255, 0.06) 1px, transparent 1px);
            background-size: 36px 36px;
            mask-image: linear-gradient(to bottom, rgba(0,0,0,.8), rgba(0,0,0,.15));
            pointer-events: none;
        }
        .card {
            position: relative;
            width: min(92vw, 760px);
            padding: 40px 32px;
            border: 1px solid var(--line);
            border-radius: 24px;
            background: var(--panel);
            backdrop-filter: blur(14px);
            box-shadow: 0 20px 80px rgba(0, 0, 0, 0.35);
            text-align: center;
        }
        .eyebrow {
            display: inline-block;
            margin-bottom: 14px;
            padding: 7px 12px;
            border-radius: 999px;
            border: 1px solid rgba(124, 255, 196, 0.25);
            color: var(--accent2);
            font-size: 12px;
            letter-spacing: .16em;
            text-transform: uppercase;
        }
        h1 {
            margin: 0 0 14px;
            font-size: clamp(34px, 6vw, 64px);
            line-height: 1.02;
            letter-spacing: -0.04em;
        }
        p {
            margin: 0 auto;
            max-width: 600px;
            color: var(--muted);
            font-size: clamp(16px, 2vw, 20px);
            line-height: 1.6;
        }
        .pulse {
            width: 12px;
            height: 12px;
            margin: 26px auto 0;
            border-radius: 999px;
            background: var(--accent);
            box-shadow: 0 0 0 0 rgba(86, 212, 255, .7);
            animation: pulse 2.2s infinite;
        }
        .footer {
            margin-top: 24px;
            font-size: 12px;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: rgba(232, 246, 255, 0.55);
        }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(86, 212, 255, .7); }
            70% { box-shadow: 0 0 0 20px rgba(86, 212, 255, 0); }
            100% { box-shadow: 0 0 0 0 rgba(86, 212, 255, 0); }
        }
    </style>
</head>
<body>
    <div class="grid"></div>
    <main class="card">
        <div class="eyebrow">HackTrader</div>
        <h1>Coming Soon</h1>
        <p>
            We’re rebuilding HackTrader for the next release. The new experience is currently in active development.
            Check back soon!
        </p>
        <div class="pulse" aria-hidden="true"></div>
        <div class="footer">Production landing page temporarily disabled</div>
    </main>
</body>
</html>
