document.addEventListener('DOMContentLoaded', () => {
    const achieveId = document.getElementById('achieve_id').value;
    const form = document.getElementById('achieveForm');
    const saveBtn = document.getElementById('saveAchieveBtn');
    
    // Auto-slug generator with Arabic support
    const titleInput = document.getElementById('achieve_title');
    const slugInput = document.getElementById('achieve_slug');
    
    // Simple arabic transliteration map for slugs
    const arMap = {
        'ا':'a','أ':'a','إ':'e','آ':'a','ب':'b','ت':'t','ث':'th','ج':'j','ح':'h','خ':'kh','د':'d','ذ':'dz','ر':'r','ز':'z','س':'s','ش':'sh','ص':'s','ض':'d','ط':'t','ظ':'z','ع':'a','غ':'gh','ف':'f','ق':'q','ك':'k','ل':'l','م':'m','ن':'n','ه':'h','و':'w','ي':'y','ى':'a','ة':'h','ؤ':'o','ئ':'e','ء':'a'
    };

    titleInput.addEventListener('input', function() {
        if (!achieveId) { // Only auto-slug for new ones
            let val = this.value;
            let slug = val.split('').map(c => arMap[c] || c).join('')
                          .toLowerCase()
                          .replace(/[^a-z0-9]+/g, '-')
                          .replace(/(^-|-$)+/g, '');
            if (!slug && val.trim() !== '') {
                slug = 'achievement-' + Date.now();
            }
            slugInput.value = slug;
        }
    });

    if (achieveId) {
        // Load data for edit using get.php
        fetch(`/api/achievements/get.php?id=${achieveId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success && data.achievement) {
                    const item = data.achievement;
                    document.getElementById('achieve_title').value = item.title;
                    document.getElementById('achieve_slug').value = item.slug;
                    document.getElementById('achieve_organization').value = item.organization || '';
                    document.getElementById('achieve_date').value = item.date_awarded || '';
                    document.getElementById('achieve_category').value = item.category || '';
                    document.getElementById('achieve_description').value = item.description || '';
                    document.getElementById('achieve_image_url').value = item.image_url || '';
                    document.getElementById('achieve_url').value = item.url || '';
                    document.getElementById('achieve_status').value = item.status;
                    
                    if (item.image_url) {
                        setMainImagePreview(item.image_url);
                    }
                    
                    loadGallery();
                    
                    if (typeof UnsavedChangesGuard !== 'undefined') {
                        UnsavedChangesGuard.init({
                            getSnapshot: getAchievementSnapshot,
                            initialBaseline: getAchievementSnapshot()
                        });
                    }
                }
            })
            .catch(err => console.error(err));
    }

    
    function getAchievementSnapshot() {
        return {
            title: document.getElementById('achieve_title')?.value || '',
            slug: document.getElementById('achieve_slug')?.value || '',
            organization: document.getElementById('achieve_organization')?.value || '',
            date_awarded: document.getElementById('achieve_date')?.value || '',
            category: document.getElementById('achieve_category')?.value || '',
            description: document.getElementById('achieve_description')?.value || '',
            image_url: document.getElementById('achieve_image_url')?.value || '',
            url: document.getElementById('achieve_url')?.value || '',
            status: document.getElementById('achieve_status')?.value || ''
        };
    }



    // MAIN IMAGE UPLOAD HANDLING
    let setMainImagePreview = () => {};
    try {
        const dropzone = document.getElementById('mainImageDropzone');
        const fileInput = document.getElementById('mainImageInput');
        const previewContainer = document.getElementById('mainImagePreview');
        const placeholder = document.getElementById('mainImagePlaceholder');
        const removeBtn = document.getElementById('mainImageRemoveBtn');
        const loading = document.getElementById('mainImageLoading');
        const urlInput = document.getElementById('achieve_image_url');
        
        const previewImg = previewContainer.querySelector('img');
        previewImg.onerror = () => previewImg.src = '/assets/images/placeholder.png';
        
        urlInput.addEventListener('input', () => {
            if (urlInput.value.trim()) {
                setMainImagePreview(urlInput.value.trim());
            } else {
                previewContainer.style.display = 'none';
                placeholder.style.display = 'block';
            }
        });

        function openMainImagePicker(e) {
            if (e.target !== fileInput && e.target !== removeBtn && !removeBtn.contains(e.target)) {
                fileInput.click();
            }
        }
        
        dropzone.addEventListener('click', openMainImagePicker);
        dropzone.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                openMainImagePicker(e);
            }
        });

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(ev => {
            dropzone.addEventListener(ev, (e) => {
                e.preventDefault();
                e.stopPropagation();
            }, false);
        });

        ['dragenter', 'dragover'].forEach(ev => {
            dropzone.addEventListener(ev, () => dropzone.style.borderColor = 'var(--accent)', false);
        });
        ['dragleave', 'drop'].forEach(ev => {
            dropzone.addEventListener(ev, () => dropzone.style.borderColor = 'var(--border-color)', false);
        });

        dropzone.addEventListener('drop', (e) => {
            if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                handleFiles(e.dataTransfer.files);
            }
        });

        fileInput.addEventListener('change', () => {
            if (fileInput.files && fileInput.files.length > 0) {
                handleFiles(fileInput.files);
            }
        });
        
        async function handleFiles(files) {
            const file = files[0];
            fileInput.value = ''; // Reset input
            
            if (file.size > 20 * 1024 * 1024) {
                if(window.showToast) showToast('File exceeds 20MB', 'error');
                else alert('File exceeds 20MB');
                return;
            }
            
            const localUrl = URL.createObjectURL(file);
            setMainImagePreview(localUrl);
            
            placeholder.style.display = 'none';
            if (loading) loading.style.display = 'block';
            saveBtn.disabled = true;
            
            const formData = new FormData();
            formData.append('image', file);
            
            try {
                const res = await fetch('/api/uploads/image.php', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json'
                    },
                    body: formData
                });
                const data = await res.json();
                
                if (data.success) {
                    urlInput.value = data.url;
                } else {
                    throw new Error(data.message || 'Upload failed');
                }
            } catch (err) {
                console.error('[Main Image Upload]', err);
                if(window.showToast) showToast(err.message, 'error');
                
                if (urlInput.value.trim()) {
                    setMainImagePreview(urlInput.value.trim());
                } else {
                    previewContainer.style.display = 'none';
                    placeholder.style.display = 'block';
                }
            } finally {
                if (loading) loading.style.display = 'none';
                saveBtn.disabled = false;
                URL.revokeObjectURL(localUrl);
            }
        }

        setMainImagePreview = function(url) {
            previewContainer.style.display = 'block';
            placeholder.style.display = 'none';
            if (loading) loading.style.display = 'none';
            previewImg.src = url;
        };

        removeBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            fileInput.value = '';
            urlInput.value = '';
            previewContainer.style.display = 'none';
            placeholder.style.display = 'block';
            previewImg.src = '';
        });
        
    } catch (err) {
        console.error('[Main Image Setup]', err);
    }

    // SUBMIT
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        document.getElementById('title_error').style.display = 'none';
        document.getElementById('slug_error').style.display = 'none';
        
        const title = document.getElementById('achieve_title').value.trim();
        const slug = document.getElementById('achieve_slug').value.trim();
        
        let hasErr = false;
        if (!title) { document.getElementById('title_error').innerText = "Required"; document.getElementById('title_error').style.display='block'; hasErr=true; }
        if (!slug) { document.getElementById('slug_error').innerText = "Required"; document.getElementById('slug_error').style.display='block'; hasErr=true; }
        if (hasErr) return;

        saveBtn.disabled = true;
        const originalText = saveBtn.innerText;
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        
        let finalImageUrl = document.getElementById('achieve_image_url').value;
        
        try {
            const payload = {
                title: title,
                slug: slug,
                organization: document.getElementById('achieve_organization').value,
                date_awarded: document.getElementById('achieve_date').value,
                category: document.getElementById('achieve_category').value,
                description: document.getElementById('achieve_description').value,
                image_url: finalImageUrl,
                url: document.getElementById('achieve_url').value,
                status: document.getElementById('achieve_status').value
            };

            let endpoint = '/api/achievements/create.php';
            if (achieveId) {
                endpoint = '/api/achievements/update.php';
                payload.id = achieveId;
            }

            const res = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            
            if (data.success) {
                if (typeof UnsavedChangesGuard !== 'undefined') {
                    UnsavedChangesGuard.markClean();
                }
                if(window.showToast) showToast(data.message, 'success');
                setTimeout(() => {
                    window.location.href = '/admin/achievements.php';
                }, 1000);
            } else {
                throw new Error(data.message || 'Error saving achievement');
            }
        } catch (err) {
            console.error(err);
            if(window.showToast) showToast(err.message, 'error');
            else alert(err.message);
            
            saveBtn.disabled = false;
            saveBtn.innerText = originalText;
        }
    });

    // GALLERY HANDLING
    if (achieveId) {
        try {
            const galDrop = document.getElementById('galleryDropzone');
            const galInput = document.getElementById('galleryInput');
            const galLoading = document.getElementById('galleryLoading');
            const galPlaceholder = document.getElementById('galleryPlaceholder');
            
            function openGalleryPicker(e) {
                if (e.target !== galInput) {
                    galInput.click();
                }
            }

            galDrop.addEventListener('click', openGalleryPicker);
            galDrop.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    openGalleryPicker(e);
                }
            });
            
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(ev => {
                galDrop.addEventListener(ev, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                }, false);
            });
            
            ['dragenter', 'dragover'].forEach(ev => {
                galDrop.addEventListener(ev, () => galDrop.style.borderColor = 'var(--accent)', false);
            });
            ['dragleave', 'drop'].forEach(ev => {
                galDrop.addEventListener(ev, () => galDrop.style.borderColor = 'var(--border-color)', false);
            });

            galDrop.addEventListener('drop', (e) => {
                if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                    uploadGalleryImages(e.dataTransfer.files);
                }
            });
            
            galInput.addEventListener('change', () => {
                if (galInput.files && galInput.files.length > 0) {
                    uploadGalleryImages(galInput.files);
                }
            });
            
            async function uploadGalleryImages(files) {
                galLoading.style.display = 'block';
                galPlaceholder.style.display = 'none';
                saveBtn.disabled = true;

                for (let i = 0; i < files.length; i++) {
                    const file = files[i];
                    if (file.size > 20 * 1024 * 1024) {
                        if(window.showToast) showToast(`File ${file.name} exceeds 20MB`, 'error');
                        continue;
                    }
                    
                    const formData = new FormData();
                    formData.append('image', file);
                    formData.append('achievement_id', achieveId);
                    
                    try {
                        const res = await fetch('/api/achievements/gallery_add.php', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || '',
                                'Accept': 'application/json'
                            },
                            body: formData
                        });
                        const data = await res.json();
                        
                        if (!data.success) {
                            throw new Error(data.message || `Upload failed for ${file.name}`);
                        }
                    } catch (err) {
                        console.error('[Gallery Upload]', err);
                        if(window.showToast) showToast(err.message, 'error');
                    }
                }

                galInput.value = '';
                galLoading.style.display = 'none';
                galPlaceholder.style.display = 'block';
                saveBtn.disabled = false;
                
                loadGallery();
                if(window.showToast) showToast('Gallery updated', 'success');
            }
        } catch (err) {
            console.error('[Gallery Setup]', err);
        }
    }

    try {
        if (typeof UnsavedChangesGuard !== 'undefined') {
            if (!achieveId) {
                UnsavedChangesGuard.init({
                    getSnapshot: getAchievementSnapshot
                });
            }
        }
    } catch (err) {
        console.error('[UnsavedChangesGuard Setup]', err);
    }
});

// Prevent document drops from opening the file
window.addEventListener('dragover', (e) => { e.preventDefault(); e.stopPropagation(); }, false);
window.addEventListener('drop', (e) => { e.preventDefault(); e.stopPropagation(); }, false);

function loadGallery() {
    const achieveId = document.getElementById('achieve_id').value;
    if (!achieveId) return;
    
    fetch(`/api/achievements/gallery_list.php?achievement_id=${achieveId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                renderGallery(data.images);
            }
        });
}

function renderGallery(images) {
    const grid = document.getElementById('galleryGrid');
    if (!grid) return;
    grid.innerHTML = '';
    
    images.forEach(img => {
        const div = document.createElement('div');
        div.className = 'gallery-item';
        div.dataset.id = img.id;
        div.style.position = 'relative';
        div.style.border = '1px solid var(--border-color)';
        div.style.borderRadius = '4px';
        div.style.overflow = 'hidden';
        div.style.backgroundColor = 'var(--bg-alt)';
        div.style.cursor = 'grab';
        
        const imgEl = document.createElement('img');
        imgEl.src = img.image_url;
        imgEl.style.width = '100%';
        imgEl.style.height = '120px';
        imgEl.style.objectFit = 'contain';
        imgEl.style.display = 'block';
        imgEl.onerror = () => imgEl.src = '/assets/images/placeholder.png';
        
        const delBtn = document.createElement('button');
        delBtn.type = 'button';
        delBtn.className = 'btn btn-danger btn-sm';
        delBtn.innerHTML = '<i class="fas fa-trash"></i>';
        delBtn.style.position = 'absolute';
        delBtn.style.top = '4px';
        delBtn.style.right = '4px';
        delBtn.style.padding = '4px';
        delBtn.onclick = () => deleteGalleryImage(img.id);
        
        div.appendChild(imgEl);
        div.appendChild(delBtn);
        grid.appendChild(div);
    });
    
    if (window.Sortable && grid.children.length > 0) {
        new Sortable(grid, {
            animation: 150,
            onEnd: function() {
                const order = [];
                grid.querySelectorAll('.gallery-item').forEach((item, index) => {
                    order.push({ id: item.dataset.id, sort_order: index });
                });
                
                fetch('/api/achievements/gallery_reorder.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    body: JSON.stringify({ order: order })
                }).catch(err => console.error(err));
            }
        });
    }
}

function deleteGalleryImage(id) {
    if (!confirm('Delete this gallery image?')) return;
    
    fetch('/api/achievements/gallery_delete.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
        },
        body: JSON.stringify({ id: id })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            if(window.showToast) showToast('Image deleted', 'success');
            loadGallery();
        } else {
            if(window.showToast) showToast(data.message, 'error');
        }
    })
    .catch(err => console.error(err));
}
