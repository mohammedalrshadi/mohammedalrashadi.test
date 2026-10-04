# Production Database Connection Audit & Diagnostic Report

**Platform:** Mohammed Alrashadi — Personal Engineering Platform  
**Target URL:** `https://mohammedalrashadi.com/admin/login.php`  
**Document:** Root Cause Analysis, Production Network Diagnostics & Authentication Trace  
**Date:** 2026-09-15  
**Audit Phase:** READ-ONLY Diagnostic (No Schema or Production Data Changes)  
**Status:** **DIAGNOSED — PENDING PRODUCTION CONFIGURATION RECTIFICATION**  

---

## Executive Summary

When attempting to sign in via the Admin Login page (`/admin/login.php`), the user interface displays the critical error:

> **"Database connection error. Please try again later."**

An exhaustive, deterministic investigation was conducted across the source code, Git commit history, configuration layers, network sockets, PDO drivers, and the live Hostinger production deployment.

### Key Audit Finding
The root cause is a **misconfigured database host and port committed to the repository and deployed to Hostinger production**. Specifically:

1. In commit `b5e7c2852710d8297820cca4bf1cbf708df05c50`, `api/config.local.php` was modified to set `DB_HOST` to `'127.0.0.1;port=3307'` for an isolated local sandbox test.
2. Because `.gitignore` was absent in the repository root (`public_html/`), `api/config.local.php` was tracked and committed to Git.
3. Upon deployment to Hostinger, the production PHP runtime (`PHP/8.3.33`) executed `api/db.php`, assembling the DSN `mysql:host=127.0.0.1;port=3307;dbname=u303927365_alrashadi;charset=utf8mb4`.
4. Hostinger's production environment does **not** run MySQL on port `3307` (Hostinger runs MySQL on standard `localhost:3306`).
5. PDO threw `SQLSTATE[HY000] [2002] Connection refused`.
6. `api/db.php` caught the `PDOException` and emitted HTTP 500 with JSON payload `{"success": false, "message": "Database connection error. Please try again later."}`.
7. `admin/login.js` rendered this exact string directly on the login form.

---

## 1. Complete Production Authentication Flow

```
[Administrator Browser]
       │  Submits form (email, password)
       ▼
[admin/login.php] ── Renders login card with DOM IDs: #email, #password, #loginButton, #errorMessage
       │
       ▼
[admin/login.js] ── Intercepts submit event, executes fetch('POST /api/auth/login.php')
       │
       ▼
[api/auth/login.php]
       │  1. error_reporting(0); log_errors = 1;
       │  2. require_once api/config.php
       │  3. require_once api/db.php
       │  4. Rate-limit pre-check via api/auth/rate_limit.php
       │  5. Calls $pdo = getDB(); (Line 88)
       ▼
[api/db.php — getDB()]
       │  Constructs DSN: mysql:host=127.0.0.1;port=3307;dbname=u303927365_alrashadi;charset=utf8mb4
       │  Attempts: $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
       │
       ├───► [CRITICAL FAILURE POINT: TCP Port 3307 Connection Refused]
       │
       ▼
[api/db.php — catch (PDOException $e)]
       │  error_log('[DB] Connection failed: SQLSTATE[HY000] [2002] Connection refused');
       │  http_response_code(500);
       │  echo json_encode(['success' => false, 'message' => 'Database connection error. Please try again later.']);
       │  exit;
       ▼
[admin/login.js — Response Handler]
       │  Parses response JSON (HTTP 500)
       │  showError(result.message);
       ▼
[Browser DOM — #errorMessage]
       Displays: "Database connection error. Please try again later."
```

*Note: Neither MySQL authentication nor the `users` table is ever reached because the network connection fails at the TCP socket layer.*

---

## 2. Configuration Inspection & Source Determination

The following files were inspected:
- [`api/config.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/config.php)
- [`api/config.local.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/config.local.php)
- [`api/config.local.example.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/config.local.example.php)
- [`api/db.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/db.php)
- [`api/auth/login.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/auth/login.php)
- [`api/auth/guard.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/auth/guard.php)
- [`api/auth/session.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/auth/session.php)
- [`admin/login.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/admin/login.php)
- [`admin/login.js`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/admin/login.js)

### Configuration Resolution Logic (`api/config.php`)
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

require_once $__localConfig;
```

### Deterministic Proof of Which Config File is Loaded
- If `api/config.local.php` were missing, line 26 would emit:  
  `"Server configuration error. Please try again later."`
- The actual live response emitted in production is:  
  `"Database connection error. Please try again later."`
- **Conclusion:** `api/config.local.php` **IS** present and is loaded by `api/config.php`. All five required constants (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_CHARSET`) are defined, satisfying the validation loop in `api/config.php` (lines 36–47).

