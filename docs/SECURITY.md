# Security

**Status as of:** Phase 2 — Security Remediation
**Supersedes:** nothing. This is the first `docs/SECURITY.md` for this project.
**Companion document:** `docs/ENGINEERING_AUDIT.md` (Phase 1) — every finding ID below (S-1, S-2, C-1, etc.) refers back to that document.

## Verification legend

| State | Meaning |
|---|---|
| **VERIFIED FROM REPOSITORY** | Confirmed directly by inspecting tracked files or Git metadata. |
| **VERIFIED FROM LOCAL RUNTIME** | Confirmed by actually executing code in this environment (see `docs/TESTING.md`). |
| **VERIFIED FROM PRODUCTION** | Confirmed against the live site/database. None of this document's findings carry this tag — this environment has no production access. |
| **INFERRED** | A reasoned conclusion from source, not directly executed or observed. |
| **NOT VERIFIED** | Cannot be confirmed from this environment. |
| **BLOCKED** | Requires information, access, or network egress this environment does not have. |

---

## SEC-01 — Credential rotation

### What happened (VERIFIED FROM REPOSITORY)

The Phase 1 audit found `api/config.local.php` tracked in Git history. This phase re-verified:

- The file was **modified** in 6 commits (`19c6504` → `48fb5dd`, 2026-09-15 to 2026-09-16), and is **present in the tree** (i.e., checking out the commit would materialize the file) of **36** of the repository's 62 total commits.
- `92da311` ("chore: untrack cache file, add .gitignore, and rotate database credentials") performed a rotation.
- `48fb5dd` ("security: stop tracking api/config.local.php") removed it from tracking going forward.
- A separate file, `docs/PRODUCTION_DATABASE_CONNECTION_AUDIT.md`, contained a plaintext password across 4 commits before `42198d9` redacted it.
- The review archive supplied for the Phase 1 audit also contained the then-current `DB_PASS` and `TELEMETRY_SECRET` in plaintext.

**No credential value from any of the above is reproduced in this document, in any file in this repository, or in any test.** Per your instruction, none were used to connect to anything, and no rotation was performed by this session.

**Operational note:** during this phase's Git-history investigation, a broad `git log -p | grep` scan against historical commits returned raw diff content that included plaintext historical password values in the session's tool output. This was a methodology error — the intended check does not require viewing raw secret values, only confirming their presence/absence, and Phase 1's original check against the *current* password used exactly that safer method (a direct equality comparison, never printed). No such value was written to any file, and this method was abandoned mid-task in favour of the boolean-only approach for everything after it. Flagging this plainly rather than omitting it, because it bears on how thoroughly "burned" the historical credentials should be considered.

### Rotation procedure (values redacted throughout — perform manually or hand to an agent with production access)

This procedure is written for **you to execute**, per your instruction not to rotate credentials automatically.

