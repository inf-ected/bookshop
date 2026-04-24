# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

A Laravel 12 bookshop application running on PHP 8.4-fpm with Docker (nginx + MySQL 8 + Redis + MinIO).

## Development Commands

### Docker (primary workflow)
```bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d   # Start all services (incl. Vite dev server)
docker compose down                                                      # Stop services
docker compose -f docker-compose.yml -f docker-compose.dev.yml exec php bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml exec php php artisan <command>
```

> **Note:** `docker-compose.dev.yml` must always be passed explicitly — it is not named `override` so it is not auto-loaded.

App is served at http://localhost:8080 (HTTP) and https://localhost:8443 (HTTPS).
Vite HMR dev server: http://localhost:5173 (started automatically by the `node` service).
MinIO S3 API: http://localhost:9000 — MinIO console: http://localhost:9001

**Frontend assets in Docker:**
- **Dev**: the `node` container runs `npm run dev` (Vite HMR) — no Node on the host needed.
- **Prod build**: `docker compose build` triggers the `node-builder` stage (`npm ci && npm run build`) — assets are baked into the image.
- **No manual `npm run build` needed** — never run npm commands on the host.

### Without Docker
```bash
composer run setup   # Install deps, copy .env, generate key, migrate, build assets
composer run dev     # Start server + queue + logs + vite concurrently
composer run test    # Clear config + run PHPUnit
```

### Individual commands
```bash
php artisan test                          # Run all tests
php artisan test --filter=TestName        # Run a single test
php artisan test tests/Unit/ExampleTest.php  # Run a specific file
./vendor/bin/pint                         # Run Laravel Pint (code style fixer)
```

### Composer scripts
```bash
composer cs-fix    # Fix code style with Pint
composer cs-check  # Check code style (no changes)
composer analyse   # Run PHPStan static analysis
```

## Architecture

- **PHP container**: `docker/php/Dockerfile` — multi-stage build (node-builder → base → builder → app). `node-builder` runs `npm ci && npm build` (Node 22 Alpine); compiled assets are copied into the `app` and `nginx-static` stages. No Node required on the host.
- **Nginx**: serves on 8080/8443, proxies to `bookshop_php` container. Config at `docker/nginx/default.conf`.
- **Database**: MySQL 8 with credentials from `.env` (`MYSQL_DATABASE`, `MYSQL_ROOT_PASSWORD`, `MYSQL_EXPOSE_PORT`).
- **Redis**: used for cache/session/queue.
- **MinIO**: S3-compatible local storage. Two buckets: `bookshop-public` (covers) and `bookshop-private` (book files: epub, fb2, docx). Disks: `s3-public`, `s3-private`, `s3-private-presign` in `config/filesystems.php`. `s3-private-presign` overrides the endpoint to `S3_TEMPORARY_URL_BASE` for presigned URL generation (needed for local MinIO).
- **Queue worker** (`bookshop_queue`, dev only): runs `php artisan queue:work` in a separate container. Defined in `docker-compose.dev.yml`.
- **Stripe CLI** (`bookshop_stripe`, dev only): forwards Stripe webhook events to `http://nginx/webhooks/stripe`. Defined in `docker-compose.dev.yml`.
- **Static analysis**: `composer analyse` (PHPStan level 5 via `phpstan.neon`, includes Larastan + banned-code extension).

## Production Deployment

Production uses `docker-compose.yml` + `docker-compose.prod.yml` (no dev services, ports 80/443, real SSL certs mounted).

```bash
# On VPS (as deploy user)
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec php php artisan migrate --force
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec php php artisan config:cache
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec php php artisan queue:restart
```

Full step-by-step plan: **`docs/deployment-plan.md`**

**Project status**: All phases (1–13) complete. New work comes from `docs/backlog.md`.

## Testing

Tests run with SQLite in-memory (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`), so no Docker DB needed for tests.

Test suites: `tests/Unit/` and `tests/Feature/`.
