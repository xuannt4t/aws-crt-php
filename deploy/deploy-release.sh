#!/usr/bin/env bash

set -euo pipefail

APP_ROOT="${APP_ROOT:-/var/www/dormida}"
REPOSITORY="${REPOSITORY:-git@github.com:xuannt4t/dormida-work.git}"
BRANCH="${BRANCH:-main}"
PHP_BIN="${PHP_BIN:-php}"
RELEASE_NAME="$(date -u +%Y%m%d%H%M%S)"
RELEASE_PATH="${APP_ROOT}/releases/${RELEASE_NAME}"

test -f "${APP_ROOT}/shared/.env"
test -d "${APP_ROOT}/shared/storage"
install -d -o deploy -g www-data -m 2775 "${APP_ROOT}/releases"

git clone --branch "${BRANCH}" --single-branch "${REPOSITORY}" "${RELEASE_PATH}"
ln -s "${APP_ROOT}/shared/.env" "${RELEASE_PATH}/.env"
mv "${RELEASE_PATH}/storage" "${RELEASE_PATH}/storage.release-template"
ln -s "${APP_ROOT}/shared/storage" "${RELEASE_PATH}/storage"

cd "${RELEASE_PATH}"
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
npm ci
npm run build

"${PHP_BIN}" artisan migrate --force
"${PHP_BIN}" artisan storage:link
"${PHP_BIN}" artisan optimize:clear
"${PHP_BIN}" artisan config:cache
"${PHP_BIN}" artisan route:cache
"${PHP_BIN}" artisan view:cache
"${PHP_BIN}" artisan scramble:cache

ln -sfn "${RELEASE_PATH}" "${APP_ROOT}/current"
"${PHP_BIN}" artisan queue:restart
sudo supervisorctl restart dormida-reverb
sudo systemctl reload php8.3-fpm nginx

printf 'Deployed %s to %s\n' "${BRANCH}" "${RELEASE_PATH}"
printf 'OpenAPI UI: https://dormida.task.pro.vn/docs/api\n'
