<?php
/**
 * Helper Functions - FIXED & ENHANCED
 */

if (!function_exists('formatRupiah')) {
    function formatRupiah($amount) {
        return 'Rp' . number_format($amount, 0, ',', '.');
    }
}

if (!function_exists('timeAgo')) {
    function timeAgo($datetime) {
        $time = strtotime($datetime);
        $now = time();
        $diff = $now - $time;

        if ($diff < 60) return $diff . 's';
        if ($diff < 3600) return floor($diff / 60) . 'm';
        if ($diff < 86400) return floor($diff / 3600) . 'h';
        if ($diff < 604800) return floor($diff / 86400) . 'd';
        return date('M d', $time);
    }
}

if (!function_exists('sanitizeInput')) {
    function sanitizeInput($data) {
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('generateEmbedKey')) {
    function generateEmbedKey() {
        return '' . substr(str_shuffle('1234567890'), 0, 10);
    }
}

if (!function_exists('generateSessionId')) {
    function generateSessionId() {
        return 'sess_' . uniqid() . '_' . bin2hex(random_bytes(8));
    }
}

if (!function_exists('getClientIP')) {
    function getClientIP() {
        $ip = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        // Handle comma-separated IPs from X-Forwarded-For
        if (strpos($ip, ',') !== false) {
            $ip = trim(explode(',', $ip)[0]);
        }
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }
}

if (!function_exists('getGeoInfo')) {
    function getGeoInfo($ip) {
        if ($ip === '127.0.0.1' || $ip === '0.0.0.0' || strpos($ip, '192.168.') === 0) {
            return ['country' => 'Local', 'city' => 'Localhost', 'region' => 'Dev'];
        }
        $geo = @json_decode(file_get_contents("http://ip-api.com/json/{$ip}?fields=status,country,city,regionName"), true);
        if ($geo && $geo['status'] === 'success') {
            return [
                'country' => $geo['country'],
                'city' => $geo['city'],
                'region' => $geo['regionName']
            ];
        }
        return ['country' => 'Unknown', 'city' => 'Unknown', 'region' => 'Unknown'];
    }
}

if (!function_exists('jsonResponse')) {
    function jsonResponse($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}

if (!function_exists('getLicenseLimits')) {
    function getLicenseLimits($tier) {
        $db = Database::getInstance();
        return $db->fetch("SELECT * FROM license_tiers WHERE name = ?", [$tier]);
    }
}

/**
 * Ambil data subscription terbaru milik user
 * @param int $userId
 * @return array|null
 */
function getUserSubscription($userId) {
    $db = Database::getInstance();
    return $db->fetch(
        "SELECT * FROM subscriptions WHERE user_id = ? ORDER BY start_date DESC LIMIT 1",
        [$userId]
    );
}

function checkLicenseLimit($userId, $type) {
    $db = Database::getInstance();
    $user = $db->fetch("SELECT * FROM users WHERE id = ?", [$userId]);
    $limits = getLicenseLimits($user['license_tier']);

    switch ($type) {
        case 'teams':
            $count = $db->fetch("SELECT COUNT(*) as count FROM teams WHERE user_id = ?", [$userId])['count'];
            return $count < $limits['max_teams'];
        case 'agents':
            $count = $db->fetch("SELECT COUNT(*) as count FROM agents a JOIN teams t ON a.team_id = t.id WHERE t.user_id = ?", [$userId])['count'];
            return $count < ($limits['max_teams'] * $limits['max_agents_per_team']);
        case 'ai':
            return $limits['ai_enabled'] == 1;
    }
    return false;
}

// ============================================================
// RATE LIMITING
// ============================================================
function checkRateLimit($key, $maxRequests = 60, $window = 60) {
    $rateKey = 'rate_' . $key . '_' . getClientIP();
    $now = time();
    $windowStart = $now - $window;

    if (!isset($_SESSION[$rateKey])) {
        $_SESSION[$rateKey] = [];
    }

    // Clean old entries
    $_SESSION[$rateKey] = array_filter($_SESSION[$rateKey], function($t) use ($windowStart) {
        return $t > $windowStart;
    });

    if (count($_SESSION[$rateKey]) >= $maxRequests) {
        return false;
    }

    $_SESSION[$rateKey][] = $now;
    return true;
}

function requireRateLimit($key, $maxRequests = 60, $window = 60) {
    if (!checkRateLimit($key, $maxRequests, $window)) {
        http_response_code(429);
        echo json_encode(['success' => false, 'error' => 'Too many requests. Please slow down.']);
        exit;
    }
}

function logActivity($userId, $action, $entityType = null, $entityId = null, $details = null) {
    $db = Database::getInstance();
    $db->insert(
        "INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)",
        [$userId, $action, $entityType, $entityId, $details, getClientIP()]
    );
}
/**
 * Ambil informasi user + agent + semua embed code
 */
function getUserAgentInfo($userId) {
    $db = Database::getInstance();
    $user = $db->fetch("SELECT * FROM users WHERE id = ?", [$userId]);
    if (!$user) return null;

    $agent = $db->fetch("SELECT * FROM agents WHERE user_id = ?", [$userId]);

    $embedCodes = [];
    if ($agent) {
        $embedCodes = $db->fetchAll("SELECT * FROM embed_codes WHERE agent_id = ?", [$agent['id']]);
    }

    return [
        'user'        => $user,
        'agent'       => $agent ?: null,
        'embed_codes' => $embedCodes
    ];
}

/**
 * Ambil data agent + team + embed codes + subscription + sisa hari
 */
function getAgentSubscription($userId) {
    $db = Database::getInstance();
    $user = $db->fetch("SELECT * FROM users WHERE id = ?", [$userId]);
    if (!$user) return null;

    $agent = $db->fetch("SELECT * FROM agents WHERE user_id = ?", [$userId]);
    $team = null;
    if ($agent && !empty($agent['team_id'])) {
        $team = $db->fetch("SELECT * FROM teams WHERE id = ?", [$agent['team_id']]);
    }

    $embedCodes = $agent ? $db->fetchAll("SELECT * FROM embed_codes WHERE agent_id = ?", [$agent['id']]) : [];

    $subscription = $db->fetch(
        "SELECT * FROM subscriptions WHERE user_id = ? ORDER BY start_date DESC LIMIT 1",
        [$userId]
    );

    $remainingDays = null;
    if ($subscription && !empty($subscription['end_date'])) {
        $endDate = strtotime($subscription['end_date']);
        $remainingDays = floor(($endDate - time()) / 86400);
    }

    return [
        'user'           => $user,
        'agent'          => $agent ?: null,
        'team'           => $team,
        'embed_codes'    => $embedCodes,
        'subscription'   => $subscription ?: null,
        'remaining_days' => $remainingDays
    ];
}

// ============================================================
// AI INTEGRATION FUNCTIONS
// ============================================================

/**
 * Match message against chat modules (keyword rules)
 * Returns response text or null if no match
 */
function matchChatModule($agentId, $messageContent) {
    $db = Database::getInstance();
    $modules = $db->fetchAll(
        "SELECT * FROM chat_modules WHERE agent_id = ? AND is_active = 1 ORDER BY priority DESC",
        [$agentId]
    );

    $message = strtolower(trim($messageContent));

    foreach ($modules as $module) {
        $trigger = strtolower($module['trigger_value']);
        $match = false;

        switch ($module['trigger_type']) {
            case 'keyword':
                $keywords = array_map('trim', explode(',', $trigger));
                foreach ($keywords as $kw) {
                    if ($kw !== '' && strpos($message, $kw) !== false) {
                        $match = true;
                        break;
                    }
                }
                break;
            case 'exact':
                $match = ($message === $trigger);
                break;
            case 'regex':
                $match = (@preg_match($module['trigger_value'], $messageContent) === 1);
                break;
            case 'starts_with':
                $match = (strpos($message, $trigger) === 0);
                break;
        }

        if ($match) {
            $db->update("UPDATE chat_modules SET match_count = match_count + 1 WHERE id = ?", [$module['id']]);
            return $module['response_text'];
        }
    }

    return null;
}

/**
 * Call Claude (Anthropic) API
 */
function callClaudeAPI($apiToken, $model, $systemPrompt, $conversationHistory, $userMessage) {
    $messages = [];
    foreach ($conversationHistory as $msg) {
        if ($msg['sender_type'] === 'visitor') {
            $messages[] = ['role' => 'user', 'content' => $msg['content']];
        } elseif (in_array($msg['sender_type'], ['bot', 'ai', 'agent'])) {
            $messages[] = ['role' => 'assistant', 'content' => $msg['content']];
        }
    }
    $messages[] = ['role' => 'user', 'content' => $userMessage];

    $payload = [
        'model' => $model ?: 'claude-haiku-4-5-20251001',
        'max_tokens' => 1024,
        'messages' => $messages
    ];
    if ($systemPrompt) {
        $payload['system'] = $systemPrompt;
    }

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-api-key: ' . $apiToken,
            'anthropic-version: 2023-06-01'
        ],
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if (isset($data['content'][0]['text'])) {
            return ['success' => true, 'text' => $data['content'][0]['text']];
        }
    }

    $err = json_decode($response, true);
    return ['success' => false, 'error' => $err['error']['message'] ?? 'API Error ' . $httpCode];
}

