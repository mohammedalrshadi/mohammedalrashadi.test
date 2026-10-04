# Full Project Audit — Multi-Discipline Engineering Report

**Target Platform:** Mohammed Alrashadi Personal Engineering Platform (`public_html`)  
**Audit Type:** Comprehensive Multi-Discipline System Inspection (No code modifications applied)  
**Date of Audit:** 2026-09-16  
**Auditing Lens:** Senior Architect, Security, Database, API, Frontend, UX/Design, Accessibility, QA, DevOps, SEO, Technical Writing  

---

## 1. Executive Summary

The platform has transitioned substantially from its legacy static Arabic prototype state into a functional, custom PHP/MariaDB application with dedicated admin studio capabilities, real session authentication, CSRF controls, and a newly implemented keyboard-driven search modal.

However, beneath the surface improvements, **significant architectural fractures, credential leakage in git history, and design-to-implementation disconnects remain**:
1. **Critical Security Leak in Git History:** While `api/config.local.php` was untracked from the working tree, the plaintext database credentials (`[REDACTED]` and `[REDACTED]`) remain fully preserved in multiple commits reachable on `main` and pushed to GitHub (`origin/main`). Anyone with repository read access can dump these credentials with `git log -p`. **Action required by owner:** rotate the production DB password in Hostinger hPanel.
2. **Fabricated Content & Disconnected Data Stores:** Despite the strict "no invented content" rule, the codebase still contains hardcoded fallback arrays featuring the fictional project `Langomind` with fabricated metrics (`views: 1420`) in `projects.php`, `project.php`, `articles.php`, `gallery.php`, and `journey.php`. Furthermore, `gallery.php` ignores the database table `achievement_images` entirely, serving a static, hardcoded array instead.
3. **Broken SEO & 404 Sitemap Crawling:** `robots.txt` points search engine crawlers to `https://mohammedalrashadi.com/sitemap.xml`, which does not exist on disk and has no `.htaccess` rewrite rule. When `sitemap.php` is accessed directly, it outputs URLs to dead legacy `.html` files (`articles.html`, `achievements.html`, `post.html`), yielding 404s for search engines.
4. **Admin Control Center Regressions:** `admin/settings.php` suffers from duplicate `<h1>` titles and broken HTML wrapper divs resulting from an uncleaned merge. Furthermore, the approved architecture decision to consolidate Social Links and Home Showcase as tabs inside the Control Center was only partially implemented—`showcase.php` and `social.php` remain active as separate sidebar entries in `admin/partials/layout_top.php`.

---

## 2. Scorecard

| Discipline | Status | Primary Rationale |
| :--- | :---: | :--- |
| **1. Architecture & Code Quality** | **Needs Work** | Good separation of concerns in modern endpoints, but severe logic duplication in database fallbacks and an unhandled `_setup_first_admin.php` backdoor script in root. |
| **2. Security** | **Critical** | Production database passwords are permanently leaked in public git commit history, and `uploads/` lacks `.htaccess` executable script execution blocking. |
| **3. Database** | **Needs Work** | Clean relational schema and idempotent migrations, but `gallery.php` completely orphans `achievement_images`, and reviews for soft-deleted posts are not filtered on public queries. |
| **4. Backend / API** | **Good** | Standardized JSON envelopes, consistent HTTP status codes, robust prepared statements, and thorough `requireAuth()` + `requireCSRF()` coverage on all write endpoints. |
| **5. Frontend Implementation** | **Needs Work** | Heavy reliance on the uncompiled Tailwind CSS runtime CDN in production; category filter pills are hardcoded HTML instead of reading from the dynamic `categories` table. |
| **6. UX / Design Coherence** | **Needs Work** | Public pages successfully adopt the calm, editorial aesthetic, but admin settings contains jarring duplicate headers and orphaned sidebar links. |
| **7. Accessibility** | **Needs Work** | Light mode text-muted contrast (`#94a3b8` on `#f8fafc`) fails WCAG AA at 2.5:1; touch targets on desktop header icons are below 44px; reduced-motion is properly supported. |
| **8. QA & Test Integrity** | **Needs Work** | Past reports repeatedly claimed production readiness based solely on reading code or testing in-memory JSON paths while bypassing database and browser execution. |
| **9. DevOps & Deployment** | **Critical** | Zero deployment automation or documentation; `.DS_Store` tracked in git; local `main` is ahead of remote with no deployment pipeline. |
| **10. SEO & Content** | **Critical** | `robots.txt` points to a non-existent `sitemap.xml`; `sitemap.php` generates dead URLs to legacy `.html` files; `og:image` uses an invalid relative URL. |
| **11. Technical Documentation** | **Needs Work** | `docs/DATA_SOURCE_MATRIX.md` documents fictional projects like `Langomind` as "Real Class A Data" despite explicit project rules forbidding fabricated content. |

