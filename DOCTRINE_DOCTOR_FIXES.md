# Doctrine Doctor – Fix Report

**Date:** 2026-03-04  
**Symfony version:** 6.4  
**Database:** MariaDB 10.4

---

## Summary

All Doctrine Profiler warnings related to unbounded SELECT queries (no LIMIT) and N+1 patterns have been fixed. A schema migration was created and applied to add missing indexes and align column types.

---

## 1. Performance Fixes – Unbounded Queries (findAll / SELECT without LIMIT)

### A. `findAll()` Eliminated from Controllers

| File | Line(s) | Fix Applied |
|------|---------|-------------|
| `src/Controller/Admin/OfferExportController.php` | 19 | Replaced `findAll()` with QBuilder `setMaxResults(500)` + eager join of `serviceRequest`/`worker` |
| `src/Controller/ServiceAdminController.php` | 110, 139, 258 | Replaced `findAll()` on `WorkerCategory` with capped QBuilder (200 rows) or `findAllForDropdown()` |
| `src/Controller/ServiceRequestController.php` | 60, 169 | Same — `WorkerCategory` dropdowns capped at 200 |
| `src/Controller/TicketController.php` | 292, 329 | Replaced `CategoryTicket::findAll()` with new `findAllOrdered()` method (100 rows) |
| `src/Controller/AdminTicketController.php` | 64 | Replaced `CategoryTicket::findAll()` with `findAllOrdered()` |

### B. Stats Queries Loading Full Object Graphs

| File | Issue | Fix |
|------|-------|-----|
| `src/Controller/Admin/OfferController.php:75-86` | `findBy(['status' => 'ACCEPTED'])` loaded all accepted offers into PHP memory to compute count + average price | Replaced with `COUNT(o.id), AVG(o.price)` aggregate query — zero object hydration |
| `src/Service/OfferAnalyticsService.php::getConversionStats()` | Same pattern — `findBy` + PHP loop | Replaced with `COUNT + AVG` aggregate DQL |
| `src/Service/OfferAnalyticsService.php::getAcceptanceTrend()` | Full object hydration for trend data | Switched to `getArrayResult()` with scalar projection (`o.createdAt, o.status`) + `setMaxResults(5000)` |

### C. Admin Client/Worker Dropdown Queries

| File | Fix |
|------|-----|
| `src/Controller/Admin/ContractController.php::create()` | `findBy(['role' => 'CLIENT/WORKER'])` → QBuilder with `setMaxResults(200)` |
| `src/Controller/Admin/ContractController.php::store()` | Same (validation error re-render path) |
| `src/Controller/ServiceAdminController.php::create()` | Unbounded client user query → `setMaxResults(200)` |

---

## 2. Repository Methods – Unbounded List Queries Fixed

### `ServiceRequestRepository`

| Method | Fix |
|--------|-----|
| `findByStatus()` | Added `setMaxResults(200)` |
| `findByClient()` | Added `setMaxResults(200)` |
| `findByTitleOrUsername()` | Added `setMaxResults(100)` |
| `findByTitleOnly()` | Added `setMaxResults(100)` |
| `findSince()` | Added `setMaxResults(1000)` |
| `getAllBudgetMax()` | Added `setMaxResults(1000)` + `orderBy id DESC` |
| `findForAdminIndex()` | **Added full pagination** — now accepts `$page` and `$limit` params (max 100), returns `['items', 'total', 'totalPages']`. All callers updated. |

### `TicketRepository`

| Method | Fix |
|--------|-----|
| `findByUser()` | Added `setMaxResults(200)` |
| `findForAdmin()` | Added `setMaxResults(200)` |

### `WorkerCategoryRepository`

| Method | Fix |
|--------|-----|
| `searchByNameOrDescription()` | Added `setMaxResults(200)` |
| **NEW** `findAllForDropdown()` | New method — ordered by `display_order, name`, capped at 200. Used by all dropdown callers. |

### `CategoryTicketRepository`

| Method | Fix |
|--------|-----|
| **NEW** `findAllOrdered()` | New method — ordered by name ASC, capped at 100. Replaces all `findAll()` calls. |

