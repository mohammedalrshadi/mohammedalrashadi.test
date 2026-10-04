// ============================================================
// ADMIN — DASHBOARD / OVERVIEW PAGE (index.php) [IMP-032]
// Pulls real numbers from the existing list endpoints
// (api/posts/list.php, api/users/list.php, api/reviews/list.php)
// — no new API endpoint, no hardcoded/fake statistics.
// Every metric, ranking, attention item, and recent activity
// feed row shown here is derived directly from authentic data.
// Strictly maintains all DOM IDs and functional contracts.
// ============================================================


// ============================================================
// INITIALIZATION
// ============================================================

document.addEventListener(
    'DOMContentLoaded',
    async () => {

        const authenticated =
            await checkAdminAuthentication();

        if (!authenticated) {
            return;
        }

        initUtilityBar();
        await loadDashboard();

    }
);


// ============================================================
// TOP UTILITY BAR & KEYBOARD SHORTCUTS
// ============================================================

function initUtilityBar() {

    if (currentUser) {

        let displayName = currentUser.name || currentUser.email || 'Admin';
        if (currentUser.name) {
            displayName = displayName.split(' ').map(w => w.charAt(0).toUpperCase() + w.slice(1).toLowerCase()).join(' ');
        }

        // setText('dashboardGreeting', 'Welcome back, ' + displayName);
        setText('userProfileName', displayName);
        setText(
            'userProfileRole',
            currentUser.role === 'admin' ? 'Administrator' : 'User'
        );

        const initialEl = document.getElementById('userAvatarInitial');
        if (initialEl) {
            const firstChar = displayName.trim().charAt(0).toUpperCase() || 'M';
            initialEl.textContent = firstChar;
        }

    }

    // ⌘K / Ctrl+K keyboard shortcut focus
    document.addEventListener('keydown', (e) => {
        if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            const searchInput = document.getElementById('dashboardSearchInput');
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        }
    });

    // Search input enter navigation to articles search
    const searchInput = document.getElementById('dashboardSearchInput');
    if (searchInput) {
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                const query = searchInput.value.trim();
                if (query) {
                    window.location.href = `articles.php?search=${encodeURIComponent(query)}`;
                }
            }
        });
    }

}


// ============================================================
// LOAD DASHBOARD DATA
// ============================================================

async function loadDashboard() {

    setSystemStatus('db', 'Checking...', 'pending');
    setSystemStatus('auth', 'Checking...', 'pending');

    try {

        const [
            postsResult,
            usersResult,
            reviewsResult,
            analyticsResult,
            feedResult,
            countsResult,
        ] = await Promise.all([
            fetchJSON('/api/posts/list.php?status=all'),
            fetchJSON('/api/users/list.php'),
            fetchJSON('/api/reviews/list.php?status=all'),
            fetchJSON('/api/analytics/dashboard.php').catch(() => ({ success: false })),
            fetchJSON('/api/activity/admin_feed.php?limit=8&grouped=true').catch(() => ({ success: false })),
            fetchJSON('/api/stats/counts.php').catch(() => ({ success: false })),
        ]);

        if (
            !postsResult.success ||
            !usersResult.success ||
            !reviewsResult.success
        ) {
            throw new Error(
                'Could not load all dashboard data.'
            );
        }

        const posts = postsResult.data || [];
        const users = usersResult.data || [];
        const reviews = reviewsResult.data || [];
        const analytics = (analyticsResult && analyticsResult.success) ? analyticsResult.data : null;

        renderStats(posts, users, reviews, analytics);
        renderContentPerformance(posts, analytics);
        renderContentSummary(posts, users, reviews, countsResult);
        renderRecentActivity(posts, users, reviews, feedResult);
        renderLastUpdated(posts, users, reviews);
        renderQuickStatsAndChart(analytics);

        setSystemStatus('db', 'Connected', 'ok');
        setSystemStatus('auth', 'Active', 'ok');

        const webStatus = document.getElementById('systemStatus_web');
        if (webStatus) {
            webStatus.textContent = 'Operational';
            webStatus.className = 'system-status-pill status-pill-success';
        }

    } catch (error) {

        console.error(
            '❌ Dashboard load error:',
            error
        );

        setSystemStatus('db', 'Unreachable', 'error');
        setSystemStatus('auth', 'Auth Error', 'error');

        showToast(
            'Error loading dashboard: ' +
            (error.message || 'Unknown error'),
            'error'
        );

    }

}


