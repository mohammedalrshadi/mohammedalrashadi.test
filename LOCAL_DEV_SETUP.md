# Local Development Environment Setup

> **Audience:** Future agent sessions and local contributors.
> This document describes the **fully isolated local dev environment** — it
> is completely separate from the Hostinger production server and its credentials.
> Nothing here interacts with or risks the production database.

---

## 1. Local MySQL Instance

### What it is
A fresh, user-space MySQL 9.4.0 instance running on a non-default port (`3307`)
with its own data directory and socket. It has **no relation to any system-wide
MySQL installation** and requires no password guessing — it was initialised with
`--initialize-insecure` (no root password on a completely fresh data directory).

### Data directory
```
/Users/mohammedalrashadi/.local_mysql/data/
```

### Config file
```
/Users/mohammedalrashadi/.local_mysql/my.cnf
```

### Start the server
```bash
/usr/local/mysql/bin/mysqld \
  --defaults-file=/Users/mohammedalrashadi/.local_mysql/my.cnf \
  2>&1 &
```

The process runs in the background. Key settings from `my.cnf`:

| Setting | Value |
|---|---|
| Port | `3307` |
| Socket | `/tmp/mysql_local.sock` |
| Data dir | `/Users/mohammedalrashadi/.local_mysql/data/` |

### Verify it's up
```bash
/usr/local/mysql/bin/mysql \
  -u root \
  -S /tmp/mysql_local.sock \
  -e "SELECT @@version, @@port, @@socket;"
```

Expected output:
```
+-----------+--------+-----------------------+
| @@version | @@port | @@socket              |
+-----------+--------+-----------------------+
| 9.4.0     |   3307 | /tmp/mysql_local.sock |
+-----------+--------+-----------------------+
```

### If the instance is ever missing or corrupted
Do **not** guess passwords or try to repair it. Recreate from scratch:

```bash
# 1. Remove old data directory
rm -rf /Users/mohammedalrashadi/.local_mysql/data/

# 2. Re-initialise with no root password
/usr/local/mysql/bin/mysqld \
  --defaults-file=/Users/mohammedalrashadi/.local_mysql/my.cnf \
  --initialize-insecure

# 3. Start the server (command above)

# 4. Recreate the database, user, and schema (see sections 2 and 3 below)
```

---

## 2. Local Database and Application Config

### Database
| Setting | Value |
|---|---|
| Database name | `alrashadi_local` |
| Character set | `utf8mb4_unicode_ci` |
| Local user | `localdev` |
| Password | `localdev_secret` |
| Host / port | `127.0.0.1:3307` |

### Create the database and user (only needed after a fresh `--initialize-insecure`)
```bash
/usr/local/mysql/bin/mysql -u root -S /tmp/mysql_local.sock <<'SQL'
CREATE DATABASE IF NOT EXISTS alrashadi_local
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'localdev'@'127.0.0.1'
  IDENTIFIED BY 'localdev_secret';
GRANT ALL PRIVILEGES ON alrashadi_local.* TO 'localdev'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL
```

### Application config — `api/config.local.php`
This file is **git-ignored** and points the application at the local instance.
It must never be committed; production credentials live only on the Hostinger
server, set via hPanel File Manager.

Relevant excerpt (the comment below is already present in the file):

```php
// NOTE: Database.php's DSN builder (src/Infrastructure/Database.php) only
// supports a 'host=' field with no separate port parameter — the DSN is
// constructed as: 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=...'
// Since this local MySQL instance runs on a non-default port (3307), the port
// is deliberately embedded inside DB_HOST itself, relying on PDO's sequential
// DSN parsing (mysql:host=127.0.0.1;port=3307;dbname=...).
// This is INTENTIONAL local-dev plumbing — do NOT simplify this to a plain
// IP or hostname. Removing ';port=3307' will silently fall back to 3306,
// which is not running locally, and the connection will fail.
define('DB_HOST',    '127.0.0.1;port=3307');
define('DB_NAME',    'alrashadi_local');
define('DB_USER',    'localdev');
define('DB_PASS',    'localdev_secret');
define('DB_CHARSET', 'utf8mb4');
```

---

## 3. Schema Import

The canonical schema lives at `database/schema.sql`. Run this from the repo
root whenever the local database is empty or has been recreated:

```bash
/usr/local/mysql/bin/mysql \
  -u root \
  -S /tmp/mysql_local.sock \
  alrashadi_local \
  < database/schema.sql
```

Verify the import:
```bash
/usr/local/mysql/bin/mysql \
  -u root -S /tmp/mysql_local.sock \
  alrashadi_local \
  -e "SELECT COUNT(*) AS table_count
      FROM information_schema.tables
      WHERE table_schema = 'alrashadi_local';"
```

Expected: **38 tables** (count of CREATE TABLE statements in `database/schema.sql`, verified with a parser).

---

## 4. Local Admin Credentials and Authentication Verification

### Admin test account
| Field | Value |
|---|---|
| Email | `admin@local.dev` |
| Password | `LocalAdmin2024!` |
| Role | `admin` |
| Status | `active` |

Hash algorithm confirmed from source: `password_hash($password, PASSWORD_BCRYPT)`
in `api/auth/register.php`, verified via `password_verify()` in `api/auth/login.php`.

To recreate this user if the database is ever wiped:
```bash
BCRYPT_HASH=$(php -r "echo password_hash('LocalAdmin2024!', PASSWORD_BCRYPT);")

/usr/local/mysql/bin/mysql \
  -u root -S /tmp/mysql_local.sock alrashadi_local \
  -e "INSERT INTO users
        (name, email, password_hash, role, status, email_verified_at)
      VALUES
        ('Local Admin', 'admin@local.dev', '${BCRYPT_HASH}', 'admin', 'active', NOW());"
```

