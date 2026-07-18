<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Services\AuthService;

final class CsrfMiddleware
{
    public function handle(Request $request, callable $next): void
    {
        if ($request->method() === 'POST') {
            $auth = \App\Core\App::auth();
            $token = $request->post('csrf_token') ?? $request->header('X-CSRF-Token');

            if (!$auth->validateCsrfToken($token)) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
                exit;
            }
        }

        $next($request);
    }
}
