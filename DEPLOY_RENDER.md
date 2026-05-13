# Deploy Orion (Symfony) on Render with Docker + Hostinger MySQL

This guide matches the production Docker setup at the **repository root** for **Symfony 6.4** on **PHP 8.2** (see `composer.json` → `extra.symfony.require` and `config.platform.php`).

**Detected stack:** Symfony **6.4.*** (constraint in `composer.json`), PHP **>=8.1** / platform **8.2**, extensions implied by Composer: `ctype`, `iconv`, plus Docker image adds `pdo_mysql`, `intl`, `zip`, `gd`, `mbstring`, `xml`, `opcache` (for Doctrine, Twig, Dompdf, PhpSpreadsheet, QR, etc.).

---

## Docker architecture

| Layer | Role |
|-------|------|
| **Base** | `php:8.2-apache-bookworm` — Apache + PHP-FPM not needed; `mod_rewrite` enabled. |
| **Extensions** | MySQL (`pdo_mysql`), i18n (`intl`), archives (`zip`), images (`gd`), Unicode (`mbstring`), XML/DOM (`xml`), bytecode cache (`opcache`). |
| **Composer** | Two-step install: (1) `composer.json` + `composer.lock` for cacheable vendor layer; (2) full source copy then `composer install --no-dev --optimize-autoloader`, authoritative classmap, optional `post-install-cmd`. |
| **Apache** | `DocumentRoot` = `/var/www/html/public`; `AllowOverride All`; custom `000-default.conf`; global `apache2.conf` paths adjusted to `public/`. |
| **Startup** | `docker/entrypoint.sh` binds Apache to Render’s **`PORT`**, ensures `var/` + upload dirs, runs `cache:clear --no-warmup` then `cache:warmup` (best-effort), fixes permissions, `apache2-foreground`. |
| **Symfony** | `APP_ENV=prod` / `APP_DEBUG=0` in image; real secrets from **Render env** at runtime (`DATABASE_URL`, `APP_SECRET`, …). |

```text
Internet → Render edge (TLS) → container :PORT → Apache → public/index.php → Symfony (prod)
                                      ↘ Hostinger MySQL (DATABASE_URL)
```

---

## What was created

| Item | Purpose |
|------|---------|
| **`Dockerfile`** | `php:8.2-apache-bookworm`, required extensions, Composer **`install --no-dev --optimize-autoloader`**, authoritative autoload, Apache docroot `public/`, production `opcache` ini. |
| **`docker/apache/000-default.conf`** | VirtualHost: `DocumentRoot` `/var/www/html/public`, `AllowOverride All` for `.htaccess`. |
| **`docker/entrypoint.sh`** | Render **`PORT`** → Apache `Listen` / vhost; dirs + permissions; **`cache:clear --no-warmup`** then **`cache:warmup`** (non-fatal if env incomplete); `apache2-foreground`. |
| **`public/.htaccess`** | `mod_rewrite` rules so all non-file requests hit `index.php` (required for Apache + Symfony routing); forwards `Authorization` for JWT/API. |
| **`.dockerignore`** | Smaller, safer images: no `.env*`, no `vendor/`, no `var/`, no Python sidecar trees, no `tests/`. |
| **`render.yaml`** | Optional Blueprint: Docker web service, `APP_ENV`/`APP_DEBUG`/`APP_TIMEZONE`. Adjust name/plan/region as you like. |
| **`config/packages/doctrine.yaml`** | `server_version` uses `%env(default:doctrine.default_server_version:DATABASE_SERVER_VERSION)%` so **Hostinger MySQL 8** works by default; override with `DATABASE_SERVER_VERSION` or tune the parameter in `services.yaml` if you use MariaDB. |
| **`config/services.yaml`** | Parameter `doctrine.default_server_version: '8.0'` as fallback when `DATABASE_SERVER_VERSION` is unset. |
| **`config/packages/framework.yaml`** | `when@prod`: `trusted_proxies` / `trusted_headers` so Symfony trusts Render’s TLS termination (correct HTTPS URLs, sessions, redirects). |
| **`config/packages/messenger.yaml`** + **`config/services.yaml`** | `MESSENGER_TRANSPORT_DSN` defaults via **`doctrine://default?auto_setup=0`** (`messenger_async_dsn_default`) when unset; override on Render for Redis/AMQP. |
| **`.gitignore`** | Also ignores `.env.prod.local` and `.env.production.local`. |

