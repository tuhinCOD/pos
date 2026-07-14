#!/bin/bash
set -e

DOMAIN="${1:-_}"
APP_DIR="/var/www/inventory"
DB_NAME="inventory"
DB_USER="inventory"

echo "=== VPS Deployment: Laravel + Vite ==="

# --- 1. System packages ---
echo "[1/10] Installing system packages..."
apt update
apt install -y software-properties-common curl git unzip
add-apt-repository -y ppa:ondrej/php
apt update

# --- 2. PHP 8.3 + extensions ---
echo "[2/10] Installing PHP 8.3..."
apt install -y php8.3 php8.3-fpm php8.3-cli php8.3-pgsql php8.3-mbstring \
    php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath php8.3-gd \
    php8.3-redis php8.3-intl php8.3-dom php8.3-tokenizer

# --- 3. PostgreSQL ---
echo "[3/10] Installing PostgreSQL..."
apt install -y postgresql postgresql-contrib
sudo -u postgres psql -tc "SELECT 1 FROM pg_roles WHERE rolname='$DB_USER'" | grep -q 1 || \
    sudo -u postgres psql -c "CREATE USER $DB_USER WITH PASSWORD 'inventory_secret';"
sudo -u postgres psql -tc "SELECT 1 FROM pg_database WHERE datname='$DB_NAME'" | grep -q 1 || \
    sudo -u postgres psql -c "CREATE DATABASE $DB_NAME OWNER $DB_USER;"
sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE $DB_NAME TO $DB_USER;"

# --- 4. Redis ---
echo "[4/10] Installing Redis..."
apt install -y redis-server
systemctl enable redis-server
systemctl start redis-server

# --- 5. Nginx ---
echo "[5/10] Installing Nginx..."
apt install -y nginx

# --- 6. Node.js 22 LTS ---
echo "[6/10] Installing Node.js 22..."
curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
apt install -y nodejs

# --- 7. Composer ---
echo "[7/10] Installing Composer..."
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# --- 8. Deploy app ---
echo "[8/10] Deploying application..."
if [ ! -d "$APP_DIR" ]; then
    echo "  Copy project files to $APP_DIR first, then re-run this script."
    echo "  Example: scp -r ./inventory root@YOUR_VPS_IP:/var/www/"
    mkdir -p "$APP_DIR"
fi

cd "$APP_DIR"

if [ -f ".env" ]; then
    echo "  .env already exists, skipping copy"
else
    cp .env.example .env
fi

# --- 9. Install dependencies & build ---
echo "[9/10] Installing dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build

# --- 10. Laravel setup ---
echo "[10/10] Configuring Laravel..."
php artisan key:generate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Set permissions
chown -R www-data:www-data "$APP_DIR"
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

# --- Nginx site ---
echo "Configuring Nginx..."
cp "$APP_DIR/deploy/nginx.conf" /etc/nginx/sites-available/inventory
ln -sf /etc/nginx/sites-available/inventory /etc/nginx/sites-enabled/inventory
rm -f /etc/nginx/sites-enabled/default

# Update server_name if domain provided
if [ "$DOMAIN" != "_" ]; then
    sed -i "s/server_name _;/server_name $DOMAIN www.$DOMAIN;/" /etc/nginx/sites-available/inventory
fi

nginx -t
systemctl reload nginx

# --- PHP-FPM ---
systemctl enable php8.3-fpm
systemctl restart php8.3-fpm

# --- Horizon (queue worker) ---
echo "Setting up Horizon..."
php artisan horizon:install 2>/dev/null || true

cat > /etc/systemd/system/horizon.service <<'EOF'
[Unit]
Description=Laravel Horizon
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/inventory
ExecStart=/usr/bin/php artisan horizon
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable horizon
systemctl restart horizon

# --- Reverb (WebSocket) ---
cat > /etc/systemd/system/reverb.service <<'EOF'
[Unit]
Description=Laravel Reverb WebSocket Server
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/inventory
ExecStart=/usr/bin/php artisan reverb:start
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable reverb
systemctl restart reverb

echo ""
echo "=== Deployment Complete ==="
echo "  URL: http://$(hostname -I | awk '{print $1}')"
echo "  DB:  $DB_NAME (user: $DB_USER, pass: inventory_secret)"
echo ""
echo "  Don't forget to:"
echo "  1. Update .env with your APP_URL, DB_PASSWORD, REVERB keys"
echo "  2. Run: php artisan migrate --force"
echo "  3. Set up SSL: certbot --nginx -d $DOMAIN"
