/* ═══════════════════════════════════════════════════════════════
   Rehletna — Main JavaScript
   Handles: Likes, Comments, Search, Toast, Validation, Chat, Menus
   ═══════════════════════════════════════════════════════════════ */

// ── Toast Notification System ─────────────────────────────────
function showToast(message, type = 'error') {
    // Remove existing toast if any
    const existing = document.querySelector('.toast-notification');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.className = `toast-notification toast-${type}`;
    toast.innerHTML = `
        <span class="toast-icon">${type === 'error' ? '⚠️' : type === 'success' ? '✅' : 'ℹ️'}</span>
        <span class="toast-message">${message}</span>
        <button class="toast-close" onclick="this.parentElement.remove()">✕</button>
    `;
    document.body.appendChild(toast);

    // Trigger animation
    requestAnimationFrame(() => toast.classList.add('toast-visible'));

    // Auto-dismiss after 4 seconds
    setTimeout(() => {
        toast.classList.remove('toast-visible');
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

// ── Custom Form Validation ────────────────────────────────────
function initFormValidation() {
    document.querySelectorAll('form').forEach(form => {
        // Skip search forms
        if (form.classList.contains('search-form')) return;
        if (form.dataset.validated) return; // prevent double-binding
        form.dataset.validated = 'true';

        form.setAttribute('novalidate', '');

        // Use capture phase to run BEFORE Turbo's submit handler
        form.addEventListener('submit', function(e) {
            const fields = form.querySelectorAll('[required], [minlength], [maxlength], [pattern]');
            let hasError = false;

            fields.forEach(field => {
                field.classList.remove('field-error');
            });

            for (const field of fields) {
                const label = field.closest('.cpd-field-row')?.querySelector('.cpd-field-icon')?.textContent?.trim()
                    || field.getAttribute('placeholder')
                    || field.getAttribute('name')
                    || 'This field';

                // Required check
                if (field.hasAttribute('required') && !field.value.trim()) {
                    field.classList.add('field-error');
                    if (!hasError) {
                        showToast(`${label} cannot be empty. Please fill it in.`);
                        field.focus();
                        field.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    hasError = true;
                    break;
                }

                // Min length check
                const minLen = field.getAttribute('minlength');
                if (minLen && field.value.trim().length < parseInt(minLen)) {
                    field.classList.add('field-error');
                    if (!hasError) {
                        showToast(`${label} must be at least ${minLen} characters long.`);
                        field.focus();
                    }
                    hasError = true;
                    break;
                }

                // Max length check
                const maxLen = field.getAttribute('maxlength');
                if (maxLen && field.value.trim().length > parseInt(maxLen)) {
                    field.classList.add('field-error');
                    if (!hasError) {
                        showToast(`${label} cannot exceed ${maxLen} characters.`);
                        field.focus();
                    }
                    hasError = true;
                    break;
                }

                // Pattern check
                const pattern = field.getAttribute('pattern');
                if (pattern && field.value.trim() && !new RegExp(pattern).test(field.value.trim())) {
                    field.classList.add('field-error');
                    if (!hasError) {
                        showToast(`${label} format is invalid. Please check your input.`);
                        field.focus();
                    }
                    hasError = true;
                    break;
                }
            }

            if (hasError) {
                e.preventDefault();
                e.stopImmediatePropagation(); // Kill Turbo's fetch cycle
            }
        }, true); // true = capture phase, runs before Turbo
    });
}
document.addEventListener('DOMContentLoaded', initFormValidation);
document.addEventListener('turbo:load', initFormValidation);

// ── Like Toggle (AJAX) ───────────────────────────────────────
function toggleLike(publicationId) {
    const btn = document.getElementById('likeBtn-' + publicationId);
    if (!btn) return;

    // Optimistic animation
    btn.style.transform = 'scale(1.2)';
    setTimeout(() => btn.style.transform = '', 200);

    const formData = new FormData();
    formData.append('publication_id', publicationId);

    fetch('/api/like/toggle', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            btn.textContent = data.liked ? '❤️ Liked' : '🤍 Like';
            btn.classList.toggle('liked', data.liked);

            // Update stats counter(s) on page
            document.querySelectorAll('#likeStats-' + publicationId).forEach(el => {
                el.textContent = data.likeCount > 0
                    ? data.likeCount + ' like' + (data.likeCount > 1 ? 's' : '')
                    : '';
            });
        })
        .catch(err => console.error('Like toggle failed:', err));
}

// ── Comment Submit (AJAX) ────────────────────────────────────
function submitComment(publicationId) {
    const input = document.getElementById('commentInput');
    if (!input) return;

    const content = input.value.trim();
    if (!content) return;

    const formData = new FormData();
    formData.append('publication_id', publicationId);
    formData.append('content', content);

    fetch('/api/comment', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.error) { alert(data.error); return; }

            input.value = '';

            // Remove "no comments" placeholder
            const noComments = document.querySelector('.no-comments');
            if (noComments) noComments.remove();

            // Build new comment element
            const commentHtml = `
                <div class="comment-item" id="comment-${data.id}">
                    <div class="comment-top-row">
                        <div class="avatar avatar-sm">${(data.username || 'U')[0].toUpperCase()}</div>
                        <div class="comment-body">
                            <div class="comment-meta-row">
                                <span class="comment-username">${data.username}</span>
                                <span class="comment-time">${data.timeAgo}</span>
                            </div>
                            <p class="comment-content" id="commentContent-${data.id}">${data.content}</p>
                        </div>
                    </div>
                    <div class="comment-owner-actions">
                        <button class="comment-action-edit" onclick="startEditComment(${data.id})">Edit</button>
                        <span class="comment-action-dot">·</span>
                        <button class="comment-action-delete" onclick="deleteComment(${data.id})">Delete</button>
                    </div>
                </div>`;

            const list = document.getElementById('commentsList');
            list.insertAdjacentHTML('beforeend', commentHtml);

            // Update count pill
            const pill = document.getElementById('commentCountPill');
            if (pill) pill.textContent = data.commentCount;

            // Update stats
            const stats = document.getElementById('commentStats-' + publicationId);
            if (stats) {
                stats.textContent = data.commentCount + ' comment' + (data.commentCount > 1 ? 's' : '');
            }

            // Scroll to bottom
            list.scrollTop = list.scrollHeight;
        })
        .catch(err => console.error('Add comment failed:', err));
}

// ── Start Edit Comment ───────────────────────────────────────
function startEditComment(commentId) {
    const contentEl = document.getElementById('commentContent-' + commentId);
    if (!contentEl) return;

    const original = contentEl.textContent;
    contentEl.innerHTML = `
        <input type="text" class="edit-comment-input" value="${original.replace(/"/g, '&quot;')}" 
               onkeydown="if(event.key==='Enter')saveEditComment(${commentId}); if(event.key==='Escape')cancelEditComment(${commentId},'${original.replace(/'/g, "\\'")}')"
               style="width:100%;padding:6px 10px;border-radius:8px;border:1px solid var(--border);
                      background:var(--bg-input);font-size:13px;font-family:var(--font);color:var(--text-primary);">`;

    contentEl.querySelector('input').focus();
}

function saveEditComment(commentId) {
    const input = document.querySelector('#commentContent-' + commentId + ' input');
    if (!input) return;

    const content = input.value.trim();
    if (!content) return;

    fetch('/api/comment/' + commentId, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ content: content }),
    })
        .then(r => r.json())
        .then(data => {
            const el = document.getElementById('commentContent-' + commentId);
            el.textContent = data.content;
        })
        .catch(err => console.error('Edit comment failed:', err));
}

