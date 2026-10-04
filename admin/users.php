<?php
// ============================================================
// USERS — Server-Side Protected Admin Page
// Redesigned as an Account & Access Management Workspace.
// Strictly maintains all DOM IDs and classes required by admin/js/users.js.
// ============================================================

require_once dirname(__DIR__) . '/api/auth/guard.php';

requireAdminPage('login.php');

$activeNav = 'users';
$pageTitle = 'User Accounts';

require __DIR__ . '/partials/layout_top.php';
?>

<!-- =====================================================
     1. WORKSPACE HEADER & CONTEXT BAR
====================================================== -->
<header class="admin-page-header">
    <div class="header-titles">
        <div class="breadcrumb" aria-label="breadcrumb">
            <a href="index.php">Admin Studio</a>
            <span class="breadcrumb-sep">/</span>
            <span style="color: var(--accent);">Users</span>
        </div>
        <h1 class="page-title">User Accounts &amp; Access Control</h1>
        <p class="page-description">Manage administrator credentials, editor permissions, and authorized team access.</p>
    </div>

    <div class="header-actions">
        <a href="#users" class="btn btn-primary" onclick="resetUserForm(); document.getElementById('user_name').focus();">
            <i class="fas fa-user-plus" aria-hidden="true"></i>
            <span>New User</span>
        </a>
    </div>
</header>


<!-- =====================================================
     2. SUMMARY METRICS RAIL
====================================================== -->
<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 24px;">
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Total Accounts</span>
            <i class="far fa-user stat-icon" aria-hidden="true"></i>
        </div>
        <div class="stat-value" id="statTotalUsersCount">0</div>
        <div class="stat-meta">Active registered profiles</div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Administrators</span>
            <i class="fas fa-shield-halved stat-icon" style="color: var(--accent);" aria-hidden="true"></i>
        </div>
        <div class="stat-value" id="statAdminUsersCount" style="color: var(--accent);">0</div>
        <div class="stat-meta">Full system &amp; publishing rights</div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Standard Users</span>
            <i class="fas fa-users stat-icon" style="color: var(--text-muted);" aria-hidden="true"></i>
        </div>
        <div class="stat-value" id="statRegularUsersCount">0</div>
        <div class="stat-meta">Restricted read access</div>
    </div>
</div>


<!-- =====================================================
     3. USERS DIRECTORY TABLE (TWO-TAB SPLIT)
====================================================== -->
<section class="table-container" aria-label="Users Directory">

    <!-- Status / Role Filter Tabs & Live Search -->
    <div style="padding: 16px 20px 0; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
        <div class="filter-tabs" id="userGroupTabs" role="tablist" aria-label="User group selection">
            <button type="button" class="filter-tab active" id="tabBtnAdmins" data-group="admins" role="tab" aria-selected="true" onclick="switchUserGroup('admins')">
                <i class="fas fa-shield-halved" style="margin-right: 6px;"></i>
                Administrators <span class="tab-counter" id="tabCountAdmins">0</span>
            </button>
            <button type="button" class="filter-tab" id="tabBtnMembers" data-group="members" role="tab" aria-selected="false" onclick="switchUserGroup('members')">
                <i class="fas fa-users" style="margin-right: 6px;"></i>
                Members <span class="tab-counter" id="tabCountMembers">0</span>
            </button>
        </div>

        <div class="search-input-wrapper" style="min-width: 260px;">
            <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
            <input type="text" id="userSearchInput" placeholder="Search by name or email..." aria-label="Search users" oninput="handleUserSearch(this.value)">
        </div>
    </div>

    <div class="table-responsive" style="margin-top: 12px;">
        <table class="admin-table">
            <thead id="usersTableHead">
                <tr>
                    <th>User / Name</th>
                    <th>Email Address</th>
                    <th>Status</th>
                    <th>Last Active</th>
                    <th>Joined</th>
                    <th style="text-align: right; min-width: 160px;">Actions</th>
                </tr>
            </thead>
            <tbody id="usersTableBody">
                <tr>
                    <td colspan="6" style="text-align: center; padding: 48px 20px; color: var(--text-muted);">
                        Loading user accounts...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

</section>



<!-- =====================================================
     4. USER ACCOUNT COMPOSER (FORM WORKSPACE)
