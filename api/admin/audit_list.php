<?php
// ============================================================
// ADMIN — AUDIT LOG API & CSV EXPORT
// GET /api/admin/audit_list.php (List query with filters & pagination)
// POST /api/admin/audit_list.php (CSV export with CSRF validation)
// Requires: authenticated admin session
// ============================================================

error_reporting(0);
ini_set('log_errors', '1');

require_once dirname(dirname(__DIR__)) . '/api/config.php';
require_once dirname(dirname(__DIR__)) . '/api/db.php';
require_once dirname(__DIR__) . '/auth/guard.php';

// Check authentication (this strictly requires role='admin')
requireAuth();

$pdo = getDB();

// ------------------------------------------------------------
// 1. Table Existence Check (Graceful migration notice)
// ------------------------------------------------------------
try {
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'admin_audit_log'");
    if ($tableCheck->rowCount() === 0) {
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'code'    => 'migration_required',
            'message' => 'The audit log table has not been initialized. Please run database migrations.',
        ]);
        exit;
    }
} catch (Throwable $e) {
    error_log('[audit_list] Table check error: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error checking audit log table.']);
    exit;
}

// ------------------------------------------------------------
// 2. Helper: CSV Formula Neutralizer (CWE-1236 Defense)
// ------------------------------------------------------------
if (!function_exists('_neutralizeCsvFormula')) {
    function _neutralizeCsvFormula(?string $value): string {
        if ($value === null || $value === '') {
            return '';
        }
        // Cells starting with =, +, -, @, tab, or carriage return trigger formula execution
        // Also check after stripping leading whitespace
        $trimmed = ltrim($value);
        if ($trimmed !== '' && preg_match('/^[\=\+\-\@\t\r]/', $trimmed)) {
            return "'" . $value;
        }
        if (preg_match('/^[\=\+\-\@\t\r]/', $value)) {
            return "'" . $value;
        }
        return $value;
    }
}

// ------------------------------------------------------------
// 3. Extract Common Query Filters
// ------------------------------------------------------------
$isPost = ($_SERVER['REQUEST_METHOD'] === 'POST');
$paramsSource = $isPost ? array_merge($_GET, $_POST) : $_GET;

$filterAdminId   = isset($paramsSource['admin_id']) && $paramsSource['admin_id'] !== '' ? (int)$paramsSource['admin_id'] : null;
$filterAction    = isset($paramsSource['action']) && trim($paramsSource['action']) !== '' ? trim($paramsSource['action']) : null;
$filterTarget    = isset($paramsSource['target_type']) && trim($paramsSource['target_type']) !== '' ? trim($paramsSource['target_type']) : null;
$filterDateFrom  = isset($paramsSource['date_from']) && trim($paramsSource['date_from']) !== '' ? trim($paramsSource['date_from']) : null;
$filterDateTo    = isset($paramsSource['date_to']) && trim($paramsSource['date_to']) !== '' ? trim($paramsSource['date_to']) : null;
$filterSearch    = isset($paramsSource['search']) && trim($paramsSource['search']) !== '' ? trim($paramsSource['search']) : null;
$isExport        = (isset($paramsSource['export']) && $paramsSource['export'] === 'csv');

// Strictly validate and parse dates to YYYY-MM-DD
function _parseDateParam(?string $dateStr): ?string {
    if (!$dateStr) return null;
    $dateStr = trim($dateStr);
    
    // Expect YYYY-MM-DD
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
        $d = DateTime::createFromFormat('Y-m-d', $dateStr);
        if ($d && $d->format('Y-m-d') === $dateStr) return $dateStr;
    }
    
    // Handle MM/DD/YYYY gracefully just in case frontend sends it
    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $dateStr)) {
        $d = DateTime::createFromFormat('m/d/Y', $dateStr);
        if ($d && $d->format('m/d/Y') === $dateStr) return $d->format('Y-m-d');
    }
    
    throw new InvalidArgumentException("Invalid date format. Expected YYYY-MM-DD.");
}

