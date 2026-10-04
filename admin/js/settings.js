// ============================================================
// ADMIN CONTROL CENTER — settings.js
// Handles tab navigation, profile settings, website & branding,
// top-4 showcase slot curation, social channels, and system metrics.
// ============================================================

let allProjects = [];
let showcaseSlotIds = [null, null, null, null]; // Exactly 4 slots

const SUPPORTED_SOCIAL_PLATFORMS = ['github', 'linkedin', 'x', 'instagram', 'tiktok', 'facebook'];

document.addEventListener('DOMContentLoaded', async () => {
    const authenticated = await checkAdminAuthentication();
    if (!authenticated) return;

    setupTabs();
    setupLivePreviews();
    setupForms();
    setupAccountForms();

    // Concurrently load all Control Center data
    await Promise.all([
        loadSiteSettings(),
        loadShowcaseData(),
        loadSocialChannels(),
        loadAccountData()
    ]);
});

// ============================================================
// 1. TAB NAVIGATION & DEEP LINKING
// ============================================================
function setupTabs() {
    const tabButtons = document.querySelectorAll('#controlCenterTabs .filter-tab');
    const tabPanes = document.querySelectorAll('.tab-pane');

    function switchTab(tabKey) {
        tabButtons.forEach(btn => {
            const isActive = btn.getAttribute('data-tab') === tabKey;
            btn.classList.toggle('active', isActive);
            btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });

        tabPanes.forEach(pane => {
            const isActive = pane.id === `pane-${tabKey}`;
            pane.classList.toggle('active', isActive);
        });

        if (tabKey === 'account') {
            loadAccountData();
        }

        // Update URL query parameter without page reload
        const url = new URL(window.location);
        url.searchParams.set('tab', tabKey);
        window.history.replaceState({}, '', url);
    }

    tabButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const tab = btn.getAttribute('data-tab');
            if (tab) switchTab(tab);
        });
    });

    // Check initial tab from URL query (?tab=...)
    const params = new URLSearchParams(window.location.search);
    const initialTab = params.get('tab');
    if (initialTab && document.getElementById(`pane-${initialTab}`)) {
        switchTab(initialTab);
    }
}

// ============================================================
// 2. LIVE PROFILE PREVIEWS
// ============================================================
function setupLivePreviews() {
    const nameInput = document.getElementById('profile_name');
    const roleInput = document.getElementById('profile_role');
    const mottoInput = document.getElementById('profile_motto');
    const shortNameInput = document.getElementById('profile_short_name');
    const avatarInput = document.getElementById('profile_avatar_url');
    const showEmailCheck = document.getElementById('profile_show_email');
    const statusTextEmail = document.getElementById('status_text_show_email');

    const previewName = document.getElementById('previewProfileName');
    const previewRole = document.getElementById('previewProfileRole');
    const previewMotto = document.getElementById('previewProfileMotto');
    const previewMonogram = document.getElementById('previewProfileMonogram');
    const avatarImg = document.getElementById('avatarPreviewImg');

    if (nameInput && previewName) {
        nameInput.addEventListener('input', () => {
            previewName.textContent = nameInput.value.trim() || 'Mohammed Alrashadi';
        });
    }
    if (roleInput && previewRole) {
        roleInput.addEventListener('input', () => {
            previewRole.textContent = roleInput.value.trim() || 'Software Engineering Student';
        });
    }
    if (mottoInput && previewMotto) {
        mottoInput.addEventListener('input', () => {
            previewMotto.textContent = mottoInput.value.trim() || 'Build. Learn. Experiment. Evolve.';
        });
    }
    if (shortNameInput && previewMonogram) {
        shortNameInput.addEventListener('input', () => {
            previewMonogram.textContent = shortNameInput.value.trim() || 'MA';
        });
    }
    if (avatarInput && avatarImg) {
        avatarInput.addEventListener('input', () => {
            const url = avatarInput.value.trim();
            if (url) avatarImg.src = url;
        });
    }
    if (showEmailCheck && statusTextEmail) {
        showEmailCheck.addEventListener('change', () => {
            statusTextEmail.textContent = showEmailCheck.checked ? 'Visible' : 'Hidden';
        });
    }

    setupAvatarUpload();
}

