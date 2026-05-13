# Forgot Password, Login Lockout & Admin Ban — Implementation Notes

## A) Forgot Password Flow

### Routes
- **GET/POST /forgot-password** (`app_forgot_password`): User enters email. Always shows success message (no account enumeration). Sends email with reset link (token in URL, 30 min expiry).
- **GET/POST /reset-password/{token}** (`app_reset_password`): Validates token (hashed compare, expiry, not used). Form: new password + confirm. Same rules as registration (min 8 chars). On success: update password, invalidate token, clear login lockout.

### Security
- Reset token: `random_bytes(32)` → hex; stored as `hash('sha256', rawToken)` in `password_reset_tokens` table. Raw token only in email link.
- Single-use: token marked `used_at` on consumption.
- CSRF: `forgot_password` and `reset_password` token ids on forms.
- No leak: forgot-password always returns same success message whether email exists or not.

### Database
- **password_reset_tokens**: `id`, `user_id` (FK to users), `token_hash`, `requested_at`, `expires_at`, `used_at`.
- Run: `php bin/console doctrine:migrations:migrate` (use `Version20260215140000`).

### Service
- **PasswordResetService**: `createRequest(User)`, `sendResetEmail(User, rawToken)`, `findValidToken(rawToken)`, `consumeToken(PasswordResetToken)`.

---

## B) Login Lockout (Anti-Bruteforce)

### User fields
- `failed_login_attempts` (int, default 0)
- `login_locked_until` (datetime nullable)
- `last_failed_login_at` (datetime nullable)

### Behaviour
- Wrong password: increment `failed_login_attempts`, set `last_failed_login_at`. If `failed_login_attempts >= 3`: set `login_locked_until = now + 10 minutes`.
- While `login_locked_until > now`: deny login with message "Too many attempts. Try again in X minutes."
- On successful login: `resetLoginAttempts()` (zero attempts, clear lock).

### Where
- Implemented in **AuthApiController** (POST /api/login): check `isLoginLocked()` before password; on wrong password `incrementFailedLoginAttempts(3, 10)` and flush; on success `resetLoginAttempts()` and flush.

---

## C) Admin Ban / Unban

### User fields
- `is_banned` (bool, default false)
- `banned_at` (datetime nullable)
- `ban_reason` (varchar 500 nullable)

### Behaviour
- If `isBanned`: cannot log in (generic message in API); **UserChecker** blocks on next request (e.g. JWT refresh) with "Your account has been restricted."
- Admin: **POST /admin/users/{id}/ban** (CSRF, optional `ban_reason`), **POST /admin/users/{id}/unban** (CSRF). Only ROLE_ADMIN.

### UI
- **Admin users list**: Ban button (opens modal with reason), Unban button (if banned).
- **Admin user show**: Ban user (expandable form with reason), Unban button (if banned); show banned_at / ban_reason when banned.

---

## Manual Test Steps

### Forgot password
1. **Request reset (no enumeration)**  
   `curl -s -X POST http://localhost:8000/forgot-password -d "email=nonexistent@example.com&_token=TOKEN" -c c.txt -b c.txt -L`  
   Use browser or replace `_token` with real CSRF from GET /forgot-password. Expect same success page as for existing email.
2. **Request reset (existing email)**  
   GET /forgot-password, copy CSRF from form, then POST with valid email. Check mailbox for reset link.
3. **Reset password**  
   Open link from email (e.g. /reset-password/abc123...). Enter new password + confirm (min 8 chars). Submit. Expect redirect to login and success message.
4. **Reuse token**  
   Use same reset link again. Expect "invalid or expired" and redirect to forgot-password.

### Login lockout
1. POST /api/login with wrong password 3 times for same user. 4th attempt: expect 429 and "Too many attempts. Try again in X minutes."
2. Wait 10 minutes (or reduce lock in code for test) and try again with correct password: expect 200 and JWT.

### Ban / Unban
1. As admin, open User Management → Ban a user (with/without reason). Check user show: banned_at and ban_reason.
2. POST /api/login as that user: expect 403 and "Your account has been restricted."
3. As admin, Unban. POST /api/login again: expect 200 (if ACTIVE and email verified).

---

## Curl Examples

```bash
# Forgot password (get CSRF from GET /forgot-password first)
curl -s -c c.txt http://localhost:8000/forgot-password
# Then POST with email and _token from form
curl -s -X POST http://localhost:8000/forgot-password -b c.txt -d "email=user@example.com&_token=YOUR_CSRF"

# Login (lockout: repeat with wrong password 3 times, then see 429)
curl -s -X POST http://localhost:8000/api/login -H "Content-Type: application/json" -d '{"email":"user@example.com","password":"wrong"}'
```

---

## Files Touched

- **Entity**: `User.php` (lockout + ban fields); `PasswordResetToken.php` (new).
- **Repository**: `PasswordResetTokenRepository.php` (new); `UserRepository.php` (`findBannedUsers`).
- **Migration**: `Version20260215140000.php` (users columns + password_reset_tokens table).
- **Service**: `PasswordResetService.php` (new); `config/services.yaml` (mailerFrom).
- **Controller**: `PasswordResetController.php` (forgot + reset); `AuthApiController.php` (lockout + banned check); `Admin\UserController.php` (ban/unban).
- **Security**: `UserChecker.php` (isBanned check); `config/packages/security.yaml` (forgot-password, reset-password PUBLIC_ACCESS).
- **Form**: `ResetPasswordType.php` (new).
- **Templates**: `forgot_password.html.twig`, `reset_password.html.twig`; `admin/users/index.html.twig` (Ban modal, Unban); `admin/users/show.html.twig` (Ban/Unban, banned info); `emails/reset_password.html.twig`; login page link to forgot-password.
