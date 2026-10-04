<?php
// ============================================================
// USER PROFILE — GET & UPDATE
// GET & POST /api/user/profile.php
// Requires: authenticated user (+ CSRF token on POST)
// ============================================================

error_reporting(0);
ini_set('log_errors', '1');

require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';
require_once dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/activity_helper.php';
require_once dirname(__DIR__) . '/posts/sanitizer.php';
require_once dirname(__DIR__) . '/helpers/mailer.php';
require_once dirname(__DIR__) . '/helpers/email_templates.php';

header('Content-Type: application/json');

requireUserAuth();

$userId = currentUserId();

try {
    $pdo = getDB();

    // -------------------------------------------------------------
    // GET: Retrieve Profile Data
    // -------------------------------------------------------------
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->prepare(
            "SELECT u.id, u.name, u.email, u.role, u.created_at,
                    p.bio, p.avatar_url, p.theme_preference, p.language_preference, 
                    p.email_notifications, p.activity_visibility
             FROM users u
             LEFT JOIN user_profiles p ON u.id = p.user_id
             WHERE u.id = ? LIMIT 1"
        );
        $stmt->execute([$userId]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$profile) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'User profile not found.']);
            exit;
        }

        // Clean default values
        $profile['bio']                 = (string)($profile['bio'] ?? '');
        $profile['avatar_url']          = (string)($profile['avatar_url'] ?? '');
        $profile['theme_preference']    = (string)($profile['theme_preference'] ?? 'dark');
        $profile['language_preference'] = (string)($profile['language_preference'] ?? 'en');
        $profile['email_notifications'] = (int)($profile['email_notifications'] ?? 1);
        $profile['activity_visibility'] = (string)($profile['activity_visibility'] ?? 'private');

        echo json_encode([
            'success' => true,
            'data'    => $profile
        ]);
        exit;
    }

    // -------------------------------------------------------------
    // POST: Update Profile
    // -------------------------------------------------------------
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        requireCSRF();

        $body  = file_get_contents('php://input');
        $input = json_decode($body, true);

        if (!is_array($input)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid JSON input.']);
            exit;
        }

        $name = isset($input['name']) ? sanitizePlain(trim((string)$input['name'])) : '';
        $bio  = isset($input['bio']) ? sanitizePlain(trim((string)$input['bio'])) : '';
        $avatar = isset($input['avatar_url']) ? trim((string)$input['avatar_url']) : '';

        if ($name === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Name cannot be empty.']);
            exit;
        }

        if (mb_strlen($name, 'UTF-8') > 255) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Name cannot exceed 255 characters.']);
            exit;
        }

        if (mb_strlen($bio, 'UTF-8') > 2000) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Bio cannot exceed 2000 characters.']);
            exit;
        }

        // DC-009: avatar_url must be empty, a same-site path ("/x", not "//host"), or an https:// URL.
        if ($avatar !== '') {
            $avatarOk = mb_strlen($avatar, 'UTF-8') <= 500
                && !preg_match('/[\x00-\x1F\x7F\x5C\s<>\x22\x27]/', $avatar)
                && (
                    (($avatar[0] ?? '') === '/' && strpos($avatar, '//') !== 0)
                    || (preg_match('#^https://#i', $avatar) === 1 && filter_var($avatar, FILTER_VALIDATE_URL) !== false)
                );
            if (!$avatarOk) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Avatar must be a site path starting with / or an https:// URL.']);
                exit;
            }
        }

        // 1. Check if email update requested
        $newEmail = isset($input['email']) ? strtolower(trim((string)$input['email'])) : '';
        $emailChanged = false;

        $currStmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ? LIMIT 1");
        $currStmt->execute([$userId]);
        $currUser = $currStmt->fetch(PDO::FETCH_ASSOC);
        $oldEmail = strtolower(trim($currUser['email'] ?? ''));

        if ($newEmail !== '' && $newEmail !== $oldEmail) {
            if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL) || strlen($newEmail) > 255) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Please provide a valid email address.']);
                exit;
            }

            // Check uniqueness
            $dupStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
            $dupStmt->execute([$newEmail, $userId]);
            if ($dupStmt->fetch()) {
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'An account with this email address already exists.']);
                exit;
            }

            // Update user with email_verified_at = NULL
            $uStmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, email_verified_at = NULL WHERE id = ?");
            $uStmt->execute([$name, $newEmail, $userId]);
            $_SESSION['user_name']         = $name;
            $_SESSION['user_email']        = $newEmail;
            $_SESSION['email_verified_at'] = null;
            $emailChanged = true;

            // Invalidate older unused tokens
            $pdo->prepare("DELETE FROM email_verifications WHERE user_id = ? AND used_at IS NULL")->execute([$userId]);

            // Issue new 24h verification token
            $rawToken  = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $rawToken);
            $expiresAt = date('Y-m-d H:i:s', time() + 86400);
            $clientIp  = getClientIp();

            $pdo->prepare(
                'INSERT INTO email_verifications (user_id, token_hash, expires_at, requested_ip) VALUES (?, ?, ?, ?)'
            )->execute([$userId, $tokenHash, $expiresAt, $clientIp]);

            // Build site URL
            $siteUrl = 'https://mohammedalrashadi.com';
            try {
                $sUrlStmt = $pdo->query("SELECT site_url FROM site_settings LIMIT 1");
                if ($sUrlStmt && ($sUrlRow = $sUrlStmt->fetch(PDO::FETCH_ASSOC)) && !empty($sUrlRow['site_url'])) {
                    $siteUrl = rtrim($sUrlRow['site_url'], '/');
                }
            } catch (Throwable $_) { error_log('[api/user/profile.php:153] non-fatal, fallback used: ' . get_class($_)); }

            // Send verification link to NEW email address
            try {
                $verifyUrl = $siteUrl . '/verify-email.php?token=' . urlencode($rawToken);
                $verifyTpl = buildVerifyEmailEmail([
                    'name'           => $name,
                    'verify_url'     => $verifyUrl,
                    'requested_time' => date('Y-m-d H:i:s T'),
                    'requested_ip'   => $clientIp,
                ]);
                sendMail($newEmail, 'Verify your new email address — Mohammed Alrashadi Platform', $verifyTpl['html'], $verifyTpl['text']);
            } catch (Throwable $m1) {
                error_log('[profile] Failed to send verification to new email: ' . $m1->getMessage());
            }

            // Send security notice to OLD email address
            try {
                $noticeTpl = buildEmailChangedNoticeEmail([
                    'name'         => $currUser['name'] ?? $name,
                    'old_email'    => $oldEmail,
                    'new_email'    => $newEmail,
                    'changed_time' => date('Y-m-d H:i:s T'),
                    'changed_ip'   => $clientIp,
                ]);
                sendMail($oldEmail, 'Security Notice: Your account email was changed', $noticeTpl['html'], $noticeTpl['text']);
            } catch (Throwable $m2) {
                error_log('[profile] Failed to send notice to old email: ' . $m2->getMessage());
            }

        } else {
            // Update name only
            $uStmt = $pdo->prepare("UPDATE users SET name = ? WHERE id = ?");
            $uStmt->execute([$name, $userId]);
            $_SESSION['user_name'] = $name;
        }

        // 2. Update user_profiles table
        $pStmt = $pdo->prepare(
            "INSERT INTO user_profiles (user_id, bio, avatar_url) 
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE bio = VALUES(bio), avatar_url = VALUES(avatar_url)"
        );
        $pStmt->execute([$userId, $bio, $avatar]);

        // 3. Log activity
        $logDesc = $emailChanged ? 'Updated profile details and changed email address.' : 'Updated personal profile details.';
        logUserActivity($pdo, $userId, 'profile_updated', null, null, $logDesc);

        echo json_encode([
            'success'       => true,
            'message'       => $emailChanged 
                ? 'Profile updated. A verification link has been sent to your new email address.' 
                : 'Profile updated successfully.',
            'name'          => $name,
            'email'         => $emailChanged ? $newEmail : $oldEmail,
            'email_changed' => $emailChanged,
        ]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);

} catch (Exception $e) {
    error_log('[user/profile] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to process profile request.']);
}

