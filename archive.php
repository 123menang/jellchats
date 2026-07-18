<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
$auth->requireAuth();
$user = $auth->getCurrentUser();
$db = Database::getInstance();

// 1. DATA LOGIC
$agent = $db->fetch("SELECT * FROM agents WHERE user_id = ?", [$user['id']]);
$agentId = $agent ? $agent['id'] : null;

$activePage = 'archive'; 
$pageTitle  = "Archives - LiveChat Console";

// 🔥 FUNGSI WARNA AVATAR BERDASARKAN NAMA
function getAvatarColor($name) {
    if (empty($name)) return '#94a3b8';
    
    $colors = [
        '#10b981', // hijau
        '#3b82f6', // biru
        '#8b5cf6', // ungu
        '#ec489a', // pink
        '#f59e0b', // orange
        '#ef4444', // merah
        '#06b6d4', // cyan
        '#84cc16', // hijau lime
        '#d946ef', // ungu muda
        '#14b8a6', // teal
        '#f97316', // orange tua
        '#6366f1', // indigo
        '#a855f7', // ungu
        '#22c55e', // hijau
        '#eab308', // kuning
        '#be123c', // merah maroon
        '#0284c7', // biru langit
        '#7c3aed', // violet
        '#db2777', // pink
        '#ea580c', // orange
    ];
    
    $hash = 0;
    for ($i = 0; $i < strlen($name); $i++) {
        $hash += ord($name[$i]);
    }
    return $colors[$hash % count($colors)];
}

