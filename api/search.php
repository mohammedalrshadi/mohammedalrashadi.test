<?php
// ============================================================
// SITE SEARCH API
// GET /api/search.php?q=...&limit=5
//
// Fast, grouped search endpoint for technical platform content:
// 1. Projects (posts WHERE type = 'project' AND status = 'published')
// 2. Articles (posts WHERE type = 'blog' AND status = 'published')
// 3. Lab Benchmarks (api/data/lab_experiments.json)
//
// Security invariants:
// - Never exposes drafts or soft-deleted records (status = 'published' AND deleted_at IS NULL).
// - Input query is clamped, sanitized, and SQL LIKE wildcards escaped.
// - Content snippets are stripped of HTML tags via strip_tags() and normalized.
// - Database credentials/exceptions are strictly concealed.
// ============================================================

require_once dirname(__DIR__) . '/api/config.php';
require_once dirname(__DIR__) . '/api/db.php';

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
}

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($requestMethod !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ------------------------------------------------------------
// Helper: Extract safe plaintext snippet from rich HTML content
// ------------------------------------------------------------
if (!function_exists('extractSafeSnippet')) {
    function extractSafeSnippet(?string $rawContent, string $query, int $maxLength = 160): string {
    if (empty($rawContent)) {
        return '';
    }

    // 1. Strip all HTML tags to prevent markup leakage
    $clean = strip_tags($rawContent);

    // 2. Decode HTML entities and strip tags again in case entities formed tags
    $clean = strip_tags(html_entity_decode($clean, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    // 3. Normalize whitespace (newlines, tabs, repeated spaces to single space)
    $clean = trim(preg_replace('/\s+/u', ' ', $clean));

    if ($clean === '') {
        return '';
    }

    // 4. Locate query match to provide contextual snippet
    $qPos = mb_stripos($clean, $query, 0, 'UTF-8');
    if ($qPos !== false) {
        $start = max(0, $qPos - 40);
        $snippet = mb_substr($clean, $start, $maxLength, 'UTF-8');
        $prefix = ($start > 0) ? '…' : '';
        $suffix = (mb_strlen($clean, 'UTF-8') > ($start + $maxLength)) ? '…' : '';
        return $prefix . trim($snippet) . $suffix;
    }

    // Default to leading excerpt if match was in title/category
    if (mb_strlen($clean, 'UTF-8') > $maxLength) {
        return mb_substr($clean, 0, $maxLength, 'UTF-8') . '…';
    }

    return $clean;
    }
}

// ------------------------------------------------------------
// Input validation & sanitization
// ------------------------------------------------------------
$rawQuery = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

// Clamp query length to prevent abuse
if (mb_strlen($rawQuery, 'UTF-8') > 100) {
    $rawQuery = mb_substr($rawQuery, 0, 100, 'UTF-8');
}

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;
if ($limit < 1) $limit = 5;
if ($limit > 10) $limit = 10;

// Short query: return empty result set immediately (< 2 chars)
if (mb_strlen($rawQuery, 'UTF-8') < 2) {
    echo json_encode([
        'success' => true,
        'query'   => $rawQuery,
        'total'   => 0,
        'results' => [
            'projects' => [],
            'articles' => [],
            'lab'      => []
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$projectResults = [];
$articleResults = [];
$labResults     = [];

try {
    require_once __DIR__ . '/../src/autoload.php';
    $pdo = getDB();
    $postRepo = new \Domain\Content\PostRepository($pdo);
    $posts = $postRepo->searchPublishedPosts($rawQuery, 40);
    foreach ($posts as $post) {
        $id = (int)$post['id'];
        $title = (string)$post['title'];
        $category = (string)$post['category'];
        $snippet = extractSafeSnippet($post['content'] ?? '', $rawQuery, 140);

        if ($post['type'] === 'project') {
            if (count($projectResults) < $limit) {
                $projectResults[] = [
                    'id'        => $id,
                    'title'     => $title,
                    'category'  => $category,
                    'type'      => 'project',
                    'url'       => '/project.php?id=' . $id,
                    'image'     => !empty($post['image_url']) ? (string)$post['image_url'] : null,
                    'snippet'   => $snippet,
                ];
            }
        } elseif ($post['type'] === 'blog') {
            if (count($articleResults) < $limit) {
                $articleResults[] = [
                    'id'        => $id,
                    'title'     => $title,
                    'category'  => $category,
                    'type'      => 'article',
                    'url'       => '/post.php?id=' . $id,
                    'snippet'   => $snippet,
                ];
            }
        }
    }
} catch (Throwable $e) {
    // Conceal database errors from client, log for ops
    error_log('[Site Search DB Error] ' . $e->getMessage());
}

// ------------------------------------------------------------
// 3. Query Lab Experiments (JSON file)
// ------------------------------------------------------------
$labFile = __DIR__ . '/data/lab_experiments.json';
if (file_exists($labFile)) {
    try {
        $labJson = file_get_contents($labFile);
        $labData = json_decode($labJson, true);

        if (is_array($labData)) {
            $needle = mb_strtolower($rawQuery, 'UTF-8');

            foreach ($labData as $exp) {
                if (count($labResults) >= $limit) {
                    break;
                }

                $id = (string)($exp['id'] ?? '');
                $title = (string)($exp['title'] ?? '');
                $cat = (string)($exp['categoryLabel'] ?? $exp['category'] ?? 'Benchmark');
                $subsystem = (string)($exp['subsystem'] ?? '');
                $question = (string)($exp['question'] ?? '');
                $hypothesis = (string)($exp['hypothesis'] ?? '');
                $outcome = (string)($exp['outcomeHeadline'] ?? '');
                $techArray = is_array($exp['tech'] ?? null) ? $exp['tech'] : [];
                $techStr = implode(' ', $techArray);

                $searchableHaystack = mb_strtolower(
                    $id . ' ' . $title . ' ' . $cat . ' ' . $subsystem . ' ' . $question . ' ' . $hypothesis . ' ' . $outcome . ' ' . $techStr,
                    'UTF-8'
                );

                $haystackNormalized = str_replace(['-', '_', ' '], '', $searchableHaystack);
                $needleNormalized   = str_replace(['-', '_', ' '], '', $needle);

                $isMatch = (mb_strpos($searchableHaystack, $needle) !== false) ||
                           ($needleNormalized !== '' && mb_strpos($haystackNormalized, $needleNormalized) !== false);

                if ($isMatch) {
                    // Choose most informative snippet: outcome headline, or question, or hypothesis
                    $displaySnippet = !empty($outcome) ? $outcome : (!empty($question) ? $question : $hypothesis);
                    $cleanSnippet = extractSafeSnippet($displaySnippet, $rawQuery, 140);

                    $labResults[] = [
                        'id'        => $id,
                        'title'     => $title,
                        'category'  => $cat,
                        'subsystem' => $subsystem,
                        'type'      => 'lab',
                        'url'       => '/lab-detail.php?id=' . urlencode($id),
                        'snippet'   => $cleanSnippet,
                    ];
                }
            }
        }
    } catch (Throwable $e) {
        error_log('[Site Search Lab JSON Error] ' . $e->getMessage());
    }
}

// ------------------------------------------------------------
// Response Assembly
// ------------------------------------------------------------
$total = count($projectResults) + count($articleResults) + count($labResults);

echo json_encode([
    'success' => true,
    'query'   => $rawQuery,
    'total'   => $total,
    'results' => [
        'projects' => $projectResults,
        'articles' => $articleResults,
        'lab'      => $labResults,
    ]
], JSON_UNESCAPED_UNICODE);