====================================================== -->
<section class="admin-card" id="users" aria-label="User Account Editor">

    <div class="admin-card-header">
        <div>
            <h2 class="card-title" id="userFormTitle">Add New User</h2>
            <p class="card-subtitle">Create a new platform account or update administrative credentials.</p>
        </div>
    </div>

    <form id="userForm" novalidate>

        <input type="hidden" id="user_id">

        <div class="form-grid">
            <!-- Full Name -->
            <div class="form-group">
                <label for="user_name">Full Name <span style="color: var(--accent);">*</span></label>
                <input
                    type="text"
                    id="user_name"
                    class="form-control"
                    required
                    placeholder="e.g., Mohammed Alrashadi"
                    autocomplete="name"
                >
            </div>

            <!-- Email Address -->
            <div class="form-group">
                <label for="user_email">Email Address <span style="color: var(--accent);">*</span></label>
                <input
                    type="email"
                    id="user_email"
                    class="form-control"
                    required
                    placeholder="user@mohammedalrashadi.com"
                    autocomplete="email"
                >
            </div>
        </div>

        <div class="form-grid" style="margin-top: 14px;">
            <!-- Role Selection -->
            <div class="form-group">
                <label for="user_role">System Role <span style="color: var(--accent);">*</span></label>
                <select id="user_role" class="form-control" required>
                    <option value="user">Regular User</option>
                    <option value="admin">Administrator (Full Control)</option>
                </select>
                <span class="form-help">Admins can publish essays, manage projects, and configure settings.</span>
            </div>

            <!-- Password Field -->
            <div class="form-group">
                <label for="user_password" id="user_password_label">Password <span style="color: var(--accent);">*</span></label>
                <input
                    type="password"
                    id="user_password"
                    class="form-control"
                    placeholder="••••••••••••"
                    autocomplete="new-password"
                >
                <span class="form-help hidden" id="user_password_hint" style="color: var(--warning);">
                    Leave blank to preserve current password when editing.
                </span>
            </div>
        </div>

        <!-- Action Bar -->
        <div class="form-actions-bar" style="margin-top: 24px;">
            <button
                type="button"
                id="userCancelBtn"
                class="btn btn-secondary hidden"
                onclick="resetUserForm()"
            >
                <i class="fas fa-times"></i>
                <span>Cancel</span>
            </button>

            <button type="submit" id="userSubmitBtn" class="btn btn-primary">
                <i class="fas fa-user-plus"></i>
                <span>Add User</span>
            </button>
        </div>

    </form>

</section>

<!-- =====================================================
     5. USER ENGAGEMENT & ACTIVITY DRAWER / MODAL
