// ============================================================
// ADMIN — SHARED / COMMON JAVASCRIPT
// Loaded on every admin page (dashboard, articles, projects,
// users, reviews). Contains authentication check, logout, the
// loading indicator, image upload helpers, and small utilities
// that every page needs.
//
// Page-specific logic (posts, projects, users, reviews,
// dashboard stats) lives in its own js/*.js file and is loaded
// in addition to this one.
// ============================================================


// ============================================================
// GLOBAL STATE
// ============================================================

let savedRange = null;

let currentUser = null;

// Read once from the <meta name="csrf-token"> tag rendered by
// partials/layout_top.php on every admin page.
const csrfToken =
    document.querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content') || '';

// ============================================================
// ARTICLE HTML SANITIZER (CLIENT-SIDE DEFENSE-IN-DEPTH)
// [IMP-006 / SEC-05] Matches server-side allowlist policy
// ============================================================
function sanitizeArticleHtml(dirtyHtml) {
    if (!dirtyHtml) return '';
    if (typeof DOMPurify !== 'undefined') {
        return DOMPurify.sanitize(dirtyHtml, {
            ALLOWED_TAGS: [
                'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
                'ul', 'ol', 'li',
                'strong', 'em', 'b', 'i',
                'blockquote',
                'a', 'img', 'br', 'mark',
            ],
            ALLOWED_ATTR: ['href', 'target', 'rel', 'title', 'src', 'alt', 'class', 'dir'],
            ALLOW_DATA_ATTR: false,
        });
    }
    return dirtyHtml;
}


// ============================================================
// AUTHENTICATION CHECK
// GET /api/auth/session.php
// ============================================================

async function checkAdminAuthentication() {

    console.log(
        '🔐 Checking admin authentication...'
    );


    try {

        const response = await fetch(
            '/api/auth/session.php',
            {
                method: 'GET',
                credentials: 'same-origin',
            }
        );


        const result = await response.json();


        if (!result.success) {

            console.warn(
                '❌ No authenticated session.'
            );

            window.location.href =
                './login.php';

            return false;

        }


        console.log(
            '✅ Admin authenticated:',
            result.email
        );


        currentUser = {
            id: result.id,
            name: result.name,
            email: result.email,
            role: result.role,
        };


        return true;


    } catch (error) {

        console.error(
            '❌ Authentication check failed:',
            error
        );

        window.location.href =
            './login.php';

        return false;

    }

}


// ============================================================
// LOGOUT ADMIN
// POST /api/auth/logout.php
// ============================================================

async function logoutAdmin() {

    const isDirty =
        typeof UnsavedChangesGuard !== 'undefined' && UnsavedChangesGuard.isDirty();

    const promptMsg =
        isDirty
            ? 'You have unsaved changes. Are you sure you want to leave and log out?'
            : 'Are you sure you want to log out?';

    const confirmed =
        confirm(
            promptMsg
        );


    if (!confirmed) {
        return;
    }


    if (isDirty) {
        UnsavedChangesGuard.bypass();
    }


    try {

        const response = await fetch(
            '/api/auth/logout.php',
            {
                method: 'POST',
                credentials: 'same-origin',
            }
        );


        const result = await response.json();


        if (result.success) {

            console.log(
                '✅ Admin logged out.'
            );

        }


        window.location.href =
            './login.php';


    } catch (error) {

        console.error(
            '❌ Logout error:',
            error
        );


        showToast(
            'An error occurred during logout.',
            'error'
        );

    }

}


// ============================================================
// LOADING UI
// Disables whichever submit button(s) exist on the current page
// (each page only ever has zero, one, or two of these ids), and
// toggles the shared #loading indicator.
// ============================================================

function showLoading(show) {

    const loading =
        document.getElementById(
            'loading'
        );


    if (loading) {

        loading.style.display =
            show
                ? 'flex'
                : 'none';

    }


    // Every page-specific submit button that might exist.
    // Missing ids are simply skipped — see the `if (el)` guard.
    [
        'submitBtn',
        'achieveSubmitBtn',
        'userSubmitBtn',
    ].forEach(
        id => {

            const el =
                document.getElementById(id);

            if (el) {

                el.disabled =
                    show;

            }

        }
    );

}


// ============================================================
// UPLOAD IMAGE TO PHP API
// POST /api/uploads/image.php
// Returns the public URL of the uploaded image.
// ============================================================

// Client-side pre-check only — a UX nicety to fail fast without a
// wasted round-trip. The server (api/uploads/image.php ->
// UPLOAD_MAX_SIZE) is the authoritative limit; keep this number in
// sync with it, but never treat this check alone as security.
const MAX_UPLOAD_SIZE_BYTES = 20 * 1024 * 1024;

async function uploadImageToAPI(file) {

    if (file && file.size > MAX_UPLOAD_SIZE_BYTES) {
        throw new Error('Image file size must not exceed 20MB.');
    }

    const formData = new FormData();
    formData.append('image', file, 'upload_' + Date.now() + '_' + file.name.replace(/[^a-zA-Z0-9.-_]/g, '_'));

    const response = await fetch(
        '/api/uploads/image.php',
        {
            method: 'POST',
            headers: {
                'X-CSRF-Token': csrfToken,
            },
            credentials: 'same-origin',
            body: formData,
        }
    );

    const result = await response.json();

    if (!result.success) {
        throw new Error(result.message || 'Image upload failed.');
    }

    return result.url;

}


// ============================================================
// INLINE IMAGE UPLOAD
// ============================================================

async function uploadInlineImage(
    editorId,
    inputId
) {

    const editor =
        document.getElementById(
            editorId
        );


    const input =
        document.getElementById(
            inputId
        );


    if (!editor || !input) {

        console.error(
            'Editor or input not found.'
        );

        return;
    }


    const selection =
        window.getSelection();


    // --------------------------------------------------------
    // Save cursor position
    // --------------------------------------------------------

    if (
        selection &&
        selection.rangeCount > 0 &&
        editor.contains(
            selection.anchorNode
        )
    ) {

        savedRange =
            selection.getRangeAt(0);

    } else {

        savedRange =
            document.createRange();


        savedRange.selectNodeContents(
            editor
        );


        savedRange.collapse(
            false
        );

    }


    // --------------------------------------------------------
    // File selection
    // --------------------------------------------------------

    input.onchange =
        async (event) => {

            const file =
                event.target.files[0];


            if (!file) {

                return;
            }


            showLoading(true);


            try {

                // ------------------------------------------------
                // Upload via PHP API
                // ------------------------------------------------

                const publicUrl =
                    await uploadImageToAPI(file);


                // ------------------------------------------------
                // Restore cursor
                // ------------------------------------------------

                const currentSelection =
                    window.getSelection();


                if (
                    savedRange &&
                    currentSelection
                ) {

                    currentSelection
                        .removeAllRanges();


                    currentSelection
                        .addRange(
                            savedRange
                        );

                }


                // ------------------------------------------------
                // Create image
                // ------------------------------------------------

                const imgNode =
                    document.createElement(
                        'img'
                    );


                imgNode.src =
                    publicUrl;


                // Sizing/spacing is handled entirely by CSS via
                // this class (see admin.css .editor-content
                // .article-image and css/post.css .post-content
                // .article-image), not inline styles, so that the
                // author can change the size later (see
                // openInlineImageSizePicker() below) just by
                // swapping the class — no second image system.
                imgNode.className =
                    'article-image';


                // ------------------------------------------------
                // Insert image
                // ------------------------------------------------

                savedRange.insertNode(
                    imgNode
                );


                savedRange.setStartAfter(
                    imgNode
                );


                savedRange.setEndAfter(
                    imgNode
                );


                if (currentSelection) {

                    currentSelection
                        .removeAllRanges();


                    currentSelection
                        .addRange(
                            savedRange
                        );

                }


                input.value =
                    '';


                console.log(
                    '✅ Inline image uploaded.'
                );


            } catch (error) {

                console.error(
                    '❌ Inline image upload error:',
                    error
                );


                showToast(
                    'Image upload error: ' +
                    error.message,
                    'error'
                );


            } finally {

                showLoading(false);

            }

        };


    input.click();

}


// ============================================================
// INLINE IMAGE SIZE PICKER
// Click any image already inside an article/project editor
// (#contentEditor or #achieve_content — both use the shared
// .editor-content class) to choose its display size. Reuses the
// same <img> elements uploadInlineImage() creates above; the
// size is just one of a fixed set of CSS classes on the <img>
// tag, exactly as it will be saved (innerHTML) and rendered on
// the public article page. No second image system, no new
// server-side field.
// ============================================================

// Keep in sync with the size classes defined in admin.css
// (.editor-content .article-image-*) and css/post.css
// (.post-content .article-image-*).
const INLINE_IMAGE_SIZE_CLASSES = [
    'article-image-small',
    'article-image-large',
];

let activeInlineImageSizePicker = null;

function closeInlineImageSizePicker() {

    if (activeInlineImageSizePicker) {

        activeInlineImageSizePicker.remove();
        activeInlineImageSizePicker = null;

    }

}

function applyInlineImageSize(imgNode, size) {

    // Always keep the base marker class so this image keeps
    // rendering correctly (and is recognized on future clicks)
    // regardless of which size is chosen.
    imgNode.classList.add('article-image');

    INLINE_IMAGE_SIZE_CLASSES.forEach(
        (cls) => imgNode.classList.remove(cls)
    );

    if (size === 'small') {
        imgNode.classList.add('article-image-small');
    } else if (size === 'large') {
        imgNode.classList.add('article-image-large');
    }
    // size === 'normal' -> no extra class; this is the existing
    // default look, unchanged from before this feature existed.

}

function openInlineImageSizePicker(imgNode) {

    closeInlineImageSizePicker();

    const picker =
        document.createElement('div');

    picker.className =
        'inline-image-size-picker';

    const sizes = [
        { key: 'small', label: 'Small' },
        { key: 'normal', label: 'Medium' },
        { key: 'large', label: 'Large' },
    ];

    sizes.forEach(({ key, label }) => {

        const btn =
            document.createElement('button');

        btn.type = 'button';
        btn.className = 'inline-image-size-btn';
        btn.textContent = label;

        const isActive =
            (key === 'small' &&
                imgNode.classList.contains('article-image-small')) ||
            (key === 'large' &&
                imgNode.classList.contains('article-image-large')) ||
            (key === 'normal' &&
                !imgNode.classList.contains('article-image-small') &&
                !imgNode.classList.contains('article-image-large'));

        if (isActive) {
            btn.classList.add('active');
        }

        btn.onclick = (event) => {
            event.preventDefault();
            event.stopPropagation();
            applyInlineImageSize(imgNode, key);
            closeInlineImageSizePicker();
        };

        picker.appendChild(btn);

    });

    document.body.appendChild(picker);

    // Position above the image, centered, flipping below if it
    // would otherwise go off the top of the viewport.
    const rect = imgNode.getBoundingClientRect();
    const pickerRect = picker.getBoundingClientRect();

    let top =
        rect.top + window.scrollY - pickerRect.height - 8;

    if (top < window.scrollY + 4) {
        top = rect.bottom + window.scrollY + 8;
    }

    let left =
        rect.left + window.scrollX +
        (rect.width - pickerRect.width) / 2;

    const maxLeft =
        window.scrollX +
        document.documentElement.clientWidth -
        pickerRect.width - 8;

    left = Math.max(8, Math.min(left, maxLeft));

    picker.style.top = top + 'px';
    picker.style.left = left + 'px';

    activeInlineImageSizePicker = picker;

}

