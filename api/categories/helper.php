<?php
// ============================================================
// CATEGORY HELPER — SHARED UTILITIES [REQ-015 Phase 2]
// Provides category normalization, validation, and collision detection.
// ============================================================

/**
 * Normalizes category whitespace:
 * - Trims leading and trailing whitespace
 * - Collapses multiple consecutive whitespace characters into a single space
 */
function normalizeCategoryWhitespace(string $s): string {
    return trim(preg_replace('/\s+/u', ' ', $s));
}

/**
 * Normalizes Arabic string for comparison and duplicate detection ONLY.
 * Strips tashkeel, tatweel, normalizes alefs, taa marbuta, and alef maksura.
 * The normalized string must NEVER replace the canonical display name.
 */
function normalizeArabicForComparison(string $s): string {
    $s = normalizeCategoryWhitespace($s);
    // Remove Arabic diacritics / tashkeel / harakat / tatweel (U+064B-U+065F, U+0670, U+0640)
    $s = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $s);
    // Normalize Alefs (أ, إ, آ, ٱ -> ا)
    $s = preg_replace('/[أإآٱ]/u', 'ا', $s);
    // Normalize Taa Marbuta (ة -> ه)
    $s = preg_replace('/ة/u', 'ه', $s);
    // Normalize Alef Maksura (ى -> ي)
    $s = preg_replace('/ى/u', 'ي', $s);
    // Unicode lowercase for mixed/Latin characters
    return mb_strtolower($s, 'UTF-8');
}

/**
 * Validates a category name:
 * Must be 2-60 characters (UTF-8 safe).
 * Returns null if valid, or a localized error message string if invalid.
 */
function validateCategoryName(string $name): ?string {
    $len = mb_strlen($name, 'UTF-8');
    if ($len < 2 || $len > 60) {
        return 'Category name must be between 2 and 60 characters.';
    }
    return null;
}

const VALID_CATEGORY_TYPES = ['blog', 'project', 'project', 'lab', 'product'];

function isValidCategoryType(string $type): bool {
    return in_array($type, VALID_CATEGORY_TYPES, true);
}

/**
 * Searches for an existing category in `categories` matching by exact name
 * or by normalized Arabic comparison within the same scope `type`.
 *
 * @param PDO $pdo
 * @param string $inputName Normalized whitespace name
 * @param string $type 'blog' or 'project'
 * @param int|null $excludeId Optional category id to exclude (for rename)
 * @return array|null Returns matching category row or null
 */
function findCategoryMatch(PDO $pdo, string $inputName, string $type, ?int $excludeId = null): ?array {
    $cleanInput = normalizeCategoryWhitespace($inputName);
    if ($cleanInput === '') {
        return null;
    }

    // 1. Fast exact check
    $sqlExact = 'SELECT id, name, type FROM categories WHERE type = ? AND name = ?';
    $paramsExact = [$type, $cleanInput];
    if ($excludeId !== null) {
        $sqlExact .= ' AND id != ?';
        $paramsExact[] = $excludeId;
    }
    $sqlExact .= ' LIMIT 1';

    $stmt = $pdo->prepare($sqlExact);
    $stmt->execute($paramsExact);
    $exactRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($exactRow) {
        return $exactRow;
    }

    // 2. Normalized Arabic comparison check against all categories of same type
    $sqlAll = 'SELECT id, name, type FROM categories WHERE type = ?';
    $paramsAll = [$type];
    if ($excludeId !== null) {
        $sqlAll .= ' AND id != ?';
        $paramsAll[] = $excludeId;
    }

    $stmtAll = $pdo->prepare($sqlAll);
    $stmtAll->execute($paramsAll);
    $allRows = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

    $normalizedTarget = normalizeArabicForComparison($cleanInput);
    foreach ($allRows as $row) {
        if (normalizeArabicForComparison($row['name']) === $normalizedTarget) {
            return $row;
        }
    }

    return null;
}

/**
 * Finds an existing category or creates a new one (REQ-019).
 * Catches PDOException SQLSTATE 23000 (duplicate entry on unique_type_name)
 * and safely re-queries to handle race conditions.
 *
 * @param PDO $pdo
 * @param string $inputName
 * @param string $type
 * @return array ['id' => int, 'name' => string, 'type' => string]
 * @throws InvalidArgumentException if validation fails
 */
function findOrCreateCategory(PDO $pdo, string $inputName, string $type): array {
    $cleanName = normalizeCategoryWhitespace($inputName);
    if ($cleanName === '') {
        throw new InvalidArgumentException('Category name cannot be empty.');
    }

    if (!isValidCategoryType($type)) {
        throw new InvalidArgumentException('Invalid category content type.');
    }

    $existing = findCategoryMatch($pdo, $cleanName, $type);
    if ($existing) {
        return $existing;
    }

    $validationError = validateCategoryName($cleanName);
    if ($validationError !== null) {
        throw new InvalidArgumentException($validationError);
    }

    try {
        $stmt = $pdo->prepare('INSERT INTO categories (name, type) VALUES (?, ?)');
        $stmt->execute([$cleanName, $type]);
        $newId = (int) $pdo->lastInsertId();

        return [
            'id'   => $newId,
            'name' => $cleanName,
            'type' => $type,
        ];
    } catch (PDOException $e) {
        // Catch SQLSTATE 23000 duplicate entry race condition
        if ($e->getCode() === '23000' || str_contains($e->getMessage(), '23000') || str_contains($e->getMessage(), 'Duplicate entry')) {
            $refetched = findCategoryMatch($pdo, $cleanName, $type);
            if ($refetched) {
                return $refetched;
            }
        }
        throw $e;
    }
}

