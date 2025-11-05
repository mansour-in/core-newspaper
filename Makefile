.PHONY: up down test cs stan seed install

install:
composer install

up:
docker-compose up -d --build

down:
docker-compose down
touch storage/logs/app.log storage/logs/cron.log
sudo chown -R $$(id -u):$$(id -g) storage

cs:
vendor/bin/phpcs

stan:
vendor/bin/psalm

test:
vendor/bin/phpunit

seed:
php scripts/seed.php
