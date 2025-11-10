#!/bin/sh
set -eu

TEMPLATE="/etc/nginx/templates/default.conf.template"
TARGET="/etc/nginx/conf.d/default.conf"

SERVER_NAME="${SERVER_NAME:-newspaper.core-life.com}"
PUBLIC_ROOT="${PUBLIC_ROOT:-/var/www/newspaper-redirector/public}"
PHP_FPM_HOST="${PHP_FPM_HOST:-newspaper_app}"

export SERVER_NAME PUBLIC_ROOT PHP_FPM_HOST

echo "[nginx] Rendering template for ${SERVER_NAME}"

if [ ! -f "$TEMPLATE" ]; then
    echo "[nginx] Template $TEMPLATE not found" >&2
    exit 1
fi

envsubst '${SERVER_NAME} ${PUBLIC_ROOT} ${PHP_FPM_HOST}' < "$TEMPLATE" > "$TARGET"
