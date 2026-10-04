<?php
// ============================================================
// DATABASE BACKUP HELPER
// api/helpers/backup.php
//
// createDatabaseBackup(): array
//
// Produces a complete MySQL dump via pure PDO (no exec/mysqldump).
// Streams output to a gzip file so memory stays flat regardless
// of database size.
//
// Returns:
//   ['success' => true,  'path' => '/abs/path/file.sql.gz',
//    'filename' => 'db-....sql.gz', 'size_bytes' => 12345]
//   ['success' => false, 'message' => 'reason']
//
// Storage priority:
//   1. Two directories above public_html  (outside web root)
//   2. api/data/backups/                  (with .htaccess block)
//
// Retention: keeps the 14 newest files, deletes older ones.
// ============================================================

require_once __DIR__ . '/../db.php';

define('BACKUP_RETENTION_COUNT', 14);
define('BACKUP_BATCH_SIZE',      500);

// ---- Resolve the storage directory ---------------------------------------
function _getBackupDir(): string
{
    // public_html/api/helpers/backup.php
    // dirname x1 → public_html/api/helpers
    // dirname x2 → public_html/api
    // dirname x3 → public_html
    // dirname x4 → home directory  (e.g. /home/u303927365)
    $homeDir    = dirname(__DIR__, 3);          // public_html's parent
    $outsideDir = $homeDir . '/backups';

    if (!is_dir($outsideDir)) {
        @mkdir($outsideDir, 0750, true);
    }

    if (is_dir($outsideDir) && is_writable($outsideDir)) {
        return $outsideDir;
    }

    // Fallback: api/data/backups/ inside the web root (protected by .htaccess)
    $fallbackDir = dirname(__DIR__) . '/data/backups';

    if (!is_dir($fallbackDir)) {
        @mkdir($fallbackDir, 0750, true);
    }

    // Ensure .htaccess protection exists
    $htaccess = $fallbackDir . '/.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess,
            "# Block all direct HTTP access to backup files\n" .
            "<IfModule mod_authz_core.c>\n" .
            "    Require all denied\n" .
            "</IfModule>\n" .
            "<IfModule !mod_authz_core.c>\n" .
            "    Deny from all\n" .
            "</IfModule>\n"
        );
    }

    $indexHtml = $fallbackDir . '/index.html';
    if (!file_exists($indexHtml)) {
        file_put_contents($indexHtml, '<!DOCTYPE html><html><body></body></html>');
    }

    return $fallbackDir;
}

// ---- Generate a unique backup filename -----------------------------------
function _makeBackupFilename(): string
{
    $ts  = date('Ymd-His');
    $rnd = bin2hex(random_bytes(4)); // 8 hex chars
    return "db-{$ts}-{$rnd}.sql.gz";
}

// ---- Copy the Labs JSON store next to the DB dump ---------------------------
// api/data/lab_experiments.json is not part of the database, so the SQL dump does not
// contain it. Best-effort: a failure here never fails the database backup.
// Kept separate from the db-*.sql.gz list (different file pattern) and pruned to the same count.
function _backupLabsJson(string $dir): void
{
    try {
        $src = dirname(__DIR__) . '/data/lab_experiments.json';
        if (!is_file($src)) {
            return;
        }
        $dest = $dir . '/labs-' . date('Y-m-d_His') . '.json';
        if (@copy($src, $dest)) {
            @chmod($dest, 0640);
        } else {
            error_log('[backup] Could not copy lab_experiments.json');
            return;
        }
        $files = glob($dir . '/labs-*.json') ?: [];
        rsort($files);
        foreach (array_slice($files, BACKUP_RETENTION_COUNT) as $old) {
            @unlink($old);
        }
    } catch (Throwable $e) {
        error_log('[backup] Labs JSON backup failed: ' . get_class($e));
    }
}