function setupAvatarUpload() {
    const container = document.getElementById('profileAvatarContainer');
    const fileInput = document.getElementById('profileAvatarFileInput');
    const avatarImg = document.getElementById('avatarPreviewImg');
    const avatarInput = document.getElementById('profile_avatar_url');
    const spinner = document.getElementById('avatarUploadSpinner');

    if (!container || !fileInput) return;

    container.addEventListener('click', (e) => {
        if (e.target === fileInput) return;
        fileInput.click();
    });

    fileInput.addEventListener('change', async () => {
        if (!fileInput.files || !fileInput.files[0]) return;

        const file = fileInput.files[0];
        if (file.size > 5 * 1024 * 1024) {
            alert('File size exceeds 5MB limit.');
            fileInput.value = '';
            return;
        }

        const validTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!validTypes.includes(file.type)) {
            alert('Invalid image format. Allowed formats: JPEG, PNG, WebP, GIF.');
            fileInput.value = '';
            return;
        }

        if (spinner) spinner.style.display = 'flex';

        const formData = new FormData();
        formData.append('avatar', file, 'upload_' + Date.now() + '_' + file.name.replace(/[^a-zA-Z0-9.-_]/g, '_'));

        try {
            const res = await fetch('/api/settings/upload_avatar.php', {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': getCsrfTokenSafe(),
                    'Accept': 'application/json'
                },
                body: formData
            });

            const data = await res.json();

            if (data.success && data.avatar_url) {
                if (avatarImg) avatarImg.src = data.avatar_url;
                if (avatarInput) avatarInput.value = data.avatar_url;
                showToast('Profile image uploaded and updated successfully.', 'success');
            } else {
                alert('Avatar upload failed: ' + (data.message || 'Unknown error'));
            }
        } catch (err) {
            alert('Network error while uploading profile image.');
        } finally {
            if (spinner) spinner.style.display = 'none';
            fileInput.value = '';
        }
    });
}

// ============================================================
// 3. LOAD SITE SETTINGS (PROFILE, BRANDING, WEBSITE, SEO)
// ============================================================
async function loadSiteSettings() {
    try {
        const res = await fetch('/api/settings/get.php', {
            method: 'GET',
            credentials: 'same-origin'
        });
        const json = await res.json();
        if (!json.success || !json.data) return;

        const s = json.data;

        // Populate Profile Tab
        if (s.profile) {
            setInputValue('profile_name', s.profile.name);
            setInputValue('profile_short_name', s.profile.short_name);
            setInputValue('profile_role', s.profile.role);
            setInputValue('profile_motto', s.profile.motto);
            setInputValue('profile_location', s.profile.location);
            setInputValue('profile_current_focus', s.profile.current_focus);
            setInputValue('profile_education_stage', s.profile.education_stage);
            setInputValue('profile_public_email', s.profile.public_email);
            setInputValue('profile_avatar_url', s.profile.avatar_url);
            setInputValue('profile_bio_short', s.profile.bio_short);
            setInputValue('profile_bio_full', s.profile.bio_full);

            const showEmailCheck = document.getElementById('profile_show_email');
            const showEmailText = document.getElementById('status_text_show_email');
            const isShow = s.profile.show_email === '1' || s.profile.show_email === 1;
            if (showEmailCheck) showEmailCheck.checked = isShow;
            if (showEmailText) showEmailText.textContent = isShow ? 'Visible' : 'Hidden';

            // Trigger preview refresh
            const nameEl = document.getElementById('previewProfileName');
            const roleEl = document.getElementById('previewProfileRole');
            const mottoEl = document.getElementById('previewProfileMotto');
            const monoEl = document.getElementById('previewProfileMonogram');
            const avatarEl = document.getElementById('avatarPreviewImg');

            if (nameEl && s.profile.name) nameEl.textContent = s.profile.name;
            if (roleEl && s.profile.role) roleEl.textContent = s.profile.role;
            if (mottoEl && s.profile.motto) mottoEl.textContent = s.profile.motto;
            if (monoEl && s.profile.short_name) monoEl.textContent = s.profile.short_name;
            if (avatarEl && s.profile.avatar_url) avatarEl.src = s.profile.avatar_url;
        }

        // Populate Website & Branding Tab
        if (s.website) {
            setInputValue('website_platform_name', s.website.platform_name);
            setInputValue('website_platform_descriptor', s.website.platform_descriptor);
            setInputValue('website_canonical_url', s.website.canonical_url);
            setInputValue('website_platform_purpose', s.website.platform_purpose);
        }

        if (s.branding) {
            setInputValue('branding_monogram_url', s.branding.monogram_url);
            setInputValue('branding_favicon_url', s.branding.favicon_url);
            setInputValue('branding_og_image_url', s.branding.og_image_url);
        }

        if (s.seo) {
            setInputValue('seo_default_title', s.seo.default_title);
            setInputValue('seo_default_description', s.seo.default_description);
        }

    } catch (err) {
        console.error('❌ Error loading site settings:', err);
        showToast('Unable to load current settings.', 'error');
    }
}

