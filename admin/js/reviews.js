// ============================================================
// ADMIN — REVIEWS PAGE (reviews.php)
// Visitor review moderation: list/filter, approve, reject,
// delete. Depends on js/common.js.
// ============================================================


let currentReviewFilter = 'all';

// Full review records for the currently loaded filter, keyed by id.
// Used to populate the "read full review" modal without re-fetching
// or re-parsing data already returned by /api/reviews/list.php.
let reviewsById = new Map();

// Preview length (characters) shown inside the table cell before a
// review is truncated and a "قراءة المراجعة" link is offered.
const REVIEW_PREVIEW_LIMIT = 160;

// Shared status label/class maps — used by both the table rows and
// the full-review modal so the two stay in sync.
const REVIEW_STATUS_LABELS = {
    pending: 'Pending',
    approved: 'Approved',
    rejected: 'Rejected',
};

const REVIEW_STATUS_CLASSES = {
    pending: 'status-badge status-warning',
    approved: 'status-badge status-success',
    rejected: 'status-badge status-danger',
};


// ============================================================
// REVIEW SCOPE HELPERS
// ============================================================
//
// Builds the HTML for the scope cell shown in the
// admin reviews table and the full-review modal.
//
//   post_id IS NULL  → global/homepage review   (grey badge)
//   post_id = N      → article-specific review  (cyan badge
//                       + link to the article)
//
// Title is truncated to SCOPE_TITLE_LIMIT characters in the
// table cell to keep the column compact; the full title appears
// in the modal.

const SCOPE_TITLE_LIMIT = 40;

/**
 * Returns safe inner HTML for the scope badge.
 * @param {object} review  – full review record from the API
 * @param {boolean} full   – true = show full title (modal), false = truncate (table)
 */
function buildReviewScopeHTML(
    review,
    full = false
) {

    if (!review.post_id) {
        // Global / homepage testimonial
        return '<span class="review-scope-badge review-scope-global">' +
            'Global — Homepage' +
            '</span>';
    }

    // Article-specific review
    const rawTitle = review.post_title || '';

    // Article was deleted since the review was submitted
    if (rawTitle === '') {
        return '<span class="review-scope-badge review-scope-article review-scope-deleted">' +
            'Deleted Post — #' + escapeHTML(String(review.post_id)) +
            '</span>';
    }

    const displayTitle = (!full && rawTitle.length > SCOPE_TITLE_LIMIT)
        ? rawTitle.slice(0, SCOPE_TITLE_LIMIT).trimEnd() + '…'
        : rawTitle;

    const safeTitle = escapeHTML(displayTitle);
    const safeId = escapeHTML(String(review.post_id));

    return '<span class="review-scope-badge review-scope-article">' +
        '<a href="/post.html?id=' + safeId + '" ' +
        'target="_blank" ' +
        'rel="noopener noreferrer" ' +
        'class="review-scope-link" ' +
        'title="' + escapeHTML(rawTitle) + '">' +
        safeTitle +
        '</a>' +
        '</span>';

}


// ============================================================
// REVIEW MESSAGE — TABLE PREVIEW TRUNCATION
// ============================================================
//
// Cuts a review's raw (unescaped) text down to a compact preview so
// a single row can never grow tall enough to dominate the page. Cuts
// on a word boundary where possible. The full text is always still
// reachable via the modal — nothing is hidden with CSS alone.

function truncateReviewMessage(
    message
) {

    if (
        !message ||
        message.length <= REVIEW_PREVIEW_LIMIT
    ) {

        return {
            text: message || '',
            truncated: false,
        };

    }


    let cut =
        message.slice(0, REVIEW_PREVIEW_LIMIT);

    const lastSpace =
        cut.lastIndexOf(' ');

    // Only cut on the word boundary if it doesn't throw away most of
    // the preview (e.g. one very long unbroken word/URL).
    if (lastSpace > REVIEW_PREVIEW_LIMIT * 0.5) {
        cut = cut.slice(0, lastSpace);
    }


    return {
        text: cut.trim() + '…',
        truncated: true,
    };

}


