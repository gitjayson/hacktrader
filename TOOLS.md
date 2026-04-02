# TOOLS.md - Local Notes

Skills define _how_ tools work. This file is for _your_ specifics — the stuff that's unique to your setup.

## What Goes Here

Things like:

- Camera names and locations
- SSH hosts and aliases
- Preferred voices for TTS
- Speaker/room names
- Device nicknames
- Anything environment-specific

## Examples

```markdown
### Obsidian
- **Vault Path:** `/Users/agent/Documents/PengoVault`
- **Folders:** `/Inbox`, `/Memories`, `/Projects`, `/Icebox`

### DreamHost
- **Server:** pdx1-shared-a2-12.dreamhost.com
- **SSH Fingerprint (Verified):** SHA256:dvF14sow9xvW94d/0HUHNQGH5CEn17epOjuJlHs/5+I
- **Username:** pengosky
- **Status:** Added to known_hosts. Private key generated at `~/.ssh/id_ed25519_pengo`. Need to add public key to server.

### Infrastructure
- **Current Host:** `MacBook Pro M3 Max` with **64GB RAM**
- **Current Wi‑Fi IP:** `192.168.0.102`
- **Fallback Host:** `2014 Mac Pro (The Trashcan!)` 🗑️✨
- **Remote Inference Box:** `192.168.0.16` = `Mac Studio M3 Ultra` with **96GB RAM**
- **Planned Remote Model:** `Qwen3.5-35B-A3B-Q8_0`
- **Local AI:** local model planned on M3 Max; Ollama/API on `.16` was not reachable over LAN during latest test
- **Primary Thinking:** Gemini 3 Flash
- **Search Key:** Perplexity (in config)

### Storage
- **Google Drive:** Available for "iceboxing" files.
```

## Why Separate?

Skills are shared. Your setup is yours. Keeping them apart means you can update skills without losing your notes, and share skills without leaking your infrastructure.

---

Add whatever helps you do your job. This is your cheat sheet.

### DreamHost Web Root
- **Correct Path:** `/home/pengosky/pngs.us/` (not `~/pngs`)
- **Pengi Game Live:** https://pngs.us/pengi-modern.html

### X.com (Twitter)
- **Handle:** @pengosky
- **Password:** rum55Bus!
- **Status:** Stored securely in workspace (local only)

### Twelve Data API (Stock Breakout Analysis)
- **API Key:** 43cb03fd841048c9ab1a95069f9461ac
- **Use:** Breakout analysis for TSLA, NVDA, AAPL, AMZN, etc.
- **Endpoint:** https://api.twelvedata.com/

### brk Command (Breakout Calculator)
- **Usage:** `brk <time> [ticker]`
- **Time params:** 1m, 5m, 1h, 1d
- **Ticker default:** TSLA if not provided
- **Examples:**
  - `brk 5m` → TSLA analysis
  - `brk 1h NVDA` → NVIDIA 1-hour breakout
  - `brk 5m AAPL` → Apple analysis

### HackTrader Dev Server
- **Host:** `dev.hacktrader.com`
- **SSH User:** `agent`
- **SSH Key:** `~/.ssh/id_ed25519_hacktrader`
- **SSH Command:** `ssh -i ~/.ssh/id_ed25519_hacktrader agent@dev.hacktrader.com`
- **Web Root:** `/var/www/html`
- **State:** Fresh install; nothing on the server needs to be preserved.
