#!/usr/bin/env bash
set -euo pipefail

usage() {
    cat <<USAGE
Usage: sudo ./scripts/install_php.sh [options]

Options:
  --app-dir PATH               Absolute path to deploy the application (default: project root)
  --server-name NAME           Server name for Nginx config and APP_URL (default: localhost)
  --db-name NAME               MySQL database name (default: newspaper)
  --db-user USER               MySQL database user (default: newspaper)
  --db-pass PASS               MySQL database password (default: auto-generate)
  --admin-username USER        Admin panel username (default: admin)
  --admin-password PASS        Admin panel password (default: auto-generate)
  --mysql-root-password PASS   MySQL root password if required for authentication
  --skip-system-packages       Skip apt package installation
  -h, --help                   Show this help message
USAGE
}

require_root() {
    if [[ $(id -u) -ne 0 ]]; then
        echo "[ERROR] This script must be run as root (use sudo)." >&2
        exit 1
    fi
}

run_mysql() {
    local statement="$1"
    local mysql_cmd=(mysql -u root --batch --skip-column-names)

    if [[ -n "$MYSQL_ROOT_PASSWORD" ]]; then
        mysql_cmd+=(--password="$MYSQL_ROOT_PASSWORD")
    fi

    if ! "${mysql_cmd[@]}" -e "$statement" >/dev/null; then
        echo "[ERROR] Failed to execute MySQL statement. Verify root credentials." >&2
        exit 1
    fi
}

random_password() {
    openssl rand -base64 18 | tr -d '=+/\n' | cut -c1-24
}

update_env_var() {
    local key="$1"
    local value="$2"
    local file="$3"
    local escaped_value
    escaped_value=$(printf '%s' "$value" | sed 's/[&/\\]/\\&/g')

    if grep -q "^${key}=" "$file"; then
        perl -0pi -e "s/^${key}=.*$/${key}=${escaped_value}/m" "$file"
    else
        echo "${key}=${value}" >> "$file"
    fi
}

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"

APP_DIR="$PROJECT_ROOT"
SERVER_NAME="localhost"
DB_NAME="newspaper"
DB_USER="newspaper"
DB_PASS=""
ADMIN_USERNAME="admin"
ADMIN_PASSWORD=""
MYSQL_ROOT_PASSWORD=""
INSTALL_PACKAGES=1

while [[ $# -gt 0 ]]; do
    case "$1" in
        --app-dir)
            APP_DIR="$2"
            shift 2
            ;;
        --server-name)
            SERVER_NAME="$2"
            shift 2
            ;;
        --db-name)
            DB_NAME="$2"
            shift 2
            ;;
        --db-user)
            DB_USER="$2"
            shift 2
            ;;
        --db-pass)
            DB_PASS="$2"
            shift 2
            ;;
        --admin-username)
            ADMIN_USERNAME="$2"
            shift 2
            ;;
        --admin-password)
            ADMIN_PASSWORD="$2"
            shift 2
            ;;
        --mysql-root-password)
            MYSQL_ROOT_PASSWORD="$2"
            shift 2
            ;;
        --skip-system-packages)
            INSTALL_PACKAGES=0
            shift
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            echo "[ERROR] Unknown option: $1" >&2
            usage
            exit 1
            ;;
    esac
done

require_root

if [[ $INSTALL_PACKAGES -eq 1 ]]; then
    if command -v apt-get >/dev/null; then
        echo "[INFO] Installing PHP/MySQL/Nginx packages via apt-get..."
        apt-get update
        DEBIAN_FRONTEND=noninteractive apt-get install -y \
            php php-cli php-fpm php-mbstring php-xml php-curl php-mysql \
            curl unzip composer nginx mysql-server
    else
        echo "[WARN] apt-get not available. Please install PHP 8.2+, Composer, MySQL 8+, and Nginx manually." >&2
    fi
fi

mkdir -p "$APP_DIR"
if [[ "$APP_DIR" != "$PROJECT_ROOT" ]]; then
    echo "[INFO] Syncing project files to $APP_DIR..."
    rsync -a --delete "$PROJECT_ROOT/" "$APP_DIR/"
fi

cd "$APP_DIR"

if [[ ! -f composer.json ]]; then
    echo "[ERROR] composer.json not found in $APP_DIR" >&2
    exit 1
fi

if [[ -z "$DB_PASS" ]]; then
    DB_PASS=$(random_password)
fi

if [[ -z "$ADMIN_PASSWORD" ]]; then
    ADMIN_PASSWORD=$(random_password)
fi

if [[ ! -f .env ]]; then
    echo "[INFO] Creating .env from template..."
    cp .env.example .env
fi

APP_URL="http://${SERVER_NAME}"
DB_DSN="mysql:host=localhost;dbname=${DB_NAME};charset=utf8mb4"

update_env_var "APP_URL" "$APP_URL" .env
update_env_var "DB_DSN" "$DB_DSN" .env
update_env_var "DB_USERNAME" "$DB_USER" .env
update_env_var "DB_PASSWORD" "$DB_PASS" .env
update_env_var "ADMIN_USERNAME" "$ADMIN_USERNAME" .env

ADMIN_PASSWORD_HASH=$(php -r "echo password_hash('${ADMIN_PASSWORD}', PASSWORD_BCRYPT);")
update_env_var "ADMIN_PASSWORD_HASH" "$ADMIN_PASSWORD_HASH" .env

if command -v systemctl >/dev/null; then
    systemctl enable --now mysql >/dev/null 2>&1 || true
fi

echo "[INFO] Configuring MySQL database and user..."
run_mysql "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
run_mysql "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
run_mysql "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';"
run_mysql "FLUSH PRIVILEGES;"

if ! command -v composer >/dev/null; then
    echo "[ERROR] Composer is required but not available." >&2
    exit 1
fi

echo "[INFO] Installing Composer dependencies..."
composer install --no-interaction --prefer-dist --optimize-autoloader

echo "[INFO] Running database migrations and seeders..."
php scripts/migrate.php
php scripts/seed.php

mkdir -p storage/logs storage/cache
chmod -R 775 storage

NGINX_CONF_PATH="/etc/nginx/sites-available/newspaper-redirector.conf"
if [[ -d /etc/nginx/sites-available ]]; then
    echo "[INFO] Writing Nginx configuration..."
    cat <<CONF > "$NGINX_CONF_PATH"
server {
    listen 80;
    server_name ${SERVER_NAME};
    root ${APP_DIR}/public;

    index index.php;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \\.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php-fpm.sock;
    }
}
CONF

    ln -sf "$NGINX_CONF_PATH" /etc/nginx/sites-enabled/newspaper-redirector.conf
    if [[ -f /etc/nginx/sites-enabled/default ]]; then
        rm -f /etc/nginx/sites-enabled/default
    fi

    if command -v nginx >/dev/null; then
        nginx -t
        systemctl reload nginx
    fi
else
    echo "[WARN] Nginx configuration directory not found. Skipping web server configuration." >&2
fi

WEB_USER="www-data"
if id "$WEB_USER" >/dev/null 2>&1; then
    chown -R "$WEB_USER":"$WEB_USER" storage public
fi

cat <<SUMMARY

[INSTALLATION COMPLETE]
Application directory: $APP_DIR
Admin URL: http://${SERVER_NAME}/admin/login
Admin username: ${ADMIN_USERNAME}
Admin password: ${ADMIN_PASSWORD}
Database name: ${DB_NAME}
Database user: ${DB_USER}
Database password: ${DB_PASS}
SUMMARY
