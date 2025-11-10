.PHONY: up down test cs stan seed install

install:
composer install

up:
docker compose up -d --build

down:
docker compose down
touch storage/logs/app.log storage/logs/cron.log
sudo chown -R $$(id -u):$$(id -g) storage

cs:
@echo "Code style checks require local tooling."

stan:
@echo "Static analysis requires local tooling."

test:
php scripts/run-tests.php

seed:
php scripts/seed.php