// ============================================================
// FETCH HELPER
// ============================================================

async function fetchJSON(url) {

    const response = await fetch(
        url,
        {
            method: 'GET',
            credentials: 'same-origin',
        }
    );

    return response.json();

}


// ============================================================
// 1. SUMMARY STATISTICS CARDS (WEBSITE PERFORMANCE)
// ============================================================

function renderStats(posts, users, reviews, analytics = null) {

    const articles =
        posts.filter(
            p => p.type !== 'project'
        );

    const projects =
        posts.filter(
            p => p.type === 'project'
        );

    const publishedArticles =
        articles.filter(
            p => p.status === 'published'
        );

    const draftArticles =
        articles.filter(
            p => p.status === 'draft'
        );

    const hiddenArticles =
        articles.filter(
            p => p.status === 'hidden'
        );

    const pendingReviews =
        reviews.filter(
            r => r.status === 'pending'
        );

    const publishedAchievements =
        projects.filter(
            p => p.status === 'published'
        );

    const hiddenAchievements =
        projects.filter(
            p => p.status !== 'published'
        );

    const adminUsers =
        users.filter(
            u => u.role === 'admin'
        );

    const regularUsers =
        users.filter(
            u => u.role !== 'admin'
        );

    const hiddenArticlesCount = articles.length - publishedArticles.length;
    const publishedAchievementsCount = publishedAchievements.length;
    const hiddenAchievementsCount = hiddenAchievements.length;

    // Primary Numbers
    setText('statTotalArticles', articles.length);
    setText('statPublishedArticles', publishedArticles.length);
    setText('statTotalAchievements', projects.length);
    setText('statTotalUsers', users.length);
    setText('statTotalReviews', reviews.length);
    setText('statPendingReviews', pendingReviews.length);

    // Meta Breakdowns
    setText('statPublishedArticlesMeta', `${publishedArticles.length} published`);
    setText('statDraftArticlesMeta', `${draftArticles.length} drafts`);
    setText('statTotalArticlesMeta', `${hiddenArticles.length} hidden`);

    setText('statTotalAchievementsMeta', `${publishedAchievementsCount} published`);
    setText('statHiddenAchievementsMeta', `${hiddenAchievementsCount} hidden`);

    setText('statTotalUsersMeta', `${adminUsers.length} admin${adminUsers.length === 1 ? '' : 's'}`);
    setText('statRegularUsersMeta', `${regularUsers.length} regular user${regularUsers.length === 1 ? '' : 's'}`);

    setText('statTotalReviewsMeta', `${pendingReviews.length} pending moderation`);
    setText(
        'statPendingReviewsMeta',
        pendingReviews.length > 0 ? `${pendingReviews.length} require moderation` : 'No pending reviews'
    );

    // Visitors & Reads (Authoritative Live Telemetry [IMP-033])
    // 1. Estimated Lifetime Unique Visitors
    const estVisitors = analytics && analytics.summary && analytics.summary.estimated_unique_visitors != null
        ? analytics.summary.estimated_unique_visitors
        : 0;
    setText('statTotalVisitors', estVisitors.toLocaleString('en-US'));
    setText('statVisitorsMeta', 'Unique Visitors (Est.)');
    updateTrendBadge('statVisitorsTrend', analytics && analytics.summary ? analytics.summary.visitors_trend : null);

    // 2. Lifetime Site-Wide Article Reads (Survives article purge)
    let totalRecordedReads = 0;
    if (analytics && analytics.summary && analytics.summary.lifetime_reads != null) {
        totalRecordedReads = analytics.summary.lifetime_reads;
    } else {
        articles.forEach(a => {
            if (a.views && !isNaN(a.views)) {
                totalRecordedReads += Number(a.views);
            }
        });
    }

    setText('statTotalReads', totalRecordedReads.toLocaleString('en-US'));
    setText('statReadsMeta', 'Article Reads');
    updateTrendBadge('statReadsTrend', analytics && analytics.summary ? analytics.summary.reads_trend : null);

    // Pending reviews badge in Moderate Reviews quick action button
    const reviewsBadge = document.getElementById('moderateReviewsBadge');
    if (reviewsBadge) {
        if (pendingReviews.length > 0) {
            reviewsBadge.style.display = 'inline-block';
            reviewsBadge.textContent = String(pendingReviews.length);
        } else {
            reviewsBadge.style.display = 'none';
        }
    }

    // Attention Panel Elements
    setText('attentionHiddenArticles', hiddenArticlesCount);
    setText('attentionHiddenAchievements', hiddenAchievementsCount);

    if (hiddenArticlesCount > 0) {
        setText('attentionArticlesMeta', `${hiddenArticlesCount} article${hiddenArticlesCount === 1 ? '' : 's'} not visible publicly`);
    } else {
        setText('attentionArticlesMeta', 'All articles published and active');
    }

    if (hiddenAchievementsCount > 0) {
        setText('attentionAchievementsMeta', `${hiddenAchievementsCount} project${hiddenAchievementsCount === 1 ? '' : 's'} currently hidden`);
    } else {
        setText('attentionAchievementsMeta', 'All projects published and active');
    }

    // Dynamic Row Indicators
    updateAttentionRowIndicator(
        'attentionReviewIndicator',
        pendingReviews.length > 0,
        'hourglass'
    );
    updateAttentionRowIndicator(
        'attentionArticlesIndicator',
        hiddenArticlesCount > 0,
        'file'
    );
    updateAttentionRowIndicator(
        'attentionAchievementsIndicator',
        hiddenAchievementsCount > 0,
        'medal'
    );

    // Dynamic Attention State Styling & Positive All-Clear
    const attentionPanel = document.getElementById('dashboardAttentionPanel');
    const attentionBadge = document.getElementById('attentionStateBadge');
    const attentionAllClear = document.getElementById('attentionAllClear');
    const reviewRow = document.getElementById('attentionRowReviews');
    const articlesRow = document.getElementById('attentionRowArticles');
    const achieveRow = document.getElementById('attentionRowAchievements');

    if (reviewRow) reviewRow.classList.toggle('is-attention-required', pendingReviews.length > 0);
    if (articlesRow) articlesRow.classList.toggle('is-attention-required', hiddenArticlesCount > 0);
    if (achieveRow) achieveRow.classList.toggle('is-attention-required', hiddenAchievementsCount > 0);

    const reviewBtn = document.getElementById('attentionBtnReviews') || reviewRow?.querySelector('.btn');
    const articlesBtn = document.getElementById('attentionBtnArticles') || articlesRow?.querySelector('.btn');
    const achieveBtn = document.getElementById('attentionBtnAchievements') || achieveRow?.querySelector('.btn');

    if (reviewBtn) reviewBtn.style.display = pendingReviews.length > 0 ? 'inline-flex' : 'none';
    if (articlesBtn) articlesBtn.style.display = hiddenArticlesCount > 0 ? 'inline-flex' : 'none';
    if (achieveBtn) achieveBtn.style.display = hiddenAchievementsCount > 0 ? 'inline-flex' : 'none';

    if (attentionPanel && attentionBadge) {
        if (pendingReviews.length > 0) {
            attentionPanel.classList.add('has-attention');
            attentionPanel.classList.remove('all-clear');
            attentionBadge.textContent = `${pendingReviews.length} Pending Action`;
            attentionBadge.className = 'status-badge status-warning';
            if (attentionAllClear) attentionAllClear.style.display = 'none';
        } else if (hiddenArticlesCount > 0 || hiddenAchievementsCount > 0) {
            attentionPanel.classList.remove('all-clear');
            attentionPanel.classList.remove('has-attention');
            attentionBadge.textContent = 'UNPUBLISHED CONTENT';
            attentionBadge.className = 'status-badge status-neutral';
            if (attentionAllClear) attentionAllClear.style.display = 'none';
        } else {
            attentionPanel.classList.add('all-clear');
            attentionPanel.classList.remove('has-attention');
            attentionBadge.innerHTML = '<i class="fas fa-check" style="margin-right: 4px;"></i> ALL CLEAR';
            attentionBadge.className = 'status-badge status-success';
            if (attentionAllClear) attentionAllClear.style.display = 'none';
        }
    }

    // System-status panel operational counts
    setText('sysCountUsers', users.length);
    setText('sysCountArticles', articles.length);
    setText('sysCountPendingReviews', pendingReviews.length);

}

