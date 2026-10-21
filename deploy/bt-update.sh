#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

PHP_BIN="${PHP_BIN:-/www/server/php/84/bin/php}"

if [[ -z "${COMPOSER_BIN:-}" ]]; then
  if [[ -f /usr/bin/composer ]]; then
    COMPOSER_BIN=/usr/bin/composer
  else
    COMPOSER_BIN="$(command -v composer || true)"
  fi
fi

if [[ ! -x "$PHP_BIN" ]]; then
  echo "找不到 PHP 8.4：$PHP_BIN" >&2
  exit 1
fi

if [[ -z "$COMPOSER_BIN" || ! -f "$COMPOSER_BIN" ]]; then
  echo "找不到 composer" >&2
  exit 1
fi

run_composer() {
  local first
  first="$(head -n 1 "$COMPOSER_BIN" || true)"

  if [[ "$first" == "#!"* && "$first" != *"php"* ]]; then
    "$COMPOSER_BIN" "$@"
  else
    "$PHP_BIN" "$COMPOSER_BIN" "$@"
  fi
}

git pull --ff-only
run_composer install --no-dev --optimize-autoloader --no-interaction
"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan filament:assets
"$PHP_BIN" artisan livewire:publish --assets
"$PHP_BIN" artisan config:clear
"$PHP_BIN" artisan route:clear
"$PHP_BIN" artisan view:clear
"$PHP_BIN" artisan queue:restart

echo "更新完成。刷新系统配置后再点同步。"
