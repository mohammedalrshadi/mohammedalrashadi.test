// ============================================================
// ADMIN — USERS PAGE (users.php)
// Two-tab architecture: Administrators vs Members
// Real-time search, platform activity subqueries, role elevation/demotion
// ============================================================

let allUsers = [];
let currentGroup = 'admins'; // 'admins' | 'members'
let userSearchQuery = '';
let currentAdminId = null;

// Safely set an element's text by id (no-op if the element is missing).
// Defined locally: users.php only loads js/users.js, and setText was
// previously defined only inside dashboard.js / reviews.js.
function setText(id, value) {
    const el = document.getElementById(id);
    if (el) {
        el.textContent = value;
    }
}

// ============================================================
// INITIALIZATION
// ============================================================

document.addEventListener('DOMContentLoaded', async () => {
    const authenticated = await checkAdminAuthentication();
    if (!authenticated) return;

    // Read deep-link group parameter from URL (?group=admins|members)
    const urlParams = new URLSearchParams(window.location.search);
    const initialGroup = urlParams.get('group');
    if (initialGroup === 'members') {
        currentGroup = 'members';
    } else {
        currentGroup = 'admins';
    }

    const userForm = document.getElementById('userForm');
    if (userForm) {
        userForm.addEventListener('submit', handleUserSubmit);
    }

    // Modal background / Esc handlers
    const modal = document.getElementById('userDetailModal');
    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeUserDetailModal();
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal.classList.contains('active')) {
                closeUserDetailModal();
            }
        });
    }

    // --------------------------------------------------------
    // SEC-002: Delegated action handler for the users table.
    // Buttons carry data-action / data-id / data-role instead of
    // embedding user-supplied strings inside onclick="...".
    // User names are resolved from allUsers[] by numeric id here,
    // so they never become executable JavaScript.
    // --------------------------------------------------------
    const tableContainer = document.getElementById('usersTableBody');
    if (tableContainer) {
        tableContainer.addEventListener('click', (e) => {
            const btn = e.target.closest('button[data-action]');
            if (!btn) return;

            const action = btn.dataset.action;
            const userId = Number(btn.dataset.id);
            const userRecord = allUsers.find(u => Number(u.id) === userId);
            const userName = userRecord ? (userRecord.name || 'User #' + userId) : 'User #' + userId;

            if (action === 'inspect') {
                openUserDetailModal(userId);
            } else if (action === 'verify') {
                markUserVerified(userId, userName);
            } else if (action === 'role') {
                const newRole = btn.dataset.role;
                handleRoleChange(userId, userName, newRole);
            } else if (action === 'edit') {
                editUserById(userId);
            } else if (action === 'delete') {
                deleteUser(userId);
            }
        });
    }

    switchUserGroup(currentGroup, false);
    await loadUsers();
});

// ============================================================
// TAB NAVIGATION & DEEP-LINKING
// ============================================================

function switchUserGroup(group, updateUrl = true) {
    currentGroup = (group === 'members') ? 'members' : 'admins';

    // Update Tab UI
    const tabAdmins = document.getElementById('tabBtnAdmins');
    const tabMembers = document.getElementById('tabBtnMembers');

    if (tabAdmins && tabMembers) {
        if (currentGroup === 'admins') {
            tabAdmins.classList.add('active');
            tabAdmins.setAttribute('aria-selected', 'true');
            tabMembers.classList.remove('active');
            tabMembers.setAttribute('aria-selected', 'false');
        } else {
            tabMembers.classList.add('active');
            tabMembers.setAttribute('aria-selected', 'true');
            tabAdmins.classList.remove('active');
            tabAdmins.setAttribute('aria-selected', 'false');
        }
    }

    // Update Table Headers
    const thead = document.getElementById('usersTableHead');
    if (thead) {
        if (currentGroup === 'admins') {
            thead.innerHTML = `
                <tr>
                    <th>Administrator</th>
                    <th>Email Address</th>
                    <th>Status</th>
                    <th>Last Active</th>
                    <th>Joined</th>
                    <th style="text-align: right; min-width: 170px;">Actions</th>
                </tr>
            `;
        } else {
            thead.innerHTML = `
                <tr>
                    <th>Member</th>
                    <th>Email Address</th>
                    <th>Platform Activity</th>
                    <th>Status</th>
                    <th>Last Active</th>
                    <th>Joined</th>
                    <th style="text-align: right; min-width: 170px;">Actions</th>
                </tr>
            `;
        }
    }

    // Update URL query string
    if (updateUrl && window.history && window.history.replaceState) {
        const url = new URL(window.location.href);
        url.searchParams.set('group', currentGroup);
        window.history.replaceState({}, '', url.toString());
    }

    // Sync composer form role default
    const roleInput = document.getElementById('user_role');
    const formTitle = document.getElementById('userFormTitle');
    const userIdInput = document.getElementById('user_id');
    if (roleInput && formTitle && (!userIdInput || !userIdInput.value)) {
        if (currentGroup === 'admins') {
            roleInput.value = 'admin';
            formTitle.textContent = 'Add Administrator';
        } else {
            roleInput.value = 'user';
            formTitle.textContent = 'Add Member';
        }
    }

    renderUsersTable();
}

