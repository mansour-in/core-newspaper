#!/bin/sh
set -eu

APP_DIR="/var/www/newspaper-redirector"
ENV_FILE="$APP_DIR/.env"

cd "$APP_DIR"

if [ ! -f "$ENV_FILE" ]; then
    echo "[app] Skipping migrations; $ENV_FILE not found."
    exec php-fpm
fi

MAX_RETRIES="${DB_READY_RETRIES:-30}"
SLEEP_SECONDS="${DB_READY_SLEEP:-2}"

printf '[app] Waiting for database'

retry=0
while [ "$retry" -lt "$MAX_RETRIES" ]; do
    if php -r 'require "bootstrap/app.php";' >/dev/null 2>&1; then
        echo ' ready.'
        break
    fi
    retry=$((retry + 1))
    printf '.'
    sleep "$SLEEP_SECONDS"

done

if [ "$retry" -ge "$MAX_RETRIES" ]; then
    echo '\n[app] Database not reachable after waiting, exiting.'
    exit 1
fi

echo '[app] Running database migrations'
php scripts/migrate.php

echo '[app] Seeding default newspapers'
php scripts/seed.php

echo '[app] Starting PHP-FPM'
exec php-fpm