// ---- Prune old backups to BACKUP_RETENTION_COUNT -------------------------
function _pruneOldBackups(string $dir): void
{
    $files = glob($dir . '/db-*.sql.gz');
    if ($files === false || count($files) <= BACKUP_RETENTION_COUNT) {
        return;
    }

    // Sort newest first by mtime
    usort($files, function (string $a, string $b): int {
        return filemtime($b) <=> filemtime($a);
    });

    $toDelete = array_slice($files, BACKUP_RETENTION_COUNT);
    foreach ($toDelete as $old) {
        @unlink($old);
    }
}

// ---- Escape a string value for SQL INSERT output -------------------------
function _sqlEscapeValue(mixed $val): string
{
    if ($val === null) {
        return 'NULL';
    }
    // Escape backslashes, single quotes, NUL bytes, and newlines
    $escaped = str_replace(
        ['\\',   "'",    "\0",  "\n",  "\r",  "\x1a"],
        ['\\\\', "\\'",  '\\0', '\\n', '\\r', '\\Z'],
        (string) $val
    );
    return "'{$escaped}'";
}

// ---- Main backup function ------------------------------------------------
function createDatabaseBackup(): array
{
    try {
        $pdo = getDB();
    } catch (Throwable $e) {
        return ['success' => false, 'message' => 'DB connection failed: ' . $e->getMessage()];
    }

    $dir      = _getBackupDir();
    $filename = _makeBackupFilename();
    $path     = $dir . '/' . $filename;

    // Verify the directory is writable before we start
    if (!is_dir($dir) || !is_writable($dir)) {
        return ['success' => false, 'message' => "Backup directory is not writable: {$dir}"];
    }

    $gz = @gzopen($path, 'wb9');
    if ($gz === false) {
        return ['success' => false, 'message' => "Failed to open gzip stream for writing: {$path}"];
    }

    try {

        // ---- Prologue -------------------------------------------------------
        $prologue = implode("\n", [
            '-- ============================================================',
            '-- Database Backup — Pure PHP PDO dump',
            '-- Generated : ' . date('Y-m-d H:i:s T'),
            '-- Database  : ' . DB_NAME,
            '-- ============================================================',
            '',
            'SET NAMES utf8mb4;',
            'SET CHARACTER SET utf8mb4;',
            'SET FOREIGN_KEY_CHECKS = 0;',
            'SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";',
            'SET time_zone = "+00:00";',
            '',
        ]);
        gzwrite($gz, $prologue);

        // ---- Get table list -------------------------------------------------
        $tables = $pdo->query('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'')
                      ->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {

            // Sanitize table name for use in SQL (backtick-quoted)
            $quotedTable = '`' . str_replace('`', '``', $table) . '`';

            gzwrite($gz, "\n-- ------------------------------------------------------------\n");
            gzwrite($gz, "-- Table: {$table}\n");
            gzwrite($gz, "-- ------------------------------------------------------------\n\n");

            // DROP TABLE IF EXISTS
            gzwrite($gz, "DROP TABLE IF EXISTS {$quotedTable};\n");

            // CREATE TABLE
            $createRow = $pdo->query("SHOW CREATE TABLE {$quotedTable}")
                              ->fetch(PDO::FETCH_NUM);
            if ($createRow && isset($createRow[1])) {
                gzwrite($gz, $createRow[1] . ";\n\n");
            }

            // INSERT DATA in batches
            $countStmt = $pdo->query("SELECT COUNT(*) FROM {$quotedTable}");
            $totalRows = (int) $countStmt->fetchColumn();

            if ($totalRows === 0) {
                continue;
            }

            $offset = 0;
            while ($offset < $totalRows) {
                $stmt = $pdo->query(
                    "SELECT * FROM {$quotedTable} LIMIT " . BACKUP_BATCH_SIZE . " OFFSET {$offset}"
                );
                $rows = $stmt->fetchAll(PDO::FETCH_NUM);

                if (empty($rows)) {
                    break;
                }

                // Get column names from the first batch only
                if ($offset === 0) {
                    $colStmt = $pdo->query("SHOW COLUMNS FROM {$quotedTable}");
                    $cols    = $colStmt->fetchAll(PDO::FETCH_COLUMN);
                    $colList = implode(', ', array_map(
                        fn(string $c) => '`' . str_replace('`', '``', $c) . '`',
                        $cols
                    ));
                }

                // Build a single multi-row INSERT per batch
                $valueGroups = [];
                foreach ($rows as $row) {
                    $escaped = array_map('_sqlEscapeValue', $row);
                    $valueGroups[] = '(' . implode(', ', $escaped) . ')';
                }

                gzwrite($gz,
                    "INSERT INTO {$quotedTable} ({$colList}) VALUES\n" .
                    implode(",\n", $valueGroups) . ";\n\n"
                );

                $offset += BACKUP_BATCH_SIZE;

                // Yield to avoid max_execution_time on very large tables
                // (shared hosting allows a brief sleep between batches)
            }
        }

        // ---- Epilogue -------------------------------------------------------
        $epilogue = implode("\n", [
            '',
            'SET FOREIGN_KEY_CHECKS = 1;',
            '',
            '-- ============================================================',
            '-- End of backup',
            '-- ============================================================',
            '',
        ]);
        gzwrite($gz, $epilogue);

        gzclose($gz);
        $gz = null;

        // Verify the file was actually written
        if (!file_exists($path) || filesize($path) === 0) {
            return ['success' => false, 'message' => 'Backup file is empty after writing.'];
        }

        // Prune old backups after a successful run
        _pruneOldBackups($dir);

        // Labs are stored in a JSON file, not in the database: copy it next to the dump.
        _backupLabsJson($dir);

        return [
            'success'    => true,
            'path'       => $path,
            'filename'   => $filename,
            'size_bytes' => (int) filesize($path),
        ];

    } catch (Throwable $e) {

        if ($gz !== null) {
            gzclose($gz);
        }
        // Remove partial file
        if (file_exists($path)) {
            @unlink($path);
        }

        error_log('[backup] createDatabaseBackup() failed: ' . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// ---- List backup files (sorted newest first) ----------------------------
function listBackupFiles(): array
{
    $dir   = _getBackupDir();
    $files = glob($dir . '/db-*.sql.gz') ?: [];

    usort($files, fn(string $a, string $b) => filemtime($b) <=> filemtime($a));

    return array_map(function (string $path) use ($dir): array {
        $filename = basename($path);
        $mtime    = (int) filemtime($path);
        $size     = (int) filesize($path);

        // Parse date from filename: db-YYYYMMDD-HHMMSS-<hex>.sql.gz
        $created_at = null;
        if (preg_match('/^db-(\d{4})(\d{2})(\d{2})-(\d{2})(\d{2})(\d{2})-/', $filename, $m)) {
            $created_at = "{$m[1]}-{$m[2]}-{$m[3]} {$m[4]}:{$m[5]}:{$m[6]}";
        }

        return [
            'filename'   => $filename,
            'path'       => $path,
            'size_bytes' => $size,
            'size_human' => _humanFileSize($size),
            'created_at' => $created_at,
            'mtime'      => $mtime,
        ];
    }, $files);
}

// ---- Get absolute path for a validated backup filename ------------------
// Returns null if the file doesn't exist in the backup directory.
function resolveBackupPath(string $filename): ?string
{
    // Strict whitelist: only our canonical filenames
    if (!preg_match('/^db-\d{8}-\d{6}-[0-9a-f]{8}\.sql\.gz$/', $filename)) {
        return null;
    }
    // basename() as extra path-traversal guard (redundant after regex, but belt-and-suspenders)
    $safe = basename($filename);
    $path = _getBackupDir() . '/' . $safe;
    return file_exists($path) ? $path : null;
}

// ---- Human-readable file size -------------------------------------------
function _humanFileSize(int $bytes): string
{
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    }
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' B';
}