// Live search handler
function handleUserSearch(val) {
    userSearchQuery = (val || '').trim().toLowerCase();
    renderUsersTable();
}

// ============================================================
// USERS — LOAD DATA FROM API
// ============================================================

async function loadUsers() {
    const tbody = document.getElementById('usersTableBody');
    if (tbody && (!allUsers || allUsers.length === 0)) {
        renderSkeletonRows(tbody, 5);
    }

    try {
        const res = await fetch('/api/users/list.php', {
            method: 'GET',
            credentials: 'same-origin',
        });
        const result = await res.json();
        if (!result.success) {
            throw new Error(result.message || 'Failed to fetch user directory.');
        }

        allUsers = result.data || [];
        if (result.current_admin_id) {
            currentAdminId = result.current_admin_id;
        }

        // Update Global Stats
        const adminCount = allUsers.filter(u => u.role === 'admin').length;
        const memberCount = allUsers.filter(u => u.role === 'user').length;

        setText('statTotalUsersCount', allUsers.length);
        setText('statAdminUsersCount', adminCount);
        setText('statRegularUsersCount', memberCount);
        setText('tabCountAdmins', adminCount);
        setText('tabCountMembers', memberCount);

        renderUsersTable();

    } catch (err) {
        console.error('❌ Error loading users:', err);
        if (tbody) {
            renderEmptyState(tbody, 6, {
                icon: 'fas fa-triangle-exclamation',
                message: 'Failed to load user records. ' + (err.message || ''),
            });
        }
        showToast('Error loading users: ' + err.message, 'error');
    }
}

// ============================================================
// RENDER ACTIVE TAB DIRECTORY
// ============================================================

