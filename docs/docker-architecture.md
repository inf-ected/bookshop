# Docker Architecture

This document describes the complete Docker setup for the Bookshop Laravel application. It is intended to let a new contributor understand and run the project without scanning the repository manually.

---

## 1. Overview

The application is a **Laravel 12** project running on **PHP 8.4-FPM** with four containers:

| Container | Image | Purpose |
|---|---|---|
| `bookshop_nginx` | `nginx:alpine` (dev) / built (prod) | Reverse proxy, serves static assets |
| `bookshop_php` | Custom build (`docker/php/Dockerfile`) | PHP-FPM application server |
| `bookshop_db` | `mysql:8` | Persistent relational database |
| `bookshop_redis` | `redis:alpine` | Sessions, cache, and queues |

Host ports:

| Port | Target | Notes |
|---|---|---|
| `8080` | nginx :80 | HTTP |
| `8443` | nginx :443 | HTTPS (self-signed cert in dev) |
| `3307` | db :3306 | MySQL — dev only, not exposed in prod |
| `6379` | redis :6379 | Redis — dev only, not exposed in prod |

---

## 2. Container Interaction Diagram

```
Browser
  │
  ├─ HTTP  :8080 ──────────────────────────────────────────┐
  └─ HTTPS :8443 ──────────────────────────────────────────┤
                                                            │
                                               ┌────────────▼────────────┐
                                               │    nginx (bookshop_nginx) │
                                               │  serves /var/www/public   │
                                               │  static assets directly   │
                                               └────────────┬────────────┘
                                                            │ FastCGI :9000
                                               ┌────────────▼────────────┐
                                               │   php-fpm (bookshop_php)  │
                                               │   PHP 8.4 / Laravel 12    │
                                               └──────┬──────────┬───────┘
                                                      │          │
                                       ┌──────────────▼──┐   ┌──▼──────────────┐
                                       │  MySQL 8        │   │  Redis           │
                                       │  bookshop_db    │   │  bookshop_redis  │
                                       │  :3306          │   │  :6379           │
                                       └─────────────────┘   └──────────────────┘
```

---

## 3. Dockerfile Design (`docker/php/Dockerfile`)

The Dockerfile uses a **four-stage build** to separate concerns:

### Stage 1 — `base`
Installs the PHP 8.4-FPM runtime, all required extensions (`pdo_mysql`, `zip`, `xml`, `dom`, `intl`, `opcache`, `bcmath`), and the `phpredis` C extension. Also installs Composer.

`php.ini` and `www.conf` are **not** baked into this image. They are bind-mounted at runtime by docker-compose, which allows dev and prod to use different configs without a rebuild.

### Stage 2 — `builder`
Implements proper **layer caching** for Composer dependencies:

```dockerfile
COPY composer.json composer.lock ./    # ← Layer 1: manifest only
RUN composer install ...               # ← cached until manifests change
COPY . .                               # ← Layer 2: application code
RUN composer dump-autoload ...         # ← only re-runs on code changes
```

A code change (e.g., editing a controller) re-runs only `dump-autoload`. A dependency change (editing `composer.json`) re-runs the full `composer install`. Previously, `COPY . .` appeared before `composer install`, causing a full reinstall on every code change.

Both composer steps use `--no-scripts` to prevent the `post-autoload-dump` event from running `php artisan package:discover` during the build. That command would fail because dev-only service providers (e.g. `PailServiceProvider`) are absent when `--no-dev` is set. Laravel regenerates `bootstrap/cache/packages.php` automatically on the first request.

### Stage 3 — `app`
The production PHP-FPM image. Copies the full built application from `builder`, sets correct ownership on `storage/` and `bootstrap/cache/`, switches to `www-data`, and starts PHP-FPM.

### Stage 4 — `nginx-static`
A lightweight `nginx:alpine` image with the `public/` directory from `builder` copied in. Used only in production so nginx can serve static assets without a bind-mount or shared volume.

---

## 4. Docker Compose Structure

```
docker-compose.yml          ← base: shared service definitions, healthchecks
docker-compose.dev.yml      ← dev overlay: ports, bind-mounts, debug env
docker-compose.prod.yml     ← prod overlay: restart policies, named volumes, prod nginx build
```

The base file **never stands alone** — always combine it with an overlay:

```bash
# Development
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d

# Production
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
```

### Key base-file decisions

- **nginx has no `env_file`** — nginx needs no Laravel application variables.
- **MySQL healthcheck** — `php` and `nginx` wait on `db: condition: service_healthy`. The healthcheck uses `127.0.0.1` (TCP) rather than `localhost` (Unix socket). MySQL's Unix socket becomes available while init scripts are still running; the TCP listener only opens after all initialisation — including `init.sql` — is complete. Using `127.0.0.1` guarantees the DB is truly ready before dependent containers start.
- **Redis AOF persistence** — `redis-server --appendonly yes` ensures session and cache data survives container restarts.
- **Dedicated MySQL user** — created explicitly in `docker/mysql/init.sql` via `CREATE USER` + `GRANT`. Relying on the `MYSQL_USER`/`MYSQL_PASSWORD` env-var mechanism proved unreliable in MySQL 8.4 (the user was sometimes not created before the TCP listener opened). The `init.sql` approach is deterministic: it runs as part of the init phase, before TCP becomes available, so the user is always present when the healthcheck passes.

### Dev overlay additions