document.addEventListener('click', (event) => {

    const img =
        event.target.closest(
            '.editor-content img'
        );

    if (img) {

        event.preventDefault();
        openInlineImageSizePicker(img);
        return;

    }

    if (
        activeInlineImageSizePicker &&
        !activeInlineImageSizePicker.contains(event.target)
    ) {

        closeInlineImageSizePicker();

    }

});

// Scroll position changes invalidate the picker's fixed
// coordinates — just close it rather than trying to track it.
window.addEventListener(
    'scroll',
    closeInlineImageSizePicker,
    true
);


// ============================================================
// EDITOR PASTE SANITIZATION
// Delegated 'paste' listener for both rich-text editors
// (#contentEditor on articles.php, #achieve_content on
// projects.php — both carry the shared .editor-content
// class, same as the click delegation above). uploadInlineImage()
// never sets inline sizing on an <img> it inserts — sizing is
// class-based (see applyInlineImageSize() above and css/post.css
// .post-content img on the public side). But a browser's default
// paste behavior inserts external HTML (copied from Word, Google
// Docs, another website, a screenshot tool, etc.) verbatim,
// which can carry an inline style="width:...;height:...px" or
// width/height attributes on <img>. That would (a) look wrong in
// the editor itself and (b) get saved into data.content exactly
// as pasted and later render oversized/distorted on the public
// article page, since an inline style always wins over any CSS
// rule regardless of specificity.
//
// This keeps the pasted HTML's structure intact (so pasted text
// formatting, links, lists, etc. still work exactly as before)
// and only strips the dimension-related attributes from any
// <img> within it — post.js applies the same normalization at
// render time as a second, independent safety net for content
// already saved before this existed. Plain-text/no-HTML pastes
// are untouched and fall through to the browser's default
// behavior.
// ============================================================
document.addEventListener('paste', (event) => {

    const editor =
        event.target.closest('.editor-content');

    if (!editor) {
        return;
    }

    const pastedHTML =
        event.clipboardData?.getData('text/html');

    if (!pastedHTML) {
        // Plain text (or an image file handled by the existing
        // upload flow) — nothing to sanitize.
        return;
    }

    const hasImg =
        /<img[\s>]/i.test(pastedHTML);

    if (!hasImg) {
        // No <img> in the pasted fragment — nothing this
        // sanitizer needs to touch, let the default paste proceed.
        return;
    }

    event.preventDefault();

    const scratch =
        document.createElement('div');

    scratch.innerHTML = sanitizeArticleHtml(pastedHTML);

    scratch.querySelectorAll('img').forEach((img) => {
        img.removeAttribute('style');
        img.removeAttribute('width');
        img.removeAttribute('height');
    });

    document.execCommand(
        'insertHTML',
        false,
        scratch.innerHTML
    );

});


// ============================================================
// MOBILE SIDEBAR TOGGLE
// The sidebar slides in from the side on small screens (see
// admin.css @media (max-width: 900px)). Runs on every admin
// page since the toggle button/overlay are in partials/layout_top.php.
// ============================================================

document.addEventListener(
    'DOMContentLoaded',
    () => {

        const toggleBtn =
            document.getElementById(
                'sidebarToggle'
            );

        const sidebar =
            document.getElementById(
                'adminSidebar'
            );

        const overlay =
            document.getElementById(
                'sidebarOverlay'
            );

        if (!toggleBtn || !sidebar || !overlay) {
            return;
        }


        const openSidebar = () => {

            sidebar.classList.add('open');
            overlay.classList.add('visible');
            toggleBtn.setAttribute('aria-expanded', 'true');

        };


        const closeSidebar = () => {

            sidebar.classList.remove('open');
            overlay.classList.remove('visible');
            toggleBtn.setAttribute('aria-expanded', 'false');

        };


        toggleBtn.addEventListener(
            'click',
            () => {

                sidebar.classList.contains('open')
                    ? closeSidebar()
                    : openSidebar();

            }
        );


        overlay.addEventListener(
            'click',
            closeSidebar
        );


        document.addEventListener(
            'keydown',
            event => {

                if (event.key === 'Escape') {
                    closeSidebar();
                }

            }
        );


        // Tapping any sidebar link closes the sidebar right away
        // instead of leaving it open behind the new page.
        sidebar.querySelectorAll('a, button').forEach(
            el => el.addEventListener('click', closeSidebar)
        );

    }
);


// ============================================================
// TOAST NOTIFICATIONS
// Replaces native alert() with a small, dismissable, in-page
// notification — used across every admin page for both success
// and error feedback. Purely presentational; does not change
// what is shown, only how.
// ============================================================

function showToast(
    message,
    type = 'error'
) {

    const container =
        document.getElementById(
            'toastContainer'
        );

    if (!container) {

        // Fallback, should never happen — every admin page
        // includes the toast container via partials/layout_top.php
        console.warn(
            'toastContainer not found, falling back to alert()'
        );

        alert(message);

        return;

    }


    const toast =
        document.createElement(
            'div'
        );

    toast.className =
        'toast toast-' + (
            type === 'success'
                ? 'success'
                : 'error'
        );

    toast.setAttribute(
        'role',
        'status'
    );

    toast.setAttribute(
        'aria-live',
        'polite'
    );


    const icon =
        type === 'success'
            ? 'fas fa-circle-check'
            : 'fas fa-circle-exclamation';


    toast.innerHTML = `
        <i class="${icon}" aria-hidden="true"></i>
        <span class="toast-message"></span>
        <button
            type="button"
            class="toast-close"
            aria-label="إغلاق"
        >
            <i class="fas fa-xmark" aria-hidden="true"></i>
        </button>
    `;

    // Text is inserted via textContent, never innerHTML, so a
    // server error message can never be interpreted as markup.
    toast.querySelector(
        '.toast-message'
    ).textContent =
        message;


    const dismiss =
        () => {

            toast.classList.add(
                'toast-leaving'
            );

            setTimeout(
                () => toast.remove(),
                200
            );

        };


    toast.querySelector(
        '.toast-close'
    ).addEventListener(
        'click',
        dismiss
    );


    container.appendChild(
        toast
    );


    setTimeout(
        dismiss,
        type === 'success' ? 3500 : 6000
    );

}

// ============================================================
// TABLE SKELETON / EMPTY STATE HELPERS
// Shared by articles.js, projects.js, users.js, reviews.js
// so every table loads and empties out the same way.
// ============================================================

function renderSkeletonRows(
    tbody,
    columnCount,
    rowCount = 4
) {

    if (!tbody) {
        return;
    }

    let html = '';

    for (let r = 0; r < rowCount; r++) {

        html += '<tr class="skeleton-row">';

        for (let c = 0; c < columnCount; c++) {

            html += `
                <td>
                    <div class="skeleton-bar" style="width:${60 + (c % 3) * 15}%"></div>
                </td>
            `;

        }

        html += '</tr>';

    }

    tbody.innerHTML = html;

}


function renderEmptyState(
    tbody,
    columnCount,
    {
        icon = 'fas fa-inbox',
        message = 'No records found.',
        ctaLabel = null,
        ctaHref = null,
    } = {}
) {

    if (!tbody) {
        return;
    }

    const cta =
        ctaLabel && ctaHref
            ? `<a href="${ctaHref}" class="btn btn-primary empty-state-action">${escapeHTML(ctaLabel)}</a>`
            : '';

    tbody.innerHTML = `
        <tr>
            <td colspan="${columnCount}">
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="${icon}"></i></div>
                    <p class="empty-state-message">${escapeHTML(message)}</p>
                    ${cta}
                </div>
            </td>
        </tr>
    `;

}


// ============================================================
// HTML ESCAPE
// ============================================================

function escapeHTML(
    value
) {

    return String(value)

        .replace(
            /&/g,
            '&amp;'
        )

        .replace(
            /</g,
            '&lt;'
        )

        .replace(
            />/g,
            '&gt;'
        )

        .replace(
            /"/g,
            '&quot;'
        )

        .replace(
            /'/g,
            '&#039;'
        );

}


// ============================================================
// UNSAVED CHANGES GUARD  [REQ-010]
// Centralized dirty-state engine for protecting unsaved edits
// across administrative editors (articles, projects).
// Tracks changes dynamically against a baseline snapshot.
// ============================================================

