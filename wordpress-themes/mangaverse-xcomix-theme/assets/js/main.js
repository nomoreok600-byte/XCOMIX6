/**
 * MangaVerse Pro - Main JavaScript
 * Handles bookmarks, chat, messaging, history, and reader interactions
 */

document.addEventListener('DOMContentLoaded', function() {
    // Auto-resize textarea
    document.querySelectorAll('textarea').forEach(ta => {
        ta.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        });
    });

    // Load conversations if on messages page
    if (document.getElementById('conversationList')) {
        loadConversations();
    }

    // Load messages if conversation is open
    const messageArea = document.getElementById('messageArea');
    if (messageArea && messageArea.dataset.user) {
        loadMessages(messageArea.dataset.user);
        // Auto-refresh
        setInterval(() => loadMessages(messageArea.dataset.user, true), 10000);
    }

    // Chat auto-refresh
    if (document.getElementById('chatMessages')) {
        const postId = document.querySelector('form#chatForm')?.closest('[data-post-id]')?.dataset.postId;
        if (postId) {
            setInterval(() => loadChats(true), 15000);
        }
    }

    // Unread count badge refresh
    if (mvData.isLogged) {
        setInterval(updateUnreadBadge, 30000);
    }
});

// ========== BOOKMARKS ==========
function toggleBookmark(mangaId) {
    if (!mvData.isLogged) { window.location.href = mvData.siteUrl + '/auth'; return; }
    
    fetch(mvData.ajaxUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'mv_toggle_bookmark', manga_id: mangaId, nonce: mvData.nonce })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const btn = document.getElementById('bookmarkBtn');
            const text = document.getElementById('bookmarkText');
            const svg = btn.querySelector('svg');
            if (data.data.status === 'added') {
                btn.classList.add('text-mv-accent', 'border-mv-accent');
                btn.classList.remove('text-gray-400');
                svg.setAttribute('fill', 'currentColor');
                text.textContent = 'Bookmarked';
            } else {
                btn.classList.remove('text-mv-accent', 'border-mv-accent');
                btn.classList.add('text-gray-400');
                svg.setAttribute('fill', 'none');
                text.textContent = 'Bookmark';
            }
        }
    });
}

function removeBookmark(mangaId) {
    if (!confirm('Remove this bookmark?')) return;
    toggleBookmark(mangaId);
    setTimeout(() => location.reload(), 300);
}

// ========== HISTORY ==========
function logHistory(mangaId, chapterId, chapterNum) {
    if (!mvData.isLogged) return;
    fetch(mvData.ajaxUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'mv_log_history', manga_id: mangaId, chapter_id: chapterId, chapter_num: chapterNum, nonce: mvData.nonce })
    });
}

function removeHistory(mangaId) {
    if (!confirm('Remove from history?')) return;
    fetch(mvData.ajaxUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'mv_remove_history', manga_id: mangaId, nonce: mvData.nonce })
    })
    .then(() => location.reload());
}

// ========== READING PLAN ==========
function updateReadingPlan(mangaId, status) {
    if (!mvData.isLogged) return;
    fetch(mvData.ajaxUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'mv_update_reading_plan', manga_id: mangaId, status: status, nonce: mvData.nonce })
    });
}

// ========== CHAT ==========
function sendChat(e) {
    e.preventDefault();
    const input = document.getElementById('chatInput');
    const content = input.value.trim();
    if (!content) return;

    const postId = document.querySelector('section')?.dataset?.postId || document.querySelector('input[name="comment_post_ID"]')?.value;
    const parentId = document.getElementById('replyTo')?.value || 0;

    fetch(mvData.ajaxUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'mv_send_chat', post_id: document.body.dataset.postId || 0, content: content, parent: parentId, nonce: mvData.nonce })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            input.value = '';
            input.style.height = 'auto';
            loadChats();
        }
    });
}

function loadChats(silent) {
    const postId = document.body.dataset.postId;
    if (!postId) return;
    
    fetch(mvData.ajaxUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'mv_get_chats', post_id: postId, nonce: mvData.nonce })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const container = document.getElementById('chatMessages');
            const wasAtBottom = container.scrollTop <= 50;
            container.innerHTML = data.data.html || '<div class="text-center text-gray-500 text-sm py-8">No messages yet</div>';
            const countEl = document.getElementById('chatCount');
            if (countEl) countEl.textContent = data.data.count + ' messages';
        }
    });
}

function deleteChat(commentId) {
    if (!confirm('Delete this message?')) return;
    fetch(mvData.ajaxUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'mv_delete_chat', comment_id: commentId, nonce: mvData.nonce })
    })
    .then(() => loadChats());
}

function likeChat(commentId) {
    fetch(mvData.ajaxUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'mv_like_chat', comment_id: commentId, nonce: mvData.nonce })
    })
    .then(() => loadChats());
}

function replyTo(commentId, author) {
    const input = document.getElementById('chatInput');
    input.value = '@' + author + ' ';
    input.focus();
    document.getElementById('replyTo').value = commentId;
}

// ========== MESSAGING ==========
function sendMessage(e) {
    e.preventDefault();
    const input = document.getElementById('messageInput');
    const content = input.value.trim();
    const recipientId = document.getElementById('messageArea')?.dataset?.user;
    if (!content || !recipientId) return;

    fetch(mvData.ajaxUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'mv_send_message', recipient_id: recipientId, content: content, nonce: mvData.nonce })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            input.value = '';
            input.style.height = 'auto';
            loadMessages(recipientId);
            loadConversations();
        }
    });
}

