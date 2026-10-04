---
name: Yggdrasil Terminal
colors:
  surface: '#031713'
  surface-dim: '#031713'
  surface-bright: '#293d38'
  surface-container-lowest: '#00110e'
  surface-container-low: '#0b1f1b'
  surface-container: '#0f231f'
  surface-container-high: '#1a2e29'
  surface-container-highest: '#253934'
  on-surface: '#d1e7e0'
  on-surface-variant: '#bbcabf'
  inverse-surface: '#d1e7e0'
  inverse-on-surface: '#20342f'
  outline: '#86948a'
  outline-variant: '#3c4a42'
  surface-tint: '#4edea3'
  primary: '#4edea3'
  on-primary: '#003824'
  primary-container: '#10b981'
  on-primary-container: '#00422b'
  inverse-primary: '#006c49'
  secondary: '#aacec2'
  on-secondary: '#14362e'
  secondary-container: '#2c4d44'
  on-secondary-container: '#99bdb1'
  tertiary: '#f3be65'
  on-tertiary: '#432c00'
  tertiary-container: '#cb9a45'
  on-tertiary-container: '#4e3400'
  error: '#ffb4ab'
  on-error: '#690005'
  error-container: '#93000a'
  on-error-container: '#ffdad6'
  primary-fixed: '#6ffbbe'
  primary-fixed-dim: '#4edea3'
  on-primary-fixed: '#002113'
  on-primary-fixed-variant: '#005236'
  secondary-fixed: '#c6eade'
  secondary-fixed-dim: '#aacec2'
  on-secondary-fixed: '#002019'
  on-secondary-fixed-variant: '#2c4d44'
  tertiary-fixed: '#ffdeac'
  tertiary-fixed-dim: '#f3be65'
  on-tertiary-fixed: '#281900'
  on-tertiary-fixed-variant: '#604100'
  background: '#031713'
  on-background: '#d1e7e0'
  surface-variant: '#253934'
typography:
  headline-xl:
    fontFamily: Geist
    fontSize: 3.5rem
    fontWeight: '600'
    lineHeight: '1.1'
    letterSpacing: -0.03em
  headline-xl-mobile:
    fontFamily: Geist
    fontSize: 2.25rem
    fontWeight: '600'
    lineHeight: '1.15'
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Geist
    fontSize: 2.25rem
    fontWeight: '600'
    lineHeight: '1.2'
    letterSpacing: -0.02em
  headline-lg-mobile:
    fontFamily: Geist
    fontSize: 1.75rem
    fontWeight: '600'
    lineHeight: '1.25'
    letterSpacing: -0.01em
  headline-md:
    fontFamily: Geist
    fontSize: 1.5rem
    fontWeight: '500'
    lineHeight: '1.3'
    letterSpacing: -0.01em
  headline-sm:
    fontFamily: Geist
    fontSize: 1.25rem
    fontWeight: '500'
    lineHeight: '1.4'
    letterSpacing: 0em
  body-lg:
    fontFamily: JetBrains Mono
    fontSize: 1rem
    fontWeight: '400'
    lineHeight: '1.6'
    letterSpacing: -0.01em
  body-md:
    fontFamily: JetBrains Mono
    fontSize: 0.875rem
    fontWeight: '400'
    lineHeight: '1.5'
    letterSpacing: 0em
  body-sm:
    fontFamily: JetBrains Mono
    fontSize: 0.75rem
    fontWeight: '400'
    lineHeight: '1.5'
    letterSpacing: 0.01em
  label-md:
    fontFamily: JetBrains Mono
    fontSize: 0.75rem
    fontWeight: '500'
    lineHeight: '1'
    letterSpacing: 0.08em
  label-sm:
    fontFamily: JetBrains Mono
    fontSize: 0.6875rem
    fontWeight: '500'
    lineHeight: '1'
    letterSpacing: 0.1em
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  gutter: 1.5rem
  gutter-mobile: 1rem
  margin: 3rem
  margin-mobile: 1.25rem
  space-xs: 0.25rem
  space-sm: 0.5rem
  space-md: 1rem
  space-lg: 1.5rem
  space-xl: 2.5rem
---

## Brand & Style

This design system expresses a disciplined, developer-first dark interface that synthesizes high-end technical instruments with mythological architecture. Inspired by the World Tree (Yggdrasil), it balances organic, rooted deep emeralds against vibrant luminescent mint terminals and radiant copper-gold accents.

The aesthetic fuses technical minimalism, terminal utility, and bespoke horological geometry. It evokes the quiet, focused confidence of a master engineer's precision workbench: calibrated, deliberate, and free of extraneous ornamentation. Visual tension is achieved through ultra-fine 1px borders, localized emerald glows, and strict monospaced data alignment balanced by disciplined, editorial headings.

## Colors

The palette is anchored by deep obsidian emerald tones that ground the interface, punctuated by crystalline mint and brushed copper-gold.

- **Primary (`#10B981` / Mint)**: Used for active states, terminal cursors, execution status, and high-priority data points. Emits a controlled luminescence against dark ground.
- **Secondary (`#0B2E26` / Deep Emerald)**: The structural foundation. Defines surface cards, containment blocks, and secondary elevation layers.
- **Tertiary (`#D4A24C` / Warm Copper-Gold)**: Reserved for horological accents, precision calibration marks, special status indicators, and editorial flourishes.
- **Neutral (`#061A16` / Obsidian Emerald)**: The base environment surface. Provides an abyssal backdrop that heightens contrast without pure black starkness.
- **Text & Muted Tokens**: Primary text renders at `#ECFDF5` for high-legibility crispness; secondary typography adopts `#6EE7B7` at lowered opacities (`0.65`); subtle borders calibrate at `rgba(52, 211, 153, 0.15)`.

