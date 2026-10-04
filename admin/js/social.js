// ============================================================
// ADMIN — SOCIAL MEDIA MANAGEMENT (social.php) [IMP-027 / IMP-035]
// Manages website social profiles (GitHub, LinkedIn, X, Instagram, TikTok, Facebook).
// Depends on js/common.js (csrfToken, showToast, checkAdminAuthentication).
// ============================================================

const SUPPORTED_PLATFORMS = ['github', 'linkedin', 'x', 'instagram', 'tiktok', 'facebook'];

document.addEventListener('DOMContentLoaded', async () => {
    const authenticated = await checkAdminAuthentication();
    if (!authenticated) {
        return;
    }

    setupToggleListeners();
    setupFormSubmit();
    await loadSocialPlatforms();
});

function updateActiveCount() {
    let count = 0;
    SUPPORTED_PLATFORMS.forEach((platform) => {
        const checkbox = document.getElementById(`enable_${platform}`);
        const statusText = document.getElementById(`status_text_${platform}`);
        const card = document.querySelector(`.social-platform-card[data-platform="${platform}"]`);
        if (checkbox && checkbox.checked) {
            count++;
            if (statusText) statusText.textContent = 'Enabled';
            if (card) card.classList.add('is-active');
        } else {
            if (statusText) statusText.textContent = 'Disabled';
            if (card) card.classList.remove('is-active');
        }
    });

    const counterBadge = document.getElementById('activePlatformsCount');
    if (counterBadge) {
        counterBadge.textContent = `${count} Active`;
    }
}

function setupToggleListeners() {
    SUPPORTED_PLATFORMS.forEach((platform) => {
        const checkbox = document.getElementById(`enable_${platform}`);
        if (checkbox) {
            checkbox.addEventListener('change', () => {
                updateActiveCount();
            });
        }
    });
}

async function loadSocialPlatforms() {
    try {
        const response = await fetch('/api/social/admin_list.php', {
            method: 'GET',
            credentials: 'same-origin',
        });

        const result = await response.json();
        if (!result.success || !Array.isArray(result.data)) {
            showToast(result.message || 'Failed to load social platform data.', 'error');
            return;
        }

        result.data.forEach((item) => {
            const platform = item.platform;
            const nameInput = document.getElementById(`name_${platform}`);
            const urlInput = document.getElementById(`url_${platform}`);
            const iconSelect = document.getElementById(`icon_${platform}`);
            const sortInput = document.getElementById(`sort_${platform}`);
            const checkbox = document.getElementById(`enable_${platform}`);

            if (nameInput && item.name) {
                nameInput.value = item.name;
            }
            if (urlInput) {
                urlInput.value = item.url || '';
            }
            if (iconSelect && item.icon_key) {
                iconSelect.value = item.icon_key;
            }
            if (sortInput && item.sort_order !== undefined) {
                sortInput.value = item.sort_order;
            }
            if (checkbox) {
                checkbox.checked = Boolean(item.is_enabled);
            }
        });

        updateActiveCount();

    } catch (err) {
        console.error('❌ Error loading social platforms:', err);
        showToast('Unable to connect to server to load social channels.', 'error');
    }
}

function setupFormSubmit() {
    const form = document.getElementById('socialForm');
    const saveBtn = document.getElementById('saveSocialBtn');
    if (!form || !saveBtn) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const platformsData = [];
        let hasValidationError = false;

        SUPPORTED_PLATFORMS.forEach((platform) => {
            const nameInput = document.getElementById(`name_${platform}`);
            const urlInput = document.getElementById(`url_${platform}`);
            const iconSelect = document.getElementById(`icon_${platform}`);
            const sortInput = document.getElementById(`sort_${platform}`);
            const checkbox = document.getElementById(`enable_${platform}`);

            const url = urlInput ? urlInput.value.trim() : '';
            const isEnabled = checkbox && checkbox.checked ? 1 : 0;
            const name = nameInput ? nameInput.value.trim() : '';
            const iconKey = iconSelect ? iconSelect.value.trim() : platform;
            const sortOrder = sortInput ? parseInt(sortInput.value, 10) || 0 : 0;

            if (url !== '') {
                try {
                    const parsed = new URL(url);
                    if (parsed.protocol !== 'http:' && parsed.protocol !== 'https:') {
                        showToast(`Invalid URL for ${platform}. It must begin with http:// or https://`, 'error');
                        hasValidationError = true;
                    }
                } catch {
                    showToast(`Invalid URL format for ${platform}. Please verify the web address.`, 'error');
                    hasValidationError = true;
                }
            }

            platformsData.push({
                platform,
                name,
                icon_key: iconKey,
                url,
                sort_order: sortOrder,
                is_enabled: isEnabled,
            });
        });

        if (hasValidationError) {
            return;
        }

        saveBtn.disabled = true;
        const originalHtml = saveBtn.innerHTML;
        saveBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Saving...';

        try {
            const response = await fetch('/api/social/update.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken,
                },
                body: JSON.stringify({ platforms: platformsData }),
            });

            const result = await response.json();

            if (result.success) {
                showToast(result.message || 'Social media channels saved successfully.', 'success');
                await loadSocialPlatforms();
            } else {
                showToast(result.message || 'Failed to save changes.', 'error');
            }

        } catch (err) {
            console.error('❌ Error saving social platforms:', err);
            showToast('An error occurred while saving settings.', 'error');
        } finally {
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalHtml;
        }
    });
}
