# Testing

**Status as of:** Phase 2 — Security Remediation
**Supersedes:** nothing. Phase 1 established there was no test infrastructure at all (`docs/ENGINEERING_AUDIT.md` §17, finding T-1).

## What this is, and what it deliberately is not

This is the **minimum viable test harness** called for by the Phase 2 testing requirement — not PHPUnit, not the full repository-layer architecture. Both of those are explicitly deferred to Phase 4/5 per the roadmap in `docs/ENGINEERING_AUDIT.md`. What exists now: a ~100-line custom runner (`tests/bootstrap.php`), a `TestSuite` class with `assertTrue`/`assertFalse`/`assertFileContains`/etc., and one test file per security fix under `tests/security/`.

**Run it:** `php tests/run.php`

**Current result** (executed in this environment, this phase):

```
TOTAL: 53 passed, 0 failed across 5 suite(s)
```

## The honest limitation, stated once and clearly

This sandbox has **no MariaDB instance and no production credentials in use**. That means:

- Every test that doesn't need a database (pure PHP logic, file-shape checks, `.htaccess` syntax review) is **real** — it executes, and it will fail if the thing it guards against regresses.
- Nothing here proves the application behaves correctly against a live database, a real browser, or real production traffic. Those remain **NOT VERIFIED** until run against an actual MariaDB instance, a running web server, or production itself.

Two categories of test in this suite, and which is which:

| Category | What it proves | Example |
|---|---|---|
| **Source-shape tests** | The exact code pattern of a fix is present/absent — e.g. "the only `apply()` call site is inside the POST branch." Real regression protection: if a future edit reintroduces the bug, the assertion about *where the code sits* breaks. Does not execute the code's actual runtime behavior. | `test_migration_safety.php`, most of `test_headers_and_https.php` |
| **Functional tests** | The code actually runs and produces the right result. Only possible where there's no DB dependency. | `test_telemetry_rate_limit.php` — genuinely calls the rate limiter 61 times and proves it blocks at the right point |

## Suite-by-suite

### `tests/security/test_migration_safety.php` — 9 assertions (SEC-03 / C-1)

Source-shape. Verifies: the file branches on `REQUEST_METHOD`; `apply()` appears exactly once in the whole file and sits inside the POST branch, after `requireCSRF()`, before the GET preview section; the GET section calls `check()` but never `apply()`; a literal confirmation string is required; applied migrations are logged with an admin user ID; `requireAdminPage()` is still present (auth requirement unchanged).

**Not covered:** actually POSTing to the endpoint and observing a real migration run or a real 403 on missing CSRF — requires a running server + DB.

### `tests/security/test_runtime_state_untracked.php` — 8 assertions (SEC-08 / D-1 / H-3)

Mixed. The `.gitignore` checks and the file-guard checks are source-shape. **One assertion actually shells out to `git ls-files`** and checks the real output is empty — this is a genuine fact about the repository's current tracking state, not an inference from reading `.gitignore` (a file can be gitignored and still tracked if it was `git add`ed before the ignore rule existed — this test catches that specific failure mode, which is exactly the mistake being fixed).

### `tests/security/test_telemetry_rate_limit.php` — 5 assertions (SEC-07 / S-5)

**Fully functional.** `api/telemetry/rate_limit.php` has zero database dependency, so this test `require`s it directly and calls the real functions: fires 60 requests against a fresh random test bucket, asserts none were blocked; fires a 61st, asserts it *is* blocked; checks a different endpoint bucket for the same simulated IP is unaffected. Test data is written to the real `api/data/telemetry_attempts.json` under keys prefixed `test_`, then cleaned up at the end of the test run.

**Not covered:** the DB-dependent parts of `visit.php`/`view.php` (identity resolution, the actual `INSERT`/`UPDATE` into `site_visitors`/`daily_site_stats`) — only the rate-limiting logic itself, which sits in front of that DB work.

### `tests/security/test_dompurify_self_hosted.php` — 7 assertions (SEC-05)

Source-shape. Confirms the vendored file exists, its license banner matches the exact pinned version (3.1.6), `layout_top.php` references the local path and not cdnjs, and the admin CSP's `script-src` was correspondingly tightened.

### `tests/security/test_headers_and_https.php` — 24 assertions (SEC-04 / SEC-06)

Source-shape, `.htaccess` syntax only — **cannot** verify actual HTTP response headers, since no Apache/LiteSpeed instance exists in this environment. Checks: all 6 security headers present with plausible values; CSP includes `frame-ancestors 'none'`; HSTS is conditional on the `IS_HTTPS` env var and excludes `preload`/`includeSubDomains`; the HTTPS redirect checks `X-Forwarded-Proto` rather than the proxy-breaking naive `%{HTTPS} off` pattern; `admin/.htaccess` exists and widens only what admin needs.

**A note on this test file's own history, left in deliberately:** the first version of these assertions used whole-file substring search, which produced 3 false-positive failures — the `.htaccess` file's own explanatory comments mention "preload," "includeSubDomains," and "cdnjs" precisely to explain why they were deliberately excluded from the actual directive, and a naive `strpos()` over the whole file matched the comments instead of the directives. Fixed by extracting the specific `Header always set ...` line via regex before asserting. Mentioning this not to pad the count, but because it's a real example of exactly the kind of thing a source-shape test suite needs to get right — asserting against comments instead of code is a false sense of security.

## What's explicitly NOT here yet

- Browser/E2E tests (Home, Projects, Lab, Gallery, Writing, Journey, About, Admin Login, Admin Dashboard, Settings, one CRUD page) — **not run**. No browser automation tool and no running server exist in this environment. This is the single largest gap in this phase's verification and should be the first thing done once there's an environment that can actually serve the site.
- Database-dependent tests of any kind (auth flow, CSRF against a live session, upload execution protection end-to-end) — **not run**. Same reason.
- Conversion of the `scratch/*.php` manual verification scripts into this harness — not done this phase; noted as a Phase 4 task in the roadmap, not silently dropped.
- PHPUnit / the full repository-layer test architecture — deferred per explicit Phase 2 scope, see `docs/ENGINEERING_AUDIT.md` roadmap Phase 4.

## Adding a test

Create `tests/security/test_whatever.php` following the existing files' shape: a single `test_*(TestSuite $t): void` function, plus the `if (php_sapi_name() === 'cli' && ...)` block at the bottom so the file can also run standalone (`php tests/security/test_whatever.php`). `tests/run.php` discovers and runs every `test_*.php` file under `tests/security/` automatically — nothing else to wire up.
