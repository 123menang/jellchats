<?php

declare(strict_types=1);

namespace App\Helpers;

function formatRupiah(int $amount): string
{
    return 'Rp' . number_format($amount, 0, ',', '.');
}

function timeAgo(string $datetime): string
{
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;

    return match (true) {
        $diff < 60 => $diff . 's',
        $diff < 3600 => floor($diff / 60) . 'm',
        $diff < 86400 => floor($diff / 3600) . 'h',
        $diff < 604800 => floor($diff / 86400) . 'd',
        default => date('M d', $time),
    };
}

function sanitize(mixed $data): string
{
    return htmlspecialchars(strip_tags(trim((string)$data)), ENT_QUOTES, 'UTF-8');
}

function jsonResponse(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function getClientIP(): string
{
    $ip = $_SERVER['HTTP_CLIENT_IP']
        ?? $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['REMOTE_ADDR']
        ?? '0.0.0.0';

    if (str_contains($ip, ',')) {
        $ip = trim(explode(',', $ip)[0]);
    }

    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

