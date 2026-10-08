---
name: Student Operating System
colors:
  surface: '#f8f9ff'
  surface-dim: '#cbdbf5'
  surface-bright: '#f8f9ff'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#eff4ff'
  surface-container: '#e5eeff'
  surface-container-high: '#dce9ff'
  surface-container-highest: '#d3e4fe'
  on-surface: '#0b1c30'
  on-surface-variant: '#464555'
  inverse-surface: '#213145'
  inverse-on-surface: '#eaf1ff'
  outline: '#777587'
  outline-variant: '#c7c4d8'
  surface-tint: '#4d44e3'
  primary: '#3525cd'
  on-primary: '#ffffff'
  primary-container: '#4f46e5'
  on-primary-container: '#dad7ff'
  inverse-primary: '#c3c0ff'
  secondary: '#565e74'
  on-secondary: '#ffffff'
  secondary-container: '#dae2fd'
  on-secondary-container: '#5c647a'
  tertiary: '#00505f'
  on-tertiary: '#ffffff'
  tertiary-container: '#006a7c'
  on-tertiary-container: '#93e8ff'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#e2dfff'
  primary-fixed-dim: '#c3c0ff'
  on-primary-fixed: '#0f0069'
  on-primary-fixed-variant: '#3323cc'
  secondary-fixed: '#dae2fd'
  secondary-fixed-dim: '#bec6e0'
  on-secondary-fixed: '#131b2e'
  on-secondary-fixed-variant: '#3f465c'
  tertiary-fixed: '#acedff'
  tertiary-fixed-dim: '#4cd7f6'
  on-tertiary-fixed: '#001f26'
  on-tertiary-fixed-variant: '#004e5c'
  background: '#f8f9ff'
  on-background: '#0b1c30'
  surface-variant: '#d3e4fe'
typography:
  display-lg:
    fontFamily: Manrope
    fontSize: 2.5rem
    fontWeight: '700'
    lineHeight: 3rem
    letterSpacing: -0.025em
  headline-xl:
    fontFamily: Manrope
    fontSize: 2rem
    fontWeight: '700'
    lineHeight: 2.5rem
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Manrope
    fontSize: 1.5rem
    fontWeight: '600'
    lineHeight: 2rem
    letterSpacing: -0.015em
  headline-md:
    fontFamily: Manrope
    fontSize: 1.25rem
    fontWeight: '600'
    lineHeight: 1.75rem
    letterSpacing: -0.01em
  headline-sm:
    fontFamily: Manrope
    fontSize: 1.125rem
    fontWeight: '600'
    lineHeight: 1.5rem
  body-lg:
    fontFamily: Hanken Grotesk
    fontSize: 1.125rem
    fontWeight: '400'
    lineHeight: 1.75rem
  body-md:
    fontFamily: Hanken Grotesk
    fontSize: 0.9375rem
    fontWeight: '400'
    lineHeight: 1.5rem
  body-sm:
    fontFamily: Hanken Grotesk
    fontSize: 0.8125rem
    fontWeight: '400'
    lineHeight: 1.25rem
  label-md:
    fontFamily: Hanken Grotesk
    fontSize: 0.875rem
    fontWeight: '500'
    lineHeight: 1.25rem
  label-sm:
    fontFamily: Hanken Grotesk
    fontSize: 0.75rem
    fontWeight: '600'
    lineHeight: 1rem
    letterSpacing: 0.02em
  code-sm:
    fontFamily: JetBrains Mono
    fontSize: 0.75rem
    fontWeight: '400'
    lineHeight: 1rem
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  gutter: 1.5rem
  gutter-compact: 1rem
  margin: 2rem
  margin-mobile: 1rem
  space-xs: 0.25rem
  space-sm: 0.5rem
  space-md: 1rem
  space-lg: 1.5rem
  space-xl: 2rem
  space-2xl: 3rem
---

## Brand & Style

This design system establishes an intelligent, calm, and executive-grade workstation tailored for students navigating high-stakes academic and career trajectories. Rejecting juvenile gamification, neon accents, and fragmented bento-box clutter, the system embraces a structured modern aesthetic that bridges institutional authority with individual agency.

