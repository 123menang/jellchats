<?php
/**
 * Agent Typing Status API - NEW FILE
 * Called by admin panel when agent is typing
 */
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

session_start();
if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false], 401);
}

$data = json_decode(file_get_contents('php://input'), true);
$convId   = (int)($data['conversation_id'] ?? 0);
$isTyping = (int)($data['is_typing'] ?? 0);

if (!$convId) jsonResponse(['success' => false, 'error' => 'Missing conversation_id'], 400);

$db = Database::getInstance();
$agent = $db->fetch("SELECT id FROM agents WHERE user_id = ?", [$_SESSION['user_id']]);
$agentId = $agent ? $agent['id'] : null;

$db->query(
    "INSERT INTO typing_status (conversation_id, user_type, user_id, is_typing, typing_text, updated_at)
     VALUES (?, 'agent', ?, ?, '', datetime('now'))
     ON CONFLICT(conversation_id, user_type) DO UPDATE SET
     is_typing   = excluded.is_typing,
     user_id     = excluded.user_id,
     updated_at  = excluded.updated_at",
    [$convId, $agentId, $isTyping]
);

jsonResponse(['success' => true]);
?>