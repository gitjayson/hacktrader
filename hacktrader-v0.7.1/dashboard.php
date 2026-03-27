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
    <title>HackTrader | v0.7.1 FUI</title>
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
        * {
            box-sizing: border-box;
        }
        html {
            min-height: 100%;
            background: var(--bg-color);
        }
        body { 
            margin: 0; 
            font-family: 'Roboto Mono', monospace; 
            background: var(--bg-color); 
            color: #fff; 
            display: flex; 
            flex-direction: column; 
            min-height: 100vh;
            min-height: 100dvh;
            overflow-x: hidden;
            overflow-y: auto;
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
            flex-wrap: wrap;
            justify-content: center;
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
        .clock-container { flex-grow: 1; display: flex; justify-content: center; align-items: flex-start; position: relative; padding: 20px 24px 72px; }
        .clock-shell {
            position: relative;
            width: min(1160px, 100%);
            min-height: 720px;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 120px 0 220px;
        }
        .clock-face { position: relative; width: min(440px, 72vw); aspect-ratio: 1 / 1; border: 1px dashed #333; border-radius: 50%; flex: 0 0 auto; box-shadow: inset 0 0 40px rgba(255,255,255,0.03), 0 0 30px rgba(0,243,255,0.05); }
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
            width: 30%; min-width: 108px; max-width: 132px; aspect-ratio: 1 / 1;
            border: 1px solid #666; 
            box-shadow: 0 0 18px rgba(255,255,255,0.08), inset 0 0 24px rgba(255,255,255,0.04);
            border-radius: 50%; 
            display: flex; flex-direction: column; 
            justify-content: center; align-items: center; 
            font-weight: 700; font-size: 11px; 
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
            background: radial-gradient(circle at center, rgba(20,20,20,0.96) 0%, rgba(0,0,0,0.94) 68%, rgba(255,255,255,0.04) 100%);
            z-index: 10;
            overflow: hidden;
            text-align: center;
            letter-spacing: 0.08em;
            padding: 12px;
        }
        .indicator { 
            width: 82px; min-width: 82px; height: 82px; 
            border: 1px solid #666; 
            border-radius: 50%; 
            position: absolute; display: flex; flex-direction: column; 
            justify-content: center; align-items: center; 
            font-size: 9px; background: rgba(0,0,0,0.8);
            text-align: center;
            -webkit-backdrop-filter: blur(2px);
            backdrop-filter: blur(2px);
            padding: 8px;
        }
        .indicator.green { border-color: var(--accent-blue); box-shadow: 0 0 10px var(--accent-blue); }
        .indicator.red { border-color: var(--accent-red); box-shadow: 0 0 10px var(--accent-red); }
        .center-ticker.green { border-color: var(--accent-blue); box-shadow: 0 0 20px var(--accent-blue), inset 0 0 25px rgba(0,243,255,0.08); }
        .center-ticker.red { border-color: var(--accent-red); box-shadow: 0 0 20px var(--accent-red), inset 0 0 25px rgba(255,62,62,0.08); }
        .focus-symbol {
            font-size: 26px;
            line-height: 1;
            margin-bottom: 8px;
            letter-spacing: 0.12em;
            color: var(--accent-blue);
            padding-left: 0.12em;
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
        .top-rail {
            position: absolute;
            top: 18px;
            left: 50%;
            transform: translateX(-50%);
            width: min(980px, 100%);
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 10px;
            pointer-events: none;
            z-index: 20;
        }
        .panel-title {
            color: var(--accent-blue);
            font-weight: 700;
            font-size: 12px;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        .side-rail {
            position: absolute;
            inset: 0;
            pointer-events: none;
            width: 100%;
        }
        .focus-stack {
            display: contents;
        }
        .price-box {
            position: absolute;
            width: 112px;
            border: 1px solid var(--panel-border);
            padding: 8px 10px;
            background: linear-gradient(180deg, rgba(255,255,255,0.06) 0%, rgba(255,255,255,0.018) 100%);
            -webkit-clip-path: polygon(8% 0, 92% 0, 100% 50%, 92% 100%, 8% 100%, 0 50%);
            clip-path: polygon(8% 0, 92% 0, 100% 50%, 92% 100%, 8% 100%, 0 50%);
            -webkit-backdrop-filter: blur(4px);
            backdrop-filter: blur(4px);
            box-shadow: inset 0 0 20px rgba(255,255,255,0.03);
            box-sizing: border-box;
            transform: translate(-50%, -50%);
        }
        .price-box.focus {
            border-color: rgba(0,243,255,0.58);
            box-shadow: 0 0 18px rgba(0, 243, 255, 0.16), inset 0 0 20px rgba(255,255,255,0.03);
        }
        .price-box.support {
            border-color: rgba(255,62,62,0.45);
        }
        .price-box.resistance {
            border-color: rgba(39,174,96,0.45);
        }
        .price-box.r2 { position: static; transform: none; width: 100%; }
        .price-box.r1 { position: static; transform: none; width: 100%; }
        .price-box.cp { position: static; transform: none; width: 100%; }
        .price-box.s1 { position: static; transform: none; width: 100%; }
        .price-box.s2 { position: static; transform: none; width: 100%; }
        .price-label {
            font-size: 9px;
            color: #8f8f8f;
            text-transform: uppercase;
            letter-spacing: 0.14em;
            margin-bottom: 3px;
        }
        .price-value {
            font-size: 16px;
            font-weight: 700;
            color: #fff;
        }
        .price-box.resistance .price-value {
            color: var(--accent-green);
        }
        .price-box.support .price-value {
            color: var(--accent-red);
        }
        .price-box.focus .price-value {
            color: var(--accent-blue);
        }
        .price-diff {
            font-size: 9px;
            color: #bbb;
            margin-top: 3px;
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
            -webkit-clip-path: polygon(4% 0, 96% 0, 100% 50%, 96% 100%, 4% 100%, 0 50%);
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
            .top-rail {
                width: min(920px, 100%);
                gap: 8px;
            }
            .price-box {
                width: 100%;
                padding: 7px 8px;
            }
            .price-value {
                font-size: 15px;
            }
            .price-diff {
                font-size: 8px;
            }
            .stats-grid {
                width: 700px;
            }
        }
        @media (max-width: 1100px) {
            .clock-container {
                padding: 20px 16px 84px;
            }
            .clock-shell {
                width: 100%;
                min-height: auto;
                padding: 0;
                display: grid;
                gap: 18px;
                justify-items: center;
            }
            .focus-panel {
                position: static;
                width: 100%;
                pointer-events: auto;
                display: contents;
            }
            .clock-face {
                width: min(440px, 88vw);
            }
            .stats-grid {
                position: static;
                transform: none;
                width: min(92vw, 760px);
                grid-template-columns: 1fr;
            }
            .top-rail {
                position: static;
                left: auto;
                transform: none;
                width: min(92vw, 760px);
                margin: 0 auto;
                grid-template-columns: 1fr;
                pointer-events: auto;
            }
            .side-rail {
                position: static;
                width: min(520px, 92vw);
                margin: 0 auto 18px;
                gap: 10px;
            }
            .price-box {
                position: static;
                width: 100%;
                transform: none;
                margin-bottom: 10px;
                padding: 10px 14px;
            }
            .price-value {
                font-size: 19px;
            }
            .price-diff {
                font-size: 10px;
            }
        }
        @media (max-width: 720px) {
            header {
                gap: 10px;
                padding: 12px;
                justify-content: stretch;
            }
            header > * {
                width: 100%;
            }
            input, select, button {
                width: 100%;
                min-height: 42px;
            }
            .slider-wrap {
                min-width: 0;
                width: 100%;
            }
            input[type='range'] {
                width: 100%;
            }
            .focus-symbol {
                font-size: 22px;
            }
            .center-ticker {
                font-size: 10px;
            }
            .indicator {
                width: 72px;
                min-width: 72px;
                height: 72px;
                font-size: 8px;
            }
            .status-banner {
                top: auto;
                bottom: 12px;
                width: calc(100vw - 24px);
                max-width: none;
            }
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
    <header>
        <input type='text' id='ticker' list='ticker-list' placeholder='TICKER' size='6'>
        <datalist id='ticker-list'>
            <?php foreach($allTickers as $t) echo "<option value='$t'>"; ?>
        </datalist>
        <button onclick='updateDashboard()'>EXECUTE</button>
        <select id='period'><option selected>5m</option><option>1m</option><option>1h</option><option>1d</option></select>
        <input type='number' id='lookback' value='100' placeholder='LOOKBACK' size='5'>
        <div class='slider-wrap'>
            <span class='slider-label'>TOLERANCE</span>
            <input type='range' id='tolerance' min='0' max='100' value='90' oninput='syncToleranceValue()'>
            <span class='slider-value' id='toleranceValue'>90</span>
        </div>
        <button onclick='resetDashboard()'>RESET</button>
        <button onclick='window.location.href="logout.php"'>LOGOUT</button>
    </header>
    <div id='statusBanner' class='status-banner'></div>
    <div class='clock-container'>
        <div class='clock-shell'>
            <div class='clock-face' id='clock'>
                <svg id='lines' style='position:absolute; top:0; left:0; width:100%; height:100%; pointer-events:none;'></svg>
                <div class='center-ticker' id='focus' onclick='resetDashboard()' style='cursor:pointer;'>INIT<br>SCAN</div>
            </div>
            <aside class='focus-panel'>
                <div class='top-rail'>
                    <div class='price-box resistance r2'>
                        <div class='price-label'>Resistance 2</div>
                        <div class='price-value' id='resistance2'>--</div>
                        <div class='price-diff' id='resistance2Diff'>Awaiting signal</div>
                    </div>
                    <div class='price-box resistance r1'>
                        <div class='price-label'>Resistance 1</div>
                        <div class='price-value' id='resistance1'>--</div>
                        <div class='price-diff' id='resistance1Diff'>Awaiting signal</div>
                    </div>
                    <div class='price-box focus cp'>
                        <div class='price-label'>Current Price</div>
                        <div class='price-value' id='focusPriceBox'>--</div>
                        <div class='price-diff' id='focusTimeBox'>Awaiting quote</div>
                    </div>
                    <div class='price-box support s1'>
                        <div class='price-label'>Support 1</div>
                        <div class='price-value' id='support1'>--</div>
                        <div class='price-diff' id='support1Diff'>Awaiting signal</div>
                    </div>
                    <div class='price-box support s2'>
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
        const logoManifest = <?php echo json_encode($logoManifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        let refreshInterval = setInterval(updateDashboard, 30000);
        let tickerInputDebounce = null;

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
            return logoManifest[symbol] || null;
        }

        function buildFocusMarkup(symbol, data) {
            return `<div class="focus-symbol">${symbol}</div><div style="font-size:18px; margin-bottom:4px;">$${formatPrice(data.current_price)}</div><div class="focus-meta"><span style='color: var(--accent-green)'>↑ ${data.probabilities.up}%</span> · <span style='color: var(--accent-red)'>↓ ${data.probabilities.down}%</span></div>`;
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
            if (dayVolumeSubtext) dayVolumeSubtext.textContent = `Avg day ${formatVolume(volume.expected_day)}`;
            if (dayVolumeRatio) dayVolumeRatio.textContent = formatRatio(volume.day_ratio);
            if (barVolumeSubtext) barVolumeSubtext.textContent = `Current bar ${formatVolume(volume.current_bar)} vs avg slot ${formatVolume(volume.expected_bar)} (${formatRatio(volume.bar_ratio)})`;

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
            updateDashboard('TSLA');
        }

        function attachTickerAutoRefresh() {
            const tickerEl = document.getElementById('ticker');
            if (!tickerEl) return;

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

        function summarizeRelationshipBias(indicatorStates, tolerance) {
            const summary = { green: 0, red: 0, neutral: 0 };
            (indicatorStates || []).forEach((item) => {
                if (!item || !item.data) {
                    summary.neutral += 1;
                    return;
                }
                const relation = item.relation || item.relationship || item.sign;
                const color = computeLineColor(item.data, relation, tolerance);
                if (color === '#27ae60') summary.green += 1;
                else if (color === '#c0392b') summary.red += 1;
                else summary.neutral += 1;
            });
            return summary;
        }

        function drawOrUpdateLine(lineId, x, y, lineColor) {
            const lines = document.getElementById('lines');
            const clock = document.getElementById('clock');
            const size = clock ? clock.getBoundingClientRect().width : 440;
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
            line.setAttribute('stroke-width', lineColor === '#444444' ? '1' : '2');
            if (lineColor === '#444444') line.setAttribute('stroke-dasharray', '4,4');
            else line.removeAttribute('stroke-dasharray');
        }

        let correlationPollTimer = null;
        let dashboardRequestSeq = 0;

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

        async function updateDashboard(newTicker = null, options = {}) {
            const clock = document.getElementById('clock');
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
            let ms = period === '1d' ? 3600000 : 30000;
            refreshInterval = setInterval(() => updateDashboard(null, { preserveInput: true, silentFocus: true }), ms);

            const focus = document.getElementById('focus');
            if (!silentFocus) {
                focus.className = 'center-ticker';
                focus.innerHTML = `<div class="focus-logo-badge"><div class="focus-logo-fallback">${ticker.slice(0, 2)}</div></div><div class="focus-symbol">${ticker}</div><div>SCANNING</div>`;
            }
            showBanner('');

            let currentFocus = null;
            try {
                currentFocus = await fetchTickerData(ticker, period, lookback);
                if (requestId !== dashboardRequestSeq) return;
                setTickerDisplay(focus, ticker, currentFocus, tolerance);
                updateFocusPanel(currentFocus);
                showBanner(formatSourceMeta(currentFocus), false);
            } catch (e) {
                if (requestId !== dashboardRequestSeq) return;
                const detail = e?.details ? JSON.stringify(e.details) : (e?.error || 'Unknown error');
                focus.innerHTML = `${ticker}<br>ERR`;
                showBanner(`MARKET DATA ERROR: ${detail}`, true);
                return;
            }

            try {
                const corrRes = await fetch(`correlate.php?ticker=${ticker}`);
                const corrPayload = await corrRes.json();
                if (requestId !== dashboardRequestSeq) return;
                const indicators = Array.isArray(corrPayload) ? corrPayload : (corrPayload.indicators || []);
                const corrStatus = Array.isArray(corrPayload) ? { status: 'ready' } : (corrPayload.status || { status: 'ready' });
                const usedFallback = !Array.isArray(corrPayload) && !!corrPayload.used_fallback;
                const activeSymbols = new Set(indicators.map(ind => ind.symbol));

                if (usedFallback && corrStatus.status === 'pending') {
                    showBanner(`Researching ${ticker} indicator basket… showing fallback set for now.`, false);
                    scheduleCorrelationRefresh(requestId, ticker, 4000);
                }

                document.querySelectorAll('.indicator').forEach(el => {
                    if (!activeSymbols.has(el.dataset.symbol)) {
                        const lineId = `line-${el.dataset.symbol}`;
                        document.getElementById(lineId)?.remove();
                        el.remove();
                    }
                });

                const clockRect = clock.getBoundingClientRect();
                const radius = Math.max(110, Math.min((clockRect.width / 2) - 46, 180));
                const indicatorHalf = window.innerWidth <= 720 ? 36 : 40;

                indicators.forEach((indObj, i) => {
                    const ind = indObj.symbol;
                    const angle = (i / Math.max(indicators.length, 1)) * 2 * Math.PI;
                    const x = Math.cos(angle) * radius;
                    const y = Math.sin(angle) * radius;
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

                    el.style.left = `calc(50% + ${x}px - ${indicatorHalf}px)`;
                    el.style.top = `calc(50% + ${y}px - ${indicatorHalf}px)`;
                });

                const indicatorStates = [];
                const promises = indicators.map(async (indObj, i) => {
                    const ind = indObj.symbol;
                    const relation = indObj.relation;
                    const angle = (i / Math.max(indicators.length, 1)) * 2 * Math.PI;
                    const x = Math.cos(angle) * radius;
                    const y = Math.sin(angle) * radius;
                    const el = document.querySelector(`.indicator[data-symbol="${ind}"]`);
                    const lineId = `line-${ind}`;

                    try {
                        const data = await fetchTickerData(ind, period, lookback);
                        if (requestId !== dashboardRequestSeq) return;
                        const nextHtml = buildTickerMarkup(ind, data);
                        const lineColor = computeLineColor(data, relation, tolerance);

                        el.innerHTML = nextHtml;
                        el.className = 'indicator';
                        applyTickerState(el, data, tolerance);
                        drawOrUpdateLine(lineId, x, y, lineColor);
                        indicatorStates.push({ symbol: ind, relation, data });
                    } catch (e) {
                        if (requestId !== dashboardRequestSeq) return;
                        indicatorStates.push({ symbol: ind, relation, data: null, error: e });
                        if (!el.innerHTML || el.innerHTML.includes('<br>...')) {
                            const detail = e?.error ? String(e.error).slice(0, 16) : 'DATA ERR';
                            el.innerHTML = `${ind}<br>${detail}`;
                        }
                    }
                });
                await Promise.all(promises);
                if (requestId !== dashboardRequestSeq) return;
                const relationshipSummary = summarizeRelationshipBias(indicatorStates, tolerance);
                updateFocusPanel(currentFocus, relationshipSummary);
            } catch (e) {
                if (requestId !== dashboardRequestSeq) return;
                console.error(e);
                showBanner('Correlation fetch failed.', true);
            }
        }

        attachTickerAutoRefresh();
    </script>
    <footer style='position: fixed; bottom: 10px; width: 100%; text-align: center; font-size: 10px; color: #444;'>v0.7.1</footer>
</body>
</html>