"""Self-check: a login gets a job back and a submit is accepted."""
import json, socket, subprocess, sys, time

def rpc(sock, obj):
    sock.sendall((json.dumps(obj) + "\n").encode())
    return json.loads(sock.makefile().readline())

def main():
    p = subprocess.Popen([sys.executable, "fake-pool.py"])
    try:
        time.sleep(1)
        s = socket.create_connection(("127.0.0.1", 3333), timeout=3)
        r = rpc(s, {"id": 1, "method": "login",
                    "params": {"login": "LAB_WALLET_DO_NOT_USE", "pass": "x"}})
        assert r["result"]["job"]["algo"] == "rx/0", r
        assert r["result"]["status"] == "OK", r
        # reuse same connection for submit
        s.sendall((json.dumps({"id": 2, "method": "submit",
                               "params": {"id": "lab-session"}}) + "\n").encode())
        r2 = json.loads(s.makefile().readline())
        assert r2["result"]["status"] == "OK", r2
        print("PASS")
    finally:
        p.terminate()

if __name__ == "__main__":
    main()
