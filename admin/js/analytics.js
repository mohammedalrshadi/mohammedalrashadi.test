// ============================================================
// ADMIN — TELEMETRY ANALYTICS (analytics.php)
// Communicates with /api/analytics/dashboard.php
// ============================================================

document.addEventListener('DOMContentLoaded', async () => {
    const authenticated = await checkAdminAuthentication();
    if (!authenticated) return;

    await loadAnalyticsData();
});

async function loadAnalyticsData() {
    try {
        const res = await fetch('/api/analytics/dashboard.php', {
            credentials: 'same-origin'
        });
        const json = await res.json();

        if (!json || !json.success || !json.data) {
            throw new Error((json && json.message) || 'Failed to load analytics data');
        }

        const data = json.data;
        const summary = data.summary || {};
        const chartData = data.chart_7d || [];
        const mostRead = data.most_read || [];
        const leastRead = data.least_read || [];

        // Summary metrics
        setText('analyticsLifetimeVisitors', (summary.estimated_unique_visitors || 0).toLocaleString());
        setText('analyticsLifetimeReads', (summary.lifetime_reads || 0).toLocaleString());
        setText('analyticsSevenDayVisitors', (summary.seven_day_visitors || 0).toLocaleString());
        setText('analyticsSevenDayReads', (summary.seven_day_reads || 0).toLocaleString());
        setText('analyticsAvgDailyVisitors', (summary.avg_daily_visitors || 0).toLocaleString());

        updateTrend('analyticsVisitorsTrend', summary.visitors_trend);
        updateTrend('analyticsReadsTrend', summary.reads_trend);

        // Render 7-Day Chart & Daily Table
        renderAnalyticsChart(chartData);
        renderDailyTable(chartData);

        // Render Rankings
        renderRankings('analyticsMostReadList', mostRead, 'most');
        renderRankings('analyticsLeastReadList', leastRead, 'least');

    } catch (err) {
        console.error('Failed to load analytics data:', err);
        showToast('Error loading analytics: ' + (err.message || 'Network error'), 'error');
    }
}

function updateTrend(elementId, trend) {
    const el = document.getElementById(elementId);
    if (!el) return;

    if (!trend) {
        el.className = 'stat-trend trend-neutral';
        el.innerHTML = '<i class="fas fa-minus"></i> 0%';
        return;
    }

    const state = trend.state;
    const label = trend.label || '—';

    if (state === 'up') {
        el.className = 'stat-trend trend-up';
        el.innerHTML = `<i class="fas fa-arrow-up"></i> ${label}`;
    } else if (state === 'down') {
        el.className = 'stat-trend trend-down';
        el.innerHTML = `<i class="fas fa-arrow-down"></i> ${label}`;
    } else if (state === 'new') {
        el.className = 'stat-trend trend-new';
        el.innerHTML = `<i class="fas fa-bolt"></i> ${label}`;
    } else {
        el.className = 'stat-trend trend-neutral';
        el.innerHTML = `<i class="fas fa-minus"></i> ${label}`;
    }
}

