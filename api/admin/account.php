<?php
// ============================================================
// ADMIN ACCOUNT MANAGEMENT API
// Endpoints for managing logged-in admin's own credentials.
// Supports inspecting session security, updating email (with re-auth),
// and updating password (with current password verification & session sync).
// ============================================================

error_reporting(0);
ini_set('log_errors', '1');

require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';
require_once dirname(dirname(__DIR__)) . '/api/db.php';

header('Content-Type: application/json');

requireAuth();

$pdo = getDB();
$adminId = currentUserId();

if ($adminId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized session.']);
    exit;
}

// ------------------------------------------------------------
// GET: Fetch current admin account details
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->prepare(
            'SELECT id, name, email, role, status, last_login_at, password_changed_at, created_at 
               FROM users 
              WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$adminId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Admin account not found.']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'data'    => $user,
        ]);
        exit;
    } catch (PDOException $e) {
        error_log('[admin/account GET] Error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error retrieving account.']);
        exit;
    }
}

// ------------------------------------------------------------
// POST: State-changing account updates (email / password)
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();

    $body = file_get_contents('php://input');
    $input = json_decode($body, true);

    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
        exit;
    }

    $action = isset($input['action']) ? trim((string)$input['action']) : '';

    // Verify admin existence and current credentials
    try {
        $stmt = $pdo->prepare('SELECT id, name, email, password_hash FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$adminId]);
        $currentAccount = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$currentAccount) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Admin account not found.']);
            exit;
        }
    } catch (PDOException $e) {
        error_log('[admin/account POST precheck] Error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error.']);
        exit;
    }

    // --------------------------------------------------------
    // Action: update_email
    // --------------------------------------------------------
    if ($action === 'update_email') {
        $newEmail        = isset($input['new_email']) ? trim((string)$input['new_email']) : '';
        $currentPassword = isset($input['current_password']) ? (string)$input['current_password'] : '';

        if ($newEmail === '' || !filter_var($newEmail, FILTER_VALIDATE_EMAIL) || safeStrlen($newEmail) > 255) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Valid email address is required (maximum 255 characters).']);
            exit;
        }

        if ($currentPassword === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Current password is required to change your email address.']);
            exit;
        }

        if (!password_verify($currentPassword, $currentAccount['password_hash'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
            exit;
        }

        if (strtolower($newEmail) === strtolower($currentAccount['email'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'New email address must be different from current email.']);
            exit;
        }

        try {
            // Check uniqueness
            $checkStmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
            $checkStmt->execute([$newEmail, $adminId]);
            if ($checkStmt->fetch()) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Email address is already in use by another account.']);
                exit;
            }

            // Update email
            $updateStmt = $pdo->prepare('UPDATE users SET email = ? WHERE id = ?');
            $updateStmt->execute([$newEmail, $adminId]);

            // Sync current active session
            $_SESSION['user_email'] = $newEmail;

            // Audit log
            try {
                $auditStmt = $pdo->prepare(
                    "INSERT INTO admin_audit_log (admin_id, action, target_type, target_id, details) 
                     VALUES (?, 'update_email', 'user', ?, ?)"
                );
                $auditStmt->execute([
                    $adminId,
                    $adminId,
                    json_encode(['old_email' => $currentAccount['email'], 'new_email' => $newEmail]),
                ]);
            } catch (Throwable $e) { error_log('[api/admin/account.php:154] non-fatal, fallback used: ' . get_class($e)); }
            logAdminAction('account.update_email', 'user', (string) $adminId, json_encode([
                'old_email' => $currentAccount['email'],
                'new_email' => $newEmail,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            echo json_encode([
                'success' => true,
                'message' => 'Email address updated successfully.',
                'email'   => $newEmail,
            ]);
            exit;
        } catch (PDOException $e) {
            error_log('[admin/account update_email] Error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error updating email address.']);
            exit;
        }
    }

    // --------------------------------------------------------
    // Action: update_password
    // --------------------------------------------------------
    if ($action === 'update_password') {
        $currentPassword = isset($input['current_password']) ? (string)$input['current_password'] : '';
        $newPassword     = isset($input['new_password']) ? (string)$input['new_password'] : '';
        $confirmPassword = isset($input['confirm_password']) ? (string)$input['confirm_password'] : '';

        if ($currentPassword === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Current password is required.']);
            exit;
        }

        if (safeStrlen($newPassword) < 8) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'New password must be at least 8 characters long.']);
            exit;
        }

        if ($newPassword !== $confirmPassword) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'New password and confirmation do not match.']);
            exit;
        }

        if (!password_verify($currentPassword, $currentAccount['password_hash'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
            exit;
        }

        try {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $nowTime = date('Y-m-d H:i:s');

            $updateStmt = $pdo->prepare(
                'UPDATE users SET password_hash = ?, password_changed_at = ? WHERE id = ?'
            );
            $updateStmt->execute([$newHash, $nowTime, $adminId]);

            // Sync session login_time so current session remains valid
            // while older sessions on other devices fail the guard check
            $_SESSION['login_time'] = time() + 2;

            // Audit log
            try {
                $auditStmt = $pdo->prepare(
                    "INSERT INTO admin_audit_log (admin_id, action, target_type, target_id, details) 
                     VALUES (?, 'update_password', 'user', ?, ?)"
                );
                $auditStmt->execute([
                    $adminId,
                    $adminId,
                    json_encode(['action' => 'self_password_change']),
                ]);
            } catch (Throwable $e) { error_log('[api/admin/account.php:230] non-fatal, fallback used: ' . get_class($e)); }
            logAdminAction('account.update_password', 'user', (string) $adminId, json_encode([
                'action' => 'self_password_change',
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            echo json_encode([
                'success' => true,
                'message' => 'Password updated successfully. Other active sessions have been invalidated.',
            ]);
            exit;
        } catch (PDOException $e) {
            error_log('[admin/account update_password] Error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error updating password.']);
            exit;
        }
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action specified.']);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed.']);