const UnsavedChangesGuard = (function () {

    let _baselineSnapshot = null;
    let _getSnapshotFn = null;
    let _isEqualFn = null;
    let _bypassed = false;
    let _initialized = false;

    // [LEGACY] The AI Review feature (removed) used to wrap suggestion matches
    // in <mark class="ai-review-highlight"> inside the editor while its results
    // were displayed. Those marks were UI-only and never meant to be saved.
    // Kept as defensive cleanup in case any already-open editor session or
    // previously-saved content still contains a leftover mark — harmless no-op
    // once no code path can insert one again.
    function stripAiReviewHighlights(html) {
        return String(html).replace(
            /<mark[^>]*class="[^"]*\bai-review-highlight\b[^"]*"[^>]*>([\s\S]*?)<\/mark>/gi,
            '$1'
        );
    }

    function normalizeHtml(html) {
        if (!html) return '';
        const text = String(stripAiReviewHighlights(html))
            .replace(/&nbsp;/g, ' ')
            .replace(/\u00A0/g, ' ')
            .replace(/\u200B/g, '')
            .replace(/\uFEFF/g, '');

        const hasMedia = /<(img|picture|svg|iframe|video|audio|table|object|embed)\b/i.test(text);
        if (!hasMedia) {
            const stripped = text
                .replace(/<br\s*\/?>/gi, '')
                .replace(/<\/?(p|div|span|h[1-6]|li|ul|ol|blockquote)[^>]*>/gi, '')
                .trim();
            if (stripped === '') {
                return '';
            }
        }
        return text.trim();
    }

    function defaultIsEqual(a, b) {
        if (!a || !b) return a === b;
        const keysA = Object.keys(a);
        const keysB = Object.keys(b);
        if (keysA.length !== keysB.length) return false;
        for (let i = 0; i < keysA.length; i++) {
            const key = keysA[i];
            if (a[key] !== b[key]) {
                return false;
            }
        }
        return true;
    }

    function isDirty() {
        if (_bypassed || !_baselineSnapshot || typeof _getSnapshotFn !== 'function') {
            return false;
        }
        const currentSnapshot = _getSnapshotFn();
        const compare = _isEqualFn || defaultIsEqual;
        return !compare(_baselineSnapshot, currentSnapshot);
    }

    function setBaseline(snapshot) {
        _baselineSnapshot = snapshot ? Object.assign({}, snapshot) : null;
        _bypassed = false;
    }

    function getBaseline() {
        return _baselineSnapshot ? Object.assign({}, _baselineSnapshot) : null;
    }

    function markClean() {
        if (typeof _getSnapshotFn === 'function') {
            setBaseline(_getSnapshotFn());
        }
    }

    function bypass() {
        _bypassed = true;
    }

    function isBypassed() {
        return _bypassed;
    }

    function resetBypass() {
        _bypassed = false;
    }

    function shouldInterceptAnchorClick(anchor, event) {
        if (!anchor || typeof anchor.getAttribute !== 'function') {
            return false;
        }

        // Ignore clicks with modifier keys (opening in new tab/window)
        if (event && (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey)) {
            return false;
        }
        // Ignore non-left click (e.g. middle click or right click)
        if (event && typeof event.button === 'number' && event.button !== 0) {
            return false;
        }

        // Ignore target="_blank"
        const target = anchor.getAttribute('target');
        if (target && target.toLowerCase() === '_blank') {
            return false;
        }

        // Ignore download attribute
        if (anchor.hasAttribute('download')) {
            return false;
        }

        const rawHref = (anchor.getAttribute('href') || '').trim();
        if (!rawHref || rawHref === '#' || rawHref.startsWith('#')) {
            return false;
        }
        if (rawHref.toLowerCase().startsWith('javascript:')) {
            return false;
        }

        try {
            if (typeof window === 'undefined' || !window.location) {
                return false;
            }
            const destUrl = new URL(anchor.href, window.location.href);

            // Same-page navigation check: pathname and search are identical
            if (destUrl.origin === window.location.origin &&
                destUrl.pathname === window.location.pathname &&
                destUrl.search === window.location.search) {
                return false;
            }

            return true;
        } catch (e) {
            return false;
        }
    }

    function init(options) {
        options = options || {};
        if (typeof options.getSnapshot === 'function') {
            _getSnapshotFn = options.getSnapshot;
        }
        if (typeof options.isEqual === 'function') {
            _isEqualFn = options.isEqual;
        }

        if (options.initialBaseline) {
            setBaseline(options.initialBaseline);
        } else if (_getSnapshotFn) {
            setBaseline(_getSnapshotFn());
        }

        if (!_initialized && typeof window !== 'undefined' && typeof document !== 'undefined') {
            _initialized = true;

            // 1. In-app navigation interceptor (delegated on document)
            document.addEventListener('click', function (event) {
                const anchor = event.target ? event.target.closest('a') : null;
                if (!anchor) return;

                if (!shouldInterceptAnchorClick(anchor, event)) {
                    return;
                }

                if (isDirty()) {
                    const confirmed = confirm('You have unsaved changes. Are you sure you want to leave this page?');
                    if (!confirmed) {
                        event.preventDefault();
                        event.stopPropagation();
                        return;
                    }
                    bypass();
                }
            }, true);

            // 2. Window beforeunload event listener
            window.addEventListener('beforeunload', function (event) {
                if (_bypassed) {
                    return;
                }
                if (isDirty()) {
                    event.preventDefault();
                    event.returnValue = '';
                    return '';
                }
            });
        }
    }

    return {
        init: init,
        isDirty: isDirty,
        setBaseline: setBaseline,
        getBaseline: getBaseline,
        markClean: markClean,
        bypass: bypass,
        isBypassed: isBypassed,
        resetBypass: resetBypass,
        normalizeHtml: normalizeHtml,
        stripAiReviewHighlights: stripAiReviewHighlights,
        defaultIsEqual: defaultIsEqual,
        shouldInterceptAnchorClick: shouldInterceptAnchorClick
    };

})();

if (typeof window !== 'undefined') {
    window.UnsavedChangesGuard = UnsavedChangesGuard;
}
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { UnsavedChangesGuard };
}

