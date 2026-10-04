# Database Entity Audit

**Platform:** Mohammed Alrashadi — Personal Engineering Platform  
**Audit Standard:** Grounded Codebase & MySQL Schema Inspection  
**Date:** 2026-09-15  
**Auditor:** Senior Full-Stack Engineer + Database Architect  

---

## 1. Executive Entity Overview

The application utilizes a normalized MySQL relational schema (Engine: InnoDB, Charset: `utf8mb4_unicode_ci`). The database schema is defined in [`database/schema.sql`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/database/schema.sql) and maintained via migrations in [`database/`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/database/).

The schema encompasses **11 distinct database tables** categorized into 4 operational domains:
1. **Identity & Access Management:** `users`
2. **Content & Relational Media:** `categories`, `posts`, `achievement_images`
3. **Community & Engagement:** `reviews`, `social_links`
4. **Telemetry & Analytics Aggregation:** `site_visitors`, `daily_site_stats`, `daily_article_stats`, `telemetry_dedup_visitors`, `telemetry_dedup_articles`

---

## 2. Comprehensive Entity Specifications

### Entity 1: User
- **Table Name:** `users`
- **Purpose:** Primary authentication, privilege verification, and admin account management. An administrator is a user row with `role = 'admin'`. Passwords are saved strictly as bcrypt/Argon2id hashes.
- **Primary Key:** `id` (INT UNSIGNED, AUTO_INCREMENT)
- **Important Columns:**
  - `name` (VARCHAR(255), NOT NULL, DEFAULT '')
  - `email` (VARCHAR(255), NOT NULL, UNIQUE: `uq_users_email`)
  - `password_hash` (VARCHAR(255), NOT NULL)
  - `role` (ENUM('admin', 'user'), NOT NULL, DEFAULT 'user')
  - `created_at` (TIMESTAMP, NOT NULL, DEFAULT CURRENT_TIMESTAMP)
  - `updated_at` (TIMESTAMP, NOT NULL, DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)
- **Relationships:**
  - Implicit author of posts, but posts table does not store `author_id` (single-author personal engineering platform model).
- **Foreign Keys:** None.
- **Used by APIs:**
  - [`api/auth/login.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/auth/login.php)
  - [`api/auth/session.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/auth/session.php)
  - [`api/auth/guard.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/auth/guard.php)
  - [`api/users/list.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/users/list.php)
  - [`api/users/create.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/users/create.php)
  - [`api/users/update.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/users/update.php)
  - [`api/users/delete.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/users/delete.php)
- **Used by Admin Pages:**
  - [`admin/users.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/admin/users.php)
  - [`admin/index.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/admin/index.php) (user count metrics)
  - [`admin/login.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/admin/login.php)
- **Used by Public Pages:** None directly (never exposed to public visitors).
- **Current CRUD Support:** Complete (Create, Read, Update with password reset, Delete).
- **Missing CRUD Operations:** None.
- **Missing Indexes:** Current indexes (`PRIMARY`, `uq_users_email`) are optimal for email lookups.
- **Potential Integrity Problems:** Last remaining admin deletion guard exists in `users/delete.php` (prevents self-deletion and ensures at least one admin persists).

---

### Entity 2: Category
- **Table Name:** `categories`
- **Purpose:** Dedicated source of truth for post categories, partitioned strictly by content type (`blog` and `achievement`).
- **Primary Key:** `id` (INT UNSIGNED, AUTO_INCREMENT)
- **Important Columns:**
  - `name` (VARCHAR(255), NOT NULL)
  - `type` (ENUM('blog', 'achievement'), NOT NULL)
  - `created_at` (DATETIME, NOT NULL, DEFAULT CURRENT_TIMESTAMP)
  - `updated_at` (DATETIME, NOT NULL, DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)
- **Relationships:**
  - Referenced loosely by `posts.category = categories.name` and `posts.type = categories.type`.
- **Foreign Keys:** None (string matching allows posts to retain legacy category names during schema updates).
- **Indexes:**
  - `UNIQUE KEY unique_type_name (type, name)`
  - `KEY idx_categories_type (type)`
