<?php
// ============================================================
// USERS — UPDATE
// POST /api/users/update.php
// Requires: authenticated admin session + CSRF token
// Body (JSON): {id, name, email, role, status?, password?}
// Guardrails:
//   - Cannot demote, suspend, or ban self
//   - Cannot demote or suspend the last active admin
//   - Password update sets password_changed_at = NOW() (invalidates sessions)
//   - Audit log recorded for all role/status changes
// ============================================================

error_reporting(0);
ini_set('log_errors', '1');

require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';

header('Content-Type: application/json');

requireAuth();
requireCSRF();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$body  = file_get_contents('php://input');
$input = json_decode($body, true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid data payload.']);
    exit;
}

$id       = isset($input['id'])       ? (int) $input['id']       : 0;
$name     = isset($input['name'])     ? trim($input['name'])     : '';
$email    = isset($input['email'])    ? trim($input['email'])    : '';
$role     = isset($input['role'])     ? trim($input['role'])     : '';
$status   = isset($input['status'])   ? trim($input['status'])   : '';
$password = isset($input['password']) ? $input['password']       : '';

$changingPassword = ($password !== '');

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid user ID.']);
    exit;
}

if ($name === '' || safeStrlen($name) > 255) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Name is required (max 255 characters).']);
    exit;
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || safeStrlen($email) > 255) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid email address is required.']);
    exit;
}

if (!in_array($role, ['user', 'admin'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid role specified.']);
    exit;
}

if ($status !== '' && !in_array($status, ['active', 'suspended', 'banned'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status specified.']);
    exit;
}

if ($changingPassword && safeStrlen($password) < 8) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'New password must be at least 8 characters.']);
    exit;
}

try {
    $pdo = getDB();

    $stmt = $pdo->prepare('SELECT id, role, status FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $target = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$target) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit;
    }

    $currentAdminId = currentUserId();
    $targetStatus   = ($status !== '') ? $status : $target['status'];

    // 1. SELF-PROTECTION GUARD
    if ($id === $currentAdminId) {
        if ($role !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'You cannot demote your own administrator account.']);
            exit;
        }
        if ($targetStatus !== 'active') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'You cannot suspend or ban your own administrator account.']);
            exit;
        }
    }

    // 2. LAST-ACTIVE-ADMIN PROTECTION GUARD
    $isDemotingOrDeactivatingAdmin = ($target['role'] === 'admin') && ($role !== 'admin' || $targetStatus !== 'active');
    if ($isDemotingOrDeactivatingAdmin) {
        $countStmt = $pdo->prepare(
            "SELECT COUNT(*) AS active_admins FROM users WHERE role = 'admin' AND status = 'active' AND id != ?"
        );
        $countStmt->execute([$id]);
        $remainingAdmins = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['active_admins'];

        if ($remainingAdmins < 1) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => 'Cannot demote or deactivate the last active administrator. Create or activate another admin first.',
            ]);
            exit;
        }
    }

    // 3. Check email uniqueness
    $checkStmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
    $checkStmt->execute([$email, $id]);
    if ($checkStmt->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Email address is already in use by another account.']);
        exit;
    }

    // 4. Update query
    $fields = ['name = ?', 'email = ?', 'role = ?', 'status = ?'];
    $params = [$name, $email, $role, $targetStatus];

    if ($changingPassword) {
        $fields[] = 'password_hash = ?';
        $fields[] = 'password_changed_at = NOW()';
        $params[] = password_hash($password, PASSWORD_DEFAULT);
    }

    $params[] = $id;

    $updateSql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?';
    $pdo->prepare($updateSql)->execute($params);

    // 5. Audit Logging
    $auditDetails = [];
    if ($target['role'] !== $role) {
        $auditDetails[] = "Role changed from {$target['role']} to {$role}";
    }
    if ($target['status'] !== $targetStatus) {
        $auditDetails[] = "Status changed from {$target['status']} to {$targetStatus}";
    }
    if ($changingPassword) {
        $auditDetails[] = "Password reset by admin";
    }

    $actionName = 'user.update';
    if ($target['role'] !== $role) {
        $actionName = 'user.update_role';
    } elseif ($target['status'] !== $targetStatus) {
        $actionName = 'user.update_status';
    }

    logAdminAction($actionName, 'user', (string) $id, json_encode([
        'changes'        => $auditDetails,
        'new_role'       => $role,
        'new_status'     => $targetStatus,
        'password_reset' => $changingPassword,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    echo json_encode([
        'success' => true,
        'message' => 'User account updated successfully.',
    ]);

} catch (PDOException $e) {
    if ((int) $e->getCode() === 23000 || str_contains($e->getMessage(), 'Duplicate')) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Email address already exists.']);
        exit;
    }
    error_log('[users/update] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error updating user.']);
}
