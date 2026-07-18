<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Conversation
{
    public function __construct(private Database $db) {}

    public function findById(int $id): ?array
    {
        return $this->db->fetch("SELECT * FROM conversations WHERE id = ?", [$id]);
    }

    public function findByAgentId(int $agentId, string $status = 'active'): array
    {
        if ($status === 'active') {
            return $this->db->fetchAll(
                "SELECT c.*, v.name as visitor_name, v.city, v.country
                 FROM conversations c
                 JOIN visitors v ON c.visitor_id = v.id
                 WHERE c.agent_id = ? AND c.status != 'closed'
                 ORDER BY c.last_message_at DESC",
                [$agentId]
            );
        }

        return $this->db->fetchAll(
            "SELECT c.*, v.name as visitor_name
             FROM conversations c
             JOIN visitors v ON c.visitor_id = v.id
             WHERE c.agent_id = ? AND c.status = ?
             ORDER BY c.last_message_at DESC",
            [$agentId, $status]
        );
    }

    public function findWithUnread(int $agentId): array
    {
        return $this->db->fetchAll(
            "SELECT c.id, c.visitor_id, c.status, c.is_pinned, c.unread_count,
                    c.last_message_at, c.agent_id,
                    v.name as visitor_name, v.phone as visitor_phone, v.username,
                    (SELECT content FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message,
                    (SELECT sender_type FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_sender_type
             FROM conversations c
             JOIN visitors v ON c.visitor_id = v.id
             WHERE c.agent_id = ? AND c.status != 'closed'
             ORDER BY c.is_pinned DESC, c.last_message_at DESC",
            [$agentId]
        );
    }

    public function getUnreadCountByAgent(int $agentId): int
    {
        return (int)($this->db->fetch(
            "SELECT COUNT(DISTINCT c.id) as count
             FROM conversations c
             JOIN messages m ON c.id = m.conversation_id
             WHERE c.agent_id = ? AND m.sender_type = 'visitor' AND m.is_read = 0 AND c.status != 'closed'",
            [$agentId]
        )['count'] ?? 0);
    }

    public function create(int $visitorId, int $agentId, string $sessionId, int $embedCodeId, array $extra = []): int
    {
        return $this->db->insert(
            "INSERT INTO conversations (visitor_id, agent_id, embed_code_id, session_id, subject, username, phone, status, created_at, last_message_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'active', datetime('now'), datetime('now'))",
            [
                $visitorId,
                $agentId,
                $embedCodeId,
                $sessionId,
                $extra['subject'] ?? '',
                $extra['username'] ?? '',
                $extra['phone'] ?? '',
            ]
        );
    }

    public function close(int $id): void
    {
        $this->db->update(
            "UPDATE conversations SET status = 'closed', closed_at = datetime('now') WHERE id = ?",
            [$id]
        );
    }

    public function updateLastMessage(int $id, ?string $content = null): void
    {
        $this->db->update(
            "UPDATE conversations SET last_message_at = datetime('now') WHERE id = ?",
            [$id]
        );
    }

    public function incrementUnread(int $id): void
    {
        $this->db->update(
            "UPDATE conversations SET unread_count = unread_count + 1 WHERE id = ?",
            [$id]
        );
    }

    public function resetUnread(int $id): void
    {
        $this->db->update("UPDATE conversations SET unread_count = 0 WHERE id = ?", [$id]);
    }

    public function pin(int $id, bool $pin): void
    {
        $this->db->update("UPDATE conversations SET is_pinned = ? WHERE id = ?", [$pin ? 1 : 0, $id]);
    }
}
