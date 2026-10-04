let currentDeleteId = null;
let currentAction = 'trash'; // 'trash' (soft delete) or 'purge' (permanent, trash view only)
let currentStatus = 'all';
let currentSearch = '';
let currentOffset = 0;
const LIMIT = 20;
let hasMore = true;

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.filter-tab').forEach(tab => {
        tab.addEventListener('click', (e) => {
            document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
            e.target.classList.add('active');
            currentStatus = e.target.getAttribute('data-status');
            currentOffset = 0;
            loadAchievements(true);
        });
    });

    let searchTimeout;
    const searchInput = document.getElementById('achieveSearch');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                currentSearch = e.target.value.trim();
                currentOffset = 0;
                loadAchievements(true);
            }, 300);
        });
    }

    const loadMoreBtn = document.getElementById('loadMoreBtn');
    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', () => {
            if (hasMore) {
                currentOffset += LIMIT;
                loadAchievements(false);
            }
        });
    }

    document.getElementById('closeDeleteModalBtn').addEventListener('click', closeDeleteModal);
    document.getElementById('cancelDeleteModalBtn').addEventListener('click', closeDeleteModal);
    document.getElementById('confirmDeleteAchieveBtn').addEventListener('click', executeDeleteAchieve);

    loadAchievements(true);
});

function loadAchievements(reset = false) {
    const tbody = document.getElementById('achievementsTableBody');
    const loadMoreBtn = document.getElementById('loadMoreBtn');
    
    if (reset) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center;">Loading...</td></tr>';
        if(loadMoreBtn) loadMoreBtn.style.display = 'none';
    } else {
        const tr = document.createElement('tr');
        tr.id = 'loadingMoreRow';
        tr.innerHTML = '<td colspan="6" style="text-align: center;"><i class="fas fa-spinner fa-spin"></i> Loading more...</td>';
        tbody.appendChild(tr);
    }
    
    const inTrash = currentStatus === 'trash';
    const listUrl = inTrash
        ? `/api/achievements/trash.php?limit=${LIMIT}&offset=${currentOffset}&search=${encodeURIComponent(currentSearch)}`
        : `/api/achievements/list.php?status=${encodeURIComponent(currentStatus)}&limit=${LIMIT}&offset=${currentOffset}&search=${encodeURIComponent(currentSearch)}`;
    fetch(listUrl)
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                if(reset) tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: red;">Error: ${escapeHTML(data.message)}</td></tr>`;
                return;
            }
            
            if (reset) tbody.innerHTML = '';
            const loadingRow = document.getElementById('loadingMoreRow');
            if(loadingRow) loadingRow.remove();

            if (data.data.length === 0 && reset) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align: center;">' + (inTrash ? 'Trash is empty.' : 'No achievements found.') + '</td></tr>';
                hasMore = false;
                if(loadMoreBtn) loadMoreBtn.style.display = 'none';
                return;
            }
            
            data.data.forEach(item => {
                const tr = document.createElement('tr');
                
                const imgCell = document.createElement('td');
                if (item.image_url) {
                    const img = document.createElement('img');
                    img.src = item.image_url;
                    img.style = "width: 40px; height: 40px; object-fit: contain; border-radius: 4px; background: var(--bg-alt);";
                    img.onerror = () => { img.src = '/assets/images/placeholder.png'; };
                    imgCell.appendChild(img);
                }
                tr.appendChild(imgCell);
                
                const titleCell = document.createElement('td');
                const titleStrong = document.createElement('strong');
                titleStrong.textContent = item.title;
                const slugSmall = document.createElement('small');
                slugSmall.style.color = "var(--text-muted)";
                slugSmall.style.display = "block";
                slugSmall.textContent = item.slug;
                titleCell.appendChild(titleStrong);
                titleCell.appendChild(slugSmall);
                tr.appendChild(titleCell);
                
                const orgCell = document.createElement('td');
                orgCell.textContent = item.organization || '-';
                tr.appendChild(orgCell);
                
                const dateCell = document.createElement('td');
                dateCell.textContent = item.date_awarded || '-';
                tr.appendChild(dateCell);
                
                const statusCell = document.createElement('td');
                const badge = document.createElement('span');
                badge.className = 'status-badge';
                if (inTrash) {
                    badge.style = "background: var(--text-muted); color: #fff;";
                    badge.textContent = "Trashed";
                } else if (item.status === 'published') {
                    badge.style = "background: var(--success); color: #fff;";
                    badge.textContent = "Published";
                } else {
                    badge.style = "background: var(--warning); color: #fff;";
                    badge.textContent = "Hidden";
                }
                statusCell.appendChild(badge);
                tr.appendChild(statusCell);
                
                const actionsCell = document.createElement('td');
                actionsCell.style.textAlign = 'right';
                
                if (inTrash) {
                    const restoreBtn = document.createElement('button');
                    restoreBtn.type = "button";
                    restoreBtn.className = "btn btn-secondary btn-sm";
                    restoreBtn.style = "padding: 4px 8px; margin-right: 4px;";
                    restoreBtn.title = "Restore";
                    restoreBtn.innerHTML = '<i class="fas fa-undo"></i>';
                    restoreBtn.dataset.id = item.id;
                    restoreBtn.addEventListener('click', (e) => restoreAchievement(e.currentTarget.dataset.id));

                    const purgeBtn = document.createElement('button');
                    purgeBtn.type = "button";
                    purgeBtn.className = "btn btn-danger btn-sm";
                    purgeBtn.style = "padding: 4px 8px;";
                    purgeBtn.title = "Delete permanently";
                    purgeBtn.innerHTML = '<i class="fas fa-times"></i>';
                    purgeBtn.dataset.id = item.id;
                    purgeBtn.dataset.title = item.title;
                    purgeBtn.addEventListener('click', (e) => {
                        const btn = e.currentTarget;
                        promptDelete(btn.dataset.id, btn.dataset.title, 'purge');
                    });

                    actionsCell.appendChild(restoreBtn);
                    actionsCell.appendChild(purgeBtn);
                    tr.appendChild(actionsCell);
                    tbody.appendChild(tr);
                    return;
                }

                const editBtn = document.createElement('a');
                editBtn.href = `achievements-edit.php?id=${item.id}`;
                editBtn.className = "btn btn-secondary btn-sm";
                editBtn.style = "padding: 4px 8px; margin-right: 4px;";
                editBtn.innerHTML = '<i class="fas fa-edit"></i>';
                
                const deleteBtn = document.createElement('button');
                deleteBtn.type = "button";
                deleteBtn.className = "btn btn-danger btn-sm";
                deleteBtn.style = "padding: 4px 8px;";
                deleteBtn.innerHTML = '<i class="fas fa-trash"></i>';
                deleteBtn.dataset.id = item.id;
                deleteBtn.dataset.title = item.title;
                deleteBtn.addEventListener('click', (e) => {
                    const btn = e.currentTarget;
                    promptDelete(btn.dataset.id, btn.dataset.title);
                });
                
                actionsCell.appendChild(editBtn);
                actionsCell.appendChild(deleteBtn);
                tr.appendChild(actionsCell);
                
                tbody.appendChild(tr);
            });
            
            hasMore = (currentOffset + LIMIT < data.total);
            if(loadMoreBtn) loadMoreBtn.style.display = hasMore ? 'inline-block' : 'none';
        })
        .catch(err => {
            if(reset) tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: red;">Network Error</td></tr>`;
        });
}

