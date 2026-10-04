// ============================================================
// ADMIN LAB EDIT — lab-edit.js
// Handles full-form authoring and editing for Engineering Labs.
// ============================================================

async function loadLabCategories(selectedCategory) {
    try {
        const res = await fetch('/api/categories/list.php?type=lab', { credentials: 'same-origin' });
        const data = await res.json();
        if (data.success && Array.isArray(data.data) && data.data.length > 0) {
            const select = document.getElementById('lab_category');
            if (select) {
                const currentVal = selectedCategory || select.value;
                select.innerHTML = data.data.map(c => `<option value="${escapeHtml(c.name)}">${escapeHtml(c.name)}</option>`).join('');
                if (currentVal && !data.data.some(c => c.name === currentVal)) {
                    const opt = document.createElement('option');
                    opt.value = currentVal;
                    opt.textContent = currentVal;
                    select.appendChild(opt);
                }
                if (currentVal) {
                    select.value = currentVal;
                }
            }
        }
    } catch (e) {
        console.warn('Could not load dynamic lab categories:', e);
    }
}

document.addEventListener('DOMContentLoaded', async () => {
    const originalId = document.getElementById('original_id').value.trim();
    await loadLabCategories();
    if (originalId) {
        loadLabData(originalId);
    } else {
        // Add 2 default empty metric rows for new experiment
        addMetricRow();
        addMetricRow();
        syncCategoryLabel();
    }
});

function getCsrfTokenSafe() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

function syncCategoryLabel() {
    const cat = document.getElementById('lab_category').value;
    const labelInput = document.getElementById('lab_categoryLabel');
    if (!labelInput.value || labelInput.dataset.autoFilled === 'true') {
        const map = {
            'database': 'Database & SQL',
            'concurrency': 'Concurrency & Runtimes',
            'performance': 'Performance & Cache',
            'systems': 'Systems Architecture',
            'network': 'Network Protocols',
            'security': 'Security & Cryptography'
        };
        labelInput.value = map[cat] || (cat.charAt(0).toUpperCase() + cat.slice(1));
        labelInput.dataset.autoFilled = 'true';
    }
}

document.getElementById('lab_categoryLabel')?.addEventListener('input', function () {
    this.dataset.autoFilled = 'false';
});

async function loadLabData(id) {
    try {
        const res = await fetch(`/api/labs/get.php?id=${encodeURIComponent(id)}`, {
            headers: { 'Accept': 'application/json' }
        });
        const data = await res.json();

        if (!data.success || !data.experiment) {
            alert('Failed to load experiment: ' + (data.message || 'Not found'));
            window.location.href = 'labs.php';
            return;
        }

        const exp = data.experiment;
        await loadLabCategories(exp.category);
        document.getElementById('lab_id').value = exp.id || '';
        document.getElementById('lab_status').value = exp.status || 'ACTIVE';
        document.getElementById('lab_category').value = exp.category || 'systems';
        document.getElementById('lab_categoryLabel').value = exp.categoryLabel || '';
        document.getElementById('lab_readTime').value = exp.readTime || '7 min';
        document.getElementById('lab_subsystem').value = exp.subsystem || '';
        document.getElementById('lab_ref').value = exp.ref || '';
        document.getElementById('lab_title').value = exp.title || '';
        document.getElementById('lab_question').value = exp.question || '';
        document.getElementById('lab_hypothesis').value = exp.hypothesis || '';
        document.getElementById('lab_environment').value = exp.environment || '';
        document.getElementById('lab_method').value = exp.method || '';
        document.getElementById('lab_outcomeTitle').value = exp.outcomeTitle || 'EMPIRICAL OUTCOME:';
        document.getElementById('lab_outcomeHeadline').value = exp.outcomeHeadline || '';
        document.getElementById('lab_outcomeDesc').value = exp.outcomeDesc || '';
        document.getElementById('lab_tech').value = Array.isArray(exp.tech) ? exp.tech.join(', ') : (exp.tech || '');
        document.getElementById('lab_image').value = exp.image || '';
        document.getElementById('lab_repro_command').value = exp.repro_command || '';
        document.getElementById('lab_observations').value = exp.observations || '';
        document.getElementById('lab_conclusion').value = exp.conclusion || '';

        updateImagePreview();

        // Metrics rows
        const tbody = document.getElementById('metricsTableBody');
        tbody.innerHTML = '';
        if (Array.isArray(exp.metrics) && exp.metrics.length > 0) {
            exp.metrics.forEach(m => addMetricRow(m.param, m.baseline, m.tuned, m.tradeoff));
        } else {
            addMetricRow();
            addMetricRow();
        }

    } catch (err) {
        alert('Network error while loading experiment.');
    }
}

function updateImagePreview() {
    const url = document.getElementById('lab_image').value.trim();
    const container = document.getElementById('imagePreviewContainer');
    const img = document.getElementById('imagePreview');

    if (url) {
        img.src = url;
        container.style.display = 'block';
    } else {
        container.style.display = 'none';
    }
}

