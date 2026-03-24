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
    <title>HackTrader | v0.5.0 FUI</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@300;400;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #0a0a0a;
            --accent-blue: #00f3ff;
            --accent-amber: #ffb400;
            --accent-red: #ff3e3e;
            --accent-green: #27ae60;
            --panel-border: rgba(255, 255, 255, 0.12);
            --grid-color: rgba(255, 255, 255, 0.05);
        }
        body { 
            margin: 0; 
            font-family: 'Roboto Mono', monospace; 
            background: var(--bg-color); 
            color: #fff; 
            display: flex; 
            flex-direction: column; 
            height: 100vh; 
            overflow: hidden;
            background-image: linear-gradient(var(--grid-color) 1px, transparent 1px),
                              linear-gradient(90deg, var(--grid-color) 1px, transparent 1px);
            background-size: 40px 40px;
        }
        header { 
            background: #111; 
            padding: 15px; 
            display: flex; 
            gap: 15px; 
            align-items: center; 
            border-bottom: 1px solid var(--accent-blue);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        input, select, button { 
            background: #000; 
            border: 1px solid #444; 
            color: #fff; 
            padding: 8px; 
            font-family: inherit;
        }
        input[type='range'] {
            padding: 0;
            width: 140px;
            accent-color: var(--accent-blue);
            cursor: pointer;
        }
        .slider-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 220px;
        }
        .slider-label {
            color: var(--accent-amber);
            font-size: 12px;
            min-width: 84px;
        }
        .slider-value {
            color: var(--accent-blue);
            font-weight: 700;
            min-width: 28px;
            text-align: right;
        }
        button { cursor: pointer; border: 1px solid var(--accent-blue); color: var(--accent-blue); transition: 0.3s; }
        button:hover { background: var(--accent-blue); color: #000; }
        .clock-container { flex-grow: 1; display: flex; justify-content: center; align-items: center; position: relative; padding: 20px 24px 40px; box-sizing: border-box; }
        .clock-shell {
            position: relative;
            width: min(1160px, 100%);
            min-height: 720px;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .clock-face { position: relative; width: 440px; height: 440px; border: 1px dashed #333; border-radius: 50%; flex: 0 0 440px; box-shadow: inset 0 0 40px rgba(255,255,255,0.03), 0 0 30px rgba(0,243,255,0.05); }
        .clock-face::before,
        .clock-face::after {
            content: '';
            position: absolute;
            inset: 22px;
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 50%;
            pointer-events: none;
        }
        .clock-face::after {
            inset: 52px;
            border-style: dashed;
            border-color: rgba(255,255,255,0.06);
        }
        .center-ticker { 
            width: 132px; height: 132px; 
            border: 1px solid #666; 
            box-shadow: 0 0 18px rgba(255,255,255,0.08), inset 0 0 24px rgba(255,255,255,0.04);
            border-radius: 50%; 
            display: flex; flex-direction: column; 
            justify-content: center; align-items: center; 
            font-weight: 700; font-size: 11px; 
            position: absolute; top: 154px; left: 154px; 
            background: radial-gradient(circle at center, rgba(20,20,20,0.96) 0%, rgba(0,0,0,0.94) 68%, rgba(255,255,255,0.04) 100%);
            z-index: 10;
            overflow: hidden;
            text-align: center;
            letter-spacing: 0.08em;
        }
        .indicator { 
            width: 82px; height: 82px; 
            border: 1px solid #666; 
            border-radius: 50%; 
            position: absolute; display: flex; flex-direction: column; 
            justify-content: center; align-items: center; 
            font-size: 9px; background: rgba(0,0,0,0.8);
            text-align: center;
            backdrop-filter: blur(2px);
        }
        .indicator.green { border-color: var(--accent-blue); box-shadow: 0 0 10px var(--accent-blue); }
        .indicator.red { border-color: var(--accent-red); box-shadow: 0 0 10px var(--accent-red); }
        .center-ticker.green { border-color: var(--accent-blue); box-shadow: 0 0 20px var(--accent-blue), inset 0 0 25px rgba(0,243,255,0.08); }
        .center-ticker.red { border-color: var(--accent-red); box-shadow: 0 0 20px var(--accent-red), inset 0 0 25px rgba(255,62,62,0.08); }
        .focus-logo-badge {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            border: 1px solid rgba(255,255,255,0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 8px;
            background: radial-gradient(circle at center, rgba(255,255,255,0.09) 0%, rgba(255,255,255,0.02) 55%, rgba(0,0,0,0.2) 100%);
            box-shadow: 0 0 0 1px rgba(255,255,255,0.04), inset 0 0 18px rgba(255,255,255,0.03);
            overflow: hidden;
        }
        .focus-logo-badge img {
            width: 30px;
            height: 30px;
            object-fit: contain;
            filter: grayscale(1) brightness(1.15) contrast(1.1);
            opacity: 0.95;
        }
        .focus-logo-fallback {
            font-size: 14px;
            color: var(--accent-amber);
            letter-spacing: 0.16em;
            padding-left: 0.16em;
        }
        .focus-symbol {
            font-size: 15px;
            margin-bottom: 4px;
        }
        .focus-meta {
            font-size: 9px;
            color: #bdbdbd;
            margin-top: 4px;
        }
        .status-banner {
            position: fixed;
            top: 72px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.92);
            border: 1px solid var(--accent-amber);
            color: var(--accent-amber);
            padding: 8px 12px;
            font-size: 11px;
            z-index: 50;
            max-width: 80vw;
            text-align: center;
            display: none;
        }
        .status-banner.error {
            border-color: var(--accent-red);
            color: var(--accent-red);
        }
        .focus-panel {
            position: absolute;
            inset: 0;
            pointer-events: none;
        }
        .panel-title {
            color: var(--accent-blue);
            font-weight: 700;
            font-size: 12px;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        .focus-arc {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            width: 520px;
            display: flex;
            gap: 14px;
            justify-content: center;
            pointer-events: none;
        }
        .focus-arc.top { top: -18px; }
        .focus-arc.bottom { bottom: 168px; }
        .focus-stack {
            display: contents;
        }
        .price-box {
            width: 190px;
            border: 1px solid var(--panel-border);
            padding: 10px 14px;
            background: linear-gradient(180deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.015) 100%);
            clip-path: polygon(8% 0, 92% 0, 100% 50%, 92% 100%, 8% 100%, 0 50%);
            backdrop-filter: blur(4px);
            box-shadow: inset 0 0 20px rgba(255,255,255,0.03);
        }
        .price-box.focus {
            border-color: rgba(0,243,255,0.5);
            box-shadow: 0 0 18px rgba(0, 243, 255, 0.12), inset 0 0 20px rgba(255,255,255,0.03);
        }
        .price-box.support {
            border-color: rgba(39,174,96,0.42);
        }
        .price-box.resistance {
            border-color: rgba(255,180,0,0.42);
        }
        .price-label {
            font-size: 10px;
            color: #8f8f8f;
            text-transform: uppercase;
            letter-spacing: 0.16em;
            margin-bottom: 4px;
        }
        .price-value {
            font-size: 19px;
            font-weight: 700;
            color: #fff;
        }
        .price-diff {
            font-size: 10px;
            color: #bbb;
            margin-top: 4px;
        }
        .stats-grid {
            position: absolute;
            left: 50%;
            bottom: 12px;
            transform: translateX(-50%);
            width: 760px;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            pointer-events: none;
        }
        .stat-card {
            border: 1px solid #2f2f2f;
            padding: 12px 14px;
            background: linear-gradient(180deg, rgba(255,255,255,0.04) 0%, rgba(255,255,255,0.015) 100%);
            clip-path: polygon(4% 0, 96% 0, 100% 50%, 96% 100%, 4% 100%, 0 50%);
            box-shadow: inset 0 0 18px rgba(255,255,255,0.025);
        }
        .stat-card.wide {
            grid-column: auto;
        }
        .stat-label {
            font-size: 10px;
            color: #8f8f8f;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 6px;
        }
        .stat-value {
            font-size: 18px;
            font-weight: 700;
            color: #fff;
        }
        .stat-subtext {
            font-size: 11px;
            color: #b8b8b8;
            margin-top: 4px;
            line-height: 1.4;
        }
        @media (max-width: 1280px) {
            .clock-shell {
                width: min(1080px, 100%);
                min-height: 700px;
            }
            .focus-arc {
                width: 480px;
                gap: 12px;
            }
            .focus-arc.top { top: -28px; }
            .focus-arc.bottom { bottom: 176px; }
            .price-box {
                width: 176px;
                padding: 9px 12px;
            }
            .price-value {
                font-size: 17px;
            }
            .price-diff {
                font-size: 9px;
            }
            .stats-grid {
                width: 700px;
            }
        }
        @media (max-width: 1100px) {
            body {
                height: auto;
                overflow: auto;
            }
            .clock-shell {
                width: 100%;
                min-height: 920px;
            }
            .focus-arc,
            .stats-grid {
                width: min(92vw, 760px);
            }
            .focus-arc {
                flex-wrap: wrap;
            }
            .focus-arc.top { top: 0; }
            .focus-arc.bottom { bottom: 180px; }
            .price-box {
                width: min(320px, 42vw);
                padding: 10px 14px;
            }
            .price-value {
                font-size: 19px;
            }
            .price-diff {
                font-size: 10px;
            }
        }
    </style>
</head>
<body onload='syncToleranceValue(); updateDashboard()'>
    <?php
    $corrData = json_decode(file_get_contents('correlations.json'), true);
    $allTickers = array_keys($corrData);
    sort($allTickers);
    ?>
    <header>
        <input type='text' id='ticker' list='ticker-list' placeholder='TICKER' size='6'>
        <datalist id='ticker-list'>
            <?php foreach($allTickers as $t) echo "<option value='$t'>"; ?>
        </datalist>
        <select id='period'><option>1m</option><option>5m</option><option>1h</option><option>1d</option></select>
        <input type='number' id='lookback' value='100' placeholder='LOOKBACK' size='5'>
        <div class='slider-wrap'>
            <span class='slider-label'>TOLERANCE</span>
            <input type='range' id='tolerance' min='0' max='100' value='90' oninput='syncToleranceValue()'>
            <span class='slider-value' id='toleranceValue'>90</span>
        </div>
        <button onclick='updateDashboard()'>EXECUTE</button>
        <button onclick='resetDashboard()'>RESET</button>
    </header>
    <div id='statusBanner' class='status-banner'></div>
    <div class='clock-container'>
        <div class='clock-shell'>
            <div class='clock-face' id='clock'>
                <svg id='lines' style='position:absolute; top:0; left:0; width:100%; height:100%; pointer-events:none;'></svg>
                <div class='center-ticker' id='focus' onclick='resetDashboard()' style='cursor:pointer;'>INIT<br>SCAN</div>
            </div>
            <aside class='focus-panel'>
                <div class='focus-arc top'>
                    <div class='price-box resistance'>
                        <div class='price-label'>Resistance 2</div>
                        <div class='price-value' id='resistance2'>--</div>
                        <div class='price-diff' id='resistance2Diff'>Awaiting signal</div>
                    </div>
                    <div class='price-box resistance'>
                        <div class='price-label'>Resistance 1</div>
                        <div class='price-value' id='resistance1'>--</div>
                        <div class='price-diff' id='resistance1Diff'>Awaiting signal</div>
                    </div>
                </div>
                <div class='focus-arc bottom'>
                    <div class='price-box support'>
                        <div class='price-label'>Support 1</div>
                        <div class='price-value' id='support1'>--</div>
                        <div class='price-diff' id='support1Diff'>Awaiting signal</div>
                    </div>
                    <div class='price-box support'>
                        <div class='price-label'>Support 2</div>
                        <div class='price-value' id='support2'>--</div>
                        <div class='price-diff' id='support2Diff'>Awaiting signal</div>
                    </div>
                </div>
                <div class='stats-grid'>
                    <div class='stat-card'>
                        <div class='stat-label'>Day Volume</div>
                        <div class='stat-value' id='dayVolumeValue'>--</div>
                        <div class='stat-subtext' id='dayVolumeSubtext'>Waiting on market data</div>
                    </div>
                    <div class='stat-card wide'>
                        <div class='stat-label'>Indicator Bias</div>
                        <div class='stat-value' id='indicatorBiasValue'>--</div>
                        <div class='stat-subtext' id='indicatorBiasSubtext'>0 of 12 processed</div>
                    </div>
                    <div class='stat-card'>
                        <div class='stat-label'>Volume Ratio</div>
                        <div class='stat-value' id='dayVolumeRatio'>--</div>
                        <div class='stat-subtext' id='barVolumeSubtext'>Current bar vs expected bar pending</div>
                    </div>
                </div>
            </aside>
        </div>
    </div>
    <script>
        let refreshInterval = setInterval(updateDashboard, 30000);

        function syncToleranceValue() {
            const slider = document.getElementById('tolerance');
            const value = document.getElementById('toleranceValue');
            value.textContent = slider.value;
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

        function formatSourceMeta(data) {
            const parts = [];
            if (data.source) parts.push(`SOURCE: ${String(data.source).toUpperCase()}`);
            if (data.cache?.stale) parts.push(`STALE CACHE · ${data.cache.age_seconds}s OLD`);
            else if (data.cache?.hit) parts.push(`CACHE HIT · ${data.cache.age_seconds}s OLD`);
            if (data.fallback_reason && data.source === 'yfinance') parts.push('TWELVEDATA EXHAUSTED → YFINANCE FALLBACK');
            if (data.warning) parts.push(data.warning.toUpperCase());
            return parts.join(' | ');
        }

        function formatPrice(value) {
            const num = Number(value);
            if (!Number.isFinite(num)) return '--';
            return num.toFixed(2);
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
            if (!Number.isFinite(num)) return '--';
            return `${num.toFixed(2)}x`;
        }

        function getLogoUrl(symbol) {
            const map = {
                TSLA: 'https://logo.clearbit.com/tesla.com',
                AAPL: 'https://logo.clearbit.com/apple.com',
                NVDA: 'https://logo.clearbit.com/nvidia.com',
                AMZN: 'https://logo.clearbit.com/amazon.com',
                META: 'https://logo.clearbit.com/meta.com',
                GOOGL: 'https://logo.clearbit.com/google.com',
                GOOG: 'https://logo.clearbit.com/google.com',
                MSFT: 'https://logo.clearbit.com/microsoft.com',
                NFLX: 'https://logo.clearbit.com/netflix.com',
                AMD: 'https://logo.clearbit.com/amd.com'
            };
            return map[symbol] || null;
        }

        function buildFocusMarkup(symbol, data) {
            const logoUrl = getLogoUrl(symbol);
            const fallback = symbol.slice(0, 2);
            const logoHtml = logoUrl
                ? `<div class="focus-logo-badge"><img src="${logoUrl}" alt="${symbol} logo" onerror="this.parentElement.innerHTML='<div class=\'focus-logo-fallback\'>${fallback}</div>'"></div>`
                : `<div class="focus-logo-badge"><div class="focus-logo-fallback">${fallback}</div></div>`;
            return `${logoHtml}<div class="focus-symbol">${symbol}</div><div>$${formatPrice(data.current_price)}</div><div class="focus-meta"><span style='color: var(--accent-green)'>↑ ${data.probabilities.up}%</span> · <span style='color: var(--accent-red)'>↓ ${data.probabilities.down}%</span></div>`;
        }

        function setPriceBox(idPrefix, entry, kind) {
            const valueEl = document.getElementById(idPrefix);
            const diffEl = document.getElementById(`${idPrefix}Diff`);
            if (!entry) {
                valueEl.textContent = '--';
                diffEl.textContent = 'Not available';
                return;
            }
            valueEl.textContent = `$${formatPrice(entry.price)}`;
            const sign = kind === 'resistance' ? '+' : '-';
            diffEl.textContent = `${sign}$${formatPrice(entry.diff)} from focus`;
        }

        function updateFocusPanel(data, indicatorSummary = null) {
            const focusPriceBox = document.getElementById('focusPriceBox');
            const focusTimeBox = document.getElementById('focusTimeBox');
            const dayVolumeValue = document.getElementById('dayVolumeValue');
            const dayVolumeSubtext = document.getElementById('dayVolumeSubtext');
            const dayVolumeRatio = document.getElementById('dayVolumeRatio');
            const barVolumeSubtext = document.getElementById('barVolumeSubtext');
            const indicatorBiasValue = document.getElementById('indicatorBiasValue');
            const indicatorBiasSubtext = document.getElementById('indicatorBiasSubtext');

            if (focusPriceBox) {
                focusPriceBox.textContent = `$${formatPrice(data.focus_price ?? data.current_price)}`;
            }
            const timeText = data.quote_time_eastern ? `${data.quote_time_eastern} ${data.quote_timezone || 'ET'}` : 'Time unavailable';
            if (focusTimeBox) {
                focusTimeBox.textContent = timeText;
            }

            const upper = data.upper_resistances || [];
            const lower = data.lower_supports || [];
            setPriceBox('resistance1', upper[0], 'resistance');
            setPriceBox('resistance2', upper[1], 'resistance');
            setPriceBox('support1', lower[0], 'support');
            setPriceBox('support2', lower[1], 'support');

            const volume = data.volume || {};
            if (dayVolumeValue) dayVolumeValue.textContent = formatVolume(volume.current_day);
            if (dayVolumeSubtext) dayVolumeSubtext.textContent = `Expected ${formatVolume(volume.expected_day)} today`;
            if (dayVolumeRatio) dayVolumeRatio.textContent = formatRatio(volume.day_ratio);
            if (barVolumeSubtext) barVolumeSubtext.textContent = `Bar ${formatVolume(volume.current_bar)} vs exp ${formatVolume(volume.expected_bar)} (${formatRatio(volume.bar_ratio)})`;

            if (indicatorSummary) {
                const total = indicatorSummary.up + indicatorSummary.down + indicatorSummary.neutral;
                if (indicatorBiasValue) indicatorBiasValue.innerHTML = `<span style="color: var(--accent-green)">${indicatorSummary.up} ↑</span> / <span style="color: var(--accent-red)">${indicatorSummary.down} ↓</span>`;
                if (indicatorBiasSubtext) indicatorBiasSubtext.textContent = `${total} processed · ${indicatorSummary.neutral} neutral/inside tolerance`;
            }
        }

        function buildTickerMarkup(symbol, data) {
            return `${symbol}<br>$${formatPrice(data.current_price)}<br><span style='color: var(--accent-blue)'>+${data.probabilities.up}%</span> | <span style='color: var(--accent-red)'>-${data.probabilities.down}%</span>`;
        }

        function applyTickerState(el, data, tolerance) {
            el.classList.remove('green', 'red');
            if (data.probabilities.up > tolerance) el.classList.add('green');
            else if (data.probabilities.down > tolerance) el.classList.add('red');
        }

        function setTickerDisplay(el, symbol, data, tolerance) {
            el.className = el.id === 'focus' ? 'center-ticker' : 'indicator';
            if (!data || data.error || !data.probabilities) {
                const detail = data?.error ? String(data.error).slice(0, 24) : 'DATA ERR';
                el.innerHTML = `${symbol}<br>${detail}`;
                return;
            }

            el.innerHTML = el.id === 'focus' ? buildFocusMarkup(symbol, data) : buildTickerMarkup(symbol, data);
            applyTickerState(el, data, tolerance);
        }

        async function fetchTickerData(ticker, period, lookback) {
            const response = await fetch(`api.php?ticker=${ticker}&period=${period}&lookback=${lookback}&t=${Date.now()}`);
            const data = await response.json();
            if (!response.ok || data.error) {
                throw data;
            }
            return data;
        }
        
        function resetDashboard() {
            document.getElementById('ticker').value = 'TSLA';
            document.getElementById('period').value = '5m';
            document.getElementById('lookback').value = '100';
            document.getElementById('tolerance').value = '90';
            syncToleranceValue();
            updateDashboard();
        }

        function computeLineColor(data, relation, tolerance) {
            const upwardTrend = data.probabilities.up >= tolerance;
            const downwardTrend = data.probabilities.down >= tolerance;

            if (relation === 'positive') {
                if (upwardTrend) return '#27ae60';
                if (downwardTrend) return '#c0392b';
                return '#444444';
            }

            if (relation === 'negative') {
                if (downwardTrend) return '#27ae60';
                if (upwardTrend) return '#c0392b';
                return '#444444';
            }

            return '#444444';
        }

        function drawOrUpdateLine(lineId, x, y, lineColor) {
            const lines = document.getElementById('lines');
            let line = document.getElementById(lineId);
            if (!line) {
                line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
                line.setAttribute('id', lineId);
                line.setAttribute('x1', '200');
                line.setAttribute('y1', '200');
                line.setAttribute('x2', 200 + x);
                line.setAttribute('y2', 200 + y);
                lines.appendChild(line);
            }
            line.setAttribute('stroke', lineColor);
            line.setAttribute('stroke-width', lineColor === '#444444' ? '1' : '2');
            if (lineColor === '#444444') line.setAttribute('stroke-dasharray', '4,4');
            else line.removeAttribute('stroke-dasharray');
        }

        async function updateDashboard(newTicker = null) {
            const clock = document.getElementById('clock');
            if (newTicker) document.getElementById('ticker').value = newTicker;
            const ticker = document.getElementById('ticker').value.toUpperCase() || 'TSLA';
            const period = document.getElementById('period').value;
            const lookback = document.getElementById('lookback').value || 100;
            const tolerance = parseFloat(document.getElementById('tolerance').value || '90');

            clearInterval(refreshInterval);
            let ms = period === '1d' ? 3600000 : 30000;
            refreshInterval = setInterval(updateDashboard, ms);

            const focus = document.getElementById('focus');
            focus.className = 'center-ticker';
            focus.innerHTML = `<div class="focus-logo-badge"><div class="focus-logo-fallback">${ticker.slice(0, 2)}</div></div><div class="focus-symbol">${ticker}</div><div>SCANNING</div>`;
            showBanner('');

            let currentFocus = null;
            try {
                currentFocus = await fetchTickerData(ticker, period, lookback);
                setTickerDisplay(focus, ticker, currentFocus, tolerance);
                updateFocusPanel(currentFocus);
                showBanner(formatSourceMeta(currentFocus), false);
            } catch (e) {
                const detail = e?.details ? JSON.stringify(e.details) : (e?.error || 'Unknown error');
                focus.innerHTML = `${ticker}<br>ERR`;
                showBanner(`MARKET DATA ERROR: ${detail}`, true);
                return;
            }

            try {
                const corrRes = await fetch(`correlate.php?ticker=${ticker}`);
                const indicators = await corrRes.json();
                const activeSymbols = new Set(indicators.map(ind => ind.symbol));

                document.querySelectorAll('.indicator').forEach(el => {
                    if (!activeSymbols.has(el.dataset.symbol)) {
                        const lineId = `line-${el.dataset.symbol}`;
                        document.getElementById(lineId)?.remove();
                        el.remove();
                    }
                });

                indicators.forEach((indObj, i) => {
                    const ind = indObj.symbol;
                    const angle = (i / indicators.length) * 2 * Math.PI;
                    const x = Math.cos(angle) * 180;
                    const y = Math.sin(angle) * 180;
                    let el = document.querySelector(`.indicator[data-symbol="${ind}"]`);

                    if (!el) {
                        el = document.createElement('div');
                        el.className = 'indicator';
                        el.style.cursor = 'pointer';
                        el.setAttribute('title', ind);
                        el.dataset.symbol = ind;
                        el.onclick = () => updateDashboard(ind);
                        el.innerHTML = `${ind}<br>...`;
                        clock.appendChild(el);
                    }

                    el.style.left = `calc(50% + ${x}px - 40px)`;
                    el.style.top = `calc(50% + ${y}px - 40px)`;
                });

                const summary = { up: 0, down: 0, neutral: 0 };
                const promises = indicators.map(async (indObj, i) => {
                    const ind = indObj.symbol;
                    const relation = indObj.relation;
                    const angle = (i / indicators.length) * 2 * Math.PI;
                    const x = Math.cos(angle) * 180;
                    const y = Math.sin(angle) * 180;
                    const el = document.querySelector(`.indicator[data-symbol="${ind}"]`);
                    const lineId = `line-${ind}`;

                    try {
                        const data = await fetchTickerData(ind, period, lookback);
                        const nextHtml = buildTickerMarkup(ind, data);
                        const lineColor = computeLineColor(data, relation, tolerance);

                        el.innerHTML = nextHtml;
                        el.className = 'indicator';
                        applyTickerState(el, data, tolerance);
                        drawOrUpdateLine(lineId, x, y, lineColor);

                        if (data.probabilities.up >= tolerance) summary.up += 1;
                        else if (data.probabilities.down >= tolerance) summary.down += 1;
                        else summary.neutral += 1;
                    } catch (e) {
                        if (!el.innerHTML || el.innerHTML.includes('<br>...')) {
                            const detail = e?.error ? String(e.error).slice(0, 16) : 'DATA ERR';
                            el.innerHTML = `${ind}<br>${detail}`;
                        }
                    }
                });
                await Promise.all(promises);
                updateFocusPanel(currentFocus, summary);
            } catch (e) {
                console.error(e);
                showBanner('Correlation fetch failed.', true);
            }
        }
    </script>
    <footer style='position: fixed; bottom: 10px; width: 100%; text-align: center; font-size: 10px; color: #444;'>v0.5.0</footer>
</body>
</html>