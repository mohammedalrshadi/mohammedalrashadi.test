# Source-of-Truth Architecture Map

**Platform:** Mohammed Alrashadi — Personal Engineering Platform
**Document Version:** 1.0.0
**Audit Standard:** Strict Data Integrity & Single Source of Truth (Zero Fictional Content)
**Last Updated:** 2026-09-16

---

## Executive Architectural Principle

Every piece of public-facing content across the platform MUST trace directly through an unbroken pipeline to an authoritative administrative source of truth. Fictional demo seed data, hardcoded fake benchmarks, fabricated metrics, and synthetic timeline stages are strictly prohibited. Where real content has not yet been authored, honest empty states or 404 responses are rendered.

---
## Content Pipeline Matrix

```text
┌─────────────┐       ┌──────────────┐       ┌─────────────────┐       ┌─────────────────┐       ┌────────────────┐
│  Public UI  │ ◄───► │ PHP Template │ ◄───► │ Data Access/API │ ◄───► │ Storage Backend │ ◄───► │ Admin Studio UI│
└─────────────┘       └──────────────┘       └─────────────────┘       └─────────────────┘       └────────────────┘
```

| Entity / Content | Public UI Route | Public Template | Data Access Layer | Storage Backend | Admin Studio UI | Pipeline Status |
| :--- | :--- | :--- | :--- | :--- | :--- | :---: |
| **1. Articles & Writing** | `/articles.php`, `/post.php?id={id}` | `articles.php`, `post.php` | Direct PDO query + `api/posts/list.php` | MariaDB: `posts`, (`type='blog'`, `status='published'`, `deleted_at IS NULL`) | `admin/articles.php`, (Full CRUD, tags, dates, cover upload) | **Verified Live** |
| **2. Projects & Systems** | `/projects.php`, `/project.php?id={id}` | `projects.php`, `project.php` | Direct PDO query + `api/posts/list.php` | MariaDB: `posts`, (`type='achievement'`, `status='published'`, `deleted_at IS NULL`) | `admin/achievements.php`, (Full CRUD, case study, gallery modal) | **Verified Live** |
| **3. Engineering Labs** | `/lab.php`, `/lab-detail.php?id={id}` | `lab.php`, `lab-detail.php` | Native JSON parser + `api/labs/*.php` | JSON File on Disk:, `api/data/lab_experiments.json`, (Deterministic locked read/write) | `admin/labs.php`, `admin/lab-edit.php`, (Full CRUD, telemetry, outcome specs) | **Verified Live** |
| **4. Taxonomy & Categories** | `/projects.php`, `/articles.php` | `projects.php`, `articles.php` | `api/categories/list.php` + PDO | MariaDB: `categories`, (`id`, `name`, `created_at`) | `admin/partials/layout_top.php`, (Global Category Manager modal) | **Verified Live** |
| **5. Visual Gallery** | `/gallery.php` | `gallery.php` | Direct PDO query + `api/achievement_images/list.php` | MariaDB: `achievement_images`, joined with `posts` | `admin/media.php`, `admin/achievements.php` (Gallery modal) | **Verified Live** |
| **6. Homepage Showcase** | `/index.php` (`#showcase`) | `index.php` | `getSiteSetting('showcase.item_ids')` + PDO | MariaDB: `site_settings`, (`key_group='showcase'`, `setting_key='item_ids'`) | `admin/showcase.php`, (Order reordering, curated selection) | **Verified Live** |
| **7. Platform Profile & Bio** | `/index.php`, `/about.php`, `includes/header.php` | `index.php`, `about.php`, `includes/settings.php` | `getSiteProfile()` helper | MariaDB: `site_settings`, cached in `api/data/settings_cache.json` | `admin/settings.php?tab=profile`, (Direct CMS avatar upload + metadata) | **Verified Live** |
| **8. Engineering Journey** | `/journey.php` | `journey.php` | Direct PDO query (or JSON store) | *Currently Unseeded*, (Honest empty state displayed) | *Future Admin Module Required*, (See Section 8 below) | **Honest Empty State** |
| **9. Social Channels** | `includes/footer.php`, `/about.php` | `includes/footer.php` | `GET /api/social/list.php` + PDO | MariaDB: `social_links`, (`platform`, `url`, `is_active`, `sort_order`) | `admin/social.php`, (Channel URLs, ordering, active toggles) | **Verified Live** |
| **10. Visitor Reviews** | `/post.php?id={id}` (`#reviews`) | `post.php` | Direct PDO query + `api/reviews/*.php` | MariaDB: `reviews`, (Filtered by `status='approved'`, post published) | `admin/reviews.php`, (Review approval, rejection, deletion) | **Verified Live** |

---

## Detailed Pipeline Breakdown

### 1. Articles & Editorial Writing

- **Public Entry Point:** `/articles.php` lists published articles ordered by publication date with dynamic category filtering. `/post.php?id={id}` renders the full essay.
- **Data Access:** PDO prepared statements querying `posts` table filtering strictly by `type = 'blog' AND status = 'published' AND deleted_at IS NULL`.
- **Integrity Rule:** Missing or soft-deleted articles immediately yield an HTTP 404 response with `http_response_code(404)`. Fabricated fallback arrays (`$canonicalEssays`) have been completely removed.
- **Admin Control:** `admin/articles.php` provides search, status filtering (All, Published, Draft, Hidden), rich form editor, cover image uploader, and instant publish/unpublish toggles.

### 2. Projects & Systems Architecture

