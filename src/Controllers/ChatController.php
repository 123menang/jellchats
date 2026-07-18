<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;

final class ChatController
{
    public function index(Request $req): void
    {
        $user = App::auth()->getCurrentUser();
        $agent = App::auth()->getCurrentAgent();

        if ($req->query('toggle_online') !== null) {
            $isOnline = App::auth()->toggleOnline();
            Response::json(['status' => 'success', 'is_online' => $isOnline]);
        }

        // Legacy compatibility: set globals for the original chats.php
        $myAgentId = $agent['id'] ?? 0;
        $myTeamId = $agent['team_id'] ?? -1;
        $isOnline = $agent['is_online'] ?? 0;
        $uAva = ($user['avatar'] ?? '') ?: 'assets/images/default-avatar.png';
        $activePage = 'chats';
        $pageTitle = 'Chats';
        $db = App::db();

        require __DIR__ . '/../../chats.php';
    }

    public function getConversations(Request $req): void
    {
        $agent = App::auth()->getCurrentAgent();
        if (!$agent) {
            Response::error('Agent not found');
        }

        $conversations = App::chat()->getConversations((int)$agent['id']);
        $unreadCount = App::chat()->getUnreadCount((int)$agent['id']);

        $result = [];
        foreach ($conversations as $conv) {
            $result[] = [
                'id' => $conv['id'],
                'visitor_id' => $conv['visitor_id'],
                'visitor_name' => $conv['visitor_name'],
                'status' => $conv['status'],
                'unread_count' => $conv['unread_count'],
                'is_pinned' => $conv['is_pinned'],
                'last_message_at' => $conv['last_message_at'],
                'last_message' => $conv['last_message'] ?? '',
                'last_sender_type' => $conv['last_sender_type'] ?? '',
            ];
        }

        Response::json([
            'success' => true,
            'conversations' => $result,
            'total_unread' => $unreadCount,
        ]);
    }

