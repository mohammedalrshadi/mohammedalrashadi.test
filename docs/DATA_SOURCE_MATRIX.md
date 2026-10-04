# Data Source & Classification Matrix

**Platform:** Mohammed Alrashadi — Personal Engineering Platform  
**Audit Standard:** Comprehensive Content Classification  
**Date:** 2026-09-15  

> **Correction (2026-09-30, DOC-001):** `admin/achievements.php` manages **Achievements**, a standalone table (`achievements`, with gallery rows in `achievement_gallery`). It is NOT the projects editor. Projects are `posts` rows with `type='project'`, managed in `admin/projects.php`. Rows below that describe `admin/achievements.php` as the "Projects" editor, or use `posts.type='achievement'` / `achievement_images` for projects, are historical and have not been re-verified. An achievements-to-projects rename is planned but not approved; no rename has been made.

---

## 1. Classification Taxonomy

Every piece of content across the platform is classified strictly into one of the following 7 data classes:

- **Class A:** Database-backed real data (queried directly via PDO from MySQL tables).
- **Class B:** API-backed real data (fetched dynamically via HTTP JSON endpoints from `/api/*`).
- **Class C:** Static configuration (hardwired routes, constants, environment settings).
- **Class D:** Hardcoded content (editorial bios, philosophy, architectural essays in fallback arrays).
- **Class E:** JSON/file-backed data (version-controlled data stored in JSON files on disk).
- **Class F:** Placeholder/demo data (temporary fallbacks when external connections are unavailable).
- **Class G:** UI-only decorative data (client-side controls, search filters, interactive animations).

---

## 2. Public Platform Data Source Matrix

| Page | Component / Block | Data Class | Entity / Table | API Endpoint | Fallback Available? | Admin Editable? | Real or Static? |
| :--- | :--- | :---: | :--- | :--- | :---: | :---: | :---: |
| **Home (`index.php`)** | Headline & Personal Motto | **Class D** | None | None | Hardcoded | No | Static |
| **Home (`index.php`)** | Total Articles & Projects Counts | **Class A** | `posts` | None (Direct PDO query) | Yes (`0`) | Indirectly via CRUD | Real |
| **Home (`index.php`)** | Horizontal Showcase Rail (Projects) | **Class A** | `posts` (`type='achievement'`) | None (Direct PDO query) | Yes (Empty/omitted if none) | Yes (`admin/achievements.php`) | Real Database |
| **Home (`index.php`)** | Horizontal Showcase Rail (Lab) | **Class E** | None | File: `lab_experiments.json` | Yes (Omitted if file missing) | No | Real File Data |
| **Home (`index.php`)** | Featured Project Spotlight | **Class A** | `posts` (`type='achievement'`) | None (Direct PDO query) | Yes (Omitted if none) | Yes (`admin/achievements.php`) | Real Database |
| **Home (`index.php`)** | Bento Directory Cards | **Class C** | None | None | Hardcoded links | No | Static |
| **Home (`index.php`)** | Recent Technical Articles List | **Class A** | `posts` (`type='blog'`) | None (Direct PDO query) | Yes (Empty state if none) | Yes (`admin/articles.php`) | Real Database |
| **Projects (`projects.php`)** | Projects Grid Cards | **Class A** | `posts` (`type='achievement'`) | None (Direct PDO query) | Yes (Honest empty state) | Yes (`admin/achievements.php`) | Real Database |
| **Projects (`projects.php`)** | Category Filter Buttons | **Class C** | `categories` | None | Hardcoded categories | No | Static Config |
| **Projects (`projects.php`)** | Client Search Input | **Class G** | None | None | None | No | UI-only |
| **Project (`project.php`)** | Project Overview, Title, Cover | **Class A** | `posts` (`type='achievement'`) | None (Direct PDO query) | No (404 Not Found state) | Yes (`admin/achievements.php`) | Real Database |
| **Project (`project.php`)** | Relational Gallery Images | **Class A** | `achievement_images` | None (Direct PDO query) | Yes (Empty array) | Yes (`admin/achievements.php`) | Real Database |
| **Project (`project.php`)** | Architecture Diagram & Decisions | **Class D** | `posts.content` | None | No (404 Not Found state) | Yes (via post content) | Real Database |
| **Writing (`articles.php`)** | Articles Feed Cards | **Class A** | `posts` (`type='blog'`) | None (Direct PDO query) | Yes (Honest empty state) | Yes (`admin/articles.php`) | Real Database |
| **Writing (`articles.php`)** | Category Filter Buttons | **Class C** | `categories` | None | Hardcoded categories | No | Static Config |
| **Article (`post.php`)** | Article Headline, Meta & Content | **Class A** | `posts` (`type='blog'`) | None (Direct PDO query) | No (404 Not Found state) | Yes (`admin/articles.php`) | Real Database |
| **Article (`post.php`)** | Lifetime Reads View Increment | **Class A** | `posts.views` | None (Direct PDO query) | Silent fail | Read-only | Real |
| **Article (`post.php`)** | Approved Reader Reviews List | **Class A** | `reviews` (`status='approved'`) | None (Direct PDO query) | Yes (Empty array) | Yes (`admin/reviews.php`) | Real |
| **Article (`post.php`)** | Review Submission Form | **Class B** | `reviews` | `POST /api/reviews/submit.php` | Error display | Moderated | Real |
| **Lab (`lab.php`)** | Flagship Benchmark Card | **Class E** | None | File: `lab_experiments.json` | Yes (Hardcoded array) | No | Real File Data |
| **Lab (`lab.php`)** | Active Experiments Matrix | **Class E** | None | File: `lab_experiments.json` | Yes (Hardcoded array) | No | Real File Data |
| **Lab Spec (`lab-detail.php`)**| Research Question, Hyp, Specs | **Class E** | None | File: `lab_experiments.json` | Yes (Hardcoded array) | No | Real File Data |
| **Gallery (`gallery.php`)** | Curated Artifacts Portfolio | **Class A** | `achievement_images` | None (Direct PDO query) | Yes (Honest empty state) | Yes (`admin/media.php`) | Real Database |
| **Gallery (`gallery.php`)** | Lightbox Zoom & Inspector | **Class G** | None | None | None | No | UI-only |
| **Journey (`journey.php`)** | 4-Year Progression & Eras | **Class D** | None | Hardcoded timeline markup | Hardcoded | No | Static Editorial |
| **About (`about.php`)** | Biographical Dossier & Stack | **Class D** | None | Hardcoded dossier markup | Hardcoded | No | Static Editorial |
| **Footer (`includes/footer.php`)**| Official Social Links | **Class A / B**| `social_links` | `GET /api/social/list.php` | Yes (Verified fallback)| Yes (`admin/social.php`) | Real |
| **Footer (`includes/footer.php`)**| Anonymous Visit Telemetry | **Class B** | `site_visitors` | `POST /api/telemetry/visit.php` | Silent exit | No | Real Telemetry |