The design movement combines **Corporate Modern** rigor with **Tactile Minimal** clarity:
- **Atmosphere**: Purposeful, focused, dignified, and clear.
- **Personality**: Intellectually empowering, structured, quiet, and deeply reliable.
- **Visual Principle**: Crisp hairline borders, measured typographic scale, and disciplined whitespace replace heavy dropshadows or arbitrary decoration. Category-specific semantic signals gently ground complex domains (Academics, Skills, Projects, Career) into a cohesive dashboard language.

## Colors

The palette operates around a deep indigo-slate anchor, paired with deliberate semantic domain accents to guide cognitive processing across vast multi-category workflows.

### Foundational Colors
- **Primary (`#4F46E5`)**: Deep Iris. Represents core executive actions, system state indicators, and AI copilot interventions.
- **Secondary (`#0F172A`)**: Slate 900. Deployed for high-contrast typographic anchors, primary headings, and grounding sidebar structures.
- **Neutral (`#64748B`)**: Slate 500. Balanced intermediate tone for descriptive metadata, subtle outlines, and neutral status indicators.
- **Canvas Base**: Pure white (`#FFFFFF`) for elevated cards set against a soft slate canvas (`#F8FAFC`).

### Semantic Domain Palette
Domain accents are applied strictly to badges, edge indicators, section tags, and progress paths:
- **Academic (`#1D4ED8`)**: Deep Royal Blue. Denotes formal coursework, exams, GPA, and institutional records.
- **Learning (`#06B6D4` / `#0E7490`)**: Clean Cyan/Teal. Applied to supplementary study, courses, reading lists, and tutorials.
- **Skills (`#047857`)**: Rich Emerald/Mint. Marks competencies, radar nodes, verifiable proficiencies, and endorsements.
- **Projects (`#6D28D9`)**: Precise Violet/Purple. Frames repositories, portfolio deliverables, architecture blueprints, and hackathons.
- **Career & Opportunities (`#D97706`)**: Warm Amber. Indicates job pipelines, internship postings, applications, and recruiter touchpoints.
- **AI & System Intelligence (`#4F46E5`)**: Focused Iris. Highlights automated roadmaps, heuristic feedback, and recommendations.

## Typography

The typographic hierarchy balances structural precision with effortless readability:
- **Display & Headings (`Manrope`)**: Geometric balance, crisp terminals, and open counters deliver clear sectional architecture across dense modular layouts. Headings utilize tight optical tracking (`-0.01em` to `-0.025em`) to project modern executive clarity.
- **Body & UI Controls (`Hanken Grotesk`)**: Provides sharp text-rendering at small scales across data grids, schedule slots, and metadata tags without introducing visual artifacts.
- **Numbers & Metrics**: Tabular figures (`font-variant-numeric: tabular-nums`) must be activated for GPA trackers, progress percentages, milestone dates, and credit-hour tallies.

## Layout & Spacing

The workstation uses a structured 12-column grid system bounded inside a fluid multi-pane frame:

### Shell & Navigation Architecture
- **Global Rail**: 72px fixed vertical icon rail for primary domain switching (Home, Academics, Skills, Projects, Career, Intelligence).
- **Secondary Panel**: 240px contextual drilldown sidebar for section navigation. Collapses seamlessly on tablet viewports.
- **Canvas Container**: Fluid area with an explicit maximum content boundary of `1600px` to maintain comfortable eye-tracking on ultra-wide desktop monitors.

### Breakpoints & Adaptations
- **Desktop (≥ 1280px)**: 12-column grid, 24px gutters, 32px canvas margins. Multi-column category comparisons sit comfortably side-by-side.
- **Tablet (768px – 1279px)**: 8-column layout, 16px gutters, 20px margins. Contextual sidebars collapse into overlay slide-ins.
- **Mobile (< 768px)**: Single-column linear stack, 16px margins. Bottom navigation bar replaces the vertical rail; multi-step pathways collapse into accordion milestones.

## Elevation & Depth

Visual hierarchy relies on structural hairline separation rather than heavy physical dropshadows, ensuring data-dense screens feel clean and breathable:

