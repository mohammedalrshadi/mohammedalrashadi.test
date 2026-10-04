# Database

**Status as of:** Phase 3 — Database & Data Architecture (continued: production schema export received and applied)
**Supersedes:** the earlier Phase 3 revision of this document, which worked from a truncated `SHOW CREATE TABLE` display and could not fully resolve C-2. This revision can.
**Companion documents:** `docs/ENGINEERING_AUDIT.md` (Phase 1, finding C-2), `docs/SECURITY.md` (Phase 2)

## Verification legend

Same as `docs/SECURITY.md`, plus:

- **OWNER-SUPPLIED** — you ran a query or export against production yourself and provided the output.
- **VERIFIED FROM PRODUCTION EXPORT** — confirmed against a complete, untruncated `phpMyAdmin` structure export you uploaded (2026-09-18). This is the strongest evidence tier in this document: not inferred, not a truncated display, a real `CREATE TABLE` statement copied programmatically and cross-checked column-by-column and index-by-index against the source file before being written anywhere.

## C-2 is resolved

The full production structure export you provided resolved every open item from this document's earlier revision. All 16 production tables are now in `database/schema.sql`, verified column-for-column and index-for-index against the export (see Appendix: how this was verified). Nothing in `schema.sql` is guessed or inferred anymore.

## What the export actually revealed

### 1. `site_settings` — confirmed singleton, key-value schema never existed

Exactly as suspected in the prior revision, but now certain: production's `site_settings` is a singleton wide-row table (`id` defaults to 1, one column per setting — `site_name`, `tagline`, `site_url`, `favicon_url`, `default_seo_title`, etc.). The key-value design (`key_group`/`setting_key`/`value`) that `schema.sql` used to define was **never deployed**. It's been removed from `schema.sql` entirely, not just flagged.

### 2. `site_profile` and `home_showcase_items` — now fully verified

Both tables' real definitions are in `schema.sql`. The column list I'd previously inferred from application code for `site_profile` turned out to be substantially correct — a reassuring sign the original developer's write-path column names were accurate, even though the code itself never trusted them enough to stop catching every write as non-fatal.

### 3. A precise, two-part finding on `posts.type` / `categories.type` — corrected after this document initially got it wrong

**This document's own first pass at this section was wrong**, and is being corrected here rather than quietly edited away. It claimed production's `type` ENUM was `('blog','achievement')` with no `'project'` value. Re-checked directly against the raw export text — `` `type` enum('blog','project','achievement') NOT NULL `` appears verbatim, twice (once for `posts`, once for `categories`) — and that claim was simply wrong. `schema.sql` has been corrected to match.

The two separate, both-true facts, stated precisely this time:

- **Schema fact:** the ENUM permits `'project'` as a storable value, on both tables.
- **Usage fact:** no current PHP code path queries or writes `type='project'`. Every query populating the public "Projects" pages filters on `type='achievement'` instead. `api/search.php`'s `'type' => 'project'` sets a display label on the JSON response object it builds for the frontend — it is never a database value.

So: the schema permits something the application never uses. Both facts matter and neither implies the other, which is exactly how the first version of this section conflated them into one wrong claim.

### 4. Two confirmed, previously-invisible bugs found by comparing code against the real schema

**Bug A — `home_showcase_items.item_type` is `NOT NULL` with no default, but the admin's "Save Showcase" sync INSERT never set it.** This means **every single admin attempt to save showcase selections would have failed** (silently — the failure was caught and only logged as a warning). Fixed: the INSERT now sets `item_type = 'achievement'`. Verified safe to hardcode because `item_type` is never read anywhere in the codebase (grep confirms zero read sites) — the homepage always re-filters `posts WHERE type = 'achievement'` regardless of this column's value, so any valid enum value satisfies the constraint without changing observable behavior.

