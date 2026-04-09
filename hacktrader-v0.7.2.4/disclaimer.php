<?php
session_start();
if (!isset($_SESSION['user_name'])) {
    header('Location: index.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept'])) {
    $_SESSION['agreed'] = true;
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang='en'>
<head><meta charset='UTF-8'><title>Disclaimer</title>
<style>
    body { font-family: sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; background-color: #f0f2f5; }
    .modal { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); max-width: 500px; text-align: center; }
    button { padding: 10px 20px; cursor: pointer; }
</style>
</head>
<body>
    <div class='modal'>
        <h1>Legal Disclaimer</h1>
        <p>This site does not contain trading or financial assistance or advice.<br>Do you agree to these terms?</p>
        <form method='post'>
            <button name='accept'>Accept</button>
            <a href='logout.php'><button type='button'>Go Back</button></a>
        </form>
    </div>
</body>
</html>
