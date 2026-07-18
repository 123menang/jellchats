<?php
// api/get-conversations.php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
header('Content-Type: application/json');
header('Cache-Control: no-cache');
$auth->requireAuth();
$user = $auth->getCurrentUser();
$db   = Database::getInstance();
$agent = $db->fetch("SELECT id FROM agents WHERE user_id = ?", [$user['id']]);
if (!$agent) { echo json_encode(['success'=>false]); exit; }
$conversations = $db->fetchAll("
    SELECT c.id,
           v.username as visitor_name,
           (SELECT content FROM messages WHERE conversation_id=c.id ORDER BY created_at DESC LIMIT 1) as last_message,
           (SELECT strftime('%s', created_at) FROM messages WHERE conversation_id=c.id ORDER BY created_at DESC LIMIT 1) as last_message_timestamp,
           (SELECT COUNT(*) FROM messages WHERE conversation_id=c.id AND sender_type='visitor' AND is_read=0) as unread_count,
           (SELECT is_typing FROM typing_status WHERE conversation_id=c.id AND user_type='visitor' AND updated_at > datetime('now', '-5 seconds')) as is_typing
    FROM conversations c
    JOIN visitors v ON c.visitor_id = v.id
    WHERE c.agent_id = ? AND c.status = 'active'
    ORDER BY c.last_message_at DESC
", [$agent['id']]);
echo json_encode(['success'=>true,'conversations'=>$conversations]);