// ============================================================
// INITIALIZATION
// ============================================================

document.addEventListener(
    'DOMContentLoaded',
    async () => {

        const authenticated =
            await checkAdminAuthentication();

        if (!authenticated) {
            return;
        }


        initReviewModal();

        await loadReviews();

    }
);


// ============================================================
// REVIEWS — LOAD + RENDER
// GET /api/reviews/list.php
// ============================================================

async function loadReviews() {

    const tbodyEl =
        document.getElementById(
            'reviewsTableBody'
        );

    tbodyEl?.classList.remove('reviews-empty-body');

    renderSkeletonRows(tbodyEl, 6);


    try {

        const response = await fetch(
            '/api/reviews/list.php?status=' + currentReviewFilter,
            {
                method: 'GET',
                credentials: 'same-origin',
            }
        );


        const result = await response.json();


        if (!result.success) {

            throw new Error(result.message || 'Failed to load reviews.');

        }


        const reviews = result.data;


        // Page-level counters always reflect real API data,
        // independent of the currently active table filter.
        await updateReviewSummaryCounts();


        const tbody =
            document.getElementById(
                'reviewsTableBody'
            );


        if (!tbody) {

            console.error(
                'reviewsTableBody not found.'
            );

            return;

        }


        tbody.innerHTML =
            '';

        tbody.classList.remove(
            'reviews-empty-body'
        );

        // Rebuild the id → full-review lookup for this filter so the
        // modal always has the complete, untruncated record on hand.
        reviewsById = new Map();


        if (!reviews || reviews.length === 0) {

            const emptyMessages = {
                all: 'No reviews yet.',
                pending: 'No reviews pending moderation.',
                approved: 'No approved reviews.',
                rejected: 'No rejected reviews.',
            };

            renderEmptyState(
                tbody,
                6,
                {
                    icon: 'fas fa-star',
                    message: emptyMessages[currentReviewFilter] || emptyMessages.all,
                }
            );

            tbody.classList.add(
                'reviews-empty-body'
            );

            return;

        }


        reviews.forEach(
            review => {

                reviewsById.set(
                    review.id,
                    review
                );


                const tr =
                    document.createElement(
                        'tr'
                    );


                const name =
                    escapeHTML(
                        review.name || ''
                    );


                const email =
                    escapeHTML(
                        review.email || ''
                    );


                // Long reviews stay compact in the table: only a short
                // preview is rendered here, with a "Read Review"
                // link that opens the full text in a modal. Truncation
                // happens on the raw text (before escaping) so escaped
                // HTML entities are never cut in half.
                const {
                    text: previewRaw,
                    truncated,
                } = truncateReviewMessage(
                    review.message || ''
                );

                const messagePreview =
                    escapeHTML(previewRaw);


                const statusLabel =
                    REVIEW_STATUS_LABELS[review.status] || review.status;


                const statusClass =
                    REVIEW_STATUS_CLASSES[review.status] || 'status-badge status-neutral';


                const formattedDate =
                    review.created_at
                        ? formatGregorianDateTime(
                            review.created_at
                        )
                        : '';


                tr.innerHTML = `

                    <td class="table-cell-strong review-name-cell" data-label="Name">
                        ${name}
                    </td>

                    <td class="table-cell-muted review-email-cell" data-label="Email">
                        ${email}
                    </td>

                    <td class="review-message-cell" data-label="Message">
                        <div class="review-message-text">
                            ${messagePreview}
                            ${truncated ? `
                            <button
                                type="button"
                                class="review-read-more-btn"
                                data-review-id="${review.id}"
                            >
                                Read Review
                            </button>
                            ` : ''}
                        </div>
                    </td>

                    <td class="review-scope-cell" data-label="Scope">
                        ${buildReviewScopeHTML(review, false)}
                    </td>

                    <td class="review-status-cell" data-label="Status">
                        <span class="${statusClass}">
                            ${statusLabel}
                        </span>
                    </td>

                    <td class="review-date-cell" data-label="Date">
                        ${formattedDate}
                    </td>

                    <td class="review-actions-cell" data-label="Actions">

                        <div class="review-actions">

                        <button
                            class="btn-action btn-approve"
                            data-action="approve"
                            ${review.status === 'approved' ? 'disabled' : ''}
                        >
                            <i class="fas fa-check"></i>
                            Approve
                        </button>

                        <button
                            class="btn-action btn-reject"
                            data-action="reject"
                            ${review.status === 'rejected' ? 'disabled' : ''}
                        >
                            <i class="fas fa-ban"></i>
                            Reject
                        </button>

                        <button
                            class="btn-action btn-delete"
                            data-action="delete"
                        >
                            <i class="fas fa-trash"></i>
                            Delete
                        </button>

                        </div>

                    </td>

                `;


                tbody.appendChild(
                    tr
                );


                const readMoreBtn =
                    tr.querySelector(
                        '.review-read-more-btn'
                    );

                if (readMoreBtn) {

                    readMoreBtn.addEventListener(
                        'click',
                        () => {
                            openReviewModal(review.id);
                        }
                    );

                }


                const approveBtn =
                    tr.querySelector(
                        '[data-action="approve"]'
                    );

                if (approveBtn) {

                    approveBtn.addEventListener(
                        'click',
                        () => {
                            setReviewStatus(review.id, 'approved');
                        }
                    );

                }


                const rejectBtn =
                    tr.querySelector(
                        '[data-action="reject"]'
                    );

                if (rejectBtn) {

                    rejectBtn.addEventListener(
                        'click',
                        () => {
                            setReviewStatus(review.id, 'rejected');
                        }
                    );

                }


                const deleteBtn =
                    tr.querySelector(
                        '[data-action="delete"]'
                    );

                if (deleteBtn) {

                    deleteBtn.addEventListener(
                        'click',
                        () => {
                            deleteReview(review.id);
                        }
                    );

                }

            }
        );


    } catch (error) {

        console.error(
            '❌ Load reviews error:',
            error
        );


        renderEmptyState(
            tbodyEl,
            6,
            {
                icon: 'fas fa-triangle-exclamation',
                message: 'Error loading reviews. Please try again.',
                ctaLabel: 'Retry',
                ctaHref: '#reviews',
            }
        );

        tbodyEl?.classList.add(
            'reviews-empty-body'
        );

        tbodyEl
            ?.querySelector('.empty-state-action')
            ?.addEventListener(
                'click',
                event => {

                    event.preventDefault();
                    loadReviews();

                }
            );


        showToast(
            'Error fetching reviews: ' +
            (error.message || 'Unknown error'),
            'error'
        );

    }

}


