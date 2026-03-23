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
    <title>HackTrader Dashboard</title>
    <style>
        body { margin: 0; font-family: sans-serif; height: 100vh; background: #1a1a1a; color: white; display: flex; flex-direction: column; overflow: hidden; }
        header { background: #333; padding: 10px; display: flex; flex-wrap: wrap; gap: 5px; align-items: center; font-size: 14px; }
        header input, header select, header button { padding: 8px; font-size: 14px; }
        .clock-container { flex-grow: 1; display: flex; justify-content: center; align-items: center; position: relative; width: 100%; height: 100%; overflow: hidden; }
        .clock-face { position: relative; width: 300px; height: 300px; }
        .center-ticker { width: 80px; height: 80px; background: #4285F4; border-radius: 50%; display: flex; flex-direction: column; justify-content: center; align-items: center; font-weight: bold; font-size: 12px; position: absolute; top: 110px; left: 110px; transition: background 0.3s; }
        .indicator { width: 60px; height: 60px; background: #f39c12; border-radius: 50%; position: absolute; display: flex; flex-direction: column; justify-content: center; align-items: center; font-size: 10px; transition: background 0.3s; padding: 2px; text-align: center; }
        .center-ticker.green { background: #27ae60; }
        .center-ticker.red { background: #c0392b; }
        .indicator.green { background: #27ae60; }
        .indicator.red { background: #c0392b; }
        @media (max-width: 600px) {
            .clock-face { transform: scale(0.8); }
        }
    </style>
</head>
<body>
    <header>
        <input type='text' id='ticker' placeholder='Ticker' size='6'>
        <select id='period'><option>5m</option><option>1h</option><option>1d</option></select>
        <input type='number' id='lookback' value='100' placeholder='Lookback' size='5'>
        <input type='number' id='tolerance' value='90' size='3'>
        <button onclick='updateDashboard()'>Refresh</button>
    </header>
    <div class='clock-container'>
        <div class='clock-face' id='clock'>
            <div class='center-ticker' id='focus'>FOCUS<br><span id='focus-price'></span></div>
        </div>
    </div>
    <script>
        async function updateDashboard() {
            const clock = document.getElementById('clock');
            const ticker = document.getElementById('ticker').value.toUpperCase() || 'TSLA';
            const period = document.getElementById('period').value;
            const lookback = document.getElementById('lookback').value || 100;
            const tolerance = document.getElementById('tolerance').value;
            
            const focus = document.getElementById('focus');
            focus.className = 'center-ticker';
            
            // 1. Fetch Focus Data
            try {
                const focusRes = await fetch(`api.php?ticker=${ticker}&period=${period}&lookback=${lookback}`);
                const focusData = await focusRes.json();
                focus.innerHTML = `${ticker}<br>$${focusData.current_price}<br><span style='color: #27ae60'>+${focusData.probabilities.up}%</span> | <span style='color: #c0392b'>-${focusData.probabilities.down}%</span>`;
                if (focusData.probabilities.up > tolerance) focus.classList.add('green');
                else if (focusData.probabilities.down > tolerance) focus.classList.add('red');
            } catch (e) { console.error(e); focus.innerHTML = `${ticker}<br>Error`; }
            
            // 2. Clear Indicators
            document.querySelectorAll('.indicator').forEach(e => e.remove());
            
            // 3. Fetch Correlations
            try {
                const corrRes = await fetch(`correlate.php?ticker=${ticker}`);
                const indicators = await corrRes.json();
                
                // 4. Batch Parallel Updates
                const promises = indicators.map(async (ind, i) => {
                    const angle = (i / indicators.length) * 2 * Math.PI;
                    const x = Math.cos(angle) * 150;
                    const y = Math.sin(angle) * 150;
                    const el = document.createElement('div');
                    el.className = 'indicator';
                    el.style.left = `calc(50% + ${x}px - 30px)`;
                    el.style.top = `calc(50% + ${y}px - 30px)`;
                    el.innerHTML = `${ind}<br>...`;
                    clock.appendChild(el);

                    try {
                        const dataRes = await fetch(`api.php?ticker=${ind}&period=${period}&lookback=${lookback}`);
                        const data = await dataRes.json();
                        el.innerHTML = `${ind}<br>$${data.current_price}<br><span style='color: #27ae60'>+${data.probabilities.up}%</span> | <span style='color: #c0392b'>-${data.probabilities.down}%</span>`;
                        if (data.probabilities.up > tolerance) el.classList.add('green');
                        else if (data.probabilities.down > tolerance) el.classList.add('red');
                    } catch (e) { el.innerHTML = `${ind}<br>Err`; console.error(e); }
                });

                await Promise.all(promises);
            } catch (e) { console.error("Correlation fetch failed", e); }
        }
    </script>
</body>
</html>
