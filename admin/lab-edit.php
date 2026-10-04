<?php
// ============================================================
// LAB EDIT / CREATE — Server-Side Protected Admin Page
// Full-form editor for reproducible engineering experiments.
// Directly backed by api/data/lab_experiments.json via api/labs/save.php.
// ============================================================

require_once dirname(__DIR__) . '/api/auth/guard.php';

requireAdminPage('login.php');

$activeNav = 'labs';
$labId = isset($_GET['id']) ? trim($_GET['id']) : '';
$isEdit = ($labId !== '');

$pageTitle = ($isEdit ? 'Edit ' . htmlspecialchars($labId) : 'New Lab Experiment');

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
            <a href="labs.php">Studio Lab</a>
            <span class="breadcrumb-sep">/</span>
            <span style="color: var(--accent);" id="breadcrumbCurrent"><?= $isEdit ? htmlspecialchars($labId) : 'New Experiment' ?></span>
        </div>
        <h1 class="page-title" id="editorTitle"><?= $isEdit ? 'Edit Investigation: ' . htmlspecialchars($labId) : 'Create New Lab Investigation' ?></h1>
        <p class="page-description">Author reproducible systems experiments, empirical telemetry, and architectural conclusions.</p>
    </div>

    <div class="header-actions">
        <a href="labs.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left" aria-hidden="true"></i>
            <span>Back to Studio Lab</span>
        </a>

        <?php if ($isEdit): ?>
        <a href="/lab-detail.php?id=<?= urlencode($labId) ?>" target="_blank" class="btn btn-secondary" id="viewPublicLink" title="View Public Spec">
            <i class="fas fa-external-link-alt" aria-hidden="true"></i>
            <span>Public Spec</span>
        </a>
        <?php endif; ?>

        <button type="button" class="btn btn-primary" id="saveLabBtn" onclick="saveLabExperiment()">
            <i class="fas fa-save" aria-hidden="true"></i>
            <span>Save Experiment</span>
        </button>
    </div>
</header>


<!-- =====================================================
     2. LAB EXPERIMENT FORM
