<?php
// ============================================================
// ACHIEVEMENTS — schema probe (api/helpers/achievements_schema.php)
//
// achievementsHasDeletedAt(): true once migration "achievements_soft_delete"
// has been applied (column achievements.deleted_at exists). Cached per request.
//
// Deploy order is MIGRATIONS FIRST, CODE SECOND. This probe only exists so that
// public read paths keep working (instead of a 500) if the code is ever live
// before the migration; admin write paths refuse with HTTP 409 instead.
// ============================================================

function achievementsHasDeletedAt(PDO $pdo): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    try {
        $pdo->query('SELECT deleted_at FROM achievements LIMIT 1');
        $cache = true;
    } catch (PDOException $e) {
        $cache = false;
    }
    return $cache;
}

/** Sends the standard "migration not applied" response for admin write endpoints. */
function achievementsRequireSoftDelete(PDO $pdo): void
{
    if (!achievementsHasDeletedAt($pdo)) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Database migration "achievements_soft_delete" has not been applied yet. Run pending migrations first.',
        ]);
        exit;
    }
}
