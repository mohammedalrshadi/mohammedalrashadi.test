# Database Backup & Manual Restore Guide

> [!CAUTION]
> **Restoring a backup overwrites every table in the live database** — all data created after the backup was taken will be permanently lost. Never run an import on the production database without first confirming you have a second, independent copy of the data you need.

## What the Backup System Does

- **Storage**: gzip-compressed `.sql.gz` files stored **outside the web root** by default (`/home/<user>/backups/`). Falls back to `api/data/backups/` with Apache `Deny all` if the outside-root location is not writable.
- **Format**: standard MySQL SQL — `DROP TABLE IF EXISTS` + `CREATE TABLE` + batched `INSERT` statements, wrapped in `SET FOREIGN_KEY_CHECKS=0/1` and `utf8mb4` charset headers.
- **Retention**: the 14 most recent backups are kept; older files are automatically deleted after each successful run.
- **Scheduling**: daily at 03:00 server time via Hostinger cron (see Admin Panel → Backups for the exact command).

## Security Notice

Backup files contain:
- **Password hashes** for all user accounts
- **Email addresses** and other personally identifiable information
- **All content** including drafts, private settings, and API-related data

**Handle downloaded backups as you would production credentials.** Store them encrypted (e.g., in a password manager vault or encrypted external drive). Never upload them to public cloud storage or share them over unencrypted channels.

---

## Creating a Backup

### Via Admin Panel (Recommended for One-Off Snapshots)

1. Log in to `https://mohammedalrashadi.com/admin/`
2. Navigate to **Optimization & System → Backups**
3. Click **Backup Now**
4. Wait for the confirmation toast — typically 5–30 seconds depending on DB size
5. The new file appears at the top of the table

### Via CLI / Cron (Automated Daily Backup)

```bash
# Run from the server shell (Hostinger SSH):
/usr/local/lsws/lsphp82/bin/php /home/u303927365/domains/mohammedalrashadi.com/public_html/cron/backup.php
```

Exit code `0` = success, `1` = failure (failure also sends a notification email).

---

## Manual Restore via phpMyAdmin

> [!WARNING]
> These steps **completely replace** the target database. Do not run them on the live production database unless you are intentionally rolling back — and only after confirming you have a separate, working copy of any data you don't want to lose.

### Step-by-step

1. **Download the backup** from Admin Panel → Backups → Download, or retrieve it from the server via SCP/FTP.

2. **Open phpMyAdmin** — Hostinger hPanel → Databases → phpMyAdmin.

3. **Create a test database first (strongly recommended)**:
   - Click "New" in the left sidebar
   - Name it e.g. `restore_test_YYYYMMDD`
   - This lets you verify the dump imports cleanly before touching the live database

4. **Select the target database** in the left sidebar (either `restore_test_YYYYMMDD` for testing, or your live `u303927365_alrashadi` for a real restore).

5. Click the **Import** tab at the top.

6. Click **Choose File** and select the `.sql.gz` file. phpMyAdmin handles gzip natively — do **not** decompress it first.

7. Set **Character set** to `utf8mb4` (it's usually pre-selected).

8. Click **Go** and wait for the import to complete. For a typical site this takes under 30 seconds.

9. **Verify the import** by browsing a few tables (e.g. `users`, `posts`, `settings`) and confirming row counts look correct.

10. If restoring to production: update the application's `api/config.local.php` credentials to point at the restored database if they differ.

### What to do if the import fails

- **File too large**: increase `upload_max_filesize` and `post_max_size` temporarily in Hostinger hPanel → PHP Configuration, or use the MySQL CLI:
  ```bash
  mysql -u DB_USER -p DB_NAME < dump.sql
  # (first decompress: gzip -d dump.sql.gz)
  ```
- **Charset errors**: ensure the database's collation is `utf8mb4_unicode_ci` before importing.
- **Foreign key errors**: the dump includes `SET FOREIGN_KEY_CHECKS=0` at the top; if the import skips that line, run it manually in phpMyAdmin's SQL tab before re-importing.

---

## One-Click Restore is Intentionally Not Implemented

A web-based restore button could wipe the live database on a misclick, a CSRF attack, or a rogue admin account. Manual phpMyAdmin import requires deliberate, physical access to the server control panel, which is the right level of friction for a destructive operation on production data.

---

## File Naming Convention

```
db-YYYYMMDD-HHMMSS-<8 random hex chars>.sql.gz
    │        │      └─ e.g. a3f7c091
    │        └─ e.g. 030000  (03:00:00 for cron runs)
    └─ e.g. 20260920
```

Example: `db-20260920-030000-a3f7c091.sql.gz`

