#!/usr/bin/env python3
from http.server import ThreadingHTTPServer, SimpleHTTPRequestHandler
from pathlib import Path
import argparse
import json
import mimetypes

ROOT = Path(__file__).resolve().parent

class HackTraderHandler(SimpleHTTPRequestHandler):
    def __init__(self, *args, directory=None, **kwargs):
        super().__init__(*args, directory=str(ROOT), **kwargs)

    def end_headers(self):
        self.send_header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
        self.send_header('Pragma', 'no-cache')
        self.send_header('Expires', '0')
        super().end_headers()

    def guess_type(self, path):
        typ, _ = mimetypes.guess_type(path)
        if typ:
            return typ
        if path.endswith('.json'):
            return 'application/json'
        if path.endswith('.md'):
            return 'text/markdown'
        return 'application/octet-stream'

    def log_message(self, format, *args):
        print(f"[{self.address_string()}] {format % args}")


def main():
    parser = argparse.ArgumentParser(description='HackTrader local dev webserver')
    parser.add_argument('--host', default='127.0.0.1')
    parser.add_argument('--port', type=int, default=8000)
    args = parser.parse_args()

    server = ThreadingHTTPServer((args.host, args.port), HackTraderHandler)
    print(f'HackTrader dev server running at http://{args.host}:{args.port}/')
    print(f'Serving: {ROOT}')
    server.serve_forever()


if __name__ == '__main__':
    main()
