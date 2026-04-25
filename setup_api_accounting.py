import json
import os

DB_FILE = "/var/www/html/api-accounts.json"
USAGE_FILE = "/var/www/html/api-usage.json"

accounts = {"test_key_123": {"owner": "admin", "tier": "premium"}}

with open(DB_FILE, "w") as f:
    json.dump(accounts, f)

with open(USAGE_FILE, "w") as f:
    json.dump([], f)

os.chmod(DB_FILE, 0o666)
os.chmod(USAGE_FILE, 0o666)
