<?php
// ============================================================
// CONTEXT-AWARE HOMEPAGE WORKSPACE (Phase 2 Redesign)
// includes/home_workspace.php
// ============================================================

require_once __DIR__ . '/../api/user/home_feed.php';
require_once __DIR__ . '/../api/auth/guard.php';

// Add page-home class instantly to prevent FOUC
echo '<script>document.body.classList.add("page-home");</script>';



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
            <?php
            $hwFocus = trim((string)(($profile ?? getSiteProfile())['current_focus'] ?? ''));
            if ($hwFocus !== '' || !empty($primaryExperiment)):
            ?>
            <section class="dash-panel" aria-labelledby="now-heading">
                <header class="dash-panel-header">
                    <span id="now-heading" class="dash-overline">NOW</span>
                </header>
                <div class="flex flex-col gap-4">
                    <?php if ($hwFocus !== ''): ?>
                    <div class="flex flex-col gap-1">
                        <span class="font-mono text-[10px] text-text-muted uppercase">Currently Studying</span>
                        <span class="text-sm text-text-primary"><?= htmlspecialchars($hwFocus) ?></span>
                    </div>
                    <?php endif; ?>
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
            <?php endif; ?>

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

    // --- WATCH CLOCK GENERATION ---
    // Rendered via HTML markup and js/world-clock.js
    ?>
    <div class="workspace-wrapper w-full">

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
                    <span id="library-heading" class="dash-overline">EXPLORE THE ARCHIVE</span>
                </header>
                <div class="dash-empty">
                    <span class="dash-empty-title">Featured resources and saved collections will appear here as the site grows.</span>
                    <span class="dash-empty-text">Bookmark content while logged in, or explore curated archives.</span>
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
                    <span id="saved-heading" class="dash-overline">SAVED ARCHIVE</span>
                    <a href="/dashboard/bookmarks.php" class="dash-header-link">All saved &rarr;</a>
                </header>
                <?php if (empty($savedItems)): ?>
                    <div class="dash-empty">
                        <span class="dash-empty-title">Featured resources and saved collections will appear here as the site grows.</span>
                        <span class="dash-empty-text">Bookmark content to add it to your archive.</span>
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




