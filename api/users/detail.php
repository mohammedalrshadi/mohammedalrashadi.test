<?php
declare(strict_types=1);

// ============================================================
// USER DEEP DETAIL & ENGAGEMENT API
// GET /api/users/detail.php?id={id}
// Requires: authenticated admin session
// Surfaces profile, activity history, library claims, downloads,
// bookmarks, likes, and reading metrics from real database tables.
// ============================================================

error_reporting(0);
ini_set('log_errors', '1');

require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';

header('Content-Type: application/json');

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($userId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid user ID.']);
    exit;
}

try {
    $pdo = getDB();

    // 1. Core User Record
    $stmtUser = $pdo->prepare(
        'SELECT id, name, email, role, created_at, updated_at
         FROM users
         WHERE id = ?'
    );
    $stmtUser->execute([$userId]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit;
    }

    // 2. User Profile Record
    $stmtProfile = $pdo->prepare(
        'SELECT bio, avatar_url, theme_preference, language_preference, 
                email_notifications, activity_visibility, updated_at
         FROM user_profiles
         WHERE user_id = ?'
    );
    $stmtProfile->execute([$userId]);
    $profile = $stmtProfile->fetch(PDO::FETCH_ASSOC) ?: [
        'bio'                  => '',
        'avatar_url'           => null,
        'theme_preference'     => 'dark',
        'language_preference'  => 'en',
        'email_notifications'  => 1,
        'activity_visibility'  => 'private',
        'updated_at'           => null,
    ];

    // 3. Authentic Aggregate Engagement Counts
    $stats = [
        'bookmarks'  => (int)$pdo->query("SELECT COUNT(*) FROM bookmarks WHERE user_id = {$userId}")->fetchColumn(),
        'likes'      => (int)$pdo->query("SELECT COUNT(*) FROM likes WHERE user_id = {$userId}")->fetchColumn(),
        'downloads'  => (int)$pdo->query("SELECT COUNT(*) FROM user_downloads WHERE user_id = {$userId}")->fetchColumn(),
        'library'    => (int)$pdo->query("SELECT COUNT(*) FROM user_library WHERE user_id = {$userId}")->fetchColumn(),
        'reading'    => (int)$pdo->query("SELECT COUNT(*) FROM reading_history WHERE user_id = {$userId}")->fetchColumn(),
        'activities' => (int)$pdo->query("SELECT COUNT(*) FROM user_activities WHERE user_id = {$userId}")->fetchColumn(),
    ];

    // 4. Recent Activities (up to 10)
    $stmtAct = $pdo->prepare(
        'SELECT id, activity_type, content_type, content_id, description, created_at
         FROM user_activities
         WHERE user_id = ?
         ORDER BY created_at DESC
         LIMIT 10'
    );
    $stmtAct->execute([$userId]);
    $recentActivities = $stmtAct->fetchAll(PDO::FETCH_ASSOC);

    // 5. Library Products (up to 10)
    $stmtLib = $pdo->prepare(
        'SELECT ul.id, ul.product_id, ul.access_type, ul.created_at,
                p.title AS product_title, p.product_type, p.thumbnail
         FROM user_library ul
         LEFT JOIN products p ON ul.product_id = p.id
         WHERE ul.user_id = ?
         ORDER BY ul.created_at DESC
         LIMIT 10'
    );
    $stmtLib->execute([$userId]);
    $library = $stmtLib->fetchAll(PDO::FETCH_ASSOC);

    // 6. Recent Downloads (up to 10)
    $stmtDl = $pdo->prepare(
        'SELECT id, product_id, product_title, downloaded_at
         FROM user_downloads
         WHERE user_id = ?
         ORDER BY downloaded_at DESC
         LIMIT 10'
    );
    $stmtDl->execute([$userId]);
    $downloads = $stmtDl->fetchAll(PDO::FETCH_ASSOC);

    // 7. Recent Bookmarks (up to 10)
    $stmtBm = $pdo->prepare(
        'SELECT id, content_type, content_id, created_at
         FROM bookmarks
         WHERE user_id = ?
         ORDER BY created_at DESC
         LIMIT 10'
    );
    $stmtBm->execute([$userId]);
    $bookmarks = $stmtBm->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data'    => [
            'user'              => $user,
            'profile'           => $profile,
            'stats'             => $stats,
            'recent_activities' => $recentActivities,
            'library'           => $library,
            'downloads'         => $downloads,
            'bookmarks'         => $bookmarks,
        ],
    ], JSON_THROW_ON_ERROR);

} catch (Throwable $e) {
    error_log('[users/detail] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load user details.']);
}

