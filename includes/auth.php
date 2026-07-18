<?php
/**
 * Authentication & Session Handler
 */

if (session_status() === PHP_SESSION_NONE) {
    // Secure session configuration
    ini_set('session.use_strict_mode', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_maxlifetime', 1800);
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

class Auth {
    private static $instance = null;
    private $db;

    private function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Singleton Instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function login($username, $password) {
        // Rate limiting by IP
        $ip = getClientIP();
        $attemptKey = 'login_attempts_' . $ip;
        $attempts = $_SESSION[$attemptKey] ?? 0;
        if ($attempts >= 5) {
            return ['success' => false, 'message' => 'Too many attempts. Try again later.'];
        }

        $user = $this->db->fetch(
            "SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1",
            [$username, $username]
        );

        if ($user && password_verify($password, $user['password_hash'])) {
            // Regenerate session ID to prevent session fixation
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['license_tier'] = $user['license_tier'];

            // Clear login attempts
            unset($_SESSION[$attemptKey]);

            // Generate CSRF token
            if (empty($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }

            // Auto-create agent if not exists
            $this->ensureAgentExists($user['id']);

            // Update last activity
            $this->db->update(
                "UPDATE users SET updated_at = datetime('now') WHERE id = ?",
                [$user['id']]
            );

            // LOG: Login Success
            if (function_exists('logActivity')) {
                logActivity($user['id'], 'LOGIN', 'users', $user['id'], 'User logged in successfully');
            }

            return ['success' => true, 'user' => $user];
        }

        // Increment login attempts
        $_SESSION[$attemptKey] = ($_SESSION[$attemptKey] ?? 0) + 1;

        return ['success' => false, 'message' => 'Invalid credentials'];
    }

    private function ensureAgentExists($userId) {
        $agent = $this->db->fetch("SELECT id FROM agents WHERE user_id = ?", [$userId]);
        if (!$agent) {
            // Get or create default team
            $team = $this->db->fetch("SELECT id FROM teams WHERE user_id = ? LIMIT 1", [$userId]);
            if (!$team) {
                $teamId = $this->db->insert(
                    "INSERT INTO teams (user_id, name, description, max_agents) VALUES (?, 'Default Team', 'Auto-created team', 10)",
                    [$userId]
                );
            } else {
                $teamId = $team['id'];
            }

            $user = $this->db->fetch("SELECT full_name, username FROM users WHERE id = ?", [$userId]);
            $displayName = $user['full_name'] ?: $user['username'];

            $this->db->insert(
                "INSERT INTO agents (team_id, user_id, display_name, reply_mode) VALUES (?, ?, ?, 'manual')",
                [$teamId, $userId, $displayName]
            );
        }
    }

    public function logout() {
        $userId = $_SESSION['user_id'] ?? null;
        
        // LOG: Logout
        if ($userId && function_exists('logActivity')) {
            logActivity($userId, 'LOGOUT', 'users', $userId, 'User logged out');
        }

        session_unset();
        session_destroy();
        return ['success' => true];
    }

    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    public function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }

    public function getCurrentUser() {
        if (!$this->isLoggedIn()) return null;
        return $this->db->fetch("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    }

    public function requireAuth() {
        if (!$this->isLoggedIn()) {
            header('Location: /login');
            exit;
        }
    }

    public function requireRole($roles) {
        $this->requireAuth();
        if (!in_array($_SESSION['role'], (array)$roles)) {
            header('Location: /unauthorized.php');
            exit;
        }
    }

    /**
     * Method untuk reset password dengan logging
     */
    public function updatePassword($userId, $newPassword) {
        $hash = $this->hashPassword($newPassword);
        $update = $this->db->update(
            "UPDATE users SET password_hash = ?, updated_at = datetime('now') WHERE id = ?",
            [$hash, $userId]
        );

        if ($update && function_exists('logActivity')) {
            logActivity($_SESSION['user_id'] ?? $userId, 'RESET_PASSWORD', 'users', $userId, 'Password has been updated');
        }

        return $update;
    }

    public function generateToken($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }

    public function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    // ========== CSRF PROTECTION ==========
    public function getCsrfToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public function validateCsrfToken($token) {
        if (empty($_SESSION['csrf_token']) || empty($token)) return false;
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    public function requireCsrfToken() {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!$this->validateCsrfToken($token)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }
        return true;
    }

    public function csrfField() {
        return '<input type="hidden" name="csrf_token" value="' . $this->getCsrfToken() . '">';
    }

    // ========== SESSION TIMEOUT (30 min) ==========
    public function checkSessionTimeout() {
        $timeout = 1800; // 30 minutes
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
            session_unset();
            session_destroy();
            header('Location: /login');
            exit;
        }
        $_SESSION['last_activity'] = time();
    }
}

// Global initialization
$auth = Auth::getInstance();

// Session timeout check for authenticated pages
if ($auth->isLoggedIn()) {
    $auth->checkSessionTimeout();
}