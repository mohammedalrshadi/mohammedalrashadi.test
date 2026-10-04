<?php
// ============================================================
// SUPPORT — Customer Support & Inquiries Inbox (Admin Studio)
// Requires: authenticated admin session
// Security: All dynamic output strictly HTML-escaped.
// ============================================================

require_once dirname(__DIR__) . '/api/auth/guard.php';

requireAdminPage('login.php');

$activeNav = 'support';
$pageTitle = 'Support Inbox';

require __DIR__ . '/partials/layout_top.php';
?>

<!-- =====================================================
     1. WORKSPACE HEADER & BREADCRUMB
====================================================== -->
<header class="admin-page-header">
    <div class="header-titles">
        <div class="breadcrumb" aria-label="breadcrumb">
            <a href="index.php">Admin Studio</a>
            <span class="breadcrumb-sep">/</span>
            <span style="color: var(--accent);">Support</span>
        </div>
        <h1 class="page-title">Support &amp; Inquiries Inbox</h1>
        <p class="page-description">Review, triage, and reply to client inquiries, technical questions, and feedback.</p>
    </div>

    <div class="header-actions">
        <button type="button" class="btn btn-secondary" onclick="loadSupportTickets()">
            <i class="fas fa-rotate" aria-hidden="true"></i>
            <span>Refresh</span>
        </button>
    </div>
</header>


<!-- =====================================================
     2. SUMMARY METRICS CARDS
====================================================== -->
<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 24px;">
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Total Messages</span>
            <i class="far fa-envelope stat-icon" aria-hidden="true"></i>
        </div>
        <div class="stat-value" id="statTotalTickets">0</div>
        <div class="stat-meta">All-time received tickets</div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">New / Pending</span>
            <i class="fas fa-bell stat-icon" style="color: var(--accent);" aria-hidden="true"></i>
        </div>
        <div class="stat-value" id="statNewTickets" style="color: var(--accent);">0</div>
        <div class="stat-meta">Awaiting review or triage</div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">In Progress</span>
            <i class="fas fa-spinner stat-icon" style="color: var(--warning);" aria-hidden="true"></i>
        </div>
        <div class="stat-value" id="statInProgressTickets">0</div>
        <div class="stat-meta">Currently being addressed</div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Resolved</span>
            <i class="fas fa-check-circle stat-icon" style="color: var(--success);" aria-hidden="true"></i>
        </div>
        <div class="stat-value" id="statResolvedTickets">0</div>
        <div class="stat-meta">Successfully addressed</div>
    </div>
</div>


<!-- =====================================================
     3. INBOX CONTAINER: TABS, FILTERS & TABLE
====================================================== -->
<div class="admin-card" style="margin-bottom: 28px;">
    
    <!-- Status Filter Tabs -->
    <div style="display: flex; gap: 8px; border-bottom: 1px solid var(--border-subtle); padding-bottom: 12px; margin-bottom: 16px; flex-wrap: wrap; align-items: center; justify-content: space-between;">
        <div class="table-tabs" role="tablist" style="display: flex; gap: 6px; flex-wrap: wrap;">
            <button type="button" class="tab-btn active" data-status="all" onclick="switchSupportTab('all')">
                All (<span id="tabCountAll">0</span>)
            </button>
            <button type="button" class="tab-btn" data-status="new" onclick="switchSupportTab('new')">
                New (<span id="tabCountNew">0</span>)
            </button>
            <button type="button" class="tab-btn" data-status="in_progress" onclick="switchSupportTab('in_progress')">
                In Progress (<span id="tabCountInProgress">0</span>)
            </button>
            <button type="button" class="tab-btn" data-status="resolved" onclick="switchSupportTab('resolved')">
                Resolved (<span id="tabCountResolved">0</span>)
            </button>
            <button type="button" class="tab-btn" data-status="archived" onclick="switchSupportTab('archived')">
                Archived (<span id="tabCountArchived">0</span>)
            </button>
        </div>

        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <!-- Category Filter -->
            <select id="supportCategoryFilter" class="form-control form-control-sm" style="width: 140px; font-size: 13px;" onchange="loadSupportTickets()">
                <option value="">All Categories</option>
                <option value="general">General</option>
                <option value="technical">Technical</option>
                <option value="product">Product</option>
                <option value="account">Account</option>
                <option value="feedback">Feedback</option>
            </select>

            <!-- Search Input -->
            <div style="position: relative; width: 220px;">
                <input type="search" id="supportSearchInput" class="form-control form-control-sm" placeholder="Search tickets…" style="padding-inline-start: 30px; font-size: 13px;" oninput="debounceSupportSearch()">
                <i class="fas fa-search" style="position: absolute; inset-inline-start: 10px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 11px; pointer-events: none;"></i>
            </div>
        </div>
    </div>

    <!-- Tickets Table -->
    <div class="table-responsive">
        <table class="admin-table" id="supportTicketsTable">
            <thead>
                <tr>
                    <th style="width: 220px;">Sender</th>
                    <th style="width: 120px;">Category</th>
                    <th>Subject &amp; Snippet</th>
                    <th style="width: 130px;">Status</th>
                    <th style="width: 140px;">Date</th>
                    <th style="width: 110px; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody id="supportTicketsTbody">
                <tr>
                    <td colspan="6" style="text-align: center; padding: 36px 16px; color: var(--text-muted);">
                        <i class="fas fa-spinner fa-spin" style="margin-right: 8px;"></i> Loading tickets…
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Table Pagination -->
    <div id="supportPagination" style="display: flex; align-items: center; justify-content: space-between; padding-top: 16px; margin-top: 12px; border-top: 1px solid var(--border-subtle); font-size: 13px; color: var(--text-secondary);">
        <span id="supportPaginationInfo">Showing 0 of 0 tickets</span>
        <div style="display: flex; gap: 8px;">
            <button type="button" class="btn btn-secondary btn-sm" id="supportPrevBtn" onclick="prevSupportPage()" disabled>Previous</button>
            <button type="button" class="btn btn-secondary btn-sm" id="supportNextBtn" onclick="nextSupportPage()" disabled>Next</button>
        </div>
    </div>

