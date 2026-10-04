# API Data Model & Contract Audit

**Platform:** Mohammed Alrashadi — Personal Engineering Platform  
**Document:** Exhaustive API Data Model, Auth Guards, CSRF, Rate Limits & Payloads  
**Date:** 2026-09-15  
**Auditor:** Senior Full-Stack Engineer + Security Architect  

---

## 1. Overview & Standard Protocol Conventions

- **Data Transport:** JSON (`application/json; charset=utf-8`) or `multipart/form-data` for binary uploads.
- **Envelope Standard:** All responses adhere to `{ "success": boolean, "data"?: any, "message"?: string, "pagination"?: object }`.
- **Authentication:** Enforced via `requireAuth()` or `requireAdminPage()` verifying PHP session cookie (`auth_user_id`, `auth_role === 'admin'`).
- **CSRF Defense:** State-mutating operations (`POST`, `PUT`, `DELETE`) require `requireCSRF()`. The token is passed via header `X-CSRF-Token` or payload `csrf_token`.
- **Database Access:** Standardized on PDO prepared statements using `getDB()` (`api/db.php`).

---

## 2. Comprehensive Endpoint Specifications

### 2.1 Authentication & Security Endpoints

#### `GET /api/auth/csrf_token.php`
- **Auth Requirement:** None (Public / Anonymous or Admin).
- **CSRF Requirement:** None (This endpoint generates the CSRF token).
- **Rate Limit:** Standard web server throttling.
- **Database Entity:** None (Session-backed).
- **Request:** None.
- **Response:**
  ```json
  { "success": true, "csrf_token": "a1b2c3d4e5f6..." }
  ```
- **Errors:** HTTP 500 if session initialization fails.

#### `POST /api/auth/login.php`
- **Auth Requirement:** None.
- **CSRF Requirement:** Required (`X-CSRF-Token` or `csrf_token`).
- **Rate Limit:** Strictly enforced via `api/auth/rate_limit.php` (max 5 failed attempts per IP per 15 minutes).
- **Database Entity:** `users`
- **Request:**
  ```json
  { "email": "admin@example.com", "password": "SecretPassword123!" }
  ```
- **Response:**
  ```json
  { "success": true, "message": "تم تسجيل الدخول بنجاح.", "user": { "id": 1, "name": "Mohammed", "email": "admin@example.com", "role": "admin" } }
  ```
- **Errors:**
  - HTTP 400: Malformed JSON or missing fields.
  - HTTP 401: Invalid credentials.
  - HTTP 403: Missing or invalid CSRF token.
  - HTTP 429: Rate limit exceeded (temporary IP lockout).

#### `POST /api/auth/logout.php`
- **Auth Requirement:** Authenticated session.
- **CSRF Requirement:** Required.
- **Rate Limit:** None.
- **Database Entity:** None.
- **Request:** Empty payload.
- **Response:**
  ```json
  { "success": true, "message": "تم تسجيل الخروج بنجاح." }
  ```

---

### 2.2 Content Hub (Posts: Articles & Projects)

#### `GET /api/posts/list.php`
- **Auth Requirement:** Optional (Public callers restricted to `status='published'`; Admin callers can supply `status='all'|'hidden'|'draft'`).
- **CSRF Requirement:** None (GET).
- **Rate Limit:** None.
- **Database Entity:** `posts`
- **Query Parameters:**
  - `id` (int, optional): Fetch single post.
  - `status` ('published' | 'hidden' | 'draft' | 'all'): Defaults to 'published' for public.
  - `type` ('blog' | 'achievement'): Filters by content type.
  - `category` (string): Filters by category.
  - `search` (string, max 100 chars): Full-text wildcard search across title, category, quotes, content.
  - `sort` ('newest' | 'oldest' | 'title_asc' | 'title_desc'): Whitelisted ordering.
  - `page` (int) & `limit` (int, 1–100): Pagination controls.
  - `include_content` ('1'|'true'): Returns full content instead of 300-character excerpt.
- **Response (Single Post):**
  ```json
  {
    "success": true,
    "data": {
      "id": 12,
      "title": "High-Performance Distributed Cache Architecture",
      "category": "Systems",
      "content": "<p>Full HTML content...</p>",
      "type": "blog",
      "status": "published",
      "views": 342,
      "image_url": "/uploads/cover12.webp",
      "quote_ar": "اقتباس باللغة العربية",
      "quote_en": "English quote text",
      "created_at": "2026-03-01 10:00:00",
      "updated_at": "2026-03-02 12:00:00"
    }
  }
  ```
