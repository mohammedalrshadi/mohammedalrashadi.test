# Platform Architecture (Target — Proposed)

**Status:** PROPOSED architecture for future evolution. Nothing in this document has been implemented. No table, API route, or admin page described below as "future" exists yet.
**Verification legend:** **EXISTING+VERIFIED** (real, confirmed this phase or earlier), **EXISTING+UNVERIFIED** (real but not confirmed), **PROPOSED** (design for later, not built).
**Companion documents:** `docs/DATABASE.md` (current verified schema), `docs/TERMINOLOGY.md` (naming audit), `docs/ENGINEERING_AUDIT.md` (original findings)

## Why this document exists

The current platform is a personal site with 6 content features. The stated direction is a personal *platform* — potentially adding a store, user accounts, purchases, and reading/progress tracking. Retrofitting those onto an architecture that wasn't designed for them is expensive. This document proposes domain boundaries now, while the surface area is still small, without building anything the current requirements don't justify — per the explicit instruction, this is a model, not a build order.

## Domain model

Four domains, each with a distinct reason to exist:

```
CORE            — identity, auth, and things every other domain depends on
CONTENT         — what the site publishes (existing today)
STORE           — digital catalog & free downloads (implemented)
USER PLATFORM   — personal user dashboard, library, bookmarks, likes, history (implemented)
```

### Core — EXISTING+VERIFIED, mostly

| Concern | Current table | Status |
|---|---|---|
| Accounts/auth | `users` | Real, verified |
| Site identity | `site_profile`, `site_settings` | Real, verified this phase |
| Migration state | `schema_migrations` | Real, new this phase |
| Security state | rate-limit JSON files (not DB) | Real, file-based by design (see `docs/SECURITY.md` SEC-07/08) |

**No change proposed here.** This domain is small and coherent already.

### Content — EXISTING+VERIFIED

| Concern | Current table(s) | Owns its own data? |
|---|---|---|
| Writing + Projects | `posts` (discriminated by `type`) | Yes |
| Gallery | `project_images` | Yes, but FK-scoped to `posts` (see Relationships below) |
| Journey | `journey_milestones` | Yes |
| About | `about_content_blocks` + `site_profile` | Yes |
| Home presentation | `home_showcase_items` | **References** content (posts, products, images), does not own it |
| Lab | `api/data/lab_experiments.json` | Yes, but not in the database at all (H-4, unresolved) |

This domain is real and follows the single-source-of-truth principle strictly: `home_showcase_items.reference_id` references `products` (when `item_type='product'`) or `posts` (when `item_type='project'` or `'writing'`), `post_id` is preserved for backward compatibility, and standalone visual items store their own `image_url`, `alt_text`, and optional `link_url`. It never duplicates the source entity's master record.

**Important Architectural Distinction: Home Showcase vs. Gallery:**

- **Home Showcase** is a curated, mixed-content presentation rail directly below the home hero. An administrator manually curates what appears and in what order across four core content types: Products (`products.id`), Images (`image_url`), Projects (`posts.id` where type='achievement'), and Writing (`posts.id` where type='blog').
- **Gallery** (`project_images`) is the dedicated visual portfolio/media gallery associated with projects. Home Showcase is NOT the Gallery and does not replace it.

**One proposed change, not applied:** if Lab ever needs relational integrity with the rest of Content (e.g., a Lab experiment referencing a Writing post, or appearing in the same search/showcase system on equal footing), it needs a real table. Today it doesn't need one — it's read by 4 call sites, has no relationships to anything else, and moving it is a real migration with no current justification. Flagged, not scheduled.

### Store — PROPOSED, does not exist
### Store MVP — IMPLEMENTED (Catalog & Product Discovery)

Modeled per the required transaction chain:
The Store discovery layer is implemented as a lightweight catalog section:

```
Website
   ↓
Store (/store)
   ↓
Product Page (/store/{slug})
   ├── Free Download → Direct secure download (/api/products/download.php?id={id})
   │
   └── External Product → Outbound CTA to external platform (Gumroad, ThemeForest, etc.)
```

**Architecture & Components:**

- **Data Layer:** `products` table (see `docs/DATABASE.md`, `database/migration_store_products.sql`).
- **Product Types:**
  - `free_download`: Digital assets (PDF, ZIP) stored in protected `uploads/products/downloads/` with `.htaccess` `Require all denied` and delivered safely through `api/products/download.php`.
  - `external`: Curated digital goods sold on external platforms.
