#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# Servora — Quick update script (pull latest code & redeploy)
#
# Usage:  bash deploy/update.sh
# ─────────────────────────────────────────────────────────────────────────────
set -euo pipefail

APP_DIR="/var/www/servora"
WEB_USER="www-data"

RED='\033[0;31m'; GREEN='\033[0;32m'; CYAN='\033[0;36m'; NC='\033[0m'
info() { echo -e "${CYAN}[INFO]${NC}  $*"; }
ok()   { echo -e "${GREEN}[OK]${NC}    $*"; }

[[ $EUID -eq 0 ]] || { echo -e "${RED}Run as root${NC}"; exit 1; }
cd "$APP_DIR"

info "Enabling maintenance mode..."
php artisan down --refresh=15

info "Pulling latest code..."
git pull origin main

info "Installing dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction --quiet

info "Building frontend..."
npm ci --silent 2>/dev/null || npm install --silent
npm run build

info "Running migrations..."
php artisan migrate --force

info "Clearing caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

info "Setting permissions..."
chown -R "${WEB_USER}:${WEB_USER}" "$APP_DIR"
chmod -R 775 "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"

info "Restarting queue worker..."
systemctl restart servora-queue

info "Disabling maintenance mode..."
php artisan up

echo ""
ok "Update complete!"
echo ""