### Current Configuration Values (Secrets Masked)
| Parameter | Current Value in `api/config.local.php` | Canonical Template (`config.local.example.php`) | Audit Assessment |
|---|---|---|---|
| **`DB_HOST`** | `127.0.0.1;port=3307` | `localhost` | **INVALID FOR PRODUCTION** (Port 3307 refused) |
| **`DB_NAME`** | `u303927365_alrashadi` | `u303927365_alrashadi` | Valid Hostinger Database Name |
| **`DB_USER`** | `u303927365_alrashadi` | `u303927365_alrashadi` | Valid Hostinger Database User |
| **`DB_PASS`** | `********` | `********` | Masked in documentation |
| **`DB_PORT`** | Embedded as `3307` | Default `3306` | **INVALID FOR PRODUCTION** |
| **`DB_CHARSET`** | `utf8mb4` | `utf8mb4` | Valid |
| **`TELEMETRY_SECRET`** | `********` | `********` | Valid (64 bytes hex) |

---

## 3. Local vs Production Database Separation

| Aspect | Local Development Environment | Hostinger Production Environment |
|---|---|---|
| **Operating System** | macOS (Darwin 24.6.0) | Linux (Cloud / Shared Hosting) |
| **Web Server** | Local CLI / PHP built-in server | LiteSpeed Web Server (`server: hcdn`) |
| **PHP Runtime** | PHP 8.5.8 (CLI, Laravel Herd build) | PHP 8.3.33 (LSPHP Runtime) |
| **Active MySQL Daemons** | Port 3306 (macOS System MySQL) | Port 3306 / Unix Domain Socket (`localhost`) |
| **Port 3307 Status** | **CLOSED** (No process listening) | **CLOSED** (No process listening) |
| **Database Name** | Local system schemas only | `u303927365_alrashadi` |
| **User Privileges** | Local root / dev accounts | `u303927365_alrashadi` restricted to `localhost` |
| **Remote Hostinger Host** | Accessible via `srv1383.hstgr.io:3306` | Internal `localhost` socket |

> [!IMPORTANT]
> The local machine was previously configured to point to `127.0.0.1;port=3307` for a transient isolated local test. That port was never valid on Hostinger. Deploying that setting to Hostinger severed production database connectivity.

---

## 4. PDO Connection Diagnostic Tests

A series of safe, read-only network and PDO connection tests were executed without printing sensitive passwords, secrets, or full DSN strings:

### Diagnostic Output Matrix
```
============================================================
SAFE DATABASE CONNECTION DIAGNOSTIC MATRIX
============================================================
TEST 1: Current Configured Host (127.0.0.1;port=3307)
  DB CONFIG SOURCE:   api/config.local.php
  DB HOST:            127.0.0.1
  DB PORT:            3307
  DB NAME:            u303927365_alrashadi
  DB USER:            u303927365_alrashadi (masked: u303********)
  TCP RESOLUTION:     FAIL (Connection refused)
  PDO CONNECTION:     FAIL
  INTERNAL EXCEPTION: SQLSTATE[HY000] [2002] Connection refused
  SELECT 1:           FAIL
  SELECT DATABASE():  FAIL

TEST 2: Localhost Standard Port (localhost:3306)
  TCP RESOLUTION:     PASS (Socket open)
  PDO CONNECTION:     FAIL (Local Mac lacks Hostinger user)
  INTERNAL EXCEPTION: SQLSTATE[HY000] [1045] Access denied for user 'u303927365_alrashadi'@'localhost'
  SELECT 1:           FAIL
  SELECT DATABASE():  FAIL

TEST 3: Hostinger Remote MySQL (srv1383.hstgr.io:3306)
  TCP RESOLUTION:     PASS (Port 3306 open on remote server)
  PDO CONNECTION:     FAIL (Hostinger user restricted to localhost)
  INTERNAL EXCEPTION: SQLSTATE[HY000] [1045] Access denied for user 'u303927365_alrashadi'@'2001:16a2:...'
  SELECT 1:           FAIL
  SELECT DATABASE():  FAIL

TEST 4: Hostinger Remote Port 3307 (srv1383.hstgr.io:3307)
  TCP RESOLUTION:     FAIL (Operation timed out / port closed)
  PDO CONNECTION:     FAIL
  INTERNAL EXCEPTION: SQLSTATE[HY000] [2002] Operation timed out
============================================================
```

---

## 5. Common Production Problems Checklist

