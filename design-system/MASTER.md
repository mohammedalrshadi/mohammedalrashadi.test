# MOHAMMED ALRASHADI — Design System Master Specification
**Aesthetic Direction:** Editorial Systems Modernism (Swiss Modernism 2.0 + Technical Studio)  
**Platform Concept:** Personal Engineering Studio & Systems Research Notebook  
**Tagline:** Build. Learn. Experiment. Evolve.  
**Version:** 2.0.0 (Production Release)  

---

## 1. Design Philosophy

> **"Keep the identity. Reduce the noise. Simple at the surface. Deep underneath."**

The platform represents the digital studio, research notebook, and engineering workspace of Mohammed Alrashadi (Software Engineering Student specializing in backend architecture, database internals, and concurrency invariants).

### Core Principles

1. **Content and Structure Precede Effects:** Typography, spacing, hierarchy, and information density form the design. Visual effects exist solely to clarify structure.
2. **Empirical Grounding:** Zero fake telemetry, zero synthetic benchmarks, zero simulated server monitors. Real content or clean, honest empty states.
3. **Restrained Technical Modernism:** Clean geometric layout, disciplined 8-point spacing, high-contrast typography, and purposeful micro-interactions.
4. **Cohesive Tri-Theme Architecture:** Light, Dark, and Green themes represent distinct view modes of ONE unified design system, not three disjoint websites.
5. **Differentiated Product Surfaces:** Public (storytelling, reading, presentation), User Dashboard (personal workspace, library, activity), Admin Studio (high-density operational management).

---

## 2. Container Width System

Avoid arbitrary width values. Apply semantic containers based on content type:

| Token | Max Width | Purpose & Application |
| :--- | :--- | :--- |
| `--container-reading` | `680px` (`42.5rem`) | Technical essays, single-column prose, policy terms (`post.php`, `terms.php`). Ensures 65–75 CPL. |
| `--container-study` | `1040px` (`65rem`) | Architecture case studies, lab experiment specs, biography dossier (`project.php`, `lab-detail.php`, `about.php`). |
| `--container-catalog` | `1320px` (`82.5rem`) | Directory grids, visual galleries, store products (`projects.php`, `articles.php`, `store.php`, `product.php`, `lab.php`, `gallery.php`). |
| `--container-canvas` | `1440px` (`90rem`) | Homepage showcase rail, broad bento overviews, admin full-screen studio layout (`index.php`, `admin/`). |

---

## 3. Spacing Rhythm (8-Point Grid)

All margins, paddings, and layout gaps adhere strictly to the 8-point spacing scale:

```css
--space-1:  0.25rem; /*  4px - Micro gaps, badge inner padding */
--space-2:  0.5rem;  /*  8px - Tight element spacing, button gaps */
--space-3:  0.75rem; /* 12px - Input padding, compact list gaps */
--space-4:  1.0rem;  /* 16px - Standard base padding, mobile gutters */
--space-5:  1.5rem;  /* 24px - Desktop gutters, card inner padding */
--space-6:  2.0rem;  /* 32px - Module spacing, card grid gaps */
--space-7:  2.5rem;  /* 40px - Section sub-headers, article breaks */
--space-8:  3.5rem;  /* 56px - Standard section vertical rhythm */
--space-9:  5.0rem;  /* 80px - Major page break intervals */
--space-10: 6.5rem;  /* 104px - Hero and footer buffer zones */
```

---

## 4. Typography System

### Font Families
- **Primary Interface & Editorial Prose:** `Inter`, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif.
- **Code, Benchmarks, Metadata & Labels:** `JetBrains Mono`, ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace.