## Typography

The typographic engine uses `Geist` for macro-structural titles and `JetBrains Mono` for granular instrumentation.

- **Headlines (`Geist`)**: Rendered tightly tracked with slight negative letter spacing. They provide architectural weight, framing sections like carved runic glyphs translated into clean Swiss modernist forms.
- **Body & Instrumentation (`JetBrains Mono`)**: Strict tabular alignment. All metadata, readouts, coordinates, and continuous prose take this monospaced voice to enforce an engineering-grade terminal rhythm.
- **Micro Labels**: Capitalized with expanded letter tracking (`0.08em` to `0.1em`) for operational status codes, horological dial intervals, and system metrics.

## Layout & Spacing

The layout is built upon a rigid, telemetry-inspired 12-column grid system paired with strict mathematical spacing.

- **Desktop (1024px+)**: 12-column fluid grid bound to a max-width container of `1440px`. Column gutters hold at `1.5rem` with canvas margins set to `3rem`.
- **Tablet (768px - 1023px)**: 8-column layout with `1.25rem` gutters and `2rem` outer padding.
- **Mobile (up to 767px)**: 4-column stack utilizing `1rem` gutters and `1.25rem` outer margins. Dynamic sub-panels compress into single-axis vertical flows.
- **Spacing Rhythm**: Derived strictly from a 4px/8px base. Element padding and component gaps follow the `space-*` tokens, prioritizing deliberate breathing room around dense technical nodes.

## Elevation & Depth

Depth avoids arbitrary dropshadows, relying instead on tonal surface layering, ultra-fine boundary definition, and luminous radial glows.

- **Surface Tiers**:
  - `Base`: Obsidian Emerald (`#061A16`) foundation.
  - `Layer 1 (Card/Container)`: Deep Emerald (`#0B2E26`) with a 1px border of `rgba(52, 211, 153, 0.12)`.
  - `Layer 2 (Floating Instrument/Popover)`: Elevated Emerald (`#0F3B31`) framed by `rgba(52, 211, 153, 0.22)`.
- **Glow Rings & Accents**: Key focal points, active nodes, and analog watch dials project a localized ambient aura using `box-shadow: 0 0 24px -4px rgba(16, 185, 129, 0.25)` or copper-gold equivalents `box-shadow: 0 0 20px -4px rgba(212, 162, 76, 0.2)`.
- **Horological Dials**: Concentric geometric rings rendered with hairline boundaries (`rgba(52, 211, 153, 0.15)`) create perceived depth through dimensional alignment rather than blur.

## Shapes

The shape system employs a disciplined, soft technical radius (`roundedness: 1`). 

- Standard components (buttons, input fields, badges) utilize `0.25rem` corners.
- Major containment vessels and cards scale to `0.5rem` (`rounded-lg`), while floating modals and system trays max out at `0.75rem` (`rounded-xl`).
- High-precision indicators, clock nodes, and terminal status dots retain circular geometry (`rounded-full`), balancing the structured rectangular panels with astronomical circular motifs.

## Components

### Buttons
- **Primary**: Solid mint background (`#10B981`) with deep obsidian text (`#061A16`), `JetBrains Mono` weight 500, uppercase letter-spacing. On hover: subtle ambient glow (`0 0 16px rgba(16, 185, 129, 0.4)`) with no vertical translation.
- **Secondary / Outline**: Deep emerald background (`#0B2E26`) enclosed in a 1px border of `rgba(52, 211, 153, 0.25)`. Text in `#ECFDF5`. Hover shifts border to copper-gold (`#D4A24C`) with matching text tone.
- **Ghost**: Zero background, 1px transparent border, muted mint text. Hover reveals faint emerald fill `rgba(11, 46, 38, 0.5)`.

### Cards & Panels
- Constructed from `#0B2E26` with a hairline `1px` border of `rgba(52, 211, 153, 0.12)`.
- Header regions feature micro monospaced index labels (e.g., `// 01_INITIALIZE`) in muted mint, separated by a 1px horizontal rule.

### Chips & Badges
- Compact padding (`0.25rem` by `0.5rem`), radius `0.25rem`.
- Background in `rgba(16, 185, 129, 0.08)`, border `1px solid rgba(16, 185, 129, 0.3)`. Text styled in `label-sm` monospaced mint.
- Status variants leverage copper-gold accents (`#D4A24C`) for warnings or staging cycles.

### Input Fields
- Monospaced typography with background `#061A16` recessed inside container panels.
- 1px border in `rgba(52, 211, 153, 0.2)`. Focus transition activates a mint border (`#10B981`) and a soft inward glow (`inset 0 0 8px rgba(16, 185, 129, 0.15)`).
- Cursor uses a solid mint block style.

### Lists & Tables
- Borderless rows separated by `1px` hairlines (`rgba(52, 211, 153, 0.08)`).
- Data alignment enforced via tabular figures. Hovering a row casts an ambient fill of `rgba(11, 46, 38, 0.4)`.

### Analog Clock & Telemetry Nodes
- Precision instrumentation widgets featuring fine concentric circles, dial ticks marked at 15-minute / 60-second intervals, and high-tensile copper-gold indicators tracking performance metrics and execution timelines.