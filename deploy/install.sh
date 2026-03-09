#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# Servora — One-click installer for Ubuntu (DigitalOcean)
#
# Supported: Ubuntu 22.04, 24.04, 24.10, 25.04, 25.10+
#
# Usage:
#   1. Spin up an Ubuntu droplet on DigitalOcean
#   2. SSH in as root
#   3. Upload or clone this repo, then run:
#        bash deploy/install.sh
#
# What it installs:
#   - Nginx, PHP 8.x (FPM), MySQL 8, Node 22, Composer
#   - Certbot (Let's Encrypt SSL — optional)
#   - Configures the app, runs migrations + seeder
#
# Default admin login after install:
#   Email:    admin@servora.test
#   Password: password
# ─────────────────────────────────────────────────────────────────────────────
set -euo pipefail

# ── Colours ──────────────────────────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; CYAN='\033[0;36m'; YELLOW='\033[1;33m'; NC='\033[0m'
info()  { echo -e "${CYAN}[INFO]${NC}  $*"; }
ok()    { echo -e "${GREEN}[OK]${NC}    $*"; }
warn()  { echo -e "${YELLOW}[WARN]${NC}  $*"; }
fail()  { echo -e "${RED}[FAIL]${NC}  $*"; exit 1; }

# ── Must be root ─────────────────────────────────────────────────────────────
[[ $EUID -eq 0 ]] || fail "Run this script as root (sudo bash deploy/install.sh)"

# ── Collect config ───────────────────────────────────────────────────────────
echo ""
echo -e "${CYAN}═══════════════════════════════════════════════════════════${NC}"
echo -e "${CYAN}           Servora — Server Setup & Deployment            ${NC}"
echo -e "${CYAN}═══════════════════════════════════════════════════════════${NC}"
echo ""

read -rp "Domain name (e.g. app.servora.com, or IP for testing): " DOMAIN
DOMAIN=${DOMAIN:-$(curl -s ifconfig.me)}

read -rp "MySQL database name [servora]: " DB_NAME
DB_NAME=${DB_NAME:-servora}

read -rp "MySQL username [servora]: " DB_USER
DB_USER=${DB_USER:-servora}

DB_PASS=$(openssl rand -base64 18 | tr -d '/+=' | head -c 20)
echo -e "  Generated MySQL password: ${YELLOW}${DB_PASS}${NC} (save this!)"

