# JWT Authentication Implementation Notes

## Overview

Authentication has been switched from session-based login to **JWT** (LexikJWTAuthenticationBundle). The app is **stateless**: no PHP sessions for auth. Dashboards and API are protected by JWT (Bearer header or `AUTH_TOKEN` HttpOnly cookie).

## Generating JWT Keys

Before using the app, generate the RSA keypair:

```bash
php bin/console lexik:jwt:generate-keypair
```

This creates:

- `config/jwt/private.pem`
- `config/jwt/public.pem`

If you get an OpenSSL error on Windows, ensure OpenSSL is available (e.g. PHP with OpenSSL extension). You can also generate keys elsewhere and place them in `config/jwt/`.

## Environment Variables

In `.env` (or `.env.local`):

```env
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=
```

## API Endpoints

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| POST | `/api/login` | No | Body: `{"email":"...","password":"..."}`. Returns JWT + user and sets `AUTH_TOKEN` cookie. |
| POST | `/api/refresh` | Yes (JWT) | Returns new JWT and updates cookie. |
| POST | `/api/logout` | No | Clears `AUTH_TOKEN` cookie. |

## Access Rules

- Only **ACTIVE** users with **emailVerified = true** can obtain a JWT. Others get clear JSON errors (e.g. `email_not_verified`, `account_not_active`).
- `/admin/**` requires `ROLE_ADMIN`, `/worker/**` requires `ROLE_WORKER`, `/client/**` requires `ROLE_CLIENT`.
- `denyAccessUnlessGranted()` in controllers continues to work as before.

## Cookie (Web / Twig)

- On successful `POST /api/login`, the response sets:
  - `Set-Cookie: AUTH_TOKEN=<jwt>; HttpOnly; Secure; SameSite=Lax; Path=/`
- The **main** and **api** firewalls both use Lexik JWT with:
  - `Authorization: Bearer <token>` **or**
  - Cookie `AUTH_TOKEN`
- Visiting `/admin/dashboard`, `/client/dashboard`, `/worker/dashboard` works with the cookie (no session).

## Web Logout

- **GET /logout** (`app_logout`): clears `AUTH_TOKEN` cookie and redirects to login.
- **POST /api/logout**: same effect (clears cookie), returns JSON.

## Manual Tests (curl)

Generate keys first, then:

```bash
# 1) Login (replace with real email/password for an ACTIVE, verified user)
curl -s -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"yourpassword"}' \
  -c cookies.txt -b cookies.txt

# Response: {"token":"...","user":{"id":1,"email":"...","role":"ADMIN"}}
# Cookie AUTH_TOKEN is in cookies.txt if using file; browser gets it via Set-Cookie.

# 2) Call protected API with Bearer token (use token from step 1)
curl -s http://localhost:8000/api/me -H "Authorization: Bearer YOUR_JWT_TOKEN"

# 3) Refresh (send same cookie or Bearer)
curl -s -X POST http://localhost:8000/api/refresh -b cookies.txt

# 4) Logout (clear cookie)
curl -s -X POST http://localhost:8000/api/logout -b cookies.txt -c cookies.txt
```

## Functional Test (optional)

A test can:

1. Create or load an ACTIVE, email-verified user.
2. `POST /api/login` with credentials, assert 200 and presence of `token` and `user.role`.
3. Call `GET /api/me` with `Authorization: Bearer <token>`, assert 200.
4. Call `GET /admin/dashboard` (or client/worker) with the same token (or cookie), assert 200 for admin user and 403 for non-admin.

## Files Touched

- `config/packages/security.yaml` — api + main JWT firewalls, access_control.
- `config/packages/lexik_jwt_authentication.yaml` — token_ttl, cookie extractor `AUTH_TOKEN`.
- `.env` — JWT key paths and passphrase.
- `src/Controller/Api/AuthApiController.php` — POST `/api/login`, `/api/refresh`, `/api/logout`.
- `src/Controller/AppController.php` — login (render only), logout (clear cookie + redirect).
- `templates/pages/auth/login.html.twig` — form submits via fetch to `/api/login`, redirect by role on success.
