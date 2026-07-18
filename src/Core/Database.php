<?php

declare(strict_types=1);

namespace App\Core;

final class Database
{
    private static ?Database $instance = null;
    private \PDO $pdo;

    private function __construct()
    {
        $dbPath = $this->resolveDbPath();

        $dbDir = dirname($dbPath);
        if (!is_dir($dbDir)) {
            @mkdir($dbDir, 0755, true);
        }

        try {
            $this->pdo = new \PDO('sqlite:' . $dbPath);
            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
            $this->pdo->exec('PRAGMA foreign_keys = ON;');
            $this->pdo->exec('PRAGMA journal_mode = WAL;');
        } catch (\PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            throw new \RuntimeException('Database connection failed');
        }
    }

    private function resolveDbPath(): string
    {
        $paths = [
            __DIR__ . '/../../storage/database/livechat.db',
            __DIR__ . '/../../database/livechat.db',
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return $paths[0];
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getPdo(): \PDO
    {
        return $this->pdo;
    }

    public function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetch(string $sql, array $params = []): ?array
    {
        $result = $this->query($sql, $params);
        $row = $result->fetch();
        return $row ?: null;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function insert(string $sql, array $params = []): int
    {
        $this->query($sql, $params);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(string $sql, array $params = []): int
    {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    public function delete(string $sql, array $params = []): int
    {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollback(): void
    {
        $this->pdo->rollBack();
    }

    public function initializeSchema(): void
    {
        $schemaPath = __DIR__ . '/../../database/schema.sql';
        if (file_exists($schemaPath)) {
            $schema = file_get_contents($schemaPath);
            if ($schema !== false) {
                $this->pdo->exec($schema);
            }
        }
    }

    public function runMigrations(): void
    {
        $tables = $this->fetch("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
        if (!$tables) {
            $this->initializeSchema();
            return;
        }

        // Migration: visitors table columns
        $visitorCols = $this->fetchAll("PRAGMA table_info(visitors)");
        $existingVisitorCols = array_column($visitorCols, 'name');
        if (!in_array('is_banned', $existingVisitorCols, true)) {
            try {
                $this->pdo->exec("ALTER TABLE visitors ADD COLUMN is_banned INTEGER DEFAULT 0");
            } catch (\Exception $e) {
                error_log("Migration failed (visitors.is_banned): " . $e->getMessage());
            }
        }
        if (!in_array('city', $existingVisitorCols, true)) {
            try {
                $this->pdo->exec("ALTER TABLE visitors ADD COLUMN city TEXT");
            } catch (\Exception $e) {
                error_log("Migration failed (visitors.city): " . $e->getMessage());
            }
        }
        if (!in_array('country', $existingVisitorCols, true)) {
            try {
                $this->pdo->exec("ALTER TABLE visitors ADD COLUMN country TEXT");
            } catch (\Exception $e) {
                error_log("Migration failed (visitors.country): " . $e->getMessage());
            }
        }
        if (!in_array('is_new', $existingVisitorCols, true)) {
            try {
                $this->pdo->exec("ALTER TABLE visitors ADD COLUMN is_new INTEGER DEFAULT 1");
            } catch (\Exception $e) {
                error_log("Migration failed (visitors.is_new): " . $e->getMessage());
            }
        }
        if (!in_array('name', $existingVisitorCols, true)) {
            try {
                $this->pdo->exec("ALTER TABLE visitors ADD COLUMN name TEXT DEFAULT ''");
            } catch (\Exception $e) {
                error_log("Migration failed (visitors.name): " . $e->getMessage());
            }
        }
        if (!in_array('session_id', $existingVisitorCols, true)) {
            try {
                $this->pdo->exec("ALTER TABLE visitors ADD COLUMN session_id TEXT DEFAULT ''");
                $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_visitors_session_id ON visitors(session_id)");
            } catch (\Exception $e) {
                error_log("Migration failed (visitors.session_id): " . $e->getMessage());
            }
        }

        // Ensure rate_limits table exists
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS rate_limits (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            key_name TEXT NOT NULL,
            ip_address TEXT NOT NULL,
            window_start INTEGER NOT NULL,
            hit_count INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(key_name, ip_address, window_start)
        )");

        $columns = $this->fetchAll("PRAGMA table_info(conversations)");
        $existingCols = array_column($columns, 'name');

        $newCols = [
            'username' => 'TEXT',
            'phone' => 'TEXT',
            'rating' => 'INTEGER DEFAULT 0',
            'rating_comment' => 'TEXT',
            'closed_at' => 'DATETIME',
        ];

        foreach ($newCols as $col => $type) {
            if (!in_array($col, $existingCols, true)) {
                try {
                    $this->pdo->exec("ALTER TABLE conversations ADD COLUMN $col $type");
                } catch (\Exception $e) {
                    error_log("Migration failed ($col): " . $e->getMessage());
                }
            }
        }

        // Re-check conversation columns after the above additions
        $convCols2 = $this->fetchAll("PRAGMA table_info(conversations)");
        $existingConvCols = array_column($convCols2, 'name');
        if (!in_array('unread_count', $existingConvCols, true)) {
            try {
                $this->pdo->exec("ALTER TABLE conversations ADD COLUMN unread_count INTEGER DEFAULT 0");
            } catch (\Exception $e) {
                error_log("Migration failed (unread_count): " . $e->getMessage());
            }
        }
        if (!in_array('is_pinned', $existingConvCols, true)) {
            try {
                $this->pdo->exec("ALTER TABLE conversations ADD COLUMN is_pinned INTEGER DEFAULT 0");
            } catch (\Exception $e) {
                error_log("Migration failed (is_pinned): " . $e->getMessage());
            }
        }

        $checkCanned = $this->fetch("SELECT name FROM sqlite_master WHERE type='table' AND name='canned_responses'");
        if (!$checkCanned) {
            try {
                $this->pdo->exec("CREATE TABLE IF NOT EXISTS canned_responses (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    agent_id INTEGER NOT NULL,
                    shortcut TEXT NOT NULL,
                    message TEXT NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE
                )");
            } catch (\Exception $e) {
                error_log("Migration failed (canned_responses): " . $e->getMessage());
            }
        }

        $checkTyping = $this->fetch("SELECT name FROM sqlite_master WHERE type='table' AND name='typing_status'");
        if (!$checkTyping) {
            try {
                $this->pdo->exec("CREATE TABLE IF NOT EXISTS typing_status (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    conversation_id INTEGER NOT NULL,
                    user_type TEXT NOT NULL,
                    user_id INTEGER,
                    typing_text TEXT,
                    is_typing INTEGER DEFAULT 0,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
                    UNIQUE(conversation_id, user_type)
                )");
            } catch (\Exception $e) {
                error_log("Migration failed (typing_status): " . $e->getMessage());
            }
        } else {
            $hasUnique = false;
            $indexes = $this->fetchAll("PRAGMA index_list(typing_status)");
            foreach ($indexes as $idx) {
                if ($idx['unique']) {
                    $indexInfo = $this->fetchAll("PRAGMA index_info({$idx['name']})");
                    $cols = array_column($indexInfo, 'name');
                    if (count($cols) === 2 && in_array('conversation_id', $cols) && in_array('user_type', $cols)) {
                        $hasUnique = true;
                        break;
                    }
                }
            }
            if (!$hasUnique) {
                try {
                    $this->pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_typing_unique ON typing_status (conversation_id, user_type)");
                } catch (\Exception $e) {
                    error_log("Migration failed (add unique index): " . $e->getMessage());
                }
            }
        }
    }
}
