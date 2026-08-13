#!/usr/bin/env bash
# One-time server provisioning for sobirjonswe.uz portfolio.
# Run once as root (or with sudo) on a fresh Ubuntu 22.04/24.04 server.
#
# Usage:  sudo bash 01-server-setup.sh
set -euo pipefail

DEPLOY_USER="${DEPLOY_USER:-deploy}"
PHP_VERSION="8.4"
DB_NAME="portfolio"
DB_USER="portfolio"

echo "==> Ensuring there is enough swap for the frontend build"
# The Vite build compiles ~2400 modules and peaks around 1-1.5 GB. This box has
# under 1 GB of RAM, so without swap `npm run build` is killed by the OOM
# reaper — with a confusing "Killed" and no other explanation.
CURRENT_SWAP_MB=$(free -m | awk '/^Swap:/{print $2}')
if [ "${CURRENT_SWAP_MB:-0}" -lt 2048 ]; then
  echo "    swap is ${CURRENT_SWAP_MB}MB, growing to 2048MB"
  swapoff -a || true
  rm -f /swapfile
  # fallocate can produce a sparse file that swapon rejects; dd is reliable.
  dd if=/dev/zero of=/swapfile bs=1M count=2048 status=none
  chmod 600 /swapfile
  mkswap /swapfile >/dev/null
  swapon /swapfile
  grep -q '^/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
  # Keep the kernel reluctant to swap under normal load, but allow it under
  # build pressure.
  sysctl -q vm.swappiness=20
  grep -q '^vm.swappiness' /etc/sysctl.conf || echo 'vm.swappiness=20' >> /etc/sysctl.conf
else
  echo "    swap already ${CURRENT_SWAP_MB}MB, leaving it alone"
fi

echo "==> Updating package index"
apt-get update -y

echo "==> Installing base packages"
apt-get install -y \
  nginx git curl unzip ca-certificates lsb-release gnupg software-properties-common \
  postgresql postgresql-contrib

echo "==> Adding PHP ${PHP_VERSION} repository (ondrej/php)"
add-apt-repository -y ppa:ondrej/php
apt-get update -y

echo "==> Installing PHP ${PHP_VERSION} + extensions required by Laravel 13"
apt-get install -y \
  "php${PHP_VERSION}-fpm" \
  "php${PHP_VERSION}-cli" \
  "php${PHP_VERSION}-pgsql" \
  "php${PHP_VERSION}-mbstring" \
  "php${PHP_VERSION}-xml" \
  "php${PHP_VERSION}-curl" \
  "php${PHP_VERSION}-zip" \
  "php${PHP_VERSION}-bcmath" \
  "php${PHP_VERSION}-intl" \
  "php${PHP_VERSION}-gd"

echo "==> Installing Composer"
if ! command -v composer >/dev/null 2>&1; then
  curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php
  php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
  rm -f /tmp/composer-setup.php
fi

echo "==> Installing Node.js 22 LTS"
if ! command -v node >/dev/null 2>&1; then
  curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
  apt-get install -y nodejs
fi

echo "==> Installing certbot (Let's Encrypt)"
apt-get install -y certbot python3-certbot-nginx

echo "==> Creating deploy user '${DEPLOY_USER}' (if missing)"
if ! id -u "${DEPLOY_USER}" >/dev/null 2>&1; then
  adduser --disabled-password --gecos "" "${DEPLOY_USER}"
fi
usermod -aG www-data "${DEPLOY_USER}"

echo "==> Creating web roots"
mkdir -p /var/www/portfolio-backend /var/www/portfolio-frontend
chown -R "${DEPLOY_USER}:www-data" /var/www/portfolio-backend /var/www/portfolio-frontend
# setgid so anything Laravel writes later (logs, caches) inherits the www-data
# group. Without it php-fpm creates files the deploy user cannot touch, and the
# next deploy dies trying to chmod them.
chmod g+s /var/www/portfolio-backend /var/www/portfolio-frontend

