// ============================================================
// ADMIN — DATABASE BACKUPS (backups.php)
// Handles: list, create, download, delete
// Security: ALL dynamic content escaped. All mutations POST + CSRF.
// ============================================================

// ============================================================
// INITIALIZATION
// ============================================================
document.addEventListener('DOMContentLoaded', async () => {
    if (typeof checkAdminAuthentication === 'function') {
        const authenticated = await checkAdminAuthentication();
        if (!authenticated) return;
    }
    await loadBackups();
});

// ============================================================
// LOAD BACKUPS — GET list via POST action=list
// ============================================================
async function loadBackups() {
    const tbody = document.getElementById('backupTableBody');
    if (tbody) {
        tbody.innerHTML = `
            <tr>
                <td colspan="4" style="text-align: center; padding: 36px 16px; color: var(--text-muted);">
                    <i class="fas fa-spinner fa-spin" style="margin-right: 8px;"></i> Loading backups...
                </td>
            </tr>`;
    }

    try {
        const resp = await fetch('/api/admin/backup_action.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
            },
            body: JSON.stringify({ action: 'list' }),
        });

        const data = await resp.json();

        if (!data.success) {
            _backupSetTableError(tbody, data.message || 'Failed to load backups.');
            return;
        }

        _backupRenderStats(data.files, data.last_run);
        _backupRenderTable(tbody, data.files);

    } catch (err) {
        console.error('[backups] loadBackups error:', err);
        _backupSetTableError(tbody, 'Network error. Please refresh.');
    }
}

// ============================================================
// RUN BACKUP NOW
// ============================================================
async function runBackupNow() {
    const btn = document.getElementById('backupNowBtn');

    if (!confirm('Create a new database backup now? This may take up to a minute for large databases.')) {
        return;
    }

    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> <span>Backing up...</span>';
    }

    try {
        const resp = await fetch('/api/admin/backup_action.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
            },
            body: JSON.stringify({ action: 'create' }),
        });

        const data = await resp.json();

        if (data.success) {
            if (typeof showToast === 'function') {
                showToast(`Backup created: ${escSafe(data.filename)} (${escSafe(data.size_human)})`, 'success');
            }
            await loadBackups();
        } else {
            if (typeof showToast === 'function') {
                showToast('Backup failed: ' + (data.message || 'Unknown error'), 'error');
            }
        }

    } catch (err) {
        console.error('[backups] runBackupNow error:', err);
        if (typeof showToast === 'function') {
            showToast('Network error during backup.', 'error');
        }
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-database"></i> <span>Backup Now</span>';
        }
    }
}

// ============================================================
// DOWNLOAD BACKUP
// Uses a hidden form POST so the browser handles the file stream
// correctly (fetch() can't trigger Save As reliably for large files).
// The form includes the CSRF token as a hidden field, and the endpoint
// reads it from $_POST since it's form-encoded.
// ============================================================
function downloadBackup(filename) {
    // Validate filename client-side (belt-and-suspenders; server enforces too)
    if (!/^db-\d{8}-\d{6}-[0-9a-f]{8}\.sql\.gz$/.test(filename)) {
        if (typeof showToast === 'function') showToast('Invalid filename.', 'error');
        return;
    }

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/api/admin/backup_download.php';
    form.style.display = 'none';

    const csrfField = document.createElement('input');
    csrfField.type = 'hidden';
    csrfField.name = 'X-CSRF-Token'; // picked up by the endpoint via $_POST fallback
    csrfField.value = csrfToken;
    form.appendChild(csrfField);

    // The endpoint also checks the X-CSRF-Token header; for form POSTs we
    // inject the token as a custom request header by using fetch() + blob
    // for the download instead — see note below.
    const filenameField = document.createElement('input');
    filenameField.type = 'hidden';
    filenameField.name = 'filename';
    filenameField.value = filename;
    form.appendChild(filenameField);

    // For CSRF on form submit we use the fetch+blob approach to preserve headers
    document.body.appendChild(form);
    document.body.removeChild(form);

    // Fetch-based download (preserves X-CSRF-Token header)
    _backupFetchDownload(filename);
}

async function _backupFetchDownload(filename) {
    const btn = document.querySelector(`button[data-download="${CSS.escape(filename)}"]`);
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i>';
    }

    try {
        const resp = await fetch('/api/admin/backup_download.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
            },
            body: JSON.stringify({ filename }),
        });

        if (!resp.ok) {
            const err = await resp.json().catch(() => ({}));
            if (typeof showToast === 'function') {
                showToast('Download failed: ' + (err.message || resp.statusText), 'error');
            }
            return;
        }

        const blob = await resp.blob();
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);

        if (typeof showToast === 'function') {
            showToast(`Downloading ${escSafe(filename)}...`, 'success');
        }

    } catch (err) {
        console.error('[backups] download error:', err);
        if (typeof showToast === 'function') showToast('Network error during download.', 'error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-download"></i> Download';
        }
    }
}

