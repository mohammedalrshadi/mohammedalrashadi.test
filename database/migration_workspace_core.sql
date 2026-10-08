-- Core Tables
CREATE TABLE IF NOT EXISTS `ws_courses` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(50) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `semester` VARCHAR(100) NULL,
    `instructor` VARCHAR(255) NULL,
    `status` ENUM('planned', 'active', 'completed', 'dropped') NOT NULL DEFAULT 'planned',
    `progress` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `start_date` DATE NULL,
    `end_date` DATE NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ws_tasks` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `status` ENUM('todo', 'in_progress', 'done') NOT NULL DEFAULT 'todo',
    `priority` ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium',
    `due_date` DATETIME NULL,
    `entity_type` VARCHAR(50) NULL,
    `entity_id` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `completed_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    KEY `idx_ws_tasks_entity` (`entity_type`, `entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ws_events` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `event_date` DATE NOT NULL,
    `event_time` TIME NULL,
    `location` VARCHAR(255) NULL,
    `description` TEXT NULL,
    `entity_type` VARCHAR(50) NULL,
    `entity_id` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ws_events_entity` (`entity_type`, `entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ws_notes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `content` LONGTEXT NOT NULL,
    `entity_type` VARCHAR(50) NULL,
    `entity_id` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ws_notes_entity` (`entity_type`, `entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ws_resources` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `url` VARCHAR(1000) NOT NULL,
    `type` VARCHAR(50) NOT NULL,
    `status` ENUM('saved', 'to_read', 'reading', 'completed', 'archived') NOT NULL DEFAULT 'saved',
    `description` TEXT NULL,
    `entity_type` VARCHAR(50) NULL,
    `entity_id` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ws_resources_entity` (`entity_type`, `entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ws_projects` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `status` ENUM('planned', 'active', 'completed', 'paused') NOT NULL DEFAULT 'planned',
    `start_date` DATE NULL,
    `target_date` DATE NULL,
    `repo_url` VARCHAR(1000) NULL,
    `demo_url` VARCHAR(1000) NULL,
    `technologies` VARCHAR(500) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ws_clubs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `organization` VARCHAR(255) NULL,
    `role` VARCHAR(100) NULL,
    `status` ENUM('interested', 'following', 'member', 'volunteer', 'committee', 'former') NOT NULL DEFAULT 'member',
    `start_date` DATE NULL,
    `end_date` DATE NULL,
    `description` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ws_skills` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `category` VARCHAR(100) NULL,
    `status` ENUM('learning', 'practicing', 'applied', 'strong') NOT NULL DEFAULT 'learning',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ws_goals` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `category` VARCHAR(100) NULL,
    `status` ENUM('active', 'completed', 'paused') NOT NULL DEFAULT 'active',
    `target_date` DATE NULL,
    `description` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ws_reading_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `type` ENUM('book', 'article', 'paper', 'doc', 'course', 'video') NOT NULL,
    `status` ENUM('want_to_read', 'reading', 'completed', 'paused', 'archived') NOT NULL DEFAULT 'want_to_read',
    `url` VARCHAR(1000) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ws_habits` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `frequency` VARCHAR(100) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ws_achievements` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `date_earned` DATE NULL,
    `description` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Junction Tables
CREATE TABLE IF NOT EXISTS `ws_course_projects` (
    `course_id` INT UNSIGNED NOT NULL,
    `project_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`course_id`, `project_id`),
    CONSTRAINT `fk_ws_cp_course` FOREIGN KEY (`course_id`) REFERENCES `ws_courses`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ws_cp_project` FOREIGN KEY (`project_id`) REFERENCES `ws_projects`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ws_project_skills` (
    `project_id` INT UNSIGNED NOT NULL,
    `skill_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`project_id`, `skill_id`),
    CONSTRAINT `fk_ws_ps_project` FOREIGN KEY (`project_id`) REFERENCES `ws_projects`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ws_ps_skill` FOREIGN KEY (`skill_id`) REFERENCES `ws_skills`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ws_goal_projects` (
    `goal_id` INT UNSIGNED NOT NULL,
    `project_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`goal_id`, `project_id`),
    CONSTRAINT `fk_ws_gp_goal` FOREIGN KEY (`goal_id`) REFERENCES `ws_goals`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ws_gp_project` FOREIGN KEY (`project_id`) REFERENCES `ws_projects`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ws_goal_skills` (
    `goal_id` INT UNSIGNED NOT NULL,
    `skill_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`goal_id`, `skill_id`),
    CONSTRAINT `fk_ws_gs_goal` FOREIGN KEY (`goal_id`) REFERENCES `ws_goals`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ws_gs_skill` FOREIGN KEY (`skill_id`) REFERENCES `ws_skills`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ws_note_tags` (
    `note_id` INT UNSIGNED NOT NULL,
    `tag` VARCHAR(50) NOT NULL,
    PRIMARY KEY (`note_id`, `tag`),
    CONSTRAINT `fk_ws_nt_note` FOREIGN KEY (`note_id`) REFERENCES `ws_notes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ws_resource_tags` (
    `resource_id` INT UNSIGNED NOT NULL,
    `tag` VARCHAR(50) NOT NULL,
    PRIMARY KEY (`resource_id`, `tag`),
    CONSTRAINT `fk_ws_rt_resource` FOREIGN KEY (`resource_id`) REFERENCES `ws_resources`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
