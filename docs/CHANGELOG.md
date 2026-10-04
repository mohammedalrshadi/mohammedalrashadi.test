# Changelog

All notable changes to the Mohammed Alrashadi Engineering Platform are documented here.

## [Context-Aware Homepage & Dashboard Grid] - 2026-10-04

- **Dashboard Panel Architecture**: Introduced a formal 12-column grid dashboard component system (`.dash-panel`, `.dash-row`, `.dash-list`, `.dash-empty`) in `css/styles.css` substituting legacy blocky UI for a tight, scannable "engineering workspace" visual language with 1px borders, subtle hover transitions, and monospace tabular-num micro-typography.
- **State-Aware Server Rendering**: Refactored `index.php` and rewrote `includes/home_workspace.php` to serve three distinct authentication states natively from PHP, avoiding client-side hydration flicker and maintaining optimal perceived performance.
  - **Guest State (State A)**: Retains the engineering thesis hero (with corrected B-Tree visual labeling and tight padding) and introduces the Latest Feed and 'Now' block.
  - **New User State (State B)**: Replaces the hero with a compact workspace header ("Welcome back, [First Name]"). Surfaces onboarding 'Start Here' modules (Project, Article, Lab) alongside the Latest Feed and empty states for Library/Saved.
  - **Returning User State (State C)**: Replaces the hero with the compact header. Provides quick-action resumption via a progress-aware 'Continue Reading' block. Grids re-flow conditionally depending on the presence of an active reading session to intelligently prioritize 'Saved' and 'Recently Viewed' feeds.
- **Architectural Documentation**: Updated `docs/PLATFORM_ARCHITECTURE.md` to formally document the `Context-Aware Homepage Workspace` routing and state behaviors.

## [Audit Follow-up Fixes] - 2026-09-30

- **Repository cleanup (owner-approved rows 1-13)**: removed `archive/`, `report/`, `docs/audit/`, root `index.html`, `scratch_footer.html`, `Phase_C_Completion_Report*.md`, `SEARCH_REPORT.md`, empty `database/db.sqlite` and `database/store.db`, `uploads/products/demos/17/secret.txt`; `api/data/backup_status.json` is no longer tracked (already in .gitignore). `tests/test_footer_social_system.php` no longer writes `scratch_footer.html` into the web root. Some older docs still mention the removed folders.


- **DB-002**: `v6_project_images_reconcile` now copies with `GROUP BY (post_id, image_url)` and `MIN(created_at)`; new test `tests/test_db002_migration.php` runs the real closures on a throwaway DB.
- **ADM-001**: `api/products/upload_demo.php` reads `numFiles` before `ZipArchive::close()`.
- **DC-001**: CR/LF/NUL stripped from mail header values (`mailerSanitizeHeaderValue`); `sendMail()` refuses invalid recipients.
- **DC-002**: empty `catch` blocks now log a context line (class name only, no message).
- **DC-003**: showcase `link_url` restricted to `/path`, `http://`, `https://`.
- **DC-004**: product description sanitised at render time with `ArticleHtmlSanitizer`.
- **Docs**: table count corrected to 38; production migration order documented.
- **Achievements trash (owner-approved 2026-09-30)**: `api/achievements/delete.php` now moves to trash (`deleted_at = NOW()`, files kept). New `trash.php` (list), `restore.php`, `purge.php` (permanent, trash items only). Admin page has a Trash tab with Restore / Delete permanently. Admin write endpoints answer HTTP 409 until migration `achievements_soft_delete` has run. Audit actions: `achievement.trash`, `achievement.restore`, `achievement.purge` (the old `achievement.delete` is no longer written).
- **DC-005/006/008/009**: atomic locked Labs JSON writes; invented Lab content removed; backup key type-safe + `X-Backup-Key` header; avatar URL validation.
- **DC-007**: purging a writing/project post also deletes its likes and bookmarks.
- **Backup**: `lab_experiments.json` is copied next to each DB dump (`labs-*.json`, same retention).
- **Decision (DC-010)**: unsupported methods on mutating endpoints keep answering via the CSRF check first; no change (cosmetic only, still rejected).

## [Database Integrity & Project Hygiene] - 2026-09-28

