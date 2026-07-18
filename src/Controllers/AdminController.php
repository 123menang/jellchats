<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;

final class AdminController
{
    public function dashboard(Request $req): void
    {
        $user = App::auth()->getCurrentUser();
        $agent = App::auth()->getCurrentAgent();

        $myAgentId = $agent['id'] ?? 0;
        $myTeamId = $agent['team_id'] ?? -1;
        $isOnline = $agent['is_online'] ?? 0;
        $uAva = ($user['avatar'] ?? '') ?: 'assets/images/default-avatar.png';
        $activePage = 'admin';
        $pageTitle = 'Admin Panel';
        $db = App::db();

        require __DIR__ . '/../../a.php';
    }

    public function modules(Request $req): void
    {
        $user = App::auth()->getCurrentUser();
        $agent = App::auth()->getCurrentAgent();

        $myAgentId = $agent['id'] ?? 0;
        $myTeamId = $agent['team_id'] ?? -1;
        $isOnline = $agent['is_online'] ?? 0;
        $uAva = ($user['avatar'] ?? '') ?: 'assets/images/default-avatar.png';
        $activePage = 'modules';
        $pageTitle = 'Chat Modules';
        $db = App::db();

        require __DIR__ . '/../../modules.php';
    }

    public function analytics(Request $req): void
    {
        $user = App::auth()->getCurrentUser();
        $agent = App::auth()->getCurrentAgent();

        $myAgentId = $agent['id'] ?? 0;
        $myTeamId = $agent['team_id'] ?? -1;
        $isOnline = $agent['is_online'] ?? 0;
        $uAva = ($user['avatar'] ?? '') ?: 'assets/images/default-avatar.png';
        $activePage = 'analytics';
        $pageTitle = 'Analytics';
        $db = App::db();

        require __DIR__ . '/../../analytics.php';
    }
}