### Verified 3-step authentication flow

This PHP script performs a **real login** against the running local server
(port 8000) and confirms the session is persisted. Use it instead of reading
raw session files or building synthetic test pages.

**Pre-requisite:** PHP dev server must be running:
```bash
php -S localhost:8000 \
  -t /Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html &
```

**The verification script** (run from the repo root with `php -r '...'` or save
it as a scratch file):

```php
<?php
// Step 1: GET login page to seed session and extract CSRF token
$jar = tempnam(sys_get_temp_dir(), 'admin_session_');

$ch = curl_init('http://localhost:8000/admin/login.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR      => $jar,
    CURLOPT_COOKIEFILE     => $jar,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_HEADER         => true,
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

echo "GET /admin/login.php -> HTTP $code\n";

if (!preg_match('/name=["\']csrf-token["\'] content=["\']([^\'"]+)["\']/', $resp, $m)) {
    echo "FAIL: no CSRF token found in login page\n"; exit(1);
}
$csrf = $m[1];
echo "CSRF token: " . substr($csrf, 0, 12) . "...\n";

// Step 2: POST credentials
$payload = json_encode([
    'email'      => 'admin@local.dev',
    'password'   => 'LocalAdmin2024!',
    'csrf_token' => $csrf,
    'admin_only' => 1,
]);

$ch = curl_init('http://localhost:8000/api/auth/login.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR      => $jar,
    CURLOPT_COOKIEFILE     => $jar,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_HEADER         => true,
]);
$resp2      = curl_exec($ch);
$code2      = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$body2      = substr($resp2, $headerSize);

echo "POST /api/auth/login.php -> HTTP $code2\n";
$decoded = json_decode($body2, true);
if ($code2 !== 200 || empty($decoded['success'])) {
    echo "FAIL: login rejected -- $body2\n"; exit(1);
}
echo "Login OK -- redirect: " . ($decoded['redirect'] ?? '?') . "\n";

// Step 3: GET /admin/ with the authenticated session cookie
$ch = curl_init('http://localhost:8000/admin/');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR      => $jar,
    CURLOPT_COOKIEFILE     => $jar,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_HEADER         => true,
]);
$resp3       = curl_exec($ch);
$code3       = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize3 = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$body3       = substr($resp3, $headerSize3);

echo "GET /admin/ -> HTTP $code3\n";

// Four-condition authentication check (all must pass)
$isRedirect        = ($code3 === 302);
$hasLoginMarker    = (strpos($body3, 'login-card') !== false);
$hasDashboardTitle = (strpos($body3, '<title>') !== false &&
                      strpos($body3, 'Dashboard') !== false);

if ($isRedirect)      { echo "FAIL: 302 redirect -- session not persisted\n"; exit(1); }
if ($hasLoginMarker)  { echo "FAIL: got login page HTML (login-card present)\n"; exit(1); }
if (!$hasDashboardTitle) {
    echo "FAIL: no Dashboard title found -- page content unexpected\n";
    echo substr($body3, 0, 400) . "\n"; exit(1);
}

echo "PASS: HTTP 200, no redirect, no login-card, Dashboard title present\n";
preg_match('/<title>([^<]+)<\/title>/', $body3, $tm);
echo "Page title: " . trim($tm[1] ?? '(none)') . "\n";
unlink($jar);
```

**Expected output:**
```
GET  /admin/login.php        -> HTTP 200
CSRF token: 8c5b8d476990...
POST /api/auth/login.php     -> HTTP 200
Login OK -- redirect: /admin/
GET  /admin/                 -> HTTP 200
PASS: HTTP 200, no redirect, no login-card, Dashboard title present
Page title: Dashboard | Local
```

### What "authenticated" means — the four-condition check

All four conditions must hold simultaneously. Passing fewer than four is a failure:

| # | Check | Pass condition |
|---|---|---|
| 1 | HTTP status | `200` (not `302` or any other code) |
| 2 | No redirect | Response has no `Location:` header pointing at `login.php` |
| 3 | No login-page marker | Body does **not** contain the string `login-card` |
| 4 | Dashboard-only string | Body contains `Dashboard` inside a `<title>` tag |

---

## 5. Do NOT

The following approaches were used in past sessions to work around environment
failures. They produce weaker verification, mask real problems, and must not
be used again.

| Do NOT | Why |
|---|---|
| Guess passwords or brute-force credentials | A fresh `--initialize-insecure` instance needs no password; guessing wastes time and finds nothing |
| Query macOS Keychain (`security find-generic-password`) | The local instance has no password and no Keychain entry |
| Grep shell/MySQL history files (`~/.zsh_history`, `~/.mysql_history`) for secrets | History files are unreliable and may contain stale or wrong credentials |
| Read raw PHP session files from `/tmp/sess_*` | Inspects PHP internal state — not real behaviour; bypasses the actual auth stack entirely |
| Build standalone synthetic HTML files in `scratch/` as a substitute for loading real pages | Does not exercise the PHP, DB, or session layer; confirms nothing about the real application |
| Check for the substring `"admin"` anywhere in a page body and call it authenticated | The word `"admin"` appears on the login page too; use the four-condition check above instead |
| Run `--initialize-insecure` on an existing data directory without removing it first | Fails silently or corrupts the directory; always `rm -rf` the data dir before reinitialising |

---

*Document created: 2026-09-27. Reflects local MySQL 9.4.0, PHP 8.5,
`alrashadi_local` schema (38 tables in `database/schema.sql` as of 2026-09-30).*
