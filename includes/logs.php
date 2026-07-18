<?php

/**
 * Wrapper untuk menyimpan log aktivitas pengguna
 * 
 * @param int|null $userId ID pengguna yang melakukan aksi (null jika sistem/anonim)
 * @param string $action Nama aksi (misal: 'LOGIN', 'CREATE_AGENT', 'UPDATE_SUBSCRIPTION')
 * @param string|null $entityType Nama tabel/objek yang dipengaruhi (misal: 'users', 'agents')
 * @param int|null $entityId ID dari baris yang dipengaruhi
 * @param string|array|null $details Detail tambahan (jika array akan di-convert ke JSON)
 * @return int ID log yang baru dibuat
 */
function logActivity($userId, $action, $entityType = null, $entityId = null, $details = null) {
    try {
        $db = Database::getInstance();

        // Jika details berupa array, ubah menjadi string JSON
        if (is_array($details)) {
            $details = json_encode($details);
        }

        // Ambil IP Address secara otomatis
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $sql = "INSERT INTO activity_logs (
                    user_id, 
                    action, 
                    entity_type, 
                    entity_id, 
                    details, 
                    ip_address, 
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";

        return $db->insert($sql, [
            $userId,
            strtoupper($action),
            $entityType,
            $entityId,
            $details,
            $ipAddress
        ]);

    } catch (Exception $e) {
        error_log('Activity Log Error: ' . $e->getMessage());
        return false;
    }
}

// --- CONTOH PENGGUNAAN ---

// 1. Log saat admin mengubah subscription user
// logActivity($user['id'], 'UPDATE_SUBSCRIPTION', 'subscriptions', 5, 'Mengubah paket ke business');

// 2. Log saat user login (tanpa entity_id)
// logActivity(2, 'LOGIN', 'users', 2, ['browser' => 'Chrome', 'os' => 'Windows']);

// 3. Log penghapusan agent
// logActivity($user['id'], 'DELETE_AGENT', 'agents', 10, 'Menghapus agent Sales Support');