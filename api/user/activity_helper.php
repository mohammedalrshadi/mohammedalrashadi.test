<?php
// ============================================================
// USER PLATFORM — ACTIVITY LOGGER HELPER
// api/user/activity_helper.php
//
// Records authentic user actions into user_activities table.
//
// Permitted actions:
//   - bookmark_added
//   - bookmark_removed
//   - like_added
//   - like_removed
//   - content_viewed
//   - download_completed
//   - library_added
//   - profile_updated
//   - settings_updated
//   - account_created
// ============================================================

const ALLOWED_ACTIVITY_TYPES = [
    'bookmark_added',
    'bookmark_removed',
    'like_added',
    'like_removed',
    'content_viewed',
    'download_completed',
    'library_added',
    'profile_updated',
    'settings_updated',
    'account_created',
];

/**
 * Logs a real action performed by an authenticated user.
 */
function logUserActivity(
    PDO $pdo,
    int $userId,
    string $actionType,
    ?string $contentType = null,
    ?string $contentId = null,
    ?string $description = null
): bool {
    if ($userId <= 0) {
        return false;
    }

    $action = strtolower(trim($actionType));
    if (!in_array($action, ALLOWED_ACTIVITY_TYPES, true)) {
        return false;
    }

    $desc = trim((string)$description);
    if ($desc === '') {
        $desc = ucfirst(str_replace('_', ' ', $action));
    }

    // Limit description length to fit schema
    if (mb_strlen($desc, 'UTF-8') > 500) {
        $desc = mb_substr($desc, 0, 497, 'UTF-8') . '...';
    }

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO user_activities (user_id, activity_type, content_type, content_id, description, created_at)
             VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)"
        );
        $stmt->execute([
            $userId,
            $action,
            $contentType !== null ? strtolower(trim($contentType)) : null,
            $contentId !== null ? trim((string)$contentId) : null,
            $desc
        ]);
        return true;
    } catch (Exception $e) {
        error_log('[logUserActivity] Failed to log user activity: ' . $e->getMessage());
        return false;
    }
}

