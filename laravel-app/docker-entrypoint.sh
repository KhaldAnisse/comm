#!/usr/bin/env bash
set -e

php artisan config:clear
php artisan migrate --force --seed

exec php artisan serve --host=0.0.0.0 --port=8000