function updateAttentionRowIndicator(indicatorId, isPending, iconType) {
    const el = document.getElementById(indicatorId);
    if (!el) return;

    if (isPending) {
        el.className = 'attention-status-indicator status-warning';
        if (iconType === 'hourglass') {
            el.innerHTML = '<i class="fas fa-hourglass-half" aria-hidden="true"></i>';
        } else if (iconType === 'file') {
            el.innerHTML = '<i class="far fa-file-lines" aria-hidden="true"></i>';
        } else {
            el.innerHTML = '<i class="fas fa-medal" aria-hidden="true"></i>';
        }
    } else {
        el.className = 'attention-status-indicator status-success';
        el.innerHTML = '<i class="fas fa-check" aria-hidden="true"></i>';
    }
}


// ============================================================
// 2. CONTENT PERFORMANCE (MOST READ & LEAST READ)
// ============================================================

function renderContentPerformance(posts, analytics) {

    const mostReadList = document.getElementById('mostReadArticlesList');
    const leastReadList = document.getElementById('leastReadArticlesList');

    if (!mostReadList || !leastReadList) {
        return;
    }

    // If live analytics rankings are available from /api/analytics/dashboard.php [IMP-033]
    if (analytics && Array.isArray(analytics.most_read) && Array.isArray(analytics.least_read)) {
        if (analytics.most_read.length === 0) {
            mostReadList.innerHTML = '<li class="ranked-empty-state">No published articles yet.</li>';
        } else {
            mostReadList.innerHTML = analytics.most_read.map(
                (article, idx) => renderRankedRow(article, idx + 1, 'most')
            ).join('');
        }

        if (analytics.least_read.length === 0) {
            leastReadList.innerHTML = '<li class="ranked-empty-state">No published articles yet.</li>';
        } else {
            leastReadList.innerHTML = analytics.least_read.map(
                (article, idx) => renderRankedRow(article, idx + 1, 'least')
            ).join('');
        }
        return;
    }

    const publishedArticles = posts.filter(
        p => p.type !== 'project' && p.status === 'published'
    );

    if (publishedArticles.length === 0) {
        mostReadList.innerHTML = '<li class="ranked-empty-state">No published articles yet.</li>';
        leastReadList.innerHTML = '<li class="ranked-empty-state">No published articles yet.</li>';
        return;
    }

    // Fallback Sort for Most Read: by views DESC, then created_at DESC
    const sortedMost = [...publishedArticles].sort((a, b) => {
        const viewsA = Number(a.views || a.read_count || 0);
        const viewsB = Number(b.views || b.read_count || 0);
        if (viewsB !== viewsA) {
            return viewsB - viewsA;
        }
        return new Date(b.created_at || 0) - new Date(a.created_at || 0);
    }).slice(0, 5);

    // Fallback Sort for Least Read: by views ASC, then created_at ASC
    const sortedLeast = [...publishedArticles].sort((a, b) => {
        const viewsA = Number(a.views || a.read_count || 0);
        const viewsB = Number(b.views || b.read_count || 0);
        if (viewsA !== viewsB) {
            return viewsA - viewsB;
        }
        return new Date(a.created_at || 0) - new Date(b.created_at || 0);
    }).slice(0, 5);

    mostReadList.innerHTML = sortedMost.map(
        (article, idx) => renderRankedRow(article, idx + 1, 'most')
    ).join('');

    leastReadList.innerHTML = sortedLeast.map(
        (article, idx) => renderRankedRow(article, idx + 1, 'least')
    ).join('');

}