// ============================================================
// REVIEWS — SUMMARY COUNTS
// ============================================================

async function updateReviewSummaryCounts() {

    try {

        const [
            allResponse,
            pendingResponse,
        ] = await Promise.all([
            fetch(
                '/api/reviews/list.php?status=all',
                {
                    method: 'GET',
                    credentials: 'same-origin',
                }
            ),
            fetch(
                '/api/reviews/list.php?status=pending',
                {
                    method: 'GET',
                    credentials: 'same-origin',
                }
            ),
        ]);

        const allResult =
            await allResponse.json();

        const pendingResult =
            await pendingResponse.json();

        const countEl =
            document.getElementById(
                'totalPendingReviews'
            );

        const badgeEl =
            document.getElementById(
                'reviewsPendingBadge'
            );

        const totalMetaEl =
            document.getElementById(
                'reviewsTotalMeta'
            );

        if (countEl && badgeEl && pendingResult.success) {

            countEl.textContent =
                pendingResult.data.length;

            badgeEl.hidden = false;

            const overviewPending = document.getElementById('overviewPendingReviews');
            if (overviewPending) {
                overviewPending.textContent = pendingResult.data.length;
            }

        }

        if (totalMetaEl && allResult.success) {

            totalMetaEl.textContent =
                'Total Reviews: ' +
                allResult.data.length;

            totalMetaEl.hidden = false;

            const overviewTotal = document.getElementById('overviewTotalReviews');
            if (overviewTotal) {
                overviewTotal.textContent = allResult.data.length;
            }

            const overviewApproved = document.getElementById('overviewApprovedReviews');
            if (overviewApproved && Array.isArray(allResult.data)) {
                overviewApproved.textContent = allResult.data.filter(r => r.status === 'approved').length;
            }

        }

    } catch (error) {

        console.error(
            '❌ Review summary count error:',
            error
        );

    }

}


