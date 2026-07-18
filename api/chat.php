<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

// ========== CORS UNTUK SEMUA DOMAIN ==========
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Max-Age: 86400");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../includes/auth.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

// Rate limiting
requireRateLimit('api_chat', 30, 60);

$db = Database::getInstance();

// ── File upload (Gambar & Dokumen) ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $convId = (int)($_POST['conversation_id'] ?? 0);
    $sender = $_POST['sender_type'] ?? 'visitor';
    if (!$convId) jsonResponse(['error'=>'Missing conversation_id'],400);

    $allowedTypes = ['image/jpeg','image/png','image/gif','image/webp','application/pdf'];
    $allowedExts = ['jpg','jpeg','png','gif','webp','pdf'];
    
    // Validate MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $_FILES['file']['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) jsonResponse(['error'=>'Invalid file type.'],400);
    
    // Validate extension matches MIME
    $ext = strtolower(pathinfo($_FILES['file']['name'],PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts)) jsonResponse(['error'=>'Invalid file extension.'],400);
    
    if ($_FILES['file']['size'] > 5*1024*1024) jsonResponse(['error'=>'Max 5MB'],400);

    $dir = __DIR__.'/../uploads/';
    if (!is_dir($dir)) mkdir($dir,0755,true);
    $fn  = 'doc_'.bin2hex(random_bytes(8)).'.'.$ext;
    if (move_uploaded_file($_FILES['file']['tmp_name'],$dir.$fn)) {
        $url = 'uploads/'.$fn;
        $db->insert("INSERT INTO messages (conversation_id,sender_type,content,content_type,file_url) VALUES (?,?,'Sent a file','file',?)",[$convId, $sender, $url]);
        $db->update("UPDATE conversations SET last_message_at=datetime('now') WHERE id=?",[$convId]);
        jsonResponse(['success'=>true,'url'=>$url]);
    }
    jsonResponse(['error'=>'Upload failed'],500);
}

// Handle GET request untuk polling
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['conv'])) {
    $convId = (int)$_GET['conv'];
    $lastId = (int)($_GET['last_id'] ?? 0);
    
    if (!$convId) jsonResponse(['error'=>'Missing conversation_id'],400);
    
    $messages = $db->fetchAll("SELECT * FROM messages WHERE conversation_id=? AND id>? ORDER BY created_at ASC", [$convId, $lastId]);
    $typing = $db->fetch("SELECT is_typing, typing_text FROM typing_status WHERE conversation_id=? AND user_type='agent'", [$convId]);
    $conv = $db->fetch("SELECT status FROM conversations WHERE id=?", [$convId]);
    
    $lastMsgId = $lastId;
    if (!empty($messages)) $lastMsgId = $messages[count($messages)-1]['id'];
    
    jsonResponse([
        'success' => true,
        'messages' => $messages,
        'last_id' => $lastMsgId,
        'is_typing' => $typing && $typing['is_typing'] == 1,
        'typing_text' => $typing['typing_text'] ?? '',
        'conv_status' => $conv['status'] ?? 'active'
    ]);
    exit();
}

$data = json_decode(file_get_contents('php://input'),true);
if (!$data) jsonResponse(['error'=>'Invalid JSON'],400);

$action = $data['action'] ?? '';