function setInputValue(id, val) {
    const el = document.getElementById(id);
    if (el && val !== undefined && val !== null) {
        el.value = val;
    }
}

// ============================================================
// 4. FORMS SUBMISSION (PROFILE & WEBSITE)
// ============================================================
function setupForms() {
    // Profile Form
    const profileForm = document.getElementById('profileForm');
    const saveProfileBtn = document.getElementById('saveProfileBtn');
    const profileStatusMsg = document.getElementById('profileStatusMsg');

    if (profileForm) {
        profileForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!saveProfileBtn) return;

            setButtonLoading(saveProfileBtn, true, 'Saving Profile...');
            if (profileStatusMsg) profileStatusMsg.textContent = '';

            const showEmailChecked = document.getElementById('profile_show_email')?.checked ? '1' : '0';

            const payload = {
                group: 'profile',
                settings: {
                    name: document.getElementById('profile_name')?.value || '',
                    short_name: document.getElementById('profile_short_name')?.value || '',
                    role: document.getElementById('profile_role')?.value || '',
                    motto: document.getElementById('profile_motto')?.value || '',
                    location: document.getElementById('profile_location')?.value || '',
                    current_focus: document.getElementById('profile_current_focus')?.value || '',
                    education_stage: document.getElementById('profile_education_stage')?.value || '',
                    public_email: document.getElementById('profile_public_email')?.value || '',
                    show_email: showEmailChecked,
                    avatar_url: document.getElementById('profile_avatar_url')?.value || '',
                    bio_short: document.getElementById('profile_bio_short')?.value || '',
                    bio_full: document.getElementById('profile_bio_full')?.value || '',
                }
            };

            try {
                const res = await fetch('/api/settings/update.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': getCsrfTokenSafe(),
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(payload),
                });

                const json = await res.json();
                setButtonLoading(saveProfileBtn, false, 'Save Profile Changes');

                if (json.success) {
                    showToast('Platform profile updated successfully.', 'success');
                    if (profileStatusMsg) profileStatusMsg.textContent = 'Saved just now';
                } else {
                    showToast(json.message || 'Failed to update profile.', 'error');
                }
            } catch (err) {
                console.error('❌ Profile save error:', err);
                setButtonLoading(saveProfileBtn, false, 'Save Profile Changes');
                showToast('Network error while saving profile.', 'error');
            }
        });
    }

    // Website & Branding Form
    const websiteForm = document.getElementById('websiteForm');
    const saveWebsiteBtn = document.getElementById('saveWebsiteBtn');
    const websiteStatusMsg = document.getElementById('websiteStatusMsg');

    if (websiteForm) {
        websiteForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!saveWebsiteBtn) return;

            setButtonLoading(saveWebsiteBtn, true, 'Saving Website...');
            if (websiteStatusMsg) websiteStatusMsg.textContent = '';

            const payload = {
                settings: {
                    'website.platform_name': document.getElementById('website_platform_name')?.value || '',
                    'website.platform_descriptor': document.getElementById('website_platform_descriptor')?.value || '',
                    'website.canonical_url': document.getElementById('website_canonical_url')?.value || '',
                    'website.platform_purpose': document.getElementById('website_platform_purpose')?.value || '',
                    'branding.monogram_url': document.getElementById('branding_monogram_url')?.value || '',
                    'branding.favicon_url': document.getElementById('branding_favicon_url')?.value || '',
                    'branding.og_image_url': document.getElementById('branding_og_image_url')?.value || '',
                    'seo.default_title': document.getElementById('seo_default_title')?.value || '',
                    'seo.default_description': document.getElementById('seo_default_description')?.value || '',
                }
            };

            try {
                const res = await fetch('/api/settings/update.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': getCsrfTokenSafe(),
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(payload),
                });

                const json = await res.json();
                setButtonLoading(saveWebsiteBtn, false, 'Save Website Changes');

                if (json.success) {
                    showToast('Website and branding settings saved.', 'success');
                    if (websiteStatusMsg) websiteStatusMsg.textContent = 'Saved just now';
                } else {
                    showToast(json.message || 'Failed to update website settings.', 'error');
                }
            } catch (err) {
                console.error('❌ Website save error:', err);
                setButtonLoading(saveWebsiteBtn, false, 'Save Website Changes');
                showToast('Network error while saving website settings.', 'error');
            }
        });
    }
}