- **Homepage Showcase Orphan Cleanup & Cascade**: Added transactional cascading deletion of matching `home_showcase_items` upon purging posts (`api/posts/purge.php`) and deleting products (`api/products/delete.php`). Registered idempotent `showcase_orphan_cleanup` migration in `admin/run_migrations.php`.
- **Engineering Labs Storage Decision**: Authored architectural analysis `docs/LABS_STORAGE_DECISION.md` evaluating JSON vs SQL persistence. Updated `docs/SOURCE_OF_TRUTH_MAP.md` and `docs/DATABASE.md` documenting `api/data/lab_experiments.json` as live operational source of truth and SQL table `lab_experiments` as reserved/dormant.
- **Schema Migrations Ledger Backfill**: Added idempotent backfill capability to `admin/run_migrations.php` to register pre-applied migrations into `schema_migrations` with `applied_by = NULL`, audit logging via `logAdminAction()`, and read-only GET preview calculation. Added comprehensive test suite `tests/test_migration_ledger_backfill.php`.
- **Database Schema Canonical Sync**: Synchronized `database/schema.sql` to 35 tables matching live production, adding `product_images` and `product_resources` tables, `media_assets.width`/`height`, `social_links.icon_key`, and foreign key constraint on `reviews.post_id`. Updated table counts from 33 to 35 across architecture and audit documentation and resolved conflict note in `SKILL.md`.
- **Database Migrations & Slugs**: Verified post slug generation uniqueness against soft-deleted rows and added `uq_posts_slug` migration in `admin/run_migrations.php` and `database/schema.sql`.
- **Category Cascades & Taxonomy Integrity**: Verified transactional cascading updates of denormalized category text columns across `posts` and `products` via `tests/test_category_cascades.php`.
- **Security & .htaccess Hardening**: Updated `.htaccess` to block direct access to dotfiles (`.env`, `.DS_Store`, `.agent*`, `.vscode`) and sensitive extensions (`*.sqlite`, `*.db`, `*.md`, `*.sql`, `scratch_footer.html`) while preserving access to `/.well-known/`. Verified via curl with 200/403 status codes.
- **Repository Hygiene**: Added `*.sqlite` and `*.db` to `.gitignore` and `docs/DEPLOYMENT.md`'s exclusion list. Deleted unreferenced temporary script `temp_migrate.php`. Listed 0-byte SQLite databases for deletion approval.

## [Admin Panel Bugs & UI/UX Overhaul] - 2026-09-20

### 1. Settings — Admin Account & Security Management

- **Endpoint Added**: Created `api/admin/account.php` supporting authenticated retrieval of admin account status (`GET`) and secure credential updates (`POST` with actions `update_email` and `update_password`).
- **Re-Authentication Guard**: Password confirmation (`current_password`) is required before modifying either email address or password.
- **Session Continuity & Revocation**:
  - Updating passwords recalculates `password_hash($new_password, PASSWORD_DEFAULT)` and sets `users.password_changed_at = NOW()`.
  - In `api/auth/guard.php`, older sessions with `$_SESSION['login_time'] < password_changed_at` are immediately destroyed.
  - To prevent accidental lockout of the acting admin, `$_SESSION['login_time']` is synchronously bumped to `time() + 2`, preserving the active session while revoking all other active devices.
- **UI Integration**: Added "Account" tab to `admin/settings.php` (`#pane-account`) featuring last login timestamp display, email update form, and password update form with CSRF token verification in `admin/js/settings.js`.

### 2. Article Real-Time Preview

- **Preview Endpoint**: Created `api/posts/preview.php` enabling non-mutating preview of unsaved drafts. Renders incoming title, excerpt, and content through the identical pipeline used by `post.php` (`ArticleHtmlSanitizer::sanitize` and paragraph block formatting) without modifying post status or database records.
- **Admin Workspace Integration**:
  - Added "Preview" button (`#previewBtn`) to article form actions in `admin/articles.php`.
  - Added modal container `#articlePreviewModal` styled with design system tokens.
  - Added preview handlers `handlePreviewArticle()` and `closeArticlePreviewModal()` in `admin/js/articles.js` with backdrop click and ESC key dismissal.

### 3. Safe HTML Rendering & Content Pipeline in Article Body

- **Canonical Renderer**: Implemented `renderArticleContent(string $rawContent): string` in `api/posts/helper.php`, shared identically across `post.php` and `api/posts/preview.php`.
- **Double-Escaping Elimination**: Fully removed the legacy redundant `echo '<p class="text-on-surface-variant">' . nl2br(htmlspecialchars($para)) . '</p>';` line.
- **Intelligent Block Formatting**:
  - Authoritative sanitization via `ArticleHtmlSanitizer::sanitize()` protecting against script and iframe injection.
  - Fenced code block extraction preserving copy-to-clipboard widgets and language tags.
  - Markdown heading parsing (`###` to `<h3>`, `##` to `<h2>`).
  - Native HTML block elements (`<p>`, `<h1>`-`<h6>`, `<ul>`, `<ol>`, `<blockquote>`, `<div>`) are recognized and rendered cleanly without invalid nested wrapping.
  - Plain text chunks are wrapped in `<p class="text-on-surface-variant leading-relaxed">` with `nl2br`.

