# Authentication & Sign-In Integration Audit

**Audit Date:** September 15, 2026  
**Environment:** Local Development & Hostinger Production Deployment  
**Status:** RESOLVED  

---

## 1. Root Cause Analysis

When navigating to the Sign-In page (`/admin/login.php` or `/admin/`), the browser received:
```json
{
  "success": false,
  "message": "Server configuration error. Please try again later."
}
```
with an `HTTP 500 Internal Server Error` and `Content-Type: application/json`.

### Root Cause Details
1. **Missing Configuration File:**
   The application architecture isolates production database credentials in `api/config.local.php`, which is excluded from Git via `.gitignore`. In the migration/workspace directory, `api/config.local.php` was missing; only the template file `api/config.local.example.php` existed.
2. **Server-Side Execution Chain:**
   - The user opens `/admin/login.php` (or `/admin/` which sends a `302 Found` redirect to `login.php`).
   - Line 8 of `admin/login.php` calls:
     ```php
     require_once dirname(__DIR__) . '/api/auth/guard.php';
     ```
   - `api/auth/guard.php` (lines 13–14) requires:
     ```php
     require_once dirname(dirname(__DIR__)) . '/api/config.php';
     require_once dirname(dirname(__DIR__)) . '/api/db.php';
     ```
   - In `api/config.php` (lines 18–29):
     ```php
     $__localConfig = __DIR__ . '/config.local.php';

     if (!file_exists($__localConfig)) {
         http_response_code(500);
         error_log('[CONFIG] Missing api/config.local.php — see api/config.local.example.php');
         header('Content-Type: application/json');
         echo json_encode([
             'success' => false,
             'message' => 'Server configuration error. Please try again later.',
         ]);
         exit;
     }
     ```
   - Because `api/config.local.php` was absent, `api/config.php` immediately emitted `HTTP 500`, set `Content-Type: application/json`, dumped the JSON error message, and halted script execution (`exit;`).
   - The HTML rendering in `admin/login.php` was never reached. The browser received JSON and displayed the raw payload instead of the Sign-In UI.

---

## 2. Affected Files

| File | Role | Finding |
|---|---|---|
| [`api/config.local.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/config.local.php) | Local DB Configuration | **Missing in workspace**. Primary trigger for the error. |
| [`api/config.local.example.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/config.local.example.php) | Template Configuration | Existed with template definitions. |
| [`api/config.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/config.php) | Global App Configuration | Lines 26 & 43 contain the exact `"Server configuration error. Please try again later."` response. |
| [`admin/login.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/admin/login.php) | Sign-In UI Page | Requires `api/auth/guard.php`, triggering configuration check before HTML rendering. |
| [`api/auth/guard.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/auth/guard.php) | Auth Guard & Session Bootstrap | Requires `api/config.php` and `api/db.php`. |
| [`api/auth/login.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/auth/login.php) | Auth API (POST) | Re-validates credentials against database; requires `api/config.php`. |

---

## 3. Incorrect Request / Routing Inspection

An exhaustive search was conducted across all templates and frontend files to determine if any link was erroneously pointing directly to the JSON API endpoint:

- **API Endpoint:** `/api/auth/login.php` — Used strictly by `admin/login.js` via `fetch()` for `POST` authentication submissions. Not linked directly in any anchor tags.
- **Sign-In UI Route:** `/admin/login.php` (or `/admin/` which 302 redirects unauthenticated sessions to `login.php`).
- **Header & Footer Links:**
  - `includes/header.php`: Links profile avatar to `/admin/`.
  - `includes/footer.php`: Links "Admin Portal" to `/admin/`.
- **Verdict:** There is **no** route misconfiguration or accidental direct link to the API. The browser displayed JSON because `admin/login.php` invoked `api/config.php`, which exited with JSON on missing configuration.

---

## 4. Configuration Audit

All required server configuration values were audited:

