<?php
// ============================================================
// SETTINGS — UPDATE SETTINGS [IMP-034]
// POST /api/settings/update.php
// Requires: authenticated admin session + valid CSRF token
// Body (JSON):
//   Format 1 (grouped): { "group": "profile", "settings": { "name": "...", "role": "..." } }
//   Format 2 (flat map): { "settings": { "profile.name": "...", "profile.role": "..." } }
//   Format 3 (records):  { "settings": [ { "key_group": "profile", "setting_key": "name", "value": "..." } ] }
// ============================================================

error_reporting(0);
ini_set('log_errors', '1');

require_once dirname(__DIR__) . '/auth/guard.php';
require_once dirname(__DIR__) . '/db.php';
require_once dirname(dirname(__DIR__)) . '/includes/settings.php';

header('Content-Type: application/json; charset=utf-8');

// Enforce authenticated admin session and valid CSRF token
requireAuth();
requireCSRF();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$allowedGroups = ['profile', 'website', 'branding', 'seo', 'showcase'];

$rawBody = file_get_contents('php://input');
$input   = json_decode($rawBody, true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON payload.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Normalize items into: array<array{group: string, key: string, value: ?string, is_public: int}>
$itemsToUpdate = [];

// Format 1: { "group": "profile", "settings": { "key": "val" } }
if (isset($input['group']) && is_string($input['group']) && isset($input['settings']) && is_array($input['settings'])) {
    $group = strtolower(trim($input['group']));
    if (!in_array($group, $allowedGroups, true)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => "Invalid settings group: {$group}",
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    foreach ($input['settings'] as $k => $v) {
        $key = trim((string)$k);
        $val = is_null($v) ? null : (string)$v;
        $itemsToUpdate[] = [
            'group'     => $group,
            'key'       => $key,
            'value'     => $val,
            'is_public' => ($group === 'profile' && $key === 'public_email') ? 0 : 1,
        ];
    }
}
// Format 2: { "settings": { "profile.name": "val", ... } }
elseif (isset($input['settings']) && is_array($input['settings']) && !isset($input['settings'][0])) {
    foreach ($input['settings'] as $compositeKey => $v) {
        if (!str_contains($compositeKey, '.')) {
            continue;
        }
        [$group, $key] = explode('.', (string)$compositeKey, 2);
        $group = strtolower(trim($group));
        $key   = trim($key);
        if (!in_array($group, $allowedGroups, true)) {
            continue;
        }

        $val = is_null($v) ? null : (string)$v;
        $itemsToUpdate[] = [
            'group'     => $group,
            'key'       => $key,
            'value'     => $val,
            'is_public' => ($group === 'profile' && $key === 'public_email') ? 0 : 1,
        ];
    }
}
// Format 3: { "settings": [ { "key_group": "...", "setting_key": "...", "value": "..." } ] }
elseif (isset($input['settings']) && is_array($input['settings']) && isset($input['settings'][0])) {
    foreach ($input['settings'] as $entry) {
        if (!is_array($entry)) continue;
        $group = strtolower(trim((string)($entry['key_group'] ?? $entry['group'] ?? '')));
        $key   = trim((string)($entry['setting_key'] ?? $entry['key'] ?? ''));
        if (!in_array($group, $allowedGroups, true) || $key === '') {
            continue;
        }

        $val = isset($entry['value']) ? (is_null($entry['value']) ? null : (string)$entry['value']) : null;
        $isPublic = isset($entry['is_public']) ? (int)(bool)$entry['is_public'] : 1;
        $itemsToUpdate[] = [
            'group'     => $group,
            'key'       => $key,
            'value'     => $val,
            'is_public' => $isPublic,
        ];
    }
}

if (empty($itemsToUpdate)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'No valid settings entries provided to update.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Validation & Sanitization per item
$multilineKeys = ['bio_short', 'bio_full', 'platform_purpose', 'default_description'];
$urlKeys       = ['avatar_url', 'monogram_url', 'favicon_url', 'og_image_url', 'canonical_url'];

foreach ($itemsToUpdate as &$item) {
    // Key format validation: alphanumeric, underscore, hyphen
    if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $item['key'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => "Invalid setting key format: {$item['key']}",
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($item['value'] === null) {
        continue;
    }

    $rawVal = (string)$item['value'];

    // 1. Email validation (e.g. profile.public_email)
    if ($item['group'] === 'profile' && $item['key'] === 'public_email') {
        $email = trim(strip_tags($rawVal));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'The provided public email address is invalid.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $item['value'] = $email;
        continue;
    }

    // 2. URL validation for asset fields
    if (in_array($item['key'], $urlKeys, true)) {
        $url = trim(strip_tags($rawVal));
        if ($url !== '') {
            // Must be absolute path (/assets/...) or full HTTP(S) URL
            $isPath = str_starts_with($url, '/');
            $isHttp = str_starts_with($url, 'http://') || str_starts_with($url, 'https://');
            if (!$isPath && !$isHttp) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => "Invalid URL path for {$item['key']}. Must start with '/' or 'https://'.",
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
        $item['value'] = $url;
        continue;
    }

    // 3. Enum validation
    if ($item['key'] === 'site_status') {
        $validStatuses = ['operational', 'maintenance', 'degraded'];
        $val = strtolower(trim(strip_tags($rawVal)));
        $item['value'] = in_array($val, $validStatuses, true) ? $val : 'operational';
        continue;
    }

    if ($item['key'] === 'showcase_mode') {
        $validModes = ['manual', 'recent', 'featured'];
        $val = strtolower(trim(strip_tags($rawVal)));
        $item['value'] = in_array($val, $validModes, true) ? $val : 'manual';
        continue;
    }

    // 4. Showcase item_ids (JSON-encoded array of integers) and pinned_ids
    if ($item['key'] === 'item_ids') {
        $decoded = is_string($rawVal) ? json_decode($rawVal, true) : (is_array($rawVal) ? $rawVal : null);
        if (is_array($decoded)) {
            $sanitizedIds = array_values(array_filter(array_map('intval', $decoded), fn($id) => $id > 0));
            $item['value'] = json_encode(array_slice($sanitizedIds, 0, 8));
        } else {
            $item['value'] = '[]';
        }
        continue;
    }

    if ($item['key'] === 'pinned_ids') {
        $cleaned = preg_replace('/[^0-9,]/', '', $rawVal);
        $ids = array_filter(array_map('trim', explode(',', $cleaned)), fn($id) => $id !== '' && ctype_digit($id));
        $item['value'] = implode(',', array_slice($ids, 0, 8));
        continue;
    }

    // 5. Multiline text sanitization (bio, description, platform purpose)
    if (in_array($item['key'], $multilineKeys, true)) {
        // Strip all tags, remove dangerous control characters while preserving newlines (\r, \n) and tabs
        $cleanText = strip_tags($rawVal);
        $cleanText = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $cleanText);
        // Length cap: 10,000 characters
        if (mb_strlen($cleanText, 'UTF-8') > 10000) {
            $cleanText = mb_substr($cleanText, 0, 10000, 'UTF-8');
        }
        $item['value'] = trim($cleanText);
        continue;
    }

    // 6. Standard single-line text sanitization (name, role, motto, location, etc.)
    // Strip tags, flatten newlines/tabs, remove non-printable chars, cap length at 500 chars
    $cleanLine = strip_tags($rawVal);
    $cleanLine = preg_replace('/[\r\n\t]+/', ' ', $cleanLine);
    $cleanLine = preg_replace('/[\x00-\x1F\x7F]/u', '', $cleanLine);
    $cleanLine = trim($cleanLine);
    if (mb_strlen($cleanLine, 'UTF-8') > 500) {
        $cleanLine = mb_substr($cleanLine, 0, 500, 'UTF-8');
    }
    $item['value'] = $cleanLine;
}
unset($item);

// Persist to MariaDB site_settings table
try {
    $pdo = getDB();
    $pdo->beginTransaction();

    // Persist to MariaDB site_settings table.
    // Phase 3 (docs/DATABASE.md): site_settings is confirmed to be the
    // singleton wide-row schema only. The key-value branch this used
    // to have (`if ($hasKeyGroup) { INSERT ... }`) was always false in
    // production — meaning every save of a 'website', 'branding',
    // 'seo', or 'showcase' group setting has silently done nothing.
    // Only 'profile' group keys were ever actually persisted (via the
    // site_profile block below). Replaced with a real singleton-row
    // UPDATE, mapping the same group.key pairs that
    // includes/settings.php reads back (kept symmetric with that
    // read-side fix deliberately).
    $settingsUpdates = [];
    $settingsParams = [];
    foreach ($itemsToUpdate as $item) {
        $col = match ($item['group'] . '.' . $item['key']) {
            'website.platform_name'       => 'site_name',
            'website.platform_descriptor' => 'tagline',
            'website.canonical_url'       => 'site_url',
            'website.platform_purpose'    => 'site_description',
            'branding.favicon_url'        => 'favicon_url',
            'branding.monogram_url'       => 'monogram_url',
            'branding.og_image_url'       => 'default_og_image_url',
            'seo.default_title'           => 'default_seo_title',
            'seo.default_description'     => 'default_meta_description',
            'showcase.item_ids'           => 'showcase_item_ids',
            default                       => null,
        };
        if ($col !== null) {
            $settingsUpdates[] = "`{$col}` = ?";
            $settingsParams[] = $item['value'];
        }
    }
    if (!empty($settingsUpdates)) {
        $sqlSettings = "UPDATE site_settings SET " . implode(', ', $settingsUpdates) . " WHERE id = 1";
        $pdo->prepare($sqlSettings)->execute($settingsParams);
    }

    // Persist to canonical site_profile table if it exists (production schema)
    try {
        $profRow = $pdo->query("SELECT id FROM site_profile LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($profRow && !empty($profRow['id'])) {
            $profUpdates = [];
            $profParams = [];
            foreach ($itemsToUpdate as $item) {
                if ($item['group'] === 'profile') {
                    if ($item['key'] === 'name') { $profUpdates[] = "display_name = ?"; $profParams[] = $item['value']; }
                    elseif ($item['key'] === 'short_name') { $profUpdates[] = "short_name = ?"; $profParams[] = $item['value']; }
                    elseif ($item['key'] === 'role') { $profUpdates[] = "professional_title = ?"; $profParams[] = $item['value']; }
                    elseif ($item['key'] === 'motto') { $profUpdates[] = "motto = ?"; $profParams[] = $item['value']; }
                    elseif ($item['key'] === 'avatar_url') { $profUpdates[] = "profile_image_url = ?"; $profParams[] = $item['value']; }
                    elseif ($item['key'] === 'bio_short') { $profUpdates[] = "short_bio = ?"; $profParams[] = $item['value']; }
                    elseif ($item['key'] === 'bio_full') { $profUpdates[] = "full_bio = ?"; $profParams[] = $item['value']; }
                    elseif ($item['key'] === 'current_focus') { $profUpdates[] = "professional_focus = ?"; $profParams[] = $item['value']; }
                    elseif ($item['key'] === 'public_email') { $profUpdates[] = "public_email = ?"; $profParams[] = $item['value']; }
                }
            }
            if (!empty($profUpdates)) {
                $sqlProf = "UPDATE site_profile SET " . implode(', ', $profUpdates) . " WHERE id = ?";
                $profParams[] = $profRow['id'];
                $pdo->prepare($sqlProf)->execute($profParams);
            }
        }
    } catch (Throwable $e) {
        // site_profile is confirmed present in production (see
        // docs/DATABASE.md); a failure here is unexpected.
        error_log('[settings/update.php] site_profile write failed: ' . $e->getMessage());
    }

    // Sync to home_showcase_items if table exists and item_ids was updated
    foreach ($itemsToUpdate as $item) {
        if ($item['group'] === 'showcase' && $item['key'] === 'item_ids') {
            try {
                // Phase 3 (docs/DATABASE.md): post_id and sort_order are
                // confirmed real column names via a full production
                // schema export — the SHOW TABLES/SHOW COLUMNS guessing
                // this used to do is no longer needed.
                //
                // Bug fixed here: `item_type` is NOT NULL with no
                // DEFAULT in the real schema, but this INSERT never set
                // it — every save via this path would have failed
                // outright (silently, since the catch below only logs a
                // warning). `item_type` is never read anywhere in this
                // codebase (grep confirms zero read sites) — the
                // homepage always re-filters `posts WHERE type =
                // 'project'` regardless of this column — so any
                // valid enum value satisfies the constraint without
                // changing observable behavior. 'project' is used
                // since that's what these curated items semantically
                // are.
                $pdo->exec("DELETE FROM home_showcase_items");
                $parsedIds = json_decode($item['value'] ?? '[]', true);
                if (is_array($parsedIds) && !empty($parsedIds)) {
                    $insStmt = $pdo->prepare(
                        "INSERT INTO home_showcase_items (post_id, item_type, sort_order) VALUES (?, 'project', ?)"
                    );
                    foreach ($parsedIds as $idx => $pid) {
                        $insStmt->execute([(int)$pid, $idx + 1]);
                    }
                }
            } catch (Throwable $e) {
                error_log('[api/settings/update] home_showcase_items sync warning: ' . $e->getMessage());
            }
        }
    }

    $pdo->commit();

    $keysUpdated = array_map(fn($item) => ($item['group'] ?? 'site') . '.' . ($item['key'] ?? ''), $itemsToUpdate);
    logAdminAction('settings.update', 'settings', null, json_encode([
        'updated_keys'  => $keysUpdated,
        'updated_count' => count($itemsToUpdate),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    // Optionally update local cache file if api/data directory is writable
    $cacheFile = dirname(__DIR__) . '/data/settings_cache.json';
    try {
        $allGrouped = getAllGroupedSettings(false);
        $flattened = [];
        foreach ($allGrouped as $grp => $grpVals) {
            foreach ($grpVals as $k => $v) {
                $flattened["{$grp}.{$k}"] = $v;
            }
        }
        @file_put_contents($cacheFile, json_encode($flattened, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    } catch (Throwable $e) {
        // Cache write is non-fatal
        error_log('[api/settings/update] Cache write warning: ' . $e->getMessage());
    }

    echo json_encode([
        'success'       => true,
        'message'       => 'Settings updated successfully.',
        'updated_count' => count($itemsToUpdate),
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[api/settings/update] Database error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error while saving settings.',
    ], JSON_UNESCAPED_UNICODE);
}

