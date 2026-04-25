# Deployment Plan — Example

> **Примечание:** Этот план был написан для развёртывания Жасмин Майер Bookshop и выполнен в приватном репо. Оставлен здесь как пример/шаблон для будущих форков.

**Target**: Single VPS, Docker, Cloudflare, Hetzner Object Storage, Resend, Stripe (Moldova), GA4
**Last updated**: 2026-04-15

---

## Overview — сервисы и их роли

| Сервис | Роль | Цена |
|--------|------|------|
| Cloudflare Registrar | Домен | по себестоимости (~$10/год) |
| Cloudflare Free | DNS + SSL + CDN + DDoS | бесплатно |
| Hetzner CX22 | VPS — приложение + БД + Redis | ~€3.79/мес |
| Hetzner Object Storage | S3-совместимое хранилище (обложки + epub) | €0.012/GB + трафик |
| Resend | Транзакционная email-рассылка | бесплатно до 3000/мес |
| Stripe | Приём платежей (физлицо, Молдова) | 1.5% + €0.25/транзакция |
| Google Analytics 4 | Аналитика | бесплатно |
| Sentry Free | Мониторинг ошибок | бесплатно |
| UptimeRobot | Uptime-мониторинг | бесплатно |

---

## Шаг 0 — Домен

**Рекомендуется**: Cloudflare Registrar (cloudflare.com/products/registrar) — продаёт по себестоимости, без наценки, и упрощает DNS-настройку (домен и DNS в одном месте).

1. Зайти на cloudflare.com → зарегистрироваться
2. Domain Registration → найти нужное имя (например, `jasminmayer.com`)
3. Оплатить → домен сразу в Cloudflare, NS уже настроены

Если домен куплен на Namecheap/reg.ru — перенести NS на Cloudflare:
```
ns1.cloudflare.com
ns2.cloudflare.com
```

---

## Шаг 1 — Cloudflare DNS

После регистрации домена в Cloudflare он уже на нужных NS. Дальнейшие DNS-записи добавляются на шаге 7 (после создания VPS и настройки почты).

