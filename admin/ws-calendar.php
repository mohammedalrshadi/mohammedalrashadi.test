<?php
// ============================================================
// WORKSPACE: CALENDAR / EVENTS
// ============================================================

require_once dirname(__DIR__) . '/api/auth/guard.php';

requireAdminPage('login.php');

$activeNav = 'ws-calendar';
$pageTitle = 'Study & Knowledge - Calendar';

require __DIR__ . '/partials/layout_top.php';
?>

<!-- =====================================================
     HEADER
====================================================== -->
<!-- HEADER -->
<header class="pw-sub-header">
    <div class="pw-header-breadcrumbs">
        <i class="fas fa-home"></i>
        <span style="margin: 0 4px;">/</span>
        <span class="current">Calendar &amp; Events</span>
    </div>
    <div class="pw-header-actions">
        <button type="button" class="pw-btn pw-btn-primary" onclick="openEventModal()">
            <i class="fas fa-plus" aria-hidden="true"></i>
            <span>Add Event</span>
        </button>
    </div>
</header>

<div class="pw-content">
    <section style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 16px;">
        <div style="display: flex; flex-direction: column; gap: 8px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="background: var(--pw-primary-bg); color: var(--pw-primary); padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Daily Focus</span>
            </div>
            <h1 class="pw-page-title" style="margin: 0;">Schedule &amp; Time</h1>
            <p class="pw-page-subtitle" style="margin: 0;">Plan your day with clarity. Manage events, lectures, and milestones.</p>
        </div>
    </section>
    <!-- Main Column: Events List -->
    
        <div class="pw-card">
            <div class="pw-card-header" style="border-bottom: 1px solid var(--pw-border-subtle); padding-bottom: 16px; margin-bottom: 16px;">
                <h2 class="pw-card-title">Upcoming Agenda</h2>
            </div>
            
            <div class="pw-card-list" id="eventsTableBody">
                <div style="padding: 24px; text-align: center; color: var(--pw-text-muted);">
                    <i class="fas fa-spinner fa-spin" style="margin-right: 8px;"></i> Syncing calendar...
                </div>
            </div>
        </div>
</div>

<!-- EVENT MODAL -->
<div id="eventModal" class="modal" role="dialog" aria-labelledby="eventModalTitle" aria-modal="true" style="display: none;">
    <div class="modal-backdrop" onclick="closeEventModal()"></div>
    <div class="modal-dialog" style="border-radius: var(--pw-radius-md); max-width: 500px; padding: 24px;">
        <div class="modal-header">
            <h3 class="modal-title" id="eventModalTitle">Add Event</h3>
            <button type="button" class="modal-close" onclick="closeEventModal()" aria-label="Close modal">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="eventForm" onsubmit="handleEventSubmit(event)">
                <input type="hidden" id="eventId" value="0">
                
                <div class="form-group" style="margin-bottom: 16px;">
                    <label for="eventTitle" style="font-size: 13px; font-weight: 500; margin-bottom: 8px; display: block;">Event Title <span class="text-danger">*</span></label>
                    <input type="text" id="eventTitle" style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-size: 14px;" placeholder="e.g., CS101 Final Exam" required>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <div class="form-group">
                        <label for="eventDate" style="font-size: 13px; font-weight: 500; margin-bottom: 8px; display: block;">Date <span class="text-danger">*</span></label>
                        <input type="date" id="eventDate" style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-size: 14px;" required>
                    </div>
                    <div class="form-group">
                        <label for="eventTime" style="font-size: 13px; font-weight: 500; margin-bottom: 8px; display: block;">Time</label>
                        <input type="time" id="eventTime" style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-size: 14px;">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label for="eventLocation" style="font-size: 13px; font-weight: 500; margin-bottom: 8px; display: block;">Location</label>
                    <input type="text" id="eventLocation" style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-size: 14px;" placeholder="Room 404 / Zoom Link">
                </div>
                
                <div class="form-group" style="margin-bottom: 24px;">
                    <label for="eventDescription" style="font-size: 13px; font-weight: 500; margin-bottom: 8px; display: block;">Notes</label>
                    <textarea id="eventDescription" style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-size: 14px;" rows="3" placeholder="Additional details..."></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 8px;">
                    <button type="button" class="pw-btn" onclick="closeEventModal()">Cancel</button>
                    <button type="submit" class="pw-btn pw-btn-primary" id="eventSubmitBtn">Save Event</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let currentEvents = [];

document.addEventListener('DOMContentLoaded', () => {
    fetchEvents();
});

function fetchEvents() {
    // Only fetch upcoming events (from yesterday onwards) by default
    const d = new Date();
    d.setDate(d.getDate() - 1);
    const startDate = d.toISOString().split('T')[0];
    
    fetch('/api/workspace/events.php?start=' + startDate)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                currentEvents = data.data;
                renderEvents(currentEvents);
            } else {
                showToast(data.message || 'Failed to load events', 'error');
            }
        })
        .catch(e => {
            console.error('Error fetching events:', e);
            showToast('Network error fetching events', 'error');
        });
}