- **Used by APIs:**
  - [`api/categories/list.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/categories/list.php)
  - [`api/categories/create.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/categories/create.php)
  - [`api/categories/rename.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/categories/rename.php)
  - [`api/categories/delete.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/categories/delete.php)
  - [`api/posts/categories.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/posts/categories.php)
- **Used by Admin Pages:**
  - [`admin/articles.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/admin/articles.php)
  - [`admin/achievements.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/admin/achievements.php)
- **Used by Public Pages:** Filter categories populated dynamically on `projects.php` and `articles.php`.
- **Current CRUD Support:** Complete (Create, Read, Rename cascading to posts, Delete).
- **Missing CRUD Operations:** None.
- **Missing Indexes:** None; `(type, name)` unique key is optimal.
- **Potential Integrity Problems:** When a category is deleted, `categories/delete.php` reassigns associated posts to `عام` (General) to prevent dangling references.

---

### Entity 3: Post (Polymorphic Content Hub)
- **Table Name:** `posts`
- **Purpose:** Core polymorphic publishing entity for both Technical Writing (`type = 'blog'`) and Engineering Projects / Achievements (`type = 'achievement'`). Features soft-delete (`deleted_at`), publishing lifecycle (`status`), view counting (`views`), and optional bilingual quotes.
- **Primary Key:** `id` (INT UNSIGNED, AUTO_INCREMENT)
- **Important Columns:**
  - `title` (VARCHAR(500), NOT NULL)
  - `category` (VARCHAR(255), NOT NULL, DEFAULT '')
  - `content` (LONGTEXT, NOT NULL)
  - `type` (ENUM('blog', 'achievement'), NOT NULL, DEFAULT 'blog')
  - `status` (ENUM('published', 'hidden', 'draft'), NOT NULL, DEFAULT 'draft')
  - `views` (INT UNSIGNED, NOT NULL, DEFAULT 0)
  - `image_url` (VARCHAR(1000), DEFAULT NULL)
  - `quote_ar` (TEXT, DEFAULT NULL)
  - `quote_en` (TEXT, DEFAULT NULL)
  - `created_at` (DATETIME, NOT NULL, DEFAULT CURRENT_TIMESTAMP)
  - `updated_at` (DATETIME, NOT NULL, DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)
  - `deleted_at` (DATETIME, DEFAULT NULL — soft delete timestamp)
- **Relationships:**
  - 1-to-Many with `achievement_images` (where `type = 'achievement'`)
  - 1-to-Many with `reviews` (where `reviews.post_id = posts.id`)
  - 1-to-Many with `daily_article_stats` (where `daily_article_stats.post_id = posts.id`)
- **Foreign Keys:** None on `posts` itself.
- **Indexes:**
  - `KEY idx_posts_status (status)`
  - `KEY idx_posts_type (type)`
  - `KEY idx_posts_created_at (created_at)`
  - `KEY idx_posts_deleted_at (deleted_at)`
  - `KEY idx_posts_category (category)`
  - `KEY idx_posts_views (views)`
  - `KEY idx_posts_type_status_cat (type, status, deleted_at, created_at)`
- **Used by APIs:**
  - [`api/posts/list.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/posts/list.php)
  - [`api/posts/create.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/posts/create.php)
  - [`api/posts/update.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/posts/update.php)
  - [`api/posts/publish.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/posts/publish.php)
  - [`api/posts/hide.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/posts/hide.php)
  - [`api/posts/delete.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/posts/delete.php) (soft-delete)
  - [`api/posts/trash.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/posts/trash.php) (trash review)
  - [`api/posts/restore.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/posts/restore.php) (un-delete)
  - [`api/posts/purge.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/posts/purge.php) (permanent purge)
  - [`api/telemetry/view.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/telemetry/view.php)
- **Used by Admin Pages:**
  - [`admin/articles.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/admin/articles.php)
  - [`admin/achievements.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/admin/achievements.php)
  - [`admin/index.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/admin/index.php)
