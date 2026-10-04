# PostgreSQL Migration Evaluation

**Status:** PROPOSED architecture and PLAN ONLY. No PostgreSQL environment exists anywhere in this project's toolchain. **Nothing in this document has been executed, tested, or verified against a real PostgreSQL instance.** Every claim about how a MariaDB feature would behave in PostgreSQL is based on documented PostgreSQL semantics, not observed execution — marked **NOT TESTED** throughout, not silently implied to be confirmed.
**Companion documents:** `docs/DATABASE.md` (current verified MariaDB schema — the source of truth this plan migrates *from*), `docs/PLATFORM_ARCHITECTURE.md` (target domain model this plan is independent of — migrating engines and restructuring domains are two different projects that happen to be requested together)

## Recommendation, stated up front

**Migrate, but only when a concrete requirement forces it — not on a schedule, and not this phase.** The dependency audit below found the actual MariaDB-specific surface area is small (6 call sites for one syntax difference, a handful of type mappings) — this is a real but bounded migration, not a rewrite. Section 4 (Original Phase 1 Decision, Revisited) explains why "stay on MariaDB" was the right call in Phase 1 and what's different now that changes the calculus.

## 1. Current database — what's actually there (VERIFIED, from `docs/DATABASE.md`)

17 tables (16 confirmed from a full production export, plus `schema_migrations` new this phase), all InnoDB, all `utf8mb4`. Full detail already documented; not repeated here. This section only covers what matters *for a Postgres migration specifically*.

## 2. MariaDB/MySQL-specific dependencies — concrete audit (VERIFIED FROM REPOSITORY)

Every item below was found by grepping the actual codebase, not assumed from knowing MySQL vs. Postgres differences in the abstract.

