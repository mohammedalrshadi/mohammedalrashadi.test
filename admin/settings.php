<?php
// ============================================================
// ADMIN CONTROL CENTER — Mohammed Alrashadi Engineering Platform
// Server-Side Protected Master Control Center.
// Consolidates Platform Profile, Website & Branding, Home Showcase,
// Social Channels, and System Diagnostics into a unified workspace.
// ============================================================

require_once dirname(__DIR__) . '/api/auth/guard.php';
require_once dirname(__DIR__) . '/api/db.php';
require_once dirname(__DIR__) . '/includes/settings.php';

requireAdminPage('login.php');

$activeNav = 'settings';
$pageTitle = 'Settings';

$uploadDir = dirname(__DIR__) . '/uploads';
$isUploadWritable = is_dir($uploadDir) && is_writable($uploadDir);

require_once dirname(__DIR__) . '/api/helpers/stats_helper.php';

// Fetch real database entity metrics for the System tab via unified helper
$dbStats = [
    'articles' => 0,
    'projects' => 0,
    'reviews'  => 0,
    'users'    => 0,
    'social'   => 0,
    'showcase' => 0,
    'settings' => 0,
    'db_name'  => defined('DB_NAME') ? DB_NAME : 'u303927365_alrashadi',
    'version'  => 'Unknown',
];
$recentMigrations = [];

