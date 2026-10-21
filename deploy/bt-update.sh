#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan filament:assets
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