---

## 3. Findings by Discipline

### Discipline 1: Senior Software Engineer / Architect

#### Finding 1.1: Root Setup Script Left in Document Root (`_setup_first_admin.php`)
- **Location:** `_setup_first_admin.php#L1-L55`
- **Evidence:**
  ```bash
  $ ls -la _setup_first_admin.php
  -rw-r--r--@ 1 mohammedalrashadi staff 3156 _setup_first_admin.php
  ```
- **Analysis:** This script allows anyone to register an administrative user if the `users` table is empty. Although line 26 checks `SELECT COUNT(*) FROM users`, having an administrative account provisioning script accessible via public HTTP in a production repository is an architectural anti-pattern and a severe risk if a database is ever re-initialized or wiped.
- **Severity:** High

#### Finding 1.2: Hardcoded Fallback Arrays Duplicating Fictional Data
- **Location:** `projects.php#L24-L64`, `articles.php#L24-L50`, `project.php#L26-L70`
- **Evidence:**
  In `projects.php`:
  ```php
  // Line 28
  'title' => 'Langomind — Resilient Distributed Event-Driven Engine',
  'views' => 1420
  ```
  In `articles.php`:
  ```php
  // Line 28
  'title' => 'Designing Idempotent REST APIs: Surviving Network Partitions & Retries in Distributed Pipelines',
  'views' => 1840
  ```
- **Analysis:** Instead of returning a clean, honest empty state or a graceful database-offline notice, the public pages silently swallow DB connection errors and hydrate the page with hardcoded arrays containing fictional projects and fabricated view counts.

#### Finding 1.3: Error Handling in `getDB()` Singleton
- **Location:** `api/db.php#L44-L59`
- **Evidence:**
  ```php
  catch (PDOException $e) {
      error_log('[DB] Connection failed: ' . $e->getMessage());
      throw $e;
  }
  ```
- **Analysis:** The fix to ensure `getDB()` does not emit JSON and call `exit;` is confirmed in place. It re-throws `PDOException`, allowing callers (both API endpoints and public templates) to handle exceptions in their own context.

---

### Discipline 2: Security

#### Finding 2.1: Plaintext Passwords Persist in Git History
- **Location:** Git commit history on branch `main` (`commits: 35a2954, 89858e4, 19c6504, 92da311, afca7e4, 48fb5dd, 42198d9`)
- **Evidence:**
  ```bash
  $ git log -S "[REDACTED]" --oneline
  42198d9 docs: redact database password in production connection audit
  48fb5dd security: stop tracking api/config.local.php
  afca7e4 feat(admin): enhance Control Center with new Home Showcase and Social Links sections; update settings page for better diagnostics
  92da311 chore: untrack cache file, add .gitignore, and rotate database credentials
  35a2954 Initial commit

  $ git log -p | grep -E "define\('DB_PASS'" | head -n 4
  -define('DB_PASS',    '[REDACTED]');
  -define('DB_PASS',    '[REDACTED]');
  +define('DB_PASS',    '[REDACTED]');
  ```
- **Analysis:** Untracking `api/config.local.php` via `git rm --cached` only removed the file from future commits; it did **not** purge the secrets from historical blobs. Both the initial password (`[REDACTED]`) and the rotated password (`[REDACTED]`) remain fully readable in git history on GitHub (`origin/main`). **Action required by owner:** rotate the production DB password in Hostinger hPanel, then decide whether to rewrite history (force-push).
- **Severity:** Critical

