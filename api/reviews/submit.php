<?php
// ============================================================
// REVIEWS — SUBMIT (public)
// POST /api/reviews/submit.php
// No login required — this site has no public user accounts.
// Requires a valid CSRF token (see api/auth/csrf_token.php).
// New reviews are stored as status='pending' and do NOT appear
// publicly until an admin approves them.
//
// Optional body field:
//   post_id (positive integer) — associates the review with a
//   specific published article. Omit or send null for a
//   global/homepage review (stored with post_id = NULL).
//
//   If post_id is provided the server verifies:
//     - The value is a positive integer
//     - The referenced post exists in the database
//     - The referenced post has status = 'published'
//   Any failure in this check returns 400. The client cannot
//   bypass this by guessing or forging a post_id.
// ============================================================

require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';
require_once __DIR__ . '/rate_limit.php';

header('Content-Type: application/json');

// CSRF check (exits with 403 on failure). Does not require login —
// just a valid token tied to the visitor's own session.
requireCSRF();

// Policy (soft gating): logged-in users must verify their email before submitting reviews.
// Guests keep their existing behavior and rate limits.
if (isUserLoggedIn()) {
    $loggedUser = currentUser();
    if ($loggedUser && empty($loggedUser['email_verified_at'])) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'code'    => 'email_unverified',
            'message' => 'Please verify your email address before submitting reviews.',
        ]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// ---- Rate limiting (SEC-06) ------------------------------------------
// Checked early — before body parsing or DB access — so an IP that has
// already exceeded the limit costs nothing more than a JSON response.
$reviewWaitSeconds = reviewRateLimitCheck();
if ($reviewWaitSeconds !== null) {
    http_response_code(429);
    $reviewWaitMinutes = (int) ceil($reviewWaitSeconds / 60);
    echo json_encode([
        'success' => false,
        'message' => "لقد أرسلت عدداً كبيراً من المراجعات مؤخراً. يرجى المحاولة مرة أخرى بعد {$reviewWaitMinutes} دقيقة تقريباً.",
    ]);
    exit;
}

// ---- Parse body ------------------------------------------------------
$body  = file_get_contents('php://input');
$input = json_decode($body, true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'البيانات غير صالحة.']);
    exit;
}

$name    = safe_trim($input['name']    ?? null);
$email   = safe_trim($input['email']   ?? null);
$message = safe_trim($input['message'] ?? null);

// ---- Optional article association ------------------------------------
// Accepted: a positive integer (PHP int or a purely numeric string).
// Rejected / absent: stored as NULL (global/homepage review).
$postId = null;
if (isset($input['post_id'])) {
    $raw = $input['post_id'];
    if (is_int($raw) && $raw > 0) {
        $postId = $raw;
    } elseif (is_string($raw) && ctype_digit($raw) && (int) $raw > 0) {
        $postId = (int) $raw;
    }
    // Floats, negatives, zero, non-numeric strings → treated as no association
}

// ---- Validate --------------------------------------------------------
// Never trust values sent by JavaScript — every rule here is
// re-checked server-side regardless of what the browser already did.

if ($name === '' || safeStrlen($name) > 255) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'الاسم مطلوب (وبحد أقصى 255 حرفاً).']);
    exit;
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || safeStrlen($email) > 255) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'البريد الإلكتروني غير صالح.']);
    exit;
}

// Reject empty/whitespace-only messages, enforce min/max length
$messageLength = safeStrlen($message);

if ($message === '' || $messageLength < 10) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'المراجعة قصيرة جداً (10 أحرف على الأقل).']);
    exit;
}

if ($messageLength > 2000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'المراجعة طويلة جداً (2000 حرف كحد أقصى).']);
    exit;
}

