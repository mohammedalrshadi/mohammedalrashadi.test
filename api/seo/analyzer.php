<?php
// ============================================================
// SEO ANALYZER — CORE LOGIC
// api/seo/analyzer.php
//
// Pure, side-effect-free analysis functions. No DB access, no
// session/auth handling, no HTTP output — those live in
// analyze.php. Everything here can be required and unit tested
// directly from a CLI script.
//
// The analyzer evaluates a post exactly as it will actually be
// seen by a browser, a search crawler, or a social-media
// scraper on the LIVE public site — it mirrors the real
// metadata-generation logic in post.php (title suffix, meta
// description truncation, image fallback, canonical URL,
// JSON-LD type mapping) rather than inventing a separate set of
// assumptions, so a "PASS" here means the live page actually
// looks the way the rule expects.
//
// CONTENT QUALITY vs PUBLIC INDEXABILITY
// ----------------------------------------------------------
// This is an authoring tool: an admin must be able to analyze a
// DRAFT or HIDDEN post before publishing it. So:
//   - Metadata / Content / Headings / Images / Links are content-
//     quality checks and are evaluated the same regardless of
//     status — they describe what WOULD be true once published.
//   - Social / Structured Data / Technical SEO describe what a
//     crawler sees on the CURRENT live page right now. post.php
//     only emits Open Graph/Twitter/JSON-LD tags and a normal
//     (non-noindex) response for status='published' posts (see
//     post.php's `$postNotFound` branch) — a draft or hidden post
//     currently renders `<meta name="robots" content="noindex,
//     nofollow">` and none of those tags. So for a non-published
//     post these three categories correctly report ERROR/not-yet-
//     live rather than a false PASS.
// Soft-deleted posts are never analyzed at all (analyze.php's
// query excludes deleted_at IS NOT NULL entirely) — matching how
// the admin editor itself treats deleted posts as gone.
// ============================================================

require_once dirname(__DIR__) . '/posts/helper.php'; // isSubstantivelyEmptyHtml()

// ------------------------------------------------------------
// Site-wide constants shared with post.php / sitemap.php.
// Kept here (not re-defined) so hostname comparison for the
// internal/external link check always matches the real domain.
// ------------------------------------------------------------
if (!defined('SEO_SITE_HOSTS')) {
    define('SEO_SITE_HOSTS', ['mohammedalrashadi.com', 'www.mohammedalrashadi.com']);
}
if (!defined('SEO_SITE_URL')) {
    define('SEO_SITE_URL', 'https://mohammedalrashadi.com');
}
if (!defined('SEO_DEFAULT_IMAGE')) {
    define('SEO_DEFAULT_IMAGE', 'https://mohammedalrashadi.com/profile.png');
}

// ============================================================
// FOCUS KEYWORD — VALIDATION / NORMALIZATION
// Transient only: never persisted anywhere by this file or by
// analyze.php.
// ============================================================
function seo_normalizeFocusKeyword(?string $raw): array {

    if ($raw === null) {
        return ['ok' => true, 'value' => null, 'error' => null];
    }

    if (!mb_check_encoding($raw, 'UTF-8')) {
        return ['ok' => false, 'value' => null, 'error' => 'ترميز كلمة البحث غير صالح (UTF-8 مطلوب). / Invalid keyword encoding (UTF-8 required).'];
    }

    // Strip control characters (keep normal spaces), then collapse whitespace.
    $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $raw);
    $clean = trim(preg_replace('/\s+/u', ' ', (string) $clean));

    if ($clean === '') {
        return ['ok' => true, 'value' => null, 'error' => null];
    }

    if (mb_strlen($clean, 'UTF-8') > 100) {
        return ['ok' => false, 'value' => null, 'error' => 'الكلمة المفتاحية طويلة جداً (الحد الأقصى 100 حرف). / Keyword is too long (100 characters max).'];
    }

    return ['ok' => true, 'value' => $clean, 'error' => null];
}

// ============================================================
// DOM / CONTENT HELPERS
// ============================================================

