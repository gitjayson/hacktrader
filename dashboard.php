<?php
session_start();
if (!isset($_SESSION['user_name']) || !isset($_SESSION['agreed'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>HackTrader | v0.3.0 FUI</title>
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
        button { cursor: pointer; border: 1px solid var(--accent-blue); color: var(--accent-blue); transition: 0.3s; }
        button:hover { background: var(--accent-blue); color: #000; }
        .clock-container { flex-grow: 1; display: flex; justify-content: center; align-items: center; position: relative; }
        .clock-face { position: relative; width: 400px; height: 400px; border: 1px dashed #333; border-radius: 50%; }
        .center-ticker { 
            width: 100px; height: 100px; 
            border: 2px solid var(--accent-blue); 
            box-shadow: 0 0 15px var(--accent-blue);
            border-radius: 50%; 
            display: flex; flex-direction: column; 
            justify-content: center; align-items: center; 
            font-weight: 700; font-size: 14px; 
            position: absolute; top: 150px; left: 150px; 
            background: rgba(0, 243, 255, 0.1);
        }
        .indicator { 
            width: 80px; height: 80px; 
            border: 1px solid #666; 
            border-radius: 50%; 
            position: absolute; display: flex; flex-direction: column; 
            justify-content: center; align-items: center; 
            font-size: 10px; background: rgba(0,0,0,0.8);
            text-align: center;
        }
        .indicator.green { border-color: var(--accent-blue); box-shadow: 0 0 10px var(--accent-blue); }
        .indicator.red { border-color: var(--accent-red); box-shadow: 0 0 10px var(--accent-red); }
        .center-ticker.green { border-color: var(--accent-blue); box-shadow: 0 0 20px var(--accent-blue); }
        .center-ticker.red { border-color: var(--accent-red); box-shadow: 0 0 20px var(--accent-red); }
    </style>
</head>
<body>
    <header>
        <input type='text' id='ticker' placeholder='TICKER' size='6'>
        <select id='period'><option>5m</option><option>1h</option><option>1d</option></select>
        <input type='number' id='lookback' placeholder='LOOKBACK' size='5'>
        <input type='number' id='tolerance' value='90' size='3'>
        <button onclick='updateDashboard()'>EXECUTE</button>
    </header>
    <div class='clock-container'>
        <div class='clock-face' id='clock'>
            <div class='center-ticker' id='focus'>INIT<br>SCAN</div>
        </div>
    </div>
    <script>
        async function updateDashboard() {
            const clock = document.getElementById('clock');
            const ticker = document.getElementById('ticker').value || 'TSLA';
            const period = document.getElementById('period').value;
            const lookback = document.getElementById('lookback').value || 100;
            const tolerance = document.getElementById('tolerance').value;
            
            const focus = document.getElementById('focus');
            focus.className = 'center-ticker';
            
            try {
                const focusRes = await fetch(`api.php?ticker=${ticker}&period=${period}&lookback=${lookback}`);
                const focusData = await focusRes.json();
                focus.innerHTML = `${ticker}<br>$${focusData.current_price}`;
                if (focusData.probabilities.up > tolerance) focus.classList.add('green');
                else if (focusData.probabilities.down > tolerance) focus.classList.add('red');
            } catch (e) { focus.innerHTML = `${ticker}<br>ERR`; }
            
            document.querySelectorAll('.indicator').forEach(e => e.remove());
            
            try {
                const corrRes = await fetch(`correlate.php?ticker=${ticker}`);
                const indicators = await corrRes.json();
                
                const promises = indicators.map(async (ind, i) => {
                    const angle = (i / indicators.length) * 2 * Math.PI;
                    const x = Math.cos(angle) * 180;
                    const y = Math.sin(angle) * 180;
                    const el = document.createElement('div');
                    el.className = 'indicator';
                    el.style.left = `calc(50% + ${x}px - 40px)`;
                    el.style.top = `calc(50% + ${y}px - 40px)`;
                    el.innerHTML = `${ind}<br>...`;
                    clock.appendChild(el);

                    try {
                        const dataRes = await fetch(`api.php?ticker=${ind}&period=${period}&lookback=${lookback}`);
                        const data = await dataRes.json();
                        el.innerHTML = `${ind}<br>$${data.current_price}<br>${Math.max(data.probabilities.up, data.probabilities.down)}%`;
                        if (data.probabilities.up > tolerance) el.classList.add('green');
                        else if (data.probabilities.down > tolerance) el.classList.add('red');
                    } catch (e) { el.innerHTML = `${ind}<br>ERR`; }
                });
                await Promise.all(promises);
            } catch (e) { console.error(e); }
        }
    </script>
</body>
</html>
