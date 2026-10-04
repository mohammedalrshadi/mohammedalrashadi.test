/**
 * ============================================================
 * ADMIN — SHARED EDITOR LOGIC
 * admin/js/shared-editor.js
 *
 * Shared utilities for both Articles and Projects editors.
 * Features:
 *   - Common image upload FormData lifecycle
 *   - Post-update status transition logic (publish / hide endpoints)
 * ============================================================
 */

const EditorUtils = {
    /**
     * Uploads an image to the server or returns the existing relative path.
     * @param {HTMLInputElement} imageInput 
     * @param {HTMLImageElement} existingImg 
     * @returns {Promise<string>} The uploaded URL or the existing image path
     */
    async uploadImageOrGetExisting(imageInput, existingImg) {
        if (imageInput && imageInput.files && imageInput.files[0]) {
            const file = imageInput.files[0];
            const safeName = 'upload_' + Date.now() + '_' + file.name.replace(/[^a-zA-Z0-9.\-_]/g, '_');
            const imgFormData = new FormData();
            imgFormData.append('image', file, safeName);
            
            const imgRes = await fetch('/api/uploads/image.php', {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': getCsrfTokenSafe(),
                    'Accept': 'application/json'
                },
                body: imgFormData
            });
            const imgData = await imgRes.json();
            if (!imgRes.ok || !imgData.success) {
                throw new Error(imgData.message || 'Failed to upload image.');
            }
            return imgData.url;
        } else {
            if (existingImg && existingImg.src && !existingImg.src.startsWith('blob:')) {
                return new URL(existingImg.src).pathname;
            }
            return '';
        }
    },

    /**
     * Handles explicit state transitions after a successful normal update.
     * Checks if the desired status differs from the original, and calls
     * the publish or hide API accordingly.
     * @param {string|number} savedId 
     * @param {string} currentStatus The status requested by the user
     * @param {string} originalStatus The status the post had when the editor loaded
     * @returns {Promise<void>}
     */
    async handleStatusTransition(savedId, currentStatus, originalStatus) {
        if (currentStatus !== originalStatus) {
            if (currentStatus === 'published') {
                await fetch('/api/posts/publish.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfTokenSafe() },
                    body: JSON.stringify({ id: parseInt(savedId, 10) })
                });
            } else if (currentStatus === 'draft' || currentStatus === 'hidden') {
                await fetch('/api/posts/hide.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfTokenSafe() },
                    body: JSON.stringify({ id: parseInt(savedId, 10) })
                });
            }
        }
    }
};