switch ($action) {

case 'init':
    $licenseKey = $data['license_key'] ?? '';
    $username   = sanitizeInput($data['username'] ?? '');
    $phone      = sanitizeInput($data['phone'] ?? '');
    $issueTypes = is_array($data['issue_type'] ?? null) ? $data['issue_type'] : [];
    $ipAddress  = getClientIP();

    if (!$licenseKey || !$username || !$phone) jsonResponse(['error'=>'Missing required fields'],400);

    $embed = $db->fetch("SELECT * FROM embed_codes WHERE embed_key=? AND status=1", [$licenseKey]);
    if (!$embed) jsonResponse(['error'=>'Invalid site ID'],404);

    $agent      = $db->fetch("SELECT * FROM agents WHERE id=?",[$embed['agent_id']]);
    $wCfg       = json_decode($embed['widget_config']??'{}',true) ?: [];
    $replyMode  = $agent['reply_mode'] ?? 'manual';

    $issueLabels = ['deposit'=>'Deposit', 'withdraw'=>'Withdraw', 'reset_password'=>'Reset Password', 'kendala_lainnya'=>'Kendala Lainnya'];
    $issueText = implode(', ', array_map(fn($k) => $issueLabels[$k] ?? $k, $issueTypes));
    $tagsJson = json_encode(['issue_type' => $issueTypes, 'issue_text' => $issueText]);

    // 1. CARI VISITOR BERDASARKAN USERNAME (lebih spesifik, hindari false positive dari IP bersama)
    $visitor = $db->fetch("SELECT * FROM visitors WHERE username=? ORDER BY last_visit DESC LIMIT 1", [$username]);
    
    if ($visitor) {
        $visitorId = $visitor['id'];
        // Update data visitor saat ini
        $db->update("UPDATE visitors SET last_visit=datetime('now'), visit_count=visit_count+1, ip_address=?, user_agent=?, referrer_url=?, phone=? WHERE id=?",
            [$ipAddress, $_SERVER['HTTP_USER_AGENT'] ?? '', $_SERVER['HTTP_REFERER'] ?? '', $phone, $visitorId]);

        // 2. CARI CONVERSATION YANG MASIH AKTIF UNTUK VISITOR INI
        $prevConv = $db->fetch("SELECT * FROM conversations WHERE visitor_id=? AND status='active' ORDER BY created_at DESC LIMIT 1", [$visitorId]);
        
        if ($prevConv) {
            $messages = $db->fetchAll("SELECT * FROM messages WHERE conversation_id=? ORDER BY created_at ASC", [$prevConv['id']]);
            jsonResponse([
                'success' => true,
                'resume' => true,
                'visitor_id' => $visitorId,
                'conversation_id' => $prevConv['id'],
                'session_id' => $prevConv['session_id'],
                'messages' => $messages,
                'agent_name' => $agent['display_name'] ?? 'Support',
                'reply_mode' => $prevConv['reply_mode'],
                'config' => $wCfg
            ]);
            exit();
        }
    } else {
        $geo = getGeoInfo($ipAddress);
        $visitorId = $db->insert(
            "INSERT INTO visitors (username, phone, ip_address, country, city, region, user_agent, referrer_url, visit_count, last_visit) VALUES (?,?,?,?,?,?,?,?,1,datetime('now'))",
            [$username, $phone, $ipAddress, $geo['country'], $geo['city'], $geo['region'], $_SERVER['HTTP_USER_AGENT'] ?? '', $_SERVER['HTTP_REFERER'] ?? '']
        );
    }

    // Jika tidak ada chat aktif, buat baru
    $sessionId = generateSessionId();
    $convMode = in_array($replyMode, ['bot', 'hybrid', 'ai']) ? $replyMode : 'manual';

    $convId = $db->insert(
        "INSERT INTO conversations (agent_id, visitor_id, embed_code_id, session_id, username, phone, subject, source_url, reply_mode, tags) VALUES (?,?,?,?,?,?,?,?,?,?)",
        [$embed['agent_id'], $visitorId, $embed['id'], $sessionId, $username, $phone, $issueText, $_SERVER['HTTP_REFERER'] ?? '', $convMode, $tagsJson]
    );

    $welcomeText = ($replyMode === 'ai') ? "Halo *{$username}*! 👋\n\nAda yang bisa saya bantu?" : ($wCfg['welcome_message'] ?? "Halo *{$username}*! 👋 Agen kami akan segera membantu Anda.");
    $db->insert("INSERT INTO messages (conversation_id, sender_type, content) VALUES (?,'bot',?)", [$convId, $welcomeText]);

    jsonResponse([
        'success' => true,
        'visitor_id' => $visitorId,
        'conversation_id' => $convId,
        'session_id' => $sessionId,
        'agent_name' => $agent['display_name'] ?? 'Support',
        'reply_mode' => $convMode,
        'config' => $wCfg
    ]);
    break;

case 'track_visitor':
    $visitorId = (int)($data['visitor_id'] ?? 0);
    $currentUrl = $data['current_url'] ?? '';
    if ($visitorId) {
        $db->update("UPDATE visitors SET last_visit=datetime('now'), referrer_url=? WHERE id=?", [$currentUrl, $visitorId]);
        jsonResponse(['success' => true]);
    }
    break;
    
case 'send':
    $convId = (int)($data['conversation_id'] ?? 0);
    $content = trim($data['content'] ?? '');
    $sender = $data['sender_type'] ?? 'visitor';
    $senderId = $data['sender_id'] ?? null;

    if (!$convId || !$content) {
        jsonResponse(['error' => 'Missing fields'], 400);
    }

    $msgId = $db->insert(
        "INSERT INTO messages (conversation_id, sender_type, sender_id, content) VALUES (?,?,?,?)",
        [$convId, $sender, $senderId, $content]
    );

    $db->update("UPDATE conversations SET last_message_at=datetime('now') WHERE id=?", [$convId]);
    $db->query("UPDATE typing_status SET is_typing=0, typing_text='' WHERE conversation_id=? AND user_type=?", [$convId, $sender]);

    if ($sender === 'visitor') {
        $conv = $db->fetch("SELECT * FROM conversations WHERE id=?", [$convId]);
        $agent = $db->fetch("SELECT * FROM agents WHERE id=?", [$conv['agent_id'] ?? 0]);
        $replyMode = $conv['reply_mode'] ?? 'manual';

        if ($agent && $replyMode !== 'manual') {
            $visitorMsgCount = $db->fetch("SELECT COUNT(*) as cnt FROM messages WHERE conversation_id=? AND sender_type='visitor'", [$convId])['cnt'];
            $convTags = json_decode($conv['tags'] ?? '{}', true) ?: [];

            if ((int)$visitorMsgCount === 1 && ($replyMode === 'bot' || $replyMode === 'hybrid')) {
                $convTags['account_id'] = $content;
                $db->update("UPDATE conversations SET tags=? WHERE id=?", [json_encode($convTags), $convId]);

                $moduleResponse = null;
                if (!empty($convTags['issue_type'])) {
                    foreach ($convTags['issue_type'] as $issueKey) {
                        if (function_exists('matchChatModule')) {
                            $moduleResponse = matchChatModule($agent['id'], $issueKey);
                            if ($moduleResponse) break;
                        }
                    }
                }

                if ($moduleResponse) {
                    $moduleResponse = str_replace(['{{account_id}}', '{{username}}'], $content, $moduleResponse);
                    $db->insert("INSERT INTO messages (conversation_id, sender_type, content) VALUES (?,'bot',?)", [$convId, $moduleResponse]);
                } else {
                    $confirmMsg = "✅ Pesan diterima! Seseorang agent akan menjawab pertanyaan Anda, silakan menunggu.";
                    $db->insert("INSERT INTO messages (conversation_id, sender_type, content) VALUES (?,'bot',?)", [$convId, $confirmMsg]);
                }
            } 
            else {
                if (function_exists('processAutoReply')) {
                    $autoReply = processAutoReply($convId, $content);
                    if ($autoReply) {
                        $db->insert("INSERT INTO messages (conversation_id, sender_type, content) VALUES (?,?,?)", [$convId, $autoReply['type'], $autoReply['text']]);
                    }
                } elseif (function_exists('matchChatModule')) {
                    $botResponse = matchChatModule($agent['id'], $content);
                    if ($botResponse) {
                        $db->insert("INSERT INTO messages (conversation_id, sender_type, content) VALUES (?,'bot',?)", [$convId, $botResponse]);
                    }
                }
            }
        }
    }

    jsonResponse(['success' => true, 'message_id' => $msgId]);
    break;

case 'typing':
    $convId = (int)($data['conversation_id'] ?? 0);
    $userType = $data['user_type'] ?? 'visitor';
    $isTyping = (int)($data['is_typing'] ?? 0);
    $typingTxt = function_exists('mb_substr') ? mb_substr($data['typing_text'] ?? '', 0, 500) : substr($data['typing_text'] ?? '', 0, 500);
    
    if (!$convId) jsonResponse(['error' => 'Missing conv'], 400);

    $existing = $db->fetch("SELECT id FROM typing_status WHERE conversation_id=? AND user_type=?", [$convId, $userType]);
    if ($existing) {
        $db->update("UPDATE typing_status SET typing_text=?, is_typing=?, updated_at=datetime('now') WHERE conversation_id=? AND user_type=?", 
            [$typingTxt, $isTyping, $convId, $userType]);
    } else {
        $db->insert("INSERT INTO typing_status (conversation_id, user_type, typing_text, is_typing, updated_at) VALUES (?,?,?,?,datetime('now'))", 
            [$convId, $userType, $typingTxt, $isTyping]);
    }
    jsonResponse(['success' => true]);
    break;

case 'transfer':
    $convId = (int)$data['conversation_id'];
    $newAgentId = (int)$data['new_agent_id'];
    $db->update("UPDATE conversations SET assigned_to = ?, agent_id = ? WHERE id = ?", [$newAgentId, $newAgentId, $convId]);
    $db->insert("INSERT INTO messages (conversation_id, sender_type, content) VALUES (?, 'system', 'Chat ditransfer ke agen lain.')", [$convId]);
    jsonResponse(['success' => true]);
    break;

case 'update_tags':
    $convId = (int)$data['conversation_id'];
    $tags = json_encode($data['tags']);
    $db->update("UPDATE conversations SET tags = ? WHERE id = ?", [$tags, $convId]);
    jsonResponse(['success' => true]);
    break;

case 'close_chat':
    $convId = (int)$data['conversation_id'];
    $db->update("UPDATE conversations SET status = 'closed', closed_at = datetime('now') WHERE id = ?", [$convId]);
    $db->insert("INSERT INTO messages (conversation_id, sender_type, content) VALUES (?, 'system', 'Chat telah ditutup oleh agen.')", [$convId]);
    jsonResponse(['success' => true]);
    break;

case 'rate_chat':
    $convId = (int)$data['conversation_id'];
    $rating = (int)$data['rating'];
    $db->update("UPDATE conversations SET rating = ? WHERE id = ?", [$rating, $convId]);
    jsonResponse(['success' => true]);
    break;

default:
    jsonResponse(['error' => 'Invalid action'], 400);
    break;
}
?>