function renderUsersTable() {
    const tbody = document.getElementById('usersTableBody');
    if (!tbody) return;

    tbody.innerHTML = '';

    const targetRole = (currentGroup === 'admins') ? 'admin' : 'user';

    // 1. Filter by role
    let filtered = allUsers.filter(u => u.role === targetRole);

    // 2. Filter by search query
    if (userSearchQuery !== '') {
        filtered = filtered.filter(u => {
            const name = (u.name || '').toLowerCase();
            const email = (u.email || '').toLowerCase();
            return name.includes(userSearchQuery) || email.includes(userSearchQuery);
        });
    }

    const colCount = (currentGroup === 'admins') ? 6 : 7;

    // Empty state
    if (filtered.length === 0) {
        const isSearch = userSearchQuery !== '';
        renderEmptyState(tbody, colCount, {
            icon: currentGroup === 'admins' ? 'fas fa-shield-halved' : 'fas fa-users',
            message: isSearch
                ? `No ${currentGroup} match "${userSearchQuery}".`
                : `No ${currentGroup} registered yet.`,
            ctaLabel: isSearch ? '' : (currentGroup === 'admins' ? 'Add Administrator' : 'Add Member'),
            ctaHref: '#users',
        });
        return;
    }

    filtered.forEach(user => {
        const tr = document.createElement('tr');
        const isSelf = currentAdminId !== null && Number(user.id) === Number(currentAdminId);
        const name = escapeHTML(user.name || 'Unnamed User');
        const email = escapeHTML(user.email || '');

        const status = user.status || 'active';
        let statusBadge = `<span class="badge badge-success">Active</span>`;
        if (status === 'suspended') {
            statusBadge = `<span class="badge badge-warning">Suspended</span>`;
        } else if (status === 'banned') {
            statusBadge = `<span class="badge badge-error">Banned</span>`;
        }

        const joinedDate = user.created_at
            ? new Date(user.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
            : '—';

        const lastActive = user.last_login_at
            ? new Date(user.last_login_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
            : '<span style="color:var(--text-muted);">Never</span>';

        let rowHtml = `
            <td class="table-cell-strong">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span>${name}</span>
                    ${isSelf ? '<span class="self-badge" style="background:var(--color-primary-container); color:var(--color-on-primary-container); font-size:10px; font-weight:700; padding:2px 6px; border-radius:4px;">You</span>' : ''}
                </div>
            </td>
            <td class="table-cell-muted font-mono" style="font-size:12.5px;">
                ${email}
            </td>
        `;

        // If members tab: include platform activity counts
        if (currentGroup === 'members') {
            const b = Number(user.bookmarks_count || 0);
            const l = Number(user.likes_count || 0);
            const d = Number(user.downloads_count || 0);

            rowHtml += `
                <td>
                    <div style="display: inline-flex; align-items: center; gap: 6px; font-size: 11px; font-family: var(--font-mono);">
                        <span title="${b} Bookmarks" style="color: var(--warning); display: inline-flex; align-items: center; gap: 3px;">
                            <i class="fas fa-bookmark" style="font-size: 10px;"></i>${b}
                        </span>
                        <span style="color: var(--border-medium);">|</span>
                        <span title="${l} Likes" style="color: var(--danger); display: inline-flex; align-items: center; gap: 3px;">
                            <i class="fas fa-heart" style="font-size: 10px;"></i>${l}
                        </span>
                        <span style="color: var(--border-medium);">|</span>
                        <span title="${d} Downloads" style="color: var(--success); display: inline-flex; align-items: center; gap: 3px;">
                            <i class="fas fa-download" style="font-size: 10px;"></i>${d}
                        </span>
                    </div>
                </td>
            `;
        }

        const isVerified = Boolean(user.email_verified_at);
        const verifyBadge = isVerified
            ? `<span class="badge" style="background:rgba(34,197,94,0.12); color:#22c55e; border:1px solid rgba(34,197,94,0.3); font-size:10px; padding:2px 6px; border-radius:4px;" title="Verified: ${escapeHTML(user.email_verified_at)}"><i class="fas fa-check" style="font-size:9px; margin-right:3px;"></i>Verified</span>`
            : `<span class="badge" style="background:rgba(234,179,8,0.12); color:#eab308; border:1px solid rgba(234,179,8,0.3); font-size:10px; padding:2px 6px; border-radius:4px;"><i class="fas fa-clock" style="font-size:9px; margin-right:3px;"></i>Unverified</span>`;

        // SEC-002: Action buttons use data-* attributes only.
        // No user-controlled string (name, email) is embedded in onclick="..."
        // The delegated listener in DOMContentLoaded resolves names from allUsers[].
        rowHtml += `
            <td>
                <div style="display:flex; flex-direction:column; gap:4px; align-items:flex-start;">
                    ${statusBadge}
                    ${verifyBadge}
                </div>
            </td>
            <td style="font-size: 12px;">${lastActive}</td>
            <td style="font-size: 12px; color: var(--text-muted);">${joinedDate}</td>
            <td style="text-align: right;">
                <div style="display: inline-flex; align-items: center; gap: 6px; justify-content: flex-end;">
                    <button type="button"
                            class="btn-action btn-inspect"
                            style="color: var(--accent);"
                            title="Inspect Profile &amp; Details"
                            data-action="inspect"
                            data-id="${user.id}">
                        <i class="fas fa-eye"></i>
                    </button>

                    ${!isVerified ? `
                    <button type="button"
                            class="btn-action"
                            style="color: #22c55e;"
                            title="Mark as Verified"
                            data-action="verify"
                            data-id="${user.id}">
                        <i class="fas fa-check-circle"></i>
                    </button>` : ''}

                    ${user.role === 'admin'
                ? `<button type="button"
                                   class="btn-action"
                                   style="color: var(--warning);"
                                   title="${isSelf ? 'Cannot demote yourself' : 'Demote to Member'}"
                                   ${isSelf ? 'disabled' : ''}
                                   data-action="role"
                                   data-id="${user.id}"
                                   data-role="user">
                                <i class="fas fa-arrow-down" style="font-size: 11px;"></i>
                           </button>`
                : `<button type="button"
                                   class="btn-action"
                                   style="color: var(--accent);"
                                   title="Promote to Administrator"
                                   data-action="role"
                                   data-id="${user.id}"
                                   data-role="admin">
                                <i class="fas fa-arrow-up" style="font-size: 11px;"></i>
                           </button>`
            }

                    <button type="button"
                            class="btn-action btn-edit"
                            title="Edit Account"
                            data-action="edit"
                            data-id="${user.id}">
                        <i class="fas fa-edit"></i>
                    </button>

                    <button type="button"
                            class="btn-action btn-delete"
                            title="${isSelf ? 'Cannot delete your own account' : 'Delete Account'}"
                            ${isSelf ? 'disabled' : ''}
                            data-action="delete"
                            data-id="${user.id}">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        `;

        tr.innerHTML = rowHtml;
        tbody.appendChild(tr);
    });
}

// ============================================================
// ROLE TRANSITION WITH CONFIRMATION
// ============================================================

async function handleRoleChange(userId, userName, newRole) {
    const isPromoting = (newRole === 'admin');
    const promptMessage = isPromoting
        ? `Are you sure you want to promote "${userName}" to an Administrator?\n\nThey will gain full access to publishing, analytics, system configuration, and user management.`
        : `Are you sure you want to demote "${userName}" from Administrator to Member?\n\nThey will immediately lose access to the Admin Studio.`;

    if (!confirm(promptMessage)) {
        return;
    }

    showLoading(true);

    try {
        const targetUser = allUsers.find(u => Number(u.id) === Number(userId));
        if (!targetUser) throw new Error('User record not found.');

        const res = await fetch('/api/users/update.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                id: Number(userId),
                name: targetUser.name,
                email: targetUser.email,
                role: newRole,
                status: targetUser.status || 'active',
            })
        });

        const result = await res.json();
        if (!result.success) {
            throw new Error(result.message || 'Failed to update user role.');
        }

        showToast(isPromoting ? `"${userName}" has been promoted to Administrator.` : `"${userName}" has been demoted to Member.`, 'success');
        await loadUsers();

    } catch (err) {
        console.error('❌ Role update error:', err);
        showToast(err.message, 'error');
    } finally {
        showLoading(false);
    }
}

