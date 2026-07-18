<?php
/**
 * Global legacy helper functions
 * For backward compatibility with old PHP files
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
        return substr(str_shuffle('1234567890'), 0, 10);
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
        if (strpos($ip, ',') !== false) $ip = trim(explode(',', $ip)[0]);
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
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

if (!function_exists('getGeoInfo')) {
    function getGeoInfo($ip) {
        if ($ip === '127.0.0.1' || $ip === '0.0.0.0' || strpos($ip, '192.168.') === 0) {
            return ['country' => 'Local', 'city' => 'Localhost', 'region' => 'Dev'];
        }
        $geo = @json_decode(file_get_contents("http://ip-api.com/json/{$ip}?fields=status,country,city,regionName"), true);
        if ($geo && $geo['status'] === 'success') {
            return ['country' => $geo['country'], 'city' => $geo['city'], 'region' => $geo['regionName']];
        }
        return ['country' => 'Unknown', 'city' => 'Unknown', 'region' => 'Unknown'];
    }
}

if (!function_exists('getLicenseLimits')) {
    function getLicenseLimits($tier) {
        $db = \App\Core\Database::getInstance();
        return $db->fetch("SELECT * FROM license_tiers WHERE name = ?", [$tier]);
    }
}
