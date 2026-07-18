<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
$auth->requireAuth();
$user = $auth->getCurrentUser();
$db   = Database::getInstance();

// 1. ENSURE AGENT EXISTS
$agent = $db->fetch("SELECT * FROM agents WHERE user_id = ?", [$user['id']]);
if (!$agent) {
    $team = $db->fetch("SELECT * FROM teams WHERE user_id = ? LIMIT 1", [$user['id']]);
    if (!$team) { $teamId = $db->insert("INSERT INTO teams (user_id, name, description, max_agents) VALUES (?, 'Default Team', 'Auto-created', 1)", [$user['id']]); }
    else { $teamId = $team['id']; }
    $agentId = $db->insert("INSERT INTO agents (team_id, user_id, display_name, reply_mode) VALUES (?, ?, ?, 'manual')", [$teamId, $user['id'], $user['full_name'] ?? $user['username']]);
    $agent = $db->fetch("SELECT * FROM agents WHERE id = ?", [$agentId]);
}
$agentId = $agent['id'];

// 2. AJAX: ADD SHORTCUT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_shortcut') {
    header('Content-Type: application/json');
    $cmd = trim($_POST['shortcut'] ?? '');
    $msg = trim($_POST['message'] ?? '');
    if (!str_starts_with($cmd, '/')) $cmd = '/' . $cmd;
    try { $db->insert("INSERT INTO canned_responses (agent_id, shortcut, message) VALUES (?, ?, ?)", [$agentId, $cmd, $msg]); echo json_encode(['success' => true, 'toast' => 'Balasan cepat ditambahkan']); }
    catch (Exception $e) { echo json_encode(['success' => false, 'error' => $e->getMessage()]); }
    exit;
}
// 8. AJAX: GET UPDATED CHAT LIST & TYPING STATUS
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'get_updates') {
    header('Content-Type: application/json');
    
    // Ambil daftar chat terbaru
    $chats = $db->fetchAll("
        SELECT c.id, v.username, c.last_message_at,
               (SELECT content FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_msg,
               (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND sender_type='visitor' AND is_read=0) as unread,
               (SELECT is_typing FROM typing_status WHERE conversation_id = c.id AND user_type = 'visitor' AND updated_at > datetime('now', '-5 seconds')) as is_typing
        FROM conversations c 
        JOIN visitors v ON c.visitor_id = v.id
        WHERE c.agent_id = ? AND c.status = 'active'
        ORDER BY c.last_message_at DESC", [$agentId]);

    echo json_encode(['success' => true, 'chats' => $chats]);
    exit;
}
// CSRF validation for all POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!$auth->validateCsrfToken($csrf)) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
}

// 3. AJAX: SET REPLY MODE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'set_reply_mode') {
    header('Content-Type: application/json');
    $mode = $_POST['mode'] ?? 'manual';
    if (in_array($mode, ['manual', 'bot', 'ai', 'hybrid'])) { $db->update("UPDATE agents SET reply_mode = ? WHERE id = ?", [$mode, $agentId]); echo json_encode(['success' => true, 'toast' => 'Mode diubah: ' . $mode]); }
    else { echo json_encode(['success' => false, 'error' => 'Mode tidak valid']); }
    exit;
}

// 4. AJAX: CLOSE CHAT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'close_chat_ajax') {
    header('Content-Type: application/json');
    $convId = (int)($_POST['conversation_id'] ?? 0);
    if ($convId) {
        $db->update("UPDATE conversations SET status = 'closed', closed_at = datetime('now') WHERE id = ? AND agent_id = ?", [$convId, $agentId]);
        $db->insert("INSERT INTO messages (conversation_id, sender_type, content) VALUES (?, 'system', 'Chat ditutup oleh agen.')", [$convId]);
        echo json_encode(['success' => true, 'toast' => 'Percakapan ditutup']);
    } else { echo json_encode(['success' => false, 'error' => 'ID percakapan tidak valid']); }
    exit;
}

// 5. AJAX: SEND MESSAGE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_message') {
    header('Content-Type: application/json');
    $convId  = (int)($_POST['conversation_id'] ?? 0);
    $content = trim($_POST['content'] ?? '');
    $fileUrl = $_POST['file_url'] ?? null;
    if (!$convId || (empty($content) && empty($fileUrl))) {
        echo json_encode(['success' => false, 'error' => 'Missing data']); exit;
    }
    $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
    $msgId = $db->insert("INSERT INTO messages (conversation_id, sender_type, sender_id, content, file_url, is_read, created_at) VALUES (?, 'agent', ?, ?, ?, 1, datetime('now'))", [$convId, $agentId, $content, $fileUrl]);
    $db->update("UPDATE conversations SET last_message_at = datetime('now') WHERE id = ?", [$convId]);
    echo json_encode(['success' => true, 'message_id' => $msgId, 'toast' => 'Pesan terkirim']);
    exit;
}

// 6. AJAX: TYPING STATUS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'typing') {
    header('Content-Type: application/json');
    $convId    = (int)($_POST['conversation_id'] ?? 0);
    $isTyping  = (int)($_POST['is_typing'] ?? 0);
    $typingTxt = $_POST['typing_text'] ?? '';
    $existing  = $db->fetch("SELECT id FROM typing_status WHERE conversation_id = ? AND user_type = 'agent'", [$convId]);
    if ($existing) {
        $db->update("UPDATE typing_status SET is_typing = ?, typing_text = ?, updated_at = datetime('now') WHERE conversation_id = ? AND user_type = 'agent'", [$isTyping, $typingTxt, $convId]);
    } else {
        $db->insert("INSERT INTO typing_status (conversation_id, user_type, is_typing, typing_text, updated_at) VALUES (?, 'agent', ?, ?, datetime('now'))", [$convId, $isTyping, $typingTxt]);
    }
    echo json_encode(['success' => true]);
    exit;
}

// 7. AJAX: TRANSFER CHAT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'transfer') {
    header('Content-Type: application/json');
    $convId     = (int)($_POST['conversation_id'] ?? 0);
    $newAgentId = (int)($_POST['new_agent_id'] ?? 0);
    if ($convId && $newAgentId) {
        $db->update("UPDATE conversations SET agent_id = ? WHERE id = ?", [$newAgentId, $convId]);
        $db->insert("INSERT INTO messages (conversation_id, sender_type, content) VALUES (?, 'system', 'Chat ditransfer ke agen lain.')", [$convId]);
        echo json_encode(['success' => true, 'toast' => 'Chat ditransfer']);
    } else { echo json_encode(['success' => false, 'error' => 'Data transfer tidak valid']); }
    exit;
}

// DATA
$cannedResponses = [];
try { $cannedResponses = $db->fetchAll("SELECT * FROM canned_responses WHERE agent_id = ?", [$agentId]); } catch(Exception $e) {}
$totalUnread = $db->fetch("SELECT COUNT(DISTINCT c.id) as count FROM conversations c JOIN messages m ON c.id = m.conversation_id WHERE c.agent_id = ? AND m.sender_type = 'visitor' AND m.is_read = 0", [$agentId])['count'] ?? 0;
$allAgents   = $db->fetchAll("SELECT id, display_name FROM agents WHERE id != ? AND status = 1", [$agentId]);

function getAvatarColor($name) {
    $colors = ['#10b981','#3b82f6','#8b5cf6','#ec489a','#f59e0b','#ef4444','#06b6d4','#84cc16','#d946ef','#14b8a6','#f97316','#6366f1','#a855f7','#22c55e','#eab308'];
    $hash = 0;
    for ($i = 0; $i < strlen($name); $i++) { $hash += ord($name[$i]); }
    return $colors[$hash % count($colors)];
}