- **Used by Public Pages:**
  - [`index.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/index.php) (featured projects & recent articles)
  - [`projects.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/projects.php)
  - [`project.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/project.php)
  - [`articles.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/articles.php)
  - [`post.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/post.php)
- **Current CRUD Support:** Complete with full soft-delete lifecycle (Draft -> Published -> Hidden -> Soft Deleted -> Restored / Purged).
- **Missing CRUD Operations:** None.
- **Missing Indexes:** None; the 4-column composite index `(type, status, deleted_at, created_at)` matches public querying precisely.
- **Potential Integrity Problems:**
  - Purging a post physically removes related files via `api/posts/image_cleanup.php` and cascades to `achievement_images`.
  - Missing foreign key constraint on `reviews.post_id` leaves orphaned review records if a post is purged without manual review reassignment.

---

### Entity 4: Achievement Image (Relational Project Gallery)
- **Table Name:** `achievement_images`
- **Purpose:** Manages multi-image relational galleries attached to specific projects/achievements.
- **Primary Key:** `id` (INT UNSIGNED, AUTO_INCREMENT)
- **Important Columns:**
  - `post_id` (INT UNSIGNED, NOT NULL)
  - `image_url` (VARCHAR(1000), NOT NULL)
  - `created_at` (DATETIME, NOT NULL, DEFAULT CURRENT_TIMESTAMP)
- **Relationships:**
  - Belongs to `posts` (Many-to-1).
- **Foreign Keys:**
  - `CONSTRAINT fk_ai_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE`
- **Indexes:**
  - `KEY idx_ai_post_id (post_id)`
- **Used by APIs:**
  - [`api/achievement_images/list.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/achievement_images/list.php)
  - [`api/achievement_images/add.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/achievement_images/add.php)
  - [`api/achievement_images/delete.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/achievement_images/delete.php)
  - [`api/posts/purge.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/posts/purge.php)
- **Used by Admin Pages:**
  - [`admin/achievements.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/admin/achievements.php)
- **Used by Public Pages:**
  - [`project.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/project.php) (displays gallery for project ID)
- **Current CRUD Support:** Complete (Add, Read list, Delete with physical image unlinking).
- **Missing CRUD Operations:** None.
- **Missing Indexes:** None.
- **Potential Integrity Problems:** None; protected by cascading foreign key constraint `fk_ai_post`.

---

### Entity 5: Review / Feedback
- **Table Name:** `reviews`
- **Purpose:** Stores public reader feedback, critiques, and site testimonials. Supports article-specific reviews (`post_id = N`) and global testimonials (`post_id IS NULL`). Requires administrative approval (`status = 'approved'`) before public display.
- **Primary Key:** `id` (INT UNSIGNED, AUTO_INCREMENT)
- **Important Columns:**
  - `name` (VARCHAR(255), NOT NULL)
  - `email` (VARCHAR(255), NOT NULL — kept private)
  - `message` (TEXT, NOT NULL)
  - `post_id` (INT UNSIGNED, DEFAULT NULL)
  - `status` (ENUM('pending', 'approved', 'rejected'), NOT NULL, DEFAULT 'pending')
  - `created_at` (TIMESTAMP, NOT NULL, DEFAULT CURRENT_TIMESTAMP)
  - `updated_at` (TIMESTAMP, NOT NULL, DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)
- **Relationships:**
  - Optional Many-to-1 relationship with `posts(id)` via `post_id`.
- **Foreign Keys:**
  - Currently missing foreign key constraint in baseline schema.
- **Indexes:**
  - `KEY idx_reviews_status (status)`
  - `KEY idx_reviews_created_at (created_at)`
  - `KEY idx_reviews_post_id (post_id)`
- **Used by APIs:**
  - [`api/reviews/list.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/reviews/list.php)
  - [`api/reviews/submit.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/reviews/submit.php)
  - [`api/reviews/update_status.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/reviews/update_status.php)
  - [`api/reviews/delete.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/reviews/delete.php)
  - [`api/reviews/rate_limit.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/reviews/rate_limit.php)
- **Used by Admin Pages:**
  - [`admin/reviews.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/admin/reviews.php)
  - [`admin/index.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/admin/index.php) (pending review badges)
- **Used by Public Pages:**
  - [`post.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/post.php) (approved reviews and submission form)
- **Current CRUD Support:** Complete (Submit by public with rate limiting, Read filtered by status, Moderate status by admin, Delete by admin).
- **Missing CRUD Operations:** None.
- **Missing Indexes:** Composite index `(post_id, status, created_at)` would optimize article detail review queries.
- **Potential Integrity Problems:** Lack of explicit FK constraint allows `reviews.post_id` to refer to purged posts.

---

### Entity 6: Social Link
- **Table Name:** `social_links`
- **Purpose:** Manages official social channels and external profiles (X/Twitter, LinkedIn, GitHub, etc.) displayed in the public footer.
- **Primary Key:** `id` (INT UNSIGNED, AUTO_INCREMENT)
- **Important Columns:**
  - `platform` (VARCHAR(50), NOT NULL, UNIQUE: `uq_social_platform`)
  - `name` (VARCHAR(100), NOT NULL)
  - `url` (VARCHAR(500), NOT NULL, DEFAULT '')
  - `is_enabled` (TINYINT(1), NOT NULL, DEFAULT 0)
  - `sort_order` (INT UNSIGNED, NOT NULL, DEFAULT 0)
  - `created_at` (TIMESTAMP, NOT NULL, DEFAULT CURRENT_TIMESTAMP)
  - `updated_at` (TIMESTAMP, NOT NULL, DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)
- **Relationships:** None.
- **Foreign Keys:** None.
- **Indexes:**
  - `UNIQUE KEY uq_social_platform (platform)`
  - `KEY idx_social_enabled_sort (is_enabled, sort_order)`
- **Used by APIs:**
  - [`api/social/list.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/social/list.php)
  - [`api/social/admin_list.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/social/admin_list.php)
  - [`api/social/update.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/social/update.php)