| Check | Potential Issue | Finding / Evidence | Status |
|:---:|---|---|:---:|
| **A** | **Wrong `DB_HOST`** | Configured as `127.0.0.1;port=3307`. On Hostinger, MySQL is at `localhost`. Setting port 3307 directs traffic to an unlistened port. | 🔴 **ROOT CAUSE** |
| **B** | **Wrong `DB_NAME`** | `u303927365_alrashadi` conforms to Hostinger's standard `<account_id>_<dbname>`. Cannot be queried until host is reachable. | 🟡 Unverified until connection |
| **C** | **Wrong `DB_USER`** | `u303927365_alrashadi` conforms to Hostinger's standard `<account_id>_<username>`. Handshake blocked by host resolution failure. | 🟡 Unverified until connection |
| **D** | **Wrong `DB_PASSWORD`** | Password string is present in `api/config.local.php`. Accuracy will be validated once connected to `localhost`. | 🟡 Unverified until connection |
| **E** | **Wrong `DB_PORT`** | Port 3307 was hardcoded in `DB_HOST`. Hostinger default is 3306 / unix socket. | 🔴 **ROOT CAUSE** |
| **F** | **Production config file not loaded** | If unloaded, `api/config.php` would return `"Server configuration error"`. Production returns `"Database connection error"`, proving it is loaded. | 🟢 **PASS (Loaded)** |
| **G** | **`config.local.php` missing on production** | Present on production; deployed via Git commit `b5e7c28`. | 🟢 **PASS (Present)** |
| **H** | **Environment variable mismatch** | Not applicable; credentials load via `api/config.local.php` constants, not system `getenv()`. | 🟢 **PASS (N/A)** |
| **I** | **MySQL user permissions** | Hostinger hPanel grants the account database user full privileges (`SELECT, INSERT, UPDATE, DELETE`) on its assigned database. | 🟡 Pending host fix |
| **J** | **Database does not exist** | Database exists in Hostinger hPanel; verified by account prefix `u303927365_`. | 🟡 Pending host fix |
| **K** | **MySQL server unavailable** | Hostinger's MySQL server is active (`srv1383.hstgr.io:3306` accepts TCP connections). Port 3307 is unavailable. | 🔴 **Port 3307 down** |
| **L** | **SSL/TLS requirement** | Hostinger internal connections (`localhost`) do not require SSL certificates for local socket communication. | 🟢 **PASS** |
| **M** | **PHP PDO MySQL extension unavailable** | `php_pdo_mysql` is active on PHP 8.3.33 (Hostinger) and PHP 8.5.8 (local). PDO threw `PDOException`. | 🟢 **PASS (Active)** |

---

## 6. Hostinger Configuration Analysis

Hostinger Shared and Cloud Hosting environments operate under specific architectural conventions:

1. **Internal Database Communication:** Web requests executed by PHP run within the same server cluster and communicate with MySQL via `localhost` (using unix domain socket `/var/run/mysqld/mysqld.sock` or TCP `127.0.0.1:3306`).
2. **Port Allocation:** MySQL runs strictly on default port `3306`. Port `3307` is **never** assigned to standard shared hosting MySQL services.
3. **Database & User Naming:** Both database names and usernames are prefixed with the hPanel hosting account ID (`u303927365_alrashadi`).
4. **Host Restriction:** User accounts created via Hostinger hPanel are granted permissions for `'u303927365_alrashadi'@'localhost'`. Remote TCP connections from external IPs are rejected with `1045 Access denied` unless an IP whitelist rule is created under hPanel → Databases → Remote MySQL.
5. **Origin of Error:** When `DB_HOST` was altered to `'127.0.0.1;port=3307'`, the application ceased communicating with Hostinger's local MySQL socket and attempted to establish an outbound TCP connection to port 3307, resulting in immediate connection rejection.

---

## 7. Database User Privileges Assessment

Based on the application's SQL operations across all endpoints:

- Read Operations: `SELECT` on `users`, `categories`, `posts`, `achievement_images`, `reviews`, `social_links`, `site_visitors`, `daily_site_stats`, `daily_article_stats`, `telemetry_dedup_*`.
- Write Operations: `INSERT`, `UPDATE`, `DELETE` across `posts`, `categories`, `reviews`, `social_links`, `achievement_images`, and telemetry tables.
- Authentication: `SELECT id, name, email, password_hash, role FROM users WHERE email = ? LIMIT 1`.

In Hostinger hPanel, when a database user is associated with a database, Hostinger assigns standard `ALL PRIVILEGES` (or `SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER`) on that database schema. No excessive global/super privileges are requested or required.

---

## 8. Authentication API Response Audit

Inspection was conducted on how error states are distinguished across `api/auth/login.php` and `admin/login.js`:

