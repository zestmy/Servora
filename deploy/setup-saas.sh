#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# Servora SaaS — Production Setup Script
#
# This script configures everything needed for the SaaS platform:
#   1. APP_DOMAIN for subdomain routing
#   2. CHIP-IN payment gateway keys
#   3. Email (EngineMailer via SMTP or keep as log)
#   4. Nginx wildcard subdomain config
#   5. SSL certificate for wildcard subdomains
#   6. Laravel scheduler cron job
#   7. Seed subscription plans
#
# Usage:  ssh root@206.189.155.9 "bash /var/www/servora/deploy/setup-saas.sh"
# ─────────────────────────────────────────────────────────────────────────────

APP_DIR="/var/www/servora"
WEB_USER="www-data"
DOMAIN="servora.com.my"
ENV_FILE="${APP_DIR}/.env"

RED='\033[0;31m'; GREEN='\033[0;32m'; CYAN='\033[0;36m'; YELLOW='\033[1;33m'; BOLD='\033[1m'; NC='\033[0m'
info()  { echo -e "${CYAN}[INFO]${NC}  $*"; }
ok()    { echo -e "${GREEN}[OK]${NC}    $*"; }
warn()  { echo -e "${YELLOW}[WARN]${NC}  $*"; }
err()   { echo -e "${RED}[ERR]${NC}   $*"; }
ask()   { echo -en "${BOLD}$*${NC} "; }

[[ $EUID -eq 0 ]] || { err "Run as root."; exit 1; }
[[ -f "$ENV_FILE" ]] || { err ".env not found at ${ENV_FILE}"; exit 1; }

cd "$APP_DIR"

echo ""
echo -e "${BOLD}╔══════════════════════════════════════════════════╗${NC}"
echo -e "${BOLD}║        Servora SaaS — Production Setup          ║${NC}"
echo -e "${BOLD}╚══════════════════════════════════════════════════╝${NC}"
echo ""

# ─────────────────────────────────────────────────────────────────────────────
# Helper: set or update a key in .env
# ─────────────────────────────────────────────────────────────────────────────
set_env() {
    local key="$1" val="$2"
    if grep -q "^${key}=" "$ENV_FILE"; then
        sed -i "s|^${key}=.*|${key}=${val}|" "$ENV_FILE"
    else
        echo "${key}=${val}" >> "$ENV_FILE"
    fi
}

# ═══════════════════════════════════════════════════════════════════════════════
# STEP 1: APP_DOMAIN for subdomain routing
# ═══════════════════════════════════════════════════════════════════════════════
echo -e "${CYAN}━━━ Step 1: Domain Configuration ━━━${NC}"
info "Setting APP_DOMAIN=${DOMAIN}"
set_env "APP_DOMAIN" "${DOMAIN}"
set_env "APP_URL" "https://app.${DOMAIN}"
ok "Domain configured: *.${DOMAIN}"
echo ""

# ═══════════════════════════════════════════════════════════════════════════════
# STEP 2: CHIP-IN Payment Gateway
# ═══════════════════════════════════════════════════════════════════════════════
echo -e "${CYAN}━━━ Step 2: CHIP-IN Payment Gateway ━━━${NC}"
echo "  Get your keys from https://gate.chip-in.asia (Merchant Portal)"
echo ""

CURRENT_CHIPIN=$(grep "^CHIPIN_BRAND_ID=" "$ENV_FILE" 2>/dev/null | cut -d= -f2)
if [[ -n "$CURRENT_CHIPIN" && "$CURRENT_CHIPIN" != "" ]]; then
    ok "CHIP-IN already configured (Brand ID: ${CURRENT_CHIPIN})"
    ask "Reconfigure? [y/N]:"
    read -r RECONF_CHIPIN
else
    RECONF_CHIPIN="y"
fi

if [[ "$RECONF_CHIPIN" =~ ^[Yy] ]]; then
    ask "CHIP-IN Brand ID:"
    read -r CHIPIN_BRAND_ID
    ask "CHIP-IN API Key (Secret Key):"
    read -r CHIPIN_API_KEY
    ask "CHIP-IN Webhook Secret:"
    read -r CHIPIN_WEBHOOK_SECRET
    ask "Use sandbox mode? [Y/n]:"
    read -r CHIPIN_SANDBOX

    if [[ "$CHIPIN_SANDBOX" =~ ^[Nn] ]]; then
        CHIPIN_SANDBOX_VAL="false"
        CHIPIN_BASE_URL="https://gate.chip-in.asia/api/v1"
    else
        CHIPIN_SANDBOX_VAL="true"
        CHIPIN_BASE_URL="https://gate.chip-in.asia/api/v1"
    fi

    set_env "CHIPIN_BRAND_ID" "${CHIPIN_BRAND_ID}"
    set_env "CHIPIN_API_KEY" "${CHIPIN_API_KEY}"
    set_env "CHIPIN_WEBHOOK_SECRET" "${CHIPIN_WEBHOOK_SECRET}"
    set_env "CHIPIN_SANDBOX" "${CHIPIN_SANDBOX_VAL}"
    set_env "CHIPIN_BASE_URL" "${CHIPIN_BASE_URL}"

    if [[ -n "$CHIPIN_BRAND_ID" ]]; then
        ok "CHIP-IN configured."
    else
        warn "CHIP-IN keys left empty — payment processing won't work until configured."
    fi
