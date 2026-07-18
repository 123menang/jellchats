#!/bin/bash
set -euo pipefail

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; BOLD='\033[1m'; NC='\033[0m'
ok()   { echo -e "${GREEN}[OK]${NC} $1"; }
warn() { echo -e "${YELLOW}[!!]${NC} $1"; }
fail() { echo -e "${RED}[ER]${NC} $1"; exit 1; }
hr()   { echo -e "${CYAN}─────────────────────────────────────────${NC}"; }

echo ""
hr
echo -e "${BOLD}  JellChat Pro - Auto Deploy${NC}"
hr
echo ""

# =============================================
# AUTO-DETECT DOMAIN
# =============================================
detect_domain() {
    local h
    h=$(hostname -f 2>/dev/null || echo "")
    if [ -n "$h" ] && [[ "$h" == *.* ]] && [ "$h" != "localhost" ]; then
        echo "$h"; return
    fi
    for d in /etc/nginx/sites-available /etc/nginx/conf.d; do
        [ -d "$d" ] || continue
        for f in "$d"/*; do
            [ -f "$f" ] || continue
            local dn
            dn=$(grep -oP 'server_name\s+\K[^;]+' "$f" 2>/dev/null | head -1 | tr -d ' ')
            if [ -n "$dn" ] && [ "$dn" != "localhost" ] && [ "$dn" != "_" ]; then
                echo "$dn"; return
            fi
        done
    done
    for d in /etc/apache2/sites-available /etc/httpd/conf.d; do
        [ -d "$d" ] || continue
        for f in "$d"/*; do
            [ -f "$f" ] || continue
            local dn
            dn=$(grep -oP 'ServerName\s+\K\S+' "$f" 2>/dev/null | head -1)
            if [ -n "$dn" ] && [ "$dn" != "localhost" ]; then
                echo "$dn"; return
            fi
        done
    done
    local ip
    ip=$(curl -s --max-time 5 ifconfig.me 2>/dev/null || curl -s --max-time 5 api.ipify.org 2>/dev/null || echo "")
    [ -n "$ip" ] && { echo "$ip"; return; }
    echo "localhost"
}

DOMAIN=$(detect_domain)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TARGET_DIR="/var/www/livechat"
DB_PATH="$TARGET_DIR/storage/database/livechat.db"

# Detect OS
OS="unknown"
[ -f /etc/debian_version ] && OS="debian"
[ -f /etc/redhat-release ] && OS="redhat"
command -v apk &>/dev/null && OS="alpine"

echo -e "  Domain  : ${BOLD}$DOMAIN${NC}"
echo -e "  Target  : $TARGET_DIR"
echo -e "  OS      : $OS"
echo ""

# =============================================
# STEP 1: Install packages
# =============================================
echo -e "\n${CYAN}[1/11]${NC} Menginstall packages..."

install_pkgs() {
    export DEBIAN_FRONTEND=noninteractive
    if [ "$OS" = "debian" ]; then
        apt-get update -qq >/dev/null 2>&1
        apt-get install -y -qq php8.3 php8.3-sqlite3 php8.3-fpm php8.3-mbstring php8.3-curl nginx sqlite3 rsync certbot python3-certbot-nginx >/dev/null 2>&1 || \
        apt-get install -y -qq php8.2 php8.2-sqlite3 php8.2-fpm php8.2-mbstring php8.2-curl nginx sqlite3 rsync certbot python3-certbot-nginx >/dev/null 2>&1 || \
        apt-get install -y -qq php php-sqlite3 php-fpm php-mbstring php-curl nginx sqlite3 rsync certbot python3-certbot-nginx >/dev/null 2>&1
    elif [ "$OS" = "redhat" ]; then
        dnf install -y epel-release >/dev/null 2>&1 || yum install -y epel-release >/dev/null 2>&1 || true
        dnf install -y https://rpms.remirepo.net/enterprise/remi-release-8.rpm >/dev/null 2>&1 || \
        dnf install -y https://rpms.remirepo.net/enterprise/remi-release-9.rpm >/dev/null 2>&1 || true
        dnf module reset php -y >/dev/null 2>&1 || true
        dnf module enable php:remi-8.3 -y >/dev/null 2>&1 || true
        dnf install -y php php-sqlite3 php-mbstring php-curl nginx sqlite certbot python3-certbot-nginx rsync >/dev/null 2>&1 || \
        yum install -y php php-sqlite3 php-mbstring php-curl nginx sqlite certbot python3-certbot-nginx rsync >/dev/null 2>&1
    elif [ "$OS" = "alpine" ]; then
        apk update >/dev/null 2>&1
        apk add php83 php83-sqlite3 php83-fpm php83-mbstring php83-curl nginx sqlite certbot rsync >/dev/null 2>&1
    fi
}

NEED_INSTALL=0
command -v php8.3 &>/dev/null || command -v php8.2 &>/dev/null || command -v php8.1 &>/dev/null || command -v php &>/dev/null || NEED_INSTALL=1
command -v nginx &>/dev/null || NEED_INSTALL=1
command -v sqlite3 &>/dev/null || NEED_INSTALL=1

if [ $NEED_INSTALL -eq 1 ]; then
    warn "Package belum ada, menginstall..."
    install_pkgs
    ok "Package terinstall"
else
    ok "Semua package sudah ada"
fi

PHP=$(command -v php8.3 2>/dev/null || command -v php8.2 2>/dev/null || command -v php8.1 2>/dev/null || command -v php 2>/dev/null)
[ -z "$PHP" ] && fail "PHP tidak ditemukan"
PHP_VER=$($PHP -r 'echo PHP_MAJOR_VERSION*100+PHP_MINOR_VERSION;')
[ "$PHP_VER" -lt 801 ] && fail "PHP 8.1+ diperlukan"
ok "PHP $($PHP -v | head -1 | cut -d' ' -f1-2)"

for ext in sqlite3 pdo_sqlite mbstring; do
    $PHP -m | grep -qi "^${ext}$" || $PHP -m | grep -qi "^${ext} " || fail "Ekstensi '$ext' tidak ada"
done
ok "Ekstensi PHP lengkap"

HAS_COMPOSER=0
command -v composer &>/dev/null && HAS_COMPOSER=1

# =============================================
# STEP 2: Copy files
# =============================================
echo -e "\n${CYAN}[2/11]${NC} Menyalin file..."
mkdir -p "$TARGET_DIR"
rsync -a --delete \
    --exclude='storage/database/livechat.db' \
    --exclude='storage/database/livechat_*.db' \
    --exclude='storage/logs/*.log' \
    --exclude='uploads/' \
    --exclude='.git/' \
    --exclude='node_modules/' \
    --exclude='*.tar.gz' \
    --exclude='JELLPLAYCHATS.tar.gz' \
    --exclude='LIVECHAT-JELLPLAY.tar.gz' \
    "$SCRIPT_DIR/" "$TARGET_DIR/"
ok "File disalin"

# =============================================
# STEP 3: Setup izin
# =============================================
echo -e "\n${CYAN}[3/11]${NC} Mengatur izin..."
mkdir -p "$TARGET_DIR/storage/database" "$TARGET_DIR/storage/logs" "$TARGET_DIR/public/uploads" "$TARGET_DIR/uploads/avatars"
find "$TARGET_DIR" -type f -exec chmod 644 {} + 2>/dev/null || true
find "$TARGET_DIR" -type d -exec chmod 755 {} + 2>/dev/null || true
chmod +x "$TARGET_DIR/deploy.sh" "$TARGET_DIR/migrate.php" 2>/dev/null || true
chmod -R 775 "$TARGET_DIR/storage" "$TARGET_DIR/public/uploads" "$TARGET_DIR/uploads" 2>/dev/null || true
ok "Izin diatur"

# =============================================
# STEP 4: Cleanup
# =============================================
echo -e "\n${CYAN}[4/11]${NC} Cleanup..."
rm -f "$TARGET_DIR/phpinfo.php" "$TARGET_DIR/storage/logs/php-error.log" \
      "$TARGET_DIR/LIVECHAT-JELLPLAY.tar.gz" "$TARGET_DIR/JELLPLAYCHATS.tar.gz" \
      "$TARGET_DIR/install.sh" "$TARGET_DIR/.user.ini.bak" \
      "$TARGET_DIR/404.html" "$TARGET_DIR/502.html" \
      "$TARGET_DIR/migrations.php" "$TARGET_DIR/expired-chat-link.php" "$TARGET_DIR/database/app.db"
ok "Selesai"

# =============================================
# STEP 5: Composer
# =============================================
echo -e "\n${CYAN}[5/11]${NC} Autoloader..."
if [ $HAS_COMPOSER -eq 1 ]; then
    cd "$TARGET_DIR" && composer dump-autoload --optimize --no-dev 2>/dev/null && cd "$SCRIPT_DIR"
    ok "Autoload di-generate"
elif [ -f "$TARGET_DIR/vendor/autoload.php" ]; then
    ok "Autoload sudah ada"
else
    warn "vendor/autoload.php tidak ada"
fi

# =============================================
# STEP 6: Database
# =============================================
echo -e "\n${CYAN}[6/11]${NC} Database..."
rm -f "$TARGET_DIR/storage/database/livechat.db" "$TARGET_DIR/database/livechat.db"
[ -f "$TARGET_DIR/migrate.php" ] && $PHP "$TARGET_DIR/migrate.php" --fresh 2>&1 || true
if [ ! -f "$DB_PATH" ] && [ -f "$TARGET_DIR/database/schema.sql" ]; then
    sqlite3 "$DB_PATH" < "$TARGET_DIR/database/schema.sql"
fi
[ -f "$DB_PATH" ] && chmod 664 "$DB_PATH"
ok "Database siap"

# =============================================
# STEP 7: Set domain
# =============================================
echo -e "\n${CYAN}[7/11]${NC} Domain '$DOMAIN'..."
$PHP -r "
    \$pdo = new PDO('sqlite:$DB_PATH');
    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    \$stmt = \$pdo->prepare('UPDATE embed_codes SET site_url = ?');
    \$stmt->execute(['$DOMAIN']);
    echo \$stmt->rowCount() . ' updated' . PHP_EOL;
" 2>/dev/null || true
ok "Selesai"

# =============================================
# STEP 8: .user.ini
# =============================================
echo -e "\n${CYAN}[8/11]${NC} PHP config..."
cat > "$TARGET_DIR/.user.ini" << USERINI
open_basedir=$TARGET_DIR/:/tmp/
upload_max_filesize=10M
post_max_size=12M
max_execution_time=30
memory_limit=128M
USERINI
ok "Selesai"

# =============================================
# STEP 9: NGINX — FULL AUTO (Debian + CentOS + Alpine)
# =============================================
echo -e "\n${CYAN}[9/11]${NC} Nginx..."

# -- Cari PHP-FPM socket (EXHAUSTIVE) --
find_php_fpm_sock() {
    # Cari file socket apapun
    local found=""
    found=$(find /run /var/run /tmp -name "php*fpm*" -type s 2>/dev/null | head -1)
    [ -n "$found" ] && { echo "$found"; return; }

    # Cek port 9000
    if ss -tlnp 2>/dev/null | grep -q ':9000 ' || netstat -tlnp 2>/dev/null | grep -q ':9000 '; then
        echo "127.0.0.1:9000"; return
    fi

    # Cek www.conf / php-fpm.conf untuk listen directive
    for conf in /etc/php*/fpm/pool.d/www.conf /etc/php-fpm.d/www.conf /etc/php/*/fpm/pool.d/www.conf; do
        [ -f "$conf" ] || continue
        local listen
        listen=$(grep -oP '^\s*listen\s*=\s*\K\S+' "$conf" 2>/dev/null | head -1)
        if [ -n "$listen" ]; then
            if [[ "$listen" == /* ]] && [ -S "$listen" ]; then
                echo "$listen"; return
            elif [[ "$listen" == *:* ]]; then
                echo "$listen"; return
            fi
        fi
    done

    # Default paths
    for sock in \
        "/run/php/php-fpm.sock" \
        "/run/php-fpm/php-fpm.sock" \
        "/var/run/php-fpm/php-fpm.sock" \
        "/run/php/php8.3-fpm.sock" \
        "/run/php/php8.2-fpm.sock" \
        "/run/php/php8.1-fpm.sock"; do
        [ -S "$sock" ] && { echo "$sock"; return; }
    done

    echo ""
}

# -- Pastikan PHP-FPM RUNNING --
echo "  Checking PHP-FPM..."
PHP_FPM_RUNNING=0
if systemctl is-active php-fpm &>/dev/null; then
    PHP_FPM_RUNNING=1
    ok "php-fpm sudah running"
elif systemctl is-active php8.3-fpm &>/dev/null; then
    PHP_FPM_RUNNING=1
    ok "php8.3-fpm sudah running"
elif systemctl is-active php8.2-fpm &>/dev/null; then
    PHP_FPM_RUNNING=1
    ok "php8.2-fpm sudah running"
elif systemctl is-active php8.1-fpm &>/dev/null; then
    PHP_FPM_RUNNING=1
    ok "php8.1-fpm sudah running"
fi

if [ $PHP_FPM_RUNNING -eq 0 ]; then
    warn "PHP-FPM tidak running, mencoba start..."
    for svc in php-fpm php8.3-fpm php8.2-fpm php8.1-fpm; do
        systemctl start "$svc" 2>/dev/null && { ok "$svc started"; PHP_FPM_RUNNING=1; break; }
        service "$svc" start 2>/dev/null && { ok "$svc started"; PHP_FPM_RUNNING=1; break; }
    done
fi

[ $PHP_FPM_RUNNING -eq 0 ] && warn "PHP-FPM mungkin belum running. Cek manual: systemctl status php-fpm"

# -- Cari socket --
PHP_FPM_SOCK=$(find_php_fpm_sock)
if [ -z "$PHP_FPM_SOCK" ]; then
    warn "PHP-FPM socket tidak ditemukan, pakai default /run/php-fpm/php-fpm.sock"
    PHP_FPM_SOCK="/run/php-fpm/php-fpm.sock"
fi
ok "PHP-FPM socket: $PHP_FPM_SOCK"

# -- Tulis nginx config --
NGINX_CONF="/etc/nginx/conf.d/jellchat.conf"
[ -d /etc/nginx/sites-available ] && NGINX_CONF="/etc/nginx/sites-available/$DOMAIN"

# Pastikan direktori ada
mkdir -p "$(dirname "$NGINX_CONF")"
[ -f "$NGINX_CONF" ] && cp "$NGINX_CONF" "${NGINX_CONF}.bak" 2>/dev/null

cat > "$NGINX_CONF" << NGINXEOF
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN;

    root $TARGET_DIR;
    index index.php index.html;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml text/javascript image/svg+xml;
    gzip_min_length 1000;

    location / {
        try_files \$uri \$uri/ @rewrite;
    }

    location @rewrite {
        rewrite ^/(.*)$ /public/index.php?\$query_string;
    }

    location ~ \.php\$ {
        fastcgi_split_path_info ^(.+\.php)(/.+)\$;
        fastcgi_pass unix:$PHP_FPM_SOCK;
        fastcgi_param SCRIPT_FILENAME \$document_root/public\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ^~ /storage/ { internal; }
    location ^~ /database/ { internal; }
    location ^~ /includes/ { internal; }
    location ^~ /src/ { internal; }
    location ^~ /vendor/ { internal; }

    location ~ /\. { deny all; access_log off; log_not_found off; }
    location ~* \.(db|sqlite|sql|md|log|bak)$ { deny all; }

    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|webp|woff|woff2|mp3)$ {
        expires 7d;
        add_header Cache-Control "public, immutable";
        try_files \$uri =404;
    }

    location = /favicon.ico { log_not_found off; access_log off; }
}
NGINXEOF

# Symlink untuk Debian
if [ -d /etc/nginx/sites-available ] && [ -d /etc/nginx/sites-enabled ]; then
    ln -sf "$NGINX_CONF" "/etc/nginx/sites-enabled/$DOMAIN" 2>/dev/null || true
    rm -f /etc/nginx/sites-enabled/default 2>/dev/null || true
fi

rm -f /etc/nginx/conf.d/default.conf 2>/dev/null || true

# Test & reload
if nginx -t 2>&1; then
    systemctl reload nginx 2>/dev/null || service nginx reload 2>/dev/null || nginx -s reload 2>/dev/null || true
    ok "Nginx OK"
else
    warn "Nginx error! Cek: nginx -t"
fi

# =============================================
# STEP 10: SSL
# =============================================
echo -e "\n${CYAN}[10/11]${NC} SSL..."
if command -v certbot &>/dev/null; then
    certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos --email "admin@$DOMAIN" 2>&1 && \
        ok "SSL terinstall" || warn "SSL gagal - pastikan DNS pointing ke IP server"
else
    warn "Certbot tidak ada, skip"
fi

# =============================================
# STEP 11: Cron
# =============================================
echo -e "\n${CYAN}[11/11]${NC} Cron..."
CRON_TAG="# jellchat-$DOMAIN"
crontab -l 2>/dev/null | grep -v "$CRON_TAG" | crontab - 2>/dev/null || true
[ -f "$TARGET_DIR/migrate.php" ] && \
    (crontab -l 2>/dev/null; echo "0 3 * * * $PHP $TARGET_DIR/migrate.php >/dev/null 2>&1 $CRON_TAG") | crontab -
ok "Cron jam 3 pagi"

# =============================================
# SELESAI
# =============================================
echo ""
hr
echo -e "${GREEN}${BOLD}  DEPLOY SELESAI!${NC}"
hr
echo ""
echo -e "  Website : ${BOLD}http://$DOMAIN${NC}"
echo -e "  Login   : ${BOLD}http://$DOMAIN/login${NC}"
echo -e "  User    : ${BOLD}admin / admin${NC}"
echo ""
echo "  Embed widget:"
echo "  <script src=\"http://$DOMAIN/widget/widget.js\""
echo "          license=\"KEY\" async></script>"
echo ""
echo -e "  ${YELLOW}Ganti password default setelah login!${NC}"
echo ""
