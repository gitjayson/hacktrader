<?php
session_start();
if (!isset($_SESSION['user_name']) || !isset($_SESSION['agreed'])) {
    header('Location: index.php');
    exit;
}
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 86400)) {
    header('Location: logout.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>HackTrader | v0.7.2.8</title>
    <link rel='preconnect' href='https://fonts.googleapis.com'>
    <link rel='preconnect' href='https://fonts.gstatic.com' crossorigin>
    <link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600;700&display=swap' rel='stylesheet'>
    <style>
        :root {
            --bg: #05101a;
            --bg-soft: #091827;
            --panel: rgba(9, 22, 35, 0.78);
            --panel-2: rgba(15, 28, 45, 0.72);
            --border: rgba(148, 163, 184, 0.16);
            --text: #e8f1ff;
            --muted: #96a9c4;
            --cyan: #5eead4;
            --blue: #60a5fa;
            --green: #22c55e;
            --red: #f87171;
            --amber: #fbbf24;
            --shadow: 0 24px 70px rgba(0,0,0,0.35);
        }
        * { box-sizing: border-box; }
        html, body { min-height: 100%; }
        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at top left, rgba(96,165,250,0.12), transparent 28%),
                radial-gradient(circle at top right, rgba(94,234,212,0.08), transparent 24%),
                linear-gradient(180deg, #06111d 0%, #081420 100%);
            min-height: 100vh;
            overflow-x: hidden;
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
        .app-shell {
            width: min(1520px, calc(100vw - 28px));
            margin: 16px auto 32px;
            display: grid;
            gap: 18px;
        }
        .glass {
            background: var(--panel);
            border: 1px solid var(--border);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            box-shadow: var(--shadow);
        }
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 16px 18px;
            border-radius: 24px;
            position: sticky;
            top: 12px;
            z-index: 20;
        }
        .brand {
            display: flex;
            flex-direction: column;
            gap: 4px;
            min-width: 170px;
        }
        .eyebrow {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.18em;
            color: var(--cyan);
            font-weight: 700;
        }
        .brand strong {
            font-size: 24px;
            letter-spacing: -0.04em;
        }
        .brand-title {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 24px;
            font-weight: 700;
            line-height: 1.1;
        }
        .brand-title .title-text {
            display: inline-block;
            font-size: 32px;
            font-weight: 800;
            letter-spacing: -0.04em;
            line-height: 0.98;
        }
        .pengo-trigger {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 999px;
            cursor: pointer;
            user-select: none;
            transition: transform 0.15s ease, background 0.15s ease;
        }
        .pengo-trigger:hover {
            transform: translateY(-1px) scale(1.05);
            background: rgba(255,255,255,0.08);
        }
        .pengo-popup {
            position: fixed;
            inset: auto 20px 20px auto;
            display: none;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            border-radius: 18px;
            border: 1px solid rgba(94,234,212,0.35);
            background: rgba(6, 17, 29, 0.94);
            color: var(--text);
            box-shadow: 0 22px 50px rgba(0,0,0,0.35);
            z-index: 60;
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
        }
        .pengo-popup.show {
            display: inline-flex;
        }
        .pengo-popup .emoji {
            font-size: 24px;
            line-height: 1;
        }
        .pengo-popup .copy {
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.01em;
        }
        .brand span:last-child {
            color: var(--muted);
            font-size: 13px;
        }
        .controls {
            display: grid;
            grid-template-columns: minmax(120px, 1.15fr) 92px 108px minmax(220px, 1fr) auto auto auto;
            gap: 14px;
            align-items: center;
            flex: 1;
        }
        .api-usage-inline {
            display: grid;
            gap: 12px;
            padding: 14px;
            margin-bottom: 14px;
            border-radius: 18px;
            background: rgba(3, 9, 17, 0.72);
            border: 1px solid rgba(94,234,212,0.16);
        }
        .api-usage-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }
        .api-usage-kpi {
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.05em;
            line-height: 0.95;
        }
        .api-usage-sub {
            color: var(--muted);
            font-size: 12px;
            line-height: 1.4;
        }
        .api-usage-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 7px 10px;
            border-radius: 999px;
            background: rgba(96,165,250,0.16);
            color: #bfdbfe;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.14em;
            white-space: nowrap;
        }
        .api-usage-pill.warn {
            background: rgba(248,113,113,0.16);
            color: #fecaca;
        }
        .api-usage-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
        }
        .api-usage-stat {
            padding: 10px 12px;
            border-radius: 14px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(148,163,184,0.12);
            display: grid;
            gap: 6px;
        }
        .api-usage-stat .label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: var(--muted);
            font-weight: 700;
        }
        .api-usage-stat .value {
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
            font-weight: 700;
            color: var(--text);
        }
        .api-usage-meta {
            color: var(--muted);
            font-size: 11px;
            line-height: 1.45;
        }
        input, select, button {
            border: 1px solid rgba(148, 163, 184, 0.22);
            background: rgba(3, 9, 17, 0.72);
            color: var(--text);
            padding: 12px 14px;
            border-radius: 14px;
            font: inherit;
        }
        input:focus, select:focus {
            outline: none;
            border-color: rgba(96,165,250,0.7);
            box-shadow: 0 0 0 3px rgba(96,165,250,0.14);
        }
        button {
            cursor: pointer;
            font-weight: 700;
            transition: transform 0.15s ease, border-color 0.15s ease, background 0.15s ease;
        }
        button:hover {
            transform: translateY(-1px);
            border-color: rgba(94,234,212,0.7);
        }
        .primary-btn {
            background: linear-gradient(135deg, rgba(96,165,250,0.96), rgba(94,234,212,0.96));
            color: #06111d;
            border: none;
        }
        .ghost-btn {
            background: rgba(255,255,255,0.03);
        }
        .slider-wrap {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            border: 1px solid rgba(148,163,184,0.22);
            border-radius: 14px;
            background: rgba(3, 9, 17, 0.72);
            min-width: 220px;
        }
        .slider-wrap label {
            font-size: 11px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.15em;
            font-weight: 700;
        }
        .slider-wrap input[type='range'] {
            flex: 1;
            accent-color: var(--cyan);
            padding: 0;
            background: transparent;
        }
        .slider-value {
            min-width: 34px;
            text-align: right;
            font-family: 'JetBrains Mono', monospace;
            color: var(--cyan);
            font-weight: 700;
        }
        .banner-wrap {
            position: fixed;
            top: 84px;
            left: 50%;
            transform: translateX(-50%);
            width: min(960px, calc(100vw - 32px));
            display: grid;
            gap: 10px;
            z-index: 40;
            pointer-events: none;
        }
        .status-banner, .debug-banner {
            padding: 12px 16px;
            border-radius: 16px;
            border: 1px solid rgba(251, 191, 36, 0.32);
            background: rgba(15, 12, 4, 0.86);
            color: var(--amber);
            font-size: 13px;
            display: none;
            box-shadow: 0 18px 44px rgba(0,0,0,0.25);
            pointer-events: auto;
        }
        .status-banner.error {
            border-color: rgba(248, 113, 113, 0.38);
            background: rgba(48, 12, 12, 0.44);
            color: var(--red);
        }
        .debug-banner {
            border-color: rgba(96,165,250,0.28);
            background: rgba(8, 18, 31, 0.9);
            color: var(--blue);
            white-space: pre-wrap;
        }
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 18px;
            align-items: start;
        }
        .main-column, .side-column {
            display: grid;
            gap: 18px;
        }
        .hero-panel {
            border-radius: 28px;
            padding: 24px;
        }
        .hero-top {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            align-items: flex-start;
            margin-bottom: 20px;
        }
        .focus-meta h1 {
            margin: 0 0 8px;
            font-size: clamp(36px, 4vw, 56px);
            line-height: 0.94;
            letter-spacing: -0.05em;
        }
        .focus-meta p {
            margin: 0;
            color: var(--muted);
            max-width: 56ch;
            line-height: 1.7;
            font-size: 14px;
        }
        .quote-pill {
            padding: 14px 16px;
            border-radius: 18px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(148,163,184,0.16);
            min-width: 220px;
        }
        .quote-pill .label {
            font-size: 11px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.14em;
            margin-bottom: 8px;
            font-weight: 700;
        }
        .quote-pill .value {
            font-size: 30px;
            font-weight: 800;
            letter-spacing: -0.04em;
        }
        .quote-pill .sub {
            margin-top: 6px;
            color: var(--muted);
            font-size: 13px;
        }
        .hero-grid {
            display: grid;
            grid-template-columns: minmax(560px, 1.15fr) minmax(320px, 0.85fr);
            gap: 18px;
            align-items: stretch;
        }
        .radar-card, .breakout-card {
            border-radius: 24px;
            padding: 20px;
            background: var(--panel-2);
            border: 1px solid rgba(148, 163, 184, 0.14);
        }
        .radar-card {
            min-width: 560px;
        }
        .section-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 14px;
        }
        .section-title h2 {
            margin: 0;
            font-size: 16px;
            letter-spacing: -0.02em;
        }
        .section-title span {
            color: var(--muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.14em;
        }
        .radar-stage {
            position: relative;
            width: min(560px, 100%);
            aspect-ratio: 1 / 1;
            margin: 0 auto;
            border-radius: 50%;
            border: 1px solid rgba(148,163,184,0.14);
            background:
                radial-gradient(circle at center, rgba(255,255,255,0.03), rgba(255,255,255,0.01) 48%, rgba(255,255,255,0) 72%),
                radial-gradient(circle, rgba(94,234,212,0.06) 0%, transparent 60%);
            overflow: visible;
            padding: 22px;
        }
        .radar-stage::before,
        .radar-stage::after {
            content: '';
            position: absolute;
            inset: 10%;
            border-radius: 50%;
            border: 1px dashed rgba(148,163,184,0.14);
            pointer-events: none;
        }
        .radar-stage::after {
            inset: 24%;
        }
        .radar-lines {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
        }
        .focus-node {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            width: clamp(128px, 28%, 170px);
            min-height: clamp(128px, 28%, 170px);
            border-radius: 28px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            background: linear-gradient(180deg, rgba(4, 10, 18, 0.92), rgba(11, 20, 31, 0.94));
            border: 1px solid rgba(94,234,212,0.28);
            box-shadow: 0 22px 50px rgba(0,0,0,0.34);
            z-index: 3;
        }
        .focus-node.up { border-color: rgba(34,197,94,0.45); box-shadow: 0 0 24px rgba(34,197,94,0.18); }
        .focus-node.down { border-color: rgba(248,113,113,0.45); box-shadow: 0 0 24px rgba(248,113,113,0.18); }
        .focus-node.neutral { border-color: rgba(96,165,250,0.4); }
        .focus-symbol { font-size: 34px; font-weight: 800; letter-spacing: -0.04em; }
        .focus-price { font-size: 22px; font-weight: 700; margin-top: 8px; }
        .focus-bias { margin-top: 10px; font-size: 12px; color: var(--muted); }
        .indicator-node {
            position: absolute;
            width: clamp(78px, 16vw, 106px);
            min-height: clamp(70px, 14vw, 94px);
            padding: 10px 8px;
            border-radius: 20px;
            background: rgba(5, 12, 21, 0.88);
            border: 1px solid rgba(148,163,184,0.15);
            box-shadow: 0 16px 30px rgba(0,0,0,0.22);
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 4px;
            text-align: center;
            transform: translate(-50%, -50%);
            cursor: pointer;
            z-index: 4;
        }
        .indicator-node .ticker { font-size: 14px; font-weight: 800; }
        .indicator-node .price { font-family: 'JetBrains Mono', monospace; font-size: 12px; color: var(--muted); }
        .indicator-node .mini-bias { font-size: 11px; font-weight: 700; }
        .indicator-node.green { border-color: rgba(34,197,94,0.42); }
        .indicator-node.red { border-color: rgba(248,113,113,0.42); }
        .indicator-node.neutral { border-color: rgba(96,165,250,0.3); }
        .breakout-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }
        .signal-card {
            border-radius: 18px;
            padding: 16px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(148,163,184,0.12);
        }
        .signal-card .label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.14em;
            color: var(--muted);
            margin-bottom: 10px;
            font-weight: 700;
        }
        .signal-card .value {
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.04em;
        }
        .signal-card .sub {
            margin-top: 6px;
            color: var(--muted);
            font-size: 13px;
        }
        .signal-card.up .value { color: var(--green); }
        .signal-card.down .value { color: var(--red); }
        .bias-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 12px;
            padding: 10px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .bias-chip.up { background: rgba(34,197,94,0.12); color: #86efac; }
        .bias-chip.down { background: rgba(248,113,113,0.12); color: #fca5a5; }
        .bias-chip.neutral { background: rgba(96,165,250,0.12); color: #bfdbfe; }
        .range-grid {
            display: grid;
            gap: 10px;
        }
        .microchart-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin-top: 16px;
        }
        .microchart-card {
            border-radius: 18px;
            padding: 14px 16px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(148,163,184,0.12);
        }
        .microchart-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.14em;
            color: var(--muted);
            margin-bottom: 10px;
            font-weight: 700;
        }
        .microchart-value {
            font-size: 20px;
            font-weight: 800;
            margin-bottom: 10px;
        }
        .meter {
            position: relative;
            width: 100%;
            height: 10px;
            border-radius: 999px;
            background: rgba(255,255,255,0.08);
            overflow: hidden;
        }
        .meter-fill {
            position: absolute;
            inset: 0 auto 0 0;
            width: 0%;
            border-radius: inherit;
            background: linear-gradient(90deg, rgba(96,165,250,0.72), rgba(94,234,212,0.9));
        }
        .meter-fill.red {
            background: linear-gradient(90deg, rgba(248,113,113,0.92), rgba(251,191,36,0.72));
        }
        .meter-fill.green {
            background: linear-gradient(90deg, rgba(74,222,128,0.92), rgba(94,234,212,0.72));
        }
        .meter-subtext {
            margin-top: 8px;
            font-size: 12px;
            color: var(--muted);
            line-height: 1.45;
        }
        .driver-impact-bar {
            width: 96px;
            height: 8px;
            border-radius: 999px;
            background: rgba(255,255,255,0.08);
            overflow: hidden;
            margin-left: auto;
            margin-top: 6px;
        }
        .driver-impact-fill {
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, rgba(96,165,250,0.82), rgba(94,234,212,0.82));
        }
        .driver-impact-fill.neg {
            background: linear-gradient(90deg, rgba(248,113,113,0.92), rgba(251,191,36,0.72));
        }
        .range-card {
            border-radius: 18px;
            padding: 14px 16px;
            background: rgba(255,255,255,0.025);
            border: 1px solid rgba(148,163,184,0.12);
        }
        .range-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
        }
        .range-name {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.14em;
            color: var(--muted);
            font-weight: 700;
        }
        .range-width {
            font-family: 'JetBrains Mono', monospace;
            color: var(--text);
            font-size: 12px;
        }
        .range-track {
            position: relative;
            height: 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.08);
            overflow: hidden;
        }
        .range-fill {
            position: absolute;
            inset: 0;
            border-radius: inherit;
            background: linear-gradient(90deg, rgba(96,165,250,0.52), rgba(94,234,212,0.72));
        }
        .range-meta {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin-top: 8px;
            font-size: 12px;
            color: var(--muted);
        }
        .compact-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }
        .stack-card {
            border-radius: 24px;
            padding: 20px;
        }
        .metric-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }
        .metric-card {
            border-radius: 18px;
            padding: 16px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(148,163,184,0.12);
        }
        .metric-card .label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.14em;
            color: var(--muted);
            margin-bottom: 8px;
            font-weight: 700;
        }
        .metric-card .value {
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.04em;
        }
        .metric-card .sub {
            margin-top: 6px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.5;
        }
        .levels-list, .drivers-list, .attempt-list {
            display: grid;
            gap: 10px;
        }
        .probe-visual {
            border-radius: 18px;
            padding: 14px 16px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(148,163,184,0.12);
            display: grid;
            gap: 12px;
        }
        .probe-visual-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .probe-visual-title {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.14em;
            color: var(--muted);
            font-weight: 700;
        }
        .probe-visual-balance {
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
            color: var(--text);
        }
        .probe-graph {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 16px minmax(0, 1fr);
            gap: 10px;
            align-items: center;
        }
        .probe-half {
            position: relative;
            height: 18px;
            border-radius: 999px;
            background: rgba(255,255,255,0.06);
            overflow: hidden;
        }
        .probe-fill {
            position: absolute;
            top: 0;
            bottom: 0;
            border-radius: inherit;
        }
        .probe-half.up .probe-fill {
            right: 0;
            background: linear-gradient(90deg, rgba(96,165,250,0.52), rgba(74,222,128,0.92));
        }
        .probe-half.down .probe-fill {
            left: 0;
            background: linear-gradient(90deg, rgba(248,113,113,0.92), rgba(251,191,36,0.72));
        }
        .probe-divider {
            width: 2px;
            height: 28px;
            border-radius: 999px;
            background: rgba(148,163,184,0.24);
            justify-self: center;
        }
        .probe-label-row {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
            font-size: 12px;
            color: var(--muted);
        }
        .probe-label-row .up strong { color: #86efac; }
        .probe-label-row .down strong { color: #fca5a5; }
        .probe-label-row strong {
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
        }
        .probe-meta {
            font-size: 12px;
            color: var(--muted);
            line-height: 1.45;
        }
        .level-row, .driver-row, .attempt-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            padding: 12px 14px;
            border-radius: 16px;
            background: rgba(255,255,255,0.025);
            border: 1px solid rgba(148,163,184,0.1);
        }
        .level-row .left, .driver-row .left, .attempt-row .left {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .row-title {
            font-size: 13px;
            font-weight: 700;
        }
        .row-meta {
            font-size: 12px;
            color: var(--muted);
        }
        .row-value {
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
            text-align: right;
        }
        .pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            background: rgba(255,255,255,0.05);
            color: var(--muted);
        }
        .pill.up { color: #86efac; background: rgba(34,197,94,0.12); }
        .pill.down { color: #fca5a5; background: rgba(248,113,113,0.12); }
        .pill.neutral { color: #bfdbfe; background: rgba(96,165,250,0.12); }
        footer {
            text-align: center;
            font-size: 11px;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: rgba(150,169,196,0.72);
            padding: 4px 0 12px;
        }
        @media (max-width: 1260px) {
            .controls {
                grid-template-columns: 1fr 1fr 1fr 1fr;
            }
            .controls .slider-wrap { grid-column: span 2; }
            .hero-grid { grid-template-columns: minmax(520px, 1fr); }
            .radar-card { min-width: 520px; }
        }
        @media (max-width: 980px) {
            .dashboard-grid { grid-template-columns: 1fr; }
            .topbar { position: static; }
            .controls { grid-template-columns: 1fr 1fr; }
            .controls > * { min-width: 0; }
            .hero-grid { grid-template-columns: 1fr; }
            .radar-card { min-width: 0; }
            .radar-stage {
                width: min(500px, 100%);
                min-width: 500px;
                padding: 18px;
            }
            .radar-card {
                overflow-x: auto;
            }
        }
        @media (max-width: 720px) {
            .app-shell { width: min(100vw - 16px, 100%); margin-top: 8px; }
            .topbar, .hero-panel, .stack-card { border-radius: 22px; }
            .controls { grid-template-columns: 1fr; }
            .api-usage-grid { grid-template-columns: 1fr; }
            .slider-wrap { min-width: 0; }
            .hero-top { flex-direction: column; }
            .breakout-grid, .compact-grid, .metric-grid, .microchart-grid { grid-template-columns: 1fr; }
            .banner-wrap {
                top: auto;
                bottom: 12px;
                width: calc(100vw - 24px);
            }
            .radar-stage {
                width: 420px;
                min-width: 420px;
                padding: 14px;
            }
            .focus-node {
                width: 122px;
                min-height: 122px;
                padding: 12px;
            }
            .focus-symbol { font-size: 26px; }
            .focus-price { font-size: 18px; }
            .focus-bias { font-size: 11px; }
            .indicator-node {
                width: 78px;
                min-height: 72px;
                padding: 8px 6px;
            }
            .indicator-node .ticker { font-size: 12px; }
            .indicator-node .price { font-size: 10px; }
            .indicator-node .mini-bias { font-size: 9px; }
        }
    </style>
</head>
<body onload='syncToleranceValue(); updateDashboard()'>
    <?php
    $corrData = json_decode(file_get_contents('correlations.json'), true);
    $allTickers = array_keys($corrData);
    sort($allTickers);
    $logoManifest = [];
    if (file_exists('logos.json')) {
        $logoManifest = json_decode(file_get_contents('logos.json'), true) ?: [];
    }
    ?>
    <main class='app-shell'>
        <section class='topbar glass'>
            <div class='brand'>
                <div class='eyebrow'>HackTrader v0.7.2.8 (by @gitjayson)</div>
                <strong class='brand-title'><span class='pengo-trigger' id='pengoTrigger' title='Activate pengo'>🐧</span><span class='title-text'>Signal cockpit</span></strong>
                <span>Breakouts, channels, and market pressure at a glance</span>
            </div>
            <div class='controls'>
                <input type='text' id='ticker' list='ticker-list' placeholder='Ticker'>
                <datalist id='ticker-list'>
                    <?php foreach($allTickers as $t) echo "<option value='$t'>"; ?>
                </datalist>
                <select id='period'><option selected>5m</option><option>1m</option><option>1h</option><option>1d</option></select>
                <input type='number' id='lookback' value='100' placeholder='Lookback'>
                <div class='slider-wrap'>
                    <label for='tolerance'>Tolerance</label>
                    <input type='range' id='tolerance' min='0' max='100' value='90' oninput='syncToleranceValue()'>
                    <span class='slider-value' id='toleranceValue'>90</span>
                </div>
                <button class='primary-btn' onclick='updateDashboard()'>Scan</button>
                <button class='ghost-btn' onclick='resetDashboard()'>Reset</button>
                <button class='ghost-btn' onclick='window.location.href="logout.php"'>Logout</button>
            </div>
        </section>

        <section class='banner-wrap'>
            <div id='statusBanner' class='status-banner'></div>
            <div id='debugBanner' class='debug-banner'></div>
        </section>

        <div id='pengoPopup' class='pengo-popup' role='status' aria-live='polite'>
            <span class='emoji'>🐧</span>
            <span class='copy'>make money bitches</span>
        </div>

        <section class='dashboard-grid'>
            <div class='main-column'>
                <section class='hero-panel glass'>
                    <div class='hero-top'>
                        <div class='focus-meta'>
                            <div class='eyebrow'>Focus symbol</div>
                            <h1 id='focusHeadline'>TSLA breakout monitor</h1>
                            <p id='focusNarrative'>Scanning live market structure for breakout pressure, failed attempts, and correlation confirmation.</p>
                        </div>
                        <div class='quote-pill'>
                            <div class='label'>Live quote</div>
                            <div class='value' id='focusPriceBox'>$--</div>
                            <div class='sub' id='focusTimeBox'>Awaiting quote</div>
                        </div>
                    </div>
                    <div class='hero-grid'>
                        <div class='radar-card'>
                            <div class='section-title'>
                                <h2>Correlation radar</h2>
                                <span id='indicatorBiasSubtext'>0 processed</span>
                            </div>
                            <div class='radar-stage' id='clock'>
                                <svg id='lines' class='radar-lines'></svg>
                                <div class='focus-node neutral' id='focus' onclick='resetDashboard()' style='cursor:pointer;'>
                                    <div class='focus-symbol'>INIT</div>
                                    <div class='focus-price'>SCAN</div>
                                    <div class='focus-bias'>Awaiting data</div>
                                </div>
                            </div>
                        </div>
                        <div class='breakout-card'>
                            <div class='section-title'>
                                <h2>Breakout bias</h2>
                                <span id='analysisMeta'>Channel structure</span>
                            </div>
                            <div class='breakout-grid'>
                                <div class='signal-card up'>
                                    <div class='label'>Upside probability</div>
                                    <div class='value' id='upProbability'>--</div>
                                    <div class='sub' id='upProbabilitySub'>Awaiting breakout model</div>
                                </div>
                                <div class='signal-card down'>
                                    <div class='label'>Downside probability</div>
                                    <div class='value' id='downProbability'>--</div>
                                    <div class='sub' id='downProbabilitySub'>Awaiting breakout model</div>
                                </div>
                            </div>
                            <div id='biasChip' class='bias-chip neutral'>Neutral bias</div>
                            <div class='microchart-grid'>
                                <div class='microchart-card'>
                                    <div class='microchart-label'>Breakout pressure</div>
                                    <div class='microchart-value' id='pressureValue'>--</div>
                                    <div class='meter'><div class='meter-fill green' id='pressureFill'></div></div>
                                    <div class='meter-subtext' id='pressureSubtext'>Awaiting breakout pressure</div>
                                </div>
                                <div class='microchart-card'>
                                    <div class='microchart-label'>Channel width</div>
                                    <div class='microchart-value' id='channelWidthValue'>--</div>
                                    <div class='meter'><div class='meter-fill' id='channelWidthFill'></div></div>
                                    <div class='meter-subtext' id='channelWidthSubtext'>Awaiting channel structure</div>
                                </div>
                                <div class='microchart-card'>
                                    <div class='microchart-label'>Attempt stress</div>
                                    <div class='microchart-value' id='attemptStressValue'>--</div>
                                    <div class='meter'><div class='meter-fill red' id='attemptStressFill'></div></div>
                                    <div class='meter-subtext' id='attemptStressSubtext'>Awaiting failed attempt count</div>
                                </div>
                            </div>
                            <div class='range-grid' id='channelList'>
                                <div class='range-card'>
                                    <div class='range-top'>
                                        <div class='range-name'>Current channel</div>
                                        <div class='range-width'>Waiting on levels</div>
                                    </div>
                                    <div class='range-track'><div class='range-fill' style='width: 0%'></div></div>
                                    <div class='range-meta'><span>--</span><span>--</span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class='stack-card glass'>
                    <div class='section-title'>
                        <h2>Price structure</h2>
                        <span id='sourceMeta'>Awaiting source</span>
                    </div>
                    <div class='compact-grid'>
                        <div>
                            <div class='section-title'><h2>Resistance stack</h2><span>R1 / R2</span></div>
                            <div class='levels-list' id='resistanceList'></div>
                        </div>
                        <div>
                            <div class='section-title'><h2>Support stack</h2><span>S1 / S2</span></div>
                            <div class='levels-list' id='supportList'></div>
                        </div>
                    </div>
                </section>
            </div>

            <div class='side-column'>
                <section class='stack-card glass'>
                    <div class='section-title'>
                        <h2>Volume + context</h2>
                        <span id='quoteTimezone'>ET</span>
                    </div>
                    <div class='api-usage-inline' aria-live='polite'>
                        <div class='api-usage-top'>
                            <div>
                                <div class='label'>API usage</div>
                                <div class='api-usage-kpi' id='apiUsageTotal'>--</div>
                            </div>
                            <span class='api-usage-pill' id='apiUsagePill'>idle</span>
                        </div>
                        <div class='api-usage-sub' id='apiUsageSub'>Waiting for your first counted request.</div>
                        <div class='api-usage-grid'>
                            <div class='api-usage-stat'>
                                <div class='label'>Success rate</div>
                                <div class='value' id='apiUsageSuccessRate'>--</div>
                            </div>
                            <div class='api-usage-stat'>
                                <div class='label'>Errors</div>
                                <div class='value' id='apiUsageErrors'>--</div>
                            </div>
                            <div class='api-usage-stat'>
                                <div class='label'>Last scan</div>
                                <div class='value' id='apiUsageLast'>--</div>
                            </div>
                        </div>
                        <div class='api-usage-meta' id='apiUsageMeta'>No counted requests yet for this signed-in user.</div>
                    </div>
                    <div class='metric-grid'>
                        <div class='metric-card'>
                            <div class='label'>Day volume</div>
                            <div class='value' id='dayVolumeValue'>--</div>
                            <div class='sub' id='dayVolumeSubtext'>Waiting on market data</div>
                        </div>
                        <div class='metric-card'>
                            <div class='label'>Day ratio</div>
                            <div class='value' id='dayVolumeRatio'>--</div>
                            <div class='sub' id='barVolumeSubtext'>Current bar vs expected slot pending</div>
                        </div>
                        <div class='metric-card'>
                            <div class='label'>Indicator bias</div>
                            <div class='value' id='indicatorBiasValue'>--</div>
                            <div class='sub'>Correlation basket disposition</div>
                        </div>
                        <div class='metric-card'>
                            <div class='label'>Recent extremes</div>
                            <div class='value' id='recentExtremesValue'>--</div>
                            <div class='sub' id='previousDayValue'>Previous day waiting</div>
                        </div>
                    </div>
                </section>

                <section class='stack-card glass'>
                    <div class='section-title'>
                        <h2>Attempt monitor</h2>
                        <span>Rule of three</span>
                    </div>
                    <div class='attempt-list' id='attemptList'></div>
                </section>

                <section class='stack-card glass'>
                    <div class='section-title'>
                        <h2>Score drivers</h2>
                        <span>Model factors</span>
                    </div>
                    <div class='drivers-list' id='driversList'></div>
                </section>
            </div>
        </section>
        <footer>HackTrader · visual refresh · v0.7.2.8</footer>
    </main>

    <script>
        let refreshInterval = setInterval(updateDashboard, 30000);
        let tickerInputDebounce = null;
        let correlationPollTimer = null;
        let dashboardRequestSeq = 0;
        let pengoPopupTimer = null;

        function syncToleranceValue() {
            document.getElementById('toleranceValue').textContent = document.getElementById('tolerance').value;
        }

        function showBanner(message, isError = false) {
            const banner = document.getElementById('statusBanner');
            if (!message) {
                banner.style.display = 'none';
                banner.classList.remove('error');
                banner.textContent = '';
                return;
            }
            banner.textContent = message;
            banner.style.display = 'block';
            banner.classList.toggle('error', !!isError);
        }

        function showDebug(message) {
            const banner = document.getElementById('debugBanner');
            if (!message) {
                banner.style.display = 'none';
                banner.textContent = '';
                return;
            }
            banner.textContent = message;
            banner.style.display = 'block';
        }

        function showPengoPopup() {
            const popup = document.getElementById('pengoPopup');
            if (!popup) return;
            popup.classList.add('show');
            if (pengoPopupTimer) clearTimeout(pengoPopupTimer);
            pengoPopupTimer = setTimeout(() => popup.classList.remove('show'), 10000);
        }

        function attachPengoTrigger() {
            const trigger = document.getElementById('pengoTrigger');
            if (!trigger) return;
            trigger.addEventListener('click', showPengoPopup);
        }

        function formatSourceMeta(data) {
            const parts = [];
            if (data.source) parts.push(`SOURCE ${String(data.source).toUpperCase()}`);
            if (data.cache?.stale) parts.push(`STALE ${data.cache.age_seconds}s`);
            else if (data.cache?.hit) parts.push(`CACHE ${data.cache.age_seconds}s`);
            if (data.fallback_reason) parts.push('FALLBACK ACTIVE');
            if (data.warning) parts.push('LIVE FETCH WARNING');
            return parts.join(' · ');
        }

        function formatPrice(value) {
            const num = Number(value);
            return Number.isFinite(num) ? num.toFixed(2) : '--';
        }

        function formatPercent(value) {
            const num = Number(value);
            return Number.isFinite(num) ? `${num.toFixed(1)}%` : '--';
        }

        function formatVolume(value) {
            const num = Number(value);
            if (!Number.isFinite(num)) return '--';
            if (num >= 1_000_000_000) return `${(num / 1_000_000_000).toFixed(2)}B`;
            if (num >= 1_000_000) return `${(num / 1_000_000).toFixed(2)}M`;
            if (num >= 1_000) return `${(num / 1_000).toFixed(1)}K`;
            return `${Math.round(num)}`;
        }

        function formatRatio(value) {
            const num = Number(value);
            return Number.isFinite(num) ? `${num.toFixed(2)}x` : '--';
        }

        function formatRelativeTime(value) {
            if (!value) return 'just now';
            const parsed = new Date(value);
            if (Number.isNaN(parsed.getTime())) return 'recently';
            const deltaSeconds = Math.max(0, Math.round((Date.now() - parsed.getTime()) / 1000));
            if (deltaSeconds < 60) return `${deltaSeconds}s ago`;
            if (deltaSeconds < 3600) return `${Math.round(deltaSeconds / 60)}m ago`;
            if (deltaSeconds < 86400) return `${Math.round(deltaSeconds / 3600)}h ago`;
            return `${Math.round(deltaSeconds / 86400)}d ago`;
        }

        function updateApiUsageCard(usage) {
            const totalEl = document.getElementById('apiUsageTotal');
            if (!totalEl) return;

            const attempts = Number(usage?.attempts);
            const errors = Number(usage?.errors);
            const successRate = Number(usage?.success_rate);
            const lastTicker = usage?.last_ticker ? String(usage.last_ticker).toUpperCase() : null;
            const lastInterval = usage?.last_interval ? String(usage.last_interval) : null;
            const hasTracker = usage && Number.isFinite(attempts);
            const hasUsage = hasTracker && attempts > 0;
            const pill = document.getElementById('apiUsagePill');

            totalEl.textContent = hasTracker ? `${attempts} calls` : '--';
            document.getElementById('apiUsageSub').textContent = hasUsage
                ? 'Counted provider requests for the current signed-in user.'
                : 'No counted requests yet for this signed-in user.';
            document.getElementById('apiUsageSuccessRate').textContent = hasUsage && Number.isFinite(successRate) ? `${Math.round(successRate)}%` : '--';
            document.getElementById('apiUsageErrors').textContent = hasTracker && Number.isFinite(errors) ? `${errors}` : '--';
            document.getElementById('apiUsageLast').textContent = hasUsage && lastTicker ? `${lastTicker}${lastInterval ? ` ${lastInterval}` : ''}` : '--';

            pill.classList.toggle('warn', hasUsage && Number.isFinite(errors) && errors > 0);
            pill.textContent = !hasUsage ? 'idle' : (errors > 0 ? `${errors} issue${errors === 1 ? '' : 's'}` : 'tracked');

            const lastWhen = formatRelativeTime(usage?.last_request_at);
            document.getElementById('apiUsageMeta').textContent = hasUsage
                ? `Updated ${lastWhen}. ${lastTicker ? `Last counted request: ${lastTicker}${lastInterval ? ` ${lastInterval}` : ''}. ` : ''}Attributed to the current signed-in user.`
                : 'No counted requests yet. Attribution is tied to the current signed-in user.';
        }

        function formatSigned(value) {
            const num = Number(value);
            if (!Number.isFinite(num)) return '--';
            return `${num >= 0 ? '+' : ''}${num.toFixed(2)}`;
        }

        function fetchTickerData(ticker, period, lookback) {
            return fetch(`api.php?ticker=${encodeURIComponent(ticker)}&period=${encodeURIComponent(period)}&lookback=${encodeURIComponent(lookback)}&t=${Date.now()}`)
                .then(async (response) => {
                    const data = await response.json();
                    updateApiUsageCard(data?.usage);
                    if (!response.ok || data.error) throw data;
                    return data;
                });
        }

        function clearCorrelationPoll() {
            if (correlationPollTimer) {
                clearTimeout(correlationPollTimer);
                correlationPollTimer = null;
            }
        }

        function scheduleCorrelationRefresh(activeRequestId, ticker, delayMs = 4000) {
            clearCorrelationPoll();
            correlationPollTimer = setTimeout(() => {
                if (activeRequestId !== dashboardRequestSeq) return;
                const currentTicker = (document.getElementById('ticker').value || '').toUpperCase();
                if (currentTicker !== ticker) return;
                updateDashboard(ticker, { preserveInput: true, silentFocus: true });
            }, delayMs);
        }

        function summarizeRelationshipBias(indicatorStates, tolerance) {
            const summary = { up: 0, down: 0, neutral: 0 };
            (indicatorStates || []).forEach((item) => {
                if (!item || !item.data) {
                    summary.neutral += 1;
                    return;
                }
                const relation = item.relation || 'positive';
                const up = Number(item.data?.probabilities?.up || 0);
                const down = Number(item.data?.probabilities?.down || 0);
                const isUp = relation === 'positive' ? up >= tolerance : down >= tolerance;
                const isDown = relation === 'positive' ? down >= tolerance : up >= tolerance;
                if (isUp) summary.up += 1;
                else if (isDown) summary.down += 1;
                else summary.neutral += 1;
            });
            return summary;
        }

        function resetDashboard() {
            document.getElementById('ticker').value = 'TSLA';
            document.getElementById('period').value = '5m';
            document.getElementById('lookback').value = '100';
            document.getElementById('tolerance').value = '90';
            syncToleranceValue();
            updateDashboard('TSLA');
        }

        function attachTickerAutoRefresh() {
            const tickerEl = document.getElementById('ticker');
            const triggerRefresh = () => {
                const nextTicker = tickerEl.value.trim().toUpperCase();
                if (!nextTicker) return;
                tickerEl.value = nextTicker;
                clearTimeout(tickerInputDebounce);
                tickerInputDebounce = setTimeout(() => updateDashboard(nextTicker), 250);
            };
            tickerEl.addEventListener('change', triggerRefresh);
            tickerEl.addEventListener('blur', triggerRefresh);
            tickerEl.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    triggerRefresh();
                }
            });
        }

        function setBiasChip(probabilities) {
            const el = document.getElementById('biasChip');
            const bias = probabilities?.bias || 'neutral';
            const confidence = probabilities?.confidence || 'low';
            el.className = `bias-chip ${bias}`;
            el.textContent = `${bias} bias · ${confidence} confidence`;
        }

        function renderLevelList(targetId, titlePrefix, levels) {
            const container = document.getElementById(targetId);
            if (!levels || !levels.length) {
                container.innerHTML = `<div class='level-row'><div class='left'><div class='row-title'>No validated levels</div><div class='row-meta'>The model did not find enough clustered structure here.</div></div><div class='row-value'>--</div></div>`;
                return;
            }
            container.innerHTML = levels.map((level, index) => `
                <div class='level-row'>
                    <div class='left'>
                        <div class='row-title'>${titlePrefix}${index + 1} · $${formatPrice(level.price)}</div>
                        <div class='row-meta'>Touches ${level.touches ?? '--'} · Range ${Array.isArray(level.range) ? `$${formatPrice(level.range[0])} to $${formatPrice(level.range[1])}` : 'n/a'}</div>
                    </div>
                    <div class='row-value'>${titlePrefix === 'R' ? '+' : '-'}$${formatPrice(level.diff)}</div>
                </div>
            `).join('');
        }

        function renderChannels(channels) {
            const container = document.getElementById('channelList');
            if (!channels || !channels.length) {
                container.innerHTML = `<div class='range-card'><div class='range-top'><div class='range-name'>Channels unavailable</div><div class='range-width'>--</div></div><div class='range-track'><div class='range-fill' style='width:0%'></div></div><div class='range-meta'><span>No usable bounds</span><span>Awaiting structure</span></div></div>`;
                return;
            }
            const maxWidth = Math.max(...channels.map(channel => Number(channel.width || 0)), 0.01);
            container.innerHTML = channels.map(channel => {
                const pct = Math.max(8, Math.round((Number(channel.width || 0) / maxWidth) * 100));
                return `
                    <div class='range-card'>
                        <div class='range-top'>
                            <div class='range-name'>${String(channel.name || 'channel').replaceAll('_', ' ')}</div>
                            <div class='range-width'>Width $${formatPrice(channel.width)}</div>
                        </div>
                        <div class='range-track'><div class='range-fill' style='width:${pct}%'></div></div>
                        <div class='range-meta'><span>$${formatPrice(channel.lower)}</span><span>${channel.location || '--'}</span><span>$${formatPrice(channel.upper)}</span></div>
                    </div>
                `;
            }).join('');
        }

        function renderAttempts(attempts) {
            const container = document.getElementById('attemptList');
            const up = Number(attempts?.failed_up_today || 0);
            const down = Number(attempts?.failed_down_today || 0);
            const maxProbe = Math.max(up, down, 3, 1);
            const upPct = up > 0 ? Math.max(8, Math.round((up / maxProbe) * 100)) : 0;
            const downPct = down > 0 ? Math.max(8, Math.round((down / maxProbe) * 100)) : 0;
            const blockedUp = !!attempts?.rule_of_three_block_up;
            const blockedDown = !!attempts?.rule_of_three_block_down;
            const ruleState = blockedUp || blockedDown ? 'active' : 'inactive';
            container.innerHTML = `
                <div class='probe-visual'>
                    <div class='probe-visual-top'>
                        <div class='probe-visual-title'>Probe pressure</div>
                        <div class='probe-visual-balance'>${up}↑ · ${down}↓</div>
                    </div>
                    <div class='probe-graph'>
                        <div class='probe-half up'><div class='probe-fill' style='width:${upPct}%'></div></div>
                        <div class='probe-divider'></div>
                        <div class='probe-half down'><div class='probe-fill' style='width:${downPct}%'></div></div>
                    </div>
                    <div class='probe-label-row'>
                        <div class='up'>Upside probes <strong>${up}</strong>${blockedUp ? ' · blocked' : ''}</div>
                        <div class='down'>Downside probes <strong>${down}</strong>${blockedDown ? ' · blocked' : ''}</div>
                    </div>
                    <div class='probe-meta'>Mirrored graph of failed breakout probes today. Rule-of-three status is ${ruleState}.</div>
                </div>
                <div class='attempt-row'>
                    <div class='left'>
                        <div class='row-title'>Failed upside attempts</div>
                        <div class='row-meta'>Repeated pushes into resistance that could not hold</div>
                    </div>
                    <div class='row-value'>${up}${blockedUp ? ' · blocked' : ''}</div>
                </div>
                <div class='attempt-row'>
                    <div class='left'>
                        <div class='row-title'>Failed downside attempts</div>
                        <div class='row-meta'>Repeated probes below support that snapped back</div>
                    </div>
                    <div class='row-value'>${down}${blockedDown ? ' · blocked' : ''}</div>
                </div>
            `;
        }

        function renderDrivers(drivers) {
            const container = document.getElementById('driversList');
            if (!drivers || !drivers.length) {
                container.innerHTML = `<div class='driver-row'><div class='left'><div class='row-title'>No drivers available</div><div class='row-meta'>The model needs validated support and resistance first.</div></div><div class='row-value'>--</div></div>`;
                return;
            }
            container.innerHTML = drivers.slice(0, 8).map((driver) => `
                <div class='driver-row'>
                    <div class='left'>
                        <div class='row-title'>${String(driver.factor || 'factor').replaceAll('_', ' ')}</div>
                        <div class='row-meta'>Value: ${driver.value ?? '--'}</div>
                    </div>
                    <div class='row-value'>
                        ${formatSigned(driver.impact)}
                        <div class='driver-impact-bar'>
                            <div class='driver-impact-fill ${Number(driver.impact || 0) < 0 ? 'neg' : ''}' style='width:${Math.max(6, Math.min(100, Math.abs(Number(driver.impact || 0)) * 4))}%'></div>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        function updateFocusNarrative(symbol, data) {
            const headline = document.getElementById('focusHeadline');
            const narrative = document.getElementById('focusNarrative');
            const bias = data?.probabilities?.bias || 'neutral';
            const confidence = data?.probabilities?.confidence || 'low';
            const upAttempts = data?.attempts?.failed_up_today ?? 0;
            const downAttempts = data?.attempts?.failed_down_today ?? 0;
            headline.textContent = `${symbol} breakout monitor`;
            narrative.textContent = `${symbol} is showing a ${bias} breakout posture with ${confidence} confidence. Failed upside attempts: ${upAttempts}. Failed downside attempts: ${downAttempts}.`; 
        }

        function updateFocusPanel(data, indicatorSummary = null, symbol = 'TSLA') {
            document.getElementById('focusPriceBox').textContent = `$${formatPrice(data.focus_price ?? data.current_price)}`;
            document.getElementById('focusTimeBox').textContent = data.quote_time_eastern ? `${data.quote_time_eastern} ${data.quote_timezone || 'ET'}` : 'Time unavailable';
            document.getElementById('sourceMeta').textContent = formatSourceMeta(data) || 'Live source pending';
            document.getElementById('quoteTimezone').textContent = data.quote_timezone || 'ET';
            document.getElementById('analysisMeta').textContent = `${data.interval || '--'} · ${data.periods || '--'} bars`;
            document.getElementById('upProbability').textContent = formatPercent(data?.probabilities?.up);
            document.getElementById('downProbability').textContent = formatPercent(data?.probabilities?.down);
            document.getElementById('upProbabilitySub').textContent = `Bias ${data?.probabilities?.bias || 'neutral'}`;
            document.getElementById('downProbabilitySub').textContent = `Confidence ${data?.probabilities?.confidence || 'low'}`;
            setBiasChip(data?.probabilities || {});

            const upProb = Number(data?.probabilities?.up || 0);
            const downProb = Number(data?.probabilities?.down || 0);
            const pressureDirection = upProb >= downProb ? 'Upside' : 'Downside';
            const pressureStrength = Math.max(upProb, downProb);
            document.getElementById('pressureValue').textContent = `${pressureDirection} ${formatPercent(pressureStrength)}`;
            document.getElementById('pressureFill').style.width = `${Math.max(4, Math.min(100, pressureStrength))}%`;
            document.getElementById('pressureSubtext').textContent = `Spread ${Math.abs(upProb - downProb).toFixed(1)} pts between up/down scenarios`;

            const currentChannel = (data?.channels || []).find(channel => channel.name === 'current') || (data?.channels || [])[0];
            const channelWidth = Number(currentChannel?.width || 0);
            const atr = Number(data?.analysis_parameters?.atr || 0);
            const widthRatio = atr > 0 ? Math.min(100, (channelWidth / atr) * 50) : 0;
            document.getElementById('channelWidthValue').textContent = channelWidth ? `$${formatPrice(channelWidth)}` : '--';
            document.getElementById('channelWidthFill').style.width = `${Math.max(4, widthRatio)}%`;
            document.getElementById('channelWidthSubtext').textContent = currentChannel ? `${currentChannel.location} · ATR ${formatPrice(atr)}` : 'No current channel detected';

            const stress = Math.min(100, ((Number(data?.attempts?.failed_up_today || 0) + Number(data?.attempts?.failed_down_today || 0)) / 6) * 100);
            document.getElementById('attemptStressValue').textContent = `${Number(data?.attempts?.failed_up_today || 0) + Number(data?.attempts?.failed_down_today || 0)} probes`;
            document.getElementById('attemptStressFill').style.width = `${Math.max(4, stress)}%`;
            document.getElementById('attemptStressSubtext').textContent = `Up ${data?.attempts?.failed_up_today || 0} · Down ${data?.attempts?.failed_down_today || 0} · Rule-of-three ${(data?.attempts?.rule_of_three_block_up || data?.attempts?.rule_of_three_block_down) ? 'active' : 'inactive'}`;

            renderChannels(data?.channels || []);
            renderLevelList('resistanceList', 'R', data?.upper_resistances || []);
            renderLevelList('supportList', 'S', data?.lower_supports || []);
            renderAttempts(data?.attempts || {});
            renderDrivers(data?.score_drivers || []);
            document.getElementById('dayVolumeValue').textContent = formatVolume(data?.volume?.current_day);
            document.getElementById('dayVolumeSubtext').textContent = `Expected ${formatVolume(data?.volume?.expected_day)} · Current bar ${formatVolume(data?.volume?.current_bar)}`;
            document.getElementById('dayVolumeRatio').textContent = formatRatio(data?.volume?.day_ratio);
            document.getElementById('barVolumeSubtext').textContent = `Bar ratio ${formatRatio(data?.volume?.bar_ratio)} · slot avg ${formatVolume(data?.volume?.expected_bar)}`;
            document.getElementById('recentExtremesValue').textContent = data?.recent_extremes ? `$${formatPrice(data.recent_extremes.recent_low)} → $${formatPrice(data.recent_extremes.recent_high)}` : '--';
            document.getElementById('previousDayValue').textContent = data?.previous_day?.date ? `Prev day ${data.previous_day.date}: H $${formatPrice(data.previous_day.high)} · L $${formatPrice(data.previous_day.low)}` : 'Previous day unavailable';
            if (indicatorSummary) {
                const up = Number(indicatorSummary.up || 0);
                const down = Number(indicatorSummary.down || 0);
                const neutral = Number(indicatorSummary.neutral || 0);
                const total = up + down + neutral;
                document.getElementById('indicatorBiasValue').textContent = `${up}↑ / ${down}↓`;
                document.getElementById('indicatorBiasSubtext').textContent = `${total} processed · ${neutral} neutral`;
            }
            updateFocusNarrative(symbol, data);
        }

        function setFocusNode(symbol, data, tolerance) {
            const focus = document.getElementById('focus');
            const probs = data?.probabilities || {};
            const bias = probs.bias || 'neutral';
            focus.className = `focus-node ${bias}`;
            focus.innerHTML = `
                <div class='focus-symbol'>${symbol}</div>
                <div class='focus-price'>$${formatPrice(data?.current_price)}</div>
                <div class='focus-bias'>↑ ${formatPercent(probs.up)} · ↓ ${formatPercent(probs.down)} · ${bias}</div>
            `;
        }

        function drawOrUpdateLine(lineId, x, y, lineColor) {
            const lines = document.getElementById('lines');
            const clock = document.getElementById('clock');
            const size = clock ? clock.getBoundingClientRect().width : 560;
            const center = size / 2;
            let line = document.getElementById(lineId);
            if (!line) {
                line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
                line.setAttribute('id', lineId);
                lines.appendChild(line);
            }
            line.setAttribute('x1', String(center));
            line.setAttribute('y1', String(center));
            line.setAttribute('x2', String(center + x));
            line.setAttribute('y2', String(center + y));
            line.setAttribute('stroke', lineColor);
            line.setAttribute('stroke-width', lineColor === '#64748b' ? '1' : '2');
            if (lineColor === '#64748b') line.setAttribute('stroke-dasharray', '4,4');
            else line.removeAttribute('stroke-dasharray');
        }

        function computeLineColor(data, relation, tolerance) {
            const upwardTrend = Number(data?.probabilities?.up || 0) >= tolerance;
            const downwardTrend = Number(data?.probabilities?.down || 0) >= tolerance;
            if (relation === 'negative') {
                if (downwardTrend) return '#22c55e';
                if (upwardTrend) return '#f87171';
                return '#64748b';
            }
            if (upwardTrend) return '#22c55e';
            if (downwardTrend) return '#f87171';
            return '#64748b';
        }

        async function updateDashboard(newTicker = null, options = {}) {
            const { preserveInput = false, silentFocus = false } = options;
            if (newTicker && !preserveInput) document.getElementById('ticker').value = newTicker;
            const ticker = (newTicker || document.getElementById('ticker').value || 'TSLA').toUpperCase();
            if (!preserveInput) document.getElementById('ticker').value = ticker;
            const period = document.getElementById('period').value;
            const lookback = document.getElementById('lookback').value || 100;
            const tolerance = parseFloat(document.getElementById('tolerance').value || '90');
            const requestId = ++dashboardRequestSeq;
            clearCorrelationPoll();
            clearInterval(refreshInterval);
            refreshInterval = setInterval(() => updateDashboard(null, { preserveInput: true, silentFocus: true }), period === '1d' ? 3600000 : 30000);
            showBanner('');
            showDebug('');
            if (!silentFocus) {
                document.getElementById('focus').className = 'focus-node neutral';
                document.getElementById('focus').innerHTML = `<div class='focus-symbol'>${ticker}</div><div class='focus-price'>SCANNING</div><div class='focus-bias'>Loading live market structure</div>`;
            }
            let currentFocus;
            try {
                currentFocus = await fetchTickerData(ticker, period, lookback);
                if (requestId !== dashboardRequestSeq) return;
                setFocusNode(ticker, currentFocus, tolerance);
                updateFocusPanel(currentFocus, null, ticker);
                showBanner(formatSourceMeta(currentFocus), false);
            } catch (e) {
                if (requestId !== dashboardRequestSeq) return;
                document.getElementById('focus').className = 'focus-node neutral';
                document.getElementById('focus').innerHTML = `<div class='focus-symbol'>${ticker}</div><div class='focus-price'>ERR</div><div class='focus-bias'>Market data unavailable</div>`;
                showBanner(`Market data error: ${e?.error || 'Unknown error'}`, true);
                return;
            }
            try {
                const corrRes = await fetch(`correlate.php?ticker=${encodeURIComponent(ticker)}&t=${Date.now()}`, { cache: 'no-store' });
                const corrPayload = await corrRes.json();
                if (requestId !== dashboardRequestSeq) return;
                const rawIndicators = Array.isArray(corrPayload) ? corrPayload : (Array.isArray(corrPayload.indicators) ? corrPayload.indicators : []);
                const indicators = rawIndicators.map(ind => ({
                    symbol: String(ind?.symbol || '').toUpperCase().trim(),
                    relation: String(ind?.relation || ind?.relationship || ind?.sign || 'positive').toLowerCase() === 'negative' ? 'negative' : 'positive'
                })).filter(ind => ind.symbol);
                const corrStatus = Array.isArray(corrPayload) ? { status: 'ready' } : (corrPayload.status || { status: 'ready' });
                const usedFallback = !Array.isArray(corrPayload) && !!corrPayload.used_fallback;
                if (!indicators.length) {
                    showBanner(`No indicator basket available for ${ticker}.`, true);
                    return;
                }
                if (usedFallback && corrStatus.status === 'pending') {
                    showBanner(`Researching ${ticker} indicator basket… showing fallback set for now.`, false);
                    scheduleCorrelationRefresh(requestId, ticker, 4000);
                }
                document.querySelectorAll('.indicator-node').forEach(el => el.remove());
                document.querySelectorAll('#lines line[id^="line-"]').forEach(line => line.remove());
                const clock = document.getElementById('clock');
                const clockRect = clock.getBoundingClientRect();
                const smallWindow = window.innerWidth <= 720;
                const mediumWindow = window.innerWidth <= 980;
                const indicatorHalf = smallWindow ? 39 : (mediumWindow ? 44 : 52);
                const usableRadius = (clockRect.width / 2) - indicatorHalf - (smallWindow ? 28 : (mediumWindow ? 24 : 18));
                const radius = Math.max(smallWindow ? 74 : 88, Math.min(usableRadius, smallWindow ? 150 : 205));
                const indicatorStates = [];
                const promises = indicators.map(async (indObj, i) => {
                    const angle = (i / Math.max(indicators.length, 1)) * Math.PI * 2;
                    const x = Math.cos(angle) * radius;
                    const y = Math.sin(angle) * radius;
                    const el = document.createElement('button');
                    el.type = 'button';
                    el.className = 'indicator-node neutral';
                    el.dataset.symbol = indObj.symbol;
                    el.style.left = `calc(50% + ${x}px)`;
                    el.style.top = `calc(50% + ${y}px)`;
                    el.onclick = () => updateDashboard(indObj.symbol);
                    el.innerHTML = `<div class='ticker'>${indObj.symbol}</div><div class='price'>Loading…</div><div class='mini-bias'>Scanning</div>`;
                    clock.appendChild(el);
                    try {
                        const data = await fetchTickerData(indObj.symbol, period, lookback);
                        if (requestId !== dashboardRequestSeq) return;
                        const lineColor = computeLineColor(data, indObj.relation, tolerance);
                        const probs = data?.probabilities || {};
                        const bias = probs.bias || 'neutral';
                        el.className = `indicator-node ${bias}`;
                        el.innerHTML = `<div class='ticker'>${indObj.symbol}</div><div class='price'>$${formatPrice(data.current_price)}</div><div class='mini-bias'>↑ ${formatPercent(probs.up)} · ↓ ${formatPercent(probs.down)}</div>`;
                        drawOrUpdateLine(`line-${indObj.symbol}`, x, y, lineColor);
                        indicatorStates.push({ symbol: indObj.symbol, relation: indObj.relation, data });
                    } catch (err) {
                        indicatorStates.push({ symbol: indObj.symbol, relation: indObj.relation, data: null, error: err });
                        el.className = 'indicator-node neutral';
                        el.innerHTML = `<div class='ticker'>${indObj.symbol}</div><div class='price'>ERR</div><div class='mini-bias'>Data unavailable</div>`;
                    }
                });
                await Promise.all(promises);
                if (requestId !== dashboardRequestSeq) return;
                const summary = summarizeRelationshipBias(indicatorStates, tolerance);
                updateFocusPanel(currentFocus, summary, ticker);
                showDebug(`focus=${ticker} | corr=${indicators.length} | fallback=${usedFallback} | status=${corrStatus.status || 'unknown'}`);
            } catch (e) {
                if (requestId !== dashboardRequestSeq) return;
                showBanner('Correlation fetch failed.', true);
            }
        }

        attachTickerAutoRefresh();
        attachPengoTrigger();
    </script>
</body>
</html>
