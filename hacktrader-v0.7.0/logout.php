<?php
session_start();
session_unset();
session_destroy();
?>
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <title>Logged Out</title>
    <style>
        body { font-family: sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; background-color: #f0f2f5; text-align: center; }
        .card { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    </style>
    <meta http-equiv='refresh' content='3;url=index.php'>
</head>
<body>
    <div class='card'>
        <h1>Logged Out</h1>
        <p>You have been logged out. Redirecting to the homepage in 3 seconds...</p>
    </div>
</body>
</html>