</div>


<!-- =====================================================
     4. TICKET DETAIL MODAL (DRAWER)
====================================================== -->
<div id="ticketDetailModal" class="modal" role="dialog" aria-label="Ticket Details" aria-modal="true" style="display: none;">
    <div class="modal-backdrop" onclick="closeTicketDetailModal()"></div>
    <div class="modal-dialog" style="max-width: 640px;">
        <div class="modal-header">
            <div style="display: flex; align-items: center; gap: 10px;">
                <h3 class="modal-title" id="detailModalTitle">Support Ticket</h3>
                <span id="detailStatusBadge" class="status-badge">New</span>
            </div>
            <button type="button" class="modal-close" onclick="closeTicketDetailModal()" aria-label="Close modal">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>

        <div class="modal-body" style="display: flex; flex-direction: column; gap: 16px;">
            <!-- Sender Metadata -->
            <div style="background-color: var(--bg-surface-elevated); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); padding: 14px 16px; display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; font-size: 13px;">
                <div>
                    <span style="color: var(--text-muted); display: block; font-size: 11px; text-transform: uppercase;">From</span>
                    <strong id="detailSenderName" style="color: var(--text-primary); font-size: 14px;">—</strong>
                </div>
                <div>
                    <span style="color: var(--text-muted); display: block; font-size: 11px; text-transform: uppercase;">Email</span>
                    <a id="detailSenderEmailLink" href="#" style="color: var(--accent); font-weight: 500; text-decoration: underline;" target="_blank" rel="noopener">—</a>
                </div>
                <div>
                    <span style="color: var(--text-muted); display: block; font-size: 11px; text-transform: uppercase;">Category</span>
                    <span id="detailCategoryBadge" style="font-weight: 600; text-transform: capitalize; color: var(--text-primary);">—</span>
                </div>
                <div>
                    <span style="color: var(--text-muted); display: block; font-size: 11px; text-transform: uppercase;">Received</span>
                    <span id="detailCreatedAt" style="color: var(--text-secondary); font-family: var(--font-mono); font-size: 12px;">—</span>
                </div>
            </div>

            <!-- Subject -->
            <div>
                <span style="color: var(--text-muted); font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em;">Subject</span>
                <h4 id="detailSubject" style="margin: 4px 0 0 0; font-size: 16px; font-weight: 600; color: var(--text-primary);">—</h4>
            </div>

            <!-- Message Body -->
            <div>
                <span style="color: var(--text-muted); font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; display: block; margin-bottom: 6px;">Message</span>
                <div id="detailMessageBody" style="background-color: var(--bg-surface-elevated); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); padding: 16px; font-size: 13.5px; line-height: 1.6; color: var(--text-primary); white-space: pre-wrap; word-break: break-word; max-height: 320px; overflow-y: auto;">—</div>
            </div>

            <!-- Client IP / Technical Details -->
            <div style="font-size: 11.5px; color: var(--text-muted); display: flex; gap: 16px;">
                <span>Origin IP: <code id="detailIpAddress" style="color: var(--text-secondary); font-family: var(--font-mono);">—</code></span>
                <span>Ticket ID: #<span id="detailTicketId">—</span></span>
            </div>

            <!-- Status Changer -->
            <div style="border-top: 1px solid var(--border-subtle); padding-top: 14px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <label for="detailStatusSelect" style="font-size: 12.5px; font-weight: 600; color: var(--text-secondary); margin: 0;">Update Status:</label>
                    <select id="detailStatusSelect" class="form-control form-control-sm" style="width: 140px; font-size: 13px;" onchange="updateTicketStatusFromModal()">
                        <option value="new">New</option>
                        <option value="in_progress">In Progress</option>
                        <option value="resolved">Resolved</option>
                        <option value="archived">Archived</option>
                    </select>
                </div>

                <div style="display: flex; gap: 8px;">
                    <a id="detailReplyBtn" href="#" class="btn btn-primary btn-sm">
                        <i class="fas fa-reply" aria-hidden="true"></i>
                        <span>Reply via Email</span>
                    </a>
                    <button type="button" class="btn btn-danger btn-sm" onclick="confirmDeleteCurrentTicket()">
                        <i class="fas fa-trash" aria-hidden="true"></i>
                        <span>Delete</span>
                    </button>
                </div>
            </div>
        </div>

        <div class="modal-footer" style="padding-top: 12px;">
            <button type="button" class="btn btn-secondary btn-sm" onclick="closeTicketDetailModal()">Close</button>
        </div>
    </div>
</div>

<?php
$pageScripts = ['js/support.js'];
require __DIR__ . '/partials/layout_bottom.php';
?>

