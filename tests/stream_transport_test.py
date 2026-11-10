#!/usr/bin/env python3
"""Exercise the PHP stream fallback against a loopback-only mock HTTP server."""
import http.server
import json
import os
from pathlib import Path
import subprocess
import threading


class MockGitHub(http.server.BaseHTTPRequestHandler):
    def do_GET(self):
        if self.path == "/network":
            self.close_connection = True
            return
        status = int(self.path[1:]) if self.path[1:].isdigit() else 200
        self.send_response(status)
        self.send_header("Content-Type", "application/json")
        self.end_headers()
        self.wfile.write(b"{invalid" if self.path == "/invalid" else b'{"login":"fixture"}')

    def log_message(self, *_):
        pass


def main():
    engine = Path(__file__).resolve().parents[1] / "auth_system/engine/skillproof_engine.php"
    environment = dict(os.environ, GITHUB_TOKEN="")
    server = http.server.ThreadingHTTPServer(("127.0.0.1", 0), MockGitHub)
    thread = threading.Thread(target=server.serve_forever, daemon=True)
    thread.start()
    cases = [("200", ""), ("404", "not_found"), ("403", "rate_limit"),
             ("429", "rate_limit"), ("500", "http"), ("302", "http"),
             ("invalid", "invalid_response"), ("network", "network")]
    try:
        for path, expected in cases:
            result = subprocess.run([
                "php", "-n", "-d", "display_errors=stderr", "-r",
                'require $argv[1]; echo json_encode(sp_api_get($argv[2]), JSON_THROW_ON_ERROR);',
                str(engine), f"http://127.0.0.1:{server.server_port}/{path}",
            ], env=environment, capture_output=True, text=True, check=True, timeout=30)
            response = json.loads(result.stdout)
            assert response["error_type"] == expected, response
            assert response["ok"] == (expected == ""), response
            assert not result.stderr, result.stderr
            if path.isdigit():
                assert response["http_status"] == int(path), response
            print(f"PASS stream transport {path}: {expected or 'success'}")
    finally:
        server.shutdown()
        server.server_close()
        thread.join()
    print(f"{len(cases)} stream transport checks passed (loopback mock; no GitHub calls).")


if __name__ == "__main__":
    main()
