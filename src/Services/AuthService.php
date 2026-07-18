<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Session;
use App\Models\User;
use App\Models\Agent;

final class AuthService
{
    private const SESSION_TIMEOUT = 1800;

    public function __construct(
        private Database $db,
        private User $userModel,
        private Agent $agentModel,
        private RateLimiterService $rateLimiter,
    ) {}

    public function login(string $username, string $password): array
    {
        $this->rateLimiter->require('login', 5, 60);

        $user = $this->userModel->findByUsernameOrEmail($username);

        if ($user && $this->userModel->verifyPassword($password, $user['password_hash'])) {
            Session::regenerate();

            Session::set('user_id', $user['id']);
            Session::set('username', $user['username']);
            Session::set('role', $user['role']);
            Session::set('license_tier', $user['license_tier'] ?? 'free');
            Session::set('last_activity', time());

            if (empty(Session::get('csrf_token'))) {
                Session::set('csrf_token', bin2hex(random_bytes(32)));
            }

            $agent = $this->agentModel->ensureExists($user['id'], $user['email']);
            $this->userModel->updateLastActivity($user['id']);

            return ['success' => true, 'user' => $user];
        }

        return ['success' => false, 'message' => 'Invalid credentials'];
    }

    public function logout(): void
    {
        Session::destroy();
    }

    public function isLoggedIn(): bool
    {
        return Session::has('user_id');
    }

    public function getUserId(): ?int
    {
        return Session::get('user_id');
    }

    public function getCurrentUser(): ?array
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return null;
        }
        return $this->userModel->findById($userId);
    }

    public function getCurrentAgent(): ?array
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return null;
        }
        return $this->agentModel->findByUserId($userId);
    }

    public function requireAuth(): void
    {
        if (!$this->isLoggedIn()) {
            header('Location: /login');
            exit;
        }
    }

    public function requireRole(string|array $roles): void
    {
        $this->requireAuth();
        $userRole = Session::get('role');
        if (!in_array($userRole, (array)$roles, true)) {
            http_response_code(403);
            echo '<h1>403 Forbidden</h1>';
            exit;
        }
    }

    public function checkSessionTimeout(): void
    {
        $lastActivity = Session::get('last_activity');
        if ($lastActivity && (time() - $lastActivity > self::SESSION_TIMEOUT)) {
            Session::destroy();
            header('Location: /login');
            exit;
        }
        Session::set('last_activity', time());
    }

    public function isOnline(): bool
    {
        $agent = $this->getCurrentAgent();
        return $agent ? (bool)$agent['is_online'] : false;
    }

    public function toggleOnline(): bool
    {
        $agent = $this->getCurrentAgent();
        if (!$agent) {
            return false;
        }
        return $this->agentModel->toggleOnline((int)$agent['id']);
    }

    // CSRF
    public function getCsrfToken(): string
    {
        $token = Session::get('csrf_token');
        if (empty($token)) {
            $token = bin2hex(random_bytes(32));
            Session::set('csrf_token', $token);
        }
        return $token;
    }

    public function validateCsrfToken(?string $token): bool
    {
        $stored = Session::get('csrf_token');
        if (empty($stored) || empty($token)) {
            return false;
        }
        return hash_equals($stored, $token);
    }

    public function requireCsrfToken(): void
    {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!$this->validateCsrfToken($token)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }
    }

    public function csrfField(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . $this->getCsrfToken() . '">';
    }

    public function getRole(): ?string
    {
        return Session::get('role');
    }

    public function getUsername(): ?string
    {
        return Session::get('username');
    }
}