1. **Record current config without exposing it.** In hPanel or via SSH (if available), copy `api/config.local.php` to a private, non-Git location (e.g. your local password manager or an encrypted note) *before* changing anything, so you have a rollback reference. Do not paste its contents into this chat.
2. **Generate a new database password.** Use hPanel's MySQL Databases panel (Hostinger generates a strong random password there directly — recommended over hand-typing one) or a local generator producing at least 24 characters with mixed classes.
3. **Change the password in hPanel** under MySQL Databases → the `u303927365_alrashadi` user (the placeholder-style value visible earlier in this conversation from your own phpMyAdmin session, not something I'm disclosing). This takes effect immediately — expect the site to error until step 4 completes.
4. **Update production `api/config.local.php`** via Hostinger File Manager or SFTP directly — not via `git push`, since this file is (correctly) gitignored and should never travel through the repository. Update only the `DB_PASS` constant.
5. **Generate a new `TELEMETRY_SECRET`.** Requirement per the existing code (`api/telemetry/identity.php`) is ≥32 characters, cryptographically random. Locally: `php -r "echo bin2hex(random_bytes(32));"`. Do not run this over a network connection you don't trust, and don't paste the output into this chat — copy it directly into the production file.
6. **Update `TELEMETRY_SECRET`** in the same production `config.local.php` edit as step 4.
7. **Verify database connectivity:** load any public page (e.g. `/`). A clean render with real content (not the hardcoded fallback strings) confirms the new `DB_PASS` works. If you see fallback content or a generic 500, `error_log` will show the specific PDO error server-side without leaking it to the browser (verified behavior — `api/db.php` re-throws, callers catch and log).
8. **Verify telemetry fails closed correctly:** temporarily set `TELEMETRY_SECRET` to a short (<32 char) value in a **non-production** test, confirm `api/telemetry/identity.php`'s length check rejects it (this logic is unchanged by this phase and was verified in Phase 1 — see `docs/ENGINEERING_AUDIT.md` §9.6). Do not leave a short secret in production even briefly.
9. **Confirm no secret leakage:** `grep -r "TELEMETRY_SECRET\|DB_PASS" api/*.log 2>/dev/null` (adjust to your actual log path) should return nothing tied to the new values. Check any HTTP response bodies from step 7 contain no credential (they won't — verified in Phase 1, `api/config.php` never echoes constant values). Confirm the new `api/config.local.php` is still `git status`-clean (untracked, matching `.gitignore`).
10. **Rollback:** if step 7 fails, restore the pre-rotation values from your step-1 backup into `config.local.php` and revert the hPanel password change to match. Because the old password and the new one are both known only to you at this point (never to this session), rollback requires no coordination with anything this session touched.

---

## SEC-02 — Git history

### Findings (VERIFIED FROM REPOSITORY)

| Item | Evidence |
|---|---|
| Remote configured | `origin` → `https://github.com/mohammedalrshadi/alrashadimohammed.git` |
| Remote visibility | **NOT VERIFIED / BLOCKED.** This sandbox has no stored GitHub credentials (`git ls-remote` fails on auth) and unauthenticated GitHub API probing returned inconsistent results — one `404` (consistent with either "private" or "doesn't exist under that exact name"), then a `403` rate-limit on retry (this sandbox's egress IP is shared and was already near its unauthenticated API quota). **Check this directly in your GitHub repo Settings — that's authoritative and takes seconds; nothing reliable can be concluded from here.** |
| Commits modifying `api/config.local.php` | 6: `19c6504`, `b5e7c28`, `ffd4ab9`, `92da311`, `afca7e4`, `48fb5dd` |
| Commits with the file present in tree | 36 of 62 total repo commits |
| Current production password present in history | **No** — verified in Phase 1 by direct equality comparison against every historical blob (method did not print the value) |
| Documentation secrets committed historically | **Yes** — `docs/PRODUCTION_DATABASE_CONNECTION_AUDIT.md`, 4 commits, redacted in `42198d9` |
| Runtime state files also tracked | `api/data/{lab_experiments,login_attempts,review_attempts}.json` — addressed separately under SEC-08, not a credential concern but same "shouldn't have been tracked" category |

### Recommended history-cleanup procedure (not executed — destructive, requires your explicit authorization)

If you confirm the remote is or was ever public, or you want to be thorough regardless:

1. **Do this only after SEC-01 rotation is complete** — cleaning history is pointless if the live credential it's protecting hasn't changed.
2. Use `git filter-repo` (not the deprecated `filter-branch`) to strip `api/config.local.php` and the pre-redaction versions of `docs/PRODUCTION_DATABASE_CONNECTION_AUDIT.md` from all history.
3. This rewrites every commit hash from `19c6504` onward — anyone else with a clone must re-clone, not pull.
4. Force-push is required (`git push --force-with-lease`) — GitHub will otherwise reject a history rewrite.
5. If any GitHub features reference specific commit SHAs (linked issues/PRs, deployment webhooks tied to a SHA), they break and need manual reconciliation afterward.
6. Alternative, lower-risk option if the repo is/was private and you're confident no one else cloned it: leave history as-is, rely on rotation, and simply note the historical exposure in this document (which is what this document already does).

**I am not executing any of this.** Per the stop conditions, this requires your explicit go-ahead, and step 1's precondition (rotation complete) isn't yet true since rotation itself also requires your action.

### New finding this phase — real PII also in Git history, not just credentials

**VERIFIED FROM REPOSITORY.** While cross-referencing the production dump against `docs/`, found that `docs/PRODUCTION_DATABASE_READINESS.md` — committed in `d7a7708`, before this session, unrelated to anything done in Phase 2 or 3 — contains the real admin email address in plaintext, twice.

**Fixed:** redacted in the current working-tree version of the file, with an inline note explaining why and pointing back here.

**Not fixed, and can't be from a redaction alone:** the original plaintext remains in `d7a7708` and any earlier commit that touched this file. This is additional, independent evidence for why the Git history cleanup above matters — it's not only credentials at stake, it's personal data too. The same procedure (steps 1–6 above) resolves both in one pass; no separate cleanup is needed for this specifically.

---

## SEC-03 — Migration execution safety (fixes C-1)

**VERIFIED FROM REPOSITORY + VERIFIED FROM LOCAL RUNTIME** (source-shape tests actually executed — see `docs/TESTING.md`, `tests/security/test_migration_safety.php`).

**Root cause:** `admin/run_migrations.php` executed every pending migration unconditionally on every page load — a plain GET. No method check, no confirmation, no audit trail.

**Files changed:** `admin/run_migrations.php` only. The `$MIGRATIONS` array and every individual migration's `check()`/`apply()` implementation are byte-for-byte unchanged.

**Exact change:**

- The execution loop now branches on `$_SERVER['REQUEST_METHOD']`.
- **GET** calls `check()` for every migration (read-only `information_schema` queries) and renders a preview. `apply()` does not appear anywhere in the GET code path.
- **POST** requires, in order: the existing `requireAdminPage()` guard (unchanged, already enforced before this code runs), `requireCSRF()` (the same mechanism every other admin mutation endpoint uses — `hash_equals` comparison against the session token), and a literal JSON body field `{"confirm": "RUN_PENDING_MIGRATIONS"}` that only the page's own button sends via a `window.confirm()`-gated `fetch()` call.
- Every migration actually applied is written to `error_log()` as `[run_migrations] APPLIED "<id>" by admin_user_id=<id> at <ISO8601>`.

**Verified by test** (source-shape, no DB required): the string `$migration['apply'])($pdo)` appears exactly once in the file, and its byte offset is confirmed to sit after the `if ($isPost)` branch opens and before the GET preview section begins — meaning it is structurally unreachable from a GET request.

**Not verified:** actual execution against a database (no MariaDB in this environment). The `check()`/`apply()` functions themselves were not modified, so their correctness is exactly as established (or not) in Phase 1 — unchanged.

**Remaining limitation:** repeated POST submission is not cryptographically prevented (no single-use token) — relies on the migrations being naturally idempotent (`apply()` is only called when `check()` returns false) plus the button disabling itself client-side. This is a deliberate, documented trade-off, not an oversight: a single-use CSRF-style nonce would need session-side bookkeeping for a low-value target (an authenticated admin re-clicking a button they already saw succeed).

---

## SEC-04 — Security response headers (fixes S-2)

**VERIFIED FROM REPOSITORY.** Cannot be verified against a live server from this environment (no Apache/LiteSpeed instance) — header behavior at the HTTP level is **NOT VERIFIED**, only the `.htaccess` syntax and directive shape.

### Asset inventory used to build the policy (VERIFIED FROM REPOSITORY — full grep across every `.php` file)

| Origin | Loaded by | Type | Executes JS? |
|---|---|---|---|
| `fonts.googleapis.com` | All 10 public pages (`includes/head.php`), all admin pages | Stylesheet | No |
| `fonts.gstatic.com` | Same (referenced by the googleapis CSS) | Font files | No |
| `cdnjs.cloudflare.com` (Font Awesome 6.4.0) | Admin only: `layout_top.php`, `login.php`, `run_migrations.php` — **never public pages** | Stylesheet + webfonts | No |
| `cdnjs.cloudflare.com` (DOMPurify 3.1.6) | Previously `layout_top.php` only | Script | **Yes — now eliminated, see SEC-05** |

Inline usage found (relevant to why `'unsafe-inline'` remains in `script-src`/`style-src`): inline `<script>` blocks on essentially every page, 14 files using inline `onclick`/`onchange`/etc. handlers, and roughly 700 inline `style=""` attributes across the codebase.

### What was implemented

**Root `.htaccess`** (applies site-wide):

```
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=()
Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline';
  style-src 'self' 'unsafe-inline' https://fonts.googleapis.com;
  font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;
  connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self';
  frame-ancestors 'none'; upgrade-insecure-requests
Strict-Transport-Security: max-age=15552000   (conditional — see SEC-06)
```

Notice this does **not** include `cdnjs.cloudflare.com` anywhere — public pages don't need it.

**`admin/.htaccess`** (new file, admin-only override): identical policy, except `style-src`/`font-src` additionally allow `https://cdnjs.cloudflare.com` for Font Awesome. `script-src` does **not** need to be widened — see SEC-05.

### Deliberate gap, stated plainly

`'unsafe-inline'` remains in both `script-src` and `style-src`. Removing it would break the site immediately (every page has inline scripts; ~700 inline style attributes exist). Closing this gap properly means either a nonce-based CSP (requires every inline `<script>` tag to carry a per-request nonce, generated server-side) or extracting all inline JS/CSS to external files — both are real refactors, explicitly out of scope for "do not perform broad refactoring during security remediation." **Recorded as follow-up work, not silently dropped**: see `docs/ENGINEERING_AUDIT.md` roadmap Phase 6.

What this CSP *does* meaningfully add despite `'unsafe-inline'` remaining: `object-src 'none'` (blocks Flash/plugin-based attacks entirely), `frame-ancestors 'none'` (clickjacking, redundant with but stronger than `X-Frame-Options` in supporting browsers), `base-uri 'self'` (blocks a class of injection attack that rewrites relative-URL resolution), `form-action 'self'` (blocks exfiltration via a malicious injected `<form>`), and a `default-src`/`connect-src` that blocks any *new* external origin from being loaded even if `'unsafe-inline'` script execution is achieved — the sanitizer bypass in S-3's threat model, a compromised cdnjs, would still be unable to `fetch()` data to an attacker-controlled origin because `connect-src 'self'` blocks it.

---

## SEC-05 — Subresource integrity / supply chain (fixes S-3)

**Mixed verification — see per-resource table.**

This sandbox's network egress does not include `cdnjs.cloudflare.com` or `fonts.googleapis.com` (confirmed: a direct `curl` to `cdnjs.cloudflare.com` from this environment returns HTTP 403 from the egress proxy). Per your explicit instruction, **no SRI hash was invented** for anything I could not actually fetch and hash myself.

| Resource | Executes JS? | Action taken | Verification |
|---|---|---|---|
| DOMPurify 3.1.6 | **Yes** | **Self-hosted.** Fetched the exact pinned version via `npm pack dompurify@3.1.6` (npm's registry IS reachable from this sandbox — confirmed by `npm view` and a successful `npm pack`), extracted `dist/purify.min.js`, verified its first line matches the upstream `/*! @license DOMPurify 3.1.6 ... */` banner, and vendored it to `assets/vendor/dompurify/purify.min.js` + `LICENSE`. `admin/partials/layout_top.php` now loads it from `/assets/vendor/dompurify/purify.min.js`. No behavior change — same version, same code, different origin. SRI is now moot for this file (it's same-origin; the entire point of SRI is defending a *cross-origin* fetch). | **VERIFIED FROM REPOSITORY** — file exists, license banner confirmed, script tag updated, admin CSP's `script-src` no longer lists cdnjs (also verified by test). |
| Font Awesome 6.4.0 CSS + webfonts | No (CSS/fonts only) | **Not changed this phase.** Cannot fetch from this sandbox to self-host or hash honestly. Lower risk than DOMPurify since it can't execute arbitrary JS even if compromised — the realistic worst case is CSS-based data exfiltration via attribute selectors, a narrower threat than full JS execution. | **BLOCKED** — requires network access to `cdnjs.cloudflare.com`, which this environment does not have. |
| Google Fonts CSS + font files | No | **Not changed this phase.** Same reasoning as Font Awesome — CSS/font resource, lower risk, and `fonts.googleapis.com`/`fonts.gstatic.com` aren't reachable from this sandbox either. | **BLOCKED** — same network limitation. |

**Recommended follow-up (not executed):** from an environment with access to `cdnjs.cloudflare.com`, run:

```bash
curl -s https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css | openssl dgst -sha384 -binary | openssl base64 -A
```

...and add the resulting `integrity="sha384-<output>"` attribute to the `<link>` tag, then verify the page still renders correctly (a stale/wrong hash silently blocks the resource under CSP). Same procedure for the Google Fonts stylesheet, noting that Google Fonts CSS is **not a stable target for SRI** — it's dynamically generated per User-Agent to serve different font formats, so its content (and therefore its hash) varies by browser, which is why major sites generally don't pin SRI on it. Self-hosting the specific font files actually used would be the more robust long-term fix, deferred to Phase 6 alongside the image-optimization work already on that roadmap.

---

## SEC-06 — HTTPS enforcement (fixes S-4)

**VERIFIED FROM REPOSITORY** (`.htaccess` syntax and logic reviewed). **NOT VERIFIED FROM LOCAL RUNTIME OR PRODUCTION** — no Apache/LiteSpeed binary exists in this sandbox to test against (confirmed: `which apachectl httpd apache2` all fail), and Hostinger's specific proxy behavior can only be confirmed on the real host.

**What was implemented**, added to the existing `<IfModule mod_rewrite.c>` block in the root `.htaccess` (not a second block):

```apache
RewriteCond %{HTTPS} =on [OR]
RewriteCond %{HTTP:X-Forwarded-Proto} =https [OR]
RewriteCond %{HTTP:X-Forwarded-Ssl} =on
RewriteRule ^ - [E=IS_HTTPS:1]

RewriteCond %{ENV:IS_HTTPS} !=1
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]
```

This deliberately mirrors the exact same proxy-aware detection already used in `api/auth/guard.php` and `api/auth/login.php` (which check `HTTPS`, `HTTP_X_FORWARDED_PROTO`, and `HTTP_X_FORWARDED_SSL` in that same order) — the reasoning being: if the PHP layer already had to solve "how do I know this request is really HTTPS behind Hostinger's proxy," the `.htaccess` layer needs the identical answer, not a different one that could disagree with it. The naive `RewriteCond %{HTTPS} off` pattern was deliberately **not** used, because behind a proxy that terminates TLS upstream, `%{HTTPS}` is `off` for every request from Apache's point of view — that pattern would 301-redirect every single request, including ones that already arrived over HTTPS via the proxy, producing an infinite loop.

HSTS is sent conditionally (`env=IS_HTTPS`) using the same env var, at `max-age=15552000` (180 days), with **no `preload`** and **no `includeSubDomains`**, exactly as instructed — this is a first rollout with no prior HSTS history for the domain, not a locked-in long-term commitment.

**Limitation, stated plainly:** none of this can be end-to-end tested from this sandbox. The `.htaccess` logic is correct *as reasoned from Hostinger's documented proxy behavior and the app's own existing PHP-level handling of it*, but "correct as reasoned" is not the same as "observed working." **Recommended verification once deployed:** `curl -I http://mohammedalrashadi.com/` should return a `301` to the `https://` URL with no loop (a `curl -IL` following redirects should terminate in one hop), and `curl -I https://mohammedalrashadi.com/` should show the `Strict-Transport-Security` header present.

---

## SEC-07 — Telemetry rate limiting (fixes S-5)

**VERIFIED FROM LOCAL RUNTIME.** Unlike the `.htaccess`-based fixes, this one has zero database dependency and was actually executed in this environment — see `docs/TESTING.md`, `tests/security/test_telemetry_rate_limit.php`.

**New file:** `api/telemetry/rate_limit.php`, deliberately mirroring `api/reviews/rate_limit.php`'s existing pattern (file-based, `flock(LOCK_EX)` for concurrency safety, fail-open on I/O error) rather than inventing a new mechanism.

**Policy:** 60 requests per 5-minute window, per IP, **per endpoint** — `visit` and `view` are tracked in separate buckets so normal article-reading traffic can't exhaust the budget shared with page-navigation beacons. Client IP resolution trusts a well-formed `X-Forwarded-For` (same choice as the review limiter, and a deliberately different choice from the login limiter's `REMOTE_ADDR`-only approach — telemetry is abuse-prevention, not an account-security boundary, so it's more important that it work correctly behind Hostinger's proxy than that it be unspoofable).

**Wired into:** `api/telemetry/visit.php` and `api/telemetry/view.php`, both immediately after `check_telemetry_guards()` (so a DNT/GPC/bot request — already exiting with a silent 204 — costs nothing extra) and before any identity resolution or database work (matching the existing "cheap check before expensive work" ordering the audit praised in the login/review limiters).

**Preserved unchanged, verified by test:** DNT/Sec-GPC handling, bot filtering, HMAC visitor hashing, fail-closed behavior on a too-short `TELEMETRY_SECRET`. **No database schema change** — the limiter is entirely file-based, matching the instruction not to introduce one.

**Functional proof (not just source inspection):** `tests/security/test_telemetry_rate_limit.php` actually calls `telemetryRateLimitExceeded()`/`telemetryRateLimitRecord()` 60 times in a loop and asserts none of them are blocked, then asserts the 61st **is** blocked, then confirms a different endpoint bucket for the same IP is unaffected. This ran and passed in this environment.

**Incidental, non-security finding surfaced during this work (not fixed, out of scope):** `api/telemetry/view.php` currently appears to be called only from `archive/legacy_static/post.js`, which is not loaded by the live `post.php` page. This suggests the article-view counter may not currently be incremented by any reachable path in production — a functionality gap, not a security issue, and outside Phase 2's scope. Flagging for your attention; recommend addressing alongside the Phase 5 application-architecture work if confirmed against production.

---

## SEC-08 — Runtime state files untracked (fixes D-1 / H-3)

**VERIFIED FROM REPOSITORY + VERIFIED FROM LOCAL RUNTIME** (git itself confirms tracking state — see `docs/TESTING.md`).

### Classification (per the instruction not to assume all three should just be deleted)

| File | Classification | Reasoning |
|---|---|---|
| `api/data/lab_experiments.json` | **B — Application content** | This is the Lab feature's actual content store, written by `api/labs/save.php`/`delete.php` through the admin UI. It is real, admin-authored content, not disposable state. |
| `api/data/login_attempts.json` | **A — Runtime state** | Pure rate-limiter bookkeeping (IP/email attempt counters with timestamps), fully regenerated by normal operation. |
| `api/data/review_attempts.json` | **A — Runtime state** | Same as above, for the review-submission rate limiter. |

### What was done

- All three **untracked** from Git (`git rm --cached`, working-tree copies preserved — nothing was deleted from disk).
- `.gitignore` updated with the reasoning inline (not just the bare paths).
- **Production data was not touched or migrated.** Per your instruction, no Lab content was deleted or replaced, and no migration to a database table was performed — that's correctly deferred to Phase 5 per the original audit roadmap.
- `api/data/.gitkeep` added (tracked) so the directory itself survives a fresh clone even though its contents are now gitignored.
- `api/labs/save.php` given a defensive `mkdir()` before writing, matching the pattern `api/auth/rate_limit.php` and `api/reviews/rate_limit.php` already used — the only writer that didn't already have it.

### Why this mattered beyond "shouldn't be in Git"

Before this fix, a `git pull`-based deploy on Hostinger would have **silently overwritten live production Lab content** with whatever was last committed (which, in this snapshot, is an empty array) and **reset any active login/review rate-limit lockouts**. This wasn't a hypothetical — Git history shows the Lab file being modified across 4 separate commits, meaning past deploys have already captured and re-committed whatever was on disk at deploy time.

---

---

## SEC-09 — Admin audit log integrity and retention policy

**VERIFIED FROM REPOSITORY + VERIFIED FROM LOCAL RUNTIME.**

### Architecture and security boundary

The admin audit logging system tracks administrative mutations and authentication events in the dedicated `admin_audit_log` table (see `docs/DATABASE.md`, Table #25).

Key architectural safeguards:
1. **Append-only by design**: There is no UI, API endpoint, or application code path that permits updating or deleting rows from `admin_audit_log`. All log entries are write-once.
2. **Centralized redaction**: Handled centrally within `logAdminAction()` in `api/auth/guard.php`. Recursively strips keys matching `/^(password|passwd|token|hash|secret|key|cookie|csrf|authorization|body)$/i`, truncates strings over 500 characters, and enforces a strict 4 KB bound on serialized JSON details while preserving valid JSON syntax.
3. **Fail-safe logging**: If `admin_audit_log` table does not exist or logging fails, `logAdminAction()` logs to `error_log` and fails open without crashing the administrative mutation.
4. **Admin-only authentication tracking**: `auth.login` and `auth.logout` are recorded exclusively for accounts with `role === 'admin'`. Normal customer/visitor logins are never recorded in `admin_audit_log`. Failed admin portal logins (`auth.login_failed`) record `admin_id = 0`, truncate the entered email/identifier to 100 characters, never record the entered password, and are rate-limited to at most 1 log row per IP per 60 seconds.
5. **Spoof-resistant IP resolution**: Utilizes the centralized `getClientIp()` helper in `api/config.php` which only inspects `X-Forwarded-For` when `REMOTE_ADDR` matches a pre-configured list of `TRUSTED_PROXIES` (exact IPs or CIDR ranges), and then reads the chain right-to-left, returning the first hop that is not itself a trusted proxy (the leftmost entry is client-controlled and is never trusted). Deployments behind Cloudflare/another CDN must set `TRUSTED_PROXIES` in `api/config.local.php`; otherwise all visitors share the proxy's IP and the login rate limiter can lock everyone out.

### Retention and pruning policy

- **No web deletion**: To maintain non-repudiation and audit integrity, no web interface or admin API exists to delete audit log entries.
- **Export before prune**: Administrators can export up to 10,000 log records at a time to CSV via `admin/audit-log.php` (powered by `api/admin/audit_list.php` using POST + CSRF verification and automatic CSV formula injection neutralization).
- **Manual pruning**: In environments where disk storage must be reclaimed, authorized database administrators should prune older records directly via the database CLI or phpMyAdmin after exporting records:
  ```sql
  -- Example: Archive / delete audit records older than 180 days
  DELETE FROM admin_audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 180 DAY);
  ```

### Optional database-level immutability triggers

An optional SQL script is provided at `database/optional_audit_log_immutability_triggers.sql`:
- Defines `BEFORE UPDATE` and `BEFORE DELETE` triggers on `admin_audit_log` that execute `SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'admin_audit_log is append-only: updates and deletes are prohibited';`.
- **Deliberately not auto-applied** by `admin/run_migrations.php`. Many shared hosting environments (including some cPanel/hPanel tiers) restrict the `TRIGGER` or `SUPER` privilege required to define database triggers. Applying triggers automatically during web migration execution could break or fail on restricted hosts.
- Where privileges allow, database administrators may manually apply this file via MySQL CLI or phpMyAdmin to enforce immutability at the storage engine level.

---

## Summary: findings closed vs. remaining this phase

| ID | Status |
|---|---|
| C-1 | **Fixed** — SEC-03 |
| S-2 | **Fixed** — SEC-04 |
| S-3 (DOMPurify) | **Fixed** — SEC-05 |
| S-3 (Font Awesome / Google Fonts) | **Blocked** — network access required, not invented |
| S-4 | **Fixed**, not runtime-verified — SEC-06 |
| S-5 | **Fixed** — SEC-07 |
| D-1 / H-3 | **Fixed** — SEC-08 |
| S-1 (credential rotation) | **Procedure documented**, not executed — requires your action (SEC-01) |
| S-1 (Git history cleanup) | **Documented, not executed** — destructive, requires your explicit authorization (SEC-02) |
| Remote visibility | **Blocked/inconclusive** from this sandbox — please check directly |
| Audit logging | **Implemented & Verified** — SEC-09 |

All other findings from `docs/ENGINEERING_AUDIT.md` (C-2, H-1, H-4, and everything MEDIUM/LOW) are **intentionally untouched** per the explicit DATABASE RULE and the instruction not to perform broad refactoring during security remediation.