try {

    $pdo = getDB();

    // ---- Validate article existence (if post_id provided) ------------
    // The backend is the sole authority on whether a post_id is valid.
    // A client-supplied post_id that points to a non-existent, hidden,
    // or wrong-type post is rejected here before any INSERT. This
    // prevents reviews from being orphaned on deleted/hidden articles
    // and stops enumeration of internal post IDs.
    if ($postId !== null) {
        $postCheckStmt = $pdo->prepare(
            "SELECT id FROM posts
             WHERE id = ? AND status = 'published' AND deleted_at IS NULL
             LIMIT 1"
        );
        $postCheckStmt->execute([$postId]);
        if (!$postCheckStmt->fetch()) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'المقالة غير موجودة أو غير منشورة.',
            ]);
            exit;
        }
    }

    // ---- Lightweight duplicate-submission guard -----------------------
    // Catches double-clicks / network retries / duplicate JS event
    // firing: blocks only an IDENTICAL name+email+message+post_id
    // re-submitted within the last 60 seconds.
    //
    // post_id is included in the scope so that the same message body
    // submitted to two different articles within 60 s is NOT
    // incorrectly swallowed as a duplicate.
    //
    // IS NULL cannot use a bound parameter in standard SQL, so the two
    // cases (article-specific vs. global) use separate prepared
    // statements rather than a single shared query.
    if ($postId !== null) {
        $dupStmt = $pdo->prepare(
            'SELECT id FROM reviews
             WHERE email   = ?
               AND message = ?
               AND post_id = ?
               AND created_at > (NOW() - INTERVAL 60 SECOND)
             LIMIT 1'
        );
        $dupStmt->execute([$email, $message, $postId]);
    } else {
        $dupStmt = $pdo->prepare(
            'SELECT id FROM reviews
             WHERE email   = ?
               AND message = ?
               AND post_id IS NULL
               AND created_at > (NOW() - INTERVAL 60 SECOND)
             LIMIT 1'
        );
        $dupStmt->execute([$email, $message]);
    }

    if ($dupStmt->fetch()) {
        // Treat as a soft success — the review already went through;
        // no need to alarm the user with an error for a double-click.
        echo json_encode([
            'success' => true,
            'message' => 'تم إرسال مراجعتك بنجاح، وستظهر بعد الموافقة عليها.',
        ]);
        exit;
    }

    // ---- Insert -------------------------------------------------------
    $insertStmt = $pdo->prepare(
        "INSERT INTO reviews (name, email, message, post_id, status)
         VALUES (?, ?, ?, ?, 'pending')"
    );
    $insertStmt->execute([$name, $email, $message, $postId]);
    $newReviewId = (int)$pdo->lastInsertId();

    // Dispatch admin notification alert
    try {
        require_once dirname(__DIR__) . '/helpers/alerts.php';
        $articleTitle = '';
        if ($postId !== null) {
            $titleStmt = $pdo->prepare("SELECT title FROM posts WHERE id = ? LIMIT 1");
            $titleStmt->execute([$postId]);
            $articleTitle = (string)$titleStmt->fetchColumn();
        }

        $alertTitle = $articleTitle !== ''
            ? 'New Pending Review for: ' . mb_substr($articleTitle, 0, 70, 'UTF-8')
            : 'New Pending Visitor Review';
        $alertBody = 'Review submitted by ' . mb_substr($name, 0, 50, 'UTF-8') . ' (' . mb_substr($email, 0, 80, 'UTF-8') . ') awaiting moderation approval.';

        createAdminAlert(
            'review.pending',
            'info',
            $alertTitle,
            $alertBody,
            'reviews.php',
            'review_pending_' . $newReviewId
        );
    } catch (Throwable $alertEx) {
        error_log('[reviews/submit] Alert error: ' . $alertEx->getMessage());
    }

    // Record this submission against the IP's rate-limit counter.
    // Only called on a real INSERT (not on duplicate-guard soft-successes
    // above) so the counter reflects genuine new submissions only.
    reviewRateLimitRecord();

    echo json_encode([
        'success' => true,
        'message' => 'تم إرسال مراجعتك بنجاح، وستظهر بعد الموافقة عليها.',
    ]);

} catch (PDOException $e) {

    error_log('[reviews/submit] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'A server error occurred. Please try again later.']);

}
