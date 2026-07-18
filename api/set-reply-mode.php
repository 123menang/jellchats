<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

session_start();
if (!isset($_SESSION['user_id'])) jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);

$data = json_decode(file_get_contents('php://input'), true);
$db = Database::getInstance();

$allowedModes = ['manual', 'bot', 'ai', 'hybrid'];
$convId = (int)($data['conversation_id'] ?? 0);
$mode   = $data['mode'] ?? '';

if (!$convId || !in_array($mode, $allowedModes)) jsonResponse(['success' => false, 'message' => 'Invalid parameters'], 400);

$db->update("UPDATE conversations SET reply_mode = ? WHERE id = ?", [$mode, $convId]);

$agent = $db->fetch("SELECT id FROM agents WHERE user_id = ?", [$_SESSION['user_id']]);
if ($agent) {
    $db->update("UPDATE agents SET reply_mode = ? WHERE id = ?", [$mode, $agent['id']]);
}

jsonResponse(['success' => true, 'mode' => $mode]);
?>