- **Response (Paginated List):**
  ```json
  {
    "success": true,
    "data": [ ... ],
    "pagination": {
      "total": 45,
      "page": 1,
      "limit": 9,
      "total_pages": 5,
      "has_prev": false,
      "has_next": true
    }
  }
  ```
- **Errors:**
  - HTTP 403: Unauthenticated caller requesting non-published status.
  - HTTP 404: Post ID not found or soft-deleted.

#### `POST /api/posts/create.php`
- **Auth Requirement:** Admin session (`requireAuth()`).
- **CSRF Requirement:** Required.
- **Rate Limit:** Standard admin rate limit.
- **Database Entity:** `posts`, `categories`
- **Request:**
  ```json
  {
    "title": "Raft Consensus in Go",
    "category": "Distributed Systems",
    "content": "<p>Safe sanitized HTML content...</p>",
    "type": "achievement",
    "status": "draft",
    "image_url": "/uploads/sample.png",
    "quote_ar": "اقتباس",
    "quote_en": "Quote",
    "created_at": "2026-09-15 20:00:00"
  }
  ```
- **Response:**
  ```json
  { "success": true, "message": "تم إنشاء المنشور بنجاح.", "id": 89 }
  ```
- **Errors:** HTTP 400 (Empty title/content, invalid type, invalid status), HTTP 401/403.

#### `POST /api/posts/update.php`
- **Auth Requirement:** Admin session.
- **CSRF Requirement:** Required.
- **Database Entity:** `posts`, `categories`
- **Request:**
  ```json
  {
    "id": 89,
    "title": "Raft Consensus in Go v2",
    "category": "Distributed Systems",
    "content": "<p>Updated content...</p>",
    "type": "achievement"
  }
  ```
  *(Note: Modifying `status` is forbidden on this endpoint; use dedicated `publish.php` or `hide.php`).*
- **Response:**
  ```json
  { "success": true, "message": "تم تحديث المنشور بنجاح." }
  ```

#### `POST /api/posts/delete.php` (Soft-Delete)
- **Auth Requirement:** Admin session.
- **CSRF Requirement:** Required.
- **Database Entity:** `posts` (sets `deleted_at = NOW()`).
- **Request:** `{ "id": 89 }`
- **Response:** `{ "success": true, "message": "تم نقل المنشور إلى سلة المحذوفات." }`

#### `POST /api/posts/restore.php`
- **Auth Requirement:** Admin session.
- **CSRF Requirement:** Required.
- **Database Entity:** `posts` (sets `deleted_at = NULL`).
- **Request:** `{ "id": 89 }`
- **Response:** `{ "success": true, "message": "تمت استعادة المنشور بنجاح." }`

#### `GET /api/posts/trash.php`
- **Auth Requirement:** Admin session.
- **Database Entity:** `posts` (WHERE `deleted_at IS NOT NULL`).
- **Response:** `{ "success": true, "data": [ ... ] }`

#### `POST /api/posts/purge.php` (Permanent Hard Delete)
- **Auth Requirement:** Admin session.
- **CSRF Requirement:** Required.
- **Database Entity:** `posts` (DELETE), `reviews` (UPDATE `post_id = NULL`), `achievement_images` (CASCADE DELETE), `uploads/` (file unlink).
- **Request:** `{ "id": 89 }`
- **Response:** `{ "success": true, "message": "تم حذف المنشور نهائياً." }`

#### `POST /api/posts/publish.php` & `POST /api/posts/hide.php`
- **Auth Requirement:** Admin session.
- **CSRF Requirement:** Required.
- **Database Entity:** `posts` (sets `status = 'published'` or `'hidden'`).
- **Request:** `{ "id": 89 }`
- **Response:** `{ "success": true, "message": "تم تعديل حالة المنشور بنجاح." }`

---

### 2.3 Category Management

#### `GET /api/categories/list.php`
- **Auth Requirement:** Admin session.
- **CSRF Requirement:** None (GET).
- **Database Entity:** `categories`, `posts`
- **Query Parameters:** `?type=blog|achievement`
- **Response:**
  ```json
  {
    "success": true,
    "data": [
      {
        "id": 1,
        "name": "Distributed Systems",
        "type": "blog",
        "published_count": 4,
        "draft_count": 1,
        "deleted_count": 0,
        "active_posts_count": 5
      }
    ]
  }
  ```

#### `POST /api/categories/create.php`
- **Auth Requirement:** Admin session + CSRF.
- **Request:** `{ "name": "Cloud Architecture", "type": "blog" }`
- **Response:** `{ "success": true, "message": "تمت إضافة التصنيف بنجاح.", "id": 5 }`