**Bug B — the main Settings save endpoint (`api/settings/update.php`) only ever persisted the `'profile'` group.** The `'website'`, `'branding'`, `'seo'`, and `'showcase.item_ids'` groups were gated behind `if ($hasKeyGroup)`, which — per finding #1 above — was always `false` in production. Fixed with a correct singleton-row `UPDATE`, mapping the same `group.key` pairs `includes/settings.php` reads back (kept deliberately symmetric with that read-side fix).

**Important nuance on Bug B's real-world impact:** checked `admin/js/settings.js` directly rather than assuming — the current admin UI only ever sends `group: 'profile'` and `group: 'showcase'`. There is **no admin form today** for `website`/`branding`/`seo` settings; those only exist as hardcoded PHP defaults. So this half of Bug B was **latent, not actively breaking anything visible** — it would have silently failed the moment such a form existed, but nothing currently exercises it. The `showcase.item_ids` mapping, by contrast, **is** actively exercised by the real "Save Showcase" button and is now correctly written to `site_settings.showcase_item_ids` for the first time.

### 5. H-1 resolved — dual-schema tolerance removed

`includes/settings.php` no longer runs `SHOW COLUMNS FROM site_settings LIKE 'key_group'` on every single page load. The routine-and-silent catch block Phase 3's first revision deliberately preserved (because logging an always-expected failure would be noise) is gone entirely, because the check it guarded no longer exists. One fewer database round-trip per page render, site-wide.

`index.php` and `api/settings/update.php` no longer run `SHOW TABLES`/`SHOW COLUMNS` against `home_showcase_items` to guess column names — `post_id` and `sort_order` are hardcoded now, confirmed real.

`api/settings/upload_avatar.php` had the same dead `hasKeyGroup` branch (updating a `site_settings` row that could never match); removed.

One more silent catch, missed in the first B-4/O-1 pass, brought in line with the others: `api/settings/update.php`'s `site_profile` write now logs on failure instead of swallowing it silently — same reasoning as the read-side fixes, now that `site_profile` is confirmed to exist.

`database/migration_v5_req015_categories_table.sql` — the original, already-executed migration that created `categories` — is flagged with a comment noting its `type` ENUM (`'blog','achievement'`) predates whatever later added `'project'` to production. Not rewritten; it's a historical record of what actually ran, not something to re-run.

### 6. A known gap found but NOT fixed — profile form fields with no clean column mapping

While fixing Bug B, `admin/js/settings.js`'s profile-save payload was checked directly. It sends `location`, `education_stage`, and `show_email` fields that don't map cleanly to the real schema:

- `location` — real schema has separate `city` and `country` columns, not one combined field.
- `education_stage` — real schema has three separate columns (`education_institution`, `education_program`, `education_status`), not one combined field.
- `show_email` — the real schema's closest column is `show_public_email`, not an exact name match, and it's unclear whether they're meant to be the same toggle.

Not fixed this pass. Splitting a combined UI field into multiple database columns (or vice versa) is a real UI/UX decision — how should the "location" text field behave when the underlying data is two separate columns? — not something to guess silently. Flagged here for a deliberate decision, not left as another silent gap.

## `schema_migrations` — unchanged from the prior revision

