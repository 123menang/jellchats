<?php
/**
 * api/poll-messages.php
 */
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, no-store');

// Rate limiting (higher limit for polling)
requireRateLimit('poll_messages', 120, 60);

$convId = (int)($_GET['conv'] ?? $_GET['conversation_id'] ?? 0);
$lastId = (int)($_GET['last_id'] ?? 0);
$side   = $_GET['side'] ?? 'visitor'; // 'visitor' atau 'agent'

if (!$convId) jsonResponse(['error' => 'Missing conv parameter'], 400);

$db = Database::getInstance();

$messages = $db->fetchAll("
    SELECT m.id, m.conversation_id, m.sender_type, m.content, m.content_type, m.file_url, m.is_read, m.created_at,
        CASE WHEN m.sender_type = 'agent' THEN a.display_name
             WHEN m.sender_type IN ('bot','ai') THEN 'Bot'
             ELSE v.username END as sender_name
    FROM messages m
    LEFT JOIN agents a ON m.sender_id = a.id AND m.sender_type = 'agent'
    LEFT JOIN conversations c2 ON m.conversation_id = c2.id
    LEFT JOIN visitors v ON c2.visitor_id = v.id AND m.sender_type = 'visitor'
    WHERE m.conversation_id = ? AND m.id > ?
    ORDER BY m.created_at ASC
", [$convId, $lastId]);

$newLastId = !empty($messages) ? max(array_column($messages, 'id')) : $lastId;

if ($side === 'visitor') {
    $db->update("UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND sender_type IN ('agent','bot','ai','system') AND is_read = 0", [$convId]);
} else {
    $db->update("UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND sender_type = 'visitor' AND is_read = 0", [$convId]);
}

$typingUserType = ($side === 'visitor') ? 'agent' : 'visitor';
$typing = $db->fetch("SELECT is_typing, typing_text FROM typing_status WHERE conversation_id = ? AND user_type = ? AND is_typing = 1 AND datetime(updated_at) > datetime('now', '-5 seconds')", [$convId, $typingUserType]);

$conv = $db->fetch("SELECT status, reply_mode FROM conversations WHERE id = ?", [$convId]);

$unreadCount = 0;
if ($side === 'agent') {
    $row = $db->fetch("SELECT COUNT(*) as cnt FROM messages WHERE conversation_id = ? AND sender_type = 'visitor' AND is_read = 0", [$convId]);
    $unreadCount = (int)($row['cnt'] ?? 0);
}

jsonResponse([
    'messages'      => $messages,
    'last_id'       => $newLastId,
    'is_typing'     => $typing ? true : false,
    'typing_text'   => $typing['typing_text'] ?? '', // Admin panel baca string ini untuk Sneak-Peek
    'conv_status'   => $conv['status'] ?? 'active',
    'reply_mode'    => $conv['reply_mode'] ?? 'hybrid',
    'unread_count'  => $unreadCount
]);
?>