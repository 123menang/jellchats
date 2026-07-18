<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Request;
use App\Controllers\AuthController;
use App\Controllers\ChatController;
use App\Controllers\AgentController;
use App\Controllers\WidgetController;
use App\Controllers\AdminController;
use App\Controllers\SettingsController;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\RoleMiddleware;
use App\Middleware\CorsMiddleware;

$router = App::router();

// ===== Public Routes =====
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login'], [CsrfMiddleware::class]);
$router->get('/signup', [AuthController::class, 'showSignup']);
$router->post('/signup', [AuthController::class, 'signup'], [CsrfMiddleware::class]);
$router->get('/logout', [AuthController::class, 'logout']);

// ===== Widget API (public CORS) =====
$widgetCors = [new CorsMiddleware(['*'])];
$router->any('/api/widget-config', [WidgetController::class, 'config'], $widgetCors);
$router->any('/api/widget/chat', [WidgetController::class, 'chat'], $widgetCors);
$router->any('/api/widget/poll-messages', [WidgetController::class, 'pollMessages'], $widgetCors);
$router->any('/api/widget/typing', [WidgetController::class, 'typing'], $widgetCors);

// ===== Agent API (authenticated) =====
$authMw = [AuthMiddleware::class];
$router->any('/api/get-conversations', [ChatController::class, 'getConversations'], $authMw);
$router->any('/api/send-message', [ChatController::class, 'sendMessage'], $authMw);
$router->any('/api/close-chat', [ChatController::class, 'closeChat'], $authMw);
$router->any('/api/agent-typing', [ChatController::class, 'typing'], $authMw);
$router->any('/api/notifications', [ChatController::class, 'notifications'], $authMw);

// Legacy API compatibility (widget uses old paths, no auth)
// These are handled by the fallback route serving legacy PHP files directly
// $router->any('/api/poll-messages', [ChatController::class, 'pollMessages'], $authMw);
// $router->any('/api/chat', [WidgetController::class, 'chat'], $widgetCors);
// $router->any('/api/agent-typing', [WidgetController::class, 'typing'], $widgetCors);
// $router->any('/api/CheckMessage.php', [WidgetController::class, 'notifications'], $widgetCors);
// $router->any('/api/api_check_notif.php', [WidgetController::class, 'notifications'], $widgetCors);

// ===== Admin Pages (authenticated) =====
$dashboardAccess = [AuthMiddleware::class];
$adminOnly = [AuthMiddleware::class, new RoleMiddleware(['owner', 'admin'])];

$router->get('/chats', [ChatController::class, 'index'], $dashboardAccess);
$router->get('/agents', [AgentController::class, 'index'], $dashboardAccess);
$router->post('/agents', [AgentController::class, 'save'], $dashboardAccess);
$router->get('/settings', [SettingsController::class, 'index'], $dashboardAccess);
$router->post('/settings', [SettingsController::class, 'save'], $dashboardAccess);
$router->get('/profile', [SettingsController::class, 'profile'], $dashboardAccess);
$router->post('/profile', [SettingsController::class, 'updateProfile'], $dashboardAccess);
$router->get('/billing', [SettingsController::class, 'billing'], $dashboardAccess);
$router->get('/modules', [AdminController::class, 'modules'], $dashboardAccess);
$router->get('/analytics', [AdminController::class, 'analytics'], $dashboardAccess);
$router->get('/admin', [AdminController::class, 'dashboard'], $adminOnly);

// Fallback: serve existing PHP files for backward compatibility, or static files
$router->any('/{path}', function (Request $req) {
    $path = $req->param('path');

    // Try PHP file
    $phpFile = __DIR__ . '/../' . $path . '.php';
    if (file_exists($phpFile)) {
        require $phpFile;
        return;
    }

    // Try exact file match (JS, CSS, images, etc.)
    $exactFile = __DIR__ . '/../' . $path;
    if (file_exists($exactFile) && !is_dir($exactFile)) {
        $ext = pathinfo($exactFile, PATHINFO_EXTENSION);
        $mimeTypes = [
            'js' => 'application/javascript',
            'css' => 'text/css',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'mp3' => 'audio/mpeg',
        ];
        if (isset($mimeTypes[$ext])) {
            header('Content-Type: ' . $mimeTypes[$ext]);
        }
        readfile($exactFile);
        return;
    }

    // Try under public/
    $publicFile = __DIR__ . '/../public/' . $path;
    if (file_exists($publicFile) && !is_dir($publicFile)) {
        readfile($publicFile);
        return;
    }

    \App\Core\Response::notFound();
});