else
    ok "CHIP-IN config unchanged."
fi
echo ""

# ═══════════════════════════════════════════════════════════════════════════════
# STEP 3: Email Configuration
# ═══════════════════════════════════════════════════════════════════════════════
echo -e "${CYAN}━━━ Step 3: Email Configuration ━━━${NC}"
CURRENT_MAILER=$(grep "^MAIL_MAILER=" "$ENV_FILE" | cut -d= -f2)
info "Current mailer: ${CURRENT_MAILER}"
echo ""
echo "  Options:"
echo "    1) smtp     — Use any SMTP server (EngineMailer, Gmail, etc.)"
echo "    2) log      — Keep logging to file (emails won't actually send)"
echo "    3) skip     — Don't change email config"
echo ""
ask "Choose [1/2/3]:"
read -r MAIL_CHOICE

case "$MAIL_CHOICE" in
    1)
        ask "SMTP Host (e.g. smtp.enginemailer.com):"
        read -r SMTP_HOST
        ask "SMTP Port (e.g. 587):"
        read -r SMTP_PORT
        ask "SMTP Username:"
        read -r SMTP_USER
        ask "SMTP Password:"
        read -rs SMTP_PASS
        echo ""
        ask "From Email (e.g. noreply@servora.com.my):"
        read -r MAIL_FROM
        ask "From Name [Servora]:"
        read -r MAIL_FROM_NAME
        MAIL_FROM_NAME="${MAIL_FROM_NAME:-Servora}"

        set_env "MAIL_MAILER" "smtp"
        set_env "MAIL_HOST" "${SMTP_HOST}"
        set_env "MAIL_PORT" "${SMTP_PORT}"
        set_env "MAIL_USERNAME" "${SMTP_USER}"
        set_env "MAIL_PASSWORD" "\"${SMTP_PASS}\""
        set_env "MAIL_ENCRYPTION" "tls"
        set_env "MAIL_FROM_ADDRESS" "\"${MAIL_FROM}\""
        set_env "MAIL_FROM_NAME" "\"${MAIL_FROM_NAME}\""
        ok "SMTP configured."
        ;;
    2)
        set_env "MAIL_MAILER" "log"
        ok "Mailer set to log."
        ;;
    *)
        ok "Email config unchanged."
        ;;
esac
echo ""

# ═══════════════════════════════════════════════════════════════════════════════
# STEP 4: Nginx — Wildcard Subdomain Configuration
# ═══════════════════════════════════════════════════════════════════════════════
echo -e "${CYAN}━━━ Step 4: Nginx Wildcard Subdomain ━━━${NC}"

NGINX_CONF="/etc/nginx/sites-available/servora"
CURRENT_SERVER_NAME=$(grep "server_name" "$NGINX_CONF" 2>/dev/null | head -1 | xargs)

if echo "$CURRENT_SERVER_NAME" | grep -q "\*\.${DOMAIN}"; then
    ok "Nginx already configured for wildcard: *.${DOMAIN}"
else
    info "Updating Nginx to handle *.${DOMAIN} and ${DOMAIN}"

    # Backup current config
    cp "$NGINX_CONF" "${NGINX_CONF}.bak.$(date +%Y%m%d%H%M%S)"

    # Create new Nginx config
    cat > "$NGINX_CONF" <<NGINX
server {
    server_name ${DOMAIN} *.${DOMAIN};
    root /var/www/servora/public;
    index index.php;

    client_max_body_size 25M;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location ~ \.php\$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 60;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    listen 443 ssl;
    ssl_certificate /etc/letsencrypt/live/app.${DOMAIN}/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/app.${DOMAIN}/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;
}

server {
    listen 80;
    server_name ${DOMAIN} *.${DOMAIN};
    return 301 https://\$host\$request_uri;
}
NGINX

    # Test and reload
    if nginx -t 2>/dev/null; then
        systemctl reload nginx
        ok "Nginx updated and reloaded for *.${DOMAIN}"
    else
        err "Nginx config test failed! Restoring backup..."
        cp "${NGINX_CONF}.bak."* "$NGINX_CONF" 2>/dev/null
        systemctl reload nginx
    fi
fi
echo ""

