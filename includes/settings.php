<?php
// ============================================================
// SETTINGS — CANONICAL SITE SETTINGS & PROFILE HELPER [IMP-034]
// Single source of truth for platform identity, profile data,
// website configuration, branding, and SEO defaults.
//
// Safe & Resilient:
//   - Zero-downtime fallback: if the database is offline or the
//     site_settings table does not exist, returns authentic
//     hard-coded default values.
//   - Request-level static caching prevents redundant queries.
//   - Optional fast JSON file cache (/api/data/settings_cache.json).
// ============================================================

/**
 * Master fallback dictionary. Personal facts (location, current focus, bios) are
 * intentionally empty: they must come from what the owner saved in the admin
 * settings, never from text invented in code.
 */
function _getDefaultSiteSettings(): array {
    return [
        'profile.name' => 'Mohammed Alrashadi',
        'profile.short_name' => 'MA',
        'profile.role' => 'Software Engineering Student',
        'profile.motto' => 'Build. Learn. Experiment. Evolve.',
        'profile.location' => '',
        'profile.current_focus' => '',
        'profile.avatar_url' => '/assets/profile_headshot.png',
        'profile.bio_short' => '',
        'profile.bio_full' => '',
        'profile.education_stage' => 'Software Engineering Student',
        'profile.public_email' => '',
        'profile.show_email' => '0',
        'branding.monogram_url' => '/assets/logo/logo.png',
        'branding.favicon_url' => '/assets/logo/logo.png',
        'branding.og_image_url' => '/assets/logo/logo.png',
        'branding.show_name_in_title' => '0',
        'branding.show_user_in_title' => '1',
        'website.platform_name' => 'Mohammed Alrashadi',
        'website.platform_descriptor' => 'Engineering Studio',
        'website.canonical_url' => 'https://mohammedalrashadi.com/',
        'website.platform_purpose' => 'This platform serves as an open personal engineering studio and research notebook — bringing together hands-on software projects, empirical benchmarks, technical writing, and a transparent learning journey.',
        'seo.default_title' => 'Home',
        'seo.default_description' => 'Systems, databases, computing fundamentals, and backend software engineering projects and research by Mohammed Alrashadi.',
        'showcase.item_ids' => '[]',
        'site.timezone' => 'Asia/Riyadh',
    ];
}

/**
 * Loads all settings into an in-memory dictionary with fail-safe fallbacks.
 *
 * @param  bool $forceRefresh  Set true to bypass in-memory cache
 * @return array<string, string|null> Keyed as "group.key", e.g. "profile.name" or "website.platform_name"
 */
