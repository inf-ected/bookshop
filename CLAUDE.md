# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

A Laravel 12 bookshop application running on PHP 8.3-fpm with Docker (nginx + MySQL 8 + Redis).

## Development Commands

### Docker (primary workflow)
```bash
docker compose up -d          # Start all services
docker compose down           # Stop services
docker compose exec php bash  # Shell into PHP container
docker compose exec php php artisan <command>
```

App is served at http://localhost:8080 (HTTP) and https://localhost:8443 (HTTPS).

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

## Architecture

- **PHP container**: `docker/php/Dockerfile` — multi-stage build (base → builder → app). Builder runs `composer install --no-dev`.
- **Nginx**: serves on 8080/8443, proxies to `bookshop_php` container. Config at `docker/nginx/default.conf`.
- **Database**: MySQL 8 with credentials from `.env` (`MYSQL_DATABASE`, `MYSQL_ROOT_PASSWORD`, `MYSQL_EXPOSE_PORT`, `MYSQL_PORT`).
- **Redis**: used for cache/session/queue.

## Testing

Tests run with SQLite in-memory (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`), so no Docker DB needed for tests.

Test suites: `tests/Unit/` and `tests/Feature/`.