try {
    $pdo = getDB();
    $platformCounts = getPlatformEntityCounts($pdo);
    $dbStats['articles'] = $platformCounts['articles']['total'];
    $dbStats['projects'] = $platformCounts['projects']['total'];
    $dbStats['reviews']  = $platformCounts['reviews']['total'];
    $dbStats['users']    = $platformCounts['users']['total'];
    $dbStats['social']   = $platformCounts['social']['enabled'];
    $dbStats['showcase'] = $platformCounts['showcase']['enabled'];
    $dbStats['settings'] = $platformCounts['system']['settings'];
    $dbStats['version']  = $platformCounts['system']['db_version'];

    // Query schema_migrations ledger
    $checkMigTable = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schema_migrations' LIMIT 1");
    if ($checkMigTable && $checkMigTable->fetch()) {
        $stmtMig = $pdo->query("SELECT id, applied_at, applied_by FROM schema_migrations ORDER BY applied_at DESC LIMIT 10");
        $recentMigrations = $stmtMig->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    error_log('[settings.php] DB metrics warning: ' . $e->getMessage());
}

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
            <span style="color: var(--accent);">Settings</span>
        </div>
        <h1 class="page-title">Admin Settings</h1>
        <p class="page-description">Unified platform configuration, personal identity, verified social channels, and runtime system posture.</p>
    </div>

    <div class="header-actions">
        <a href="../index.php" target="_blank" rel="noopener noreferrer" class="btn btn-secondary">
            <span>View Live Site</span>
            <i class="fas fa-arrow-up-right-from-square" style="font-size: 11px;"></i>
        </a>
        <a href="run_migrations.php" class="btn btn-secondary">
            <i class="fas fa-wrench" aria-hidden="true"></i>
            <span>Run Migrations</span>
        </a>
    </div>
</header>

<!-- =====================================================
     2. CONTROL CENTER TABS NAVIGATION
====================================================== -->
<nav class="filter-tabs control-center-tabs" id="controlCenterTabs" role="tablist" aria-label="Settings Tabs">
    <button type="button" class="filter-tab active" data-tab="profile" role="tab" aria-selected="true" aria-controls="pane-profile">
        <i class="fas fa-user-gear"></i>
        <span>Platform Profile</span>
    </button>
    <button type="button" class="filter-tab" data-tab="website" role="tab" aria-selected="false" aria-controls="pane-website">
        <i class="fas fa-globe"></i>
        <span>Website &amp; Branding</span>
    </button>
    <button type="button" class="filter-tab" data-tab="showcase" role="tab" aria-selected="false" aria-controls="pane-showcase">
        <i class="fas fa-star"></i>
        <span>Home Showcase</span>
        <span class="tab-counter" id="showcaseCountBadge"><?= (int)$dbStats['showcase'] ?></span>
    </button>
    <button type="button" class="filter-tab" data-tab="about" role="tab" aria-selected="false" aria-controls="pane-about">
        <i class="fas fa-user-pen"></i>
        <span>About Content</span>
    </button>
    <button type="button" class="filter-tab" data-tab="social" role="tab" aria-selected="false" aria-controls="pane-social">
        <i class="fas fa-share-nodes"></i>
        <span>Social Channels</span>
        <span class="tab-counter" id="socialCountBadge"><?= (int)$dbStats['social'] ?></span>
    </button>
    <button type="button" class="filter-tab" data-tab="system" role="tab" aria-selected="false" aria-controls="pane-system">
        <i class="fas fa-server"></i>
        <span>System &amp; Diagnostics</span>
    </button>
    <button type="button" class="filter-tab" data-tab="email" role="tab" aria-selected="false" aria-controls="pane-email">
        <i class="fas fa-envelope-circle-check"></i>
        <span>Email &amp; SMTP</span>
    </button>
    <button type="button" class="filter-tab" data-tab="account" role="tab" aria-selected="false" aria-controls="pane-account">
        <i class="fas fa-user-shield"></i>
        <span>Account &amp; Security</span>
    </button>
</nav>

<div style="display: flex; flex-direction: column; gap: 28px; width: 100%;">

    <!-- =====================================================
         TAB 1: PLATFORM PROFILE & IDENTITY
    ====================================================== -->
    <div class="tab-pane active" id="pane-profile" role="tabpanel" aria-labelledby="tab-profile">

        <div class="profile-preview-card">
            <div class="profile-avatar-frame" id="profileAvatarContainer" title="Click to upload new portrait">
                <img id="avatarPreviewImg" src="/assets/profile_headshot.png" alt="Profile Avatar Preview" onerror="this.src='/uploads/placeholder.svg';">
                <div class="avatar-upload-overlay" id="avatarUploadOverlay">
                    <i class="fas fa-camera"></i>
                    <span>Change</span>
                </div>
                <div class="avatar-upload-spinner" id="avatarUploadSpinner">
                    <i class="fas fa-spinner fa-spin" style="font-size: 18px; color: var(--accent);"></i>
                </div>
                <input type="file" id="profileAvatarFileInput" accept="image/jpeg,image/png,image/webp" style="display: none;">
            </div>
            <div style="flex: 1; min-width: 0;">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 4px;">
                    <h2 style="font-size: 17px; font-weight: 700; color: var(--text-primary); margin: 0;" id="previewProfileName">Mohammed Alrashadi</h2>
                    <span class="status-badge status-published" id="previewProfileMonogram">MA</span>
                </div>
                <div style="font-size: 13px; color: var(--accent); font-family: var(--font-mono); margin-bottom: 4px;" id="previewProfileRole">Software Engineering Student</div>
                <div style="font-size: 12.5px; color: var(--text-muted); line-height: 1.4;" id="previewProfileMotto">Build. Learn. Experiment. Evolve.</div>
            </div>
        </div>

        <section class="admin-card" aria-label="Profile Identity Form">
            <div class="admin-card-header">
                <div>
                    <h2 class="card-title">Personal Engineering Identity</h2>
                    <p class="card-subtitle">Values configured here are reflected across the public About page, author bios, and footer credits.</p>
                </div>
                <span class="status-badge status-published">Single Source of Truth</span>
            </div>

            <form id="profileForm" novalidate>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 18px; margin-bottom: 20px;">
                    
                    <div class="form-group">
                        <label for="profile_name">Full Name <span style="color: var(--accent);">*</span></label>
                        <input type="text" id="profile_name" class="form-control" required placeholder="Mohammed Alrashadi">
                        <span class="form-help">Canonical display name for platform headers and author attribution.</span>
                    </div>

                    <div class="form-group">
                        <label for="profile_short_name">Short Name / Monogram <span style="color: var(--accent);">*</span></label>
                        <input type="text" id="profile_short_name" class="form-control" required placeholder="MA" maxlength="8">
                        <span class="form-help">Used for badge monograms, mobile navigation, and compact references.</span>
                    </div>

                    <div class="form-group">
                        <label for="profile_role">Professional &amp; Academic Role <span style="color: var(--accent);">*</span></label>
                        <input type="text" id="profile_role" class="form-control" required placeholder="Software Engineering Student">
                        <span class="form-help">Primary academic and engineering qualification subtitle.</span>
                    </div>

                    <div class="form-group">
                        <label for="profile_motto">Platform Architectural Motto</label>
                        <input type="text" id="profile_motto" class="form-control" placeholder="Build. Learn. Experiment. Evolve.">
                        <span class="form-help">Core guiding principle displayed in banners and editorial intros.</span>
                    </div>

                    <div class="form-group">
                        <label for="profile_location">Primary Location</label>
                        <input type="text" id="profile_location" class="form-control" placeholder="Riyadh, Saudi Arabia">
                        <span class="form-help">Academic or operational jurisdiction.</span>
                    </div>

                    <div class="form-group">
                        <label for="profile_current_focus">Current Technical Focus</label>
                        <input type="text" id="profile_current_focus" class="form-control" placeholder="Systems, Databases &amp; Backend">
                        <span class="form-help">Areas of active research, systems benchmarking, and deep exploration.</span>
                    </div>

                    <div class="form-group">
                        <label for="profile_education_stage">Education Stage</label>
                        <input type="text" id="profile_education_stage" class="form-control" placeholder="Software Engineering Student">
                        <span class="form-help">Academic journey stage.</span>
                    </div>

                    <div class="form-group">
                        <label for="profile_avatar_url">Avatar / Portrait URL</label>
                        <div style="display: flex; gap: 8px;">
                            <input type="text" id="profile_avatar_url" class="form-control" placeholder="/assets/profile_headshot.png">
                            <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('profileAvatarFileInput').click()" style="white-space: nowrap;">
                                <i class="fas fa-upload"></i> Upload
                            </button>
                        </div>
                        <span class="form-help">Click avatar above or upload high-res portrait (WebP, PNG, JPG under 5MB).</span>
                    </div>

                </div>

                <!-- Public Contact Email Section (Audit Decision 2 & User Additions) -->
                <div style="background: var(--bg-canvas); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); padding: 16px 20px; margin-bottom: 20px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 12px;">
                        <div>
                            <strong style="color: var(--text-primary); font-size: 13.5px; display: block;">Public Contact Email Address</strong>
                            <span style="font-size: 12px; color: var(--text-muted);">
                                Distinct from your login administrator email. Powers the public mailto link when enabled.
                            </span>
                        </div>
                        <label class="toggle-switch-label" for="profile_show_email" style="margin: 0;">
                            <span class="toggle-text" id="status_text_show_email">Hidden</span>
                            <input type="checkbox" id="profile_show_email" class="social-toggle">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <input type="email" id="profile_public_email" class="form-control" placeholder="contact@mohammedalrashadi.com" dir="ltr">
                        <span class="form-help">Leave empty or keep toggle disabled to omit email contact links from the public platform.</span>
                    </div>
                </div>

                <!-- Bios -->
                <div style="display: flex; flex-direction: column; gap: 18px; margin-bottom: 24px;">
                    <div class="form-group">
                        <label for="profile_bio_short">Short Editorial Abstract</label>
                        <textarea id="profile_bio_short" rows="3" class="form-control" placeholder="A personal engineering platform and research notebook focused on systems, databases, computing fundamentals, and backend architecture."></textarea>
                        <span class="form-help">Displayed on homepage hero, article author boxes, and metadata.</span>
                    </div>

                    <div class="form-group">
                        <label for="profile_bio_full">Extended Narrative Story &amp; Approach</label>
                        <textarea id="profile_bio_full" rows="6" class="form-control" placeholder="I am a software engineering student driven by curiosity for how computer systems and software architectures behave under real-world conditions..."></textarea>
                        <span class="form-help">Detailed narrative presented on the dedicated About page. Plain text or paragraphs.</span>
                    </div>
                </div>

                <div class="form-actions-bar">
                    <button type="submit" id="saveProfileBtn" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        <span>Save Profile Changes</span>
                    </button>
                    <span id="profileStatusMsg" style="font-size: 12.5px; color: var(--text-muted); font-family: var(--font-mono);"></span>
                </div>
            </form>
        </section>

    </div>

    <!-- =====================================================
         TAB 2: WEBSITE & BRANDING
    ====================================================== -->
    <div class="tab-pane" id="pane-website" role="tabpanel" aria-labelledby="tab-website">

        <section class="admin-card" aria-label="Website and Branding Form">
            <div class="admin-card-header">
                <div>
                    <h2 class="card-title">Website Parameters &amp; Brand Assets</h2>
                    <p class="card-subtitle">Global brand strings, canonical domains, and default search engine metadata.</p>
                </div>
                <span class="status-badge status-published">SEO &amp; Identity</span>
            </div>

            <form id="websiteForm" novalidate>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 18px; margin-bottom: 20px;">

                    <div class="form-group">
                        <label for="website_platform_name">Platform Title</label>
                        <input type="text" id="website_platform_name" class="form-control" placeholder="Mohammed Alrashadi">
                        <span class="form-help">Primary branding title displayed in the global navbar header.</span>
                    </div>

                    <div class="form-group">
                        <label for="website_platform_descriptor">Platform Descriptor</label>
                        <input type="text" id="website_platform_descriptor" class="form-control" placeholder="Engineering Studio">
                        <span class="form-help">Header subtitle/descriptor (e.g. Engineering Studio, Research Lab).</span>
                    </div>

                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="website_canonical_url">Canonical Production URL</label>
                        <input type="url" id="website_canonical_url" class="form-control" placeholder="https://mohammedalrashadi.com/" dir="ltr">
                        <span class="form-help">Base URL used for sitemaps, RSS feeds, and canonical SEO links.</span>
                    </div>

                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="website_platform_purpose">Platform Purpose Statement</label>
                        <textarea id="website_platform_purpose" rows="3" class="form-control" placeholder="This platform serves as an open personal engineering studio and research notebook — bringing together hands-on software projects, empirical benchmarks, technical writing, and a transparent learning journey."></textarea>
                        <span class="form-help">Core purpose statement rendered in public footers and platform guides.</span>
                    </div>

                </div>

                <div style="border-top: 1px solid var(--border-subtle); padding-top: 20px; margin-top: 10px; margin-bottom: 20px;">
                    <h3 style="font-size: 14px; font-weight: 600; color: var(--text-primary); margin-bottom: 14px;">Brand &amp; Meta Assets</h3>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px;">
                        <div class="form-group">
                            <label for="branding_monogram_url">Brand Logo</label>
                            <input type="text" id="branding_monogram_url" class="form-control" placeholder="/assets/logo/logo.png">
                            <span class="form-help">Navbar brand icon URL.</span>
                        </div>

                        <div class="form-group">
                            <label for="branding_favicon_url">Favicon URL</label>
                            <input type="text" id="branding_favicon_url" class="form-control" placeholder="/assets/logo/logo.png">
                            <span class="form-help">Browser tab icon URL (.ico or .png).</span>
                        </div>

                        <div class="form-group">
                            <label for="branding_og_image_url">Default Social Share Image (OG Image)</label>
                            <input type="text" id="branding_og_image_url" class="form-control" placeholder="/assets/logo/logo.png">
                            <span class="form-help">Default image when sharing pages on social networks (1200x630 recommended).</span>
                        </div>
                    </div>
                </div>

                <div style="border-top: 1px solid var(--border-subtle); padding-top: 20px; margin-top: 10px; margin-bottom: 24px;">
                    <h3 style="font-size: 14px; font-weight: 600; color: var(--text-primary); margin-bottom: 14px;">Default Search Engine Optimization</h3>
                    
                    <div style="display: flex; flex-direction: column; gap: 16px;">
                        <div class="form-group">
                            <label for="seo_default_title">Default Meta Title</label>
                            <input type="text" id="seo_default_title" class="form-control" placeholder="Mohammed Alrashadi // Systems &amp; Software Engineering">
                            <span class="form-help">Fallback title tag when individual pages do not specify a bespoke title.</span>
                        </div>

                        <div class="form-group">
                            <label for="seo_default_description">Default Meta Description</label>
                            <textarea id="seo_default_description" rows="3" class="form-control" placeholder="Systems, databases, computing fundamentals, and backend software engineering projects and research by Mohammed Alrashadi."></textarea>
                            <span class="form-help">Fallback meta description tag (150-160 characters recommended).</span>
                        </div>
                    </div>
                </div>

                <div class="form-actions-bar">
                    <button type="submit" id="saveWebsiteBtn" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        <span>Save Website Changes</span>
                    </button>
                    <span id="websiteStatusMsg" style="font-size: 12.5px; color: var(--text-muted); font-family: var(--font-mono);"></span>
                </div>
            </form>
        </section>

    </div>

    <!-- =====================================================
         TAB 3: HOME SHOWCASE CURATION
    ====================================================== -->
    <div class="tab-pane" id="pane-showcase" role="tabpanel" aria-labelledby="tab-showcase">

        <!-- Dedicated Showcase Studio Callout Card -->
        <section class="admin-card" style="margin-bottom: 24px; border-left: 3px solid var(--accent);">
            <div class="admin-card-header">
                <div>
                    <h2 class="card-title" style="display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-layer-group" style="color: var(--accent);"></i>
                        Dedicated Home Showcase Studio
                    </h2>
                    <p class="card-subtitle">Showcase curation is centralized in the dedicated studio with mixed-content support.</p>
                </div>
                <a href="showcase.php" class="btn btn-primary">
                    <i class="fas fa-arrow-up-right-from-square"></i>
                    <span>Open Showcase Studio</span>
                </a>
            </div>

            <div style="padding: 6px 0 10px 0; color: var(--text-secondary); font-size: 13.5px; line-height: 1.6;">
                <p style="margin: 0 0 14px 0;">
                    The live homepage features mixed content cards across all platform entities (Engineering Projects, Technical Articles, Store Catalog Products, and Visual Assets). Showcase sequencing, custom labels, and spotlight ordering are managed in the dedicated studio.
                </p>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-top: 16px;">
                    <div style="background: var(--bg-canvas); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); padding: 16px;">
                        <div style="font-weight: 600; color: var(--text-primary); margin-bottom: 6px; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-toggle-on" style="color: var(--accent);"></i>
                            In-Editor Showcase Toggles
                        </div>
                        <p style="font-size: 12.5px; color: var(--text-muted); margin: 0;">
                            When authoring or editing an Article, Engineering Project, or Store Product, toggle <strong>"Feature on Home Showcase"</strong> directly inside the edit form to immediately pin or unpin it.
                        </p>
                    </div>

                    <div style="background: var(--bg-canvas); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); padding: 16px;">
                        <div style="font-weight: 600; color: var(--text-primary); margin-bottom: 6px; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-arrow-down-short-wide" style="color: var(--accent);"></i>
                            Custom Sequence &amp; Reordering
                        </div>
                        <p style="font-size: 12.5px; color: var(--text-muted); margin: 0;">
                            In the <a href="showcase.php" style="color: var(--accent); text-decoration: underline;">Showcase Studio</a>, rearrange cards in any custom order, preview hero spotlights, and edit card badges and link targets.
                        </p>
                    </div>
                </div>
            </div>

            <div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--border-subtle); display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="showcase.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-star"></i>
                    <span>Manage Showcase Items (<?= (int)$dbStats['showcase'] ?> Active)</span>
                </a>
                <a href="projects.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-code-branch"></i>
                    <span>Engineering Projects</span>
                </a>
                <a href="articles.php" class="btn btn-secondary btn-sm">
                    <i class="far fa-file-alt"></i>
                    <span>Articles</span>
                </a>
                <a href="store.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-bag-shopping"></i>
                    <span>Store Catalog</span>
                </a>
            </div>
        </section>

    </div>

    <!-- =====================================================
         TAB: ABOUT CONTENT (Principles + Focus Area Tags)
    ====================================================== -->
    <div class="tab-pane" id="pane-about" role="tabpanel" aria-labelledby="tab-about">

        <div class="admin-notice-box">
            <i class="fas fa-circle-info" style="color: var(--accent); font-size: 16px; margin-top: 2px;"></i>
            <div>
                <strong>About Page Sections:</strong> Manage the "Engineering Principles" cards and "Focus Areas &amp; Tooling" tags shown on the public About page. Profile, bio, and motto are edited in the <strong>Platform Profile</strong> tab instead.
            </div>
        </div>

        <!-- Principles -->
        <section class="admin-card" style="margin-bottom: 24px;">
            <div class="admin-card-header">
                <div>
                    <h2 class="card-title">Engineering Principles</h2>
                    <p class="card-subtitle">The three (or more) principle cards shown under "Philosophy".</p>
                </div>
                <button type="button" class="btn btn-primary btn-sm" onclick="startNewAboutBlock('principle')">
                    <i class="fas fa-plus"></i> Add Principle
                </button>
            </div>
            <div id="principlesListContainer" style="padding: 4px 20px 20px;">
                <p style="color: var(--text-muted); font-size: 13px;"><i class="fas fa-spinner fa-spin"></i> Loading...</p>
            </div>
        </section>

        <!-- Focus Area Tags -->
        <section class="admin-card">
            <div class="admin-card-header">
                <div>
                    <h2 class="card-title">Focus Areas &amp; Tooling Tags</h2>
                    <p class="card-subtitle">Grouped tag chips (e.g. "Languages &amp; Core") shown under "Technical Exploration".</p>
                </div>
                <button type="button" class="btn btn-primary btn-sm" onclick="startNewAboutBlock('focus_tag')">
                    <i class="fas fa-plus"></i> Add Tag
                </button>
            </div>
            <div id="focusTagsListContainer" style="padding: 4px 20px 20px;">
                <p style="color: var(--text-muted); font-size: 13px;"><i class="fas fa-spinner fa-spin"></i> Loading...</p>
            </div>
        </section>

        <!-- Add/Edit Modal -->
        <div class="modal" id="aboutBlockModal" role="dialog" aria-modal="true" aria-labelledby="aboutBlockModalTitle" style="display: none;">
            <div class="modal-backdrop" onclick="closeAboutBlockModal()"></div>
            <div class="modal-dialog" style="max-width: 480px;">
                <div class="modal-header">
                    <h3 class="modal-title" id="aboutBlockModalTitle">Add Principle</h3>
                    <button type="button" class="btn-close" onclick="closeAboutBlockModal()" aria-label="Close"><i class="fas fa-times"></i></button>
                </div>
                <form id="aboutBlockForm" novalidate>
                    <div class="modal-body" style="padding: 20px; display: flex; flex-direction: column; gap: 14px;">
                        <input type="hidden" id="about_block_id" value="0">
                        <input type="hidden" id="about_block_type" value="principle">

                        <div class="form-group" id="about_group_label_wrap" style="display: none;">
                            <label for="about_group_label">Group Label <span style="color: var(--accent);">*</span></label>
                            <input type="text" id="about_group_label" class="form-control" placeholder="e.g., Languages &amp; Core" maxlength="100">
                        </div>

                        <div class="form-group" id="about_icon_wrap">
                            <label for="about_icon">Icon Name</label>
                            <input type="text" id="about_icon" class="form-control" placeholder="Material Symbols name, e.g. shield" maxlength="60">
                        </div>

                        <div class="form-group">
                            <label for="about_title">Title <span style="color: var(--accent);">*</span></label>
                            <input type="text" id="about_title" class="form-control" required maxlength="150" placeholder="e.g., Resilience First">
                        </div>

                        <div class="form-group" id="about_description_wrap">
                            <label for="about_description">Description</label>
                            <textarea id="about_description" rows="3" class="form-control" placeholder="Shown under the title on a principle card."></textarea>
                        </div>

                        <div class="form-group">
                            <label for="about_sort_order">Sort Order</label>
                            <input type="number" id="about_sort_order" class="form-control" value="0" step="1">
                        </div>
                    </div>
                    <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 8px;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="closeAboutBlockModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm" id="aboutBlockSaveBtn">
                            <i class="fas fa-save"></i> Save
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>

    <!-- =====================================================
         TAB 4: SOCIAL CHANNELS
    ====================================================== -->
    <div class="tab-pane" id="pane-social" role="tabpanel" aria-labelledby="tab-social">

        <div class="admin-notice-box">
            <i class="fas fa-circle-info" style="color: var(--accent); font-size: 16px; margin-top: 2px;"></i>
            <div>
                <strong>Public Visibility:</strong> Only enabled channels with valid URLs appear in the public site footer. Disabled or blank channels are automatically excluded.
            </div>
        </div>

        <section class="admin-card" aria-label="Social Channels Form">
            <div class="admin-card-header">
                <div>
                    <h2 class="card-title">Verified Social Channels &amp; Profiles</h2>
                    <p class="card-subtitle">Set direct profile URLs and toggle visibility on the public platform.</p>
                </div>
                <span class="status-badge status-published" id="activePlatformsCountBadge">0 Active</span>
            </div>

            <form id="socialForm" novalidate>
                <div class="social-platforms-list" id="socialPlatformsList">

                    <!-- 1. GitHub -->
                    <div class="social-platform-card" data-platform="github" style="display: flex; flex-direction: column; gap: 12px; align-items: stretch;">
                        <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border-subtle); padding-bottom: 10px;">
                            <div class="social-brand-info">
                                <span class="social-icon-wrapper">
                                    <i class="fa-brands fa-github"></i>
                                </span>
                                <div>
                                    <h3 class="social-platform-title">GitHub</h3>
                                    <span class="social-platform-slug">github.com</span>
                                </div>
                            </div>
                            <label class="toggle-switch-label" for="enable_github">
                                <span class="toggle-text" id="status_text_github">Disabled</span>
                                <input type="checkbox" id="enable_github" class="social-toggle" data-platform="github">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; align-items: end;">
                            <div class="form-group" style="margin: 0;">
                                <label for="name_github" style="font-size: 11px; margin-bottom: 4px; display: block; color: var(--text-muted);">Display Label</label>
                                <input type="text" id="name_github" class="form-control" placeholder="GitHub" maxlength="100">
                            </div>
                            <div class="form-group" style="margin: 0; grid-column: span 2;">
                                <label for="url_github" style="font-size: 11px; margin-bottom: 4px; display: block; color: var(--text-muted);">Profile URL</label>
                                <input type="url" id="url_github" class="form-control" placeholder="https://github.com/username" dir="ltr">
                            </div>
                            <div class="form-group" style="margin: 0;">
                                <label for="icon_github" style="font-size: 11px; margin-bottom: 4px; display: block; color: var(--text-muted);">Icon</label>
                                <select id="icon_github" class="form-control">
                                    <option value="github">GitHub</option>
                                    <option value="linkedin">LinkedIn</option>
                                    <option value="x">X (Twitter)</option>
                                    <option value="instagram">Instagram</option>
                                    <option value="facebook">Facebook</option>
                                    <option value="tiktok">TikTok</option>
                                    <option value="youtube">YouTube</option>
                                    <option value="email">Email</option>
                                    <option value="globe">Globe (Web)</option>
                                    <option value="external">External Link</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin: 0; max-width: 100px;">
                                <label for="sort_github" style="font-size: 11px; margin-bottom: 4px; display: block; color: var(--text-muted);">Sort Order</label>
                                <input type="number" id="sort_github" class="form-control" value="0" min="0" step="1">
                            </div>
                        </div>
                    </div>

                    <!-- 2. LinkedIn -->
                    <div class="social-platform-card" data-platform="linkedin" style="display: flex; flex-direction: column; gap: 12px; align-items: stretch;">
                        <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border-subtle); padding-bottom: 10px;">
                            <div class="social-brand-info">
                                <span class="social-icon-wrapper">
                                    <i class="fa-brands fa-linkedin-in"></i>
                                </span>
                                <div>
                                    <h3 class="social-platform-title">LinkedIn</h3>
                                    <span class="social-platform-slug">linkedin.com</span>
                                </div>
                            </div>
                            <label class="toggle-switch-label" for="enable_linkedin">
                                <span class="toggle-text" id="status_text_linkedin">Disabled</span>
                                <input type="checkbox" id="enable_linkedin" class="social-toggle" data-platform="linkedin">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; align-items: end;">
                            <div class="form-group" style="margin: 0;">
                                <label for="name_linkedin" style="font-size: 11px; margin-bottom: 4px; display: block; color: var(--text-muted);">Display Label</label>
                                <input type="text" id="name_linkedin" class="form-control" placeholder="LinkedIn" maxlength="100">
                            </div>
                            <div class="form-group" style="margin: 0; grid-column: span 2;">
                                <label for="url_linkedin" style="font-size: 11px; margin-bottom: 4px; display: block; color: var(--text-muted);">Profile URL</label>
                                <input type="url" id="url_linkedin" class="form-control" placeholder="https://linkedin.com/in/username" dir="ltr">
                            </div>
                            <div class="form-group" style="margin: 0;">
                                <label for="icon_linkedin" style="font-size: 11px; margin-bottom: 4px; display: block; color: var(--text-muted);">Icon</label>
                                <select id="icon_linkedin" class="form-control">
                                    <option value="linkedin">LinkedIn</option>
                                    <option value="github">GitHub</option>
                                    <option value="x">X (Twitter)</option>
                                    <option value="instagram">Instagram</option>
                                    <option value="facebook">Facebook</option>
                                    <option value="tiktok">TikTok</option>
                                    <option value="youtube">YouTube</option>
                                    <option value="email">Email</option>
                                    <option value="globe">Globe (Web)</option>
                                    <option value="external">External Link</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin: 0; max-width: 100px;">
                                <label for="sort_linkedin" style="font-size: 11px; margin-bottom: 4px; display: block; color: var(--text-muted);">Sort Order</label>
                                <input type="number" id="sort_linkedin" class="form-control" value="0" min="0" step="1">
                            </div>
                        </div>
                    </div>

                    <!-- 3. X (Twitter) -->
                    <div class="social-platform-card" data-platform="x" style="display: flex; flex-direction: column; gap: 12px; align-items: stretch;">
                        <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border-subtle); padding-bottom: 10px;">
                            <div class="social-brand-info">
                                <span class="social-icon-wrapper">
                                    <i class="fa-brands fa-x-twitter"></i>
                                </span>
                                <div>
                                    <h3 class="social-platform-title">X (Twitter)</h3>
                                    <span class="social-platform-slug">x.com</span>
                                </div>
                            </div>
                            <label class="toggle-switch-label" for="enable_x">
                                <span class="toggle-text" id="status_text_x">Disabled</span>
                                <input type="checkbox" id="enable_x" class="social-toggle" data-platform="x">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; align-items: end;">
                            <div class="form-group" style="margin: 0;">
                                <label for="name_x" style="font-size: 11px; margin-bottom: 4px; display: block; color: var(--text-muted);">Display Label</label>
                                <input type="text" id="name_x" class="form-control" placeholder="X" maxlength="100">
                            </div>
                            <div class="form-group" style="margin: 0; grid-column: span 2;">
                                <label for="url_x" style="font-size: 11px; margin-bottom: 4px; display: block; color: var(--text-muted);">Profile URL</label>
                                <input type="url" id="url_x" class="form-control" placeholder="https://x.com/username" dir="ltr">
                            </div>
                            <div class="form-group" style="margin: 0;">
                                <label for="icon_x" style="font-size: 11px; margin-bottom: 4px; display: block; color: var(--text-muted);">Icon</label>
                                <select id="icon_x" class="form-control">
                                    <option value="x">X (Twitter)</option>
                                    <option value="github">GitHub</option>
                                    <option value="linkedin">LinkedIn</option>
                                    <option value="instagram">Instagram</option>
                                    <option value="facebook">Facebook</option>
                                    <option value="tiktok">TikTok</option>
                                    <option value="youtube">YouTube</option>
                                    <option value="email">Email</option>
                                    <option value="globe">Globe (Web)</option>
                                    <option value="external">External Link</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin: 0; max-width: 100px;">
                                <label for="sort_x" style="font-size: 11px; margin-bottom: 4px; display: block; color: var(--text-muted);">Sort Order</label>
                                <input type="number" id="sort_x" class="form-control" value="0" min="0" step="1">
                            </div>
                        </div>
                    </div>

                    <!-- 4. Instagram -->
                    <div class="social-platform-card" data-platform="instagram" style="display: flex; flex-direction: column; gap: 12px; align-items: stretch;">
                        <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border-subtle); padding-bottom: 10px;">
                            <div class="social-brand-info">
                                <span class="social-icon-wrapper">
                                    <i class="fa-brands fa-instagram"></i>
                                </span>
                                <div>
                                    <h3 class="social-platform-title">Instagram</h3>
                                    <span class="social-platform-slug">instagram.com</span>
                                </div>
                            </div>
                            <label class="toggle-switch-label" for="enable_instagram">
                                <span class="toggle-text" id="status_text_instagram">Disabled</span>
                                <input type="checkbox" id="enable_instagram" class="social-toggle" data-platform="instagram">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; align-items: end;">
                            <div class="form-group" style="margin: 0;">
                                <label for="name_instagram" style="font-size: 11px; margin-bottom: 4px; display: block; color: var(--text-muted);">Display Label</label>
                                <input type="text" id="name_instagram" class="form-control" placeholder="Instagram" maxlength="100">
                            </div>
                            <div class="form-group" style="margin: 0; grid-column: span 2;">
                                <label for="url_instagram" style="font-size: 11px; margin-bottom: 4px; display: block; color: var(--text-muted);">Profile URL</label>
                                <input type="url" id="url_instagram" class="form-control" placeholder="https://instagram.com/username" dir="ltr">
                            </div>
                            <div class="form-group" style="margin: 0;">
                                <label for="icon_instagram" style="font-size: 11px; margin-bottom: 4px; display: block; color: var(--text-muted);">Icon</label>
                                <select id="icon_instagram" class="form-control">
                                    <option value="instagram">Instagram</option>
                                    <option value="github">GitHub</option>
                                    <option value="linkedin">LinkedIn</option>
                                    <option value="x">X (Twitter)</option>
                                    <option value="facebook">Facebook</option>
                                    <option value="tiktok">TikTok</option>
                                    <option value="youtube">YouTube</option>
                                    <option value="email">Email</option>
                                    <option value="globe">Globe (Web)</option>
                                    <option value="external">External Link</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin: 0; max-width: 100px;">
                                <label for="sort_instagram" style="font-size: 11px; margin-bottom: 4px; display: block; color: var(--text-muted);">Sort Order</label>
                                <input type="number" id="sort_instagram" class="form-control" value="0" min="0" step="1">
                            </div>
                        </div>
                    </div>

                    <!-- 5. TikTok -->
                    <div class="social-platform-card" data-platform="tiktok" style="display: flex; flex-direction: column; gap: 12px; align-items: stretch;">
                        <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border-subtle); padding-bottom: 10px;">
                            <div class="social-brand-info">
                                <span class="social-icon-wrapper">
                                    <i class="fa-brands fa-tiktok"></i>
                                </span>
                                <div>
                                    <h3 class="social-platform-title">TikTok</h3>
                                    <span class="social-platform-slug">tiktok.com</span>
                                </div>
                            </div>
                            <label class="toggle-switch-label" for="enable_tiktok">
                                <span class="toggle-text" id="status_text_tiktok">Disabled</span>
                                <input type="checkbox" id="enable_tiktok" class="social-toggle" data-platform="tiktok">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; align-items: end;">
                            <div class="form-group" style="margin: 0;">
                                <label for="name_tiktok" style="font-size: 11px; margin-bottom: 4px; display: block; color: var(--text-muted);">Display Label</label>
                                <input type="text" id="name_tiktok" class="form-control" placeholder="TikTok" maxlength="100">
                            </div>
                            <div class="form-group" style="margin: 0; grid-column: span 2;">
                                <label for="url_tiktok" style="font-size: 11px; margin-bottom: 4px; display: block; color: var(--text-muted);">Profile URL</label>
                                <input type="url" id="url_tiktok" class="form-control" placeholder="https://tiktok.com/@username" dir="ltr">
                            </div>
                            <div class="form-group" style="margin: 0;">
                                <label for="icon_tiktok" style="font-size: 11px; margin-bottom: 4px; display: block; color: var(--text-muted);">Icon</label>
                                <select id="icon_tiktok" class="form-control">
                                    <option value="tiktok">TikTok</option>
                                    <option value="github">GitHub</option>
                                    <option value="linkedin">LinkedIn</option>
                                    <option value="x">X (Twitter)</option>
                                    <option value="instagram">Instagram</option>
                                    <option value="facebook">Facebook</option>
                                    <option value="youtube">YouTube</option>
                                    <option value="email">Email</option>
                                    <option value="globe">Globe (Web)</option>
                                    <option value="external">External Link</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin: 0; max-width: 100px;">
                                <label for="sort_tiktok" style="font-size: 11px; margin-bottom: 4px; display: block; color: var(--text-muted);">Sort Order</label>
                                <input type="number" id="sort_tiktok" class="form-control" value="0" min="0" step="1">
                            </div>
                        </div>
                    </div>

                    <!-- 6. Facebook -->
                    <div class="social-platform-card" data-platform="facebook" style="display: flex; flex-direction: column; gap: 12px; align-items: stretch;">
                        <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border-subtle); padding-bottom: 10px;">
                            <div class="social-brand-info">
                                <span class="social-icon-wrapper">
                                    <i class="fa-brands fa-facebook-f"></i>
                                </span>
                                <div>
                                    <h3 class="social-platform-title">Facebook</h3>
                                    <span class="social-platform-slug">facebook.com</span>
                                </div>
                            </div>
                            <label class="toggle-switch-label" for="enable_facebook">
                                <span class="toggle-text" id="status_text_facebook">Disabled</span>
                                <input type="checkbox" id="enable_facebook" class="social-toggle" data-platform="facebook">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; align-items: end;">
                            <div class="form-group" style="margin: 0;">
                                <label for="name_facebook" style="font-size: 11px; margin-bottom: 4px; display: block; color: var(--text-muted);">Display Label</label>
                                <input type="text" id="name_facebook" class="form-control" placeholder="Facebook" maxlength="100">
                            </div>
                            <div class="form-group" style="margin: 0; grid-column: span 2;">
                                <label for="url_facebook" style="font-size: 11px; margin-bottom: 4px; display: block; color: var(--text-muted);">Profile URL</label>
                                <input type="url" id="url_facebook" class="form-control" placeholder="https://facebook.com/username" dir="ltr">
                            </div>
                            <div class="form-group" style="margin: 0;">
                                <label for="icon_facebook" style="font-size: 11px; margin-bottom: 4px; display: block; color: var(--text-muted);">Icon</label>
                                <select id="icon_facebook" class="form-control">
                                    <option value="facebook">Facebook</option>
                                    <option value="github">GitHub</option>
                                    <option value="linkedin">LinkedIn</option>
                                    <option value="x">X (Twitter)</option>
                                    <option value="instagram">Instagram</option>
                                    <option value="tiktok">TikTok</option>
                                    <option value="youtube">YouTube</option>
                                    <option value="email">Email</option>
                                    <option value="globe">Globe (Web)</option>
                                    <option value="external">External Link</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin: 0; max-width: 100px;">
                                <label for="sort_facebook" style="font-size: 11px; margin-bottom: 4px; display: block; color: var(--text-muted);">Sort Order</label>
                                <input type="number" id="sort_facebook" class="form-control" value="0" min="0" step="1">
                            </div>
                        </div>
                    </div>

                </div>

                <div class="form-actions-bar">
                    <button type="submit" id="saveSocialBtn" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        <span>Save Social Channels</span>
                    </button>
                    <span id="socialStatusMsg" style="font-size: 12.5px; color: var(--text-muted); font-family: var(--font-mono);"></span>
                </div>
            </form>
        </section>

    </div>

    <!-- =====================================================
         TAB 5: SYSTEM & DIAGNOSTICS
    ====================================================== -->
    <div class="tab-pane" id="pane-system" role="tabpanel" aria-labelledby="tab-system">

        <!-- Server & PHP Runtime Environment -->
        <section class="admin-card" aria-labelledby="runtimeEnvTitle" style="margin-bottom: 24px;">
            <div class="admin-card-header">
                <div>
                    <h2 class="card-title" id="runtimeEnvTitle">
                        <i class="fas fa-server" style="color: var(--accent); margin-right: 8px;"></i>
                        Server &amp; PHP Runtime Environment
                    </h2>
                    <p class="card-subtitle">Verified host constraints, memory limits, and file system permissions.</p>
                </div>
                <span class="status-badge status-published">Hostinger Linux</span>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 14px;">
                <div style="background: var(--bg-canvas); padding: 14px 16px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
                    <span style="display: block; font-size: 11px; color: var(--text-muted); font-family: var(--font-mono); text-transform: uppercase;">PHP Version</span>
                    <span style="font-family: var(--font-mono); font-size: 14px; font-weight: 600; color: var(--accent);"><?= phpversion() ?></span>
                </div>

                <div style="background: var(--bg-canvas); padding: 14px 16px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
                    <span style="display: block; font-size: 11px; color: var(--text-muted); font-family: var(--font-mono); text-transform: uppercase;">MySQL Version</span>
                    <span style="font-family: var(--font-mono); font-size: 13.5px; font-weight: 600; color: var(--accent);"><?= htmlspecialchars($dbStats['version']) ?></span>
                </div>

                <div style="background: var(--bg-canvas); padding: 14px 16px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
                    <span style="display: block; font-size: 11px; color: var(--text-muted); font-family: var(--font-mono); text-transform: uppercase;">Memory Limit</span>
                    <span style="font-family: var(--font-mono); font-size: 14px; font-weight: 600; color: var(--text-primary);"><?= ini_get('memory_limit') ?></span>
                </div>

                <div style="background: var(--bg-canvas); padding: 14px 16px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
                    <span style="display: block; font-size: 11px; color: var(--text-muted); font-family: var(--font-mono); text-transform: uppercase;">Max Upload Size</span>
                    <span style="font-family: var(--font-mono); font-size: 14px; font-weight: 600; color: var(--text-primary);"><?= ini_get('upload_max_filesize') ?></span>
                </div>

                <div style="background: var(--bg-canvas); padding: 14px 16px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
                    <span style="display: block; font-size: 11px; color: var(--text-muted); font-family: var(--font-mono); text-transform: uppercase;">Max Execution Time</span>
                    <span style="font-family: var(--font-mono); font-size: 14px; font-weight: 600; color: var(--text-primary);"><?= ini_get('max_execution_time') ?>s</span>
                </div>

                <div style="background: var(--bg-canvas); padding: 14px 16px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
                    <span style="display: block; font-size: 11px; color: var(--text-muted); font-family: var(--font-mono); text-transform: uppercase;">Upload Storage (/uploads)</span>
                    <span style="font-family: var(--font-mono); font-size: 13px; font-weight: 600; color: <?= $isUploadWritable ? 'var(--success)' : 'var(--danger)' ?>;">
                        <?= $isUploadWritable ? '✓ Writable &amp; Active' : '✕ Read-Only / Inaccessible' ?>
                    </span>
                </div>
            </div>
        </section>

        <!-- Database Table Inventory Diagnostics -->
        <section class="admin-card" aria-labelledby="dbInventoryTitle" style="margin-bottom: 24px;">
            <div class="admin-card-header">
                <div>
                    <h2 class="card-title" id="dbInventoryTitle">
                        <i class="fas fa-database" style="color: var(--accent); margin-right: 8px;"></i>
                        Database Entity Inventory
                    </h2>
                    <p class="card-subtitle">Live production row counts from MariaDB: <code style="font-family: var(--font-mono); color: var(--accent);"><?= htmlspecialchars($dbStats['db_name']) ?></code></p>
                </div>
                <span class="status-badge status-published">Online &amp; Connected</span>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px;">
                <div style="background: var(--bg-canvas); padding: 12px 14px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle); text-align: center;">
                    <span style="display: block; font-size: 22px; font-weight: 700; color: var(--text-primary); font-family: var(--font-mono);"><?= $dbStats['projects'] ?></span>
                    <span style="font-size: 11.5px; color: var(--text-muted);">Engineering Projects</span>
                </div>
                <div style="background: var(--bg-canvas); padding: 12px 14px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle); text-align: center;">
                    <span style="display: block; font-size: 22px; font-weight: 700; color: var(--text-primary); font-family: var(--font-mono);"><?= $dbStats['articles'] ?></span>
                    <span style="font-size: 11.5px; color: var(--text-muted);">Technical Articles</span>
                </div>
                <div style="background: var(--bg-canvas); padding: 12px 14px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle); text-align: center;">
                    <span style="display: block; font-size: 22px; font-weight: 700; color: var(--text-primary); font-family: var(--font-mono);"><?= $dbStats['reviews'] ?></span>
                    <span style="font-size: 11.5px; color: var(--text-muted);">Visitor Reviews</span>
                </div>
                <div style="background: var(--bg-canvas); padding: 12px 14px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle); text-align: center;">
                    <span style="display: block; font-size: 22px; font-weight: 700; color: var(--text-primary); font-family: var(--font-mono);"><?= $dbStats['users'] ?></span>
                    <span style="font-size: 11.5px; color: var(--text-muted);">User Accounts</span>
                </div>
                <div style="background: var(--bg-canvas); padding: 12px 14px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle); text-align: center;">
                    <span style="display: block; font-size: 22px; font-weight: 700; color: var(--text-primary); font-family: var(--font-mono);"><?= $dbStats['social'] ?></span>
                    <span style="font-size: 11.5px; color: var(--text-muted);">Active Social Links</span>
                </div>
                <div style="background: var(--bg-canvas); padding: 12px 14px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle); text-align: center;">
                    <span style="display: block; font-size: 22px; font-weight: 700; color: var(--accent); font-family: var(--font-mono);"><?= $dbStats['settings'] ?></span>
                    <span style="font-size: 11.5px; color: var(--text-muted);">Site Settings (IMP-034)</span>
                </div>
            </div>
        </section>

        <!-- Database Migrations & Ledger (P2.4) -->
        <section class="admin-card" aria-labelledby="migrationsLedgerTitle" style="margin-bottom: 24px;">
            <div class="admin-card-header">
                <div>
                    <h2 class="card-title" id="migrationsLedgerTitle">
                        <i class="fas fa-cubes" style="color: var(--accent); margin-right: 8px;"></i>
                        Database Migrations &amp; Ledger
                    </h2>
                    <p class="card-subtitle">Tracked DDL migrations and immutable deployment ledger (<code style="font-family: var(--font-mono); color: var(--accent);">schema_migrations</code>).</p>
                </div>
                <a href="run_migrations.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-play" style="font-size: 11px;"></i>
                    <span>Open Migration Runner</span>
                </a>
            </div>

            <?php if (!empty($recentMigrations)): ?>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Migration Identifier</th>
                                <th>Applied At</th>
                                <th style="text-align: center;">Applied By Admin</th>
                                <th style="text-align: right;">Ledger Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentMigrations as $mig): ?>
                                <tr>
                                    <td>
                                        <div style="font-family: var(--font-mono); font-size: 12.5px; font-weight: 600; color: var(--text-primary);">
                                            <?= htmlspecialchars($mig['id']) ?>
                                        </div>
                                    </td>
                                    <td style="font-size: 12px; color: var(--text-secondary); font-family: var(--font-mono);">
                                        <?= htmlspecialchars($mig['applied_at'] ?? '—') ?>
                                    </td>
                                    <td style="text-align: center; font-family: var(--font-mono); font-size: 12px; color: var(--accent);">
                                        #<?= (int)($mig['applied_by'] ?? 1) ?>
                                    </td>
                                    <td style="text-align: right;">
                                        <span class="status-badge status-published">Recorded</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="padding: 16px; background: var(--bg-canvas); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap;">
                    <div style="font-size: 13px; color: var(--text-secondary);">
                        <i class="fas fa-check-circle" style="color: var(--success); margin-right: 6px;"></i>
                        No migrations ledger records found yet. Use the Migration Runner to verify DDL schema integrity or apply pending migrations.
                    </div>
                    <a href="run_migrations.php" class="btn btn-primary btn-sm">
                        <span>Check Migrations</span>
                    </a>
                </div>
            <?php endif; ?>
        </section>

        <!-- Defense-In-Depth Security Posture -->
        <section class="admin-card" aria-labelledby="securityPostureTitle">
            <div class="admin-card-header">
                <div>
                    <h2 class="card-title" id="securityPostureTitle">
                        <i class="fas fa-shield-halved" style="color: var(--accent); margin-right: 8px;"></i>
                        Defense-in-Depth Security Invariants
                    </h2>
                    <p class="card-subtitle">Active security controls enforcing authentication, least privilege, and injection defenses.</p>
                </div>
                <span class="status-badge status-published">Active Defense</span>
            </div>

            <div style="display: flex; flex-direction: column; gap: 10px; font-size: 13.5px;">
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; background: var(--bg-canvas); border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
                    <span style="color: var(--text-secondary);">CSRF Protection State</span>
                    <span style="font-family: var(--font-mono); color: var(--success); font-weight: 600;">Active (requireCSRF Guard)</span>
                </div>
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; background: var(--bg-canvas); border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
                    <span style="color: var(--text-secondary);">Session Cookie Security</span>
                    <span style="font-family: var(--font-mono); color: var(--success); font-weight: 600;">HttpOnly, SameSite=Lax, Secure</span>
                </div>
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; background: var(--bg-canvas); border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
                    <span style="color: var(--text-secondary);">Password Encryption Standard</span>
                    <span style="font-family: var(--font-mono); color: var(--success); font-weight: 600;">Argon2id / PASSWORD_DEFAULT</span>
                </div>
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; background: var(--bg-canvas); border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
                    <span style="color: var(--text-secondary);">Write-Time Sanitization (IMP-034)</span>
                    <span style="font-family: var(--font-mono); color: var(--success); font-weight: 600;">strip_tags + Length Caps + Enums</span>
                </div>
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; background: var(--bg-canvas); border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
                    <span style="color: var(--text-secondary);">SQL Injection Mitigation</span>
                    <span style="font-family: var(--font-mono); color: var(--success); font-weight: 600;">Parameterized Prepared Statements (PDO)</span>
                </div>
            </div>
        </section>

    </div>

    <!-- =====================================================
         TAB: EMAIL & SMTP
    ====================================================== -->
    <div class="tab-pane" id="pane-email" role="tabpanel" aria-labelledby="tab-email">

        <!-- SMTP Status Card -->
        <section class="admin-card" style="margin-bottom: 24px;">
            <div class="admin-card-header">
                <div>
                    <h2 class="card-title">
                        <i class="fas fa-envelope-circle-check" style="color: var(--accent); margin-right: 8px;"></i>
                        SMTP Configuration Status
                    </h2>
                    <p class="card-subtitle">Reads from <code style="font-family: var(--font-mono); color: var(--accent);">api/config.local.php</code> — set SMTP_PASS on the server to activate SMTP delivery.</p>
                </div>
                <?php
                $smtpActive = (
                    defined('SMTP_HOST') && !empty(SMTP_HOST) &&
                    defined('SMTP_PASS') && !empty(SMTP_PASS) &&
                    SMTP_PASS !== 'your_smtp_password' &&
                    SMTP_PASS !== 'YOUR_SMTP_PASSWORD'
                );
                ?>
                <span class="status-badge <?= $smtpActive ? 'status-published' : 'status-draft' ?>">
                    <?= $smtpActive ? 'SMTP Active' : 'Fallback: mail()' ?>
                </span>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">
                <div style="background: var(--bg-canvas); padding: 12px 16px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
                    <span style="display: block; font-size: 11px; color: var(--text-muted); font-family: var(--font-mono); text-transform: uppercase; margin-bottom: 4px;">SMTP Host</span>
                    <span style="font-family: var(--font-mono); font-size: 13px; color: <?= $smtpActive ? 'var(--accent)' : 'var(--text-muted)' ?>;"><?= defined('SMTP_HOST') ? htmlspecialchars(SMTP_HOST) : '—' ?></span>
                </div>
                <div style="background: var(--bg-canvas); padding: 12px 16px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
                    <span style="display: block; font-size: 11px; color: var(--text-muted); font-family: var(--font-mono); text-transform: uppercase; margin-bottom: 4px;">Port / Encryption</span>
                    <span style="font-family: var(--font-mono); font-size: 13px; color: var(--text-primary);"><?= defined('SMTP_PORT') ? (int)SMTP_PORT . ' (' . ((int)SMTP_PORT === 465 ? 'SMTPS' : 'STARTTLS') . ')' : '—' ?></span>
                </div>
                <div style="background: var(--bg-canvas); padding: 12px 16px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
                    <span style="display: block; font-size: 11px; color: var(--text-muted); font-family: var(--font-mono); text-transform: uppercase; margin-bottom: 4px;">From Address</span>
                    <span style="font-family: var(--font-mono); font-size: 13px; color: var(--text-primary);"><?= defined('SMTP_FROM') ? htmlspecialchars(SMTP_FROM) : '—' ?></span>
                </div>
                <div style="background: var(--bg-canvas); padding: 12px 16px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
                    <span style="display: block; font-size: 11px; color: var(--text-muted); font-family: var(--font-mono); text-transform: uppercase; margin-bottom: 4px;">Password</span>
                    <span style="font-family: var(--font-mono); font-size: 13px; color: <?= $smtpActive ? 'var(--success)' : 'var(--danger)' ?>;">
                        <?= $smtpActive ? '••••••••  (set)' : 'Not set / placeholder' ?>
                    </span>
                </div>
            </div>

            <?php if (!$smtpActive): ?>
            <div style="margin-top: 14px; padding: 12px 16px; background: rgba(245,158,11,0.08); border: 1px solid rgba(245,158,11,0.25); border-radius: var(--radius-sm);">
                <p style="margin: 0; font-size: 13px; color: var(--warning); font-family: var(--font-mono);">
                    ⚠ SMTP_PASS is not set or still contains the placeholder. Edit <code>api/config.local.php</code> on the server and set the real Hostinger email password.
                </p>
            </div>
            <?php endif; ?>
        </section>

        <!-- Email Preview Card -->
        <section class="admin-card" style="margin-bottom: 24px;">
            <div class="admin-card-header">
                <div>
                    <h2 class="card-title">
                        <i class="fas fa-eye" style="color: var(--accent); margin-right: 8px;"></i>
                        Email Template Preview
                    </h2>
                    <p class="card-subtitle">Renders each template with sample data. The iframe is sandboxed (no scripts, no external requests).</p>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <select id="emailTemplateSelect" class="form-control" style="font-size: 13px; padding: 7px 12px; min-width: 200px;">
                        <option value="password_reset">Password Reset Request</option>
                        <option value="password_changed">Password Changed</option>
                        <option value="support_confirmation">Support — Visitor Confirmation</option>
                        <option value="support_owner">Support — Owner Notification</option>
                        <option value="welcome">Welcome Email</option>
                    </select>
                </div>
            </div>

            <div style="padding: 0 20px 20px;">
                <div style="border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); overflow: hidden; background: var(--bg-surface-elevated);">
                    <iframe
                        id="emailPreviewFrame"
                        sandbox="allow-same-origin"
                        style="width: 100%; height: 700px; border: none; display: block;"
                        title="Email template preview"
                        loading="lazy">
                    </iframe>
                </div>
                <p id="emailPreviewStatus" style="margin: 8px 0 0; font-size: 12px; color: var(--text-muted); font-family: var(--font-mono);"></p>
            </div>
        </section>

        <!-- Send Test Email Card -->
        <section class="admin-card">
            <div class="admin-card-header">
                <div>
                    <h2 class="card-title">
                        <i class="fas fa-paper-plane" style="color: var(--accent); margin-right: 8px;"></i>
                        Send Test Email
                    </h2>
                    <p class="card-subtitle">Sends the selected template to <strong style="color: var(--text-primary);"><?= defined('SMTP_FROM') ? htmlspecialchars(SMTP_FROM) : 'alrashadi@mohammedalrashadi.com' ?></strong> using the live SMTP configuration. Rate-limited to 1 per 30 seconds.</p>
                </div>
            </div>

            <div style="padding: 0 20px 20px;">
                <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                    <select id="emailTestTemplateSelect" class="form-control" style="font-size: 13px; padding: 7px 12px; min-width: 200px;">
                        <option value="password_reset">Password Reset Request</option>
                        <option value="password_changed">Password Changed</option>
                        <option value="support_confirmation">Support — Visitor Confirmation</option>
                        <option value="support_owner">Support — Owner Notification</option>
                        <option value="welcome">Welcome Email</option>
                    </select>
                    <button type="button" id="sendTestEmailBtn" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i>
                        <span>Send Test Email</span>
                    </button>
                </div>
                <div id="emailTestResult" style="margin-top: 12px; display: none; padding: 10px 14px; border-radius: var(--radius-sm); font-size: 13px; font-family: var(--font-mono);"></div>
            </div>
        </section>

    </div>

    <!-- =====================================================
         TAB 7: ACCOUNT CREDENTIALS & SECURITY
    ====================================================== -->
    <div class="tab-pane" id="pane-account" role="tabpanel" aria-labelledby="tab-account">

        <!-- Account Security Snapshot Card -->
        <div class="profile-preview-card" style="margin-bottom: 24px;">
            <div class="admin-monogram" style="width: 48px; height: 48px; font-size: 16px; border-radius: var(--radius-sm);">
                <i class="fas fa-shield-halved" style="color: var(--accent);"></i>
            </div>
            <div style="flex: 1; min-width: 0;">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 4px; flex-wrap: wrap;">
                    <h2 style="font-size: 16px; font-weight: 700; color: var(--text-primary); margin: 0;" id="accountDisplayEmail">Loading...</h2>
                    <span class="status-badge status-published">Administrator</span>
                </div>
                <div style="font-size: 12.5px; color: var(--text-muted); font-family: var(--font-mono); line-height: 1.5;">
                    <span>Last Login: </span><span id="accountLastLogin" style="color: var(--text-secondary);">—</span>
                    <span style="margin: 0 8px; color: var(--border-medium);">|</span>
                    <span>Password Changed: </span><span id="accountPasswordChanged" style="color: var(--text-secondary);">—</span>
                </div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px;">
            
            <!-- Section 1: Change Email -->
            <section class="admin-card" aria-label="Change Admin Email Form">
                <div class="admin-card-header">
                    <div>
                        <h2 class="card-title">Change Login Email</h2>
                        <p class="card-subtitle">Updating your email address requires re-authenticating with your current password.</p>
                    </div>
                </div>

                <form id="changeEmailForm" novalidate>
                    <div class="form-group" style="margin-bottom: 16px;">
                        <label for="new_email">New Email Address <span style="color: var(--accent);">*</span></label>
                        <input type="email" id="new_email" class="form-control" required placeholder="admin@example.com" autocomplete="email">
                        <span class="form-help">Must be a valid, unique email address.</span>
                    </div>

                    <div class="form-group" style="margin-bottom: 20px;">
                        <label for="email_current_password">Current Password (Re-Auth) <span style="color: var(--accent);">*</span></label>
                        <input type="password" id="email_current_password" class="form-control" required placeholder="Enter current password..." autocomplete="current-password">
                        <span class="form-help">Required to confirm you own this administrative session.</span>
                    </div>

                    <button type="submit" class="btn btn-primary" id="btnSubmitEmail">
                        <i class="fas fa-envelope-circle-check"></i>
                        <span>Update Email</span>
                    </button>
                </form>
            </section>

            <!-- Section 2: Change Password -->
            <section class="admin-card" aria-label="Change Admin Password Form">
                <div class="admin-card-header">
                    <div>
                        <h2 class="card-title">Change Password</h2>
                        <p class="card-subtitle">Requires current password. Enforces bcrypt hash and invalidates all older sessions.</p>
                    </div>
                </div>

                <form id="changePasswordForm" novalidate>
                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="pwd_current_password">Current Password <span style="color: var(--accent);">*</span></label>
                        <input type="password" id="pwd_current_password" class="form-control" required placeholder="Current password..." autocomplete="current-password">
                    </div>

                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="pwd_new_password">New Password <span style="color: var(--accent);">*</span></label>
                        <input type="password" id="pwd_new_password" class="form-control" required minlength="8" placeholder="Minimum 8 characters..." autocomplete="new-password">
                        <span class="form-help">Must be at least 8 characters.</span>
                    </div>

                    <div class="form-group" style="margin-bottom: 20px;">
                        <label for="pwd_confirm_password">Confirm New Password <span style="color: var(--accent);">*</span></label>
                        <input type="password" id="pwd_confirm_password" class="form-control" required minlength="8" placeholder="Re-type new password..." autocomplete="new-password">
                    </div>

                    <button type="submit" class="btn btn-primary" id="btnSubmitPassword">
                        <i class="fas fa-key"></i>
                        <span>Update Password</span>
                    </button>
                </form>
            </section>

        </div>

    </div>