====================================================== -->
<div id="userDetailModal" class="modal" aria-hidden="true" role="dialog" aria-labelledby="modalUserName">
    <div class="modal-dialog" style="max-width: 720px; max-height: 88vh; display: flex; flex-direction: column;">
        <div class="modal-header">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div id="modalUserAvatar" class="admin-monogram-sm" style="width: 38px; height: 38px; font-size: 16px;">U</div>
                <div>
                    <h3 class="modal-title" id="modalUserName" style="margin: 0; font-size: 16px;">User Details</h3>
                    <div style="display: flex; align-items: center; gap: 8px; font-size: 12px; color: var(--text-muted); margin-top: 2px;">
                        <span id="modalUserEmail">user@example.com</span>
                        <span>•</span>
                        <span id="modalUserRole" class="role-badge role-user">User</span>
                    </div>
                </div>
            </div>
            <button type="button" class="modal-close" onclick="closeUserDetailModal()" aria-label="Close modal">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="modal-body" style="overflow-y: auto; display: flex; flex-direction: column; gap: 20px;">
            <!-- Live Engagement Metrics Rail -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(95px, 1fr)); gap: 10px;">
                <div style="background: var(--bg-canvas); border: 1px solid var(--border-subtle); padding: 10px; border-radius: var(--radius-sm); text-align: center;">
                    <div style="font-size: 10px; text-transform: uppercase; color: var(--text-muted);">Library</div>
                    <div id="modalStatLibrary" style="font-size: 18px; font-weight: 700; font-family: var(--font-mono); color: var(--accent);">0</div>
                </div>
                <div style="background: var(--bg-canvas); border: 1px solid var(--border-subtle); padding: 10px; border-radius: var(--radius-sm); text-align: center;">
                    <div style="font-size: 10px; text-transform: uppercase; color: var(--text-muted);">Downloads</div>
                    <div id="modalStatDownloads" style="font-size: 18px; font-weight: 700; font-family: var(--font-mono); color: var(--success);">0</div>
                </div>
                <div style="background: var(--bg-canvas); border: 1px solid var(--border-subtle); padding: 10px; border-radius: var(--radius-sm); text-align: center;">
                    <div style="font-size: 10px; text-transform: uppercase; color: var(--text-muted);">Bookmarks</div>
                    <div id="modalStatBookmarks" style="font-size: 18px; font-weight: 700; font-family: var(--font-mono); color: var(--warning);">0</div>
                </div>
                <div style="background: var(--bg-canvas); border: 1px solid var(--border-subtle); padding: 10px; border-radius: var(--radius-sm); text-align: center;">
                    <div style="font-size: 10px; text-transform: uppercase; color: var(--text-muted);">Likes</div>
                    <div id="modalStatLikes" style="font-size: 18px; font-weight: 700; font-family: var(--font-mono); color: var(--danger);">0</div>
                </div>
                <div style="background: var(--bg-canvas); border: 1px solid var(--border-subtle); padding: 10px; border-radius: var(--radius-sm); text-align: center;">
                    <div style="font-size: 10px; text-transform: uppercase; color: var(--text-muted);">Reads</div>
                    <div id="modalStatReads" style="font-size: 18px; font-weight: 700; font-family: var(--font-mono); color: var(--accent);">0</div>
                </div>
                <div style="background: var(--bg-canvas); border: 1px solid var(--border-subtle); padding: 10px; border-radius: var(--radius-sm); text-align: center;">
                    <div style="font-size: 10px; text-transform: uppercase; color: var(--text-muted);">Activities</div>
                    <div id="modalStatActivities" style="font-size: 18px; font-weight: 700; font-family: var(--font-mono); color: var(--text-primary);">0</div>
                </div>
            </div>

            <!-- Profile Preferences Summary -->
            <div style="background: var(--bg-canvas); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); padding: 14px;">
                <h4 style="margin: 0 0 10px 0; font-size: 12px; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-muted);">Profile Preferences</h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; font-size: 12.5px;">
                    <div><span style="color: var(--text-muted);">Theme:</span> <strong id="modalThemePref" style="color: var(--text-primary);">—</strong></div>
                    <div><span style="color: var(--text-muted);">Language:</span> <strong id="modalLangPref" style="color: var(--text-primary);">—</strong></div>
                    <div><span style="color: var(--text-muted);">Visibility:</span> <strong id="modalVisPref" style="color: var(--text-primary);">—</strong></div>
                    <div><span style="color: var(--text-muted);">Member Since:</span> <strong id="modalCreatedAt" style="color: var(--text-primary);">—</strong></div>
                </div>
                <div id="modalBioContainer" style="margin-top: 10px; font-size: 12.5px; color: var(--text-secondary); border-top: 1px solid var(--border-subtle); padding-top: 8px;">
                    <span style="color: var(--text-muted);">Bio:</span> <span id="modalBioText">No bio provided.</span>
                </div>
            </div>

            <!-- Recent Activity Timeline -->
            <div>
                <h4 style="margin: 0 0 10px 0; font-size: 12px; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-muted);">Recent Activity History</h4>
                <ul id="modalActivityList" style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 8px; font-size: 12.5px;">
                    <li style="color: var(--text-muted); padding: 8px 0;">No activities logged yet.</li>
                </ul>
            </div>

            <!-- Claimed Digital Products / Downloads -->
            <div>
                <h4 style="margin: 0 0 10px 0; font-size: 12px; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-muted);">Claimed Library &amp; Downloads</h4>
                <div id="modalLibraryList" style="display: flex; flex-direction: column; gap: 8px; font-size: 12.5px;">
                    <span style="color: var(--text-muted);">No products claimed or downloaded.</span>
                </div>
            </div>
        </div>

        <div class="modal-footer" style="padding-top: 14px; margin-top: 10px;">
            <button type="button" class="btn btn-secondary btn-sm" onclick="closeUserDetailModal()">Close</button>
        </div>
    </div>
</div>

<?php
$pageScripts = ['js/users.js'];
require __DIR__ . '/partials/layout_bottom.php';
?>
