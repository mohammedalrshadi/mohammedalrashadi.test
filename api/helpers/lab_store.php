<?php
// ============================================================
// LAB STORE — locked, atomic read-modify-write for lab_experiments.json
// api/helpers/lab_store.php  (DC-005)
//
// labStoreMutate($dataFile, $mutator):
//   1. takes an exclusive lock on "<file>.lock" (serialises all writers);
//   2. reads the LATEST JSON while holding the lock;
//   3. calls $mutator(array $experiments): array
//        - return ['experiments' => array, 'result' => mixed] to save, or
//        - return ['abort' => ['code' => int, 'message' => string]] to save nothing;
//   4. writes the full JSON to a temp file in the same directory, fsyncs, renames
//      it over the destination (atomic on the same filesystem), keeping the
//      destination's previous permissions (default 0644);
//   5. releases the lock.
// A corrupt (non-array) existing file aborts with 500 instead of being overwritten.
// Never logs experiment content.
// ============================================================

/**
 * @return array{ok:bool, code:int, message:string, result:mixed}
 */
function labStoreMutate(string $dataFile, callable $mutator): array
{
    $fail = static fn(int $code, string $msg): array => ['ok' => false, 'code' => $code, 'message' => $msg, 'result' => null];

    $dir = dirname($dataFile);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        return $fail(500, 'Storage directory is not available.');
    }

    $lock = @fopen($dataFile . '.lock', 'c');
    if ($lock === false) {
        return $fail(500, 'Could not open storage lock.');
    }
    if (!flock($lock, LOCK_EX)) {
        fclose($lock);
        return $fail(500, 'Could not lock storage.');
    }

    $tmp = null;
    try {
        $experiments = [];
        $mode = 0644;
        if (file_exists($dataFile)) {
            $raw = @file_get_contents($dataFile);
            if ($raw === false) {
                return $fail(500, 'Could not read storage.');
            }
            if (trim($raw) !== '') {
                $decoded = json_decode($raw, true);
                if (!is_array($decoded)) {
                    error_log('[lab_store] existing JSON is invalid; refusing to overwrite.');
                    return $fail(500, 'Stored data is unreadable; nothing was changed.');
                }
                $experiments = $decoded;
            }
            $perm = @fileperms($dataFile);
            if ($perm !== false) {
                $mode = $perm & 0777;
            }
        }

        $out = $mutator($experiments);
        if (isset($out['abort'])) {
            return $fail((int) $out['abort']['code'], (string) $out['abort']['message']);
        }

        $json = json_encode(array_values($out['experiments']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return $fail(500, 'Could not encode data.');
        }

        $tmp = $dir . '/.lab_experiments.' . bin2hex(random_bytes(6)) . '.tmp';
        $fh = @fopen($tmp, 'wb');
        if ($fh === false) {
            $tmp = null;
            return $fail(500, 'Failed to write to storage.');
        }
        $len = strlen($json);
        $written = fwrite($fh, $json);
        $flushed = fflush($fh);
        if (function_exists('fsync')) {
            @fsync($fh);
        }
        fclose($fh);
        if ($written !== $len || !$flushed) {
            @unlink($tmp);
            $tmp = null;
            return $fail(500, 'Failed to write to storage.');
        }
        @chmod($tmp, $mode);
        if (!@rename($tmp, $dataFile)) {
            @unlink($tmp);
            $tmp = null;
            return $fail(500, 'Failed to write to storage.');
        }
        $tmp = null;

        return ['ok' => true, 'code' => 200, 'message' => '', 'result' => $out['result'] ?? null];
    } finally {
        if ($tmp !== null && file_exists($tmp)) {
            @unlink($tmp);
        }
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
