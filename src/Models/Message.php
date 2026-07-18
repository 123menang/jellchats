<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Message
{
    public function __construct(private Database $db) {}

    public function findById(int $id): ?array
    {
        return $this->db->fetch("SELECT * FROM messages WHERE id = ?", [$id]);
    }

    public function findByConversationId(int $conversationId, int $limit = 50): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM messages WHERE conversation_id = ? ORDER BY created_at ASC LIMIT ?",
            [$conversationId, $limit]
        );
    }

    public function findRecentByConversationId(int $conversationId, int $sinceId = 0): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM messages WHERE conversation_id = ? AND id > ? ORDER BY created_at ASC",
            [$conversationId, $sinceId]
        );
    }

    public function create(int $conversationId, string $senderType, ?int $senderId, string $content, string $msgType = 'text'): int
    {
        $fileUrl = $msgType === 'file' ? $content : null;
        return $this->db->insert(
            "INSERT INTO messages (conversation_id, sender_type, sender_id, content, content_type, file_url, created_at)
             VALUES (?, ?, ?, ?, ?, ?, datetime('now'))",
            [$conversationId, $senderType, $senderId, $content, $msgType, $fileUrl]
        );
    }

    public function markAsRead(int $conversationId, string $senderType): int
    {
        return $this->db->update(
            "UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND sender_type = ? AND is_read = 0",
            [$conversationId, $senderType]
        );
    }

    public function countUnreadByConversation(int $conversationId, string $senderType): int
    {
        return (int)($this->db->fetch(
            "SELECT COUNT(*) as count FROM messages WHERE conversation_id = ? AND sender_type = ? AND is_read = 0",
            [$conversationId, $senderType]
        )['count'] ?? 0);
    }

    public function getHistoryForAI(int $conversationId, int $limit = 20): array
    {
        $messages = $this->db->fetchAll(
            "SELECT sender_type, content FROM messages WHERE conversation_id = ? ORDER BY created_at DESC LIMIT ?",
            [$conversationId, $limit]
        );
        return array_reverse($messages);
    }
}
