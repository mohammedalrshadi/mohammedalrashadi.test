# Comprehensive Visual Design Integration & Pre-Migration Audit

**Project:** MOHAMMED ALRASHADI — Personal Engineering Platform  
**Audit Date:** September 15, 2026  
**Auditor:** Senior Full-Stack Engineer + Frontend Architect + UI/UX Engineer  
**Status:** Completed & Accepted  

---

## 1. Executive Summary

This audit establishes the definitive baseline of the existing application prior to migrating the visual design from the reference package (`stitch_personal_engineering_platform_design_system/`) into the production codebase.

The system is a production-oriented personal engineering platform for Mohammed Alrashadi (Software Engineering Student). The backend, authentication, database, APIs, review moderation, uploads, and application logic are functional. The challenge addressed is visual design fragmentation: old Arabic static HTML files coexisted with modern PHP templates, conflicting CSS frameworks were loaded across entry points, and prototype-generated decorative HUD telemetry contaminated technical identity.

---

## 2. Exhaustive Audit Questions (A through T)

### A. Which file actually renders the public Home page?
`index.php` is the intended modern dynamic Home page. However, `index.html` (an obsolete static Arabic page) is also present in the root directory.

### B. Is `index.html` still used?
**No.** `index.html` is an obsolete artifact from a prior static Arabic version of the site. It contains hardcoded placeholder strings (`TODO_YOUR_NAME`, `TODO_YOUR_BIO`), Arabic RTL orientation, Cairo font styling, and missing image references (`profile.png`). It has zero runtime utility.

### C. Is `index.php` the canonical Home route?
**Yes.** `index.php` connects to the real MySQL database via `api/db.php`, queries real post counts, fetches featured projects (`type = 'achievement'`), loads recent essays (`type = 'blog'`), loads lab benchmark experiments, and includes the unified site chrome (`includes/head.php`, `includes/header.php`, `includes/footer.php`). Every navigation link across the platform links to canonical `.php` routes (`/index.php`, `/projects.php`, `/lab.php`, `/gallery.php`, `/articles.php`, `/journey.php`, `/about.php`).

### D. Are both `index.html` and `index.php` causing conflicts?
**Yes.** On LiteSpeed/Apache servers (such as Hostinger), default server configurations prioritize `index.html` over `index.php` when resolving the root path (`/`). Consequently, visitors requesting the bare domain saw the broken placeholder static HTML rather than the real application.

### E. Which CSS files are loaded by each page?
- **All Canonical Public PHP Pages** (`index.php`, `projects.php`, `project.php`, `articles.php`, `post.php`, `lab.php`, `lab-detail.php`, `gallery.php`, `journey.php`, `about.php`): Include `includes/head.php`, which loads:
  1. Google Fonts: `Inter`, `JetBrains Mono`, `Material Symbols Outlined`
  2. Tailwind CSS CDN (`https://cdn.tailwindcss.com`)
  3. Shared Editorial Configuration (`/js/tailwind-config.js`)
  4. Prototype CSS (`/css/stitch.css`)
- **Legacy Static HTML Pages** (`index.html`, `articles.html`, `achievements.html`): Load `css/styles.css`.
- **Legacy Post HTML** (`post.html`): Loads `css/styles.css` and `css/post.css`.
- **Admin Pages** (`admin/*.php`): Load `admin/css/admin.css` (and `admin/login.php` loads `admin/css/login.css`).

### F. Is `styles.css` still containing old design rules?
**Yes.** `css/styles.css` consists of 1,615 lines of old design rules: Cairo Arabic font imports, warm gold palette tokens (`--accent-color: #efca73`, `--bg-color: #fbf9f4`), `.yellow-badge`, `.card`, and obsolete RTL navigation classes.

### G. Is `stitch.css` still containing prototype-specific rules?
**Yes.** `css/stitch.css` is named after the prototype and contains CSS variables for dual light/dark modes and utility classes, but creates an ambiguous separation of concerns with `styles.css`.

### H. Which JavaScript files are actually used?
- **Active in Public PHP Site:**
  - `js/tailwind-config.js` (Tailwind token map)
  - Inline Zero-Flash Theme Initializer (`includes/head.php`)
  - Inline Navigation & Theme Controller (`includes/header.php`)
  - Inline Telemetry Analytics Ping (`includes/footer.php`)
  - Inline Horizontal Showcase Carousel Controller (`index.php`)
  - Inline Reading Progress, Code Copy, Review AJAX Form (`post.php`)
  - Inline Modal Lightbox Controller (`gallery.php`)
  - Inline Category Filter Controllers (`journey.php`, `lab.php`)
- **Legacy Scripts (Unused by PHP site):**
  - `script.js` (tied to `index.html`)
  - `articles.js` (tied to `articles.html`)
  - `achievements.js` (tied to `achievements.html`)
  - `post.js` (tied to `post.html`)
  - `lang.js` (old language switcher)
  - `nav.js` (old mobile hamburger toggle)
  - `reviews.js` (old reviews loader for static HTML)
- **Active in Admin Panel:**
  - `admin/login.js` (login AJAX)
  - `admin/js/common.js` (CSRF management, session guard, modal dialogs, toasts, image size validation)
  - `admin/js/dashboard.js` (metric sparklines, health stats)
  - `admin/js/articles.js` (article editor, category selectors, image insertion)
  - `admin/js/achievements.js` (project CRUD, multi-image gallery upload)
  - `admin/js/reviews.js` (review status moderation: approve/reject/delete)
  - `admin/js/social.js` (social links table reordering and updates)
  - `admin/js/users.js` (admin user account management)

