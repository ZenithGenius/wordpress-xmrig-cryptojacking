# Academy WordPress XMRig Miner Lab

A local, isolated, resettable lab that reproduces a real-world WordPress
cryptominer compromise end-to-end for security training and PoC. It runs a **real
XMRig** — but on a network with no route to the internet, so it can only ever talk
to a local fake mining pool.

> ⚠️ **This lab contains real malware by design.** `wp-loader.php` and
> `wp-helper.php` are the actual seized payloads; the `cdn` image fetches a real
> XMRig release at build time. A commit security scanner **will** flag these — that
> is expected and correct. Never run any of it outside this isolated compose stack.

## Safety model

- All services run on a Docker network with `internal: true` — **no route off the
  host**. The WordPress container (where the miner runs) is on this network *only*.
- Host browser access goes through a small inbound-only `edge` proxy (socat) that
  bridges the host to WordPress. The proxy is not a forward proxy, so the miner
  can't use it to escape.
- Proven isolation (run it yourself):
  ```sh
  docker compose exec wordpress sh -c 'curl -m6 -s -o /dev/null -w "%{http_code}" http://pool.supportxmr.com:3333 || echo BLOCKED'
  # -> BLOCKED (no route). Meanwhile http://lab-cdn/wp-worker.part1 -> 200.
  ```
- All IOCs in runnable code are lab-only: pool `lab-pool.internal:3333`, wallet
  `LAB_WALLET_DO_NOT_USE`, CDN `http://lab-cdn/`. No real attacker infrastructure
  is referenced.
- The miner is resource-capped: `--randomx-mode=light` (~256 MB) and `nproc/2` threads.

## Quickstart

```sh
docker compose up -d --build      # build + start (WordPress, DB, CDN, fake pool, proxy)
# wait for: docker compose logs wpsetup  ->  "[wpsetup] done"
./trigger.sh                      # fire the dropper -> miner starts, pool shows shares
./reset.sh                        # tear down + wipe everything the attack dropped
```

WordPress: <http://127.0.0.1:8017> (port `8017`, matching the real incident).

## Credentials

| What | User | Pass |
|------|------|------|
| WordPress admin | `admin` | `labadmin` |
| Web shell (`wp-loader.php`) | `admin` | `AsterISK` (from the real payload) |

## Modes — `LAB_MODE` in `.env`

- `preseeded` (default) — `wp-loader.php` + `wp-helper.php` are already on disk.
  Start the story at "attacker has a web shell"; focus on the miner + IR.
- `exploit` — installs the real vulnerable **wp-file-manager 6.0** (CVE-2020-25213);
  the shells are *not* present. The trainee performs the unauthenticated upload to
  drop the web shell themselves. See `docs/01-attacker-walkthrough.md`.

Change the value, then `./reset.sh && docker compose up -d --build`.

## What's in here

| Path | Role |
|------|------|
| `docker-compose.yml` | 5 services + the `edge` proxy on `labnet` (internal) / `edge` (bridge) |
| `wordpress/` | WP image + `procps`/`util-linux` so IR tools (`ps`, `top`, `lscpu`) exist |
| `setup/wp-init.sh` | one-shot wp-cli install + `LAB_MODE` branch |
| `cdn/` | nginx serving XMRig as `wp-worker.part1..11` (the 11-GET dropper IOC) |
| `pool/fake-pool.py` | ~90-line Stratum sink; accepts logins/shares, mines nothing |
| `payloads/` | the seized `wp-loader.php` / `wp-helper.php` (lab-IOC versions) |
| `docs/` | attacker walkthrough, defender IR, detection & hardening |

## Docs

1. `docs/01-attacker-walkthrough.md` — exploit → shell → dropper → miner
2. `docs/02-defender-IR.md` — find it, kill it, quarantine, remediate
3. `docs/03-detection.md` — log signatures, IOCs, and the hardening that breaks the chain