// ============================================================
// SEO ANALYZER UI — shared by articles.js and projects.js
// (single implementation, no duplicated analyzer logic per page).
//
// The rest of the admin panel has no i18n system and stays
// Arabic-only. This feature is the one exception: it needs real
// Arabic/English support, so it carries its OWN small string
// dictionary (just the ~13 static UI labels below) rather than
// building a site-wide translation framework. Rule messages and
// category labels are already bilingual as returned by the API
// (api/seo/analyzer.php) — this dictionary only covers button/
// heading/status text that never leaves the client.
// ============================================================
const SeoAnalyzerUI = (function () {

    const STRINGS = {
        ar: {
            title: 'تحليل SEO',
            description: 'تدقيق شامل لعناصر SEO الأساسية للمنشور.',
            scoreLabel: 'درجة صحة SEO',
            keywordLabel: 'الكلمة المفتاحية',
            keywordPlaceholder: 'اختياري — مثال: هندسة البرمجيات',
            analyzeBtn: 'تحليل SEO',
            analyzing: 'جاري تحليل SEO...',
            statusPass: 'ناجح',
            statusWarning: 'تنبيه',
            statusError: 'خطأ',
            statusNotice: 'استشاري',
            statusNotEvaluated: 'غير مُقيّم',
            notPublicBanner: 'هذا المنشور غير منشور حالياً، لذا فئات التواصل الاجتماعي والبيانات المنظمة وSEO التقني تعكس وضعه الحالي (غير مفهرس) وليس تقييماً نهائياً.',
            langToggle: 'English',
            scoreStatusExcellent: 'ممتاز',
            scoreStatusGood: 'جيد',
            scoreStatusNeedsImprovement: 'يحتاج تحسين',
            scoreStatusPoor: 'ضعيف',
            summaryCritical: 'مشاكل حرجة',
            summaryWarnings: 'تنبيهات',
            summaryNotices: 'استشارات',
            summaryPassed: 'فحوصات ناجحة',
            summaryNotEvaluated: 'غير مُقيّم',
            quickWinsTitle: 'تحسينات سريعة',
            quickWinsEmpty: 'لا توجد تحسينات سريعة إضافية — كل الفحوصات القابلة للتنفيذ إما ناجحة أو منخفضة الأولوية.',
            categoryHealthTitle: 'صحة الأقسام',
            detailedAuditTitle: 'التدقيق التفصيلي',
            currentValueLabel: 'القيمة الحالية',
            problemLabel: 'المشكلة',
            whyItMattersLabel: 'لماذا يهم؟',
            recommendationLabel: 'التوصية',
            reasonLabel: 'السبب',
            priorityLabel: 'الأولوية',
            impactLabel: 'الأثر',
            effortLabel: 'الجهد',
            priorityHigh: 'عالية',
            priorityMedium: 'متوسطة',
            priorityLow: 'منخفضة',
            levelHigh: 'مرتفع',
            levelMedium: 'متوسط',
            levelLow: 'منخفض',
            showPassed: 'إظهار الفحوصات الناجحة',
            hidePassed: 'إخفاء الفحوصات الناجحة',
            passedCount: 'فحص ناجح',
            issuesCount: 'مشكلة',
            expand: 'توسيع',
            collapse: 'طي',
            advisoryTag: 'استشاري',
        },
        en: {
            title: 'SEO Analysis',
            description: "Comprehensive audit of the page's core SEO signals.",
            scoreLabel: 'SEO Health Score',
            keywordLabel: 'Focus Keyword',
            keywordPlaceholder: 'Optional — e.g. software engineering',
            analyzeBtn: 'Analyze SEO',
            analyzing: 'Analyzing SEO...',
            statusPass: 'Pass',
            statusWarning: 'Warning',
            statusError: 'Error',
            statusNotice: 'Notice',
            statusNotEvaluated: 'Not Evaluated',
            notPublicBanner: 'This post is not currently published, so the Social, Structured Data and Technical SEO categories reflect its current (not-indexed) state rather than a final grade.',
            langToggle: 'العربية',
            scoreStatusExcellent: 'Excellent',
            scoreStatusGood: 'Good',
            scoreStatusNeedsImprovement: 'Needs Improvement',
            scoreStatusPoor: 'Poor',
            summaryCritical: 'Critical Issues',
            summaryWarnings: 'Warnings',
            summaryNotices: 'Notices',
            summaryPassed: 'Passed Checks',
            summaryNotEvaluated: 'Not Evaluated',
            quickWinsTitle: 'Quick Wins',
            quickWinsEmpty: 'No additional quick wins — every actionable check is either passing or low priority.',
            categoryHealthTitle: 'Category Health',
            detailedAuditTitle: 'Detailed Audit',
            currentValueLabel: 'Current value',
            problemLabel: 'Problem',
            whyItMattersLabel: 'Why it matters',
            recommendationLabel: 'Recommendation',
            reasonLabel: 'Reason',
            priorityLabel: 'Priority',
            impactLabel: 'Impact',
            effortLabel: 'Effort',
            priorityHigh: 'High',
            priorityMedium: 'Medium',
            priorityLow: 'Low',
            levelHigh: 'High',
            levelMedium: 'Medium',
            levelLow: 'Low',
            showPassed: 'Show passed checks',
            hidePassed: 'Hide passed checks',
            passedCount: 'passed',
            issuesCount: 'issue',
            expand: 'Expand',
            collapse: 'Collapse',
            advisoryTag: 'Advisory',
        },
    };

    function getLang() {
        try {
            const stored = window.localStorage ? window.localStorage.getItem('seoAnalyzerLang') : null;
            return stored === 'en' ? 'en' : 'ar';
        } catch (e) {
            return 'ar';
        }
    }

    function setLang(lang) {
        try {
            if (window.localStorage) {
                window.localStorage.setItem('seoAnalyzerLang', lang);
            }
        } catch (e) { /* localStorage unavailable — non-fatal, just won't persist */ }
    }

    // status -> badge color class. 'notice' and 'not_evaluated' both use the
    // neutral style: neither is a "failure" (Section 8/20 of the V2 spec) —
    // they're visually distinct from warning/error on purpose.
    function statusBadgeClass(status) {
        if (status === 'pass') return 'status-success';
        if (status === 'warning') return 'status-warning';
        if (status === 'error') return 'status-danger';
        return 'status-neutral'; // notice | not_evaluated
    }

    function scoreBadgeClass(ratio) {
        if (ratio >= 90) return 'status-success';
        if (ratio >= 50) return 'status-warning';
        return 'status-danger';
    }

    // Status is never communicated by color alone (Section 50) — every
    // badge always pairs an icon with a text label.
    function statusIcon(status) {
        if (status === 'pass') return '✓';
        if (status === 'warning') return '⚠';
        if (status === 'error') return '✕';
        if (status === 'not_evaluated') return '○';
        return 'ℹ'; // notice
    }

    function statusLabel(strings, status) {
        if (status === 'pass') return strings.statusPass;
        if (status === 'warning') return strings.statusWarning;
        if (status === 'error') return strings.statusError;
        if (status === 'not_evaluated') return strings.statusNotEvaluated;
        return strings.statusNotice;
    }

    function scoreStatusMeta(strings, scoreStatus) {
        if (scoreStatus === 'excellent') return { label: strings.scoreStatusExcellent, cls: 'status-success' };
        if (scoreStatus === 'good') return { label: strings.scoreStatusGood, cls: 'status-success' };
        if (scoreStatus === 'needs_improvement') return { label: strings.scoreStatusNeedsImprovement, cls: 'status-warning' };
        return { label: strings.scoreStatusPoor, cls: 'status-danger' };
    }

    function levelLabel(strings, level) {
        if (level === 'high') return strings.levelHigh;
        if (level === 'medium') return strings.levelMedium;
        if (level === 'low') return strings.levelLow;
        return '';
    }

    function priorityLabel(strings, priority) {
        if (priority === 'high') return strings.priorityHigh;
        if (priority === 'medium') return strings.priorityMedium;
        if (priority === 'low') return strings.priorityLow;
        return '';
    }

    function priorityBadgeClass(priority) {
        if (priority === 'high') return 'status-danger';
        if (priority === 'medium') return 'status-warning';
        return 'status-neutral';
    }

    // Within a category: actionable checks (error/warning/notice) surface
    // first — ranked critical-severity first — then not_evaluated, and
    // passed checks last (they're rendered separately, behind a toggle,
    // but this ordering is also used if that ever changes).
    const STATUS_SORT_WEIGHT = { error: 0, warning: 1, notice: 2, not_evaluated: 3, pass: 4 };
    function sortRulesForDisplay(rules) {
        return rules.slice().sort(function (a, b) {
            const wa = STATUS_SORT_WEIGHT[a.status] ?? 2;
            const wb = STATUS_SORT_WEIGHT[b.status] ?? 2;
            if (wa !== wb) return wa - wb;
            if (a.severity === 'critical' && b.severity !== 'critical') return -1;
            if (b.severity === 'critical' && a.severity !== 'critical') return 1;
            return 0;
        });
    }

    function fieldText(field, lang) {
        if (!field) return '';
        return field[lang] || field.ar || field.en || '';
    }

    // A structured rule card: status/priority/impact/effort badges up top,
    // then current value / problem / why it matters / recommendation —
    // exactly Section 37's card shape. Passed checks get a much lighter
    // treatment (just the confirmation + current value, no problem/why/
    // recommendation, since there is nothing to fix).
    function renderRuleCard(strings, lang, rule) {
        const message = fieldText(rule.message, lang);
        const problem = fieldText(rule.problem, lang);
        const why = fieldText(rule.whyItMatters, lang);
        const rec = fieldText(rule.recommendation, lang);
        const isAdvisory = rule.max_points === 0;

        let html = '<li class="seo-rule-card seo-rule-card-' + escapeHTML(rule.status) + (isAdvisory ? ' seo-rule-card-advisory' : '') + '">';
        html += '<div class="seo-rule-card-head">';
        html += '<span class="status-badge ' + statusBadgeClass(rule.status) + '"><span class="seo-rule-icon" aria-hidden="true">' + statusIcon(rule.status) + '</span>' + escapeHTML(statusLabel(strings, rule.status)) + '</span>';
        if (rule.priority) {
            html += '<span class="status-badge ' + priorityBadgeClass(rule.priority) + ' seo-meta-badge">' + escapeHTML(strings.priorityLabel) + ': ' + escapeHTML(priorityLabel(strings, rule.priority)) + '</span>';
        }
        if (isAdvisory && rule.status !== 'not_evaluated') {
            html += '<span class="status-badge status-neutral seo-advisory-tag">' + escapeHTML(strings.advisoryTag) + '</span>';
        }
        html += '</div>';

        if (rule.priority) {
            html += '<div class="seo-rule-meta-row">';
            html += '<span class="seo-rule-meta-item">' + escapeHTML(strings.impactLabel) + ': ' + escapeHTML(levelLabel(strings, rule.impact)) + '</span>';
            html += '<span class="seo-rule-meta-item">' + escapeHTML(strings.effortLabel) + ': ' + escapeHTML(levelLabel(strings, rule.effort)) + '</span>';
            html += '</div>';
        }

        if (rule.currentValue !== null && rule.currentValue !== undefined && rule.currentValue !== '') {
            html += '<div class="seo-rule-field"><span class="seo-rule-field-label">' + escapeHTML(strings.currentValueLabel) + ':</span> <span class="seo-rule-field-value">' + escapeHTML(String(rule.currentValue)) + '</span></div>';
        }

        if (rule.status === 'not_evaluated') {
            html += '<div class="seo-rule-field"><span class="seo-rule-field-label">' + escapeHTML(strings.reasonLabel) + ':</span> <span class="seo-rule-field-value">' + escapeHTML(message) + '</span></div>';
        } else if (problem) {
            html += '<div class="seo-rule-field"><span class="seo-rule-field-label">' + escapeHTML(strings.problemLabel) + ':</span> <span class="seo-rule-field-value">' + escapeHTML(problem) + '</span></div>';
            if (why) {
                html += '<div class="seo-rule-field"><span class="seo-rule-field-label">' + escapeHTML(strings.whyItMattersLabel) + ':</span> <span class="seo-rule-field-value">' + escapeHTML(why) + '</span></div>';
            }
            if (rec) {
                html += '<div class="seo-rule-field seo-rule-field-recommendation"><span class="seo-rule-field-label">' + escapeHTML(strings.recommendationLabel) + ':</span> <span class="seo-rule-field-value">' + escapeHTML(rec) + '</span></div>';
            }
        } else if (message) {
            html += '<div class="seo-rule-field"><span class="seo-rule-field-value">' + escapeHTML(message) + '</span></div>';
        }

        html += '</li>';
        return html;
    }

    function renderQuickWins(strings, lang, quickWins, rulesById) {
        let html = '<div class="seo-quickwins-block">';
        html += '<h4 class="seo-block-title">' + escapeHTML(strings.quickWinsTitle) + '</h4>';
        if (!quickWins || quickWins.length === 0) {
            html += '<p class="seo-quickwins-empty">' + escapeHTML(strings.quickWinsEmpty) + '</p>';
        } else {
            html += '<ol class="seo-quickwins-list">';
            quickWins.forEach(function (win) {
                const rule = rulesById[win.ruleId];
                if (!rule) return;
                const problem = fieldText(rule.problem, lang) || fieldText(rule.message, lang);
                html += '<li class="seo-quickwin-item">';
                html += '<div class="seo-quickwin-text">' + escapeHTML(problem);
                if (rule.currentValue !== null && rule.currentValue !== undefined && rule.currentValue !== '') {
                    html += '<span class="seo-quickwin-value"> — ' + escapeHTML(String(rule.currentValue)) + '</span>';
                }
                html += '</div>';
                html += '<div class="seo-quickwin-meta">';
                html += '<span class="seo-rule-meta-item">' + escapeHTML(strings.impactLabel) + ': ' + escapeHTML(levelLabel(strings, rule.impact)) + '</span>';
                html += '<span class="seo-rule-meta-item">' + escapeHTML(strings.effortLabel) + ': ' + escapeHTML(levelLabel(strings, rule.effort)) + '</span>';
                html += '</div>';
                html += '</li>';
            });
            html += '</ol>';
        }
        html += '</div>';
        return html;
    }

    function renderCategoryHealth(strings, lang, categories) {
        let html = '<div class="seo-category-health-block">';
        html += '<h4 class="seo-block-title">' + escapeHTML(strings.categoryHealthTitle) + '</h4>';
        html += '<ul class="seo-category-health-list">';
        categories.forEach(function (cat) {
            const label = (cat.label && cat.label[lang]) ? cat.label[lang] : cat.key;
            const ratio = cat.max > 0 ? (cat.earned / cat.max) * 100 : 100;
            html += '<li class="seo-category-health-row">';
            html += '<span class="seo-category-health-label">' + escapeHTML(label) + '</span>';
            html += '<span class="seo-category-health-bar"><span class="seo-category-health-bar-fill ' + scoreBadgeClass(ratio) + '" style="width:' + Math.max(0, Math.min(100, ratio)) + '%"></span></span>';
            html += '<span class="seo-category-health-score">' + escapeHTML(String(cat.earned)) + ' / ' + escapeHTML(String(cat.max)) + '</span>';
            html += '</li>';
        });
        html += '</ul>';
        html += '</div>';
        return html;
    }

    function renderResults(container, data, lang) {
        const strings = STRINGS[lang];
        const rulesByCategory = {};
        const rulesById = {};
        (data.rules || []).forEach(function (rule) {
            if (!rulesByCategory[rule.category]) rulesByCategory[rule.category] = [];
            rulesByCategory[rule.category].push(rule);
            rulesById[rule.id] = rule;
        });

        const scoreRatio = data.max_score > 0 ? (data.score / data.max_score) * 100 : 0;
        const summary = data.summary || { critical: 0, warnings: 0, notices: 0, passed: 0, not_evaluated: 0 };
        const statusMeta = scoreStatusMeta(strings, data.score_status);

        let html = '';

        if (data.meta && data.meta.is_public === false) {
            html += '<div class="seo-notice-banner">' + escapeHTML(strings.notPublicBanner) + '</div>';
        }

        // ---- Overview: score + status + 5-bucket summary ----
        html += '<div class="seo-overview">';
        html += '<div class="seo-score-row">';
        html += '<span class="seo-score-value">' + escapeHTML(String(data.score)) + ' / ' + escapeHTML(String(data.max_score)) + '</span>';
        html += '<span class="status-badge ' + scoreBadgeClass(scoreRatio) + '">' + escapeHTML(strings.scoreLabel) + '</span>';
        html += '<span class="status-badge ' + statusMeta.cls + '">' + escapeHTML(statusMeta.label) + '</span>';
        html += '</div>';

        html += '<div class="seo-summary-row">';
        html += '<div class="seo-summary-stat seo-summary-stat-critical"><span class="seo-summary-count">' + escapeHTML(String(summary.critical)) + '</span><span class="seo-summary-label">' + escapeHTML(strings.summaryCritical) + '</span></div>';
        html += '<div class="seo-summary-stat seo-summary-stat-warning"><span class="seo-summary-count">' + escapeHTML(String(summary.warnings)) + '</span><span class="seo-summary-label">' + escapeHTML(strings.summaryWarnings) + '</span></div>';
        html += '<div class="seo-summary-stat seo-summary-stat-notice"><span class="seo-summary-count">' + escapeHTML(String(summary.notices)) + '</span><span class="seo-summary-label">' + escapeHTML(strings.summaryNotices) + '</span></div>';
        html += '<div class="seo-summary-stat seo-summary-stat-passed"><span class="seo-summary-count">' + escapeHTML(String(summary.passed)) + '</span><span class="seo-summary-label">' + escapeHTML(strings.summaryPassed) + '</span></div>';
        html += '<div class="seo-summary-stat seo-summary-stat-neutral"><span class="seo-summary-count">' + escapeHTML(String(summary.not_evaluated)) + '</span><span class="seo-summary-label">' + escapeHTML(strings.summaryNotEvaluated) + '</span></div>';
        html += '</div>';
        html += '</div>';

        // ---- Quick Wins: answers "what should I fix first?" up top,
        // before 40+ passed checks can visually dominate the report ----
        html += renderQuickWins(strings, lang, data.quick_wins, rulesById);

        // ---- Category health overview ----
        html += renderCategoryHealth(strings, lang, data.categories || []);

        // ---- Detailed audit: collapsible categories, collapsed by default ----
        html += '<div class="seo-detailed-audit-block">';
        html += '<h4 class="seo-block-title">' + escapeHTML(strings.detailedAuditTitle) + '</h4>';

        (data.categories || []).forEach(function (cat) {
            const catRatio = cat.max > 0 ? (cat.earned / cat.max) * 100 : 100;
            const label = (cat.label && cat.label[lang]) ? cat.label[lang] : cat.key;
            const allRules = sortRulesForDisplay(rulesByCategory[cat.key] || []);
            const actionableRules = allRules.filter(function (r) { return r.status !== 'pass'; });
            const passedRules = allRules.filter(function (r) { return r.status === 'pass'; });

            html += '<div class="seo-category-block">';
            html += '<button type="button" class="seo-category-header" data-category-toggle="' + escapeHTML(cat.key) + '" aria-expanded="false">';
            html += '<span class="seo-category-title">' + escapeHTML(label) + '</span>';
            html += '<span class="seo-category-header-meta">';
            html += '<span class="status-badge ' + scoreBadgeClass(catRatio) + '">' + escapeHTML(String(cat.earned)) + ' / ' + escapeHTML(String(cat.max)) + '</span>';
            if (actionableRules.length > 0) {
                html += '<span class="seo-category-issue-count">' + escapeHTML(String(actionableRules.length)) + ' ' + escapeHTML(strings.issuesCount) + '</span>';
            }
            html += '<span class="seo-category-toggle-icon" aria-hidden="true">▾</span>';
            html += '</span>';
            html += '</button>';

            html += '<div class="seo-category-body" data-category-body="' + escapeHTML(cat.key) + '" hidden>';
            html += '<ul class="seo-rule-list">';
            actionableRules.forEach(function (rule) {
                html += renderRuleCard(strings, lang, rule);
            });
            html += '</ul>';

            if (passedRules.length > 0) {
                html += '<button type="button" class="seo-passed-toggle" data-passed-toggle="' + escapeHTML(cat.key) + '" aria-expanded="false">';
                html += '<span data-passed-toggle-label="' + escapeHTML(cat.key) + '">' + escapeHTML(String(passedRules.length)) + ' ' + escapeHTML(strings.passedCount) + ' — ' + escapeHTML(strings.showPassed) + '</span>';
                html += '</button>';
                html += '<ul class="seo-rule-list seo-passed-list" data-passed-list="' + escapeHTML(cat.key) + '" hidden>';
                passedRules.forEach(function (rule) {
                    html += renderRuleCard(strings, lang, rule);
                });
                html += '</ul>';
            }

            html += '</div>'; // .seo-category-body
            html += '</div>'; // .seo-category-block
        });

        html += '</div>'; // .seo-detailed-audit-block

        container.innerHTML = html;
    }

    function renderError(container, message) {
        container.innerHTML = '<p class="seo-error-message">' + escapeHTML(message) + '</p>';
    }

    function attach(options) {
        const btn = document.getElementById(options.buttonId);
        const container = document.getElementById(options.resultsContainerId);
        const keywordInput = options.keywordInputId ? document.getElementById(options.keywordInputId) : null;
        const hintEl = options.hintId ? document.getElementById(options.hintId) : null;
        const sectionEl = options.sectionId ? document.getElementById(options.sectionId) : null;
        const titleEl = options.titleId ? document.getElementById(options.titleId) : null;
        const descEl = options.descriptionId ? document.getElementById(options.descriptionId) : null;
        const keywordLabelEl = options.keywordLabelId ? document.getElementById(options.keywordLabelId) : null;
        const langToggleBtn = options.langToggleId ? document.getElementById(options.langToggleId) : null;

        if (!btn || !container) {
            return null;
        }

        let inFlight = false;
        let lastResultData = null;
        let currentLang = getLang();

        const notSavedHint = options.notSavedHint || {
            ar: 'احفظ المقال أولاً لتتمكن من تحليل SEO.',
            en: 'Save the article first to analyze its SEO.',
        };

        function applyStaticStrings() {
            const strings = STRINGS[currentLang];
            if (titleEl) titleEl.textContent = strings.title;
            if (descEl) descEl.textContent = strings.description;
            if (keywordLabelEl) keywordLabelEl.textContent = strings.keywordLabel;
            if (keywordInput) keywordInput.placeholder = strings.keywordPlaceholder;
            if (!inFlight) btn.textContent = strings.analyzeBtn;
            if (hintEl) hintEl.textContent = notSavedHint[currentLang] || notSavedHint.ar;
            if (langToggleBtn) langToggleBtn.textContent = strings.langToggle;
            if (sectionEl) {
                sectionEl.setAttribute('dir', currentLang === 'en' ? 'ltr' : 'rtl');
            }
            if (lastResultData) {
                renderResults(container, lastResultData, currentLang);
            }
        }

        function refreshState() {
            const id = options.getPostId ? options.getPostId() : '';
            const hasId = !!(id && String(id).trim() !== '');
            btn.disabled = !hasId || inFlight;
            if (hintEl) {
                hintEl.style.display = hasId ? 'none' : '';
            }
            if (!hasId) {
                container.innerHTML = '';
                lastResultData = null;
            }
        }

        async function runAnalysis() {
            const id = options.getPostId ? options.getPostId() : '';
            if (!id || inFlight) {
                return;
            }

            inFlight = true;
            btn.disabled = true;
            const strings = STRINGS[currentLang];
            btn.textContent = strings.analyzing;
            container.innerHTML = '';
            lastResultData = null;

            try {
                const keyword = keywordInput ? keywordInput.value.trim() : '';
                let url = '/api/seo/analyze.php?id=' + encodeURIComponent(id);
                if (keyword) {
                    url += '&focus_keyword=' + encodeURIComponent(keyword);
                }

                const response = await fetch(url, {
                    method: 'GET',
                    credentials: 'same-origin',
                });

                let payload = null;
                try {
                    payload = await response.json();
                } catch (parseErr) {
                    renderError(container, currentLang === 'en'
                        ? 'Could not read the server response.'
                        : 'تعذّرت قراءة استجابة الخادم.');
                    return;
                }

                if (!response.ok || !payload || !payload.success) {
                    const fallback = (response.status === 401 || response.status === 403)
                        ? (currentLang === 'en' ? 'Your session is invalid. Please log in again.' : 'الجلسة غير صالحة. أعد تسجيل الدخول والمحاولة مرة أخرى.')
                        : response.status === 404
                            ? (currentLang === 'en' ? 'Post not found.' : 'تعذّر العثور على المنشور.')
                            : (currentLang === 'en' ? 'Something went wrong while analyzing SEO. Please try again.' : 'حدث خطأ أثناء تحليل السيو. حاول مرة أخرى.');
                    renderError(container, (payload && payload.message) ? payload.message : fallback);
                    return;
                }

                lastResultData = payload.data;
                renderResults(container, lastResultData, currentLang);

            } catch (err) {
                console.error('[SeoAnalyzerUI] analysis failed:', err);
                renderError(container, currentLang === 'en'
                    ? 'Could not reach the server. Check your connection and try again.'
                    : 'تعذّر الاتصال بالخادم. تحقق من الاتصال وحاول مرة أخرى.');
            } finally {
                inFlight = false;
                refreshState();
                applyStaticStrings();
            }
        }

        btn.addEventListener('click', function (e) {
            e.preventDefault();
            runAnalysis();
        });

        // Event delegation on the persistent container: category collapse/
        // expand and the passed-checks show/hide toggle both re-render
        // fresh HTML on every analysis, so listeners are bound once here
        // rather than re-attached per element on every renderResults() call.
        container.addEventListener('click', function (e) {
            const categoryToggle = e.target.closest('[data-category-toggle]');
            if (categoryToggle) {
                const key = categoryToggle.getAttribute('data-category-toggle');
                const body = container.querySelector('[data-category-body="' + CSS.escape(key) + '"]');
                if (body) {
                    const isHidden = body.hasAttribute('hidden');
                    if (isHidden) {
                        body.removeAttribute('hidden');
                    } else {
                        body.setAttribute('hidden', '');
                    }
                    categoryToggle.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
                }
                return;
            }

            const passedToggle = e.target.closest('[data-passed-toggle]');
            if (passedToggle) {
                const key = passedToggle.getAttribute('data-passed-toggle');
                const list = container.querySelector('[data-passed-list="' + CSS.escape(key) + '"]');
                const label = container.querySelector('[data-passed-toggle-label="' + CSS.escape(key) + '"]');
                if (list) {
                    const isHidden = list.hasAttribute('hidden');
                    if (isHidden) {
                        list.removeAttribute('hidden');
                    } else {
                        list.setAttribute('hidden', '');
                    }
                    passedToggle.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
                    if (label) {
                        const strings = STRINGS[currentLang];
                        const count = list.querySelectorAll('.seo-rule-card').length;
                        label.textContent = count + ' ' + strings.passedCount + ' — ' + (isHidden ? strings.hidePassed : strings.showPassed);
                    }
                }
            }
        });

        if (langToggleBtn) {
            langToggleBtn.addEventListener('click', function (e) {
                e.preventDefault();
                currentLang = currentLang === 'ar' ? 'en' : 'ar';
                setLang(currentLang);
                applyStaticStrings();
            });
        }

        applyStaticStrings();
        refreshState();

        return { refreshState: refreshState };
    }

    return { attach: attach };

})();

