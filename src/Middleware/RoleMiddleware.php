<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;

final class RoleMiddleware
{
    public function __construct(private array $allowedRoles) {}

    public function handle(Request $request, callable $next): void
    {
        $auth = \App\Core\App::auth();
        $role = $auth->getRole();

        if (!$role || !in_array($role, $this->allowedRoles, true)) {
            if ($request->isAjax()) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Forbidden']);
                exit;
            }
            http_response_code(403);
            echo '<h1>403 Forbidden</h1>';
            exit;
        }

        $next($request);
    }
}
