<?php
require_once __DIR__ . '/../includes/functions.php';
$db = Database::getInstance();
$timeout_minutes = 30; // Batas waktu tidak ada chat baru

try {
    // Cari percakapan 'active' yang pesan terakhirnya sudah lebih dari X menit
    // Kita filter agar hanya menutup chat yang pesan terakhirnya dikirim oleh Agent (opsional)
    $query = "UPDATE conversations c
              SET c.status = 'closed', c.updated_at = NOW()
              WHERE c.status = 'active'
              AND (
                  SELECT MAX(created_at) 
                  FROM messages 
                  WHERE conversation_id = c.id
              ) < DATE_SUB(NOW(), INTERVAL ? MINUTE)";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$timeout_minutes]);
    
    $closedCount = $stmt->rowCount();
    
    // Opsional: Log ke console jika dijalankan manual
    // echo json_encode(['success' => true, 'closed' => $closedCount]);

} catch (Exception $e) {
    // error_log($e->getMessage());
}