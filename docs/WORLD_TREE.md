# Home Page "World Tree" Design (current state)

The Home page (`index.php`, `$currentPage = 'home'`) uses the World Tree look.
The earlier canvas tree engine (`js/world-tree.js`, `js/tree-generator.js`) was
removed and no page loads it. The reference prototype folder `stitch/` was also
removed (still available in git history).

## What is active
- `css/world-tree.css` - loaded on Home only (`includes/head.php`). Contains a
  responsive utility gap-fill (`md:*`, `lg:*`) from before `css/tailwind.css`
  was rebuilt; it is now redundant but harmless.
- `css/new-design.css` - Home-only overrides, all scoped to `body.page-home`
  (class is set server-side in `index.php`, and again by JS in `head.php`).
- `js/world-clock.js` - drives the clock (second hand, digital time, timezone)
  rendered by `includes/home_workspace.php`.
- Themes: `light`, `dark`, `green` (`js/theme-toggle.js`, tokens in `css/styles.css`).

## Tailwind
`css/tailwind.css` is a build artifact. Rebuild it with the project's
`tailwind.config.js` (Tailwind 3.x, `--minify`) whenever new utility classes are
added to PHP/JS files, otherwise they silently have no effect.

## Reduced motion
Keep honoring `prefers-reduced-motion` in any animation added to Home.