// ============================================================
// CREATE & UPDATE ACCOUNT FORM
// ============================================================

async function handleUserSubmit(e) {
    e.preventDefault();
    showLoading(true);

    try {
        const id = document.getElementById('user_id').value;
        const name = document.getElementById('user_name').value.trim();
        const email = document.getElementById('user_email').value.trim();
        const password = document.getElementById('user_password').value;
        const role = document.getElementById('user_role').value;
        const isUpdate = !!id;

        if (!isUpdate && password.length < 8) {
            throw new Error('Password must be at least 8 characters long.');
        }

        const payload = { name, email, role };
        if (isUpdate) {
            payload.id = parseInt(id, 10);
        }
        if (password !== '') {
            payload.password = password;
        }

        const url = isUpdate ? '/api/users/update.php' : '/api/users/create.php';

        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        });

        const result = await response.json();
        if (!result.success) {
            throw new Error(result.message || 'Failed to save user.');
        }

        resetUserForm();
        await loadUsers();
        showToast(isUpdate ? 'User updated successfully.' : 'User account created successfully.', 'success');

    } catch (err) {
        console.error('❌ Save user error:', err);
        showToast('Error: ' + err.message, 'error');
    } finally {
        showLoading(false);
    }
}

function editUserById(userId) {
    const user = allUsers.find(u => Number(u.id) === Number(userId));
    if (user) {
        editUser(user);
    }
}