| Feature | Where | Postgres equivalent | Effort |
|---|---|---|---|
| `ENUM(...)` columns | 9 occurrences across `posts.type`, `posts.status`, `categories.type`, `reviews.status`, `home_showcase_items.item_type`, `about_content_blocks.block_type`, `journey_milestones.status`, `site_settings.default_theme` | PostgreSQL has a native `CREATE TYPE ... AS ENUM`, or a `CHECK` constraint on `TEXT`. Not a blocker, just a different mechanism — and Postgres enums are more rigid (adding a value requires `ALTER TYPE`, can't be done inside some transaction contexts pre-PG12), so a `CHECK` constraint is likely the better target, not a 1:1 port. | Low — mechanical, one decision (enum type vs. check constraint) applied consistently |
| `AUTO_INCREMENT` | 9 tables | `GENERATED ALWAYS AS IDENTITY` (modern Postgres) or `SERIAL` (older style) | Low — mechanical |
| `UNSIGNED` integers | 23 occurrences | No direct equivalent — Postgres integers are always signed. A `CHECK (column >= 0)` constraint is the standard substitute. | Low — mechanical, but 23 constraints to add, not zero-effort |
| Backtick identifier quoting | 230 occurrences in `schema.sql` alone | Postgres uses double-quotes for quoted identifiers (backticks are invalid syntax) | Low — mechanical find-replace, but must be done carefully around string literals that legitimately contain backticks (none found, but worth verifying, not assuming) |
| `` `ON DUPLICATE KEY UPDATE` `` | **6 active call sites**, all in real application code (not `scratch/`): `admin/run_migrations.php`, `api/telemetry/view.php` (x2), `api/telemetry/visit.php` (x2), `api/social/update.php` | `INSERT ... ON CONFLICT (...) DO UPDATE SET ...` — semantically similar but requires explicitly naming the conflicting unique/PK column(s), which `ON DUPLICATE KEY UPDATE` doesn't | **Medium** — this is the one genuine rewrite, not a mechanical swap; each of the 6 call sites needs its conflict target identified and the statement restructured |
| `current_timestamp()` (lowercase function call) / `NOW()` | 22 occurrences | Postgres accepts `CURRENT_TIMESTAMP` (no parens, no case sensitivity issue) or `now()` — both exist in Postgres already | None — already compatible |
| `REGEXP_REPLACE(...)` with `ESCAPE '\\'` | 2 occurrences (`api/search.php`, `api/posts/list.php`) | Postgres has `regexp_replace()` too, but MySQL and PostgreSQL use different regex dialects (MySQL: ICU-based; PostgreSQL: POSIX). The specific pattern used (`<[^>]+>` — stripping HTML tags) is simple enough to likely translate directly, but this is exactly the kind of claim that needs actual testing, not just reading two docs pages and assuming they match. **NOT TESTED.** | Low-Medium — probably fine, must be verified against real Postgres before trusting it |
| `SHOW TABLES` / `SHOW COLUMNS` (MySQL-specific introspection syntax) | **Zero active call sites** — confirmed by grep. Every remaining match is inside a comment explaining that this code used to exist before this phase's H-1 fix. | N/A — already resolved as a side effect of Phase 3's schema-governance work, not something this migration needs to solve | None |
| `information_schema.tables` / `information_schema.columns` (used in `admin/run_migrations.php`'s `tableExists()`/`columnExists()` helpers) | `admin/run_migrations.php` | `information_schema` is ANSI SQL standard and exists in PostgreSQL with the same table/column names for this use case | Low — likely works with zero changes, but **NOT TESTED** |
| `LONGTEXT`, `TEXT`, `VARCHAR(n)` | Throughout | PostgreSQL's `TEXT` has no MySQL-style length-tiering (`TEXT` vs `MEDIUMTEXT` vs `LONGTEXT`) — a single `TEXT` type handles all of it, with no practical size limit. Simplification, not a blocker. | None — actually easier in Postgres |
| `TINYINT(1)` for booleans | Every `is_enabled`/`show_*`/`public_contact_enabled` column | Postgres has a real `BOOLEAN` type | Low — mechanical, and arguably a correctness improvement (today `TINYINT(1)` permits values other than 0/1; a real `BOOLEAN` doesn't) |
| `CHAR(64)` for hashed identifiers (`visitor_hash`) | `site_visitors`, `telemetry_dedup_*` | Identical in Postgres | None |

**What this table shows, in one sentence:** the actual MariaDB-specific surface area is small and mostly mechanical — one real rewrite category (`ON DUPLICATE KEY UPDATE`, 6 call sites), everything else is type-mapping or already compatible.

## 3. What does NOT need to change

- All application-level PDO usage (`api/db.php`) — PDO supports a `pgsql` driver with the same interface; no rewrite of the connection/query-execution pattern needed, only the DSN and driver name.
- Every `?`-placeholder prepared statement — identical syntax in both.
- The entire authentication, CSRF, rate-limiting, and session layer — none of it is database-engine-specific.
- The file-based rate limiters (SEC-07/08) — not database-backed at all.
- `REGEXP_REPLACE`'s general shape (see caveat above).

## 4. Original Phase 1 decision, revisited

Phase 1's audit (`docs/ENGINEERING_AUDIT.md` §4.6) recommended staying on MariaDB, reasoning that Hostinger shared hosting offers MariaDB natively at zero cost, and Postgres would require external hosting plus migration cost with no concrete requirement driving it. **That reasoning still holds for hosting cost and current requirements.** What's different now: `docs/PLATFORM_ARCHITECTURE.md`'s proposed Store domain needs real transactional integrity (`Order` → `Order Item` → `Payment` → `Entitlement`, with money involved) — and while MariaDB's InnoDB engine has real ACID transactions too, PostgreSQL's constraint system (`CHECK` constraints, deferrable constraints, native `BOOLEAN`, richer `JSON`/`JSONB` for flexible product metadata) is a genuinely better fit for that specific future domain.

**This is not a verdict.** It's the honest tension: current hosting reality favors staying, a specific future domain favors moving. Per the requirement's own priority order (P0 first, P1 architecture, P2 migration design only after that), the right sequencing is: build nothing on the Store domain yet (nothing currently requires it), keep this document as the plan, and make the actual migrate-or-don't decision when the Store domain moves from PROPOSED to justified-by-a-real-requirement — not before.

## 5. Target PostgreSQL schema — proposed, for the CURRENT verified tables only

This translates today's real 17 tables, not the proposed future Store/User Platform domains (those don't have a MariaDB schema to translate *from* — they're new designs, already covered conceptually in `docs/PLATFORM_ARCHITECTURE.md`, and would be designed directly in PostgreSQL if/when built, not migrated).

```sql
-- PROPOSED — NOT TESTED — illustrative of the mechanical conversions in
-- Section 2, not a file meant to be run. One representative table shown
-- in full; the same pattern applies to the other 16.

CREATE TABLE posts (
    id          INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    title       VARCHAR(500) NOT NULL,
    category    VARCHAR(255) NOT NULL DEFAULT '',
    content     TEXT NOT NULL,
    type        TEXT NOT NULL DEFAULT 'blog'
                    CHECK (type IN ('blog', 'project', 'achievement')),
    status      TEXT NOT NULL DEFAULT 'draft'
                    CHECK (status IN ('published', 'hidden', 'draft')),
    views       INTEGER NOT NULL DEFAULT 0 CHECK (views >= 0),
    image_url   VARCHAR(1000),
    quote_ar    TEXT,
    quote_en    TEXT,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at  TIMESTAMP
);
-- updated_at's ON UPDATE CURRENT_TIMESTAMP has no direct Postgres
-- equivalent — needs a trigger (BEFORE UPDATE, sets NEW.updated_at).
-- This applies to every table with that column, not just posts.
```

**The `ON UPDATE CURRENT_TIMESTAMP` gap is worth calling out on its own**, since it wasn't in the Section 2 table: MariaDB has this as a column-level declaration; PostgreSQL needs an actual trigger function, written once and attached to every table that needs it. One new piece of infrastructure, not present in the current schema at all, needed for a faithful migration.

## 6. Migration strategy (proposed, unexecuted)

Per the required sequence:

1. **Backup** — a full `mysqldump` of production (this phase already has one, dated 2026-09-18, used to build `schema.sql`; a fresh one would be needed at actual migration time, not reused).
2. **Non-production target** — provision a real PostgreSQL instance somewhere (not decided here — Hostinger doesn't offer managed Postgres on shared hosting, so this alone is a hosting decision requiring your input, not a technical one this document can resolve).
3. **Schema conversion** — apply Section 5's translated DDL (extended to all 17 tables) to the test instance.
4. **Data migration** — export each MariaDB table's data, transform types where needed (booleans, enums), load into Postgres. A tool like `pgloader` handles most of this automatically, including much of Section 2's type mapping — worth using rather than hand-writing a full ETL script.
5. **Validation**, all against the test instance, none against production:
   - Row counts match, table by table.
   - Spot-check real records (careful: per the PII lesson from earlier this phase, exclude `users.email` and `password_hash` from any validation output that could end up in a doc or a log).
   - Every FK/constraint from Section 5 actually enforces (insert a violating row, confirm it's rejected).
   - Application connects to the Postgres test instance (swap `api/config.local.php`'s DSN, nothing else) and every page loads.
   - Auth flow works end-to-end (login, session, CSRF) — this is the highest-risk area to silently break, since it's the security boundary.
   - The 6 `ON DUPLICATE KEY UPDATE` rewrites specifically get their own test each, since they're the one non-mechanical change.
6. **Rollback plan** — the original MariaDB database is never touched during any of the above; "rollback" is simply "don't cut over," which is why step 2 (a separate non-production instance) matters as much as it does.
7. **Cutover** — only after step 5 passes completely, and only with your explicit authorization, per the stop conditions already established in Phase 2 and reaffirmed by this request's own rule against destructive production action.

## 7. What this phase did NOT do

- No PostgreSQL instance was created, connected to, or tested against.
- No data was exported from or migrated out of production.
- No application code was changed to target a different database driver.
- The full 17-table DDL translation was not written out (Section 5 shows the pattern on one representative table, not all 17) — doing so before a real Postgres instance exists to test it against would produce 16 more tables of **NOT TESTED** SQL, which adds bulk without adding verified value. Worth generating in full once there's somewhere to actually run it.
