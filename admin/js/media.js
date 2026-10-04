// ============================================================
// ADMIN — MEDIA LIBRARY (media.php)
// Aggregates cover illustrations, architecture diagrams, and
// uploaded assets without inventing a new database table.
// ============================================================

let allMediaAssets = [];
let currentFilter = 'all';

document.addEventListener('DOMContentLoaded', async () => {
    const authenticated = await checkAdminAuthentication();
    if (!authenticated) return;

    await loadMediaLibrary();

    // SEC-003: delegated listener for media card action buttons and thumbnail clicks.
    document.addEventListener('click', function (e) {
        // Preview: img thumbnail (data-action="preview") or preview button
        const previewEl = e.target.closest('[data-action="preview"]');
        if (previewEl) {
            openMediaPreview(previewEl.dataset.url, previewEl.dataset.title);
            return;
        }
        // Copy URL button
        const copyBtn = e.target.closest('button[data-action="copy-url"]');
        if (copyBtn) {
            copyAssetUrl(copyBtn.dataset.url);
        }
    });
});

async function loadMediaLibrary() {
    const loadingState = document.getElementById('mediaLoadingState');
    const emptyState = document.getElementById('mediaEmptyState');
    const grid = document.getElementById('mediaGrid');

    if (loadingState) loadingState.style.display = 'block';
    if (emptyState) emptyState.style.display = 'none';
    if (grid) grid.style.display = 'none';

    try {
        const postsRes = await fetch('/api/posts/list.php?status=all', {
            credentials: 'same-origin'
        });
        const postsData = await postsRes.json();

        allMediaAssets = [];

        // 1. Fetch metadata registry from /api/media/list.php
        let metaMap = {};
        try {
            const mediaListRes = await fetch('/api/media/list.php', { credentials: 'same-origin' });
            const mediaListData = await mediaListRes.json();
            if (mediaListData && mediaListData.success && Array.isArray(mediaListData.data)) {
                mediaListData.data.forEach(m => {
                    const cleanPath = m.file_path || ('/uploads/' + m.file_name);
                    metaMap[cleanPath] = m;
                    metaMap[m.file_name] = m;
                });
            }
        } catch (e) {
            // Non-fatal if media registry fetch fails
        }

        if (postsData && postsData.success && Array.isArray(postsData.data)) {
            const posts = postsData.data;

            // 2. Primary Cover Images from Posts & Projects
            posts.forEach(p => {
                if (p.image_url && typeof p.image_url === 'string' && p.image_url.trim() !== '') {
                    const isProject = p.type === 'project';
                    const url = p.image_url.trim();
                    const filename = url.split('/').pop();
                    const meta = metaMap[url] || metaMap[filename] || {};

                    allMediaAssets.push({
                        url: url,
                        title: p.title || 'Untitled',
                        type: isProject ? 'project' : 'article',
                        typeLabel: isProject ? 'Project Architecture' : 'Article Cover',
                        date: p.created_at || null,
                        sourceId: p.id,
                        fileSize: meta.file_size_formatted || null,
                        width: meta.width || null,
                        height: meta.height || null,
                    });
                }
            });

            // 3. Query Project Gallery Images for Projects
            const projects = posts.filter(p => p.type === 'project');
            const galleryPromises = projects.map(async (achieve) => {
                try {
                    const galRes = await fetch(`/api/project_images/list.php?achievement_id=${encodeURIComponent(achieve.id)}`, {
                        credentials: 'same-origin'
                    });
                    const galData = await galRes.json();
                    if (galData && galData.success && Array.isArray(galData.data)) {
                        galData.data.forEach(img => {
                            if (img.image_url) {
                                const url = img.image_url.trim();
                                const filename = url.split('/').pop();
                                const meta = metaMap[url] || metaMap[filename] || {};

                                allMediaAssets.push({
                                    url: url,
                                    title: `${achieve.title} (Gallery)`,
                                    type: 'project',
                                    typeLabel: 'Project Diagram',
                                    date: img.created_at || achieve.created_at || null,
                                    sourceId: achieve.id,
                                    fileSize: meta.file_size_formatted || null,
                                    width: meta.width || null,
                                    height: meta.height || null,
                                });
                            }
                        });
                    }
                } catch (e) {
                    // Non-fatal if a gallery fetch fails
                }
            });

            await Promise.all(galleryPromises);
        }

        // 4. Also include standalone uploaded assets from registry
        Object.values(metaMap).forEach(m => {
            const path = m.file_path || ('/uploads/' + m.file_name);
            const exists = allMediaAssets.some(a => a.url === path);
            if (!exists) {
                allMediaAssets.push({
                    url: path,
                    title: m.file_name || 'Uploaded Asset',
                    type: 'upload',
                    typeLabel: 'Uploaded Asset',
                    date: m.created_at || null,
                    sourceId: m.id || null,
                    fileSize: m.file_size_formatted || null,
                    width: m.width || null,
                    height: m.height || null,
                });
            }
        });

        // De-duplicate by URL
        const seenUrls = new Set();
        allMediaAssets = allMediaAssets.filter(item => {
            if (seenUrls.has(item.url)) return false;
            seenUrls.add(item.url);
            return true;
        });

        // Update counts
        updateMediaCounts();

        // Render
        if (loadingState) loadingState.style.display = 'none';

        if (allMediaAssets.length === 0) {
            if (emptyState) emptyState.style.display = 'block';
            if (grid) grid.style.display = 'none';
        } else {
            if (emptyState) emptyState.style.display = 'none';
            if (grid) grid.style.display = 'grid';
            renderMediaGrid();
        }

    } catch (err) {
        console.error('Failed to load media library:', err);
        if (loadingState) loadingState.style.display = 'none';
        showToast('Error loading media assets: ' + (err.message || 'Network error'), 'error');
    }
}