if (typeof window !== 'undefined') {
    window.SeoAnalyzerUI = SeoAnalyzerUI;
}
if (typeof module !== 'undefined' && module.exports) {
    module.exports.SeoAnalyzerUI = SeoAnalyzerUI;
}

// ============================================================
// GLOBAL COMMAND PALETTE (⌘K / Ctrl+K)
// ============================================================
let commandPaletteEntitiesLoaded = false;
let commandPaletteEntities = [];

function openCommandPalette() {
    const modal = document.getElementById('commandPaletteModal');
    const input = document.getElementById('commandPaletteInput');
    if (!modal || !input) return;

    modal.classList.add('open');
    modal.style.display = 'flex';
    input.value = '';
    input.focus();
    filterCommandPalette('');

    if (!commandPaletteEntitiesLoaded) {
        loadCommandPaletteEntities();
    }
}

function closeCommandPalette() {
    const modal = document.getElementById('commandPaletteModal');
    if (!modal) return;
    modal.classList.remove('open');
    modal.style.display = 'none';
}

let paletteActiveIndex = 0;

function updatePaletteActiveItem(newIndex) {
    const container = document.getElementById('commandPaletteResults');
    if (!container) return;
    const items = container.querySelectorAll('.command-palette-item');
    if (!items || items.length === 0) {
        paletteActiveIndex = -1;
        return;
    }
    if (newIndex < 0) newIndex = 0;
    if (newIndex >= items.length) newIndex = items.length - 1;
    paletteActiveIndex = newIndex;
    items.forEach((it, idx) => {
        if (idx === paletteActiveIndex) {
            it.classList.add('active');
            it.scrollIntoView({ block: 'nearest' });
        } else {
            it.classList.remove('active');
        }
    });
}

