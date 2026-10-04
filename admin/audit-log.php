<?php
// ============================================================
// ADMIN — AUDIT LOG WORKSPACE
// admin/audit-log.php
// Requires: authenticated admin session (admin role only)
// ============================================================

require_once dirname(__DIR__) . '/api/config.php';
require_once dirname(__DIR__) . '/api/db.php';
require_once dirname(__DIR__) . '/api/auth/guard.php';

requireAdminPage('login.php');

$activeNav = 'audit';
$pageTitle = 'Audit Log';

// Diagnostic IP metrics for proxy/CDN verification (Requirement 1)
$detectedIp      = function_exists('getClientIp') ? getClientIp() : ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
$remoteAddr      = $_SERVER['REMOTE_ADDR'] ?? '(not set)';
$hasXff          = !empty($_SERVER['HTTP_X_FORWARDED_FOR']);
$xffVal          = $hasXff ? $_SERVER['HTTP_X_FORWARDED_FOR'] : '(none)';
$trustedProxies  = defined('TRUSTED_PROXIES') && is_array(TRUSTED_PROXIES) ? TRUSTED_PROXIES : ['127.0.0.1', '::1'];
$isTrustedProxy  = function_exists('isTrustedProxyIp') ? isTrustedProxyIp($remoteAddr) : in_array($remoteAddr, $trustedProxies, true);

// Check if migration is pending server-side
$tableExists = false;
try {
    $pdo = getDB();
    $chk = $pdo->query("SHOW TABLES LIKE 'admin_audit_log'");
    $tableExists = ($chk->rowCount() > 0);
} catch (Throwable $e) {
    $tableExists = false;
}

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
            <span style="color: var(--accent);">Audit Log</span>
        </div>
        <h1 class="page-title">Administrative Audit Log</h1>
        <p class="page-description">
            Immutable, append-only chronological record of all administrative operations, logins, mutations, and security events.
        </p>
    </div>

    <div class="header-actions">
        <button type="button" class="btn btn-secondary" onclick="loadAuditLog()" id="btnRefreshAudit">
            <i class="fas fa-arrows-rotate" aria-hidden="true"></i>
            <span>Refresh</span>
        </button>
        <button type="button" class="btn btn-primary" onclick="exportAuditCsv()" id="btnExportAuditCsv">
            <i class="fas fa-file-csv" aria-hidden="true"></i>
            <span>Export CSV</span>
        </button>
    </div>
</header>

<!-- =====================================================
     2. ADMIN-ONLY IP DIAGNOSTICS BAR (SEC-AUDIT)
====================================================== -->
<div class="audit-diagnostic-bar" style="background: var(--bg-surface-elevated); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); padding: 12px 18px; margin-bottom: 20px; font-size: 12.5px; display: flex; flex-wrap: wrap; gap: 16px; align-items: center;">
    <div style="font-weight: 600; color: var(--text-secondary); display: flex; align-items: center; gap: 6px;">
        <i class="fas fa-network-wired" style="color: var(--accent);"></i>
        <span>IP Diagnostics:</span>
    </div>
    <div>
        <span style="color: var(--text-muted);">Detected Client IP:</span>
        <code style="color: var(--accent); font-weight: 600; font-family: var(--font-mono); margin-left: 4px;"><?php echo htmlspecialchars($detectedIp, ENT_QUOTES, 'UTF-8'); ?></code>
    </div>
    <div>
        <span style="color: var(--text-muted);">REMOTE_ADDR:</span>
        <code style="font-family: var(--font-mono); margin-left: 4px;"><?php echo htmlspecialchars($remoteAddr, ENT_QUOTES, 'UTF-8'); ?></code>
        <?php if ($isTrustedProxy): ?>
            <span class="badge" style="background: rgba(34, 197, 94, 0.15); color: var(--success); font-size: 10px; padding: 2px 6px; border-radius: 4px; margin-left: 4px;">Trusted Proxy</span>
        <?php endif; ?>
    </div>
    <div>
        <span style="color: var(--text-muted);">X-Forwarded-For:</span>
        <code style="font-family: var(--font-mono); margin-left: 4px;"><?php echo htmlspecialchars($xffVal, ENT_QUOTES, 'UTF-8'); ?></code>
    </div>
</div>

<!-- =====================================================
     3. MIGRATION NOTICE BANNER (Graceful Fallback)