// ============================================================
// 5. HOME SHOWCASE CURATION & MANUAL ORDERING (AUDIT DECISION 3)
// ============================================================
async function loadShowcaseData() {
    const loadingState = document.getElementById('showcaseLoadingState');
    const slotsGrid = document.getElementById('showcaseSlotsGrid');
    if (!slotsGrid) return; // Legacy showcase slots grid retired; dedicated showcase.php handles curation.

    try {
        // 1. Fetch all projects
        const postsRes = await fetch('/api/posts/list.php?status=all', { credentials: 'same-origin' });
        const postsJson = await postsRes.json();
        if (postsJson.success && Array.isArray(postsJson.data)) {
            allProjects = postsJson.data.filter(p => p.type === 'project');
        }

        // 2. Fetch current showcase settings
        const settingsRes = await fetch('/api/settings/get.php?group=showcase', { credentials: 'same-origin' });
        const settingsJson = await settingsRes.json();

        let savedIds = [];
        if (settingsJson.success && settingsJson.data) {
            const rawItemIds = settingsJson.data.item_ids || settingsJson.data.pinned_ids || '';
            try {
                savedIds = typeof rawItemIds === 'string' && rawItemIds.startsWith('[')
                    ? JSON.parse(rawItemIds)
                    : rawItemIds.split(',').map(id => parseInt(id.trim(), 10)).filter(Boolean);
            } catch (e) {
                savedIds = [];
            }
        }

        // Published projects sorted by date descending
        const publishedProjects = allProjects
            .filter(p => p.status === 'published')
            .sort((a, b) => new Date(b.created_at || 0) - new Date(a.created_at || 0));

        // Initialize 4 slots: use savedIds, or default to first 4 published projects
        showcaseSlotIds = [null, null, null, null];
        for (let i = 0; i < 4; i++) {
            if (savedIds[i] && allProjects.some(p => p.id === savedIds[i])) {
                showcaseSlotIds[i] = savedIds[i];
            } else if (publishedProjects[i]) {
                showcaseSlotIds[i] = publishedProjects[i].id;
            }
        }

        if (loadingState) loadingState.style.display = 'none';
        if (slotsGrid) slotsGrid.style.display = 'grid';

        renderShowcaseSlots();
        renderReserveInventory();
        setupShowcaseActionHandlers();

    } catch (err) {
        console.error('❌ Error loading showcase data:', err);
        if (loadingState) {
            loadingState.innerHTML = `<p style="color: var(--danger);">Failed to load showcase projects.</p>`;
        }
    }
}

