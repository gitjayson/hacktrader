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

// v0.11.0 — Lite mode. Render-only feature: hides supporting panels and lets
// the correlation radar fill the viewport. Same fetch path, same data, same JS.
// Initial state from ?lite=1 (shareable URL); JS layer also honors localStorage
// for daily-use stickiness without dirtying the URL.
$liteMode = isset($_GET['lite'])
    && in_array(strtolower((string) $_GET['lite']), ['1', 'true', 'yes', 'on'], true);
?>
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>HackTrader | v0.12.0</title>
    <link rel='preconnect' href='https://fonts.googleapis.com'>
    <link rel='preconnect' href='https://fonts.gstatic.com' crossorigin>
    <link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600;700&display=swap' rel='stylesheet'>
    <script src="https://unpkg.com/lightweight-charts@4.1.1/dist/lightweight-charts.standalone.production.js"></script>
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
            /* Quieter background. Two faint radial gradients give the page a
               center of gravity; dropped the grid overlay since at 3% opacity
               it was mostly imperceptible noise that didn't encode anything. */
            background:
                radial-gradient(circle at top left, rgba(96,165,250,0.06), transparent 30%),
                radial-gradient(circle at top right, rgba(94,234,212,0.04), transparent 26%),
                linear-gradient(180deg, #06111d 0%, #081420 100%);
            min-height: 100vh;
            overflow-x: hidden;
            /* Tabular numerals everywhere — prices, ratios, percentages all
               line up vertically and won't jitter as values change. */
            font-variant-numeric: tabular-nums;
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
            display: grid;
            grid-template-columns: minmax(240px, 320px) minmax(0, 1fr);
            align-items: end;
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
            min-width: 0;
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
            font-size: 18px;
            font-weight: 500;
            letter-spacing: -0.02em;
            line-height: 1.2;
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
            /* v0.11.x — column count grew from 7 to 9 (added the Lite toggle
               and the status pill became a trailing element). The grid had
               only 3 max-content columns for the trailing buttons; with 5
               actual trailing items (Scan / Reset / Lite / Logout / status
               pill) the extras wrapped to a second row. Expand the trailing
               repeat to 5 and tighten the input widths slightly to keep the
               whole strip on one line at common laptop viewports. */
            display: grid;
            /* v0.12.0 — Lite toggle moved out of the topbar to the radar
               corner, so the trailing repeat dropped from 5 to 4 (Scan,
               Reset, Logout, status pill). */
            grid-template-columns: minmax(120px, 1fr) minmax(72px, 84px) minmax(80px, 96px) minmax(200px, 0.9fr) repeat(4, minmax(0, max-content));
            gap: 12px;
            align-items: center;
            min-width: 0;
            width: 100%;
        }
        .controls > * {
            min-width: 0;
        }
        /* v0.10.0 subscription panel — sits above the API activity block in
           the Usage tab, showing plan + quota usage + manage-billing link. */
        .sub-panel {
            display: grid;
            gap: 14px;
            padding: 16px;
            border-radius: 18px;
            background: rgba(94, 234, 212, 0.04);
            border: 1px solid rgba(94, 234, 212, 0.22);
            margin-bottom: 14px;
        }
        .sub-panel-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }
        .sub-panel-plan {
            font-size: 18px;
            font-weight: 600;
            letter-spacing: -0.02em;
            margin-top: 2px;
        }
        .sub-panel-pill {
            font-size: 11px;
            font-weight: 500;
            padding: 4px 10px;
            border-radius: 999px;
            background: rgba(148, 163, 184, 0.12);
            color: var(--muted);
        }
        .sub-panel-pill.active   { background: rgba(34, 197, 94, 0.14);  color: #86efac; }
        .sub-panel-pill.trialing { background: rgba(94, 234, 212, 0.14); color: var(--cyan); }
        .sub-panel-pill.past_due { background: rgba(251, 191, 36, 0.16); color: #fde68a; }
        .sub-panel-pill.canceled { background: rgba(248, 113, 113, 0.12); color: #fca5a5; }
        .sub-panel-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }
        .sub-panel-stat .label { font-size: 10px; color: var(--muted); letter-spacing: 0.06em; }
        .sub-panel-stat .value {
            font-family: 'JetBrains Mono', ui-monospace, monospace;
            font-size: 16px;
            font-weight: 600;
            margin: 4px 0 8px;
            font-variant-numeric: tabular-nums;
            letter-spacing: -0.02em;
        }
        .sub-panel-meta { font-size: 11px; color: var(--muted); line-height: 1.4; }
        .sub-panel-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .sub-panel-btn {
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            padding: 8px 14px;
            border-radius: 8px;
            border: 1px solid rgba(148, 163, 184, 0.24);
            color: var(--text);
        }
        .sub-panel-btn.primary {
            background: rgba(94, 234, 212, 0.92);
            color: #06111d;
            border-color: transparent;
        }
        .sub-panel-btn.primary:hover { background: rgba(94, 234, 212, 1); }
        .sub-panel-btn.ghost:hover { background: rgba(255, 255, 255, 0.04); }

        .api-usage-inline {
            display: grid;
            gap: 12px;
            padding: 14px;
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
        button:focus-visible,
        input:focus-visible,
        select:focus-visible {
            outline: 2px solid rgba(94,234,212,0.95);
            outline-offset: 2px;
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
            /* 200px gives the label + range track + value comfortable room
               without overflowing into the next grid column. Smaller than
               this and the "90" value collides with the Scan button. */
            min-width: 200px;
            overflow: hidden;
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
        /* v0.8.0 — single-column layout. Right rail removed; Activity/Context/
           Usage moved to a tabs card below the chart. The radar earns the
           full content width and becomes the unambiguous visual centerpiece. */
        .dashboard-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 18px;
            align-items: start;
        }
        .main-column {
            display: grid;
            gap: 18px;
            min-width: 0;
        }
        /* Status pill that lives in the topbar (replaces the old yellow
           full-width banner). Cyan dot for live, amber/blue/slate variants
           via the same .stale/.error/.cached classes the bias chip uses. */
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 11px;
            color: var(--muted);
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(148,163,184,0.16);
        }
        .status-pill::before {
            content: '';
            width: 6px; height: 6px; border-radius: 50%;
            background: var(--cyan);
            box-shadow: 0 0 0 3px rgba(94,234,212,0.14);
        }
        .status-pill.stale::before { background: var(--amber); box-shadow: 0 0 0 3px rgba(251,191,36,0.16); }
        .status-pill.error::before { background: #94a3b8; box-shadow: 0 0 0 3px rgba(148,163,184,0.18); }
        .status-pill.cached::before { background: var(--blue); box-shadow: 0 0 0 3px rgba(96,165,250,0.14); }
        /* Hero row: focus header + price stat side by side. Replaces the
           old hero-panel that had a big H1 + paragraph + side quote pill. */
        .hero-row {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: flex-end;
            gap: 16px;
            padding: 6px 4px 0;
        }
        .focus-header { min-width: 0; }
        .focus-eyebrow {
            font-size: 10px;
            color: var(--muted);
            letter-spacing: 0.06em;
            font-weight: 500;
            text-transform: uppercase;
        }
        .focus-line {
            display: flex;
            flex-wrap: wrap;
            align-items: baseline;
            gap: 12px;
            margin-top: 4px;
        }
        .focus-line .focus-symbol-text {
            font-size: 28px;
            font-weight: 600;
            letter-spacing: -0.03em;
        }
        .focus-narrative-line {
            color: var(--muted);
            font-size: 12px;
            margin-top: 6px;
            line-height: 1.5;
        }
        .focus-stat { text-align: right; }
        .focus-stat .focus-stat-price {
            font-family: 'JetBrains Mono', ui-monospace, monospace;
            font-size: 28px;
            font-weight: 600;
            letter-spacing: -0.03em;
            font-variant-numeric: tabular-nums;
        }
        .focus-stat .focus-stat-sub {
            font-family: 'JetBrains Mono', ui-monospace, monospace;
            font-size: 12px;
            color: var(--muted);
            margin-top: 2px;
            font-variant-numeric: tabular-nums;
        }
        /* Microcharts strip below the radar. Three equal cards with a label,
           a value, a small horizontal meter, and a one-line subtext. */
        .microcharts-row {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }
        .microchart-card {
            border-radius: 14px;
            padding: 14px 16px;
            background: var(--panel-2);
            border: 1px solid rgba(148,163,184,0.12);
        }
        .microchart-label {
            font-size: 10px;
            letter-spacing: 0.06em;
            color: var(--muted);
            margin-bottom: 8px;
            font-weight: 500;
        }
        .microchart-value {
            font-family: 'JetBrains Mono', ui-monospace, monospace;
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 8px;
            font-variant-numeric: tabular-nums;
            letter-spacing: -0.02em;
        }
        .meter {
            position: relative;
            width: 100%;
            height: 6px;
            border-radius: 999px;
            background: rgba(255,255,255,0.06);
            overflow: hidden;
        }
        .meter-fill {
            position: absolute;
            inset: 0 auto 0 0;
            width: 0%;
            border-radius: inherit;
            background: var(--cyan);
            transition: width 0.18s ease;
        }
        .meter-fill.green { background: var(--green); }
        .meter-fill.red   { background: var(--red); }
        .meter-fill.amber { background: var(--amber); }
        .meter-subtext {
            margin-top: 8px;
            font-size: 11px;
            color: var(--muted);
            line-height: 1.4;
        }
        /* The single intel card below the chart absorbs the old right-rail
           Activity / Context / Usage tabs. */
        .intel-card { padding: 18px; }
        @media (max-width: 720px) {
            .hero-row { align-items: flex-start; }
            .focus-stat { text-align: left; }
            .microcharts-row { grid-template-columns: 1fr; }
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
        /* Demoted from display heading (was up to 56px, weight 800) to a
           proper section title size. This is a label ("TSLA breakout monitor"),
           not a metric — the focus node price + symbol is the real hero. */
        .focus-meta h1 {
            margin: 0 0 8px;
            font-size: 22px;
            line-height: 1.2;
            font-weight: 600;
            letter-spacing: -0.02em;
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
            font-size: 10px;
            color: var(--muted);
            letter-spacing: 0.06em;
            margin-bottom: 6px;
            font-weight: 500;
        }
        .quote-pill .value {
            font-size: 26px;
            font-weight: 600;
            letter-spacing: -0.03em;
            font-variant-numeric: tabular-nums;
        }
        .quote-pill .sub {
            margin-top: 6px;
            color: var(--muted);
            font-size: 13px;
        }
        .hero-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.2fr) minmax(320px, 0.8fr);
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
            min-width: 0;
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
        .focus-direction {
            margin-top: 6px;
            font-size: 16px;
            font-weight: 800;
            line-height: 1;
        }
        .focus-node.up .focus-direction { color: #86efac; }
        .focus-node.down .focus-direction { color: #fca5a5; }
        .focus-node.neutral .focus-direction { color: #bfdbfe; }
        /* The focus node IS the hero — this is the largest, heaviest text on
           screen because it's what traders actually look at. */
        .focus-symbol { font-size: 32px; font-weight: 600; letter-spacing: -0.03em; }
        .focus-price { font-size: 24px; font-weight: 600; margin-top: 6px; font-variant-numeric: tabular-nums; }
        .focus-bias { margin-top: 8px; font-size: 12px; color: var(--muted); }
        /* v0.8.1 — indicator nodes are now circles. The rounded-rectangle
           form fought the polar geometry of the radar; circles read as
           data points on a polar chart. Per-node price display moved into
           a hover tooltip; only ticker + breakout % live in the circle. */
        .indicator-node {
            position: absolute;
            width: clamp(48px, 7.4vw, 64px);
            height: clamp(48px, 7.4vw, 64px);
            padding: 0;
            border-radius: 50%;
            background: rgba(5, 12, 21, 0.92);
            border: 1.5px solid rgba(148,163,184,0.45);
            box-shadow: 0 6px 16px rgba(0,0,0,0.30);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            gap: 1px;
            text-align: center;
            transform: translate(-50%, -50%);
            cursor: pointer;
            z-index: 4;
            font-family: inherit;
            color: var(--text);
            /* Ease into new positions when breakout strength changes
               between refreshes — gives the radar a continuous-momentum
               feel. Scale on hover for affordance. */
            transition: left 0.45s cubic-bezier(0.22, 1, 0.36, 1),
                        top  0.45s cubic-bezier(0.22, 1, 0.36, 1),
                        transform 0.15s ease,
                        box-shadow 0.15s ease,
                        border-color 0.15s ease;
        }
        .indicator-node:hover {
            transform: translate(-50%, -50%) scale(1.08);
            z-index: 6;
            box-shadow: 0 10px 24px rgba(0,0,0,0.45);
        }
        .indicator-node:focus-visible {
            outline: 2px solid var(--cyan);
            outline-offset: 2px;
        }
        /* Connecting lines also animate to follow the node smoothly. */
        #lines line[id^='line-'] {
            transition: x2 0.45s cubic-bezier(0.22, 1, 0.36, 1),
                        y2 0.45s cubic-bezier(0.22, 1, 0.36, 1);
        }
        .indicator-node .ticker {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: -0.01em;
            line-height: 1;
        }
        /* Score line inside the circle: tight monospace, slight muted color
           so the ticker remains the primary read at a glance. */
        .indicator-node .mini-bias {
            font-family: 'JetBrains Mono', ui-monospace, monospace;
            font-size: 9px;
            font-weight: 500;
            line-height: 1;
            margin-top: 2px;
            color: rgba(232,241,255,0.78);
            font-variant-numeric: tabular-nums;
        }
        /* The .price element no longer renders inside the circle (circles
           don't have room for prices). Hidden but kept for any legacy
           innerHTML that may still write to it during transitions. */
        .indicator-node .price { display: none; }
        .indicator-node.green { border-color: rgba(34,197,94,0.55); }
        .indicator-node.red { border-color: rgba(248,113,113,0.55); }
        .indicator-node.neutral { border-color: rgba(148,163,184,0.45); }
        /* Inverse-correlation indicators get a dashed border so the encoding
           survives even if the user can't tell green-stroke from green-stroke
           (color blindness, low-contrast, screenshot grayscaling). */
        .indicator-node.inverse {
            border-style: dashed;
            border-width: 1.5px;
        }
        /* Concentric SVG rings at strength thresholds (0.5 / 0.7 / 0.9).
           Together with the score-driven radius, they turn the radar from
           decoration into a real correlation chart. */
        .radar-ring {
            fill: none;
            stroke: rgba(148,163,184,0.18);
            stroke-dasharray: 2,5;
        }
        .radar-ring-label {
            fill: rgba(148,163,184,0.45);
            font-size: 10px;
            font-family: 'JetBrains Mono', ui-monospace, monospace;
            font-variant-numeric: tabular-nums;
            text-anchor: middle;
        }
        .radar-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
            margin-top: 14px;
            padding-top: 12px;
            border-top: 1px solid rgba(148,163,184,0.08);
            font-size: 11px;
            color: var(--muted);
            justify-content: center;
        }
        .radar-legend-item { display: inline-flex; align-items: center; gap: 6px; }
        .radar-legend-swatch { width: 22px; height: 2px; border-radius: 1px; background: var(--green); }
        .radar-legend-swatch.inverse { background: transparent; border-top: 2px dashed var(--green); height: 0; }
        .radar-legend-swatch.neutral { background: rgba(148,163,184,0.6); height: 1px; }
        .radar-legend-swatch.dot {
            width: 10px; height: 10px; border-radius: 50%;
            background: transparent;
            border: 1px dashed rgba(148,163,184,0.6);
        }
        /* Verdict line in the center node — "9/12 ↑". Tells the user the
           basket disposition at a glance without parsing every indicator. */
        .focus-verdict {
            font-family: 'JetBrains Mono', ui-monospace, monospace;
            font-size: 12px;
            color: var(--cyan);
            margin-top: 6px;
            font-variant-numeric: tabular-nums;
            letter-spacing: 0.04em;
        }
        .focus-node.up    .focus-verdict { color: #86efac; }
        .focus-node.down  .focus-verdict { color: #fca5a5; }
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
            font-size: 10px;
            letter-spacing: 0.06em;
            color: var(--muted);
            margin-bottom: 8px;
            font-weight: 500;
        }
        .signal-card .value {
            font-size: 26px;
            font-weight: 600;
            letter-spacing: -0.03em;
            font-variant-numeric: tabular-nums;
        }
        .signal-card .sub {
            margin-top: 6px;
            color: var(--muted);
            font-size: 13px;
        }
        .signal-card.up .value { color: var(--green); }
        .signal-card.down .value { color: var(--red); }
        /* Status chip composes two facts: bias (green/red/blue background)
           and freshness (a small dot at the start). Green/red are reserved
           for market direction only — system status (live/stale/error) is
           encoded as the dot color, not the chip background, so the eye
           never reads "green chip" as "system OK" and vice versa. */
        .bias-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 12px;
            padding: 8px 14px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 500;
            letter-spacing: 0.04em;
        }
        .bias-chip::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--cyan);
            box-shadow: 0 0 0 3px rgba(94,234,212,0.14);
            flex-shrink: 0;
        }
        .bias-chip.up { background: rgba(34,197,94,0.10); color: #86efac; }
        .bias-chip.down { background: rgba(248,113,113,0.10); color: #fca5a5; }
        .bias-chip.neutral { background: rgba(148,163,184,0.10); color: #bfdbfe; }
        /* Freshness modifier — only changes the dot, never the chip body,
           so up-bias-but-stale still reads as up, with a clearly-flagged
           stale dot rather than a chip that fights the bias signal. */
        .bias-chip.stale::before { background: var(--amber); box-shadow: 0 0 0 3px rgba(251,191,36,0.16); }
        .bias-chip.error::before { background: #94a3b8; box-shadow: 0 0 0 3px rgba(148,163,184,0.18); }
        .bias-chip.cached::before { background: var(--blue); box-shadow: 0 0 0 3px rgba(96,165,250,0.14); }
        .range-grid {
            display: grid;
            gap: 10px;
        }
        /* Levels ladder: vertical R2/R1/now/S1/S2 stack mapping screen
           position to price position. Replaces the side-by-side R+S tables. */
        .levels-ladder {
            display: grid;
            gap: 6px;
            margin-top: 8px;
        }
        .ladder-row {
            display: grid;
            grid-template-columns: 44px 1fr auto auto;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            border-radius: 12px;
            background: rgba(255,255,255,0.025);
            border-left: 3px solid transparent;
            font-variant-numeric: tabular-nums;
        }
        .ladder-row.resistance { border-left-color: rgba(248,113,113,0.45); }
        .ladder-row.support    { border-left-color: rgba(74,222,128,0.45); }
        .ladder-row.current {
            background: rgba(96,165,250,0.10);
            border-left-color: rgba(96,165,250,0.85);
            box-shadow: 0 0 0 1px rgba(96,165,250,0.18) inset;
        }
        .ladder-tag {
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.12em;
            color: var(--muted);
        }
        .ladder-row.resistance .ladder-tag { color: #fca5a5; }
        .ladder-row.support    .ladder-tag { color: #86efac; }
        .ladder-row.current    .ladder-tag { color: #bfdbfe; }
        .ladder-price {
            font-size: 16px;
            font-weight: 700;
        }
        .ladder-meta {
            font-size: 11px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .ladder-diff {
            font-size: 12px;
            font-weight: 600;
            color: var(--muted);
            min-width: 64px;
            text-align: right;
        }
        .ladder-row.resistance .ladder-diff { color: #fca5a5; }
        .ladder-row.support    .ladder-diff { color: #86efac; }
        .ladder-empty {
            padding: 18px;
            color: var(--muted);
            font-size: 13px;
            text-align: center;
            border: 1px dashed rgba(148,163,184,0.2);
            border-radius: 12px;
        }
        /* Section divider used inside the Activity tab so probes/attempts/drivers
           are still visually delineated even though they share one panel. */
        .activity-section-label {
            margin: 16px 0 8px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--muted);
        }
        .activity-section-label:first-of-type {
            margin-top: 18px;
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
        .stack-card {
            border-radius: 24px;
            padding: 20px;
        }
        .metric-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }
        .panel-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 14px;
        }
        .panel-tab {
            padding: 10px 12px;
            border-radius: 999px;
            border: 1px solid rgba(148, 163, 184, 0.18);
            background: rgba(255,255,255,0.03);
            color: var(--muted);
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.14em;
        }
        .panel-tab.active {
            border-color: transparent;
            background: linear-gradient(135deg, rgba(96,165,250,0.96), rgba(94,234,212,0.96));
            color: #06111d;
        }
        .tab-panel {
            display: none;
        }
        .tab-panel.active {
            display: block;
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
            font-variant-numeric: tabular-nums;
        }
        .metric-card .sub {
            margin-top: 6px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.5;
        }
        .drivers-list, .attempt-list {
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
            font-variant-numeric: tabular-nums;
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
            .topbar {
                grid-template-columns: 1fr;
                align-items: stretch;
            }
            /* v0.12.0 — keep 5 cols at this breakpoint. The 4-col attempt
               broke because the slider spans 2 cols, leaving 4 - 2 = 2 cols
               for the 4 inputs (ticker, period, lookback) on row 1, forcing
               another item to wrap. 5 cols cleanly accommodates row 1 =
               ticker / period / lookback / slider(span 2) and row 2 =
               Scan / Reset / Logout / status pill (4 items, 1 empty slot). */
            .controls {
                grid-template-columns: repeat(5, minmax(0, 1fr));
            }
            .controls .slider-wrap { grid-column: span 2; }
            .controls button { width: 100%; }
            .hero-grid { grid-template-columns: minmax(0, 1fr); }
            .radar-card { min-width: 0; }
        }
        @media (max-width: 980px) {
            .dashboard-grid { grid-template-columns: 1fr; }
            .topbar { position: static; }
            .controls { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .controls > * { min-width: 0; }
            .controls .slider-wrap { grid-column: span 2; }
            .controls button { width: 100%; }
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
            .controls .slider-wrap { grid-column: auto; }
            .controls button { width: 100%; }
            .api-usage-grid { grid-template-columns: 1fr; }
            .slider-wrap { min-width: 0; }
            .hero-top { flex-direction: column; }
            .breakout-grid, .metric-grid { grid-template-columns: 1fr; }
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

        /* v0.12.0 — persistent "not a forecast" tagline in the focus
           header. Sits below the narrative line, low visual weight, but
           always visible so the honesty stance is communicated through
           the UI itself, not just the disclaimer gate. */
        .focus-honesty-tagline {
            margin: 6px 0 0;
            font-size: 11px;
            letter-spacing: 0.04em;
            color: rgba(156, 176, 202, 0.55);
            font-style: italic;
        }
        body.lite .focus-honesty-tagline { display: none; }

        /* v0.12.0 — small-label contrast bump. Co-Claude flagged the
           uppercase 10–11px muted-color labels (.microchart-label,
           .focus-eyebrow, .panel-label, .slider-wrap label) as effortful
           to read at that size. Bumping their weight a touch and tightening
           the muted color closer to text white preserves the eyebrow look
           while improving legibility. */
        .microchart-label,
        .focus-eyebrow,
        .panel-label,
        .slider-wrap label,
        .callout-title,
        .activity-section-label {
            color: #b6c5dd;
        }

        /* v0.12.0 — Lite mode toggle relocated to bottom-right of the radar
           card. Discoverable via spatial proximity rather than topbar label
           scan. The topbar Lite button is hidden in favor of this. */
        .radar-lite-toggle {
            position: absolute;
            right: 14px;
            bottom: 14px;
            z-index: 5;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 999px;
            border: 1px solid rgba(148, 163, 184, 0.22);
            background: rgba(5, 12, 21, 0.72);
            color: var(--muted);
            font-family: inherit;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            cursor: pointer;
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            transition: color 0.16s ease, border-color 0.16s ease, background 0.16s ease;
        }
        .radar-lite-toggle:hover {
            color: var(--text);
            border-color: rgba(94, 234, 212, 0.42);
        }
        body.lite .radar-lite-toggle {
            color: var(--cyan);
            border-color: rgba(94, 234, 212, 0.5);
            background: rgba(94, 234, 212, 0.1);
        }
        .radar-card { position: relative; }

        /* v0.11.0 — HackTrader Lite. A render-only theme that strips
           everything except topbar, focus header, correlation radar, and
           footer. Same data path; just hides the supporting cards and lets
           the radar grow into the available viewport. Toggle via the Lite
           button in the topbar or the ?lite=1 URL param. */
        body.lite .microcharts-row,
        body.lite .stack-card,
        body.lite .intel-card,
        body.lite #debugBanner,
        body.lite #statusBanner {
            display: none !important;
        }
        body.lite .controls .slider-wrap,
        body.lite #lookback,
        body.lite #period {
            display: none;
        }
        body.lite .hero-row {
            margin-bottom: 12px;
        }
        body.lite .radar-card {
            min-height: calc(100vh - 260px);
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        body.lite .radar-stage {
            max-width: min(78vh, 100%);
            margin: 0 auto;
            aspect-ratio: 1 / 1;
        }
        body.lite .focus-narrative-line { display: none; }
        .lite-toggle {
            border: 1px solid rgba(148,163,184,0.18);
            background: rgba(255,255,255,0.03);
            color: var(--muted);
            border-radius: 999px;
            padding: 6px 14px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            cursor: pointer;
            transition: color 0.16s ease, border-color 0.16s ease, background 0.16s ease;
            font-family: inherit;
        }
        .lite-toggle:hover {
            color: var(--text);
            border-color: rgba(148,163,184,0.32);
        }
        body.lite .lite-toggle {
            color: var(--cyan);
            border-color: rgba(94,234,212,0.42);
            background: rgba(94,234,212,0.08);
        }
    </style>
</head>
<body class='<?= $liteMode ? "lite" : "" ?>' onload='syncToleranceValue(); applyLiteFromStorage(); updateDashboard()'>
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
        <header class='topbar glass'>
            <div class='brand'>
                <strong class='brand-title'><span class='pengo-trigger' id='pengoTrigger' title='Activate pengo'>🐧</span><span class='title-text'>Structure cockpit</span></strong>
            </div>
            <div class='controls'>
                <input type='text' id='ticker' list='ticker-list' placeholder='Ticker' autocomplete='off' spellcheck='false'>
                <datalist id='ticker-list'>
                    <?php foreach ($allTickers as $t): ?>
                        <option value='<?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?>'></option>
                    <?php endforeach; ?>
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
                <!-- Lite toggle moved to the radar card bottom-right corner in
                     v0.12.0 for spatial-proximity discoverability. -->
                <button class='ghost-btn' onclick='window.location.href="logout.php"'>Logout</button>
                <div id='topbarStatus' class='status-pill' aria-live='polite'>—</div>
            </div>
        </header>

        <!-- Debug banner only. The old yellow source/status banner is gone;
             it's replaced by the topbar status-pill that sits next to the
             logout button. Cleaner because system-level facts (live, stale)
             belong with system controls, not in the page body. -->
        <div id='debugBanner' class='debug-banner' style='display:none;'></div>
        <!-- Hidden-but-present so legacy JS that targets statusBanner won't error. -->
        <div id='statusBanner' class='status-banner' style='display:none;'></div>

        <div id='pengoPopup' class='pengo-popup' role='status' aria-live='polite'>
            <span class='emoji'>🐧</span>
            <span class='copy'>pengo approves the setup</span>
        </div>

        <section class='dashboard-grid'>
            <section class='main-column' aria-label='HackTrader cockpit'>

                <!-- Hero row: focus header (eyebrow + symbol + bias pill +
                     one-line narrative) on the left, live price stat on the
                     right. No more giant H1, no more giant quote pill. -->
                <section class='hero-row'>
                    <div class='focus-header'>
                        <div class='focus-eyebrow' id='focusHeadline'>Focus symbol</div>
                        <div class='focus-line'>
                            <span class='focus-symbol-text' id='focusSymbolText'>TSLA</span>
                            <span id='statusChip' class='bias-chip neutral'>—</span>
                        </div>
                        <p class='focus-narrative-line' id='focusNarrative'>—</p>
                        <!-- v0.12.0 — persistent honesty stance. Counters the
                             user's prior expectation (set by every other
                             trading product) that this is forecasting. Low
                             visual weight, always visible. -->
                        <p class='focus-honesty-tagline'>Describing current structure — not a forecast.</p>
                    </div>
                    <div class='focus-stat'>
                        <div class='focus-stat-price' id='focusPriceBox'>$—</div>
                        <div class='focus-stat-sub' id='focusTimeBox'>—</div>
                    </div>
                </section>

                <!-- Correlation radar — full content width now, no longer
                     fighting a side-by-side breakout card. Indicators plot
                     at radius proportional to correlation strength. -->
                <section class='radar-card glass'>
                    <div class='section-title'>
                        <h2>Correlation radar</h2>
                        <span id='indicatorBiasSubtext'>Distance from center = directional pressure</span>
                    </div>
                    <div class='radar-stage' id='clock'>
                        <svg id='lines' class='radar-lines'></svg>
                        <div class='focus-node neutral' id='focus' onclick='resetDashboard()' style='cursor:pointer;'>
                            <div class='focus-symbol'>INIT</div>
                            <div class='focus-price'>SCAN</div>
                            <div class='focus-bias'>—</div>
                        </div>
                    </div>
                    <div class='radar-legend' aria-hidden='true'>
                        <span class='radar-legend-item'><span class='radar-legend-swatch'></span> confirming</span>
                        <span class='radar-legend-item'><span class='radar-legend-swatch inverse'></span> inverse confirm</span>
                        <span class='radar-legend-item'><span class='radar-legend-swatch neutral'></span> neutral</span>
                        <span class='radar-legend-item'><span class='radar-legend-swatch dot'></span> dashed border = inverse</span>
                    </div>
                    <!-- v0.12.0 — Lite toggle in the corner of the radar card.
                         Relocated from the topbar so users discover it through
                         spatial adjacency to the thing it affects. -->
                    <button type='button' class='radar-lite-toggle' onclick='toggleLite()' title='Toggle Lite mode (radar-only view)' aria-label='Toggle Lite mode'>
                        <span aria-hidden='true'>⤢</span>
                        <span>Lite</span>
                    </button>
                </section>

                <!-- Microcharts strip: 3 satellite cards under the radar.
                     Re-introduced after v0.7.7 stripped them out — in the
                     single-column layout there's room and they don't
                     duplicate other on-screen elements anymore. -->
                <section class='microcharts-row'>
                    <div class='microchart-card'>
                        <div class='microchart-label'>Directional pressure</div>
                        <div class='microchart-value' id='pressureValue'>—</div>
                        <div class='meter'><div class='meter-fill green' id='pressureFill'></div></div>
                        <div class='meter-subtext' id='pressureSubtext'>—</div>
                    </div>
                    <div class='microchart-card'>
                        <div class='microchart-label'>Next channel band</div>
                        <div class='microchart-value' id='channelWidthValue'>—</div>
                        <div class='meter'><div class='meter-fill' id='channelWidthFill'></div></div>
                        <div class='meter-subtext' id='channelWidthSubtext'>—</div>
                    </div>
                    <div class='microchart-card'>
                        <div class='microchart-label'>Attempt stress</div>
                        <div class='microchart-value' id='attemptStressValue'>—</div>
                        <div class='meter'><div class='meter-fill amber' id='attemptStressFill'></div></div>
                        <div class='meter-subtext' id='attemptStressSubtext'>—</div>
                    </div>
                </section>

                <!-- Price action chart -->
                <section class='stack-card glass' style='padding-top: 16px; padding-bottom: 8px;'>
                    <div class='section-title'>
                        <h2>Price action</h2>
                        <span id='chartMeta'>—</span>
                    </div>
                    <div id='tvChartContainer' style='width: 100%; height: 420px; margin-top: 10px; border-radius: 12px; overflow: hidden;'></div>
                </section>

                <!-- Levels ladder — moved out of the deleted right rail
                     into the main column. Compact, sits between chart and
                     intel card so traders can glance at structural prices
                     without scrolling. -->
                <section class='stack-card glass'>
                    <div class='section-title'>
                        <h2>Levels</h2>
                        <span id='sourceMeta'>—</span>
                    </div>
                    <div class='levels-ladder' id='levelsLadder' aria-label='Price levels ladder'></div>
                </section>

                <!-- Intel card: tabs that previously lived in the right
                     rail. Activity (probes + attempts + drivers) /
                     Context (volume + indicator bias + extremes) /
                     Usage (API counters). -->
                <section class='intel-card glass'>
                    <div class='section-title'>
                        <h2>Intel</h2>
                        <span id='analysisMeta'>—</span>
                    </div>
                    <div class='panel-tabs' role='tablist' aria-label='Intel views'>
                        <button type='button' role='tab' aria-selected='true' aria-controls='rightPanel-activity' class='panel-tab active' id='rightTab-activity' onclick="switchRightPanelTab('activity')">Activity</button>
                        <button type='button' role='tab' aria-selected='false' aria-controls='rightPanel-context' class='panel-tab' id='rightTab-context' onclick="switchRightPanelTab('context')">Context</button>
                        <button type='button' role='tab' aria-selected='false' aria-controls='rightPanel-usage' class='panel-tab' id='rightTab-usage' onclick="switchRightPanelTab('usage')">Usage</button>
                    </div>
                    <div class='tab-panel active' role='tabpanel' aria-labelledby='rightTab-activity' id='rightPanel-activity'>
                        <div id='probeGraphPanel'></div>
                        <div class='activity-section-label'>Failed attempts today</div>
                        <div class='attempt-list' id='attemptList'></div>
                        <div class='activity-section-label'>Top drivers</div>
                        <div class='drivers-list' id='driversList'></div>
                        <div class='activity-section-label'>Channels</div>
                        <div class='range-grid' id='channelList'>
                            <div class='range-card'>
                                <div class='range-top'>
                                    <div class='range-name'>Current channel</div>
                                    <div class='range-width'>—</div>
                                </div>
                                <div class='range-track'><div class='range-fill' style='width: 0%'></div></div>
                                <div class='range-meta'><span>--</span><span>--</span></div>
                            </div>
                        </div>
                    </div>
                    <div class='tab-panel' role='tabpanel' aria-labelledby='rightTab-context' id='rightPanel-context'>
                        <div class='metric-grid'>
                            <div class='metric-card'>
                                <div class='label'>Day volume</div>
                                <div class='value' id='dayVolumeValue'>--</div>
                                <div class='sub' id='dayVolumeSubtext'>—</div>
                            </div>
                            <div class='metric-card'>
                                <div class='label'>Day ratio</div>
                                <div class='value' id='dayVolumeRatio'>--</div>
                                <div class='sub' id='barVolumeSubtext'>—</div>
                            </div>
                            <div class='metric-card'>
                                <div class='label'>Indicator bias</div>
                                <div class='value' id='indicatorBiasValue'>--</div>
                                <div class='sub' id='indicatorBiasSubtextCtx'>Correlation basket disposition</div>
                            </div>
                            <div class='metric-card'>
                                <div class='label'>Recent extremes</div>
                                <div class='value' id='recentExtremesValue'>--</div>
                                <div class='sub' id='previousDayValue'>—</div>
                            </div>
                        </div>
                    </div>
                    <div class='tab-panel' role='tabpanel' aria-labelledby='rightTab-usage' id='rightPanel-usage'>
                        <!-- v0.10.0 subscription summary. Populated by
                             updateSubscriptionPanel() from /me.php. -->
                        <div class='sub-panel'>
                            <div class='sub-panel-top'>
                                <div>
                                    <div class='label'>Plan</div>
                                    <div class='sub-panel-plan' id='subPlanName'>—</div>
                                </div>
                                <span class='sub-panel-pill' id='subPlanPill'>—</span>
                            </div>
                            <div class='sub-panel-grid'>
                                <div class='sub-panel-stat'>
                                    <div class='label'>API calls this period</div>
                                    <div class='value' id='subCallsUsed'>—</div>
                                    <div class='meter'><div class='meter-fill' id='subCallsFill'></div></div>
                                </div>
                                <div class='sub-panel-stat'>
                                    <div class='label'>Watched tickers</div>
                                    <div class='value' id='subTickersUsed'>—</div>
                                    <div class='meter'><div class='meter-fill' id='subTickersFill'></div></div>
                                </div>
                            </div>
                            <div class='sub-panel-meta' id='subPanelMeta'>—</div>
                            <div class='sub-panel-actions'>
                                <a class='sub-panel-btn primary' id='subUpgradeBtn' href='index.php#pricing'>Upgrade plan</a>
                                <a class='sub-panel-btn ghost' href='billing.php'>Manage billing</a>
                            </div>
                        </div>

                        <div class='activity-section-label'>Live API activity</div>
                        <div class='api-usage-inline' aria-live='polite'>
                            <div class='api-usage-top'>
                                <div>
                                    <div class='label'>This session</div>
                                    <div class='api-usage-kpi' id='apiUsageTotal'>--</div>
                                </div>
                                <span class='api-usage-pill' id='apiUsagePill'>idle</span>
                            </div>
                            <div class='api-usage-sub' id='apiUsageSub'>No counted requests yet.</div>
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
                    </div>
                </section>

                <!-- Hidden compatibility shims: a couple of legacy IDs that
                     JS still writes to but no longer have a UI position
                     (they were inside the breakout-card we deleted). -->
                <span style='display:none' id='upProbability'>--</span>
                <span style='display:none' id='downProbability'>--</span>
                <span style='display:none' id='upProbabilitySub'>—</span>
                <span style='display:none' id='downProbabilitySub'>—</span>
                <span style='display:none' id='quoteTimezone'>ET</span>

            </section>
        </section>
        <footer>HackTrader v0.12.0 · © 2026 Jayson Hawley · All rights reserved.</footer>
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

        // v0.11.0 — Lite mode toggle. URL param wins on first paint (PHP
        // already applied it); on subsequent visits localStorage carries
        // the preference so the user doesn't have to re-toggle. Toggling
        // is a CSS class swap — no reload — and we re-run updateDashboard
        // so the radar nodes reposition to the new viewport.
        function applyLiteFromStorage() {
            try {
                const params = new URLSearchParams(window.location.search);
                const fromUrl = params.has('lite');
                if (fromUrl) {
                    return;
                }
                if (localStorage.getItem('htLite') === '1') {
                    document.body.classList.add('lite');
                }
            } catch (e) {
                // localStorage blocked / SSR-mode — no-op.
            }
        }

        function toggleLite() {
            const isLite = document.body.classList.toggle('lite');
            try {
                localStorage.setItem('htLite', isLite ? '1' : '0');
                const url = new URL(window.location.href);
                if (isLite) {
                    url.searchParams.set('lite', '1');
                } else {
                    url.searchParams.delete('lite');
                }
                window.history.replaceState({}, '', url.toString());
            } catch (e) {
                // localStorage / history blocked — toggle still applied.
            }
            // Reflow happened; nudge the radar to recompute node positions
            // for the new container size.
            if (typeof updateDashboard === 'function') {
                setTimeout(() => updateDashboard(), 60);
            }
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
            const debugEnabled = new URLSearchParams(window.location.search).get('debug') === '1';
            if (!debugEnabled || !message) {
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
            if (data.live_status) parts.push(`STATUS ${String(data.live_status).toUpperCase()}`);
            if (data.cache?.stale) parts.push(`STALE ${data.cache.age_seconds}s`);
            else if (data.cache?.hit) parts.push(`CACHE ${data.cache.age_seconds}s`);
            if (data.fallback_reason) parts.push('FALLBACK ACTIVE');
            if (data.warning) parts.push('LIVE FETCH WARNING');
            if (data.live_error_summary && data.live_status !== 'live') parts.push(String(data.live_error_summary).slice(0, 80));
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

        function switchRightPanelTab(tabName) {
            ['activity', 'context', 'usage'].forEach((name) => {
                const tab = document.getElementById(`rightTab-${name}`);
                const panel = document.getElementById(`rightPanel-${name}`);
                const active = name === tabName;
                if (tab) {
                    tab.classList.toggle('active', active);
                    tab.setAttribute('aria-selected', active ? 'true' : 'false');
                }
                if (panel) panel.classList.toggle('active', active);
            });
        }

        // Unified status chip: combines market bias + data freshness in one badge.
        // Bias drives the chip background color (green/red/neutral). Freshness
        // drives only the leading dot color (cyan = live, blue = cached,
        // amber = stale, slate = error). This separation prevents the
        // pre-attentive misread of "green chip = system OK" since green is
        // strictly market direction.
        // v0.10.0 status chip — language reframed. The underlying score from
        // run-brk.py was originally framed as "breakout probability"; backtest
        // showed it has no predictive edge after costs (47% hit rate, anti-
        // predictive on intraday). Reframed here as "directional pressure" /
        // "leaning" — observational structure, NOT a prediction. Same data,
        // honest labeling.
        // Example: "↑ Up-leaning · structure-aligned · Live · MASSIVE"
        function updateStatusChip(data) {
            const el = document.getElementById('statusChip');
            if (!el) return;

            const probs = data?.probabilities || {};
            const bias = probs.bias || 'neutral';
            const alignment = probs.confidence || 'low';  // alignment, not confidence
            const biasIcon = bias === 'up' ? '↑' : bias === 'down' ? '↓' : '→';
            const biasLabel = bias === 'neutral'
                ? 'Balanced'
                : `${bias.charAt(0).toUpperCase()}${bias.slice(1)}-leaning`;

            const liveStatus = String(data?.live_status || '').toLowerCase();
            const source = data?.source ? String(data.source).toUpperCase() : '';
            const summary = data?.live_error_summary ? String(data.live_error_summary) : '';

            // Two independent state classes: the bias class paints the chip
            // background, the freshness class paints only the leading dot.
            const biasClass = bias;  // 'up' | 'down' | 'neutral'
            let freshnessClass = '';  // '' = live (default cyan dot)
            let freshnessLabel;
            if (liveStatus === 'stale_fallback') {
                freshnessClass = 'stale';
                freshnessLabel = `Stale${summary ? ` (${summary})` : ''}`;
            } else if (liveStatus === 'error') {
                freshnessClass = 'error';
                freshnessLabel = `Error${summary ? ` (${summary})` : ''}`;
            } else if (liveStatus === 'cache_hit') {
                freshnessClass = 'cached';
                const age = Number(data?.cache?.age_seconds);
                freshnessLabel = `Cached${Number.isFinite(age) ? ` ${age}s` : ''}`;
            } else {
                freshnessLabel = 'Live';
            }

            const parts = [`${biasIcon} ${biasLabel}`];
            // "high alignment" rather than "high confidence" — describes
            // how many indicators point the same way, not how confident
            // the system is about the future.
            if (alignment && bias !== 'neutral') parts.push(`${alignment} alignment`);
            parts.push(freshnessLabel);
            if (source) parts.push(source);

            el.className = `bias-chip ${biasClass} ${freshnessClass}`.trim();
            el.textContent = parts.join(' · ');
        }

        // v0.8.0 — small status pill in the topbar, mirrors freshness only
        // (bias direction does NOT belong here; this pill is for system
        // health). Replaces the old full-width yellow source banner.
        function updateTopbarStatus(data) {
            const el = document.getElementById('topbarStatus');
            if (!el) return;
            const liveStatus = String(data?.live_status || '').toLowerCase();
            const source = data?.source ? String(data.source).toUpperCase() : '';
            let cls = '';
            let label = 'Live';
            if (liveStatus === 'stale_fallback') { cls = 'stale'; label = 'Stale'; }
            else if (liveStatus === 'error')     { cls = 'error'; label = 'Error'; }
            else if (liveStatus === 'cache_hit') {
                cls = 'cached';
                const age = Number(data?.cache?.age_seconds);
                label = `Cached${Number.isFinite(age) ? ` ${age}s` : ''}`;
            }
            el.className = `status-pill ${cls}`.trim();
            el.textContent = source ? `${label} · ${source}` : label;
        }

        // v0.8.0 — re-introduced microcharts strip below the radar.
        // Three meters: breakout pressure (up vs down probability spread),
        // channel width (ATR-relative), attempt stress (failed probes).
        function updateMicrocharts(data) {
            const probs = data?.probabilities || {};
            const upProb = Number(probs.up || 0);
            const downProb = Number(probs.down || 0);
            const dominantSide = upProb >= downProb ? 'Up' : 'Down';
            const dominantPct = Math.max(upProb, downProb);
            const fillEl = document.getElementById('pressureFill');
            const valueEl = document.getElementById('pressureValue');
            const subEl = document.getElementById('pressureSubtext');
            if (valueEl) valueEl.textContent = `${dominantSide} ${formatPercent(dominantPct)}`;
            if (fillEl) {
                fillEl.style.width = `${Math.max(4, Math.min(100, dominantPct))}%`;
                fillEl.className = `meter-fill ${dominantSide === 'Up' ? 'green' : 'red'}`;
            }
            if (subEl) subEl.textContent = `Spread ${Math.abs(upProb - downProb).toFixed(1)} pts`;

            // Next-channel-band microchart — shows the price band of the
            // NEXT structural trading channel above (or below) the current
            // one. NOT a prediction — it's where the chart-defined level
            // structure would place price IF current support or resistance
            // breaks. A reference, not a target.
            const channels = Array.isArray(data?.channels) ? data.channels : [];
            const currentChannel = channels.find(c => c?.name === 'current') || channels[0];
            const aboveChannel = channels.find(c => c?.name === 'above_resistance');
            const belowChannel = channels.find(c => c?.name === 'below_support');
            const atr = Number(data?.analysis_parameters?.atr || 0);
            const currentPrice = Number(data?.current_price || data?.focus_price || 0);

            // Pick the breakout-target channel based on bias direction —
            // upside breakout enters the above-resistance channel, downside
            // breakout enters the below-support channel.
            const breakoutSide = upProb >= downProb ? 'up' : 'down';
            const breakoutChannel = breakoutSide === 'up' ? (aboveChannel || belowChannel) : (belowChannel || aboveChannel);
            const targetLower = Number(breakoutChannel?.lower);
            const targetUpper = Number(breakoutChannel?.upper);
            const targetWidth = Number(breakoutChannel?.width || 0);

            const cwValue = document.getElementById('channelWidthValue');
            const cwFill = document.getElementById('channelWidthFill');
            const cwSub = document.getElementById('channelWidthSubtext');
            if (cwValue) {
                // Headline: the bounds of the next structural channel.
                // Reference where price would sit if the current level
                // breaks; not a prediction or recommendation.
                if (Number.isFinite(targetLower) && Number.isFinite(targetUpper)) {
                    const arrow = breakoutSide === 'up' ? '↑' : '↓';
                    cwValue.textContent = `${arrow} $${formatPrice(targetLower)} – $${formatPrice(targetUpper)}`;
                } else if (Number.isFinite(targetLower) || Number.isFinite(targetUpper)) {
                    const single = Number.isFinite(targetUpper) ? targetUpper : targetLower;
                    cwValue.textContent = `$${formatPrice(single)}`;
                } else {
                    cwValue.textContent = '—';
                }
            }
            // Meter shows the band width relative to ATR — wider band = bigger
            // post-breakout move available. Color follows breakout direction.
            if (cwFill) {
                const widthRatio = atr > 0 ? Math.min(100, (targetWidth / atr) * 50) : 0;
                cwFill.style.width = `${Math.max(4, widthRatio)}%`;
                cwFill.className = `meter-fill ${breakoutSide === 'up' ? 'green' : 'red'}`;
            }
            if (cwSub) {
                const parts = [];
                const dirLabel = breakoutSide === 'up' ? 'if breaks up' : 'if breaks down';
                parts.push(dirLabel);
                if (targetWidth) parts.push(`width $${formatPrice(targetWidth)}`);
                if (atr) parts.push(`ATR ${formatPrice(atr)}`);
                if (currentChannel && currentPrice && Number.isFinite(targetLower)) {
                    // Distance from current price to the near edge of the
                    // breakout band — gives a rough sense of how soon
                    // exits would matter.
                    const nearEdge = breakoutSide === 'up' ? targetLower : targetUpper;
                    const distance = Math.abs(currentPrice - nearEdge);
                    if (Number.isFinite(distance) && distance > 0) {
                        parts.push(`${formatPrice(distance)} away`);
                    }
                }
                cwSub.textContent = parts.length ? parts.join(' · ') : 'No channel data';
            }

            const upAttempts = Number(data?.attempts?.failed_up_today || 0);
            const downAttempts = Number(data?.attempts?.failed_down_today || 0);
            const totalProbes = upAttempts + downAttempts;
            const stress = Math.min(100, (totalProbes / 6) * 100);
            const ruleArmed = !!(data?.attempts?.rule_of_three_block_up || data?.attempts?.rule_of_three_block_down);
            const asValue = document.getElementById('attemptStressValue');
            const asFill = document.getElementById('attemptStressFill');
            const asSub = document.getElementById('attemptStressSubtext');
            if (asValue) asValue.textContent = `${totalProbes} ${totalProbes === 1 ? 'probe' : 'probes'}`;
            if (asFill) asFill.style.width = `${Math.max(4, stress)}%`;
            if (asSub) asSub.textContent = `Up ${upAttempts} · Down ${downAttempts}${ruleArmed ? ' · rule-of-3 armed' : ''}`;
        }

        // Unified levels ladder: stacks resistances above current price above supports,
        // mapping the on-screen vertical order to actual price order. The current
        // price marker sits in the middle and shows the live quote.
        function renderLevelLadder(data) {
            const container = document.getElementById('levelsLadder');
            if (!container) return;

            const resistances = (data?.upper_resistances || []).slice(0, 2);
            const supports    = (data?.lower_supports   || []).slice(0, 2);
            const current     = Number(data?.current_price ?? data?.last_price ?? 0);

            const empty = !resistances.length && !supports.length;
            if (empty && !current) {
                container.innerHTML = `<div class='ladder-empty'>No validated levels yet</div>`;
                return;
            }

            const rowFor = (level, prefix, index, kind) => `
                <div class='ladder-row ${kind}'>
                    <div class='ladder-tag'>${prefix}${index}</div>
                    <div class='ladder-price'>$${formatPrice(level.price)}</div>
                    <div class='ladder-meta'>${level.touches ?? '--'} touches</div>
                    <div class='ladder-diff'>${kind === 'resistance' ? '+' : '-'}$${formatPrice(Math.abs(Number(level.diff || 0)))}</div>
                </div>
            `;

            const parts = [];
            // Resistances: highest first (R2 above R1)
            const sortedR = [...resistances].sort((a, b) => Number(b.price) - Number(a.price));
            sortedR.forEach((lvl, i) => {
                const idx = sortedR.length - i;  // R2 then R1
                parts.push(rowFor(lvl, 'R', idx, 'resistance'));
            });

            parts.push(`
                <div class='ladder-row current'>
                    <div class='ladder-tag'>NOW</div>
                    <div class='ladder-price'>${current ? `$${formatPrice(current)}` : '—'}</div>
                    <div class='ladder-meta'>current</div>
                    <div class='ladder-diff'></div>
                </div>
            `);

            // Supports: highest first (S1 above S2)
            const sortedS = [...supports].sort((a, b) => Number(b.price) - Number(a.price));
            sortedS.forEach((lvl, i) => {
                parts.push(rowFor(lvl, 'S', i + 1, 'support'));
            });

            container.innerHTML = parts.join('');
        }

        function renderChannels(channels) {
            const container = document.getElementById('channelList');
            if (!channels || !channels.length) {
                container.innerHTML = `<div class='range-card'><div class='range-top'><div class='range-name'>No channels</div><div class='range-width'>—</div></div></div>`;
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

        function renderProbeGraph(attempts) {
            const up = Number(attempts?.failed_up_today || 0);
            const down = Number(attempts?.failed_down_today || 0);
            const maxProbe = Math.max(up, down, 3, 1);
            const upPct = up > 0 ? Math.max(8, Math.round((up / maxProbe) * 100)) : 0;
            const downPct = down > 0 ? Math.max(8, Math.round((down / maxProbe) * 100)) : 0;
            const blockedUp = !!attempts?.rule_of_three_block_up;
            const blockedDown = !!attempts?.rule_of_three_block_down;
            const ruleState = blockedUp || blockedDown ? 'active' : 'inactive';
            const container = document.getElementById('probeGraphPanel');
            if (!container) return;
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
            `;
        }

        function renderAttempts(attempts) {
            const container = document.getElementById('attemptList');
            const up = Number(attempts?.failed_up_today || 0);
            const down = Number(attempts?.failed_down_today || 0);
            const blockedUp = !!attempts?.rule_of_three_block_up;
            const blockedDown = !!attempts?.rule_of_three_block_down;
            container.innerHTML = `
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

        function updateFocusNarrative(symbol, data, indicatorSummary) {
            // v0.8.0 hero structure: focusHeadline is the small "FOCUS SYMBOL"
            // eyebrow above the ticker — leave it static. focusSymbolText
            // shows the ticker. focusNarrative is the muted one-liner below.
            const symbolText = document.getElementById('focusSymbolText');
            const narrative = document.getElementById('focusNarrative');
            if (symbolText) symbolText.textContent = symbol;

            // v0.10.0: structural framing only. Describe what's on the
            // chart ("aligned", "leaning", "probes"), never claim what
            // will happen next.
            const bias = data?.probabilities?.bias || 'neutral';
            const alignment = data?.probabilities?.confidence || 'low';
            const upAttempts = Number(data?.attempts?.failed_up_today || 0);
            const downAttempts = Number(data?.attempts?.failed_down_today || 0);
            const parts = [];
            if (indicatorSummary) {
                const up = Number(indicatorSummary.up || 0);
                const down = Number(indicatorSummary.down || 0);
                const neutral = Number(indicatorSummary.neutral || 0);
                const total = up + down + neutral;
                const winning = Math.max(up, down);
                if (total > 0) parts.push(`${winning} of ${total} indicators aligned`);
            }
            if (upAttempts) parts.push(`${upAttempts} failed upside ${upAttempts === 1 ? 'probe' : 'probes'}`);
            if (downAttempts) parts.push(`${downAttempts} failed downside ${downAttempts === 1 ? 'probe' : 'probes'}`);
            if (!parts.length) {
                const biasLabel = bias === 'neutral' ? 'balanced structure' : `${bias}-leaning structure`;
                parts.push(`${biasLabel} · ${alignment} alignment`);
            }
            if (narrative) narrative.textContent = parts.join(' · ');
        }

        
        let tvChartInstance = null;
        let candlestickSeries = null;
        let r1Line = null;
        let r2Line = null;
        let s1Line = null;
        let s2Line = null;
        let ma20Series = null;

        function renderTradingChart(data, symbol) {
            const container = document.getElementById('tvChartContainer');
            if (!container) return;
            if (!data || !data.history || !data.history.length) {
                document.getElementById('chartMeta').textContent = 'No chart data available';
                return;
            }

            document.getElementById('chartMeta').textContent = `${symbol} \u00B7 ${data.interval} \u00B7 ${data.history.length} bars`;

            if (!tvChartInstance) {
                tvChartInstance = LightweightCharts.createChart(container, {
                    layout: {
                        background: { type: 'solid', color: 'transparent' },
                        textColor: '#96a9c4',
                    },
                    grid: {
                        vertLines: { color: 'rgba(148, 163, 184, 0.05)' },
                        horzLines: { color: 'rgba(148, 163, 184, 0.05)' },
                    },
                    crosshair: {
                        mode: LightweightCharts.CrosshairMode.Normal,
                    },
                    rightPriceScale: {
                        borderColor: 'rgba(148, 163, 184, 0.15)',
                    },
                    timeScale: {
                        borderColor: 'rgba(148, 163, 184, 0.15)',
                        timeVisible: true,
                        secondsVisible: false,
                    },
                });
                candlestickSeries = tvChartInstance.addCandlestickSeries({
                    upColor: '#22c55e',
                    downColor: '#f87171',
                    borderVisible: false,
                    wickUpColor: '#22c55e',
                    wickDownColor: '#f87171',
                });
                ma20Series = tvChartInstance.addLineSeries({
                    color: '#60a5fa',
                    lineWidth: 1,
                    priceScaleId: 'right',
                    title: 'MA(20)'
                });
            }

            // Format history
            const isDaily = data.interval === '1day';
            const parseTime = (timeStr) => {
                const d = new Date(timeStr);
                if (isNaN(d.getTime())) return timeStr;
                if (isDaily) {
                    return d.toISOString().split('T')[0];
                }
                return d.getTime() / 1000;
            };

            const formattedData = data.history.map(row => {
                return {
                    time: parseTime(row.time),
                    open: row.open,
                    high: row.high,
                    low: row.low,
                    close: row.close
                };
            }).sort((a, b) => a.time - b.time);

            
            // Unique data
            const uniqueData = Array.from(new Map(formattedData.map(item => [item.time, item])).values());
            candlestickSeries.setData(uniqueData);



            // Add MA data
            const maData = data.history.map(row => {
                let time = row.time;
                if (typeof time === 'string') {
                    const d = new Date(time);
                    if (!isNaN(d.getTime())) time = d.getTime() / 1000;
                }
                return { time: parseTime(row.time), value: row.ma20 };
            }).filter(d => d.value !== null).sort((a, b) => a.time - b.time);
            const uniqueMaData = Array.from(new Map(maData.map(item => [item.time, item])).values());
            ma20Series.setData(uniqueMaData);

            

            if (r1Line) candlestickSeries.removePriceLine(r1Line);
            if (r2Line) candlestickSeries.removePriceLine(r2Line);
            if (s1Line) candlestickSeries.removePriceLine(s1Line);
            if (s2Line) candlestickSeries.removePriceLine(s2Line);

            const drawLine = (price, color, title) => {
                if (!price) return null;
                return candlestickSeries.createPriceLine({
                    price: price,
                    color: color,
                    lineWidth: 1,
                    lineStyle: 2, // Dashed
                    axisLabelVisible: true,
                    title: title,
                });
            };

            // Two colors at two opacities. Was four (red, amber, green, emerald)
            // for what's conceptually two things in two strengths. Strong
            // (R1/S1, the nearer level) gets full color; weak (R2/S2, the
            // outer level) gets muted opacity so the eye reads "two of the
            // same thing" rather than four competing signals.
            if (data.resistance_1) r1Line = drawLine(data.resistance_1.price, 'rgba(248,113,113,0.95)', 'R1');
            if (data.resistance_2) r2Line = drawLine(data.resistance_2.price, 'rgba(248,113,113,0.55)', 'R2');
            if (data.support_1)    s1Line = drawLine(data.support_1.price,    'rgba(34,197,94,0.95)',  'S1');
            if (data.support_2)    s2Line = drawLine(data.support_2.price,    'rgba(34,197,94,0.55)',  'S2');

            tvChartInstance.timeScale().fitContent();

            tvChartInstance.subscribeCrosshairMove((param) => {
                const focusPriceBox = document.getElementById('focusPriceBox');
                const focusTimeBox = document.getElementById('focusTimeBox');
                const focusTimezone = document.getElementById('quoteTimezone');
                
                if (param.point === undefined || !param.time || param.point.x < 0 || param.point.x > container.clientWidth || param.point.y < 0 || param.point.y > container.clientHeight) {
                    focusPriceBox.textContent = `$${formatPrice(data.focus_price ?? data.current_price)}`;
                    focusTimeBox.textContent = data.quote_time_eastern ? `${data.quote_time_eastern} ${data.quote_timezone || 'ET'}` : 'Time unavailable';
                    return;
                }
                
                const price = param.seriesData.get(candlestickSeries);
                if (price) {
                    focusPriceBox.textContent = `$${Number(price.close).toFixed(2)}`;
                    
                    let timeStr = param.time;
                    if (typeof param.time === 'number') {
                        const d = new Date(param.time * 1000);
                        timeStr = d.toLocaleString('en-US', { timeZone: 'America/New_York', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', timeZoneName: 'short' });
                    } else if (typeof param.time === 'object' && param.time.year) {
                        timeStr = `${param.time.year}-${String(param.time.month).padStart(2, '0')}-${String(param.time.day).padStart(2, '0')}`;
                    } else if (typeof param.time === 'string') {
                        timeStr = param.time;
                    }
                    focusTimeBox.textContent = timeStr;
                }
            });

        }

        function updateFocusPanel(data, indicatorSummary = null, symbol = 'TSLA') {
            // Hero stat
            document.getElementById('focusPriceBox').textContent = `$${formatPrice(data.focus_price ?? data.current_price)}`;
            document.getElementById('focusTimeBox').textContent = data.quote_time_eastern ? `${data.quote_time_eastern} ${data.quote_timezone || 'ET'}` : '—';

            // Various meta strings (some now write to hidden compat shims)
            const sourceMetaEl = document.getElementById('sourceMeta');
            if (sourceMetaEl) sourceMetaEl.textContent = formatSourceMeta(data) || '—';
            const quoteTzEl = document.getElementById('quoteTimezone');
            if (quoteTzEl) quoteTzEl.textContent = data.quote_timezone || 'ET';
            const analysisMetaEl = document.getElementById('analysisMeta');
            if (analysisMetaEl) analysisMetaEl.textContent = `${data.interval || '--'} · ${data.periods || '--'} bars${data.live_status === 'stale_fallback' ? ' · degraded mode' : ''}`;

            updateStatusChip(data);
            updateTopbarStatus(data);

            // Hidden shims keep legacy code paths alive
            const upEl = document.getElementById('upProbability');
            const downEl = document.getElementById('downProbability');
            const upSubEl = document.getElementById('upProbabilitySub');
            const downSubEl = document.getElementById('downProbabilitySub');
            if (upEl)   upEl.textContent = formatPercent(data?.probabilities?.up);
            if (downEl) downEl.textContent = formatPercent(data?.probabilities?.down);
            if (upSubEl)   upSubEl.textContent = `Lean ${data?.probabilities?.bias || 'neutral'}`;
            if (downSubEl) downSubEl.textContent = `Alignment ${data?.probabilities?.confidence || 'low'}`;

            // v0.8.0: microcharts strip below the radar
            updateMicrocharts(data);

            renderChannels(data?.channels || []);
            renderLevelLadder(data);
            renderProbeGraph(data?.attempts || {});
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
            updateFocusNarrative(symbol, data, indicatorSummary);
        }

        function setFocusNode(symbol, data, tolerance, verdict) {
            const focus = document.getElementById('focus');
            const probs = data?.probabilities || {};
            const bias = probs.bias || 'neutral';
            // v0.12.0 — same colorblind-safe glyph set as indicator nodes.
            const directionGlyph = bias === 'up' ? '▲' : (bias === 'down' ? '▼' : '▪');
            // v0.10.0: this is the focus node's directional pressure score.
            // It's NOT a probability of breakout — backtests showed the
            // signal is anti-predictive at this timeframe. It's a
            // structural snapshot: how lopsided is the current pivot /
            // level / volume picture, scored 0-100.
            const dominantPct = Math.max(Number(probs.up || 0), Number(probs.down || 0));
            const dominantText = Number.isFinite(dominantPct) && dominantPct > 0
                ? `${dominantPct.toFixed(1)}`
                : '—';
            focus.className = `focus-node ${bias}`;

            // v0.11.x — graded fill on the focus node, matching the indicator
            // node treatment but with a punchier alpha cap (0.85 vs 0.6) so the
            // centerpiece still visually leads when leaning strongly. Same
            // power-curve so weak leans read quiet on both surfaces.
            const t = Math.max(0, Math.min(1, dominantPct / 100));
            const alpha = Math.pow(t, 1.3) * 0.85;
            let centerColor = null;
            if (bias === 'up') centerColor = `rgba(34, 197, 94, ${alpha.toFixed(3)})`;
            else if (bias === 'down') centerColor = `rgba(248, 113, 113, ${alpha.toFixed(3)})`;
            if (centerColor) {
                focus.style.background = `radial-gradient(circle at 50% 50%, ${centerColor} 0%, rgba(5, 12, 21, 0.92) 80%)`;
            } else {
                focus.style.background = '';
            }

            // verdict is a small "N/M ↑" line that summarizes the indicator
            // basket disposition (set later when the correlation fetch finishes).
            const verdictHtml = verdict
                ? `<div class='focus-verdict'>${verdict}</div>`
                : `<div class='focus-verdict'>&nbsp;</div>`;
            focus.innerHTML = `
                <div class='focus-symbol'>${symbol}</div>
                <div class='focus-price'>$${formatPrice(data?.current_price)}</div>
                <div class='focus-direction' aria-hidden='true'>${directionGlyph} ${dominantText}</div>
                ${verdictHtml}
            `;
        }

        // Build the "9/12 ↑" verdict line shown inside the focus node.
        function formatBasketVerdict(summary) {
            if (!summary) return '';
            const up = Number(summary.up || 0);
            const down = Number(summary.down || 0);
            const neutral = Number(summary.neutral || 0);
            const total = up + down + neutral;
            if (!total) return '';
            const winning = Math.max(up, down);
            const arrow = up >= down ? '↑' : '↓';
            return `${winning}/${total} ${arrow}`;
        }

        function drawOrUpdateLine(lineId, x, y, lineColor, isInverse) {
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
            // Confirmed-signal lines render thicker so the eye lands on them
            // first; neutral lines stay 1px. Inverse-confirming relations
            // get a dashed stroke so the encoding reads even at a glance.
            const isNeutral = lineColor === '#64748b';
            line.setAttribute('stroke-width', isNeutral ? '1' : '2.5');
            if (isInverse && !isNeutral) line.setAttribute('stroke-dasharray', '5,4');
            else if (isNeutral) line.setAttribute('stroke-dasharray', '4,4');
            else line.removeAttribute('stroke-dasharray');
        }

        // Draw labeled concentric rings inside the radar SVG. The rings sit
        // at strength thresholds (0.5 / 0.7 / 0.9) computed against the same
        // min/max radius the indicators use, so a node sitting on the 0.7
        // ring really does have ~0.7 correlation with focus. Cleared and
        // redrawn on every layout pass.
        function drawRadarRings(center, minR, maxR) {
            const lines = document.getElementById('lines');
            if (!lines) return;
            // Remove any rings/labels from a prior render
            lines.querySelectorAll('.radar-ring, .radar-ring-label').forEach(n => n.remove());
            const SVG = 'http://www.w3.org/2000/svg';
            const thresholds = [
                { score: 0.9, label: '0.9' },
                { score: 0.7, label: '0.7' },
                { score: 0.5, label: '0.5' },
            ];
            for (const t of thresholds) {
                const r = maxR - (maxR - minR) * t.score;
                const ring = document.createElementNS(SVG, 'circle');
                ring.setAttribute('class', 'radar-ring');
                ring.setAttribute('cx', String(center));
                ring.setAttribute('cy', String(center));
                ring.setAttribute('r', String(r));
                lines.appendChild(ring);

                const label = document.createElementNS(SVG, 'text');
                label.setAttribute('class', 'radar-ring-label');
                label.setAttribute('x', String(center));
                label.setAttribute('y', String(center - r - 4));
                label.textContent = t.label;
                lines.appendChild(label);
            }
        }

        // Map a normalized strength in [0, 1] to a pixel radius.
        // Higher strength = closer to the focus node. Used by both the
        // correlation-score fallback (first paint, before per-ticker fetches
        // resolve) and the breakout-strength repositioning (after fetch).
        function radiusForStrength(strength, minR, maxR) {
            const fallback = 0.4;  // a touch outside the 0.5 ring
            let s = (strength === null || strength === undefined || Number.isNaN(Number(strength)))
                ? fallback
                : Math.abs(Number(strength));
            // Clamp so even a perfect 1.0 doesn't visually merge into the focus node
            s = Math.max(0, Math.min(0.95, s));
            return maxR - (maxR - minR) * s;
        }
        // Backwards-compatible alias for the correlation-score code path.
        function radiusForScore(score, minR, maxR) {
            return radiusForStrength(score, minR, maxR);
        }
        // Convert per-ticker probability data into a [0, 1] strength value.
        // We treat decisiveness — max(up, down) / 100 — as the encoding:
        // an indicator screaming up at 90% is just as "strong" a signal as
        // one screaming down at 90%. Confirmation vs. opposition is encoded
        // separately by the connecting line color, not the radius.
        function breakoutStrength(data) {
            const up = Number(data?.probabilities?.up || 0);
            const down = Number(data?.probabilities?.down || 0);
            return Math.max(up, down) / 100;
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
            // v0.12.0 — default ticker priority: explicit arg > input value >
            // last-used ticker from localStorage > SPY (universal first-run
            // default — broad market index, every user understands it).
            // Co-Claude UI review flagged TSLA as too brand-specific for a
            // cold-start impression; SPY's basket also reads as more
            // structurally informative for someone seeing the radar concept
            // for the first time.
            let lastTicker = null;
            try { lastTicker = localStorage.getItem('htLastTicker'); } catch (e) {}
            const ticker = (newTicker
                || document.getElementById('ticker').value
                || lastTicker
                || 'SPY').toUpperCase();
            if (!preserveInput) document.getElementById('ticker').value = ticker;
            try { localStorage.setItem('htLastTicker', ticker); } catch (e) {}
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
                renderTradingChart(currentFocus, ticker);
                // v0.11.x — drop the showBanner(formatSourceMeta(...)) call.
                // The "Source MASSIVE · Status LIVE" line is now rendered by
                // updateStatusChip() into the topbar status pill (a system
                // affordance), so painting the same info into the page-body
                // banner duplicates it and re-introduces the orange band we
                // explicitly retired in v0.8.0.
            } catch (e) {
                if (requestId !== dashboardRequestSeq) return;
                document.getElementById('focus').className = 'focus-node neutral';
                document.getElementById('focus').innerHTML = `<div class='focus-symbol'>${ticker}</div><div class='focus-price'>ERR</div><div class='focus-bias'>Market data unavailable</div>`;
                showBanner(`Market data error: ${e?.error || e?.message || 'Unknown error'}`, true);
                return;
            }
            try {
                const corrRes = await fetch(`correlate.php?ticker=${encodeURIComponent(ticker)}&t=${Date.now()}`, { cache: 'no-store' });
                const corrPayload = await corrRes.json();
                if (requestId !== dashboardRequestSeq) return;
                const rawIndicators = Array.isArray(corrPayload) ? corrPayload : (Array.isArray(corrPayload.indicators) ? corrPayload.indicators : []);
                const indicators = rawIndicators.map(ind => {
                    const rawScore = ind?.score ?? ind?.correlation ?? ind?.coefficient;
                    let score = null;
                    if (rawScore !== undefined && rawScore !== null) {
                        const parsed = Number(rawScore);
                        if (!Number.isNaN(parsed)) score = parsed;
                    }
                    return {
                        symbol: String(ind?.symbol || '').toUpperCase().trim(),
                        relation: String(ind?.relation || ind?.relationship || ind?.sign || 'positive').toLowerCase() === 'negative' ? 'negative' : 'positive',
                        score,
                    };
                }).filter(ind => ind.symbol);
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
                // ---- In-place radar refresh -----------------------------
                // Don't wipe nodes/lines up front — the user reads a blanked
                // radar as "data lost" rather than "data refreshing". Instead
                // reposition existing nodes (so geometry follows new scores)
                // and let each indicator's previous values stay rendered until
                // its replacement fetch resolves. After Promise.all, prune
                // any nodes whose symbol fell out of the new basket.
                const clock = document.getElementById('clock');
                const clockRect = clock.getBoundingClientRect();
                const smallWindow = window.innerWidth <= 720;
                const mediumWindow = window.innerWidth <= 980;

                // Smaller indicators (v0.8.1 circles ~48–64px diameter) so
                // we recover usable radius. The min ring sits closer to the
                // focus, the outer ring sits closer to the stage edge —
                // strong-vs-weak signal separation reads with more clarity.
                const indicatorHalf = smallWindow ? 24 : (mediumWindow ? 28 : 32);
                const focusHalf     = smallWindow ? 64 : (mediumWindow ? 72 : 85);
                const padding       = smallWindow ? 12 : 16;
                const stageRadius   = clockRect.width / 2;
                const center        = stageRadius;
                const minDist       = focusHalf + indicatorHalf + padding;
                const maxDist       = Math.max(minDist + 30, stageRadius - indicatorHalf - padding);

                drawRadarRings(center, minDist, maxDist);

                const sortedIndicators = [...indicators].sort((a, b) => {
                    const aScore = a.score === null ? -1 : Math.abs(a.score);
                    const bScore = b.score === null ? -1 : Math.abs(b.score);
                    return aScore - bScore;
                });

                // Track which symbols are in the current basket so we can prune
                // nodes that fell out at the end of the refresh.
                const newBasketSymbols = new Set(sortedIndicators.map(o => o.symbol));

                const indicatorStates = [];
                const promises = sortedIndicators.map(async (indObj, i) => {
                    const angle = (i / Math.max(sortedIndicators.length, 1)) * Math.PI * 2;
                    const r = radiusForScore(indObj.score, minDist, maxDist);
                    const x = Math.cos(angle) * r;
                    const y = Math.sin(angle) * r;
                    const isInverse = indObj.relation === 'negative';

                    // Reuse the existing node if we already have one for this
                    // symbol, so its prior price/bias stays on screen until
                    // the new fetch returns. Only first-time symbols get the
                    // "—" placeholder; everything else is a continuous update.
                    let el = clock.querySelector(`.indicator-node[data-symbol="${CSS.escape(indObj.symbol)}"]`);
                    const isNew = !el;
                    if (isNew) {
                        el = document.createElement('button');
                        el.type = 'button';
                        el.dataset.symbol = indObj.symbol;
                        el.onclick = () => updateDashboard(indObj.symbol);
                        // First render: ticker + score (or — if no score yet).
                        // Price moves to the hover tooltip.
                        const scoreLabel = indObj.score === null
                            ? `<div class='mini-bias'>—</div>`
                            : `<div class='mini-bias'>${(isInverse ? '−' : '')}${Math.abs(indObj.score).toFixed(2)}</div>`;
                        el.innerHTML = `<div class='ticker'>${indObj.symbol}</div>${scoreLabel}`;
                        // Native title acts as the hover tooltip until per-fetch
                        // data lands; gets updated below with full info.
                        el.title = `${indObj.symbol}${indObj.score !== null ? ` · corr ${(isInverse ? '−' : '')}${Math.abs(indObj.score).toFixed(2)}` : ''}`;
                        clock.appendChild(el);
                    }
                    // Keep the inverse class fresh and reposition. We don't
                    // overwrite the bias color class here — that stays at
                    // whatever the previous fetch set it to until the new
                    // fetch lands a fresh bias.
                    el.classList.toggle('inverse', isInverse);
                    el.style.left = `calc(50% + ${x}px)`;
                    el.style.top = `calc(50% + ${y}px)`;

                    try {
                        const data = await fetchTickerData(indObj.symbol, period, lookback);
                        if (requestId !== dashboardRequestSeq) return;
                        const lineColor = computeLineColor(data, indObj.relation, tolerance);
                        const probs = data?.probabilities || {};
                        const bias = probs.bias || 'neutral';
                        el.className = `indicator-node ${bias}${isInverse ? ' inverse' : ''}`;

                        // Re-position based on breakout strength now that we
                        // have it. The CSS transition makes this read as the
                        // node "swimming" inward as its signal strengthens.
                        const strength = breakoutStrength(data);
                        const newR = radiusForStrength(strength, minDist, maxDist);
                        const newX = Math.cos(angle) * newR;
                        const newY = Math.sin(angle) * newR;
                        el.style.left = `calc(50% + ${newX}px)`;
                        el.style.top  = `calc(50% + ${newY}px)`;

                        // v0.12.0 — colorblind-safe direction glyph. Triangles
                        // ▲▼ are shape-distinct (a deuteranopic user can read
                        // direction even if green/red collapse to the same
                        // hue). Falls back to ▪ for neutral.
                        const arrow = bias === 'up' ? '▲' : bias === 'down' ? '▼' : '▪';
                        // Inside the circle: ticker + dominant breakout % only.
                        // Price, full bias, correlation move to the title tooltip
                        // because circles don't have room for that much content.
                        const dominant = Math.max(Number(probs.up || 0), Number(probs.down || 0));

                        // v0.11.x — graded fill. Tint the circle green when
                        // up-leaning, red when down-leaning, with alpha scaling
                        // by dominant %. A 95% reads strong; a 52% reads barely
                        // tinted — direction is visible at a glance, magnitude
                        // is in the saturation. Power-curve (^1.3) keeps the
                        // low-confidence end visually quiet so a faint signal
                        // doesn't shout for attention.
                        const t = Math.max(0, Math.min(1, dominant / 100));
                        const alpha = Math.pow(t, 1.3) * 0.6;
                        let centerColor = null;
                        if (bias === 'up') centerColor = `rgba(34, 197, 94, ${alpha.toFixed(3)})`;
                        else if (bias === 'down') centerColor = `rgba(248, 113, 113, ${alpha.toFixed(3)})`;
                        if (centerColor) {
                            el.style.background = `radial-gradient(circle at 50% 50%, ${centerColor} 0%, rgba(5, 12, 21, 0.92) 78%)`;
                        } else {
                            el.style.background = '';
                        }

                        el.innerHTML = `<div class='ticker'>${indObj.symbol}</div><div class='mini-bias'>${dominant.toFixed(0)}${arrow}</div>`;
                        const corrText = indObj.score !== null
                            ? ` · corr ${(isInverse ? '−' : '')}${Math.abs(indObj.score).toFixed(2)}`
                            : '';
                        el.title = `${indObj.symbol} · $${formatPrice(data.current_price)} · ${bias} bias · up ${formatPercent(probs.up)} · down ${formatPercent(probs.down)}${corrText}`;
                        drawOrUpdateLine(`line-${indObj.symbol}`, newX, newY, lineColor, isInverse);
                        indicatorStates.push({ symbol: indObj.symbol, relation: indObj.relation, data });
                    } catch (err) {
                        indicatorStates.push({ symbol: indObj.symbol, relation: indObj.relation, data: null, error: err });
                        // Keep the prior node text intact — failing one fetch
                        // shouldn't blank a previously-good indicator.
                        if (isNew) {
                            el.className = `indicator-node neutral${isInverse ? ' inverse' : ''}`;
                            el.innerHTML = `<div class='ticker'>${indObj.symbol}</div><div class='price'>—</div><div class='mini-bias'>err</div>`;
                        }
                    }
                });
                await Promise.all(promises);
                if (requestId !== dashboardRequestSeq) return;
                // Prune nodes + lines for symbols that fell out of the basket.
                clock.querySelectorAll('.indicator-node').forEach((el) => {
                    const sym = el.dataset.symbol;
                    if (sym && !newBasketSymbols.has(sym)) {
                        el.remove();
                        const orphanLine = document.getElementById(`line-${sym}`);
                        if (orphanLine) orphanLine.remove();
                    }
                });
                if (requestId !== dashboardRequestSeq) return;
                const summary = summarizeRelationshipBias(indicatorStates, tolerance);
                // Refresh the focus node with the basket verdict ("9/12 ↑")
                // now that we know the indicator dispositions.
                setFocusNode(ticker, currentFocus, tolerance, formatBasketVerdict(summary));
                updateFocusPanel(currentFocus, summary, ticker);
                showDebug(`focus=${ticker} | corr=${indicators.length} | fallback=${usedFallback} | status=${corrStatus.status || 'unknown'}`);
            } catch (e) {
                if (requestId !== dashboardRequestSeq) return;
                showBanner('Correlation fetch failed.', true);
            }
        }

        attachTickerAutoRefresh();
        attachPengoTrigger();
        updateSubscriptionPanel();
        // Refresh subscription summary periodically so quota usage ticks up
        // visibly as the dashboard auto-refreshes.
        setInterval(updateSubscriptionPanel, 60000);

        // v0.10.0 — fetch /me.php and paint the Subscription panel inside
        // the Usage tab. Silently no-ops on unauthenticated responses (some
        // dev paths might not be logged in).
        async function updateSubscriptionPanel() {
            try {
                const res = await fetch('me.php', { cache: 'no-store' });
                if (!res.ok) return;
                const me = await res.json();
                if (!me || me.error) return;

                const planEl = document.getElementById('subPlanName');
                const pillEl = document.getElementById('subPlanPill');
                const callsUsedEl = document.getElementById('subCallsUsed');
                const callsFillEl = document.getElementById('subCallsFill');
                const tickersUsedEl = document.getElementById('subTickersUsed');
                const tickersFillEl = document.getElementById('subTickersFill');
                const metaEl = document.getElementById('subPanelMeta');
                const upgradeBtn = document.getElementById('subUpgradeBtn');

                if (planEl) planEl.textContent = me.plan_name || '—';
                if (pillEl) {
                    const status = String(me.subscription_status || 'none');
                    pillEl.className = `sub-panel-pill ${status}`;
                    pillEl.textContent = status === 'trialing'
                        ? 'Trial'
                        : status === 'active' ? 'Active'
                        : status === 'past_due' ? 'Past due'
                        : status === 'canceled' ? 'Canceled'
                        : status;
                }

                const calls = me.api_calls || {};
                const callsLimit = calls.limit;
                const callsUsed = Number(calls.used || 0);
                if (callsUsedEl) {
                    callsUsedEl.textContent = callsLimit == null
                        ? `${callsUsed.toLocaleString()} / unlimited`
                        : `${callsUsed.toLocaleString()} / ${Number(callsLimit).toLocaleString()}`;
                }
                if (callsFillEl) {
                    const pct = callsLimit ? Math.min(100, (callsUsed / callsLimit) * 100) : 4;
                    callsFillEl.style.width = `${Math.max(4, pct)}%`;
                    callsFillEl.className = `meter-fill ${pct >= 90 ? 'red' : pct >= 70 ? 'amber' : 'green'}`;
                }

                const tickers = me.tickers || {};
                const tickersLimit = tickers.limit;
                const tickersUsed = Number(tickers.used || 0);
                if (tickersUsedEl) {
                    tickersUsedEl.textContent = tickersLimit == null
                        ? `${tickersUsed} / unlimited`
                        : `${tickersUsed} / ${tickersLimit}`;
                }
                if (tickersFillEl) {
                    const pct = tickersLimit ? Math.min(100, (tickersUsed / tickersLimit) * 100) : 4;
                    tickersFillEl.style.width = `${Math.max(4, pct)}%`;
                    tickersFillEl.className = `meter-fill ${pct >= 90 ? 'red' : pct >= 70 ? 'amber' : 'green'}`;
                }

                if (metaEl) {
                    const parts = [];
                    if (me.subscription_status === 'trialing' && me.trial_end) {
                        const days = Math.max(0, Math.ceil((Number(me.trial_end) - Date.now() / 1000) / 86400));
                        parts.push(`Trial · ${days} day${days === 1 ? '' : 's'} left`);
                    } else if (me.current_period_end && me.subscription_status === 'active') {
                        const d = new Date(Number(me.current_period_end) * 1000);
                        parts.push(`Renews ${d.toLocaleDateString()}`);
                    }
                    if (me.price_monthly > 0) parts.push(`$${me.price_monthly}/mo`);
                    metaEl.textContent = parts.length ? parts.join(' · ') : 'Free tier';
                }

                if (upgradeBtn) {
                    // Hide upgrade button on Pro (no higher tier to upgrade to)
                    upgradeBtn.style.display = me.plan === 'pro' ? 'none' : '';
                }
            } catch (e) {
                // Silent — subscription panel is non-critical UI
            }
        }
    </script>
</body>
</html>