- **Admin Management:**
  - Directory: `admin/store.php` (search, category filter, status tabs, publish toggle, deletion).
  - Editor: `admin/store-edit.php` (dynamic fields by product type, auto-slug, thumbnail upload, asset upload, rich text description with `ArticleHtmlSanitizer`).
- **API Endpoints:**
  - `api/products/list.php` (public & admin queries, pagination, search, category filtering)
  - `api/products/create.php` (admin POST with CSRF and validation)
  - `api/products/update.php` (admin POST with CSRF and validation)
  - `api/products/delete.php` (admin POST with file cleanup)
  - `api/products/upload.php` (admin image/thumbnail and protected PDF/ZIP upload)
  - `api/products/download.php` (secure, path-traversal-resistant streaming delivery)
- **Public Presentation:** `store.php`, `product.php` with responsive design, light/dark theme support, and dynamic XML sitemap integration (`sitemap.php`).

---

### Internal Commerce — PROPOSED, NOT IMPLEMENTED (Future Phase)

Internal checkout and payments are explicitly out of scope for the Store MVP. If the platform ever evolves to process purchases natively, the conceptual transaction chain below remains the reference target:

```
Product
  ↓
Order ──── one order, many items
  ↓
Order Item ──── references a Product at time of purchase
  ↓
Payment ──── one or more payment attempts per Order
  ↓
Entitlement ──── what the payment actually grants access to
  ↓
User Library ──── the user-facing view of their Entitlements
```

Why each table exists, not just that it exists:
Why each table would exist if built:

- **`Product`** owns catalog data (name, price, description, files). A `product_files` child table (one-to-many) rather than a column, because a product may ship multiple downloadable assets and that's a real one-to-many relationship, not a convenience denormalization.
- **`Order`** is the transaction envelope: one row per checkout, owned by a `user_id`.
- **`Order Item`** is deliberately a *snapshot* — it copies the product's name/price at time of purchase rather than only holding a `product_id` FK. This is the one place in this whole document where copying data instead of referencing it is correct: if a product's price changes next month, last month's order must still show what was actually paid. That's not duplication for convenience, it's an audit requirement.
- **`Payment`** is separate from `Order` because a single order can have multiple payment attempts (failed card, retry) — collapsing them into the Order row would lose that history.
- **`Entitlement`** is the real answer to "does this user have access to this product," decoupled from *how* they got it (a purchase today; possibly a free grant, a bundle, or a subscription later — none of which should require restructuring this table, only adding a `source` column).
- **`User Library`** is not a table — it's a *query* over `Entitlement` joined to `Product`. Building it as its own table would be the exact "second source of truth" problem this document's Single Source of Truth section forbids.
- **`Order`** would be the transaction envelope: one row per checkout, owned by a `user_id`.
- **`Order Item`** would be a *snapshot* — copying product name and price at time of purchase.
- **`Payment`** would track payment attempts independently.
- **`Entitlement`** would decouple access rights from the purchase mechanism.
- **`User Library`** would be a presentation query over entitlements.

**Nothing here is built.** No requirement currently justifies it (no product to sell yet). Documented so that if/when a Store becomes real, the shape is decided before the first line of code, not discovered halfway through.
**None of these commerce tables are built.** The current platform operates strictly as a discovery catalog.

### User Platform — IMPLEMENTED (Dashboard & Personal Experience)

```
User (Core)
  ├─ Profile (user_profiles)          — bio, avatar, interface & email preferences
  ├─ Library (user_library)           — claimed digital resources, direct tool access
  ├─ Reading History (reading_history) — (user, content) last viewed timestamp & scroll progress
  ├─ Bookmarks (bookmarks)            — saved reading list with content-type filters
  ├─ Likes (likes)                    — content appreciation with public counters
  ├─ Downloads (user_downloads)       — historical immutable download trail (ON DELETE SET NULL)
  └─ Activity (user_activities)       — append-only chronological interaction audit log
```

**Architecture & Components:**