### 4. Dynamic Category Creation & Concurrency Handling (REQ-019)

- **Auto-Creation Helper**: Implemented `findOrCreateCategory(PDO $pdo, string $inputName, string $type)` in `api/categories/helper.php`.
- **Race Condition Resolution (REQ-019)**:
  - If two concurrent requests attempt to insert the same new category name for a given entity type, the second insert catches `PDOException` with SQLSTATE `23000` (duplicate entry against `unique_type_name` index on `categories(type, name)`).
  - Automatically re-queries `findCategoryMatch()` to resolve the category ID without failing the user request.
- **API & UI Wiring**: Integrated into `api/posts/create.php` and `api/posts/update.php`. Refreshes category list in `admin/js/articles.js` so newly created categories appear instantly in filters and selectors.

### 5. Independent Panel Scrolling & Sticky Header (REQ-020)

- **Independent Right Panel Scroll**: Updated `admin/css/admin.css` on desktop viewports (`@media (min-width: 901px)`):
  - `body { height: 100vh; overflow: hidden; }`
  - `.main-content { height: 100vh; overflow-y: auto; overflow-x: hidden; }`
  - Eliminates whole-page jitter and double scrollbars while maintaining fixed sidebar navigation.
- **Sticky Workspace Header (REQ-020)**: Added `.admin-page-header { position: sticky; top: 0; z-index: 10; background-color: var(--bg-canvas); }` ensuring breadcrumbs, titles, and global actions remain accessible while scrolling long tables.

### 6. Section Overview Rails & Quick Actions

- Standardized top metric rails across all major admin workspaces:
  - `admin/articles.php`: Total Articles, Published, Drafts, Quick Actions (New, Categories, Public link).
  - `admin/achievements.php`: Total Projects, Published, Drafts, Quick Actions (New, Categories, Public link).
  - `admin/labs.php`: Total Experiments, Concluded, Active, Quick Actions (New Experiment, Public link).
  - `admin/store.php`: Catalog Products, Published, Downloads, Quick Actions (New Product, Categories, Public link).
  - `admin/reviews.php`: Total Reviews, Awaiting Approval, Approved, Quick Actions (Pending Filter, Refresh, Public link).
- Driven by authentic entity counts via `api/helpers/stats_helper.php` with zero simulated data.

### 7. WCAG Contrast Compliance Audit

- Conducted mathematical luminance and contrast ratio audit for design system tokens across all dark surfaces:
  - `--text-muted` (`#8E9BB0`) on `--bg-surface` (`#131922`): **6.27:1** (Passes WCAG AA, min 4.5:1).
  - `--text-muted` (`#8E9BB0`) on `--bg-surface-elevated` (`#1C2532`): **5.49:1** (Passes WCAG AA).
  - `--text-muted` (`#8E9BB0`) on `--bg-surface-hover` (`#243042`): **4.74:1** (Passes WCAG AA).
  - `--text-secondary` (`#B7C3D3`) on `--bg-surface` (`#131922`): **9.88:1** (Passes WCAG AA).
  - `--text-secondary` (`#B7C3D3`) on `--bg-surface-elevated` (`#1C2532`): **8.65:1** (Passes WCAG AA).
- All tested text-to-background combinations meet or exceed WCAG AA requirements without requiring palette overrides.

### 8. Regression Fixes: Admin Settings Visual Overlap & Avatar Upload

- **Settings Visual Overlap Resolved**:
  - Identified root cause of `.control-center-tabs` overlapping `.admin-page-header`: In WebKit/Safari, when `.admin-page-header` became `position: sticky; top: 0;` inside `.main-content` (`display: flex; flex-direction: column`), missing `flex-shrink: 0` caused the header to lose computed height in the document flow, positioning `.control-center-tabs` on top of header actions.
  - Added `-webkit-sticky` and `position: sticky; top: 0; z-index: 20; background-color: var(--bg-canvas); flex-shrink: 0; margin-top: -32px; padding-top: 32px;` to `@media (min-width: 901px) .admin-page-header`.
  - Added `flex-shrink: 0; width: 100%;` to `.admin-page-header`.
  - Added `flex-shrink: 0; position: relative; z-index: 1;` to `.control-center-tabs`, and `flex-shrink: 0;` to `.filter-tabs`.
  - Confirmed on `admin/settings.php` that tabs stack strictly below the header on load and scroll cleanly underneath with no text bleed-through or button clipping.