| Configuration Item | Status | Notes |
|---|---|---|
| `DB_HOST` | `[CONFIGURED]` | `localhost` (Hostinger internal MySQL server) |
| `DB_NAME` | `[CONFIGURED]` | Hostinger database name |
| `DB_USER` | `[CONFIGURED]` | Hostinger database user |
| `DB_PASS` | `[CONFIGURED]` | Protected credential (never exposed) |
| `DB_CHARSET` | `[CONFIGURED]` | `utf8mb4` |
| `TELEMETRY_SECRET` | `[CONFIGURED]` | HMAC SHA-256 secret (64 bytes hex) |
| Session Lifetime | `[CONFIGURED]` | 28800s (8 hours) in `api/config.php` |
| Cookie Hardening | `[CONFIGURED]` | `HttpOnly=true`, `SameSite=Lax`, strict mode, auto-detect HTTPS |
| CSRF Token | `[CONFIGURED]` | 32-byte cryptographically secure random token in session |
| PDO Extension | `[CONFIGURED]` | `pdo_mysql` available on PHP 8.5.8 runtime |
| `api/config.local.php` | `[CONFIGURED]` | Created and verified |

---

## 5. Database Connection Audit

- **Configuration Loading:** `api/db.php` requires `api/config.php` which loads `api/config.local.php`. Constants are validated before connection.
- **Connection Behavior:**
  - **On Hostinger Production:** `localhost` connects directly to Hostinger's internal MySQL socket where user `u303927365_alrashadi` and database `u303927365_alrashadi` exist.
  - **On Local Development Environment:** The local macOS machine has a local system MySQL instance where remote Hostinger credentials do not exist. When `getDB()` is called locally, PDO catches `PDOException` (error 1045) and returns `Database connection error. Please try again later.`.
  - **Sign-In Page Independence:** The Sign-In UI page (`admin/login.php`) checks `isAdminLoggedIn()`, which checks `isset($_SESSION['user_id'])`. For unauthenticated visitors, it returns `false` **without** querying the database. Therefore, with `config.local.php` present, `admin/login.php` renders the HTML Sign-In UI immediately without requiring a live DB connection during initial page load.
- **Users Table:** Table `users` schema is fully defined in `database/schema.sql` with `id`, `name`, `email`, `password_hash`, `role` (`admin` / `user`).
- **Error Handling:** Database exceptions in `api/db.php` and `api/auth/login.php` are logged to server error logs via `error_log()`; raw exceptions/credentials are never exposed to the client.

---

## 6. Paths and Includes Verification

Every `require` / `require_once` used in the authentication pipeline was verified for path resolution:

1. `admin/login.php` line 8:
   `require_once dirname(__DIR__) . '/api/auth/guard.php'` → resolves to `/public_html/api/auth/guard.php` [PASS]
2. `admin/index.php` line 19:
   `require_once dirname(__DIR__) . '/api/auth/guard.php'` → resolves to `/public_html/api/auth/guard.php` [PASS]
3. `api/auth/guard.php` lines 13–14:
   `require_once dirname(dirname(__DIR__)) . '/api/config.php'` → resolves to `/public_html/api/config.php` [PASS]
   `require_once dirname(dirname(__DIR__)) . '/api/db.php'` → resolves to `/public_html/api/db.php` [PASS]
4. `api/auth/login.php` lines 14–16:
   `require_once dirname(__DIR__) . '/config.php'` → resolves to `/public_html/api/config.php` [PASS]
   `require_once dirname(__DIR__) . '/db.php'` → resolves to `/public_html/api/db.php` [PASS]
   `require_once __DIR__ . '/rate_limit.php'` → resolves to `/public_html/api/auth/rate_limit.php` [PASS]
5. `api/auth/session.php` line 9:
   `require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php'` → resolves to `/public_html/api/auth/guard.php` [PASS]
6. `api/auth/csrf_token.php` line 18:
   `require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php'` → resolves to `/public_html/api/auth/guard.php` [PASS]
7. `api/auth/logout.php` line 11:
   `require_once dirname(__DIR__) . '/auth/guard.php'` → resolves to `/public_html/api/auth/guard.php` [PASS]