function renderRankedRow(article, rank, type) {

    const rankNumber = String(rank);
    const safeTitle = escapeHTML(article.title || 'Untitled');
    const views = Number(article.views || article.read_count || 0);
    const readsText = views > 0 ? `${views.toLocaleString('en-US')} reads` : '0 reads';

    const badgeClass = type === 'most' ? 'rank-badge-gold' : 'rank-badge-coral';

    let thumbMarkup = '';
    if (article.image_url) {
        thumbMarkup = `
            <img
                src="${escapeHTML(article.image_url)}"
                alt=""
                class="ranked-item-thumb"
                loading="lazy"
                onerror="this.outerHTML='<span class=\\'ranked-item-thumb-placeholder\\'><i class=\\'far fa-file-lines\\'></i></span>'"
            />
        `;
    } else {
        thumbMarkup = `
            <span class="ranked-item-thumb-placeholder">
                <i class="far fa-file-lines"></i>
            </span>
        `;
    }

    return `
        <li class="ranked-content-item">
            <a href="articles.php?search=${encodeURIComponent(article.title || '')}" class="ranked-item-link" title="${safeTitle}">
                <span class="rank-badge ${badgeClass}">${rankNumber}</span>
                ${thumbMarkup}
                <div class="ranked-item-info">
                    <span class="ranked-item-title">${safeTitle}</span>
                    <span class="ranked-item-reads">${readsText}</span>
                </div>
            </a>
        </li>
    `;

}