// Backwards-compatible alias in case an older inline reference
// still points to the previous helper name.
async function updatePendingReviewCount() {

    return updateReviewSummaryCounts();

}


// ============================================================
// REVIEWS — FILTER TABS
// ============================================================

function filterReviews(
    status
) {

    currentReviewFilter = status;


    document.querySelectorAll(
        '.filter-tab, .filter-btn'
    ).forEach(
        btn => {

            const isActive =
                btn.dataset.status === status;

            btn.classList.toggle(
                'active',
                isActive
            );

            btn.setAttribute(
                'aria-pressed',
                isActive ? 'true' : 'false'
            );

        }
    );


    loadReviews();

}


// ============================================================
// REVIEWS — APPROVE / REJECT
// ============================================================

async function setReviewStatus(
    id,
    status
) {

    showLoading(true);


    try {

        const response = await fetch(
            '/api/reviews/update_status.php',
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken,
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    id,
                    status,
                }),
            }
        );


        const result = await response.json();


        if (!result.success) {

            throw new Error(result.message || 'Failed to update review status.');

        }


        await loadReviews();
        showToast(status === 'approved' ? 'Review approved successfully.' : 'Review rejected.', 'success');


    } catch (error) {

        console.error(
            '❌ Update review status error:',
            error
        );

        showToast(
            'Error: ' +
            error.message,
            'error'
        );


    } finally {

        showLoading(false);

    }

}


// ============================================================
// REVIEWS — DELETE
// ============================================================

async function deleteReview(
    id
) {

    const confirmed =
        confirm(
            'Are you sure you want to delete this review? This action cannot be undone.'
        );


    if (!confirmed) {

        return;

    }


    showLoading(true);


    try {

        const response = await fetch(
            '/api/reviews/delete.php',
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken,
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    id,
                }),
            }
        );


        const result = await response.json();


        if (!result.success) {

            throw new Error(result.message || 'Failed to delete review.');

        }


        await loadReviews();
        showToast('Review deleted successfully.', 'success');


    } catch (error) {

        console.error(
            '❌ Delete review error:',
            error
        );

        showToast(
            'Error: ' +
            error.message,
            'error'
        );


    } finally {

        showLoading(false);

    }

}


// ============================================================
// REVIEW MODAL — FULL REVIEW READ + ACTIONS
// Opened from the "قراءة المراجعة" link in a table row. Shows the
// complete, untruncated review plus the same approve/reject/delete
// actions already available in the table (no new capabilities).
// ============================================================

let reviewModalLastFocusedEl = null;
let currentReviewModalId = null;

function initReviewModal() {

    const overlay =
        document.getElementById(
            'reviewModalOverlay'
        );

    if (!overlay) {
        // Modal markup not present on this page render — nothing to wire up.
        return;
    }


    document
        .getElementById('reviewModalCloseBtn')
        ?.addEventListener('click', closeReviewModal);


    // Clicking the dimmed backdrop closes the modal; clicking inside
    // the dialog itself must not.
    overlay.addEventListener(
        'click',
        event => {

            if (event.target === overlay) {
                closeReviewModal();
            }

        }
    );


    document.addEventListener(
        'keydown',
        event => {

            if (overlay.hidden) {
                return;
            }

            if (event.key === 'Escape') {
                closeReviewModal();
                return;
            }

            if (event.key === 'Tab') {
                trapReviewModalFocus(event);
            }

        }
    );


    document
        .getElementById('reviewModalApproveBtn')
        ?.addEventListener('click', () => {
            if (currentReviewModalId !== null) {
                const id = currentReviewModalId;
                closeReviewModal();
                setReviewStatus(id, 'approved');
            }
        });

    document
        .getElementById('reviewModalRejectBtn')
        ?.addEventListener('click', () => {
            if (currentReviewModalId !== null) {
                const id = currentReviewModalId;
                closeReviewModal();
                setReviewStatus(id, 'rejected');
            }
        });

    document
        .getElementById('reviewModalDeleteBtn')
        ?.addEventListener('click', () => {
            if (currentReviewModalId !== null) {
                const id = currentReviewModalId;
                closeReviewModal();
                deleteReview(id);
            }
        });

}