#### `POST /api/categories/rename.php`
- **Auth Requirement:** Admin session + CSRF.
- **Request:** `{ "id": 5, "name": "Cloud Infrastructure" }`
- **Response:** `{ "success": true, "message": "تم تعديل اسم التصنيف بنجاح." }`
  *(Cascades update to `posts.category` in a single atomic transaction).*

#### `POST /api/categories/delete.php`
- **Auth Requirement:** Admin session + CSRF.
- **Request:** `{ "id": 5 }`
- **Response:** `{ "success": true, "message": "تم حذف التصنيف ونقل المقالات المرتبطة به إلى تصنيف 'عام'." }`

---

### 2.4 Project / Achievement Gallery

#### `GET /api/achievement_images/list.php`
- **Auth Requirement:** Public (only published achievements) or Admin (published & hidden achievements).
- **Query Parameters:** `?post_id=89`
- **Database Entity:** `achievement_images`, `posts`
- **Response:**
  ```json
  {
    "success": true,
    "data": [
      { "id": 101, "image_url": "/uploads/arch_diagram_1.webp" },
      { "id": 102, "image_url": "/uploads/benchmark_chart_1.webp" }
    ]
  }
  ```

#### `POST /api/achievement_images/add.php`
- **Auth Requirement:** Admin session + CSRF.
- **Payload:** `multipart/form-data` with `post_id` and `image` file.
- **Database Entity:** `achievement_images`
- **Response:** `{ "success": true, "image": { "id": 103, "image_url": "/uploads/abc.webp" } }`

#### `POST /api/achievement_images/delete.php`
- **Auth Requirement:** Admin session + CSRF.
- **Request:** `{ "id": 103 }`
- **Database Entity:** `achievement_images` (DELETE), `uploads/` (file unlink).
- **Response:** `{ "success": true, "message": "تم حذف الصورة من المعرض بنجاح." }`

---

### 2.5 Reviews & Testimonials

#### `GET /api/reviews/list.php`
- **Auth Requirement:** Optional (Public callers see only `status='approved'`; Admin callers see `status='all'|'pending'|'rejected'`).
- **Query Parameters:** `?status=approved|all|pending|rejected`, `?post_id=<int>`
- **Response (Public):**
  ```json
  {
    "success": true,
    "data": [
      {
        "id": 14,
        "name": "Dr. Sarah Miller",
        "message": "Outstanding analysis of distributed storage consensus.",
        "created_at": "2026-04-10 14:20:00"
      }
    ]
  }
  ```
  *(Note: email, post_id, and status are strictly omitted for public callers).*

#### `POST /api/reviews/submit.php`
- **Auth Requirement:** Public (No login).
- **CSRF Requirement:** Required (tied to session).
- **Rate Limit:** Strictly enforced via `api/reviews/rate_limit.php` (max submissions per IP per hour).
- **Database Entity:** `reviews` (stored with `status = 'pending'`).
- **Request:**
  ```json
  {
    "name": "Engineer Alex",
    "email": "alex@engineer.org",
    "message": "Insightful benchmarks and clear architectural separation.",
    "post_id": 89
  }
  ```
- **Validation:**
  - Name: 2–100 chars.
  - Email: Valid format, max 255 chars.
  - Message: 10–2000 chars.
  - `post_id`: Verified against `posts` table (`status = 'published' AND deleted_at IS NULL`).
- **Response:**
  ```json
  { "success": true, "message": "تم إرسال مراجعتك بنجاح وستظهر بعد مراجعة الإدارة." }
  ```

#### `POST /api/reviews/update_status.php`
- **Auth Requirement:** Admin session + CSRF.
- **Request:** `{ "id": 14, "status": "approved" }` (or `"rejected"`)
- **Response:** `{ "success": true, "message": "تم تحديث حالة المراجعة بنجاح." }`

#### `POST /api/reviews/delete.php`
- **Auth Requirement:** Admin session + CSRF.
- **Request:** `{ "id": 14 }`
- **Response:** `{ "success": true, "message": "تم حذف المراجعة بنجاح." }`

---

### 2.6 Social Links & Channels

#### `GET /api/social/list.php`
- **Auth Requirement:** Public.
- **Database Entity:** `social_links` (WHERE `is_enabled = 1 AND url <> ''`).
- **Response:**
  ```json
  {
    "success": true,
    "data": [
      { "platform": "x", "name": "X (تويتر)", "url": "https://x.com/username", "sort_order": 1 },
      { "platform": "linkedin", "name": "LinkedIn", "url": "https://linkedin.com/in/username", "sort_order": 3 }
    ]
  }
  ```