// ============================================================
// 3. CONTENT SUMMARY HUB
// ============================================================

function renderContentSummary(posts, users, reviews, countsResult = null) {

    const articles =
        posts.filter(
            p => p.type !== 'project'
        );

    const projects =
        posts.filter(
            p => p.type === 'project'
        );

    const publishedArticles =
        articles.filter(p => p.status === 'published').length;

    const hiddenArticles =
        articles.length - publishedArticles;

    const publishedAchievements =
        projects.filter(p => p.status === 'published').length;

    const hiddenAchievements =
        projects.length - publishedAchievements;

    const pendingReviews =
        reviews.filter(r => r.status === 'pending').length;

    const approvedReviews =
        reviews.filter(r => r.status === 'approved').length;

    const rejectedReviews =
        reviews.filter(r => r.status === 'rejected').length;

    const adminUsers =
        users.filter(u => u.role === 'admin').length;

    const regularUsers =
        users.length - adminUsers;

    setText('summaryArticlesCount', articles.length);
    setText(
        'summaryArticlesBreakdown',
        `Published: ${publishedArticles} · Hidden: ${hiddenArticles}`
    );

    setText('summaryAchievementsCount', projects.length);
    setText(
        'summaryAchievementsBreakdown',
        `Published: ${publishedAchievements} · Hidden: ${hiddenAchievements}`
    );

    setText('summaryReviewsCount', reviews.length);
    setText('statTotalReviews', reviews.length);
    setText(
        'summaryReviewsBreakdown',
        `Pending: ${pendingReviews} · Approved: ${approvedReviews} · Rejected: ${rejectedReviews}`
    );

    setText('summaryUsersCount', users.length);
    setText(
        'summaryUsersBreakdown',
        `Admins: ${adminUsers} · Users: ${regularUsers}`
    );

    if (countsResult && countsResult.success && countsResult.data) {
        const d = countsResult.data;
        if (d.products) {
            setText('summaryProductsCount', d.products.total || 0);
            setText(
                'summaryProductsBreakdown',
                `Published: ${d.products.published || 0} · Downloads: ${d.products.downloads || 0}`
            );
        }
        if (d.labs) {
            setText('summaryLabsCount', d.labs.total || 0);
            setText(
                'summaryLabsBreakdown',
                `Active: ${d.labs.active || 0} · Planned: ${d.labs.planned || 0}`
            );
        }
    }

}


// ============================================================
// 4. RECENT ACTIVITY
// Timeline feed backed by unified api/activity/admin_feed.php
// (posts, products, reviews, journey, user actions, audits).
// ============================================================

