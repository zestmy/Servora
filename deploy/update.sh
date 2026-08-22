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

# ── Serialize deploys ────────────────────────────────────────────────────────
# The GitHub Actions webhook and manual SSH deploys both run this script; two
# at once collide on .git/index.lock and can leave a deploy half-applied.
# Wait up to 10 minutes for any in-flight deploy, then proceed. The caller can
# pre-acquire the lock (see .github/workflows/deploy.yml) and set
# SERVORA_DEPLOY_LOCK_HELD=1 so we don't deadlock re-acquiring it.
if [[ -z "${SERVORA_DEPLOY_LOCK_HELD:-}" ]]; then
    exec 9>/var/lock/servora-deploy.lock
    if ! flock -w 600 9; then
        echo -e "${RED}Another deploy has been running for 10+ minutes — aborting.${NC}"
        exit 1
    fi
fi

# Ensure maintenance mode is lifted and the worker is running again, even on
# failure. A deploy that dies halfway must not leave queued work stranded.
cleanup() {
    info "Disabling maintenance mode..."
    php artisan up
    systemctl start servora-queue 2>/dev/null || true
}
trap cleanup EXIT

# Stop the worker BEFORE maintenance mode, not after the deploy.
#
# Two reasons, and the first one is the bug that had this service restarting
# fifty times a deploy: a worker that is up while `artisan down` is in effect
# exits cleanly every few seconds, forever. deploy/servora-queue.service has
# the full derivation.
#
# The second reason stands on its own. Between `git reset` and the end of
# migrations the checked-out code and the database disagree, and a worker left
# running picks jobs off the queue and runs them against exactly that. Nothing
# should be executing application code during those ninety seconds.
info "Stopping queue worker for the deploy..."
systemctl stop servora-queue 2>/dev/null || true

# --render snapshots the maintenance page BEFORE the deploy touches any files,
# so it keeps serving even while composer/npm have the codebase half-replaced.
# The view is fully inline (no @vite) for the same reason.
info "Enabling maintenance mode..."
php artisan down --refresh=15 --render="errors::503"

info "Fetching latest code..."
git fetch origin main
git reset --hard origin/main

# The optimised classmap still points at the files the PREVIOUS release had.
# If that release included a class this one deletes, the map now references a
# missing file and the very next command that boots Laravel dies — including
# composer's own post-autoload-dump hook (artisan package:discover), which
# takes the whole deploy down before migrations ever run.
#
# --no-scripts is the point: rebuild the map WITHOUT booting Laravel, so
# everything after this sees a map that matches the checked-out code.
info "Refreshing autoloader for the new file set..."
composer dump-autoload --optimize --no-interaction --no-scripts --quiet

info "Installing dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction --quiet

info "Building frontend..."
npm ci --silent 2>/dev/null || npm install --silent
npm run build

info "Running migrations..."
php artisan migrate --force

# ── Help centre content ─────────────────────────────────────────────────────
# Run on EVERY deploy, deliberately. DocsSeeder upserts by slug: it adds
# articles that are missing and leaves every existing row untouched, so an
# editor's corrections at /admin/docs survive, and a new shipped article lands
# without anybody remembering to run a seeder by hand. Cheap — it is a
# handful of selects when there is nothing to add.
info "Syncing help centre content..."
php artisan db:seed --class=DocsSeeder --force

info "Clearing caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

info "Setting permissions..."
chown -R "${WEB_USER}:${WEB_USER}" "$APP_DIR"
chmod -R 775 "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"

# ── Can staff faces be told apart? ──────────────────────────────────────────
# TEMPORARY, and read-only. Recognising somebody from the camera alone is
# identification rather than verification, and whether that is safe depends on
# these particular people in these particular rooms — not on anything that can
# be reasoned out from here. This measures it on the faces already enrolled so
# the thresholds come from data instead of from a guess.
#
# --anonymise because this lands in a GitHub Actions log: the numbers are the
# point, staff names are not. Remove this block once the answer is in hand.
info "Face separation (read-only measurement):"
sudo -u "${WEB_USER}" php artisan clock:face-separation --anonymise 2>&1 | sed 's/^/    /' || true

# ── Queue worker ────────────────────────────────────────────────────────────
# The unit file was written once by install.sh and never touched again, so a
# box installed months ago kept whatever ExecStart it was installed with —
# including the --max-time that made the worker exit every few seconds under
# maintenance mode. Reconcile it against the repo on every deploy so a fix to
# the unit ships like a fix to anything else.
#
# Only rewrite when the content differs: an unconditional write plus
# daemon-reload on every deploy is noise, and it makes "the unit changed" stop
# meaning anything in the log.
UNIT_SRC="${APP_DIR}/deploy/servora-queue.service"
UNIT_DST="/etc/systemd/system/servora-queue.service"

if [[ -f "$UNIT_SRC" ]]; then
    UNIT_RENDERED="$(sed -e "s|__WEB_USER__|${WEB_USER}|g" -e "s|__APP_DIR__|${APP_DIR}|g" "$UNIT_SRC")"

    if [[ ! -f "$UNIT_DST" ]] || [[ "$UNIT_RENDERED" != "$(cat "$UNIT_DST")" ]]; then
        info "Queue worker unit changed — installing it..."
        printf '%s\n' "$UNIT_RENDERED" > "$UNIT_DST"
        systemctl daemon-reload
        systemctl enable servora-queue 2>/dev/null || true
    fi
fi

if systemctl list-unit-files 2>/dev/null | grep -q servora-queue; then
    info "Starting queue worker..."
    systemctl start servora-queue

    # Proof, in the deploy log, that it is still up a moment later rather than
    # merely accepted. A worker that flaps looks identical to a healthy one at
    # the instant `start` returns; NRestarts is the number that tells them
    # apart, and it is the number that went to fifty unnoticed.
    sleep 2
    systemctl show servora-queue -p ActiveState -p SubState -p NRestarts --no-pager 2>&1 \
        | sed 's/^/    /' || true
else
    warn "Queue worker service not found. Skipping."
fi

# cleanup (php artisan up) runs automatically via trap
echo ""
ok "Update complete!"
echo ""