- **Used by Admin Pages:**
  - [`admin/social.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/admin/social.php)
  - [`admin/index.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/admin/index.php)
- **Used by Public Pages:**
  - [`includes/footer.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/includes/footer.php)
- **Current CRUD Support:** Complete (List public, List admin, Batch Update).
- **Missing CRUD Operations:** None.
- **Missing Indexes:** None.
- **Potential Integrity Problems:** None.

---

### Entities 7–11: Telemetry & Analytics Pipeline
- **Table Names:**
  - `site_visitors`: Registry of distinct anonymous visitor hashes (`visitor_hash` CHAR(64) PK).
  - `daily_site_stats`: Daily site-wide aggregates (`stat_date` DATE PK, `visitors_count`, `reads_count`).
  - `daily_article_stats`: Daily article view aggregates (`stat_date` DATE, `post_id` INT UNSIGNED, PK: `(stat_date, post_id)`).
  - `telemetry_dedup_visitors`: 48h ephemeral deduplication buffer (`visitor_hash`, `visit_date`).
  - `telemetry_dedup_articles`: 48h ephemeral article view deduplication buffer (`visitor_hash`, `post_id`, `view_date`).
- **Purpose:** Privacy-respecting, GDPR-compliant local analytics tracking real unique visits and article read activity without third-party trackers.
- **Used by APIs:**
  - [`api/telemetry/visit.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/telemetry/visit.php)
  - [`api/telemetry/view.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/telemetry/view.php)
  - [`api/telemetry/identity.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/telemetry/identity.php)
  - [`api/analytics/dashboard.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/api/analytics/dashboard.php)
- **Used by Admin Pages:**
  - [`admin/index.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/admin/index.php) (7-day chart, lifetime metrics, reading trends).
- **Used by Public Pages:** Dispatched asynchronously from `includes/footer.php` and `post.php`.
- **Current CRUD Support:** Complete append/upsert telemetry with automatic ephemeral table cleanup.

---

## 3. Comprehensive Entity Mapping Summary

| Entity | Table | Type / Purpose | Primary Key | Parent / Relationships | Admin CRUD | Public Surface |
| :--- | :--- | :--- | :--- | :--- | :---: | :---: |
| **User** | `users` | Auth & Permissions | `id` | None | Full | None |
| **Category** | `categories` | Categorization | `id` | Soft link to `posts` | Full | Filter Bar |
| **Post** | `posts` | Articles & Projects | `id` | Relational parent to images/reviews | Full | Main Content |
| **AchievementImage** | `achievement_images` | Project Gallery | `id` | Belongs to `posts` (FK CASCADE) | Full | Project Detail |
| **Review** | `reviews` | Feedback & Moderation | `id` | Belongs to `posts` (optional) | Moderate/Delete | Article Detail |
| **SocialLink** | `social_links` | Social Links | `id` | None | Update | Footer |
| **SiteVisitor** | `site_visitors` | Analytics Registry | `visitor_hash` | None | Read | Telemetry Ping |
| **DailySiteStat** | `daily_site_stats` | Site Aggregates | `stat_date` | None | Read | Telemetry Ping |
| **DailyArticleStat**| `daily_article_stats` | Article Reads | `(stat_date, post_id)` | Linked to `posts` | Read | Telemetry Ping |
| **TelemetryDedup** | `telemetry_dedup_*` | Ephemeral Dedup | Composite | None | Automated | Telemetry Ping |

---
*Entity audit complete. No legacy tables or untracked models detected.*