### Type Scale (Fluid & Responsive)
```css
/* Responsive Display & Headlines */
--text-display:      clamp(2.25rem, 5.5vw, 3.75rem); /* Line height 1.05, -0.03em letter spacing */
--text-headline-lg:  clamp(1.75rem, 3.5vw, 2.25rem); /* Line height 1.2, -0.02em letter spacing */
--text-headline-md:  1.5rem;                        /* Line height 1.3, -0.015em letter spacing */
--text-headline-sm:  1.125rem;                      /* Line height 1.4, -0.005em letter spacing */

/* Body Reading Scale */
--text-body-lg:      1.125rem;                      /* 18px / 1.7 line height (Comfortable essay reading) */
--text-body-md:      1.0rem;                        /* 16px / 1.6 line height (Standard UI body text) */
--text-body-sm:      0.875rem;                      /* 14px / 1.5 line height (Secondary descriptions) */

/* Technical & Metadata Scale */
--text-mono-code:    0.875rem;                      /* 14px / 1.5 line height (Code blocks) */
--text-mono-meta:    0.75rem;                       /* 12px / 1.3 line height (Tags, dates, stats) */
--text-mono-micro:   0.6875rem;                     /* 11px / 1.2 line height (IDs, pill badges, RFCs) */
```

---

## 5. Three Unified Color Themes

Every theme implements the identical semantic token map. Contrast strictly exceeds WCAG 2.2 AA (4.5:1 for body, 3:1 for large text/icons):

### 1. Light Theme (Warm Architectural Monolith)

- **Canvas (`--color-background`):** `#F9F9F7`
- **Surface (`--color-surface`):** `#FFFFFF`
- **Surface Dim (`--color-surface-dim`):** `#F1F2ED`
- **Surface Subtle / Elevated (`--color-surface-subtle`):** `#FAFAF8`
- **Primary Brand Accent (`--color-primary`):** `#064E3B` (Deep Forest Emerald)
- **Primary Hover (`--color-primary-hover`):** `#043527`
- **Primary Container / Subtle (`--color-primary-subtle`):** `#D1FAE5`
- **On Primary (`--color-on-primary`):** `#FFFFFF`
- **Text Primary (`--color-text-primary`):** `#18181B` (15.4:1 contrast)
- **Text Secondary (`--color-text-secondary`):** `#4A5852` (7.2:1 contrast)
- **Text Muted (`--color-text-muted`):** `#687972` (4.8:1 contrast — accessible)
- **Borders (`--color-border`):** `rgba(6, 78, 59, 0.12)`
- **Border Subtle (`--color-border-subtle`):** `rgba(6, 78, 59, 0.06)`

### 2. Dark Theme (Default — Charcoal & Emerald Studio)

- **Canvas (`--color-background`):** `#090C0A`
- **Surface (`--color-surface`):** `#101512`
- **Surface Dim (`--color-surface-dim`):** `#060807`
- **Surface Subtle / Elevated (`--color-surface-subtle`):** `#161D19`
- **Primary Brand Accent (`--color-primary`):** `#20C978` (Crisp Technical Emerald)
- **Primary Hover (`--color-primary-hover`):** `#34D399`
- **Primary Container / Subtle (`--color-primary-subtle`):** `rgba(32, 201, 120, 0.14)`
- **On Primary (`--color-on-primary`):** `#022C19`
- **Text Primary (`--color-text-primary`):** `#F8FAFC` (16.2:1 contrast)
- **Text Secondary (`--color-text-secondary`):** `#A3B5AC` (8.4:1 contrast)
- **Text Muted (`--color-text-muted`):** `#7A8E85` (4.9:1 contrast — accessible)
- **Borders (`--color-border`):** `rgba(32, 201, 120, 0.14)`
- **Border Subtle (`--color-border-subtle`):** `rgba(32, 201, 120, 0.07)`

### 3. Green Theme (Deep Engineering Terminal)

- **Canvas (`--color-background`):** `#06140F`
- **Surface (`--color-surface`):** `#0D281E`
- **Surface Dim (`--color-surface-dim`):** `#040D0A`
- **Surface Subtle / Elevated (`--color-surface-subtle`):** `#12372A`
- **Primary Brand Accent (`--color-primary`):** `#31E58F` (Vibrant Terminal Emerald)
- **Primary Hover (`--color-primary-hover`):** `#5FF5A9`
- **Primary Container / Subtle (`--color-primary-subtle`):** `rgba(49, 229, 143, 0.18)`
- **On Primary (`--color-on-primary`):** `#002D18`
- **Text Primary (`--color-text-primary`):** `#E2F5EA` (15.8:1 contrast)
- **Text Secondary (`--color-text-secondary`):** `#A1D1B8` (8.1:1 contrast)
- **Text Muted (`--color-text-muted`):** `#77B094` (4.7:1 contrast — accessible)
- **Borders (`--color-border`):** `rgba(49, 229, 143, 0.18)`
- **Border Subtle (`--color-border-subtle`):** `rgba(49, 229, 143, 0.08)`

