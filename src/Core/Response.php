<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function error(string $message, int $status = 400): never
    {
        self::json(['success' => false, 'error' => $message], $status);
    }

    public static function success(mixed $data = null, string $message = 'OK'): never
    {
        self::json(['success' => true, 'message' => $message, 'data' => $data]);
    }

    public static function redirect(string $url): never
    {
        header('Location: ' . $url);
        exit;
    }

    public static function notFound(): never
    {
        http_response_code(404);
        if (self::isJsonRequest()) {
            self::json(['success' => false, 'error' => 'Not found'], 404);
        }
        self::renderErrorView('404');
    }

    public static function unauthorized(): never
    {
        http_response_code(401);
        if (self::isJsonRequest()) {
            self::json(['success' => false, 'error' => 'Unauthorized'], 401);
        }
        self::renderErrorView('403');
    }

    public static function forbidden(): never
    {
        http_response_code(403);
        if (self::isJsonRequest()) {
            self::json(['success' => false, 'error' => 'Forbidden'], 403);
        }
        self::renderErrorView('403');
    }

    public static function internalError(string $message = 'Internal server error'): never
    {
        http_response_code(500);
        error_log($message);
        if (self::isJsonRequest()) {
            self::json(['success' => false, 'error' => 'Internal server error'], 500);
        }
        self::renderErrorView('500');
    }

    public static function tooManyRequests(): never
    {
        http_response_code(429);
        self::json(['success' => false, 'error' => 'Too many requests'], 429);
    }

    private static function isJsonRequest(): bool
    {
        return isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false
            || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)
            || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest');
    }

    private static function renderErrorView(string $code): never
    {
        $viewPath = __DIR__ . '/../../views/errors/' . $code . '.php';
        if (file_exists($viewPath)) {
            require $viewPath;
        } else {
            echo '<h1>' . $code . ' Error</h1>';
        }
        exit;
    }

    public static function view(string $view, array $data = []): void
    {
        extract($data);
        $viewPath = __DIR__ . '/../../views/' . $view . '.php';

        if (!file_exists($viewPath)) {
            throw new \RuntimeException("View not found: {$view}");
        }

        require $viewPath;
    }
}