### I. Which Stitch components have already been migrated?
- Monogram header with canonical navigation and mobile drawer.
- 4-column footer with platform index, focus areas, and live database social links.
- Public Home layout: Hero section structure, horizontal showcase rail, bento directory grid, recent writing list.
- Projects list and project case study structure.
- Engineering lab list and empirical detail layout.
- Visual gallery masonry grid and image preview lightbox.
- Journey chronological milestone stream.
- About personal dossier narrative and principles.

### J. Which Stitch components are still missing?
- **The entire Admin Panel.** Admin pages (`admin/*.php`) still use the legacy Arabic RTL interface, Cairo font, Font Awesome icons, and gold theme (`#dfb256`).
- Unified operational dashboard merging Stitch Dashboard Variant 1 and Variant 2.
- Dedicated genuine Settings control center in Admin.
- Full Light/Dark/System theme consistency across all admin tools.

### K. Which old UI elements are still present?
- Legacy static files (`index.html`, `articles.html`, `achievements.html`, `post.html`).
- Legacy stylesheets (`styles.css` with gold/Cairo, `post.css`).
- Legacy admin CSS (`admin.css`, `login.css`).
- Syntax glitch `w<?php` at line 1 of `journey.php`.

### L. Which elements are fake/decorative telemetry?
- **Home (`index.php`):**
  - `Availability: Academic & Research` in hero CTA
  - Section 6: `LIVE REST ENDPOINTS MONITOR (CORE_API: 200 OK / HEALTH CHECK / MONITOR: ACTIVE)`
  - Cards: `Verified Build`, `Verified Dataset`, `01 / ARCHIVE`, `02 / BENCHMARKS`
  - Featured Deep Dive: `Latency —`, `Throughput —`, `Topology Raft Decoupled`
- **Project Detail (`project.php`):**
  - `SYS-REF: #LGM-409`, `SPECIFICATION DOSSIER`
  - `Latency SLA —`, `Throughput —`
  - `SYSTEM ARCHITECTURE TOPOLOGY // VECTOR 1:1`, `UHD SCHEMA`
- **About (`about.php`):**
  - `OPERATIONAL PROFILE / REF: DOSSIER-MA-2025`
  - `PORTRAIT // MOHAMMED ALRASHADI // ENGINEER`
- **Gallery (`gallery.php`):**
  - `Resolution: 3840 × 2160 UHD`, `Vector 1:1 Schema`, `Empirical Telemetry`

### M. Which data comes from the real API/database?
- Post counts for projects and blog articles (`posts` table).
- Dynamic projects list and details (`posts` table where `type = 'achievement'`).
- Project multi-image attachments (`achievement_images` table).
- Articles list and details (`posts` table where `type = 'blog'`).
- Approved reader reviews (`reviews` table where `status = 'approved'`).
- Dynamic footer social links (`social_links` table where `is_enabled = 1`).
- Analytics session tracking (`api/analytics/ping.php`).
- Admin authentication and user management (`users` table).

### N. Which data is hardcoded?
- Lab experiments (`api/data/lab_experiments.json`, with fallback in `lab.php`).
- Journey timeline eras and milestones (`journey.php`).
- Visual gallery catalog items (`gallery.php`).
- About page biographical narrative and technical stack pills (`about.php`).
- Safe fallbacks in `index.php`, `projects.php`, `articles.php`, `post.php` when MySQL is offline.

### O. Which pages still use old markup?
- `index.html`, `articles.html`, `achievements.html`, `post.html`.
- All admin pages (`admin/index.php`, `admin/articles.php`, `admin/achievements.php`, `admin/reviews.php`, `admin/social.php`, `admin/users.php`, `admin/login.php`, `admin/partials/layout_top.php`, `admin/partials/layout_bottom.php`).

### P. Which admin pages are still using the old dashboard design?
**All of them.** No admin page has been migrated to the Stitch Editorial Engineering Studio aesthetic yet.

### Q. Are there duplicate components/styles?
- `index.html` vs `index.php`
- `articles.html` vs `articles.php`
- `achievements.html` vs `projects.php`
- `post.html` vs `post.php`
- `css/styles.css` vs `css/stitch.css`
- Stitch prototype: `admin_dashboard_mohammed_alrashadi_1` vs `admin_dashboard_mohammed_alrashadi_2`

### R. Are there conflicting CSS variables?
- `styles.css`: `--bg-color: #fbf9f4; --accent-color: #efca73;`
- `stitch.css`: `--color-background: #0f131c; --color-primary: #6cd3f7;`
- `admin.css`: `--bg-main: #fcfbfa; --gold: #dfb256;`

### S. Are there unused files?
`index.html`, `articles.html`, `achievements.html`, `post.html`, `script.js`, `articles.js`, `achievements.js`, `post.js`, `lang.js`, `nav.js`, `reviews.js`, `hello.md` (0 bytes), `api/hi.md` (0 bytes).

### T. Are there production references to the Stitch folder?
**Zero.** There are no runtime imports, iframes, fetches, or links targeting `stitch_personal_engineering_platform_design_system/` in any PHP, JS, CSS, or API file.

---

## 3. Migration Directives Summary

1. **Routing:** Eliminate `index.html` by archiving it to `archive/legacy_static/`. Configure `.htaccess` with `DirectoryIndex index.php`.
2. **CSS:** Refactor `css/styles.css` into the single canonical public design stylesheet. Retire `css/stitch.css` and `css/post.css`.
3. **Telemetry:** Strip all fictional version tags, fake SLA stats, and simulated monitoring banners from public pages.
4. **Admin UI:** Fully migrate the admin panel to the Editorial Engineering Studio design system (slate `#0b0f17`/`#0f1420`, cyan `#6cd3f7`, Inter, JetBrains Mono), merging the two dashboard variants into one unified control center.
5. **Assets:** Ensure all visual assets are self-contained in `/assets/`. Verify complete zero-dependency before deleting the Stitch reference folder.

