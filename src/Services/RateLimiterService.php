<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class RateLimiterService
{
    public function __construct(private Database $db) {}

    public function check(string $key, int $maxRequests = 60, int $window = 60): bool
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $now = time();
        $windowStart = $now - ($now % $window);

        try {
            $record = $this->db->fetch(
                "SELECT hit_count FROM rate_limits WHERE key_name = ? AND ip_address = ? AND window_start = ?",
                [$key, $ip, $windowStart]
            );

            if ($record) {
                if ((int)$record['hit_count'] >= $maxRequests) {
                    return false;
                }
                $this->db->update(
                    "UPDATE rate_limits SET hit_count = hit_count + 1 WHERE key_name = ? AND ip_address = ? AND window_start = ?",
                    [$key, $ip, $windowStart]
                );
            } else {
                $this->db->insert(
                    "INSERT OR REPLACE INTO rate_limits (key_name, ip_address, window_start, hit_count)
                     VALUES (?, ?, ?, 1)",
                    [$key, $ip, $windowStart]
                );
            }

            $this->cleanup();
            return true;
        } catch (\Exception $e) {
            error_log("RateLimiter error: " . $e->getMessage());
            return true;
        }
    }

    public function require(string $key, int $maxRequests = 60, int $window = 60): void
    {
        if (!$this->check($key, $maxRequests, $window)) {
            http_response_code(429);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Too many requests. Please slow down.']);
            exit;
        }
    }

    private function cleanup(): void
    {
        $threshold = time() - 3600;
        try {
            $this->db->delete("DELETE FROM rate_limits WHERE window_start < ?", [$threshold]);
        } catch (\Exception) {
        }
    }
}