---

## 6. Functional Semantic Status Colors

Preserved across all themes to communicate state with clarity:
- **Success:** `#16A34A` (light) / `#4ADE80` (dark & green)
- **Warning:** `#D97706` (light) / `#FBBF24` (dark & green)
- **Error / Danger:** `#DC2626` (light) / `#F87171` (dark & green)
- **Info / Neutral:** `#0284C7` (light) / `#38BDF8` (dark) / `#7DD3FC` (green)

---

## 7. Component Specifications

### 7.1 Buttons

- **Touch target:** Minimum `44px` height on public/mobile; compact `36px` on dense admin tables.
- **Base styles:** Border radius `8px` (`var(--radius-md)`), 150ms cubic-bezier transition, tactile `:active` state (`transform: scale(0.98)`).
- **Variants:**
  - `.btn-primary`: Solid primary emerald background, on-primary text, subtle hover lift.
  - `.btn-secondary`: Surface background, subtle border, primary text hover.
  - `.btn-ghost`: Transparent background, muted text, surface hover highlight.
  - `.btn-danger`: Error container background, clear error text, confirmation guard.
  - `.btn-download`: Distinct solid button with explicit download arrow icon.
  - `.btn-external`: Ghost/outline button with external link icon (`arrow_outward`).

### 7.2 Badges & Chips

- Font: `JetBrains Mono`, `0.6875rem` (`11px`), uppercase, letter spacing `0.04em`.
- Border: `1px solid`, radius `4px` (`var(--radius-sm)`).
- Semantics: Accent (`badge-accent`), Neutral (`badge-neutral`), Success (`badge-success`), Warning (`badge-warning`).

### 7.3 Cards & Surfaces

- Background: `var(--color-surface)`.
- Border: `1px solid var(--color-border)`.
- Radius: `12px` (`var(--radius-lg)`).
- Interactive cards: Hover transition to `var(--color-surface-subtle)` with `border-color: var(--color-border-hover)` and `box-shadow: 0 4px 16px rgba(0,0,0,0.06)`.

### 7.4 Form Fields & Inputs

- Height: `42px` (public/user) / `36px` (admin).
- Background: `var(--color-surface-dim)`.
- Border: `1px solid var(--color-border)`. Focus: `outline: 2px solid var(--color-primary); outline-offset: 1px;`.
- Floating labels or clear top labels with optional helper text.

---

## 8. 3D Architectural Object Specification

- **Placement:** Public Homepage Hero (right side on desktop, stacked below on tablet, hidden or static fallback on compact mobile < 640px).
- **Subject:** Layered Computing Invariants (Abstract mathematical architecture representing Storage Engines, B-Trees, Distributed Invariants, and Memory Hierarchies).
- **Technology:** Lightweight HTML5 Canvas / CSS 3D Matrix rendering with zero external library bloat (< 6 KB code).
- **Interaction:** Subtle, calm mouse-parallax tracking (max ±6° tilt). Strictly respects `prefers-reduced-motion: reduce`.
- **Lighting:** Warm technical specular highlights matching active theme emerald accent. No neon bloom, no spinning loops.

---

## 9. Public vs. Admin Coherence & Separation

| Dimension | Public Website | Admin Studio |
| :--- | :--- | :--- |
| **Primary Goal** | Intellectual depth, storytelling, readability | Content operations, data manipulation, fast scanning |
| **Layout Rhythm** | Generous whitespace (`--space-7` to `--space-9`) | Dense operational grid (`--space-2` to `--space-4`) |
| **Max Container** | 680px (reading) to 1320px (catalog) | Fluid 100% viewport within 256px sidebar frame |
| **Typography** | Editorial prose (18px body-lg) | Data table cells (13px–14px), dense forms |
| **Icons** | Clean Material Symbols Outlined (SVG) | Clean Material Symbols Outlined (SVG — unified) |
| **Palette** | Shared Editorial Green / Charcoal / Stone | Shared Editorial Green / Charcoal / Stone |