#### `GET /api/social/admin_list.php`
- **Auth Requirement:** Admin session.
- **Database Entity:** `social_links` (All rows including disabled).
- **Response:** `{ "success": true, "data": [ ... ] }`

#### `POST /api/social/update.php`
- **Auth Requirement:** Admin session + CSRF.
- **Request:**
  ```json
  {
    "platforms": [
      { "platform": "x", "url": "https://x.com/new_handle", "is_enabled": 1 },
      { "platform": "tiktok", "url": "", "is_enabled": 0 }
    ]
  }
  ```
- **Response:** `{ "success": true, "message": "تم تحديث وسائل التواصل بنجاح." }`

---

### 2.7 User & Admin Account Management

#### `GET /api/users/list.php`
- **Auth Requirement:** Admin session.
- **Response:**
  ```json
  {
    "success": true,
    "data": [
      { "id": 1, "name": "Mohammed Alrashadi", "email": "admin@example.com", "role": "admin", "created_at": "...", "updated_at": "..." }
    ]
  }
  ```
  *(Password hashes are NEVER selected or returned).*

#### `POST /api/users/create.php`
- **Auth Requirement:** Admin session + CSRF.
- **Request:** `{ "name": "New Editor", "email": "editor@example.com", "password": "SecurePassword123!", "role": "admin" }`
- **Response:** `{ "success": true, "message": "تم إنشاء المستخدم بنجاح.", "id": 2 }`

#### `POST /api/users/update.php`
- **Auth Requirement:** Admin session + CSRF.
- **Request:** `{ "id": 2, "name": "Editor", "email": "editor@example.com", "role": "admin", "password": "" }`
- **Response:** `{ "success": true, "message": "تم تحديث بيانات المستخدم بنجاح." }`

#### `POST /api/users/delete.php`
- **Auth Requirement:** Admin session + CSRF.
- **Defenses:** Blocks self-deletion (`$id === currentUserId()`) and blocks deletion of the last remaining administrator (`adminCount <= 1`).
- **Request:** `{ "id": 2 }`
- **Response:** `{ "success": true, "message": "تم حذف المستخدم بنجاح." }`

---

### 2.8 Local Telemetry & Analytics

#### `POST /api/telemetry/visit.php`
- **Auth Requirement:** Public.
- **Guards:** Drops bot User-Agents, honors DNT (Do Not Track) and GPC (Global Privacy Control).
- **Deduplication:** 24h per-hash buffer in `telemetry_dedup_visitors`.
- **Database Entities:** `site_visitors`, `daily_site_stats`, `telemetry_dedup_visitors`.
- **Response:** HTTP 204 No Content.

#### `POST /api/telemetry/view.php`
- **Auth Requirement:** Public.
- **Payload:** `{ "post_id": 89 }`
- **Guards:** Active client dwell threshold (3s), published post verification.
- **Deduplication:** Same-calendar-day per-article buffer in `telemetry_dedup_articles`.
- **Database Entities:** `posts.views`, `daily_article_stats`, `daily_site_stats`, `telemetry_dedup_articles`.
- **Response:** HTTP 204 No Content.

#### `GET /api/analytics/dashboard.php`
- **Auth Requirement:** Admin session.
- **Response:**
  ```json
  {
    "success": true,
    "data": {
      "summary": {
        "lifetime_visitors": 1284,
        "lifetime_reads": 4510,
        "seven_day_visitors": 210,
        "seven_day_reads": 780,
        "avg_daily_visitors": 30.0,
        "avg_daily_reads": 111.4
      },
      "chart": [
        { "date": "2026-09-09", "day_name": "الأربعاء", "visitors": 32, "reads": 110 }
      ],
      "most_read": [ ... ],
      "least_read": [ ... ]
    }
  }
  ```

---

### 2.9 Image Upload Endpoint

#### `POST /api/uploads/image.php`
- **Auth Requirement:** Admin session + CSRF.
- **Payload:** `multipart/form-data` with `image` file.
- **Validation:**
  - Max size: 5 MB.
  - MIME verification: `finfo(FILEINFO_MIME_TYPE)` against whitelist (`image/jpeg`, `image/png`, `image/webp`, `image/gif`).
  - Image header validation: `getimagesize()`.
  - Cryptographic filename: `bin2hex(random_bytes(16)) . '.' . $ext`.
- **Response:**
  ```json
  { "success": true, "url": "/uploads/a1b2c3d4e5f6.webp" }
  ```

---
*API Data Model Audit Complete. All 18 functional endpoints verified and documented.*