#### Finding 2.2: Missing Executable Script Execution Guard in `uploads/`
- **Location:** `uploads/`, `.htaccess#L34`
- **Evidence:**
  In `.htaccess`:
  ```apache
  RewriteRule ^uploads/.*\.php$ - [F,L,NC]
  ```
- **Analysis:** The rule only matches `.php$`. On shared Apache/Hostinger environments, files with extensions like `.phtml`, `.php5`, `.php7`, `.phar`, or `.inc` may be parsed by PHP handlers if not explicitly disabled. Furthermore, there is no `.htaccess` file inside `uploads/` setting `php_flag engine off` or `SetHandler default-handler`.
- **Severity:** Medium

#### Finding 2.3: CSRF Coverage Across Write Endpoints
- **Location:** `api/*`
- **Evidence:**
  Audited all 18 state-changing POST/DELETE endpoints via automated reflection script:
  - `api/achievement_images/add.php`: `requireAuth()` + `requireCSRF()`
  - `api/achievement_images/delete.php`: `requireAuth()` + `requireCSRF()`
  - `api/categories/create.php`: `requireAuth()` + `requireCSRF()`
  - `api/categories/delete.php`: `requireAuth()` + `requireCSRF()`
  - `api/categories/rename.php`: `requireAuth()` + `requireCSRF()`
  - `api/posts/create.php`: `requireAuth()` + `requireCSRF()`
  - `api/posts/delete.php`: `requireAuth()` + `requireCSRF()`
  - `api/posts/hide.php`: `requireAuth()` + `requireCSRF()`
  - `api/posts/publish.php`: `requireAuth()` + `requireCSRF()`
  - `api/posts/purge.php`: `requireAuth()` + `requireCSRF()`
  - `api/posts/restore.php`: `requireAuth()` + `requireCSRF()`
  - `api/posts/update.php`: `requireAuth()` + `requireCSRF()`
  - `api/reviews/delete.php`: `requireAuth()` + `requireCSRF()`
  - `api/reviews/submit.php`: Public `requireCSRF()` + Rate Limiting
  - `api/reviews/update_status.php`: `requireAuth()` + `requireCSRF()`
  - `api/settings/update.php`: `requireAuth()` + `requireCSRF()`
  - `api/social/update.php`: `requireAuth()` + `requireCSRF()`
  - `api/uploads/image.php`: `requireAuth()` + `requireCSRF()`
  - `api/users/create.php`: `requireAuth()` + `requireCSRF()`
  - `api/users/delete.php`: `requireAuth()` + `requireCSRF()`
  - `api/users/update.php`: `requireAuth()` + `requireCSRF()`
- **Analysis:** All state-changing administrative endpoints strictly enforce both admin session authentication and timing-safe `hash_equals()` CSRF tokens. Public submission endpoints (`api/reviews/submit.php`) enforce session-bound CSRF and IP rate limiting.
- **Severity:** Verified Secure (Good)

---

### Discipline 3: Database

#### Finding 3.1: Complete Disconnect Between `achievement_images` and Public Gallery
- **Location:** `gallery.php#L8-L57`, `database/schema.sql#L116-L129`
- **Evidence:**
  In `gallery.php`:
  ```php
  // Curated Gallery Artifacts with Shared System Connections
  $artifacts = [
      ['id' => 'ART-01', 'title' => 'Decoupled State Octree...', ...],
      ['id' => 'ART-02', 'title' => 'Langomind Distributed...', ...],
      ...
  ];
  ```
- **Analysis:** The database defines `achievement_images` with administrative CRUD (`admin/media.php`), but `gallery.php` contains zero SQL queries and renders a static, hardcoded array. The public gallery does not reflect any database uploads.
- **Severity:** High

#### Finding 3.2: Missing Soft-Delete Filter on Post-Specific Public Reviews Query
- **Location:** `api/reviews/list.php#L120-L138`
- **Evidence:**
  ```php
  if ($requestedPostId !== null) {
      $conditions[] = 'r.post_id = ?';
      $params[]     = $requestedPostId;
  }
  ...
  $fromClause = 'reviews r';
  ```