</div>

<?php
$pageScripts = ['js/settings.js', 'js/about-content.js'];
require __DIR__ . '/partials/layout_bottom.php';
?>
<script>
// ============================================================
// Email Preview & Test — Admin Settings / Email & SMTP Tab
// ============================================================
(function () {
    'use strict';

    const previewFrame    = document.getElementById('emailPreviewFrame');
    const previewSelect   = document.getElementById('emailTemplateSelect');
    const previewStatus   = document.getElementById('emailPreviewStatus');
    const testSelect      = document.getElementById('emailTestTemplateSelect');
    const testBtn         = document.getElementById('sendTestEmailBtn');
    const testResult      = document.getElementById('emailTestResult');

    if (!previewFrame || !previewSelect) return;

    // ---- Keep both selects in sync ------------------------------------
    previewSelect.addEventListener('change', () => {
        testSelect.value = previewSelect.value;
        loadPreview(previewSelect.value);
    });
    testSelect.addEventListener('change', () => {
        previewSelect.value = testSelect.value;
        loadPreview(testSelect.value);
    });

    // ---- Load preview on tab activation (lazy) ------------------------
    const emailTab = document.querySelector('[data-tab="email"]');
    if (emailTab) {
        emailTab.addEventListener('click', () => {
            if (!previewFrame.dataset.loaded) {
                loadPreview(previewSelect.value);
            }
        });
    }

    // ---- Fetch and inject HTML via srcdoc -----------------------------
    function loadPreview(templateName) {
        if (previewStatus) previewStatus.textContent = '⟳ Loading preview…';
        fetch('/api/admin/email_preview.php?template=' + encodeURIComponent(templateName), {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.text();
        })
        .then(html => {
            previewFrame.srcdoc = html;
            previewFrame.dataset.loaded = '1';
            if (previewStatus) previewStatus.textContent = '✓ Preview loaded — ' + templateName.replace(/_/g, ' ');
        })
        .catch(err => {
            if (previewStatus) previewStatus.textContent = '✕ Preview failed: ' + err.message;
        });
    }

    // ---- Send test email ----------------------------------------------
    if (testBtn) {
        testBtn.addEventListener('click', async () => {
            testBtn.disabled = true;
            testBtn.querySelector('span').textContent = 'Sending…';
            if (testResult) { testResult.style.display = 'none'; }

            try {
                // Get CSRF token from meta or window
                const csrfToken = (
                    (typeof window._csrfToken !== 'undefined' ? window._csrfToken : null) ||
                    (document.querySelector('meta[name="csrf-token"]')?.content) ||
                    ''
                );

                const resp = await fetch('/api/admin/email_test.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        template: testSelect.value,
                        csrf_token: csrfToken
                    })
                });
                const data = await resp.json();

                if (testResult) {
                    testResult.style.display = 'block';
                    testResult.style.background = data.success
                        ? 'rgba(34,197,94,0.1)'
                        : 'rgba(239,68,68,0.1)';
                    testResult.style.border = '1px solid ' + (data.success ? 'rgba(34,197,94,0.3)' : 'rgba(239,68,68,0.3)');
                    testResult.style.color  = data.success ? '#86EFAC' : '#FCA5A5';
                    testResult.textContent  = data.message || (data.success ? 'Sent.' : 'Failed.');
                }
            } catch (e) {
                if (testResult) {
                    testResult.style.display     = 'block';
                    testResult.style.background  = 'rgba(239,68,68,0.1)';
                    testResult.style.border      = '1px solid rgba(239,68,68,0.3)';
                    testResult.style.color       = '#FCA5A5';
                    testResult.textContent       = 'Network error: ' + e.message;
                }
            } finally {
                testBtn.disabled = false;
                testBtn.querySelector('span').textContent = 'Send Test Email';
            }
        });
    }
})();
</script>
