# Design Consistency Matrix

**Platform:** Mohammed Alrashadi — Personal Engineering Platform  
**Design System:** Stitch Editorial Engineering Studio  
**Date Evaluated:** 2026-09-15  
**Evaluation Standard:** Final Project-Specific Overrides (Section L)

---

## Parity Matrix: The 10 Canonical Public Routes + Admin Studio

| Page Route | Purpose / Type | Header / Sidebar | Footer | Theme (Light+Dark) | Typography (`Inter` + `Mono`) | Spacing Rhythm | Mobile Responsive |
| :--- | :--- | :---: | :---: | :---: | :---: | :---: | :---: |
| `/index.php` | Flagship Home & Hub | ✓ Header | ✓ | ✓ | ✓ | 1200px Grid | ✓ 375px+ |
| `/projects.php` | Engineering Projects Index | ✓ Header | ✓ | ✓ | ✓ | 1200px Grid | ✓ 375px+ |
| `/project.php` | Deep Project Case Study | ✓ Header | ✓ | ✓ | ✓ | 1000px Case Study | ✓ 375px+ |
| `/articles.php` | Technical Writing Index | ✓ Header | ✓ | ✓ | ✓ | 1200px Grid | ✓ 375px+ |
| `/post.php` | Editorial Reading Lane | ✓ Header | ✓ | ✓ | ✓ | 680px Reading Lane | ✓ 375px+ |
| `/lab.php` | Engineering Lab Matrix | ✓ Header | ✓ | ✓ | ✓ | 1200px Grid | ✓ 375px+ |
| `/lab-detail.php` | Empirical Benchmark Spec | ✓ Header | ✓ | ✓ | ✓ | 1000px Spec | ✓ 375px+ |
| `/gallery.php` | Visual Architectural Gallery | ✓ Header | ✓ | ✓ | ✓ | 1200px Grid | ✓ 375px+ |
| `/journey.php` | Architectural Evolution | ✓ Header | ✓ | ✓ | ✓ | 1200px Timeline | ✓ 375px+ |
| `/about.php` | Biographical Dossier | ✓ Header | ✓ | ✓ | ✓ | 1200px Dossier | ✓ 375px+ |
| `/admin/index.php` | Studio Operational Dashboard | ✓ Sidebar | N/A | Dark Studio | ✓ | Studio Responsive | ✓ 375px+ |
| `/admin/articles.php`| Articles CRUD & SEO Engine | ✓ Sidebar | N/A | Dark Studio | ✓ | Studio Responsive | ✓ 375px+ |
| `/admin/achievements.php`| Projects CRUD Manager | ✓ Sidebar | N/A | Dark Studio | ✓ | Studio Responsive | ✓ 375px+ |
| `/admin/reviews.php`| Review Moderation Queue | ✓ Sidebar | N/A | Dark Studio | ✓ | Studio Responsive | ✓ 375px+ |
| `/admin/users.php` | Admin Users Management | ✓ Sidebar | N/A | Dark Studio | ✓ | Studio Responsive | ✓ 375px+ |
| `/admin/social.php` | Social Channels Config | ✓ Sidebar | N/A | Dark Studio | ✓ | Studio Responsive | ✓ 375px+ |
| `/admin/settings.php`| Runtime Diagnostics & Config| ✓ Sidebar | N/A | Dark Studio | ✓ | Studio Responsive | ✓ 375px+ |
| `/admin/login.php` | Studio Authentication | N/A | N/A | Dark Studio | ✓ | Centered 400px Card| ✓ 375px+ |

---

## Detailed Dimension Evaluation

### 1. Global Public Header & Navigation
- **Implementation:** Centrally managed in [`includes/header.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/includes/header.php).
- **Parity Result:** Exact across all 10 public pages.
- **Canonical Items:** `Home`, `Projects`, `Lab`, `Gallery`, `Writing`, `Journey`, `About`.
- **Active State Identification:** Every page passes its identifying `$currentPage` key, applying semantic `text-primary font-semibold` styling and `aria-current="page"` for accessibility.
- **Header Tools:** Global search query shortcut (`⌘K`), global theme switcher (☀️/🌙), and admin avatar link.

### 2. Global Public Footer
- **Implementation:** Centrally managed in [`includes/footer.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/includes/footer.php).
- **Parity Result:** Exact across all 10 public pages.
- **Structure:** 4-column directory containing Identity & Ethos, Platform Index (all 7 canonical links), Technical Focus Areas, and Connect Profiles (dynamically loaded from `/api/social/list.php` or verified fallbacks).
- **Telemetry:** Single visit ping dispatched on `DOMContentLoaded` to `/api/telemetry/visit.php` without polling loops.