function promptDelete(id, title, action = 'trash') {
    currentDeleteId = id;
    currentAction = action;
    const purge = action === 'purge';
    document.getElementById('deleteAchieveModalTitle').textContent = purge ? 'Delete Permanently' : 'Move to Trash';
    document.getElementById('deleteAchieveLead').textContent = purge ? 'Permanently delete' : 'Move';
    document.getElementById('deleteAchieveTail').textContent = purge
        ? '? Its record and all its images will be removed. This cannot be undone.'
        : 'to the trash? You can restore it later from the Trash tab.';
    document.getElementById('confirmDeleteAchieveBtn').textContent = purge ? 'Delete Permanently' : 'Move to Trash';
    document.getElementById('deleteAchieveTitle').textContent = title;
    document.getElementById('deleteAchieveModal').style.display = 'flex';
}

function closeDeleteModal() {
    currentDeleteId = null;
    document.getElementById('deleteAchieveModal').style.display = 'none';
}

function executeDeleteAchieve() {
    if (!currentDeleteId) return;
    
    const endpoint = currentAction === 'purge' ? '/api/achievements/purge.php' : '/api/achievements/delete.php';
    fetch(endpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
        },
        body: JSON.stringify({ id: currentDeleteId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            closeDeleteModal();
            if(window.showToast) showToast(currentAction === 'purge' ? 'Achievement permanently deleted' : 'Achievement moved to trash', 'success');
            currentOffset = 0;
            loadAchievements(true);
        } else {
            if(window.showToast) showToast(data.message || 'Error deleting achievement', 'error');
            else alert(data.message);
        }
    })
    .catch(err => {
        if(window.showToast) showToast('Network error', 'error');
        else alert('Network error');
    });
}

function restoreAchievement(id) {
    fetch('/api/achievements/restore.php', {
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
            if(window.showToast) showToast('Achievement restored', 'success');
            currentOffset = 0;
            loadAchievements(true);
        } else {
            if(window.showToast) showToast(data.message || 'Error restoring achievement', 'error');
            else alert(data.message);
        }
    })
    .catch(() => {
        if(window.showToast) showToast('Network error', 'error');
        else alert('Network error');
    });
}

function escapeHTML(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
