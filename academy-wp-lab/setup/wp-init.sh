#!/bin/sh
set -e
WP=/var/www/html

echo "[wpsetup] waiting for wp-config.php..."
until [ -f "$WP/wp-config.php" ]; do sleep 2; done

# Install via PHP/mysqli (no TLS requirement, unlike the mariadb-check client).
# Retry to absorb the brief window before the DB accepts connections.
if ! wp core is-installed --path="$WP" >/dev/null 2>&1; then
  n=0
  until wp core install --path="$WP" \
        --url="http://localhost:${WP_PORT:-8080}" --title="Academy Training" \
        --admin_user=admin --admin_password=labadmin \
        --admin_email=admin@lab.local --skip-email; do
    n=$((n + 1))
    [ "$n" -ge 20 ] && { echo "[wpsetup] database never became reachable"; exit 1; }
    echo "[wpsetup] waiting for database (install retry $n)..."
    sleep 3
  done
fi

MODE="${LAB_MODE:-preseeded}"
echo "[wpsetup] LAB_MODE=$MODE"

if [ "$MODE" = "exploit" ]; then
  # Real CVE-2020-25213: wp-file-manager 6.0 unauth upload via connector.minimal.php.
  # wordpress.org ships 6.0 as a zip-inside-a-zip, so unwrap it before installing.
  curl -sSL -o /tmp/o.zip https://downloads.wordpress.org/plugin/wp-file-manager.6.0.zip
  mkdir -p /tmp/ux && unzip -qo /tmp/o.zip -d /tmp/ux
  INNER="$(find /tmp/ux -name '*.zip' | head -1)"
  wp plugin install "${INNER:-/tmp/o.zip}" --force --activate --path="$WP" \
    || echo "[wpsetup] WARN: wp-file-manager 6.0 install failed"
  # Trainee drops the shells themselves in exploit mode.
  rm -f "$WP/wp-content/wp-loader.php" "$WP/wp-content/wp-helper.php"
else
  cp /payloads/wp-loader.php "$WP/wp-content/wp-loader.php"
  cp /payloads/wp-helper.php "$WP/wp-content/wp-helper.php"
  echo "[wpsetup] pre-seeded wp-loader.php + wp-helper.php"
fi

echo "[wpsetup] done"