/**
 * Call Google Gemini API
 */
function callGeminiAPI($apiToken, $model, $systemPrompt, $conversationHistory, $userMessage) {
    $geminiModel = $model ?: 'gemini-1.5-flash';
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$geminiModel}:generateContent?key={$apiToken}";

    $contents = [];

    // Add system instruction as first user turn if provided
    if ($systemPrompt) {
        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => "System instruction: " . $systemPrompt]]
        ];
        $contents[] = [
            'role' => 'model',
            'parts' => [['text' => 'Understood. I will follow those instructions.']]
        ];
    }

    foreach ($conversationHistory as $msg) {
        if ($msg['sender_type'] === 'visitor') {
            $contents[] = ['role' => 'user', 'parts' => [['text' => $msg['content']]]];
        } elseif (in_array($msg['sender_type'], ['bot', 'ai', 'agent'])) {
            $contents[] = ['role' => 'model', 'parts' => [['text' => $msg['content']]]];
        }
    }
    $contents[] = ['role' => 'user', 'parts' => [['text' => $userMessage]]];

    $payload = ['contents' => $contents];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $data = json_decode($response, true);
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if ($text) {
            return ['success' => true, 'text' => $text];
        }
    }

    $err = json_decode($response, true);
    return ['success' => false, 'error' => $err['error']['message'] ?? 'Gemini API Error ' . $httpCode];
}

