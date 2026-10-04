# Architecture Decision Record: Labs Storage Strategy (JSON vs. SQL)

**Document:** `docs/LABS_STORAGE_DECISION.md`  
**Status:** PROPOSED (Awaiting User Decision)  
**Author:** AI Pair Programmer  
**Date:** 2026-09-28  

---

## 1. Context and Current State

The Engineering Labs feature (`/lab.php`, `/lab-detail.php?id={id}`, `admin/labs.php`) provides deep technical benchmarks, empirical specifications, reproducibility CLI commands, and metrics telemetry.

### Proven Findings (Read-Only Verification):

1. **Live Operational Storage:** `api/data/lab_experiments.json` is the sole active source of truth.
   - **Readers:** `lab.php`, `lab-detail.php`, `api/labs/list.php`, `api/labs/get.php`, `api/search.php`, `api/user/content_resolver.php`, `api/categories/list.php`, `api/categories/rename.php`, `api/categories/delete.php`, `api/helpers/stats_helper.php`, `sitemap.php`, `src/Domain/Content/RelatedContentResolver.php`.
   - **Writers:** `api/labs/save.php`, `api/labs/delete.php` (both utilize `LOCK_EX` for concurrency safety).
   - **Repository Hygiene:** `api/data/lab_experiments.json` is ignored by Git in `.gitignore` to prevent live content overwrite on deployment.
2. **SQL Table `lab_experiments`:**
   - Exists in schema (`CREATE TABLE IF NOT EXISTS lab_experiments ...`).
   - Contains **0 rows** in the live production database dump (`u303927365_alrashadi-12.sql`) and **0 rows** in the local development database.
   - **Zero runtime code** executes `SELECT`, `INSERT`, `UPDATE`, or `DELETE` against this table. The only references in the entire codebase are in `database/migration_labs_database_table.sql`, migration registration in `admin/run_migrations.php`, and a migration test in `tests/test_phase_p3_migrations.php`.

---

## 2. Option A: Keep JSON as Operational Source of Truth (Recommended)

Keep `api/data/lab_experiments.json` as the live storage backend and formally designate the SQL table `lab_experiments` as reserved/deprecated (or dormant).

### Advantages:

- **Zero Regression Risk:** No changes to 14 production runtime files.
- **Native Document Alignment:** Lab experiments contain deeply nested document structures:
  - `hypotheses` (list of text hypotheses)
  - `specs` (key-value hardware/software benchmark parameters)
  - `metrics` (telemetry names, units, targets, outcomes)
  - `results` (observations, CLI reproduction steps, log outputs)
  JSON stores and renders these natively without SQL serialization/deserialization impedance.
- **Polymorphic Compatibility:** The User Platform (`bookmarks`, `likes`, `reading_history`) already uses `content_type = 'lab'` with `content_id VARCHAR(64)` specifically designed to reference string IDs (e.g. `EXP-001`) from JSON without requiring foreign key constraints.
- **File Concurrency:** Writes already enforce exclusive locking (`LOCK_EX`).

### Disadvantages:

- Inability to perform complex SQL joins (e.g. `INNER JOIN lab_experiments`) inside MariaDB, though no current feature requires this.
- Backup requires filesystem backup/zip in addition to SQL dump.

### Exact Files Affected:

- None at runtime.
- Documentation updates: `docs/SOURCE_OF_TRUTH_MAP.md`, `docs/DATABASE.md`.

### Rollback Plan:

- Not applicable (no code mutations).

---

## 3. Option B: Multi-Step Migration from JSON to SQL

Migrate all data structures, read queries, write operations, and category cascades from JSON to the MariaDB `lab_experiments` table.

### Required Implementation Steps:

1. **Data Backfill Migration:** Write an idempotent migration script to parse `api/data/lab_experiments.json` and insert experiments into `lab_experiments` (serializing complex arrays into JSON strings).
2. **Refactor Write Endpoints:**
   - `api/labs/save.php`: Change file writes to transactional PDO `INSERT ... ON DUPLICATE KEY UPDATE` prepared statements, calling `logAdminAction()`.
   - `api/labs/delete.php`: Change file writes to `DELETE FROM lab_experiments WHERE id = ?`.
3. **Refactor Read Endpoints (12 files):**
   - `lab.php`: Replace `file_get_contents()` + `json_decode()` with `SELECT ... FROM lab_experiments WHERE status != 'archived' ORDER BY sort_order ASC`.
   - `lab-detail.php`: Query `SELECT ... FROM lab_experiments WHERE id = ?`.
   - `api/labs/list.php`: Query `SELECT ... FROM lab_experiments`.
   - `api/labs/get.php`: Query `SELECT ... FROM lab_experiments WHERE id = ?`.
   - `api/search.php`: Replace JSON scan with fulltext or LIKE queries on `lab_experiments`.
   - `api/user/content_resolver.php`: Query `SELECT ... FROM lab_experiments WHERE id = ?`.
   - `api/categories/rename.php`: Update `category` column in `lab_experiments`.
   - `api/categories/delete.php`: Disassociate or nullify categories in `lab_experiments`.
   - `api/categories/list.php`: Aggregate categories from `lab_experiments`.
   - `api/helpers/stats_helper.php`: Query counts from `lab_experiments`.
   - `sitemap.php`: Fetch experiment URLs via PDO query.
   - `src/Domain/Content/RelatedContentResolver.php`: Query related experiments via PDO.
4. **Test Suite:** Update all test fixtures across the test suite.

### Risks:

- **High Surface Area:** Mutates 14+ core files touching search, sitemap, category management, user interactions, and admin authoring.
- **Serialization Discrepancies:** Differences in how MariaDB JSON columns vs text blobs handle quotes, Unicode escapes, and arrays.
- **Data Loss / Concurrency Bugs:** If migration desynchronizes before cutover, concurrent admin edits could be dropped.

### Rollback Plan for Option B:

- Git revert of the commit modifying the 14+ files.
- Export current state of `lab_experiments` back into `api/data/lab_experiments.json` with pretty-printed JSON.

---

## 4. Recommendation and Comparison Matrix

| Factor | Option A: Keep JSON (Mark SQL Dormant) | Option B: Migrate to SQL |
| :--- | :--- | :--- |
| **Stability / Risk** | **Zero Risk** (Live production remains untouched) | **High Risk** (Touches 14+ files across public & admin) |
| **Nested Spec Handling** | **Native** (Clean JSON arrays & objects) | **Serialized** (`json_encode` / `json_decode` on text columns) |
| **User Platform Fit** | **Clean** (Compatible with polymorphic `VARCHAR(64)`) | **Same** (No foreign keys anyway due to alphanumeric IDs) |
| **Effort** | **Zero dev hours** | **12-16 dev hours + full regression test** |

### Recommendation:
**Adopt Option A.**  
Keep `api/data/lab_experiments.json` as the operational source of truth. Formally document the SQL table `lab_experiments` as reserved/dormant in `docs/DATABASE.md` and `docs/SOURCE_OF_TRUTH_MAP.md`. Do not perform any migration or schema alteration at this time.