**Not changed:** Entities, business logic, routes, or dev/test behavior outside the items above.

---

## Render: root directory & Docker paths

| Setting | Value |
|---------|--------|
| **Root Directory** | *(empty)* — the Symfony app and `composer.json` live at the **Git repository root**. |
| **Environment** | **Docker** |
| **Dockerfile path** | `./Dockerfile` (default when the file is at repo root) |
| **Docker build context** | `.` (repository root) |

---

## Required Render environment variables

Set these in the Render dashboard (**Environment** tab). Do **not** commit secrets; use Render’s env UI or [secret files](https://render.com/docs/configure-environment-variables).

### Core Symfony (minimum to document)

| Variable | Example / notes |
|----------|------------------|
| **`APP_ENV`** | **`prod`** — also set in the Dockerfile `ENV`; Render should set this to `prod` to match. |
| **`APP_SECRET`** | Long random string (e.g. `openssl rand -hex 32`). **Required**; never commit to Git. |
| **`DATABASE_URL`** | `mysql://USER:PASSWORD@HOST:3306/DBNAME` for **Hostinger** (URL-encode the password if needed). |
| `APP_DEBUG` | `0` |
| `APP_TIMEZONE` | `UTC` (matches `framework.yaml` / `index.php` default) |

### Database (Hostinger)

| Variable | Example / notes |
|----------|------------------|
| `DATABASE_URL` | `mysql://USER:PASSWORD@HOST:3306/DBNAME` — URL-encode special characters in the password. |
| `DATABASE_SERVER_VERSION` | *(optional)* e.g. `8.0.36` for MySQL, or `mariadb-10.11.2` for MariaDB. If omitted, Doctrine uses default **`8.0`** from `services.yaml`. |

If Hostinger requires SSL, add PDO flags to the URL per Doctrine/Hostinger docs (e.g. `?sslmode=REQUIRED` is PostgreSQL; for MySQL use the `mysqli`/`pdo_mysql` SSL query params Hostinger documents).

### Mailer (required by config)

| Variable | Example / notes |
|----------|------------------|
| `MAILER_DSN` | Your provider DSN, or `null://null` only for a **non‑mailing** smoke test. |
| `MAILER_FROM` | `no-reply@yourdomain.com` |

### Messenger (optional override)

| Variable | Example / notes |
|----------|------------------|
| `MESSENGER_TRANSPORT_DSN` | Omit to use **`doctrine://default?auto_setup=0`** (see `messenger_async_dsn_default` in `config/services.yaml`). Set to `amqp://...` or `redis://...` if you add those services. |

### JWT (Lexik) — paths must exist in the container

| Variable | Example / notes |
|----------|------------------|
| `JWT_SECRET_KEY` | `%kernel.project_dir%/config/jwt/private.pem` (default style) — you must supply the **files** (Render secret files mounted to those paths, or bake via a secure private image registry flow). |
| `JWT_PUBLIC_KEY` | `%kernel.project_dir%/config/jwt/public.pem` |
| `JWT_PASSPHRASE` | Passphrase for the private key |

### Integrations referenced in `config/services.yaml`

Configure as in your current `.env` (values are project-specific):  
`ZEGO_APP_ID`, `ZEGO_SERVER_SECRET`, `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET`, `FACE_SERVICE_URL`, `FACEPP_API_KEY`, `FACEPP_API_SECRET`, `CONTRACT_GENERATOR_API_URL`, `MATCHMAKING_API_URL`, `PYTHON_AI_SERVICE_URL`, `TICKET_SUPPORT_AI_URL`, `TICKET_AI_HTTP_TIMEOUT`, `OFFER_PREDICTION_SERVICE_URL`, `SERVICE_REQUEST_AI_URL`, `GROQ_API_KEY`, `GROQ_API_KEY_SCORING`, `PAYMENT_PROVIDER` (optional; defaults to `mock` via parameter).

If any of these are missing, Symfony may fail at **container compile** or first request—mirror what you already use locally for production-like values.

---

## Step-by-step: Create the Render service

1. Push this branch to GitHub (`wissemnaddari1/orion` or your repo).
2. In [Render](https://dashboard.render.com): **New +** → **Web Service** → connect the repository.
3. **Runtime:** Docker. **Root Directory:** leave blank.
4. **Instance type:** choose a plan with enough RAM for Composer + Symfony (starter may be tight on first deploy).
5. Add **environment variables** (see tables above). Minimum to boot: `APP_SECRET`, `DATABASE_URL`, `MAILER_DSN`, `MAILER_FROM`, JWT trio, and integration URLs/keys your code paths hit on startup.
6. **Create Web Service.** Wait for the Docker build and deploy logs.

---

## Migrations (Hostinger MySQL)

Run **after** `DATABASE_URL` points at the correct database and network access from Render to Hostinger is allowed (Hostinger often restricts hosts—**whitelist Render outbound IPs** or use a tunnel/plan that allows remote MySQL).

**Option A — One-off Shell on Render** (if available on your plan):  
`php bin/console doctrine:migrations:migrate --no-interaction --env=prod`

**Option B — From your PC** (VPN / IP allowed by Hostinger):  
Set `DATABASE_URL` locally and run the same command against the production database.

**Option C — Release command** in Render (paid feature): add a release step that runs the migrate command before traffic switches.

---

## How the container starts

1. `docker/entrypoint.sh` sets Apache **`Listen $PORT`** and matches `<VirtualHost *:$PORT>`.
2. Creates `var/cache`, `var/log`, and `public/uploads/*` trees.
3. Runs `php bin/console cache:warmup --env=prod --no-debug` (ignored if env incomplete).
4. `chown`/`chmod` for `www-data` on `var/` and `public/uploads/`.
5. `apache2-foreground` serves **`/public`** with **`mod_rewrite`**.

---

## Common errors and fixes

| Symptom | Likely cause | Fix |
|---------|----------------|-----|
| Build fails on `composer install` | Network / lock file | Ensure `composer.lock` is committed; retry deploy. |
| **403** on all URLs | `AllowOverride` / missing rewrite | Image enables `mod_rewrite` and `AllowOverride All`; ensure `public/.htaccess` is in the repo. |
| **404** on non-`/` routes | Rewrite / docroot | Confirm Render uses this Dockerfile; docroot must be `public/`. |
| Wrong **http vs https** links | Reverse proxy | `trusted_proxies` / `trusted_headers` for `prod` (already added). |
| Doctrine **server version** mismatch | MySQL vs MariaDB string | Set `DATABASE_SERVER_VERSION` (e.g. `8.0.36` or `mariadb-10.11.2`). |
| **Connection refused** to DB | Hostinger firewall | Allow Render’s egress IPs or use Hostinger’s remote-MySQL / VPN guidance. |
| JWT errors at boot | Missing key files | Mount or copy `config/jwt/*.pem` into the image at build time via secrets, or use a private build pipeline. |
| Messenger / mail errors | Missing DSN | Set `MAILER_DSN`; optionally set `MESSENGER_TRANSPORT_DSN` to override defaults. |

---

## Local Docker check (optional)

```bash
docker build -t orion:local .
docker run --rm -p 8080:8080 -e PORT=8080 \
  -e APP_SECRET=devsecretdevsecretdevsecret12 \
  -e DATABASE_URL="mysql://user:pass@host:3306/dbname" \
  -e MAILER_DSN=null://null \
  -e MAILER_FROM=test@example.com \
  # ... add remaining env vars your app needs ...
  orion:local
```

Then open `http://localhost:8080`.

---

## Security checklist

- Keep **`.env`**, **`.env.local`**, **`.env.*.local`**, **`ai_service/.env`**, and **JWT keys** out of Git (see `.gitignore`).
- Prefer **Render secret files** or **encrypted env** for keys and database passwords.
- After any accidental push of secrets, **rotate** credentials and consider `git filter-repo` / new keys.

---

## Files reference (quick)

- `Dockerfile` — image definition  
- `docker/entrypoint.sh` — Render `PORT`, permissions, cache warmup  
- `docker/apache/000-default.conf` — Apache vhost  
- `public/.htaccess` — front controller routing  
- `render.yaml` — optional Blueprint  
- `DEPLOY_RENDER.md` — this document  

For questions about Hostinger-specific MySQL URLs or SSL flags, use their current documentation for remote connections and match `DATABASE_URL` accordingly.