async function loadCommandPaletteEntities() {
    try {
        const [postsRes, usersRes, reviewsRes, blogCatsRes, achieveCatsRes] = await Promise.all([
            fetch('/api/posts/list.php?status=all&limit=50', { credentials: 'same-origin' }).then(r => r.json()).catch(() => null),
            fetch('/api/users/list.php', { credentials: 'same-origin' }).then(r => r.json()).catch(() => null),
            fetch('/api/reviews/list.php?status=all&limit=30', { credentials: 'same-origin' }).then(r => r.json()).catch(() => null),
            fetch('/api/categories/list.php?type=blog', { credentials: 'same-origin' }).then(r => r.json()).catch(() => null),
            fetch('/api/categories/list.php?type=project', { credentials: 'same-origin' }).then(r => r.json()).catch(() => null),
        ]);

        commandPaletteEntities = [];

        if (postsRes && postsRes.success && Array.isArray(postsRes.data)) {
            postsRes.data.forEach(p => {
                const isProject = p.type === 'project';
                commandPaletteEntities.push({
                    title: p.title || 'Untitled',
                    url: isProject ? `projects.php?search=${encodeURIComponent(p.title || '')}` : `articles.php?search=${encodeURIComponent(p.title || '')}`,
                    icon: isProject ? 'fas fa-code-branch' : 'far fa-file-alt',
                    tag: isProject ? 'Project' : 'Article',
                    status: p.status || 'published',
                });
            });
        }

        if (blogCatsRes && blogCatsRes.success && Array.isArray(blogCatsRes.data)) {
            blogCatsRes.data.forEach(c => {
                commandPaletteEntities.push({
                    title: c.name,
                    url: `articles.php?category=${encodeURIComponent(c.name)}`,
                    icon: 'fas fa-folder',
                    tag: 'Article Category',
                    status: `${c.active_posts_count || 0} items`,
                });
            });
        }

        if (achieveCatsRes && achieveCatsRes.success && Array.isArray(achieveCatsRes.data)) {
            achieveCatsRes.data.forEach(c => {
                commandPaletteEntities.push({
                    title: c.name,
                    url: `projects.php?category=${encodeURIComponent(c.name)}`,
                    icon: 'fas fa-folder-open',
                    tag: 'Project Category',
                    status: `${c.active_posts_count || 0} items`,
                });
            });
        }

        if (usersRes && usersRes.success && Array.isArray(usersRes.data)) {
            usersRes.data.forEach(u => {
                commandPaletteEntities.push({
                    title: u.name || u.email,
                    url: 'users.php',
                    icon: 'far fa-user',
                    tag: 'User',
                    status: u.role || 'user',
                });
            });
        }

        if (reviewsRes && reviewsRes.success && Array.isArray(reviewsRes.data)) {
            reviewsRes.data.forEach(r => {
                const author = r.name || 'Anonymous';
                const snippet = r.message ? (r.message.length > 30 ? r.message.substring(0, 30) + '...' : r.message) : 'Review';
                commandPaletteEntities.push({
                    title: `${author}: "${snippet}"`,
                    url: 'reviews.php',
                    icon: 'far fa-comments',
                    tag: 'Review',
                    status: r.status || 'pending',
                });
            });
        }

        commandPaletteEntitiesLoaded = true;
    } catch (err) {
        console.warn('Could not preload command palette entities:', err);
    }
}

function filterCommandPalette(query) {
    const container = document.getElementById('commandPaletteResults');
    if (!container) return;

    const q = (query || '').toLowerCase().trim();

    // Default static navigation items
    const staticNavItems = [
        { title: 'Dashboard', url: 'index.php', icon: 'fas fa-chart-line', tag: 'Overview' },
        { title: 'Articles & Writing', url: 'articles.php', icon: 'far fa-file-alt', tag: 'Content' },
        { title: 'Engineering Projects', url: 'projects.php', icon: 'fas fa-code-branch', tag: 'Content' },
        { title: 'Categories & Taxonomy', url: 'categories.php', icon: 'fas fa-folder-tree', tag: 'Structure' },
        { title: 'Store Catalog', url: 'store.php', icon: 'fas fa-bag-shopping', tag: 'Content' },
        { title: 'Career Journey', url: 'journey.php', icon: 'fas fa-compass', tag: 'Timeline' },
        { title: 'Media Library', url: 'media.php', icon: 'fas fa-images', tag: 'Media' },
        { title: 'Home Showcase Studio', url: 'showcase.php', icon: 'fas fa-star', tag: 'Display' },
        { title: 'Visitor Reviews', url: 'reviews.php', icon: 'far fa-comments', tag: 'Moderation' },
        { title: 'SEO Workspace', url: 'seo.php', icon: 'fas fa-chart-simple', tag: 'Optimization' },
        { title: 'Analytics Telemetry', url: 'analytics.php', icon: 'fas fa-chart-pie', tag: 'Telemetry' },
        { title: 'Social Links', url: 'social.php', icon: 'fas fa-share-nodes', tag: 'System' },
        { title: 'Users & Roles', url: 'users.php', icon: 'far fa-user', tag: 'System' },
        { title: 'Settings & Diagnostics', url: 'settings.php', icon: 'fas fa-sliders', tag: 'System' },
    ];

    if (!q) {
        let html = '<div class="command-palette-group-title">STUDIO NAVIGATION</div>';
        staticNavItems.forEach(item => {
            html += `
                <a href="${item.url}" class="command-palette-item">
                    <i class="${item.icon}" aria-hidden="true"></i>
                    <span>${escapeHTML(item.title)}</span>
                    <span class="command-palette-tag">${item.tag}</span>
                </a>
            `;
        });
        container.innerHTML = html;
        updatePaletteActiveItem(0);
        return;
    }

    // Filter both static nav and dynamically loaded content
    const matchedNav = staticNavItems.filter(item => item.title.toLowerCase().includes(q) || item.tag.toLowerCase().includes(q));
    const matchedEntities = commandPaletteEntities.filter(item => item.title.toLowerCase().includes(q) || item.tag.toLowerCase().includes(q));

    let html = '';

    if (matchedNav.length > 0) {
        html += '<div class="command-palette-group-title">PAGES & WORKSPACES</div>';
        matchedNav.forEach(item => {
            html += `
                <a href="${item.url}" class="command-palette-item">
                    <i class="${item.icon}" aria-hidden="true"></i>
                    <span>${escapeHTML(item.title)}</span>
                    <span class="command-palette-tag">${item.tag}</span>
                </a>
            `;
        });
    }

    if (matchedEntities.length > 0) {
        html += '<div class="command-palette-group-title">MATCHED CONTENT &amp; RECORDS</div>';
        matchedEntities.slice(0, 15).forEach(item => {
            html += `
                <a href="${item.url}" class="command-palette-item">
                    <i class="${item.icon}" aria-hidden="true"></i>
                    <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${escapeHTML(item.title)}</span>
                    <span class="command-palette-tag">${escapeHTML(item.tag)}</span>
                </a>
            `;
        });
    }

    if (!matchedNav.length && !matchedEntities.length) {
        html = `
            <div style="padding: 32px 16px; text-align: center; color: var(--text-muted); font-size: 13px;">
                <i class="fas fa-magnifying-glass" style="font-size: 20px; display: block; margin-bottom: 8px; opacity: 0.5;"></i>
                No matching studio items or records found.
            </div>
        `;
    }

    container.innerHTML = html;
    updatePaletteActiveItem(0);
}

// ============================================================
// GLOBAL TOP HEALTH / ATTENTION BAR
// ============================================================
async function initGlobalAttentionBar() {
    const bar = document.getElementById('globalAttentionBar');
    const textEl = document.getElementById('attentionBarText');
    const linkEl = document.getElementById('attentionBarLink');
    const iconEl = document.getElementById('attentionBarIcon');
    const closeBtn = document.getElementById('attentionBarClose');
    if (!bar || !textEl || !linkEl) return;

    try {
        const [postsRes, reviewsRes] = await Promise.all([
            fetch('/api/posts/list.php?status=all&limit=50', { credentials: 'same-origin' }).then(r => r.json()).catch(() => null),
            fetch('/api/reviews/list.php?status=all&limit=30', { credentials: 'same-origin' }).then(r => r.json()).catch(() => null),
        ]);

        const posts = (postsRes && postsRes.success && Array.isArray(postsRes.data)) ? postsRes.data : [];
        const reviews = (reviewsRes && reviewsRes.success && Array.isArray(reviewsRes.data)) ? reviewsRes.data : [];

        const drafts = posts.filter(p => p.status === 'draft');
        const hidden = posts.filter(p => p.status === 'hidden');
        const pendingReviews = reviews.filter(r => r.status === 'pending');

        const attentionCount = drafts.length + pendingReviews.length + hidden.length;

        // If no attention items, hide the bar completely
        if (attentionCount === 0) {
            bar.style.display = 'none';
            return;
        }

        // Check if dismissed for this specific count
        const dismissedCount = sessionStorage.getItem('attention_bar_dismissed');
        if (dismissedCount && parseInt(dismissedCount, 10) === attentionCount) {
            bar.style.display = 'none';
            return;
        }

        bar.style.display = 'flex';

        if (pendingReviews.length > 0 && drafts.length > 0) {
            bar.className = 'global-attention-bar attention-warning';
            iconEl.className = 'fas fa-triangle-exclamation attention-bar-icon';
            textEl.textContent = `${attentionCount} items need attention (${pendingReviews.length} review${pendingReviews.length > 1 ? 's' : ''}, ${drafts.length} draft${drafts.length > 1 ? 's' : ''}).`;
            linkEl.href = 'index.php#attention';
            linkEl.textContent = 'View attention queue →';
        } else if (pendingReviews.length > 0) {
            bar.className = 'global-attention-bar attention-warning';
            iconEl.className = 'fas fa-comment-dots attention-bar-icon';
            textEl.textContent = `${pendingReviews.length} review${pendingReviews.length > 1 ? 's' : ''} awaiting moderation approval.`;
            linkEl.href = 'reviews.php';
            linkEl.textContent = 'Moderate reviews →';
        } else if (drafts.length > 0) {
            bar.className = 'global-attention-bar attention-warning';
            iconEl.className = 'fas fa-pen-to-square attention-bar-icon';
            textEl.textContent = `${drafts.length} draft article${drafts.length > 1 ? 's' : ''} pending review before publication.`;
            linkEl.href = 'articles.php?status=draft';
            linkEl.textContent = 'Inspect drafts →';
        } else if (hidden.length > 0) {
            bar.className = 'global-attention-bar attention-info';
            iconEl.className = 'fas fa-eye-slash attention-bar-icon';
            textEl.textContent = `${hidden.length} item${hidden.length > 1 ? 's are' : ' is'} currently hidden from public visibility.`;
            linkEl.href = 'articles.php?status=hidden';
            linkEl.textContent = 'View hidden →';
        }

        if (closeBtn) {
            closeBtn.onclick = function() {
                bar.style.display = 'none';
                sessionStorage.setItem('attention_bar_dismissed', attentionCount.toString());
            };
        }

    } catch (err) {
        // Silently hide bar on network failure
        bar.style.display = 'none';
    }
}

