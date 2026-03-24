#!/usr/bin/env python3
import json
import subprocess
import sys
import time
from datetime import datetime, timezone
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent
OUTPUT_PATH = BASE_DIR / "mdata.json"
LOG_PATH = BASE_DIR / "reflector.log"


def now_iso() -> str:
    return datetime.now(timezone.utc).replace(microsecond=0).isoformat().replace("+00:00", "Z")


def log(message: str) -> None:
    line = f"[{now_iso()}] {message}\n"
    with LOG_PATH.open("a") as f:
        f.write(line)
    print(line, end="")


def read_status() -> dict:
    if not OUTPUT_PATH.exists():
        return {}
    try:
        return json.loads(OUTPUT_PATH.read_text()).get("status", {})
    except Exception:
        return {}


def main() -> int:
    interval = 60
    log("reflector minute loop starting")
    while True:
        start = time.time()
        proc = subprocess.run([sys.executable, str(BASE_DIR / "reflector.py")], capture_output=True, text=True)
        duration = round(time.time() - start, 2)
        if proc.returncode == 0:
            status = read_status()
            log(f"refresh ok duration={duration}s error_count={status.get('error_count', 'unknown')}")
        else:
            log(f"refresh failed duration={duration}s code={proc.returncode} stderr={proc.stderr.strip()}")

        sleep_for = max(0, interval - (time.time() - start))
        time.sleep(sleep_for)


if __name__ == "__main__":
    raise SystemExit(main())
