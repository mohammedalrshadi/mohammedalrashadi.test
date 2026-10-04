# Production Deployment Guide

**Platform:** Mohammed Alrashadi — Personal Engineering Platform  
**Target Host:** Hostinger Linux Shared / Cloud Hosting (LSPHP 8.3+ & MariaDB 11.x)  
**Document Standard:** OPS-01 Architectural Deployment & Runbook  
**Last Updated:** 2026-09-16  

---

## 1. System Architecture & Prerequisites

The platform is an editorial engineering platform built with custom PHP, relational MariaDB, and a precompiled Tailwind CSS design system. It requires **zero Node.js or build runtime on the production server**.

### Server Requirements
| Component | Production Requirement | Hostinger Specification |
| :--- | :--- | :--- |
| **PHP Runtime** | PHP 8.3 or higher | `LSPHP 8.3.33` (LiteSpeed PHP) |
| **PHP Extensions** | `pdo_mysql`, `curl`, `json`, `mbstring`, `fileinfo` | Standard in Hostinger PHP 8.3 |
| **Database** | MariaDB 10.6+ or MySQL 8.0+ | `MariaDB 11.8.9-log` |
| **Web Server** | Apache 2.4+ or LiteSpeed with `mod_rewrite` & `mod_expires` | Hostinger Enterprise LiteSpeed |
| **Node.js** | **Not required on server** | Precompiled CSS is committed in repo |

---

## 2. Directory Layout & Web Root Mapping

On Hostinger, the repository root maps directly to the web root:

```text
/home/u303927365/domains/mohammedalrashadi.com/public_html/
├── .htaccess                   # Canonical routing, security, and cache headers
├── .gitignore                  # Excludes local configs, cache, and OS artifacts
├── index.php                   # Homepage with dynamic Bento showcase
├── projects.php                # Systems & projects listing
├── project.php                 # Relational project case study
├── articles.php                # Engineering writing feed
├── post.php                    # Article view with reviews
├── lab.php                     # Controlled benchmarks matrix
├── lab-detail.php              # Benchmark specification
├── gallery.php                 # Relational visual archive (achievement_images)
├── journey.php                 # Engineering trajectory
├── about.php                   # Biographical dossier & contact
├── sitemap.php                 # Dynamic XML sitemap generator
├── sitemap.xml                 # Physical XML sitemap baseline
├── robots.txt                  # Search crawler directives
├── admin/                      # Admin Studio (session-guarded)
│   ├── settings.php            # Admin Control Center (all 5 tabs)
│   ├── run_migrations.php      # Idempotent database migrations runner
│   ├── achievements.php        # Achievements manager (standalone `achievements` table); projects are managed in admin/projects.php
│   ├── articles.php            # Article publication manager
│   ├── media.php               # Relational asset uploader
│   ├── reviews.php             # Review moderation queue
│   └── users.php               # User administration
├── api/                        # JSON REST Endpoints
│   ├── config.php              # Base configuration & error handling
│   ├── config.local.php        # Server-specific credentials (UNTRACKED)
│   ├── db.php                  # PDO singleton with socket fallback
│   ├── auth/                   # Session & CSRF guards
│   ├── posts/                  # Content CRUD
│   ├── settings/               # Site settings update
│   ├── social/                 # Verified social channels
│   └── reviews/                # Moderated reviews
├── css/
│   ├── styles.css              # Global semantic CSS design tokens & components
│   └── tailwind.css            # Precompiled production Tailwind CSS (27KB)
└── uploads/                    # Media upload target (write-enabled, script-locked)
```

### Files and Directories to Exclude from Upload
The following files and directories are for development or local use only. **Do not upload** them to the production server:

- `.git` and `.gitignore`
- `.DS_Store`
- `.agent*` (Antigravity AI agent directories)
- `scratch/` (Temporary scripts)
- `tests/` (Test suite)
- `docs/` (Documentation)
- `*.sqlite` and `*.db` (Local SQLite databases)
- `api/config.local.php` (Should be created directly on the server, not uploaded)

---

## 3. Environment Configuration (`api/config.local.php`)

`api/config.local.php` is strictly untracked in Git for security. On the production server, create this file in `api/`:

```php
<?php
// ============================================================
// PRODUCTION ENVIRONMENT CONFIGURATION — HOSTINGER
// ============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'u303927365_alrashadi');
define('DB_USER', 'u303927365_alrashadi');
define('DB_PASS', 'YOUR_STRONG_DATABASE_PASSWORD');

// Session configuration
define('SESSION_SECURE', true);
define('SESSION_HTTPONLY', true);
define('SESSION_SAMESITE', 'Lax');
```

> [!CAUTION]
> **Hostinger Database Binding Rule:**  
> Always set `DB_HOST` to `'localhost'`. Hostinger binds MariaDB users strictly to `'u303927365_alrashadi'@'localhost'`. Using an IP address (like `127.0.0.1`), custom port (`3307`), or remote hostname (`auth-db1417.hstgr.io`) will result in access denied errors.

---

## 4. Database Migrations Execution