- **Analysis:** When a public visitor requests `api/reviews/list.php?post_id=N`, the endpoint queries `FROM reviews r WHERE r.status = 'approved' AND r.post_id = ?` without joining `posts` to verify `posts.deleted_at IS NULL`. If post `N` is soft-deleted, approved reviews for that post remain publicly retrievable via the API.
- **Severity:** Medium

#### Finding 3.3: Idempotency of Database Migrations
- **Location:** `admin/run_migrations.php#L117-L403`
- **Evidence:**
  All 11 migrations implement a condition check (`check => fn(PDO $pdo) => tableExists(...)` or `columnExists(...)`). Tested empirically against local MariaDB: running migrations repeatedly returns `already_applied` without modifying table structure or throwing duplicate column errors.
- **Severity:** Verified Stable (Good)

---

### Discipline 4: Backend / API

#### Finding 4.1: Consistent JSON Envelopes and HTTP Status Codes
- **Location:** `api/*`
- **Evidence:**
  - Success responses consistently return `{ "success": true, "data": ... }` with HTTP 200.
  - Authentication errors return `{ "success": false, "message": "غير مصرح به..." }` with HTTP 401.
  - CSRF errors return `{ "success": false, "message": "رمز الحماية..." }` with HTTP 403.
  - Method mismatch returns `{ "success": false, "message": "Method not allowed." }` with HTTP 405.
- **Analysis:** Contractual consistency across the API surface is disciplined and predictable.

#### Finding 4.2: SQL Parameterization & LIKE Escaping
- **Location:** `api/posts/list.php#L178-L186`, `api/search.php#L118-L135`
- **Evidence:**
  In `api/search.php`:
  ```php
  $escapedQuery = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $rawQuery);
  $searchPattern = '%' . $escapedQuery . '%';
  ...
  title LIKE :p1 ESCAPE '\\\\'
  ```
