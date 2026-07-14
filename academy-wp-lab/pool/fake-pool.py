#!/usr/bin/env python3
"""Fake Monero/RandomX Stratum pool for the Academy WP miner lab.

Accepts XMRig logins, hands out one easy-target job, and blindly accepts every
submitted share so the miner *looks* like it is working. It never validates
shares and never contacts a blockchain. Safe sink only.
"""
import json, socketserver

# ponytail: fixed dummy job. rx/0 wants a 76-byte blob + 32-byte seed_hash;
# the values are arbitrary hex because the pool never verifies shares.
JOB = {
    "blob": "0" * 152,
    "job_id": "lab-job-1",
    "target": "ffffff00",   # very easy -> frequent "accepted" shares
    "algo": "rx/0",
    "seed_hash": "0" * 64,
    "height": 1,
}

class Handler(socketserver.StreamRequestHandler):
    def handle(self):
        peer = self.client_address[0]
        print(f"[+] miner connected: {peer}", flush=True)
        for raw in self.rfile:
            line = raw.decode(errors="replace").strip()
            if not line:
                continue
            try:
                msg = json.loads(line)
            except json.JSONDecodeError:
                continue
            self._dispatch(msg, peer)
        print(f"[-] miner disconnected: {peer}", flush=True)

    def _send(self, mid, result):
        self.wfile.write(
            (json.dumps({"id": mid, "jsonrpc": "2.0",
                         "error": None, "result": result}) + "\n").encode())
        self.wfile.flush()

    def _dispatch(self, msg, peer):
        mid, method = msg.get("id"), msg.get("method")
        if method == "login":
            user = msg.get("params", {}).get("login", "?")
            print(f"[*] login user={user}", flush=True)
            self._send(mid, {"id": "lab-session", "job": JOB, "status": "OK"})
        elif method == "submit":
            print(f"[$] share accepted from {peer}", flush=True)
            self._send(mid, {"status": "OK"})
        elif method == "keepalived":
            self._send(mid, {"status": "KEEPALIVED"})
        else:
            self._send(mid, {"status": "OK"})

class Server(socketserver.ThreadingMixIn, socketserver.TCPServer):
    allow_reuse_address = True
    daemon_threads = True

if __name__ == "__main__":
    print("[*] fake stratum pool listening on :3333", flush=True)
    Server(("0.0.0.0", 3333), Handler).serve_forever()