// Query arsip yang lebih baik dengan filter yang benar
$archives = $db->fetchAll("
    SELECT 
        c.*, 
        COALESCE(v.username, 'Unknown Visitor') as visitor_name, 
        COALESCE(v.email, 'No Email') as visitor_email,
        COALESCE(a.display_name, 'System') as agent_name,
        (SELECT content FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message,
        (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id) as message_count,
        (SELECT created_at FROM messages WHERE conversation_id = c.id ORDER BY created_at ASC LIMIT 1) as first_message_time
    FROM conversations c
    LEFT JOIN visitors v ON c.visitor_id = v.id
    LEFT JOIN agents a ON c.agent_id = a.id
    WHERE c.status = 'closed'
    ORDER BY c.closed_at DESC, c.last_message_at DESC
");

$activeConvId = isset($_GET['conv']) ? (int)$_GET['conv'] : null;
$activeConv = null;
$messages = [];

if ($activeConvId) {
    $activeConv = $db->fetch("
        SELECT c.*, v.*, COALESCE(a.display_name, 'System') as agent_name 
        FROM conversations c 
        LEFT JOIN visitors v ON c.visitor_id = v.id 
        LEFT JOIN agents a ON c.agent_id = a.id
        WHERE c.id = ?
    ", [$activeConvId]);
    
    if ($activeConv) {
        $messages = $db->fetchAll("
            SELECT m.*, 
                   CASE 
                       WHEN m.sender_type = 'agent' THEN COALESCE(a.display_name, 'Agent')
                       WHEN m.sender_type = 'visitor' THEN COALESCE(v.username, 'Visitor')
                       ELSE m.sender_type
                   END as sender_name
            FROM messages m
            LEFT JOIN agents a ON m.sender_id = a.id AND m.sender_type = 'agent'
            LEFT JOIN visitors v ON m.sender_id = v.id AND m.sender_type = 'visitor'
            WHERE m.conversation_id = ? 
            ORDER BY m.created_at ASC
        ", [$activeConvId]);
    }
}

// Avatar URL
$uAva = !empty($agent['avatar_url']) ? $agent['avatar_url'] : 'assets/img/default-avatar.png';

include 'includes/layout-header.php'; 
?>

<style>
/* Archive Page Styles */
.columns-container {
    display: flex;
    height: calc(100vh - 60px);
    overflow: hidden;
    background: #f8fafc;
}

/* LEFT COLUMN - Archive List */
.chat-list-col {
    width: 320px;
    background: #fff;
    border-right: 1px solid #e2e8f0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    flex-shrink: 0;
}

/* MIDDLE COLUMN - Chat View */
.chat-middle-col {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background: #f8fafc;
}

/* RIGHT COLUMN - Details */
.details-col {
    width: 300px;
    background: #fff;
    border-left: 1px solid #e2e8f0;
    overflow-y: auto;
    flex-shrink: 0;
}

.chat-header {
    background: #fff;
    padding: 16px 24px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0;
}

.chat-items {
    flex: 1;
    overflow-y: auto;
}

.chat-item {
    display: flex;
    gap: 12px;
    padding: 14px 16px;
    border-bottom: 1px solid #f1f5f9;
    text-decoration: none;
    transition: all 0.2s;
    cursor: pointer;
}

.chat-item:hover {
    background: #f8fafc;
    transform: translateX(2px);
}

.chat-item.active {
    background: #eff6ff;
    border-left: 3px solid #3b82f6;
}

/* 🔥 STYLE AVATAR DENGAN VARIASI WARNA */
.chat-avatar-list {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 18px;
    color: #fff;
    flex-shrink: 0;
    transition: transform 0.2s;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.chat-item:hover .chat-avatar-list {
    transform: scale(1.05);
}

.chat-avatar-list.small {
    width: 32px;
    height: 32px;
    font-size: 14px;
}

.messages-area {
    flex: 1;
    overflow-y: auto;
    padding: 20px 24px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.pre-chat-card {
    background: #fff;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 10px;
    border: 1px solid #e2e8f0;
}

.pre-chat-item {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #f1f5f9;
    font-size: 13px;
}

.pre-chat-item:last-child {
    border-bottom: none;
}

.pre-chat-label {
    color: #64748b;
    font-weight: 500;
}

.pre-chat-value {
    color: #1e293b;
    font-weight: 600;
}

.pre-chat-value.dark {
    color: #0f172a;
}

.message-row {
    display: flex;
    gap: 10px;
    max-width: 80%;
}

.message-row.visitor {
    align-self: flex-start;
}

.message-row.agent, 
.message-row.bot, 
.message-row.ai,
.message-row.system {
    align-self: flex-end;
    flex-direction: row-reverse;
}

.chat-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 700;
    color: #fff;
    flex-shrink: 0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.chat-avatar.agent-ava {
    background: #3b82f6;
    overflow: hidden;
}

.message-bubble {
    padding: 10px 16px;
    border-radius: 18px;
    font-size: 14px;
    line-height: 1.5;
    word-break: break-word;
}

.visitor .message-bubble {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-bottom-left-radius: 4px;
}

.agent .message-bubble {
    background: #3b82f6;
    color: #fff;
    border-bottom-right-radius: 4px;
}

.bot .message-bubble,
.ai .message-bubble {
    background: #f0fdf4;
    color: #065f46;
    border: 1px solid #bbf7d0;
    border-bottom-right-radius: 4px;
}

.system .message-bubble {
    background: #f1f5f9;
    color: #64748b;
    font-size: 12px;
    font-style: italic;
}

.msg-img {
    max-width: 200px;
    border-radius: 8px;
    cursor: pointer;
}

.time-stamp {
    font-size: 10px;
    color: #94a3b8;
    margin-top: 4px;
    display: flex;
    align-items: center;
    gap: 6px;
    justify-content: flex-end;
}

.badge-unread {
    background: #e2e8f0;
    color: #475569;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}

.visitor-profile-head {
    text-align: center;
    padding: 24px 20px;
    border-bottom: 1px solid #e2e8f0;
}

.vp-avatar {
    width: 72px;
    height: 72px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    font-weight: 700;
    color: #fff;
    margin: 0 auto 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.accordion-item {
    border-bottom: 1px solid #e2e8f0;
}

.accordion-header {
    padding: 14px 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    color: #1e293b;
}

.accordion-header:hover {
    background: #f8fafc;
}

.accordion-body {
    display: none;
    padding: 0 18px 16px;
    font-size: 12px;
}

.accordion-item.open .accordion-body {
    display: block;
}

.accordion-item.open .accordion-header i {
    transform: rotate(180deg);
}

.info-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    font-size: 12px;
}

.info-label {
    color: #64748b;
}

.info-value {
    color: #1e293b;
    font-weight: 600;
}

.btn-primary {
    background: #3b82f6;
    color: #fff;
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    transition: all 0.2s;
}

.btn-primary:hover {
    background: #2563eb;
    transform: translateY(-1px);
}

.hide-desktop {
    display: none;
}

@media (max-width: 768px) {
    .chat-list-col {
        position: fixed;
        left: 0;
        top: 0;
        bottom: 0;
        z-index: 100;
        transform: translateX(-100%);
        transition: transform 0.3s;
        width: 280px;
    }
    
    .chat-list-col.show {
        transform: translateX(0);
    }
    
    .details-col {
        position: fixed;
        right: 0;
        top: 0;
        bottom: 0;
        z-index: 100;
        transform: translateX(100%);
        transition: transform 0.3s;
        width: 280px;
    }
    
    .details-col.show {
        transform: translateX(0);
    }
    
    .hide-desktop {
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .message-row {
        max-width: 90%;
    }
}
</style>

<div class="columns-container" id="archiveColumns">

    <!-- LEFT: ARCHIVE LIST -->
    <div class="chat-list-col" id="archiveListCol">
        <div style="padding:20px 20px 10px; display:flex; justify-content:space-between; align-items:center;">
            <strong style="color:var(--text-light); font-size:18px;"><i class="fas fa-box-archive" style="margin-right:8px;"></i>Archives</strong>
            <button class="hide-desktop btn-icon" onclick="hideChatList()" style="font-size:18px; background:none; border:none;"><i class="fas fa-times"></i></button>
        </div>
        <div class="inbox-controls" style="padding:0 16px 16px;">
            <div class="inbox-search" style="display:flex; gap:10px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:8px 12px;">
                <i class="fas fa-search" style="color:#94a3b8"></i>
                <input type="text" id="archiveSearch" placeholder="Cari arsip..." style="border:none; outline:none; width:100%; background:transparent;">
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:10px;">
                <span style="font-size:12px; color:var(--text-muted);"><span id="archiveCount"><?= count($archives) ?></span> arsip</span>
                <select id="archiveSort" class="inbox-sort" style="border:1px solid #e2e8f0; border-radius:6px; padding:4px 8px; font-size:12px;">
                    <option value="newest">Terbaru</option>
                    <option value="oldest">Terlama</option>
                </select>
            </div>
        </div>
        <div class="chat-items" id="archiveListContainer">
            <?php if (empty($archives)): ?>
            <div style="text-align:center; padding:60px 20px; color:#94a3b8;">
                <i class="fas fa-box-archive" style="font-size:48px; margin-bottom:16px; opacity:0.5;"></i>
                <p style="font-size:13px;">Belum ada percakapan yang diarsipkan</p>
            </div>
            <?php else: ?>
                <?php foreach ($archives as $arch): 
                    $timeUnix = strtotime($arch['closed_at'] ?? $arch['last_message_at']);
                    $searchContent = htmlspecialchars(strtolower($arch['visitor_name'] . ' ' . ($arch['last_message'] ?? '')));
                    $isActive = ($activeConvId == $arch['id']);
                    $closedDate = !empty($arch['closed_at']) ? date('d M Y', strtotime($arch['closed_at'])) : '';
                    // 🔥 Generate warna avatar berdasarkan nama visitor
                    $avatarColor = getAvatarColor($arch['visitor_name']);
                    $initial = strtoupper(substr($arch['visitor_name'], 0, 1));
                ?>
                <a href="?conv=<?= $arch['id'] ?>" 
                   class="chat-item <?= $isActive ? 'active' : '' ?>"
                   data-search="<?= $searchContent ?>"
                   data-time="<?= $timeUnix ?>"
                   onclick="handleChatClick(event, <?= $isActive ? 'true' : 'false' ?>)">
                    <!-- 🔥 Avatar dengan warna variasi -->
                    <div class="chat-avatar-list" style="background: <?= $avatarColor ?>;">
                        <?= $initial ?>
                    </div>
                    <div style="flex:1; min-width:0;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                            <strong style="color:#1e293b; font-size:13px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:120px;"><?= htmlspecialchars($arch['visitor_name']) ?></strong>
                            <span style="color:#64748b; font-size:10px; white-space:nowrap;"><?= $closedDate ?: timeAgo($arch['last_message_at']) ?></span>
                        </div>
                        <div style="font-size:12px; color:#64748b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                            <?= htmlspecialchars(substr($arch['last_message'] ?? 'Percakapan selesai', 0, 40)) ?>
                        </div>
                        <div style="font-size:10px; color:#94a3b8; margin-top:4px;">
                            <i class="fas fa-user"></i> <?= htmlspecialchars($arch['agent_name']) ?> • <?= $arch['message_count'] ?> pesan
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- MIDDLE: ARCHIVE CHAT HISTORY -->
    <div class="chat-middle-col" id="archiveMiddleCol">
        <?php if ($activeConv && $activeConvId): 
            // 🔥 Warna untuk visitor di chat area
            $visitorColor = getAvatarColor($activeConv['username'] ?? 'Visitor');
            $visitorInitial = strtoupper(substr($activeConv['username'] ?? 'V', 0, 1));
        ?>
        <div class="chat-header">
            <div style="display:flex; align-items:center; gap:10px;">
                <button class="hide-desktop" onclick="showChatList()" style="background:none; border:none; font-size:20px; color:#64748b; cursor:pointer;">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <strong style="font-size:16px; color:#1e293b;"><?= htmlspecialchars($activeConv['username'] ?? 'Visitor') ?></strong>
                    <div style="font-size:11px; color:#64748b; margin-top:2px;">
                        <i class="fas fa-archive"></i> Diarsipkan • <?= date('d M Y', strtotime($activeConv['closed_at'] ?? 'now')) ?>
                    </div>
                </div>
            </div>
            <div style="display:flex; align-items:center; gap:8px;">
                <span style="background:#e2e8f0; color:#475569; padding:4px 12px; border-radius:20px; font-size:11px; font-weight:600;">
                    <i class="fas fa-check-double"></i> Selesai
                </span>
                <button class="hide-desktop" onclick="showDetails()" style="background:none; border:none; font-size:18px; color:#64748b; cursor:pointer;">
                    <i class="fas fa-info-circle"></i>
                </button>
            </div>
        </div>

        <div class="messages-area" id="messagesArea">
            <!-- Start Date -->
            <div style="text-align:center; margin-bottom:16px;">
                <span style="background:#e2e8f0; padding:4px 12px; border-radius:20px; font-size:11px; color:#64748b;">
                    <i class="fas fa-play"></i> Dimulai <?= date('d M Y, H:i', strtotime($activeConv['created_at'])) ?>
                </span>
            </div>

            <!-- Pre-chat Info -->
            <div class="pre-chat-card">
                <strong><i class="fas fa-clipboard-list"></i> Data Pengunjung</strong>
                <div class="pre-chat-item">
                    <span class="pre-chat-label">Username:</span>
                    <span class="pre-chat-value dark"><?= htmlspecialchars($activeConv['username'] ?? 'N/A') ?></span>
                </div>
                <div class="pre-chat-item">
                    <span class="pre-chat-label">WhatsApp:</span>
                    <span class="pre-chat-value"><?= htmlspecialchars($activeConv['phone'] ?? 'N/A') ?></span>
                </div>
                <div class="pre-chat-item">
                    <span class="pre-chat-label">Topik:</span>
                    <?php $tagsData = json_decode($activeConv['tags'] ?? '{}', true); ?>
                    <span class="pre-chat-value dark"><?= htmlspecialchars(str_replace('_', ' ', implode(', ', (array)($tagsData['issue_type'] ?? ['Umum'])))) ?></span>
                </div>
                <div class="pre-chat-item">
                    <span class="pre-chat-label">Agen:</span>
                    <span class="pre-chat-value"><?= htmlspecialchars($activeConv['agent_name'] ?? 'System') ?></span>
                </div>
            </div>

            <!-- Messages -->
            <?php foreach ($messages as $msg): 
                $timeFmt = date('H:i', strtotime($msg['created_at']));
                $fullDate = date('d M Y H:i', strtotime($msg['created_at']));
                $msgType = $msg['sender_type'];
                $isBot = in_array($msgType, ['bot', 'ai']);
                $isSystem = $msgType === 'system';
            ?>
            <div class="message-row <?= $msgType ?>">
                <?php if($msgType === 'visitor'): ?>
                    <!-- 🔥 Avatar visitor dengan warna variasi -->
                    <div class="chat-avatar" style="background: <?= $visitorColor ?>;">
                        <?= $visitorInitial ?>
                    </div>
                <?php elseif($msgType === 'agent'): ?>
                    <div class="chat-avatar agent-ava">
                        <?php if($uAva && file_exists($uAva)): ?>
                            <img src="<?= $uAva ?>" style="width:100%; height:100%; object-fit:cover;" alt="Agent">
                        <?php else: ?>
                            <?= strtoupper(substr($activeConv['agent_name'] ?? 'A', 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="message-content">
                    <div class="message-bubble" title="<?= $fullDate ?>">
                        <?php if(!empty($msg['file_url'])): ?>
                            <?php if(preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $msg['file_url'])): ?>
                                <img src="<?= htmlspecialchars($msg['file_url']) ?>" class="msg-img" onclick="window.open(this.src,'_blank')">
                            <?php else: ?>
                                <a href="<?= htmlspecialchars($msg['file_url']) ?>" target="_blank" style="color:inherit; text-decoration:underline;">
                                    <i class="fas fa-file"></i> Download File
                                </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <?= nl2br(htmlspecialchars($msg['content'] ?? '')) ?>
                        <?php endif; ?>
                    </div>
                    <div class="time-stamp">
                        <i class="far fa-clock"></i> <?= $timeFmt ?>
                        <?php if ($isBot): ?> <i class="fas fa-robot"></i> Bot<?php endif; ?>
                        <?php if ($isSystem): ?> <i class="fas fa-cog"></i> System<?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- Closed Marker -->
            <div style="text-align:center; margin:20px 0;">
                <span style="background:#fef2f2; color:#991b1b; padding:6px 16px; border-radius:20px; font-size:11px; font-weight:600; border:1px solid #fecaca;">
                    <i class="fas fa-archive"></i> Percakapan ditutup pada <?= date('d M Y, H:i', strtotime($activeConv['closed_at'] ?? 'now')) ?>
                </span>
            </div>

            <!-- Rating -->
            <?php if (!empty($activeConv['rating'])): ?>
            <div style="text-align:center; margin:10px 0 20px;">
                <div style="display:inline-block; background:#fffbeb; padding:12px 20px; border-radius:12px; border:1px solid #fde68a;">
                    <div style="font-size:11px; color:#92400e; margin-bottom:5px;">Penilaian Pelanggan</div>
                    <div style="color:#fbbf24; font-size:18px;">
                        <?php for($i=1; $i<=5; $i++): ?>
                            <i class="<?= $i <= $activeConv['rating'] ? 'fas fa-star' : 'far fa-star' ?>"></i>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Reopen Button -->
        <div style="padding:16px 20px; text-align:center; background:#fff; border-top:1px solid #e2e8f0;">
            <p style="font-size:12px; color:#64748b; margin-bottom:12px;">
                <i class="fas fa-info-circle"></i> Ini adalah percakapan yang sudah diarsipkan.
            </p>
            <button class="btn-primary" onclick="if(confirm('Buka kembali chat ini? Pelanggan akan dapat melanjutkan percakapan.')) window.location.href='chats.php?reopen=<?= $activeConv['id'] ?>'">
                <i class="fas fa-redo"></i> Buka Kembali Chat
            </button>
        </div>

        <?php else: ?>
        <div style="flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; color:#64748b; padding:20px;">
            <div style="display:flex; align-items:center; gap:15px; margin-bottom:20px;">
                <button class="hide-desktop" onclick="showChatList()" style="width:50px; height:50px; font-size:24px; border:2px solid #e2e8f0; background:#fff; border-radius:12px;">
                    <i class="fas fa-bars"></i>
                </button>
                <i class="fas fa-box-archive" style="font-size:54px; color:#cbd5e1;"></i>
            </div>
            <h3 style="color:#1e293b;">Tidak Ada Arsip Dipilih</h3>
            <p style="margin-top:8px; font-size:13px;">Pilih percakapan yang sudah ditutup dari daftar di samping</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- RIGHT: ARCHIVE DETAILS -->
    <?php if ($activeConv && $activeConvId): 
        // 🔥 Warna untuk detail profile
        $detailColor = getAvatarColor($activeConv['username'] ?? 'Visitor');
    ?>
    <div class="details-col" id="detailsCol">
        <div style="padding:15px 20px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
            <strong style="font-size:14px; color:#1e293b;">Detail Arsip</strong>
            <button class="hide-desktop" onclick="hideDetails()" style="background:none; border:none; font-size:18px; cursor:pointer;"><i class="fas fa-times"></i></button>
        </div>
        <div class="visitor-profile-head">
            <!-- 🔥 Avatar di detail dengan warna variasi -->
            <div class="vp-avatar" style="background: <?= $detailColor ?>;">
                <?= strtoupper(substr($activeConv['username'] ?? 'V', 0, 1)) ?>
            </div>
            <div style="font-weight:bold; font-size:16px; color:#1e293b;"><?= htmlspecialchars($activeConv['username'] ?? 'Visitor') ?></div>
            <div style="font-size:13px; color:#2563eb; margin-top:5px;"><i class="fab fa-whatsapp"></i> <?= htmlspecialchars($activeConv['phone'] ?: 'Tidak ada') ?></div>
            <div style="font-size:12px; color:#64748b; margin-top:5px;"><?= htmlspecialchars(($activeConv['city'] ?? 'Tidak diketahui') . ', ' . ($activeConv['country'] ?? 'Tidak diketahui')) ?></div>
            <div style="font-size:11px; color:#64748b; margin-top:2px;">Ditutup <?= date('d M Y', strtotime($activeConv['closed_at'] ?? 'now')) ?></div>
        </div>

        <div class="accordion-item open">
            <div class="accordion-header">Ringkasan Chat <i class="fas fa-chevron-down"></i></div>
            <div class="accordion-body">
                <div class="info-row"><span class="info-label">Total Pesan:</span><span class="info-value"><?= count($messages) ?></span></div>
                <div class="info-row"><span class="info-label">Durasi Chat:</span>
                    <?php 
                    $end = strtotime($activeConv['closed_at'] ?? 'now'); 
                    $start = strtotime($activeConv['created_at']); 
                    $mins = floor(($end - $start) / 60); 
                    $hours = floor($mins / 60);
                    $remainMins = $mins % 60;
                    $duration = $hours > 0 ? "{$hours} jam {$remainMins} menit" : "{$mins} menit";
                    ?>
                    <span class="info-value"><?= $duration ?></span>
                </div>
                <div class="info-row"><span class="info-label">Ditangani oleh:</span><span class="info-value"><?= htmlspecialchars($activeConv['agent_name'] ?? 'Tidak diketahui') ?></span></div>
                <div class="info-row"><span class="info-label">Penilaian:</span>
                    <span class="info-value"><?= $activeConv['rating'] ? $activeConv['rating'] . '/5 <i class="fas fa-star" style="color:#fbbf24;"></i>' : 'Belum dinilai' ?></span>
                </div>
            </div>
        </div>

        <div class="accordion-item">
            <div class="accordion-header">Info Pengunjung <i class="fas fa-chevron-down"></i></div>
            <div class="accordion-body">
                <div class="info-row"><span class="info-label">Kunjungan:</span><span class="info-value"><?= $activeConv['visit_count'] ?? 1 ?></span></div>
                <div class="info-row"><span class="info-label">IP Address:</span><span class="info-value"><?= htmlspecialchars($activeConv['ip_address'] ?? 'N/A') ?></span></div>
                <div class="info-row"><span class="info-label">Sumber:</span>
                    <?php if(!empty($activeConv['source_url'])): ?>
                        <a href="<?= htmlspecialchars($activeConv['source_url']) ?>" target="_blank" style="color:#2563eb; font-size:11px; text-decoration:none;">Lihat Halaman</a>
                    <?php else: ?>
                        <span class="info-value">Langsung</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="accordion-item">
            <div class="accordion-header">Teknologi <i class="fas fa-chevron-down"></i></div>
            <div class="accordion-body">
                <div class="info-row"><span class="info-label">Browser:</span><span class="info-value"><i class="fab fa-chrome"></i> Web Client</span></div>
                <div class="info-row"><span class="info-label">User Agent:</span><span class="info-value" style="font-size:10px; word-break:break-all;"><?= htmlspecialchars(substr($activeConv['user_agent'] ?? 'N/A', 0, 60)) ?>...</span></div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// Mobile Functions
function showChatList() {
    document.getElementById('archiveListCol').classList.add('show');
}

function hideChatList() {
    document.getElementById('archiveListCol').classList.remove('show');
}

function showDetails() {
    document.getElementById('detailsCol').classList.add('show');
}

function hideDetails() {
    document.getElementById('detailsCol').classList.remove('show');
}

function handleChatClick(e, isActive) {
    if (window.innerWidth <= 768) {
        if (isActive) {
            e.preventDefault();
            hideChatList();
        }
    }
}

// Filter & Sort Archives
const archiveSearch = document.getElementById('archiveSearch');
const archiveSort = document.getElementById('archiveSort');
const archiveContainer = document.getElementById('archiveListContainer');
const archiveCount = document.getElementById('archiveCount');

function filterAndSortArchives() {
    if(!archiveContainer) return;
    const term = archiveSearch ? archiveSearch.value.toLowerCase() : '';
    const sortDir = archiveSort ? archiveSort.value : 'newest';
    let items = Array.from(archiveContainer.querySelectorAll('.chat-item:not(.hidden-item)'));
    let visibleCount = 0;
    
    items.forEach(item => {
        const dataStr = item.getAttribute('data-search') || '';
        if (dataStr.includes(term)) { 
            item.style.display = 'flex'; 
            visibleCount++; 
        } else { 
            item.style.display = 'none'; 
        }
    });
    
    if(archiveCount) archiveCount.innerText = visibleCount;
    
    items.sort((a, b) => {
        const tA = parseInt(a.getAttribute('data-time') || 0);
        const tB = parseInt(b.getAttribute('data-time') || 0);
        return sortDir === 'newest' ? tB - tA : tA - tB;
    });
    
    items.forEach(item => archiveContainer.appendChild(item));
}

if(archiveSearch) archiveSearch.addEventListener('input', filterAndSortArchives);
if(archiveSort) archiveSort.addEventListener('change', filterAndSortArchives);

// Accordion Functionality
document.querySelectorAll('.accordion-header').forEach(header => {
    header.addEventListener('click', function() {
        this.parentElement.classList.toggle('open');
    });
});

// Auto scroll to bottom
const msgArea = document.getElementById('messagesArea');
if(msgArea) msgArea.scrollTop = msgArea.scrollHeight;
</script>

<?php include 'includes/layout-footer.php'; ?>