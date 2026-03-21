<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <title>Sign In</title>
    <style>
        body { 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            height: 100vh; 
            margin: 0; 
            font-family: sans-serif; 
            background: url('https://images.unsplash.com/photo-1449824913935-59a10b8d2000?q=80&w=2070&auto=format&fit=crop') no-repeat center center fixed; 
            background-size: cover; 
        }
        .signin-container { background: rgba(255,255,255,0.8); padding: 40px; border-radius: 10px; text-align: center; }
        .signin-button { padding: 15px 30px; font-size: 18px; color: #fff; background-color: #4285F4; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; }
        .signin-button:hover { background-color: #357ae8; }
        footer { position: fixed; bottom: 10px; width: 100%; text-align: center; font-size: 10px; color: #fff; text-shadow: 1px 1px 2px #000; }
    </style>
</head>
<body>
    <div class='signin-container'>
        <h1>Welcome to HackTrader</h1>
        <a href='callback.php' class='signin-button'>Sign in with Google</a>
    </div>
    <footer>v0.2.0</footer>
</body>
</html>