function renderRecentActivity(posts, users, reviews, feedResult = null) {

    const list =
        document.getElementById(
            'recentActivityList'
        );

    const emptyMessage =
        document.getElementById(
            'recentActivityEmpty'
        );

    if (!list) {
        return;
    }

    list.innerHTML = '';

    // Prefer genuine chronological feed from api/activity/admin_feed.php
    if (feedResult && feedResult.success && Array.isArray(feedResult.data) && feedResult.data.length > 0) {
        if (emptyMessage) {
            emptyMessage.style.display = 'none';
        }

        feedResult.data.forEach(item => {
            let actorName = item.actor_name || '';
            if (actorName) {
                actorName = actorName.split(' ').map(w => w.charAt(0).toUpperCase() + w.slice(1).toLowerCase()).join(' ');
            }
            const li = document.createElement('li');
            li.className = 'activity-row';
            
            const targetHtml = item.target_name ? ` · <span>${escapeHTML(item.target_name)}</span>` : '';
            const actionContent = item.url !== '#' 
                ? `<a href="${escapeHTML(item.url)}" style="color: inherit; text-decoration: none;" class="hover-underline">${escapeHTML(item.action_label)}</a>`
                : escapeHTML(item.action_label);

            const formattedDate = item.created_at ? formatEnglishRelativeTime(item.created_at) : '';
            const fullDate = item.created_at ? new Date(item.created_at).toLocaleString('en-US', {timeZone: 'Asia/Riyadh'}) : '';

            li.innerHTML = `
                <div class="activity-icon-badge ${escapeHTML(item.type_class)}">
                    <i class="fas ${escapeHTML(item.icon)}"></i>
                </div>
                <div class="activity-content">
                    <div class="activity-action">${actionContent}</div>
                    <div class="activity-meta" title="${escapeHTML(actorName)}${item.target_name ? ' · ' + escapeHTML(item.target_name) : ''}">
                        ${escapeHTML(actorName)}${targetHtml}
                    </div>
                </div>
                <div class="activity-time" title="${escapeHTML(fullDate)}">
                    ${escapeHTML(formattedDate)}
                </div>
            `;

            // Make the whole row clickable if there's a link
            if (item.url !== '#') {
                li.style.cursor = 'pointer';
                li.addEventListener('click', (e) => {
                    if (e.target.tagName.toLowerCase() !== 'a') {
                        window.location.href = item.url;
                    }
                });
            }

            list.appendChild(li);
        });

        return;
    }

    // Fallback if feed API is empty or unavailable
    const articles =
        posts.filter(
            p => p.type !== 'project'
        );

    const projects =
        posts.filter(
            p => p.type === 'project'
        );

    const latestArticle = latestByDate(articles);
    const latestAchievement = latestByDate(projects);
    const latestUser = latestByDate(users);
    const latestReview = latestByDate(reviews);

    const rows = [];

    if (latestArticle) {
        rows.push({
            icon: 'far fa-file-lines',
            colorClass: 'circle-green',
            label: latestArticle.status === 'published' ? 'Article published' : 'Article draft saved',
            title: `"${latestArticle.title}"`,
            date: latestArticle.updated_at || latestArticle.created_at,
            user: currentUser ? (currentUser.name || currentUser.email) : 'Admin',
            statusLabel: latestArticle.status === 'published' ? 'Published' : 'Draft',
            statusClass: latestArticle.status === 'published' ? 'status-success' : 'status-neutral',
        });
    }

    if (latestAchievement) {
        rows.push({
            icon: 'fas fa-medal',
            colorClass: 'circle-gold',
            label: latestAchievement.status === 'published' ? 'Project published' : 'Project hidden',
            title: `"${latestAchievement.title}"`,
            date: latestAchievement.updated_at || latestAchievement.created_at,
            user: currentUser ? (currentUser.name || currentUser.email) : 'Admin',
            statusLabel: latestAchievement.status === 'published' ? 'Published' : 'Hidden',
            statusClass: latestAchievement.status === 'published' ? 'status-success' : 'status-neutral',
        });
    }

    if (latestUser) {
        rows.push({
            icon: 'fas fa-user-plus',
            colorClass: 'circle-blue',
            label: 'User account added',
            title: 'New user in system',
            date: latestUser.updated_at || latestUser.created_at,
            user: currentUser ? (currentUser.name || currentUser.email) : 'Admin',
            statusLabel: latestUser.role === 'admin' ? 'Admin' : 'User',
            statusClass: 'status-neutral',
        });
    }

    if (latestReview) {
        rows.push({
            icon: 'fas fa-check',
            colorClass: 'circle-amber',
            label: latestReview.status === 'approved'
                ? 'Review approved'
                : latestReview.status === 'rejected'
                    ? 'Review rejected'
                    : 'New review submitted',
            title: latestReview.message
                ? `"${latestReview.message.substring(0, 40)}${latestReview.message.length > 40 ? '...' : ''}"`
                : 'Visitor review received',
            date: latestReview.updated_at || latestReview.created_at,
            user: currentUser ? (currentUser.name || currentUser.email) : 'Admin',
            statusLabel:
                latestReview.status === 'pending' ? 'Pending' :
                    latestReview.status === 'approved' ? 'Approved' :
                        'Rejected',
            statusClass:
                latestReview.status === 'pending' ? 'status-warning' :
                    latestReview.status === 'approved' ? 'status-success' :
                        'status-danger',
        });
    }

    if (rows.length === 0) {
        if (emptyMessage) {
            emptyMessage.textContent = 'No recent activity recorded yet.';
            emptyMessage.style.display = 'block';
        }
        return;
    }

    if (emptyMessage) {
        emptyMessage.style.display = 'none';
    }

    rows.sort((a, b) => new Date(b.date || 0) - new Date(a.date || 0));

    rows.slice(0, 8).forEach(
        row => {
            const li = document.createElement('li');
            li.className = 'activity-row';

            const targetHtml = row.title ? ` · <span>${escapeHTML(row.title)}</span>` : '';
            const formattedDate = row.date ? formatEnglishRelativeTime(row.date) : '';
            const fullDate = row.date ? new Date(row.date).toLocaleString() : '';
            
            let typeClass = 'type-default';
            if (row.colorClass === 'circle-green') typeClass = 'type-create';
            else if (row.colorClass === 'circle-amber') typeClass = 'type-update';
            else if (row.colorClass === 'circle-red') typeClass = 'type-delete';
            else if (row.colorClass === 'circle-blue') typeClass = 'type-update';

            li.innerHTML = `
                <div class="activity-icon-badge ${typeClass}">
                    <i class="${escapeHTML(row.icon)}"></i>
                </div>
                <div class="activity-content">
                    <div class="activity-action">${escapeHTML(row.label)}</div>
                    <div class="activity-meta" title="${escapeHTML(row.user || '')}${row.title ? ' · ' + escapeHTML(row.title) : ''}">
                        ${escapeHTML(row.user || '')}${targetHtml}
                    </div>
                </div>
                <div class="activity-time" title="${escapeHTML(fullDate)}">
                    ${escapeHTML(formattedDate)}
                </div>
            `;
            list.appendChild(li);
        }
    );

}