function renderShowcaseSlots() {
    const slotsGrid = document.getElementById('showcaseSlotsGrid');
    const badge = document.getElementById('showcaseSlotsStatusBadge');
    if (!slotsGrid) return;

    const published = allProjects.filter(p => p.status === 'published');
    const filledCount = showcaseSlotIds.filter(id => id !== null).length;

    if (badge) {
        badge.textContent = `${filledCount} / 4 Slots Filled`;
        badge.className = filledCount === 4 ? 'status-badge status-published' : 'status-badge status-warning';
    }

    const slotLabels = [
        'Slot #1 (Hero Spotlight)',
        'Slot #2 (Secondary Card)',
        'Slot #3 (Third Card)',
        'Slot #4 (Fourth Card)'
    ];

    slotsGrid.innerHTML = showcaseSlotIds.map((assignedId, slotIdx) => {
        const project = allProjects.find(p => p.id === assignedId);
        const isEmpty = !project;

        let optionsHtml = `<option value="">-- Empty Slot --</option>`;
        published.forEach(p => {
            const isSelected = p.id === assignedId;
            optionsHtml += `<option value="${p.id}" ${isSelected ? 'selected' : ''}>${escapeHTML(p.title)} (${escapeHTML(p.category || 'Systems')})</option>`;
        });

        const previewHtml = project ? `
            <div class="slot-preview">
                <img src="${escapeHTML(project.image_url || '/assets/diagram_distributed_systems.png')}" alt="${escapeHTML(project.title)}" class="slot-preview-thumb" onerror="this.src='/uploads/placeholder.svg';">
                <div class="slot-preview-info">
                    <div class="slot-preview-meta">${escapeHTML(project.category || 'SYSTEMS')}</div>
                    <div class="slot-preview-title">${escapeHTML(project.title)}</div>
                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">ID #${project.id} · Published</div>
                </div>
            </div>
        ` : `
            <div style="padding: 14px; text-align: center; color: var(--text-muted); font-size: 12px; background: var(--bg-canvas); border-radius: var(--radius-sm); border: 1px dashed var(--border-subtle);">
                No project assigned to this slot.
            </div>
        `;

        return `
            <div class="showcase-slot-card ${isEmpty ? 'is-empty' : ''}" data-slot-index="${slotIdx}">
                <div class="slot-header">
                    <span class="slot-badge">${slotLabels[slotIdx]}</span>
                    <div class="slot-controls">
                        <button type="button" class="slot-ctrl-btn move-up-btn" data-slot="${slotIdx}" title="Move Up" ${slotIdx === 0 ? 'disabled' : ''}>
                            <i class="fas fa-arrow-up"></i>
                        </button>
                        <button type="button" class="slot-ctrl-btn move-down-btn" data-slot="${slotIdx}" title="Move Down" ${slotIdx === 3 ? 'disabled' : ''}>
                            <i class="fas fa-arrow-down"></i>
                        </button>
                        <button type="button" class="slot-ctrl-btn clear-slot-btn" data-slot="${slotIdx}" title="Clear Slot" ${isEmpty ? 'disabled' : ''}>
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>

                <div>
                    <label style="font-size: 11px; color: var(--text-muted); display: block; margin-bottom: 4px;">Assign Project:</label>
                    <select class="form-control slot-select" data-slot="${slotIdx}">
                        ${optionsHtml}
                    </select>
                </div>

                ${previewHtml}
            </div>
        `;
    }).join('');

    // Attach listeners to slot controls
    slotsGrid.querySelectorAll('.slot-select').forEach(sel => {
        sel.addEventListener('change', (e) => {
            const slot = parseInt(e.target.getAttribute('data-slot'), 10);
            const val = e.target.value ? parseInt(e.target.value, 10) : null;
            showcaseSlotIds[slot] = val;
            renderShowcaseSlots();
            renderReserveInventory();
        });
    });

    slotsGrid.querySelectorAll('.move-up-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const slot = parseInt(btn.getAttribute('data-slot'), 10);
            if (slot > 0) {
                const temp = showcaseSlotIds[slot - 1];
                showcaseSlotIds[slot - 1] = showcaseSlotIds[slot];
                showcaseSlotIds[slot] = temp;
                renderShowcaseSlots();
            }
        });
    });

    slotsGrid.querySelectorAll('.move-down-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const slot = parseInt(btn.getAttribute('data-slot'), 10);
            if (slot < 3) {
                const temp = showcaseSlotIds[slot + 1];
                showcaseSlotIds[slot + 1] = showcaseSlotIds[slot];
                showcaseSlotIds[slot] = temp;
                renderShowcaseSlots();
            }
        });
    });

    slotsGrid.querySelectorAll('.clear-slot-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const slot = parseInt(btn.getAttribute('data-slot'), 10);
            showcaseSlotIds[slot] = null;
            renderShowcaseSlots();
            renderReserveInventory();
        });
    });
}

