 </div><!-- /main-content-wrapper -->
</div><!-- /app-body -->

</div><!-- /app-wrapper -->
<script src='https://kit.fontawesome.com/a076d05399.js'></script>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// ==========================================
// UNIVERSAL ADMIN SCRIPTS - COMPLETE
// ==========================================

// 1. Page Loader
window.addEventListener('load', () => {
    const loader = document.getElementById('pageLoader');
    if (loader) {
        loader.classList.add('fade-out');
        setTimeout(() => loader.remove(), 500);
    }
});

// 2. Toastr Setup
toastr.options = { closeButton: true, positionClass: "toast-top-right", timeOut: "5000" };

// 3. Topbar Avatar Dropdown
const topAvaBtn = document.getElementById('topAvaBtn');
const topAvaDrop = document.getElementById('topAvaDrop');
if (topAvaBtn && topAvaDrop) {
    topAvaBtn.addEventListener('click', (e) => { e.stopPropagation(); topAvaDrop.classList.toggle('show'); });
    document.addEventListener('click', (e) => { if (!topAvaBtn.contains(e.target)) topAvaDrop.classList.remove('show'); });
}

// 4. Sidebar Notif Dropdown
const notifBellBtn = document.getElementById('notifBellBtn');
const sideNotifDrop = document.getElementById('sideNotifDrop');
if (notifBellBtn && sideNotifDrop) {
    notifBellBtn.addEventListener('click', (e) => { e.stopPropagation(); sideNotifDrop.classList.toggle('show'); });
    document.addEventListener('click', (e) => { if (!notifBellBtn.contains(e.target)) sideNotifDrop.classList.remove('show'); });
}

// 5. Accordion Logic
document.querySelectorAll('.accordion-header').forEach(header => {
    header.addEventListener('click', () => { header.parentElement.classList.toggle('open'); });
});

// 6. Mobile Chat Toggle Helpers
function showChatList() {
    const col = document.querySelector('.chat-list-col');
    if (col) col.classList.add('mobile-show');
}
function hideChatList() {
    const col = document.querySelector('.chat-list-col');
    if (col) col.classList.remove('mobile-show');
}
function showDetails() {
    const col = document.querySelector('.details-col');
    if (col) col.classList.add('mobile-show');
}
function hideDetails() {
    const col = document.querySelector('.details-col');
    if (col) col.classList.remove('mobile-show');
}

// 7. Online Toggle (AJAX)
function toggleOnline() {
    fetch('?toggle_online=1', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(d => {
            const dot = document.getElementById('sidebarStatusDot');
            if (dot) dot.classList.toggle('online', d.is_online);
        }).catch(() => {});
}
// 8. Sound Notifications - New Visitor + Messages
// Skip if chats.php already has its own sound system
if (typeof playNotif === 'undefined') {
const sounds = {
    new_visitor: new Audio('/assets/sounds/new_visitor.mp3'),
    returning_visitor: new Audio('/assets/sounds/returning_visitor.mp3'),
    incoming_chat: new Audio('/assets/sounds/incoming_chat.mp3'),
    message: new Audio('/assets/sounds/message.mp3')
};

// Preload sounds
Object.values(sounds).forEach(s => { s.load(); s.volume = 0.7; });

// Unlock Autoplay Policy (modern browsers block audio before user interaction)
let audioUnlocked = false;
function unlockAudioPlayback() {
    if (audioUnlocked) return;
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const buf = ctx.createBuffer(1, 1, 22050);
        const src = ctx.createBufferSource();
        src.buffer = buf;
        src.connect(ctx.destination);
        src.start();
        ctx.close();
        audioUnlocked = true;
        console.log('Audio unlocked successfully');
    } catch(e) {
        console.warn('Audio unlock failed, will retry on click:', e);
    }
}
document.addEventListener('click', unlockAudioPlayback, { once: true });
document.addEventListener('touchstart', unlockAudioPlayback, { once: true });
document.addEventListener('keydown', unlockAudioPlayback, { once: true });

let lastCheckTime = '<?= date("Y-m-d H:i:s") ?>';

function checkNotifications() {
    const agentId = <?= intval($myAgentId ?? 0) ?>;
    if (!agentId) return;
    
    fetch('/api/CheckMessage.php?agent_id=' + agentId + '&since=' + encodeURIComponent(lastCheckTime))
        .then(res => {
            if (!res.ok) throw new Error('Network response was not ok');
            return res.json();
        })
        .then(data => {
            if (data.play && sounds[data.play]) {
                const soundToPlay = sounds[data.play];
                soundToPlay.currentTime = 0;
                soundToPlay.play().catch(e => console.warn("Autoplay blocked or failed:", e));
            }
            if (data.timestamp) {
                lastCheckTime = data.timestamp;
            }
        })
        .catch(err => console.error("Poll error:", err));
}
setInterval(checkNotifications, 3000);
} // end if (typeof playNotif === 'undefined')

// 9. Global Search Shortcut
document.addEventListener('keydown', (e) => {
    if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
        e.preventDefault();
        const searchInput = document.getElementById('globalSearch');
        if (searchInput) searchInput.focus();
    }
});

// 10. Modal Close on Escape
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal.show').forEach(modal => {
            modal.classList.remove('show');
        });
    }
});

// 11. Real-time sidebar updates (online status, unread count)
let sidebarUpdating = false;
async function updateSidebar() {
    if (sidebarUpdating) return;
    sidebarUpdating = true;
    try {
        // Update unread count from conversations
        const convRes = await fetch('/api/get-conversations');
        const convData = await convRes.json();
        if (convData.success && convData.conversations) {
            let totalUnread = 0;
            convData.conversations.forEach(function(c) {
                totalUnread += parseInt(c.unread_count) || 0;
            });
            const badge = document.querySelector('.sidebar-badge');
            if (badge) {
                badge.textContent = totalUnread > 99 ? '99+' : totalUnread;
                badge.style.display = totalUnread > 0 ? '' : 'none';
            }
        }
        
        // Get online agents count
        const onlineRes = await fetch('/api/api_check_notif.php?agent_id=<?= intval($myAgentId ?? 0) ?>');
        const onlineData = await onlineRes.json();
        // The response contains online_agents count
        if (onlineData.online_agents !== undefined) {
            const countBadge = document.querySelector('.online-count-badge');
            if (countBadge) {
                countBadge.textContent = onlineData.online_agents > 3 ? '+' + (onlineData.online_agents - 3) : onlineData.online_agents;
            }
        }
    } catch(e) {}
    sidebarUpdating = false;
}

// Update sidebar every 10 seconds
setInterval(updateSidebar, 10000);

</script>
</body>
</html>