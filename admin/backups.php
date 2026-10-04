<?php
// ============================================================
// ADMIN — DATABASE BACKUPS
// Requires: authenticated admin session (admin role only)
// ============================================================

require_once dirname(__DIR__) . '/api/auth/guard.php';

requireAdminPage('login.php');

$activeNav = 'backups';
$pageTitle = 'Database Backups';

require __DIR__ . '/partials/layout_top.php';
?>

<!-- =====================================================
     1. WORKSPACE HEADER
====================================================== -->
<header class="admin-page-header">
    <div class="header-titles">
        <div class="breadcrumb" aria-label="breadcrumb">
            <a href="index.php">Admin Studio</a>
            <span class="breadcrumb-sep">/</span>
            <span>Platform &amp; Security</span>
            <span class="breadcrumb-sep">/</span>
            <span style="color: var(--accent);">Backups</span>
        </div>
        <h1 class="page-title">Database Backups</h1>
        <p class="page-description">Create, download, and manage point-in-time database snapshots. Backups are stored outside the web root and are not publicly accessible.</p>
    </div>

    <div class="header-actions">
        <button type="button" class="btn btn-secondary" onclick="loadBackups()">
            <i class="fas fa-arrows-rotate" aria-hidden="true"></i>
            <span>Refresh</span>
        </button>
        <button type="button" class="btn btn-primary" onclick="runBackupNow()" id="backupNowBtn">
            <i class="fas fa-database" aria-hidden="true"></i>
            <span>Backup Now</span>
        </button>
    </div>
</header>


<!-- =====================================================
     2. SECURITY WARNING BANNER
====================================================== -->
<div style="background: rgba(234, 179, 8, 0.08); border: 1px solid rgba(234, 179, 8, 0.35); border-radius: var(--radius); padding: 14px 18px; margin-bottom: 24px; display: flex; gap: 12px; align-items: flex-start;">
    <i class="fas fa-triangle-exclamation" style="color: var(--warning); margin-top: 2px; flex-shrink: 0;" aria-hidden="true"></i>
    <div>
        <strong style="color: var(--warning); font-size: 13px;">Security Notice — Handle Backups With Care</strong>
        <p style="margin: 4px 0 0; font-size: 12.5px; color: var(--text-secondary); line-height: 1.6;">
            Backup files contain <strong>all database content including password hashes, email addresses, and personal data</strong>.
            Store downloaded backups in an encrypted location. Never share them or leave them in publicly accessible storage.
            See <code style="font-family: var(--font-mono); font-size: 11px;">docs/BACKUP_RESTORE.md</code> for the safe restore procedure via phpMyAdmin.
        </p>
    </div>
</div>


<!-- =====================================================
     3. SUMMARY STAT CARDS
====================================================== -->
<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 24px;">
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Backup Files</span>
            <i class="fas fa-file-zipper stat-icon" aria-hidden="true"></i>
        </div>
        <div class="stat-value" id="statBackupCount">—</div>
        <div class="stat-meta">Stored snapshots (max 14)</div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Total Size</span>
            <i class="fas fa-hard-drive stat-icon" aria-hidden="true"></i>
        </div>
        <div class="stat-value" id="statBackupTotalSize">—</div>
        <div class="stat-meta">Compressed .sql.gz on disk</div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Last Backup</span>
            <i class="far fa-clock stat-icon" aria-hidden="true"></i>
        </div>
        <div class="stat-value" id="statLastBackupDate" style="font-size: 18px;">—</div>
        <div class="stat-meta" id="statLastBackupStatus">Checking...</div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Auto Cron</span>
            <i class="fas fa-clock-rotate-left stat-icon" aria-hidden="true"></i>
        </div>
        <div class="stat-value" style="font-size: 15px; color: var(--accent);">03:00 daily</div>
        <div class="stat-meta">Retention: 14 backups</div>
    </div>
</div>


<!-- =====================================================
     4. BACKUP FILES TABLE
====================================================== -->
<section class="table-container">
    <div class="admin-card-header" style="padding: 16px 20px; border-bottom: 1px solid var(--border-subtle);">
        <div>
            <h2 class="card-title">Backup Archive</h2>
            <p class="card-subtitle">Click Download to save a backup locally. Delete removes it from the server permanently.</p>
        </div>
    </div>

    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Filename</th>
                    <th>Created</th>
                    <th style="text-align: right;">Size</th>
                    <th style="text-align: center;">Actions</th>
                </tr>
            </thead>
            <tbody id="backupTableBody">
                <tr>
                    <td colspan="4" style="text-align: center; padding: 36px 16px; color: var(--text-muted);">
                        <i class="fas fa-spinner fa-spin" style="margin-right: 8px;"></i> Loading backups...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</section>


<!-- =====================================================
     5. CRON SETUP REFERENCE CARD
====================================================== -->
<section class="admin-card" style="margin-top: 24px; background: rgba(34, 211, 238, 0.03); border: 1px solid rgba(34, 211, 238, 0.18);">
    <div class="admin-card-header">
        <h3 class="card-title" style="font-size: 13.5px; color: var(--accent); display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-terminal" aria-hidden="true"></i> Hostinger Cron Configuration
        </h3>
    </div>
    <div style="padding: 0 20px 16px;">
        <p style="font-size: 12.5px; color: var(--text-secondary); margin-bottom: 10px;">
            Add this line in <strong>hPanel → Advanced → Cron Jobs</strong> (runs daily at 03:00 server time):
        </p>
        <pre style="background: var(--bg-surface-elevated); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); padding: 10px 14px; font-family: var(--font-mono); font-size: 12px; color: var(--text-primary); overflow-x: auto; white-space: pre-wrap; word-break: break-all; margin: 0;">0 3 * * * /usr/local/lsws/lsphp82/bin/php /home/u303927365/domains/mohammedalrashadi.com/public_html/cron/backup.php</pre>
        <p style="font-size: 11.5px; color: var(--text-muted); margin-top: 8px;">
            Adjust the PHP binary path if you are on a different PHP version (e.g., <code style="font-family: var(--font-mono);">lsphp81</code>).
            On failure the cron sends an alert email to your configured SMTP address — no email on success.
        </p>
    </div>
</section>


<?php
$pageScripts = ['js/backups.js'];
require __DIR__ . '/partials/layout_bottom.php';
?>

