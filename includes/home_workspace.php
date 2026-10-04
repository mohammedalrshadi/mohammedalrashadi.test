<?php
// ============================================================
// CONTEXT-AWARE HOMEPAGE WORKSPACE (Phase 2 Redesign)
// includes/home_workspace.php
// ============================================================

require_once __DIR__ . '/../api/user/home_feed.php';
require_once __DIR__ . '/../api/auth/guard.php';

$pdo = getDB();
$userId = currentUserId();
$isLoggedIn = ($userId > 0);

// Data fetching
$latestItems = homeLatestItems($pdo);

if (!$isLoggedIn) {
    // -------------------------------------------------------------
    // GUEST STATE
    // -------------------------------------------------------------
    global $primaryExperiment; // From index.php
    ?>
    <section class="grid grid-cols-1 lg:grid-cols-12 gap-6 pb-16" aria-label="Guest Dashboard">
        <!-- LATEST -->
        <div class="lg:col-span-8 dash-panel">
            <header class="dash-panel-header">
                <span class="dash-overline">LATEST</span>
            </header>
            <?php if (empty($latestItems)): ?>
                <div class="dash-empty">
                    <span class="dash-empty-title">No articles published yet</span>
                    <span class="dash-empty-text">New writing and projects appear here as soon as they're published.</span>
                    <a href="/projects.php" class="btn btn-primary text-sm mt-2">Browse projects</a>
                </div>
            <?php else: ?>
                <ul class="dash-list">
                    <?php 
                    $count = 0;
                    foreach ($latestItems as $item): 
                        if ($count >= 6) break;
                    ?>
                        <li>
                            <a href="<?= htmlspecialchars($item['url']) ?>" class="dash-row group">
                                <span class="dash-meta"><time datetime="<?= date('Y-m-d', $item['date']) ?>"><?= date('M j, Y', $item['date']) ?></time></span>
                                <span class="dash-type"><?= htmlspecialchars($item['type_badge']) ?></span>
                                <span class="dash-title"><?= htmlspecialchars($item['title']) ?></span>
                                <span class="dash-meta hidden md:inline-flex material-symbols-outlined text-[16px] group-hover:text-primary transition-colors opacity-0 group-hover:opacity-100" aria-hidden="true">arrow_forward</span>
                            </a>
                        </li>
                    <?php 
                        $count++;
                    endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <!-- STACKED PANELS (NOW & ACCOUNT) -->
        <div class="lg:col-span-4 flex flex-col gap-6">
            <!-- NOW -->
            <section class="dash-panel" aria-labelledby="now-heading">
                <header class="dash-panel-header">
                    <span id="now-heading" class="dash-overline">NOW</span>
                </header>
                <div class="flex flex-col gap-4">
                    <div class="flex flex-col gap-1">
                        <span class="font-mono text-[10px] text-text-muted uppercase">Currently Studying</span>
                        <span class="text-sm text-text-primary">MVCC and B-Tree Indexes</span>
                    </div>
                    <?php if (!empty($primaryExperiment)): ?>
                    <div class="flex flex-col gap-1">
                        <span class="font-mono text-[10px] text-text-muted uppercase">Active Benchmark</span>
                        <a href="/lab-detail.php?id=<?= urlencode($primaryExperiment['id']) ?>" class="text-sm text-primary hover:underline truncate">
                            <?= htmlspecialchars($primaryExperiment['title']) ?>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- ACCOUNT -->
            <section class="dash-panel bg-surface-container" aria-labelledby="account-heading">
                <header class="dash-panel-header hidden">
                    <span id="account-heading" class="dash-overline">ACCOUNT</span>
                </header>
                <p class="text-sm text-text-secondary mb-4">
                    Sign in to continue reading where you stopped, save articles and keep your history.
                </p>
                <div class="flex flex-wrap items-center gap-3">
                    <a href="/login.php" class="btn btn-primary text-sm px-4">Sign in</a>
                    <a href="/register.php" class="text-sm font-mono text-text-muted hover:text-primary transition-colors">Create account</a>
                </div>
            </section>
        </div>
    </section>
    <?php
} else {
    // -------------------------------------------------------------
    // LOGGED-IN STATE (New & Returning)
    // -------------------------------------------------------------
    
    // User Prefs
    $profStmt = $pdo->prepare("SELECT save_reading_history FROM user_profiles WHERE user_id = ?");
    $profStmt->execute([$userId]);
    $prof = $profStmt->fetch(PDO::FETCH_ASSOC);
    $saveHistory = !isset($prof['save_reading_history']) || (int)$prof['save_reading_history'] === 1;

    $continueItem = homeContinueReading($pdo, $userId, $saveHistory);
    $savedItems = homeSavedItems($pdo, $userId);
    $recentlyViewed = homeRecentlyViewed($pdo, $userId, $saveHistory, $continueItem ? $continueItem['url'] : null);
    
    $isNewUser = empty($continueItem) && empty($savedItems) && empty($recentlyViewed);
    
    $userProfQuery = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $userProfQuery->execute([$userId]);
    $userNameRow = $userProfQuery->fetch(PDO::FETCH_ASSOC);
    $userName = trim($userNameRow['name'] ?? 'User');
    
    $counts = homeUserCounts($pdo, $userId);

    // --- STATIC WATCH FACE GENERATION ---
    $watchFaceSvg = '<svg viewBox="0 0 200 200" aria-hidden="true" width="100%" height="100%">';
    $watchFaceSvg .= '<defs>';
    $watchFaceSvg .= '  <radialGradient id="bg-grad" cx="50%" cy="50%" r="50%">';
    $watchFaceSvg .= '    <stop offset="20%" stop-color="#0a1a14"/>';
    $watchFaceSvg .= '    <stop offset="90%" stop-color="#050e09"/>';
    $watchFaceSvg .= '    <stop offset="100%" stop-color="#030805"/>';
    $watchFaceSvg .= '  </radialGradient>';
    $watchFaceSvg .= '  <filter id="glow" x="-20%" y="-20%" width="140%" height="140%">';
    $watchFaceSvg .= '    <feGaussianBlur stdDeviation="1.5" result="blur" />';
    $watchFaceSvg .= '    <feComposite in="SourceGraphic" in2="blur" operator="over" />';
    $watchFaceSvg .= '  </filter>';
    $watchFaceSvg .= '</defs>';
    
    // Background dial
    $watchFaceSvg .= '<circle cx="100" cy="100" r="98" fill="#030805" stroke="#16382a" stroke-width="2" />';
    $watchFaceSvg .= '<circle cx="100" cy="100" r="95" fill="url(#bg-grad)" />';
    
    // Concentric tech circles
    $watchFaceSvg .= '<g stroke="#20C978" fill="none">';
    $watchFaceSvg .= '  <circle cx="100" cy="100" r="85" stroke-width="0.3" opacity="0.4" stroke-dasharray="1 3" />';
    $watchFaceSvg .= '  <circle cx="100" cy="100" r="75" stroke-width="0.5" opacity="0.2" />';
    $watchFaceSvg .= '  <circle cx="100" cy="100" r="65" stroke-width="0.3" opacity="0.3" stroke-dasharray="4 2" />';
    $watchFaceSvg .= '  <circle cx="100" cy="100" r="50" stroke-width="0.2" opacity="0.5" />';
    $watchFaceSvg .= '  <circle cx="100" cy="100" r="35" stroke-width="0.4" opacity="0.2" stroke-dasharray="2 4" />';
    $watchFaceSvg .= '  <circle cx="100" cy="100" r="20" stroke-width="0.2" opacity="0.4" />';
    $watchFaceSvg .= '</g>';

    // Hour markers
    $watchFaceSvg .= '<g class="watch-static-marks">';
    for ($i = 0; $i < 12; $i++) {
        $angle = $i * 30;
        if ($i % 3 === 0) {
            $watchFaceSvg .= '<rect x="98.5" y="10" width="3" height="15" fill="#e2e8f0" rx="1" transform="rotate('.$angle.' 100 100)" />';
            $watchFaceSvg .= '<circle cx="100" cy="30" r="1.5" fill="#34D399" filter="url(#glow)" transform="rotate('.$angle.' 100 100)" />';
        } else {
            $watchFaceSvg .= '<circle cx="100" cy="15" r="1.5" fill="#94a3b8" transform="rotate('.$angle.' 100 100)" />';
            $watchFaceSvg .= '<line x1="100" y1="20" x2="100" y2="25" stroke="#475569" stroke-width="1" transform="rotate('.$angle.' 100 100)" />';
        }
    }
    $watchFaceSvg .= '</g>';
    $watchFaceSvg .= '</svg>';

    // --- WATCH HANDS GENERATION ---
    $watchHandsSvg = '<svg viewBox="0 0 200 200" aria-hidden="true" width="100%" height="100%">';
    $watchHandsSvg .= '<text class="watch-date-full" x="100" y="145" fill="#D1FAE5" font-family="system-ui, -apple-system, sans-serif" font-size="8" font-weight="500" letter-spacing="1" text-anchor="middle" opacity="0.6">'.date('D M j').'</text>';
    $watchHandsSvg .= '<g class="watch-hour" transform="rotate(305 100 100)">';
    $watchHandsSvg .= '  <path d="M100,108 L95,100 L100,50 Z" fill="#94a3b8" />';
    $watchHandsSvg .= '  <path d="M100,108 L105,100 L100,50 Z" fill="#f8fafc" />';
    $watchHandsSvg .= '</g>';
    $watchHandsSvg .= '<g class="watch-min" transform="rotate(60 100 100)">';
    $watchHandsSvg .= '  <path d="M100,110 L96,100 L100,20 Z" fill="#94a3b8" />';
    $watchHandsSvg .= '  <path d="M100,110 L104,100 L100,20 Z" fill="#f8fafc" />';
    $watchHandsSvg .= '</g>';
    $watchHandsSvg .= '<g class="watch-sec" transform="rotate(0 100 100)">';
    $watchHandsSvg .= '  <line x1="100" y1="120" x2="100" y2="15" stroke="#fbbf24" stroke-width="1.5" opacity="0.8" />';
    $watchHandsSvg .= '  <line x1="100" y1="120" x2="100" y2="15" stroke="#fff" stroke-width="0.5" />';
    $watchHandsSvg .= '  <circle cx="100" cy="100" r="3" fill="#fbbf24" />';
    $watchHandsSvg .= '  <circle cx="100" cy="100" r="1.5" fill="#fff" />';
    $watchHandsSvg .= '</g>';
    $watchHandsSvg .= '</svg>';
    ?>
    <div class="workspace-wrapper w-full">
    <section class="grid grid-cols-1 md:grid-cols-12 gap-6 pt-6 pb-4 border-b border-border-subtle mb-6" aria-label="Workspace Header">
        <!-- LEFT COLUMN -->
        <div class="flex flex-col justify-center md:col-span-8 lg:col-span-9 py-2">
            <!-- Mobile Top Row -->
            <div class="flex justify-between items-center w-full md:hidden mb-4">
                <span class="editorial-overline">WORKSPACE</span>
                <!-- Mobile Watch Placeholder -->
                <div class="relative flex items-center justify-center watch-container" style="width: 72px; height: 72px; aspect-ratio: 1/1;" data-size="72" id="workspace-watch-mobile" aria-hidden="true">
                    <div class="absolute inset-0 z-0"><?= $watchFaceSvg ?></div>
                    <canvas class="absolute z-10 pointer-events-none clock-of-life-canvas"></canvas>
                    <div class="absolute inset-0 z-20 pointer-events-none"><?= $watchHandsSvg ?></div>
                </div>
            </div>
            
            <!-- Desktop Overline -->
            <div class="hidden md:block mb-2">
                <span class="editorial-overline">WORKSPACE</span>
            </div>
            
            <h1 class="font-display text-on-surface tracking-tight mb-2" style="font-size: clamp(2rem, 4vw, 3rem); font-weight: 700; line-height: 1.1;">
                <span id="greeting-prefix"><?= $isNewUser ? 'Welcome' : 'Welcome back' ?></span>, <?= htmlspecialchars($userName) ?>
            </h1>
            <span class="font-mono text-xs text-text-muted mb-5">Currently studying MVCC and B-Tree Indexes</span>
            
            <div class="w-full md:w-auto">
                <nav aria-label="Your library shortcuts" class="dash-quick-bar">
                    <a href="/dashboard/library.php" class="dash-quick-btn" aria-label="Library<?= $counts['library'] > 0 ? ', ' . $counts['library'] . ' items' : '' ?>">
                        <span class="material-symbols-outlined dash-quick-icon" aria-hidden="true">folder_special</span>
                        <span class="dash-quick-label">Library</span>
                        <?php if ($counts['library'] > 0): ?>
                            <span class="dash-quick-count"><?= $counts['library'] ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="/dashboard/bookmarks.php" class="dash-quick-btn" aria-label="Saved<?= $counts['saved'] > 0 ? ', ' . $counts['saved'] . ' items' : '' ?>">
                        <span class="material-symbols-outlined dash-quick-icon" aria-hidden="true">bookmark</span>
                        <span class="dash-quick-label">Saved</span>
                        <?php if ($counts['saved'] > 0): ?>
                            <span class="dash-quick-count"><?= $counts['saved'] ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="/dashboard/history.php" class="dash-quick-btn" aria-label="History<?= ($saveHistory && $counts['history'] > 0) ? ', ' . $counts['history'] . ' items' : '' ?>">
                        <span class="material-symbols-outlined dash-quick-icon" aria-hidden="true">history</span>
                        <span class="dash-quick-label">History</span>
                        <?php if ($saveHistory && $counts['history'] > 0): ?>
                            <span class="dash-quick-count"><?= $counts['history'] ?></span>
                        <?php endif; ?>
                    </a>
                </nav>
            </div>
        </div>
        
        <!-- RIGHT COLUMN -->
        <div class="hidden md:flex flex-col items-end justify-start gap-3 md:col-span-4 lg:col-span-3">
            <div class="relative flex items-center justify-center watch-container" style="width: 128px; height: 128px; aspect-ratio: 1/1;" data-size="128" id="workspace-watch-desktop" aria-hidden="true">
                <div class="absolute inset-0 z-0"><?= $watchFaceSvg ?></div>
                <canvas class="absolute z-10 pointer-events-none clock-of-life-canvas"></canvas>
                <div class="absolute inset-0 z-20 pointer-events-none"><?= $watchHandsSvg ?></div>
            </div>
            <time class="dash-clock-caption" id="workspace-caption-desktop"></time>
        </div>
        <!-- Mobile Caption -->
        <time class="sr-only" id="workspace-caption-mobile"></time>
    </section>

    <?php if ($isNewUser): ?>
        <!-- NEW USER STATE -->
        <?php
        $featuredProj = null;
        $latestWriting = null;
        $currentLab = null;
        foreach ($latestItems as $item) {
            if (!$featuredProj && $item['type_badge'] === 'Project') $featuredProj = $item;
            if (!$latestWriting && $item['type_badge'] === 'Article') $latestWriting = $item;
            if (!$currentLab && $item['type_badge'] === 'Studio Lab') $currentLab = $item;
        }
        if (!$featuredProj) $featuredProj = $latestItems[0] ?? null;
        if (!$latestWriting) $latestWriting = $latestItems[1] ?? null;
        if (!$currentLab) $currentLab = $latestItems[2] ?? null;
        
        $startItems = array_filter([$featuredProj, $latestWriting, $currentLab]);
        // Deduplicate
        $uniqueUrls = [];
        foreach ($startItems as $k => $item) {
            if (in_array($item['url'], $uniqueUrls)) {
                unset($startItems[$k]);
            } else {
                $uniqueUrls[] = $item['url'];
            }
        }
        ?>
        <div class="flex flex-col gap-6 pb-16" aria-label="New User Dashboard">
            <!-- START HERE ROW -->
            <?php if (!empty($startItems)): ?>
                <section class="grid grid-cols-1 lg:grid-cols-12 gap-6" aria-label="Start Here Modules">
                <?php foreach ($startItems as $item): ?>
                    <section class="lg:col-span-4 dash-panel flex flex-col justify-between group cursor-pointer hover:border-primary/50 transition-colors" onclick="window.location.href='<?= htmlspecialchars($item['url']) ?>'" aria-labelledby="start-<?= $item['id'] ?>">
                        <div class="flex flex-col gap-2">
                            <span id="start-<?= $item['id'] ?>" class="dash-overline"><?= htmlspecialchars($item['type_badge']) ?></span>
                            <h3 class="text-base font-semibold text-text-primary group-hover:text-primary transition-colors leading-snug line-clamp-2"><?= htmlspecialchars($item['title']) ?></h3>
                        </div>
                        <div class="mt-6 flex items-center gap-1 font-mono text-xs text-primary">
                            <span>Open</span>
                            <span class="material-symbols-outlined text-[14px] group-hover:translate-x-1 transition-transform" aria-hidden="true">arrow_forward</span>
                        </div>
                    </section>
                <?php endforeach; ?>
                </section>
            <?php endif; ?>
            
            <!-- ROW 2: LATEST & LIBRARY -->
            <section class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start" aria-label="Latest and Library">
            <section class="lg:col-span-7 dash-panel" aria-labelledby="latest-new-heading">
                <header class="dash-panel-header">
                    <span id="latest-new-heading" class="dash-overline">LATEST</span>
                </header>
                <?php if (empty($latestItems)): ?>
                    <div class="dash-empty">
                        <span class="dash-empty-title">No articles published yet</span>
                        <span class="dash-empty-text">New writing and projects appear here as soon as they're published.</span>
                        <a href="/projects.php" class="btn btn-primary text-sm mt-2">Browse projects</a>
                    </div>
                <?php else: ?>
                    <ul class="dash-list">
                        <?php 
                        $count = 0;
                        foreach ($latestItems as $item): 
                            if ($count >= 6) break;
                        ?>
                            <li>
                                <a href="<?= htmlspecialchars($item['url']) ?>" class="dash-row group">
                                    <span class="dash-meta"><time datetime="<?= date('Y-m-d', $item['date']) ?>"><?= date('M j, Y', $item['date']) ?></time></span>
                                    <span class="dash-type"><?= htmlspecialchars($item['type_badge']) ?></span>
                                    <span class="dash-title"><?= htmlspecialchars($item['title']) ?></span>
                                    <span class="dash-meta hidden md:inline-flex material-symbols-outlined text-[16px] group-hover:text-primary transition-colors opacity-0 group-hover:opacity-100" aria-hidden="true">arrow_forward</span>
                                </a>
                            </li>
                        <?php 
                            $count++;
                        endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <section class="lg:col-span-5 dash-panel" aria-labelledby="library-heading">
                <header class="dash-panel-header">
                    <span id="library-heading" class="dash-overline">YOUR LIBRARY</span>
                </header>
                <div class="dash-empty">
                    <span class="dash-empty-title">Nothing saved yet</span>
                    <span class="dash-empty-text">Use the bookmark button on any article to keep it here.</span>
                    <a href="/articles.php" class="btn btn-secondary text-sm mt-2">Browse writing</a>
                </div>
            </section>
        </div>

    <?php else: ?>
        <!-- RETURNING USER STATE -->
        <?php
        $row1ColContinue = $continueItem ? 'lg:col-span-8' : 'hidden';
        $row1ColSaved = $continueItem ? 'lg:col-span-4' : 'lg:col-span-6';
        
        $row2ColRecent = $continueItem ? 'lg:col-span-5' : 'lg:col-span-6';
        $row2ColLatest = $continueItem ? 'lg:col-span-7' : 'lg:col-span-12';
        ?>
        
        <div class="flex flex-col gap-6 pb-16" aria-label="Returning User Dashboard">
            <!-- ROW 1 -->
            <section class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start" aria-label="Continuing and Saved">
            <?php if ($continueItem): ?>
            <section class="<?= $row1ColContinue ?> dash-panel flex flex-col justify-between bg-surface-container/50 border-primary/20" aria-labelledby="continue-heading">
                <div class="flex flex-col gap-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <span id="continue-heading" class="dash-overline text-primary">CONTINUE READING</span>
                            <span class="dash-meta" aria-hidden="true">&middot;</span>
                            <span class="dash-type text-primary/80"><?= htmlspecialchars($continueItem['type_badge']) ?></span>
                        </div>
                        <span class="dash-meta">Last read <?= htmlspecialchars($continueItem['time_ago'] ?? 'recently') ?></span>
                    </div>
                    
                    <h3 class="font-display text-[clamp(1.25rem,2vw,1.75rem)] text-on-surface font-semibold leading-tight line-clamp-2">
                        <?= htmlspecialchars($continueItem['title']) ?>
                    </h3>
                </div>
                
                <div class="mt-8 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex-1 flex items-center gap-3">
                        <div class="flex-1 max-w-[200px] h-[2px] bg-border rounded-full overflow-hidden" role="progressbar" aria-valuenow="<?= $continueItem['progress'] ?>" aria-valuemin="0" aria-valuemax="100">
                            <div class="h-full bg-primary" style="width: <?= $continueItem['progress'] ?>%"></div>
                        </div>
                        <span class="dash-meta text-primary font-medium tabular-nums"><?= $continueItem['progress'] ?>% read</span>
                    </div>
                    
                    <a href="<?= htmlspecialchars($continueItem['url']) ?>" class="btn btn-primary px-5 py-2 text-sm shrink-0">
                        Continue reading
                    </a>
                </div>
            </section>
            <?php endif; ?>

            <section class="<?= $row1ColSaved ?> dash-panel" aria-labelledby="saved-heading">
                <header class="dash-panel-header">
                    <span id="saved-heading" class="dash-overline">SAVED</span>
                    <a href="/dashboard/bookmarks.php" class="dash-header-link">All saved &rarr;</a>
                </header>
                <?php if (empty($savedItems)): ?>
                    <div class="dash-empty">
                        <span class="dash-empty-title">No bookmarks</span>
                        <span class="dash-empty-text">Save articles to read later.</span>
                    </div>
                <?php else: ?>
                    <ul class="dash-list">
                        <?php 
                        $count = 0;
                        foreach ($savedItems as $item): 
                            if ($count >= 4) break;
                        ?>
                            <li>
                                <a href="<?= htmlspecialchars($item['url']) ?>" class="dash-row dash-row-saved group">
                                    <div class="flex flex-col gap-1">
                                        <span class="dash-title" style="white-space: normal; line-clamp: 1; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical;"><?= htmlspecialchars($item['title']) ?></span>
                                        <div class="flex items-center gap-2">
                                            <span class="dash-type" style="font-size: 10px;"><?= htmlspecialchars($item['type_badge']) ?></span>
                                            <span class="text-border" aria-hidden="true">&middot;</span>
                                            <span class="dash-meta" style="font-size: 10px;"><?= htmlspecialchars($item['time_ago']) ?></span>
                                        </div>
                                    </div>
                                </a>
                            </li>
                        <?php 
                            $count++;
                        endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
            </section>

            <!-- ROW 2 -->
            <section class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start" aria-label="Recent and Latest">
            <section class="<?= $row2ColRecent ?> dash-panel" aria-labelledby="recent-heading">
                <header class="dash-panel-header">
                    <span id="recent-heading" class="dash-overline">RECENTLY VIEWED</span>
                    <a href="/dashboard/history.php" class="dash-header-link">History &rarr;</a>
                </header>
                <?php if (empty($recentlyViewed)): ?>
                    <div class="dash-empty">
                        <span class="dash-empty-title">No history</span>
                        <span class="dash-empty-text">Your reading history will appear here.</span>
                    </div>
                <?php else: ?>
                    <ul class="dash-list">
                        <?php 
                        $count = 0;
                        foreach ($recentlyViewed as $item): 
                            if ($count >= 5) break;
                        ?>
                            <li>
                                <a href="<?= htmlspecialchars($item['url']) ?>" class="dash-row dash-row-recent group">
                                    <span class="dash-title" style="white-space: normal; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical;"><?= htmlspecialchars($item['title']) ?></span>
                                    <span class="dash-meta"><?= htmlspecialchars($item['time_ago']) ?></span>
                                </a>
                            </li>
                        <?php 
                            $count++;
                        endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <section class="<?= $row2ColLatest ?> dash-panel" aria-labelledby="latest-ret-heading">
                <header class="dash-panel-header">
                    <span id="latest-ret-heading" class="dash-overline">LATEST</span>
                </header>
                <?php if (empty($latestItems)): ?>
                    <div class="dash-empty">
                        <span class="dash-empty-title">No articles published yet</span>
                        <span class="dash-empty-text">New writing and projects appear here as soon as they're published.</span>
                        <a href="/projects.php" class="btn btn-primary text-sm mt-2">Browse projects</a>
                    </div>
                <?php else: ?>
                    <ul class="dash-list">
                        <?php 
                        $count = 0;
                        foreach ($latestItems as $item): 
                            if ($count >= 6) break;
                        ?>
                            <li>
                                <a href="<?= htmlspecialchars($item['url']) ?>" class="dash-row group <?= $continueItem ? 'dash-row-compact' : '' ?>">
                                    <?php if (!$continueItem): ?>
                                    <span class="dash-meta hidden md:inline-flex"><time datetime="<?= date('Y-m-d', $item['date']) ?>"><?= date('M j, Y', $item['date']) ?></time></span>
                                    <?php endif; ?>
                                    <span class="dash-type"><?= htmlspecialchars($item['type_badge']) ?></span>
                                    <span class="dash-title"><?= htmlspecialchars($item['title']) ?></span>
                                    <span class="dash-meta hidden md:inline-flex material-symbols-outlined text-[16px] group-hover:text-primary transition-colors opacity-0 group-hover:opacity-100" aria-hidden="true">arrow_forward</span>
                                </a>
                            </li>
                        <?php 
                            $count++;
                        endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            </section>
        </div>
    <?php endif; ?>
    </div>
<?php } ?>

<script type="module" src="/js/clock_of_life.js"></script>