// ============================================================
// UNIVERSAL CATEGORY MANAGEMENT MODAL
// ============================================================
let currentGlobalCatType = 'blog';

function openGlobalCategoryModal() {
    const modal = document.getElementById('globalCategoryModal');
    if (!modal) return;
    modal.style.display = 'flex';
    modal.classList.add('open');
    loadGlobalCategories(currentGlobalCatType);
}

function closeGlobalCategoryModal() {
    const modal = document.getElementById('globalCategoryModal');
    if (!modal) return;
    modal.style.display = 'none';
    modal.classList.remove('open');
}

function switchGlobalCatType(type) {
    currentGlobalCatType = type;
    const tabBlog = document.getElementById('globalCatTabBlog');
    const tabAchieve = document.getElementById('globalCatTabAchieve');
    if (tabBlog && tabAchieve) {
        if (type === 'blog') {
            tabBlog.classList.add('active');
            tabAchieve.classList.remove('active');
        } else {
            tabAchieve.classList.add('active');
            tabBlog.classList.remove('active');
        }
    }
    loadGlobalCategories(type);
}

async function loadGlobalCategories(type) {
    const listEl = document.getElementById('globalCategoryList');
    if (!listEl) return;
    listEl.innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 20px;">Loading categories...</div>';

    try {
        const res = await fetch(`/api/categories/list.php?type=${encodeURIComponent(type)}`, { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success || !Array.isArray(json.data)) {
            listEl.innerHTML = '<div style="color: var(--danger); padding: 10px;">Failed to load categories.</div>';
            return;
        }

        if (json.data.length === 0) {
            listEl.innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 24px;">No categories created yet.</div>';
            return;
        }

        let html = '';
        json.data.forEach(cat => {
            html += `
                <div class="category-manager-item">
                    <div>
                        <strong>${escapeHTML(cat.name)}</strong>
                    </div>
                    <div class="category-item-meta">
                        <span class="category-item-count">${cat.active_posts_count || 0} items</span>
                        <button type="button" class="btn btn-secondary btn-sm" style="padding: 2px 8px; color: var(--danger);" data-action="global-cat-delete" data-id="${cat.id}" data-name="${escapeHTML(cat.name)}" data-type="${type}" title="Delete category">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </div>
            `;
        });
        listEl.innerHTML = html;
    } catch (err) {
        listEl.innerHTML = '<div style="color: var(--danger); padding: 10px;">Network error loading categories.</div>';
    }
}

async function handleGlobalCategoryCreate(e) {
    e.preventDefault();
    const input = document.getElementById('globalCategoryNewName');
    if (!input || !input.value.trim()) return;

    const name = input.value.trim();
    showLoading(true);

    try {
        const res = await fetch('/api/categories/create.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
            },
            body: JSON.stringify({ name, type: currentGlobalCatType }),
        });
        const json = await res.json();
        showLoading(false);

        if (json.success) {
            showToast('Category created successfully.', 'success');
            input.value = '';
            loadGlobalCategories(currentGlobalCatType);
        } else {
            showToast(json.message || 'Unable to create category.', 'error');
        }
    } catch (err) {
        showLoading(false);
        showToast('Network error while creating category.', 'error');
    }
}

async function deleteGlobalCategory(id, name, type) {
    if (!confirm(`Are you sure you want to delete category "${name}"?`)) return;

    showLoading(true);
    try {
        const res = await fetch('/api/categories/delete.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
            },
            body: JSON.stringify({ id, type }),
        });
        const json = await res.json();
        showLoading(false);

        if (json.success) {
            showToast('Category deleted successfully.', 'success');
            loadGlobalCategories(type);
        } else {
            showToast(json.message || 'Unable to delete category.', 'error');
        }
    } catch (err) {
        showLoading(false);
        showToast('Network error deleting category.', 'error');
    }
}

// Window exports
window.openCommandPalette = openCommandPalette;
window.closeCommandPalette = closeCommandPalette;
window.openGlobalCategoryModal = openGlobalCategoryModal;
window.closeGlobalCategoryModal = closeGlobalCategoryModal;
window.switchGlobalCatType = switchGlobalCatType;
window.handleGlobalCategoryCreate = handleGlobalCategoryCreate;
window.deleteGlobalCategory = deleteGlobalCategory;

// Attach global keyboard shortcuts & listeners
document.addEventListener('DOMContentLoaded', () => {
    // Global keyboard listener
    document.addEventListener('keydown', (e) => {
        const modal = document.getElementById('commandPaletteModal');
        const isOpen = modal && modal.classList.contains('open');

        // ⌘K / Ctrl+K listener
        if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            if (isOpen) {
                closeCommandPalette();
            } else {
                openCommandPalette();
            }
            return;
        }

        if (e.key === 'Escape') {
            closeCommandPalette();
            closeGlobalCategoryModal();
            return;
        }

        if (isOpen) {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                updatePaletteActiveItem(paletteActiveIndex + 1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                updatePaletteActiveItem(paletteActiveIndex - 1);
            } else if (e.key === 'Enter') {
                const container = document.getElementById('commandPaletteResults');
                if (container) {
                    const items = container.querySelectorAll('.command-palette-item');
                    if (items && items.length > 0) {
                        const targetIndex = (paletteActiveIndex >= 0 && paletteActiveIndex < items.length) ? paletteActiveIndex : 0;
                        const target = items[targetIndex];
                        if (target && target.href) {
                            e.preventDefault();
                            window.location.href = target.href;
                        }
                    }
                }
            }
        }
    });

    const paletteInput = document.getElementById('commandPaletteInput');
    if (paletteInput) {
        paletteInput.addEventListener('input', (e) => {
            filterCommandPalette(e.target.value);
        });
    }

    const paletteResults = document.getElementById('commandPaletteResults');
    if (paletteResults) {
        paletteResults.addEventListener('mouseover', (e) => {
            const item = e.target.closest('.command-palette-item');
            if (item) {
                const items = Array.from(paletteResults.querySelectorAll('.command-palette-item'));
                const idx = items.indexOf(item);
                if (idx !== -1 && idx !== paletteActiveIndex) {
                    paletteActiveIndex = idx;
                    items.forEach((it, i) => it.classList.toggle('active', i === idx));
                }
            }
        });
    }

    // Initialize global attention bar on authenticated pages
    initGlobalAttentionBar();

    // SEC-003: delegated listener for global category delete buttons.
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('button[data-action="global-cat-delete"]');
        if (!btn) return;
        const catId = Number(btn.dataset.id);
        const name  = btn.dataset.name;
        const type  = btn.dataset.type;
        deleteGlobalCategory(catId, name, type);
    });
});

// ============================================================
// INLINE HOME SHOWCASE TOGGLE (SHARED EDIT-FORM UTILITY)
// Enables/disables home showcase status with zero page navigation.
// ============================================================

async function syncInlineShowcaseToggle(itemType, entityId) {
    const toggle = document.getElementById('featureOnShowcaseToggle');
    if (!toggle) return;

    if (!entityId || entityId <= 0) {
        toggle.checked = false;
        return;
    }

    try {
        const res = await fetch(`/api/showcase/toggle_entity.php?item_type=${encodeURIComponent(itemType)}&reference_id=${encodeURIComponent(entityId)}`, {
            credentials: 'same-origin'
        });
        const data = await res.json();
        if (data && data.success) {
            toggle.checked = !!data.is_featured;
        }
    } catch (e) {
        console.warn('[showcase] Could not sync showcase state:', e);
    }
}

async function handleInlineShowcaseToggle(itemType) {
    const toggle = document.getElementById('featureOnShowcaseToggle');
    if (!toggle) return;

    // Detect entity ID from common ID fields across articles, projects, store
    const idEl = document.getElementById('update_id') ||
        document.getElementById('achieve_update_id') ||
        document.getElementById('product_id') ||
        document.getElementById('edit_product_id');
    const entityId = idEl ? parseInt(idEl.value, 10) : 0;

    if (!entityId || entityId <= 0) {
        showToast('Please save the item first before featuring it on Home Showcase.', 'warning');
        toggle.checked = false;
        return;
    }

    const isEnabled = toggle.checked ? 1 : 0;
    toggle.disabled = true;

    try {
        const response = await fetch('/api/showcase/toggle_entity.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                item_type: itemType,
                reference_id: entityId,
                is_enabled: isEnabled,
            }),
        });

        const result = await response.json();
        if (!result.success) {
            throw new Error(result.message || 'Failed to update showcase status.');
        }

        toggle.checked = !!result.is_featured;
        showToast(result.message || (isEnabled ? 'Featured on Home Showcase.' : 'Removed from Home Showcase.'), 'success');

    } catch (err) {
        toggle.checked = !isEnabled; // Revert switch state
        showToast('Error: ' + err.message, 'error');
    } finally {
        toggle.disabled = false;
    }
}

// ============================================================
// ADMIN NOTIFICATION CENTER (ALERTS)
// ============================================================

let alertsDropdownOpen = false;
let alertsPollingTimer = null;

function formatRelativeTime(seconds) {
    if (seconds < 45) return 'Just now';
    if (seconds < 90) return '1m ago';
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `${minutes}m ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours}h ago`;
    const days = Math.floor(hours / 24);
    if (days < 30) return `${days}d ago`;
    return 'Recently';
}

function formatSiteDate(dateString, options = {}) {
    if (!dateString) return '';
    try {
        const d = new Date(dateString.replace && dateString.includes(' ') ? dateString.replace(' ', 'T') + 'Z' : dateString);
        if (isNaN(d.getTime())) return dateString;
        
        const tz = window.siteTimezone || 'Asia/Riyadh';
        
        // Default options if none provided
        if (Object.keys(options).length === 0) {
            options = { year: 'numeric', month: 'short', day: 'numeric' };
        }
        
        return d.toLocaleString('en-US', { timeZone: tz, ...options });
    } catch (e) {
        return dateString;
    }
}

