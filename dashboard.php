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
    <title>HackTrader | v0.7.7</title>
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
        .bias-chip.stale { background: rgba(251,191,36,0.14); color: #fde68a; border-color: rgba(251,191,36,0.28); }
        .bias-chip.error { background: rgba(248,113,113,0.16); color: #fecaca; border-color: rgba(248,113,113,0.28); }
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
            background: linear-gradient(90deg, rgba(248,113,113,0.9

... [OUTPUT TRUNCATED - 70 chars omitted out of 50070 total] ...


inite(age) ? `${age}s old` : 'recent'} · ${source}`;
                return;
            }
            el.className = 'bias-chip up';
            el.textContent = `Live feed healthy · ${source}`;
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

            if (data.resistance_1) r1Line = drawLine(data.resistance_1.price, '#f87171', 'R1');
            if (data.resistance_2) r2Line = drawLine(data.resistance_2.price, '#fbbf24', 'R2');
            if (data.support_1) s1Line = drawLine(data.support_1.price, '#22c55e', 'S1');
            if (data.support_2) s2Line = drawLine(data.support_2.price, '#34d399', 'S2');

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
            document.getElementById('focusPriceBox').textContent = `$${formatPrice(data.focus_price ?? data.current_price)}`;
            document.getElementById('focusTimeBox').textContent = data.quote_time_eastern ? `${data.quote_time_eastern} ${data.quote_timezone || 'ET'}` : 'Time unavailable';
            document.getElementById('sourceMeta').textContent = formatSourceMeta(data) || 'Live source pending';
            document.getElementById('quoteTimezone').textContent = data.quote_timezone || 'ET';
            document.getElementById('analysisMeta').textContent = `${data.interval || '--'} · ${data.periods || '--'} bars${data.live_status === 'stale_fallback' ? ' · degraded mode' : ''}`;
            setLiveStatusChip(data);
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
                renderTradingChart(currentFocus, ticker);
                showBanner(formatSourceMeta(currentFocus), false);
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