====================================================== -->
<div id="auditMigrationAlert" style="<?php echo $tableExists ? 'display: none;' : 'display: flex;'; ?> background: rgba(234, 179, 8, 0.1); border: 1px solid rgba(234, 179, 8, 0.4); border-radius: var(--radius, 8px); padding: 16px 20px; margin-bottom: 24px; gap: 14px; align-items: center;">
    <i class="fas fa-triangle-exclamation" style="color: var(--warning); font-size: 20px; flex-shrink: 0;"></i>
    <div style="flex: 1;">
        <strong style="color: var(--warning); font-size: 14px;">Audit Log Table Not Initialized</strong>
        <p style="margin: 4px 0 0; font-size: 13px; color: var(--text-secondary); line-height: 1.5;">
            The table <code style="font-family: var(--font-mono); font-size: 12px;">admin_audit_log</code> does not exist in the database. Run migrations to initialize audit tracking.
        </p>
    </div>
    <a href="run_migrations.php" class="btn btn-primary btn-sm" style="flex-shrink: 0;">
        <i class="fas fa-play" style="margin-right: 6px;"></i> Run Migrations
    </a>
</div>

<!-- =====================================================
     4. SUMMARY STATS RAIL
====================================================== -->
<section class="stats-grid" aria-label="Audit Log Metrics" style="margin-bottom: 24px;">
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Total Logged Events</span>
            <i class="fas fa-clipboard-list stat-icon" aria-hidden="true"></i>
        </div>
        <div id="statTotalAuditEvents" class="stat-value">0</div>
        <div class="stat-meta">Append-only audit trail</div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Filtered Matches</span>
            <i class="fas fa-filter stat-icon" aria-hidden="true"></i>
        </div>
        <div id="statFilteredEvents" class="stat-value">0</div>
        <div class="stat-meta" id="statFilteredEventsMeta">All historical records</div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Active Administrators</span>
            <i class="fas fa-user-shield stat-icon" aria-hidden="true"></i>
        </div>
        <div id="statActiveAdminsCount" class="stat-value">0</div>
        <div class="stat-meta">Configured with admin role</div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Latest Recorded Event</span>
            <i class="fas fa-clock-rotate-left stat-icon" aria-hidden="true"></i>
        </div>
        <div id="statLatestEventTime" class="stat-value" style="font-size: 16px; margin-top: 6px; font-family: var(--font-mono);">-</div>
        <div class="stat-meta" id="statLatestEventMeta">Synchronized with UTC</div>
    </div>
</section>

<!-- =====================================================
     5. FILTER TOOLBAR
====================================================== -->
<div class="admin-card" style="margin-bottom: 24px; padding: 18px 20px;">
    <div style="display: flex; flex-wrap: wrap; gap: 14px; align-items: flex-end;">
        <div style="flex: 1; min-width: 150px;">
            <label for="auditFilterAdmin" style="display: block; font-size: 12px; font-weight: 500; margin-bottom: 6px; color: var(--text-secondary);">Administrator</label>
            <select id="auditFilterAdmin" class="form-control" style="width: 100%;">
                <option value="">All Administrators</option>
            </select>
        </div>

        <div style="flex: 1; min-width: 150px;">
            <label for="auditFilterAction" style="display: block; font-size: 12px; font-weight: 500; margin-bottom: 6px; color: var(--text-secondary);">Action</label>
            <select id="auditFilterAction" class="form-control" style="width: 100%;">
                <option value="">All Actions</option>
            </select>
        </div>

        <div style="flex: 1; min-width: 150px;">
            <label for="auditFilterTarget" style="display: block; font-size: 12px; font-weight: 500; margin-bottom: 6px; color: var(--text-secondary);">Target Type</label>
            <select id="auditFilterTarget" class="form-control" style="width: 100%;">
                <option value="">All Target Types</option>
            </select>
        </div>

        <div style="flex: 1; min-width: 130px;">
            <label for="auditFilterDateFrom" style="display: block; font-size: 12px; font-weight: 500; margin-bottom: 6px; color: var(--text-secondary);">Date From</label>
            <input type="date" id="auditFilterDateFrom" class="form-control" style="width: 100%;">
        </div>

        <div style="flex: 1; min-width: 130px;">
            <label for="auditFilterDateTo" style="display: block; font-size: 12px; font-weight: 500; margin-bottom: 6px; color: var(--text-secondary);">Date To</label>
            <input type="date" id="auditFilterDateTo" class="form-control" style="width: 100%;">
        </div>

        <div style="flex: 1.5; min-width: 180px;">
            <label for="auditFilterSearch" style="display: block; font-size: 12px; font-weight: 500; margin-bottom: 6px; color: var(--text-secondary);">Search Target / Details</label>
            <input type="text" id="auditFilterSearch" class="form-control" placeholder="Target ID, IP, or keywords..." style="width: 100%;">
        </div>

        <div style="display: flex; gap: 8px;">
            <button type="button" class="btn btn-primary" onclick="applyAuditFilters()" id="btnApplyAuditFilters">
                <i class="fas fa-filter" aria-hidden="true"></i>
                <span>Filter</span>
            </button>
            <button type="button" class="btn btn-secondary" onclick="resetAuditFilters()" id="btnResetAuditFilters">
                <i class="fas fa-rotate-left" aria-hidden="true"></i>
                <span>Reset</span>
            </button>
        </div>
    </div>
