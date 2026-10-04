<?php
// ============================================================
// SEO WORKSPACE — Server-Side Protected Admin Page
// Full SEO audit, keyword density, and technical metadata analysis
// powered directly by api/seo/analyze.php.
// ============================================================

require_once dirname(__DIR__) . '/api/auth/guard.php';

requireAdminPage('login.php');

$activeNav = 'seo';
$pageTitle = 'SEO & Discoverability';

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
            <span>Audience &amp; Insights</span>
            <span class="breadcrumb-sep">/</span>
            <span style="color: var(--accent);">SEO Workspace</span>
        </div>
        <h1 class="page-title">SEO &amp; Discoverability Workspace</h1>
        <p class="page-description">Audit search engine indexability, Open Graph cards, structured schema, and content density across articles.</p>
    </div>

    <div class="header-actions">
        <a href="articles.php" class="btn btn-secondary">
            <i class="far fa-file-alt"></i>
            <span>All Articles</span>
        </a>
    </div>
</header>


<!-- =====================================================
     2. SEO METRICS OVERVIEW
====================================================== -->
<section class="seo-overview-grid" aria-label="SEO Metrics Overview">
    <div class="seo-metric-card">
        <span class="seo-metric-label">Total Articles in Catalog</span>
        <div id="seoTotalArticles" class="seo-metric-val">0</div>
        <div style="font-size: 11.5px; color: var(--text-muted);">
            <span id="seoPublishedArticles" style="color: var(--success);">0 published</span> · <span id="seoDraftArticles">0 drafts</span>
        </div>
    </div>

    <div class="seo-metric-card">
        <span class="seo-metric-label">Indexable on Live Site</span>
        <div id="seoIndexableCount" class="seo-metric-val" style="color: var(--success);">0</div>
        <div style="font-size: 11.5px; color: var(--text-muted);">Emits index, follow &amp; OpenGraph</div>
    </div>

    <div class="seo-metric-card">
        <span class="seo-metric-label">Unpublished / Noindex</span>
        <div id="seoNoindexCount" class="seo-metric-val" style="color: var(--warning);">0</div>
        <div style="font-size: 11.5px; color: var(--text-muted);">Emits noindex until published</div>
    </div>

    <div class="seo-metric-card">
        <span class="seo-metric-label">Engine Capability</span>
        <div class="seo-metric-val" style="color: var(--accent); font-size: 18px; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-shield-halved"></i> Active
        </div>
        <div style="font-size: 11.5px; color: var(--text-muted);">Schema.org + Twitter / OG meta</div>
    </div>
</section>


<!-- =====================================================
     3. INTERACTIVE ARTICLE SEO AUDITOR
====================================================== -->
<section class="admin-card" style="margin-bottom: 24px;">
    <div class="admin-card-header">
        <div>
            <h2 class="card-title">Run Comprehensive SEO Audit</h2>
            <p class="card-subtitle">Select any article from your catalog to analyze title lengths, keyword placement, headings, and schema.</p>
        </div>
    </div>

    <!-- Selection Controls -->
    <div style="display: grid; grid-template-columns: 2fr 1fr auto; gap: 14px; align-items: flex-end; flex-wrap: wrap;">
        <div class="form-group" style="margin-bottom: 0;">
            <label for="seoArticleSelect" style="font-size: 12px; margin-bottom: 6px;">Select Article to Audit <span style="color: var(--accent);">*</span></label>
            <select id="seoArticleSelect" class="form-control" onchange="handleSelectArticleForAudit()">
                <option value="">Loading articles...</option>
            </select>
        </div>

        <div class="form-group" style="margin-bottom: 0;">
            <label for="seoTargetKeyword" style="font-size: 12px; margin-bottom: 6px;">Target Focus Keyword (Optional)</label>
            <input type="text" id="seoTargetKeyword" class="form-control" placeholder="e.g., distributed systems" maxlength="100">
        </div>

        <button type="button" id="seoAuditBtn" class="btn btn-primary" onclick="runLiveSeoAudit()" disabled style="height: 40px;">
            <i class="fas fa-magnifying-glass-chart"></i>
            <span>Audit Post</span>
        </button>
    </div>
</section>


<!-- =====================================================
     4. AUDIT RESULTS SECTION
====================================================== -->
<section id="seoResultsSection" class="admin-card" style="display: none; margin-bottom: 32px;">
    <div class="admin-card-header">
        <div>
            <h3 class="card-title" id="seoResultTitle">Audit Report</h3>
            <p class="card-subtitle" id="seoResultMeta">Analysis completed.</p>
        </div>
        <div style="display: flex; align-items: center; gap: 10px;">
            <a id="seoEditArticleLink" href="#" class="btn btn-secondary btn-sm">
                <i class="far fa-edit"></i>
                <span>Edit in Articles Workspace</span>
            </a>
        </div>
    </div>

    <!-- Scorecards Container -->
    <div id="seoReportBody" style="display: flex; flex-direction: column; gap: 20px;">
        <!-- Filled by admin/js/seo.js -->
    </div>
</section>


<!-- =====================================================
     5. CATALOG AUDIT DIRECTORY TABLE
====================================================== -->
<section class="table-container">
    <div class="admin-card-header" style="padding: 18px 20px; border-bottom: 1px solid var(--border-subtle);">
        <div>
            <h3 class="card-title">Content Discoverability Directory</h3>
            <p class="card-subtitle">Overview of all catalog items and their live indexability status.</p>
        </div>
    </div>

    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th style="min-width: 240px;">Article Title</th>
                    <th>Type</th>
                    <th>Category</th>
                    <th style="text-align: center;">Indexable</th>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody id="seoArticlesTableBody">
                <tr>
                    <td colspan="5" style="text-align: center; padding: 48px 20px; color: var(--text-muted);">
                        Loading catalog articles...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</section>

<?php
$pageScripts = ['js/seo.js'];
require __DIR__ . '/partials/layout_bottom.php';
?>