// ============================================================
// 5. HELPER UTILITIES
// ============================================================

function renderLastUpdated(posts, users, reviews) {

    const el = document.getElementById('dashboardLastUpdated');
    if (!el) {
        return;
    }

    const latest = latestByDate([
        ...(posts || []),
        ...(users || []),
        ...(reviews || []),
    ]);

    const date = latest
        ? (latest.updated_at || latest.created_at)
        : new Date();

    const now = new Date(date || Date.now());

    const options = {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
        timeZone: 'Asia/Riyadh'
    };

    el.textContent = now.toLocaleTimeString('en-US', options);
    
    // Add full date tooltip
    const fullOptions = {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
        timeZone: 'Asia/Riyadh'
    };
    el.title = now.toLocaleString('en-US', fullOptions);
    el.style.cursor = 'help';

}

function latestByDate(items) {

    if (!items || items.length === 0) {
        return null;
    }

    return items.reduce(
        (latest, item) => {

            if (!latest) {
                return item;
            }

            return new Date(item.updated_at || item.created_at) > new Date(latest.updated_at || latest.created_at)
                ? item
                : latest;

        },
        null
    );

}

function formatEnglishRelativeTime(value) {

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const seconds = Math.max(
        0,
        Math.floor((Date.now() - date.getTime()) / 1000)
    );

    const minutes = Math.floor(seconds / 60);
    const hours = Math.floor(minutes / 60);
    const days = Math.floor(hours / 24);

    if (seconds < 60) {
        return 'just now';
    }

    if (minutes < 60) {
        return minutes === 1 ? '1m ago' : `${minutes}m ago`;
    }

    if (hours < 24) {
        if (hours === 1) return '1h ago';
        return `${hours}h ago`;
    }

    if (days === 1) return '1d ago';
    if (days < 7) return `${days}d ago`;

    return date.toLocaleDateString(
        'en-US',
        {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        }
    );

}

function setText(id, value) {

    const el = document.getElementById(id);
    if (el) {
        el.textContent = value;
    }

}

function setSystemStatus(key, label, state) {

    const el = document.getElementById('systemStatus_' + key);
    if (!el) {
        return;
    }

    el.textContent = label;

    if (state === 'ok') {
        el.className = 'system-status-pill status-pill-success';
    } else if (state === 'pending') {
        el.className = 'system-status-pill status-pill-neutral';
    } else {
        el.className = 'system-status-pill status-pill-danger';
    }

}