try {
    $filterDateFrom = _parseDateParam($filterDateFrom);
    $filterDateTo = _parseDateParam($filterDateTo);
    
    // Handle Date From > Date To
    if ($filterDateFrom && $filterDateTo && $filterDateFrom > $filterDateTo) {
        $temp = $filterDateFrom;
        $filterDateFrom = $filterDateTo;
        $filterDateTo = $temp;
    }
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

// Build WHERE conditions
$whereClauses = ['1=1'];
$whereParams  = [];

if ($filterAdminId !== null) {
    $whereClauses[] = 'l.admin_id = ?';
    $whereParams[]  = $filterAdminId;
}

if ($filterAction !== null) {
    $whereClauses[] = 'l.action = ?';
    $whereParams[]  = $filterAction;
}

if ($filterTarget !== null) {
    $whereClauses[] = 'l.target_type = ?';
    $whereParams[]  = $filterTarget;
}

if ($filterDateFrom !== null) {
    $whereClauses[] = 'l.created_at >= ?';
    $whereParams[]  = $filterDateFrom . ' 00:00:00';
}

if ($filterDateTo !== null) {
    $whereClauses[] = 'l.created_at <= ?';
    $whereParams[]  = $filterDateTo . ' 23:59:59';
}

if ($filterSearch !== null) {
    $whereClauses[] = '(l.target_id LIKE ? OR l.details LIKE ? OR l.ip_address LIKE ?)';
    $searchTerm     = '%' . $filterSearch . '%';
    $whereParams[]  = $searchTerm;
    $whereParams[]  = $searchTerm;
    $whereParams[]  = $searchTerm;
}

$whereSql = implode(' AND ', $whereClauses);

// ------------------------------------------------------------
// 4. Handle CSV Export (POST + CSRF Required, max 10,000 rows)
// ------------------------------------------------------------
if ($isExport) {
    if (!$isPost) {
        http_response_code(405);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'CSV export requires HTTP POST with a valid CSRF token.'
        ]);
        exit;
    }

    // CSRF Protection for stateful/logged export action
    requireCSRF();

    try {
        $exportSql = "SELECT 
                        l.id,
                        l.created_at,
                        l.admin_id,
                        u.name AS admin_name,
                        u.email AS admin_email,
                        l.action,
                        l.target_type,
                        l.target_id,
                        l.ip_address,
                        l.details
                      FROM admin_audit_log l
                      LEFT JOIN users u ON u.id = l.admin_id
                      WHERE {$whereSql}
                      ORDER BY l.id DESC
                      LIMIT 10000";

        $stmt = $pdo->prepare($exportSql);
        $stmt->execute($whereParams);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $rowCount = count($rows);

        // Audit log the export itself
        logAdminAction('audit.export', 'audit_log', null, json_encode([
            'exported_rows' => $rowCount,
            'filters'       => array_filter([
                'admin_id'    => $filterAdminId,
                'action'      => $filterAction,
                'target_type' => $filterTarget,
                'date_from'   => $filterDateFrom,
                'date_to'     => $filterDateTo,
                'search'      => $filterSearch,
            ]),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        // Set streaming headers
        $filename = 'admin_audit_log_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');

        // UTF-8 BOM for Microsoft Excel / Arabic multilingual display
        fwrite($out, "\xEF\xBB\xBF");

        // Header row
        fputcsv($out, [
            'Log ID',
            'Timestamp (UTC)',
            'Admin ID',
            'Admin Name',
            'Admin Email',
            'Action',
            'Target Type',
            'Target ID',
            'IP Address',
            'Details',
        ]);

        foreach ($rows as $row) {
            $adminDisplay = 'System / Portal';
            if ((int)$row['admin_id'] > 0) {
                $adminDisplay = $row['admin_name'] ?: ('Deleted user #' . $row['admin_id']);
            }

            fputcsv($out, [
                _neutralizeCsvFormula((string)$row['id']),
                _neutralizeCsvFormula($row['created_at']),
                _neutralizeCsvFormula((string)$row['admin_id']),
                _neutralizeCsvFormula($adminDisplay),
                _neutralizeCsvFormula($row['admin_email'] ?: ''),
                _neutralizeCsvFormula($row['action']),
                _neutralizeCsvFormula($row['target_type']),
                _neutralizeCsvFormula((string)($row['target_id'] ?? '')),
                _neutralizeCsvFormula($row['ip_address']),
                _neutralizeCsvFormula($row['details'] ?? ''),
            ]);
        }

        fclose($out);
        exit;

    } catch (Throwable $e) {
        error_log('[audit_list] CSV export error: ' . $e->getMessage());
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Failed to generate CSV export.']);
        exit;
    }
}

// ------------------------------------------------------------
// 5. Paginated JSON Listing
// ------------------------------------------------------------
header('Content-Type: application/json');

$page  = isset($paramsSource['page']) ? max(1, (int)$paramsSource['page']) : 1;
$limit = isset($paramsSource['limit']) ? min(200, max(1, (int)$paramsSource['limit'])) : 50;
$offset = ($page - 1) * $limit;

try {
    // Total matching records
    $countSql = "SELECT COUNT(*) FROM admin_audit_log l WHERE {$whereSql}";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($whereParams);
    $totalRecords = (int)$countStmt->fetchColumn();
    $totalPages   = $totalRecords > 0 ? (int)ceil($totalRecords / $limit) : 1;

    // Fetch records
    $dataSql = "SELECT 
                    l.id,
                    l.created_at,
                    l.admin_id,
                    u.name AS admin_name,
                    u.email AS admin_email,
                    l.action,
                    l.target_type,
                    l.target_id,
                    l.ip_address,
                    l.details
                FROM admin_audit_log l
                LEFT JOIN users u ON u.id = l.admin_id
                WHERE {$whereSql}
                ORDER BY l.id DESC
                LIMIT ? OFFSET ?";

    $dataStmt = $pdo->prepare($dataSql);
    $execIndex = 1;
    foreach ($whereParams as $param) {
        $dataStmt->bindValue($execIndex++, $param);
    }
    $dataStmt->bindValue($execIndex++, $limit, PDO::PARAM_INT);
    $dataStmt->bindValue($execIndex++, $offset, PDO::PARAM_INT);
    $dataStmt->execute();

    $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

    // Format display for deleted admins
    $formattedRows = array_map(function ($row) {
        if ((int)$row['admin_id'] > 0) {
            if (empty($row['admin_name'])) {
                $row['admin_name'] = 'Deleted user #' . $row['admin_id'];
            }
        } else {
            $row['admin_name'] = 'System / Portal';
        }
        return $row;
    }, $rows);

    // Filter metadata for UI population
    $metaActions = $pdo->query("SELECT DISTINCT action FROM admin_audit_log ORDER BY action ASC")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $metaTargets = $pdo->query("SELECT DISTINCT target_type FROM admin_audit_log ORDER BY target_type ASC")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $metaAdmins  = $pdo->query("SELECT id, name, email FROM users WHERE role = 'admin' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];

    echo json_encode([
        'success'    => true,
        'data'       => $formattedRows,
        'pagination' => [
            'page'        => $page,
            'limit'       => $limit,
            'total'       => $totalRecords,
            'total_pages' => $totalPages,
        ],
        'meta'       => [
            'actions'      => $metaActions,
            'target_types' => $metaTargets,
            'admins'       => $metaAdmins,
        ],
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('[audit_list] Query error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to fetch audit log records.']);
}