function renderReserveInventory() {
    const tbody = document.getElementById('showcaseInventoryBody');
    if (!tbody) return;

    if (allProjects.length === 0) {
        tbody.innerHTML = `<tr><td colspan="5" style="text-align: center; padding: 24px; color: var(--text-muted);">No engineering projects created yet.</td></tr>`;
        return;
    }

    tbody.innerHTML = allProjects.map(proj => {
        const slotIdx = showcaseSlotIds.indexOf(proj.id);
        const isAssigned = slotIdx !== -1;
        const isPublished = proj.status === 'published';

        const statusBadge = isPublished
            ? `<span class="status-badge status-published">Published</span>`
            : `<span class="status-badge status-draft">Draft</span>`;

        let actionHtml = '';
        if (isAssigned) {
            actionHtml = `<span class="status-badge status-published" style="font-size: 11px;">Active in Slot #${slotIdx + 1}</span>`;
        } else if (!isPublished) {
            actionHtml = `<span style="font-size: 12px; color: var(--text-muted);">Publish project to assign</span>`;
        } else {
            const firstEmptySlot = showcaseSlotIds.indexOf(null);
            if (firstEmptySlot !== -1) {
                actionHtml = `<button type="button" class="btn btn-secondary btn-sm assign-to-slot-btn" data-project-id="${proj.id}" data-slot="${firstEmptySlot}">+ Assign to Slot #${firstEmptySlot + 1}</button>`;
            } else {
                actionHtml = `<span style="font-size: 12px; color: var(--text-muted);">All 4 slots filled</span>`;
            }
        }

        const dateStr = proj.created_at ? new Date(proj.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';

        return `
            <tr>
                <td>
                    <div style="font-weight: 600; color: var(--text-primary); font-size: 13.5px;">${escapeHTML(proj.title)}</div>
                    <div style="font-size: 11.5px; color: var(--text-muted);">ID #${proj.id}</div>
                </td>
                <td><span style="font-family: var(--font-mono); font-size: 12px; color: var(--accent);">${escapeHTML(proj.category || 'General')}</span></td>
                <td style="font-size: 12px; color: var(--text-secondary);">${dateStr}</td>
                <td style="text-align: center;">${statusBadge}</td>
                <td style="text-align: right;">${actionHtml}</td>
            </tr>
        `;
    }).join('');

    tbody.querySelectorAll('.assign-to-slot-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const projId = parseInt(btn.getAttribute('data-project-id'), 10);
            const slot = parseInt(btn.getAttribute('data-slot'), 10);
            if (!isNaN(projId) && !isNaN(slot)) {
                showcaseSlotIds[slot] = projId;
                renderShowcaseSlots();
                renderReserveInventory();
            }
        });
    });
}

function setupShowcaseActionHandlers() {
    const saveBtn = document.getElementById('saveShowcaseBtn');
    const statusMsg = document.getElementById('showcaseStatusMsg');
    if (!saveBtn) return;

    saveBtn.addEventListener('click', async () => {
        setButtonLoading(saveBtn, true, 'Saving Showcase...');
        if (statusMsg) statusMsg.textContent = '';

        const activeIds = showcaseSlotIds.filter(id => id !== null);
        const payload = {
            group: 'showcase',
            settings: {
                item_ids: JSON.stringify(activeIds),
                pinned_ids: activeIds.join(','),
                showcase_mode: 'manual'
            }
        };

        try {
            const res = await fetch('/api/settings/update.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': getCsrfTokenSafe(),
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            });

            const json = await res.json();
            setButtonLoading(saveBtn, false, 'Save Showcase Order');

            if (json.success) {
                showToast('Homepage showcase order saved successfully.', 'success');
                if (statusMsg) statusMsg.textContent = 'Saved just now';
            } else {
                showToast(json.message || 'Failed to save showcase order.', 'error');
            }
        } catch (err) {
            console.error('❌ Showcase save error:', err);
            setButtonLoading(saveBtn, false, 'Save Showcase Order');
            showToast('Network error while saving showcase order.', 'error');
        }
    });
}

// ============================================================
// 6. SOCIAL CHANNELS MANAGEMENT (AUDIT DECISION 1)
// ============================================================
async function loadSocialChannels() {
    try {
        const res = await fetch('/api/social/admin_list.php', {
            method: 'GET',
            credentials: 'same-origin',
        });
        const json = await res.json();
        if (!json.success || !Array.isArray(json.data)) return;

        json.data.forEach(item => {
            const platform = item.platform;
            const nameInput = document.getElementById(`name_${platform}`);
            const urlInput = document.getElementById(`url_${platform}`);
            const iconSelect = document.getElementById(`icon_${platform}`);
            const sortInput = document.getElementById(`sort_${platform}`);
            const checkbox = document.getElementById(`enable_${platform}`);

            if (nameInput && item.name) nameInput.value = item.name;
            if (urlInput) urlInput.value = item.url || '';
            if (iconSelect && item.icon_key) iconSelect.value = item.icon_key;
            if (sortInput && item.sort_order !== undefined) sortInput.value = item.sort_order;
            if (checkbox) checkbox.checked = Boolean(item.is_enabled);
        });

        updateActiveSocialCount();
        setupSocialListeners();

    } catch (err) {
        console.error('❌ Error loading social channels:', err);
    }
}

