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
    <title>HackTrader | v0.4.9 FUI</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@300;400;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #0a0a0a;
            --accent-blue: #00f3ff;
            --accent-amber: #ffb400;
            --accent-red: #ff3e3e;
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
        .clock-container { flex-grow: 1; display: flex; justify-content: center; align-items: center; position: relative; gap: 28px; padding: 20px 24px 40px; box-sizing: border-box; }
        .clock-face { position: relative; width: 400px; height: 400px; border: 1px dashed #333; border-radius: 50%; flex: 0 0 400px; }
        .center-ticker { 
            width: 100px; height: 100px; 
            border: 2px solid #666; 
            box-shadow: 0 0 10px rgba(255,255,255,0.08);
            border-radius: 50%; 
            display: flex; flex-direction: column; 
            justify-content: center; align-items: center; 
            font-weight: 700; font-size: 11px; 
            position: absolute; top: 150px; left: 150px; 
            background: rgba(0,0,0,0.88);
            z-index: 10;
        }
        .indicator { 
            width: 80px; height: 80px; 
            border: 1px solid #666; 
            border-radius: 50%; 
            position: absolute; display: flex; flex-direction: column; 
            justify-content: center; align-items: center; 
            font-size: 9px; background: rgba(0,0,0,0.8);
            text-align: center;
        }
        .indicator.green { border-color: var(--accent-blue); box-shadow: 0 0 10px var(--accent-blue); }
        .indicator.red { border-color: var(--accent-red); box-shadow: 0 0 10px var(--accent-red); }
        .center-ticker.green { border-color: var(--accent-blue); box-shadow: 0 0 20px var(--accent-blue); }
        .center-ticker.red { border-color: var(--accent-red); box-shadow: 0 0 20px var(--accent-red); }
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
            width: 320px;
            background: rgba(0, 0, 0, 0.9);
            border: 1px solid #2a2a2a;
            box-shadow: 0 0 18px rgba(0, 243, 255, 0.08);
            padding: 16px;
            box-sizing: border-box;
            min-height: 400px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .panel-title {
            color: var(--accent-blue);
            font-weight: 700;
            font-size: 14px;
            letter-spacing: 1px;
        }
        .focus-stack {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .price-box {
            border: 1px solid #333;
            padding: 10px 12px;
            background: rgba(255,255,255,0.02);
        }
        .price-box.focus {
            border-color: var(--accent-blue);
            box-shadow: 0 0 10px rgba(0, 243, 255, 0.15);
        }
        .price-box.support {
            border-left: 3px solid #25c26e;
        }
        .price-box.resistance {
            border-left: 3px solid var(--accent-amber);
        }
        .price-label {
            font-size: 10px;
            color: #8f8f8f;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }
        .price-value {
            font-size: 22px;
            font-weight: 700;
            color: #fff;
        }
        .price-diff {
            font-size: 11px;
            color: #bbb;
            margin-top: 4px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .stat-card {
            border: 1px solid #2f2f2f;
            padding: 10px;
            background: rgba(255,255,255,0.02);
        }
        .stat-card.wide {
            grid-column: 1 / -1;
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
        <div class='clock-face' id='clock'>
            <svg id='lines' style='position:absolute; top:0; left:0; width:100%; height:100%; pointer-events:none;'></svg>
            <div class='center-ticker' id='focus' onclick='resetDashboard()' style='cursor:pointer;'>INIT<br>SCAN</div>
        </div>
        <aside class='focus-panel'>
            <div class='panel-title'>FOCUS LADDER</div>
            <div class='focus-stack'>
                <div class='price-box resistance'>
                    <div class='price-label'>RESISTANCE 2</div>
                    <div class='price-value' id='resistance2'>--</div>
                    <div class='price-diff' id='resistance2Diff'>Awaiting signal</div>
                </div>
                <div class='price-box resistance'>
                    <div class='price-label'>RESISTANCE 1</div>
                    <div class='price-value' id='resistance1'>--</div>
                    <div class='price-diff' id='resistance1Diff'>Awaiting signal</div>
                </div>
                <div class='price-box focus'>
                    <div class='price-label'>FOCUS PRICE</div>
                    <div class='price-value' id='focusPriceBox'>--</div>
                    <div class='price-diff' id='focusTimeBox'>Awaiting signal</div>
                </div>
                <div class='price-box support'>
                    <div class='price-label'>SUPPORT 1</div>
                    <div class='price-value' id='support1'>--</div>
                    <div class='price-diff' id='support1Diff'>Awaiting signal</div>
                </div>
                <div class='price-box support'>
                    <div class='price-label'>SUPPORT 2</div>
                    <div class='price-value' id='support2'>--</div>
                    <div class='price-diff' id='support2Diff'>Awaiting signal</div>
                </div>
            </div>
            <div class='stats-grid'>
                <div class='stat-card'>
                    <div class='stat-label'>DAY VOLUME</div>
                    <div class='stat-value' id='dayVolumeValue'>--</div>
                    <div class='stat-subtext' id='dayVolumeSubtext'>Waiting on market data</div>
                </div>
                <div class='stat-card'>
                    <div class='stat-label'>VOLUME RATIO</div>
                    <div class='stat-value' id='dayVolumeRatio'>--</div>
                    <div class='stat-subtext' id='barVolumeSubtext'>Current bar vs expected bar pending</div>
                </div>
                <div class='stat-card wide'>
                    <div class='stat-label'>INDICATOR BIAS</div>
                    <div class='stat-value' id='indicatorBiasValue'>--</div>
                    <div class='stat-subtext' id='indicatorBiasSubtext'>0 of 12 processed</div>
                </div>
            </div>
        </aside>
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
            document.getElementById('focusPriceBox').textContent = `$${formatPrice(data.focus_price ?? data.current_price)}`;
            const timeText = data.quote_time_eastern ? `${data.quote_time_eastern} ${data.quote_timezone || 'ET'}` : 'Time unavailable';
            document.getElementById('focusTimeBox').textContent = timeText;

            const upper = data.upper_resistances || [];
            const lower = data.lower_supports || [];
            setPriceBox('resistance1', upper[0], 'resistance');
            setPriceBox('resistance2', upper[1], 'resistance');
            setPriceBox('support1', lower[0], 'support');
            setPriceBox('support2', lower[1], 'support');

            const volume = data.volume || {};
            document.getElementById('dayVolumeValue').textContent = formatVolume(volume.current_day);
            document.getElementById('dayVolumeSubtext').textContent = `Expected ${formatVolume(volume.expected_day)} today`;
            document.getElementById('dayVolumeRatio').textContent = formatRatio(volume.day_ratio);
            document.getElementById('barVolumeSubtext').textContent = `Bar ${formatVolume(volume.current_bar)} vs exp ${formatVolume(volume.expected_bar)} (${formatRatio(volume.bar_ratio)})`;

            if (indicatorSummary) {
                const total = indicatorSummary.up + indicatorSummary.down + indicatorSummary.neutral;
                document.getElementById('indicatorBiasValue').textContent = `${indicatorSummary.up} ↑ / ${indicatorSummary.down} ↓`;
                document.getElementById('indicatorBiasSubtext').textContent = `${total} processed · ${indicatorSummary.neutral} neutral/inside tolerance`;
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
                el.innerHTML = `${symbol}<br>DATA ERR`;
                return;
            }

            el.innerHTML = buildTickerMarkup(symbol, data);
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
            focus.innerHTML = `${ticker}<br>SCANNING`;
            showBanner('');

            let currentFocus = null;
            try {
                currentFocus = await fetchTickerData(ticker, period, lookback);
                setTickerDisplay(focus, ticker, currentFocus, tolerance);
                updateFocusPanel(currentFocus);
                showBanner(formatSourceMeta(currentFocus), false);
            } catch (e) {
                focus.innerHTML = `${ticker}<br>DATA ERR`;
                const detail = e?.details ? JSON.stringify(e.details) : (e?.error || 'Unknown error');
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
                            el.innerHTML = `${ind}<br>DATA ERR`;
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
    <footer style='position: fixed; bottom: 10px; width: 100%; text-align: center; font-size: 10px; color: #444;'>v0.4.9</footer>
</body>
</html>