<?php
/**
 * API: Check Notifications & Online Agents
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
$db = Database::getInstance();

header('Content-Type: application/json');

// Rate limiting
requireRateLimit('check_notif', 30, 60);

$agentId = (int)($_GET['agent_id'] ?? 0);
$since = $_GET['since'] ?? date('Y-m-d H:i:s', strtotime('-5 seconds'));

if (!$agentId) {
    echo json_encode(['success' => false, 'error' => 'Agent ID required']);
    exit;
}

// Get online agents count
$onlineAgents = $db->fetch("SELECT COUNT(*) as count FROM agents WHERE is_online = 1")['count'] ?? 0;

$response = [
    'play' => null,
    'timestamp' => date('Y-m-d H:i:s'),
    'online_agents' => (int)$onlineAgents
];

// Cek visitor baru
$newVisitor = $db->fetch("
    SELECT v.* FROM visitors v
    JOIN conversations c ON v.id = c.visitor_id
    WHERE c.agent_id = ? AND v.is_new = 1 AND v.first_visit >= ?
    ORDER BY v.first_visit DESC LIMIT 1
", [$agentId, $since]);

if ($newVisitor) {
    $response['play'] = 'new_visitor';
    $db->update("UPDATE visitors SET is_new = 0 WHERE id = ?", [$newVisitor['id']]);
} else {
    // Cek pesan baru dari visitor
    $newMessage = $db->fetch("
        SELECT m.* FROM messages m
        JOIN conversations c ON m.conversation_id = c.id
        WHERE c.agent_id = ? AND m.sender_type = 'visitor' AND m.is_read = 0 
        AND m.created_at >= ?
        ORDER BY m.created_at DESC LIMIT 1
    ", [$agentId, $since]);
    
    if ($newMessage) {
        $response['play'] = 'message';
    }
}

echo json_encode($response);