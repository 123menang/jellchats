<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;

final class WidgetController
{
    private static function matchDomain(string $allowed, string $origin): bool
    {
        $allowed = strtolower(trim($allowed));
        $origin = strtolower(trim($origin));
        $origin = preg_replace('#^https?://#', '', $origin);
        $origin = preg_replace('#[:/].*$#', '', $origin);
        $allowed = preg_replace('#^https?://#', '', $allowed);
        $allowed = preg_replace('#[:/].*$#', '', $allowed);
        if ($allowed === $origin) return true;
        if (str_starts_with($allowed, '*.')) {
            return str_ends_with($origin, substr($allowed, 1));
        }
        return false;
    }

    private static function checkDomain(string $licenseKey): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
        if (empty($origin)) return;

        $embed = App::db()->fetch(
            "SELECT site_url, allowed_domains FROM embed_codes WHERE embed_key = ? AND status = 1",
            [$licenseKey]
        );
        if (!$embed) return;

        $domains = [];
        if (!empty($embed['allowed_domains'])) {
            $domains = array_map('trim', explode(',', $embed['allowed_domains']));
        }
        if (!empty($embed['site_url'])) {
            $domains[] = $embed['site_url'];
        }
        if (empty($domains)) return;

        foreach ($domains as $d) {
            if (self::matchDomain($d, $origin)) return;
        }

