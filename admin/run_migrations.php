<?php
// ============================================================
// ADMIN — DATABASE MIGRATION RUNNER
// ============================================================
// ROOT CAUSE THIS FILE FIXES (Achievement Gallery investigation):
//
//   Every database/migration_*.sql file in this project is
//   documented as "HOW TO RUN — MANUAL EXECUTION REQUIRED":
//   log into Hostinger phpMyAdmin, paste the SQL by hand, click
//   Go. Hostinger shared hosting gives no SSH/CLI access (see
//   HOSTINGER_SETUP.md), so this copy-paste ritual has been the
//   only way to apply schema changes.
//
//   The REQ-006 achievement gallery feature shipped its API code
//   and admin UI, but database/migration_v5_achievement_images.sql
//   was never actually pasted into phpMyAdmin. Every gallery
//   upload (api/achievement_images/add.php) and every gallery
//   read (api/achievement_images/list.php) has therefore been
//   failing with "Base table or view not found:
//   achievement_images" — caught, logged, and turned into a
//   generic error response. On the admin side this surfaces as
//   an easy-to-miss toast. On the public site, list.php's
//   "fail gracefully, no gallery, no JS error" design (see
//   post.js) means a visitor sees nothing at all: no gallery,
//   no error — exactly the reported symptom, "images uploaded
//   never appear."
//
//   Confirmed by reproducing the full request against a real
//   MySQL/MariaDB instance built from schema.sql + every OTHER
//   migration applied (so deleted_at etc. all exist) with only
//   migration_v5_achievement_images.sql withheld: the exact same
//   500 + "Base table or view not found" error reproduced. After
//   applying that one migration, the identical request sequence
//   (create achievement → upload gallery image → public list)
//   succeeded end-to-end.
//
// WHAT THIS PAGE DOES
//   Runs each pending migration using the app's OWN PDO
//   connection (api/config.local.php) — no separate phpMyAdmin
//   login, no manual copy-pasting, no risk of transcription
//   errors. Every check is idempotent (safe to run any number of
//   times): each migration is skipped if already applied.
//
//   This directly removes the failure mode that caused this bug:
//   a schema change that only exists as a a .sql file and a
//   human's memory. Future schema changes should be added to the
//   $MIGRATIONS list below instead of a new "manual execution
//   required" .sql file.
//
// ACCESS
//   Admin-authenticated page (requireAdminPage()) — same guard
//   used by every other admin page. Visit this URL once after
//   deploying and whenever a new migration is added below.
// ============================================================

require_once __DIR__ . '/../api/auth/guard.php';
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/db.php';

if (php_sapi_name() !== 'cli') {
    requireAdminPage('login.php');
}

try {
    $pdo = getDB();
} catch (PDOException $e) {
    error_log('[run_migrations] DB error: ' . $e->getMessage());
    http_response_code(500);
    die('Database connection error. Please try again later.');
}

// ---- Helpers --------------------------------------------------------

function tableExists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare(
        "SELECT 1 FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
          LIMIT 1"
    );
    $stmt->execute([$table]);
    return (bool) $stmt->fetch();
}

function columnExists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare(
        "SELECT 1 FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
          LIMIT 1"
    );
    $stmt->execute([$table, $column]);
    return (bool) $stmt->fetch();
}

function indexExists(PDO $pdo, string $table, string $index): bool {
    $stmt = $pdo->prepare(
        "SELECT 1 FROM information_schema.STATISTICS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?
          LIMIT 1"
    );
    $stmt->execute([$table, $index]);
    return (bool) $stmt->fetch();
}

function foreignKeyExists(PDO $pdo, string $table, string $fk): bool {
    $stmt = $pdo->prepare(
        "SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'
          LIMIT 1"
    );
    $stmt->execute([$table, $fk]);
    return (bool) $stmt->fetch();
}

// ---- Migration definitions --------------------------------------------
// Each entry: id, human label, check() -> bool (true = already applied),
// apply() -> void. Add future schema changes here instead of a new
// "manual execution required" .sql file.