function seo_loadFragment(string $html): DOMDocument {
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $wrapped = '<?xml encoding="utf-8"?><div id="__seo_root__">' . $html . '</div>';
    $dom->loadHTML($wrapped, LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    return $dom;
}

function seo_plainText(?string $html): string {
    if ($html === null || $html === '') {
        return '';
    }
    $text = strip_tags($html);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = str_replace(["\xc2\xa0", "\xe2\x80\x8b", "\u{00A0}", "\u{200B}"], ' ', $text);
    $text = trim(preg_replace('/\s+/u', ' ', $text));
    return $text;
}

function seo_wordCount(string $plainText): int {
    if (trim($plainText) === '') {
        return 0;
    }
    $parts = preg_split('/\s+/u', trim($plainText));
    return $parts === false ? 0 : count(array_filter($parts, fn($p) => $p !== ''));
}

function seo_buildDescription(string $content): string {
    if (trim($content) === '') {
        return '';
    }
    $clean = strip_tags($content);
    $clean = html_entity_decode($clean, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $clean = preg_replace('/\s+/', ' ', $clean);
    return mb_substr(trim((string) $clean), 0, 180, 'UTF-8');
}

function seo_extractHeadings(DOMDocument $dom): array {
    $headings = [];
    $all = $dom->getElementsByTagName('*'); // DOM spec guarantees document order
    foreach ($all as $el) {
        if (preg_match('/^h([1-6])$/i', $el->tagName, $m)) {
            $headings[] = [
                'level' => (int) $m[1],
                'text'  => seo_plainText($dom->saveHTML($el)),
            ];
        }
    }
    return $headings;
}

function seo_extractImages(DOMDocument $dom): array {
    $images = [];
    foreach ($dom->getElementsByTagName('img') as $el) {
        $images[] = [
            'src'     => trim($el->getAttribute('src')),
            'has_alt' => $el->hasAttribute('alt'),
            'alt'     => $el->hasAttribute('alt') ? trim($el->getAttribute('alt')) : null,
        ];
    }
    return $images;
}

function seo_extractLinks(DOMDocument $dom): array {
    $links = [];
    foreach ($dom->getElementsByTagName('a') as $el) {
        $links[] = [
            'href'     => trim($el->getAttribute('href')),
            'text'     => seo_plainText($dom->saveHTML($el)),
            'hasMedia' => $el->getElementsByTagName('img')->length > 0,
        ];
    }
    return $links;
}

function seo_classifyLink(string $href): string {
    $href = trim($href);
    if ($href === '' || $href === '#') {
        return 'invalid';
    }

    $lower = strtolower($href);
    if (strpos($lower, 'mailto:') === 0 || strpos($lower, 'tel:') === 0) {
        return 'other';
    }
    if (strpos($lower, 'javascript:') === 0) {
        return 'invalid';
    }

    $parts = @parse_url($href);
    if ($parts === false) {
        return 'invalid';
    }

    if (!isset($parts['host'])) {
        if (empty($parts['path']) && empty($parts['query']) && empty($parts['fragment'])) {
            return 'invalid';
        }
        return 'internal';
    }

    $host = strtolower($parts['host']);
    return in_array($host, SEO_SITE_HOSTS, true) ? 'internal' : 'external';
}

function seo_countHierarchyJumps(array $levels): int {
    $jumps = 0;
    for ($i = 1; $i < count($levels); $i++) {
        if ($levels[$i] > $levels[$i - 1] + 1) {
            $jumps++;
        }
    }
    return $jumps;
}

function seo_containsCI(string $haystack, string $needle): bool {
    if ($needle === '') {
        return false;
    }
    return mb_stripos($haystack, $needle, 0, 'UTF-8') !== false;
}

function seo_countOccurrencesCI(string $haystack, string $needle): int {
    if ($needle === '' || $haystack === '') {
        return 0;
    }
    $pattern = '/' . preg_quote($needle, '/') . '/iu';
    $count = @preg_match_all($pattern, $haystack);
    return $count === false ? 0 : $count;
}

function seo_isGenericAlt(string $alt): bool {
    $trimmed = trim($alt);
    if ($trimmed === '') {
        return false;
    }
    $lower = mb_strtolower($trimmed, 'UTF-8');
    $genericWords = ['image', 'img', 'photo', 'picture', 'pic', 'صورة', 'صوره', 'الصورة'];
    if (in_array($lower, $genericWords, true)) {
        return true;
    }
    if (preg_match('/^(img|dsc|screenshot|photo|image)[_\-]?\d*\.(jpe?g|png|gif|webp|bmp)$/i', $trimmed)) {
        return true;
    }
    if (preg_match('/\.(jpe?g|png|gif|webp|bmp)$/i', $trimmed)) {
        return true;
    }
    return false;
}

// ============================================================
// SCORING
// ------------------------------------------------------------
// V2 adds two statuses on top of the original pass/warning/error:
//   - 'notice'        : a real, scored issue that behaves exactly
//                        like 'warning' for point math (this is a
//                        pure UX/severity relabeling for advisory-
//                        style issues — fallback images, duplicate
//                        title/description, "no images at all",
//                        "all links are external" — see the
//                        SEO_RULE_INFO table and call sites below).
//                        Using the SAME formula as 'warning' means
//                        reclassifying an existing rule to 'notice'
//                        NEVER changes its score contribution —
//                        the 100-point weighting model is untouched.
//   - 'not_evaluated'  : the check genuinely could not run (e.g. no
//                        focus keyword was supplied). Always 0
//                        points — never awarded (it wasn't earned)
//                        and never treated as a failure either; it
//                        is its own summary bucket, distinct from
//                        both 'passed' and 'warning'/'critical'.
// ============================================================

function seo_pointsForStatus(string $status, int $maxPoints): int {
    switch ($status) {
        case 'pass':
            return $maxPoints;
        case 'notice':
        case 'warning':
            return (int) ceil($maxPoints / 2);
        case 'not_evaluated':
        case 'error':
        default:
            return 0;
    }
}

/**
 * SEVERITY vs STATUS
 * ------------------------------------------------------------
 * `status` answers "what happened when this check ran?"
 * (pass|warning|error|notice|not_evaluated).
 * `severity` answers "how much should the user care?"
 * (critical|warning|info) and is a SEPARATE axis: a passed check,
 * a notice, and a not-evaluated check are ALWAYS 'info' — none of
 * them represent a genuine problem needing urgent attention. A
 * failed/warned check is 'critical' only for the handful of ids in
 * SEO_CRITICAL_RULE_IDS below — genuinely blocking, foundational
 * problems (no title, no content, no H1, not indexable...). Every
 * other warning/error is 'warning' severity. 'notice' is a STATUS
 * only, never a severity value, per the V2 spec.
 */
const SEO_CRITICAL_RULE_IDS = [
    'META_TITLE_EXISTS',
    'META_DESCRIPTION_EXISTS',
    'CONTENT_EXISTS',
    'HEADING_H1_EXISTS',
    'TECH_INDEXABILITY',
];

function seo_severityForRule(string $id, string $status, ?string $override = null): string {
    if ($override !== null) {
        return $override;
    }
    if ($status === 'pass' || $status === 'notice' || $status === 'not_evaluated') {
        return 'info';
    }
    return in_array($id, SEO_CRITICAL_RULE_IDS, true) ? 'critical' : 'warning';
}

/**
 * PRIORITY MODEL
 * ------------------------------------------------------------
 * Deterministic, and deliberately NOT just "biggest score
 * deduction" — a rule's priority blends severity, impact, and
 * effort so that a cheap, worthwhile fix can rank as highly as a
 * severe-but-hard one. Only actionable statuses (warning, error,
 * notice) receive a priority; pass/not_evaluated are null — there
 * is nothing to prioritize fixing.
 *
 * score = severityWeight*3 + impactWeight*2 + effortWeight*1
 * (effort is weighted so LOWER effort scores HIGHER — an easy fix
 * is more attractive, not less). Weighted sum range is 6..18:
 *   >= 13 -> high
 *   >= 9  -> medium
 *   else  -> low
 *
 * Worked examples (documented per the spec's own examples):
 *   critical + high impact + low effort    = 9+6+3=18 -> high
 *   warning  + medium impact + low effort  = 6+4+3=13 -> high
 *     (a cheap, moderately-impactful fix deserves to surface — this
 *     is exactly what "quick win" means)
 *   notice   + low impact + low effort     = 3+2+3=8  -> low
 *     (matches the spec's own "NOTICE + LOW IMPACT -> low priority")
 */
const SEO_SEVERITY_WEIGHT = ['critical' => 3, 'warning' => 2, 'info' => 1];
const SEO_IMPACT_WEIGHT   = ['high' => 3, 'medium' => 2, 'low' => 1];
const SEO_EFFORT_WEIGHT   = ['low' => 3, 'medium' => 2, 'high' => 1]; // inverted: lower effort scores higher

function seo_priorityForRule(string $status, string $severity, string $impact, string $effort): ?string {
    if (!in_array($status, ['warning', 'error', 'notice'], true)) {
        return null;
    }
    $score = (SEO_SEVERITY_WEIGHT[$severity] ?? 1) * 3
        + (SEO_IMPACT_WEIGHT[$impact] ?? 1) * 2
        + (SEO_EFFORT_WEIGHT[$effort] ?? 1) * 1;
    if ($score >= 13) {
        return 'high';
    }
    if ($score >= 9) {
        return 'medium';
    }
    return 'low';
}

/**
 * CENTRALIZED RULE INFO — impact/effort + static why-it-matters /
 * recommendation copy, keyed by rule id. This is the single place
 * that defines these per-rule editorial fields, rather than
 * hardcoding them ad hoc across 47 call sites. Data (numbers,
 * counts) stays in each rule's own $messages — this table only
 * holds the parts that are the same for every post (why a category
 * of problem matters, and the generic fix for it).
 *
 * Rules that are structurally always-pass (META_CANONICAL_HTTPS,
 * SOCIAL_OG_URL/OG_TYPE, SOCIAL_TWITTER_CARD, STRUCTURED_FIELD_URL/
 * MAIN_ENTITY/AUTHOR/PUBLISHER) are omitted — they can never be
 * "actionable", so why/recommendation would never be shown anyway.
 * Missing ids fall back to impact=low/effort=low with no copy.
 */
const SEO_RULE_INFO = [
    'META_TITLE_EXISTS' => ['impact' => 'high', 'effort' => 'low', 'why' => [
        'ar' => 'العنوان هو أول ما يراه الزوار ومحركات البحث، ويُستخدم عادة كعنوان النتيجة في نتائج البحث.',
        'en' => "The title is usually the first thing visitors and search engines see, and is typically used as the search result's headline.",
    ], 'rec' => [
        'ar' => 'أضف عنواناً واضحاً ووصفياً للمنشور.',
        'en' => 'Add a clear, descriptive title for the post.',
    ]],
    'META_TITLE_LENGTH' => ['impact' => 'medium', 'effort' => 'low', 'why' => [
        'ar' => 'العناوين القصيرة جداً أو الطويلة جداً قد تُقصّ في نتائج البحث أو لا تنقل الموضوع بوضوح.',
        'en' => 'Titles that are too short or too long may get truncated in search results or fail to clearly convey the topic.',
    ], 'rec' => [
        'ar' => 'اجعل طول العنوان بين 10 و60 حرفاً تقريباً.',
        'en' => 'Keep the title roughly between 10 and 60 characters.',
    ]],
    'META_TITLE_UNIQUE' => ['impact' => 'medium', 'effort' => 'low', 'why' => [
        'ar' => 'تكرار العنوان نفسه على أكثر من منشور قد يُربك القراء ومحركات البحث حول أي صفحة هي الأكثر صلة.',
        'en' => 'Reusing the exact same title across posts can confuse both readers and search engines about which page is most relevant.',
    ], 'rec' => [
        'ar' => 'اجعل عنوان كل منشور مميزاً وخاصاً به.',
        'en' => "Give each post its own distinct title.",
    ]],
    'META_DESCRIPTION_EXISTS' => ['impact' => 'medium', 'effort' => 'low', 'why' => [
        'ar' => 'الوصف التعريفي يُستخدم غالباً كنص المقتطف في نتائج البحث.',
        'en' => "The meta description is often used as the search result's snippet text.",
    ], 'rec' => [
        'ar' => 'أضف محتوى فعلياً للمنشور حتى يمكن توليد وصف تعريفي منه.',
        'en' => 'Add real content to the post so a meta description can be generated from it.',
    ]],
    'META_DESCRIPTION_LENGTH' => ['impact' => 'medium', 'effort' => 'low', 'why' => [
        'ar' => 'الوصف الأقصر أو الأطول من النطاق المستهدف قد يُقصّ أو لا يستغل مساحة المقتطف بالكامل.',
        'en' => 'A description outside the target range may get truncated or waste the space available in the result snippet.',
    ], 'rec' => [
        'ar' => 'اضبط طول الوصف ليكون بين 70 و160 حرفاً تقريباً.',
        'en' => 'Aim for a description roughly between 70 and 160 characters.',
    ]],
    'META_DESCRIPTION_UNIQUE' => ['impact' => 'medium', 'effort' => 'low', 'why' => [
        'ar' => 'وصف تعريفي مكرر بين منشورين قد يجعل من الصعب على القارئ ومحركات البحث التمييز بينهما.',
        'en' => "A duplicated description across two posts can make it harder for readers and search engines to tell them apart.",
    ], 'rec' => [
        'ar' => 'اكتب وصفاً تعريفياً خاصاً بموضوع هذا المنشور تحديداً.',
        'en' => "Write a description specific to this post's own topic.",
    ]],
    'CONTENT_EXISTS' => ['impact' => 'high', 'effort' => 'low', 'why' => [
        'ar' => 'بدون محتوى فعلي، لا يوجد للصفحة ما تُفهرسه محركات البحث أو يستفيد منه القارئ.',
        'en' => "Without real content, there's nothing for search engines to index or for readers to get value from.",
    ], 'rec' => [
        'ar' => 'أضف محتوى فعلياً للمنشور.',
        'en' => 'Add real content to the post.',
    ]],
    'CONTENT_LENGTH' => ['impact' => 'medium', 'effort' => 'medium', 'why' => [
        'ar' => 'المحتوى القصير جداً غالباً ما يواجه صعوبة في تغطية الموضوع بعمق كافٍ.',
        'en' => 'Very short content often struggles to cover a topic in enough depth.',
    ], 'rec' => [
        'ar' => 'وسّع المحتوى ليغطي الموضوع بتفصيل أكبر، بحيث يقترب من 300 كلمة أو أكثر.',
        'en' => 'Expand the content to cover the topic in more depth, aiming for roughly 300+ words.',
    ]],
    'CONTENT_FIRST_PARAGRAPH' => ['impact' => 'low', 'effort' => 'low', 'why' => [
        'ar' => 'الفقرة الأولى غالباً ما تُشكّل انطباع القارئ الأول وقد تُستخدم كمقتطف تلقائي.',
        'en' => "The first paragraph often shapes the reader's first impression and may be used as an automatic excerpt.",
    ], 'rec' => [
        'ar' => 'اكتب فقرة أولى واضحة تلخص موضوع المقال.',
        'en' => "Write a clear opening paragraph that summarizes the article's topic.",
    ]],
    'CONTENT_KEYWORD_ANALYSIS' => ['impact' => 'medium', 'effort' => 'low'],
    'CONTENT_KEYWORD_TITLE' => ['impact' => 'medium', 'effort' => 'low', 'why' => [
        'ar' => 'وجود الكلمة المفتاحية في العنوان يساعد محركات البحث على ربط الصفحة بذلك الموضوع بوضوح.',
        'en' => 'Having the focus keyword in the title helps search engines clearly associate the page with that topic.',
    ], 'rec' => [
        'ar' => 'أضف الكلمة المفتاحية إلى العنوان بشكل طبيعي.',
        'en' => 'Work the focus keyword naturally into the title.',
    ]],
    'CONTENT_KEYWORD_DESCRIPTION' => ['impact' => 'low', 'effort' => 'low', 'why' => [
        'ar' => 'ظهور الكلمة المفتاحية في الوصف قد يجعل مقتطف نتيجة البحث أكثر صلة بالبحث المستهدف.',
        'en' => 'Having the keyword in the description can make the search snippet feel more relevant to the target search.',
    ], 'rec' => [
        'ar' => 'أضف الكلمة المفتاحية إلى الوصف التعريفي بشكل طبيعي.',
        'en' => 'Work the focus keyword naturally into the meta description.',
    ]],
    'CONTENT_KEYWORD_FIRST_PARAGRAPH' => ['impact' => 'low', 'effort' => 'low', 'why' => [
        'ar' => 'ذكر الكلمة المفتاحية مبكراً في المحتوى يوضح للقارئ ومحركات البحث موضوع الصفحة بسرعة.',
        'en' => "Mentioning the keyword early in the content quickly signals the page's topic to readers and search engines.",
    ], 'rec' => [
        'ar' => 'اذكر الكلمة المفتاحية في الفقرة الأولى إن كان ذلك طبيعياً.',
        'en' => 'Mention the focus keyword in the first paragraph where it reads naturally.',
    ]],
    'CONTENT_KEYWORD_FREQUENCY' => ['impact' => 'low', 'effort' => 'medium', 'why' => [
        'ar' => 'التكرار شديد الانخفاض قد يُضعف الصلة الموضوعية، بينما التكرار المفرط قد يبدو حشواً غير طبيعي للكلمات المفتاحية.',
        'en' => 'Too little repetition can weaken topical relevance, while excessive repetition can look like unnatural keyword stuffing.',
    ], 'rec' => [
        'ar' => 'استخدم الكلمة المفتاحية بشكل طبيعي عدة مرات ضمن السياق، دون إفراط.',
        'en' => 'Use the keyword naturally a few times in context, without overusing it.',
    ]],
    'HEADING_H1_EXISTS' => ['impact' => 'high', 'effort' => 'low', 'why' => [
        'ar' => 'العنوان الرئيسي (H1) يساعد القراء ومحركات البحث على فهم الموضوع الأساسي للصفحة فوراً.',
        'en' => "The main heading (H1) helps readers and search engines immediately understand the page's primary topic.",
    ], 'rec' => [
        'ar' => 'تأكد من وجود عنوان للمنشور.',
        'en' => 'Make sure the post has a title.',
    ]],
    'HEADING_H1_COUNT' => ['impact' => 'low', 'effort' => 'low', 'why' => [
        'ar' => 'وجود أكثر من H1 واحد في الصفحة قد يُضعف وضوح البنية الهرمية للمحتوى.',
        'en' => "Having more than one H1 on the page can blur the content's hierarchical structure.",
    ], 'rec' => [
        'ar' => 'استخدم H2 وما بعده للعناوين الفرعية بدلاً من H1 إضافي.',
        'en' => 'Use H2 and lower for subheadings instead of an extra H1.',
    ]],
    'HEADING_EMPTY' => ['impact' => 'low', 'effort' => 'low', 'why' => [
        'ar' => 'العناوين الفارغة لا تضيف قيمة للقارئ أو للبنية الدلالية للصفحة.',
        'en' => 'Empty headings add no value for readers or for the page\'s semantic structure.',
    ], 'rec' => [
        'ar' => 'احذف العناوين الفارغة أو أضف لها نصاً وصفياً.',
        'en' => 'Remove empty headings or give them descriptive text.',
    ]],
    'HEADING_HIERARCHY' => ['impact' => 'low', 'effort' => 'low', 'why' => [
        'ar' => 'تسلسل العناوين المتقطع (كالقفز من H2 إلى H4) يجعل بنية المستند أقل وضوحاً لقارئات الشاشة وأدوات الزحف الآلي — وهو أمر يتعلق بوضوح البنية، وليس بالضرورة عقوبة مباشرة في الترتيب.',
        'en' => "A broken heading sequence (e.g. jumping from H2 to H4) makes the document's structure less clear for screen readers and automated crawling — a structural clarity issue, not necessarily a direct ranking penalty.",
    ], 'rec' => [
        'ar' => 'اجعل مستويات العناوين متسلسلة دون تخطي مستوى.',
        'en' => 'Keep heading levels sequential without skipping a level.',
    ]],
    'IMAGE_EXISTS' => ['impact' => 'low', 'effort' => 'medium', 'why' => [
        'ar' => 'الصور يمكن أن تُحسّن تجربة القارئ وقد تظهر في نتائج بحث الصور، لكنها ليست شرطاً أساسياً لكل مقال.',
        'en' => "Images can improve the reader's experience and may appear in image search results, but they aren't a strict requirement for every article.",
    ], 'rec' => [
        'ar' => 'فكّر في إضافة صورة أو أكثر ذات صلة بموضوع المقال عند الإمكان.',
        'en' => 'Consider adding one or more relevant images when practical.',
    ]],
    'IMAGE_ALT_MISSING' => ['impact' => 'medium', 'effort' => 'low', 'why' => [
        'ar' => 'النص البديل (alt) يساعد ذوي الإعاقة البصرية ومحركات البحث على فهم محتوى الصورة.',
        'en' => 'Alt text helps visually impaired users and search engines understand what an image shows.',
    ], 'rec' => [
        'ar' => 'أضف نصاً بديلاً وصفياً لكل صورة.',
        'en' => 'Add descriptive alt text to every image.',
    ]],
    'IMAGE_ALT_GENERIC' => ['impact' => 'low', 'effort' => 'low', 'why' => [
        'ar' => 'النص البديل العام (مثل "photo.jpg") لا يصف محتوى الصورة فعلياً.',
        'en' => 'Generic alt text (like "photo.jpg") doesn\'t actually describe what the image shows.',
    ], 'rec' => [
        'ar' => 'استبدل النصوص البديلة العامة بوصف حقيقي لمحتوى كل صورة.',
        'en' => "Replace generic alt text with a real description of each image's content.",
    ]],
    'IMAGE_ALT_DUPLICATE' => ['impact' => 'low', 'effort' => 'low', 'why' => [
        'ar' => 'تكرار النص البديل نفسه على صور مختلفة يقلل من فائدته في التمييز بينها.',
        'en' => 'Reusing the same alt text across different images reduces its usefulness for telling them apart.',
    ], 'rec' => [
        'ar' => 'اكتب نصاً بديلاً مختلفاً ووصفياً لكل صورة.',
        'en' => 'Write a distinct, descriptive alt text for each image.',
    ]],
    'IMAGE_COVER_ALT_UNSTORED' => ['impact' => 'low', 'effort' => 'medium', 'why' => [
        'ar' => 'لا يخزّن النظام حالياً نصاً بديلاً منفصلاً لصورة الغلاف، لذا لا يمكن التحقق من جودته آلياً.',
        'en' => "The system doesn't currently store separate alt text for the cover image, so its quality can't be verified automatically.",
    ], 'rec' => [
        'ar' => 'يمكن إضافة حقل نص بديل مخصص لصورة الغلاف في تحديث مستقبلي إذا رغبت بذلك.',
        'en' => 'A dedicated alt-text field for the cover image could be added in a future update if desired.',
    ]],
    'IMAGE_GALLERY_ALT_UNSTORED' => ['impact' => 'low', 'effort' => 'medium', 'why' => [
        'ar' => 'لا يخزّن النظام حالياً نصاً بديلاً منفصلاً لصور المعرض، لذا لا يمكن التحقق من جودته آلياً.',
        'en' => "The system doesn't currently store separate alt text for gallery images, so their quality can't be verified automatically.",
    ], 'rec' => [
        'ar' => 'يمكن إضافة حقل نص بديل مخصص لصور المعرض في تحديث مستقبلي إذا رغبت بذلك.',
        'en' => 'A dedicated alt-text field for gallery images could be added in a future update if desired.',
    ]],
    'LINK_EMPTY_ANCHOR_TEXT' => ['impact' => 'low', 'effort' => 'low', 'why' => [
        'ar' => 'نص الرابط الوصفي يساعد القراء ومحركات البحث على فهم وجهة الرابط قبل النقر عليه.',
        'en' => 'Descriptive link text helps readers and search engines understand where a link leads before clicking it.',
    ], 'rec' => [
        'ar' => 'أضف نصاً واضحاً لكل رابط بدلاً من تركه فارغاً.',
        'en' => 'Add clear text to every link instead of leaving it empty.',
    ]],
    'LINK_URL_VALIDITY' => ['impact' => 'medium', 'effort' => 'low', 'why' => [
        'ar' => 'الروابط ذات العناوين غير الصالحة تؤدي غالباً إلى صفحات معطلة لدى الزوار.',
        'en' => 'Links with invalid URLs usually lead to broken pages for visitors.',
    ], 'rec' => [
        'ar' => 'تحقق من صحة عناوين الروابط وصححها.',
        'en' => 'Check and fix the invalid link URLs.',
    ]],
    'LINK_TYPES' => ['impact' => 'low', 'effort' => 'low', 'why' => [
        'ar' => 'الروابط الداخلية تساعد الزوار ومحركات البحث على اكتشاف محتوى آخر ذي صلة في موقعك.',
        'en' => 'Internal links help both readers and search engines discover other relevant content on your site.',
    ], 'rec' => [
        'ar' => 'فكّر في إضافة روابط لمنشورات أو صفحات أخرى ذات صلة عندما يكون ذلك مفيداً للقارئ.',
        'en' => "Consider linking to other relevant posts or pages when it's genuinely useful for the reader.",
    ]],
    'SOCIAL_OPEN_GRAPH' => ['impact' => 'medium', 'effort' => 'low', 'why' => [
        'ar' => 'وسوم Open Graph تتحكم بشكل الصورة والعنوان والوصف عند مشاركة الرابط على فيسبوك ومنصات مشابهة.',
        'en' => "Open Graph tags control how the link's image, title, and description appear when shared on Facebook and similar platforms.",
    ], 'rec' => [
        'ar' => 'أضف صورة غلاف ومحتوى فعلياً للمنشور حتى تُستخدم بيانات خاصة به بدل القيم الافتراضية.',
        'en' => 'Add a cover image and real content so post-specific data is used instead of the site defaults.',
    ]],
    'SOCIAL_TWITTER' => ['impact' => 'low', 'effort' => 'low', 'why' => [
        'ar' => 'بطاقة Twitter/X تتحكم بشكل الرابط عند مشاركته على منصة X.',
        'en' => "The Twitter/X Card controls how the link appears when shared on the X platform.",
    ], 'rec' => [
        'ar' => 'أضف صورة غلاف ومحتوى فعلياً للمنشور حتى تُستخدم بيانات خاصة به بدل القيم الافتراضية.',
        'en' => 'Add a cover image and real content so post-specific data is used instead of the site defaults.',
    ]],
    'SOCIAL_OG_TITLE' => ['impact' => 'medium', 'effort' => 'low', 'why' => [
        'ar' => 'عنوان المشاركة هو أول ما يراه المستخدمون عند مشاركة الرابط اجتماعياً.',
        'en' => 'The share title is the first thing users see when the link is shared socially.',
    ], 'rec' => [
        'ar' => 'أضف عنواناً للمنشور.',
        'en' => 'Add a title to the post.',
    ]],
    'SOCIAL_TWITTER_TITLE' => ['impact' => 'medium', 'effort' => 'low', 'why' => [
        'ar' => 'عنوان المشاركة هو أول ما يراه المستخدمون عند مشاركة الرابط على X.',
        'en' => 'The share title is the first thing users see when the link is shared on X.',
    ], 'rec' => [
        'ar' => 'أضف عنواناً للمنشور.',
        'en' => 'Add a title to the post.',
    ]],
    'SOCIAL_OG_DESCRIPTION' => ['impact' => 'low', 'effort' => 'low', 'why' => [
        'ar' => 'وصف المشاركة يساعد على توضيح موضوع الرابط قبل أن ينقر عليه أحد.',
        'en' => 'The share description helps clarify what the link is about before someone clicks it.',
    ], 'rec' => [
        'ar' => 'أضف محتوى فعلياً للمنشور بحيث يُستخدم وصف خاص به بدل النص الافتراضي.',
        'en' => 'Add real content so a post-specific description is used instead of the generic fallback text.',
    ]],
    'SOCIAL_TWITTER_DESCRIPTION' => ['impact' => 'low', 'effort' => 'low', 'why' => [
        'ar' => 'وصف المشاركة يساعد على توضيح موضوع الرابط قبل أن ينقر عليه أحد على X.',
        'en' => 'The share description helps clarify what the link is about before someone clicks it on X.',
    ], 'rec' => [
        'ar' => 'أضف محتوى فعلياً للمنشور بحيث يُستخدم وصف خاص به بدل النص الافتراضي.',
        'en' => 'Add real content so a post-specific description is used instead of the generic fallback text.',
    ]],
    'SOCIAL_OG_IMAGE' => ['impact' => 'low', 'effort' => 'low', 'why' => [
        'ar' => 'صورة المشاركة تجذب الانتباه بصرياً عند ظهور الرابط على المنصات الاجتماعية.',
        'en' => 'The share image visually draws attention when the link appears on social platforms.',
    ], 'rec' => [
        'ar' => 'أضف صورة غلاف خاصة بهذا المنشور بدلاً من الاعتماد على الصورة الافتراضية.',
        'en' => 'Add a cover image specific to this post instead of relying on the default image.',
    ]],
    'SOCIAL_TWITTER_IMAGE' => ['impact' => 'low', 'effort' => 'low', 'why' => [
        'ar' => 'صورة المشاركة تجذب الانتباه بصرياً عند ظهور الرابط على X.',
        'en' => 'The share image visually draws attention when the link appears on X.',
    ], 'rec' => [
        'ar' => 'أضف صورة غلاف خاصة بهذا المنشور بدلاً من الاعتماد على الصورة الافتراضية.',
        'en' => 'Add a cover image specific to this post instead of relying on the default image.',
    ]],
    'STRUCTURED_JSONLD_EXISTS' => ['impact' => 'high', 'effort' => 'medium', 'why' => [
        'ar' => 'البيانات المنظمة (JSON-LD) تساعد محركات البحث على فهم نوع الصفحة ومحتواها بشكل أدق.',
        'en' => 'Structured data (JSON-LD) helps search engines understand the page type and content more precisely.',
    ], 'rec' => [
        'ar' => 'انشر المنشور ليتم توليد بيانات JSON-LD تلقائياً.',
        'en' => 'Publish the post so JSON-LD data is generated automatically.',
    ]],
    'STRUCTURED_TYPE' => ['impact' => 'medium', 'effort' => 'low', 'why' => [
        'ar' => 'تحديد نوع البيانات المنظمة الصحيح يساعد محركات البحث على تصنيف الصفحة بدقة.',
        'en' => 'Setting the correct structured-data type helps search engines classify the page accurately.',
    ], 'rec' => [
        'ar' => 'انشر المنشور ليُستخدم النوع الصحيح تلقائياً.',
        'en' => 'Publish the post so the correct type is applied automatically.',
    ]],
    'STRUCTURED_FIELD_HEADLINE' => ['impact' => 'medium', 'effort' => 'low', 'why' => [
        'ar' => 'حقل headline يخبر محركات البحث بعنوان المقال ضمن البيانات المنظمة.',
        'en' => "The headline field tells search engines the article's title within the structured data.",
    ], 'rec' => [
        'ar' => 'أضف عنواناً للمنشور.',
        'en' => 'Add a title to the post.',
    ]],
    'STRUCTURED_FIELD_DESCRIPTION' => ['impact' => 'low', 'effort' => 'low', 'why' => [
        'ar' => 'حقل description ضمن البيانات المنظمة يساعد على توضيح موضوع الصفحة.',
        'en' => 'The description field within the structured data helps clarify the page\'s topic.',
    ], 'rec' => [
        'ar' => 'أضف محتوى فعلياً للمنشور بحيث يُستخدم وصف خاص به بدل النص الافتراضي.',
        'en' => 'Add real content so a post-specific description is used instead of the generic fallback text.',
    ]],
    'STRUCTURED_FIELD_IMAGE' => ['impact' => 'low', 'effort' => 'low', 'why' => [
        'ar' => 'حقل image ضمن البيانات المنظمة قد يُستخدم من قبل بعض محركات البحث ضمن نتائج غنية.',
        'en' => "The image field within the structured data may be used by some search engines in rich results.",
    ], 'rec' => [
        'ar' => 'أضف صورة غلاف خاصة بهذا المنشور بدلاً من الاعتماد على الصورة الافتراضية.',
        'en' => 'Add a cover image specific to this post instead of relying on the default image.',
    ]],
    'STRUCTURED_FIELD_DATE_PUBLISHED' => ['impact' => 'low', 'effort' => 'low', 'why' => [
        'ar' => 'تاريخ النشر يساعد محركات البحث على فهم حداثة المحتوى.',
        'en' => 'The publish date helps search engines understand how current the content is.',
    ], 'rec' => [
        'ar' => 'تأكد من أن تاريخ إنشاء هذا المنشور مسجّل بشكل صحيح.',
        'en' => "Make sure this post's creation date is recorded correctly.",
    ]],
    'STRUCTURED_FIELD_DATE_MODIFIED' => ['impact' => 'low', 'effort' => 'low', 'why' => [
        'ar' => 'تاريخ التحديث يساعد محركات البحث على فهم حداثة المحتوى.',
        'en' => 'The update date helps search engines understand how current the content is.',
    ], 'rec' => [
        'ar' => 'تأكد من أن تاريخ تحديث هذا المنشور مسجّل بشكل صحيح.',
        'en' => "Make sure this post's update date is recorded correctly.",
    ]],
    'TECH_INDEXABILITY' => ['impact' => 'high', 'effort' => 'low', 'why' => [
        'ar' => 'الصفحة غير القابلة للفهرسة لا يمكن أن تظهر في نتائج البحث إطلاقاً.',
        'en' => "A page that isn't indexable can't appear in search results at all.",
    ], 'rec' => [
        'ar' => 'انشر المنشور عندما يكون جاهزاً ليصبح قابلاً للفهرسة.',
        'en' => "Publish the post when it's ready so it becomes indexable.",
    ]],
    'TECH_SITEMAP_ELIGIBILITY' => ['impact' => 'medium', 'effort' => 'low', 'why' => [
        'ar' => 'ظهور الصفحة في خريطة الموقع يساعد محركات البحث على اكتشافها بسرعة أكبر.',
        'en' => 'Being included in the sitemap helps search engines discover the page faster.',
    ], 'rec' => [
        'ar' => 'انشر المنشور عندما يكون جاهزاً ليصبح مؤهلاً للظهور في خريطة الموقع.',
        'en' => "Publish the post when it's ready so it becomes eligible for the sitemap.",
    ]],
    'TECH_PUBLIC_URL' => ['impact' => 'medium', 'effort' => 'low', 'why' => [
        'ar' => 'الرابط العام يجب أن يعرض المحتوى الفعلي حتى يصل إليه الزوار ومحركات البحث.',
        'en' => 'The public URL needs to show the real content so visitors and search engines can reach it.',
    ], 'rec' => [
        'ar' => 'انشر المنشور عندما يكون جاهزاً ليعمل الرابط العام.',
        'en' => "Publish the post when it's ready so the public URL works.",
    ]],
];

/**
 * Builds one V2 structured rule-result entry. $messages is
 * ['ar' => '...', 'en' => '...'] — both languages are computed
 * together, right where any dynamic numbers (counts, percentages)
 * are interpolated. For a non-passing/non-not_evaluated status this
 * text becomes `problem`; for a passing check it stays available
 * only as `message` (a plain confirmation, e.g. "H1 is present").
 * For `not_evaluated`, it becomes the explanatory reason.
 *
 * $severityOverride lets a caller pin severity explicitly instead of
 * the automatic id-based mapping — used for the "not published yet"
 * gating rules, where an 'error' status just reflects the current
 * authoring state (already surfaced via the not-public banner)
 * rather than a critical content defect.
 *
 * $currentValue is an optional evidence string ("180 characters",
 * "3 images", the actual canonical URL...) — omitted when there is
 * nothing meaningful to show (e.g. a boolean-only check).
 */
function seo_rule(string $id, string $category, string $status, int $maxPoints, array $messages, ?string $severityOverride = null, $currentValue = null): array {
    $severity = seo_severityForRule($id, $status, $severityOverride);
    $points   = seo_pointsForStatus($status, $maxPoints);
    $info     = SEO_RULE_INFO[$id] ?? ['impact' => 'low', 'effort' => 'low'];
    $priority = seo_priorityForRule($status, $severity, $info['impact'], $info['effort']);

    $isActionable = !in_array($status, ['pass', 'not_evaluated'], true);
    $message = ['ar' => $messages['ar'] ?? '', 'en' => $messages['en'] ?? ''];

    return [
        'id'             => $id,
        'category'       => $category,
        'status'         => $status, // 'pass' | 'warning' | 'error' | 'notice' | 'not_evaluated'
        'severity'       => $severity, // 'critical' | 'warning' | 'info'
        'priority'       => $priority, // 'high' | 'medium' | 'low' | null (pass/not_evaluated)
        'impact'         => $info['impact'], // 'high' | 'medium' | 'low'
        'effort'         => $info['effort'], // 'low' | 'medium' | 'high'
        'points'         => $points,
        'max_points'     => $maxPoints,
        'scoreImpact'    => $maxPoints - $points, // points currently at stake / unearned — 0 for pass, 0 for 0-weight advisory rules
        'currentValue'   => $currentValue,
        'problem'        => $isActionable ? $message : null,
        'whyItMatters'   => $isActionable ? ($info['why'] ?? null) : null,
        'recommendation' => $isActionable ? ($info['rec'] ?? null) : null,
        // Kept for backward compatibility with the pre-V2 frontend/tests during transition;
        // always populated (confirmation text for pass, reason text for not_evaluated, problem text otherwise).
        'message'        => $message,
    ];
}

const SEO_CATEGORIES = [
    'metadata'        => ['label' => ['ar' => 'المعلومات الوصفية', 'en' => 'Metadata'],        'max' => 20],
    'content'         => ['label' => ['ar' => 'المحتوى',            'en' => 'Content'],         'max' => 20],
    'headings'        => ['label' => ['ar' => 'العناوين',           'en' => 'Headings'],         'max' => 15],
    'images'          => ['label' => ['ar' => 'الصور',              'en' => 'Images'],           'max' => 15],
    'links'           => ['label' => ['ar' => 'الروابط',            'en' => 'Links'],            'max' => 10],
    'social'          => ['label' => ['ar' => 'التواصل الاجتماعي',  'en' => 'Social'],           'max' => 10],
    'structured_data' => ['label' => ['ar' => 'البيانات المنظمة',   'en' => 'Structured Data'],  'max' => 5],
    'technical_seo'   => ['label' => ['ar' => 'SEO التقني',         'en' => 'Technical SEO'],    'max' => 5],
];

// ============================================================
// MAIN ENTRY POINT
// ============================================================

/**
 * @param array       $post           Row from `posts` (id, title, content, image_url, type, status, created_at, updated_at ...).
 * @param array       $galleryImages  Rows from `project_images` for this post (empty for blog posts).
 * @param string|null $focusKeyword   Already-normalized (seo_normalizeFocusKeyword) keyword, or null.
 * @param array       $otherPosts     Other non-deleted posts' ['id','title','content'] for uniqueness checks
 *                                    (excludes the current post; capped/queried by analyze.php). Optional —
 *                                    defaults to empty, in which case uniqueness checks simply pass (nothing
 *                                    to compare against). This function still performs no DB access itself.
 * @return array Full analysis result: score, max_score, score_status, summary, categories[], rules[], meta{}.
 */
function seo_analyzePost(array $post, array $galleryImages, ?string $focusKeyword, array $otherPosts = []): array {

    $title      = trim((string) ($post['title'] ?? ''));
    $content    = (string) ($post['content'] ?? '');
    $type       = ($post['type'] ?? 'blog') === 'project' ? 'project' : 'blog';
    $status     = (string) ($post['status'] ?? 'draft');
    $isLive     = ($status === 'published'); // matches post.php's exact gate
    $coverImage = trim((string) ($post['image_url'] ?? ''));
    $hasCover   = $coverImage !== '';
    $hasGallery = count($galleryImages) > 0;

    $dom          = seo_loadFragment($content);
    $bodyHeadings = seo_extractHeadings($dom);
    $bodyImages   = seo_extractImages($dom);
    $bodyLinks    = seo_extractLinks($dom);
    $plainText    = seo_plainText($content);
    $wordCount    = seo_wordCount($plainText);
    $description  = seo_buildDescription($content);
    $contentEmpty = isSubstantivelyEmptyHtml($content);

    $firstPTag = $dom->getElementsByTagName('p')->item(0);
    if ($firstPTag !== null) {
        $firstParagraphText = seo_plainText($dom->saveHTML($firstPTag));
    } else {
        $firstParagraphText = mb_substr($plainText, 0, 300, 'UTF-8');
    }

    $keyword = $focusKeyword;
    $rules = [];

    // ------------------------------------------------------------
    // METADATA (20) — content-quality, status-independent
    // ------------------------------------------------------------
    $titleLen = mb_strlen($title, 'UTF-8');
    $rules[] = seo_rule('META_TITLE_EXISTS', 'metadata', $title !== '' ? 'pass' : 'error', 4, [
        'ar' => $title !== '' ? 'عنوان المنشور موجود.' : 'لا يوجد عنوان للمنشور.',
        'en' => $title !== '' ? 'Post title is present.' : 'Post title is missing.',
    ], null, $title !== '' ? $title : null);

    if ($titleLen === 0) {
        $status1 = 'error';
    } elseif ($titleLen >= 10 && $titleLen <= 60) {
        $status1 = 'pass';
    } elseif ($titleLen > 75) {
        $status1 = 'error';
    } else {
        $status1 = 'warning';
    }
    $rules[] = seo_rule('META_TITLE_LENGTH', 'metadata', $status1, 6, [
        'ar' => "طول العنوان الحالي: {$titleLen} حرفاً (المثالي بين 10 و60 حرفاً).",
        'en' => "Current title length: {$titleLen} characters (ideal range: 10-60).",
    ], null, "{$titleLen} " . 'characters');

    $descLen = mb_strlen($description, 'UTF-8');
    $rules[] = seo_rule('META_DESCRIPTION_EXISTS', 'metadata', $description !== '' ? 'pass' : 'error', 4, [
        'ar' => $description !== '' ? 'يمكن توليد وصف تعريفي من المحتوى.' : 'لا يمكن توليد وصف تعريفي — المحتوى فارغ.',
        'en' => $description !== '' ? 'A meta description can be generated from the content.' : 'No meta description can be generated — content is empty.',
    ]);

    if ($descLen === 0) {
        $status2 = 'error';
    } elseif ($descLen >= 70 && $descLen <= 160) {
        $status2 = 'pass';
    } else {
        $status2 = 'warning';
    }
    $rules[] = seo_rule('META_DESCRIPTION_LENGTH', 'metadata', $status2, 4, [
        'ar' => "طول الوصف الحالي: {$descLen} حرفاً (المثالي بين 70 و160 حرفاً).",
        'en' => "Current description length: {$descLen} characters (ideal range: 70-160).",
    ], null, "{$descLen} characters");

    $rules[] = seo_rule('META_CANONICAL_HTTPS', 'metadata', 'pass', 2, [
        'ar' => 'الرابط الأساسي (canonical) يُبنى دائماً عبر HTTPS.',
        'en' => 'The canonical URL is always built over HTTPS.',
    ]);

    // ------------------------------------------------------------
    // CONTENT (20) — content-quality, status-independent
    // ------------------------------------------------------------
    $rules[] = seo_rule('CONTENT_EXISTS', 'content', !$contentEmpty ? 'pass' : 'error', 4, [
        'ar' => !$contentEmpty ? 'يحتوي المنشور على محتوى فعلي.' : 'محتوى المنشور فارغ فعلياً.',
        'en' => !$contentEmpty ? 'The post has real content.' : 'The post content is substantively empty.',
    ]);

    if ($wordCount >= 300) {
        $status3 = 'pass';
    } elseif ($wordCount >= 100) {
        $status3 = 'warning';
    } else {
        $status3 = 'error';
    }
    $rules[] = seo_rule('CONTENT_LENGTH', 'content', $status3, 6, [
        'ar' => "عدد الكلمات التقريبي: {$wordCount} (يُفضّل 300 كلمة أو أكثر).",
        'en' => "Approximate word count: {$wordCount} (300+ words recommended).",
    ], null, "{$wordCount} words");

    $firstParaLen = mb_strlen(trim($firstParagraphText), 'UTF-8');
    if ($firstParaLen >= 40) {
        $status4 = 'pass';
    } elseif ($firstParaLen > 0) {
        $status4 = 'warning';
    } else {
        $status4 = 'error';
    }
    $rules[] = seo_rule('CONTENT_FIRST_PARAGRAPH', 'content', $status4, 4, [
        'ar' => $firstPTag !== null ? 'الفقرة الأولى موجودة وتحتوي على نص.' : 'لم يتم العثور على فقرة (p) صريحة — تم تقييم بداية النص فقط.',
        'en' => $firstPTag !== null ? 'A first paragraph exists and contains text.' : 'No explicit <p> found — evaluated the start of the plain text instead.',
    ]);

    if ($keyword === null) {
        // V2: previously these 4 rules reported 'pass' (awarding full
        // points) when no keyword was supplied — exactly the "checked
        // as passed when nothing was evaluated" anti-pattern the V2
        // spec calls out. Consolidated into a single not_evaluated
        // rule instead of 4 near-duplicate "not evaluated" entries;
        // when a keyword IS supplied, the original 4 separate rules
        // run exactly as before (unchanged detection logic/weights).
        $rules[] = seo_rule('CONTENT_KEYWORD_ANALYSIS', 'content', 'not_evaluated', 6, [
            'ar' => 'لم يتم تحديد كلمة مفتاحية مستهدفة، لذا لا يمكن تقييم فحوصات الكلمة المفتاحية (في العنوان، والوصف، والفقرة الأولى، وتكرارها).',
            'en' => 'No target keyword was provided, so keyword-based checks (title, description, first paragraph, and frequency) cannot be evaluated.',
        ]);
        $internalCount = 0;
        $externalCount = 0;
    } else {
        $inTitle = seo_containsCI($title, $keyword);
        $rules[] = seo_rule('CONTENT_KEYWORD_TITLE', 'content', $inTitle ? 'pass' : 'warning', 2, [
            'ar' => $inTitle ? 'الكلمة المفتاحية موجودة في العنوان.' : 'الكلمة المفتاحية غير موجودة في العنوان.',
            'en' => $inTitle ? 'The focus keyword appears in the title.' : 'The focus keyword does not appear in the title.',
        ]);

        $inDesc = seo_containsCI($description, $keyword);
        $rules[] = seo_rule('CONTENT_KEYWORD_DESCRIPTION', 'content', $inDesc ? 'pass' : 'warning', 2, [
            'ar' => $inDesc ? 'الكلمة المفتاحية موجودة في الوصف التعريفي.' : 'الكلمة المفتاحية غير موجودة في الوصف التعريفي.',
            'en' => $inDesc ? 'The focus keyword appears in the meta description.' : 'The focus keyword does not appear in the meta description.',
        ]);

        $inFirstPara = seo_containsCI($firstParagraphText, $keyword);
        $rules[] = seo_rule('CONTENT_KEYWORD_FIRST_PARAGRAPH', 'content', $inFirstPara ? 'pass' : 'warning', 1, [
            'ar' => $inFirstPara ? 'الكلمة المفتاحية موجودة في الفقرة الأولى.' : 'الكلمة المفتاحية غير موجودة في الفقرة الأولى.',
            'en' => $inFirstPara ? 'The focus keyword appears in the first paragraph.' : 'The focus keyword does not appear in the first paragraph.',
        ]);

        $occurrences = seo_countOccurrencesCI($plainText, $keyword);
        $density = $wordCount > 0 ? ($occurrences / $wordCount) * 100 : 0;
        if ($occurrences === 0) {
            $status5 = 'error';
        } elseif ($density > 5) {
            $status5 = 'error';
        } elseif ($density < 0.5 || $density > 3) {
            $status5 = 'warning';
        } else {
            $status5 = 'pass';
        }
        $rules[] = seo_rule('CONTENT_KEYWORD_FREQUENCY', 'content', $status5, 1, [
            'ar' => sprintf('تكرار الكلمة المفتاحية: %d مرة (كثافة %.2f%%).', $occurrences, $density),
            'en' => sprintf('Focus keyword occurs %d time(s) (density %.2f%%).', $occurrences, $density),
        ]);
    }

    // ------------------------------------------------------------
    // HEADINGS (15) — content-quality, status-independent
    // ------------------------------------------------------------
    $rules[] = seo_rule('HEADING_H1_EXISTS', 'headings', $title !== '' ? 'pass' : 'error', 5, [
        'ar' => $title !== '' ? 'العنوان الرئيسي (H1) موجود — يُعرض تلقائياً كعنوان الصفحة.' : 'لا يوجد عنوان رئيسي (H1) لأن عنوان المنشور فارغ.',
        'en' => $title !== '' ? 'The main heading (H1) is present — rendered automatically as the page title.' : 'No H1 heading — the post title is empty.',
    ]);

    $bodyH1Count = count(array_filter($bodyHeadings, fn($h) => $h['level'] === 1));
    if ($bodyH1Count === 0) {
        $status6 = 'pass';
    } elseif ($bodyH1Count === 1) {
        $status6 = 'warning';
    } else {
        $status6 = 'error';
    }
    $rules[] = seo_rule('HEADING_H1_COUNT', 'headings', $status6, 4, [
        'ar' => $bodyH1Count === 0
            ? 'لا يوجد عنوان H1 إضافي داخل المحتوى (جيد — العنوان الرئيسي وحده يكفي).'
            : "يوجد {$bodyH1Count} عنوان (عناوين) H1 داخل المحتوى، بالإضافة إلى عنوان الصفحة — يُفضّل عدم تكرار H1.",
        'en' => $bodyH1Count === 0
            ? 'No extra H1 inside the body content (good — the page title alone is enough).'
            : "There are {$bodyH1Count} extra H1 heading(s) inside the body, in addition to the page title — avoid duplicate H1s.",
    ], null, (string) $bodyH1Count);

    $emptyHeadingsCount = count(array_filter($bodyHeadings, fn($h) => trim($h['text']) === ''));
    if ($emptyHeadingsCount === 0) {
        $status7 = 'pass';
    } elseif ($emptyHeadingsCount === 1) {
        $status7 = 'warning';
    } else {
        $status7 = 'error';
    }
    $rules[] = seo_rule('HEADING_EMPTY', 'headings', $status7, 3, [
        'ar' => $emptyHeadingsCount === 0 ? 'لا توجد عناوين فارغة.' : "يوجد {$emptyHeadingsCount} عنوان (عناوين) فارغة بدون نص.",
        'en' => $emptyHeadingsCount === 0 ? 'No empty headings.' : "There are {$emptyHeadingsCount} empty heading(s) with no text.",
    ]);

    $fullLevels = array_merge([1], array_map(fn($h) => $h['level'], $bodyHeadings));
    $jumps = seo_countHierarchyJumps($fullLevels);
    if ($jumps === 0) {
        $status8 = 'pass';
    } elseif ($jumps === 1) {
        $status8 = 'warning';
    } else {
        $status8 = 'error';
    }
    $rules[] = seo_rule('HEADING_HIERARCHY', 'headings', $status8, 3, [
        'ar' => $jumps === 0 ? 'ترتيب العناوين متسلسل بشكل صحيح.' : "تم رصد {$jumps} قفزة (قفزات) في تسلسل مستويات العناوين (مثال: H2 إلى H4 مباشرة).",
        'en' => $jumps === 0 ? 'Heading levels are correctly sequential.' : "Detected {$jumps} level jump(s) in the heading sequence (e.g. H2 straight to H4).",
    ]);

    // ------------------------------------------------------------
    // IMAGES (15) — content-quality, status-independent
    // ------------------------------------------------------------
    $totalImages = ($hasCover ? 1 : 0) + count($bodyImages) + count($galleryImages);
    $rules[] = seo_rule('IMAGE_EXISTS', 'images', $totalImages > 0 ? 'pass' : 'notice', 3, [
        'ar' => $totalImages > 0 ? "يحتوي المنشور على {$totalImages} صورة (صور)." : 'لا توجد أي صور في المنشور.',
        'en' => $totalImages > 0 ? "The post contains {$totalImages} image(s)." : 'The post has no images at all.',
    ], null, $totalImages);

    $bodyImageCount = count($bodyImages);
    $missingAltCount = count(array_filter($bodyImages, fn($img) => !$img['has_alt'] || trim((string) $img['alt']) === ''));
    if ($bodyImageCount === 0) {
        $status9 = 'pass';
        $msgAr = 'لا توجد صور داخل المحتوى للتحقق منها.';
        $msgEn = 'No images inside the content to check.';
    } elseif ($missingAltCount === 0) {
        $status9 = 'pass';
        $msgAr = 'جميع صور المحتوى تحتوي على نص بديل (alt).';
        $msgEn = 'All content images have alt text.';
    } elseif ($missingAltCount === $bodyImageCount) {
        $status9 = 'error';
        $msgAr = "جميع صور المحتوى ({$bodyImageCount}) بدون نص بديل (alt).";
        $msgEn = "All content images ({$bodyImageCount}) are missing alt text.";
    } else {
        $status9 = 'warning';
        $msgAr = "{$missingAltCount} من أصل {$bodyImageCount} صورة في المحتوى بدون نص بديل (alt).";
        $msgEn = "{$missingAltCount} of {$bodyImageCount} content images are missing alt text.";
    }
    $rules[] = seo_rule('IMAGE_ALT_MISSING', 'images', $status9, 4, ['ar' => $msgAr, 'en' => $msgEn]);

    $withAlt = array_values(array_filter($bodyImages, fn($img) => $img['has_alt'] && trim((string) $img['alt']) !== ''));
    $genericCount = count(array_filter($withAlt, fn($img) => seo_isGenericAlt((string) $img['alt'])));
    if (count($withAlt) === 0) {
        $status10 = 'pass';
        $msgAr = 'لا توجد نصوص بديلة لتقييم جودتها.';
        $msgEn = 'No alt text to evaluate for quality.';
    } elseif ($genericCount === 0) {
        $status10 = 'pass';
        $msgAr = 'النصوص البديلة الموجودة وصفية وليست عامة.';
        $msgEn = 'Existing alt text is descriptive, not generic.';
    } else {
        $status10 = 'warning';
        $msgAr = "{$genericCount} نص بديل يبدو عاماً أو باسم ملف (مثل \"photo.jpg\").";
        $msgEn = "{$genericCount} alt text value looks generic or filename-like (e.g. \"photo.jpg\").";
    }
    $rules[] = seo_rule('IMAGE_ALT_GENERIC', 'images', $status10, 2, ['ar' => $msgAr, 'en' => $msgEn]);

    $altValues = array_map(fn($img) => mb_strtolower(trim((string) $img['alt']), 'UTF-8'), $withAlt);
    $duplicateAlt = count($altValues) !== count(array_unique($altValues));
    $rules[] = seo_rule('IMAGE_ALT_DUPLICATE', 'images', $duplicateAlt ? 'warning' : 'pass', 2, [
        'ar' => $duplicateAlt ? 'يوجد نص بديل مكرر على أكثر من صورة.' : 'لا يوجد تكرار في النصوص البديلة.',
        'en' => $duplicateAlt ? 'The same alt text is duplicated across more than one image.' : 'No duplicate alt text found.',
    ]);

    $rules[] = seo_rule('IMAGE_COVER_ALT_UNSTORED', 'images', $hasCover ? 'notice' : 'pass', 2, [
        'ar' => $hasCover
            ? 'صورة الغلاف موجودة، لكن النظام الحالي لا يخزّن نصاً بديلاً (alt) لها — لا يمكن التحقق منه.'
            : 'لا توجد صورة غلاف.',
        'en' => $hasCover
            ? 'A cover image is set, but the current system does not store alt text for it — cannot be verified.'
            : 'No cover image is set.',
    ]);

    $galleryApplicable = ($type === 'project');
    $galleryWarn = ($galleryApplicable && $hasGallery);
    $rules[] = seo_rule('IMAGE_GALLERY_ALT_UNSTORED', 'images', $galleryWarn ? 'notice' : 'pass', 2, [
        'ar' => $galleryWarn
            ? 'توجد صور في معرض الإنجاز، لكن النظام الحالي لا يخزّن نصاً بديلاً (alt) لها — لا يمكن التحقق منه.'
            : 'لا توجد صور معرض لهذا المنشور.',
        'en' => $galleryWarn
            ? 'Gallery images exist for this project, but the current system does not store alt text for them — cannot be verified.'
            : 'No gallery images for this post.',
    ]);

    // ------------------------------------------------------------
    // LINKS (10) — content-quality, status-independent
    // ------------------------------------------------------------
    $emptyAnchorCount = count(array_filter($bodyLinks, fn($l) => trim($l['text']) === '' && !$l['hasMedia']));
    if ($emptyAnchorCount === 0) {
        $status11 = 'pass';
    } elseif ($emptyAnchorCount === 1) {
        $status11 = 'warning';
    } else {
        $status11 = 'error';
    }
    $rules[] = seo_rule('LINK_EMPTY_ANCHOR_TEXT', 'links', $status11, 4, [
        'ar' => $emptyAnchorCount === 0 ? 'لا توجد روابط بنص فارغ.' : "يوجد {$emptyAnchorCount} رابط (روابط) بدون نص واضح.",
        'en' => $emptyAnchorCount === 0 ? 'No links with empty anchor text.' : "There are {$emptyAnchorCount} link(s) with no visible anchor text.",
    ]);

    $classifications = array_map(fn($l) => seo_classifyLink($l['href']), $bodyLinks);
    $invalidCount = count(array_filter($classifications, fn($c) => $c === 'invalid'));
    $totalLinks = count($bodyLinks);
    if ($totalLinks === 0 || $invalidCount === 0) {
        $status12 = 'pass';
    } elseif ($invalidCount === $totalLinks) {
        $status12 = 'error';
    } else {
        $status12 = 'warning';
    }
    $rules[] = seo_rule('LINK_URL_VALIDITY', 'links', $status12, 3, [
        'ar' => $invalidCount === 0 ? 'جميع الروابط تحتوي على عناوين URL صالحة.' : "يوجد {$invalidCount} من أصل {$totalLinks} رابط بعنوان URL غير صالح.",
        'en' => $invalidCount === 0 ? 'All links have valid URLs.' : "{$invalidCount} of {$totalLinks} link(s) have an invalid URL.",
    ]);

    $internalCount = count(array_filter($classifications, fn($c) => $c === 'internal'));
    $externalCount = count(array_filter($classifications, fn($c) => $c === 'external'));
    if (($internalCount + $externalCount) === 0) {
        $status13 = 'pass';
        $msgAr = 'لا توجد روابط داخلية أو خارجية للتصنيف.';
        $msgEn = 'No internal or external links to classify.';
    } elseif ($internalCount === 0) {
        $status13 = 'notice';
        $msgAr = "جميع الروابط ({$externalCount}) خارجية — لا يوجد ربط داخلي بمحتوى الموقع.";
        $msgEn = "All links ({$externalCount}) are external — no internal linking to the site's own content.";
    } else {
        $status13 = 'pass';
        $msgAr = "{$internalCount} رابط داخلي و{$externalCount} رابط خارجي.";
        $msgEn = "{$internalCount} internal link(s) and {$externalCount} external link(s).";
    }
    $rules[] = seo_rule('LINK_TYPES', 'links', $status13, 3, ['ar' => $msgAr, 'en' => $msgEn], null, "{$internalCount} internal / {$externalCount} external");

    // ------------------------------------------------------------
    // METADATA — TITLE / DESCRIPTION UNIQUENESS (advisory, 0 points)
    // $otherPosts is supplied by analyze.php via a single, capped DB
    // query (id, title, content of other non-deleted posts) — this
    // function still makes no DB calls itself. Meta description is
    // not a stored column, so its uniqueness is checked by running
    // the same seo_buildDescription() used above over that one
    // result set in PHP, not via a separate query per post (no N+1).
    // Never fatal — always a 'warning' at most, per spec.
    // ------------------------------------------------------------
    if ($title === '') {
        $titleUnique = true; // nothing meaningful to compare
    } else {
        $titleUnique = true;
        foreach ($otherPosts as $other) {
            if (mb_strtolower(trim((string) ($other['title'] ?? '')), 'UTF-8') === mb_strtolower($title, 'UTF-8')) {
                $titleUnique = false;
                break;
            }
        }
    }
    $rules[] = seo_rule('META_TITLE_UNIQUE', 'metadata', $titleUnique ? 'pass' : 'notice', 0, [
        'ar' => $titleUnique ? 'عنوان المنشور فريد بين المقالات الأخرى.' : 'يوجد منشور آخر بنفس العنوان تماماً.',
        'en' => $titleUnique ? 'The post title is unique among other articles.' : 'Another post has the exact same title.',
    ]);

    if ($description === '') {
        $descUnique = true;
    } else {
        $descUnique = true;
        foreach ($otherPosts as $other) {
            $otherDesc = seo_buildDescription((string) ($other['content'] ?? ''));
            if ($otherDesc !== '' && mb_strtolower($otherDesc, 'UTF-8') === mb_strtolower($description, 'UTF-8')) {
                $descUnique = false;
                break;
            }
        }
    }
    $rules[] = seo_rule('META_DESCRIPTION_UNIQUE', 'metadata', $descUnique ? 'pass' : 'notice', 0, [
        'ar' => $descUnique ? 'الوصف التعريفي فريد بين المقالات الأخرى (ضمن العينة المفحوصة).' : 'يوجد منشور آخر بوصف تعريفي مطابق تقريباً (ضمن العينة المفحوصة).',
        'en' => $descUnique ? 'The meta description is unique among other articles (within the sample checked).' : 'Another post has a matching meta description (within the sample checked).',
    ]);

    // ------------------------------------------------------------
    // SOCIAL (10) — CURRENT LIVE-PAGE state: post.php only emits
    // Open Graph / Twitter tags for status='published' posts. The
    // two rules immediately below are the ORIGINAL weighted checks
    // (tag existence, and post-specific vs. generic-default
    // quality) — unchanged. The field-level rules that follow are
    // additive and worth 0 points each: they name every individual
    // og:*/twitter:* tag post.php actually emits, for transparency,
    // without perturbing the existing 10-point category weighting.
    // ------------------------------------------------------------
    $canonicalUrl = SEO_SITE_URL . '/post.html?id=' . (int) ($post['id'] ?? 0);

    if (!$isLive) {
        $rules[] = seo_rule('SOCIAL_OPEN_GRAPH', 'social', 'error', 5, [
            'ar' => 'المنشور غير منشور حالياً، لذا لا تُعرض وسوم Open Graph على الصفحة العامة بعد.',
            'en' => 'The post is not currently published, so Open Graph tags are not yet rendered on the public page.',
        ]);
        $rules[] = seo_rule('SOCIAL_TWITTER', 'social', 'error', 5, [
            'ar' => 'المنشور غير منشور حالياً، لذا لا تُعرض وسوم بطاقة Twitter/X على الصفحة العامة بعد.',
            'en' => 'The post is not currently published, so Twitter/X Card tags are not yet rendered on the public page.',
        ]);

        $notLiveTagAr = 'المنشور غير منشور حالياً — هذا الوسم لا يُعرض على الصفحة العامة بعد.';
        $notLiveTagEn = 'The post is not currently published — this tag is not yet rendered on the public page.';
        foreach ([
            'SOCIAL_OG_TITLE', 'SOCIAL_OG_DESCRIPTION', 'SOCIAL_OG_URL', 'SOCIAL_OG_IMAGE', 'SOCIAL_OG_TYPE',
            'SOCIAL_TWITTER_CARD', 'SOCIAL_TWITTER_TITLE', 'SOCIAL_TWITTER_DESCRIPTION', 'SOCIAL_TWITTER_IMAGE',
        ] as $fieldId) {
            $rules[] = seo_rule($fieldId, 'social', 'error', 0, ['ar' => $notLiveTagAr, 'en' => $notLiveTagEn]);
        }
    } else {
        $socialQualityGood = $hasCover && !$contentEmpty;
        $socialMsgAr = $socialQualityGood
            ? 'الوسوم تستخدم صورة ووصفاً خاصين بالمنشور (وليست القيم الافتراضية العامة للموقع).'
            : 'الوسوم موجودة تقنياً، لكنها ستستخدم الصورة و/أو الوصف الافتراضي العام للموقع بدل محتوى خاص بالمنشور.';
        $socialMsgEn = $socialQualityGood
            ? "The tags use an image and description specific to this post (not the site's generic defaults)."
            : "The tags exist technically, but will fall back to the site's generic default image and/or description instead of post-specific content.";
        $rules[] = seo_rule('SOCIAL_OPEN_GRAPH', 'social', $socialQualityGood ? 'pass' : 'notice', 5, ['ar' => $socialMsgAr, 'en' => $socialMsgEn]);
        $rules[] = seo_rule('SOCIAL_TWITTER', 'social', $socialQualityGood ? 'pass' : 'notice', 5, [
            'ar' => $socialMsgAr . ' (تستخدم بطاقة Twitter/X نفس القيم الحالية.)',
            'en' => $socialMsgEn . ' (The Twitter/X Card uses the same current values.)',
        ]);

        // og:title / twitter:title — mirrors post.php's $metaTitle = $post['title']
        $ogTitleOk = $title !== '';
        $rules[] = seo_rule('SOCIAL_OG_TITLE', 'social', $ogTitleOk ? 'pass' : 'error', 0, [
            'ar' => $ogTitleOk ? "og:title موجود ويطابق عنوان المنشور: \"{$title}\"." : 'og:title سيكون فارغاً لأن عنوان المنشور فارغ.',
            'en' => $ogTitleOk ? "og:title is present and matches the post title: \"{$title}\"." : 'og:title will be empty because the post title is empty.',
        ]);
        $rules[] = seo_rule('SOCIAL_TWITTER_TITLE', 'social', $ogTitleOk ? 'pass' : 'error', 0, [
            'ar' => $ogTitleOk ? 'twitter:title يستخدم نفس عنوان المنشور.' : 'twitter:title سيكون فارغاً لأن عنوان المنشور فارغ.',
            'en' => $ogTitleOk ? 'twitter:title uses the same post title.' : 'twitter:title will be empty because the post title is empty.',
        ]);

        // og:description / twitter:description — real content-derived text vs. the site's generic fallback
        $rules[] = seo_rule('SOCIAL_OG_DESCRIPTION', 'social', $contentEmpty ? 'notice' : 'pass', 0, [
            'ar' => $contentEmpty ? 'og:description سيستخدم النص الافتراضي العام للموقع لأن محتوى المنشور فارغ.' : 'og:description يُبنى من محتوى المنشور الفعلي.',
            'en' => $contentEmpty ? "og:description will fall back to the site's generic text because the post content is empty." : 'og:description is built from the actual post content.',
        ]);
        $rules[] = seo_rule('SOCIAL_TWITTER_DESCRIPTION', 'social', $contentEmpty ? 'notice' : 'pass', 0, [
            'ar' => $contentEmpty ? 'twitter:description سيستخدم النص الافتراضي العام للموقع لأن محتوى المنشور فارغ.' : 'twitter:description يستخدم نفس الوصف المبني من المحتوى.',
            'en' => $contentEmpty ? "twitter:description will fall back to the site's generic text because the post content is empty." : 'twitter:description uses the same content-derived description.',
        ]);

        // og:image / twitter:image — post-specific cover vs. the site's default profile image
        $rules[] = seo_rule('SOCIAL_OG_IMAGE', 'social', $hasCover ? 'pass' : 'notice', 0, [
            'ar' => $hasCover ? 'og:image يستخدم صورة غلاف خاصة بالمنشور.' : 'og:image سيستخدم صورة الموقع الافتراضية (profile.png) لعدم وجود صورة غلاف.',
            'en' => $hasCover ? 'og:image uses a cover image specific to this post.' : "og:image will fall back to the site's default image (profile.png) since no cover image is set.",
        ]);
        $rules[] = seo_rule('SOCIAL_TWITTER_IMAGE', 'social', $hasCover ? 'pass' : 'notice', 0, [
            'ar' => $hasCover ? 'twitter:image يستخدم نفس صورة الغلاف الخاصة بالمنشور.' : 'twitter:image سيستخدم صورة الموقع الافتراضية لعدم وجود صورة غلاف.',
            'en' => $hasCover ? 'twitter:image uses the same post-specific cover image.' : "twitter:image will fall back to the site's default image since no cover image is set.",
        ]);

        // og:url — always the real canonical post URL by construction
        $rules[] = seo_rule('SOCIAL_OG_URL', 'social', 'pass', 0, [
            'ar' => "og:url يطابق الرابط الأساسي للمنشور: {$canonicalUrl}",
            'en' => "og:url matches the post's canonical URL: {$canonicalUrl}",
        ], null, $canonicalUrl);

        // og:type — always 'article' for a published post
        $rules[] = seo_rule('SOCIAL_OG_TYPE', 'social', 'pass', 0, [
            'ar' => 'og:type يساوي "article" للمنشورات المنشورة، كما هو متوقع.',
            'en' => 'og:type is "article" for a published post, as expected.',
        ]);

        // twitter:card — always the same fixed value post.php emits
        $rules[] = seo_rule('SOCIAL_TWITTER_CARD', 'social', 'pass', 0, [
            'ar' => 'twitter:card يساوي "summary_large_image".',
            'en' => 'twitter:card is set to "summary_large_image".',
        ]);
    }

    // ------------------------------------------------------------
    // STRUCTURED DATA (5) — CURRENT LIVE-PAGE state: post.php only
    // emits the JSON-LD block for status='published' posts. The two
    // rules immediately below are the ORIGINAL weighted checks
    // (JSON-LD existence + @type correctness) — unchanged. The
    // field-level rules that follow are additive, 0 points each,
    // and mirror exactly the fields post.php actually populates in
    // $jsonLd (headline/description/image/url/mainEntityOfPage/
    // author/publisher/datePublished/dateModified) — never inventing
    // data the application doesn't actually have.
    // ------------------------------------------------------------
    if (!$isLive) {
        $rules[] = seo_rule('STRUCTURED_JSONLD_EXISTS', 'structured_data', 'error', 3, [
            'ar' => 'المنشور غير منشور حالياً، لذا لا يتم توليد بيانات JSON-LD على الصفحة العامة بعد.',
            'en' => 'The post is not currently published, so JSON-LD data is not yet generated on the public page.',
        ]);
        $rules[] = seo_rule('STRUCTURED_TYPE', 'structured_data', 'error', 2, [
            'ar' => 'لا يمكن تقييم نوع البيانات المنظمة لأن الصفحة العامة لا تعرض البيانات المنظمة قبل النشر.',
            'en' => 'Structured-data type cannot be evaluated because the public page does not render it before publishing.',
        ]);

        $notLiveFieldAr = 'المنشور غير منشور حالياً — هذا الحقل غير موجود في JSON-LD على الصفحة العامة بعد.';
        $notLiveFieldEn = 'The post is not currently published — this field is not present in JSON-LD on the public page yet.';
        foreach ([
            'STRUCTURED_FIELD_HEADLINE', 'STRUCTURED_FIELD_DESCRIPTION', 'STRUCTURED_FIELD_IMAGE',
            'STRUCTURED_FIELD_URL', 'STRUCTURED_FIELD_MAIN_ENTITY', 'STRUCTURED_FIELD_AUTHOR',
            'STRUCTURED_FIELD_PUBLISHER', 'STRUCTURED_FIELD_DATE_PUBLISHED', 'STRUCTURED_FIELD_DATE_MODIFIED',
        ] as $fieldId) {
            $rules[] = seo_rule($fieldId, 'structured_data', 'error', 0, ['ar' => $notLiveFieldAr, 'en' => $notLiveFieldEn]);
        }
    } else {
        $rules[] = seo_rule('STRUCTURED_JSONLD_EXISTS', 'structured_data', 'pass', 3, [
            'ar' => 'يتم توليد بيانات JSON-LD تلقائياً لكل منشور منشور.',
            'en' => 'JSON-LD data is generated automatically for every published post.',
        ]);
        $expectedSchemaType = $type === 'project' ? 'Article' : 'BlogPosting';
        $rules[] = seo_rule('STRUCTURED_TYPE', 'structured_data', 'pass', 2, [
            'ar' => "نوع البيانات المنظمة الصحيح لهذا المنشور هو \"{$expectedSchemaType}\".",
            'en' => "The correct structured-data type for this post is \"{$expectedSchemaType}\".",
        ]);

        $rules[] = seo_rule('STRUCTURED_FIELD_HEADLINE', 'structured_data', $title !== '' ? 'pass' : 'error', 0, [
            'ar' => $title !== '' ? "headline موجود: \"{$title}\"." : 'headline سيكون فارغاً لأن عنوان المنشور فارغ.',
            'en' => $title !== '' ? "headline is present: \"{$title}\"." : 'headline will be empty because the post title is empty.',
        ]);
        $rules[] = seo_rule('STRUCTURED_FIELD_DESCRIPTION', 'structured_data', $contentEmpty ? 'notice' : 'pass', 0, [
            'ar' => $contentEmpty ? 'description سيستخدم النص الافتراضي العام للموقع لأن المحتوى فارغ.' : 'description يُبنى من محتوى المنشور الفعلي.',
            'en' => $contentEmpty ? "description will fall back to the site's generic text because the content is empty." : 'description is built from the actual post content.',
        ]);
        $rules[] = seo_rule('STRUCTURED_FIELD_IMAGE', 'structured_data', $hasCover ? 'pass' : 'notice', 0, [
            'ar' => $hasCover ? 'image يستخدم صورة غلاف خاصة بالمنشور.' : 'image سيستخدم صورة الموقع الافتراضية لعدم وجود صورة غلاف.',
            'en' => $hasCover ? 'image uses a cover image specific to this post.' : "image will fall back to the site's default image since no cover image is set.",
        ]);
        $rules[] = seo_rule('STRUCTURED_FIELD_URL', 'structured_data', 'pass', 0, [
            'ar' => "url يطابق الرابط الأساسي للمنشور: {$canonicalUrl}",
            'en' => "url matches the post's canonical URL: {$canonicalUrl}",
        ], null, $canonicalUrl);
        $rules[] = seo_rule('STRUCTURED_FIELD_MAIN_ENTITY', 'structured_data', 'pass', 0, [
            'ar' => 'mainEntityOfPage موجود ويشير إلى رابط المنشور نفسه.',
            'en' => "mainEntityOfPage is present and points to the post's own URL.",
        ]);
        $rules[] = seo_rule('STRUCTURED_FIELD_AUTHOR', 'structured_data', 'pass', 0, [
            'ar' => 'author موجود (بيانات ثابتة تخص صاحب الموقع).',
            'en' => "author is present (fixed data for the site owner).",
        ]);
        $rules[] = seo_rule('STRUCTURED_FIELD_PUBLISHER', 'structured_data', 'pass', 0, [
            'ar' => 'publisher موجود (بيانات ثابتة تخص صاحب الموقع).',
            'en' => "publisher is present (fixed data for the site owner).",
        ]);

        $hasCreatedAt = !empty($post['created_at']) && strtotime((string) $post['created_at']) !== false;
        $rules[] = seo_rule('STRUCTURED_FIELD_DATE_PUBLISHED', 'structured_data', $hasCreatedAt ? 'pass' : 'warning', 0, [
            'ar' => $hasCreatedAt ? 'datePublished موجود وصالح.' : 'datePublished غير متوفر — لا يوجد تاريخ إنشاء صالح لهذا المنشور.',
            'en' => $hasCreatedAt ? 'datePublished is present and valid.' : 'datePublished is missing — this post has no valid creation date.',
        ]);
        $hasUpdatedAt = !empty($post['updated_at']) && strtotime((string) $post['updated_at']) !== false;
        $rules[] = seo_rule('STRUCTURED_FIELD_DATE_MODIFIED', 'structured_data', $hasUpdatedAt ? 'pass' : 'warning', 0, [
            'ar' => $hasUpdatedAt ? 'dateModified موجود وصالح.' : 'dateModified غير متوفر — لا يوجد تاريخ تحديث صالح لهذا المنشور.',
            'en' => $hasUpdatedAt ? 'dateModified is present and valid.' : 'dateModified is missing — this post has no valid update date.',
        ]);
    }

    // ------------------------------------------------------------
    // TECHNICAL SEO (5) — CURRENT LIVE-PAGE indexability, honestly
    // reported per status (this is the rule set most directly
    // answering "can this currently be indexed publicly?").
    // TECH_INDEXABILITY's "not yet published" case is pinned to
    // 'warning' severity (not 'critical') — it reflects the current,
    // expected authoring state (already surfaced via the not-public
    // banner in the UI), not a genuine content defect.
    // ------------------------------------------------------------
    if (!$isLive) {
        $statusLabelAr = $status === 'hidden' ? 'مخفي' : 'مسودة';
        $statusLabelEn = $status === 'hidden' ? 'hidden' : 'draft';
        $rules[] = seo_rule('TECH_INDEXABILITY', 'technical_seo', 'error', 2, [
            'ar' => "المنشور حالياً بحالة \"{$statusLabelAr}\" — تعرض الصفحة العامة وسم noindex ولا يمكن فهرستها.",
            'en' => "The post is currently \"{$statusLabelEn}\" — the public page renders a noindex tag and cannot be indexed.",
        ], 'warning');
        $rules[] = seo_rule('TECH_SITEMAP_ELIGIBILITY', 'technical_seo', 'error', 2, [
            'ar' => 'المنشور غير مؤهل للظهور في خريطة الموقع (sitemap.xml) قبل نشره.',
            'en' => 'The post is not eligible to appear in the sitemap (sitemap.xml) before it is published.',
        ]);
        $rules[] = seo_rule('TECH_PUBLIC_URL', 'technical_seo', 'error', 1, [
            'ar' => 'رابط المنشور العام /post.html?id=' . (int) ($post['id'] ?? 0) . ' يعرض حالياً صفحة "غير موجود" (noindex) للزوار حتى يُنشر.',
            'en' => 'The public URL /post.html?id=' . (int) ($post['id'] ?? 0) . ' currently shows a "not found" (noindex) page to visitors until it is published.',
        ]);
    } else {
        $rules[] = seo_rule('TECH_INDEXABILITY', 'technical_seo', 'pass', 2, [
            'ar' => 'المنشور قابل للفهرسة (منشور وغير محذوف، بدون وسم noindex).',
            'en' => 'The post is indexable (published, not deleted, no noindex tag).',
        ]);
        $rules[] = seo_rule('TECH_SITEMAP_ELIGIBILITY', 'technical_seo', 'pass', 2, [
            'ar' => 'المنشور مؤهل للظهور في خريطة الموقع (sitemap.xml).',
            'en' => 'The post is eligible to appear in the sitemap (sitemap.xml).',
        ]);
        $rules[] = seo_rule('TECH_PUBLIC_URL', 'technical_seo', 'pass', 1, [
            'ar' => 'رابط المنشور العام يعمل عبر /post.html?id=' . (int) ($post['id'] ?? 0) . '.',
            'en' => 'The public URL works at /post.html?id=' . (int) ($post['id'] ?? 0) . '.',
        ]);
    }

    // ------------------------------------------------------------
    // AGGREGATE SCORING
    // ------------------------------------------------------------
    $categories = [];
    foreach (SEO_CATEGORIES as $key => $meta) {
        $categories[$key] = [
            'key'    => $key,
            'label'  => $meta['label'],
            'earned' => 0,
            'max'    => $meta['max'],
        ];
    }
    foreach ($rules as $rule) {
        $categories[$rule['category']]['earned'] += $rule['points'];
    }
    foreach ($categories as $key => $cat) {
        $categories[$key]['percentage'] = $cat['max'] > 0 ? (int) round(($cat['earned'] / $cat['max']) * 100) : 100;
    }

    $totalEarned = array_sum(array_column($categories, 'earned'));
    $totalEarned = max(0, min(100, $totalEarned));

    // V2: added the 'excellent' tier and moved to the four thresholds
    // explicitly specified in the approved V2 spec (90/75/50), replacing
    // the original three-tier model (good >=80, needs_improvement >=50,
    // poor otherwise). This is a deliberate, documented threshold change,
    // not a preservation of the old boundary — see the V2 approval notes
    // and the updated tests in test_seo_analyzer_extensions.php.
    if ($totalEarned >= 90) {
        $scoreStatus = 'excellent';
    } elseif ($totalEarned >= 75) {
        $scoreStatus = 'good';
    } elseif ($totalEarned >= 50) {
        $scoreStatus = 'needs_improvement';
    } else {
        $scoreStatus = 'poor';
    }

    // ------------------------------------------------------------
    // SUMMARY — five mutually-exclusive buckets, partitioned by
    // status first (notice/not_evaluated are always their own
    // bucket regardless of severity), then by severity for the
    // remaining warning/error statuses. not_evaluated is NEVER
    // counted as passed, per the V2 spec.
    // ------------------------------------------------------------
    $summary = ['critical' => 0, 'warnings' => 0, 'notices' => 0, 'passed' => 0, 'not_evaluated' => 0];
    foreach ($rules as $rule) {
        if ($rule['status'] === 'pass') {
            $summary['passed']++;
        } elseif ($rule['status'] === 'notice') {
            $summary['notices']++;
        } elseif ($rule['status'] === 'not_evaluated') {
            $summary['not_evaluated']++;
        } elseif ($rule['severity'] === 'critical') {
            $summary['critical']++;
        } else {
            $summary['warnings']++;
        }
    }

    // ------------------------------------------------------------
    // QUICK WINS — up to 5 actionable issues (excludes pass and
    // not_evaluated), ranked deterministically:
    //   priority desc -> impact desc -> effort desc (lower effort
    //   first) -> category declaration order -> rule id asc.
    // This is NOT "the 5 biggest score deductions" — a cheap,
    // decent-impact notice can rank above an expensive critical one
    // if its priority/impact/effort combination scores higher (see
    // seo_priorityForRule's documented formula).
    // ------------------------------------------------------------
    $rank = ['high' => 3, 'medium' => 2, 'low' => 1];
    $categoryOrder = array_flip(array_keys(SEO_CATEGORIES));
    $actionable = array_values(array_filter($rules, fn($r) => !in_array($r['status'], ['pass', 'not_evaluated'], true)));
    usort($actionable, function ($a, $b) use ($rank, $categoryOrder) {
        $pa = $rank[$a['priority']] ?? 0;
        $pb = $rank[$b['priority']] ?? 0;
        if ($pa !== $pb) return $pb - $pa;

        $ia = $rank[$a['impact']] ?? 0;
        $ib = $rank[$b['impact']] ?? 0;
        if ($ia !== $ib) return $ib - $ia;

        // effort: LOW is preferred first, so invert the raw rank comparison
        $ea = $rank[$a['effort'] === 'low' ? 'high' : ($a['effort'] === 'high' ? 'low' : 'medium')] ?? 0;
        $eb = $rank[$b['effort'] === 'low' ? 'high' : ($b['effort'] === 'high' ? 'low' : 'medium')] ?? 0;
        if ($ea !== $eb) return $eb - $ea;

        $ca = $categoryOrder[$a['category']] ?? 999;
        $cb = $categoryOrder[$b['category']] ?? 999;
        if ($ca !== $cb) return $ca - $cb;

        return strcmp($a['id'], $b['id']);
    });
    $quickWins = array_map(function ($r) {
        return [
            'ruleId'   => $r['id'],
            'category' => $r['category'],
            'status'   => $r['status'],
            'severity' => $r['severity'],
            'priority' => $r['priority'],
            'impact'   => $r['impact'],
            'effort'   => $r['effort'],
        ];
    }, array_slice($actionable, 0, 5));

    return [
        'score'        => $totalEarned,
        'max_score'    => 100,
        'score_status' => $scoreStatus, // 'excellent' | 'good' | 'needs_improvement' | 'poor'
        'summary'      => $summary,
        'quick_wins'   => $quickWins,
        'categories'   => array_values($categories),
        'rules'        => $rules,
        'meta'         => [
            'post_status'           => $status,
            'is_public'             => $isLive,
            'focus_keyword'         => $keyword,
            'generated_title'       => $title,
            'generated_description' => $description,
            'word_count'            => $wordCount,
            'image_count'           => $totalImages,
            'link_count'            => count($bodyLinks),
            'internal_link_count'   => $internalCount,
            'external_link_count'   => $externalCount,
            'uniqueness_sample_size' => count($otherPosts),
        ],
    ];
}
