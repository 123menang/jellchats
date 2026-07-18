<?php

declare(strict_types=1);

namespace App\Core;

final class Session
{
    private static bool $initialized = false;

    public static function initialize(): void
    {
        if (self::$initialized) {
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_samesite', 'Lax');
            ini_set('session.gc_maxlifetime', '1800');
            session_start();
        }

        self::$initialized = true;
    }

    public static function set(string $key, mixed $value): void
    {
        self::initialize();
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::initialize();
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        self::initialize();
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        self::initialize();
        unset($_SESSION[$key]);
    }

    public static function destroy(): void
    {
        self::initialize();
        $_SESSION = [];
        session_destroy();
    }

    public static function regenerate(): void
    {
        self::initialize();
        session_regenerate_id(true);
    }

    public static function id(): string
    {
        self::initialize();
        return session_id();
    }
}
