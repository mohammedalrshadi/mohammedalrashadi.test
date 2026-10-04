// ============================================================
// ADMIN — SEO WORKSPACE (seo.php)
// Communicates directly with /api/seo/analyze.php
// ============================================================

let seoArticles = [];

document.addEventListener('DOMContentLoaded', async () => {
    const authenticated = await checkAdminAuthentication();
    if (!authenticated) return;

    await loadSeoArticles();
});

async function loadSeoArticles() {
    const select = document.getElementById('seoArticleSelect');
    const tbody = document.getElementById('seoArticlesTableBody');

    try {
        const res = await fetch('/api/posts/list.php?status=all', {
            credentials: 'same-origin'
        });
        const data = await res.json();

        if (!data || !data.success || !Array.isArray(data.data)) {
            throw new Error((data && data.message) || 'Failed to fetch articles');
        }

        seoArticles = data.data.filter(p => p.type !== 'project');

        // Update Overview Cards
        const total = seoArticles.length;
        const published = seoArticles.filter(p => p.status === 'published').length;
        const drafts = total - published;

        setText('seoTotalArticles', total);
        setText('seoPublishedArticles', `${published} published`);
        setText('seoDraftArticles', `${drafts} drafts`);
        setText('seoIndexableCount', published);
        setText('seoNoindexCount', drafts);

        // Populate Select Box
        if (select) {
            if (seoArticles.length === 0) {
                select.innerHTML = '<option value="">No articles found in catalog</option>';
            } else {
                select.innerHTML = '<option value="">-- Choose an article to audit --</option>' +
                    seoArticles.map(a => {
                        const statusTag = a.status === 'published' ? '[Published]' : '[Draft]';
                        return `<option value="${a.id}">${statusTag} ${escapeHTML(a.title || 'Untitled')}</option>`;
                    }).join('');
            }
        }

        // Populate Table Body
        if (tbody) {
            if (seoArticles.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 36px 20px; color: var(--text-muted);">
                            No articles found. Create an article first to run SEO audits.
                        </td>
                    </tr>
                `;
            } else {
                tbody.innerHTML = seoArticles.map(art => {
                    const safeTitle = escapeHTML(art.title || 'Untitled');
                    const safeCat = escapeHTML(art.category || 'General');
                    const isPub = art.status === 'published';
                    const indexableBadge = isPub
                        ? '<span class="status-badge status-published"><i class="fas fa-check"></i> Live Index</span>'
                        : '<span class="status-badge status-warning"><i class="fas fa-eye-slash"></i> Noindex (Draft)</span>';

                    return `
                        <tr>
                            <td>
                                <strong style="color: var(--text-primary); font-size: 13.5px;">${safeTitle}</strong>
                            </td>
                            <td>
                                <span class="status-badge status-neutral" style="font-size: 11px;">Article</span>
                            </td>
                            <td style="font-size: 12.5px; color: var(--text-secondary);">
                                ${safeCat}
                            </td>
                            <td style="text-align: center;">
                                ${indexableBadge}
                            </td>
                            <td style="text-align: right;">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="selectAndAuditArticle(${art.id})" style="font-size: 11px;">
                                    <i class="fas fa-magnifying-glass-chart"></i> Audit
                                </button>
                            </td>
                        </tr>
                    `;
                }).join('');
            }
        }

    } catch (err) {
        console.error('Failed to load SEO articles:', err);
        showToast('Error loading articles: ' + (err.message || 'Network error'), 'error');
    }
}

function handleSelectArticleForAudit() {
    const select = document.getElementById('seoArticleSelect');
    const btn = document.getElementById('seoAuditBtn');
    if (!select || !btn) return;

    btn.disabled = !select.value;
}

function selectAndAuditArticle(id) {
    const select = document.getElementById('seoArticleSelect');
    if (select) {
        select.value = String(id);
        handleSelectArticleForAudit();
    }
    window.scrollTo({ top: 180, behavior: 'smooth' });
    runLiveSeoAudit();
}

async function runLiveSeoAudit() {
    const select = document.getElementById('seoArticleSelect');
    const keywordInput = document.getElementById('seoTargetKeyword');
    const btn = document.getElementById('seoAuditBtn');
    const resultsSection = document.getElementById('seoResultsSection');
    const reportBody = document.getElementById('seoReportBody');
    const titleEl = document.getElementById('seoResultTitle');
    const metaEl = document.getElementById('seoResultMeta');
    const editLink = document.getElementById('seoEditArticleLink');

    const postId = select ? select.value : '';
    if (!postId) {
        showToast('Please select an article first.', 'error');
        return;
    }

    const keyword = keywordInput ? keywordInput.value.trim() : '';

    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Auditing...';
    }

    try {
        let url = `/api/seo/analyze.php?id=${encodeURIComponent(postId)}`;
        if (keyword) {
            url += `&focus_keyword=${encodeURIComponent(keyword)}`;
        }

        const res = await fetch(url, { credentials: 'same-origin' });
        const json = await res.json();

        if (!json || !json.success || !json.data) {
            throw new Error((json && json.message) || 'Analysis failed');
        }

        const data = json.data;
        const summary = data.summary || {};
        const post = data.post || {};
        const categories = data.categories || {};

        if (resultsSection) resultsSection.style.display = 'block';

        if (titleEl) titleEl.textContent = `SEO Audit: ${post.title || 'Article'}`;
        if (metaEl) {
            metaEl.textContent = `Status: ${post.status || 'unknown'} · Words: ${post.word_count || 0} · Reading time: ~${post.reading_time_min || 1} min ${post.focus_keyword ? '· Focus Keyword: "' + escapeHTML(post.focus_keyword) + '"' : ''}`;
        }
        if (editLink) {
            editLink.href = `articles.php?search=${encodeURIComponent(post.title || '')}`;
        }

        // Render Summary Score Header
        const score = Number(summary.score || 0);
        let scoreColor = 'var(--success)';
        if (score < 50) scoreColor = 'var(--danger)';
        else if (score < 75) scoreColor = 'var(--warning)';

        let html = `
            <div style="background: var(--bg-surface-elevated); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); padding: 20px; display: flex; align-items: center; justify-content: space-between; gap: 20px; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 18px;">
                    <div style="width: 64px; height: 64px; border-radius: 50%; border: 3px solid ${scoreColor}; display: flex; flex-direction: column; align-items: center; justify-content: center; font-family: var(--font-mono);">
                        <span style="font-size: 20px; font-weight: 700; color: ${scoreColor};">${score}</span>
                        <span style="font-size: 9px; color: var(--text-muted); text-transform: uppercase;">/ 100</span>
                    </div>
                    <div>
                        <div style="font-size: 16px; font-weight: 600; color: var(--text-primary);">Grade: ${escapeHTML(summary.grade || 'C')}</div>
                        <div style="font-size: 12.5px; color: var(--text-muted); margin-top: 2px;">
                            <span style="color: var(--success);"><i class="fas fa-check-circle"></i> ${summary.passed_count || 0} passed</span> · 
                            <span style="color: var(--warning);"><i class="fas fa-exclamation-triangle"></i> ${summary.warning_count || 0} warnings</span> · 
                            <span style="color: var(--danger);"><i class="fas fa-times-circle"></i> ${summary.error_count || 0} critical</span>
                        </div>
                    </div>
                </div>

                <div style="font-size: 12px; color: var(--text-secondary); max-width: 380px; text-align: right;">
                    ${post.status === 'published' 
                        ? '<span class="status-badge status-published">Indexed on Production</span>' 
                        : '<span class="status-badge status-warning">Draft (Emits noindex until published)</span>'}
                </div>
            </div>
        `;

        // Render Categories
        html += '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 16px;">';

        for (const [catKey, catData] of Object.entries(categories)) {
            const catTitle = catData.label_en || catData.label || catKey;
            const catPassed = catData.passed ? '<span class="status-badge status-published">Passed</span>' : '<span class="status-badge status-warning">Check</span>';
            const rules = catData.rules || [];

            html += `
                <div style="background: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); padding: 16px; display: flex; flex-direction: column; gap: 10px;">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <h4 style="margin: 0; font-size: 13.5px; font-weight: 600; color: var(--text-primary); text-transform: capitalize;">${escapeHTML(catTitle)}</h4>
                        ${catPassed}
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 8px; font-size: 12px;">
                        ${rules.map(rule => {
                            const isOk = rule.status === 'pass';
                            const isWarn = rule.status === 'warning';
                            const icon = isOk 
                                ? '<i class="fas fa-check" style="color: var(--success);"></i>'
                                : (isWarn 
                                    ? '<i class="fas fa-triangle-exclamation" style="color: var(--warning);"></i>'
                                    : '<i class="fas fa-circle-xmark" style="color: var(--danger);"></i>');

                            const msg = escapeHTML(rule.message_en || rule.message || '');

                            return `
                                <div style="display: flex; align-items: flex-start; gap: 8px; line-height: 1.4;">
                                    <span style="margin-top: 2px;">${icon}</span>
                                    <span style="color: ${isOk ? 'var(--text-secondary)' : 'var(--text-primary)'};">${msg}</span>
                                </div>
                            `;
                        }).join('')}
                    </div>
                </div>
            `;
        }

        html += '</div>';

        if (reportBody) {
            reportBody.innerHTML = html;
        }

        resultsSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        showToast('SEO Audit completed.', 'success');

    } catch (err) {
        console.error('Audit failed:', err);
        showToast('Audit failed: ' + (err.message || 'Error occurred'), 'error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-magnifying-glass-chart"></i> <span>Audit Post</span>';
        }
    }
}
