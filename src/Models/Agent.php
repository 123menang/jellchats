<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Agent
{
    public function __construct(private Database $db) {}

    public function findById(int $id): ?array
    {
        return $this->db->fetch("SELECT * FROM agents WHERE id = ?", [$id]);
    }

    public function findByUserId(int $userId): ?array
    {
        return $this->db->fetch("SELECT * FROM agents WHERE user_id = ?", [$userId]);
    }

    public function findByTeamId(int $teamId): array
    {
        return $this->db->fetchAll("SELECT * FROM agents WHERE team_id = ?", [$teamId]);
    }

    public function findOnlineByTeamId(int $teamId, int $limit = 5): array
    {
        return $this->db->fetchAll(
            "SELECT id, display_name, avatar FROM agents WHERE team_id = ? AND is_online = 1 LIMIT ?",
            [$teamId, $limit]
        );
    }

    public function findOnline(int $limit = 5): array
    {
        return $this->db->fetchAll(
            "SELECT id, display_name, avatar FROM agents WHERE is_online = 1 LIMIT ?",
            [$limit]
        );
    }

    public function countOnlineByTeamId(int $teamId): int
    {
        return (int)($this->db->fetch(
            "SELECT COUNT(*) as count FROM agents WHERE team_id = ? AND is_online = 1",
            [$teamId]
        )['count'] ?? 0);
    }

    public function countOnline(): int
    {
        return (int)($this->db->fetch(
            "SELECT COUNT(*) as count FROM agents WHERE is_online = 1"
        )['count'] ?? 0);
    }

    public function ensureExists(int $userId, string $email): array
    {
        $agent = $this->findByUserId($userId);
        if ($agent) {
            return $agent;
        }

        $teamModel = new Team($this->db);
        $team = $teamModel->findByUserId($userId);
        $teamId = $team ? $team['id'] : $teamModel->createDefault($userId);

        $displayName = explode('@', $email)[0];
        $agentId = $this->db->insert(
            "INSERT INTO agents (team_id, user_id, display_name, reply_mode, created_at)
             VALUES (?, ?, ?, 'manual', datetime('now'))",
            [$teamId, $userId, $displayName]
        );

        return $this->findById($agentId);
    }

    public function toggleOnline(int $agentId): bool
    {
        $agent = $this->findById($agentId);
        if (!$agent) {
            return false;
        }

        $newStatus = $agent['is_online'] ? 0 : 1;
        $this->db->update("UPDATE agents SET is_online = ? WHERE id = ?", [$newStatus, $agentId]);
        return (bool)$newStatus;
    }

    public function setOnline(int $agentId, bool $online): void
    {
        $this->db->update("UPDATE agents SET is_online = ? WHERE id = ?", [$online ? 1 : 0, $agentId]);
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
            "UPDATE agents SET " . implode(', ', $sets) . " WHERE id = ?",
            $params
        );
    }

    public function getAll(): array
    {
        return $this->db->fetchAll(
            "SELECT a.*, t.name as team_name FROM agents a LEFT JOIN teams t ON a.team_id = t.id ORDER BY a.created_at DESC"
        );
    }
}
