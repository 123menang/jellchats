<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;

final class AgentController
{
    public function index(Request $req): void
    {
        $user = App::auth()->getCurrentUser();
        $agent = App::auth()->getCurrentAgent();

        if ($req->query('toggle_online') !== null) {
            $isOnline = App::auth()->toggleOnline();
            Response::json(['status' => 'success', 'is_online' => $isOnline]);
        }

        // Legacy compatibility
        $myAgentId = $agent['id'] ?? 0;
        $myTeamId = $agent['team_id'] ?? -1;
        $isOnline = $agent['is_online'] ?? 0;
        $uAva = ($user['avatar'] ?? '') ?: 'assets/images/default-avatar.png';
        $activePage = 'agents';
        $pageTitle = 'Agents & Teams';
        $db = App::db();
        $agents = App::agentModel()->getAll();
        $teams = App::teamModel()->findByUserIdAll((int)$user['id']);

        require __DIR__ . '/../../agents.php';
    }

    public function save(Request $req): void
    {
        $action = $req->post('action', '');

        if ($action === 'create_team') {
            $name = $req->post('name', '');
            $desc = $req->post('description', '');
            $maxAgents = (int)$req->post('max_agents', 10);

            App::teamModel()->create((int)App::auth()->getUserId(), $name, $desc, $maxAgents);
            Response::redirect('/agents');
        }

        if ($action === 'update_agent') {
            $agentId = (int)$req->post('agent_id', 0);
            $data = [];
            if ($req->post('display_name')) $data['display_name'] = $req->post('display_name');
            if ($req->post('team_id')) $data['team_id'] = (int)$req->post('team_id');
            if ($req->post('reply_mode')) $data['reply_mode'] = $req->post('reply_mode');

            App::agentModel()->update($agentId, $data);
            Response::redirect('/agents');
        }

        Response::redirect('/agents');
    }

    private function getLayoutData(?array $user, ?array $agent): array
    {
        $agentId = $agent['id'] ?? 0;
        $teamId = $agent['team_id'] ?? -1;
        $role = $user['role'] ?? 'agent';

        if ($role === 'agent') {
            $onlineAgents = App::agentModel()->findOnlineByTeamId((int)$teamId);
            $totalOnline = App::agentModel()->countOnlineByTeamId((int)$teamId);
        } else {
            $onlineAgents = App::agentModel()->findOnline();
            $totalOnline = App::agentModel()->countOnline();
        }

        $totalUnread = App::conversationModel()->getUnreadCountByAgent((int)$agentId);
        $uAva = ($user['avatar'] ?? '') ?: '/assets/images/default-avatar.png';
        $isOnline = $agent['is_online'] ?? 0;

        return [
            'user' => $user,
            'agent' => $agent,
            'myAgentId' => $agentId,
            'myTeamId' => $teamId,
            'isOnline' => $isOnline,
            'uAva' => $uAva,
            'onlineAgents' => $onlineAgents,
            'totalOnline' => $totalOnline,
            'totalUnread' => $totalUnread,
        ];
    }
}
