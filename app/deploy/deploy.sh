#!/usr/bin/env bash
set -euo pipefail

# GO AI EZ — Production Deployment Script
# Deploys application code, database migrations, and refreshes framework caches.

SRC_DIR="${1:-/home/goaiez/agents/grs-antig/app}"
TARGET_DIR="${2:-/home/goaiez/public_html/anti.goaiez.com}"

echo "==> Deploying GO AI EZ from ${SRC_DIR} to ${TARGET_DIR}..."

# 1. Rsync application code, domain modules, database migrations, routes, and resources
rsync -av --delete \
    --exclude='.git' \
    --exclude='.env' \
    --exclude='storage' \
    --exclude='vendor' \
    --exclude='node_modules' \
    --exclude='public/build' \
    "${SRC_DIR}/app/" "${TARGET_DIR}/app/"

rsync -av "${SRC_DIR}/database/" "${TARGET_DIR}/database/"
rsync -av "${SRC_DIR}/routes/" "${TARGET_DIR}/routes/"
rsync -av "${SRC_DIR}/resources/" "${TARGET_DIR}/resources/"
rsync -av "${SRC_DIR}/config/" "${TARGET_DIR}/config/"
rsync -av "${SRC_DIR}/lang/" "${TARGET_DIR}/lang/"

# 2. Clear old config cache and run migrations on live database
cd "${TARGET_DIR}"
php artisan config:clear
php artisan migrate --database=pgsql_migrate --force

# 3. Refresh Laravel caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart

echo "==> Deployment complete. Live site status:"
curl -s -o /dev/null -w "%{http_code}\n" https://anti.goaiez.com/