function updateActiveSocialCount() {
    let count = 0;
    SUPPORTED_SOCIAL_PLATFORMS.forEach(platform => {
        const checkbox = document.getElementById(`enable_${platform}`);
        const statusText = document.getElementById(`status_text_${platform}`);
        const card = document.querySelector(`.social-platform-card[data-platform="${platform}"]`);

        if (checkbox && checkbox.checked) {
            count++;
            if (statusText) statusText.textContent = 'Enabled';
            if (card) card.classList.add('is-active');
        } else {
            if (statusText) statusText.textContent = 'Disabled';
            if (card) card.classList.remove('is-active');
        }
    });

    const countBadge = document.getElementById('activePlatformsCountBadge');
    const tabCount = document.getElementById('socialCountBadge');
    if (countBadge) countBadge.textContent = `${count} Active`;
    if (tabCount) tabCount.textContent = count;
}

function setupSocialListeners() {
    SUPPORTED_SOCIAL_PLATFORMS.forEach(platform => {
        const checkbox = document.getElementById(`enable_${platform}`);
        if (checkbox) {
            checkbox.addEventListener('change', updateActiveSocialCount);
        }
    });

    const socialForm = document.getElementById('socialForm');
    const saveSocialBtn = document.getElementById('saveSocialBtn');
    const socialStatusMsg = document.getElementById('socialStatusMsg');

    if (socialForm && !socialForm.dataset.initialized) {
        socialForm.dataset.initialized = 'true';
        socialForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!saveSocialBtn) return;

            setButtonLoading(saveSocialBtn, true, 'Saving Channels...');
            if (socialStatusMsg) socialStatusMsg.textContent = '';

            const platformsData = [];
            SUPPORTED_SOCIAL_PLATFORMS.forEach(platform => {
                const nameInput = document.getElementById(`name_${platform}`);
                const urlInput = document.getElementById(`url_${platform}`);
                const iconSelect = document.getElementById(`icon_${platform}`);
                const sortInput = document.getElementById(`sort_${platform}`);
                const checkbox = document.getElementById(`enable_${platform}`);

                platformsData.push({
                    platform: platform,
                    name: nameInput ? nameInput.value.trim() : '',
                    url: urlInput ? urlInput.value.trim() : '',
                    icon_key: iconSelect ? iconSelect.value.trim() : platform,
                    sort_order: sortInput ? parseInt(sortInput.value, 10) || 0 : 0,
                    is_enabled: checkbox && checkbox.checked ? 1 : 0
                });
            });

            try {
                const res = await fetch('/api/social/update.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': getCsrfTokenSafe(),
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ platforms: platformsData }),
                });

                const json = await res.json();
                setButtonLoading(saveSocialBtn, false, 'Save Social Channels');

                if (json.success) {
                    showToast('Social channels updated successfully.', 'success');
                    if (socialStatusMsg) socialStatusMsg.textContent = 'Saved just now';
                } else {
                    showToast(json.message || 'Failed to update social channels.', 'error');
                }
            } catch (err) {
                console.error('❌ Social channels save error:', err);
                setButtonLoading(saveSocialBtn, false, 'Save Social Channels');
                showToast('Network error while saving social channels.', 'error');
            }
        });
    }
}

// ============================================================
// 7. UTILITIES & UI HELPERS
// ============================================================
function setButtonLoading(btn, isLoading, text) {
    if (!btn) return;
    btn.disabled = isLoading;
    if (isLoading) {
        btn.innerHTML = `<i class="fas fa-circle-notch fa-spin"></i> <span>${escapeHTML(text)}</span>`;
    } else {
        btn.innerHTML = `<i class="fas fa-save"></i> <span>${escapeHTML(text)}</span>`;
    }
}

function getCsrfTokenSafe() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