$conversations = $db->fetchAll("
    SELECT c.*, v.username as visitor_name, v.phone as visitor_phone,
           (SELECT content FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message,
           (SELECT created_at FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message_time,
           (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND sender_type='visitor' AND is_read=0) as unread_count
    FROM conversations c JOIN visitors v ON c.visitor_id = v.id
    WHERE c.agent_id = ? AND c.status = 'active'
    ORDER BY c.last_message_at DESC", [$agentId]);

$activeConvId = $_GET['conv'] ?? null;
$activeConv   = null;
$messages     = [];
if ($activeConvId) {
    $activeConv = $db->fetch("SELECT c.*, v.*, c.id as conv_id, c.status as conv_status FROM conversations c JOIN visitors v ON c.visitor_id = v.id WHERE c.id = ? AND c.agent_id = ?", [$activeConvId, $agentId]);
    if ($activeConv) {
        $messages = $db->fetchAll("SELECT m.*, CASE WHEN m.sender_type='agent' THEN a.display_name WHEN m.sender_type IN ('bot','ai') THEN 'Bot' ELSE v.username END as sender_name FROM messages m LEFT JOIN agents a ON m.sender_id = a.id AND m.sender_type = 'agent' LEFT JOIN visitors v ON m.sender_id = v.id AND m.sender_type = 'visitor' WHERE m.conversation_id = ? ORDER BY m.created_at ASC", [$activeConvId]);
        $db->update("UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND sender_type = 'visitor'", [$activeConvId]);
    }
}
$lastMessageId = count($messages) > 0 ? end($messages)['id'] : 0;
$uAva          = !empty($user['avatar']) ? $user['avatar'] : 'assets/images/default-avatar.png';
$pageTitle     = 'LiveChat – Inbox';
$activePage    = 'chats';
include 'includes/layout-header.php';
?>
<style>
:root{--toast-success:#10b981;--toast-error:#ef4444;--toast-warning:#f59e0b;--panel-bg:#ffffff;--chat-bg:#f0f2f5;}
*{-webkit-tap-highlight-color:transparent;}
.main-content-wrapper{padding:0;display:flex;flex-direction:column;overflow:hidden;height:100%;}
.chat-columns{display:flex;flex:1;overflow:hidden;height:100%;position:relative;}

/* LEFT PANEL */
.panel-list{width:320px;flex-shrink:0;display:flex;flex-direction:column;border-right:1px solid var(--border-light);background:var(--panel-bg);transition:transform .3s cubic-bezier(0.2,0.9,0.4,1.1);z-index:30;}
.panel-list-header{display:flex;align-items:center;justify-content:space-between;padding:20px 20px 16px;border-bottom:1px solid var(--border-light);}
.panel-list-header h2{font-size:18px;font-weight:700;color:var(--text-light);display:flex;align-items:center;gap:10px;}
.inbox-controls{padding:12px 16px;border-bottom:1px solid var(--border-light);display:flex;flex-direction:column;gap:10px;}
.inbox-search{display:flex;align-items:center;gap:10px;background:#f8fafc;border:1px solid var(--border-color);border-radius:10px;padding:10px 14px;transition:all .2s;}
.inbox-search:focus-within{border-color:var(--accent-blue);box-shadow:0 0 0 3px rgba(37,99,235,.1);}
.inbox-search input{border:none;outline:none;width:100%;font-size:13px;background:transparent;color:var(--text-light);}
.inbox-search i{color:var(--text-secondary);font-size:14px;}
.inbox-meta{display:flex;justify-content:space-between;align-items:center;font-size:12px;color:var(--text-muted);}
.inbox-sort{border:1px solid var(--border-color);border-radius:8px;font-size:12px;padding:6px 10px;background:#fff;outline:none;cursor:pointer;}
.chat-list{flex:1;overflow-y:auto;}
.ci{display:flex;align-items:flex-start;gap:12px;padding:14px 16px;text-decoration:none;color:inherit;border-bottom:1px solid #f1f5f9;transition:all .2s;cursor:pointer;position:relative;}
.ci:hover{background:#f8fafc;transform:translateX(2px);}
.ci.active{background:linear-gradient(135deg,#dbeafe,#eff6ff);border-left:4px solid var(--accent-blue);box-shadow:inset 0 0 0 1px rgba(59,130,246,.15),0 2px 8px rgba(59,130,246,.08);}
.ci.active .ci-ava{box-shadow:0 0 0 3px rgba(59,130,246,.25),0 2px 8px rgba(0,0,0,.1);}
.ci.active .ci-name{color:var(--accent-blue);}
.ci-ava{width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:16px;color:#fff;flex-shrink:0;box-shadow:0 2px 8px rgba(0,0,0,.1);transition:transform .2s;}
.ci:hover .ci-ava{transform:scale(1.05);}
.ci-body{flex:1;min-width:0;}
.ci-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;}
.ci-name{font-size:14px;font-weight:700;color:var(--text-light);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:140px;}
.ci-time{font-size:10px;color:var(--text-muted);white-space:nowrap;}
.ci-preview{font-size:12px;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:flex;align-items:center;gap:6px;}
.ci-preview i{font-size:10px;color:#94a3b8;}
.unread-dot{width:10px;height:10px;background:#ef4444;border-radius:50%;display:inline-block;margin-right:6px;animation:pulse 1.5s infinite;}
.unread-count{background:#ef4444;color:#fff;font-size:10px;font-weight:700;padding:2px 6px;border-radius:20px;min-width:18px;text-align:center;}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1);}50%{opacity:.5;transform:scale(1.2);}}

/* MIDDLE PANEL */
.panel-chat{flex:1;display:flex;flex-direction:column;background:var(--chat-bg);min-width:0;}
.mode-bar{display:flex;align-items:center;gap:8px;padding:8px 20px;background:var(--panel-bg);border-bottom:1px solid var(--border-light);flex-shrink:0;flex-wrap:wrap;}
.mode-btn{padding:6px 14px;border-radius:24px;font-size:12px;font-weight:600;border:1px solid var(--border-color);cursor:pointer;background:transparent;color:var(--text-muted);transition:all .2s;display:inline-flex;align-items:center;gap:6px;}
.mode-btn.am{border-color:transparent;}
.mode-btn[data-mode=manual].am{background:#eff6ff;color:#1e40af;}
.mode-btn[data-mode=bot].am{background:#ecfdf5;color:#065f46;border-color:#a7f3d0;}
.mode-btn[data-mode=ai].am{background:#f5f3ff;color:#5b21b6;}
.mode-btn[data-mode=hybrid].am{background:#fffbeb;color:#92400e;}
.chat-hdr{display:flex;align-items:center;justify-content:space-between;padding:10px 20px;background:var(--panel-bg);border-bottom:1px solid var(--border-color);flex-shrink:0;}
.chat-hdr-left{display:flex;align-items:center;gap:12px;}
.visitor-status{display:flex;flex-direction:column;}
.visitor-name{font-size:15px;font-weight:700;color:var(--text-light);}
.visitor-status-badge{font-size:11px;font-weight:600;margin-top:1px;}
.visitor-status-badge.online{color:#10b981;}
.visitor-status-badge.offline{color:#94a3b8;}
.btn-mobile-panel{width:36px;height:36px;border-radius:50px;border:1px solid var(--border-color);background:#fff;color:var(--text-muted);font-size:16px;cursor:pointer;display:none;align-items:center;justify-content:center;transition:all .2s;}
.btn-mobile-panel:hover{background:#f1f5f9;}
.chat-acts{display:flex;gap:10px;}
.chat-acts button{font-size:12px;font-weight:600;border-radius:8px;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:8px;transition:all .2s;}
.btn-xfer{background:#f1f5f9;color:#475569;}
.btn-xfer:hover{background:#e2e8f0;transform:translateY(-1px);}
.btn-close-c{background:#fef2f2;color:#dc2626;}
.btn-close-c:hover{background:#fee2e2;transform:translateY(-1px);}

/* Messages Area */
.messages-area{flex:1;overflow-y:auto;padding:20px 24px;display:flex;flex-direction:column;gap:16px;scroll-behavior:smooth;border:0px;}
.msg-divider{text-align:center;margin:8px 0;}
.msg-divider span{background:#e2e8f0;color:#64748b;font-size:11px;font-weight:600;padding:4px 14px;border-radius:20px;}
.pre-chat-card{background:#fff;border:1px solid var(--border-light);border-radius:16px;padding:16px 20px;font-size:13px;max-width:340px;align-self:flex-start;box-shadow:0 2px 8px rgba(0,0,0,.04);}
.pre-chat-card strong{display:block;font-size:14px;font-weight:700;color:var(--text-light);margin-bottom:12px;}
.pci{margin-bottom:8px;display:flex;justify-content:space-between;align-items:center;}
.pci-label{font-size:11px;color:var(--text-muted);}
.pci-val{font-size:13px;color:var(--text-light);font-weight:500;}
.pci-val.accent{color:var(--accent-blue);font-weight:600;}
.message-row{display:flex;gap:10px;align-items:flex-end;max-width:80%;animation:fadeInUp .2s ease;}
@keyframes fadeInUp{from{opacity:0;transform:translateY(10px);}to{opacity:1;transform:translateY(0);}}
.message-row.visitor{align-self:flex-start;}
.message-row.agent,.message-row.bot,.message-row.ai{align-self:flex-end;flex-direction:row-reverse;}
.message-row.system{max-width:100%;align-self:center;}
.chat-ava{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0;overflow:hidden;box-shadow:0 2px 6px rgba(0,0,0,.1);}
.chat-ava img{width:100%;height:100%;object-fit:cover;}
.message-content{display:flex;flex-direction:column;max-width:100%;}
.message-bubble{padding:10px 16px;border-radius:18px;font-size:14px;line-height:1.5;word-break:break-word;transition:all .2s;}
.visitor .message-bubble{background:#fff;border:1px solid var(--border-color);color:var(--text-light);border-bottom-left-radius:4px;box-shadow:0 1px 3px rgba(0,0,0,.05);}
.agent .message-bubble{background:var(--accent-blue);color:#fff;border-bottom-right-radius:4px;}
.bot .message-bubble,.ai .message-bubble{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;border-bottom-right-radius:4px;}
.system .message-bubble{background:#f1f5f9;color:#64748b;font-size:12px;font-style:italic;border-radius:20px;padding:6px 16px;}
.msg-img{max-width:220px;border-radius:12px;cursor:pointer;transition:transform .2s;}
.msg-img:hover{transform:scale(1.02);}
.time-stamp{font-size:10px;color:var(--text-muted);margin-top:4px;display:flex;align-items:center;gap:6px;}

/* Typing Indicator */
.typing-wrap{display:none;align-self:flex-start;gap:10px;align-items:flex-end;margin-top:8px;}
.typing-bubble{background:#f1f5f9;border:1px solid #e2e8f0;padding:10px 16px;border-radius:20px;border-bottom-left-radius:4px;font-size:13px;color:var(--text-muted);display:flex;align-items:center;gap:8px;}
.typing-dots{display:inline-flex;gap:4px;}
.typing-dots span{width:6px;height:6px;background:#94a3b8;border-radius:50%;animation:typingBlink 1.4s infinite both;}
.typing-dots span:nth-child(2){animation-delay:.2s;}
.typing-dots span:nth-child(3){animation-delay:.4s;}
@keyframes typingBlink{0%,80%,100%{opacity:.3;transform:scale(.8);}40%{opacity:1;transform:scale(1.2);}}

/* Composer */
.composer-wrap{background:var(--panel-bg);border-top:1px solid var(--border-light);padding:14px 20px 24px;flex-shrink:0;position:relative;}
.canned-row{display:flex;gap:10px;overflow-x:auto;margin-bottom:12px;scrollbar-width:none;padding-bottom:4px;}
.canned-row::-webkit-scrollbar{display:none;}
.cpill{white-space:nowrap;background:#f1f5f9;border:1px solid var(--border-color);color:var(--text-light);font-size:12px;padding:6px 14px;border-radius:24px;cursor:pointer;transition:all .2s;flex-shrink:0;}
.cpill:hover{background:#e2e8f0;transform:translateY(-1px);}
.cpill.add{background:#1e293b;color:#fff;border-color:transparent;}
.cpill.add:hover{background:#0f172a;}
.composer-box{border:1px solid var(--border-color);border-radius:16px;background:#fff;display:flex;flex-direction:column;overflow:hidden;transition:all .2s;}
.composer-box:focus-within{border-color:var(--accent-blue);box-shadow:0 0 0 3px rgba(37,99,235,.1);}
.composer-box textarea{width:100%;border:none;background:transparent;padding:14px 18px;font-size:14px;outline:none;resize:none;min-height:50px;max-height:120px;font-family:inherit;color:var(--text-light);}
.composer-toolbar{display:flex;justify-content:space-between;align-items:center;padding:8px 16px;background:#fafbfc;border-top:1px solid var(--border-light);}
.ctool{display:flex;gap:16px;color:var(--text-secondary);font-size:18px;}
.ctool i{cursor:pointer;transition:all .2s;padding:4px;}
.ctool i:hover{color:var(--accent-blue);transform:scale(1.1);}
.ctool i.emoji-active{color:var(--accent-blue);}
.btn-send{padding:8px 24px;background:#cbd5e1;color:#fff;border:none;border-radius:10px;font-size:13px;font-weight:600;cursor:not-allowed;transition:all .2s;}
.btn-send.active{background:var(--accent-blue);cursor:pointer;}
.btn-send.active:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(37,99,235,.3);}
.sc-popup{display:none;position:absolute;bottom:calc(100% + 8px);left:0;right:0;background:#fff;border:1px solid var(--border-color);border-radius:12px;box-shadow:0 10px 25px rgba(0,0,0,.1);z-index:20;max-height:240px;overflow-y:auto;}
.sc-item{padding:12px 16px;border-bottom:1px solid var(--border-light);cursor:pointer;transition:background .2s;}
.sc-item:hover{background:#f8fafc;}
.sc-cmd{display:block;color:var(--accent-blue);font-weight:700;font-size:12px;margin-bottom:4px;}
.sc-msg{display:block;color:var(--text-muted);font-size:11px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}

/* EMOJI PICKER */
.emoji-picker{display:none;position:absolute;bottom:calc(100% + 6px);left:20px;width:310px;background:#fff;border:1px solid var(--border-color);border-radius:16px;box-shadow:0 12px 32px rgba(0,0,0,.12);z-index:100;flex-direction:column;overflow:hidden;animation:epUp .15s ease;}
.emoji-picker.show{display:flex;}
@keyframes epUp{from{opacity:0;transform:translateY(8px);}to{opacity:1;transform:translateY(0);}}
.ep-search{padding:10px 12px;border-bottom:1px solid var(--border-light);}
.ep-search input{width:100%;border:1px solid var(--border-color);border-radius:10px;padding:8px 12px;font-size:13px;outline:none;background:#f8fafc;color:var(--text-light);transition:all .2s;}
.ep-search input:focus{border-color:var(--accent-blue);box-shadow:0 0 0 3px rgba(37,99,235,.1);}
.ep-cats{display:flex;gap:2px;padding:8px 10px;border-bottom:1px solid var(--border-light);overflow-x:auto;}
.ep-cats::-webkit-scrollbar{display:none;}
.ep-cat{background:none;border:none;cursor:pointer;font-size:18px;padding:5px 7px;border-radius:8px;transition:background .15s;flex-shrink:0;}
.ep-cat:hover,.ep-cat.active{background:#f1f5f9;}
.ep-grid{display:grid;grid-template-columns:repeat(8,1fr);gap:2px;padding:10px;max-height:180px;overflow-y:auto;scrollbar-width:thin;}
.ep-grid::-webkit-scrollbar{width:4px;}
.ep-grid::-webkit-scrollbar-thumb{background:#e2e8f0;border-radius:2px;}
.ep-btn{background:none;border:none;cursor:pointer;font-size:20px;padding:5px;border-radius:7px;transition:all .15s;text-align:center;line-height:1;}
.ep-btn:hover{background:#f1f5f9;transform:scale(1.15);}

/* Toast baru di area pesan */
.new-msg-toast{display:none;position:absolute;bottom:140px;left:50%;transform:translateX(-50%);background:#1e293b;color:#fff;font-size:12px;font-weight:600;padding:8px 18px;border-radius:24px;align-items:center;gap:8px;cursor:pointer;z-index:50;box-shadow:0 4px 14px rgba(0,0,0,.2);white-space:nowrap;animation:toastSlide .25s ease;}
.new-msg-toast.show{display:flex;}

/* RIGHT PANEL */
.panel-info{width:300px;flex-shrink:0;border-left:1px solid var(--border-light);background:var(--panel-bg);overflow-y:auto;transition:transform .3s cubic-bezier(0.2,0.9,0.4,1.1);z-index:30;}
.vp-head{text-align:center;padding:24px 20px 20px;border-bottom:1px solid var(--border-light);}
.vp-ava{width:72px;height:72px;border-radius:50%;color:#fff;font-size:28px;font-weight:700;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;box-shadow:0 4px 12px rgba(0,0,0,.15);}
#visitorMap{height:120px;width:100%;border-radius:12px;margin-top:12px;overflow:hidden;}
.acc-item{border-bottom:1px solid var(--border-light);}
.acc-hdr{padding:14px 18px;display:flex;justify-content:space-between;align-items:center;cursor:pointer;font-size:13px;font-weight:600;color:#1e293b;transition:background .2s;}
.acc-hdr:hover{background:#f8fafc;}
.acc-hdr i{transition:transform .3s;color:var(--text-secondary);}
.acc-item.open .acc-hdr i{transform:rotate(180deg);}
.acc-body{display:none;padding:0 18px 16px;font-size:12px;color:var(--text-muted);}
.acc-item.open .acc-body{display:block;}
.info-row{display:flex;justify-content:space-between;margin-bottom:10px;font-size:12px;}
.info-label{color:var(--text-muted);font-weight:500;}
.info-value{color:var(--text-light);font-weight:600;text-align:right;}
.tag-badge{display:inline-block;background:#e0e7ff;color:#4338ca;font-size:10px;font-weight:700;padding:3px 10px;border-radius:20px;margin:2px;}

/* Context Menu */
.context-menu{position:fixed;background:#fff;border:1px solid var(--border-color);border-radius:12px;box-shadow:0 10px 25px rgba(0,0,0,.15);z-index:10000;min-width:160px;overflow:hidden;animation:menuFadeIn .15s ease;}
@keyframes menuFadeIn{from{opacity:0;transform:scale(.95);}to{opacity:1;transform:scale(1);}}
.context-menu-item{padding:12px 16px;cursor:pointer;transition:background .2s;font-size:13px;display:flex;align-items:center;gap:10px;}
.context-menu-item:hover{background:#f1f5f9;}
.context-menu-item.danger{color:#dc2626;}
.context-menu-item.danger:hover{background:#fef2f2;}

/* Toast Notification */
.toast-notification{position:fixed;bottom:24px;right:24px;background:#1e293b;color:#fff;padding:12px 20px;border-radius:12px;font-size:13px;font-weight:500;z-index:10001;animation:toastSlide .3s ease;box-shadow:0 4px 12px rgba(0,0,0,.2);display:flex;align-items:center;gap:10px;}
.toast-notification.success{background:#10b981;}
.toast-notification.error{background:#ef4444;}
.toast-notification.warning{background:#f59e0b;}
@keyframes toastSlide{from{opacity:0;transform:translateX(100px);}to{opacity:1;transform:translateX(0);}}

/* Empty State */
.empty-chat{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;color:var(--text-muted);text-align:center;padding:60px 20px;}
.empty-chat i{font-size:64px;margin-bottom:20px;color:#cbd5e1;}
.empty-chat h3{font-size:18px;font-weight:600;color:var(--text-light);margin-bottom:8px; }
.closed-bar{background:#f8fafc;border-top:1px solid var(--border-light);padding:14px 20px;text-align:center;color:var(--text-muted);font-size:13px;font-weight:500;}

/* Modal */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);backdrop-filter:blur(4px);z-index:2000;align-items:center;justify-content:center;}
.modal-overlay.show{display:flex;}
.modal-box{background:#fff;border-radius:24px;padding:28px;width:90%;max-width:420px;box-shadow:0 25px 50px rgba(0,0,0,.25);}
.modal-box h3{font-size:20px;font-weight:700;margin-bottom:20px;}
.mform-label{display:block;margin-bottom:6px;font-size:13px;font-weight:600;color:var(--text-light);}
.mform-inp{width:100%;padding:10px 14px;border:1px solid var(--border-color);border-radius:12px;font-size:14px;outline:none;transition:all .2s;}
.mform-inp:focus{border-color:var(--accent-blue);box-shadow:0 0 0 3px rgba(37,99,235,.1);}
.mob-overlay-bg{display:none;position:absolute;inset:0;background:rgba(0,0,0,.3);z-index:25;}
.mob-overlay-bg.show{display:block;}

/* Responsive */
@media(max-width:768px){
    .main-content-wrapper{padding-bottom:75px;}
    .panel-list{position:fixed;top:0;left:0;bottom:0;width:85%;max-width:320px;transform:translateX(-100%);box-shadow:0 0 40px rgba(0,0,0,.2);z-index:40;transition:transform .3s cubic-bezier(0.2,0.9,0.4,1.1);}
    .panel-list.mo{transform:translateX(0);}
    .panel-info{position:fixed;top:0;right:0;bottom:0;width:85%;max-width:320px;transform:translateX(100%);z-index:40;transition:transform .3s cubic-bezier(0.2,0.9,0.4,1.1);}
    .panel-info.mo{transform:translateX(0);}
    .panel-chat{width:100%;height:100%;}
    .panel-list-header{padding:16px 18px 14px;}
    .panel-list-header h2{font-size:16px;}
    .panel-list-header button{width:40px!important;height:40px!important;}
    .inbox-controls{padding:10px 14px;}
    .chat-acts button span{display:none;}
    .btn-mobile-panel{display:flex!important;}
    .ci{padding:12px 14px;}
    .ci-ava{width:38px;height:38px;font-size:14px;}
    .chat-hdr{padding:8px 12px;}
    .visitor-name{font-size:14px;}
    .btn-mobile-panel{width:34px;height:34px;font-size:15px;}
    .chat-hdr-left{gap:8px;}
    .visitor-status-badge{font-size:10px;margin-top:1px;}
    .mode-bar{padding:6px 12px;gap:6px;}
    .mode-btn{padding:5px 10px;font-size:11px;border-radius:20px;}
    .mode-btn span{display:none;}
    .messages-area{padding:14px 16px;gap:12px;}
    .message-row{max-width:92%;}
    .message-bubble{padding:9px 14px;font-size:13px;}
    .composer-wrap{padding:10px 14px 18px;}
    .canned-row{gap:8px;margin-bottom:10px;}
    .cpill{font-size:11px;padding:5px 12px;}
    .composer-box textarea{padding:10px 14px;font-size:13px;min-height:42px;}
    .composer-toolbar{padding:6px 12px;}
    .ctool{font-size:16px;gap:12px;}
    .btn-send{padding:6px 18px;font-size:12px;}
    .emoji-picker{left:0;width:100%;border-radius:16px 16px 0 0;bottom:100%;max-height:50vh;}
    .ep-grid{max-height:140px;}
    .panel-info .vp-head{padding:16px 18px 14px;}
    .panel-info .vp-ava{width:56px;height:56px;font-size:22px;}
    .panel-info > div:first-child button{width:40px!important;height:40px!important;}
    .acc-hdr{padding:12px 16px;}
    .acc-body{padding:0 16px 12px;}
    .pre-chat-card{padding:14px 16px;max-width:100%;}
    .btn-close-item{opacity:1;background:#fef2f2;border-color:#fecaca;}
}
@media(min-width:769px){.mob-overlay-bg{display:none!important;}}
/* Container untuk waktu dan tombol */
.ci-meta {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: flex-end;
}

/* Style default tombol close di list */
.btn-close-item {
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bg-light, #f1f5f9);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    width: 26px;
    height: 26px;
    cursor: pointer;
    color: #ef4444; /* Warna merah */
    opacity: 0; /* Sembunyi */
    transition: all 0.2s ease;
    position: absolute;
    right: 0;
    z-index: 2;
}

.btn-close-item:hover {
    background: #ef4444;
    color: #fff;
    border-color: #ef4444;
}

/* Efek Hover pada Item Chat */
.ci:hover .btn-close-item {
    opacity: 1; /* Tampilkan tombol */
    transform: translateX(0);
}

/* Sembunyikan waktu saat tombol muncul agar tidak bertumpuk (opsional) */
.ci:hover .ci-time {
    opacity: 0;
    transform: translateX(-10px);
}

.ci-time {
    transition: all 0.2s ease;
}
</style>

<div class="chat-columns">
<div class="mob-overlay-bg" id="mobOverlay" onclick="closePanels()" ontouchend="closePanels()"></div>

<!-- LEFT PANEL -->
<div class="panel-list" id="panelList">
    <div class="panel-list-header">
        <h2><i class="fas fa-comments" style="color:var(--accent-blue);"></i> Inbox <?php if($totalUnread > 0): ?><span class="unread-count" id="headerUnread"><?= $totalUnread ?></span><?php endif; ?></h2>
        <button onclick="closePanels()" ontouchend="closePanels()" style="background:none;border:1px solid var(--border-color);border-radius:8px;width:32px;height:32px;cursor:pointer;color:var(--text-muted);display:flex;align-items:center;justify-content:center;"><i class="fas fa-times"></i></button>
    </div>
    <div class="inbox-controls">
        <div class="inbox-search"><i class="fas fa-search"></i><input type="text" id="inboxSearch" placeholder="Cari chat..."></div>
        <div class="inbox-meta">
            <span><i class="far fa-message"></i> <span id="chatCount"><?= count($conversations) ?></span> percakapan</span>
            <select id="inboxSort" class="inbox-sort"><option value="newest">Terbaru</option><option value="oldest">Terlama</option></select>
        </div>
    </div>
    <div class="chat-list" id="chatList">
        <?php if (empty($conversations)): ?>
        <div style="padding:40px;text-align:center;color:var(--text-muted);"><i class="fas fa-comment-dots" style="font-size:40px;display:block;margin-bottom:12px;"></i>Tidak ada percakapan aktif</div>
        <?php endif; ?>
        <?php foreach ($conversations as $chat):
            $avatarColor = getAvatarColor($chat['visitor_name']);
            $hasUnread   = $chat['unread_count'] > 0;
            $lastMsgTime = !empty($chat['last_message_time']) ? timeAgo($chat['last_message_time']) : '';
        ?>
        <a href="?conv=<?= $chat['id'] ?>" class="ci <?= ($activeConvId == $chat['id']) ? 'active' : '' ?>"
           data-id="<?= $chat['id'] ?>" data-name="<?= htmlspecialchars(strtolower($chat['visitor_name'])) ?>"
           data-preview="<?= htmlspecialchars(strtolower($chat['last_message'] ?? '')) ?>"
           data-time="<?= strtotime($chat['last_message_at']) ?>">
            <div class="ci-ava" style="background:<?= $avatarColor ?>"><?= strtoupper(substr($chat['visitor_name'],0,1)) ?></div>
            <div class="ci-body">
               <div class="ci-top">
    <span class="ci-name"><?= htmlspecialchars($chat['visitor_name']) ?></span>
    <div class="ci-meta">
        <span class="ci-time"><?= $lastMsgTime ?></span>
        <button class="btn-close-item" onclick="event.preventDefault(); closeChatFromContext(<?= $chat['id'] ?>)" title="Tutup Chat">
            <i class="fas fa-times"></i>
        </button>
    </div>
</div>
                <div class="ci-preview">
                    <?php if ($hasUnread): ?><span class="unread-dot"></span><?php else: ?><i class="fas fa-check-double" style="font-size:10px;"></i><?php endif; ?>
                    <?= htmlspecialchars(substr($chat['last_message'] ?? 'Mulai percakapan...', 0, 40)) ?>
                </div>
            </div>
            
            <?php if ($chat['unread_count'] > 0): ?>
            <span class="unread-count"><?= $chat['unread_count'] > 9 ? '9+' : $chat['unread_count'] ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- MIDDLE PANEL -->
<div class="panel-chat">
<?php if ($activeConv):
    $visitorColor = getAvatarColor($activeConv['username']);
?>
<div class="mode-bar">
    <?php $rm = $agent['reply_mode'] ?? 'manual';
    foreach (['manual'=>['fa-user','Manual'],'bot'=>['fa-robot','Bot'],'ai'=>['fa-brain','AI'],'hybrid'=>['fa-shuffle','Hybrid']] as $mk=>[$ic,$ml]): ?>
    <button class="mode-btn <?= $rm===$mk?'am':'' ?>" data-mode="<?= $mk ?>"><i class="fas <?= $ic ?>"></i><span><?= $ml ?></span></button>
    <?php endforeach; ?>
</div>

<div class="chat-hdr">
    <div class="chat-hdr-left">
        <button class="btn-mobile-panel" onclick="togglePanel('list')"><i class="fas fa-bars"></i></button>
        <button class="btn-mobile-panel" onclick="togglePanel('info')"><i class="fas fa-user-circle"></i></button>
        <div class="visitor-status">
            <div class="visitor-name"><?= htmlspecialchars($activeConv['username']) ?></div>
            <div class="visitor-status-badge <?= $activeConv['conv_status'] === 'closed' ? 'offline' : 'online' ?>">
                <i class="fas fa-circle" style="font-size:8px;"></i> <?= $activeConv['conv_status'] === 'closed' ? 'Offline' : 'Online' ?>
            </div>
        </div>
    </div>
    <div style="display:flex;align-items:center;gap:8px;">
        <!--<button class="btn-mobile-panel" onclick="togglePanel('info')"><i class="fas fa-user-circle"></i></button>-->
        <?php if ($activeConv['conv_status'] !== 'closed'): ?>
        <div class="chat-acts">
            <button class="btn-xfer" onclick="transferChat()"><i class="fas fa-exchange-alt"></i><span> Transfer</span></button>
            <button class="btn-close-c" id="closeChatBtn"><i class="fas fa-times-circle"></i><span> Tutup</span></button>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="messages-area" id="messagesArea">
    <div class="msg-divider"><span><i class="far fa-calendar-alt"></i> <?= date('d M Y, H:i', strtotime($activeConv['created_at'])) ?></span></div>
    <?php
    $tagsData  = json_decode($activeConv['tags'] ?? '{}', true);
    $issueType = !empty($tagsData['issue_type']) ? implode(' / ', array_map('ucfirst', $tagsData['issue_type'])) : 'General';
    ?>
    <div class="pre-chat-card">
        <strong><i class="fas fa-clipboard-list" style="margin-right:8px;color:var(--accent-blue);"></i>Data Pengunjung</strong>
        <div class="pci"><span class="pci-label">Username</span><span class="pci-val"><?= htmlspecialchars($activeConv['username']) ?></span></div>
        <div class="pci"><span class="pci-label">WhatsApp</span><span class="pci-val accent"><i class="fab fa-whatsapp"></i> <?= htmlspecialchars($activeConv['phone'] ?? '-') ?></span></div>
        <div class="pci"><span class="pci-label">Topik</span><span class="pci-val"><?= htmlspecialchars($issueType) ?></span></div>
    </div>

    <?php foreach ($messages as $msg):
        $tf = date('H:i', strtotime($msg['created_at']));
        $tp = $msg['sender_type'];
    ?>
    <div class="message-row <?= $tp ?>">
        <?php if ($tp === 'visitor'): ?>
        <div class="chat-ava" style="background:<?= $visitorColor ?>"><?= strtoupper(substr($activeConv['username'],0,1)) ?></div>
        <?php elseif ($tp === 'agent'): ?>
        <div class="chat-ava agent-ava"><img src="<?= htmlspecialchars($uAva) ?>" alt="A"></div>
        <?php endif; ?>
        <div class="message-content">
            <div class="message-bubble" title="<?= $tf ?>">
                <?php if ($msg['file_url']):
                    $fu = htmlspecialchars($msg['file_url']);
                    if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $msg['file_url'])):
                        echo '<img src="'.$fu.'" class="msg-img" onclick="window.open(this.src,\'_blank\')">';
                    else:
                        echo '<a href="'.$fu.'" target="_blank" style="color:inherit;font-weight:600;"><i class="fas fa-file-download"></i> Download File</a>';
                    endif;
                else:
                    echo nl2br(htmlspecialchars($msg['content']));
                endif; ?>
            </div>
            <div class="time-stamp">
                <i class='far fa-check-circle' style='color:green'></i> <?= $tf ?>
                <?php if(in_array($tp,['bot','ai'])): ?><i class="fas fa-robot"></i> Bot<?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="typing-wrap" id="typingIndicator">
        <div class="chat-ava" style="background:<?= $visitorColor ?>"><?= strtoupper(substr($activeConv['username'],0,1)) ?></div>
        <div class="typing-bubble">
            <div class="typing-dots"><span></span><span></span><span></span></div>
            <span id="sneakPeek" style="margin-left:6px;"></span>
        </div>
    </div>
</div>

<!-- Toast pesan baru -->
<div class="new-msg-toast" id="newMsgToast" onclick="scrollToBottom(true)">
    <i class="fas fa-arrow-down"></i> Pesan baru
</div>

<?php if ($activeConv['conv_status'] !== 'closed'): ?>
<div class="composer-wrap" id="composerWrap">
    <!-- Emoji Picker -->
    <div class="emoji-picker" id="emojiPicker"></div>

    <div class="canned-row">
        <div class="cpill add" onclick="document.getElementById('scModal').classList.add('show')"><i class="fas fa-plus"></i> Tambah</div>
        <?php foreach ($cannedResponses as $cr): ?>
        <div class="cpill" onclick="insertSc(<?= json_encode($cr['message']) ?>)"><?= htmlspecialchars($cr['shortcut']) ?></div>
        <?php endforeach; ?>
    </div>
    <div style="position:relative; padding-bottom:50px;">
        <div class="sc-popup" id="scPopup"></div>
        <div class="composer-box">
            <textarea id="msgInput" placeholder="Ketik pesan... (Ctrl+K untuk shortcut, Enter untuk kirim)"></textarea>
            <div class="composer-toolbar">
           <div class="ctool">
                    <input type="file" id="fileUp" style="display:none;" accept="image/*,application/pdf">
                    <i class="fas fa-paperclip" id="attachBtn" title="Lampirkan file"></i>
                    <i class="far fa-smile" id="emojiBtnIcon" title="Emoji" style="cursor:pointer;"></i>
                </div>
                <button class="btn-send" id="sendBtn" disabled><i class="fas fa-paper-plane"></i> Kirim</button>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
<div class="closed-bar"><i class="fas fa-lock" style="margin-right:8px;"></i>Percakapan telah ditutup - hanya dapat dibaca</div>
<?php endif; ?>

<?php else: ?>
<div class="empty-chat">
    <i class="fas fa-comment-dots"></i>
    <h3>Tidak ada percakapan dipilih</h3>
    <p>Pilih chat dari daftar di samping</p>
</div>
<?php endif; ?>
</div>
<?php if ($activeConv): ?>
<div class="panel-info" id="panelInfo">
    <div style="display:flex;justify-content:flex-end;padding:12px 12px 0;">
        <button onclick="closePanels()" ontouchend="closePanels()" style="background:none;border:1px solid var(--border-color);border-radius:8px;width:32px;height:32px;cursor:pointer;"><i class="fas fa-times"></i></button>
    </div>

    <div class="vp-head" style="text-align:center; padding-bottom: 20px;">
        <div class="vp-ava" style="background:<?= $visitorColor ?>; margin: 0 auto 10px;"><?= strtoupper(substr($activeConv['username'],0,1)) ?></div>
        <div style="font-weight:800;font-size:16px;"><?= htmlspecialchars($activeConv['username']) ?></div>
        <div style="font-size:13px;color:var(--text-muted);margin-top:4px;"><?= htmlspecialchars($activeConv['phone'] ?: 'No Email/Phone') ?></div>
        <div style="font-size:12px;color:var(--text-muted);margin-top:2px;">
            <?= htmlspecialchars(($activeConv['city']??'?').', '.($activeConv['region']??'?').', '.($activeConv['country']??'?')) ?>
        </div>
        <div style="font-size:11px;color:var(--text-secondary);margin-top:5px;"><?= htmlspecialchars($activeConv['first_visit']) ?></div>
        
        <div id="visitorMap" style="width:100%; height:140px; margin-top:15px; border-radius:8px; overflow:hidden;"></div>
    </div>

    <div class="acc-item open">
        <div class="acc-hdr" style="display:flex; justify-content:space-between; align-items:center;">
            <span style="font-weight:700; font-size:14px;">Additional info</span>
            <i class="fas fa-chevron-down" style="font-size:12px;"></i>
        </div>
        <div class="acc-body" style="padding: 10px 0;">
            <div class="info-row" style="margin-bottom:8px;">
                <span style="color:var(--text-secondary);">Returning visitor:</span> 
                <span style="margin-left:5px;"><?= $activeConv['visit_count'] ?? 1 ?> visits, 1 chat</span>
            </div>
            <div class="info-row" style="margin-bottom:8px;">
                <span style="color:var(--text-secondary);">Last seen:</span> 
                <span style="margin-left:5px;">today</span>
            </div>
            <div class="info-row" style="margin-bottom:8px;">
                <span style="color:var(--text-secondary);">Came from:</span> 
                <span style="margin-left:5px; color:var(--accent-blue);"><?= parse_url($activeConv['source_url']??'', PHP_URL_HOST) ?: 'Direct' ?></span>
            </div>
            <!--<div class="info-row" style="margin-bottom:8px;">-->
            <!--    <span style="color:var(--text-secondary);">Chat duration:</span> -->
            <!--    <span style="margin-left:5px;">15m 16s <i class="far fa-question-circle" style="font-size:10px;"></i></span>-->
            <!--</div>-->
            <div class="info-row">
                <span style="color:var(--text-secondary);">Groups:</span> 
                <span style="margin-left:5px;"><span style="background:#3b82f6; color:white; padding:1px 4px; border-radius:3px; font-size:10px; font-weight:bold;">G</span> General</span>
            </div>
        </div>
    </div>

    <div class="acc-item open">
        <div class="acc-hdr" style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid var(--border-color); padding-top:15px;">
            <span style="font-weight:700; font-size:14px;">Technology</span>
            <i class="fas fa-chevron-down" style="font-size:12px;"></i>
        </div>
        <div class="acc-body" style="padding: 10px 0;">
            <div class="info-row" style="margin-bottom:8px;">
                <span style="color:var(--text-secondary);">IP address:</span> 
                <span style="margin-left:5px;"><?= htmlspecialchars($activeConv['ip_address']??'-') ?></span>
            </div>
            <div class="info-row" style="margin-bottom:8px;">
                <span style="color:var(--text-secondary);">OS/Device:</span> 
                <span style="margin-left:5px;">
                    <i class="fab fa-android"></i> Android (6.0)
                </span>
            </div>
            <div class="info-row">
                <span style="color:var(--text-secondary);">Browser:</span> 
                <span style="margin-left:5px;">
                    <img src="https://www.google.com/s2/favicons?domain=chrome.com" style="width:12px; vertical-align:middle;"> Chrome (148.0.0.0) <i class="far fa-info-circle" style="font-size:10px;"></i>
                </span>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- MODALS -->
<!-- Confirm Modal -->
<div class="modal-overlay" id="confirmModal">
    <div class="modal-box" style="text-align:center;">
        <div style="width:56px;height:56px;border-radius:50%;background:#fef2f2;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
            <i class="fas fa-exclamation-triangle" style="color:#ef4444;font-size:24px;"></i>
        </div>
        <h3 style="margin-bottom:8px;" id="confirmTitle">Konfirmasi</h3>
        <p style="color:var(--text-muted);font-size:14px;margin-bottom:24px;" id="confirmDesc">Apakah Anda yakin?</p>
        <div style="display:flex;gap:12px;justify-content:center;">
            <button onclick="closeConfirmModal()" style="flex:1;padding:12px;border:1px solid var(--border-color);border-radius:12px;background:#fff;cursor:pointer;font-weight:600;color:var(--text-light);">Batal</button>
            <button id="confirmOkBtn" style="flex:1;padding:12px;background:#ef4444;color:#fff;border:none;border-radius:12px;cursor:pointer;font-weight:600;">Ya, Tutup</button>
        </div>
    </div>
</div>

<!-- Shortcut Modal -->
<div class="modal-overlay" id="scModal">
    <div class="modal-box">
        <h3><i class="fas fa-bolt" style="color:var(--accent-blue);margin-right:8px;"></i>Tambah Balasan Cepat</h3>
        <div style="margin-bottom:16px;"><label class="mform-label">Perintah (contoh: /help)</label><input type="text" id="scCmd" placeholder="/greet" class="mform-inp"></div>
        <div><label class="mform-label">Pesan</label><textarea id="scMsg" rows="3" placeholder="Isi pesan..." class="mform-inp" style="resize:none;"></textarea></div>
        <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:24px;">
            <button onclick="document.getElementById('scModal').classList.remove('show')" style="padding:10px 20px;border:1px solid var(--border-color);border-radius:12px;background:#fff;cursor:pointer;">Batal</button>
            <button onclick="saveShortcut()" style="padding:10px 24px;background:var(--accent-blue);color:#fff;border:none;border-radius:12px;cursor:pointer;font-weight:600;">Simpan</button>
        </div>
    </div>
</div>

<!-- Transfer Modal -->
<div class="modal-overlay" id="transferModal">
    <div class="modal-box">
        <h3><i class="fas fa-exchange-alt" style="color:var(--accent-blue);margin-right:8px;"></i>Transfer Chat</h3>
        <p style="color:var(--text-muted);font-size:14px;margin-bottom:16px;">Pilih agen tujuan:</p>
        <div id="transferList" style="display:flex;flex-direction:column;gap:8px;margin-bottom:20px;max-height:240px;overflow-y:auto;">
        </div>
        <div style="display:flex;gap:12px;justify-content:flex-end;">
            <button onclick="document.getElementById('transferModal').classList.remove('show')" style="padding:10px 20px;border:1px solid var(--border-color);border-radius:12px;background:#fff;cursor:pointer;">Batal</button>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// ============================================================
// VARS
// ============================================================
const convId    = <?= $activeConvId ? (int)$activeConvId : 'null' ?>;
const myAid     = <?= (int)$agentId ?>;
const myAva     = <?= json_encode($uAva) ?>;
const visInit   = <?= json_encode($activeConv ? strtoupper(substr($activeConv['username'],0,1)) : '') ?>;
const visColor  = <?= json_encode($activeConv ? getAvatarColor($activeConv['username']) : '#10b981') ?>;
const allAgents = <?= json_encode($allAgents) ?>;
const csrfToken = <?= json_encode($auth->getCsrfToken()) ?>;

function addCsrf(data) {
    if (data instanceof FormData) {
        data.append('csrf_token', csrfToken);
    }
    return data;
}

function stringToColor(name) {
    const colors = ['#10b981','#3b82f6','#8b5cf6','#ec489a','#f59e0b','#ef4444','#06b6d4','#84cc16','#d946ef','#14b8a6','#f97316','#6366f1','#a855f7','#22c55e','#eab308'];
    let hash = 0;
    for (let i = 0; i < name.length; i++) hash += name.charCodeAt(i);
    return colors[hash % colors.length];
}

function escapeHtml(text) {
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
}

let lastId       = <?= (int)$lastMessageId ?>;
let closed       = <?= ($activeConv && $activeConv['conv_status'] === 'closed') ? 'true' : 'false' ?>;
let pollTimer    = null;
let pollRunning  = false;
let pollFails    = 0;

const area     = document.getElementById('messagesArea');
const inp      = document.getElementById('msgInput');
const sBtn     = document.getElementById('sendBtn');
const tWrap    = document.getElementById('typingIndicator');
const sPeek    = document.getElementById('sneakPeek');
const fUp      = document.getElementById('fileUp');
const attachBtn= document.getElementById('attachBtn');
const scPopup  = document.getElementById('scPopup');
const chatList = document.getElementById('chatList');

// ============================================================
// MOBILE PANELS
// ============================================================
function getPanel(id) { return document.getElementById(id); }
function panelOpen() { return getPanel('panelList')?.classList.contains('mo') || getPanel('panelInfo')?.classList.contains('mo'); }

function togglePanel(side) {
    if (window.innerWidth > 768) return;
    const pl = getPanel('panelList'), pi = getPanel('panelInfo'), mo = getPanel('mobOverlay');
    if (side === 'list') { pl?.classList.toggle('mo'); pi?.classList.remove('mo'); }
    else                 { pi?.classList.toggle('mo'); pl?.classList.remove('mo'); }
    mo?.classList.toggle('show', panelOpen());
}
function closePanels() {
    getPanel('panelList')?.classList.remove('mo');
    getPanel('panelInfo')?.classList.remove('mo');
    getPanel('mobOverlay')?.classList.remove('show');
}

// ============================================================
// ACCORDION
// ============================================================
document.querySelectorAll('.acc-hdr').forEach(h => h.addEventListener('click', () => h.parentElement.classList.toggle('open')));

// ============================================================
// INBOX SEARCH & SORT
// ============================================================
const iSearch  = document.getElementById('inboxSearch');
const iSort    = document.getElementById('inboxSort');
const chatCount= document.getElementById('chatCount');

function filterChats() {
    if (!chatList) return;
    const term = (iSearch?.value || '').toLowerCase();
    const dir  = iSort?.value || 'newest';
    let items  = [...chatList.querySelectorAll('.ci')];
    let visible = 0;
    items.forEach(item => {
        const match = (item.dataset.name||'').includes(term) || (item.dataset.preview||'').includes(term);
        item.style.display = match ? 'flex' : 'none';
        if (match) visible++;
    });
    if (chatCount) chatCount.textContent = visible;
    items.sort((a, b) => {
        const tA = parseInt(a.dataset.time)||0, tB = parseInt(b.dataset.time)||0;
        return dir === 'newest' ? tB - tA : tA - tB;
    });
    items.forEach(item => chatList.appendChild(item));
}
iSearch?.addEventListener('input', filterChats);
iSort?.addEventListener('change', filterChats);

// ============================================================
// REPLY MODE
// ============================================================
document.querySelectorAll('.mode-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.mode-btn').forEach(b => b.classList.remove('am'));
        this.classList.add('am');
        const fd = new FormData(); fd.append('action','set_reply_mode'); fd.append('mode', this.dataset.mode);
        addCsrf(fd); fetch('chats', { method:'POST', body:fd }).catch(()=>{});
        showToast('Mode: ' + this.dataset.mode, 'success');
    });
});

// ============================================================
// TOAST
// ============================================================
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast-notification ${type}`;
    const icon = type==='success' ? 'fa-check-circle' : type==='error' ? 'fa-exclamation-circle' : type==='warning' ? 'fa-exclamation-triangle' : 'fa-info-circle';
    toast.innerHTML = `<i class="fas ${icon}"></i> ${message}`;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

// Universal fetch wrapper — auto-toast SEMUA hasil eksekusi
async function apiFetch(url, opts = {}) {
    try {
        const res = await fetch(url, opts);
        const ct = res.headers.get('content-type') || '';
        if (!ct.includes('application/json')) {
            showToast('Response error: ' + res.status, 'error');
            return null;
        }
        const d = await res.json();
        if (d.success === false || d.error) {
            showToast(d.error || d.message || 'Gagal', 'error');
        } else if (d.success === true && d.toast) {
            showToast(d.toast, 'success');
        }
        return d;
    } catch(e) {
        showToast('Koneksi error: ' + e.message, 'error');
        return null;
    }
}

// ============================================================
// CONTEXT MENU
// ============================================================
// ============================================================
// CONFIRM MODAL
// ============================================================
let confirmCallback = null;

function confirmAction(title, desc, callback) {
    document.getElementById('confirmTitle').textContent = title;
    document.getElementById('confirmDesc').textContent = desc;
    confirmCallback = callback;
    document.getElementById('confirmModal').classList.add('show');
}

function closeConfirmModal() {
    document.getElementById('confirmModal').classList.remove('show');
    confirmCallback = null;
}

document.getElementById('confirmOkBtn')?.addEventListener('click', function() {
    if (confirmCallback) confirmCallback();
    closeConfirmModal();
});

function closeContextMenu() { document.querySelector('.context-menu')?.remove(); }

function showContextMenu(e, chatId) {
    e.preventDefault();
    closeContextMenu();
    const menu = document.createElement('div');
    menu.className = 'context-menu';
    menu.style.left = e.pageX + 'px';
    menu.style.top  = e.pageY + 'px';
    menu.innerHTML  = `<div class="context-menu-item danger" onclick="closeChatFromContext(${chatId})"><i class="fas fa-times-circle"></i> Tutup Percakapan</div>`;
    document.body.appendChild(menu);
    setTimeout(() => document.addEventListener('click', closeContextMenu, { once:true }), 10);
}

function closeChatFromContext(chatId) {
    closeContextMenu();
    confirmAction('Tutup Percakapan', 'Tutup percakapan ini?', function() {
        const fd = new FormData(); fd.append('action','close_chat_ajax'); fd.append('conversation_id', chatId);
        addCsrf(fd); fetch('chats', { method:'POST', body:fd }).then(r=>r.json()).then(d => {
            if (d.success) {
                showToast('Percakapan ditutup', 'success');
                document.querySelector(`.ci[data-id="${chatId}"]`)?.remove();
                refreshTotalUnread();
                if (chatId == convId) setTimeout(() => location.href = 'chats', 600);
            } else showToast('Gagal menutup', 'error');
        });
    });
}

document.querySelectorAll('.ci').forEach(item => {
    item.addEventListener('contextmenu', e => showContextMenu(e, item.dataset.id));
    let timer;
    item.addEventListener('touchstart', e => { timer = setTimeout(() => showContextMenu(e.touches[0], item.dataset.id), 500); });
    item.addEventListener('touchend',   () => clearTimeout(timer));
    item.addEventListener('touchmove',  () => clearTimeout(timer));
});

// ============================================================
// MAP
// ============================================================
<?php if($activeConv): ?>
if (document.getElementById('visitorMap')) {
    const lat = <?= floatval($activeConv['latitude']??-6.2088) ?>, lng = <?= floatval($activeConv['longitude']??106.8456) ?>;
    const map = L.map('visitorMap', {zoomControl:false, dragging:false, scrollWheelZoom:false}).setView([lat,lng], 10);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
    L.marker([lat,lng]).addTo(map);
}
<?php endif; ?>

// ============================================================
// SCROLL
// ============================================================
function isAtBottom() { return area ? (area.scrollHeight - area.scrollTop - area.clientHeight < 80) : true; }
function scrollToBottom(force = false) { if (area && (force || isAtBottom())) area.scrollTop = area.scrollHeight; }
setTimeout(() => scrollToBottom(true), 120);

// Toast pesan baru (muncul saat scroll tidak di bawah)
const newMsgToast = document.getElementById('newMsgToast');
if (newMsgToast) newMsgToast.onclick = () => { scrollToBottom(true); newMsgToast.classList.remove('show'); };
let toastTimer;
function triggerNewMsgToast() {
    if (!newMsgToast) return;
    if (isAtBottom()) { scrollToBottom(true); return; }
    newMsgToast.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => newMsgToast.classList.remove('show'), 5000);
}

// ============================================================
// APPEND MESSAGE
// ============================================================
function appendMsg(type, content, fu = null, createdAt = null) {
    if (!area) return;
    const t = createdAt
        ? new Date(createdAt).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})
        : new Date().toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
    const row = document.createElement('div');
    row.className = `message-row ${type}`;
    let inner = '';
    if (fu) {
        inner = /\.(jpeg|jpg|gif|png|webp)$/i.test(fu)
            ? `<img src="${fu}" class="msg-img" onclick="window.open(this.src,'_blank')">`
            : `<a href="${fu}" target="_blank" style="color:inherit;font-weight:600;"><i class="fas fa-file-download"></i> Download File</a>`;
    } else {
        // Escape HTML lalu render baris baru; emoji unicode tampil apa adanya
        inner = content.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');
    }
    let avaHtml = '';
    if (type === 'visitor')     avaHtml = `<div class="chat-ava" style="background:${visColor}">${visInit}</div>`;
    else if (type === 'agent')  avaHtml = `<div class="chat-ava agent-ava"><img src="${myAva}" alt="A"></div>`;
    const botIcon = ['bot','ai'].includes(type) ? '<i class="fas fa-robot"></i> Bot' : '';
    row.innerHTML = `${avaHtml}<div class="message-content"><div class="message-bubble" title="${t}">${inner}</div><div class="time-stamp"><i class="far fa-check-circle"></i> ${t} ${botIcon}</div></div>`;
    area.insertBefore(row, tWrap);
    triggerNewMsgToast();
}

// ============================================================
// UPDATE SIDEBAR ITEM TANPA RELOAD
// ============================================================
function updateChatItem(id, lastMsg, unread, lastMsgTime) {
    const item = chatList?.querySelector(`.ci[data-id="${id}"]`);
    if (!item) return;
    const prev = item.querySelector('.ci-preview');
    if (prev) {
        const txt = (lastMsg || '').substring(0, 40);
        prev.innerHTML = unread > 0
            ? `<span class="unread-dot"></span>${escapeHtml(txt)}`
            : `<i class="fas fa-check-double" style="font-size:10px;"></i> ${escapeHtml(txt)}`;
        item.dataset.preview = txt.toLowerCase();
    }
    const timeEl = item.querySelector('.ci-time');
    if (timeEl && lastMsgTime) timeEl.textContent = timeSince(lastMsgTime);
    else if (timeEl) timeEl.textContent = 'baru saja';
    let badge = item.querySelector('.unread-count');
    if (unread > 0) {
        if (!badge) { badge = document.createElement('span'); badge.className = 'unread-count'; item.appendChild(badge); }
        badge.textContent = unread > 9 ? '9+' : unread;
    } else { badge?.remove(); }
    if (unread > 0 && chatList.firstChild !== item) chatList.insertBefore(item, chatList.firstChild);
    refreshTotalUnread();
}

function refreshTotalUnread() {
    let total = 0;
    chatList?.querySelectorAll('.ci .unread-count').forEach(b => { const n = parseInt(b.textContent); if (!isNaN(n)) total += n; });
    const hb = document.getElementById('headerUnread');
    if (hb) { hb.textContent = total; hb.style.display = total > 0 ? '' : 'none'; }
}

// ============================================================
// DYNAMIC CHAT ITEM — new conversations without page reload
// ============================================================
function addChatItem(conv) {
    if (document.querySelector(`.ci[data-id="${conv.id}"]`)) return;

    const avatarColor = stringToColor(conv.visitor_name || '?');
    const initial = (conv.visitor_name || '?').charAt(0).toUpperCase();
    const lastMsg = conv.last_message || 'Mulai percakapan...';
    const lastMsgTime = conv.last_message_at ? timeSince(conv.last_message_at) : '';
    const hasUnread = conv.unread_count > 0;

    const el = document.createElement('a');
    el.href = `?conv=${conv.id}`;
    el.className = conv.id == convId ? 'ci active' : 'ci';
    el.dataset.id = conv.id;
    el.dataset.name = (conv.visitor_name || '').toLowerCase();
    el.dataset.preview = lastMsg.substring(0, 40).toLowerCase();
    el.dataset.time = conv.last_message_at ? Math.floor(new Date(conv.last_message_at).getTime() / 1000) : Date.now();

    el.addEventListener('contextmenu', e => showContextMenu(e, conv.id));
    let timer;
    el.addEventListener('touchstart', e => { timer = setTimeout(() => showContextMenu(e.touches[0], conv.id), 500); });
    el.addEventListener('touchend', () => clearTimeout(timer));
    el.addEventListener('touchmove', () => clearTimeout(timer));

    el.innerHTML = `
        <div class="ci-ava" style="background:${avatarColor}">${initial}</div>
        <div class="ci-body">
            <div class="ci-top">
                <span class="ci-name">${escapeHtml(conv.visitor_name || '')}</span>
                <div class="ci-meta">
                    <span class="ci-time">${lastMsgTime}</span>
                    <button class="btn-close-item" onclick="event.preventDefault(); closeChatFromContext(${conv.id})" title="Tutup Chat">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="ci-preview">
                ${hasUnread ? '<span class="unread-dot"></span>' : '<i class="fas fa-check-double" style="font-size:10px;"></i>'}
                ${escapeHtml(lastMsg.substring(0, 40))}
            </div>
        </div>
        ${hasUnread ? `<span class="unread-count">${conv.unread_count > 9 ? '9+' : conv.unread_count}</span>` : ''}
    `;

    const emptyState = chatList?.querySelector('div:first-child');
    if (emptyState && emptyState.tagName === 'DIV' && !emptyState.classList.contains('ci')) {
        emptyState.remove();
    }

    if (chatList) {
        chatList.insertBefore(el, chatList.firstChild);
    }

    const countEl = document.getElementById('chatCount');
    if (countEl) {
        countEl.textContent = chatList?.querySelectorAll('.ci')?.length || 0;
    }
}

function timeSince(dt) {
    const diff = Math.floor((Date.now() - new Date(dt).getTime()) / 1000);
    if (diff < 60) return 'baru saja';
    if (diff < 3600) return Math.floor(diff / 60) + 'm';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h';
    return new Date(dt).toLocaleDateString('id', { month: 'short', day: 'numeric' });
}

// ============================================================
// REAL-TIME ENGINE - Single unified polling system
// ============================================================
async function doPoll() {
    if (pollRunning || !convId || closed) return;
    pollRunning = true;
    try {
        const res = await fetch(`api/poll-messages?conv=${convId}&last_id=${lastId}&side=agent`);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const d = await res.json();
        pollFails = 0;

        // 1. New messages from visitor / bot / ai
        if (d.messages && d.messages.length) {
            d.messages.forEach(m => {
                if (m.sender_type !== 'agent') {
                    appendMsg(m.sender_type, m.content, m.file_url, m.created_at || null);
                }
                if (m.id > lastId) lastId = m.id;
            });
            updateChatItem(convId, d.messages[d.messages.length - 1]?.content, d.unread_count ?? 0);
        }

        // 2. Typing indicator with sneak-peek
        if (tWrap && sPeek) {
            if (d.is_typing) {
                tWrap.style.display = 'flex';
                sPeek.textContent = d.typing_text ? ' ' + d.typing_text : ' mengetik...';
                scrollToBottom();
            } else {
                tWrap.style.display = 'none';
                sPeek.textContent = '';
            }
        }

        // 3. Chat closed by system/visitor
        if (d.conv_status === 'closed' && !closed) {
            closed = true;
            clearTimeout(pollTimer);
            showToast('Percakapan telah ditutup', 'warning');
            setTimeout(() => location.reload(), 1800);
        }
    } catch(e) {
        pollFails++;
    } finally {
        pollRunning = false;
        if (!closed) {
            const interval = pollFails > 3 ? 5000 : 2500;
            pollTimer = setTimeout(doPoll, interval);
        }
    }
}

// ============================================================
// SIDEBAR REAL-TIME UPDATES - Unified with main polling
// ============================================================
let sidebarTimer = null;
async function pollSidebar() {
    try {
        const res = await fetch('/api/get-conversations');
        const d = await res.json();
        if (!d.success) return;
        
        d.conversations.forEach(c => {
            if (c.id != convId) {
                updateChatItem(c.id, c.last_message, c.unread_count, c.last_message_at);
            }
            if (c.id == convId) {
                const badge = document.querySelector(`.ci[data-id="${c.id}"] .unread-count`);
                if (badge) badge.textContent = c.unread_count;
            }
        });
        
        d.conversations.forEach(c => {
            const exists = document.querySelector(`.ci[data-id="${c.id}"]`);
            if (!exists) {
                addChatItem(c);
            }
        });
    } catch(e) {}
    
    if (!closed) sidebarTimer = setTimeout(pollSidebar, 4000);
}

// ============================================================
// NOTIFICATION SOUNDS - New visitor / incoming chat
// ============================================================
let notifTimer = null;
let notifSince = new Date().toISOString().replace('T',' ').substring(0,19);
let audioUnlocked = false;
const audioCache = {};

const SOUNDS = {
    new_visitor: '/assets/sounds/new_visitor.mp3',
    returning_visitor: '/assets/sounds/returning_visitor.mp3',
    incoming_chat: '/assets/sounds/incoming_chat.mp3',
    message: '/assets/sounds/message.mp3',
};

function preloadSounds() {
    Object.keys(SOUNDS).forEach(key => {
        const a = new Audio();
        a.preload = 'auto';
        a.src = SOUNDS[key];
        a.volume = 0.6;
        audioCache[key] = a;
    });
}
preloadSounds();

function unlockAudio() {
    if (audioUnlocked) return;
    // Create silent buffer to unlock audio context
    const ctx = new (window.AudioContext || window.webkitAudioContext)();
    const buf = ctx.createBuffer(1, 1, 22050);
    const src = ctx.createBufferSource();
    src.buffer = buf;
    src.connect(ctx.destination);
    src.start();
    ctx.close();
    audioUnlocked = true;
}
document.addEventListener('mousedown', unlockAudio, { once: true });
document.addEventListener('touchstart', unlockAudio, { once: true });
document.addEventListener('keydown', unlockAudio, { once: true });

function playNotif(name) {
    if (!name || !audioCache[name]) return;
    const a = audioCache[name];
    a.currentTime = 0;
    a.play().catch(() => {});
}

function sendBrowserNotif(name) {
    if (Notification.permission === 'granted' && document.hidden) {
        const titles = {
            new_visitor: 'Pengunjung Baru!',
            returning_visitor: 'Pengunjung kembali!',
            incoming_chat: 'Percakapan Baru!',
            message: 'Pesan Baru!',
        };
        new Notification(titles[name] || 'Notifikasi', {
            body: name === 'message' ? 'Ada pesan baru dari pengunjung' : 'Klik untuk melihat',
            icon: '/assets/images/default-avatar.png',
            tag: 'lc-notif',
        });
    }
}
if ('Notification' in window && Notification.permission === 'default') {
    Notification.requestPermission();
}

async function pollNotifications() {
    try {
        const params = notifSince ? '?since=' + encodeURIComponent(notifSince) : '';
        const res = await fetch('/api/notifications' + params);
        if (!res.ok) return;
        const d = await res.json();
        if (d.play) {
            playNotif(d.play);
            sendBrowserNotif(d.play);
        }
        if (d.timestamp) notifSince = d.timestamp;
    } catch(e) {}
    notifTimer = setTimeout(pollNotifications, 4000);
}

// Start all polling systems
if (convId && !closed) {
    doPoll();
}
pollSidebar();
pollNotifications();

// Mobile: auto-show list panel if no conversation selected
if (window.innerWidth <= 768 && !convId) {
    const pl = getPanel('panelList');
    if (pl) { pl.classList.add('mo'); getPanel('mobOverlay')?.classList.add('show'); }
}
// Mobile: tap chat → close panel first, then navigate (prevents race condition)
(function(){
    if (window.innerWidth > 768) return;
    const cl = document.getElementById('chatList');
    if (!cl) return;
    cl.addEventListener('click', function(e) {
        const ci = e.target.closest('.ci');
        if (!ci || e.target.closest('.btn-close-item')) return;
        e.preventDefault();
        closePanels();
        const href = ci.getAttribute('href');
        if (href) setTimeout(() => { location.href = href; }, 200);
    });
})();

// ============================================================
// EMOJI PICKER
// ============================================================
const emojiData = {
    '😊': ['😀','😃','😄','😁','😆','😅','🤣','😂','🙂','🙃','😉','😊','😇','🥰','😍','🤩','😘','😗','☺️','😚','😙','🥲','😋','😛','😜','🤪','😝','🤑','🤗','🤭','🤫','🤔','🤐','🤨','😐','😑','😶','😏','😒','🙄','😬','🤥','😌','😔','😪','🤤','😴','😷','🤒','🤕','🤢','🤮','🤧','🥵','🥶','🥴','😵','🤯','🤠','🥳','😎','🤓','🥸','😈','👿','💀','☠️','💩','🤡','👹','👺','👻','👽','👾','🤖'],
    '👋': ['👋','🤚','🖐️','✋','🖖','👌','🤌','🤏','✌️','🤞','🤟','🤘','🤙','👈','👉','👆','🖕','👇','☝️','👍','👎','✊','👊','🤛','🤜','👏','🙌','👐','🤲','🤝','🙏','✍️','💅','🤳','💪','🦾','🦵','🦶','👂','🦻','👃','🧠','🦷','🦴','👀','👁️','👅','👄','💋'],
    '❤️': ['❤️','🧡','💛','💚','💙','💜','🖤','🤍','🤎','💔','❣️','💕','💞','💓','💗','💖','💘','💝','💟','❤️‍🔥','❤️‍🩹','🫀'],
    '😺': ['🐶','🐱','🐭','🐹','🐰','🦊','🐻','🐼','🐻‍❄️','🐨','🐯','🦁','🐮','🐷','🐸','🐵','🐔','🐧','🐦','🦆','🦅','🦉','🦋','🐛','🐝','🐞','🐢','🦎','🐍','🦖','🐳','🦈','🐬','🐟','🐠','🦀','🦞','🦐','🐙','🦑','🦭'],
    '🍕': ['🍎','🍊','🍋','🍇','🍓','🫐','🍒','🍑','🥭','🍍','🥥','🥝','🍅','🍆','🥑','🌽','🥕','🍔','🍟','🍕','🌭','🥪','🌮','🌯','🍜','🍝','🍛','🍱','🍣','🍦','🧁','🎂','🍰','🍫','🍬','🍭','🧃','☕','🧋','🥤','🍺','🍷','🥂'],
    '⚽': ['⚽','🏀','🏈','⚾','🎾','🏐','🏉','🥏','🎱','🏓','🏸','🥊','🥋','🎯','⛳','🏊','🤸','🚴','🥇','🏆','🎪','🎭','🎨','🎬','🎤','🎵','🎶','🎸','🎹','🎺','🥁','🎲','🎮','🕹️'],
    '✈️': ['🚗','🚕','🚙','🚌','🏎️','🚓','🚑','🚒','🛻','🚚','🚛','✈️','🛫','🛬','🚀','🛸','🚁','🛶','⛵','🚢','🏠','🏢','🏥','🏦','🏛️','🗼','🗽','🗺️','🌍','🌎','🌏'],
    '💡': ['📱','💻','⌨️','🖥️','🖨️','📷','📸','📹','📞','☎️','📺','📻','🧭','⏱️','⏰','🔋','🔌','💡','🔦','💰','💳','✉️','📧','📝','📚','📖','🔍','🔐','🔑','🔨','⚙️','🧲','💊','🧪','🔭','📡'],
    '🌈': ['☀️','🌤️','⛅','☁️','🌧️','⛈️','🌩️','❄️','☃️','⛄','🌈','🌊','🌋','🏔️','🌺','🌻','🌹','🌷','🌸','💐','🍀','🌿','🍃','🌾','🌵','🌴','🌳','🌲','🌍'],
};

let activeCat = '😊';
const emojiPicker = document.getElementById('emojiPicker');
const emojiBtnIcon = document.getElementById('emojiBtnIcon');

function buildEmojiPicker() {
    if (!emojiPicker) return;
    const cats = Object.keys(emojiData);
    emojiPicker.innerHTML = `
        <div class="ep-search"><input type="text" id="epSearch" placeholder="Cari emoji..." autocomplete="off"></div>
        <div class="ep-cats">${cats.map(c => `<button class="ep-cat${c===activeCat?' active':''}" onclick="setEmojiCat('${c}')" title="${c}">${c}</button>`).join('')}</div>
        <div class="ep-grid" id="epGrid"></div>
    `;
    renderEmojiGrid(emojiData[activeCat]);
    document.getElementById('epSearch').addEventListener('input', function() {
        const term = this.value.trim().toLowerCase();
        if (!term) { renderEmojiGrid(emojiData[activeCat]); return; }
        const all = Object.values(emojiData).flat();
        renderEmojiGrid(all.slice(0, 64)); // tampilkan semua saat search aktif
    });
}

function renderEmojiGrid(list) {
    const grid = document.getElementById('epGrid');
    if (grid) grid.innerHTML = list.map(e => `<button class="ep-btn" onclick="insertEmoji('${e}')" title="${e}">${e}</button>`).join('');
}

function setEmojiCat(cat) {
    activeCat = cat;
    document.querySelectorAll('.ep-cat').forEach(b => b.classList.toggle('active', b.textContent === cat));
    renderEmojiGrid(emojiData[cat]);
}

function insertEmoji(emoji) {
    if (!inp) return;
    const s = inp.selectionStart, e = inp.selectionEnd;
    inp.value = inp.value.slice(0, s) + emoji + inp.value.slice(e);
    inp.selectionStart = inp.selectionEnd = s + emoji.length;
    inp.focus();
    checkInput();
}

function toggleEmojiPicker() {
    if (!emojiPicker) return;
    if (!emojiPicker.classList.contains('show')) buildEmojiPicker();
    emojiPicker.classList.toggle('show');
    emojiBtnIcon?.classList.toggle('emoji-active', emojiPicker.classList.contains('show'));
}

emojiBtnIcon?.addEventListener('click', function(e) { e.stopPropagation(); toggleEmojiPicker(); });

document.addEventListener('click', function(e) {
    if (emojiPicker?.classList.contains('show') && !emojiPicker.contains(e.target) && e.target !== emojiBtnIcon) {
        emojiPicker.classList.remove('show');
        emojiBtnIcon?.classList.remove('emoji-active');
    }
});

// ============================================================
// INPUT & SEND
// ============================================================
function checkInput() {
    if (!inp || !sBtn) return;
    const has = inp.value.trim().length > 0;
    sBtn.classList.toggle('active', has);
    sBtn.disabled = !has;
}

if (inp) {
    inp.addEventListener('input', function() {
        checkInput();
        if (this.value.startsWith('/') && scPopup?.children.length > 0) {
            scPopup.style.display = 'flex';
            scPopup.style.flexDirection = 'column';
            const term = this.value.toLowerCase();
            Array.from(scPopup.children).forEach(item => {
                const cmd = item.querySelector('.sc-cmd')?.innerText.toLowerCase();
                item.style.display = cmd?.includes(term) ? 'block' : 'none';
            });
        } else if (scPopup) { scPopup.style.display = 'none'; }
    });
    inp.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 'k') { e.preventDefault(); this.value = '/'; this.dispatchEvent(new Event('input')); }
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); if (scPopup?.style.display !== 'flex') sendMsg(); }
    });
}

let sendingMsg = false;
function sendMsg() {
    if (closed || !convId || sendingMsg) return;
    const text = inp?.value.trim();
    if (!text) return;
    sendingMsg = true;
    sBtn.disabled = true;
    sBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
    inp.value = '';
    checkInput();
    if (scPopup) scPopup.style.display = 'none';
    appendMsg('agent', text);
    const fd = new FormData();
    fd.append('action', 'send_message');
    fd.append('conversation_id', convId);
    fd.append('content', text);
    addCsrf(fd); apiFetch('chats', { method:'POST', body:fd }).then(res => {
        if (res?.success && res.message_id) lastId = Math.max(lastId, res.message_id);
        inp?.focus();
    }).finally(() => {
        sendingMsg = false;
        if (!inp?.value.trim()) { sBtn.disabled = true; sBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i>'; }
    });
}
if (sBtn) sBtn.onclick = sendMsg;

// Load shortcuts
<?php if (!empty($cannedResponses)): ?>
if (scPopup) scPopup.innerHTML = `<?php foreach ($cannedResponses as $cr): ?>
<div class="sc-item" onclick="insertSc(<?= json_encode($cr['message']) ?>)">
    <span class="sc-cmd"><?= htmlspecialchars($cr['shortcut']) ?></span>
    <span class="sc-msg"><?= htmlspecialchars(substr($cr['message'],0,50)) ?></span>
</div>
<?php endforeach; ?>`;
<?php endif; ?>

function insertSc(text) {
    if (!inp) return;
    inp.value = text;
    if (scPopup) scPopup.style.display = 'none';
    inp.focus();
    checkInput();
}

// ============================================================
// FILE UPLOAD
// ============================================================
if (attachBtn) attachBtn.onclick = () => fUp?.click();
if (fUp) {
    fUp.onchange = function(e) {
        const file = e.target.files[0];
        if (!file || closed) return;
        if (file.size > 5 * 1024 * 1024) { showToast('Maksimal ukuran file 5MB', 'error'); fUp.value=''; return; }
        const fd = new FormData(); fd.append('conversation_id', convId); fd.append('sender_type','agent'); fd.append('file', file);
        fd.append('csrf_token', csrfToken);
        apiFetch('api/chat', { method:'POST', body:fd }).then(d => {
            if (d?.success) appendMsg('agent', '', d.url);
        });
        fUp.value = '';
    };
}

// ============================================================
// CLOSE CHAT BUTTON
// ============================================================
const closeChatBtn = document.getElementById('closeChatBtn');
if (closeChatBtn) {
    closeChatBtn.onclick = () => {
        confirmAction('Tutup Percakapan', 'Tutup percakapan ini?', function() {
            const fd = new FormData(); fd.append('action','close_chat_ajax'); fd.append('conversation_id', convId);
            addCsrf(fd); fetch('chats', { method:'POST', body:fd }).then(r=>r.json()).then(d => {
                if (d.success) { closed=true; clearTimeout(pollTimer); showToast('Percakapan ditutup','success'); setTimeout(()=>location.reload(), 800); }
                else showToast('Gagal menutup chat','error');
            }).catch(()=>showToast('Gagal menutup chat','error'));
        });
    };
}

// ============================================================
// TRANSFER
// ============================================================
function transferChat() {
    if (!allAgents.length) { showToast('Tidak ada agen tersedia','error'); return; }
    const list = document.getElementById('transferList');
    if (!list) return;
    list.innerHTML = allAgents.map(a => `
        <button onclick="doTransfer(${a.id})" style="display:flex;align-items:center;gap:12px;padding:12px 16px;border:1px solid var(--border-color);border-radius:12px;background:#fff;cursor:pointer;transition:all .2s;text-align:left;width:100%;font-size:14px;"
            onmouseover="this.style.borderColor='var(--accent-blue)';this.style.background='#f8fafc'"
            onmouseout="this.style.borderColor='var(--border-color)';this.style.background='#fff'">
            <div style="width:36px;height:36px;border-radius:50%;background:var(--accent-blue);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0;">${a.display_name.charAt(0).toUpperCase()}</div>
            <div><div style="font-weight:600;color:var(--text-light);">${escapeHtml(a.display_name)}</div></div>
        </button>
    `).join('');
    document.getElementById('transferModal').classList.add('show');
}

function doTransfer(agentId) {
    document.getElementById('transferModal').classList.remove('show');
    if (agentId == myAid) { showToast('Tidak bisa transfer ke diri sendiri','error'); return; }
    const fd = new FormData(); fd.append('action','transfer'); fd.append('conversation_id', convId); fd.append('new_agent_id', agentId);
    addCsrf(fd); fetch('chats', { method:'POST', body:fd }).then(() => { showToast('Chat ditransfer','success'); setTimeout(()=>location.href='chats',1000); });
}

// ============================================================
// SAVE SHORTCUT
// ============================================================
function saveShortcut() {
    const cmd = document.getElementById('scCmd')?.value.trim();
    const msg = document.getElementById('scMsg')?.value.trim();
    if (!cmd || !msg) { showToast('Harap isi semua field','error'); return; }
    const fd = new FormData(); fd.append('action','add_shortcut'); fd.append('shortcut',cmd); fd.append('message',msg);
    addCsrf(fd); fetch('chats', { method:'POST', body:fd }).then(r=>r.json()).then(d => {
        if (d.success) { showToast('Shortcut ditambahkan','success'); location.reload(); }
        else showToast('Error: '+(d.error||'Unknown'),'error');
    }).catch(()=>showToast('Network error','error'));
}

// ============================================================
// TYPING INDICATOR TO VISITOR (Agent typing notification)
// ============================================================
const activeConvId = "<?= $activeConvId ?>";
const msgTextarea = document.querySelector('textarea#msgInput');
if (msgTextarea && convId) {
    let typingTimer2;
    msgTextarea.addEventListener('input', () => {
        fetch('chats', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=typing&conversation_id=${convId}&is_typing=1&typing_text=${encodeURIComponent(msgTextarea.value)}&csrf_token=${csrfToken}`
        });
        clearTimeout(typingTimer2);
        typingTimer2 = setTimeout(() => {
            fetch('chats', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=typing&conversation_id=${convId}&is_typing=0&typing_text=&csrf_token=${csrfToken}`
            });
        }, 2000);
    });
}
</script>
<?php include 'includes/layout-footer.php'; ?>