Still new-going-forward, still not in the production export (correctly — it doesn't exist yet; it will be created the first time `admin/run_migrations.php` is run after this deploy). Nothing about this changed with the fuller export.

## What Phase 3 still did not touch

Per the explicit instruction not to modify production schema directly from this environment (no database connection exists here regardless):

- No migration was executed against production. Everything in this document is repository-level until you deploy and run `admin/run_migrations.php` yourself.
- `posts.category` as a loose `VARCHAR` instead of an FK to `categories.id`, and the missing `reviews.post_id` FK despite `migration_v5_reviews_fk.sql` existing (confirmed via the export: production genuinely has no such FK) — left alone, same reasoning as before: real data-integrity decisions, not schema-documentation fixes.
- The three profile-field mapping gaps in finding #6 above.

## Store MVP — `products` table (new, table #17)

Added as part of the Store MVP implementation. This table is a **digital product catalog** — it stores metadata for products that are either linked externally (Gumroad, ThemeForest, etc.) or available as free downloads from this website.

**Migration:** `database/migration_store_products.sql` (registered in `admin/run_migrations.php` as `store_products_table`)

**Schema conventions followed:**

- `int(10) UNSIGNED AUTO_INCREMENT` primary key
- `timestamp NOT NULL DEFAULT current_timestamp()` / `ON UPDATE` for timestamps
- `tinyint(1) NOT NULL DEFAULT 0` for booleans (`featured`)
- `ENUM` for controlled values (`product_type`, `status`)
- Index naming: `idx_products_{column}`, unique key: `uq_products_slug`
- `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`
- `category` as `VARCHAR(255)` matching `posts.category`

**Key design decisions:**

- `slug` is `UNIQUE` — products are identified by slug in public URLs (`/store/{slug}`), unlike `posts` which use `id` in URLs
- `product_type` is `ENUM('external','free_download')` — only two types for the MVP; no speculative future values
- `download_path` stores the internal file path for free downloads; this is never exposed publicly as a direct URL — a secure download endpoint validates the product before streaming the file
- `category` is a simple `VARCHAR` string, not a foreign key to `categories` — consistent with the existing `posts.category` pattern and appropriate for the MVP scope
- No `deleted_at` / soft-delete — products use `status = 'archived'` instead, which is simpler and sufficient for a catalog with no dependent data (no orders, no reviews)

**What this table is NOT:**

- Not an e-commerce system — no orders, payments, carts, subscriptions, or internal checkout
- Those features belong to a possible future commerce phase (see `docs/PLATFORM_ARCHITECTURE.md`)

## Home Showcase — `home_showcase_items` Table (Mixed Content Strip)

Evolved as part of the Home Showcase Mixed Content Strip implementation. This table serves as a **curated presentation layer** for the homepage strip directly below the header/hero.

**Live schema definition:**

- `id` (`int(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY`)
- `item_type` ENUM: `('product', 'image', 'project', 'writing') NOT NULL DEFAULT 'project'`
- `reference_id` (`int(10) UNSIGNED DEFAULT NULL`) — generic foreign key referencing `products.id` (for `product`) or `posts.id` (for `project` and `writing`). Indexed by `idx_home_showcase_reference`.
- `post_id` (`int(10) UNSIGNED DEFAULT NULL`) — preserved for backward compatibility with earlier queries and legacy data. Indexed by `idx_home_showcase_post`.
- `title_override` (`varchar(255) DEFAULT NULL`) — optional custom card title overriding source entity title
- `description_override` (`text DEFAULT NULL`) — optional custom card excerpt/description overriding source entity excerpt
- `image_url` (`varchar(2000) DEFAULT NULL`) — standalone image URL or custom thumbnail override
- `alt_text` (`varchar(500) DEFAULT NULL`) — accessible alt text for image items or card thumbnail
- `link_url` (`varchar(2000) DEFAULT NULL`) — optional custom link/destination URL (e.g. for standalone image or external link)
- `is_enabled` (`tinyint(1) NOT NULL DEFAULT 1`)
- `sort_order` (`int(11) NOT NULL DEFAULT 0`)
- `created_at` (`timestamp NULL DEFAULT CURRENT_TIMESTAMP`)
- `updated_at` (`timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`)

**Key design decisions:**

- **Generic Reference (`reference_id`):** Showcase items reference existing entities via `reference_id`: `products.id` when `item_type = 'product'`, and `posts.id` when `item_type = 'project'` or `item_type = 'writing'`. `post_id` is preserved to ensure backward compatibility. Standalone visual items (`item_type = 'image'`) use `reference_id = NULL` and provide `image_url`, `alt_text`, and optional `link_url`.
- **References, never duplicates:** Master records remain in their respective source tables (`products` and `posts`). Content (titles, excerpts, images) is resolved dynamically at render time.
- **Fail-safe resolution:** If a referenced product or post is deleted or unpublished, public queries omit the item gracefully rather than breaking the home page. In Admin Studio, missing references are flagged clearly for administrative review.
- **Independent deletion safety:** Deleting or reordering a showcase item NEVER affects the underlying product, project, or post entity.
- **Strict separation from Gallery:** Home Showcase is a curated, mixed-type presentation rail (Product, Image, Project, Writing). It does not replace or conflate with `achievement_images` (the dedicated project Gallery).

## Appendix: how the new schema.sql was verified

Because 16 tables is too much to hand-transcribe without transcription errors (and an early attempt at auto-generating the file with case-normalization *did* introduce bugs — corrupted identifiers like `stat_date` → `stat_DATE`, duplicated foreign-key lines, missing indexes on 5 tables — all caught before use, not shipped), the final `schema.sql` was built with a different, more conservative method:

1. Every column definition was extracted verbatim from the export — no re-casing, no reformatting of keywords, byte-for-byte identical to what `phpMyAdmin` produced.
2. Every index/`PRIMARY KEY`/`FOREIGN KEY` line was extracted by anchoring to the export's own `-- Indexes for table X` / `-- Constraints for table X` comment markers, rather than a loose pattern that could conflate index blocks with `AUTO_INCREMENT` blocks (which caused the duplication bug in the first attempt).

**Note:** The `products` table (#17) and the User Platform tables (#18 through #24) were added via formal migration scripts following the same conventions observed in the verified production schema.

## User Platform — Tables #18 through #24 (User Dashboard & Interaction System)

Added as part of the User Platform implementation. The User Platform stores **relationships between users and content**, adhering strictly to the single-source-of-truth principle (content master records reside in `posts`, `products`, or `lab_experiments.json`).

**Migration:** `database/migration_user_platform.sql` (registered in `admin/run_migrations.php` as `user_platform`).

### Table Summary:

- **`user_profiles` (#18):** 1-to-1 extension of `users` table (`user_id` PK FK `users.id` ON DELETE CASCADE). Holds presentation details (`bio`, `avatar_url`) and preferences (`theme_preference`, `language_preference`, `email_notifications`, `activity_visibility`).
- **`bookmarks` (#19):** Saved reading list. `user_id` FK `users.id` CASCADE, `content_type` ENUM('writing','project','lab','product'), `content_id` VARCHAR(64). `UNIQUE KEY(user_id, content_type, content_id)`.
- **`likes` (#20):** Content appreciation. `user_id` FK `users.id` CASCADE, `content_type`, `content_id`, `UNIQUE KEY(user_id, content_type, content_id)`.
- **`reading_history` (#21):** Reading progress and recently viewed items. `user_id`, `content_type`, `content_id`, `progress_percent` (0-100), `last_viewed_at` (TIMESTAMP ON UPDATE). `UNIQUE KEY(user_id, content_type, content_id)`.
- **`user_downloads` (#22):** Historical immutable record of digital asset downloads. `user_id` FK `users.id` CASCADE, `product_id` NULLABLE FK `products.id` ON DELETE SET NULL, `product_title` VARCHAR(255), `downloaded_at`. Protects download history even if product catalog item is archived or removed.
- **`user_library` (#23):** User access to digital resources and claimed tools. `user_id` FK `users.id` CASCADE, `product_id` FK `products.id` CASCADE, `access_type` ENUM('free_claim','external_link'), `created_at`. `UNIQUE KEY(user_id, product_id)`.
- **`user_activities` (#24):** Append-only audit log of user interactions (`bookmark_added`, `like_added`, `content_viewed`, `download_completed`, `profile_updated`, `settings_updated`). `user_id` FK `users.id` CASCADE, `created_at`.

### Key Architectural Invariants:

1. **Canonical Content Types:** Public User Platform content types are strictly `writing` (`posts WHERE type='blog'`), `project` (`posts WHERE type='achievement'`), `lab` (`api/data/lab_experiments.json`), and `product` (`products`).
2. **Polymorphic Reference Design:** `content_id` is defined as `VARCHAR(64)` to cleanly support alphanumeric Lab benchmark identifiers (`EXP-001`) without breaking relational integrity or imposing invalid foreign keys onto file-based lab data.
3. **Download Longevity:** `user_downloads.product_id` uses `ON DELETE SET NULL`, preserving the title and download timestamp for user records even if the master product is removed from the store catalog.
4. **No Internal Commerce:** No orders, payments, carts, or checkout tables exist. Library access represents claimed/granted resources, not financial purchases.

---

## Administrative Audit Logging — Table #25 (`admin_audit_log`)

Added as part of the Phase P0 Security & Audit Logging architecture (`database/migration_admin_audit_log.sql`).

### Schema:

- `id` (int(10) UNSIGNED NOT NULL AUTO_INCREMENT PK)
- `admin_id` (int(10) UNSIGNED NOT NULL) — ID of administrator who performed action, or `0` for unauthenticated system/portal actions (e.g. failed admin logins).
- `action` (varchar(100) NOT NULL) — Dotted canonical action name (e.g. `post.create`, `user.update`, `auth.login`, `backup.download`).
- `target_type` (varchar(50) NOT NULL) — Domain entity type (e.g. `post`, `product`, `user`, `auth`, `settings`).
- `target_id` (varchar(64) DEFAULT NULL) — Entity ID or unique reference.
- `details` (text DEFAULT NULL) — Sanitized JSON payload with sensitive keys redacted and 4 KB bounding.
- `ip_address` (varchar(45) NOT NULL DEFAULT '') — Client IP resolved securely via `getClientIp()` respecting `TRUSTED_PROXIES`.
- `created_at` (timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP)

### Retention & Immutability Policy:

1. **Append-Only by Design:** No web interfaces or API endpoints exist to update or delete audit log entries.
2. **Retention & Archival:** Administrators can export up to 10,000 log records to CSV via `POST /api/admin/audit_list.php?export=csv` (with CSRF verification and spreadsheet formula neutralization).
3. **Manual Pruning Only:** Pruning of aged records should only be executed manually by authorized database administrators directly via SQL or an offline maintenance cron script. Never expose log pruning to the web interface.
4. **Optional Engine-Level Immutability Triggers:** An optional, non-auto-applied SQL file (`database/optional_audit_log_immutability_triggers.sql`) is provided for environments requiring hard database-level write-once enforcement via `BEFORE UPDATE` and `BEFORE DELETE` triggers that raise `SIGNAL SQLSTATE '45000'`.

---

## Engineering Labs Table — `lab_experiments` (Dormant / Reserved)

- **Operational Source of Truth:** `api/data/lab_experiments.json`. All read and write operations for benchmarks, specifications, telemetry, and categories operate strictly on this JSON document store with `LOCK_EX` concurrency safety.
- **SQL Table Status:** The table `lab_experiments` was created via `database/migration_labs_database_table.sql`. It contains **0 rows** in production and local development, and **no application endpoints read from or write to it**.
- **Architecture Governance:** Detailed risk assessment, migration feasibility, and decision analysis are documented in `docs/LABS_STORAGE_DECISION.md`. The SQL table is retained as dormant/reserved; no ALTER, DROP, or schema mutations will occur without explicit authorization.

## Canonical table count and migration order (2026-09-30)

- `database/schema.sql` contains **38** `CREATE TABLE` statements (counted with a parser that ignores comments), matching the 38 tables of the production export of 2026-09-29. Older figures of 33 or 35 in other documents are obsolete.
- `schema_migrations` in production was populated by a bulk backfill: all ledger rows share one backfill timestamp and do **not** record when each change was really applied.
- Production order is always: backup, verify backup, GET preview of `/admin/run_migrations.php`, POST `RUN_PENDING_MIGRATIONS` through the approved process, verify, and only then deploy code. See `docs/DEPLOYMENT.md` section 4. This file does not claim that any migration has been executed in production.
