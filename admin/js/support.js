// ============================================================
// ADMIN — SUPPORT INBOX (support.php)
// Ticket triage, search, filtering, status updates, deletion
// Security: ALL dynamic fields strictly HTML-escaped.
// ============================================================

let currentStatus = 'all';
let currentCategory = '';
let searchQuery = '';
let currentPage = 1;
const pageLimit = 25;
let currentTickets = [];
let activeTicket = null;
let searchTimeout = null;

// ============================================================
// HELPER: ESCAPE HTML (XSS DEFENSE)
// ============================================================
function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// ============================================================
// INITIALIZATION
// ============================================================
document.addEventListener('DOMContentLoaded', async () => {
    if (typeof checkAdminAuthentication === 'function') {
        const authenticated = await checkAdminAuthentication();
        if (!authenticated) return;
    }

    // Read initial status from URL param
    const urlParams = new URLSearchParams(window.location.search);
    const initialStatus = urlParams.get('status');
    const validStatuses = ['all', 'new', 'in_progress', 'resolved', 'archived'];
    if (initialStatus && validStatuses.includes(initialStatus)) {
        currentStatus = initialStatus;
        updateTabButtonUI(currentStatus);
    }

    // Modal background and Esc key handlers
    const modal = document.getElementById('ticketDetailModal');
    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal || e.target.classList.contains('modal-backdrop')) {
                closeTicketDetailModal();
            }
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal.style.display !== 'none') {
                closeTicketDetailModal();
            }
        });
    }

    await loadSupportTickets();
});

// ============================================================
// LOAD TICKETS
// ============================================================
async function loadSupportTickets() {
    const tbody = document.getElementById('supportTicketsTbody');
    const categorySelect = document.getElementById('supportCategoryFilter');
    if (categorySelect) {
        currentCategory = categorySelect.value;
    }

    if (tbody) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" style="text-align: center; padding: 36px 16px; color: var(--text-muted);">
                    <i class="fas fa-spinner fa-spin" style="margin-right: 8px;"></i> Loading tickets…
                </td>
            </tr>
        `;
    }

    try {
        const params = new URLSearchParams({
            status: currentStatus,
            category: currentCategory,
            q: searchQuery,
            page: currentPage.toString(),
            limit: pageLimit.toString(),
        });

        const res = await fetch(`/api/support/list.php?${params.toString()}`);
        if (!res.ok) {
            let errorMsg = `Server returned ${res.status}`;
            try {
                const errorData = await res.json();
                if (errorData && errorData.message) errorMsg = errorData.message;
            } catch (e) {}
            throw new Error(errorMsg);
        }

        const data = await res.json();
        if (!data.success) {
            throw new Error(data.message || 'Failed to fetch tickets.');
        }

        currentTickets = data.messages || [];

        // Update counts & metrics
        updateMetricsAndTabs(data.counts || {});

        // Render table
        renderTicketsTable(currentTickets);

        // Update pagination
        updatePagination(data.total || 0, data.page || 1, data.total_pages || 1);

    } catch (err) {
        console.error('Failed to load support tickets:', err);
        if (tbody) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" style="text-align: center; padding: 36px 16px; color: var(--color-error, #FF7A7A);">
                        <i class="fas fa-triangle-exclamation" style="margin-right: 8px;"></i>
                        Failed to load tickets: ${escapeHtml(err.message)}
                    </td>
                </tr>
            `;
        }
        if (typeof showToast === 'function') {
            showToast('Error loading support tickets: ' + err.message, 'error');
        }
    }
}

