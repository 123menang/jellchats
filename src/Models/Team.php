<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Team
{
    public function __construct(private Database $db) {}

    public function findById(int $id): ?array
    {
        return $this->db->fetch("SELECT * FROM teams WHERE id = ?", [$id]);
    }

    public function findByUserId(int $userId): ?array
    {
        return $this->db->fetch("SELECT * FROM teams WHERE user_id = ? LIMIT 1", [$userId]);
    }

    public function findByUserIdAll(int $userId): array
    {
        return $this->db->fetchAll("SELECT * FROM teams WHERE user_id = ? ORDER BY created_at DESC", [$userId]);
    }

    public function createDefault(int $userId): int
    {
        return $this->db->insert(
            "INSERT INTO teams (user_id, name, description, max_agents, created_at)
             VALUES (?, 'Default Team', 'Auto-created team', 10, datetime('now'))",
            [$userId]
        );
    }

    public function create(int $userId, string $name, string $description = '', int $maxAgents = 10): int
    {
        return $this->db->insert(
            "INSERT INTO teams (user_id, name, description, max_agents, created_at)
             VALUES (?, ?, ?, ?, datetime('now'))",
            [$userId, $name, $description, $maxAgents]
        );
    }

    public function update(int $id, array $data): int
    {
        $sets = [];
        $params = [];
        foreach ($data as $col => $val) {
            $sets[] = "$col = ?";
            $params[] = $val;
        }
        $params[] = $id;
        return $this->db->update(
            "UPDATE teams SET " . implode(', ', $sets) . " WHERE id = ?",
            $params
        );
    }

    public function delete(int $id): int
    {
        return $this->db->delete("DELETE FROM teams WHERE id = ?", [$id]);
    }

    public function getAgentCount(int $teamId): int
    {
        return (int)($this->db->fetch(
            "SELECT COUNT(*) as count FROM agents WHERE team_id = ?",
            [$teamId]
        )['count'] ?? 0);
    }
}