    public function sendMessage(Request $req): void
    {
        $agent = App::auth()->getCurrentAgent();
        if (!$agent) {
            Response::error('Unauthorized', 401);
        }

        $convId = (int)$req->post('conversation_id', 0);
        $content = $req->post('content', '');

        if (!$convId || empty($content)) {
            Response::error('Missing required fields');
        }

        $message = App::chat()->sendMessage($convId, 'agent', (int)$agent['id'], $content);

        $payload = json_encode([
            'success' => true,
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE);
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo $payload;

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        try {
            $autoReply = App::ai()->processAutoReply($convId, $content);
            if ($autoReply) {
                App::chat()->sendMessage($convId, $autoReply['type'], null, $autoReply['text']);
            }
        } catch (\Exception $e) {
            error_log('Auto-reply background error: ' . $e->getMessage());
        }
        exit;
    }

    public function pollMessages(Request $req): void
    {
        $agent = App::auth()->getCurrentAgent();
        if (!$agent) {
            Response::error('Unauthorized', 401);
        }

        $convId = (int)($req->input('conversation_id') ?: $req->query('conv', 0));
        $sinceId = (int)($req->input('since_id') ?: $req->query('last_id', 0));

        App::rateLimiter()->require('poll_messages', 120, 60);

        if ($convId) {
            $messages = App::chat()->getConversationMessages($convId, $sinceId);
            App::chat()->markAsRead($convId, (int)$agent['id']);

            // Check typing status
            $typing = App::db()->fetch(
                "SELECT is_typing, typing_text, user_type FROM typing_status WHERE conversation_id = ? AND user_type = 'visitor'",
                [$convId]
            );

            $unreadCount = App::db()->fetch(
                "SELECT COUNT(*) as cnt FROM messages WHERE conversation_id = ? AND sender_type='visitor' AND is_read=0",
                [$convId]
            );
            $convInfo = App::db()->fetch(
                "SELECT status FROM conversations WHERE id = ?",
                [$convId]
            );

            Response::json([
                'success' => true,
                'messages' => $messages,
                'last_id' => $messages ? end($messages)['id'] : $sinceId,
                'unread_count' => (int)($unreadCount['cnt'] ?? 0),
                'conv_status' => $convInfo['status'] ?? '',
                'is_typing' => $typing ? (bool)$typing['is_typing'] : false,
                'typing_text' => $typing['typing_text'] ?? '',
            ]);
        }

        Response::json(['success' => true, 'messages' => [], 'last_id' => 0]);
    }

    public function closeChat(Request $req): void
    {
        $convId = (int)$req->input('conversation_id', 0);
        if (!$convId) {
            Response::error('Missing conversation_id');
        }

        App::chat()->closeConversation($convId);
        Response::json(['success' => true]);
    }

    public function typing(Request $req): void
    {
        $agent = App::auth()->getCurrentAgent();
        if (!$agent) {
            Response::error('Unauthorized', 401);
        }

        $convId = (int)$req->input('conversation_id', 0);
        $isTyping = (int)$req->input('is_typing', 0);
        $text = $req->input('typing_text', '');

        if (!$convId) {
            Response::error('Missing conversation_id');
        }

        App::db()->insert(
            "INSERT INTO typing_status (conversation_id, user_type, user_id, is_typing, typing_text, updated_at)
             VALUES (?, 'agent', ?, ?, ?, datetime('now'))
             ON CONFLICT(conversation_id, user_type)
             DO UPDATE SET is_typing = ?, typing_text = ?, updated_at = datetime('now')",
            [$convId, $agent['id'], $isTyping, $text, $isTyping, $text]
        );

        Response::json(['success' => true]);
    }

    public function notifications(Request $req): void
    {
        $agent = App::auth()->getCurrentAgent();
        if (!$agent) {
            Response::error('Unauthorized', 401);
        }

        $since = $req->query('since', date('Y-m-d H:i:s', strtotime('-5 seconds')));

        App::rateLimiter()->require('check_notif', 30, 60);

        $response = ['play' => null, 'timestamp' => date('Y-m-d H:i:s')];

        $newMsg = App::db()->fetch(
            "SELECT m.id, m.conversation_id, m.sender_type
             FROM messages m
             JOIN conversations c ON m.conversation_id = c.id
             WHERE c.agent_id = ? AND m.sender_type != 'agent' AND m.is_read = 0 AND m.created_at > ?
             ORDER BY m.id DESC LIMIT 1",
            [(int)$agent['id'], $since]
        );

        if ($newMsg) {
            $msgCount = App::db()->fetch(
                "SELECT COUNT(*) as total FROM messages WHERE conversation_id = ?",
                [$newMsg['conversation_id']]
            );
            $response['play'] = ((int)$msgCount['total'] === 1) ? 'incoming_chat' : 'message';
        } else {
            $visitor = App::db()->fetch(
                "SELECT visit_count FROM visitors WHERE last_visit > ? ORDER BY last_visit DESC LIMIT 1",
                [$since]
            );
            if ($visitor) {
                $response['play'] = ((int)$visitor['visit_count'] === 1) ? 'new_visitor' : 'returning_visitor';
            }
        }

        $response['online_agents'] = App::agentModel()->countOnline();

        Response::json($response);
    }

    private function getLayoutData(?array $user, ?array $agent): array
    {
        $userId = $user['id'] ?? 0;
        $agentId = $agent['id'] ?? 0;
        $teamId = $agent['team_id'] ?? -1;
        $role = $user['role'] ?? 'agent';

        if ($role === 'agent') {
            $onlineAgents = App::agentModel()->findOnlineByTeamId((int)$teamId);
            $totalOnline = App::agentModel()->countOnlineByTeamId((int)$teamId);
        } else {
            $onlineAgents = App::agentModel()->findOnline();
            $totalOnline = App::agentModel()->countOnline();
        }

        $totalUnread = App::conversationModel()->getUnreadCountByAgent((int)$agentId);
        $uAva = ($user['avatar'] ?? '') ?: '/assets/images/default-avatar.png';
        $isOnline = $agent['is_online'] ?? 0;

        return [
            'user' => $user,
            'agent' => $agent,
            'myAgentId' => $agentId,
            'myTeamId' => $teamId,
            'isOnline' => $isOnline,
            'uAva' => $uAva,
            'onlineAgents' => $onlineAgents,
            'totalOnline' => $totalOnline,
            'totalUnread' => $totalUnread,
        ];
    }
}
