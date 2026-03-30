#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# deploy.sh — Build and deploy the bookshop application
#
# Usage:
#   ./scripts/deploy.sh dev    # start / rebuild development environment
#   ./scripts/deploy.sh prod   # deploy production environment
#
# Requirements:
#   dev  — .env file present (copy from .env.docker and fill in APP_KEY)
#   prod — .env file present with production values (or .env.production)
# ─────────────────────────────────────────────────────────────────────────────
set -euo pipefail

ENV=${1:-dev}

# Load .env so shell variables (e.g. MYSQL_ROOT_PASSWORD) are available
# to this script. set -a exports every sourced variable automatically.
if [[ -f ".env" ]]; then
    set -a
    # shellcheck source=../.env
    source .env
    set +a
fi
COMPOSE="docker compose -f docker-compose.yml -f docker-compose.${ENV}.yml"

# ── Validate environment ──────────────────────────────────────────────────────
if [[ "$ENV" != "dev" && "$ENV" != "prod" ]]; then
    echo "Usage: $0 [dev|prod]" >&2
    exit 1
fi

if [[ ! -f ".env" ]]; then
    echo "Error: .env file not found." >&2
    echo "  For dev:  cp .env.docker .env  (then set APP_KEY)" >&2
    echo "  For prod: create .env with production values" >&2
    exit 1
fi

echo "==> Deploying [$ENV] environment..."

# ── Build images ──────────────────────────────────────────────────────────────
echo "==> Building images..."
$COMPOSE build

# ── Start containers ──────────────────────────────────────────────────────────
echo "==> Starting containers..."
$COMPOSE up -d --remove-orphans

# ── Run migrations ────────────────────────────────────────────────────────────
# Note: no explicit DB wait needed — the compose healthcheck on the db service
# already ensures MySQL is accepting connections before php starts.
echo "==> Running migrations..."
$COMPOSE exec php php artisan migrate --force

# ── Environment-specific steps ───────────────────────────────────────────────
if [[ "$ENV" == "dev" ]]; then
    echo "==> Seeding dev data (idempotent)..."
    $COMPOSE exec php php artisan db:seed --class=DevSeeder
fi

if [[ "$ENV" == "prod" ]]; then
    echo "==> Caching configuration, routes, and views..."
    $COMPOSE exec php php artisan config:cache
    $COMPOSE exec php php artisan route:cache
    $COMPOSE exec php php artisan view:cache

    echo "==> Restarting queue workers..."
    $COMPOSE exec php php artisan queue:restart
fi

echo ""
echo "==> Done. Application is up."
echo "    HTTP:  http://localhost:8080"
echo "    HTTPS: https://localhost:8443"