- **Analysis:** All user search queries are escaped for wildcard characters (`%`, `_`, `\`) prior to prepared statement execution, neutralizing wildcard amplification and SQL injection.

---

### Discipline 5: Frontend Implementation

#### Finding 5.1: Tailwind CSS Play CDN in Production
- **Location:** `includes/head.php#L51`
- **Evidence:**
  ```html
  <!-- Tailwind CSS via CDN + Shared Editorial Configuration -->
  <script src="https://cdn.tailwindcss.com"></script>
  ```
- **Analysis:** `cdn.tailwindcss.com` is an in-browser JIT compiler meant exclusively for development prototyping. It loads a 3.5MB+ runtime script that parses every DOM node and injects stylesheet rules on every page load, causing render latency, layout shifts (CLS), and full reliance on an external third-party CDN.
- **Severity:** High

#### Finding 5.2: Hardcoded Category Filter Pills in Public Templates
- **Location:** `projects.php#L97-L108`, `articles.php#L131-L140`
- **Evidence:**
  In `projects.php`:
  ```html
  <button class="filter-btn ... " data-category="distributed">Distributed Systems</button>
  <button class="filter-btn ... " data-category="database">Database & Storage</button>
  <button class="filter-btn ... " data-category="lowlevel">Low-Level & Runtimes</button>
  <button class="filter-btn ... " data-category="3d">3D Computing</button>
  ```
- **Analysis:** The database provides a dynamic `categories` table, but the public filter buttons are static HTML. If an admin creates a new category (e.g. "Networking"), it cannot be filtered on the frontend.
- **Severity:** Medium

---

### Discipline 6: UX / Design Coherence

#### Finding 6.1: Duplicate Titles and Markup Bloat in Admin Settings
- **Location:** `admin/settings.php#L62-L69`, `admin/settings.php#L112-L113`
- **Evidence:**
  ```html
  <!-- Line 65 & 67 -->
  <h1 class="page-title">Studio Settings &amp; Runtime Environment</h1>
  <p class="page-description">Authentic server configuration, runtime parameters...</p>
  <h1 class="page-title">Admin Control Center</h1>
  <p class="page-description">Unified editorial control, platform identity...</p>

  <!-- Line 112 & 113 -->
  <div style="display: flex; flex-direction: column; gap: 28px; max-width: 1040px;">
  <div style="display: flex; flex-direction: column; gap: 28px; max-width: 1080px;">
  ```
- **Analysis:** Two `<h1>` elements and duplicate description paragraphs render consecutively due to an uncleaned merge, degrading UX and creating duplicate headings in the document outline.
- **Severity:** High

#### Finding 6.2: Incomplete Sidebar Consolidation
- **Location:** `admin/partials/layout_top.php#L147-L151`, `admin/partials/layout_top.php#L178-L182`
- **Evidence:**
  ```html
  <li class="nav-item">
      <a href="showcase.php" ...><span>Home Showcase</span></a>
  </li>
  <li class="nav-item">
      <a href="social.php" ...><span>Social Links</span></a>
  </li>
  ```
- **Analysis:** The approved Phase A plan was to eliminate separate sidebar links for Showcase and Social Links in favor of the new Control Center tabs. They still remain in the sidebar, leading to fragmented navigation.
- **Severity:** Medium

---

### Discipline 7: Accessibility (WCAG 2.1)

#### Finding 7.1: Insufficient Contrast Ratio on Muted Text (Light Mode)
- **Location:** `css/styles.css#L34`, `css/styles.css#L15`
- **Evidence:**
  - Background: `--color-background: #f8fafc`
  - Text Muted: `--color-text-muted: #94a3b8`
  - **Calculated Contrast Ratio:** **2.5:1** (WCAG AA requires 4.5:1 for normal text).
- **Analysis:** In light mode, metadata badges, dates, and placeholder labels using `text-muted` are unreadable for users with moderate visual impairments. (Dark mode passes with 5.6:1).
- **Severity:** High

#### Finding 7.2: Touch Target Sizing on Header Controls
- **Location:** `includes/header.php#L53`, `includes/header.php#L63`
- **Evidence:**
  - Search trigger: `h-10 min-h-[40px] px-2.5 sm:px-3`
- **Analysis:** While `min-h-[40px]` is close, WCAG 2.5.5 recommends touch target dimensions of at least 44x44px for interactive touchscreen buttons.
- **Severity:** Low

#### Finding 7.3: Keyboard Focus Management in Search Modal
- **Location:** `includes/search-modal.php#L188-L230`
- **Evidence:**
  - Implements `role="dialog"` and `aria-modal="true"`.
  - Body scroll locking (`document.body.style.overflow = 'hidden'`).
  - Restores focus to `lastFocusedElement` on dismissal.
  - Esc key dismissal and ArrowUp/ArrowDown selection.
- **Severity:** Verified Compliant (Good)

---

### Discipline 8: QA / Verification Status

#### Finding 8.1: Unverified Claims in Previous Phase Walkthroughs
- **Evidence:**
  1. **Phase A Walkthrough:** Claimed `api/config.local.php` credential rotation was complete. Verification proved `DB_PASS` was defined twice and the leaked password remained in active use.
  2. **Phase B Migration:** Claimed production readiness, but code was only tested in scratch sandbox directories without live database socket verification.
  3. **Site Search Walkthrough:** Claimed complete verification, but only tested against in-memory JSON data, never verifying `posts` table querying or draft exclusion against a real database.
- **Analysis:** There has been a systemic gap between "code written" and "empirically tested in a production-identical runtime."
- **Severity:** High

---

### Discipline 9: DevOps & Deployment

#### Finding 9.1: Operating System Artifacts Committed to Git
- **Location:** `.DS_Store`
- **Evidence:**
  ```bash
  $ git log -n 1 --stat 72a671a
  .DS_Store | Bin 8196 -> 8196 bytes
  ```
- **Analysis:** `.DS_Store` is explicitly listed in `.gitignore` (line 8), but was previously tracked and committed in commit `72a671a`. It must be untracked using `git rm --cached .DS_Store`.
- **Severity:** Low

#### Finding 9.2: Absence of Documented Deployment Workflow or Automation
- **Location:** Repository root
- **Evidence:**
  - No `.github/workflows/` directory.
  - No `deploy.sh` or automated synchronization script.
  - Local `main` branch is ahead of `origin/main` by 1 commit (`72a671a`), unpushed.
- **Analysis:** There is no single source of truth explaining how code moves from GitHub to Hostinger shared hosting.
- **Severity:** High

---

### Discipline 10: SEO & Content

#### Finding 10.1: Broken Sitemap and 404 Robots.txt Directive
- **Location:** `robots.txt#L6`, `sitemap.php#L58-L98`
- **Evidence:**
  ```bash
  $ cat robots.txt
  Sitemap: https://mohammedalrashadi.com/sitemap.xml

  $ ls -la sitemap.xml
  ls: sitemap.xml: No such file or directory
  ```
  In `sitemap.php`:
  ```php
  // Lines 58, 63, 92
  $urls[] = ['loc' => SITE_URL . '/articles.html', ...];
  $urls[] = ['loc' => SITE_URL . '/achievements.html', ...];
  $urls[] = ['loc' => SITE_URL . '/post.html?id=' . $id, ...];
  ```
- **Analysis:** `robots.txt` points to `sitemap.xml`, which does not exist. Direct execution of `sitemap.php` generates URLs pointing to obsolete `.html` files in `archive/legacy_static/`, resulting in indexation of broken 404 links.
- **Severity:** Critical

#### Finding 10.2: Invalid Relative Open Graph Image
- **Location:** `includes/head.php#L24`
- **Evidence:**
  ```html
  <meta property="og:image" content="/assets/monogram.png"/>
  ```
- **Analysis:** Open Graph protocol specifications require fully qualified URLs with protocol and domain (e.g. `https://mohammedalrashadi.com/assets/monogram.png`). Relative URLs fail preview generation on social crawlers.
- **Severity:** Medium

---

### Discipline 11: Technical Documentation

#### Finding 11.1: Documented Factual Drift in Architecture Documentation
- **Location:** `docs/DATA_SOURCE_MATRIX.md#L31`
- **Evidence:**
  ```markdown
  | **Home (`index.php`)** | Langomind Flagship Spotlight | **Class A** | `posts` (`type='achievement'`) | None (Direct PDO query) | Yes (Canonical array) | Yes (`admin/achievements.php`) | Real |
  ```
- **Analysis:** The documentation reports `Langomind` as "Real Class A Data", directly contradicting project directives and masking placeholder artifacts as production deliverables.
- **Severity:** Medium

---

## 4. Prioritized Issue List

| Priority | Issue ID | Discipline | Description | Location |
| :---: | :---: | :---: | :--- | :--- |
| **CRITICAL** | **SEC-01** | Security | Database passwords leaked in plain text in git commit history. | Git History (`commits: 35a2954, 89858e4, 19c6504, 92da311, afca7e4, 48fb5dd`) |
| **CRITICAL** | **SEO-01** | SEO | Missing `sitemap.xml` and `sitemap.php` emitting 404 links to legacy `.html` files. | `robots.txt#L6`, `sitemap.php#L58-L98`, `.htaccess` |
| **HIGH** | **SEC-02** | Security | Unauthenticated first-admin creation script present in root. | `_setup_first_admin.php#L1-L55` |
| **HIGH** | **CNT-01** | Content | Hardcoded fallback arrays with fictional `Langomind` project and fabricated metrics. | `projects.php#L28`, `articles.php#L28`, `gallery.php#L23`, `journey.php#L154` |
| **HIGH** | **DAT-01** | Database | `gallery.php` completely disconnected from `achievement_images` database table. | `gallery.php#L8-L57`, `database/schema.sql#L116` |
| **HIGH** | **FND-01** | Frontend | In-browser Tailwind Play CDN loaded in production instead of precompiled CSS. | `includes/head.php#L51` |
| **HIGH** | **UX-01** | UX / Design | Duplicate `<h1>` titles and uncleaned merge markup in Admin Control Center. | `admin/settings.php#L65-L67`, `admin/settings.php#L112-L113` |
| **HIGH** | **A11Y-01** | Accessibility | Light mode `text-muted` fails WCAG AA minimum contrast ratio (2.5:1). | `css/styles.css#L34` (`#94a3b8` on `#f8fafc`) |
| **HIGH** | **OPS-01** | DevOps | Undocumented deployment procedure with unpushed commits on local `main`. | Git Repository (`origin/main` vs `HEAD`) |
| **MEDIUM** | **SEC-03** | Security | `uploads/` directory lacks Apache directive preventing alternative PHP execution. | `.htaccess#L34`, `uploads/` |
| **MEDIUM** | **DAT-02** | Database | Public reviews endpoint returns approved reviews for soft-deleted posts. | `api/reviews/list.php#L120-L138` |
| **MEDIUM** | **FND-02** | Frontend | Hardcoded category pills in public articles and projects pages. | `projects.php#L97-L108`, `articles.php#L131-L140` |
| **MEDIUM** | **UX-02** | UX / Design | Unconsolidated Showcase and Social links lingering in Admin sidebar. | `admin/partials/layout_top.php#L147`, `L178` |
| **MEDIUM** | **SEO-02** | SEO | Relative Open Graph image path breaks rich link unfurling. | `includes/head.php#L24` |
| **MEDIUM** | **DOC-01** | Documentation | Architectural documentation claims fabricated projects are "Real Class A Data". | `docs/DATA_SOURCE_MATRIX.md#L31` |
| **LOW** | **OPS-02** | DevOps | Tracked `.DS_Store` binary artifact committed in git tree. | `.DS_Store` |
| **LOW** | **A11Y-02** | Accessibility | Header control buttons measure 40px height instead of recommended 44px. | `includes/header.php#L53`, `L63` |

---

## 5. What's Actually Verified vs. Claimed

| Feature / Subsystem | Claimed Status (Prior Reports) | Actually Re-Verified This Audit | Real Evidence & Findings |
| :--- | :---: | :---: | :--- |
| **Credential Untracking** | "Fixed & Protected" | **Partially Verified** | `api/config.local.php` is untracked in working tree (`git ls-files` is empty), **BUT** plaintext secrets exist across 7 git commits on `main`. |
| **Database `getDB()` Singleton** | "Fixed to re-throw" | **Verified Working** | `api/db.php#L58` re-throws `PDOException`; verified callers handle exceptions gracefully without white-screening. |
| **Site Settings (IMP-034)** | "Ready for Production" | **Partially Verified** | Table structure & seeding work idempotently. Write sanitization verified via live HTTP. **However**, `admin/settings.php` UI contains duplicate headings and broken markup. |
| **Admin Control Center Navigation** | "Consolidated into Tabs" | **NOT Implemented** | `admin/partials/layout_top.php#L147` still renders `showcase.php` and `social.php` as separate sidebar entries. |
| **Site Search v1 API** | "Verified with 7 Tests" | **Verified Working** | Tested via live PHP built-in server + curl against real MariaDB records. Parameterized queries, LIKE wildcard escaping, and draft exclusions verified. |
| **Site Search Modal UI** | "Verified Keyboard & Text" | **Verified Working** | Tested in headless Google Chrome via CDP: `⌘K` opens modal, renders search results safely via text DOM nodes without `innerHTML` injection, and Esc dismisses. |
| **XML Sitemap** | "Ready & Dynamic" | **Broken** | `sitemap.xml` does not exist; `sitemap.php` generates URLs pointing to obsolete `.html` files in `archive/legacy_static/`. |
| **Visual Gallery Dynamic Display** | "Linked to Relational DB" | **NOT Implemented** | `gallery.php` contains zero database queries and renders a static, hardcoded array ignoring `achievement_images`. |

---

## 6. Open Questions & Undetermined Items

1. **Production Hostinger MariaDB State:**  
   Because we do not have direct network access or active credentials to the remote Hostinger MySQL server from this workspace, we cannot determine whether the `site_settings` migration has been applied to the live production database, or whether the production database password was rotated in Hostinger hPanel to diverge from the leaked git history.
2. **Web Server Engine & Module Support:**  
   The `.htaccess` configuration assumes an Apache environment with `mod_rewrite`, `mod_authz_core`, and `mod_expires`. If Hostinger is running Nginx as a reverse proxy or LiteSpeed with different directive inheritance, `.htaccess` deny rules (such as blocking `.json` files in `api/data/`) may behave differently in production.
3. **Canonical Deployment Mechanism:**  
   It is unverified whether Hostinger is configured to pull automatically from GitHub on commit, whether Git deployment is triggered manually in hPanel, or whether files are uploaded via FTP.

