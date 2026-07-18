<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Services\AuthService;

final class AuthMiddleware
{
    public function __construct(private ?AuthService $auth = null) {}

    public function handle(Request $request, callable $next): void
    {
        $auth = $this->auth ?? \App\Core\App::auth();

        if (!$auth->isLoggedIn()) {
            if ($request->isAjax()) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                exit;
            }
            header('Location: /login');
            exit;
        }

        $auth->checkSessionTimeout();
        $next($request);
    }
}
