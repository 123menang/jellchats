<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class AIService
{
    public function __construct(private Database $db) {}

    public function processAutoReply(int $convId, string $messageContent): ?array
    {
        $conversation = $this->db->fetch("SELECT * FROM conversations WHERE id = ?", [$convId]);
        if (!$conversation) {
            return null;
        }

        $agent = $this->db->fetch("SELECT * FROM agents WHERE id = ?", [$conversation['agent_id']]);
        if (!$agent) {
            return null;
        }

        $replyMode = $agent['reply_mode'] ?? 'manual';
        if ($replyMode === 'manual') {
            return null;
        }

        if (in_array($replyMode, ['bot', 'hybrid'], true)) {
            $botResponse = $this->matchChatModule((int)$agent['id'], $messageContent);
            if ($botResponse) {
                return ['type' => 'bot', 'text' => $botResponse];
            }

            if ($replyMode === 'bot') {
                $fallback = $agent['ai_fallback_message'] ?? 'Maaf, saya tidak mengerti pertanyaan Anda.';
                return ['type' => 'bot', 'text' => $fallback];
            }
        }

        if (in_array($replyMode, ['ai', 'hybrid'], true)) {
            if (empty($agent['ai_api_token'])) {
                $fallback = $agent['ai_fallback_message'] ?? 'AI sedang tidak tersedia saat ini.';
                return ['type' => 'bot', 'text' => $fallback];
            }

            $history = $this->db->fetchAll(
                "SELECT sender_type, content FROM messages WHERE conversation_id = ? ORDER BY created_at DESC LIMIT 20",
                [$convId]
            );
            $history = array_reverse($history);

            $aiResult = $this->callAI($agent, $history, $messageContent);

            if ($aiResult['success']) {
                return ['type' => 'ai', 'text' => $aiResult['text']];
            }

            $fallback = $agent['ai_fallback_message'] ?? 'AI sedang tidak dapat merespons saat ini.';
            return ['type' => 'bot', 'text' => $fallback];
        }

        return null;
    }

    private function matchChatModule(int $agentId, string $messageContent): ?string
    {
        $modules = $this->db->fetchAll(
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
                        if ($kw !== '' && str_contains($message, $kw)) {
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
                    $match = str_starts_with($message, $trigger);
                    break;
            }

            if ($match) {
                $this->db->update("UPDATE chat_modules SET match_count = match_count + 1 WHERE id = ?", [$module['id']]);
                return $module['response_text'];
            }
        }

        return null;
    }

    private function callAI(array $agent, array $history, string $userMessage): array
    {
        $provider = $agent['ai_provider'] ?? 'claude';
        $model = $agent['ai_model'] ?? '';
        $systemPrompt = $agent['ai_system_prompt'] ?? '';
        $token = $agent['ai_api_token'];

        return match ($provider) {
            'gemini' => $this->callGemini($token, $model, $systemPrompt, $history, $userMessage),
            'openai' => $this->callOpenAI($token, $model, $systemPrompt, $history, $userMessage),
            default => $this->callClaude($token, $model, $systemPrompt, $history, $userMessage),
        };
    }

    private function callClaude(string $apiToken, string $model, string $systemPrompt, array $history, string $userMessage): array
    {
        $messages = [];
        foreach ($history as $msg) {
            if ($msg['sender_type'] === 'visitor') {
                $messages[] = ['role' => 'user', 'content' => $msg['content']];
            } elseif (in_array($msg['sender_type'], ['bot', 'ai', 'agent'], true)) {
                $messages[] = ['role' => 'assistant', 'content' => $msg['content']];
            }
        }
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $payload = ['model' => $model ?: 'claude-sonnet-4-20250514', 'max_tokens' => 1024, 'messages' => $messages];
        if ($systemPrompt) {
            $payload['system'] = $systemPrompt;
        }

        return $this->httpPost('https://api.anthropic.com/v1/messages', $payload, [
            'Content-Type: application/json',
            'x-api-key: ' . $apiToken,
            'anthropic-version: 2023-06-01',
        ], function (array $data) {
            if (isset($data['content'][0]['text'])) {
                return ['success' => true, 'text' => $data['content'][0]['text']];
            }
            return ['success' => false, 'error' => 'Unexpected response format'];
        });
    }

    private function callGemini(string $apiToken, string $model, string $systemPrompt, array $history, string $userMessage): array
    {
        $geminiModel = $model ?: 'gemini-1.5-flash';
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$geminiModel}:generateContent";

        $contents = [];

        if ($systemPrompt) {
            $contents[] = ['role' => 'user', 'parts' => [['text' => "System instruction: " . $systemPrompt]]];
            $contents[] = ['role' => 'model', 'parts' => [['text' => 'Understood.']]];
        }

        foreach ($history as $msg) {
            if ($msg['sender_type'] === 'visitor') {
                $contents[] = ['role' => 'user', 'parts' => [['text' => $msg['content']]]];
            } elseif (in_array($msg['sender_type'], ['bot', 'ai', 'agent'], true)) {
                $contents[] = ['role' => 'model', 'parts' => [['text' => $msg['content']]]];
            }
        }
        $contents[] = ['role' => 'user', 'parts' => [['text' => $userMessage]]];

        return $this->httpPost($url, ['contents' => $contents], [
            'Content-Type: application/json',
            'X-Goog-Api-Key: ' . $apiToken,
        ], function (array $data) {
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
            if ($text) {
                return ['success' => true, 'text' => $text];
            }
            return ['success' => false, 'error' => 'Unexpected response format'];
        });
    }

    private function callOpenAI(string $apiToken, string $model, string $systemPrompt, array $history, string $userMessage): array
    {
        $messages = [];
        if ($systemPrompt) {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }
        foreach ($history as $msg) {
            if ($msg['sender_type'] === 'visitor') {
                $messages[] = ['role' => 'user', 'content' => $msg['content']];
            } elseif (in_array($msg['sender_type'], ['bot', 'ai', 'agent'], true)) {
                $messages[] = ['role' => 'assistant', 'content' => $msg['content']];
            }
        }
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        return $this->httpPost('https://api.openai.com/v1/chat/completions', [
            'model' => $model ?: 'gpt-3.5-turbo',
            'messages' => $messages,
            'max_tokens' => 1024,
        ], [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiToken,
        ], function (array $data) {
            $text = $data['choices'][0]['message']['content'] ?? null;
            if ($text) {
                return ['success' => true, 'text' => $text];
            }
            return ['success' => false, 'error' => 'Unexpected response format'];
        });
    }

    private function httpPost(string $url, array $payload, array $headers, callable $parse): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            $err = json_decode($response, true);
            return ['success' => false, 'error' => $err['error']['message'] ?? 'API Error ' . $httpCode];
        }

        $data = json_decode($response, true);
        return $parse($data);
    }
}
