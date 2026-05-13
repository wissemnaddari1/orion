# JWT Auth (HttpOnly Cookie) — How to Test

## Overview

- **Web (Twig)**: JWT is stored in HttpOnly cookie `AUTH_TOKEN`. No session; main firewall is stateless.
- **API**: Same JWT via `Authorization: Bearer <token>` or `AUTH_TOKEN` cookie (Lexik firewall).

---

## 1. Browser (Twig pages)

### Login

1. Open `GET /login` (e.g. `http://localhost:8000/login`).
2. Submit the form (email + password). Form POSTs to `/login` with CSRF token.
3. On success:
   - Response sets cookie: `AUTH_TOKEN=<jwt>; HttpOnly; Path=/; SameSite=Lax` (and `Secure` if HTTPS).
   - Redirect to dashboard by role: `/admin/dashboard`, `/client/dashboard`, or `/worker/dashboard`.
4. Visit any protected page (e.g. `/client/dashboard`). Browser sends `AUTH_TOKEN` cookie; `getUser()` is available in Twig.

### Rejection cases (flash message on login page)

- Invalid credentials → "Invalid credentials."
- Status ≠ ACTIVE or email not verified → Clear message (e.g. "Please verify your email before logging in.").
- Login locked (too many attempts) → "Too many failed attempts. Try again in X minutes."

### Logout

1. Go to `/logout` (GET or POST), e.g. link: `{{ path('app_logout') }}`.
2. Response clears `AUTH_TOKEN` (expires in past) and redirects to `/login`.
3. Next request to a protected page has no cookie → redirected to `/login` with flash "Please login again."

### Invalid/expired JWT

- Delete or tamper the cookie, or wait for token expiry.
- Request a protected page → redirect to `/login` with flash "Please login again."

---

## 2. cURL

### Login (web form flow) — get cookie

```bash
# 1) GET login page to obtain CSRF token (and optional session cookie)
curl -s -c cookies.txt -b cookies.txt 'http://localhost:8000/login' | grep -o 'name="_csrf_token" value="[^"]*"'

# 2) POST login with email, password, and _csrf_token (replace CSRF_VALUE and EMAIL/PASSWORD)
curl -s -c cookies.txt -b cookies.txt -X POST 'http://localhost:8000/login' \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  --data-urlencode 'email=admin@example.com' \
  --data-urlencode 'password=yourpassword' \
  --data-urlencode '_csrf_token=CSRF_VALUE'

# 3) Follow redirect; cookies.txt now contains AUTH_TOKEN
```

### Use cookie for protected page

```bash
curl -s -b cookies.txt 'http://localhost:8000/client/dashboard'
# Should return 200 with dashboard HTML (not redirect to /login)
```

### Logout (clear cookie)

```bash
curl -s -c cookies.txt -b cookies.txt -L 'http://localhost:8000/logout'
# Then: request dashboard again — should redirect to /login
curl -s -b cookies.txt -w '%{redirect_url}' 'http://localhost:8000/client/dashboard'
```

### API: Bearer token (no cookie)

```bash
# Get JWT from API login (JSON)
RESP=$(curl -s -X POST 'http://localhost:8000/api/login' \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@example.com","password":"yourpassword"}')
TOKEN=$(echo "$RESP" | php -r 'echo json_decode(file_get_contents("php://stdin"))->token ?? "";')

# Request protected API with Bearer
curl -s -H "Authorization: Bearer $TOKEN" 'http://localhost:8000/api/some-protected-route'
```

### API: cookie (same as web)

```bash
# After web login, use the same cookie for API
curl -s -b cookies.txt 'http://localhost:8000/api/some-protected-route'
```

---

## 3. Quick checks

| Check | How |
|-------|-----|
| Login form POSTs to `/login` | Form `action="{{ path('app_login') }}"` and `method="post"`. |
| CSRF on login | Hidden input `_csrf_token` and `csrf_token('authenticate')`. |
| Cookie name | `AUTH_TOKEN` (HttpOnly, SameSite=Lax, Path=/, Secure on HTTPS). |
| Dashboards by role | `/admin/**` → ROLE_ADMIN; `/client/**` → ROLE_CLIENT; `/worker/**` → ROLE_WORKER. |
| `getUser()` in Twig | After login, any Twig page can use `app.user`; controller can use `$this->getUser()`. |
| Logout clears cookie | GET or POST `/logout`; cookie cleared and redirect to `/login`. |
