<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class User
{
    public function __construct(private Database $db) {}

    public function findById(int $id): ?array
    {
        return $this->db->fetch("SELECT * FROM users WHERE id = ?", [$id]);
    }

    public function findByEmail(string $email): ?array
    {
        return $this->db->fetch("SELECT * FROM users WHERE email = ?", [$email]);
    }

    public function findByUsernameOrEmail(string $username): ?array
    {
        return $this->db->fetch("SELECT * FROM users WHERE username = ? OR email = ?", [$username, $username]);
    }

    public function create(array $data): int
    {
        return $this->db->insert(
            "INSERT INTO users (username, email, password_hash, full_name, phone, role, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))",
            [
                $data['username'] ?? explode('@', $data['email'])[0],
                $data['email'],
                password_hash($data['password'], PASSWORD_BCRYPT),
                $data['full_name'] ?? '',
                $data['phone'] ?? '',
                $data['role'] ?? 'agent',
            ]
        );
    }

    public function updateLastActivity(int $id): void
    {
        $this->db->update("UPDATE users SET updated_at = datetime('now') WHERE id = ?", [$id]);
    }

    public function updatePassword(int $id, string $password): int
    {
        return $this->db->update(
            "UPDATE users SET password_hash = ?, updated_at = datetime('now') WHERE id = ?",
            [password_hash($password, PASSWORD_BCRYPT), $id]
        );
    }

    public function update(int $id, array $data): int
    {
        $sets = [];
        $params = [];
        foreach ($data as $col => $val) {
            $sets[] = "$col = ?";
            $params[] = $val;
        }
        $params[] = $id;
        return $this->db->update(
            "UPDATE users SET " . implode(', ', $sets) . ", updated_at = datetime('now') WHERE id = ?",
            $params
        );
    }

    public function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
}