// ============================================================
// METRICS & TABS
// ============================================================
function updateMetricsAndTabs(counts) {
    const allCount = counts.all || 0;
    const newCount = counts.new || 0;
    const inProgressCount = counts.in_progress || 0;
    const resolvedCount = counts.resolved || 0;
    const archivedCount = counts.archived || 0;

    // Stat cards
    const statTotal = document.getElementById('statTotalTickets');
    if (statTotal) statTotal.textContent = allCount;

    const statNew = document.getElementById('statNewTickets');
    if (statNew) statNew.textContent = newCount;

    const statProg = document.getElementById('statInProgressTickets');
    if (statProg) statProg.textContent = inProgressCount;

    const statRes = document.getElementById('statResolvedTickets');
    if (statRes) statRes.textContent = resolvedCount;

    // Tab badges
    const tabAll = document.getElementById('tabCountAll');
    if (tabAll) tabAll.textContent = allCount;

    const tabNew = document.getElementById('tabCountNew');
    if (tabNew) tabNew.textContent = newCount;

    const tabProg = document.getElementById('tabCountInProgress');
    if (tabProg) tabProg.textContent = inProgressCount;

    const tabRes = document.getElementById('tabCountResolved');
    if (tabRes) tabRes.textContent = resolvedCount;

    const tabArch = document.getElementById('tabCountArchived');
    if (tabArch) tabArch.textContent = archivedCount;

    // Sidebar badge
    const navBadge = document.getElementById('supportNavBadge');
    if (navBadge) {
        if (newCount > 0) {
            navBadge.textContent = newCount;
            navBadge.style.display = 'inline-block';
        } else {
            navBadge.style.display = 'none';
        }
    }
}

function updateTabButtonUI(status) {
    document.querySelectorAll('.table-tabs .tab-btn').forEach(btn => {
        if (btn.getAttribute('data-status') === status) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });
}

function switchSupportTab(status) {
    currentStatus = status;
    currentPage = 1;
    updateTabButtonUI(status);

    const url = new URL(window.location.href);
    if (status === 'all') {
        url.searchParams.delete('status');
    } else {
        url.searchParams.set('status', status);
    }
    window.history.replaceState({}, '', url.toString());

    loadSupportTickets();
}

function debounceSupportSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        const input = document.getElementById('supportSearchInput');
        searchQuery = input ? input.value.trim() : '';
        currentPage = 1;
        loadSupportTickets();
    }, 300);
}