function openReviewModal(
    id
) {

    const review =
        reviewsById.get(id);

    if (!review) {
        console.error('openReviewModal: unknown review id', id);
        return;
    }


    const overlay =
        document.getElementById('reviewModalOverlay');

    if (!overlay) {
        return;
    }


    currentReviewModalId = id;


    setText('reviewModalName', review.name || '');
    setText('reviewModalAuthor', review.name || '');
    setText('reviewModalEmail', review.email || '');
    setText('reviewModalMessage', review.message || '');

    setText(
        'reviewModalDate',
        review.created_at ? formatGregorianDateTime(review.created_at) : ''
    );

    // Scope field: set innerHTML rather than textContent because
    // buildReviewScopeHTML() returns a badge + optional anchor tag.
    // All user-supplied values inside that HTML are passed through
    // escapeHTML() so this is XSS-safe.
    const scopeEl = document.getElementById('reviewModalScope');
    if (scopeEl) {
        scopeEl.innerHTML = buildReviewScopeHTML(review, true);
    }


    const statusEl =
        document.getElementById('reviewModalStatus');

    if (statusEl) {

        statusEl.textContent =
            REVIEW_STATUS_LABELS[review.status] || review.status || '';

        statusEl.className =
            REVIEW_STATUS_CLASSES[review.status] || 'status-badge status-neutral';

    }


    const approveBtn =
        document.getElementById('reviewModalApproveBtn');

    const rejectBtn =
        document.getElementById('reviewModalRejectBtn');

    if (approveBtn) {
        approveBtn.disabled = review.status === 'approved';
    }

    if (rejectBtn) {
        rejectBtn.disabled = review.status === 'rejected';
    }


    reviewModalLastFocusedEl =
        document.activeElement;

    overlay.hidden = false;

    document.body.classList.add('review-modal-open');


    document
        .getElementById('reviewModalCloseBtn')
        ?.focus();

}


function closeReviewModal() {

    const overlay =
        document.getElementById('reviewModalOverlay');

    if (!overlay || overlay.hidden) {
        return;
    }


    overlay.hidden = true;

    document.body.classList.remove('review-modal-open');

    currentReviewModalId = null;


    reviewModalLastFocusedEl?.focus();

    reviewModalLastFocusedEl = null;

}


function trapReviewModalFocus(
    event
) {

    const modal =
        document.getElementById('reviewModal');

    if (!modal) {
        return;
    }


    const focusable =
        modal.querySelectorAll(
            'button:not([disabled]), [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );

    if (focusable.length === 0) {
        return;
    }


    const first = focusable[0];
    const last = focusable[focusable.length - 1];


    if (event.shiftKey && document.activeElement === first) {

        event.preventDefault();
        last.focus();

    } else if (!event.shiftKey && document.activeElement === last) {

        event.preventDefault();
        first.focus();

    }

}


function setText(
    elementId,
    value
) {

    const el =
        document.getElementById(elementId);

    if (el) {
        el.textContent = value;
    }

}


// ============================================================
// DATE FORMAT — GREGORIAN ADMIN DISPLAY
// ============================================================

function formatGregorianDateTime(
    value
) {

    const date =
        new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return date.toLocaleString(
        'en-US',
        {
            calendar: 'gregory',
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        }
    );

}