- **Border Hierarchy**: Every card, split panel, and dialog utilizes deliberate structural boundaries. Default borders employ `border-slate-200` (`#E2E8F0`), while internal module separators use `border-slate-100` (`#F1F5F9`).
- **Surface Elevation Levels**:
  - `Level 0 (Canvas)`: Flat `#F8FAFC`. Base backdrop.
  - `Level 1 (Panels & Cards)`: Pure `#FFFFFF` with a 1px border (`#E2E8F0`) and subtle ambient dispersion (`0 1px 3px 0 rgba(15, 23, 42, 0.04)`).
  - `Level 2 (Hover & Interactive Cards)`: `0 4px 6px -1px rgba(15, 23, 42, 0.06), 0 2px 4px -2px rgba(15, 23, 42, 0.04)` with border transitioning to `#CBD5E1`.
  - `Level 3 (Modals, Overlays & Command Bars)`: Elevated floating surfaces (`0 20px 25px -5px rgba(15, 23, 42, 0.1), 0 8px 10px -6px rgba(15, 23, 42, 0.04)`) with a 1px border.
- **Glassmorphism / Frosting**: Restricted strictly to persistent navigation top bars and floating utility pills (`backdrop-filter: blur(12px); background: rgba(255, 255, 255, 0.85)`).

## Shapes

The design system enforces a **Soft (`1`)** roundedness profile to maintain a crisp, enterprise-grade atmosphere:
- **Base Components**: Buttons, inputs, and tab segments use a 4px corner radius (`rounded`, `0.25rem`).
- **Containers**: Structural cards, table wrappers, and calendar grids use an 8px radius (`rounded-lg`, `0.5rem`).
- **Modals & Flyouts**: Floating modal shells and dropdown overlays use a 12px radius (`rounded-xl`, `0.75rem`).
- **Tags & Indicators**: Domain badges and status chips use a soft 4px radius (`rounded`) or precise 9999px pills for compact numerical counters only.

## Components

### Buttons
- **Primary**: Solid Iris background (`#4F46E5`), crisp white text, 4px border radius. Hover: `#4338CA`. Active: scale 0.99.
- **Secondary / Outline**: White background, 1px `#E2E8F0` border, `#0F172A` text. Hover: `#F8FAFC` background with `#CBD5E1` border.
- **Domain Ghost**: Transparent surface, domain-tinted text and icon. Hover: 8% domain color fill.

### Mode Switcher (Standard, Easy, Hard, Go)
The ecosystem supports cognitive mode adaptations across an interconnected segmented pill switch:
- **Container**: Slate-100 container (`#F1F5F9`) with 4px inner padding.
- **States**:
  - `Standard`: Balanced default view showing balanced academic, project, and career modules.
  - `Easy`: Simplified interface prioritizing immediate daily tasks, calendar blocks, and reduced cognitive load.
  - `Hard`: High-density dashboard exposing granular metrics, dependency trees, and skill maps.
  - `Go`: High-velocity sprint execution layout emphasizing active assignments, pending applications, and countdown timers.
- **Active Segment**: White background with micro-elevation (`0 1px 2px rgba(0,0,0,0.06)`), 4px radius, `#0F172A` text.

### Domain Category Badges & Chips
- Crisp, low-saturation pill indicators:
  - **Academic**: Pale blue background (`#EFF6FF`), dark blue text (`#1D4ED8`), 1px border (`#DBEAFE`).
  - **Learning**: Cyan background (`#ECFEFF`), cyan text (`#0E7490`), 1px border (`#CFFAFE`).
  - **Skills**: Mint background (`#ECFDF5`), emerald text (`#047857`), 1px border (`#D1FAE5`).
  - **Projects**: Purple background (`#F5F3FF`), violet text (`#6D28D9`), 1px border (`#EDE9FE`).
  - **Career**: Amber background (`#FFFBEB`), amber text (`#D97706`), 1px border (`#FEF3C7`).

### Input Fields & Controls
- **Inputs**: 1px `#E2E8F0` border on white canvas, 4px radius. Focus: border shifts to primary Iris (`#4F46E5`) with an exterior 2px soft ring (`rgba(79, 70, 229, 0.15)`).
- **Checkboxes & Radios**: 16px geometric squares/circles with `#CBD5E1` border. Checked: solid `#4F46E5` with a high-contrast white glyph.

### Cards & Structured Lists
- **Data Cards**: Clean white surface, 1px `#E2E8F0` border, 8px radius. Card headers feature clear eyebrow labels with optional semantic category color dots (6px diameter).
- **List Items**: Border-bottom dividers (`#F1F5F9`). Hover states gently shift row backgrounds to `#F8FAFC`.