// ============================================================
// RENDER TABLE ROWS (ALL OUTPUT STRICTLY ESCAPED)
// ============================================================
function renderTicketsTable(tickets) {
    const tbody = document.getElementById('supportTicketsTbody');
    if (!tbody) return;

    if (!tickets || tickets.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" style="text-align: center; padding: 48px 16px; color: var(--text-muted);">
                    <i class="far fa-folder-open" style="font-size: 28px; display: block; margin-bottom: 8px; opacity: 0.5;"></i>
                    No support messages found matching this criteria.
                </td>
            </tr>
        `;
        return;
    }

    const rows = tickets.map(t => {
        const id = Number(t.id);
        const name = escapeHtml(t.name);
        const email = escapeHtml(t.email);
        const category = escapeHtml(t.category);
        const subject = escapeHtml(t.subject);
        const messageText = escapeHtml(t.message || '');
        const snippet = messageText.length > 70 ? (messageText.slice(0, 70) + '…') : messageText;
        const status = escapeHtml(t.status);
        const createdAt = escapeHtml(t.created_at ? t.created_at.slice(0, 16).replace('T', ' ') : '—');

        let statusClass = 'status-pending';
        let statusLabel = 'New';
        if (t.status === 'in_progress') {
            statusClass = 'status-draft';
            statusLabel = 'In Progress';
        } else if (t.status === 'resolved') {
            statusClass = 'status-published';
            statusLabel = 'Resolved';
        } else if (t.status === 'archived') {
            statusClass = 'status-hidden';
            statusLabel = 'Archived';
        }

        const isUnread = t.status === 'new';

        return `
            <tr style="${isUnread ? 'background-color: var(--bg-surface-container, rgba(255,255,255,0.02)); font-weight: 500;' : ''}">
                <td>
                    <div style="display: flex; flex-direction: column;">
                        <span style="color: var(--text-primary); font-weight: 600;">${name}</span>
                        <span style="color: var(--text-muted); font-size: 12px; font-family: var(--font-mono);">${email}</span>
                    </div>
                </td>
                <td>
                    <span style="display: inline-block; padding: 2px 8px; border-radius: var(--radius-xs); background: var(--bg-surface-elevated); border: 1px solid var(--border-subtle); font-size: 11.5px; text-transform: capitalize; color: var(--text-secondary);">
                        ${category}
                    </span>
                </td>
                <td>
                    <div style="display: flex; flex-direction: column; max-width: 380px;">
                        <span style="color: var(--text-primary); font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                            ${isUnread ? '<span style="display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: var(--accent); margin-right: 6px; vertical-align: middle;"></span>' : ''}
                            ${subject}
                        </span>
                        <span style="color: var(--text-muted); font-size: 12px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin-top: 2px;">
                            ${snippet}
                        </span>
                    </div>
                </td>
                <td>
                    <span class="status-badge ${statusClass}">
                        ${statusLabel}
                    </span>
                </td>
                <td>
                    <span style="font-size: 12px; color: var(--text-secondary); font-family: var(--font-mono);">
                        ${createdAt}
                    </span>
                </td>
                <td style="text-align: right;">
                    <div style="display: inline-flex; gap: 6px;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="viewTicketDetail(${id})" title="View Ticket Details">
                            <i class="fas fa-eye" aria-hidden="true"></i>
                        </button>
                        <button type="button" class="btn btn-danger btn-sm" onclick="confirmDeleteTicket(${id})" title="Delete Message">
                            <i class="fas fa-trash" aria-hidden="true"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = rows.join('');
}

// ============================================================
// PAGINATION
// ============================================================
function updatePagination(total, page, totalPages) {
    const info = document.getElementById('supportPaginationInfo');
    const prevBtn = document.getElementById('supportPrevBtn');
    const nextBtn = document.getElementById('supportNextBtn');

    if (info) {
        if (total === 0) {
            info.textContent = 'Showing 0 of 0 tickets';
        } else {
            const start = ((page - 1) * pageLimit) + 1;
            const end = Math.min(page * pageLimit, total);
            info.textContent = `Showing ${start}–${end} of ${total} tickets (Page ${page} of ${totalPages})`;
        }
    }

    if (prevBtn) prevBtn.disabled = (page <= 1);
    if (nextBtn) nextBtn.disabled = (page >= totalPages);
}

function prevSupportPage() {
    if (currentPage > 1) {
        currentPage--;
        loadSupportTickets();
    }
}

function nextSupportPage() {
    currentPage++;
    loadSupportTickets();
}

// ============================================================
// DETAIL MODAL (ESCAPED DATA DISPLAY)
// ============================================================
function viewTicketDetail(id) {
    const ticket = currentTickets.find(t => Number(t.id) === Number(id));
    if (!ticket) return;

    activeTicket = ticket;

    // Populate modal fields
    const titleEl = document.getElementById('detailModalTitle');
    if (titleEl) titleEl.textContent = `Support Ticket #${ticket.id}`;

    const badgeEl = document.getElementById('detailStatusBadge');
    if (badgeEl) {
        badgeEl.className = 'status-badge';
        if (ticket.status === 'new') {
            badgeEl.classList.add('status-pending');
            badgeEl.textContent = 'New';
        } else if (ticket.status === 'in_progress') {
            badgeEl.classList.add('status-draft');
            badgeEl.textContent = 'In Progress';
        } else if (ticket.status === 'resolved') {
            badgeEl.classList.add('status-published');
            badgeEl.textContent = 'Resolved';
        } else if (ticket.status === 'archived') {
            badgeEl.classList.add('status-hidden');
            badgeEl.textContent = 'Archived';
        }
    }

    const nameEl = document.getElementById('detailSenderName');
    if (nameEl) nameEl.textContent = ticket.name || '—';

    const emailLink = document.getElementById('detailSenderEmailLink');
    if (emailLink) {
        emailLink.textContent = ticket.email || '—';
        emailLink.href = `mailto:${encodeURIComponent(ticket.email || '')}`;
    }

    const catEl = document.getElementById('detailCategoryBadge');
    if (catEl) catEl.textContent = ticket.category || 'General';

    const dateEl = document.getElementById('detailCreatedAt');
    if (dateEl) dateEl.textContent = ticket.created_at ? ticket.created_at.replace('T', ' ') : '—';

    const subjEl = document.getElementById('detailSubject');
    if (subjEl) subjEl.textContent = ticket.subject || '—';

    const msgEl = document.getElementById('detailMessageBody');
    if (msgEl) msgEl.textContent = ticket.message || '';

    const ipEl = document.getElementById('detailIpAddress');
    if (ipEl) ipEl.textContent = ticket.ip_address || '—';

    const ticketIdEl = document.getElementById('detailTicketId');
    if (ticketIdEl) ticketIdEl.textContent = ticket.id;

    const statusSelect = document.getElementById('detailStatusSelect');
    if (statusSelect) statusSelect.value = ticket.status;

    const replyBtn = document.getElementById('detailReplyBtn');
    if (replyBtn) {
        const replySubj = 'Re: ' + (ticket.subject || 'Support Inquiry');
        replyBtn.href = `mailto:${encodeURIComponent(ticket.email || '')}?subject=${encodeURIComponent(replySubj)}`;
    }

    const modal = document.getElementById('ticketDetailModal');
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

function closeTicketDetailModal() {
    const modal = document.getElementById('ticketDetailModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
    activeTicket = null;
}

// ============================================================
// UPDATE TICKET STATUS
// ============================================================
async function updateTicketStatusFromModal() {
    if (!activeTicket) return;
    const statusSelect = document.getElementById('detailStatusSelect');
    if (!statusSelect) return;

    const newStatus = statusSelect.value;
    await updateTicketStatus(activeTicket.id, newStatus);
}

async function updateTicketStatus(id, newStatus) {
    const metaCsrf = document.querySelector('meta[name="csrf-token"]');
    const token = metaCsrf ? metaCsrf.getAttribute('content') : (typeof csrfToken !== 'undefined' ? csrfToken : '');

    try {
        const res = await fetch('/api/support/update.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': token,
            },
            body: JSON.stringify({ id: Number(id), status: newStatus }),
        });

        const data = await res.json();
        if (!res.ok || !data.success) {
            throw new Error(data.message || 'Failed to update status.');
        }

        if (typeof showToast === 'function') {
            showToast('Ticket status updated.', 'success');
        }

        // If modal open, update badge
        if (activeTicket && Number(activeTicket.id) === Number(id)) {
            activeTicket.status = newStatus;
            const badgeEl = document.getElementById('detailStatusBadge');
            if (badgeEl) {
                badgeEl.className = 'status-badge';
                if (newStatus === 'new') badgeEl.classList.add('status-pending'), badgeEl.textContent = 'New';
                else if (newStatus === 'in_progress') badgeEl.classList.add('status-draft'), badgeEl.textContent = 'In Progress';
                else if (newStatus === 'resolved') badgeEl.classList.add('status-published'), badgeEl.textContent = 'Resolved';
                else if (newStatus === 'archived') badgeEl.classList.add('status-hidden'), badgeEl.textContent = 'Archived';
            }
        }

        await loadSupportTickets();

    } catch (err) {
        console.error('Status update failed:', err);
        if (typeof showToast === 'function') {
            showToast('Failed to update status: ' + err.message, 'error');
        }
    }
}

// ============================================================
// DELETE TICKET
// ============================================================
function confirmDeleteCurrentTicket() {
    if (!activeTicket) return;
    confirmDeleteTicket(activeTicket.id);
}

async function confirmDeleteTicket(id) {
    const msg = 'Are you sure you want to delete this support message? This action cannot be undone.';
    if (!window.confirm(msg)) return;

    const metaCsrf = document.querySelector('meta[name="csrf-token"]');
    const token = metaCsrf ? metaCsrf.getAttribute('content') : (typeof csrfToken !== 'undefined' ? csrfToken : '');

    try {
        const res = await fetch('/api/support/delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': token,
            },
            body: JSON.stringify({ id: Number(id) }),
        });

        const data = await res.json();
        if (!res.ok || !data.success) {
            throw new Error(data.message || 'Failed to delete message.');
        }

        if (typeof showToast === 'function') {
            showToast('Support message deleted.', 'success');
        }

        if (activeTicket && Number(activeTicket.id) === Number(id)) {
            closeTicketDetailModal();
        }

        await loadSupportTickets();

    } catch (err) {
        console.error('Deletion failed:', err);
        if (typeof showToast === 'function') {
            showToast('Failed to delete message: ' + err.message, 'error');
        }
    }
}