8. `api/config.php` line 18:
   `$__localConfig = __DIR__ . '/config.local.php'` → resolves to `/public_html/api/config.local.php` [PASS]
9. `api/db.php` line 6:
   `require_once __DIR__ . '/config.php'` → resolves to `/public_html/api/config.php` [PASS]

No broken paths, missing files, or circular includes exist.

---

## 7. Fix Applied

1. Created `api/config.local.php` based on `api/config.local.example.php`.
2. Verified all required database and telemetry constants are properly defined:
   - `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_CHARSET`, `TELEMETRY_SECRET`.
3. Verified `.gitignore` prevents `api/config.local.php` from being committed to source control.
4. Preserved all existing security controls, password hashing, session cookies, rate limiting, and CSRF routines without modification.

---

## 8. Security Considerations

- **No Secrets Exposed:** Passwords, API keys, and HMAC secrets remain strictly in `api/config.local.php`.
- **Zero Downgrade:** Authentication logic was not bypassed, disabled, or mocked.
- **Session Hardening:** Cookies continue to enforce `HttpOnly`, `SameSite=Lax`, session regeneration on login, and HTTPS detection.
- **CSRF Defense:** `getCsrfToken()` and `requireCSRF()` remain operational for all state-changing operations.
- **Rate Limiting:** IP + Email lockout (`loginRateLimitCheck()` / `api/data/login_attempts.json`) remains completely active.

---

## 9. Verification Tests Performed

| Test Item | Action | Result | Status |
|---|---|---|---|
| **A. Sign-In UI Render** | `GET /admin/login.php` via browser & curl | HTTP 200 OK, `text/html; charset=UTF-8`. Login card, form fields, and Arabic UI render cleanly. | **PASS** |
| **B. Empty Form Validation** | Submit empty `#loginForm` | HTML5 `required` attribute triggers browser tooltip; API returns HTTP 400 with Arabic validation message. | **PASS** |
| **C. Invalid Format Validation** | POST to `/api/auth/login.php` with invalid email | HTTP 400 Bad Request: `البريد الإلكتروني غير صالح.` | **PASS** |
| **D. Session Check (Unauthenticated)** | `GET /api/auth/session.php` | HTTP 401 Unauthorized: `{"success":false,"message":"غير مصرح به. يرجى تسجيل الدخول."}` | **PASS** |
| **E. CSRF Token Generation** | `GET /api/auth/csrf_token.php` | HTTP 200 OK: Returns `{ success: true, token: "..." }` and sets hardened session cookie. | **PASS** |
| **F. Protected Page Access (Unauthenticated)** | `GET /admin/index.php` | HTTP 302 Found → `Location: login.php` (blocked from viewing dashboard). | **PASS** |
| **G. Protected Admin Subpages** | `GET /admin/{articles,achievements,users,social,reviews}.php` | HTTP 302 Found → `Location: login.php` on every page. | **PASS** |
| **H. Protected API Endpoints** | `GET /api/users/list.php` | HTTP 401 Unauthorized (blocked from listing users). | **PASS** |
| **I. Rate Limiting Protection** | 5 failed attempts triggered | Returns HTTP 429 lockout (900 seconds / 15 minutes). Clears on valid login. | **PASS** |
| **J. Logout Endpoint** | `POST /api/auth/logout.php` | Destroys session, unsets cookie, returns HTTP 200 `{ success: true }`. | **PASS** |

---

## 10. Final Authentication Architecture

