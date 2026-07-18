<?php
/**
 * Send Message API (Agent Side) - FIXED VERSION
 * Called by admin panel when agent sends a message
 */
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

session_start();
if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['conversation_id']) || !isset($data['content'])) {
    jsonResponse(['success' => false, 'message' => 'Missing required fields'], 400);
}

$db = Database::getInstance();

$convId      = (int)$data['conversation_id'];
$content     = htmlspecialchars(trim($data['content']), ENT_QUOTES, 'UTF-8');
$senderType  = $data['sender_type'] ?? 'agent';
$contentType = $data['content_type'] ?? 'text';
$userId      = $_SESSION['user_id'];

// Get agent for this user
$agent = $db->fetch("SELECT * FROM agents WHERE user_id = ?", [$userId]);
$agentId = $agent ? $agent['id'] : null;

// Verify conversation belongs to this agent
$conv = $db->fetch("SELECT * FROM conversations WHERE id = ?", [$convId]);
if (!$conv || ($agentId && $conv['agent_id'] != $agentId)) {
    jsonResponse(['success' => false, 'message' => 'Conversation not found'], 404);
}

// Insert message
$msgId = $db->insert(
    "INSERT INTO messages (conversation_id, sender_type, sender_id, content, content_type) VALUES (?, ?, ?, ?, ?)",
    [$convId, $senderType, $agentId, $content, $contentType]
);

// Update conversation timestamp
$db->update(
    "UPDATE conversations SET last_message_at = datetime('now') WHERE id = ?",
    [$convId]
);

// Clear agent typing status
if ($agentId) {
    $db->query(
        "INSERT INTO typing_status (conversation_id, user_type, user_id, is_typing, typing_text, updated_at)
         VALUES (?, 'agent', ?, 0, '', datetime('now'))
         ON CONFLICT(conversation_id, user_type) DO UPDATE SET
         is_typing = 0, typing_text = '', updated_at = datetime('now')",
        [$convId, $agentId]
    );
}

jsonResponse(['success' => true, 'message_id' => $msgId]);
?>