- **Public Entry Point:** `/projects.php` displays systems builds and research implementations. `/project.php?id={id}` displays the deep-dive architectural case study and relational image gallery.
- **Data Access:** PDO prepared statements querying `posts` table with `type = 'achievement' AND status = 'published' AND deleted_at IS NULL`. Associated gallery images query `achievement_images` where `post_id = ?`.
- **Integrity Rule:** Missing or soft-deleted project IDs yield an HTTP 404 response. No demo projects or mock architectures are seeded.
- **Admin Control:** `admin/achievements.php` manages full project metadata and relational multi-image uploads via `api/achievement_images/add.php`.

### 3. Engineering Lab & Benchmarks

- **Public Entry Point:** `/lab.php` renders the benchmark notebook matrix. `/lab-detail.php?id={id}` renders deep telemetry, hypothesis, empirical outcomes, and repro commands.
- **Operational Source of Truth:** `api/data/lab_experiments.json` (JSON on disk with deterministic locked read/write via `LOCK_EX`). This file is live and authoritatively managed by `admin/labs.php` and `api/labs/*.php`.
- **SQL Table Status:** The SQL table `lab_experiments` exists in the schema but is completely empty (0 rows) and bypassed by runtime code (see `docs/LABS_STORAGE_DECISION.md`).
- **Integrity Rule:** Fictional Stitch demo benchmarks (`LAB-014`, `LAB-011`, `LAB-008`) removed. When no experiments exist, `/lab.php` displays an honest empty state. Invalid IDs in `/lab-detail.php?id={id}` emit HTTP 404.
- **Admin Control:** `admin/labs.php` and `admin/lab-edit.php` provide comprehensive CRUD capabilities backed by `api/labs/save.php` and `api/labs/delete.php`.

### 4. Taxonomy & Categories

- **Public Entry Point:** Dynamic category filter pills on `/projects.php` and `/articles.php`.
- **Data Access:** Queries the `categories` relational database table (`SELECT id, name FROM categories ORDER BY name ASC`).
- **Integrity Rule:** Filter pills are populated strictly from real categories stored in the `categories` table.
- **Admin Control:** Global Category Manager modal accessible from Admin Studio header and sidebar (`admin/partials/layout_top.php`), allowing instant category addition, renaming, and deletion.

### 5. Visual Gallery

- **Public Entry Point:** `/gallery.php` renders curated computing artifacts, hardware systems, and architectural visuals.
- **Data Access:** Queries `achievement_images` joined with `posts` table, restricted to published, non-deleted parent posts.
- **Integrity Rule:** If no relational achievement images exist in the database, an honest empty state is rendered.
- **Admin Control:** Managed directly via `admin/media.php` and the project gallery manager in `admin/achievements.php`.

### 6. Curated Homepage Showcase

- **Public Entry Point:** `/index.php` (`#showcase` rail).
- **Data Access:** Resolves item IDs configured in `site_settings` under key `showcase.item_ids` and loads matching published database projects/articles and authentic lab experiments.
- **Integrity Rule:** Fabricated cards (e.g. hardcoded "Visual Archive" with `geometric_structure_3d.png`) removed. When zero showcase items are configured or published, an honest empty state is displayed.
- **Admin Control:** `admin/showcase.php` allows the site owner to drag, select, and prioritize published content for the homepage rail.

### 7. Platform Profile & Bio

- **Public Entry Point:** Header branding, `/index.php` hero identity, `/about.php` dossier, and author cards on `/post.php`.
- **Data Access:** `getSiteProfile()` helper in `includes/settings.php` reading from `site_settings` with file-cache fallback (`api/data/settings_cache.json`).
- **Integrity Rule:** Public views never expose internal administrative user accounts (`users.email` is protected).
- **Admin Control:** `admin/settings.php?tab=profile` with direct CMS image upload updating `site_settings` (`profile.avatar_url`) via secure upload pipeline (`upload_helper.php`).

### 8. Engineering Journey (Future Roadmap)

- **Public Entry Point:** `/journey.php`.
- **Current State:** Fabricated demo stages (`Stage 01 2021` - `Stage 04 2024`, `ERA 01` - `ERA 03`) removed. Displays an honest empty state ("No journey milestones published yet").
- **Required Admin Extension for Future Implementation:**
  1. Storage: A dedicated table `journey_milestones` (`id`, `year`, `stage_number`, `title`, `description`, `paradigm_vector`, `sort_order`, `created_at`) or a clean JSON store `api/data/journey_milestones.json`.
  2. API Endpoints: `api/journey/list.php`, `api/journey/save.php`, `api/journey/delete.php`.
  3. Admin UI: An admin management panel in Admin Studio under `CONTENT` allowing the owner to author authentic milestone entries.

### 9. Social Channels

- **Public Entry Point:** Official social icons in `includes/footer.php` and contact links on `/about.php`.
- **Data Access:** PDO query fetching active links from `social_links` table ordered by `sort_order`.
- **Admin Control:** `admin/social.php` provides full control over platform URLs, active flags, and display order.

### 10. Visitor Reviews

- **Public Entry Point:** Public review list and submission form on `/post.php?id={id}`.
- **Data Access:** PDO query fetching approved reviews from `reviews` table (`status = 'approved'`).
- **Security & Integrity:** Rate-limited submissions (`api/reviews/rate_limit.php`), honeypot spam protection, soft-delete exclusion, and mandatory admin approval.
- **Admin Control:** `admin/reviews.php` moderation dashboard for approving, rejecting, or deleting incoming feedback.

