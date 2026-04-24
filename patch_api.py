import sys

with open('/var/www/html/api.php', 'r') as f:
    content = f.read()

patch = """<?php
require_once __DIR__ . '/api_auth.php';

$apiKey = $_GET['api_key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? null;
$account = authenticate_api_key($apiKey);
if (!$account) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized. Invalid or missing API key.']);
    exit;
}

log_api_usage($apiKey, '/api.php', $_GET['ticker'] ?? 'TSLA');
?>
"""

if "api_auth.php" not in content:
    content = content.replace("<?php", patch, 1)
    with open('/var/www/html/api.php', 'w') as f:
        f.write(content)
    print("Patched api.php")
else:
    print("Already patched")