function renderEvents(events) {
    const tbody = document.getElementById('eventsTableBody');
    if (events.length === 0) {
        tbody.innerHTML = `
            <div style="padding: 48px 24px; text-align: center; color: var(--pw-text-muted);">
                <div style="margin-bottom: 16px;"><span class="material-symbols-outlined" style="font-size: 32px; color: var(--pw-border-hover);">event_busy</span></div>
                <div style="font-size: 14px; font-weight: 500;">No upcoming events scheduled.</div>
                <div style="font-size: 13px; margin-top: 4px;">Time to focus on deep work.</div>
            </div>
        `;
        return;
    }

    let html = '';
    
    // Group by month
    let currentMonth = '';
    
    events.forEach(ev => {
        const d = new Date(ev.event_date);
        const monthYear = d.toLocaleString('default', { month: 'long', year: 'numeric' });
        
        if (monthYear !== currentMonth) {
            currentMonth = monthYear;
            html += `
                <div class="pw-section-title" style="margin: 16px 0 8px 4px; font-size: 12px;">
                    ${monthYear}
                </div>
            `;
        }
        
        let timeStr = ev.event_time ? ev.event_time.substring(0, 5) : 'All Day';
        let isPast = new Date(ev.event_date + (ev.event_time ? 'T' + ev.event_time : 'T23:59:59')) < new Date();
        
        const opacity = isPast ? 'opacity: 0.6;' : '';

        html += `
            <div class="pw-list-item bg-lowest" style="${opacity}">
                <div style="display: flex; gap: 16px; align-items: flex-start; flex: 1;">
                    <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; min-width: 60px; padding: 6px; background: var(--pw-surface-container); border-radius: 8px;">
                        <span class="font-mono" style="font-size: 12px; font-weight: 700; color: var(--pw-text-main);">${ev.event_date.substring(5)}</span>
                        <span style="font-size: 9px; color: var(--pw-text-secondary); text-transform: uppercase; font-weight: 600; margin-top: 2px;">${timeStr}</span>
                    </div>
                    <div style="flex: 1;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                            <span style="font-size: 14px; font-weight: 600; color: var(--pw-text-main);">${escapeHtml(ev.title)}</span>
                        </div>
                        ${ev.description ? `<p style="font-size: 12px; color: var(--pw-text-muted); margin: 0 0 4px 0;">${escapeHtml(ev.description)}</p>` : ''}
                        ${ev.location ? `<div style="font-size: 11px; color: var(--pw-text-secondary); display: flex; align-items: center; gap: 4px;"><span class="material-symbols-outlined" style="font-size: 12px;">location_on</span> ${escapeHtml(ev.location)}</div>` : ''}
                    </div>
                </div>
                <div style="display: flex; gap: 4px; align-items: center;">
                    <button type="button" class="pw-btn" style="padding: 4px 8px;" onclick="editEvent(${ev.id})" title="Edit">
                        <span class="material-symbols-outlined" style="font-size: 16px;">edit</span>
                    </button>
                    <button type="button" class="pw-btn text-danger" style="padding: 4px 8px; border-color: transparent; background: transparent;" onclick="deleteEvent(${ev.id})" title="Delete">
                        <span class="material-symbols-outlined" style="font-size: 16px;">delete</span>
                    </button>
                </div>
            </div>
        `;
    });
    
    tbody.innerHTML = html;
}

function openEventModal(event = null) {
    const modal = document.getElementById('eventModal');
    const form = document.getElementById('eventForm');
    
    if (event) {
        document.getElementById('eventModalTitle').textContent = 'Edit Event';
        document.getElementById('eventId').value = event.id;
        document.getElementById('eventTitle').value = event.title;
        document.getElementById('eventDate').value = event.event_date;
        document.getElementById('eventTime').value = event.event_time ? event.event_time.substring(0, 5) : '';
        document.getElementById('eventLocation').value = event.location || '';
        document.getElementById('eventDescription').value = event.description || '';
    } else {
        document.getElementById('eventModalTitle').textContent = 'Add Event';
        form.reset();
        document.getElementById('eventId').value = '0';
        
        // Default to today
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('eventDate').value = today;
    }
    
    modal.style.display = 'block';
    document.getElementById('eventTitle').focus();
}

function closeEventModal() {
    document.getElementById('eventModal').style.display = 'none';
}

function editEvent(id) {
    const ev = currentEvents.find(e => e.id === id);
    if (ev) {
        openEventModal(ev);
    }
}

function handleEventSubmit(e) {
    e.preventDefault();
    const btn = document.getElementById('eventSubmitBtn');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    btn.disabled = true;

    const id = parseInt(document.getElementById('eventId').value, 10);
    const payload = {
        action: id > 0 ? 'update_event' : 'create_event',
        id: id,
        title: document.getElementById('eventTitle').value,
        event_date: document.getElementById('eventDate').value,
        event_time: document.getElementById('eventTime').value,
        location: document.getElementById('eventLocation').value,
        description: document.getElementById('eventDescription').value,
    };

    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    fetch('/api/workspace/events.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        
        if (data.success) {
            showToast(data.message, 'success');
            closeEventModal();
            fetchEvents();
        } else {
            showToast(data.message || 'Failed to save event', 'error');
        }
    })
    .catch(err => {
        console.error(err);
        btn.innerHTML = originalText;
        btn.disabled = false;
        showToast('Network error saving event', 'error');
    });
}

function deleteEvent(id) {
    if (!confirm('Are you sure you want to delete this event?')) return;
    
    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    
    fetch('/api/workspace/events.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token },
        body: JSON.stringify({ action: 'delete_event', id: id })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Event deleted successfully.', 'success');
            fetchEvents();
        } else {
            showToast(data.message || 'Failed to delete event', 'error');
        }
    })
    .catch(e => {
        console.error(e);
        showToast('Network error deleting event', 'error');
    });
}

function escapeHtml(unsafe) {
    if (!unsafe) return '';
    return (unsafe+'').replace(/[&<"']/g, function(m) {
        switch (m) {
            case '&': return '&amp;';
            case '<': return '&lt;';
            case '"': return '&quot;';
            case "'": return '&#039;';
        }
    });
}
</script>

<?php
require __DIR__ . '/partials/layout_bottom.php';
?>