- Exposes ports 8080/8443 (nginx), 3307 (MySQL), 6379 (Redis).
- Bind-mounts the project directory into `php` (`:delegated`) and `nginx` (`:ro`).
- Injects `APP_ENV=local` and `APP_DEBUG=true`.
- Mounts `php.ini` and `www.conf` (dev pool config) into the PHP container.

### Prod overlay additions

- Builds nginx from the `nginx-static` Dockerfile stage (no bind-mount).
- Uses named volumes `app_storage` and `app_bootstrap_cache` so writable Laravel directories persist across deploys without bind-mounting the entire codebase.
- Mounts `www.prod.conf` (higher FPM limits, `pm.max_requests=500`).
- Adds `restart: unless-stopped` to all services.

---

## 5. Environment File Strategy

| File | Committed | Purpose |
|---|---|---|
| `.env.example` | Yes | Template for non-Docker local dev (SQLite defaults) |
| `.env.docker` | Yes | Template for Docker dev (MySQL + Redis, safe placeholders) |
| `.env` | **No** (gitignored) | Active local config; copy from `.env.docker` to get started |
| `.env.local` | **No** (gitignored) | Personal overrides; loaded after `.env` when present |
| `.env.production` | **No** (gitignored) | Production secrets; injected by CI/CD at deploy time |

### Getting started (Docker)

```bash
cp .env.docker .env
docker compose -f docker-compose.yml -f docker-compose.dev.yml exec php php artisan key:generate
```

### Variable ownership

**Laravel application variables** (in `.env`):
`APP_*`, `DB_*`, `REDIS_*`, `SESSION_*`, `CACHE_*`, `QUEUE_*`, `MAIL_*`, `LOG_*`, etc.

**Docker infrastructure variables** (in `.env`, used only by docker-compose YAML for `${VAR}` substitution and the MySQL container):
`MYSQL_DATABASE`, `MYSQL_ROOT_PASSWORD`, `MYSQL_PORT`, `MYSQL_EXPOSE_PORT`

Note: `MYSQL_USER`/`MYSQL_PASSWORD` are no longer passed to the MySQL container. The application user is created by `docker/mysql/init.sql` instead.

These infrastructure variables are never forwarded to the nginx or Redis containers.

### Redis configuration

The project uses Redis for all three subsystems:

```
SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
```

Two Redis databases are used to prevent cache eviction from polluting session data:

| Variable | Value | Used by |
|---|---|---|
| `REDIS_DB` | `0` | Default / queue connection |
| `REDIS_CACHE_DB` | `1` | Cache connection |

---

## 6. Development Workflow

```bash
# 1. First-time setup
cp .env.docker .env

# 2. Generate application key
docker compose -f docker-compose.yml -f docker-compose.dev.yml run --rm php php artisan key:generate

# 3. Start all services (builds the PHP image on first run)
./scripts/deploy.sh dev

# 4. Install Node dependencies and build assets (run on host or in container)
npm install && npm run dev

# 5. Useful commands
docker compose -f docker-compose.yml -f docker-compose.dev.yml exec php bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml exec php php artisan tinker
docker compose -f docker-compose.yml -f docker-compose.dev.yml logs -f php
```

Access:
- Application: http://localhost:8080
- Database: `mysql -h 127.0.0.1 -P 3307 -u bookshop -p`
- Redis: `redis-cli -p 6379`

---

## 7. Production Deployment Workflow

### Prerequisites

1. Create `.env` (or `.env.production`) on the server with real values.
2. Generate `docker/nginx/certs/server.crt` and `server.key` (or mount a real certificate).
3. Ensure Docker and Docker Compose are installed on the server.

### Required production environment variables

```
APP_ENV=production
APP_DEBUG=false
APP_KEY=<generated with php artisan key:generate>
APP_URL=https://yourdomain.com

DB_HOST=db
DB_USERNAME=bookshop
DB_PASSWORD=<strong password>

REDIS_HOST=redis

MYSQL_DATABASE=bookshop
MYSQL_ROOT_PASSWORD=<strong root password>
```

### Deploy

```bash
./scripts/deploy.sh prod
```

This script:
1. Builds both the `app` and `nginx-static` Docker images.
2. Starts all containers with `--remove-orphans`.
3. Runs `php artisan migrate --force` (MySQL readiness is guaranteed by the compose healthcheck — no separate wait step needed).
4. Caches config, routes, and views.
5. Restarts queue workers so they pick up the new code.

---

## 8. Deployment Script (`scripts/deploy.sh`)

```
scripts/deploy.sh [dev|prod]
```

| Step | Dev | Prod |
|---|---|---|
| Build images | Yes (`app` stage) | Yes (`app` + `nginx-static` stages) |
| Start containers | Yes | Yes |
| `artisan migrate` | Yes | Yes |
| `artisan config:cache` | No | Yes |
| `artisan route:cache` | No | Yes |
| `artisan view:cache` | No | Yes |
| `artisan queue:restart` | No | Yes |

**Why caches are prod-only:** Running `config:cache` in development causes Laravel to ignore `.env` changes until you run `php artisan config:clear`. In production, the cache is desirable for performance and the env does not change between requests.

**Why `queue:restart` is needed:** Queue workers load the application once and keep it in memory. Without a restart signal, workers continue running old code after a deploy. `queue:restart` sets a flag in Redis/cache that causes workers to gracefully exit after finishing their current job; the process manager (or Docker restart policy) then brings up a fresh worker with the new code.