function editUser(user) {
    document.getElementById('user_id').value = user.id;
    document.getElementById('user_name').value = user.name || '';
    document.getElementById('user_email').value = user.email || '';
    document.getElementById('user_password').value = '';
    document.getElementById('user_role').value = user.role;

    document.getElementById('userFormTitle').textContent = 'Edit Account: ' + (user.name || user.email);
    document.getElementById('user_password_label').textContent = 'New Password (optional)';
    document.getElementById('user_password_hint').classList.remove('hidden');
    document.getElementById('userSubmitBtn').innerHTML = '<i class="fas fa-save"></i> Save Changes';
    document.getElementById('userCancelBtn').classList.remove('hidden');

    document.getElementById('users').scrollIntoView({ behavior: 'smooth' });
}

function resetUserForm() {
    const form = document.getElementById('userForm');
    if (form) form.reset();

    document.getElementById('user_id').value = '';
    document.getElementById('user_password_label').textContent = 'Password';
    document.getElementById('user_password_hint').classList.add('hidden');
    document.getElementById('userSubmitBtn').innerHTML = '<i class="fas fa-user-plus"></i> Save Account';
    document.getElementById('userCancelBtn').classList.add('hidden');

    // Default to active tab
    const roleInput = document.getElementById('user_role');
    const title = document.getElementById('userFormTitle');
    if (currentGroup === 'admins') {
        if (roleInput) roleInput.value = 'admin';
        if (title) title.textContent = 'Add Administrator';
    } else {
        if (roleInput) roleInput.value = 'user';
        if (title) title.textContent = 'Add Member';
    }
}

async function deleteUser(id) {
    const user = allUsers.find(u => Number(u.id) === Number(id));
    const name = user ? user.name : `User #${id}`;

    if (!confirm(`Are you sure you want to delete the account for "${name}"?\n\nThis permanently removes access and cascades user preferences. This action cannot be undone.`)) {
        return;
    }

    showLoading(true);

    try {
        const response = await fetch('/api/users/delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
            },
            credentials: 'same-origin',
            body: JSON.stringify({ id: parseInt(id, 10) }),
        });

        const result = await response.json();
        if (!result.success) {
            throw new Error(result.message || 'Failed to delete user.');
        }

        await loadUsers();
        showToast('User deleted successfully.', 'success');

    } catch (err) {
        console.error('❌ Delete user error:', err);
        showToast('Error: ' + err.message, 'error');
    } finally {
        showLoading(false);
    }
}

// ============================================================
// USERS — INSPECT DETAIL MODAL
// ============================================================