/**
 * Call OpenAI-compatible API (OpenAI, DeepSeek, etc.)
 */
function callOpenAIAPI($apiToken, $model, $systemPrompt, $conversationHistory, $userMessage) {
    $messages = [];
    if ($systemPrompt) {
        $messages[] = ['role' => 'system', 'content' => $systemPrompt];
    }
    foreach ($conversationHistory as $msg) {
        if ($msg['sender_type'] === 'visitor') {
            $messages[] = ['role' => 'user', 'content' => $msg['content']];
        } elseif (in_array($msg['sender_type'], ['bot', 'ai', 'agent'])) {
            $messages[] = ['role' => 'assistant', 'content' => $msg['content']];
        }
    }
    $messages[] = ['role' => 'user', 'content' => $userMessage];

    $payload = [
        'model' => $model ?: 'gpt-3.5-turbo',
        'messages' => $messages,
        'max_tokens' => 1024
    ];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiToken
        ],
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $data = json_decode($response, true);
        $text = $data['choices'][0]['message']['content'] ?? null;
        if ($text) {
            return ['success' => true, 'text' => $text];
        }
    }

    $err = json_decode($response, true);
    return ['success' => false, 'error' => $err['error']['message'] ?? 'OpenAI API Error ' . $httpCode];
}

/**
 * Main AI dispatcher — selects provider based on agent config
 */
function callAI($agent, $conversationHistory, $userMessage) {
    if (empty($agent['ai_api_token'])) {
        return ['success' => false, 'error' => 'No API token configured'];
    }

    $provider = $agent['ai_provider'] ?? 'claude';
    $model = $agent['ai_model'] ?? '';
    $systemPrompt = $agent['ai_system_prompt'] ?? '';
    $token = $agent['ai_api_token'];

    switch ($provider) {
        case 'gemini':
            return callGeminiAPI($token, $model, $systemPrompt, $conversationHistory, $userMessage);
        case 'openai':
            return callOpenAIAPI($token, $model, $systemPrompt, $conversationHistory, $userMessage);
        case 'claude':
        default:
            return callClaudeAPI($token, $model, $systemPrompt, $conversationHistory, $userMessage);
    }
}

/**
 * Process auto-reply for a conversation (Bot + AI logic)
 * Returns sender_type and response text, or null if manual mode
 */
function processAutoReply($convId, $messageContent) {
    $db = Database::getInstance();
    $conversation = $db->fetch("SELECT * FROM conversations WHERE id = ?", [$convId]);
    if (!$conversation) return null;

    $agent = $db->fetch("SELECT * FROM agents WHERE id = ?", [$conversation['agent_id']]);
    if (!$agent) return null;

    $replyMode = $agent['reply_mode'] ?? 'manual';
    if ($replyMode === 'manual') return null;

    // 1. Try bot modules first
    if ($replyMode === 'bot' || $replyMode === 'hybrid') {
        $botResponse = matchChatModule($agent['id'], $messageContent);
        if ($botResponse) {
            return ['type' => 'bot', 'text' => $botResponse];
        }

        // In bot mode, use fallback message if no match
        if ($replyMode === 'bot') {
            $fallback = $agent['ai_fallback_message'] ?? 'Maaf, saya tidak mengerti pertanyaan Anda.';
            return ['type' => 'bot', 'text' => $fallback];
        }
    }

    // 2. Try AI (for 'ai' or 'hybrid' modes)
    if ($replyMode === 'ai' || $replyMode === 'hybrid') {
        if (empty($agent['ai_api_token'])) {
            $fallback = $agent['ai_fallback_message'] ?? 'AI sedang tidak tersedia saat ini.';
            return ['type' => 'bot', 'text' => $fallback];
        }

        // Get recent conversation history (last 20 messages)
        $history = $db->fetchAll(
            "SELECT sender_type, content FROM messages WHERE conversation_id = ? ORDER BY created_at DESC LIMIT 20",
            [$convId]
        );
        $history = array_reverse($history);

        $aiResult = callAI($agent, $history, $messageContent);

        if ($aiResult['success']) {
            return ['type' => 'ai', 'text' => $aiResult['text']];
        } else {
            // AI failed, use fallback
            $fallback = $agent['ai_fallback_message'] ?? 'AI sedang tidak dapat merespons saat ini.';
            return ['type' => 'bot', 'text' => $fallback];
        }
    }

    return null;
}
?>