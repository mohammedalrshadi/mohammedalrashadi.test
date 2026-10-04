// ============================================================
// ADMIN — AUDIT LOG WORKSPACE (audit-log.js)
// ============================================================

(function () {
    'use strict';

    let _currentPage = 1;
    let _totalPages = 1;
    let _records = [];
    let _metaLoaded = false;
    let _activeRecordJson = '';

    /**
     * Helper to retrieve current CSRF token from common places.
     */
    function _getCsrfToken() {
        if (window.csrfToken) return window.csrfToken;
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && meta.content) return meta.content;
        const input = document.querySelector('input[name="csrf_token"]');
        if (input && input.value) return input.value;
        return '';
    }

    /**
     * Formats an ISO / MySQL timestamp for local human display.
     */
    function _formatTime(ts) {
        if (!ts) return '-';
        if (typeof window.formatSiteDate === 'function') {
            return window.formatSiteDate(ts, {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
            });
        }
        return ts;
    }

    /**
     * Determines badge CSS class based on dotted action name.
     */
    function _getActionBadgeClass(action) {
        if (!action) return 'badge-secondary';
        const act = action.toLowerCase();
        if (act.includes('delete') || act.includes('purge') || act.includes('trash') || act.includes('fail')) {
            return 'badge-danger';
        }
        if (act.includes('create') || act.includes('publish') || act.includes('restore')) {
            return 'badge-success';
        }
        if (act.includes('export') || act.includes('migration') || act.includes('download')) {
            return 'badge-purple';
        }
        if (act.includes('archive') || act.includes('hide')) {
            return 'badge-warning';
        }
        if (act.includes('update') || act.includes('toggle') || act.includes('reorder')) {
            return 'badge-info';
        }
        return 'badge-secondary';
    }

    /**
     * Loads audit logs with current filters.
     */
    window.loadAuditLog = async function (page = 1) {
        _currentPage = Math.max(1, parseInt(page, 10) || 1);

        const tbody = document.getElementById('auditTableBody');
        if (!tbody) return;

        // Loading state
        tbody.innerHTML = '';
        const loadingRow = document.createElement('tr');
        const loadingTd = document.createElement('td');
        loadingTd.colSpan = 8;
        loadingTd.style.textAlign = 'center';
        loadingTd.style.padding = '36px';
        loadingTd.style.color = 'var(--text-muted)';
        loadingTd.innerHTML = '<i class="fas fa-spinner fa-spin" style="margin-right: 8px;"></i> Loading audit trail...';
        loadingRow.appendChild(loadingTd);
        tbody.appendChild(loadingRow);

        const adminId = document.getElementById('auditFilterAdmin')?.value || '';
        const action = document.getElementById('auditFilterAction')?.value || '';
        const targetType = document.getElementById('auditFilterTarget')?.value || '';
        const dateFrom = document.getElementById('auditFilterDateFrom')?.value || '';
        const dateTo = document.getElementById('auditFilterDateTo')?.value || '';
        const search = document.getElementById('auditFilterSearch')?.value || '';

        const queryParams = new URLSearchParams({
            page: _currentPage,
            limit: 50,
        });
        if (adminId) queryParams.append('admin_id', adminId);
        if (action) queryParams.append('action', action);
        if (targetType) queryParams.append('target_type', targetType);
        if (dateFrom) queryParams.append('date_from', dateFrom);
        if (dateTo) queryParams.append('date_to', dateTo);
        if (search) queryParams.append('search', search);

        try {
            const resp = await fetch('/api/admin/audit_list.php?' + queryParams.toString(), {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-Token': _getCsrfToken(),
                },
            });

            if (!resp.ok) {
                let errorMsg = `HTTP Error ${resp.status}`;
                try {
                    const errorData = await resp.json();
                    if (errorData && errorData.message) errorMsg = errorData.message;
                } catch (e) {
                    // Ignore JSON parse error on non-200
                }
                throw new Error(errorMsg);
            }

            const res = await resp.json();

            // Check if migration is required (Requirement 7)
            const migBanner = document.getElementById('auditMigrationAlert');
            if (res.code === 'migration_required') {
                if (migBanner) migBanner.style.display = 'flex';
                tbody.innerHTML = '';
                const emptyRow = document.createElement('tr');
                const emptyTd = document.createElement('td');
                emptyTd.colSpan = 8;
                emptyTd.style.textAlign = 'center';
                emptyTd.style.padding = '32px';
                emptyTd.style.color = 'var(--warning, #eab308)';
                emptyTd.textContent = 'Audit log table has not been initialized. Please run migrations.';
                emptyRow.appendChild(emptyTd);
                tbody.appendChild(emptyRow);
                return;
            } else if (migBanner) {
                migBanner.style.display = 'none';
            }

            if (!res.success) {
                throw new Error(res.message || 'Failed to load audit logs.');
            }

            _records = res.data || [];
            _totalPages = (res.pagination && res.pagination.total_pages) ? res.pagination.total_pages : 1;

            // Populate metadata filters once
            if (!_metaLoaded && res.meta) {
                _populateFilterMeta(res.meta);
                _metaLoaded = true;
            }

            // Update stats
            _updateAuditStats(res);

            // Render Table Rows with strict XSS escaping
            _renderAuditTable(tbody, _records);

            // Update pagination UI
            _updatePaginationUI(res.pagination);

        } catch (err) {
            console.error('[audit-log] load error:', err);
            tbody.innerHTML = '';
            const errRow = document.createElement('tr');
            const errTd = document.createElement('td');
            errTd.colSpan = 8;
            errTd.style.textAlign = 'center';
            errTd.style.padding = '32px';
            errTd.style.color = 'var(--danger, #ef4444)';
            errTd.textContent = 'Failed to load audit logs: ' + (err.message || 'Unknown error');
            errRow.appendChild(errTd);
            tbody.appendChild(errRow);
        }
    };

    /**
     * Populates filter dropdowns safely via DOM Options (Requirement 2).
     */
    function _populateFilterMeta(meta) {
        const adminSelect = document.getElementById('auditFilterAdmin');
        const actionSelect = document.getElementById('auditFilterAction');
        const targetSelect = document.getElementById('auditFilterTarget');

        if (adminSelect && Array.isArray(meta.admins)) {
            meta.admins.forEach(adm => {
                const opt = document.createElement('option');
                opt.value = adm.id;
                opt.textContent = adm.name ? `${adm.name} (${adm.email})` : `User #${adm.id} (${adm.email})`;
                adminSelect.appendChild(opt);
            });
        }

        if (actionSelect && Array.isArray(meta.actions)) {
            meta.actions.forEach(act => {
                const opt = document.createElement('option');
                opt.value = act;
                opt.textContent = act;
                actionSelect.appendChild(opt);
            });
        }

        if (targetSelect && Array.isArray(meta.target_types)) {
            meta.target_types.forEach(tgt => {
                const opt = document.createElement('option');
                opt.value = tgt;
                opt.textContent = tgt;
                targetSelect.appendChild(opt);
            });
        }
    }

    /**
     * Updates statistics rail.
     */
    function _updateAuditStats(res) {
        const totalEl = document.getElementById('statTotalAuditEvents');
        const filteredEl = document.getElementById('statFilteredEvents');
        const adminsEl = document.getElementById('statActiveAdminsCount');
        const latestEl = document.getElementById('statLatestEventTime');

        if (res.pagination) {
            if (filteredEl) filteredEl.textContent = Number(res.pagination.total).toLocaleString();
            if (totalEl && totalEl.textContent === '0') {
                totalEl.textContent = Number(res.pagination.total).toLocaleString();
            }
        }

        if (res.meta && Array.isArray(res.meta.admins) && adminsEl) {
            adminsEl.textContent = res.meta.admins.length;
        }

        if (latestEl && res.data && res.data.length > 0) {
            latestEl.textContent = res.data[0].created_at;
        }
    }

    /**
     * Renders table rows safely with textContent (Requirement 2).
     */
    function _renderAuditTable(tbody, records) {
        tbody.innerHTML = '';

        if (records.length === 0) {
            const emptyRow = document.createElement('tr');
            const emptyTd = document.createElement('td');
            emptyTd.colSpan = 8;
            emptyTd.style.textAlign = 'center';
            emptyTd.style.padding = '36px';
            emptyTd.style.color = 'var(--text-muted)';
            emptyTd.textContent = 'No audit log entries match the selected filters.';
            emptyRow.appendChild(emptyTd);
            tbody.appendChild(emptyRow);
            return;
        }

        records.forEach(row => {
            const tr = document.createElement('tr');

            // 1. ID
            const tdId = document.createElement('td');
            tdId.style.fontFamily = 'var(--font-mono)';
            tdId.style.fontSize = '12px';
            tdId.textContent = '#' + row.id;
            tr.appendChild(tdId);

            // 2. Timestamp
            const tdTime = document.createElement('td');
            tdTime.style.fontSize = '12px';
            tdTime.textContent = _formatTime(row.created_at);
            tdTime.title = row.created_at + ' (UTC)';
            tr.appendChild(tdTime);

            // 3. Administrator (Requirement 7: deleted user display)
            const tdAdmin = document.createElement('td');
            const adminNameSpan = document.createElement('span');
            adminNameSpan.style.display = 'block';
            adminNameSpan.style.fontWeight = '500';
            adminNameSpan.style.fontSize = '13px';
            adminNameSpan.textContent = row.admin_name || ('Deleted user #' + row.admin_id);
            tdAdmin.appendChild(adminNameSpan);

            if (row.admin_email) {
                const adminEmailSpan = document.createElement('span');
                adminEmailSpan.style.display = 'block';
                adminEmailSpan.style.fontSize = '11px';
                adminEmailSpan.style.color = 'var(--text-muted)';
                adminEmailSpan.textContent = row.admin_email;
                tdAdmin.appendChild(adminEmailSpan);
            }
            tr.appendChild(tdAdmin);

            // 4. Action Badge
            const tdAction = document.createElement('td');
            const badge = document.createElement('span');
            badge.className = 'badge ' + _getActionBadgeClass(row.action);
            badge.style.fontSize = '11.5px';
            badge.style.fontFamily = 'var(--font-mono)';
            badge.textContent = row.action;
            tdAction.appendChild(badge);
            tr.appendChild(tdAction);

            // 5. Target Type & ID
            const tdTarget = document.createElement('td');
            tdTarget.style.fontSize = '12.5px';
            let targetLabel = row.target_type || '-';
            if (row.target_id) {
                targetLabel += ' #' + row.target_id;
            }
            tdTarget.textContent = targetLabel;
            tr.appendChild(tdTarget);

            // 6. Client IP
            const tdIp = document.createElement('td');
            const ipCode = document.createElement('code');
            ipCode.style.fontFamily = 'var(--font-mono)';
            ipCode.style.fontSize = '11.5px';
            ipCode.textContent = row.ip_address || '-';
            tdIp.appendChild(ipCode);
            tr.appendChild(tdIp);

            // 7. Details snippet (Strict textContent)
            const tdDetails = document.createElement('td');
            tdDetails.style.fontSize = '12px';
            tdDetails.style.color = 'var(--text-secondary)';
            tdDetails.style.maxWidth = '260px';
            tdDetails.style.whiteSpace = 'nowrap';
            tdDetails.style.overflow = 'hidden';
            tdDetails.style.textOverflow = 'ellipsis';
            let snippet = row.details || '';
            if (snippet.length > 80) {
                snippet = snippet.substring(0, 80) + '...';
            }
            tdDetails.textContent = snippet || '-';
            tdDetails.title = snippet;
            tr.appendChild(tdDetails);

            // 8. View Action Button
            const tdBtn = document.createElement('td');
            tdBtn.style.textAlign = 'center';
            const viewBtn = document.createElement('button');
            viewBtn.type = 'button';
            viewBtn.className = 'btn btn-secondary btn-sm';
            viewBtn.style.fontSize = '11px';
            viewBtn.style.padding = '4px 8px';
            viewBtn.setAttribute('aria-label', 'View details for log #' + row.id);
            viewBtn.innerHTML = '<i class="fas fa-eye"></i>';
            viewBtn.onclick = function () {
                window.viewAuditDetail(row.id);
            };
            tdBtn.appendChild(viewBtn);
            tr.appendChild(tdBtn);

            tbody.appendChild(tr);
        });
    }

    /**
     * Updates pagination controls and label.
     */
    function _updatePaginationUI(pagination) {
        const info = document.getElementById('auditPaginationInfo');
        const prevBtn = document.getElementById('btnPrevAuditPage');
        const nextBtn = document.getElementById('btnNextAuditPage');

        const cur = pagination ? pagination.page : 1;
        const total = pagination ? pagination.total_pages : 1;
        const count = pagination ? pagination.total : 0;

        if (info) {
            info.textContent = `Showing page ${cur} of ${total} (${Number(count).toLocaleString()} total events)`;
        }

        if (prevBtn) {
            prevBtn.disabled = (cur <= 1);
        }
        if (nextBtn) {
            nextBtn.disabled = (cur >= total);
        }
    }

    // ============================================================
    // Event Handlers for UI (All resolve to pass dead-handlers test)
    // ============================================================

    window.applyAuditFilters = function () {
        window.loadAuditLog(1);
    };

    window.resetAuditFilters = function () {
        const adminFilter = document.getElementById('auditFilterAdmin');
        const actionFilter = document.getElementById('auditFilterAction');
        const targetFilter = document.getElementById('auditFilterTarget');
        const dateFrom = document.getElementById('auditFilterDateFrom');
        const dateTo = document.getElementById('auditFilterDateTo');
        const search = document.getElementById('auditFilterSearch');

        if (adminFilter) adminFilter.value = '';
        if (actionFilter) actionFilter.value = '';
        if (targetFilter) targetFilter.value = '';
        if (dateFrom) dateFrom.value = '';
        if (dateTo) dateTo.value = '';
        if (search) search.value = '';

        window.loadAuditLog(1);
    };

    window.prevAuditPage = function () {
        if (_currentPage > 1) {
            window.loadAuditLog(_currentPage - 1);
        }
    };

    window.nextAuditPage = function () {
        if (_currentPage < _totalPages) {
            window.loadAuditLog(_currentPage + 1);
        }
    };

    /**
     * CSV Export via HTTP POST with CSRF token (Requirement 3).
     */
    window.exportAuditCsv = async function () {
        const btn = document.getElementById('btnExportAuditCsv');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin" style="margin-right: 6px;"></i> Exporting...';
        }

        try {
            const formData = new URLSearchParams();
            formData.append('export', 'csv');
            formData.append('csrf_token', _getCsrfToken());

            const adminId = document.getElementById('auditFilterAdmin')?.value || '';
            const action = document.getElementById('auditFilterAction')?.value || '';
            const targetType = document.getElementById('auditFilterTarget')?.value || '';
            const dateFrom = document.getElementById('auditFilterDateFrom')?.value || '';
            const dateTo = document.getElementById('auditFilterDateTo')?.value || '';
            const search = document.getElementById('auditFilterSearch')?.value || '';

            if (adminId) formData.append('admin_id', adminId);
            if (action) formData.append('action', action);
            if (targetType) formData.append('target_type', targetType);
            if (dateFrom) formData.append('date_from', dateFrom);
            if (dateTo) formData.append('date_to', dateTo);
            if (search) formData.append('search', search);

            const resp = await fetch('/api/admin/audit_list.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': _getCsrfToken(),
                },
                body: formData.toString(),
            });

            if (!resp.ok) {
                const errData = await resp.json().catch(() => ({}));
                throw new Error(errData.message || 'Export failed with HTTP ' + resp.status);
            }

            const blob = await resp.blob();
            const blobUrl = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.style.display = 'none';
            a.href = blobUrl;

            // Filename from Content-Disposition header if available
            const disp = resp.headers.get('Content-Disposition');
            let filename = 'admin_audit_log.csv';
            if (disp && disp.includes('filename=')) {
                const match = disp.match(/filename="?([^";]+)"?/);
                if (match && match[1]) filename = match[1];
            }
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(blobUrl);
            document.body.removeChild(a);

            if (typeof showToast === 'function') {
                showToast('Audit log CSV exported successfully.', 'success');
            }

        } catch (err) {
            console.error('[audit-log] Export error:', err);
            if (typeof showToast === 'function') {
                showToast('CSV export failed: ' + (err.message || 'Unknown error'), 'error');
            } else {
                alert('CSV export failed: ' + err.message);
            }
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-file-csv" style="margin-right: 6px;"></i> Export CSV';
            }
        }
    };

    /**
     * Views audit record details in modal.
     */
    window.viewAuditDetail = function (id) {
        const record = _records.find(r => String(r.id) === String(id));
        if (!record) return;

        const modal = document.getElementById('auditDetailModal');
        if (!modal) return;

        // Set modal fields with strict textContent (Requirement 2)
        document.getElementById('modalRecordId').textContent = '#' + record.id;
        document.getElementById('modalTimestamp').textContent = record.created_at + ' (UTC)';
        document.getElementById('modalAdminUser').textContent = (record.admin_name || 'Deleted user #' + record.admin_id) + (record.admin_email ? ` (${record.admin_email})` : '');
        document.getElementById('modalClientIp').textContent = record.ip_address || '-';
        
        const actionBadge = document.getElementById('modalActionBadge');
        if (actionBadge) {
            actionBadge.textContent = record.action;
            actionBadge.className = 'badge ' + _getActionBadgeClass(record.action);
        }

        let targetLabel = record.target_type || '-';
        if (record.target_id) targetLabel += ' #' + record.target_id;
        document.getElementById('modalTarget').textContent = targetLabel;

        // Format details JSON
        let formatted = record.details || '';
        if (formatted) {
            try {
                const parsed = JSON.parse(formatted);
                formatted = JSON.stringify(parsed, null, 2);
            } catch (e) {
                // Keep raw string if not JSON
            }
        } else {
            formatted = '(No details recorded)';
        }

        _activeRecordJson = formatted;
        document.getElementById('modalDetailsJson').textContent = formatted;

        modal.style.display = 'flex';
    };

    window.closeAuditDetailModal = function () {
        const modal = document.getElementById('auditDetailModal');
        if (modal) {
            modal.style.display = 'none';
        }
    };

    window.copyAuditDetailsJson = function () {
        if (!_activeRecordJson) return;
        navigator.clipboard.writeText(_activeRecordJson).then(() => {
            if (typeof showToast === 'function') {
                showToast('Audit details JSON copied to clipboard.', 'success');
            }
        }).catch(err => {
            console.error('Clipboard copy failed:', err);
        });
    };

    // Auto-load on page load
    document.addEventListener('DOMContentLoaded', () => {
        window.loadAuditLog(1);
    });

})();