# ═══════════════════════════════════════════════════════════════════════════════
# STEP 5: SSL Certificate
# ═══════════════════════════════════════════════════════════════════════════════
echo -e "${CYAN}━━━ Step 5: SSL Certificate ━━━${NC}"
echo ""
echo "  Your current SSL covers: app.${DOMAIN}"
echo "  For wildcard subdomains (*.${DOMAIN}), you have two options:"
echo ""
echo "    A) Cloudflare Proxy (recommended, easiest)"
echo "       → Point DNS to Cloudflare, enable proxy, get free universal SSL"
echo "       → No server-side cert changes needed"
echo ""
echo "    B) Certbot DNS challenge (manual)"
echo "       → Requires DNS API access (DigitalOcean, Cloudflare plugin, etc.)"
echo "       → Run: certbot certonly --manual --preferred-challenges dns -d '*.${DOMAIN}' -d '${DOMAIN}'"
echo ""
echo "  For now, the existing cert (app.${DOMAIN}) will be used for all subdomains."
echo "  Browsers may show a warning on other subdomains until wildcard SSL is set up."
echo ""

ask "Do you want to attempt a wildcard cert via Certbot DNS challenge now? [y/N]:"
read -r DO_WILDCARD

if [[ "$DO_WILDCARD" =~ ^[Yy] ]]; then
    info "Starting Certbot DNS challenge for *.${DOMAIN} and ${DOMAIN}"
    echo ""
    warn "You will need to create a DNS TXT record when prompted."
    warn "Keep this terminal open and create the record in your DNS provider."
    echo ""
    certbot certonly --manual --preferred-challenges dns \
        -d "*.${DOMAIN}" \
        -d "${DOMAIN}" \
        --agree-tos \
        --no-eff-email

    if [[ $? -eq 0 ]]; then
        # Update Nginx to use the new wildcard cert
        sed -i "s|ssl_certificate .*|ssl_certificate /etc/letsencrypt/live/${DOMAIN}/fullchain.pem;|" "$NGINX_CONF"
        sed -i "s|ssl_certificate_key .*|ssl_certificate_key /etc/letsencrypt/live/${DOMAIN}/privkey.pem;|" "$NGINX_CONF"
        nginx -t && systemctl reload nginx
        ok "Wildcard SSL configured!"
    else
        warn "Certbot failed. You can retry later or use Cloudflare proxy instead."
    fi
else
    info "Skipping wildcard SSL. Current cert will be used."
    warn "Set up Cloudflare proxy or run the certbot command above when ready."
fi
echo ""

# ═══════════════════════════════════════════════════════════════════════════════
# STEP 6: Laravel Scheduler (Cron Job)
# ═══════════════════════════════════════════════════════════════════════════════
echo -e "${CYAN}━━━ Step 6: Laravel Scheduler ━━━${NC}"

CRON_LINE="* * * * * cd ${APP_DIR} && php artisan schedule:run >> /dev/null 2>&1"

if crontab -l 2>/dev/null | grep -q "servora.*schedule:run"; then
    ok "Laravel scheduler cron already exists."
else
    info "Adding Laravel scheduler to crontab..."
    (crontab -l 2>/dev/null; echo "$CRON_LINE") | crontab -
    ok "Cron job added. Laravel scheduler will run every minute."
    info "Scheduled tasks:"
    echo "    - billing:process-recurring  → daily at 06:00 (renewals, past due, expiry)"
    echo "    - usage:snapshot             → daily at 00:00 (usage tracking)"
    echo "    - onboarding:send-emails     → daily at 09:00 (trial email sequence)"
fi
echo ""

# ═══════════════════════════════════════════════════════════════════════════════
# STEP 7: Seed Plans & Clear Caches
# ═══════════════════════════════════════════════════════════════════════════════
echo -e "${CYAN}━━━ Step 7: Finalize ━━━${NC}"

info "Seeding subscription plans (if not already seeded)..."
php artisan db:seed --class=PlanSeeder --force

info "Clearing and rebuilding caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

info "Setting permissions..."
chown -R "${WEB_USER}:${WEB_USER}" "$APP_DIR"
chmod -R 775 "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"

if systemctl is-active --quiet servora-queue 2>/dev/null; then
    info "Restarting queue worker..."
    systemctl restart servora-queue
fi

echo ""
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}  Servora SaaS setup complete!${NC}"
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo "  Summary:"
echo "    Domain:     *.${DOMAIN}"
echo "    App URL:    https://app.${DOMAIN}"
echo "    Marketing:  https://${DOMAIN}"
echo "    LMS:        https://{company}.${DOMAIN}/lms/login"
echo "    Register:   https://${DOMAIN}/register/start"
echo "    Webhook:    https://app.${DOMAIN}/webhooks/chipin"
echo ""
echo "  DNS Required (add these A records if not done):"
echo "    ${DOMAIN}       →  $(curl -s ifconfig.me)"
echo "    *.${DOMAIN}     →  $(curl -s ifconfig.me)"
echo ""
echo "  Next steps:"
echo "    1. Add wildcard DNS:  *.${DOMAIN} → $(curl -s ifconfig.me)"
echo "    2. Set up wildcard SSL (Cloudflare proxy or certbot DNS challenge)"
echo "    3. Register CHIP-IN webhook URL: https://app.${DOMAIN}/webhooks/chipin"
echo "    4. Test registration at: https://${DOMAIN}/register/start"
echo ""
