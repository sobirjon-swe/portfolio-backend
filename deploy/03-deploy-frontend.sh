#!/usr/bin/env bash
# Build and deploy the React frontend. Run as the deploy user (NOT root).
#
# Prerequisites:
#   - 02-deploy-backend.sh has run
#   - the nginx configs are installed and certbot has issued the certificates,
#     so https://api.sobirjonswe.uz answers
#
# That last point matters: `npm run build` runs scripts/prerender.js, which
# fetches posts and projects from the API to emit a static HTML shell per route
# (correct <title>/description/OG tags for crawlers that do not run JS). If the
# API is unreachable the build still succeeds, but only the six static routes
# get shells — blog posts and project pages would share the generic preview.
#
# Usage:  bash 03-deploy-frontend.sh
set -euo pipefail

FRONTEND_DIR="/var/www/portfolio-frontend"
# SSH rather than HTTPS: the repo is private, and the server authenticates with
# a read-only deploy key whose private half never leaves the machine.
FRONTEND_REPO="git@github.com:sobirjon-swe/portfolio-frontend.git"
API_URL="https://api.sobirjonswe.uz/api/v1"

# Same as the backend: the directory already exists (dist/ is created for
# nginx before the first build), so initialise in place rather than cloning.
if [ ! -d "${FRONTEND_DIR}/.git" ]; then
  echo "==> Initialising portfolio-frontend from ${FRONTEND_REPO}"
  git init -q "${FRONTEND_DIR}"
else
  echo "==> Updating portfolio-frontend"
fi

# Set the remote every run, not just on first init: a checkout left over from an
# earlier attempt can be pointing at a URL this host cannot authenticate to.
git -C "${FRONTEND_DIR}" remote add origin "${FRONTEND_REPO}" 2>/dev/null \
  || git -C "${FRONTEND_DIR}" remote set-url origin "${FRONTEND_REPO}"

git -C "${FRONTEND_DIR}" fetch --depth 1 origin main
git -C "${FRONTEND_DIR}" checkout -f -B main origin/main

cd "${FRONTEND_DIR}"

# VITE_* variables are inlined at build time, so this file must exist before the
# build. The *.local suffix is already in the repo's .gitignore, so a future
# `git reset --hard` cannot wipe it and it can never be committed by mistake.
cat > .env.production.local <<ENV
VITE_API_URL=${API_URL}
ENV

echo "==> Checking the API is reachable (needed for prerendering)"
if ! curl -sf --max-time 10 "${API_URL}/posts" -o /dev/null; then
  echo "  ! ${API_URL} did not answer."
  echo "  ! The build will continue, but blog posts and projects will not get"
  echo "  ! their own link previews. Fix nginx/SSL and re-run this script."
fi

echo "==> Installing Node dependencies"
npm ci

echo "==> Building (vite build + prerender)"
PRERENDER_API_URL="${API_URL}" npm run build

echo
echo "==> Frontend deployed to ${FRONTEND_DIR}/dist"
echo "    Re-run this script after publishing a post so its shell and the"
echo "    sitemap pick it up (or let the GitHub Actions workflow do it)."
