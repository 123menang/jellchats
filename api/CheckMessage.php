<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

$auth = Auth::getInstance();
if (!$auth->isLoggedIn()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();
// Gunakan format timestamp yang konsisten
$lastCheck = $_GET['since'] ?? date('Y-m-d H:i:s', strtotime('-5 seconds'));

$response = [
    'play' => null,
    'timestamp' => date('Y-m-d H:i:s')
];

try {
    /**
     * 1. CEK PESAN MASUK (Incoming Chat atau Message)
     * Prioritas utama: Suara chat/pesan lebih penting daripada suara visitor datang.
     */
    $newMsg = $db->fetch("SELECT m.id, m.conversation_id, m.sender_type 
                          FROM messages m 
                          WHERE m.sender_type != 'agent' 
                          AND m.created_at > ? 
                          ORDER BY m.id DESC LIMIT 1", [$lastCheck]);

    if ($newMsg) {
        // Hitung total pesan di percakapan ini untuk menentukan apakah ini chat baru (first message)
        $msgCount = $db->fetch("SELECT COUNT(*) as total FROM messages WHERE conversation_id = ?", [$newMsg['conversation_id']]);
        
        if ((int)$msgCount['total'] === 1) {
            $response['play'] = 'incoming_chat';
        } else {
            $response['play'] = 'message';
        }
    } 
    /**
     * 2. CEK VISITOR (New atau Returning)
     * Hanya dicek jika tidak ada pesan masuk baru.
     */
    else {
        $visitor = $db->fetch("SELECT visit_count FROM visitors 
                               WHERE last_visit > ? 
                               ORDER BY last_visit DESC LIMIT 1", [$lastCheck]);
        
        if ($visitor) {
            // visit_count > 1 berarti returning visitor
            if ((int)$visitor['visit_count'] === 1) {
                $response['play'] = 'new_visitor';
            } else {
                $response['play'] = 'returning_visitor';
            }
        }
    }

    echo json_encode($response);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}