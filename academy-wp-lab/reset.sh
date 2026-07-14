#!/bin/sh
# Tear the lab down and wipe everything the attack dropped.
# WP core files are root/www-data owned, so wipe from inside a container.
set -e
cd "$(dirname "$0")"
docker compose down -v
if [ -d wordpress_data ]; then
  docker run --rm -v "$PWD/wordpress_data:/d" alpine \
    sh -c 'rm -rf /d/* /d/.[!.]* 2>/dev/null; true'
  rmdir wordpress_data 2>/dev/null || true
fi
echo "[reset] clean. 'docker compose up -d --build' for a fresh lab."