$MIGRATIONS = [

    [
        // Phase 3 (docs/DATABASE.md): every OTHER migration in this file
        // is idempotent via information_schema introspection (check()),
        // which works but leaves no record of WHEN a migration ran or
        // WHO ran it — exactly the "migration state is inferred by
        // introspection, not recorded" gap the engineering audit (C-2)
        // called out. This table doesn't replace that check() pattern
        // (see the note in the POST handler above about not silently
        // converting the migration system into a different framework)
        // — it's an audit trail alongside it. Once this exists, every
        // migration actually applied via a POST to this page also gets
        // a row here (see the POST handler above).
        'id'    => 'schema_migrations_ledger',
        'label' => 'Phase 3 — schema_migrations ledger (audit trail for this page)',
        'check' => fn(PDO $pdo) => tableExists($pdo, 'schema_migrations'),
        'apply' => function (PDO $pdo) {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS `schema_migrations` (
                    `id`          VARCHAR(100)  NOT NULL,
                    `applied_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `applied_by`  INT UNSIGNED  NULL,
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        },
    ],

    [
        'id'    => 'v5_soft_delete',
        'label' => 'REQ-005 — posts.deleted_at (soft delete)',
        'check' => fn(PDO $pdo) => columnExists($pdo, 'posts', 'deleted_at'),
        'apply' => function (PDO $pdo) {
            $pdo->exec(
                "ALTER TABLE `posts`
                    ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL
                    AFTER `updated_at`"
            );
            $pdo->exec(
                "ALTER TABLE `posts` ADD KEY `idx_posts_deleted_at` (`deleted_at`)"
            );
        },
    ],

    [
        'id'    => 'v5_achievement_images',
        'label' => 'REQ-006 — achievement_images table (achievement gallery)',
        'check' => fn(PDO $pdo) => tableExists($pdo, 'achievement_images'),
        'apply' => function (PDO $pdo) {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS `achievement_images` (
                    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
                    `post_id`    INT UNSIGNED  NOT NULL,
                    `image_url`  VARCHAR(1000) NOT NULL,
                    `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_ai_post_id` (`post_id`),
                    CONSTRAINT `fk_ai_post`
                        FOREIGN KEY (`post_id`)
                        REFERENCES `posts` (`id`)
                        ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        },
    ],

    [
        'id'    => 'v5_social_links',
        'label' => 'IMP-027 — social_links table & seeded platforms',
        'check' => fn(PDO $pdo) => tableExists($pdo, 'social_links'),
        'apply' => function (PDO $pdo) {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS `social_links` (
                    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `platform`   VARCHAR(50)  NOT NULL,
                    `name`       VARCHAR(100) NOT NULL,
                    `url`        VARCHAR(500) NOT NULL DEFAULT '',
                    `is_enabled` TINYINT(1)   NOT NULL DEFAULT 0,
                    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
                    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_social_platform` (`platform`),
                    KEY `idx_social_enabled_sort` (`is_enabled`, `sort_order`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            $pdo->exec(
                "INSERT IGNORE INTO `social_links` (`platform`, `name`, `url`, `is_enabled`, `sort_order`) VALUES
                ('x', 'X (تويتر)', '', 0, 1),
                ('tiktok', 'تيك توك', '', 0, 2),
                ('linkedin', 'لينكد إن', '', 0, 3),
                ('instagram', 'إنستغرام', '', 0, 4),
                ('facebook', 'فيسبوك', '', 0, 5)"
            );
        },
    ],

    [
        'id'    => 'multi_user',
        'label' => 'SEC-01 — users.name and updated_at columns',
        'check' => fn(PDO $pdo) => columnExists($pdo, 'users', 'name'),
        'apply' => function (PDO $pdo) {
            $pdo->exec(
                "ALTER TABLE `users`
                    ADD COLUMN `name` VARCHAR(255) NOT NULL DEFAULT '' AFTER `id`"
            );
            $pdo->exec(
                "ALTER TABLE `users`
                    ADD COLUMN `updated_at` TIMESTAMP NOT NULL
                        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                        AFTER `created_at`"
            );
            $pdo->exec(
                "UPDATE `users`
                 SET `name` = SUBSTRING_INDEX(`email`, '@', 1)
                 WHERE `name` = ''"
            );
        },
    ],

    [
        'id'    => 'v5_req012_draft_status',
        'label' => 'REQ-012 — posts.status draft workflow',
        'check' => function (PDO $pdo): bool {
            $stmt = $pdo->prepare(
                "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'posts' AND COLUMN_NAME = 'status'
                 LIMIT 1"
            );
            $stmt->execute();
            $colType = (string) $stmt->fetchColumn();
            return str_contains($colType, "'draft'");
        },
        'apply' => function (PDO $pdo) {
            $pdo->exec(
                "ALTER TABLE `posts`
                    MODIFY COLUMN `status` ENUM('published', 'hidden', 'draft') NOT NULL DEFAULT 'draft'"
            );
        },
    ],

    [
        'id'    => 'req001_article_quote',
        'label' => 'REQ-001 — posts.quote_ar and quote_en columns',
        'check' => fn(PDO $pdo) => columnExists($pdo, 'posts', 'quote_ar'),
        'apply' => function (PDO $pdo) {
            $pdo->exec(
                "ALTER TABLE `posts`
                    ADD COLUMN `quote_ar` TEXT NULL DEFAULT NULL AFTER `image_url`,
                    ADD COLUMN `quote_en` TEXT NULL DEFAULT NULL AFTER `quote_ar`"
            );
        },
    ],

    [
        'id'    => 'v5_req015_categories_table',
        'label' => 'REQ-015 — categories table & initial seed',
        'check' => fn(PDO $pdo) => tableExists($pdo, 'categories'),
        'apply' => function (PDO $pdo) {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS `categories` (
                    `id`         INT UNSIGNED                  NOT NULL AUTO_INCREMENT,
                    `name`       VARCHAR(255)                  NOT NULL,
                    `type`       ENUM('blog', 'achievement')   NOT NULL,
                    `created_at` DATETIME                      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME                      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `unique_type_name` (`type`, `name`),
                    KEY `idx_categories_type` (`type`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            $pdo->exec(
                "INSERT IGNORE INTO `categories` (`name`, `type`, `created_at`, `updated_at`)
                 SELECT DISTINCT TRIM(`category`), `type`, NOW(), NOW()
                 FROM `posts`
                 WHERE `category` IS NOT NULL AND TRIM(`category`) <> ''"
            );
        },
    ],

    [
        'id'    => 'v5_req015_indexes',
        'label' => 'REQ-015 — category and composite filtering indexes on posts',
        'check' => fn(PDO $pdo) => indexExists($pdo, 'posts', 'idx_posts_category'),
        'apply' => function (PDO $pdo) {
            $pdo->exec("ALTER TABLE `posts` ADD INDEX `idx_posts_category` (`category`)");
            $pdo->exec("ALTER TABLE `posts` ADD INDEX `idx_posts_type_status_cat` (`type`, `status`, `deleted_at`, `created_at`)");
        },
    ],

    [
        'id'    => 'imp033_analytics',
        'label' => 'IMP-033 — Analytics telemetry tables & posts.views',
        'check' => fn(PDO $pdo) => tableExists($pdo, 'site_visitors'),
        'apply' => function (PDO $pdo) {
            if (!columnExists($pdo, 'posts', 'views')) {
                $pdo->exec("ALTER TABLE `posts` ADD COLUMN `views` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `status`");
            }
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS `site_visitors` (
                    `visitor_hash` CHAR(64) NOT NULL,
                    `first_seen_date` DATE NOT NULL,
                    `last_seen_date` DATE NOT NULL,
                    PRIMARY KEY (`visitor_hash`),
                    KEY `idx_first_seen` (`first_seen_date`),
                    KEY `idx_last_seen` (`last_seen_date`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS `daily_site_stats` (
                    `stat_date` DATE NOT NULL,
                    `visitors_count` INT UNSIGNED NOT NULL DEFAULT 0,
                    `reads_count` INT UNSIGNED NOT NULL DEFAULT 0,
                    PRIMARY KEY (`stat_date`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS `daily_article_stats` (
                    `stat_date` DATE NOT NULL,
                    `post_id` INT UNSIGNED NOT NULL,
                    `views_count` INT UNSIGNED NOT NULL DEFAULT 0,
                    PRIMARY KEY (`stat_date`, `post_id`),
                    KEY `idx_post_date` (`post_id`, `stat_date`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS `telemetry_dedup_visitors` (
                    `visitor_hash` CHAR(64) NOT NULL,
                    `visit_date` DATE NOT NULL,
                    PRIMARY KEY (`visitor_hash`, `visit_date`),
                    KEY `idx_visit_date` (`visit_date`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS `telemetry_dedup_articles` (
                    `visitor_hash` CHAR(64) NOT NULL,
                    `post_id` INT UNSIGNED NOT NULL,
                    `view_date` DATE NOT NULL,
                    PRIMARY KEY (`visitor_hash`, `post_id`, `view_date`),
                    KEY `idx_view_date` (`view_date`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        },
    ],

    [
        'id'    => 'v5_reviews_fk',
        'label' => 'REQ-010 — reviews.post_id foreign key constraint (ON DELETE SET NULL)',
        'check' => fn(PDO $pdo) => foreignKeyExists($pdo, 'reviews', 'fk_reviews_post'),
        'apply' => function (PDO $pdo) {
            if (!tableExists($pdo, 'reviews')) {
                throw new Exception("The 'reviews' table is missing. You must import database/schema.sql before running migrations.");
            }
            $pdo->exec(
                "UPDATE `reviews`
                 SET `post_id` = NULL
                 WHERE `post_id` IS NOT NULL
                   AND `post_id` NOT IN (SELECT `id` FROM `posts`)"
            );
            $pdo->exec(
                "ALTER TABLE `reviews`
                    ADD CONSTRAINT `fk_reviews_post`
                        FOREIGN KEY (`post_id`)
                        REFERENCES `posts` (`id`)
                        ON DELETE SET NULL"
            );
        },
    ],

    [
        'id'    => 'v5_site_settings',
        'label' => 'IMP-034 — site_settings table & canonical seed (Control Center)',
        'check' => fn(PDO $pdo) => tableExists($pdo, 'site_settings'),
        'apply' => function (PDO $pdo) {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS `site_settings` (
                    `key_group`   VARCHAR(50)   NOT NULL,
                    `setting_key` VARCHAR(100)  NOT NULL,
                    `value`       LONGTEXT      NULL,
                    `is_public`   TINYINT(1)    NOT NULL DEFAULT 1,
                    `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`key_group`, `setting_key`),
                    KEY `idx_settings_public` (`is_public`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            $pdo->exec(
                "INSERT IGNORE INTO `site_settings` (`key_group`, `setting_key`, `value`, `is_public`) VALUES
                ('profile', 'name', 'Mohammed Alrashadi', 1),
                ('profile', 'short_name', 'MA', 1),
                ('profile', 'role', 'Software Engineering Student', 1),
                ('profile', 'motto', 'Build. Learn. Experiment. Evolve.', 1),
                ('profile', 'location', 'Riyadh, Saudi Arabia', 1),
                ('profile', 'current_focus', 'Systems, Databases & Backend', 1),
                ('profile', 'avatar_url', '/assets/profile_headshot.png', 1),
                ('profile', 'bio_short', 'A personal engineering platform and research notebook focused on systems, databases, computing fundamentals, and backend architecture.', 1),
                ('profile', 'bio_full', 'I am a software engineering student driven by curiosity for how computer systems and software architectures behave under real-world conditions. Rather than treating software merely as a collection of third-party frameworks, I focus on understanding fundamentals, storage internals, backend mechanics, and concurrency.\n\nMy approach is grounded in the principle that great software is simple at the surface, but deeply engineered underneath. I build practical systems, conduct controlled benchmarks, and write technical notes to document architectural trade-offs.', 1),
                ('profile', 'education_stage', 'Software Engineering Student', 1),
                ('profile', 'public_email', '', 0),
                ('profile', 'show_email', '0', 1),
                ('branding', 'monogram_url', '/assets/logo/logo.png', 1),
                ('branding', 'favicon_url', '/assets/logo/logo.png', 1),
                ('branding', 'og_image_url', '/assets/logo/logo.png', 1),
                ('website', 'platform_name', 'Mohammed Alrashadi', 1),
                ('website', 'platform_descriptor', 'Engineering Studio', 1),
                ('website', 'canonical_url', 'https://mohammedalrashadi.com/', 1),
                ('website', 'platform_purpose', 'This platform serves as an open personal engineering studio and research notebook — bringing together hands-on software projects, empirical benchmarks, technical writing, and a transparent learning journey.', 1),
                ('seo', 'default_title', 'Mohammed Alrashadi // Systems & Software Engineering', 1),
                ('seo', 'default_description', 'Systems, databases, computing fundamentals, and backend software engineering projects and research by Mohammed Alrashadi.', 1),
                ('showcase', 'item_ids', '[]', 1)"
            );
        },
    ],

    [
        'id'    => 'journey_milestones_table',
        'label' => 'Journey — journey_milestones table (admin-managed timeline)',
        'check' => fn(PDO $pdo) => tableExists($pdo, 'journey_milestones'),
        'apply' => function (PDO $pdo) {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS `journey_milestones` (
                    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
                    `title`       VARCHAR(200)  NOT NULL,
                    `period_label` VARCHAR(80)  NOT NULL,
                    `description` TEXT          NOT NULL,
                    `category`    VARCHAR(60)   NOT NULL DEFAULT 'milestone',
                    `icon`        VARCHAR(60)   NOT NULL DEFAULT 'timeline',
                    `sort_order`  INT           NOT NULL DEFAULT 0,
                    `status`      ENUM('draft','published') NOT NULL DEFAULT 'draft',
                    `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    `deleted_at`  DATETIME      NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    KEY `idx_journey_status` (`status`),
                    KEY `idx_journey_deleted_at` (`deleted_at`),
                    KEY `idx_journey_sort` (`sort_order`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        },
    ],

    [
        'id'    => 'about_content_blocks_table',
        'label' => 'About — about_content_blocks table (Principles + Focus Area tags)',
        'check' => fn(PDO $pdo) => tableExists($pdo, 'about_content_blocks'),
        'apply' => function (PDO $pdo) {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS `about_content_blocks` (
                    `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
                    `block_type`    ENUM('principle','focus_tag') NOT NULL,
                    `group_label`   VARCHAR(100)  NULL,
                    `icon`          VARCHAR(60)   NULL,
                    `title`         VARCHAR(150)  NOT NULL,
                    `description`   TEXT          NULL,
                    `sort_order`    INT           NOT NULL DEFAULT 0,
                    `created_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    `deleted_at`    DATETIME      NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    KEY `idx_about_block_type` (`block_type`),
                    KEY `idx_about_deleted_at` (`deleted_at`),
                    KEY `idx_about_sort` (`sort_order`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            // Seed with the site's existing hardcoded content so nothing
            // visually changes on the public About page until the admin
            // deliberately edits or reorders these rows.
            $pdo->exec(
                "INSERT IGNORE INTO `about_content_blocks`
                    (`id`, `block_type`, `group_label`, `icon`, `title`, `description`, `sort_order`) VALUES
                    (1, 'principle', NULL, 'shield', 'Resilience First', 'Design software assuming that network splits, process crashes, and retries are inevitable. Systems should degrade gracefully under stress.', 1),
                    (2, 'principle', NULL, 'analytics', 'Empirical Rigor', 'Measure rather than assume. Validate architectural decisions through reproducible benchmarks, profiling, and controlled test environments.', 2),
                    (3, 'principle', NULL, 'layers', 'Deep Clarity', 'Keep surfaces clean and intuitive, while giving technical collaborators full visibility into underlying mechanisms and trade-offs.', 3),
                    (4, 'focus_tag', 'Languages & Core', NULL, 'Go', NULL, 1),
                    (5, 'focus_tag', 'Languages & Core', NULL, 'C++', NULL, 2),
                    (6, 'focus_tag', 'Languages & Core', NULL, 'SQL', NULL, 3),
                    (7, 'focus_tag', 'Languages & Core', NULL, 'Python', NULL, 4),
                    (8, 'focus_tag', 'Languages & Core', NULL, 'PHP', NULL, 5),
                    (9, 'focus_tag', 'Languages & Core', NULL, 'JavaScript', NULL, 6),
                    (10, 'focus_tag', 'Databases & Storage', NULL, 'MySQL / InnoDB', NULL, 1),
                    (11, 'focus_tag', 'Databases & Storage', NULL, 'B-Tree Indexing', NULL, 2),
                    (12, 'focus_tag', 'Databases & Storage', NULL, 'Hash Indexes', NULL, 3),
                    (13, 'focus_tag', 'Databases & Storage', NULL, 'Storage Engines', NULL, 4),
                    (14, 'focus_tag', 'Databases & Storage', NULL, 'Key-Value Lookups', NULL, 5),
                    (15, 'focus_tag', 'Systems & Architecture', NULL, 'Distributed Systems', NULL, 1),
                    (16, 'focus_tag', 'Systems & Architecture', NULL, 'Database Internals', NULL, 2),
                    (17, 'focus_tag', 'Systems & Architecture', NULL, 'Concurrency & Runtimes', NULL, 3),
                    (18, 'focus_tag', 'Systems & Architecture', NULL, 'Systems Architecture', NULL, 4),
                    (19, 'focus_tag', 'Systems & Architecture', NULL, 'Empirical Benchmarks', NULL, 5)"
            );
        },
    ],

    [
        'id'    => 'v6_social_links_enhancements',
        'label' => 'IMP-035 — social_links.icon_key, GitHub platform & clean defaults',
        'check' => fn(PDO $pdo) => columnExists($pdo, 'social_links', 'icon_key'),
        'apply' => function (PDO $pdo) {
            $pdo->exec(
                "ALTER TABLE `social_links`
                    ADD COLUMN `icon_key` VARCHAR(50) NULL DEFAULT NULL AFTER `name`"
            );
            $pdo->exec(
                "INSERT IGNORE INTO `social_links` (`platform`, `name`, `url`, `is_enabled`, `sort_order`, `icon_key`)
                 VALUES ('github', 'GitHub', '', 0, 0, 'github')"
            );
            $pdo->exec(
                "UPDATE `social_links`
                 SET `icon_key` = `platform`
                 WHERE `icon_key` IS NULL OR `icon_key` = ''"
            );
            $pdo->exec(
                "UPDATE `social_links` SET `name` = 'LinkedIn'  WHERE `platform` = 'linkedin'  AND `name` = 'لينكد إن'"
            );
            $pdo->exec(
                "UPDATE `social_links` SET `name` = 'X'         WHERE `platform` = 'x'         AND `name` = 'X (تويتر)'"
            );
            $pdo->exec(
                "UPDATE `social_links` SET `name` = 'Instagram' WHERE `platform` = 'instagram' AND `name` = 'إنستغرام'"
            );
            $pdo->exec(
                "UPDATE `social_links` SET `name` = 'TikTok'    WHERE `platform` = 'tiktok'    AND `name` = 'تيك توك'"
            );
            $pdo->exec(
                "UPDATE `social_links` SET `name` = 'Facebook'  WHERE `platform` = 'facebook'  AND `name` = 'فيسبوك'"
            );
        },
    ],

    // ── Store MVP: products table ────────────────────────────
    [
        'id'    => 'store_products_table',
        'label' => 'Store MVP — products catalog table',
        'check' => fn(PDO $pdo) => tableExists($pdo, 'products'),
        'apply' => function (PDO $pdo) {
            $sql = file_get_contents(
                dirname(__DIR__) . '/database/migration_store_products.sql'
            );
            if ($sql === false) {
                throw new RuntimeException(
                    'Cannot read database/migration_store_products.sql'
                );
            }
            $pdo->exec($sql);
        },
    ],

    // ── Store: Product Images Gallery ────────────────────────
    [
        'id'    => 'store_product_images',
        'label' => 'Store — Product images gallery table (product_images)',
        'check' => fn(PDO $pdo) => tableExists($pdo, 'product_images'),
        'apply' => function (PDO $pdo) {
            $sql = file_get_contents(
                dirname(__DIR__) . '/database/migration_product_images.sql'
            );
            if ($sql === false) {
                throw new RuntimeException(
                    'Cannot read database/migration_product_images.sql'
                );
            }
            $pdo->exec($sql);
        },
    ],

    // ── Store: Product Resources ─────────────────────────────
    [
        'id'    => 'store_product_resources',
        'label' => 'Store — Product resources table (product_resources)',
        'check' => fn(PDO $pdo) => tableExists($pdo, 'product_resources'),
        'apply' => function (PDO $pdo) {
            $sql = file_get_contents(
                dirname(__DIR__) . '/database/migration_product_resources.sql'
            );
            if ($sql === false) {
                throw new RuntimeException(
                    'Cannot read database/migration_product_resources.sql'
                );
            }
            $pdo->exec($sql);
        },
    ],


    // ── Home Showcase: mixed content evolution ───────────────
    [
        'id'    => 'home_showcase_mixed_content',
        'label' => 'Home Showcase — mixed content support (product, image, project, writing)',
        'check' => fn(PDO $pdo) => columnExists($pdo, 'home_showcase_items', 'reference_id'),
        'apply' => function (PDO $pdo) {
            $sql = file_get_contents(
                dirname(__DIR__) . '/database/migration_home_showcase_mixed_content.sql'
            );
            if ($sql === false) {
                throw new RuntimeException(
                    'Cannot read database/migration_home_showcase_mixed_content.sql'
                );
            }
            $pdo->exec($sql);
        },
    ],

    // ── User Platform: personal dashboard & content interaction tables ───
    [
        'id'    => 'user_platform',
        'label' => 'User Platform — dashboard, profiles, bookmarks, likes, history, library, downloads, and activity',
        'check' => fn(PDO $pdo) => tableExists($pdo, 'bookmarks'),
        'apply' => function (PDO $pdo) {
            $sql = file_get_contents(
                dirname(__DIR__) . '/database/migration_user_platform.sql'
            );
            if ($sql === false) {
                throw new RuntimeException(
                    'Cannot read database/migration_user_platform.sql'
                );
            }
            $pdo->exec($sql);
        },
    ],

    // ── Phase P0: Immutable Administrative Mutation Audit Trail ───────────
    [
        'id'    => 'admin_audit_log',
        'label' => 'Phase P0 — admin_audit_log table for immutable administrative mutation audit trail',
        'check' => fn(PDO $pdo) => tableExists($pdo, 'admin_audit_log'),
        'apply' => function (PDO $pdo) {
            $sql = file_get_contents(
                dirname(__DIR__) . '/database/migration_admin_audit_log.sql'
            );
            if ($sql === false) {
                throw new RuntimeException(
                    'Cannot read database/migration_admin_audit_log.sql'
                );
            }
            $pdo->exec($sql);
        },
    ],

    // ── Phase P3: Account Lifecycle (Users Status & Last Login) ───────────
    [
        'id'    => 'user_status_and_login',
        'label' => 'Phase P3 — users.status ENUM and users.last_login_at tracking',
        'check' => fn(PDO $pdo) => columnExists($pdo, 'users', 'status') && columnExists($pdo, 'users', 'last_login_at'),
        'apply' => function (PDO $pdo) {
            $sql = file_get_contents(dirname(__DIR__) . '/database/migration_user_status_and_login.sql');
            if ($sql === false) {
                throw new RuntimeException('Cannot read database/migration_user_status_and_login.sql');
            }
            $pdo->exec($sql);
        },
    ],

    // ── Phase P3: Empirical Research Labs Relational Table ─────────────────
    [
        'id'    => 'lab_experiments_table',
        'label' => 'Phase P3 — lab_experiments relational table for empirical benchmarks and experiments',
        'check' => fn(PDO $pdo) => tableExists($pdo, 'lab_experiments'),
        'apply' => function (PDO $pdo) {
            $sql = file_get_contents(dirname(__DIR__) . '/database/migration_labs_database_table.sql');
            if ($sql === false) {
                throw new RuntimeException('Cannot read database/migration_labs_database_table.sql');
            }
            $pdo->exec($sql);
        },
    ],

    // ── Phase P3: Digital Asset Registry ──────────────────────────────────
    [
        'id'    => 'media_assets_table',
        'label' => 'Phase P3 — media_assets table for uploaded digital asset registry and metadata',
        'check' => fn(PDO $pdo) => tableExists($pdo, 'media_assets'),
        'apply' => function (PDO $pdo) {
            $sql = file_get_contents(dirname(__DIR__) . '/database/migration_media_assets.sql');
            if ($sql === false) {
                throw new RuntimeException('Cannot read database/migration_media_assets.sql');
            }
            $pdo->exec($sql);
        },
    ],

    // ── Image Optimization: Media Assets Dimensions ───────────────────────
    [
        'id'    => 'media_assets_dimensions',
        'label' => 'Image Optimization — media_assets.width and media_assets.height dimension columns',
        'check' => fn(PDO $pdo) => columnExists($pdo, 'media_assets', 'width') && columnExists($pdo, 'media_assets', 'height'),
        'apply' => function (PDO $pdo) {
            $sql = file_get_contents(dirname(__DIR__) . '/database/migration_media_assets_dimensions.sql');
            if ($sql === false) {
                throw new RuntimeException('Cannot read database/migration_media_assets_dimensions.sql');
            }
            $pdo->exec($sql);
        },
    ],

    // ── Phase P3: Content SEO Metadata & Slugs ────────────────────────────
    [
        'id'    => 'posts_seo_metadata',
        'label' => 'Phase P3 — posts.slug, posts.meta_title, and posts.meta_description SEO columns',
        'check' => fn(PDO $pdo) => columnExists($pdo, 'posts', 'slug') && columnExists($pdo, 'posts', 'meta_title'),
        'apply' => function (PDO $pdo) {
            $sql = file_get_contents(dirname(__DIR__) . '/database/migration_posts_seo_metadata.sql');
            if ($sql === false) {
                throw new RuntimeException('Cannot read database/migration_posts_seo_metadata.sql');
            }
            $pdo->exec($sql);
        },
    ],

    // ── Phase 2a: Password Recovery & Session Security ────────────────────
    [
        'id'    => 'password_resets',
        'label' => 'Phase 2a — password_resets table and users.password_changed_at for session invalidation',
        'check' => fn(PDO $pdo) => tableExists($pdo, 'password_resets') && columnExists($pdo, 'users', 'password_changed_at'),
        'apply' => function (PDO $pdo) {
            $sql = file_get_contents(dirname(__DIR__) . '/database/migration_password_resets.sql');
            if ($sql === false) {
                throw new RuntimeException('Cannot read database/migration_password_resets.sql');
            }
            $pdo->exec($sql);
        },
    ],

    // ── Email Verification System ─────────────────────────────────────────
    [
        'id'    => 'email_verification',
        'label' => 'Email verification system (email_verifications table, users.email_verified_at column, grandfathering)',
        'check' => fn(PDO $pdo) => tableExists($pdo, 'email_verifications') && columnExists($pdo, 'users', 'email_verified_at'),
        'apply' => function (PDO $pdo) {
            // Guarded Step 1: Add email_verified_at and grandfather only if newly added
            if (!columnExists($pdo, 'users', 'email_verified_at')) {
                $pdo->exec("ALTER TABLE `users` ADD COLUMN `email_verified_at` DATETIME NULL DEFAULT NULL AFTER `status`");
                // Grandfather existing users ONLY in this initial run
                $pdo->exec("UPDATE `users` SET `email_verified_at` = `created_at` WHERE `email_verified_at` IS NULL");
            }

            // Guarded Step 2: Create email_verifications table
            if (!tableExists($pdo, 'email_verifications')) {
                $pdo->exec(
                    "CREATE TABLE IF NOT EXISTS `email_verifications` (
                      `id`           INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                      `user_id`      INT(10) UNSIGNED NOT NULL,
                      `token_hash`   CHAR(64)         NOT NULL,
                      `expires_at`   DATETIME         NOT NULL,
                      `used_at`      DATETIME         NULL DEFAULT NULL,
                      `requested_ip` VARCHAR(45)      NOT NULL DEFAULT '',
                      `created_at`   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
                      PRIMARY KEY (`id`),
                      KEY `idx_ev_user_id` (`user_id`),
                      KEY `idx_ev_token_hash` (`token_hash`),
                      KEY `idx_ev_expires_at` (`expires_at`),
                      CONSTRAINT `fk_ev_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
                );
            }
        },
    ],

    // ── Phase 5d: Customer Support Message System ─────────────────────────
    [
        'id'    => 'support_messages',
        'label' => 'Phase 5d — support_messages table for customer inquiries and feedback',
        'check' => fn(PDO $pdo) => tableExists($pdo, 'support_messages'),
        'apply' => function (PDO $pdo) {
            $sql = file_get_contents(dirname(__DIR__) . '/database/migration_support_messages.sql');
            if ($sql === false) {
                throw new RuntimeException('Cannot read database/migration_support_messages.sql');
            }
            $pdo->exec($sql);
        },
    ],

    // ── Phase 6: Extended Categories (Labs & Products) ───────────────────
    [
        'id'    => 'categories_extended',
        'label' => 'Phase 6 — categories.type extended to lab/product and categories.link_group column',
        'check' => fn(PDO $pdo) => tableExists($pdo, 'categories') && columnExists($pdo, 'categories', 'link_group'),
        'apply' => function (PDO $pdo) {
            $sql = file_get_contents(dirname(__DIR__) . '/database/migration_categories_extended.sql');
            if ($sql === false) {
                throw new RuntimeException('Cannot read database/migration_categories_extended.sql');
            }
            $pdo->exec($sql);
        },
    ],

    // ── Admin Notification Center: admin_alerts table ────────────────────
    [
        'id'    => 'admin_alerts_table',
        'label' => 'Admin Studio — admin_alerts table for notification center',
        'check' => fn(PDO $pdo) => tableExists($pdo, 'admin_alerts'),
        'apply' => function (PDO $pdo) {
            $sql = file_get_contents(dirname(__DIR__) . '/database/migration_admin_alerts.sql');
            if ($sql === false) {
                throw new RuntimeException('Cannot read database/migration_admin_alerts.sql');
            }
            $pdo->exec($sql);
        },
    ],

    // ── Phase 7: SEO Architecture Upgrades ───────────────────────────────
    [
        'id'    => 'url_redirects_table',
        'label' => 'Phase 7 — url_redirects table for URL lifecycle management',
        'check' => fn(PDO $pdo) => tableExists($pdo, 'url_redirects'),
        'apply' => function (PDO $pdo) {
            $sql = file_get_contents(dirname(__DIR__) . '/database/migration_url_redirects.sql');
            if ($sql === false) {
                throw new RuntimeException('Cannot read database/migration_url_redirects.sql');
            }
            $pdo->exec($sql);
        },
    ],

    // ── Showcase Integrity: Cleanup orphaned showcase items ─────────────
    [
        'id'    => 'showcase_orphan_cleanup',
        'label' => 'Integrity — Clean up orphaned rows in home_showcase_items',
        'check' => function (PDO $pdo): bool {
            if (!tableExists($pdo, 'home_showcase_items')) {
                return true;
            }
            $sql = "SELECT COUNT(*) FROM home_showcase_items WHERE (
                item_type = 'product'
                AND (reference_id IS NULL OR reference_id NOT IN (SELECT id FROM products))
            ) OR (
                item_type IN ('writing', 'project')
                AND (
                    COALESCE(reference_id, post_id) IS NULL
                    OR COALESCE(reference_id, post_id) NOT IN (SELECT id FROM posts)
                )
            )";
            return ((int)$pdo->query($sql)->fetchColumn()) === 0;
        },
        'apply' => function (PDO $pdo): void {
            $sql = "DELETE FROM home_showcase_items WHERE (
                item_type = 'product'
                AND (reference_id IS NULL OR reference_id NOT IN (SELECT id FROM products))
            ) OR (
                item_type IN ('writing', 'project')
                AND (
                    COALESCE(reference_id, post_id) IS NULL
                    OR COALESCE(reference_id, post_id) NOT IN (SELECT id FROM posts)
                )
            )";
            $pdo->exec($sql);
        },
    ],
    // ── Integrity: Unique slug for posts ─────────────
    [
        'id'    => 'uq_posts_slug',
        'label' => 'Integrity — Enforce unique slugs for posts (uq_posts_slug)',
        'check' => fn(PDO $pdo) => indexExists($pdo, 'posts', 'uq_posts_slug'),
        'apply' => function (PDO $pdo) {
            if (indexExists($pdo, 'posts', 'idx_posts_slug')) {
                $pdo->exec('ALTER TABLE posts DROP INDEX idx_posts_slug');
            }
            $pdo->exec('ALTER TABLE posts ADD UNIQUE INDEX uq_posts_slug (slug)');
        }
    ],

    // ── Achievements: Standalone table ─────────────
    [
        'id'    => 'achievements_table',
        'label' => 'Achievements — Create standalone achievements table',
        'check' => fn(PDO $pdo) => tableExists($pdo, 'achievements'),
        'apply' => function (PDO $pdo) {
            $sql = file_get_contents(dirname(__DIR__) . '/database/migration_v5_achievements_table.sql');
            if ($sql === false) {
                throw new RuntimeException('Cannot read database/migration_v5_achievements_table.sql');
            }
            $pdo->exec($sql);
        }
    ],

    // ── Phase 1: Rename achievement_images to project_images ─────────────
    [
        'id'    => 'v6_project_images_rename',
        'label' => 'Phase 1 — Rename achievement_images to project_images',
        // Applied as soon as project_images exists. The old check also demanded that
        // achievement_images be gone, but apply() (RENAME TABLE) can never succeed while
        // project_images exists, so that made this migration pending forever and
        // failing with error 1050 on every run. Leftover achievement_images rows are
        // handled by v6_project_images_reconcile below.
        'check' => fn(PDO $pdo) => tableExists($pdo, 'project_images'),
        'apply' => function (PDO $pdo) {
            $sql = file_get_contents(dirname(__DIR__) . '/database/migration_v6_project_images_rename.sql');
            if ($sql === false) {
                throw new RuntimeException('Cannot read database/migration_v6_project_images_rename.sql');
            }
            $pdo->exec($sql);
        }
    ],

    // DB-001 follow-up: check() must be TRUE only when BOTH the column AND the
    // index exist. Previously it returned true when only the column existed,
    // which made the index permanently unadded if apply() was interrupted after
    // the first exec() succeeded but before the second.
    [
        'id'    => 'achievements_soft_delete',
        'label' => 'DB-001 — achievements: add deleted_at column for soft-delete support',
        'check' => fn(PDO $pdo) => columnExists($pdo, 'achievements', 'deleted_at')
                               && indexExists($pdo, 'achievements', 'idx_achievements_deleted_at'),
        'apply' => function (PDO $pdo) {
            // Guard each piece individually so a partial earlier run can be completed.
            if (!columnExists($pdo, 'achievements', 'deleted_at')) {
                $pdo->exec(
                    "ALTER TABLE `achievements`
                     ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL
                     AFTER `updated_at`"
                );
            }
            if (!indexExists($pdo, 'achievements', 'idx_achievements_deleted_at')) {
                $pdo->exec(
                    "ALTER TABLE `achievements`
                     ADD KEY `idx_achievements_deleted_at` (`deleted_at`)"
                );
            }
        },
    ],

    // ── DB-002: project_images reconcile (fixes defective v6_project_images_reconcile) ──
    // The DEFECT in the previous version: check = tableExists('project_images').
    // In production project_images already exists, so check() returned true
    // immediately and apply() (the row copy) never ran.
    //
    // FIXED check(): returns true only when reconciliation is genuinely complete
    // or unnecessary:
    //   • project_images exists  AND
    //   • achievement_images does NOT exist  OR
    //   • every achievement_images row whose post_id is valid in posts already
    //     has a matching row in project_images (same post_id, same image_url).
    //     Orphaned rows (post_id not in posts) are excluded from the predicate
    //     because they cannot legally be copied (FK constraint) and their
    //     presence must not keep check() permanently false.
    //
    // FIXED apply(): copies only rows whose post_id exists in posts;
    //   uses GROUP BY (post_id, image_url) with MIN(created_at) + NOT EXISTS so it
    //   is deterministic and idempotent;
    //   does NOT use INSERT IGNORE (that would silently hide FK failures);
    //   each statement in its own exec() call.
    //   No DROP, TRUNCATE, RENAME or destructive DDL anywhere.
    //
    // Match key: (post_id, image_url) — the only non-generated columns shared
    // by both tables. `id` and `created_at` are not part of the match key.
    // `created_at` is preserved from the source row.
    [
        'id'    => 'v6_project_images_reconcile',
        'label' => 'DB-002 — Reconcile achievement_images → project_images (row-copy; no DROP)',
        'check' => function (PDO $pdo): bool {
            // If project_images doesn't exist at all, nothing is ready.
            if (!tableExists($pdo, 'project_images')) {
                return false;
            }
            // If achievement_images doesn't exist, no rows to reconcile — done.
            if (!tableExists($pdo, 'achievement_images')) {
                return true;
            }
            // Check: are there any achievement_images rows (with a valid post_id
            // FK into posts) that are missing from project_images?
            // If zero such rows exist, reconciliation is complete.
            $stmt = $pdo->prepare(
                "SELECT COUNT(*)
                   FROM (
                     SELECT ai.post_id, ai.image_url
                       FROM `achievement_images` ai
                      INNER JOIN `posts` p ON p.id = ai.post_id
                      GROUP BY ai.post_id, ai.image_url
                   ) AS eligible
                  WHERE NOT EXISTS (
                     SELECT 1
                       FROM `project_images` pi
                      WHERE pi.post_id = eligible.post_id
                        AND pi.image_url = eligible.image_url
                  )"
            );
            $stmt->execute();
            return ((int) $stmt->fetchColumn()) === 0;
        },
        'apply' => function (PDO $pdo): void {
            // Copy only rows whose post_id exists in posts (FK-safe).
            // GROUP BY (post_id, image_url) de-duplicates legacy rows; MIN(created_at)
            // makes the copied timestamp deterministic.
            // NOT EXISTS ensures idempotency: already-present rows are skipped.
            // Each exec() call is a single statement — no multi-statement chains.
            $pdo->exec(
                "INSERT INTO `project_images` (`post_id`, `image_url`, `created_at`)
                 SELECT ai.`post_id`, ai.`image_url`, MIN(ai.`created_at`)
                   FROM `achievement_images` ai
                  INNER JOIN `posts` p ON p.id = ai.post_id
                  WHERE NOT EXISTS (
                     SELECT 1
                       FROM `project_images` pi
                      WHERE pi.post_id = ai.post_id
                        AND pi.image_url = ai.image_url
                  )
                  GROUP BY ai.`post_id`, ai.`image_url`"
            );
            // Do NOT drop achievement_images here.
            // The owner must confirm which table is canonical before any DROP.
        },
    ],

    // ── Privacy: Save Reading History Preference ─────────────────────────
    [
        'id'    => 'user_profiles_save_history',
        'label' => 'Privacy — user_profiles.save_reading_history preference',
        'check' => fn(PDO $pdo) => columnExists($pdo, 'user_profiles', 'save_reading_history'),
        'apply' => function (PDO $pdo) {
            $pdo->exec("ALTER TABLE `user_profiles` ADD COLUMN `save_reading_history` TINYINT(1) NOT NULL DEFAULT 1 AFTER `activity_visibility`");
        },
    ],
];

// Test hook: lets tests load the REAL $MIGRATIONS closures without running the
// page/POST logic below. Only honoured for the CLI; never defined in production.
if (php_sapi_name() === 'cli' && defined('MIGRATIONS_LOAD_ONLY')) {
    return;
}


// ============================================================
// SEC-03 — SAFE MIGRATION EXECUTION (fixes C-1)
// ============================================================
// Previously this entire loop ran unconditionally on every page
// load — a plain GET. Visiting the URL (a bookmark, a prefetch, a
// stray link) silently mutated the production schema, with no
// confirmation and no record of who triggered it.
//
// Now:
//   GET  -> PREVIEW ONLY. Calls check() for every migration (a
//           read-only information_schema query). apply() is never
//           reachable from a GET request, full stop.
//   POST -> EXECUTE. Requires everything GET already requires
//           (admin session, via requireAdminPage() above) PLUS a
//           valid CSRF token (requireCSRF() — the same mechanism
//           every other state-changing admin endpoint in this
//           codebase already uses) PLUS a literal confirmation
//           string that only this page's own "Run Pending
//           Migrations" button sends, so a CSRF token alone isn't
//           enough to trigger execution by accident. Every
//           migration actually applied is written to error_log()
//           with the acting admin's user id and a timestamp.
//
// The $MIGRATIONS definitions and the check()/apply() contract
// above are completely unchanged — this only changes when apply()
// is allowed to run.
// ============================================================

$isPost = (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') || (php_sapi_name() === 'cli');

if ($isPost) {
    if (php_sapi_name() !== 'cli') {
        requireCSRF();
        header('Content-Type: application/json');
        $body = json_decode(file_get_contents('php://input'), true);
        $confirmToken = is_array($body) ? ($body['confirm'] ?? '') : '';
        if ($confirmToken !== 'RUN_PENDING_MIGRATIONS') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Missing or invalid confirmation. No migrations were executed.',
            ]);
            exit;
        }
    }

    $results = [];
    $adminId = currentUserId();

    foreach ($MIGRATIONS as $migration) {

        $alreadyApplied = false;
        $error = null;

        try {
            $alreadyApplied = ($migration['check'])($pdo);
            if (!$alreadyApplied) {
                ($migration['apply'])($pdo);
                // Audit trail (§20 of the engineering audit flagged the
                // absence of one): who ran which migration, and when.
                error_log(sprintf(
                    '[run_migrations] APPLIED "%s" by admin_user_id=%d at %s',
                    $migration['id'],
                    $adminId,
                    date('c')
                ));
                logAdminAction(
                    'migration.run',
                    'migration',
                    $migration['id'],
                    json_encode(['label' => $migration['label']], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                );
                // Phase 3: also record it in schema_migrations, if that
                // table exists (it may not yet, on the very first run,
                // if this loop hasn't reached the ledger migration
                // itself yet — guarded so a missing ledger never breaks
                // the actual migration that just succeeded).
                if (tableExists($pdo, 'schema_migrations')) {
                    try {
                        $pdo->prepare(
                            'INSERT INTO schema_migrations (id, applied_by) VALUES (?, ?)
                             ON DUPLICATE KEY UPDATE applied_at = applied_at' // keep first-applied timestamp
                        )->execute([$migration['id'], $adminId]);
                    } catch (PDOException $ledgerError) {
                        error_log('[run_migrations] ledger write failed for "' . $migration['id'] . '": ' . $ledgerError->getMessage());
                    }
                }
            }
        } catch (PDOException $e) {
            $error = $e->getMessage();
            error_log('[run_migrations] ' . $migration['id'] . ' failed: ' . $error);
        }

        $results[] = [
            'id'      => $migration['id'],
            'label'   => $migration['label'],
            'status'  => $error ? 'error' : ($alreadyApplied ? 'already_applied' : 'applied_now'),
            'error'   => $error,
        ];

    }

    // ---- Backfill Ledger Step -------------------------------------------
    // For migrations whose check() passes but have no ledger row,
    // insert them with applied_by = NULL (idempotent, ON DUPLICATE UPDATE).
    $backfilledCount = 0;
    $backfilledIds   = [];

    if (tableExists($pdo, 'schema_migrations')) {
        try {
            $existingLedger = $pdo->query('SELECT id FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
            $existingLedgerSet = array_flip($existingLedger);

            $stmtBackfill = $pdo->prepare(
                'INSERT INTO schema_migrations (id, applied_by) VALUES (?, NULL)
                 ON DUPLICATE KEY UPDATE applied_at = applied_at'
            );

            foreach ($MIGRATIONS as $migration) {
                if (!isset($existingLedgerSet[$migration['id']])) {
                    try {
                        if (($migration['check'])($pdo)) {
                            $stmtBackfill->execute([$migration['id']]);
                            $backfilledCount++;
                            $backfilledIds[] = $migration['id'];
                            $existingLedgerSet[$migration['id']] = true;
                        }
                    } catch (PDOException $checkErr) {
                        // ignore check errors during backfill
                    }
                }
            }

            if ($backfilledCount > 0) {
                error_log(sprintf(
                    '[run_migrations] BACKFILLED %d migration ledger entries by admin_user_id=%d at %s',
                    $backfilledCount,
                    $adminId,
                    date('c')
                ));
                logAdminAction(
                    'migration.backfill_ledger',
                    'schema_migrations',
                    'all',
                    json_encode([
                        'backfilled_count' => $backfilledCount,
                        'migrations'       => $backfilledIds,
                    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                );
            }
        } catch (PDOException $e) {
            error_log('[run_migrations] Ledger backfill error: ' . $e->getMessage());
        }
    }

    echo json_encode([
        'success'          => true,
        'results'          => $results,
        'backfilled_count' => $backfilledCount,
        'backfilled_ids'   => $backfilledIds,
    ]);
    exit;

}

// ---- GET: PREVIEW ONLY — check() only, apply() is never called here ----

$results = [];

foreach ($MIGRATIONS as $migration) {

    $alreadyApplied = false;
    $error = null;

    try {
        $alreadyApplied = ($migration['check'])($pdo);
    } catch (PDOException $e) {
        $error = $e->getMessage();
        error_log('[run_migrations] ' . $migration['id'] . ' check failed: ' . $error);
    }

    $results[] = [
        'id'      => $migration['id'],
        'label'   => $migration['label'],
        'status'  => $error ? 'error' : ($alreadyApplied ? 'already_applied' : 'pending'),
        'error'   => $error,
    ];

}

$ledgerIds = [];
if (tableExists($pdo, 'schema_migrations')) {
    try {
        $ledgerIds = $pdo->query('SELECT id FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        $ledgerIds = [];
    }
}
$ledgerSet = array_flip($ledgerIds);

$backfillCount = 0;
foreach ($results as &$r) {
    if ($r['status'] === 'already_applied' && !isset($ledgerSet[$r['id']])) {
        $r['in_ledger'] = false;
        $backfillCount++;
    } else {
        $r['in_ledger'] = isset($ledgerSet[$r['id']]);
    }
}
unset($r);

$csrfToken    = getCsrfToken();
$pendingCount = count(array_filter($results, fn($r) => $r['status'] === 'pending'));

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php require_once dirname(__DIR__) . '/includes/settings.php'; ?>
<title><?= htmlspecialchars(buildPageTitle('Database Migrations Check')) ?></title>

<!-- Instant Theme Initialization Script (Zero Flash, 3-Theme System) -->
<script>
  (function() {
    try {
      var stored = localStorage.getItem('site-theme') || localStorage.getItem('theme');
      var validThemes = ['light', 'dark', 'green'];
      var theme = (stored && validThemes.indexOf(stored) !== -1) ? stored : null;
      if (!theme) {
        var systemDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        theme = systemDark ? 'dark' : 'light';
      }
      document.documentElement.setAttribute('data-theme', theme);
    } catch(e) {}
  })();
</script>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="/css/styles.css?v=<?= filemtime(dirname(__DIR__) . '/css/styles.css') ?>">
<script src="/js/theme-toggle.js?v=<?= filemtime(dirname(__DIR__) . '/js/theme-toggle.js') ?>" defer></script>

<style>
    *, *::before, *::after { box-sizing: border-box; }
    body {
        margin: 0;
        padding: 40px 24px;
        background-color: var(--color-background, #0B0F14);
        color: var(--color-text-primary, #F1F5F9);
        font-family: 'Inter', system-ui, sans-serif;
        line-height: 1.5;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        align-items: center;
        transition: background-color 0.2s ease, color 0.2s ease;
    }
    .migration-container {
        width: 100%;
        max-width: 800px;
    }
    .header {
        margin-bottom: 28px;
        padding-bottom: 16px;
        border-bottom: 1px solid var(--color-border, rgba(255, 255, 255, 0.08));
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
    }
    h1 {
        font-size: 22px;
        font-weight: 700;
        margin: 0 0 4px 0;
        color: var(--color-text-primary, #F1F5F9);
    }
    .subtitle {
        font-size: 13px;
        color: var(--color-text-secondary, #B7C3D3);
        margin: 0;
    }
    .card {
        background-color: var(--color-surface, #131922);
        border: 1px solid var(--color-border, rgba(255, 255, 255, 0.08));
        border-radius: 10px;
        padding: 16px 20px;
        margin-bottom: 12px;
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .ok      { border-left: 3px solid var(--color-success, #4ADE80); }
    .skip    { border-left: 3px solid var(--color-primary, #4CC9F0); }
    .err     { border-left: 3px solid var(--color-error, #FF7A7A); }
    .pending { border-left: 3px solid var(--color-warning, #FBBF24); }
    .run-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 18px;
        background-color: var(--color-primary, #4CC9F0);
        border: none;
        color: var(--color-on-primary, #0D2A4A);
        border-radius: 6px;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        transition: background 0.15s ease;
    }
    .run-btn:hover:not(:disabled) {
        background-color: var(--color-secondary, #67B8E3);
        color: var(--color-on-secondary, #F0F6FC);
    }
    .run-btn:disabled {
        background-color: var(--color-surface-container-high, #2D333B);
        color: var(--color-text-muted, #8E9BB0);
        cursor: not-allowed;
    }
    .label {
        font-size: 14px;
        font-weight: 600;
        color: var(--color-text-primary, #F1F5F9);
        font-family: 'JetBrains Mono', monospace;
    }
    .status {
        font-size: 12.5px;
        color: var(--color-text-secondary, #B7C3D3);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .back-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-top: 24px;
        padding: 8px 16px;
        background-color: var(--color-surface-container-low, #1F242B);
        border: 1px solid var(--color-border, rgba(255, 255, 255, 0.08));
        color: var(--color-text-primary, #F1F5F9);
        border-radius: 6px;
        text-decoration: none;
        font-size: 13px;
        font-weight: 500;
        transition: background 0.15s ease, color 0.15s ease;
    }
    .back-btn:hover {
        background-color: var(--color-surface-container, #262C34);
        color: var(--color-primary, #4CC9F0);
    }
    .theme-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        border-radius: 6px;
        background: var(--color-surface-container-low, #1F242B);
        border: 1px solid var(--color-border, rgba(255, 255, 255, 0.08));
        color: var(--color-text-secondary, #B7C3D3);
        cursor: pointer;
        transition: all 0.15s ease;
    }
    .theme-btn:hover {
        color: var(--color-primary, #4CC9F0);
        background: var(--color-surface-container, #262C34);
    }
</style>
</head>
<body>
    <div class="migration-container">
        <div class="header">
            <div>
                <h1>Database Migrations</h1>
                <p class="subtitle">
                    <?php if ($pendingCount > 0): ?>
                        Preview only — <strong><?= (int) $pendingCount ?></strong> migration<?= $pendingCount === 1 ? '' : 's' ?> pending<?= $backfillCount > 0 ? " (would backfill $backfillCount into ledger)" : '' ?>. Nothing runs until you confirm below.
                    <?php elseif ($backfillCount > 0): ?>
                        All migrations applied to schema — <strong><?= (int) $backfillCount ?></strong> migration<?= $backfillCount === 1 ? '' : 's' ?> would backfill into ledger on execution.
                    <?php else: ?>
                        All migrations applied — schema and migration ledger are fully up to date.
                    <?php endif; ?>
                </p>
            </div>
            <button type="button" class="theme-btn theme-toggle-btn" aria-label="Toggle theme" title="Toggle Theme (Light / Dark / Green)">
                <i class="fas fa-moon"></i>
            </button>
        </div>

        <?php foreach ($results as $r): ?>
            <div class="card <?php
                echo $r['status'] === 'error' ? 'err' : ($r['status'] === 'already_applied' ? 'skip' : 'pending');
            ?>">
                <div class="label"><?php echo htmlspecialchars($r['label']); ?></div>
                <div class="status">
                    <?php if ($r['status'] === 'already_applied'): ?>
                        <?php if (!empty($r['in_ledger'])): ?>
                            <span style="color: var(--accent);">✓ Already applied — recorded in ledger.</span>
                        <?php else: ?>
                            <span style="color: var(--color-primary, #4CC9F0);">✓ Already applied to schema (would backfill into ledger).</span>
                        <?php endif; ?>
                    <?php elseif ($r['status'] === 'pending'): ?>
                        <span style="color: var(--warning);">⏳ Pending — will run when confirmed.</span>
                    <?php else: ?>
                        <span style="color: var(--danger);">✕ Check failed: <?php echo htmlspecialchars($r['error']); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <?php
            $canExecute = ($pendingCount > 0 || $backfillCount > 0);
            $btnText = 'Run Pending Migrations';
            if ($pendingCount > 0 && $backfillCount > 0) {
                $btnText = "Run Migrations ($pendingCount) & Backfill ($backfillCount)";
            } elseif ($pendingCount > 0) {
                $btnText = "Run Pending Migrations ($pendingCount)";
            } elseif ($backfillCount > 0) {
                $btnText = "Backfill Migration Ledger ($backfillCount)";
            }
        ?>
        <div style="display: flex; gap: 12px; margin-top: 16px; align-items: center; flex-wrap: wrap;">
            <button type="button" id="runMigrationsBtn" class="run-btn" <?= !$canExecute ? 'disabled' : '' ?>>
                <i class="fas fa-play"></i>
                <span><?= htmlspecialchars($btnText) ?></span>
            </button>
            <a class="back-btn" href="settings.php">← Return to Settings</a>
            <a class="back-btn" href="index.php">Studio Dashboard</a>
        </div>
        <p id="runMigrationsResult" style="font-size: 13px; color: var(--text-secondary); margin-top: 12px;"></p>
    </div>

    <script>
        // SEC-03: execution requires POST + this page's CSRF token + an
        // explicit confirm() dialog + the literal confirmation string
        // the server checks for. A page reload afterwards re-runs the
        // GET preview against the now-current schema state.
        (function () {
            var btn = document.getElementById('runMigrationsBtn');
            if (!btn) return;

            btn.addEventListener('click', function () {
                var pendingCount = <?= (int) $pendingCount ?>;
                var backfillCount = <?= (int) $backfillCount ?>;
                if (pendingCount === 0 && backfillCount === 0) return;

                var msg = '';
                if (pendingCount > 0 && backfillCount > 0) {
                    msg = 'This will run ' + pendingCount + ' pending database migration' +
                        (pendingCount === 1 ? '' : 's') + ' and backfill ' + backfillCount +
                        ' ledger entries against the LIVE production database. Continue?';
                } else if (pendingCount > 0) {
                    msg = 'This will run ' + pendingCount + ' pending database migration' +
                        (pendingCount === 1 ? '' : 's') + ' against the LIVE production database. Continue?';
                } else {
                    msg = 'This will backfill ' + backfillCount + ' migration ledger entries with applied_by=NULL. Continue?';
                }
                if (!window.confirm(msg)) return;

                btn.disabled = true;
                btn.querySelector('span').textContent = 'Running…';

                var resultEl = document.getElementById('runMigrationsResult');
                resultEl.textContent = '';

                fetch(window.location.pathname, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': <?= json_encode($csrfToken) ?>
                    },
                    body: JSON.stringify({ confirm: 'RUN_PENDING_MIGRATIONS' })
                })
                    .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
                    .then(function (result) {
                        if (!result.ok || !result.data.success) {
                            resultEl.style.color = 'var(--color-error, #FF7A7A)';
                            resultEl.textContent = 'Failed: ' + (result.data.message || 'Unknown error.');
                            btn.disabled = false;
                            btn.querySelector('span').textContent = <?= json_encode($btnText) ?>;
                            return;
                        }
                        resultEl.style.color = 'var(--color-success, #4ADE80)';
                        resultEl.textContent = 'Done. Reloading…';
                        window.location.reload();
                    })
                    .catch(function (err) {
                        resultEl.style.color = 'var(--color-error, #FF7A7A)';
                        resultEl.textContent = 'Request failed: ' + err.message;
                        btn.disabled = false;
                        btn.querySelector('span').textContent = <?= json_encode($btnText) ?>;
                    });
            });
        })();
    </script>
</body>
</html>
