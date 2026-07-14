#!/bin/sh
# Start the miner via the dropper, then show the IR evidence.
set -e
cd "$(dirname "$0")"
PORT="$(grep -E '^WP_PORT=' .env | cut -d= -f2)"
PORT="${PORT:-8017}"

echo "[*] hitting dropper: GET /wp-content/wp-helper.php"
curl -s "http://127.0.0.1:${PORT}/wp-content/wp-helper.php" | head -20
echo
echo "[*] processes in the wordpress container:"
docker compose exec -T wordpress ps aux | grep -E 'wp-worker|xmrig' | grep -v grep \
  || echo "  (miner not seen yet; re-run in a few seconds)"
echo "[*] fake pool log (accepted shares = miner is 'working'):"
docker compose logs --tail=15 lab-pool