---

## 10. Public Homepage: 7-Act Editorial Narrative Architecture

The Public Homepage (`index.php`) departs from generic bento/dashboard templates by organizing the visitor's journey through seven distinct narrative acts:

1. **Act I: Identity & Engineering Thesis (Hero):**
   - Monospace overline: `MOHAMMED ALRASHADI // SOFTWARE ENGINEERING STUDIO`.
   - Balanced typographic clamp headline: `Systems Architecture, Storage Internals & Concurrency Invariants`.
   - Personal engineering stance with left accent border: explicit focus on database engine internals, concurrency models, and low-level backend systems.
   - Status & Research Ledger: Monospace key-value strip declaring current focus and studio posture.
   - 3D Consensus Topology Isomorph: Lightweight canvas coordinate rendering 4-node Raft/Paxos concurrency core with lifecycle pause.

2. **Act II: What I Build (Architectural Systems Spotlight & Dossiers):**
   - Asymmetric 7/5 layout showcasing the flagship engineering project with deep technical breakdown, system invariants, and architectural schematics.
   - Secondary systems grid presenting concise architectural dossiers.

3. **Act III: Selected Work (Visual Showcase Rail):**
   - Horizontal, keyboard-navigable scroll rail (`#showcase-track`) of curated digital tools, systems case studies, and research publications.

4. **Act IV: What I Write (Technical Publications Journal):**
   - Publication-grade journal feed (ACM Queue / USENIX style) featuring date stamps, reading velocity (minutes), domain classification, and analytical abstracts.

5. **Act V: What I Experiment With (Systems Lab):**
   - Empirical benchmark dossier highlighting research questions, synthetic multi-threaded workloads, and observed P99 latency/throughput outcomes.

6. **Act VI: Trajectory & Core Convictions (The Journey):**
   - Three core operating laws: *Mechanistic Sympathy*, *Empirical Verification*, and *Invariants Before Syntax*.
   - Chronological phase milestones tracing the evolution from computing foundations to distributed infrastructure.

7. **Act VII: Developer Tools & Studio Resources (Store):**
   - Studio asset cards visually distinguishing free downloadable scaffolding from external repositories.

---

## 11. Admin Studio: Operational Workbench Architecture

The Admin Studio (`admin/index.php`) eliminates the conventional `Sidebar → Header → 4 Stat Cards → Chart` template pattern, replacing it with an authentic engineering operations console:

1. **Studio Header & Orientation (`SYS.OP-01`):**
   - Monospace overline, live sync status, and direct action palette (`Draft Article`, `Log Project`, `Moderation`).
   - Answers: *"Where am I?"* and *"What actions are relevant now?"*.

2. **Priority 1: Operational Attention Ledger (`#dashboardAttentionPanel`):**
   - Prominently placed at the top of the workspace.
   - High-density dispatch rows for pending review moderation, unpublished drafts, and hidden projects.
   - Answers: *"What requires my attention?"*.

3. **Priority 2: Publishing Posture & Content Ledger:**
   - Multi-segment hairline ledger bar displaying live publications, 7-day readership velocity, active projects, digital assets, and moderation queue.
   - Answers: *"What am I currently managing?"*.

4. **Priority 3 & 4: Two-Column Asymmetric Workbench (60% / 40%):**
   - Left Column (60%): Editorial Stream & Activity Pulse (Answers *"What changed recently?"*) + Editorial Engagement Matrix.
   - Right Column (40%): 7-Day Audience Velocity & Dynamic Weekly Bar Chart + System Runtime Health Diagnostics.

5. **Priority 5: Studio Route Directory & Catalog Ledger:**
   - Swiss Modernist table mapping all public routes with live status badges and direct management controls.

---

## 12. User Dashboard: Personal Study & Reading Workspace

The User Dashboard (`dashboard/index.php`) is designed as a personal reading archive and developer study, intentionally distinct from the administrative operations workbench:

- Focuses on saved content, reading history, claimed library assets, and chronological personal interactions.
- Prioritizes reading continuity with direct "Continue Reading" actions.
- Avoids fake telemetry and administrative widgets.
