# Platform SEO Architecture

This document outlines the SEO architecture and capabilities of the Mohammed Alrashadi engineering platform, reflecting the production-grade upgrades.

## 1. URL Architecture & Normalization

The platform enforces a strict "One Authoritative URL" policy to prevent duplicate content and consolidate indexing signals.

- **Clean URLs**: All core resources are mapped to clean, semantic paths via `.htaccess`.
  - Articles: `/articles/{slug}`
  - Projects: `/projects/{slug}`
  - Labs: `/labs/{id}`
  - Store: `/store/{slug}`
- **Trailing Slash Removal**: Trailing slashes are strictly removed via an HTTP 301 redirect in `.htaccess` (e.g., `/articles/` -> `/articles`).
- **HTTPS Enforcement**: All non-HTTPS traffic is redirected to `https://`.

## 2. Slug Lifecycle & 301 Redirects

The platform actively protects inbound link equity and search indexing using an immutable slug lifecycle.

- **Immutable Slugs**: Slugs are generated once upon publication. If an administrator edits the title of an article or project, the slug is **not** automatically regenerated, preserving the existing URL.
- **`url_redirects` Table**: If a slug must be manually changed, the system automatically inserts a 301 redirect rule into the `url_redirects` table mapping the old path to the new path.
- **Controller Fallbacks**: The frontend controllers (`post.php`, `product.php`, `project.php`, `lab-detail.php`) proactively intercept 404s and query the `url_redirects` table to seamlessly redirect users to the correct location.
- **Legacy URL Support**: Parameterized URLs (e.g., `?id=123`) from earlier versions of the platform are preserved and automatically 301 redirect to their clean `{slug}` equivalent.

## 3. Metadata & Server-Side Rendering (SSR)

The platform utilizes 100% server-side rendered PHP, guaranteeing immediate content crawlability.

- **Database-Driven Meta Tags**: `posts` utilizes `meta_title` and `meta_description` columns for explicit SEO overrides. If empty, the system gracefully falls back to the `title` and a 160-character excerpt of the `content`.
- **Dynamic `<head>` Injection**: The centralized `includes/head.php` generates standard tags, Open Graph (OG), and Twitter Cards dynamically based on controller variables.
- **Canonical Tags**: Every page outputs a strict, self-referential `<link rel="canonical">` mirroring the clean URL.

## 4. Structured Data (JSON-LD)

High-fidelity schema is injected on corresponding entity pages to power rich search results.

- **Articles (`/articles/{slug}`)**: `TechArticle` & `BreadcrumbList`
- **Projects (`/projects/{slug}`)**: `CreativeWork` & `BreadcrumbList`
- **Products (`/store/{slug}`)**: `Product` & `BreadcrumbList` (includes Offer & Availability data)
- **Labs (`/labs/{id}`)**: `Dataset` (represents raw experimental data architecture)

## 5. Dynamic Sitemap (`/sitemap.xml`)

The `sitemap.php` script automatically generates a standard XML sitemap for search engines.

- **Memory-Safe Iteration**: The sitemap uses database cursors (`while($row = fetch())`) rather than buffering records into memory, enabling it to scale to tens of thousands of URLs safely.
- **Data Integrity**: Only items with `status = 'published'` and `deleted_at IS NULL` are included.
- **Change Frequencies**: Weighted effectively (`daily` for homepage, `weekly` for articles/store, `monthly` for static pages).

## 6. Image Optimization Pipeline

- Responsive image delivery is handled by the `responsiveImage()` PHP helper (`includes/image_helper.php`).
- Synchronous extraction of width/height attributes prevents Cumulative Layout Shift (CLS).
- `fetchpriority="high"` is utilized for hero images to optimize Largest Contentful Paint (LCP).
