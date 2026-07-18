<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Visitor;

final class ChatService
{
    public function __construct(
        private Database $db,
        private Conversation $conversationModel,
        private Message $messageModel,
        private Visitor $visitorModel,
    ) {}

    public function sendMessage(int $conversationId, string $senderType, ?int $senderId, string $content, string $msgType = 'text'): array
    {
        $msgId = $this->messageModel->create($conversationId, $senderType, $senderId, $content, $msgType);

        $this->conversationModel->updateLastMessage($conversationId, mb_substr($content, 0, 100));

        if ($senderType === 'agent') {
            $this->conversationModel->resetUnread($conversationId);
        } else {
            $this->conversationModel->incrementUnread($conversationId);
        }

        $message = $this->messageModel->findById($msgId);
        return $message ?: [];
    }

    public function getConversationMessages(int $conversationId, int $sinceId = 0): array
    {
        if ($sinceId > 0) {
            return $this->messageModel->findRecentByConversationId($conversationId, $sinceId);
        }
        return $this->messageModel->findByConversationId($conversationId);
    }

    public function markAsRead(int $conversationId, int $agentId): void
    {
        $this->messageModel->markAsRead($conversationId, 'visitor');
        $this->conversationModel->resetUnread($conversationId);
    }

    public function closeConversation(int $conversationId): void
    {
        $this->conversationModel->close($conversationId);
    }

    public function getConversations(int $agentId): array
    {
        return $this->conversationModel->findWithUnread($agentId);
    }

    public function getUnreadCount(int $agentId): int
    {
        return $this->conversationModel->getUnreadCountByAgent($agentId);
    }

    public function initiateChat(string $sessionId, string $visitorName, array $extra = []): array
    {
        $this->db->beginTransaction();
        try {
            $visitor = $this->visitorModel->findOrCreate($sessionId, $visitorName, $extra);
            $agentId = (int)($extra['agent_id'] ?? $this->findAvailableAgent());

            $conversation = $this->db->fetch(
                "SELECT * FROM conversations WHERE visitor_id = ? AND status != 'closed' ORDER BY created_at DESC LIMIT 1",
                [$visitor['id']]
            );

            if (!$conversation) {
                $convId = $this->conversationModel->create(
                    (int)$visitor['id'],
                    $agentId,
                    $sessionId,
                    (int)($extra['embed_code_id'] ?? 0),
                    $extra
                );
                $conversation = $this->conversationModel->findById($convId);
            }

            $this->db->commit();
            return ['conversation' => $conversation, 'visitor' => $visitor];
        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    private function findAvailableAgent(): int
    {
        $agent = $this->db->fetch(
            "SELECT id FROM agents WHERE is_online = 1 ORDER BY RANDOM() LIMIT 1"
        );
        return $agent ? (int)$agent['id'] : 1;
    }

    public function handleFileUpload(array $file): ?string
    {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes, true)) {
            return null;
        }

        $extMap = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
        ];
        $ext = $extMap[$mimeType] ?? 'bin';

        if ($file['size'] > 5 * 1024 * 1024) {
            return null;
        }

        $dir = __DIR__ . '/../../public/uploads/';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $filename = 'doc_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $dest = $dir . $filename;

        if (move_uploaded_file($file['tmp_name'], $dest)) {
            return '/uploads/' . $filename;
        }

        return null;
    }
}
