#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# Servora — Quick update script (pull latest code & redeploy)
#
# Usage:  bash deploy/update.sh
# ─────────────────────────────────────────────────────────────────────────────

set -e  # Exit immediately on any error

APP_DIR="/var/www/servora"
WEB_USER="www-data"

RED='\033[0;31m'; GREEN='\033[0;32m'; CYAN='\033[0;36m'; YELLOW='\033[1;33m'; NC='\033[0m'
info() { echo -e "${CYAN}[INFO]${NC}  $*"; }
ok()   { echo -e "${GREEN}[OK]${NC}    $*"; }
warn() { echo -e "${YELLOW}[WARN]${NC}  $*"; }

[[ $EUID -eq 0 ]] || { echo -e "${RED}Run as root${NC}"; exit 1; }
cd "$APP_DIR"

# Ensure maintenance mode is always lifted, even on failure
cleanup() {
    info "Disabling maintenance mode..."
    php artisan up
}
trap cleanup EXIT

info "Enabling maintenance mode..."
php artisan down --refresh=15

info "Fetching latest code..."
git fetch origin main
git reset --hard origin/main

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

if systemctl is-active --quiet servora-queue 2>/dev/null; then
    info "Restarting queue worker..."
    systemctl restart servora-queue
elif systemctl list-unit-files | grep -q servora-queue; then
    info "Starting queue worker..."
    systemctl start servora-queue
else
    warn "Queue worker service not found. Skipping."
fi

# cleanup (php artisan up) runs automatically via trap
echo ""
ok "Update complete!"
echo ""