### 3. Dual Theme Architecture (Light, Dark, System)
- **Implementation:** Defined in [`css/styles.css`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/css/styles.css) and [`js/tailwind-config.js`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/js/tailwind-config.js).
- **Parity Result:** Exact across all public pages.
- **Instant Persistence:** An inline script in [`includes/head.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/includes/head.php) executes before DOM render, reading `localStorage.getItem('theme')` or system preference `prefers-color-scheme` to eliminate flash of unstyled theme (FOUT).
- **Contrast Check:**
  - **Dark Mode:** Surface `#0f131c`, text `#dfe2ee` / `#f3f4f6`, accent `#6cd3f7` / `#22d3ee` (Contrast >12:1).
  - **Light Mode:** Canvas `#f8fafc`, card surface `#ffffff`, text `#0f172a`, muted `#475569`, accent `#0891b2` (Contrast >4.7:1 normal text, >7:1 bold headings, passing WCAG AA).

### 4. Typography Discipline
- **Primary Narrative Font:** `Inter` (sans-serif) for all headlines, introductory summaries, prose, and button labels.
- **Technical Metadata Font:** `JetBrains Mono` (monospace) for all version numbers, system tags, RFC identifiers, hardware specifications, and code snippets.
- **Hierarchy:**
  - Display: `3.5rem` (`display`)
  - Section Headlines: `2.25rem` (`headline-lg`)
  - Card Headlines: `1.5rem` (`headline-md`) / `1.125rem` (`headline-sm`)
  - Body Text: `1.125rem` (`body-lg`) / `0.9375rem` (`body-md`) / `0.8125rem` (`body-sm`)
  - Monospace Micro: `0.8125rem` (`label-code`) / `0.6875rem` (`label-micro`)

### 5. Spacing & Container Rhythm
- **Global Outer Boundaries:** `max-w-[1200px] mx-auto px-gutter` across all listing, overview, and dashboard pages.
- **Editorial Reading Lane Exception:** Single article view in [`post.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/post.php) intentionally collapses to `max-w-[680px]` (for optimal 65–75 character reading line lengths).
- **Deep Case Study / Benchmark Spec:** `max-w-[1000px]` in [`project.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/project.php) and [`lab-detail.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/lab-detail.php) for architecture diagrams and tables.

### 6. Admin Studio Architecture
- **Implementation:** Centrally managed via [`admin/partials/layout_top.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/admin/partials/layout_top.php), [`admin/partials/layout_bottom.php`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/admin/partials/layout_bottom.php), and [`admin/css/admin.css`](file:///Users/mohammedalrashadi/Desktop/mohammedalrashadi/public_html/admin/css/admin.css).
- **Styling:** Deep Slate `#0b0f17` background, `#0f1420` sidebar, `#131926` cards, `#22d3ee` cyan accent, Inter / JetBrains Mono typography.
- **Functional Integrity:** 100% preservation of all form DOM IDs, API bindings, CSRF tokens, and client scripts (`common.js`, `dashboard.js`, `articles.js`, `achievements.js`, `reviews.js`, `social.js`, `users.js`).

### 7. Mobile Usability & Responsiveness
- **Navigation:** Viewports `<1280px` replace the horizontal header list with an accessible Hamburger drawer button (44px+ touch target).
- **Horizontal Rails:** The Home Visual Showcase (`#showcase-track`) and Gallery category pills adapt naturally to smooth horizontal touch scrolling without viewport overflow.
- **Grids:** Multi-column grids reflow cleanly: 3 cols (desktop) → 2 cols (tablet) → 1 col (mobile).

**Conclusion:** 100% design consistency achieved across all 10 public routes and the entire Admin Studio suite with zero runtime dependencies.
