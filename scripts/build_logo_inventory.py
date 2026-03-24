#!/usr/bin/env python3
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
CORR = ROOT / 'correlations.json'
MANIFEST = ROOT / 'logos.json'
OUTDIR = ROOT / 'assets' / 'logos'

DEFAULT_COLORS = {
    'TSLA': ('#111111', '#e82127'),
    'AAPL': ('#111111', '#cfd8dc'),
    'NVDA': ('#111111', '#76b900'),
    'AMZN': ('#111111', '#ff9900'),
    'META': ('#111111', '#4aa3ff'),
    'GOOGL': ('#111111', '#4285F4'),
    'MSFT': ('#111111', '#7FBA00'),
    'NFLX': ('#111111', '#e50914'),
    'AMD': ('#111111', '#58b848'),
    'QQQ': ('#111111', '#00c2ff'),
    'SPY': ('#111111', '#7cb342'),
    'ARKK': ('#111111', '#26c6da'),
    'LIT': ('#111111', '#ab47bc'),
    'SMOG': ('#111111', '#26a69a'),
    'XLY': ('#111111', '#ffa726'),
    'RIVN': ('#111111', '#ff6f00'),
    'LCID': ('#111111', '#00bcd4'),
    'NIO': ('#111111', '#90caf9'),
    'UNG': ('#111111', '#8bc34a'),
    'UUP': ('#111111', '#29b6f6'),
    'WTI': ('#111111', '#ffca28'),
}


def load_symbols():
    data = json.loads(CORR.read_text())
    symbols = set(data.keys())
    for vals in data.values():
        for item in vals:
            symbols.add(item['symbol'])
    return sorted(symbols)


def colors_for(symbol):
    return DEFAULT_COLORS.get(symbol, ('#111111', '#00f3ff'))


def build_svg(symbol):
    bg, accent = colors_for(symbol)
    initials = symbol[:4]
    return f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 128 128" role="img" aria-label="{symbol} logo tile">
  <defs>
    <radialGradient id="g" cx="50%" cy="35%" r="75%">
      <stop offset="0%" stop-color="#1d1d1d"/>
      <stop offset="100%" stop-color="{bg}"/>
    </radialGradient>
  </defs>
  <rect width="128" height="128" rx="24" fill="url(#g)" stroke="{accent}" stroke-width="3"/>
  <circle cx="64" cy="36" r="14" fill="{accent}" opacity="0.18"/>
  <text x="64" y="74" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="30" font-weight="700" fill="{accent}" letter-spacing="2">{initials}</text>
  <text x="64" y="102" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="10" fill="#cfd8dc" letter-spacing="3">TICKER</text>
</svg>'''


def main():
    OUTDIR.mkdir(parents=True, exist_ok=True)
    manifest = json.loads(MANIFEST.read_text()) if MANIFEST.exists() else {}

    for symbol in load_symbols():
        out = OUTDIR / f'{symbol}.svg'
        if not out.exists():
            out.write_text(build_svg(symbol))
        manifest.setdefault(symbol, f'/assets/logos/{symbol}.svg')

    MANIFEST.write_text(json.dumps(dict(sorted(manifest.items())), indent=2) + '\n')
    print(f'Wrote {len(manifest)} manifest entries to {MANIFEST}')
    print(f'Logos stored in {OUTDIR}')


if __name__ == '__main__':
    main()