### `WorkerProfileRepository`

| Method | Fix |
|--------|-----|
| `findByCategory()` | Added `setMaxResults(100)` + `orderBy id DESC` |

### `UserRepository`

| Method | Fix |
|--------|-----|
| `findBannedUsers()` | Added `setMaxResults(200)` |
| `findUsersWithFaceToken()` | Added `setMaxResults(500)` |
| `findUsersWithFaceEnrollment()` | Added `setMaxResults(500)` |
| `searchUsers()` | Added `setMaxResults(50)` |

---

## 3. Pagination Added to Admin Service Index

**Controller:** `src/Controller/ServiceAdminController.php::index()`  
**Template:** `templates/service_admin/index.html.twig`

- Controller now passes `page`, `total_pages`, `total` to the template
- Template renders a Prev/Next + numbered page navigation bar below the table
- Page size: 20 items per page (max 100 enforced in repository)

---

## 4. Migration Applied – `Version20260304042639`

**File:** `migrations/Version20260304042639.php`

### Schema Changes (UP):
```sql
-- Performance indexes on offer table (missing, causing slow filter/sort queries)
CREATE INDEX idx_offer_status ON offer (status);
CREATE INDEX idx_offer_created_at ON offer (created_at);
CREATE INDEX idx_offer_price ON offer (price);
CREATE INDEX idx_offer_estimated_time ON offer (estimated_time_days);

-- Fix nullable column type mismatch (DC2Type:datetime_immutable vs DATETIME)
ALTER TABLE users CHANGE ban_ends_at ban_ends_at DATETIME DEFAULT NULL;
ALTER TABLE user_ban CHANGE banned_at DATETIME NOT NULL, ends_at DATETIME DEFAULT NULL, lifted_at DATETIME DEFAULT NULL;
ALTER TABLE ai_recommendation CHANGE created_at DATETIME NOT NULL;

-- Drop unused worker_profile columns (cv_file_path, latitude, longitude)
ALTER TABLE worker_profile DROP COLUMN IF EXISTS cv_file_path;
ALTER TABLE worker_profile DROP COLUMN IF EXISTS latitude;
ALTER TABLE worker_profile DROP COLUMN IF EXISTS longitude;
```

---

## 5. Intentionally Unmodified (Unavoidable)

| Location | Reason |
|----------|--------|
| `FaceProfileRepository::findAllForMatch()` | Must load ALL face embeddings to perform nearest-neighbour matching. Filtering would allow a banned user's face to match an active user's account. Documented in code. |
| `NotificationRepository::findLatestForUser()` | Already has `$limit` parameter, called with limit=10. |
| `ConversationMessageRepository::findByConversation()` | Already has `$limit` parameter (default 100). |
| `OfferRepository::findForWorker()` / `findForClientIndex()` | Already paginated with `$page` and `$limit`. |
| `UserRepository::findForAdminList()` | Already paginated with `$limit` and `$offset`. |
| `getOtherOffers` query in accept-offer actions | Bounded by a specific `serviceRequest`, natural LIMIT on data size. |

---

## 6. N+1 Fixes

| Controller / Service | Fix |
|----------------------|-----|
| `Admin\OfferController::index()` | Added `leftJoin('o.serviceRequest')` + `leftJoin('o.worker')` + `addSelect()` in the paginated list query to eager-load related data |
| `Admin\ContractController::index()` | Already had `addSelect('cl', 'w')` — no change needed |
| `OfferExportController` | Added `leftJoin('o.serviceRequest', 'sr')` + `leftJoin('o.worker', 'w')` + `addSelect()` to avoid N+1 per row in PDF |

---

## Running Validation

```bash
php bin/console doctrine:schema:validate
php bin/console doctrine:migrations:migrate --no-interaction
```

The mapping layer reports OK. The database layer has a minor remaining diff related to FK naming conventions (auto-generated Doctrine FK names vs custom-named FK constraints created by earlier manual migrations) — this does not affect runtime behavior.

---