read -rp "GitHub repo URL [https://github.com/zestmy/Servora.git]: " REPO_URL
REPO_URL=${REPO_URL:-https://github.com/zestmy/Servora.git}

read -rp "Git branch [main]: " GIT_BRANCH
GIT_BRANCH=${GIT_BRANCH:-main}

read -rp "Enable SSL with Let's Encrypt? (y/N): " ENABLE_SSL
ENABLE_SSL=${ENABLE_SSL:-n}

if [[ "${ENABLE_SSL,,}" == "y" ]]; then
    read -rp "Email for SSL certificate: " SSL_EMAIL
fi

APP_DIR="/var/www/servora"
WEB_USER="www-data"

echo ""
info "Starting installation..."
echo ""

# ── 1. System packages ──────────────────────────────────────────────────────
info "Updating system packages..."
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get upgrade -y -qq

info "Installing prerequisites..."
apt-get install -y -qq software-properties-common curl git unzip acl ufw

# ── 2. PHP (auto-detect best version) ────────────────────────────────────────
# Try to find PHP 8.3 or 8.4 in default repos first; fall back to ondrej PPA on LTS
info "Detecting PHP version..."

# Check what's available in default repos
PHP_VER=""
for v in 8.4 8.3; do
    if apt-cache show "php${v}-fpm" > /dev/null 2>&1; then
        PHP_VER="$v"
        break
    fi
done

# If nothing found in default repos, try the ondrej PPA (works on LTS releases)
if [[ -z "$PHP_VER" ]]; then
    info "Adding ondrej/php PPA..."
    if add-apt-repository -y ppa:ondrej/php > /dev/null 2>&1; then
        apt-get update -qq
        for v in 8.4 8.3; do
            if apt-cache show "php${v}-fpm" > /dev/null 2>&1; then
                PHP_VER="$v"
                break
            fi
        done
    fi
fi

[[ -n "$PHP_VER" ]] || fail "Could not find PHP 8.3 or 8.4. Please install PHP manually."

info "Installing PHP ${PHP_VER}..."
apt-get install -y -qq \
    "php${PHP_VER}-fpm" "php${PHP_VER}-cli" "php${PHP_VER}-mysql" \
    "php${PHP_VER}-mbstring" "php${PHP_VER}-xml" "php${PHP_VER}-bcmath" \
    "php${PHP_VER}-curl" "php${PHP_VER}-zip" "php${PHP_VER}-gd" \
    "php${PHP_VER}-intl" "php${PHP_VER}-readline"
ok "PHP ${PHP_VER} installed"

# Tune PHP for production
PHP_INI="/etc/php/${PHP_VER}/fpm/php.ini"
sed -i 's/^upload_max_filesize.*/upload_max_filesize = 20M/' "$PHP_INI"
sed -i 's/^post_max_size.*/post_max_size = 25M/' "$PHP_INI"
sed -i 's/^memory_limit.*/memory_limit = 256M/' "$PHP_INI"
sed -i 's/^max_execution_time.*/max_execution_time = 60/' "$PHP_INI"

systemctl restart "php${PHP_VER}-fpm"
ok "PHP-FPM configured"

# ── 3. MySQL 8 ──────────────────────────────────────────────────────────────
info "Installing MySQL 8..."
apt-get install -y -qq mysql-server

systemctl start mysql
systemctl enable mysql

mysql -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mysql -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"
ok "MySQL configured — database: ${DB_NAME}, user: ${DB_USER}"

# ── 4. Nginx ────────────────────────────────────────────────────────────────
info "Installing Nginx..."
apt-get install -y -qq nginx

cat > /etc/nginx/sites-available/servora <<NGINX
server {
    listen 80;
    server_name ${DOMAIN};
    root ${APP_DIR}/public;
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
        fastcgi_pass unix:/run/php/php${PHP_VER}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 60;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX

ln -sf /etc/nginx/sites-available/servora /etc/nginx/sites-enabled/servora
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx
ok "Nginx configured for ${DOMAIN}"

# ── 5. Node.js 22 ───────────────────────────────────────────────────────────
info "Installing Node.js 22..."
curl -fsSL https://deb.nodesource.com/setup_22.x | bash - > /dev/null 2>&1
apt-get install -y -qq nodejs
ok "Node $(node -v) installed"

# ── 6. Composer ─────────────────────────────────────────────────────────────
info "Installing Composer..."
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer > /dev/null 2>&1
ok "Composer installed"

# ── 7. Firewall ─────────────────────────────────────────────────────────────
info "Configuring firewall..."
ufw allow OpenSSH > /dev/null
ufw allow 'Nginx Full' > /dev/null
echo "y" | ufw enable > /dev/null 2>&1
ok "Firewall enabled (SSH + HTTP/HTTPS)"

# ── 8. Clone & configure app ────────────────────────────────────────────────
info "Cloning application..."
if [[ -d "$APP_DIR" ]]; then
    warn "Directory ${APP_DIR} exists — pulling latest..."
    cd "$APP_DIR"
    git fetch origin
    git reset --hard "origin/${GIT_BRANCH}"
else
    git clone -b "$GIT_BRANCH" "$REPO_URL" "$APP_DIR"
    cd "$APP_DIR"
fi
ok "Code deployed to ${APP_DIR}"

# ── 9. Environment file ─────────────────────────────────────────────────────
info "Configuring environment..."
cp .env.example .env

# Update .env values
sed -i "s|APP_NAME=Laravel|APP_NAME=Servora|" .env
sed -i "s|APP_ENV=local|APP_ENV=production|" .env
sed -i "s|APP_DEBUG=true|APP_DEBUG=false|" .env
sed -i "s|APP_URL=http://localhost|APP_URL=http://${DOMAIN}|" .env

# Switch from SQLite to MySQL
sed -i "s|DB_CONNECTION=sqlite|DB_CONNECTION=mysql|" .env
sed -i "s|# DB_HOST=127.0.0.1|DB_HOST=127.0.0.1|" .env
sed -i "s|# DB_PORT=3306|DB_PORT=3306|" .env
sed -i "s|# DB_DATABASE=laravel|DB_DATABASE=${DB_NAME}|" .env
sed -i "s|# DB_USERNAME=root|DB_USERNAME=${DB_USER}|" .env
sed -i "s|# DB_PASSWORD=|DB_PASSWORD=${DB_PASS}|" .env

ok "Environment configured"

# ── 10. Install dependencies ────────────────────────────────────────────────
info "Installing PHP dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction --quiet
ok "Composer dependencies installed"

info "Installing Node dependencies..."
npm ci --silent 2>/dev/null || npm install --silent
ok "Node dependencies installed"

# ── 11. Build frontend ──────────────────────────────────────────────────────
info "Building frontend assets..."
npm run build
ok "Vite build complete"

# ── 12. Laravel setup ────────────────────────────────────────────────────────
info "Running Laravel setup..."
php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
ok "Laravel configured — migrations, seeder, caches"

# ── 13. Permissions ─────────────────────────────────────────────────────────
info "Setting file permissions..."
chown -R "${WEB_USER}:${WEB_USER}" "$APP_DIR"
chmod -R 755 "$APP_DIR"
chmod -R 775 "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"
ok "Permissions set"

# ── 14. SSL (optional) ──────────────────────────────────────────────────────
if [[ "${ENABLE_SSL,,}" == "y" ]]; then
    info "Setting up SSL with Let's Encrypt..."
    apt-get install -y -qq certbot python3-certbot-nginx
    certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos -m "$SSL_EMAIL" --redirect
    sed -i "s|APP_URL=http://${DOMAIN}|APP_URL=https://${DOMAIN}|" "${APP_DIR}/.env"
    cd "$APP_DIR" && php artisan config:cache
    ok "SSL certificate installed"
fi

# ── 15. Queue worker (systemd) ──────────────────────────────────────────────
info "Setting up queue worker..."
cat > /etc/systemd/system/servora-queue.service <<UNIT
[Unit]
Description=Servora Queue Worker
After=network.target mysql.service

[Service]
User=${WEB_USER}
Group=${WEB_USER}
WorkingDirectory=${APP_DIR}
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
UNIT

systemctl daemon-reload
systemctl enable servora-queue
systemctl start servora-queue
ok "Queue worker running"

# ── 16. Scheduler cron ──────────────────────────────────────────────────────
info "Setting up scheduler..."
CRON_LINE="* * * * * cd ${APP_DIR} && php artisan schedule:run >> /dev/null 2>&1"
(crontab -u "${WEB_USER}" -l 2>/dev/null | grep -v "schedule:run"; echo "$CRON_LINE") | crontab -u "${WEB_USER}" -
ok "Laravel scheduler cron installed"

# ── Done ─────────────────────────────────────────────────────────────────────
echo ""
echo -e "${GREEN}═══════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}           Installation Complete!                         ${NC}"
echo -e "${GREEN}═══════════════════════════════════════════════════════════${NC}"
echo ""
echo -e "  URL:       ${CYAN}http://${DOMAIN}${NC}"
echo -e "  Admin:     ${CYAN}admin@servora.test${NC}"
echo -e "  Password:  ${CYAN}password${NC}"
echo ""
echo -e "  DB Name:   ${YELLOW}${DB_NAME}${NC}"
echo -e "  DB User:   ${YELLOW}${DB_USER}${NC}"
echo -e "  DB Pass:   ${YELLOW}${DB_PASS}${NC}"
echo ""
echo -e "  App Dir:   ${APP_DIR}"
echo ""
echo -e "  ${RED}IMPORTANT: Change the admin password after first login!${NC}"
echo -e "  ${RED}IMPORTANT: Save the database credentials above!${NC}"
echo ""
