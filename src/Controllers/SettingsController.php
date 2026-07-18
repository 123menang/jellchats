<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;

final class SettingsController
{
    public function index(Request $req): void
    {
        $user = App::auth()->getCurrentUser();
        $agent = App::auth()->getCurrentAgent();

        $myAgentId = $agent['id'] ?? 0;
        $myTeamId = $agent['team_id'] ?? -1;
        $isOnline = $agent['is_online'] ?? 0;
        $uAva = ($user['avatar'] ?? '') ?: 'assets/images/default-avatar.png';
        $activePage = 'settings';
        $pageTitle = 'Settings';
        $db = App::db();

        require __DIR__ . '/../../settings.php';
    }

    public function save(Request $req): void
    {
        $agent = App::auth()->getCurrentAgent();
        if (!$agent) {
            Response::redirect('/settings');
        }

        $updates = [];
        if ($req->post('display_name')) $updates['display_name'] = $req->post('display_name');
        if ($req->post('reply_mode')) $updates['reply_mode'] = $req->post('reply_mode');
        if ($req->post('ai_provider')) $updates['ai_provider'] = $req->post('ai_provider');
        if ($req->post('ai_api_token')) $updates['ai_api_token'] = $req->post('ai_api_token');
        if ($req->post('ai_model')) $updates['ai_model'] = $req->post('ai_model');
        if ($req->post('ai_system_prompt')) $updates['ai_system_prompt'] = $req->post('ai_system_prompt');
        if ($req->post('ai_fallback_message')) $updates['ai_fallback_message'] = $req->post('ai_fallback_message');

        if (!empty($updates)) {
            App::agentModel()->update((int)$agent['id'], $updates);
        }

        Response::redirect('/settings');
    }

    public function profile(Request $req): void
    {
        $user = App::auth()->getCurrentUser();
        $agent = App::auth()->getCurrentAgent();

        $myAgentId = $agent['id'] ?? 0;
        $myTeamId = $agent['team_id'] ?? -1;
        $isOnline = $agent['is_online'] ?? 0;
        $uAva = ($user['avatar'] ?? '') ?: 'assets/images/default-avatar.png';
        $activePage = 'profile';
        $pageTitle = 'My Profile';
        $db = App::db();

        require __DIR__ . '/../../profile.php';
    }

    public function updateProfile(Request $req): void
    {
        $user = App::auth()->getCurrentUser();
        if (!$user) {
            Response::redirect('/login');
        }

        if ($req->post('full_name')) {
            App::userModel()->update((int)$user['id'], ['full_name' => $req->post('full_name')]);
        }

        if ($req->post('password') && $req->post('confirm_password')) {
            if ($req->post('password') === $req->post('confirm_password')) {
                App::userModel()->updatePassword((int)$user['id'], $req->post('password'));
            }
        }

        Response::redirect('/profile');
    }

    public function billing(Request $req): void
    {
        $user = App::auth()->getCurrentUser();
        $agent = App::auth()->getCurrentAgent();

        $myAgentId = $agent['id'] ?? 0;
        $myTeamId = $agent['team_id'] ?? -1;
        $isOnline = $agent['is_online'] ?? 0;
        $uAva = ($user['avatar'] ?? '') ?: 'assets/images/default-avatar.png';
        $activePage = 'billing';
        $pageTitle = 'Billing';
        $db = App::db();

        require __DIR__ . '/../../billing.php';
    }
}