// ============================================================
// DELETE BACKUP
// ============================================================
async function deleteBackup(filename) {
    if (!/^db-\d{8}-\d{6}-[0-9a-f]{8}\.sql\.gz$/.test(filename)) {
        if (typeof showToast === 'function') showToast('Invalid filename.', 'error');
        return;
    }

    if (!confirm(`Permanently delete backup:\n\n${filename}\n\nThis cannot be undone.`)) {
        return;
    }

    try {
        const resp = await fetch('/api/admin/backup_action.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
            },
            body: JSON.stringify({ action: 'delete', filename }),
        });

        const data = await resp.json();

        if (data.success) {
            if (typeof showToast === 'function') showToast('Backup deleted.', 'success');
            await loadBackups();
        } else {
            if (typeof showToast === 'function') {
                showToast('Delete failed: ' + (data.message || 'Unknown error'), 'error');
            }
        }

    } catch (err) {
        console.error('[backups] deleteBackup error:', err);
        if (typeof showToast === 'function') showToast('Network error.', 'error');
    }
}

// ============================================================
// RENDER HELPERS
// ============================================================
function _backupRenderStats(files, lastRun) {
    const countEl = document.getElementById('statBackupCount');
    const sizeEl = document.getElementById('statBackupTotalSize');
    const lastDateEl = document.getElementById('statLastBackupDate');
    const lastStatusEl = document.getElementById('statLastBackupStatus');

    if (countEl) countEl.textContent = files.length;

    if (sizeEl) {
        const totalBytes = files.reduce((sum, f) => sum + (f.size_bytes || 0), 0);
        sizeEl.textContent = _formatBytes(totalBytes);
    }

    if (lastDateEl && lastStatusEl) {
        if (lastRun && lastRun.last_run_at) {
            lastDateEl.textContent = lastRun.last_run_at.substring(0, 10);
            if (lastRun.status === 'success') {
                lastStatusEl.textContent = '✓ ' + (lastRun.trigger === 'manual' ? 'Manual run' : 'Cron run') + ' succeeded';
                lastStatusEl.style.color = 'var(--success, #22c55e)';
            } else {
                lastStatusEl.textContent = '✗ Last run failed';
                lastStatusEl.style.color = 'var(--danger, #ef4444)';
            }
        } else if (files.length > 0) {
            lastDateEl.textContent = files[0].created_at ? files[0].created_at.substring(0, 10) : '—';
            lastStatusEl.textContent = 'Based on newest file';
        } else {
            lastDateEl.textContent = 'Never';
            lastStatusEl.textContent = 'No backups yet';
        }
    }
}

function _backupRenderTable(tbody, files) {
    if (!tbody) return;

    if (!files || files.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="4" style="text-align: center; padding: 48px 16px; color: var(--text-muted);">
                    <i class="fas fa-database" style="font-size: 28px; margin-bottom: 10px; display: block; opacity: 0.3;"></i>
                    No backups yet. Click <strong>Backup Now</strong> to create the first snapshot.
                </td>
            </tr>`;
        return;
    }

    tbody.innerHTML = files.map((f, idx) => {
        const isNewest = idx === 0;
        const rowStyle = isNewest ? 'background: rgba(34, 211, 238, 0.04);' : '';
        const dateStr = f.created_at ? escSafe(f.created_at) : '—';
        const name = escSafe(f.filename);
        const size = escSafe(f.size_human || _formatBytes(f.size_bytes || 0));
        // Use data attributes to avoid inline-string injection into onclick
        return `<tr style="${rowStyle}">
            <td style="font-family: var(--font-mono); font-size: 12px;">
                ${isNewest ? '<span class="nav-badge" style="background: var(--accent); color: #000; margin-right: 6px;">Latest</span>' : ''}
                ${name}
            </td>
            <td style="color: var(--text-secondary);">${dateStr}</td>
            <td style="text-align: right; font-family: var(--font-mono); font-size: 12px;">${size}</td>
            <td style="text-align: center;">
                <div style="display: flex; gap: 6px; justify-content: center; flex-wrap: wrap;">
                    <button
                        type="button"
                        class="btn btn-secondary btn-sm"
                        data-download="${escSafe(f.filename)}"
                        onclick="downloadBackup(this.dataset.download)"
                        title="Download ${name}">
                        <i class="fas fa-download"></i> Download
                    </button>
                    <button
                        type="button"
                        class="btn btn-sm"
                        style="background: rgba(239,68,68,0.1); color: var(--danger, #ef4444); border: 1px solid rgba(239,68,68,0.3);"
                        data-delete="${escSafe(f.filename)}"
                        onclick="deleteBackup(this.dataset.delete)"
                        title="Delete ${name}">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </div>
            </td>
        </tr>`;
    }).join('');
}

function _backupSetTableError(tbody, message) {
    if (!tbody) return;
    tbody.innerHTML = `
        <tr>
            <td colspan="4" style="text-align: center; padding: 36px 16px; color: var(--danger, #ef4444);">
                <i class="fas fa-circle-exclamation" style="margin-right: 6px;"></i>
                ${escSafe(message)}
            </td>
        </tr>`;
}

function _formatBytes(bytes) {
    if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(2) + ' GB';
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' MB';
    if (bytes >= 1024) return (bytes / 1024).toFixed(2) + ' KB';
    return bytes + ' B';
}

// Safe HTML-escape helper (may already exist in common.js under escapeHtml —
// defined here too so backups.js is self-contained if loaded alone)
function escSafe(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