- **Data Layer:** 7 tables (`user_profiles`, `bookmarks`, `likes`, `reading_history`, `user_downloads`, `user_library`, `user_activities`) defined in `database/migration_user_platform.sql`.
- **Single Source of Truth:** Content metadata is resolved dynamically via `api/user/content_resolver.php` from `posts` (for `writing` and `project`), `products` (for `product`), and `api/data/lab_experiments.json` (for `lab`). Interaction tables store relationships, never duplicate content.
- **Canonical Content Types:** Public User Platform content types are strictly:
  - `writing`: `posts WHERE type = 'blog'`
  - `project`: `posts WHERE type = 'achievement'` (live database retains `type ENUM('blog','project','achievement')`; public projects filter strictly on `type = 'achievement'`)
  - `lab`: `api/data/lab_experiments.json` (polymorphic alphanumeric ID support: `VARCHAR(64)`)
  - `product`: `products` catalog
  - *Invariant:* `post` is never exposed or accepted as a public content type. All mutations validate content existence and type match before execution.
- **Strict Data Isolation (Anti-IDOR):** Every interaction and dashboard endpoint strictly validates `WHERE user_id = ?` with the authenticated session's user ID.
- **Dashboard Workspace:** 9 responsive pages under `/dashboard/`:
  - `index.php` (Overview metrics, quick access, reading trail, activity)
  - `library.php` (Claimed toolkits & resources with re-download triggers)
  - `bookmarks.php` (Curated reading list with content-type filter pills and AJAX removal)
  - `downloads.php` (Historical download ledger with direct secure access)
  - `history.php` (Reading history trail with clear history capability)
  - `likes.php` (Liked technical essays, projects, and benchmarks)
  - `activity.php` (Chronological timeline of personal platform interactions)
  - `profile.php` (Display identity, avatar preview, background bio)
  - `settings.php` (Theme & language preferences, password change, guarded account deletion)
- **Public Integration:** Like & Bookmark buttons with live counts and reading history tracking integrated into `post.php`, `project.php`, `lab-detail.php`, and `product.php` via `assets/js/user_interactions.js`.
- **Context-Aware Homepage Workspace:** The `includes/home_workspace.php` component acts as a dynamic 12-column grid dashboard surface on the homepage, rendering completely on the server-side (`PHP`) without client-side hydration or redirects. It adapts to three distinct states:
  - **Guest:** Displays Latest Feed and 'Now' block.
  - **New Signed-in User:** Displays 'Start Here' modules, Latest Feed, and empty states for Library/Saved.
  - **Returning Signed-in User:** Replaces the standard hero with a compact workspace header, surfacing 'Continue Reading', 'Saved', 'Recently Viewed', and 'Latest Updates' based on real user activity via `api/user/home_feed.php`.

---

## Relationships — the actual test applied to each one

Per requirement 7, every relationship below was asked: why does it exist, who owns the data, what happens if the referenced side changes, is it required or optional.

| Relationship | Exists today? | Required or optional | Reasoning |
|---|---|---|---|
| `home_showcase_items.reference_id → products.id` / `posts.id` | Yes | Optional (`reference_id` is nullable) | Generic reference to `products.id` (when `item_type = 'product'`) or `posts.id` (when `item_type = 'project'` or `'writing'`). Indexed via `idx_home_showcase_reference`. |
| `home_showcase_items.post_id → posts.id` | Yes | Optional (`post_id` is nullable) | Preserved for backward compatibility with legacy rows and queries |
| `user_profiles.user_id → users.id` | Yes | Required (PK & FK ON DELETE CASCADE) | 1-to-1 extension of user account identity and preferences |
| `bookmarks.user_id → users.id` | Yes | Required (FK ON DELETE CASCADE) | Personal saved items tied to account |
| `likes.user_id → users.id` | Yes | Required (FK ON DELETE CASCADE) | Personal content likes tied to account |
| `reading_history.user_id → users.id` | Yes | Required (FK ON DELETE CASCADE) | Personal reading trail |
| `user_downloads.user_id → users.id` | Yes | Required (FK ON DELETE CASCADE) | Download audit trail |
| `user_downloads.product_id → products.id` | Yes | Optional (FK ON DELETE SET NULL) | Preserves historical download records even if catalog product is deleted |
| `user_library.user_id → users.id` | Yes | Required (FK ON DELETE CASCADE) | User access to resources |
| `user_library.product_id → products.id` | Yes | Required (FK ON DELETE CASCADE) | Links resource access to master product catalog |
| `user_activities.user_id → users.id` | Yes | Required (FK ON DELETE CASCADE) | Append-only audit log tied to user |
| `reviews.post_id → posts.id` | **No FK exists in production** (confirmed, `docs/ENGINEERING_AUDIT.md` finding, re-confirmed via the dump this phase) | Should probably be required | A review with no post is meaningless. This is existing+incorrect, not proposed — a real data-integrity gap, deferred to a future phase per the existing roadmap, not fixed by this document |
| *(proposed)* `order_items.product_id → products.id` | No | Required, but denormalized (see Store section) | Order integrity needs the FK, but also needs the snapshot — both, not either/or |
| *(proposed)* `payments.order_id → orders.id` | No | Required | Payment attempt tied to order |

