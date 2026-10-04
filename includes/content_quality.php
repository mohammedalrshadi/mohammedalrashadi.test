<?php

/**
 * Checks if a string appears to be placeholder or garbage content.
 * Looks for minimum length, common test strings, and repeated characters.
 *
 * @param string $value     The text to check.
 * @param int    $minLength Minimum acceptable character count (default 10).
 *                          Pass a higher value (e.g. 20) for fields like
 *                          short_description where very brief text is still
 *                          legitimately meaningful and should not be flagged.
 * @return bool True if it looks like a placeholder, false otherwise.
 */
function isPlaceholderContent(string $value, int $minLength = 10): bool {
    $val = strtolower(trim(strip_tags($value)));

    // Check length against the field-specific minimum
    if (mb_strlen($val) < $minLength) {
        return true;
    }

    // Check common test strings — only flag when the whole value is suspiciously short
    $testStrings = ['test', 'asdf', 'qwer', '1234', 'placeholder'];
    foreach ($testStrings as $ts) {
        if (strpos($val, $ts) !== false && mb_strlen($val) < 20) {
            return true;
        }
    }

    // Repeated characters (5 or more identical characters in a row)
    if (preg_match('/(.)\\1{4,}/', $val)) {
        return true;
    }

    return false;
}