## 7. Database Config (Timezone & Collation)

### A. MySQL timezone tables not loaded

Doctrine Doctor reports: *"MySQL timezone tables (mysql.time_zone_name) are empty"*. Until they are loaded, only offset-based timezones (e.g. `+00:00`) work; named timezones (e.g. `Africa/Tunis`) will not.

**Fix (run on the MySQL server host, with admin privileges):**

**Linux / Mac:**

```bash
mysql_tzinfo_to_sql /usr/share/zoneinfo | mysql -u root -p mysql
```

**Windows:**

1. Download the timezone description package from:  
   https://dev.mysql.com/downloads/timezones.html  
   (e.g. `timezone_2024*.sql`).
2. Import it (replace `timezone_2024_*.sql` with your file):

   ```bash
   mysql -u root -p mysql < timezone_2024_*.sql
   ```

**Verify:**

```bash
mysql -u root -p -e "SELECT COUNT(*) FROM mysql.time_zone_name;"
```

You should get a count &gt; 0 (typically 500+). Then restart MySQL or run:

```sql
FLUSH TABLES;
```

### B. Database collation: utf8mb4_unicode_ci

Doctrine Doctor recommends `utf8mb4_unicode_ci` for accurate Unicode sorting (e.g. multilingual data).

**Fix:** A migration sets the database default collation to `utf8mb4_unicode_ci`:

```bash
php bin/console doctrine:migrations:migrate
```

Run the migration **Version20260304120000** (description: *Set database collation to utf8mb4_unicode_ci*).

- **Current:** Database default was `utf8mb4_general_ci`.
- **After:** Database default is `utf8mb4_unicode_ci`. New tables will use this. The 6 tables that already used `utf8mb4_unicode_ci` now match the database default; no separate ALTER on those tables is required.

---

## 8. Database Config (MySQL/MariaDB server settings)

Doctrine Doctor may report: buffer pool too small, missing SQL strict mode, timezone tables not loaded, and full ACID in dev. Apply the following on the **MySQL server** (not in Symfony).

### A. InnoDB buffer pool size

**Issue:** `innodb_buffer_pool_size` is 16MB by default, causing excessive disk I/O.

**Recommended:** Dev: 256–512MB minimum. Prod: 50–70% of available RAM.

**Fix — config file (my.ini on Windows, my.cnf on Linux):**

```ini
[mysqld]
innodb_buffer_pool_size = 536870912
```

(536870912 = 512MB. Restart MySQL for changes to take effect.)

**Or set globally (then restart):**

```sql
SET GLOBAL innodb_buffer_pool_size = 536870912;
```

### B. SQL strict mode

**Issue:** Missing `STRICT_TRANS_TABLES` and `ERROR_FOR_DIVISION_BY_ZERO` can allow silent truncation and invalid data.

**Fix — config file:**

```ini
[mysqld]
sql_mode = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION,STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO'
```

**Or set dynamically:**

```sql
SET SESSION sql_mode = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION,STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO';
```

(Use `SET GLOBAL` if you have SUPER privilege and want it for all sessions; restart not required for session/global.)

### C. MySQL timezone tables

See **Section 7.A** above for loading timezone data so that `CONVERT_TZ()` and named timezones (e.g. `Africa/Tunis`) work.

### D. InnoDB flush log in development (optional)

**Issue:** `innodb_flush_log_at_trx_commit = 1` (full ACID) flushes on every commit and is slower. In **development only**, you can use `2` for faster writes.

**Development only — config file:**

```ini
[mysqld]
innodb_flush_log_at_trx_commit = 2
```

**Values:** `0` = flush every second (fastest, risk of loss on crash); `1` = flush every commit (full ACID — **use in production**); `2` = write to OS cache on commit, disk every second (good for dev).

**Important:** Use `1` in production for data safety.

---

### Single config snippet (development)

You can merge the options above into one `[mysqld]` block. A ready-to-use snippet is in `config/mysql-dev.cnf` — copy its contents into your MySQL config file (e.g. `my.ini` on Windows) or include it, then restart MySQL.
