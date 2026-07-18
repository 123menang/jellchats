<?php
// ==========================================
// KELAS DATABASE & INISIALISASI
// ==========================================
class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
    $possiblePaths =[
        __DIR__ . '/../storage/database/livechat.db',
        __DIR__ . '/../../storage/database/livechat.db',
        dirname($_SERVER['DOCUMENT_ROOT'] ?? '.') . '/database/livechat.db',
        $_SERVER['DOCUMENT_ROOT'] . '/database/livechat.db',
    ];
        
        $dbPath = null;
        foreach ($possiblePaths as $path) {
            $dir = dirname($path);
            if (is_dir($dir) || @mkdir($dir, 0755, true)) {
                $dbPath = $path;
                break;
            }
        }
        if (!$dbPath) { $dbPath = $possiblePaths[0]; }
        
        $dbDir = dirname($dbPath);
        if (!is_dir($dbDir)) { @mkdir($dbDir, 0755, true); }
        
        try {
            $this->pdo = new PDO('sqlite:' . $dbPath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->pdo->exec('PRAGMA foreign_keys = ON;');
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            throw new Exception('Database connection failed: ' . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) { self::$instance = new self(); }
        return self::$instance;
    }
    
    public function getPdo() { return $this->pdo; }
    public function query($sql, $params =[]) { $stmt = $this->pdo->prepare($sql); $stmt->execute($params); return $stmt; }
    public function fetch($sql, $params =[]) { $result = $this->query($sql, $params); return $result->fetch(); }
    public function fetchAll($sql, $params =[]) { $result = $this->query($sql, $params); return $result->fetchAll(); }
    public function insert($sql, $params =[]) { $this->query($sql, $params); return $this->pdo->lastInsertId(); }
    public function update($sql, $params =[]) { $stmt = $this->query($sql, $params); return $stmt->rowCount(); }
    public function delete($sql, $params =[]) { $stmt = $this->query($sql, $params); return $stmt->rowCount(); }
}

function initDatabase() {
    try {
        $db = Database::getInstance();
        $schemaPath = __DIR__ . '/../database/schema.sql';
        if (file_exists($schemaPath)) {
            $schema = file_get_contents($schemaPath);
            if ($schema !== false) {
                $db->getPdo()->exec($schema);
            }
        }
    } catch (Exception $e) { error_log('DB init failed: ' . $e->getMessage()); }
}

// ==========================================
// AUTO-MIGRATION & INITIALIZATION LOGIC
// ==========================================
try {
    $db = Database::getInstance();
    $tables = $db->fetch("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
    if (!$tables) {
        initDatabase();
    } else {
        // 1. Cek dan tambahkan kolom baru di conversations jika belum ada
        $columns = $db->fetchAll("PRAGMA table_info(conversations)");
        $existingCols = array_column($columns, 'name');
        
        $newCols =[
            'username' => 'TEXT',
            'phone' => 'TEXT',
            'rating' => 'INTEGER DEFAULT 0',
            'rating_comment' => 'TEXT',
            'closed_at' => 'DATETIME'
        ];

        foreach($newCols as $col => $type) {
            if (!in_array($col, $existingCols)) {
                try {
                    $db->getPdo()->exec("ALTER TABLE conversations ADD COLUMN $col $type");
                } catch (Exception $e) { error_log("Migration failed ($col): " . $e->getMessage()); }
            }
        }

        // 2. Buat Tabel Canned Responses (Shortcut) jika belum ada
        $checkCanned = $db->fetch("SELECT name FROM sqlite_master WHERE type='table' AND name='canned_responses'");
        if (!$checkCanned) {
            try {
                $db->getPdo()->exec("
                    CREATE TABLE IF NOT EXISTS canned_responses (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        agent_id INTEGER NOT NULL,
                        shortcut TEXT NOT NULL,
                        message TEXT NOT NULL,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE
                    )
                ");
                error_log("Migration: Table 'canned_responses' created successfully.");
            } catch (Exception $e) { error_log("Migration failed (canned_responses): " . $e->getMessage()); }
        }

        // 3. Buat atau Perbaiki Tabel typing_status dengan UNIQUE constraint
        $checkTyping = $db->fetch("SELECT name FROM sqlite_master WHERE type='table' AND name='typing_status'");
        if (!$checkTyping) {
            // Buat tabel baru langsung dengan constraint UNIQUE
            try {
                $db->getPdo()->exec("
                    CREATE TABLE IF NOT EXISTS typing_status (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        conversation_id INTEGER NOT NULL,
                        user_type TEXT NOT NULL,
                        user_id INTEGER,
                        typing_text TEXT,
                        is_typing INTEGER DEFAULT 0,
                        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
                        UNIQUE(conversation_id, user_type)
                    )
                ");
                error_log("Migration: Table 'typing_status' created successfully.");
            } catch (Exception $e) {
                error_log("Migration failed (typing_status): " . $e->getMessage());
            }
        } else {
            // Tabel sudah ada, pastikan ada UNIQUE constraint pada (conversation_id, user_type)
            // Cek apakah unique index sudah ada
            $hasUnique = false;
            $indexes = $db->fetchAll("PRAGMA index_list(typing_status)");
            foreach ($indexes as $idx) {
                if ($idx['unique']) {
                    $indexInfo = $db->fetchAll("PRAGMA index_info({$idx['name']})");
                    $cols = array_column($indexInfo, 'name');
                    if (count($cols) === 2 && in_array('conversation_id', $cols) && in_array('user_type', $cols)) {
                        $hasUnique = true;
                        break;
                    }
                }
            }
            if (!$hasUnique) {
                try {
                    $db->getPdo()->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_typing_unique ON typing_status (conversation_id, user_type)");
                    error_log("Migration: Unique index added to typing_status.");
                } catch (Exception $e) {
                    error_log("Migration failed (add unique index): " . $e->getMessage());
                }
            }
        }
    }
} catch (Exception $e) {
    initDatabase();
}