**SSL/TLS** → выбрать **Full (strict)** — это означает шифрование между Cloudflare и VPS.  
Cloudflare Origin Certificate выписывается бесплатно на 15 лет (без Let's Encrypt и его ежеквартального обновления).

---

## Шаг 2 — Hetzner VPS

1. Зарегистрироваться на hetzner.com
2. Cloud → New Server:
   - **Location**: Falkenstein (EU, хорошая задержка)
   - **Image**: Ubuntu 24.04 LTS
   - **Type**: Shared AMD → **CX22** (2 vCPU, 4 GB RAM, 40 GB SSD)
   - **SSH Keys**: добавить свой публичный ключ (`~/.ssh/id_rsa.pub`)
   - **Name**: `bookshop-prod`
3. Записать **IP-адрес** сервера

### Начальная настройка VPS

```bash
# Подключиться
ssh root@<VPS_IP>

# Обновить систему
apt update && apt upgrade -y

# Установить Docker
curl -fsSL https://get.docker.com | sh

# Установить Docker Compose plugin
apt install docker-compose-plugin -y

# Создать пользователя для деплоя
adduser deploy
usermod -aG docker deploy

# Скопировать SSH-ключ для deploy
mkdir -p /home/deploy/.ssh
cp ~/.ssh/authorized_keys /home/deploy/.ssh/
chown -R deploy:deploy /home/deploy/.ssh
chmod 700 /home/deploy/.ssh
chmod 600 /home/deploy/.ssh/authorized_keys

# Настроить firewall
ufw allow OpenSSH
ufw allow 80
ufw allow 443
ufw enable

# Создать директорию приложения
mkdir -p /var/www/bookshop
chown deploy:deploy /var/www/bookshop

# Создать директорию для SSL-сертификатов
mkdir -p /etc/bookshop/certs
```

---

## Шаг 3 — Hetzner Object Storage

1. Hetzner Cloud → Object Storage → **Create bucket** (выбрать регион Falkenstein)
2. Создать **два** bucket:
   - `bookshop-public` — обложки книг и постов (публичный read)
   - `bookshop-private` — epub-файлы (приватный)
3. Storage → Credentials → **Create credentials** → записать:
   - `Access Key` → `AWS_ACCESS_KEY_ID`
   - `Secret Key` → `AWS_SECRET_ACCESS_KEY`
   - Endpoint: `https://fsn1.your-objectstorage.com`

4. Для `bookshop-public` → настроить публичный доступ на чтение (ACL: public-read на уровне bucket).

> **Endpoint в .env**: `S3_ENDPOINT=https://fsn1.your-objectstorage.com`  
> **Region**: `fsn1` (Hetzner использует собственные регионы, не AWS)

---

## Шаг 4 — Resend (email)

1. Зарегистрироваться на resend.com
2. Domains → **Add Domain** → ввести свой домен
3. Получить DNS-записи (SPF, DKIM, DMARC) — добавить в Cloudflare на шаге 7
4. API Keys → **Create API Key** → записать как `RESEND_API_KEY`

---

## Шаг 5 — Stripe

1. Зарегистрироваться на stripe.com
2. **Country**: Moldova
3. **Business type**: Individual
4. Заполнить: имя, адрес, телефон, ИНН (если применимо)
5. **Bank account**: подключить молдавский банк или Revolut/Wise (Stripe поддерживает)
6. Verify identity (паспорт + selfie)
7. После верификации в Dashboard → Developers → API Keys → записать:
   - `Publishable key` → `STRIPE_KEY`
   - `Secret key` → `STRIPE_SECRET`
8. Webhook — настраивается **после деплоя** (шаг 11)

> Stripe test mode: сначала деплоить с тестовыми ключами, переключать на live только после smoke-теста.

---

## Шаг 6 — Google Analytics 4

1. analytics.google.com → **Start measuring**
2. Account name: Жасмин Майер
3. Property name: jasminmayer.com
4. Platform: Web → ввести URL сайта
5. Measurement ID вида `G-XXXXXXXXXX` → записать как `GOOGLE_ANALYTICS_ID`

---

## Шаг 7 — DNS в Cloudflare

Добавить записи:

| Type | Name | Content | Proxy |
|------|------|---------|-------|
| A | `@` | `<VPS_IP>` | ✅ (оранжевое облако) |
| A | `www` | `<VPS_IP>` | ✅ |
| TXT | `@` | SPF от Resend | — |
| CNAME | `resend._domainkey` | DKIM от Resend | — |
| TXT | `_dmarc` | DMARC от Resend | — |

**Cloudflare Origin Certificate** (SSL для VPS):

1. Cloudflare → SSL/TLS → Origin Server → **Create Certificate**
2. Hosts: `yourdomain.com`, `*.yourdomain.com`
3. Validity: 15 years
4. Скачать **Certificate** и **Private Key**
5. Загрузить на VPS в директорию `docker/nginx/certs/` внутри репозитория:

```bash
# С локальной машины:
scp origin.crt deploy@<VPS_IP>:/var/www/bookshop/docker/nginx/certs/server.crt
scp origin.key deploy@<VPS_IP>:/var/www/bookshop/docker/nginx/certs/server.key
```

> Файлы в `/docker/nginx/certs/` добавлены в `.gitignore` — в репозиторий не попадут.
> nginx монтирует их как `/etc/nginx/certs/server.crt` и `server.key` (прописано в `docker-compose.yml`).

**SSL/TLS mode** в Cloudflare → **Full (strict)**

---

## Шаг 8 — Репозиторий на VPS

```bash
ssh deploy@<VPS_IP>
cd /var/www/bookshop

# Клонировать репозиторий
git clone https://github.com/<your-org>/bookshop.git .

# Создать .env
cp .env.example .env
nano .env   # заполнить (см. шаг 9)
```

### docker-compose.prod.yml

Создать файл `/var/www/bookshop/docker-compose.prod.yml`:

```yaml
services:
  nginx:
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - /etc/bookshop/certs:/etc/nginx/certs:ro
```

Этот файл монтирует production-сертификаты и открывает стандартные порты.  
Запуск: `docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d`

---

## Шаг 9 — Production .env

```env
APP_NAME="Жасмин Майер"
APP_ENV=production
APP_KEY=                          # сгенерировать: php artisan key:generate --show
APP_DEBUG=false
APP_URL=https://yourdomain.com

# Database (MySQL в Docker)
DB_CONNECTION=mysql
DB_HOST=bookshop_mysql
DB_PORT=3306
DB_DATABASE=bookshop
DB_USERNAME=bookshop
DB_PASSWORD=<strong-password>

MYSQL_DATABASE=bookshop
MYSQL_USER=bookshop
MYSQL_PASSWORD=<strong-password>
MYSQL_ROOT_PASSWORD=<strong-root-password>
MYSQL_EXPOSE_PORT=               # не открывать порт наружу в prod

# Redis
REDIS_HOST=bookshop_redis
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# S3 — Hetzner Object Storage
FILESYSTEM_DISK=local
AWS_ACCESS_KEY_ID=<hetzner-access-key>
AWS_SECRET_ACCESS_KEY=<hetzner-secret-key>
AWS_DEFAULT_REGION=fsn1
S3_PUBLIC_BUCKET=bookshop-public
S3_PRIVATE_BUCKET=bookshop-private
S3_PUBLIC_ENDPOINT=https://fsn1.your-objectstorage.com
S3_PRIVATE_ENDPOINT=https://fsn1.your-objectstorage.com
S3_PUBLIC_URL=https://bookshop-public.fsn1.your-objectstorage.com

# Mail — Resend
MAIL_MAILER=resend
MAIL_FROM_ADDRESS=hello@yourdomain.com
MAIL_FROM_NAME="Жасмин Майер"
RESEND_API_KEY=re_xxxxxxxxxxxx

# Stripe
STRIPE_KEY=pk_live_xxxxxxxxxxxx
STRIPE_SECRET=sk_live_xxxxxxxxxxxx
STRIPE_WEBHOOK_SECRET=whsec_xxxxxxxxxxxx   # получить после шага 11

# OAuth
GOOGLE_OAUTH_ENABLED=true
GOOGLE_CLIENT_ID=xxxx.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-xxxxxxxxxxxx
GOOGLE_REDIRECT_URI=https://yourdomain.com/auth/google/callback

VK_OAUTH_ENABLED=false

# Analytics
GOOGLE_ANALYTICS_ID=G-XXXXXXXXXX

# Sentry (опционально)
SENTRY_LARAVEL_DSN=https://xxxx@sentry.io/xxxx

# Cookie consent
COOKIE_CONSENT_ENABLED=true

# Adult content gate (если применимо)
ADULT_GATE_ENABLED=false
```

---

## Шаг 10 — CI/CD (GitHub Actions)

Два workflow-файла уже созданы в репозитории:

- `.github/workflows/ci.yml` — lint + static analysis + tests (запускается на каждый push и PR)
- `.github/workflows/deploy.yml` — деплой на VPS (запускается только после успешного CI на master)

**Порядок событий при `git push master`:**
```
push → CI запускается (ci.yml)
         ↓ если прошёл
       Deploy запускается (deploy.yml)
         → git pull на VPS
         → artisan down (maintenance mode)
         → docker compose build (пересборка образов)
         → docker compose up -d (перезапуск)
         → artisan migrate --force
         → artisan config:cache + route:cache + view:cache
         → artisan up
         → artisan queue:restart
```

**GitHub Secrets** (Settings → Secrets → Actions):

| Secret | Значение |
|--------|---------|
| `VPS_HOST` | IP-адрес VPS (например: `65.109.12.34`) |
| `VPS_USER` | `deploy` |
| `VPS_SSH_KEY` | Приватный SSH-ключ (`cat ~/.ssh/id_rsa`) |

---

## Шаг 11 — Первый деплой

```bash
ssh deploy@<VPS_IP>
cd /var/www/bookshop

# Запустить контейнеры
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d

# Сгенерировать APP_KEY (если не сделано)
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec php php artisan key:generate

# Запустить миграции
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec php php artisan migrate --force

# Создать admin-пользователя
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec php php artisan tinker --execute "
  \App\Models\User::create([
    'name' => 'Admin',
    'email' => 'admin@yourdomain.com',
    'password' => bcrypt('your-admin-password'),
    'role' => \App\Enums\UserRole::Admin,
    'email_verified_at' => now(),
  ]);
"

# Закэшировать конфиг/роуты/вью
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec php php artisan config:cache
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec php php artisan route:cache
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec php php artisan view:cache
```

### Настройка Stripe Webhook

1. Stripe Dashboard → Developers → Webhooks → **Add endpoint**
2. URL: `https://yourdomain.com/webhooks/stripe`
3. Events: `checkout.session.completed`
4. Скопировать **Signing secret** → добавить в `.env` как `STRIPE_WEBHOOK_SECRET`
5. Перезапустить: `docker compose ... exec php php artisan config:cache`

---

## Шаг 12 — Smoke test

- [ ] Открыть `https://yourdomain.com` — главная страница загружается
- [ ] Нет ошибок в логах: `docker compose ... logs php -f`
- [ ] Зарегистрировать нового пользователя → письмо пришло на email
- [ ] Войти → добавить книгу в корзину
- [ ] Оплатить через Stripe (тестовая карта: `4242 4242 4242 4242`)
- [ ] Книга появилась в библиотеке
- [ ] Скачать epub — файл скачивается
- [ ] `/sitemap.xml` — открывается
- [ ] `/robots.txt` — открывается
- [ ] GA4 Realtime — видит посещение
- [ ] Проверить Sentry — нет необработанных ошибок

---

## Переключение на Stripe Live

После успешного smoke-теста с тестовыми ключами:

1. Stripe Dashboard → переключить в **Live mode**
2. Получить live ключи (`pk_live_`, `sk_live_`)
3. Создать новый webhook endpoint в live mode
4. Обновить `.env` на VPS — заменить тестовые ключи на боевые
5. `docker compose ... exec php php artisan config:cache`
6. Провести реальный тест-платёж

---

## Обслуживание

### Бэкап БД
Уже настроен в Phase 12.4 — автоматически по расписанию.

### Обновление приложения
```bash
# Через GitHub Actions — автоматически при push в master
# Или вручную на VPS:
cd /var/www/bookshop
git pull
docker compose -f docker-compose.yml -f docker-compose.prod.yml build php
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec php php artisan migrate --force
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec php php artisan config:cache
```

### Просмотр логов
```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml logs php --tail=100 -f
docker compose -f docker-compose.yml -f docker-compose.prod.yml logs nginx --tail=50
```

### Horizon (очередь)
`https://yourdomain.com/horizon` — доступен только для admin-пользователей.

### Telescope
Отключён в production (Phase 12.3). Для временной диагностики — включить в `.env`, потом выключить.