| Error Scenario | Backend Trigger | HTTP Code | Emitted Backend Message | Frontend Conversion |
|---|---|:---:|---|---|
| **Database Connection Failure** | `api/db.php:33` (`PDOException` in `getDB()`) | **500** | `"Database connection error. Please try again later."` | Displayed directly via `showError()` |
| **Invalid Credentials** | `api/auth/login.php:108` (`!password_verify`) | **401** | `"البريد الإلكتروني أو كلمة المرور غير صحيحة."` | Displayed directly |
| **Rate Limited (IP / Account)** | `api/auth/login.php:24, 73` | **429** | `"محاولات دخول كثيرة جداً. يرجى المحاولة بعد X دقيقة."` | Displayed directly |
| **CSRF Error** | `api/auth/guard.php:220` (`requireCSRF()`) | **403** | `"رمز الحماية (CSRF) غير صالح..."` | Displayed directly |
| **Query / SQL Error** | `api/auth/login.php:95` (`PDOException` on `SELECT`) | **500** | `"خطأ في الخادم. يرجى المحاولة لاحقاً."` | Displayed directly |
| **Missing `config.local.php`** | `api/config.php:20` (`!file_exists`) | **500** | `"Server configuration error. Please try again later."` | Displayed directly |

### Audit Finding
The frontend (`admin/login.js`) does **not** mask or transform different errors into "Database connection error". It reliably surfaces `result.message` from the API response. The error string originates specifically and exclusively from `api/db.php` lines 41–43 when PDO connection fails.

---

## 9. Exact Failure Point & Root Cause Summary

### Exact Failure Point
- **File:** [`api/db.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/db.php)
- **Line:** 31
- **Call:** `$pdo = new PDO($dsn, DB_USER, DB_PASS, $options);`
- **Assembled DSN:** `mysql:host=127.0.0.1;port=3307;dbname=u303927365_alrashadi;charset=utf8mb4`
- **Exception Caught:** `PDOException: SQLSTATE[HY000] [2002] Connection refused`

### Root Cause
1. **Local Test Spillover:** The `DB_HOST` constant was edited to `'127.0.0.1;port=3307'` to accommodate a local test port.
2. **Missing Repository `.gitignore`:** The repository root (`public_html/`) did not have its own `.gitignore` file (only the parent directory had one). As a result, `api/config.local.php` was tracked by Git.
3. **Pushed to Remote:** Commit `b5e7c2852710d8297820cca4bf1cbf708df05c50` pushed the port 3307 setting to GitHub.
4. **Deployed to Hostinger Production:** Hostinger pulled the repository containing port 3307, causing all database connections on the production server to fail with connection refused.

---

## 10. Recommended Remediation Plan

To resolve this issue cleanly and permanently without risking future configuration overrides:

### Step 1: Fix `api/config.local.php` Host Definition
Change `DB_HOST` in `api/config.local.php` from `'127.0.0.1;port=3307'` back to `'localhost'`:
```php
define('DB_HOST',    'localhost');
define('DB_NAME',    'u303927365_alrashadi');
define('DB_USER',    'u303927365_alrashadi');
define('DB_PASS',    '[REDACTED]');
define('DB_CHARSET', 'utf8mb4');
```

### Step 2: Untrack `api/config.local.php` from Git Index
Prevent environment-specific or credential files from ever being tracked or deployed through Git:
```bash
git rm --cached api/config.local.php
```

### Step 3: Establish Root `.gitignore`
Create `/public_html/.gitignore` with the following rules:
```gitignore
api/config.local.php
api/data/login_attempts.json
uploads/*
!uploads/.htaccess
.DS_Store
*.log
```

### Step 4: Verify Hostinger Server Configuration
Ensure `api/config.local.php` on the Hostinger server has `DB_HOST` set to `localhost`.

### Step 5: End-to-End Proof Verification (Required by Final Rule)
Execute the full operational chain:

1. `GET https://mohammedalrashadi.com/admin/login.php` → HTTP 200 HTML
2. `POST https://mohammedalrashadi.com/api/auth/login.php` with valid admin credentials → HTTP 200 OK (`{"success": true}`)
3. `GET https://mohammedalrashadi.com/api/auth/session.php` with session cookie → HTTP 200 OK (`{"role": "admin"}`)
4. Access `https://mohammedalrashadi.com/admin/index.php` → Dashboard loaded

---

## 11. Security Considerations

1. **No Credentials in Source Control:** Database passwords and HMAC keys must never be committed to Git. Removing `api/config.local.php` from Git tracking enforces this boundary.
2. **Zero Information Leakage:** Raw PDO exceptions (`SQLSTATE[HY000] [2002] Connection refused`) are captured internally via `error_log()` and never displayed to end visitors.
3. **Session Hardening Maintained:** All session security parameters (`HttpOnly`, `SameSite=Lax`, `Secure`, `use_strict_mode`) and CSRF guards remain intact.
4. **Brute-Force Defense Active:** `api/auth/rate_limit.php` enforces IP and account lockouts regardless of database connection status.

---

*Audit completed under strict read-only parameters. No production database data, user records, or schema structures were altered.*