function cancelEditComment(commentId, original) {
    const el = document.getElementById('commentContent-' + commentId);
    if (el) el.textContent = original;
}

// ── Delete Comment ───────────────────────────────────────────
function deleteComment(commentId) {
    if (!confirm('Delete this comment?')) return;

    fetch('/api/comment/' + commentId, { method: 'DELETE' })
        .then(r => r.json())
        .then(data => {
            const el = document.getElementById('comment-' + commentId);
            if (el) {
                el.style.opacity = '0';
                el.style.transform = 'translateX(20px)';
                setTimeout(() => el.remove(), 300);
            }

            const pill = document.getElementById('commentCountPill');
            if (pill) pill.textContent = data.commentCount;
        })
        .catch(err => console.error('Delete comment failed:', err));
}

// ── Post Menu Toggle ─────────────────────────────────────────
function togglePostMenu(btn) {
    const dropdown = btn.nextElementSibling;
    const isVisible = dropdown.style.display === 'block';

    // Close all other menus
    document.querySelectorAll('.post-menu-dropdown').forEach(d => d.style.display = 'none');

    dropdown.style.display = isVisible ? 'none' : 'block';
}

// Close menus on outside click
document.addEventListener('click', e => {
    if (!e.target.closest('.post-menu')) {
        document.querySelectorAll('.post-menu-dropdown').forEach(d => d.style.display = 'none');
    }
});

// ── Chat Panel ───────────────────────────────────────────────
function toggleChatPanel() {
    const panel = document.getElementById('chatPanel');
    if (!panel) return;
    panel.style.display = panel.style.display === 'none' ? 'flex' : 'none';
    if (panel.style.display === 'flex') {
        document.getElementById('chatInput')?.focus();
    }
}

