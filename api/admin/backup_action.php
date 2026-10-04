<?php
// ============================================================
// BACKUP ACTION API
// api/admin/backup_action.php
//
// POST /api/admin/backup_action.php
// Requires: authenticated admin session + CSRF token
// Body (JSON): { "action": "list"|"create"|"delete", "filename": "..." }
//
// Actions:
//   list   — returns all backup files (no additional params needed)
//   create — runs createDatabaseBackup()
//   delete — deletes a single backup file (filename validated by strict regex)
// ============================================================

require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';
require_once dirname(dirname(__DIR__)) . '/api/helpers/backup.php';

header('Content-Type: application/json');

// Auth + CSRF checks
requireAuth();
requireCSRF();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$body  = file_get_contents('php://input');
$input = json_decode($body, true);

$action = isset($input['action']) ? trim((string) $input['action']) : '';

// ---- LIST ---------------------------------------------------------------
if ($action === 'list') {

    try {
        $files = listBackupFiles();

        // Attach last-run status
        $statusFile = dirname(dirname(__DIR__)) . '/api/data/backup_status.json';
        $lastRun    = null;
        if (file_exists($statusFile)) {
            $raw = @file_get_contents($statusFile);
            if ($raw) {
                $lastRun = json_decode($raw, true);
            }
        }

        echo json_encode([
            'success'  => true,
            'files'    => $files,
            'last_run' => $lastRun,
        ]);
    } catch (Throwable $e) {
        error_log('[backup_action/list] ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to list backups.']);
    }
    exit;
}

// ---- CREATE -------------------------------------------------------------
if ($action === 'create') {

    // Bump time limit for large databases — 10 minutes max
    @set_time_limit(600);

    try {
        $result = createDatabaseBackup();

        if ($result['success']) {
            logAdminAction('backup.create', 'backup', $result['filename'],
                'Manual backup created: ' . $result['filename'] . ' (' . $result['size_bytes'] . ' bytes)');

            // Also update the status file (same as cron does)
            $statusFile = dirname(dirname(__DIR__)) . '/api/data/backup_status.json';
            @file_put_contents($statusFile, json_encode([
                'last_run_at'   => date('Y-m-d H:i:s'),
                'last_run_ts'   => time(),
                'last_filename' => $result['filename'],
                'last_size'     => $result['size_bytes'],
                'status'        => 'success',
                'trigger'       => 'manual',
            ], JSON_PRETTY_PRINT));

            echo json_encode([
                'success'    => true,
                'message'    => 'Backup created successfully.',
                'filename'   => $result['filename'],
                'size_bytes' => $result['size_bytes'],
                'size_human' => _humanFileSize($result['size_bytes']),
            ]);
        } else {
            error_log('[backup_action/create] ' . ($result['message'] ?? 'unknown'));
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Backup failed: ' . ($result['message'] ?? 'Unknown error'),
            ]);
        }
    } catch (Throwable $e) {
        error_log('[backup_action/create] ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error during backup.']);
    }
    exit;
}

// ---- DELETE -------------------------------------------------------------
if ($action === 'delete') {

    $filename = isset($input['filename']) ? trim((string) $input['filename']) : '';

    // Strict filename whitelist — prevents any path traversal
    if (!preg_match('/^db-\d{8}-\d{6}-[0-9a-f]{8}\.sql\.gz$/', $filename)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid backup filename.']);
        exit;
    }

    $path = resolveBackupPath($filename);
    if ($path === null) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Backup file not found.']);
        exit;
    }

    if (!@unlink($path)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to delete backup file.']);
        exit;
    }

    logAdminAction('backup.delete', 'backup', $filename, "Deleted backup file: {$filename}");

    echo json_encode([
        'success' => true,
        'message' => 'Backup deleted.',
    ]);
    exit;
}

// ---- Unknown action -----------------------------------------------------
http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Unknown action.']);

