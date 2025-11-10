# Newspaper Redirector

Production-ready PHP 8.2+ application that delivers permanent QR-code friendly endpoints for Core Life newspapers. Each endpoint always redirects to the latest issue based on date, sequence number, or monthly cadence.

## Features

- Lightweight custom MVC running on PHP-FPM (no framework).
- Public directory-based endpoints (`/okaz/`, `/arabnews/`, etc.) with Tailwind-powered listing page.
- Secure admin panel with CSRF protection, rate-limited login, and manual sequence adjustments.
- Daily cron-safe sequence incrementer with HEAD rollback checks.
- Health endpoint (`/admin/status.json`) for operational visibility.
- Docker Compose stack (PHP-FPM, Nginx, MySQL) with a self-contained PHP test runner.

## Getting Started (Docker-first)

The application now ships with a single, fully configurable Docker workflow for both local
development and production. Everything (PHP-FPM, Nginx, MySQL) comes up through
`docker compose`.

### Configure the application

```bash
cp .env.example .env
php -r "echo password_hash('your-password', PASSWORD_BCRYPT), PHP_EOL;"
```

Update `.env` with your generated `ADMIN_PASSWORD_HASH` and any other settings you need.

### Launch the stack

All configuration is driven by environment variables. The values below are the production
defaults and can be overridden inline or via a `.env` file consumed by Docker Compose.

```bash
SERVER_NAME=newspaper.core-life.com \
HOST_HTTP_PORT=80 \
docker compose up -d --build
```

That single command builds the PHP container, renders the Nginx vhost for
`newspaper.core-life.com`, provisions MySQL with persistent storage, runs database
migrations and seeds, and brings the stack online.

Point the DNS `A`/`AAAA` record for `newspaper.core-life.com` at the server running the
command and the site is immediately live.

### Customising the stack

- `SERVER_NAME` – Hostname served by Nginx (defaults to `newspaper.core-life.com`).
- `HOST_HTTP_PORT` – Host port that maps to container port 80 (defaults to `80`).
- `HOST_DB_PORT` – Exposed MySQL port (defaults to `3306`).
- `MYSQL_DATABASE`, `MYSQL_USER`, `MYSQL_PASSWORD`, `MYSQL_ROOT_PASSWORD` – Database credentials.
- `APP_ENV` – Passed to PHP-FPM (defaults to `production`).

For local development you might prefer:

```bash
SERVER_NAME=localhost HOST_HTTP_PORT=8080 APP_ENV=local docker compose up -d --build
```

When you're done, tear everything down with:

```bash
docker compose down
```

### Database Schema

Run `php scripts/migrate.php` to create the `newspapers` table. Seed defaults with `php scripts/seed.php`.

Seeded rows include:

- `arabnews` – sequence-based (`https://www.arabnews.com/sites/default/files/pdf/{id}/index.html`)
- `aawsat` – sequence-based (`https://aawsat.com/files/pdf/issue{id}/`)
- `okaz` – date-based
- `ring` – monthly magazine

Sequence-based newspapers can optionally specify a `pattern` containing `{id}` to override the default `/{id}/index.html` suffix (used for Aawsat's `issue{id}/` format).

### Daily Cron

Schedule the incrementer in KSA local time:

```
5 0 * * * /usr/bin/php /var/www/newspaper-redirector/scripts/cron_increment.php >> /var/www/newspaper-redirector/storage/logs/cron.log 2>&1
```

The script is idempotent, heals missed days, and rolls back on HEAD 404s.

### Makefile Helpers

- `make install` – Composer install.
- `make cs` – Prints guidance for running code style checks with your locally installed tools.
- `make stan` – Prints guidance for running static analysis with your locally installed tools.
- `make test` – Lightweight built-in PHP test runner.
- `make seed` – Seed base newspapers.
- `make up` / `make down` – Docker lifecycle.

### Testing

Run the bundled test harness with either command:

```bash
php scripts/run-tests.php
# or
composer test
```

The runner auto-discovers `*Test.php` files, executes `test*` methods, and reports a compact
pass/fail summary without requiring external Composer packages.

### Web Server Configuration

#### Nginx

```
server {
    listen 80;
    server_name example.com;
    root /var/www/newspaper-redirector/public;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

#### Apache (.htaccess included)

```
Options -Indexes
RewriteEngine On
RewriteBase /
RewriteRule ^admin/status\.json$ index.php [L,QSA]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [L]
```

### Troubleshooting

- Ensure `storage/logs` and `storage/cache` are writable.
- Confirm MySQL credentials match `.env`.
- For login lockouts, remove `storage/cache/login_attempts.json`.
- Review `storage/logs/app.log` and `storage/logs/cron.log` for errors.

## Admin Panel

Default credentials from `.env.example`:

- Username: `admin`
- Password hash placeholder – replace with your hash.

Admin features:

- View newspaper metadata, last redirect, and provider IDs.
- Increment/decrement or set exact sequence IDs (sequence types only).
- Logout, CSRF-protected actions, and rate-limited login.

## Observability

- `/admin/status.json` exposes operational metrics (count, pending sequences, last redirect info).
- Logs written to `storage/logs/app.log` and `storage/logs/cron.log`.

## Optional Probing

`scripts/probe_head.php` issues HEAD requests against all configured destinations and prints the computed URLs for verification.
