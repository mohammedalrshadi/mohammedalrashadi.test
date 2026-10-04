<?php
// ============================================================
// USER SETTINGS — PREFERENCES, PASSWORD & ACCOUNT DELETION
// POST /api/user/settings.php
// Requires: authenticated user + CSRF token
// ============================================================

error_reporting(0);
ini_set('log_errors', '1');

require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';
require_once __DIR__ . '/activity_helper.php';

header('Content-Type: application/json');

requireUserAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

requireCSRF();

$body  = file_get_contents('php://input');
$input = json_decode($body, true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input.']);
    exit;
}

$action = isset($input['action']) ? trim((string)$input['action']) : '';
$userId = currentUserId();

try {
    $pdo = getDB();

    // -------------------------------------------------------------
    // ACTION 1: UPDATE PREFERENCES
    // -------------------------------------------------------------
    if ($action === 'update_preferences') {
        $notifs = !empty($input['email_notifications']) ? 1 : 0;
        $saveHistory = !isset($input['save_reading_history']) || !empty($input['save_reading_history']) ? 1 : 0; // Default ON

        // Safely update or insert notification preference without requiring removed fields
        $stmt = $pdo->prepare(
            "INSERT INTO user_profiles (user_id, email_notifications, save_reading_history)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE 
                email_notifications = VALUES(email_notifications),
                save_reading_history = VALUES(save_reading_history)"
        );
        $stmt->execute([$userId, $notifs, $saveHistory]);

        logUserActivity($pdo, $userId, 'settings_updated', null, null, 'Updated notification preferences.');

        echo json_encode([
            'success' => true,
            'message' => 'Notification preferences updated successfully.'
        ]);
        exit;
    }

    // -------------------------------------------------------------
    // ACTION 2: CHANGE PASSWORD
    // -------------------------------------------------------------
    if ($action === 'change_password') {
        $currentPass = isset($input['current_password']) ? (string)$input['current_password'] : '';
        $newPass     = isset($input['new_password']) ? (string)$input['new_password'] : '';
        $confirmPass = isset($input['confirm_password']) ? (string)$input['confirm_password'] : '';

        if ($currentPass === '' || $newPass === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Current password and new password are required.']);
            exit;
        }

        if (strlen($newPass) < 8) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'New password must be at least 8 characters long.']);
            exit;
        }

        if ($newPass !== $confirmPass) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'New passwords do not match.']);
            exit;
        }

        // Verify current password against database
        $uStmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
        $uStmt->execute([$userId]);
        $userRow = $uStmt->fetch(PDO::FETCH_ASSOC);

        if (!$userRow || !password_verify($currentPass, $userRow['password_hash'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Incorrect current password.']);
            exit;
        }

        // Update with new bcrypt hash and invalidate older sessions
        $newHash = password_hash($newPass, PASSWORD_BCRYPT);
        $upStmt = $pdo->prepare('UPDATE users SET password_hash = ?, password_changed_at = NOW() WHERE id = ?');
        $upStmt->execute([$newHash, $userId]);

        // Keep current session valid
        $_SESSION['login_time'] = time();

        logUserActivity($pdo, $userId, 'settings_updated', null, null, 'Changed account password.');

        echo json_encode([
            'success' => true,
            'message' => 'Password changed successfully.'
        ]);
        exit;
    }

    // -------------------------------------------------------------
    // ACTION 3: DELETE ACCOUNT (Strict Safeguards)
    // -------------------------------------------------------------
    if ($action === 'delete_account') {
        $password    = isset($input['password']) ? (string)$input['password'] : '';
        $confirmText = isset($input['confirm_text']) ? trim((string)$input['confirm_text']) : '';

        if ($confirmText !== 'DELETE') {
            http_response_code(400);
            echo json_encode([
                'success' => false, 
                'message' => 'Please type DELETE in capital letters to confirm account deletion.'
            ]);
            exit;
        }

        // Verify password
        $uStmt = $pdo->prepare('SELECT role, password_hash FROM users WHERE id = ? LIMIT 1');
        $uStmt->execute([$userId]);
        $userRow = $uStmt->fetch(PDO::FETCH_ASSOC);

        if (!$userRow || !password_verify($password, $userRow['password_hash'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Incorrect password. Account deletion aborted.']);
            exit;
        }

        // Guard: Prevent deleting sole administrator account via user self-service
        if ($userRow['role'] === 'admin') {
            $adminCountStmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
            $adminCount = (int)$adminCountStmt->fetchColumn();
            if ($adminCount <= 1) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'message' => 'The sole administrator account cannot be deleted. Promote another admin first.'
                ]);
                exit;
            }
        }

        // Delete user row (cascades automatically delete user_profiles, bookmarks, likes,
        // reading_history, user_library, user_activities. Shared content like posts,
        // products, reviews are strictly preserved).
        $delStmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $delStmt->execute([$userId]);

        // Destroy session
        _startSecureSession();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();

        echo json_encode([
            'success'  => true,
            'message'  => 'Your account and personal data have been deleted.',
            'redirect' => '/'
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unrecognized settings action.']);

} catch (Exception $e) {
    error_log('[user/settings] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to process settings request.']);
}

