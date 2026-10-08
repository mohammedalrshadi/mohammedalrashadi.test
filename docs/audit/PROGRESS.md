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

## Session 3 — design-only pass (2026-10-06)
Scope: only design claims of docs/DESIGN_INTEGRATION_AUDIT.md, DESIGN_MIGRATION_FINAL.md, PAGE_CONSISTENCY_MATRIX.md (dated 2026-09-15, written before the World Tree work). No redesign, no URL changes. Note: this zip has no root .htaccess, so DirectoryIndex / stitch/ exposure could not be checked.
Verdicts (code evidence):
- index.html/articles.html/achievements.html/post.html, css/stitch.css, css/post.css, hello.md, api/hi.md: REFUTED (files do not exist).
- Tailwind CDN: REFUTED (includes/head.php:113 loads /css/tailwind.css). `w<?php` in journey.php: REFUTED (line 1 is `<?php`).
- Fake telemetry strings (SYS-REF, CORE_API, UHD SCHEMA, ...): REFUTED (0 matches in *.php outside stitch/).
- Admin still gold/Cairo: REFUTED (admin.css tokens are slate; Cairo only in a font fallback stack, admin.css:43). Font Awesome CDN still loaded: VERIFIED (admin/partials/layout_top.php:95, admin/login.php:53) — not changed (no visual defect).
- "stitch/ deleted": REFUTED (stitch/ exists, 11 tracked files, 0 runtime references). Left untouched; owner decision.
- DESIGN_MIGRATION_FINAL says single dark/light theme: outdated, code has light/dark/green (head.php:100, theme-toggle.js).
- docs/WORLD_TREE.md refers to js/world-tree.js, which is deleted in the working tree and not loaded by any page: doc is stale (not edited).
New findings:
- DSG-1 VERIFIED, FIXED: css/tailwind.css (built 2026-09-27) was stale. 63 utility classes used in PHP/JS were missing, incl. md:grid-cols-12, md:col-span-4/8, lg:col-span-6/12, md:flex, md:hidden, md:block, mt-8, items-end, xl:max-w-4xl (home_workspace.php, index.php, achievements.php, product.php, footer.php). Rebuilt with tailwindcss 3.4.17 and the project's tailwind.config.js, minified. Diff vs old file: every shared rule identical except grouping of selectors and `.ring-2` (old file lacked its box-shadow); 13 classes dropped, each grep-verified unused repo-wide. 0 missing classes after rebuild. Owner: if you rebuild Tailwind locally, keep using the same command so the file stays in sync.
- DSG-2 VERIFIED, FIXED: home styles in css/new-design.css are scoped to `body.page-home`, but the class was added by JS on DOMContentLoaded (head.php:118-125) -> unstyled/default-token flash. index.php:184 now has `page-home` in the server-rendered body class (JS left as is, idempotent).
Tests: php -l index.php clean; no tests/ directory in upload. Class coverage re-check (postcss) = 0 missing.
Not tested: real browser render/visual diff (no browser in container); LiteSpeed.