function updateBadgeDisplay(elementId, count) {
    const el = document.getElementById(elementId);
    if (!el) return;
    if (count > 0) {
        el.textContent = count > 99 ? '99+' : String(count);
        el.style.display = 'inline-flex';
    } else {
        el.textContent = '0';
        el.style.display = 'none';
    }
}

async function refreshAdminAlertCounts() {
    try {
        const res = await fetch('/api/admin/alerts.php?count_only=1', {
            credentials: 'same-origin',
        });
        const data = await res.json();
        if (data && data.success && data.counts) {
            const counts = data.counts;
            updateBadgeDisplay('adminAlertsBadge', counts.unread_alerts);
            updateBadgeDisplay('adminAlertsBadgeMobile', counts.unread_alerts);
            updateBadgeDisplay('notificationBadge', counts.unread_alerts);
            updateBadgeDisplay('supportNavBadge', counts.new_support);
            updateBadgeDisplay('reviewsNavBadge', counts.pending_reviews);

            const headerCount = document.getElementById('alertsHeaderCount');
            if (headerCount) {
                headerCount.textContent = `${counts.unread_alerts} unread`;
            }

            // If dropdown is open, reload alerts list to reflect updates
            if (alertsDropdownOpen) {
                loadAdminAlertsDropdown();
            }
        }
    } catch (err) {
        // Silently fail on network error
    }
}

function toggleAdminAlertsDropdown(event) {
    if (event) {
        event.stopPropagation();
        event.preventDefault();
    }
    const dropdown = document.getElementById('adminAlertsDropdown');
    if (!dropdown) return;

    if (alertsDropdownOpen) {
        closeAdminAlertsDropdown();
    } else {
        const trigger = event ? (event.currentTarget || event.target?.closest('button, a')) : null;
        const isHeader = trigger && (
            trigger.closest('.header-actions') ||
            trigger.closest('.admin-page-header') ||
            trigger.closest('.global-top-bar') ||          // Phase 1: persistent global top bar
            trigger.querySelector('#notificationBadge') ||
            trigger.id === 'notificationBadge'
        );

        if (isHeader && trigger) {
            dropdown.classList.add('dropdown-from-header');
            const rect = trigger.getBoundingClientRect();
            if (rect && rect.bottom > 0) {
                dropdown.style.top = `${Math.round(rect.bottom + 8)}px`;
                dropdown.style.bottom = 'auto';
                if (document.documentElement.dir === 'rtl') {
                    dropdown.style.left = `${Math.max(16, Math.round(rect.left))}px`;
                    dropdown.style.right = 'auto';
                } else {
                    const rightOffset = Math.max(16, Math.round(window.innerWidth - rect.right));
                    dropdown.style.right = `${rightOffset}px`;
                    dropdown.style.left = 'auto';
                }
            }
        } else {
            dropdown.classList.remove('dropdown-from-header');
            dropdown.style.top = '';
            dropdown.style.bottom = '';
            dropdown.style.left = '';
            dropdown.style.right = '';
        }

        dropdown.style.display = 'flex';
        alertsDropdownOpen = true;
        loadAdminAlertsDropdown();
    }
}

function closeAdminAlertsDropdown() {
    const dropdown = document.getElementById('adminAlertsDropdown');
    if (!dropdown) return;
    dropdown.style.display = 'none';
    dropdown.classList.remove('dropdown-from-header');
    dropdown.style.top = '';
    dropdown.style.bottom = '';
    dropdown.style.left = '';
    dropdown.style.right = '';
    alertsDropdownOpen = false;
}

async function loadAdminAlertsDropdown() {
    const listEl = document.getElementById('alertsDropdownList');
    if (!listEl) return;

    try {
        const res = await fetch('/api/admin/alerts.php?limit=10', {
            credentials: 'same-origin',
        });
        const data = await res.json();

        if (!data || !data.success || !Array.isArray(data.alerts)) {
            listEl.innerHTML = '<div class="alert-empty-state"><i class="fas fa-exclamation-circle"></i><span>Failed to load alerts.</span></div>';
            return;
        }

        const alerts = data.alerts;
        if (alerts.length === 0) {
            listEl.innerHTML = '<div class="alert-empty-state"><i class="far fa-bell-slash" style="font-size: 24px; margin-bottom: 8px; opacity: 0.5;"></i><span>No system alerts right now.</span></div>';
            return;
        }

        // Build DOM nodes safely without innerHTML for user strings
        listEl.innerHTML = '';
        alerts.forEach(alert => {
            const card = document.createElement('div');
            card.className = `alert-card severity-${alert.severity || 'info'} ${alert.is_read ? 'is-read' : 'is-unread'}`;
            card.id = `alertCard_${alert.id}`;

            // Icon
            const iconWrap = document.createElement('div');
            iconWrap.className = 'alert-card-icon';
            const icon = document.createElement('i');
            if (alert.severity === 'critical') {
                icon.className = 'fas fa-triangle-exclamation';
            } else if (alert.severity === 'warning') {
                icon.className = 'fas fa-circle-exclamation';
            } else {
                icon.className = 'fas fa-circle-info';
            }
            iconWrap.appendChild(icon);
            card.appendChild(iconWrap);

            // Content
            const content = document.createElement('div');
            content.className = 'alert-card-content';

            const title = document.createElement('div');
            title.className = 'alert-card-title';
            title.textContent = alert.title || 'System Notification';
            content.appendChild(title);

            if (alert.body) {
                const body = document.createElement('div');
                body.className = 'alert-card-body';
                body.textContent = alert.body;
                content.appendChild(body);
            }

            // Meta row (time, occurrences, actions)
            const meta = document.createElement('div');
            meta.className = 'alert-card-meta';

            const timeEl = document.createElement('span');
            timeEl.textContent = formatRelativeTime(alert.age_seconds || 0);
            meta.appendChild(timeEl);

            if (alert.occurrences && alert.occurrences > 1) {
                const occEl = document.createElement('span');
                occEl.className = 'alert-occurrences-badge';
                occEl.textContent = `×${alert.occurrences}`;
                occEl.title = `${alert.occurrences} occurrences`;
                meta.appendChild(occEl);
            }

            // Actions wrapper
            const actions = document.createElement('div');
            actions.className = 'alert-card-actions';

            if (alert.link) {
                const linkBtn = document.createElement('a');
                linkBtn.className = 'alert-action-btn';
                linkBtn.href = alert.link;
                linkBtn.innerHTML = '<i class="fas fa-arrow-up-right-from-square"></i> Open';
                actions.appendChild(linkBtn);
            }

            if (!alert.is_read) {
                const readBtn = document.createElement('button');
                readBtn.type = 'button';
                readBtn.className = 'alert-action-btn';
                readBtn.title = 'Mark as read';
                readBtn.innerHTML = '<i class="fas fa-check"></i>';
                readBtn.onclick = (e) => markAlertRead(e, alert.id);
                actions.appendChild(readBtn);
            }

            meta.appendChild(actions);
            content.appendChild(meta);
            card.appendChild(content);

            listEl.appendChild(card);
        });

    } catch (err) {
        listEl.innerHTML = '<div class="alert-empty-state"><i class="fas fa-wifi"></i><span>Network error loading alerts.</span></div>';
    }
}

async function markAlertRead(event, id) {
    if (event) {
        event.stopPropagation();
        event.preventDefault();
    }
    if (!id || id <= 0) return;

    try {
        const res = await fetch('/api/admin/alerts_read.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
            },
            body: JSON.stringify({ id: id }),
        });
        const data = await res.json();
        if (data && data.success) {
            const card = document.getElementById(`alertCard_${id}`);
            if (card) {
                card.classList.remove('is-unread');
                card.classList.add('is-read');
                const btn = card.querySelector('button[title="Mark as read"]');
                if (btn) btn.remove();
            }
            if (data.counts) {
                updateBadgeDisplay('adminAlertsBadge', data.counts.unread_alerts);
                updateBadgeDisplay('adminAlertsBadgeMobile', data.counts.unread_alerts);
                updateBadgeDisplay('notificationBadge', data.counts.unread_alerts);
                const headerCount = document.getElementById('alertsHeaderCount');
                if (headerCount) {
                    headerCount.textContent = `${data.counts.unread_alerts} unread`;
                }
            }
        }
    } catch (err) {
        // Silently ignore
    }
}

async function markAllAlertsRead(event) {
    if (event) {
        event.stopPropagation();
        event.preventDefault();
    }

    try {
        const res = await fetch('/api/admin/alerts_read.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
            },
            body: JSON.stringify({ all: true }),
        });
        const data = await res.json();
        if (data && data.success) {
            updateBadgeDisplay('adminAlertsBadge', 0);
            updateBadgeDisplay('adminAlertsBadgeMobile', 0);
            updateBadgeDisplay('notificationBadge', 0);
            const headerCount = document.getElementById('alertsHeaderCount');
            if (headerCount) {
                headerCount.textContent = '0 unread';
            }
            loadAdminAlertsDropdown();
            showToast('All alerts marked as read.', 'success');
        }
    } catch (err) {
        showToast('Error marking alerts as read.', 'error');
    }
}

// Window exports for dead handler checks and external triggers
window.toggleAdminAlertsDropdown = toggleAdminAlertsDropdown;
window.closeAdminAlertsDropdown = closeAdminAlertsDropdown;
window.loadAdminAlertsDropdown = loadAdminAlertsDropdown;
window.markAlertRead = markAlertRead;
window.markAllAlertsRead = markAllAlertsRead;
window.refreshAdminAlertCounts = refreshAdminAlertCounts;

// Page Visibility API polling: every 60s ONLY while tab is visible
function initAlertsPolling() {
    if (alertsPollingTimer) return;

    alertsPollingTimer = setInterval(() => {
        if (!document.hidden) {
            refreshAdminAlertCounts();
        }
    }, 60000);

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            refreshAdminAlertCounts();
        }
    });

    // Close dropdown on outside click
    document.addEventListener('click', (e) => {
        if (!alertsDropdownOpen) return;
        const dropdown = document.getElementById('adminAlertsDropdown');
        const btnDesktop = document.getElementById('adminAlertsBtnDesktop');
        const btnMobile = document.getElementById('adminAlertsBtnMobile');
        const notifBadgeBtn = document.getElementById('notificationBadge')?.closest('a, button');

        if (
            dropdown && !dropdown.contains(e.target) &&
            (!btnDesktop || !btnDesktop.contains(e.target)) &&
            (!btnMobile || !btnMobile.contains(e.target)) &&
            (!notifBadgeBtn || !notifBadgeBtn.contains(e.target))
        ) {
            closeAdminAlertsDropdown();
        }
    });

    // Close on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && alertsDropdownOpen) {
            closeAdminAlertsDropdown();
        }
    });
}

// Attach polling on DOMContentLoaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAlertsPolling);
} else {
    initAlertsPolling();
}



