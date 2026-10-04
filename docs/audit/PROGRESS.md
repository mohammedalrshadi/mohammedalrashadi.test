# Audit Fix Progress

## Session 1 — verification + first fixes (2026-10-01)
Environment: PHP 8.3.6 CLI, MariaDB 10.11 (container), schema.sql loaded, site run on php -S with a throwaway test config (not shipped).
Baseline: `php -l` on 245 PHP files = 0 errors; `node --check` on 35 JS files = 0 errors. No tests/ directory in the upload (docs/TESTING.md refers to one).

### Audit claims (docs/FULL_PROJECT_AUDIT.md, dated 2026-09-16) — verdicts
- 1.1 _setup_first_admin.php in root: REFUTED (file does not exist).
- 1.2/CNT-01 Langomind fallback arrays, fake views: REFUTED (no match in any *.php).
- 1.3 getDB() rethrows: VERIFIED (src/Infrastructure/Database.php:29-34).
- 2.1 secrets in git history: UNVERIFIED (history not audited; owner must rotate DB password regardless).
- 2.2/SEC-03 uploads exec guard: REFUTED (uploads/.htaccess exists; .htaccess:103 covers php[0-9]?|phtml|phar|inc|pl|py|cgi).
- 2.3 CSRF/auth coverage: VERIFIED (72 writing endpoints; only public ones lack requireAuth, all have CSRF/rate-limit).
- 3.1/DAT-01 gallery.php ignores DB: REFUTED (gallery.php:12-66 queries achievement_images).
- 3.2/DAT-02 reviews of soft-deleted posts: REFUTED (api/reviews/list.php:124 INNER JOIN ... deleted_at IS NULL; tested live).
- 5.1/FND-01 Tailwind CDN: REFUTED (includes/head.php:114 uses /css/tailwind.css).
- 5.2/FND-02 hardcoded category pills: REFUTED (projects.php:61-65 dynamic).
- 6.1/UX-01 duplicate h1: REFUTED (admin/settings.php has a single h1, line 72).
- 6.2/UX-02 sidebar links: not a defect (admin/social.php redirects to settings tab; showcase kept as "Commerce & Assets").
- 7.1/A11Y-01 contrast: REFUTED (--color-text-muted is #52525B on #F9F9F7 in styles.css:88,105).
- 9.1/OPS-02 .DS_Store tracked: REFUTED (git ls-files empty).
- 10.1/SEO-01 sitemap: REFUTED (.htaccess:57 rewrite, no .html URLs in sitemap.php).
- 10.2 og:image relative: not reproduced (head.php:63 uses $ogImage variable) — UNVERIFIED value.
- Labs JSON vs DB table: by design (docs/LABS_STORAGE_DECISION.md), not a bug.

### New findings (found by live testing) and fixes
- NEW-1 VERIFIED, FIXED: src/Infrastructure/Database.php let any HTTP request switch the database via X-Test-DB-Name when DB_HOST contained localhost (production uses localhost). Proven: header -> other DB -> 500. Removed the HTTP branch; CLI TEST_DB_NAME kept.
- NEW-2 VERIFIED, FIXED: includes/image_helper.php:153,156 undefined $width/$height; documented opts width/height were never read. Now initialised from $opts (no caller passes them, so no visual change).
- NEW-3 VERIFIED, FIXED: product.php:211 read $product['title'] when product missing (/store/nope). Now `?? ''`.
- NEW-4 VERIFIED, FIXED: PostRepository logged an error and ran an extra query on every article view (posts.published_at does not exist). Detection cached per request, no log.

### Test evidence
- php -l on the 4 changed files: clean. Re-crawl of 20+ public pages, all admin pages, every API endpoint (GET+POST, admin session) and post/category/review/support lifecycles: no PHP warnings, no 5xx (only env-related mailer log).

### Pending — needs explicit GO (gated areas), nothing touched
- (CORRECTED in session 1e) The earlier claim here that api/composer.json and /LOCAL_DEV_SETUP.md were not denied was WRONG: .htaccess FilesMatch already denies *.json and *.md. Only api/composer.lock (and root tailwind.config.js) were actually reachable. See session 1e.

## Session 1b — production structure comparison (owner allowed reading the dump for schema comparison only)
Method: only CREATE/ALTER TABLE statements were extracted from the dump into a scratch DB; no row data was read or shipped. Production = MariaDB 11.8.9, dump generated 2026-10-01.
- Tables: 38 in production vs 38 in database/schema.sql (identical sets).
- Column diff: only `achievements.deleted_at` exists in schema.sql but NOT in production (migration `achievements_soft_delete` not applied there yet).
- `posts.published_at` does not exist in production (confirms NEW-4 was a real per-request cost).
- Index name diffs on project_images (idx_ai_post_id vs idx_project_images_post_id) and achievements.idx_achievements_deleted_at — cosmetic, tied to pending migrations v6_project_images_rename / achievements_soft_delete.
- NEW-5 VERIFIED, FIXED: api/achievements/gallery_list.php used `deleted_at` unconditionally -> HTTP 500 on the production structure ("Unknown column"). Now uses achievementsHasDeletedAt() like its sibling endpoints. Tested on both structures (prod: 200, schema.sql: 200, missing id: 404).
- Other achievements endpoints behave correctly on production structure (delete/restore/purge answer 409 "migration not applied"; list/get/trash 200; public pages use fallback).
- NEW-6 VERIFIED, FIXED (owner GO given, upload area): api/achievements/gallery_add.php had the same unguarded `deleted_at` existence check (HTTP 500 on production structure). Now uses achievementsHasDeletedAt(). Only the existence-check query and one require_once changed; processUploadedImage() and all upload logic untouched. Tested without uploading any file: production structure existing ids -> reaches upload step (400 no file), missing id -> 404; schema.sql structure same. No PHP log output.
- Test-harness note: php -S uses OPcache with revalidate_freq=2, so swapping config.local.php needs a >2s wait; two apparent anomalies during testing were caused by this, not by the code.
- Owner action: apply migrations (admin/run_migrations.php) BEFORE relying on achievement trash/gallery features. Deploy order: migrations first, code second.

## Session 1d — migration inspection + runner cleanup (owner GO given for migrations)
Inspection of `achievements_soft_delete` (defined inline in admin/run_migrations.php, order 37/38):
- Schema change: ADD COLUMN `achievements.deleted_at` DATETIME NULL DEFAULT NULL AFTER `updated_at` + ADD KEY `idx_achievements_deleted_at`. Nothing else; no DROP/DELETE/TRUNCATE/type change/data change.
- Dependencies: needs table `achievements` (migration 35) only. Independent of rename (36) and reconcile (38).
- Idempotent: each statement guarded by columnExists()/indexExists(); check() needs column AND index. Production state (from verified dump): neither exists -> not partially applied.
- The runner executes ALL pending migrations in one POST (no per-migration selection).
- NEW-7 VERIFIED, FIXED: `v6_project_images_rename` check() required `project_images` to exist AND `achievement_images` to be absent. Production (and a fresh schema.sql) have both tables, so it was pending forever and its apply() (`RENAME TABLE ... ` onto an existing table) fails with error 1050 on every run (proved with a generic PDO test; nothing is changed by the failure). check() is now `tableExists('project_images')`. Only cases where apply() could never succeed are affected. Code already prefers project_images (gallery.php:20, GalleryRepository.php:19).
- Result: on the production-structure scratch copy exactly ONE migration is pending (achievements_soft_delete); on fresh schema.sql zero.
Test evidence (isolated clone of the production structure; real runner POST path via CLI):
- before/after snapshot diff = new column + new index only (+ audit-log and ledger rows). All other tables identical (columns, indexes, constraints, row counts).
- achievements data md5 identical to the untouched original; all deleted_at = NULL (nothing trashed).
- 2nd run: 38 already_applied, 0 backfilled, schema unchanged. Partial state (column present, index missing) is completed on re-run.
- App after migration: soft delete -> 404 on public page, gone from list/sitemap, present in trash; restore works; purge refuses non-trashed item and permanently deletes a trashed one; neighbour untouched; empty PHP error log.
Not tested: real production ledger contents, v6_project_images_reconcile with real data (check is data dependent), lock time on MariaDB 11.8 (expected sub-second on ~10 rows).
Owner runbook: backup -> deploy run_migrations.php -> GET preview (expect 1 pending) -> POST via button -> verify -> deploy remaining code.

## Session 1e — .htaccess hardening, verified on a real Apache (owner GO given)
Method: Apache 2.4.58 + mod_php 8.3 + the uploaded .htaccess, 65-URL before/after sweep (pages, rewrites, redirects, assets, API, sensitive paths).
- Baseline (original .htaccess): composer.json 403, LOCAL_DEV_SETUP.md 403, design-system/MASTER.md 403, .vscode 403, config.local*.php 403, api/data/*.json 403, src/includes/database/docs 403, api/vendor 403, .git 403 — all already protected.
- NEW-8 VERIFIED, FIXED (low severity): `api/composer.lock` was served (HTTP 200, exposes exact dependency versions) and root `tailwind.config.js` (build config) too. Added `lock` to the existing FilesMatch extension list and `tailwind.config.js` to its filename list. One line changed in .htaccess; nothing else.
- After: those two -> 403; the other 63 URLs byte-for-byte same status/redirect as before; `js/tailwind-config.js` (used at runtime) still 200; no PHP log output.
- Real-Apache confirmation of SEC-03: uploads/ with php, phtml, php5, PHP (upper case), phar, inc -> 403, nothing executed. Existing rewrites/redirects (trailing slash, /dashboard, pretty URLs) work as designed.
- Read-only note: upload filenames are random hex (upload_helper.php:109,155) and MIME is checked with finfo + getimagesize, so a client-supplied double extension never reaches disk.
- Not tested: LiteSpeed (Hostinger) itself; real image uploads (next session). On LiteSpeed `php_flag` in uploads/.htaccess is ignored, the FilesMatch/RewriteRule deny remains the effective guard.
Owner action: upload .htaccess (backup the old one first), then open /api/composer.lock and /tailwind.config.js (expect 403) and the home page + one article (expect 200).

## Session 2 — uploads, labs, auth flows (real Apache 2.4 + mod_php 8.3, MariaDB 10.11, production-structure DB)
Applied (not gated):
- NEW-9 VERIFIED, FIXED: stored XSS through JSON-LD. 5 public pages used JSON_UNESCAPED_SLASHES, so a title containing `</script><script>..` closed the JSON-LD block and injected an executable script (reproduced on /articles/<slug> with an admin-authored title; project/lab pages safe by luck). Added JSON_HEX_TAG|JSON_HEX_AMP to all 15 inline json_encode outputs: about.php, index.php, post.php, product.php, project.php, lab-detail.php, gallery.php, dashboard/partials/layout_top.php. After: 0 injected scripts, every JSON-LD block still parses and round-trips the original title.
Verified, NOT applied (gated: uploads/auth) — ready as patches in docs/audit/pending-patches/ (apply cleanly to the originals, byte-identical to the tested versions):
- NEW-10 api_helpers_image_optimizer.patch: GD cannot encode palette images (GIF, PNG-8) to WebP; imagewebp() returns true but writes 0 bytes, the optimizer kept the empty file as "smaller than source" and reported success. Reproduced on 4 endpoints (image.php, gallery_add, project_images/add, products/image_upload); DB rows get media_assets.file_size = 0. Fix: imagepalettetotruecolor() after decoding + reject empty encodes. Transparency, animated GIF (kept as-is), JPEG/PNG-24/WebP unchanged.
- NEW-11 api_products_upload_demo.patch: (a) a ZIP whose declared size is forged is trusted: 300 MB extracted despite the 100 MB cap, and `shell.php` declared size 0 bypasses the dangerous-extension check and is extracted; (b) .htaccess / .user.ini / pht / phps / php6 not in deny-list; a planted .htaccess re-grants access and, where php_flag engine off is ignored (LiteSpeed), executed PHP (proved on Apache with the flag removed; not tested on LiteSpeed); (c) `>` instead of `>=` on the file-count limit; (d) no ZipArchive guard. Fix: real byte counting with early abort, deny-list on every entry, wider list, guard.
- NEW-12 api_auth_forgot.patch: password-reset mail flooding. Only failures were counted, so 8 rapid requests for a real account sent 8 mails and each one invalidated the previous link. Fix: 2-minute cooldown per user (uses password_resets.created_at, exists in production, no schema change) + every real dispatch counts toward the per-IP limit. After: 1 mail, new link allowed after the cooldown, unknown-email response unchanged (no enumeration).
- NEW-13 api_auth_register.patch: ini_set()/session_set_cookie_params() called while a session is active -> 3 PHP warnings per registration (would corrupt the JSON response if display_errors is on). Fix: only when session_status() === PHP_SESSION_NONE. Login-after-register and cookie flags unchanged.
Checked and fine (REFUTED as issues): upload endpoints reject php/phtml/svg/text/empty/21 MB/truncated-less content without leaving orphan files; polyglot images are re-encoded (payload stripped); 900-megapixel PNG bomb rejected by the pixel guard; resource_upload (whitelist + finfo, svg only downloadable as attachment, directories denied); Labs JSON store: 20 parallel saves = 20 intact records, no tmp leftovers, same-id race = upsert; auth: tokens hashed (SHA-256), single-use, expired/forged tokens rejected, enumeration-safe text, session id rotates on login, old sessions die after password reset; unverified accounts may log in but features are gated by email_verified_at read from DB per request (design, not a bug).
Low / informational (no change made): labs status accepts any string (escaped on output); labs/products json_encode in admin pages are server constants; DirectorySlash redirect /dashboard -> /dashboard/ is standard Apache behaviour.
Not tested: LiteSpeed itself, real SMTP delivery, images through a real camera EXIF pipeline.
Owner SQL (read-only) to find already-broken production images: SELECT id, file_name, file_path FROM media_assets WHERE file_size = 0;  and on the server: find uploads -type f -size 0 -name '*.webp'

## Full project package (owner request after session 2)
- Contains all fixes applied so far (15 modified files vs the original upload, verified with diff -rq): .htaccess, about.php, admin/run_migrations.php, api/achievements/gallery_add.php, api/achievements/gallery_list.php, dashboard/partials/layout_top.php, gallery.php, includes/image_helper.php, index.php, lab-detail.php, post.php, product.php, project.php, src/Domain/Content/PostRepository.php, src/Infrastructure/Database.php.
- NOT applied (waiting for owner GO): the 4 patches in docs/audit/pending-patches/ (image_optimizer, upload_demo, forgot, register).
- Deliberately excluded from the package: .git history, *.sql files (rule: no dumps; they are unchanged, keep the ones already on the server), api/data/*.json runtime state (contains IPs), .DS_Store, config.local.php/.env (never shipped).
- Deploy by copying OVER the existing site; do not delete api/config.local.php, uploads/, api/data/ or database/ on the server.