async function handleImageUpload(input) {
    if (!input.files || !input.files[0]) return;

    const file = input.files[0];
    if (file.size > 5 * 1024 * 1024) {
        alert('File size exceeds 5MB limit.');
        input.value = '';
        return;
    }

    const formData = new FormData();
    formData.append('image', file, 'upload_' + Date.now() + '_' + file.name.replace(/[^a-zA-Z0-9.-_]/g, '_'));

    const btn = input.previousElementSibling;
    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';

    try {
        const res = await fetch('/api/uploads/image.php', {
            method: 'POST',
            headers: {
                'X-CSRF-Token': getCsrfTokenSafe(),
                'Accept': 'application/json'
            },
            body: formData
        });
        const data = await res.json();

        if (data.success && data.url) {
            document.getElementById('lab_image').value = data.url;
            updateImagePreview();
            showToast('Image uploaded successfully', 'success');
        } else {
            alert('Upload failed: ' + (data.message || 'Unknown error'));
        }
    } catch (err) {
        alert('Network error while uploading image.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = origHtml;
        input.value = '';
    }
}

function addMetricRow(param = '', baseline = '', tuned = '', tradeoff = '') {
    const tbody = document.getElementById('metricsTableBody');
    const tr = document.createElement('tr');
    tr.className = 'metric-row';
    tr.innerHTML = `
        <td>
            <input type="text" class="form-control metric-param" placeholder="e.g. p99 Latency" value="${escapeHtml(param)}" style="font-size: 12.5px;">
        </td>
        <td>
            <input type="text" class="form-control metric-baseline" placeholder="e.g. 6.40 ms" value="${escapeHtml(baseline)}" style="font-size: 12.5px;">
        </td>
        <td>
            <input type="text" class="form-control metric-tuned" placeholder="e.g. 3.05 ms" value="${escapeHtml(tuned)}" style="font-size: 12.5px; font-weight: 600; color: var(--accent);">
        </td>
        <td>
            <input type="text" class="form-control metric-tradeoff" placeholder="e.g. B-Tree traversal suffers L3 cache evictions" value="${escapeHtml(tradeoff)}" style="font-size: 12.5px;">
        </td>
        <td style="text-align: center;">
            <button type="button" class="btn btn-icon btn-sm" onclick="this.closest('tr').remove()" title="Remove Row" style="color: var(--accent-red);">
                <i class="fas fa-times"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);
}

async function saveLabExperiment() {
    const id = document.getElementById('lab_id').value.trim();
    const title = document.getElementById('lab_title').value.trim();

    if (!id) {
        alert('Please enter an Experiment ID (e.g. LAB-001).');
        document.getElementById('lab_id').focus();
        return;
    }

    if (!title) {
        alert('Please enter an Investigation Title.');
        document.getElementById('lab_title').focus();
        return;
    }

    // Collect metrics
    const metricRows = document.querySelectorAll('.metric-row');
    const metrics = [];
    metricRows.forEach(row => {
        const param = row.querySelector('.metric-param')?.value.trim();
        const baseline = row.querySelector('.metric-baseline')?.value.trim();
        const tuned = row.querySelector('.metric-tuned')?.value.trim();
        const tradeoff = row.querySelector('.metric-tradeoff')?.value.trim();

        if (param) {
            metrics.push({ param, baseline, tuned, tradeoff });
        }
    });

    const payload = {
        id: id,
        original_id: document.getElementById('original_id').value.trim(),
        title: title,
        status: document.getElementById('lab_status').value,
        category: document.getElementById('lab_category').value,
        categoryLabel: document.getElementById('lab_categoryLabel').value.trim(),
        readTime: document.getElementById('lab_readTime').value.trim(),
        subsystem: document.getElementById('lab_subsystem').value.trim(),
        ref: document.getElementById('lab_ref').value.trim(),
        question: document.getElementById('lab_question').value.trim(),
        hypothesis: document.getElementById('lab_hypothesis').value.trim(),
        environment: document.getElementById('lab_environment').value.trim(),
        method: document.getElementById('lab_method').value.trim(),
        outcomeTitle: document.getElementById('lab_outcomeTitle').value.trim(),
        outcomeHeadline: document.getElementById('lab_outcomeHeadline').value.trim(),
        outcomeDesc: document.getElementById('lab_outcomeDesc').value.trim(),
        tech: document.getElementById('lab_tech').value.trim(),
        image: document.getElementById('lab_image').value.trim(),
        repro_command: document.getElementById('lab_repro_command').value.trim(),
        observations: document.getElementById('lab_observations').value.trim(),
        conclusion: document.getElementById('lab_conclusion').value.trim(),
        metrics: metrics
    };

    const saveBtns = document.querySelectorAll('#saveLabBtn, button[onclick="saveLabExperiment()"]');
    saveBtns.forEach(b => {
        b.disabled = true;
        b.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    });

    try {
        const res = await fetch('/api/labs/save.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrfTokenSafe(),
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const data = await res.json();

        if (data.success) {
            showToast('Experiment saved successfully!', 'success');
            // Update original_id so next save updates
            document.getElementById('original_id').value = id;
            const breadcrumb = document.getElementById('breadcrumbCurrent');
            if (breadcrumb) breadcrumb.textContent = id;
            const editorTitle = document.getElementById('editorTitle');
            if (editorTitle) editorTitle.textContent = 'Edit Investigation: ' + id;

            setTimeout(() => {
                window.location.href = 'labs.php';
            }, 800);
        } else {
            alert('Failed to save experiment: ' + (data.message || 'Unknown error'));
        }
    } catch (err) {
        alert('Network error while saving experiment.');
    } finally {
        saveBtns.forEach(b => {
            b.disabled = false;
            b.innerHTML = '<i class="fas fa-save"></i> Save Experiment';
        });
    }
}

function showToast(msg, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast-notice toast-${type}`;
    toast.style.cssText = 'position: fixed; bottom: 24px; right: 24px; z-index: 9999; padding: 12px 20px; background: var(--bg-surface-elevated, #1C2532); border: 1px solid var(--border-medium, rgba(148,163,184,0.16)); border-radius: 8px; color: var(--text-primary, #F1F5F9); font-size: 13px; box-shadow: 0 4px 16px rgba(0,0,0,0.4);';
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.3s';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

function escapeHtml(str) {
    if (typeof str !== 'string') return '';
    return str.replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