echo
echo "==> PostgreSQL: creating database '${DB_NAME}' and role '${DB_USER}'"
DB_PASSWORD="$(openssl rand -base64 24 | tr -d '/+=' | head -c 32)"
sudo -u postgres psql -v ON_ERROR_STOP=1 <<SQL
DO \$\$
BEGIN
  IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = '${DB_USER}') THEN
    CREATE ROLE ${DB_USER} LOGIN PASSWORD '${DB_PASSWORD}';
  END IF;
END
\$\$;
SQL
sudo -u postgres psql -tc "SELECT 1 FROM pg_database WHERE datname = '${DB_NAME}'" \
  | grep -q 1 || sudo -u postgres createdb -O "${DB_USER}" "${DB_NAME}"
sudo -u postgres psql -v ON_ERROR_STOP=1 -c \
  "GRANT ALL PRIVILEGES ON DATABASE ${DB_NAME} TO ${DB_USER};"
# Laravel needs to create tables in the public schema.
sudo -u postgres psql -v ON_ERROR_STOP=1 -d "${DB_NAME}" -c \
  "GRANT ALL ON SCHEMA public TO ${DB_USER};"

echo "==> Raising PHP upload limits above the application's own"
# PHP ships upload_max_filesize=2M, which is BELOW the 5 MB the app allows
# (config/images.php). PHP discards oversized uploads before Laravel ever runs,
# so the user would just see "attach a file" with no explanation. These must
# stay >= images.max_kilobytes, and nginx's client_max_body_size >= these.
cat > "/etc/php/${PHP_VERSION}/fpm/conf.d/99-portfolio.ini" <<'PHPINI'
upload_max_filesize = 12M
post_max_size = 16M
max_file_uploads = 20
PHPINI

echo "==> Installing the Laravel scheduler cron entry"
# Drives routes/console.php — currently the daily page-views:prune job.
cat > /etc/cron.d/portfolio-scheduler <<CRON
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
* * * * * ${DEPLOY_USER} cd /var/www/portfolio-backend && php artisan schedule:run >> /dev/null 2>&1
CRON
chmod 0644 /etc/cron.d/portfolio-scheduler

echo "==> Allowing '${DEPLOY_USER}' to reload php-fpm without a password"
# The deploy workflow reloads php-fpm to drop stale OPcache entries. Grant that
# one command only — not blanket sudo.
cat > "/etc/sudoers.d/${DEPLOY_USER}-deploy" <<SUDOERS
${DEPLOY_USER} ALL=(root) NOPASSWD: /bin/systemctl reload php${PHP_VERSION}-fpm, /usr/bin/systemctl reload php${PHP_VERSION}-fpm, /bin/chgrp -R www-data /var/www/portfolio-backend/storage, /bin/chgrp -R www-data /var/www/portfolio-backend/bootstrap/cache
SUDOERS
chmod 0440 "/etc/sudoers.d/${DEPLOY_USER}-deploy"
visudo -cf "/etc/sudoers.d/${DEPLOY_USER}-deploy"

echo "==> Enabling services"
systemctl enable --now "php${PHP_VERSION}-fpm" nginx postgresql

echo "==> Configuring UFW firewall (SSH + HTTP + HTTPS)"
if command -v ufw >/dev/null 2>&1; then
  ufw allow OpenSSH || true
  ufw allow 'Nginx Full' || true
  # Enable non-interactively; safe because SSH is already allowed above.
  ufw --force enable || true
fi

echo
echo "======================================================================"
echo " Setup complete."
echo
echo " SAVE THIS DATABASE PASSWORD — it goes into the backend .env file:"
echo
echo "   DB_CONNECTION=pgsql"
echo "   DB_HOST=127.0.0.1"
echo "   DB_PORT=5432"
echo "   DB_DATABASE=${DB_NAME}"
echo "   DB_USERNAME=${DB_USER}"
echo "   DB_PASSWORD=${DB_PASSWORD}"
echo
echo " Next: copy the nginx configs, then run 02-first-deploy.sh"
echo "======================================================================"