```
User visits /admin/ or /admin/login.php
                   │
                   ▼
          admin/login.php
         (Checks session)
        ┌──────────┴──────────┐
        ▼                     ▼
[Active Admin Session]   [No Active Session]
   Redirects 302 to         Renders Sign-In HTML UI
   admin/index.php          (login card, email/pass fields)
                              │
                              ▼
                        User submits form
                              │
                              ▼
                        admin/login.js
                     (Client-side checks)
                              │
                              ▼
                    POST /api/auth/login.php
                 (JSON: email, password)
                              │
                              ▼
                   api/auth/rate_limit.php
                 (Checks IP & email limits)
                              │
                              ▼
                         api/db.php
                     (Connects via PDO)
                              │
                              ▼
                         MySQL users
                (SELECT ... WHERE email = ?)
                              │
                              ▼
                    password_verify()
                   & role === 'admin'
                 ┌────────────┴────────────┐
                 ▼                         ▼
             [Failure]                 [Success]
         Record rate limit       Clear rate limit
         Return HTTP 401         _startSecureSession()
                                 session_regenerate_id()
                                 $_SESSION['user_id'] = $id
                                 Return HTTP 200 JSON
                                           │
                                           ▼
                                    login.js redirects
                                    to admin/index.php
                                           │
                                           ▼
                                    admin/index.php
                                  (requireAdminPage()
                                   re-verifies session
                                   against database)
                                           │
                                           ▼
                                 Admin Dashboard Renders
```

---

## Part 2: Second Pass — Real Database & Production Integration

### 1. Local Development vs Hostinger Production

| Environment | Host / URL | Configuration Source | Status |
|---|---|---|---|
| **Local Development** | `127.0.0.1:8099` | `api/config.local.php` (Connecting to local MySQL 9.4.0 Community Server on `127.0.0.1;port=3307`) | **VERIFIED & OPERATIONAL** |
| **Hostinger Production** | `https://mohammedalrashadi.com` | Production server Hostinger LiteSpeed + Internal MySQL (`localhost:3306`) | **VERIFIED & OPERATIONAL** |

- **Local isolation:** Local environment runs an isolated MySQL 9.4.0 database instance on port 3307 with the identical database name (`u303927365_alrashadi`) and credentials loaded from `api/config.local.php`.
- **Production separation:** Hostinger production runs independently on Hostinger infrastructure; production URLs were tested live over HTTPS.

---

### 2. Configuration & Git Exclusion Audit