**Relationship deliberately NOT proposed:** anything connecting Store or User Platform tables directly to Content tables like `journey_milestones` or `about_content_blocks`. There's no real domain reason a purchase or a bookmark needs to know about the About page.

## Admin structure — target model (proposed)

```
Admin
├── Overview                    (EXISTING — admin/index.php)
├── Content
│   ├── Projects                (EXISTING — admin/achievements.php)
│   ├── Writing                 (EXISTING — admin/articles.php)
│   ├── Gallery                 (EXISTING — admin/media.php)
│   ├── Journey                 (EXISTING — admin/journey.php)
│   └── Lab                     (EXISTING — admin/labs.php)
├── Store                       (PROPOSED — nothing exists)
│   ├── Products
│   ├── Lab                     (EXISTING — admin/labs.php)
│   └── Store                   (EXISTING — admin/store.php)
├── Store Commerce              (PROPOSED — future phase)
│   ├── Orders
│   └── Customer Access
├── Users                       (PROPOSED — nothing exists beyond admin/users.php's basic CRUD)
│   ├── Users                   (EXISTING — admin/users.php, admin accounts only, not customer accounts)
│   ├── Activity                (PROPOSED)
│   └── Access                  (PROPOSED — would surface Entitlement data)
├── Website
│   ├── Profile / About         (EXISTING — folded into admin/settings.php, see docs/TERMINOLOGY.md)
│   ├── Home Showcase           (EXISTING — admin/showcase.php)
│   └── Settings                (EXISTING — admin/settings.php)
│   └── Social Links            (EXISTING but DUPLICATED — admin/social.php + a pane in settings.php; unresolved, see docs/TERMINOLOGY.md)
└── System
    ├── Security                (PROPOSED as a dedicated page — currently spread across docs/SECURITY.md and code, no admin UI)
    ├── Migrations               (EXISTING — admin/run_migrations.php)
    └── Logs                     (PROPOSED — error_log output isn't surfaced in admin anywhere)
```

**What this means right now, concretely:** nothing. The current admin nav (`admin/partials/layout_top.php`) is flat, not grouped into these sections. Regrouping it is a real UI change with real risk of breaking existing muscle-memory navigation for a single-admin site — not something to do as a side effect of writing a planning document. Deferred, per the "postpone" guidance in the final report below.

## API boundaries — target model (proposed)

Current API layout already mostly follows domain boundaries — this is a genuine strength, not something needing a rewrite:

```
EXISTING, already domain-scoped:
  /api/posts/          /api/journey/        /api/reviews/
  /api/project_images/   /api/about_content/   /api/social/
  /api/categories/     /api/labs/           /api/users/
  /api/auth/           /api/telemetry/      /api/seo/
  /api/products/       (Store MVP catalog)
  /api/showcase/       (Home Showcase curation)
  /api/user/           (User Platform profile & settings)
  /api/bookmarks/      (User Platform saved reading list)
  /api/likes/          (User Platform content appreciation)
  /api/history/        (User Platform reading history)
  /api/library/        (User Platform claimed digital resources)
  /api/downloads/      (User Platform download audit trail)
  /api/activity/       (User Platform interaction logs)
  /api/settings/       (catch-all / system config)

PROPOSED, do not exist (future internal commerce):
  /api/orders/         /api/payments/
```

## What Phase 3's continuation deliberately did NOT do

Per the explicit critical rule not to turn this into an uncontrolled rewrite:

- No table was created for Store, User Platform, or any proposed domain.
- No admin page was restructured or regrouped.
- No API endpoint was added, split, or moved.
- No terminology was renamed anywhere (database, API, or UI).
- The `reviews.post_id` FK gap identified above was **documented**, consistent with prior phases, not fixed — it remains a deliberate, deferred decision, not an oversight.
