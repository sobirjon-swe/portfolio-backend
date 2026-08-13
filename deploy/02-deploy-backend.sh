#!/usr/bin/env bash
# Deploy the Laravel API. Run as the deploy user (NOT root).
#
# Prerequisites:
#   - 01-server-setup.sh has run
#   - /var/www/portfolio-backend/.env exists (from backend.env.production)
#
# Run this BEFORE the frontend: the frontend build queries this API to
# prerender blog-post and project pages.
#
# Usage:  bash 02-deploy-backend.sh
set -euo pipefail

BACKEND_DIR="/var/www/portfolio-backend"
BACKEND_REPO="https://github.com/sobirjon-swe/portfolio-backend.git"

# The directory already exists (the setup script created it, and .env was
# written into it), so `git clone` would refuse. Initialise in place instead:
# checkout -f replaces tracked files and leaves untracked ones — .env included —
# exactly where they are.
if [ ! -d "${BACKEND_DIR}/.git" ]; then
  echo "==> Initialising portfolio-backend from ${BACKEND_REPO}"
  git init -q "${BACKEND_DIR}"
else
  echo "==> Updating portfolio-backend"
fi

# Set the remote every run, not just on first init: a checkout left over from an
# earlier attempt can be pointing at a URL this host cannot authenticate to.
git -C "${BACKEND_DIR}" remote add origin "${BACKEND_REPO}" 2>/dev/null \
  || git -C "${BACKEND_DIR}" remote set-url origin "${BACKEND_REPO}"

git -C "${BACKEND_DIR}" fetch --depth 1 origin main
git -C "${BACKEND_DIR}" checkout -f -B main origin/main

cd "${BACKEND_DIR}"

if [ ! -f .env ]; then
  echo "ERROR: ${BACKEND_DIR}/.env is missing. Create it from backend.env.production first." >&2
  exit 1
fi
chmod 600 .env

echo "==> Installing PHP dependencies (production)"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Generate APP_KEY only if it is still empty — regenerating it would invalidate
# every existing encrypted value, session and hashed analytics IP.
if grep -qE '^APP_KEY=$' .env; then
  echo "==> Generating APP_KEY"
  php artisan key:generate --force
fi

echo "==> Running migrations"
php artisan migrate --force

# Publishes storage/app/public as public/storage so uploaded images are
# reachable at /storage/... . Safe to re-run.
if [ ! -e public/storage ]; then
  echo "==> Linking public storage"
  php artisan storage:link
fi

echo "==> Caching config, routes and views"
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Fixing writable paths"
# php-fpm runs as www-data and must be able to write logs and the cache.
# One path per call: the sudoers rule grants each directory separately, and a
# single command listing both would not match either entry.
sudo chgrp -R www-data "${BACKEND_DIR}/storage"
sudo chgrp -R www-data "${BACKEND_DIR}/bootstrap/cache"

# Best-effort: php-fpm owns the log files it creates, so this cannot touch them
# and must not abort the deploy. The directories carry setgid (set during server
# setup), which is what actually keeps new files group-writable.
chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || true

echo
echo "==> Backend deployed to ${BACKEND_DIR}"
echo "    Next: install the nginx configs, run certbot, then 03-deploy-frontend.sh"
echo
echo "    Do NOT run 'php artisan db:seed' here — the seeder creates"
echo "    test@example.com with the default factory password. Create the admin"
echo "    by hand (see README step 6)."
