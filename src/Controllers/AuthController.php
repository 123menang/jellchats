<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;

final class AuthController
{
    public function showLogin(Request $req): void
    {
        if (App::auth()->isLoggedIn()) {
            Response::redirect('/chats');
        }
        $error = '';
        Response::view('auth/login', ['error' => $error]);
    }

    public function login(Request $req): void
    {
        $username = $req->post('username', '');
        $password = $req->post('password', '');

        if (empty($username) || empty($password)) {
            Response::view('auth/login', ['error' => 'Please enter username and password.']);
            return;
        }

        $result = App::auth()->login($username, $password);

        if ($result['success']) {
            Response::redirect('/chats');
        }

        Response::view('auth/login', ['error' => $result['message'] ?? 'Invalid credentials.']);
    }

    public function showSignup(Request $req): void
    {
        if (App::auth()->isLoggedIn()) {
            Response::redirect('/chats');
        }
        Response::view('auth/signup', ['error' => '', 'success' => '']);
    }

    public function signup(Request $req): void
    {
        $email = trim($req->post('email', ''));
        $phone = trim($req->post('phone', ''));
        $password = $req->post('password', '');
        $confirm = $req->post('confirm_password', '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::view('auth/signup', ['error' => 'Invalid email address.', 'success' => '']);
            return;
        }

        if (empty($phone) || !preg_match('/^\+?[0-9]{8,15}$/', $phone)) {
            Response::view('auth/signup', ['error' => 'Invalid phone number.', 'success' => '']);
            return;
        }

        if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            Response::view('auth/signup', ['error' => 'Password must be at least 8 characters with letters and numbers.', 'success' => '']);
            return;
        }

        if ($password !== $confirm) {
            Response::view('auth/signup', ['error' => 'Passwords do not match.', 'success' => '']);
            return;
        }

        $existing = App::userModel()->findByEmail($email);
        if ($existing) {
            Response::view('auth/signup', ['error' => 'Email already registered.', 'success' => '']);
            return;
        }

        $userId = App::userModel()->create([
            'email' => $email,
            'password' => $password,
            'phone' => $phone,
            'role' => 'agent',
        ]);

        App::agentModel()->ensureExists($userId, $email);

        Response::view('auth/signup', ['error' => '', 'success' => 'Registration successful! You can now login.']);
    }

    public function logout(Request $req): void
    {
        App::auth()->logout();
        Response::redirect('/login');
    }
}