## Session 3b — cleanup (owner gave full access)
- DSG-3 FIXED: deleted includes/home_workspace_old_clock_backup.php (0 references anywhere).
- DSG-4 FIXED: deleted stitch/ (12 files; 0 runtime references; recoverable from git history). Supersedes "left untouched" above.
- DSG-5 FIXED: docs/WORLD_TREE.md rewritten to match the code (canvas engine and world-tree.js no longer exist). Added an "Outdated" banner to DESIGN_INTEGRATION_AUDIT.md, DESIGN_MIGRATION_FINAL.md, PAGE_CONSISTENCY_MATRIX.md.
- Note: css/world-tree.css section 0 already contained a gap-fill for the md:/lg: utilities missing from the old tailwind.css (same root cause as DSG-1). Now redundant after the rebuild; left in place (harmless, avoids risk). Can be removed later after a visual check.
- Kept on purpose: Font Awesome CDN in admin (icons depend on it).
Tests: php -l on index.php and includes/*.php clean.
Server: delete these on the server too: stitch/ and includes/home_workspace_old_clock_backup.php (see docs/audit/DELETED_FILES.txt).

## Session 3c — more design verification
- REFUTED: "no reduced-motion support" for Home (global rule in css/styles.css:1152 covers all transitions/animations).
- REFUTED: duplicate <h1> in product.php/post.php (second h1 is in the "not found" branch only: product.php:228, post.php:183).
- REFUTED: light theme selector on Home never matches (css/new-design.css:33 uses html[data-theme="light"] body.page-home).
- DSG-6 VERIFIED, FIXED: green theme was ignored on Home. body.page-home (css/new-design.css:19) redefines colour tokens on <body>, shadowing html[data-theme="green"] from css/styles.css:213. Added `html[data-theme="green"] body.page-home` block with the exact green token values (css/new-design.css, after the light block). Dark and light unchanged.
- Observation (not changed, new feature): no skip-to-content link and <main> has no id on any page.
Tests: new-design.css parsed with postcss OK. No browser available for visual check.

## Session 3d — whole-site design verification
Method: scripted checks (postcss) of CSS vars, class coverage per stylesheet, asset refs, hardcoded colours.
- DSG-7 VERIFIED, FIXED: admin used legacy CSS variables that no stylesheet defined (--accent-red, --bg-hover, --bg-alt, --bg-body, --border-color): delete buttons/error text had no colour, dropzone borders and hover backgrounds were missing (admin/achievements-edit.php:84,106, admin/showcase*.php, admin/js/{labs,journey,about-content,lab-edit,store,showcase,achievements*,projects-edit}.js, admin/partials/layout_top.php). Added an alias block in admin/css/admin.css mapping them to current tokens (--danger, --bg-surface-hover, --bg-surface-elevated, --bg-canvas, --border-medium), so all three admin themes follow. --bg-surface-container (support.js) left alone: it already has an inline fallback.
- DSG-8 VERIFIED, FIXED: `text-primary/80` and `hover:border-primary/50` (includes/home_workspace.php:272,352) existed in no stylesheet (var()-based colours cannot use Tailwind opacity modifiers). Added both rules to css/styles.css next to the other color-mix opacity rules.
- DSG-9 VERIFIED, FIXED: product.php:495 wrapped sanitized description HTML in `prose prose-on-surface` (neither defined) so headings/lists/links/code were unstyled by Tailwind preflight. Now uses existing `.prose-editorial` (same as post.php). Side effect: description text uses on-surface colour/1.125rem like articles.
- REFUTED: privacy.php/terms.php `prose` (headings carry explicit classes); missing /assets/profile_headshot.png (about.php:110 file_exists guard, admin/settings.php:137 onerror fallback); hardcoded white/black in public pages (only image scrims and an admin banner, achievements.php:176, gallery.php:178,255, product.php:272).
- Remaining undefined class names are JS/BEM hooks or email-template classes (no CSS needed).
Tests: php -l product.php, index.php clean; admin.css and styles.css parse OK. No browser available.

## Session 3e — animation + typography
- DSG-10 VERIFIED, FIXED: no no-JS/failed-JS fallback. `html.is-animating` (opacity 0) and `.reveal-section` (opacity 0) are only cleared by assets/js/motion.js, so with JS disabled or blocked the page stayed invisible. Added `<noscript><style>` fallback in includes/head.php (after the font noscript). Normal behaviour unchanged. (bfcache restore: REFUTED, motion.js:71 already handles `pageshow`.)
- DSG-11 VERIFIED, FIXED: page titles had no base font size below their breakpoint. Tailwind preflight sets h1 to `font-size: inherit`, so these h1 rendered at 16px on mobile/tablet: project.php:194, achievement.php:115 (only `lg:text-display`), post.php:229 (only `md:text-[2.5rem]`), lab-detail.php (only `lg:text-[2.25rem]`). Added `text-headline-lg-mobile` as base (+ `md:text-headline-lg` where the next size was lg).
- DSG-12 VERIFIED, FIXED: the design tokens `text-headline-lg-mobile` (28px) and `text-display-mobile` (36px) existed in tailwind.config.js but were never used, so every page title was 36px (gallery.php 56px) on phones. h1 now `text-headline-lg-mobile md:text-headline-lg` (gallery: `text-display-mobile lg:text-display`) in gallery, journey, achievements, achievement, project, product, store, projects, lab, lab-detail, post, support, terms, privacy, articles. Home h1 untouched (sized in css/new-design.css). Desktop sizes unchanged (>=768px).
- css/tailwind.css rebuilt again (tailwindcss 3.4.17, project config); 0 missing classes. Classes only used by the deleted backup file were dropped by the rebuild.
- Observation, not changed: 55 uses of text-[10px]/[11px] micro labels (search-modal, index, store, header); 4 kbd hints at 9px. Design intent; the token label-micro is 12px if you want them standardised.
Tests: php -l clean on all 15 edited pages and includes/head.php. No browser available.

## Session 3f — responsive sizing (clock, search, footer)
- DSG-13 VERIFIED, FIXED: Home clock did not scale. `.chrono-clock` was a fixed 130px (desktop) and an inline 80px (mobile, includes/home_workspace.php:136); its text used fixed tiny sizes (0.35rem = 5.6px, 0.45rem = 7px, inline font-size on the mobile time/tz). Now `--clock-size: clamp(120px, 11vw, 170px)` (mobile `.chrono-clock--sm`: clamp(72px, 22vw, 96px)), text in `em` relative to the dial, label/tz floor 0.55rem (css/world-tree.css). Inline width/height/font-size styles removed from the mobile clock markup.
- DSG-14 VERIFIED, FIXED: clock had no light-theme styling (white rings, pale-gold text, dark translucent card on a light page). Added `html[data-theme="light"]` overrides at the end of css/world-tree.css.
- DSG-15 VERIFIED, FIXED: Home footer reserved `padding-top: 470px` (mobile) / `700px` (>=768px) as a stage for the canvas tree that no longer exists (no element renders there; no world-tree refs in PHP/JS) -> huge empty block above the footer. Now 3.5rem / 4.5rem (css/new-design.css:243,260).
- DSG-16 VERIFIED, FIXED: search input was `text-sm` (14px) -> iOS Safari zooms the page on focus when a field is <16px. Now `text-base sm:text-sm` (includes/search-modal.php:42). Modal heights `max-h-[82vh]`/`[58vh]` do not shrink with the mobile keyboard/URL bar; added `@supports (height:100dvh)` overrides (82dvh/58dvh) at the end of css/styles.css (vh stays as fallback).
- REFUTED: header not responsive (includes/header.php:28-186 has breakpoints at 768/1439/480/340, More menu, 44px targets); fixed multi-column grids at mobile (0 found: no `grid-cols-N` without grid-cols-1 base); fixed-height clipping sections (0); iOS text-size-adjust (styles.css:342,1977).
- css/tailwind.css rebuilt (sm:text-sm / text-base present).
- Observation, not changed: header uses px font sizes (14px/15px) so it ignores browser font-size settings; micro labels 10-11px.
Tests: php -l home_workspace.php and search-modal.php clean; world-tree.css, new-design.css, styles.css parse OK. No browser available.

## Session 3g — real content only (no invented data)
Goal (owner): the site must show only what the owner entered (admin: settings, projects, posts, labs, about content, journey, showcase). Everything below was hardcoded in code and presented as the owner's facts.
- DSG-17 FIXED includes/home_workspace.php: removed fake telemetry (P99.9 LATENCY 0.08ms, TICK OSCILLATOR 1,000 Hz, MULTI-RAFT QUORUM 3/3 SYNC, RING OVERRUN 0 DROPS, STABLE LOCK-FREE EPOCH, label SUB-MS // CHRONO). "Currently studying MVCC and B-Tree Indexes" (3 places: workspace header, NOW panel, index hero) now comes from setting profile.current_focus and is hidden when empty.
- DSG-18 FIXED index.php: hero strip had hardcoded CURRENTLY STUDYING / MAIN FOCUS / STATUS "ACTIVELY LEARNING"; now only the real current focus. Flagship project: removed hardcoded tags (ACID Storage, Lock-Free Concurrency, Zero-Copy IO), fallback category "SYSTEMS ARCHITECTURE", stock diagram + caption "SCHEMATIC // CORE TOPOLOGY & DATA PIPELINE" (image shown only if the project has one), always-appended "...". Lab block: removed fallback texts (EXP-001, Database Engines, "Evaluating storage engine...", "Synthetic multi-threaded key-value benchmark...", "Empirical observation reveals...") and hardcoded ENVIRONMENT "POSIX x86_64 Linux", METRIC "P99 Latency & Throughput", STATUS "Reproducible"; now shows only question/methodology/outcome/environment/status fields that exist in the experiment record. Showcase fallbacks: no invented categories ("Software Engineering", "Systems", "Database Internals"), descriptions or stock images.
- DSG-19 FIXED index.php: principles and journey on Home were hardcoded text (3 principles, STAGE 1-3). Now read from about_content_blocks (block_type principle) and journey_milestones (published), try/catch per query (missing table = hidden); whole section hidden when both are empty.
- DSG-20 FIXED about.php: removed hardcoded default principles (Resilience First, Empirical Rigor, Deep Clarity) and default focus-area tags (MySQL / InnoDB, B-Tree Indexing, ...); sections hidden when the admin has not added any. Rows Current Focus/Location/Motto/Platform Purpose hidden when empty; badge now shows the real role instead of fixed "Software Engineering".
- DSG-21 FIXED includes/settings.php: default values for profile.location ("Riyadh, Saudi Arabia"), profile.current_focus, profile.bio_short, profile.bio_full are now empty. If the admin already saved them in the DB nothing changes. about.php meta description falls back to seo.default_description. NOTE: profile.name, short_name, role, motto, platform_purpose and seo defaults are still in code: confirm they are correct.
- DSG-22 FIXED: removed stock images ('/assets/diagram_distributed_systems.png', 'code_ide_architecture.png'; the files do not exist in the upload anyway) from api/showcase/helper.php and api/user/content_resolver.php; consumers guard empty images (dashboard/history.php, index.php, likes.php, bookmarks.php, project.php). project.php: removed fake caption "System Architecture & Data Flow / Structural Diagram"; image section only if image_url exists.
- DSG-23 FIXED small claims: journey.php "2021 TO PRESENT" removed; gallery.php "UHD Vector & 4K" removed, hover text "Inspect High-Res Geometric Render" -> "View larger"; articles.php "Regularly Updated" removed; lab.php "Deterministic Harnesses" removed; includes/search-modal.php "Suggested Topics" chips (B-Tree, Redis MsgPack, ...) removed.
- Deleted js/clock_of_life.js (old tree engine, 0 references).
- REFUTED/kept: post.php "Reader Reviews" are real moderated reviews; JSON-LD jobTitle uses profile.role; about.php/lab data pages already DB/JSON-driven with honest empty states.
- NOT changed, owner to confirm: index.php hero identity copy (overline, headline "Database Systems, Storage & Concurrency.", thesis paragraph, B-tree decorative diagram labelled INDEX STRUCTURE / B-TREE / ORDER 3), lab.php/projects.php/articles.php/gallery.php intro paragraphs, support.php FAQ statements (60-minute recovery link, code-sample licence sentence), store.php intro.
Tests: php -l clean on all 16 edited PHP files; tailwind rebuilt, 0 missing classes. Not tested in a browser or against a live database.
Server: delete js/clock_of_life.js too (see docs/audit/DELETED_FILES.txt).
