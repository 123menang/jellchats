<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Visitor
{
    public function __construct(private Database $db) {}

    public function findById(int $id): ?array
    {
        return $this->db->fetch("SELECT * FROM visitors WHERE id = ?", [$id]);
    }

    public function findBySessionId(string $sessionId): ?array
    {
        return $this->db->fetch("SELECT * FROM visitors WHERE session_id = ?", [$sessionId]);
    }

    public function findOrCreate(string $sessionId, string $name, array $data = []): array
    {
        // Try by session_id first (returning visitor with localStorage)
        $visitor = $this->findBySessionId($sessionId);
        if ($visitor) {
            $updates = ['last_visit' => "datetime('now')", 'visit_count' => $visitor['visit_count'] + 1];
            if (!empty($data['ip'])) $updates['ip_address'] = "'" . $data['ip'] . "'";
            if (!empty($data['user_agent'])) $updates['user_agent'] = "'" . $data['user_agent'] . "'";

            foreach ($updates as $col => $val) {
                if (str_starts_with((string)$val, "datetime") || str_starts_with((string)$val, "'")) {
                    $this->db->getPdo()->exec("UPDATE visitors SET $col = $val WHERE id = {$visitor['id']}");
                }
            }

            return $visitor;
        }

        // Try by (username, phone) next — visitor cleared localStorage
        $existing = $this->db->fetch(
            "SELECT * FROM visitors WHERE username = ? AND phone = ?",
            [$name, $data['phone'] ?? '']
        );
        if ($existing) {
            $this->db->update(
                "UPDATE visitors SET session_id = ?, last_visit = datetime('now'), visit_count = visit_count + 1 WHERE id = ?",
                [$sessionId, $existing['id']]
            );
            return $this->findById($existing['id']);
        }

        // Create new visitor
        $visitorId = $this->db->insert(
            "INSERT INTO visitors (session_id, name, username, phone, email, ip_address, user_agent, city, country, first_visit, last_visit, visit_count)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'), 1)",
            [
                $sessionId,
                $name,
                $data['username'] ?? $name,
                $data['phone'] ?? '',
                $data['email'] ?? '',
                $data['ip'] ?? '',
                $data['user_agent'] ?? '',
                $data['city'] ?? '',
                $data['country'] ?? '',
            ]
        );

        return $this->findById($visitorId);
    }

    public function markAsNotified(int $id): void
    {
        $this->db->update("UPDATE visitors SET is_new = 0 WHERE id = ?", [$id]);
    }

    public function getNewSince(int $agentId, string $since): ?array
    {
        return $this->db->fetch(
            "SELECT v.* FROM visitors v
             JOIN conversations c ON v.id = c.visitor_id
             WHERE c.agent_id = ? AND v.is_new = 1 AND v.first_visit >= ?
             ORDER BY v.first_visit DESC LIMIT 1",
            [$agentId, $since]
        );
    }
}