        error_log("Domain mismatch: origin=$origin, allowed=" . implode(',', $domains) . ", key=$licenseKey");
        Response::json(['success' => false, 'error' => 'Domain not allowed'], 403);
    }

    public function config(Request $req): void
    {
        $licenseKey = $req->query('license_key', '');

        if (empty($licenseKey)) {
            Response::json(['success' => false, 'error' => 'Missing license_key'], 400);
        }

        self::checkDomain($licenseKey);

        App::rateLimiter()->require('widget_config', 60, 60);

        // Check IP ban
        $visitorIp = $req->ip();
        try {
            $banned = App::db()->fetch("SELECT is_banned FROM visitors WHERE ip_address = ? AND is_banned = 1 LIMIT 1", [$visitorIp]);
            if ($banned) {
                Response::json(['success' => false, 'error' => 'Access denied'], 403);
            }
        } catch (\Exception $e) {
            error_log("Ban check error: " . $e->getMessage());
        }

        // Look up embed code
        $embed = App::db()->fetch(
            "SELECT * FROM embed_codes WHERE embed_key = ? AND status = 1",
            [$licenseKey]
        );

        if (!$embed) {
            Response::json(['success' => false, 'error' => 'Invalid license key'], 404);
        }

        // Check license status
        $agentData = App::db()->fetch(
            "SELECT a.*, u.id as user_id, u.license_expires, u.license_tier
             FROM agents a
             JOIN users u ON a.user_id = u.id
             WHERE a.id = ?",
            [$embed['agent_id']]
        );

        $licenseStatus = 'active';
        $remainingDays = null;

        if ($agentData) {
            $subscription = App::db()->fetch(
                "SELECT * FROM subscriptions WHERE user_id = ? AND status = 'active' AND end_date >= date('now') ORDER BY end_date DESC LIMIT 1",
                [$agentData['user_id']]
            );

            if ($subscription) {
                $endDate = new \DateTime($subscription['end_date']);
                $now = new \DateTime();
                $interval = $now->diff($endDate);
                $remainingDays = $interval->invert ? -$interval->days : $interval->days;
                if ($endDate < $now) {
                    $licenseStatus = 'expired';
                }
            } elseif (!empty($agentData['license_expires'])) {
                $endDate = new \DateTime($agentData['license_expires']);
                $now = new \DateTime();
                $interval = $now->diff($endDate);
                $remainingDays = $interval->invert ? -$interval->days : $interval->days;
                if ($endDate < $now) {
                    $licenseStatus = 'expired';
                }
            } else {
                $licenseStatus = 'inactive';
            }
        }

        if ($licenseStatus !== 'active') {
            Response::json([
                'success' => false,
                'license_status' => $licenseStatus,
                'message' => 'License expired or inactive. Widget disabled.',
            ], 403);
        }

        // Get agent info
        $agentInfo = App::db()->fetch(
            "SELECT a.display_name, a.is_online, a.reply_mode, u.avatar
             FROM agents a
             JOIN users u ON a.user_id = u.id
             WHERE a.id = ?",
            [$embed['agent_id']]
        );

        $config = json_decode($embed['widget_config'] ?? '{}', true) ?: [];
        $baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . "://{$_SERVER['HTTP_HOST']}/";
        $rawAvatar = $config['agent_avatar'] ?? $agentInfo['avatar'] ?? '';
        $finalAvatar = (!empty($rawAvatar) && (str_starts_with($rawAvatar, 'http') || str_starts_with($rawAvatar, '//'))) ? $rawAvatar : $baseUrl . ($rawAvatar ?: 'assets/images/default-avatar.png');

        $prechatFields = $config['prechat_fields'] ?? [];
        $lcInfoBox = $config['lc_info_box'] ?? 'Please fill in the form to start chatting.';

        $responseConfig = array_merge([
            'primary_color' => '#1e62ff',
            'widget_theme' => 'transparent',
            'position' => 'right',
            'welcome_message' => 'Hello! How can we help you?',
            'pre_chat_form' => (int)($embed['pre_chat_form'] ?? 1),
            'allow_upload' => (int)($embed['allow_upload'] ?? 1),
            'show_typing' => 1,
            'agent_name' => $agentInfo['display_name'] ?? 'Support',
            'agent_avatar' => $finalAvatar,
            'is_online' => (int)($agentInfo['is_online'] ?? 0),
            'reply_mode' => $agentInfo['reply_mode'] ?? 'hybrid',
            'prechat_fields' => $prechatFields,
            'lc_info_box' => $lcInfoBox,
            'widget_version' => '',
        ], $config);

        Response::json([
            'success' => true,
            'license_status' => $licenseStatus,
            'remaining_days' => $remainingDays,
            'site_name' => $embed['site_name'],
            'config' => $responseConfig,
        ]);
    }

    public function chat(Request $req): void
    {
        App::rateLimiter()->require('widget_chat', 30, 60);

        if ($req->method() === 'GET') {
            $sessionId = $req->query('session_id', '');
            $licenseKey = $req->query('license_key', '');

            if (empty($sessionId) || empty($licenseKey)) {
                Response::json(['success' => false, 'error' => 'Missing required params'], 400);
            }

            self::checkDomain($licenseKey);

            $config = App::db()->fetch("SELECT id, agent_id FROM embed_codes WHERE embed_key = ? AND status = 1", [$licenseKey]);
            if (!$config) {
                Response::json(['success' => false, 'error' => 'Invalid license'], 404);
            }

            $ip = $req->ip();
            $banned = App::db()->fetch("SELECT is_banned FROM visitors WHERE ip_address = ? AND is_banned = 1 LIMIT 1", [$ip]);
            if ($banned) {
                Response::json(['success' => false, 'error' => 'Access denied'], 403);
            }

            try {
                $result = App::chat()->initiateChat($sessionId, $req->query('name', 'Visitor'), [
                    'agent_id' => $config['agent_id'],
                    'embed_code_id' => $config['id'],
                    'username' => $req->query('name', ''),
                    'phone' => $req->query('phone', ''),
                    'subject' => $req->query('subject', ''),
                    'ip' => $ip,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                ]);

                $existingMessages = App::db()->fetchAll(
                    "SELECT * FROM messages WHERE conversation_id = ? ORDER BY created_at ASC",
                    [$result['conversation']['id']]
                );

                Response::json([
                    'success' => true,
                    'conversation_id' => $result['conversation']['id'],
                    'visitor_id' => $result['visitor']['id'],
                    'session_id' => $sessionId,
                    'resume' => !empty($existingMessages),
                    'messages' => $existingMessages,
                ]);
            } catch (\Exception $e) {
                error_log('WidgetController::chat(GET) error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
                Response::error('Failed to initiate chat: ' . $e->getMessage(), 500);
            }
        }

        if ($req->method() === 'POST') {
            try {
                $file = $req->file('file');
                $convId = (int)$req->input('conversation_id', 0);
                $content = $req->input('content', '');
                $senderId = (int)$req->input('sender_id', 0);

                // File upload
                if ($file && $file['error'] === UPLOAD_ERR_OK) {
                    $url = App::chat()->handleFileUpload($file);
                    if (!$url) {
                        Response::error('Invalid file', 400);
                    }
                    if (!$convId) {
                        Response::error('Missing conversation_id', 400);
                    }
                    $message = App::chat()->sendMessage($convId, 'visitor', $senderId ?: null, $url, 'file');
                    Response::json(['success' => true, 'url' => $url, 'message' => $message]);
                }

                // Text message
                if (!$convId || empty($content)) {
                    Response::error('Missing required fields', 400);
                }

                $message = App::chat()->sendMessage($convId, 'visitor', $senderId ?: null, $content);

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
            } catch (\Exception $e) {
                error_log('WidgetController::chat(POST) error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
                Response::error('Failed to send message: ' . $e->getMessage(), 500);
            }
        }
    }

    public function pollMessages(Request $req): void
    {
        App::rateLimiter()->require('widget_poll', 120, 60);

        $convId = (int)$req->input('conversation_id', 0);
        $sinceId = (int)$req->input('since_id', 0);

        if (!$convId) {
            Response::error('Missing conversation_id', 400);
        }

        $messages = App::db()->fetchAll(
            "SELECT * FROM messages WHERE conversation_id = ? AND id > ? ORDER BY created_at ASC",
            [$convId, $sinceId]
        );

        $typing = App::db()->fetch(
            "SELECT is_typing, typing_text FROM typing_status WHERE conversation_id = ? AND user_type = 'agent'",
            [$convId]
        );

        $conversation = App::db()->fetch(
            "SELECT status FROM conversations WHERE id = ?",
            [$convId]
        );

        Response::json([
            'success' => true,
            'messages' => $messages,
            'last_id' => $messages ? end($messages)['id'] : $sinceId,
            'is_typing' => $typing ? (bool)$typing['is_typing'] : false,
            'typing_text' => $typing['typing_text'] ?? '',
            'conv_status' => $conversation['status'] ?? 'closed',
        ]);
    }

    public function typing(Request $req): void
    {
        $convId = (int)$req->input('conversation_id', 0);
        $isTyping = (int)$req->input('is_typing', 0);
        $text = $req->input('typing_text', '');

        if (!$convId) {
            Response::error('Missing conversation_id', 400);
        }

        App::db()->insert(
            "INSERT INTO typing_status (conversation_id, user_type, is_typing, typing_text, updated_at)
             VALUES (?, 'visitor', ?, ?, datetime('now'))
             ON CONFLICT(conversation_id, user_type)
             DO UPDATE SET is_typing = ?, typing_text = ?, updated_at = datetime('now')",
            [$convId, $isTyping, $text, $isTyping, $text]
        );

        Response::json(['success' => true]);
    }

    public function notifications(Request $req): void
    {
        App::rateLimiter()->require('check_notif', 30, 60);

        $agentId = (int)$req->query('agent_id', 0);
        $since = $req->query('since', date('Y-m-d H:i:s', strtotime('-5 seconds')));

        $response = [
            'play' => null,
            'timestamp' => date('Y-m-d H:i:s'),
            'online_agents' => App::agentModel()->countOnline(),
        ];

        if ($agentId) {
            $newMsg = App::db()->fetch(
                "SELECT m.id, m.conversation_id, m.sender_type
                 FROM messages m
                 JOIN conversations c ON m.conversation_id = c.id
                 WHERE c.agent_id = ? AND m.sender_type != 'agent' AND m.is_read = 0 AND m.created_at > ?
                 ORDER BY m.id DESC LIMIT 1",
                [$agentId, $since]
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
        }

        Response::json($response);
    }
}