function _loadAllSiteSettings(bool $forceRefresh = false): array {
    static $memoryCache = null;

    if ($memoryCache !== null && !$forceRefresh) {
        return $memoryCache;
    }

    $defaults = _getDefaultSiteSettings();
    $loaded = $defaults;

    // 1. Attempt reading from MariaDB database (site_profile & site_settings)
    $configFile = dirname(__DIR__) . '/api/config.local.php';
    if (file_exists($configFile)) {
        try {
            require_once dirname(__DIR__) . '/api/db.php';
            $pdo = getDB();

            // A. Read site_settings — singleton wide-row table, one row.
            // Phase 3 (docs/DATABASE.md): confirmed via a full production
            // schema export that this table has only ever been the
            // singleton design. The key-value schema this branch used to
            // also check for (`SHOW COLUMNS ... LIKE 'key_group'`, then a
            // key_group/setting_key/value read) was never actually
            // deployed — removed rather than kept as dead tolerance.
            //
            // This also fixes a real bug the old mapping had: it checked
            // for columns named `site_title`, `platform_name`,
            // `default_seo_description`, and `canonical_url` — none of
            // which exist in the real table. The correct names (below)
            // are `site_name`, `site_description`, `site_url`, and
            // `default_meta_description`. Those checks had silently
            // matched nothing in production the whole time.
            try {
                $stmt = $pdo->query("SELECT * FROM site_settings LIMIT 1");
                if ($stmt && ($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
                    foreach ($row as $col => $val) {
                        $loaded['settings.' . $col] = $val;
                    }
                    if (!empty($row['site_name'])) $loaded['website.platform_name'] = $row['site_name'];
                    if (!empty($row['tagline'])) $loaded['website.platform_descriptor'] = $row['tagline'];
                    if (!empty($row['site_url'])) $loaded['website.canonical_url'] = $row['site_url'];
                    if (!empty($row['site_description'])) $loaded['website.platform_purpose'] = $row['site_description'];
                    if (!empty($row['default_seo_title'])) $loaded['seo.default_title'] = $row['default_seo_title'];
                    if (!empty($row['default_meta_description'])) $loaded['seo.default_description'] = $row['default_meta_description'];
                    if (!empty($row['favicon_url'])) $loaded['branding.favicon_url'] = $row['favicon_url'];
                    if (!empty($row['default_og_image_url'])) $loaded['branding.og_image_url'] = $row['default_og_image_url'];
                    if (!empty($row['monogram_url'])) $loaded['branding.monogram_url'] = $row['monogram_url'];
                    if (!empty($row['showcase_item_ids'])) $loaded['showcase.item_ids'] = $row['showcase_item_ids'];
                    if (!empty($row['timezone'])) $loaded['site.timezone'] = $row['timezone'];
                }
            } catch (Throwable $e) {
                // site_settings is confirmed present in production (see
                // docs/DATABASE.md); a failure here is an unexpected
                // query error, not "table missing" or "wrong schema".
                error_log('[settings.php] site_settings read failed: ' . $e->getMessage());
            }

            // B. Check canonical site_profile table (production source of truth)
            try {
                $pRow = $pdo->query("SELECT * FROM site_profile LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                if ($pRow) {
                    if (!empty($pRow['display_name'])) $loaded['profile.name'] = $pRow['display_name'];
                    if (!empty($pRow['short_name'])) $loaded['profile.short_name'] = $pRow['short_name'];
                    if (!empty($pRow['professional_title'])) $loaded['profile.role'] = $pRow['professional_title'];
                    elseif (!empty($pRow['role_title'])) $loaded['profile.role'] = $pRow['role_title'];
                    if (!empty($pRow['motto'])) $loaded['profile.motto'] = $pRow['motto'];
                    if (!empty($pRow['profile_image_url'])) $loaded['profile.avatar_url'] = $pRow['profile_image_url'];
                    if (!empty($pRow['short_bio'])) $loaded['profile.bio_short'] = $pRow['short_bio'];
                    if (!empty($pRow['full_bio'])) $loaded['profile.bio_full'] = $pRow['full_bio'];
                    if (!empty($pRow['professional_focus'])) $loaded['profile.current_focus'] = $pRow['professional_focus'];

                    $loc = trim(($pRow['city'] ?? '') . (!empty($pRow['city']) && !empty($pRow['country']) ? ', ' : '') . ($pRow['country'] ?? ''));
                    if (!empty($loc)) $loaded['profile.location'] = $loc;

                    $edu = trim(($pRow['education_program'] ?? '') . (!empty($pRow['education_program']) && !empty($pRow['education_institution']) ? ' at ' : '') . ($pRow['education_institution'] ?? ''));
                    if (!empty($edu)) $loaded['profile.education_stage'] = $edu;
                    elseif (!empty($pRow['education_status'])) $loaded['profile.education_stage'] = $pRow['education_status'];

                    if (isset($pRow['public_email'])) $loaded['profile.public_email'] = $pRow['public_email'];
                    if (isset($pRow['preferred_contact_method'])) $loaded['profile.preferred_contact_method'] = $pRow['preferred_contact_method'];
                    if (isset($pRow['show_name'])) $loaded['profile.show_name'] = (string)$pRow['show_name'];
                    if (isset($pRow['show_profile_image'])) $loaded['profile.show_profile_image'] = (string)$pRow['show_profile_image'];
                    if (isset($pRow['show_public_email'])) $loaded['profile.show_email'] = (string)$pRow['show_public_email'];
                }
            } catch (Throwable $e) {
                // site_profile is confirmed present in production (see
                // docs/DATABASE.md); a failure here means an unexpected
                // query error or column mismatch, not "table missing".
                error_log('[settings.php] site_profile read failed: ' . $e->getMessage());
            }

            $memoryCache = $loaded;
            return $memoryCache;
        } catch (Throwable $e) {
            // DB unreachable or table error — proceed to fallback checks
            error_log('[settings.php] DB query failed, checking cache/fallbacks: ' . $e->getMessage());
        }
    }

    // 2. Attempt reading from local JSON cache if database was unavailable
    $jsonCacheFile = dirname(__DIR__) . '/api/data/settings_cache.json';
    if (file_exists($jsonCacheFile)) {
        try {
            $cachedContent = file_get_contents($jsonCacheFile);
            if ($cachedContent) {
                $cachedJson = json_decode($cachedContent, true);
                if (is_array($cachedJson)) {
                    foreach ($cachedJson as $k => $v) {
                        if (is_string($k) && (is_string($v) || is_null($v))) {
                            $loaded[$k] = $v;
                        }
                    }
                    $memoryCache = $loaded;
                    return $memoryCache;
                }
            }
        } catch (Throwable $e) {
            error_log('[settings.php] Cache read failed: ' . $e->getMessage());
        }
    }

    // 3. Fallback to hard-coded authentic defaults
    $memoryCache = $loaded;
    return $memoryCache;
}

/**
 * Retrieves a single setting by dot-notation key (e.g. 'profile.name', 'branding.monogram_url').
 *
 * @param  string $key      The composite setting key (e.g. 'profile.role')
 * @param  mixed  $default  Optional override default if setting is unset or empty
 * @return mixed            The setting string, or default/fallback
 */
function getSiteSetting(string $key, mixed $default = null, bool $forceRefresh = false): mixed {
    $settings = _loadAllSiteSettings($forceRefresh);

    if (array_key_exists($key, $settings) && $settings[$key] !== null && $settings[$key] !== '') {
        return $settings[$key];
    }

    if ($default !== null) {
        return $default;
    }

    $defaults = _getDefaultSiteSettings();
    return $defaults[$key] ?? null;
}

/**
 * Retrieves the full canonical Profile object with all standard identity fields.
 *
 * @return array{
 *     name: string,
 *     short_name: string,
 *     role: string,
 *     motto: string,
 *     location: string,
 *     current_focus: string,
 *     avatar_url: string,
 *     bio_short: string,
 *     bio_full: string,
 *     education_stage: string,
 *     public_email: string,
 *     show_email: bool
 * }
 */
function getSiteProfile(bool $forceRefresh = false): array {
    return [
        'name'            => (string) getSiteSetting('profile.name', null, $forceRefresh),
        'short_name'      => (string) getSiteSetting('profile.short_name', null, $forceRefresh),
        'role'            => (string) getSiteSetting('profile.role', null, $forceRefresh),
        'motto'           => (string) getSiteSetting('profile.motto', null, $forceRefresh),
        'location'        => (string) getSiteSetting('profile.location', null, $forceRefresh),
        'current_focus'   => (string) getSiteSetting('profile.current_focus', null, $forceRefresh),
        'avatar_url'      => (string) getSiteSetting('profile.avatar_url', null, $forceRefresh),
        'bio_short'       => (string) getSiteSetting('profile.bio_short', null, $forceRefresh),
        'bio_full'        => (string) getSiteSetting('profile.bio_full', null, $forceRefresh),
        'education_stage' => (string) getSiteSetting('profile.education_stage', null, $forceRefresh),
        'public_email'    => (string) getSiteSetting('profile.public_email', null, $forceRefresh),
        'show_email'      => (bool)(int) getSiteSetting('profile.show_email', '0', $forceRefresh),
    ];
}

/**
 * Returns all settings grouped by group name ('profile', 'website', 'branding', 'seo', 'showcase').
 *
 * @param  bool $publicOnly  If true, filters out non-public settings
 * @return array<string, array<string, mixed>>
 */
function getAllGroupedSettings(bool $publicOnly = false): array {
    $all = _loadAllSiteSettings();
    $grouped = [];

    foreach ($all as $compositeKey => $value) {
        if (!str_contains($compositeKey, '.')) {
            continue;
        }
        [$group, $key] = explode('.', $compositeKey, 2);

        // Hide private settings (like public_email when show_email is 0) if publicOnly is requested
        if ($publicOnly && $group === 'profile' && $key === 'public_email') {
            $showEmail = (bool)(int)($all['profile.show_email'] ?? '0');
            if (!$showEmail) {
                continue;
            }
        }

        if (!isset($grouped[$group])) {
            $grouped[$group] = [];
        }
        $grouped[$group][$key] = $value;
    }

    return $grouped;
}

/**
 * Centralized Title Builder [IMP-036]
 * Follows the pattern: {Page Title} | {Site Name}
 * Homepage uses: {Site Name} | {Descriptor}
 * Truncates long titles to keep the total length under ~60 chars while preserving the site name.
 */
function buildPageTitle(string $pageTitle = '', bool $forSocial = false): string {
    $siteName = getSiteSetting('website.platform_name', 'Mohammed Alrashadi');
    $showName = getSiteSetting('branding.show_name_in_title', '0');
    $showUser = getSiteSetting('branding.show_user_in_title', '1');
    $separator = ' | ';
    
    // Clean input from old formats just in case
    $pageTitle = trim(str_replace(['//', '— Digital Products & Resources', '— Admin', '—'], '', $pageTitle));
    
    // Remove site name from existing page titles (in case it was hardcoded)
    if (str_ends_with($pageTitle, $siteName)) {
        $pageTitle = trim(str_replace($siteName, '', $pageTitle));
        $pageTitle = trim(str_replace('|', '', $pageTitle));
        $pageTitle = trim($pageTitle, '-—'); // remove dashes
        $pageTitle = trim($pageTitle);
    }
    
    $isHome = (empty($pageTitle) || strtolower($pageTitle) === 'home' || str_contains(strtolower($pageTitle), 'software engineering student') || $pageTitle === $siteName);
    
    // Option A for home (Guest base)
    if ($isHome) {
        $descriptor = getSiteSetting('website.platform_descriptor', 'Engineering Studio');
        $baseTitle = $siteName . $separator . $descriptor;
        $guestTitle = 'Home'; // Fallback for signed-in home title
    } else {
        $guestTitle = empty($pageTitle) ? 'Home' : $pageTitle;
        $baseTitle = $guestTitle;
        // Only append site name if branding.show_name_in_title is enabled
        if ($showName === '1' || $showName === 'true') {
            $baseTitle = $guestTitle . $separator . $siteName;
        }
    }
    
    // If generating for social media / search engines, return the base format immediately
    if ($forSocial) {
        return $baseTitle;
    }
    
    // Safety Limit: trim page title to 40 chars maximum for tabs
    if (!$isHome && mb_strlen($guestTitle, 'UTF-8') > 40) {
        $cut = mb_substr($guestTitle, 0, 40, 'UTF-8');
        $lastSpace = mb_strrpos($cut, ' ', 0, 'UTF-8');
        if ($lastSpace !== false) {
            $guestTitle = rtrim(mb_substr($cut, 0, $lastSpace, 'UTF-8')) . '…';
        } else {
            $guestTitle = $cut . '…';
        }
    }
    
    // Check if user personalization is enabled and user is logged in
    if (($showUser === '1' || $showUser === 'true') && function_exists('currentUser')) {
        $user = currentUser();
        if ($user && (!empty($user['display_name']) || !empty($user['name']))) {
            // Get first name
            $nameField = !empty($user['display_name']) ? $user['display_name'] : $user['name'];
            $firstName = explode(' ', trim($nameField))[0];
            // Fix capitalization rules (Normal capitalization as requested)
            $firstName = mb_convert_case($firstName, MB_CASE_TITLE, 'UTF-8');
            if (mb_strlen($firstName, 'UTF-8') > 20) {
                $firstName = mb_substr($firstName, 0, 19, 'UTF-8') . '…';
            }
            
            // Escape the name as explicitly required by security policy
            $firstName = htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8');
            
            // Format for logged in user: Page Name | FirstName
            return $guestTitle . $separator . $firstName;
        }
    }
    
    // Return final format for guests
    if (!$isHome && ($showName === '1' || $showName === 'true')) {
        return $guestTitle . $separator . $siteName;
    }
    
    return $isHome ? $baseTitle : $guestTitle;
}

/**
 * Format a date string or timestamp according to the site's timezone.
 * Uses site.timezone (default Asia/Riyadh).
 * 
 * @param mixed $dateInput A date string, DateTime object, or Unix timestamp
 * @param string $format PHP date format string (default: 'M j, Y')
 * @return string
 */
if (!function_exists('formatSiteDate')) {
    function formatSiteDate($dateInput, string $format = 'M j, Y'): string {
        if (empty($dateInput)) {
            return '';
        }
        
        try {
            $tz = new DateTimeZone(getSiteSetting('site.timezone', 'Asia/Riyadh'));
            if ($dateInput instanceof DateTimeInterface) {
                $dt = clone $dateInput;
            } elseif (is_numeric($dateInput)) {
                $dt = new DateTime('@' . $dateInput);
            } else {
                $dt = new DateTime($dateInput);
            }
            $dt->setTimezone($tz);
            return $dt->format($format);
        } catch (Exception $e) {
            return is_string($dateInput) ? $dateInput : ''; // fallback
        }
    }
}