async function openUserDetailModal(userId) {
    const modal = document.getElementById('userDetailModal');
    if (!modal) return;

    setText('modalUserName', 'Loading details...');
    setText('modalUserEmail', '...');
    setText('modalUserRole', '...');
    setText('modalStatLibrary', '0');
    setText('modalStatDownloads', '0');
    setText('modalStatBookmarks', '0');
    setText('modalStatLikes', '0');
    setText('modalStatReads', '0');
    setText('modalStatActivities', '0');
    setText('modalThemePref', '—');
    setText('modalLangPref', '—');
    setText('modalVisPref', '—');
    setText('modalCreatedAt', '—');
    setText('modalBioText', '—');

    const actList = document.getElementById('modalActivityList');
    if (actList) actList.innerHTML = '<li style="color: var(--text-muted); padding: 8px 0;">Loading activity events...</li>';

    const libList = document.getElementById('modalLibraryList');
    if (libList) libList.innerHTML = '<span style="color: var(--text-muted);">Loading items...</span>';

    modal.classList.add('active');

    try {
        const response = await fetch(`/api/users/detail.php?id=${encodeURIComponent(userId)}`, {
            method: 'GET',
            credentials: 'same-origin',
        });
        const result = await response.json();
        if (!result.success || !result.data) {
            throw new Error(result.message || 'Failed to load user details.');
        }

        const data = result.data;
        const u = data.user || {};
        const p = data.profile || {};
        const s = data.stats || {};

        const displayName = u.name || u.email || 'User';
        setText('modalUserName', displayName);
        setText('modalUserEmail', u.email || 'No email');
        setText('modalUserRole', u.role === 'admin' ? 'Administrator' : 'Member');

        const avatarEl = document.getElementById('modalUserAvatar');
        if (avatarEl) {
            avatarEl.textContent = displayName.trim().charAt(0).toUpperCase() || 'U';
        }

        setText('modalStatLibrary', s.library || 0);
        setText('modalStatDownloads', s.downloads || 0);
        setText('modalStatBookmarks', s.bookmarks || 0);
        setText('modalStatLikes', s.likes || 0);
        setText('modalStatReads', s.reading || 0);
        setText('modalStatActivities', s.activities || 0);

        setText('modalThemePref', (p.theme_preference || 'dark').toUpperCase());
        setText('modalLangPref', (p.language_preference || 'en').toUpperCase());
        setText('modalVisPref', (p.activity_visibility || 'private').toUpperCase());
        setText('modalCreatedAt', u.created_at ? new Date(u.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : 'Unknown');
        setText('modalBioText', p.bio ? p.bio : 'No bio provided.');

        if (actList) {
            actList.innerHTML = '';
            const acts = data.recent_activities || [];
            if (acts.length === 0) {
                actList.innerHTML = '<li style="color: var(--text-muted); padding: 8px 0;">No activities logged yet.</li>';
            } else {
                acts.forEach(a => {
                    const li = document.createElement('li');
                    li.style.cssText = 'padding: 8px 10px; background: var(--bg-canvas); border-radius: var(--radius-sm); border: 1px solid var(--border-subtle); display: flex; justify-content: space-between; align-items: center; gap: 10px;';
                    const timeStr = a.created_at ? new Date(a.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : '';
                    li.innerHTML = `
                        <div style="min-width: 0;">
                            <span style="font-weight: 600; color: var(--text-primary);">${escapeHTML(a.activity_type || 'Activity')}</span>: 
                            <span style="color: var(--text-secondary);">${escapeHTML(a.description || a.content_type || '')}</span>
                        </div>
                        <span style="font-family: var(--font-mono); font-size: 11px; color: var(--text-muted); white-space: nowrap;">${timeStr}</span>
                    `;
                    actList.appendChild(li);
                });
            }
        }

        if (libList) {
            libList.innerHTML = '';
            const items = [...(data.library || []), ...(data.downloads || [])];
            if (items.length === 0) {
                libList.innerHTML = '<span style="color: var(--text-muted);">No products claimed or downloaded.</span>';
            } else {
                items.forEach(it => {
                    const row = document.createElement('div');
                    row.style.cssText = 'padding: 8px 10px; background: var(--bg-canvas); border-radius: var(--radius-sm); border: 1px solid var(--border-subtle); display: flex; justify-content: space-between; align-items: center;';
                    const title = it.product_title || `Product #${it.product_id}`;
                    const time = it.created_at || it.downloaded_at;
                    const dateStr = time ? new Date(time).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '';
                    row.innerHTML = `
                        <span style="font-weight: 500; color: var(--text-primary);"><i class="fas fa-box-open" style="margin-right: 6px; color: var(--accent);"></i>${escapeHTML(title)}</span>
                        <span style="font-size: 11px; color: var(--text-muted); font-family: var(--font-mono);">${dateStr}</span>
                    `;
                    libList.appendChild(row);
                });
            }
        }

    } catch (err) {
        console.error('Error loading user details:', err);
        showToast('Error loading user details: ' + err.message, 'error');
    }
}

function closeUserDetailModal() {
    const modal = document.getElementById('userDetailModal');
    if (modal) {
        modal.classList.remove('active');
    }
}

// Mark user as verified (Admin Action)
async function markUserVerified(userId, userName) {
    if (!confirm(`Mark email as verified for ${userName}?`)) {
        return;
    }

    try {
        const res = await fetch('/api/users/verify.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
            },
            body: JSON.stringify({ id: Number(userId) }),
        });

        const result = await res.json();
        if (result.success) {
            showToast(result.message || 'User marked as verified.', 'success');
            await loadUsers();
        } else {
            showToast(result.message || 'Failed to verify user.', 'error');
        }
    } catch (err) {
        console.error('Error verifying user:', err);
        showToast('Network error while marking user as verified.', 'error');
    }
}