function renderAnalyticsChart(chartData) {
    const svg = document.querySelector('.weekly-bar-chart');
    if (!svg || !Array.isArray(chartData) || chartData.length !== 7) return;

    const visitorCounts = chartData.map(d => Number(d.visitors || 0));
    const maxVisitor = Math.max(...visitorCounts, 0);

    let gridMax = 400;
    if (maxVisitor <= 10) gridMax = 10;
    else if (maxVisitor <= 50) gridMax = 50;
    else if (maxVisitor <= 100) gridMax = 100;
    else if (maxVisitor <= 200) gridMax = 200;
    else if (maxVisitor <= 400) gridMax = 400;
    else gridMax = Math.ceil(maxVisitor / 100) * 100;

    const gridTexts = svg.querySelectorAll('text[text-anchor="end"]');
    if (gridTexts.length >= 5) {
        gridTexts[0].textContent = String(gridMax);
        gridTexts[1].textContent = String(Math.round(gridMax * 0.75));
        gridTexts[2].textContent = String(Math.round(gridMax * 0.5));
        gridTexts[3].textContent = String(Math.round(gridMax * 0.25));
        gridTexts[4].textContent = '0';
    }

    const rects = svg.querySelectorAll('rect');
    const dayTexts = svg.querySelectorAll('text[text-anchor="middle"]');

    chartData.forEach((day, idx) => {
        if (rects[idx]) {
            const visitors = Number(day.visitors || 0);
            const barHeight = gridMax > 0 ? Math.round((visitors / gridMax) * 130) : 0;
            const finalHeight = visitors > 0 ? Math.max(barHeight, 4) : 0;
            const barY = 150 - finalHeight;

            rects[idx].setAttribute('y', String(barY));
            rects[idx].setAttribute('height', String(finalHeight));

            let titleEl = rects[idx].querySelector('title');
            if (!titleEl) {
                titleEl = document.createElementNS('http://www.w3.org/2000/svg', 'title');
                rects[idx].appendChild(titleEl);
            }
            titleEl.textContent = `${day.day_name || day.date}: ${visitors} visitors · ${day.reads || 0} reads`;
        }

        if (dayTexts[idx]) {
            dayTexts[idx].textContent = day.day_name || day.date.substring(5);
        }
    });
}

function renderDailyTable(chartData) {
    const tbody = document.getElementById('analyticsDailyTableBody');
    if (!tbody) return;

    if (!Array.isArray(chartData) || chartData.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" style="text-align: center; padding: 24px; color: var(--text-muted);">No activity recorded.</td></tr>';
        return;
    }

    tbody.innerHTML = chartData.map(day => `
        <tr>
            <td style="font-family: var(--font-mono); font-size: 12.5px; color: var(--text-primary);">${escapeHTML(day.date)}</td>
            <td style="font-size: 13px; color: var(--text-secondary);">${escapeHTML(day.day_name || '—')}</td>
            <td style="text-align: right; font-family: var(--font-mono); font-weight: 600; color: var(--accent);">${Number(day.visitors || 0).toLocaleString()}</td>
            <td style="text-align: right; font-family: var(--font-mono); font-weight: 600; color: var(--success);">${Number(day.reads || 0).toLocaleString()}</td>
        </tr>
    `).join('');
}

function renderRankings(listId, articles, type) {
    const list = document.getElementById(listId);
    if (!list) return;

    if (!Array.isArray(articles) || articles.length === 0) {
        list.innerHTML = '<li class="ranked-empty-state">No published articles recorded yet.</li>';
        return;
    }

    const badgeClass = type === 'most' ? 'rank-badge-gold' : 'rank-badge-coral';

    list.innerHTML = articles.map((article, idx) => {
        const safeTitle = escapeHTML(article.title || 'Untitled');
        const views = Number(article.views || 0);
        const readsText = views > 0 ? `${views.toLocaleString()} reads` : '0 reads';
        const imgUrl = article.image_url ? escapeHTML(article.image_url) : null;

        const thumbMarkup = imgUrl
            ? `<img src="${imgUrl}" alt="" class="ranked-item-thumb" loading="lazy" onerror="this.outerHTML='<span class=\\'ranked-item-thumb-placeholder\\'><i class=\\'far fa-file-lines\\'></i></span>'"/>`
            : `<span class="ranked-item-thumb-placeholder"><i class="far fa-file-lines"></i></span>`;

        return `
            <li class="ranked-content-item">
                <a href="articles.php?search=${encodeURIComponent(article.title || '')}" class="ranked-item-link" title="${safeTitle}">
                    <span class="rank-badge ${badgeClass}">${idx + 1}</span>
                    ${thumbMarkup}
                    <div class="ranked-item-info">
                        <span class="ranked-item-title">${safeTitle}</span>
                        <span class="ranked-item-reads">${readsText}</span>
                    </div>
                </a>
            </li>
        `;
    }).join('');
}