> **Deploy order: MIGRATIONS FIRST, CODE SECOND.** Some code (for example the admin achievements endpoints) queries columns such as `achievements.deleted_at` that only exist after the migrations ran. Deploying code before migrating breaks those endpoints.
>
> Required sequence for every production release that contains migrations:
>
> 1. **Back up the production database** (full dump, stored off the server).
> 2. **Verify the backup** (file size is non-zero, it opens, and it lists the expected tables).
> 3. **Run the migration preview**: open `/admin/run_migrations.php` with a normal GET as administrator. A GET is preview-only and changes nothing; review the pending list.
> 4. **Execute `RUN_PENDING_MIGRATIONS`** only through the approved production process (the admin page's confirmation button sends `{"confirm": "RUN_PENDING_MIGRATIONS"}` with a CSRF token via POST).
> 5. **Verify the results**: re-open the preview and confirm nothing is pending; check the new columns/indexes/rows listed for that release.
> 6. **Deploy the application code.**
> 7. **Run the post-deployment verification checklist** (section 8).
>
> This document does not record that any migration has been executed in production. Migration status must always be read from the live preview and the `schema_migrations` ledger.

All database migrations in `admin/run_migrations.php` are designed to be **strictly idempotent** (`already_applied` guards prevent duplicate column or table errors).

### Running Migrations via Admin Browser:

1. Log in to `/admin/login.php`.
2. Navigate to `https://mohammedalrashadi.com/admin/settings.php?tab=system`.
3. Review the Database Migrations section or click **Run Pending Migrations**.
4. Alternatively, visit `/admin/run_migrations.php` while logged in as an administrator.

### Running Migrations via Hostinger SSH CLI:

```bash
ssh -p 65002 u303927365@mohammedalrashadi.com
cd public_html
php admin/run_migrations.php --cli
```

Each migration runs its check first and reports `applied` or `already_applied`; the list is defined in `$MIGRATIONS` inside `admin/run_migrations.php`.

---

## 5. Directory Permissions

Ensure the web server user has proper read/write access:

```bash
# Set base directory permissions
find . -type d -exec chmod 755 {} +
find . -type f -exec chmod 644 {} +

# Ensure uploads directory is writable by web server
chmod -R 775 uploads/
chmod 775 api/data/
```

### Uploads Security Protection
The root `.htaccess` and `uploads/.htaccess` prevent arbitrary script execution:
```apache
# Block any PHP or executable script handler in uploads/
RewriteRule ^uploads/.*\.(php[0-9]?|phtml|phar|inc)$ - [F,L,NC]
```

---

## 6. Precompiled CSS Workflow (No Server Build Step)

The platform does not rely on third-party runtime CDNs in production. The CSS workflow is:

1. **Input files:** `tailwind.config.js` and `css/tailwind-input.css`.
2. **Local build:** Whenever new Tailwind utility classes are added, run locally:

   ```bash
   npx --yes tailwindcss@3 -i css/tailwind-input.css -o css/tailwind.css --minify
   ```

3. **Commit & Deploy:** `css/tailwind.css` is committed directly to Git. Production serves the minified 27KB static CSS file with cache-busting headers.

---

## 7. Deployment Procedures

### Method A: Hostinger hPanel Git Deployment (Recommended)

1. In Hostinger hPanel, navigate to **Advanced** → **Git**.
2. If already configured, click **Deploy** on the `main` branch repository.
3. Hostinger pulls latest commits into `public_html/`.
4. Verify `api/config.local.php` is intact.

### Method B: SSH Git Deployment

```bash
ssh -p 65002 u303927365@mohammedalrashadi.com
cd public_html
git pull origin main
```

---

## 8. Post-Deployment Verification Checklist

After deploying, run through this 8-point checklist:

- [ ] **Homepage loads:** `https://mohammedalrashadi.com/` returns HTTP 200 with Bento showcase.
- [ ] **Database connectivity:** Hero displays dynamic motto, showcase renders top 4 curated slots.
- [ ] **No CDN in-browser compilation:** Open browser DevTools Network tab. Confirm `cdn.tailwindcss.com` is NOT loaded; `css/tailwind.css` is served.
- [ ] **Admin Authentication:** `/admin/login.php` allows secure login without database alerts.
- [ ] **Admin Control Center:** `/admin/settings.php` has a single `<h1>`, tabs work without redirects to deleted standalone pages.
- [ ] **Search Modal:** Press `⌘K` or click header search icon. Confirm modal opens and searches live articles.
- [ ] **Sitemap & Robots:** `https://mohammedalrashadi.com/sitemap.xml` returns valid XML with modern `.php` endpoints.
- [ ] **Open Graph:** Confirm `<meta property="og:image">` has an absolute URL starting with `https://`.

---

## 9. Rollback & Troubleshooting

### Issue: "Database connection error. Please try again later."

1. Check `api/config.local.php`.
2. Confirm `DB_HOST` is `'localhost'`, NOT `127.0.0.1` or custom port.
3. Verify password matches Hostinger hPanel Database management.

### Issue: 403 Forbidden on static assets or uploads

1. Verify permissions: `chmod 755` for directories, `chmod 644` for images.
2. Check `.htaccess` rules for accidental blocking.

### Issue: Stylesheet changes not visible

1. Confirm `css/tailwind.css` was recompiled and committed before pushing.
2. Clear browser cache or check that `?v=timestamp` query strings updated in `includes/head.php`.