// ============================================================
// 8. ADMIN ACCOUNT & CREDENTIAL MANAGEMENT
// ============================================================
async function loadAccountData() {
    try {
        const res = await fetch('/api/admin/account.php', {
            method: 'GET',
            credentials: 'same-origin',
        });
        const json = await res.json();
        if (json.success && json.data) {
            const u = json.data;
            const displayEmailEl = document.getElementById('accountDisplayEmail');
            const lastLoginEl = document.getElementById('accountLastLogin');
            const pwdChangedEl = document.getElementById('accountPasswordChanged');

            if (displayEmailEl) displayEmailEl.textContent = u.email || '—';
            if (lastLoginEl) {
                lastLoginEl.textContent = u.last_login_at
                    ? new Date(u.last_login_at).toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' })
                    : 'Never recorded';
            }
            if (pwdChangedEl) {
                pwdChangedEl.textContent = u.password_changed_at
                    ? new Date(u.password_changed_at).toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' })
                    : 'Default';
            }
        }
    } catch (err) {
        console.error('❌ Failed to load account details:', err);
    }
}

function setupAccountForms() {
    // Change Email Form
    const changeEmailForm = document.getElementById('changeEmailForm');
    const btnSubmitEmail = document.getElementById('btnSubmitEmail');

    if (changeEmailForm) {
        changeEmailForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const newEmail = document.getElementById('new_email')?.value.trim();
            const currentPassword = document.getElementById('email_current_password')?.value;

            if (!newEmail || !currentPassword) {
                showToast('Please fill in both new email and current password.', 'warning');
                return;
            }

            if (btnSubmitEmail) {
                btnSubmitEmail.disabled = true;
                btnSubmitEmail.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> <span>Updating...</span>';
            }

            try {
                const res = await fetch('/api/admin/account.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': getCsrfTokenSafe(),
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        action: 'update_email',
                        new_email: newEmail,
                        current_password: currentPassword,
                    }),
                });
                const json = await res.json();
                if (res.ok && json.success) {
                    showToast(json.message || 'Email updated successfully.', 'success');
                    changeEmailForm.reset();
                    await loadAccountData();
                } else {
                    showToast(json.message || 'Failed to update email address.', 'error');
                }
            } catch (err) {
                console.error('❌ Error updating email:', err);
                showToast('Network error while updating email.', 'error');
            } finally {
                if (btnSubmitEmail) {
                    btnSubmitEmail.disabled = false;
                    btnSubmitEmail.innerHTML = '<i class="fas fa-envelope-circle-check"></i> <span>Update Email</span>';
                }
            }
        });
    }

    // Change Password Form
    const changePasswordForm = document.getElementById('changePasswordForm');
    const btnSubmitPassword = document.getElementById('btnSubmitPassword');

    if (changePasswordForm) {
        changePasswordForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const currentPassword = document.getElementById('pwd_current_password')?.value;
            const newPassword = document.getElementById('pwd_new_password')?.value;
            const confirmPassword = document.getElementById('pwd_confirm_password')?.value;

            if (!currentPassword || !newPassword || !confirmPassword) {
                showToast('Please fill in all password fields.', 'warning');
                return;
            }

            if (newPassword.length < 8) {
                showToast('New password must be at least 8 characters.', 'warning');
                return;
            }

            if (newPassword !== confirmPassword) {
                showToast('New password and confirmation do not match.', 'error');
                return;
            }

            if (btnSubmitPassword) {
                btnSubmitPassword.disabled = true;
                btnSubmitPassword.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> <span>Updating...</span>';
            }

            try {
                const res = await fetch('/api/admin/account.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': getCsrfTokenSafe(),
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        action: 'update_password',
                        current_password: currentPassword,
                        new_password: newPassword,
                        confirm_password: confirmPassword,
                    }),
                });
                const json = await res.json();
                if (res.ok && json.success) {
                    showToast(json.message || 'Password updated successfully.', 'success');
                    changePasswordForm.reset();
                    await loadAccountData();
                } else {
                    showToast(json.message || 'Failed to update password.', 'error');
                }
            } catch (err) {
                console.error('❌ Error updating password:', err);
                showToast('Network error while updating password.', 'error');
            } finally {
                if (btnSubmitPassword) {
                    btnSubmitPassword.disabled = false;
                    btnSubmitPassword.innerHTML = '<i class="fas fa-key"></i> <span>Update Password</span>';
                }
            }
        });
    }
}