</div>

<!-- =====================================================
     6. AUDIT LOG DATA TABLE
====================================================== -->
<div class="admin-card" style="padding: 0; overflow: hidden; margin-bottom: 24px;">
    <div class="table-responsive">
        <table class="admin-table" style="width: 100%; margin: 0; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="width: 70px;">ID</th>
                    <th style="width: 160px;">Timestamp</th>
                    <th style="width: 180px;">Administrator</th>
                    <th style="width: 140px;">Action</th>
                    <th style="width: 130px;">Target</th>
                    <th style="width: 120px;">Client IP</th>
                    <th>Details</th>
                    <th style="width: 80px; text-align: center;">View</th>
                </tr>
            </thead>
            <tbody id="auditTableBody">
                <tr>
                    <td colspan="8" style="text-align: center; padding: 36px; color: var(--text-muted);">
                        <i class="fas fa-spinner fa-spin" style="margin-right: 8px;"></i> Loading audit trail...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Pagination Toolbar -->
    <div style="display: flex; justify-content: space-between; align-items: center; padding: 14px 20px; border-top: 1px solid var(--border-subtle); background: var(--bg-surface-elevated);">
        <div id="auditPaginationInfo" style="font-size: 12.5px; color: var(--text-secondary);">
            Showing page 1 of 1
        </div>
        <div style="display: flex; gap: 8px;">
            <button type="button" class="btn btn-secondary btn-sm" onclick="prevAuditPage()" id="btnPrevAuditPage" disabled>
                <i class="fas fa-chevron-left" aria-hidden="true"></i> Previous
            </button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="nextAuditPage()" id="btnNextAuditPage" disabled>
                Next <i class="fas fa-chevron-right" aria-hidden="true"></i>
            </button>
        </div>
    </div>
</div>

<!-- =====================================================
     7. AUDIT DETAIL MODAL
====================================================== -->
<div id="auditDetailModal" class="modal" role="dialog" aria-labelledby="modalDetailTitle" aria-modal="true" style="display: none;">
    <div class="modal-backdrop" onclick="closeAuditDetailModal()"></div>
    <div class="modal-dialog" style="max-width: 650px; max-height: 85vh; display: flex; flex-direction: column;">
        <div class="modal-header">
            <h3 class="modal-title" id="modalDetailTitle">Audit Record Details</h3>
            <button type="button" class="modal-close" onclick="closeAuditDetailModal()" aria-label="Close modal">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>
        <div class="modal-body" style="overflow-y: auto; flex: 1;">
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 16px; font-size: 13px;">
                <div>
                    <span style="color: var(--text-muted); display: block; font-size: 11px;">Record ID</span>
                    <strong id="modalRecordId" style="font-family: var(--font-mono);">-</strong>
                </div>
                <div>
                    <span style="color: var(--text-muted); display: block; font-size: 11px;">Timestamp (UTC)</span>
                    <span id="modalTimestamp" style="font-family: var(--font-mono);">-</span>
                </div>
                <div>
                    <span style="color: var(--text-muted); display: block; font-size: 11px;">Administrator</span>
                    <span id="modalAdminUser">-</span>
                </div>
                <div>
                    <span style="color: var(--text-muted); display: block; font-size: 11px;">Client IP Address</span>
                    <code id="modalClientIp" style="font-family: var(--font-mono); color: var(--accent);">-</code>
                </div>
                <div>
                    <span style="color: var(--text-muted); display: block; font-size: 11px;">Action</span>
                    <span id="modalActionBadge" class="badge badge-secondary">-</span>
                </div>
                <div>
                    <span style="color: var(--text-muted); display: block; font-size: 11px;">Target</span>
                    <span id="modalTarget">-</span>
                </div>
            </div>

            <div style="margin-top: 16px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <span style="font-size: 12px; font-weight: 600; color: var(--text-secondary);">Details Payload (Sanitized JSON):</span>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="copyAuditDetailsJson()" id="btnCopyAuditJson" style="font-size: 11px; padding: 3px 8px;">
                        <i class="fas fa-copy" style="margin-right: 4px;"></i> Copy JSON
                    </button>
                </div>
                <pre style="background: var(--bg-surface-elevated); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); padding: 12px; font-family: var(--font-mono); font-size: 12px; max-height: 280px; overflow-y: auto; margin: 0; white-space: pre-wrap; word-break: break-word;"><code id="modalDetailsJson"></code></pre>
            </div>
        </div>
        <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 8px;">
            <button type="button" class="btn btn-secondary" onclick="closeAuditDetailModal()">Close</button>
        </div>
    </div>
</div>

<?php
$pageScripts = ['js/audit-log.js'];
require __DIR__ . '/partials/layout_bottom.php';
?>