function updateMediaCounts() {
    const total = allMediaAssets.length;
    const articles = allMediaAssets.filter(a => a.type === 'article').length;
    const projects = allMediaAssets.filter(a => a.type === 'project').length;

    const countAllEl = document.getElementById('countMediaAll');
    const countArticlesEl = document.getElementById('countMediaArticles');
    const countProjectsEl = document.getElementById('countMediaProjects');

    if (countAllEl) countAllEl.textContent = String(total);
    if (countArticlesEl) countArticlesEl.textContent = String(articles);
    if (countProjectsEl) countProjectsEl.textContent = String(projects);
}

function setMediaFilter(filter) {
    currentFilter = filter;

    document.querySelectorAll('.filter-tabs .filter-tab').forEach(btn => {
        const btnFilter = btn.getAttribute('data-filter');
        btn.classList.toggle('active', btnFilter === filter);
    });

    renderMediaGrid();
}

function filterMediaGrid() {
    renderMediaGrid();
}

function renderMediaGrid() {
    const grid = document.getElementById('mediaGrid');
    const emptyState = document.getElementById('mediaEmptyState');
    if (!grid) return;

    const searchInput = document.getElementById('mediaSearchInput');
    const searchQuery = searchInput ? searchInput.value.trim().toLowerCase() : '';

    const filtered = allMediaAssets.filter(item => {
        if (currentFilter === 'articles' && item.type !== 'article') return false;
        if (currentFilter === 'projects' && item.type !== 'project') return false;

        if (searchQuery) {
            const matchTitle = item.title.toLowerCase().includes(searchQuery);
            const matchUrl = item.url.toLowerCase().includes(searchQuery);
            if (!matchTitle && !matchUrl) return false;
        }

        return true;
    });

    if (filtered.length === 0) {
        grid.style.display = 'none';
        if (emptyState) emptyState.style.display = 'block';
        return;
    }

    if (emptyState) emptyState.style.display = 'none';
    grid.style.display = 'grid';

    grid.innerHTML = filtered.map(item => {
        const safeUrl = escapeHTML(item.url);
        const safeTitle = escapeHTML(item.title);
        const safeType = escapeHTML(item.typeLabel);
        const filename = safeUrl.split('/').pop() || 'image';

        const isUpload = safeUrl.includes('/uploads/');
        const thumbUrl = isUpload ? safeUrl.replace(/\.(jpe?g|png|webp)$/i, '-480.webp') : safeUrl;
        const sizeBadge = item.fileSize ? `<span style="font-family: var(--font-mono); font-size: 10px; color: var(--accent); background: var(--accent-subtle); padding: 1px 5px; border-radius: 4px;">${escapeHTML(item.fileSize)}</span>` : '';

        return `
            <div class="media-card">
                <img
                    src="${thumbUrl}"
                    alt="${safeTitle}"
                    class="media-card-thumb"
                    loading="lazy"
                    data-action="preview"
                    data-url="${safeUrl}"
                    data-title="${escapeHTML(item.title)}"
                    onerror="if(this.src !== '${safeUrl}') { this.src='${safeUrl}'; } else { this.src='/uploads/placeholder.svg'; this.onerror=null; }"
                >
                <div class="media-card-body">
                    <div class="media-card-title" title="${safeTitle}">${safeTitle}</div>
                    <div class="media-card-meta">
                        <span class="status-badge status-neutral" style="font-size: 10.5px;">${safeType}</span>
                        ${sizeBadge}
                        <span title="${filename}">${filename.length > 16 ? filename.substring(0, 14) + '…' : filename}</span>
                    </div>
                    <div class="media-card-actions">
                        <button type="button" class="btn btn-secondary btn-sm" style="flex: 1; font-size: 11px;" data-action="copy-url" data-url="${safeUrl}">
                            <i class="far fa-copy"></i> Copy URL
                        </button>
                        <button type="button" class="btn btn-secondary btn-sm" style="font-size: 11px;" data-action="preview" data-url="${safeUrl}" data-title="${escapeHTML(item.title)}">
                            <i class="far fa-eye"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function openMediaPreview(url, contextTitle) {
    const modal = document.getElementById('mediaPreviewModal');
    const modalImg = document.getElementById('previewModalImage');
    const modalUrl = document.getElementById('previewModalUrl');
    const modalContext = document.getElementById('previewModalContext');
    const modalLink = document.getElementById('previewModalOpenLink');

    if (modalImg) modalImg.src = url;
    if (modalUrl) modalUrl.value = url;

    const item = allMediaAssets.find(a => a.url === url);
    let details = contextTitle || 'Unassigned';
    if (item && item.fileSize) {
        details += ` · ${item.fileSize}`;
        if (item.width && item.height) {
            details += ` (${item.width}×${item.height}px)`;
        }
    }
    if (modalContext) modalContext.textContent = details;
    if (modalLink) modalLink.href = url;

    modal.style.display = 'flex';
}

function closeMediaPreviewModal() {
    const modal = document.getElementById('mediaPreviewModal');
    if (modal) modal.style.display = 'none';
}

function copyPreviewUrl() {
    const modalUrl = document.getElementById('previewModalUrl');
    if (modalUrl && modalUrl.value) {
        copyAssetUrl(modalUrl.value);
    }
}

async function copyAssetUrl(url) {
    try {
        const fullUrl = url.startsWith('http') ? url : window.location.origin + url;
        await navigator.clipboard.writeText(fullUrl);
        showToast('Asset URL copied to clipboard.', 'success');
    } catch (e) {
        // Fallback for older browsers
        const temp = document.createElement('input');
        temp.value = url.startsWith('http') ? url : window.location.origin + url;
        document.body.appendChild(temp);
        temp.select();
        document.execCommand('copy');
        document.body.removeChild(temp);
        showToast('Asset URL copied to clipboard.', 'success');
    }
}

function openMediaUploadModal() {
    const modal = document.getElementById('mediaUploadModal');
    const form = document.getElementById('mediaUploadForm');
    const previewWrapper = document.getElementById('uploadPreviewWrapper');
    const progress = document.getElementById('uploadProgressMessage');

    if (form) form.reset();
    if (previewWrapper) previewWrapper.style.display = 'none';
    if (progress) progress.style.display = 'none';
    if (modal) modal.style.display = 'flex';
}

function closeMediaUploadModal() {
    const modal = document.getElementById('mediaUploadModal');
    if (modal) modal.style.display = 'none';
}

function handleMediaFileSelect(e) {
    const file = e.target.files && e.target.files[0];
    const previewWrapper = document.getElementById('uploadPreviewWrapper');
    const previewImg = document.getElementById('uploadPreviewImage');

    if (file && previewWrapper && previewImg) {
        const reader = new FileReader();
        reader.onload = (evt) => {
            previewImg.src = evt.target.result;
            previewWrapper.style.display = 'block';
        };
        reader.readAsDataURL(file);
    }
}

async function handleMediaUpload(e) {
    e.preventDefault();

    const fileInput = document.getElementById('mediaFileInput');
    const file = fileInput && fileInput.files && fileInput.files[0];

    if (!file) {
        showToast('Please select an image file to upload.', 'error');
        return;
    }

    const submitBtn = document.getElementById('mediaUploadSubmitBtn');
    const progress = document.getElementById('uploadProgressMessage');

    if (submitBtn) submitBtn.disabled = true;
    if (progress) progress.style.display = 'block';

    try {
        const csrfToken = getCSRFToken();
        const formData = new FormData();
        formData.append('image', file, 'upload_' + Date.now() + '_' + file.name.replace(/[^a-zA-Z0-9.-_]/g, '_'));
        if (csrfToken) {
            formData.append('csrf_token', csrfToken);
        }

        const headers = {};
        if (csrfToken) {
            headers['X-CSRF-Token'] = csrfToken;
        }

        const res = await fetch('/api/uploads/image.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers,
            body: formData
        });

        const data = await res.json();

        if (!data || !data.success) {
            throw new Error((data && data.message) || 'Upload failed');
        }

        showToast('Media uploaded successfully!', 'success');
        closeMediaUploadModal();

        // Add to active library immediately
        allMediaAssets.unshift({
            url: data.url,
            title: file.name,
            type: 'article',
            typeLabel: 'Uploaded Asset',
            date: new Date().toISOString(),
            sourceId: null
        });

        updateMediaCounts();
        renderMediaGrid();

    } catch (err) {
        console.error('Upload error:', err);
        showToast('Upload error: ' + (err.message || 'Unknown error'), 'error');
    } finally {
        if (submitBtn) submitBtn.disabled = false;
        if (progress) progress.style.display = 'none';
    }
}