====================================================== -->
<form id="labForm" class="admin-form-page" onsubmit="event.preventDefault(); saveLabExperiment();" style="display: flex; flex-direction: column; gap: 24px; padding-bottom: 60px;">

    <input type="hidden" id="original_id" value="<?= htmlspecialchars($labId) ?>">

    <!-- SECTION 1: CORE IDENTIFICATION -->
    <div class="card" style="padding: 24px; background: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-md);">
        <div style="border-bottom: 1px solid var(--border-subtle); padding-bottom: 14px; margin-bottom: 20px;">
            <h2 style="font-size: 16px; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-fingerprint" style="color: var(--accent);"></i>
                <span>Core Identification &amp; Classification</span>
            </h2>
            <p style="font-size: 13px; color: var(--text-muted); margin-top: 4px;">
                Unique experiment key, system classification, status, and taxonomy.
            </p>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px;">
            <div class="form-group">
                <label for="lab_id" style="font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px; display: block;">
                    Experiment ID <span style="color: var(--accent);">*</span>
                </label>
                <input
                    type="text"
                    id="lab_id"
                    class="form-control"
                    required
                    placeholder="e.g. LAB-001"
                    style="font-family: var(--font-mono); font-weight: 700; text-transform: uppercase;"
                >
                <span style="font-size: 11px; color: var(--text-muted); margin-top: 4px; display: block;">Unique identifier (e.g. LAB-001, LAB-015).</span>
            </div>

            <div class="form-group">
                <label for="lab_status" style="font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px; display: block;">
                    Status <span style="color: var(--accent);">*</span>
                </label>
                <select id="lab_status" class="form-control">
                    <option value="ACTIVE">ACTIVE</option>
                    <option value="VERIFIED &amp; CONCLUDED" selected>VERIFIED &amp; CONCLUDED</option>
                    <option value="IN_PROGRESS">IN_PROGRESS</option>
                    <option value="PLANNED">PLANNED</option>
                </select>
            </div>

            <div class="form-group">
                <label for="lab_category" style="font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px; display: block;">
                    Category Slug <span style="color: var(--accent);">*</span>
                </label>
                <select id="lab_category" class="form-control" onchange="syncCategoryLabel()">
                    <option value="database">database</option>
                    <option value="concurrency">concurrency</option>
                    <option value="performance">performance</option>
                    <option value="systems">systems</option>
                    <option value="network">network</option>
                    <option value="security">security</option>
                </select>
            </div>

            <div class="form-group">
                <label for="lab_categoryLabel" style="font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px; display: block;">
                    Category Display Label
                </label>
                <input
                    type="text"
                    id="lab_categoryLabel"
                    class="form-control"
                    placeholder="e.g. Database &amp; SQL"
                >
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-top: 16px;">
            <div class="form-group">
                <label for="lab_readTime" style="font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px; display: block;">
                    Read Time
                </label>
                <input type="text" id="lab_readTime" class="form-control" placeholder="e.g. 8 min" value="7 min">
            </div>

            <div class="form-group">
                <label for="lab_subsystem" style="font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px; display: block;">
                    Subsystem Flag
                </label>
                <input type="text" id="lab_subsystem" class="form-control" placeholder="e.g. DATABASE_SUBSYSTEM" style="font-family: var(--font-mono);">
            </div>

            <div class="form-group">
                <label for="lab_ref" style="font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px; display: block;">
                    Hex Reference / Commit Hash
                </label>
                <input type="text" id="lab_ref" class="form-control" placeholder="e.g. 0x47B0" style="font-family: var(--font-mono);">
            </div>
        </div>

        <div class="form-group" style="margin-top: 16px;">
            <label for="lab_title" style="font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px; display: block;">
                Investigation Title <span style="color: var(--accent);">*</span>
            </label>
            <input
                type="text"
                id="lab_title"
                class="form-control"
                required
                placeholder="e.g. B-Tree vs Hash Index Performance Under 10M Row Point Lookups"
                style="font-size: 15px; font-weight: 600;"
            >
        </div>
    </div>


    <!-- SECTION 2: RESEARCH QUESTION & METHODOLOGY -->
    <div class="card" style="padding: 24px; background: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-md);">
        <div style="border-bottom: 1px solid var(--border-subtle); padding-bottom: 14px; margin-bottom: 20px;">
            <h2 style="font-size: 16px; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-microscope" style="color: var(--accent);"></i>
                <span>Research Inquiry &amp; Methodology</span>
            </h2>
            <p style="font-size: 13px; color: var(--text-muted); margin-top: 4px;">
                Hypothesis formulation, execution harness, and test methodology.
            </p>
        </div>

        <div class="form-group">
            <label for="lab_question" style="font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px; display: block;">
                Primary Research Question
            </label>
            <textarea id="lab_question" class="form-control" rows="2" placeholder="At what table cardinality and concurrency level does memory pointer indirection outperform page traversal?"></textarea>
        </div>

        <div class="form-group" style="margin-top: 16px;">
            <label for="lab_hypothesis" style="font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px; display: block;">
                Hypothesis
            </label>
            <textarea id="lab_hypothesis" class="form-control" rows="2" placeholder="Hash indexes will yield 35% lower latency on exact-match UUID keys under high concurrency..."></textarea>
        </div>

        <div class="form-group" style="margin-top: 16px;">
            <label for="lab_environment" style="font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px; display: block;">
                Environment Specs &amp; Hardware
            </label>
            <textarea id="lab_environment" class="form-control" rows="2" placeholder="Dedicated Ubuntu 22.04 LTS, AMD EPYC 8-Core @ 3.2GHz, 32GB DDR4 RAM, NVMe PCIe 4.0 SSD..."></textarea>
        </div>

        <div class="form-group" style="margin-top: 16px;">
            <label for="lab_method" style="font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px; display: block;">
                Controlled Test Method
            </label>
            <textarea id="lab_method" class="form-control" rows="3" placeholder="Generated 10,000,000 synthetic rows with uniform distribution. Ran Sysbench for 600s across 64 concurrent threads..."></textarea>
        </div>
    </div>


    <!-- SECTION 3: EMPIRICAL OUTCOME & ARTIFACTS -->
    <div class="card" style="padding: 24px; background: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-md);">
        <div style="border-bottom: 1px solid var(--border-subtle); padding-bottom: 14px; margin-bottom: 20px;">
            <h2 style="font-size: 16px; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-chart-line" style="color: var(--accent);"></i>
                <span>Empirical Outcome &amp; Telemetry Artwork</span>
            </h2>
            <p style="font-size: 13px; color: var(--text-muted); margin-top: 4px;">
                Headline findings, technology stack badges, and benchmark visual profiles.
            </p>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 16px;">
            <div class="form-group">
                <label for="lab_outcomeTitle" style="font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px; display: block;">
                    Outcome Section Title
                </label>
                <input type="text" id="lab_outcomeTitle" class="form-control" value="EMPIRICAL OUTCOME:">
            </div>

            <div class="form-group">
                <label for="lab_outcomeHeadline" style="font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px; display: block;">
                    Outcome Headline Callout
                </label>
                <input type="text" id="lab_outcomeHeadline" class="form-control" placeholder="e.g. Result: 41.2% speedup in point query execution (2.4ms vs 4.1ms)">
            </div>
        </div>

        <div class="form-group" style="margin-top: 16px;">
            <label for="lab_outcomeDesc" style="font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px; display: block;">
                Outcome Detailed Narrative
            </label>
            <textarea id="lab_outcomeDesc" class="form-control" rows="2" placeholder="Evaluated at 10,000,000 indexed UUID rows; B-Tree cache misses triggered I/O thrashing..."></textarea>
        </div>

        <div class="form-group" style="margin-top: 16px;">
            <label for="lab_tech" style="font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px; display: block;">
                Tech Stack Badges (Comma-separated)
            </label>
            <input type="text" id="lab_tech" class="form-control" placeholder="e.g. MySQL 8.0 InnoDB, EXPLAIN ANALYZE, Sysbench 1.0.20, UUIDv4 Point Keys">
        </div>

        <!-- Telemetry Image Upload / URL -->
        <div class="form-group" style="margin-top: 16px;">
            <label for="lab_image" style="font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px; display: block;">
                Benchmark Telemetry Chart / Diagram Image
            </label>
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <input type="text" id="lab_image" class="form-control" placeholder="/uploads/benchmark_xyz.png" style="flex: 1; min-width: 240px;" oninput="updateImagePreview()">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('labImageFileInput').click()">
                    <i class="fas fa-upload"></i> Upload Image
                </button>
                <input type="file" id="labImageFileInput" accept="image/jpeg,image/png,image/webp,image/gif" style="display: none;" onchange="handleImageUpload(this)">
            </div>
            <div id="imagePreviewContainer" style="margin-top: 10px; display: none;">
                <div style="width: 240px; height: 140px; border-radius: 6px; overflow: hidden; border: 1px solid var(--border-medium); background: var(--bg-surface-elevated);">
                    <img id="imagePreview" src="" alt="Preview" style="width: 100%; height: 100%; object-fit: contain;">
                </div>
            </div>
        </div>

        <div class="form-group" style="margin-top: 16px;">
            <label for="lab_repro_command" style="font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px; display: block;">
                Deterministic Repro CLI Command
            </label>
            <textarea id="lab_repro_command" class="form-control" rows="2" placeholder="sysbench oltp_point_select --threads=64 --time=600 ... run" style="font-family: var(--font-mono); font-size: 12.5px;"></textarea>
        </div>
    </div>


    <!-- SECTION 4: TELEMETRY METRICS MATRIX -->
    <div class="card" style="padding: 24px; background: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-md);">
        <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border-subtle); padding-bottom: 14px; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
            <div>
                <h2 style="font-size: 16px; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-table" style="color: var(--accent);"></i>
                    <span>Telemetry Metrics Comparison Matrix</span>
                </h2>
                <p style="font-size: 13px; color: var(--text-muted); margin-top: 4px;">
                    Deterministic parameter baselines, tuned benchmarks, and architectural tradeoffs.
                </p>
            </div>
            <button type="button" class="btn btn-secondary btn-sm" onclick="addMetricRow()">
                <i class="fas fa-plus"></i> Add Metric Row
            </button>
        </div>

        <div class="table-responsive">
            <table class="admin-table" id="metricsTable">
                <thead>
                    <tr>
                        <th style="width: 25%;">Parameter / Metric</th>
                        <th style="width: 20%;">Baseline</th>
                        <th style="width: 20%;">Tuned / Optimized</th>
                        <th>Tradeoff / Architectural Note</th>
                        <th style="width: 50px; text-align: center;"></th>
                    </tr>
                </thead>
                <tbody id="metricsTableBody">
                    <!-- Dynamic Rows -->
                </tbody>
            </table>
        </div>
    </div>


    <!-- SECTION 5: IN-DEPTH OBSERVATIONS & CONCLUSION -->
    <div class="card" style="padding: 24px; background: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-md);">
        <div style="border-bottom: 1px solid var(--border-subtle); padding-bottom: 14px; margin-bottom: 20px;">
            <h2 style="font-size: 16px; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-book" style="color: var(--accent);"></i>
                <span>In-Depth Observations &amp; Architectural Conclusion</span>
            </h2>
            <p style="font-size: 13px; color: var(--text-muted); margin-top: 4px;">
                Comprehensive empirical write-up and production recommendations.
            </p>
        </div>

        <div class="form-group">
            <label for="lab_observations" style="font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px; display: block;">
                Telemetry Observations
            </label>
            <textarea id="lab_observations" class="form-control" rows="5" placeholder="When cardinality crossed 8M rows, the B-Tree index depth expanded to 4 levels..."></textarea>
        </div>

        <div class="form-group" style="margin-top: 16px;">
            <label for="lab_conclusion" style="font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px; display: block;">
                Architectural Conclusion &amp; Recommendation
            </label>
            <textarea id="lab_conclusion" class="form-control" rows="4" placeholder="Use Hash indexes exclusively for high-velocity point-lookup key-value caches..."></textarea>
        </div>
    </div>

    <!-- FORM ACTION FOOTER -->
    <div style="display: flex; align-items: center; justify-content: flex-end; gap: 12px; padding: 16px 0;">
        <a href="labs.php" class="btn btn-secondary">Cancel</a>
        <button type="button" class="btn btn-primary" onclick="saveLabExperiment()">
            <i class="fas fa-save"></i> Save Experiment
        </button>
    </div>

</form>

<?php
$pageScripts = ['js/lab-edit.js'];
require __DIR__ . '/partials/layout_bottom.php';
?>