function loadMessages(userId, silent) {
    fetch(mvData.ajaxUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'mv_get_messages', user_id: userId, nonce: mvData.nonce })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const area = document.getElementById('messageArea');
            const wasAtBottom = area.scrollHeight - area.scrollTop - area.clientHeight < 50;
            let html = '';
            data.data.forEach(msg => {
                const isMine = parseInt(msg.sender_id) === mvData.userId;
                html += renderMessageBubble(msg, isMine);
            });
            area.innerHTML = html || '<div class="text-center text-gray-500 text-sm py-8">No messages yet</div>';
            if (wasAtBottom || !silent) area.scrollTop = area.scrollHeight;
        }
    });
}

function renderMessageBubble(msg, isMine) {
    const time = new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    const align = isMine ? 'ml-auto bg-mv-accent text-white' : 'bg-[#252530] text-gray-200';
    return `<div class="flex ${isMine ? 'justify-end' : 'justify-start'}">
        <div class="max-w-[75%] ${align} rounded-2xl px-4 py-2.5 text-sm">
            <p>${escapeHtml(msg.content)}</p>
            <p class="text-[10px] ${isMine ? 'text-white/60' : 'text-gray-500'} mt-1">${time}</p>
        </div>
    </div>`;
}

function loadConversations() {
    fetch(mvData.ajaxUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'mv_get_conversations', nonce: mvData.nonce })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const list = document.getElementById('conversationList');
            if (!data.data || data.data.length === 0) {
                list.innerHTML = '<div class="text-center text-gray-500 text-sm py-8">No conversations yet</div>';
                return;
            }
            let html = '';
            data.data.forEach(conv => {
                const user = conv.other_data || { display_name: 'User', user_login: '' };
                const avatar = conv.avatar || mvData.themeUrl + '/assets/img/default-avatar.png';
                html += `<a href="?user=${conv.other_id}" class="flex items-center gap-3 px-4 py-3 hover:bg-white/[0.03] transition border-b border-[#2a2a35] ${conv.unread_count > 0 ? 'bg-mv-accent/5' : ''}">
                    <div class="relative shrink-0">
                        <img src="${avatar}" class="w-10 h-10 rounded-full object-cover border border-[#2a2a35]">
                        ${conv.unread_count > 0 ? `<span class="absolute -top-1 -right-1 w-4 h-4 bg-mv-accent text-white text-[9px] font-bold rounded-full flex items-center justify-center">${conv.unread_count}</span>` : ''}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold truncate">${escapeHtml(user.display_name || 'User')}</p>
                        <p class="text-xs text-gray-500 truncate">${escapeHtml(conv.last_message || '')}</p>
                    </div>
                </a>`;
            });
            list.innerHTML = html;
        }
    });
}

function updateUnreadBadge() {
    fetch(mvData.ajaxUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'mv_get_unread_count', nonce: mvData.nonce })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.data.count > 0) {
            // Could update a badge here
        }
    });
}

// ========== PRIVACY ==========
function savePrivacy(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const privacy = formData.get('privacy');
    fetch(mvData.ajaxUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'mv_save_privacy', privacy: privacy, nonce: mvData.nonce })
    })
    .then(() => alert('Privacy settings saved!'));
}

// ========== COMMENTS ON MANGA PAGE ==========
function postComment(e, postId) {
    e.preventDefault();
    const input = document.getElementById('commentInput');
    const content = input.value.trim();
    if (!content) return;

    fetch(mvData.ajaxUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'mv_send_chat', post_id: postId, content: content, nonce: mvData.nonce })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            input.value = '';
            location.reload();
        }
    });
}

// ========== UTILITIES ==========
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function copyLink() {
    navigator.clipboard.writeText(window.location.href).then(() => {
        const btn = event.target.closest('button');
        const orig = btn.innerHTML;
        btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Copied!';
        setTimeout(() => btn.innerHTML = orig, 2000);
    });
}

// ========== CHAPTER LIST FILTERING ==========
function filterChapters() {
    const search = document.getElementById('chapterSearch').value.toLowerCase();
    document.querySelectorAll('.chapter-item').forEach(item => {
        const num = item.dataset.chapter;
        const title = item.textContent.toLowerCase();
        item.style.display = (num.includes(search) || title.includes(search)) ? '' : 'none';
    });
}

function sortChapters() {
    const sort = document.getElementById('chapterSort').value;
    const list = document.getElementById('chapterList');
    const items = Array.from(list.querySelectorAll('.chapter-item'));
    items.sort((a, b) => {
        const aNum = parseFloat(a.dataset.chapter) || 0;
        const bNum = parseFloat(b.dataset.chapter) || 0;
        return sort === 'asc' ? aNum - bNum : bNum - aNum;
    });
    items.forEach(item => list.appendChild(item));
}

// ========== KEYBOARD SHORTCUTS (Chapter Reader) ==========
document.addEventListener('keydown', function(e) {
    // Only on chapter pages
    if (!document.querySelector('.reader-container')) return;
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;

    switch(e.key) {
        case 'ArrowRight':
        case 'd':
        case 'D':
            const nextBtn = document.querySelector('a[href*="read"]');
            if (nextBtn) nextBtn.click();
            break;
        case 'ArrowLeft':
        case 'a':
        case 'A':
            const prevBtn = document.querySelectorAll('.sticky.bottom-0 a')[0];
            if (prevBtn) prevBtn.click();
            break;
    }
});
