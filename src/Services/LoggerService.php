<?php

declare(strict_types=1);

namespace App\Services;

final class LoggerService
{
    private const LOG_DIR = __DIR__ . '/../../storage/logs';

    public static function info(string $message, array $context = []): void
    {
        self::log('INFO', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log('WARNING', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('ERROR', $message, $context);
    }

    private static function log(string $level, string $message, array $context = []): void
    {
        $dir = self::LOG_DIR;
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $date = date('Y-m-d');
        $time = date('H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $line = "[{$date} {$time}] {$level}: {$message}{$contextStr}" . PHP_EOL;

        $file = $dir . '/app-' . $date . '.log';
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }
}