- **Avatar Upload Restored**:
  - Fixed syntax error in `admin/js/settings.js` caused by a duplicate `loadSocialChannels()` call on line 25 without a comma in `Promise.all`, which caused a parse error preventing `DOMContentLoaded` listeners (including `setupForms()` and `setupAvatarUpload()`) from running.
  - Added `if (e.target === fileInput) return;` guard to `#profileAvatarContainer` click handler to prevent event recursion when `fileInput.click()` triggers.
  - Verified end-to-end: clicking `#profileAvatarContainer` opens native file picker, image upload to `/api/settings/upload_avatar.php` updates `site_profile.profile_image_url`, updates `#avatarPreviewImg`, and persists across hard page reloads.

## [Admin Master-Detail Navigation Architecture Migration] - 2026-09-20

### 1. Architectural Standardization Across Admin Workspaces

- Applied the reference master-detail pattern (established in `labs.php`/`lab-edit.php` and `store.php`/`store-edit.php`) to the 4 remaining admin sections:
  1. **Articles**: `admin/articles.php` (directory) + `admin/articles-edit.php` (editor) + `admin/js/articles.js` & `admin/js/articles-edit.js`
  2. **Achievements / Projects**: `admin/achievements.php` (directory) + `admin/achievements-edit.php` (editor) + `admin/js/achievements.js` & `admin/js/achievements-edit.js`
  3. **Journey Timeline**: `admin/journey.php` (directory) + `admin/journey-edit.php` (editor) + `api/journey/get.php` + `admin/js/journey.js` & `admin/js/journey-edit.js`
  4. **Home Showcase**: `admin/showcase.php` (directory) + `admin/showcase-edit.php` (editor) + `api/showcase/get.php` + `admin/js/showcase.js` & `admin/js/showcase-edit.js`

### 2. Zero-Loss State & Feature Transfer

- **Articles Workspace**:
  - Full authoring form moved from inline card in `articles.php` to dedicated `articles-edit.php`.
  - Preserved: title, slug generation, category datalist with modal creation/management, publish date, cover image, showcase strip toggle, bilingual quote highlights (Arabic/English), excerpt, full WYSIWYG editor with formatters, real-time SEO analyzer, and non-mutating preview modal via `/api/posts/preview.php`.
  - `articles.php` streamlined to directory-only table: search debounce, status filter tabs, category filter, pagination, direct publish/hide, trash management, and modal category management. Table row titles and Edit action buttons navigate to `articles-edit.php?id=${post.id}`.
- **Achievements / Projects Workspace**:
  - Project composer moved to dedicated `achievements-edit.php`.
  - Preserved: title, category datalist with modal management, completion date, featured cover image, showcase strip toggle, excerpt, rich-text project description editor, and multi-file gallery staging/upload grid (`achievement_images`).
  - `achievements.php` streamlined to directory-only table: search, status filter tabs, category filter, pagination, direct publish/hide, trash management.
- **Journey Timeline Workspace**:
  - Milestone composer moved to dedicated `journey-edit.php`.
  - Created single-milestone retrieval endpoint: `GET /api/journey/get.php?id={id}` requiring admin auth.
  - Preserved: title, period label, category, status (draft/published), icon name, sort order, description, and breadcrumb context.
  - `journey.php` streamlined to timeline directory: category filter tabs, delete confirmation modal, soft delete & restore.
- **Home Showcase Workspace**:
  - Showcase item composer and type selector moved to dedicated `showcase-edit.php`.
  - Created single-showcase retrieval endpoint: `GET /api/showcase/get.php?id={id}` requiring admin auth.
  - Preserved: Content type pills (Product, Project, Writing, Image), dynamic entity pickers with candidate preloading (`/api/showcase/candidates.php`), custom overrides (title, excerpt, custom image URL, alt text), active in showcase toggle.
  - `showcase.php` streamlined to sequence directory: drag/button order sequence reordering (`/api/showcase/reorder.php`), homepage visibility toggling (`/api/showcase/toggle.php`), item removal (`/api/showcase/delete.php`).

### 3. Dirty-State Protection (`UnsavedChangesGuard`)

- All 4 dedicated editor controllers (`articles-edit.js`, `achievements-edit.js`, `journey-edit.js`, `showcase-edit.js`) integrate `UnsavedChangesGuard`:
  - Captures baseline state after data prefill.
  - Prevents accidental navigation loss on clicking "Back", cancel button, or closing the tab if form fields are modified.
  - Safely bypasses protection upon successful save.

### 4. Verification & Linting

- All 10 PHP files pass strict syntax linting (`php -l`).
- All 8 JavaScript controllers pass syntax linting (`node --check`).
- Ran `tests/test_admin_dead_handlers.php`: 88/88 inline handlers passed with 0 dead or missing event handlers.
- Updated `tests/test_home_showcase.php` to validate master-detail architecture; 68/68 assertions passing.



