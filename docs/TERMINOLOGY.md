# Terminology

**Status:** Phase 3 continuation — terminology audit (requirement 11)
**Verification:** VERIFIED FROM REPOSITORY — every mapping below traced to an actual file/array/query, not assumed.

## The mapping

| Public Website | Admin Dashboard | Admin file | Database | API directory | Notes |
|---|---|---|---|---|---|
| Writing | Articles | `admin/articles.php` | `posts` WHERE `type='blog'` | `api/posts/` | Same table as Projects, different `type` filter. Canonical User Platform type: `writing`. |
| Projects | Projects / "Engineering Projects" | `admin/achievements.php` | `posts` WHERE `type='project'` | `api/posts/` | Canonical User Platform type: `project`. Public says "Projects". **Current:** Admin filenames/API dirs/table are named `achievement` or `achievement_images`. **Target:** `project_images`/`projects.php`/`api/project_images`. |
| Lab | Labs | `admin/labs.php`, `admin/lab-edit.php` | **Current:** `api/data/lab_experiments.json` (live) and unused `lab_experiments` SQL table. **Target:** SQL table becomes live in Phase 2. | `api/labs/` | Canonical User Platform type: `lab`. Uses alphanumeric ID (`EXP-xxx`). |
| Store | Store / "Digital Products" | `admin/store.php`, `admin/store-edit.php` | `products` | `api/products/` | Canonical User Platform type: `product`. Digital catalog & downloads. |
| Home Showcase | Showcase / "Home Showcase" | `admin/showcase.php` | `home_showcase_items` | `api/showcase/` | Curated mixed-content strip referencing `products`, `posts`, or standalone `image`. |
| User Platform | Users / User Management | `dashboard/*.php` | `user_profiles`, `bookmarks`, `likes`, `reading_history`, `user_downloads`, `user_library`, `user_activities` | `api/user/`, `api/bookmarks/`, `api/likes/`, `api/history/`, `api/downloads/`, `api/library/`, `api/activity/` | Personal user experience. Strictly canonical content types (`writing`, `project`, `lab`, `product`). Never expose `post`. |
| Gallery | Media / "Media Library" | `admin/media.php` | **Current:** `achievement_images`. **Target:** `project_images`. | `api/achievement_images/` | Table name ties gallery images to "project" domain boundary. |
| Journey | Journey / "Journey Timeline" | `admin/journey.php` | `journey_milestones` | `api/journey/` | Clean 1:1 mapping, no drift |
| About | *(no dedicated admin page)* | Folded into `admin/settings.php` | `about_content_blocks` + `site_profile` | `api/about_content/` + `api/settings/` | Two tables, one API area, one settings-page tab — no dedicated admin surface |
| *(footer, every page)* | "Social Links" | `admin/social.php` **and** a pane inside `admin/settings.php` | `social_links` | `api/social/` | Duplicated admin surface — already flagged as U-3/U-4 in `docs/ENGINEERING_AUDIT.md`, unresolved |

## What this reveals, precisely

**Genuine terminology drift (same concept, different name per layer):**

- Writing/Articles — cosmetic only, no architectural concern.
- Projects/Achievement — the deepest one. Public-facing product language ("Projects") evolved, and the database actually uses `type='project'` now, but the underlying table for gallery images remains `achievement_images` and older admin/API code still uses the word 'achievement'.

**Not terminology drift — a real structural gap:**

- Lab — the table exists but is unused. This isn't a naming inconsistency; it's the one feature not integrated into the persistence layer the rest of the platform uses (already flagged as H-4 in the audit).
- About has no dedicated admin page. Not wrong, but worth deciding deliberately if About content grows.
- Home Showcase has a table and an admin page but no API directory of its own — its read/write logic lives inside the generic `api/settings/` endpoints.

**Not terminology drift — genuine duplication:**

- Social Links has two admin surfaces. This is U-3/U-4, unresolved, and unrelated to naming.

## Target State & Action Plan

**Current (verified):** The database schema and production data already use `type='project'` for Projects. The remaining inconsistency is the table `achievement_images` and the various `achievement` references in older codebase components (admin URLs, variables, API paths).
**Target (approved, not yet applied):** The components should be aligned to use `project_images`, `projects.php`, and `api/project_images/`. The word "achievement" should eventually be reclaimed for a genuinely different concept (like awards and certificates), distinct from Projects.