function startSupportCall() {
    const panel = document.getElementById('chatPanel');
    const messages = document.getElementById('chatMessages');
    const room = panel?.dataset?.callRoom || 'rehletna-admin-support';
    const meetingUrl = 'https://meet.jit.si/' + encodeURIComponent(room);

    window.open(meetingUrl, '_blank', 'noopener,noreferrer');

    if (messages) {
        messages.insertAdjacentHTML(
            'beforeend',
            `<div class="chat-msg assistant"><p>Call room opened. Share this with admin if needed: <a href="${meetingUrl}" target="_blank" rel="noopener noreferrer">${meetingUrl}</a></p></div>`
        );
        messages.scrollTop = messages.scrollHeight;
    }
}

function sendChatMessage() {
    const input = document.getElementById('chatInput');
    const messages = document.getElementById('chatMessages');
    if (!input || !messages) return;

    const text = input.value.trim();
    if (!text) return;

    // Add user message to chat
    messages.insertAdjacentHTML('beforeend',
        `<div class="chat-msg user"><p>${escapeHtml(text)}</p></div>`);
    input.value = '';
    messages.scrollTop = messages.scrollHeight;

    // Show typing indicator
    const typingId = 'typing-' + Date.now();
    messages.insertAdjacentHTML('beforeend',
        `<div class="chat-msg assistant" id="${typingId}"><p>Thinking…</p></div>`);
    messages.scrollTop = messages.scrollHeight;

    fetch('/api/chat', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: text }),
    })
        .then(r => r.json())
        .then(data => {
            // Remove typing indicator
            document.getElementById(typingId)?.remove();

            // Add assistant reply
            messages.insertAdjacentHTML('beforeend',
                `<div class="chat-msg assistant"><p>${escapeHtml(data.reply || data.error || 'No response')}</p></div>`);
            messages.scrollTop = messages.scrollHeight;
        })
        .catch(() => {
            document.getElementById(typingId)?.remove();
            messages.insertAdjacentHTML('beforeend',
                `<div class="chat-msg assistant"><p>Connection error. Please try again.</p></div>`);
        });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ── Search Debounce ──────────────────────────────────────────
(function () {
    document.addEventListener('DOMContentLoaded', () => {
        const field = document.getElementById('searchField');
        if (!field) return;

        let timeout;
        field.addEventListener('input', () => {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                field.form.submit();
            }, 500);
        });
    });
})();

// ── Image Lightbox ───────────────────────────────────────────
(function () {
    document.addEventListener('DOMContentLoaded', () => {
        // Attach to all post images (feed cards + detail hero)
        document.addEventListener('click', e => {
            const img = e.target.closest('.post-image, .detail-hero-image');
            if (!img) return;
            e.preventDefault();
            e.stopPropagation();
            openLightbox(img.src);
        });
    });

    function openLightbox(src) {
        const overlay = document.createElement('div');
        overlay.className = 'lightbox-overlay';
        overlay.innerHTML = `
            <button class="lightbox-close">✕</button>
            <img src="${src}" class="lightbox-img" alt="">`;

        document.body.appendChild(overlay);
        document.body.style.overflow = 'hidden';

        function close() {
            overlay.classList.add('closing');
            setTimeout(() => {
                overlay.remove();
                document.body.style.overflow = '';
            }, 200);
        }

        // Close on backdrop click
        overlay.addEventListener('click', e => {
            if (e.target === overlay || e.target.classList.contains('lightbox-close')) {
                close();
            }
        });

        // Close on Escape
        const onKey = e => {
            if (e.key === 'Escape') { close(); document.removeEventListener('keydown', onKey); }
        };
        document.addEventListener('keydown', onKey);
    }
})();

// ── Weather Badges Load ──────────────────────────────────────────
function loadWeatherBadges() {
    document.querySelectorAll('.weather-badge').forEach(badge => {
        if (badge.dataset.loaded || !badge.dataset.place) return;
        badge.dataset.loaded = 'true';
        fetch('/api/weather/' + encodeURIComponent(badge.dataset.place))
            .then(r => r.json())
            .then(data => {
                if (data && !data.error) {
                    badge.innerHTML = `${data.emoji} ${data.formattedTemp}`;
                    badge.style.display = 'inline-flex';
                }
            }).catch(() => {});
    });
}
document.addEventListener('DOMContentLoaded', loadWeatherBadges);
document.addEventListener('turbo:load', loadWeatherBadges);

// ── Mood/Sentiment Badges Load ───────────────────────────────────
function loadMoodBadges() {
    document.querySelectorAll('.mood-badge').forEach(badge => {
        if (badge.dataset.loaded || !badge.dataset.postId) return;
        badge.dataset.loaded = 'true';
        fetch('/api/sentiment/' + badge.dataset.postId)
            .then(r => r.json())
            .then(data => {
                if (data && data.mood && data.emoji) {
                    badge.textContent = `${data.emoji} ${data.mood}`;
                    badge.style.display = 'inline-flex';
                }
            }).catch(() => {});
    });
}
document.addEventListener('DOMContentLoaded', loadMoodBadges);
document.addEventListener('turbo:load', loadMoodBadges);