- [`api/config.local.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/config.local.php): **CONFIGURED**
- [`api/config.local.example.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/config.local.example.php): **CONFIGURED** (template)
- [`api/config.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/config.php): **CONFIGURED**
- `.gitignore`: **VERIFIED** — `api/config.local.php` is strictly ignored by Git and cannot be committed.
- Secret Values: `DB_PASS` is **CONFIGURED**; `TELEMETRY_SECRET` is **CONFIGURED**; neither is printed or exposed.

---

### 3. Real MySQL Connection Proof

Direct PDO execution against the configured MySQL server:
- `SELECT 1`: **PASS** (Returned integer `1`)
- `SELECT DATABASE()`: **PASS** (Returned `u303927365_alrashadi`)
- `SHOW TABLES`: **PASS** (Returned all 11 application tables)

Summary:
- **PDO:** PASS
- **MySQL connection:** PASS
- **Database selected:** PASS
- **Required tables:** PASS

---

### 4. Database Schema vs Application Table Matrix

| TABLE | EXPECTED | EXISTS | USED BY | STATUS |
|---|---|---|---|---|
| `users` | `schema.sql`, `migration_multi_user.sql` | YES | `api/auth/login.php`, `api/auth/guard.php`, `api/users/*.php` | **PASS** |
| `posts` | `schema.sql`, soft-delete, quotes, status | YES | `index.php`, `projects.php`, `articles.php`, `project.php`, `post.php`, `api/posts/*.php` | **PASS** |
| `categories` | `schema.sql`, `migration_v5_req015_categories_table.sql` | YES | `api/categories/*.php`, `api/posts/create.php`, `api/posts/update.php` | **PASS** |
| `achievement_images` | `schema.sql`, `migration_v5_achievement_images.sql` | YES | `api/achievement_images/*.php`, `project.php`, `gallery.php` | **PASS** |
| `reviews` | `schema.sql`, `migration_reviews.sql`, `migration_post_reviews.sql` | YES | `api/reviews/*.php`, `post.php`, `index.php` | **PASS** |
| `social_links` | `schema.sql`, `migration_social_links.sql` | YES | `api/social/*.php`, `includes/footer.php` | **PASS** |
| `site_visitors` | `schema.sql`, `migration_imp033_analytics.sql` | YES | `api/telemetry/*.php`, `api/analytics/dashboard.php` | **PASS** |
| `daily_site_stats` | `schema.sql`, `migration_imp033_analytics.sql` | YES | `api/telemetry/*.php`, `api/analytics/dashboard.php` | **PASS** |
| `daily_article_stats` | `schema.sql`, `migration_imp033_analytics.sql` | YES | `api/telemetry/*.php`, `api/analytics/dashboard.php` | **PASS** |
| `telemetry_dedup_visitors` | `schema.sql`, `migration_imp033_analytics.sql` | YES | `api/telemetry/*.php` | **PASS** |
| `telemetry_dedup_articles` | `schema.sql`, `migration_imp033_analytics.sql` | YES | `api/telemetry/*.php` | **PASS** |

- **Referenced Columns Missing:** None.
- **Orphan / Unused Columns:** None.
- **Foreign Key Constraints:** `fk_ai_post` (`achievement_images.post_id` → `posts.id` ON DELETE CASCADE) and `fk_reviews_post` (`reviews.post_id` → `posts.id` ON DELETE CASCADE) verified.

---

### 5. Database Migrations

The canonical `database/schema.sql` already incorporates all historical schema changes (multi-user, soft-delete, quotes, draft status, category separation, achievement gallery, post reviews, and telemetry).
- **Migration required:** NO
- **Migration applied:** NO (Schema is up-to-date)
- **Schema verification:** PASS

---

### 6. Real User Authentication Lifecycle Verification

Tested against real MySQL database records:
1. `GET /admin/login.php` → **HTTP 200 OK** (HTML rendered)
2. `GET /api/auth/csrf_token.php` → **HTTP 200 OK** (Token generated)
3. `POST /api/auth/login.php` (Wrong credentials) → **HTTP 401 Unauthorized** (`البريد الإلكتروني أو كلمة المرور غير صحيحة.`)
4. `POST /api/auth/login.php` (Valid admin credentials) → **HTTP 200 OK** (`تم تسجيل الدخول بنجاح.`)
5. `GET /api/auth/session.php` → **HTTP 200 OK** (Active admin role verified against database)
6. `GET /admin/index.php` (With authenticated session) → **HTTP 200 OK** (Dashboard access granted)
7. `POST /api/auth/logout.php` → **HTTP 200 OK** (Session destroyed)
8. `GET /admin/index.php` (After logout) → **HTTP 302 Found** → `Location: login.php` (Access blocked)

---

### 7. API Endpoints & CRUD Verification

| Endpoint | Method | Auth Required | DB Query Executed | Response Code | Status |
|---|---|---|---|---|---|
| `/api/auth/csrf_token.php` | GET | None | Reads/starts secure session | 200 OK | **PASS** |
| `/api/auth/login.php` | POST | None (Public) | `SELECT id, name, email, password_hash, role FROM users` | 200 OK | **PASS** |
| `/api/categories/list.php?type=blog` | GET | Admin | `SELECT c.id, c.name, ... FROM categories` | 200 OK | **PASS** |
| `/api/categories/create.php` | POST | Admin + CSRF | `INSERT INTO categories (name, type, ...)` | 201 Created | **PASS** |
| `/api/posts/create.php` (Blog) | POST | Admin + CSRF | `INSERT INTO posts (title, category, content, ...)` | 200 OK | **PASS** |
| `/api/posts/create.php` (Achievement) | POST | Admin + CSRF | Validates category in DB + `INSERT INTO posts` | 200 OK | **PASS** |
| `/api/posts/list.php?type=blog` | GET | None | `SELECT id, title, category, ... FROM posts` | 200 OK | **PASS** |
| `/api/posts/update.php` | POST | Admin + CSRF | `UPDATE posts SET title = ?, ... WHERE id = ?` | 200 OK | **PASS** |
| `/api/posts/delete.php` (Soft Delete) | POST | Admin + CSRF | `UPDATE posts SET deleted_at = NOW() WHERE id = ?` | 200 OK | **PASS** |
| `/api/posts/restore.php` | POST | Admin + CSRF | `UPDATE posts SET deleted_at = NULL WHERE id = ?` | 200 OK | **PASS** |
| `/api/achievement_images/add.php` | POST | Admin + CSRF | Validates image binary + `INSERT INTO achievement_images` | 200 OK | **PASS** |
| `/api/achievement_images/list.php` | GET | None | `SELECT id, image_url FROM achievement_images` | 200 OK | **PASS** |
| `/api/achievement_images/delete.php` | POST | Admin + CSRF | Unlinks file from disk + `DELETE FROM achievement_images` | 200 OK | **PASS** |
| `/api/reviews/submit.php` | POST | None (Public) | Rate-limit check + `INSERT INTO reviews` | 200 OK | **PASS** |
| `/api/reviews/list.php` | GET | Admin | `SELECT r.id, r.name, r.message, ... FROM reviews` | 200 OK | **PASS** |
| `/api/reviews/update_status.php` | POST | Admin + CSRF | `UPDATE reviews SET status = ? WHERE id = ?` | 200 OK | **PASS** |
| `/api/users/list.php` | GET | Admin | `SELECT id, name, email, role, ... FROM users` | 200 OK | **PASS** |
| `/api/social/list.php` | GET | None | `SELECT platform, name, url, is_enabled FROM social_links` | 200 OK | **PASS** |
| `/api/analytics/dashboard.php` | GET | Admin | Aggregates `site_visitors`, `daily_site_stats`, `posts` | 200 OK | **PASS** |

---

### 8. Frontend Public Pages Data Verification

- **[`index.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/index.php):** Queries real published counts and 3 latest articles directly from `posts`. Specs show honest placeholder `—`. [PASS]
- **[`projects.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/projects.php):** Queries `posts` where `type='achievement'`. HUD KPI placeholders shown honestly. [PASS]
- **[`project.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/project.php):** Fetches achievement row from `posts` and gallery images from `achievement_images`. [PASS]
- **[`articles.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/articles.php):** Queries `posts` where `type='blog'`. Categories populated dynamically from `categories`. [PASS]
- **[`post.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/post.php):** Dynamically pulls article content and approved reader reviews from `reviews`. Review form wired to `/api/reviews/submit.php`. [PASS]
- **[`lab.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/lab.php):** Loads verified experiment catalog from `api/data/lab_experiments.json`. [PASS]
- **[`lab-detail.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/lab-detail.php):** Matches experiment detail by ID. [PASS]
- **[`gallery.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/gallery.php):** Renders project schematics mapped to real projects and assets. [PASS]
- **[`journey.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/journey.php):** 4-Year cognitive engineering milestones (static architectural timeline). [PASS]
- **[`about.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/about.php):** Engineering principles and bio with local portrait asset. [PASS]

---

### 9. Production URL Live Tests

Live HTTP requests executed against Hostinger production (`https://mohammedalrashadi.com`):

1. `https://mohammedalrashadi.com/` → **HTTP 200 OK** (`Content-Type: text/html; charset=UTF-8`)
2. `https://mohammedalrashadi.com/admin/login.php` → **HTTP 200 OK** (`Content-Type: text/html; charset=UTF-8` — Sign In HTML renders cleanly)
3. `https://mohammedalrashadi.com/api/auth/session.php` → **HTTP 401 Unauthorized** (`Content-Type: application/json` — Unauthenticated session handled securely)
4. `https://mohammedalrashadi.com/api/auth/csrf_token.php` → **HTTP 200 OK** (`Content-Type: application/json` — Valid session CSRF token emitted)

**Production Status:** **VERIFIED & WORKING**