// ============================================================
// 6. QUICK STATS & 7-DAY WEEKLY CHART [IMP-033]
// ============================================================

function renderQuickStatsAndChart(analytics) {

    if (!analytics || !analytics.summary) {
        return;
    }

    const summary = analytics.summary;
    const chartData = analytics.chart_7d || [];

    // Quick Stats Summary Row
    setText('quickStatsTotalVisitors', (summary.seven_day_visitors || 0).toLocaleString('en-US'));
    updateTrendBadge('quickStatsVisitorsTrend', summary.visitors_trend);

    setText('quickStatsTotalReads', (summary.seven_day_reads || 0).toLocaleString('en-US'));
    updateTrendBadge('quickStatsReadsTrend', summary.reads_trend);

    setText('quickStatsDailyAvg', (summary.avg_daily_visitors || 0).toLocaleString('en-US'));
    updateTrendBadge('quickStatsDailyTrend', summary.visitors_trend);

    // Dynamic Weekly Bar Chart SVG
    const svg = document.querySelector('.weekly-bar-chart');
    if (!svg || !Array.isArray(chartData) || chartData.length !== 7) {
        return;
    }

    // Calculate dynamic scaling based on 7-day visitors
    const visitorCounts = chartData.map(d => Number(d.visitors || 0));
    const maxVisitor = Math.max(...visitorCounts, 0);

    // Compute nice upper bound for grid lines
    let gridMax = 400;
    if (maxVisitor <= 10) {
        gridMax = 10;
    } else if (maxVisitor <= 50) {
        gridMax = 50;
    } else if (maxVisitor <= 100) {
        gridMax = 100;
    } else if (maxVisitor <= 200) {
        gridMax = 200;
    } else if (maxVisitor <= 400) {
        gridMax = 400;
    } else {
        gridMax = Math.ceil(maxVisitor / 100) * 100;
    }

    // Update the 5 grid line labels (y: 24, 59, 94, 129, 153)
    const gridTexts = svg.querySelectorAll('text[text-anchor="end"]');
    if (gridTexts.length >= 5) {
        gridTexts[0].textContent = String(gridMax);
        gridTexts[1].textContent = String(Math.round(gridMax * 0.75));
        gridTexts[2].textContent = String(Math.round(gridMax * 0.5));
        gridTexts[3].textContent = String(Math.round(gridMax * 0.25));
        gridTexts[4].textContent = '0';
    }

    // Update the 7 bars and day labels
    // In SVG, baseline is y=150. Max available height is 130 (y=20 to y=150).
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

            // Tooltip title
            let titleEl = rects[idx].querySelector('title');
            if (!titleEl) {
                titleEl = document.createElementNS('http://www.w3.org/2000/svg', 'title');
                rects[idx].appendChild(titleEl);
            }
            titleEl.textContent = `${day.day_name || day.date} (${day.date}): ${visitors} visitors · ${day.reads || 0} reads`;
        }

        if (dayTexts[idx] && day.day_name) {
            dayTexts[idx].textContent = day.day_name;
        }
    });

}

function updateTrendBadge(elementId, trend) {

    const el = document.getElementById(elementId);
    if (!el) {
        return;
    }

    if (!trend) {
        el.className = 'stat-trend trend-neutral';
        el.innerHTML = '<i class="fas fa-minus" aria-hidden="true"></i> 0%';
        return;
    }

    const state = trend.state;
    const label = trend.label || '—';
    const isQuickStat = el.classList.contains('quick-stat-trend');
    const baseClass = isQuickStat ? 'quick-stat-trend' : 'stat-trend';

    if (state === 'new') {
        el.className = `${baseClass} trend-new`;
        el.innerHTML = `<i class="fas fa-bolt" aria-hidden="true"></i> ${label}`;
    } else if (state === 'up') {
        el.className = `${baseClass} trend-up`;
        el.innerHTML = `<i class="fas fa-arrow-up" aria-hidden="true"></i> ${label}`;
    } else if (state === 'down') {
        el.className = `${baseClass} trend-down`;
        el.innerHTML = `<i class="fas fa-arrow-down" aria-hidden="true"></i> ${label}`;
    } else {
        el.className = `${baseClass} trend-neutral`;
        el.innerHTML = `<i class="fas fa-minus" aria-hidden="true"></i> ${label}`;
    }

}