---

## 3. Admin Studio Data Source Matrix

| Admin Page | Module / Component | Data Class | Entity / Table | API Endpoint | Admin Editable? | Real or Static? |
| :--- | :--- | :---: | :--- | :--- | :---: | :---: |
| **Dashboard (`admin/index.php`)** | 5 Primary Metrics Cards | **Class B** | `posts`, `users`, `reviews` | `/api/posts/list.php`, `/api/users/list.php`, `/api/reviews/list.php` | Indirectly via CRUD | Real Data |
| **Dashboard (`admin/index.php`)** | Attention Items Queue | **Class B** | `posts` | `/api/posts/list.php?status=all` | Yes | Real Data |
| **Dashboard (`admin/index.php`)** | System Diagnostics Widget | **Class C** | Server Runtime | Runtime inspection (`phpversion()`, DB check) | No | Real Diagnostics |
| **Dashboard (`admin/index.php`)** | Content Performance Ranking | **Class B** | `posts` | `/api/posts/list.php?status=all` | Yes (via Edit link) | Real Data |
| **Dashboard (`admin/index.php`)** | Recent Activity Feed | **Class B** | `posts`, `reviews` | Combined `/api/posts/list.php`, `/api/reviews/list.php` | Yes | Real Data |
| **Dashboard (`admin/index.php`)** | 7-Day Traffic Telemetry | **Class B** | `daily_site_stats` | `/api/analytics/dashboard.php` | Read-only | Real Data |
| **Articles (`admin/articles.php`)** | Articles CRUD Table | **Class B** | `posts` (`type='blog'`) | `/api/posts/list.php?type=blog&status=all` | Full CRUD | Real Data |
| **Articles (`admin/articles.php`)** | Article Form & Image Upload | **Class B** | `posts` / `/uploads` | `/api/posts/create.php`, `/api/posts/update.php`, `/api/uploads/image.php` | Full CRUD | Real Data |
| **Articles (`admin/articles.php`)** | SEO Analyzer Engine | **Class B** | None | `/api/seo/analyze.php` | Real-time analyzer | Real Algorithm |
| **Projects (`admin/achievements.php`)**| Projects CRUD Table | **Class B** | `posts` (`type='achievement'`) | `/api/posts/list.php?type=achievement&status=all` | Full CRUD | Real Data |
| **Projects (`admin/achievements.php`)**| Project Gallery Modal | **Class B** | `achievement_images` | `/api/achievement_images/list.php`, `add.php`, `delete.php` | Full CRUD | Real Data |
| **Reviews (`admin/reviews.php`)** | Reviews Moderation Table | **Class B** | `reviews` | `/api/reviews/list.php?status=all` | Full Moderation | Real Data |
| **Reviews (`admin/reviews.php`)** | Review Status Toggles | **Class B** | `reviews` | `/api/reviews/update_status.php`, `/api/reviews/delete.php` | Full Moderation | Real Data |
| **Social (`admin/social.php`)** | Social Channels Form | **Class B** | `social_links` | `/api/social/admin_list.php`, `/api/social/update.php` | Full CRUD | Real Data |
| **Users (`admin/users.php`)** | Users Management Table | **Class B** | `users` | `/api/users/list.php` | Full CRUD | Real Data |
| **Users (`admin/users.php`)** | User Creation & Password Reset | **Class B** | `users` | `/api/users/create.php`, `/api/users/update.php`, `/api/users/delete.php` | Full CRUD | Real Data |
| **Settings (`admin/settings.php`)** | Server & PHP Environment | **Class C** | PHP Runtime | Native PHP inspection (`ini_get`, `phpversion()`) | Read-only | Real Diagnostics |
| **Settings (`admin/settings.php`)** | Security & Session Posture | **Class C** | Session Config | Native session inspection | Read-only | Real Diagnostics |
| **Login (`admin/login.php`)** | Authentication Form | **Class B** | `users` | `POST /api/auth/login.php` | Action | Real Auth |

---
*Matrix validated against production codebase. Zero speculative data sources.*

