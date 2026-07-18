
<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
$auth->requireAuth();
$user = $auth->getCurrentUser();
$db = Database::getInstance();

// ==========================================
// ROLE LOGIC
// ==========================================
$currentRole = $user['role']; // 'owner', 'admin', 'agent', 'team'
$currentAgentData = $db->fetch("SELECT id, team_id, display_name FROM agents WHERE user_id = ?", [$user['id']]);
$myAgentId = $currentAgentData ? $currentAgentData['id'] : 0;
$myTeamId = $currentAgentData ? $currentAgentData['team_id'] : -1;

// License limits
$licenseTier = $db->fetch("SELECT * FROM license_tiers WHERE name = ?", [$user['license_tier'] ?? 'starter']);
$maxTeams = (int)($licenseTier['max_teams'] ?? 1);
$maxAgentsPerTeam = (int)($licenseTier['max_agents_per_team'] ?? 1);
$maxAgentsTotal = $maxTeams * $maxAgentsPerTeam;

// Count existing
$teamCount = (int)$db->fetch("SELECT COUNT(*) AS cnt FROM teams WHERE user_id = ?", [$user['id']])['cnt'];
$totalAgentCount = (int)$db->fetch("
    SELECT COUNT(*) AS cnt FROM agents a 
    JOIN teams t ON a.team_id = t.id 
    WHERE t.user_id = ?", [$user['id']])['cnt'];

// Permission flags
$canCreateTeam = in_array($currentRole, ['owner', 'admin', 'agent']) && ($teamCount < $maxTeams);
$canCreateAgent = in_array($currentRole, ['owner', 'admin', 'agent']) && ($totalAgentCount < $maxAgentsTotal);
$canEditAll = in_array($currentRole, ['owner', 'admin']);
$canDelete = in_array($currentRole, ['owner', 'admin']);
$isTeamRole = ($currentRole === 'team');

// ==========================================
// HANDLE POST ACTIONS
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    // Validate CSRF for all POST actions
    if (!in_array($action, ['set_reply_mode', 'typing', 'send_message', 'close_chat_ajax', 'add_shortcut', 'transfer'])) {
        if (!$auth->validateCsrfToken($_POST['csrf_token'] ?? '')) {
            header('Location: agents.php?error=csrf');
            exit;
        }
    }

    // CREATE TEAM (Owner/Admin/Agent only)
    if ($action === 'create_team' && $canCreateTeam) {
        $teamName = sanitizeInput($_POST['team_name'] ?? '');
        $teamDesc = sanitizeInput($_POST['team_description'] ?? '');
        $teamColor = sanitizeInput($_POST['team_color'] ?? '#2563eb');
        $maxAgents = (int)($_POST['max_agents'] ?? $maxAgentsPerTeam);

        $db->insert("INSERT INTO teams (user_id, name, description, color, max_agents) VALUES (?, ?, ?, ?, ?)",
            [$user['id'], $teamName, $teamDesc, $teamColor, $maxAgents]);
        header('Location: agents.php?tab=teams&success=team_created');
        exit;
    }

    // CREATE AGENT (Owner/Admin/Agent only)
    if ($action === 'create_agent' && $canCreateAgent) {
        $username = sanitizeInput($_POST['username'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $password = password_hash($_POST['password'] ?? 'password123', PASSWORD_BCRYPT);
        $fullName = sanitizeInput($_POST['full_name'] ?? '');
        $displayName = sanitizeInput($_POST['display_name'] ?? $fullName);
        $teamId = (int)($_POST['team_id'] ?? 0);
        $replyMode = $_POST['reply_mode'] ?? 'manual';
        $role = sanitizeInput($_POST['agent_role'] ?? 'agent'); // Can be 'agent' or 'team'

        // Auto-create default team if none selected
        if ($teamId === 0) {
            $defaultTeamName = $displayName . "'s Team";
            $db->insert(
                "INSERT INTO teams (user_id, name, description, max_agents) VALUES (?, ?, ?, ?)",
                [$user['id'], $defaultTeamName, 'Default team', 5]
            );
            $teamId = (int)$db->getPdo()->lastInsertId();
        } else {
            $team = $db->fetch("SELECT * FROM teams WHERE id = ? AND user_id = ?", [$teamId, $user['id']]);
            if (!$team) { header('Location: agents.php?error=invalid_team'); exit; }
        }

        // Generate unique email if empty or already exists
        if (empty($email)) {
            $email = $username . '@livechat.local';
        }
        $emailBase = $email;
        $suffix = 1;
        while ($db->fetch("SELECT id FROM users WHERE email = ?", [$email])) {
            $email = strstr($emailBase, '@', true) . $suffix . '@' . strstr($emailBase, '@');
            $suffix++;
        }

        $newUserId = $db->insert(
            "INSERT INTO users (username, email, password_hash, full_name, role, license_tier) VALUES (?, ?, ?, ?, ?, ?)",
            [$username, $email, $password, $fullName, $role, $user['license_tier']]
        );

        $db->insert(
            "INSERT INTO agents (team_id, user_id, display_name, reply_mode) VALUES (?, ?, ?, ?)",
            [$teamId, $newUserId, $displayName, $replyMode]
        );
        header('Location: agents.php?tab=agents&success=agent_created');
        exit;
    }

    // UPDATE AGENT
    if ($action === 'update_agent' && $canEditAll) {
        $editId = (int)($_POST['agent_id'] ?? 0);
        $displayName = sanitizeInput($_POST['display_name'] ?? '');
        $replyMode = $_POST['reply_mode'] ?? 'manual';
        $teamId = (int)($_POST['team_id'] ?? 0);
        $status = isset($_POST['status']) ? 1 : 0;

        $db->update("UPDATE agents SET display_name = ?, reply_mode = ?, team_id = ?, status = ? WHERE id = ?",
            [$displayName, $replyMode, $teamId, $status, $editId]);
        header('Location: agents.php?tab=agents&success=agent_updated');
        exit;
    }

    // UPDATE TEAM
    if ($action === 'update_team' && $canEditAll) {
        $editId = (int)($_POST['team_id'] ?? 0);
        $name = sanitizeInput($_POST['team_name'] ?? '');
        $desc = sanitizeInput($_POST['team_description'] ?? '');
        $color = sanitizeInput($_POST['team_color'] ?? '#2563eb');
        $maxAgents = (int)($_POST['max_agents'] ?? 1);

        $db->update("UPDATE teams SET name = ?, description = ?, color = ?, max_agents = ? WHERE id = ? AND user_id = ?",
            [$name, $desc, $color, $maxAgents, $editId, $user['id']]);
        header('Location: agents.php?tab=teams&success=team_updated');
        exit;
    }
}

// ==========================================
// HANDLE GET ACTIONS
// ==========================================
// Delete Agent
if (isset($_GET['delete_agent']) && $canDelete) {
    $delId = (int)$_GET['delete_agent'];
    // Don't allow deleting yourself
    $target = $db->fetch("SELECT user_id FROM agents WHERE id = ?", [$delId]);
    if ($target && $target['user_id'] != $user['id']) {
        $db->delete("DELETE FROM agents WHERE id = ?", [$delId]);
    }
    header('Location: agents.php?tab=agents');
    exit;
}

// Delete Team
if (isset($_GET['delete_team']) && $canDelete) {
    $delId = (int)$_GET['delete_team'];
    // Check if team belongs to user
    $team = $db->fetch("SELECT id FROM teams WHERE id = ? AND user_id = ?", [$delId, $user['id']]);
    if ($team) {
        // Move agents to no team first
        $db->update("UPDATE agents SET team_id = 0 WHERE team_id = ?", [$delId]);
        $db->delete("DELETE FROM teams WHERE id = ?", [$delId]);
    }
    header('Location: agents.php?tab=teams');
    exit;
}

// Toggle Agent Status
if (isset($_GET['toggle_agent']) && $canEditAll) {
    $tId = (int)$_GET['toggle_agent'];
    $target = $db->fetch("SELECT status FROM agents WHERE id = ?", [$tId]);
    if ($target) {
        $newStatus = $target['status'] ? 0 : 1;
        $db->update("UPDATE agents SET status = ? WHERE id = ?", [$newStatus, $tId]);
    }
    header('Location: agents.php?tab=agents');
    exit;
}

// Toggle Online (AJAX)
if (isset($_GET['toggle_online']) && $myAgentId) {
    $currentOnline = $currentAgentData['is_online'] ?? 0;
    $newOnline = $currentOnline ? 0 : 1;
    $db->update("UPDATE agents SET is_online = ? WHERE id = ?", [$newOnline, $myAgentId]);
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo json_encode(['status' => 'success', 'is_online' => $newOnline]);
        exit;
    }
    header('Location: agents.php');
    exit;
}

// ==========================================
// FETCH DATA
// ==========================================
$activeTab = $_GET['tab'] ?? 'agents';

// Teams data
if ($isTeamRole) {
    // Team role hanya lihat team sendiri
    $teams = $db->fetchAll("SELECT * FROM teams WHERE id = ?", [$myTeamId]);
} else {
    // Owner/Admin/Agent lihat semua team miliknya
    $teams = $db->fetchAll("SELECT * FROM teams WHERE user_id = ? ORDER BY created_at DESC", [$user['id']]);
}

// Agents data
if ($isTeamRole) {
    // Team role: hanya lihat agent dalam team yang sama
    $agents = $db->fetchAll("
        SELECT a.*, u.username, u.email, u.full_name, u.role as user_role, u.status as user_status,
               t.name as team_name, t.color as team_color
        FROM agents a
        JOIN users u ON a.user_id = u.id
        LEFT JOIN teams t ON a.team_id = t.id
        WHERE a.team_id = ?
        ORDER BY a.created_at DESC
    ", [$myTeamId]);
} elseif ($currentRole === 'agent') {
    // Agent role: lihat semua agent dalam team milik user
    $agents = $db->fetchAll("
        SELECT a.*, u.username, u.email, u.full_name, u.role as user_role, u.status as user_status,
               t.name as team_name, t.color as team_color
        FROM agents a
        JOIN users u ON a.user_id = u.id
        LEFT JOIN teams t ON a.team_id = t.id
        WHERE t.user_id = ? OR a.user_id = ?
        ORDER BY a.created_at DESC
    ", [$user['id'], $user['id']]);
} else {
    // Owner/Admin: lihat semua
    $agents = $db->fetchAll("
        SELECT a.*, u.username, u.email, u.full_name, u.role as user_role, u.status as user_status,
               t.name as team_name, t.color as team_color
        FROM agents a
        JOIN users u ON a.user_id = u.id
        LEFT JOIN teams t ON a.team_id = t.id
        ORDER BY a.created_at DESC
    ");
}

$pageTitle = 'Agents & Team - LiveChat Console';
$activePage = 'agents';

include 'includes/layout-header.php';
?>

<div style="padding:32px 40px; overflow-y:auto; height:100%;">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1>Agents & Team</h1>
            <p>Manage team members, roles, and communication modes.</p>
        </div>
        <div style="display:flex; gap:10px;">
            <?php if ($canCreateAgent && $activeTab === 'agents'): ?>
            <button class="btn-primary" onclick="document.getElementById('addAgentModal').style.display='flex'">
                <i class="fas fa-user-plus"></i> Add Agent
            </button>
            <?php endif; ?>
            <?php if ($canCreateTeam && $activeTab === 'teams'): ?>
            <button class="btn-primary" onclick="document.getElementById('addTeamModal').style.display='flex'">
                <i class="fas fa-users"></i> Create Team
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- License Status Bar -->
    <div style="background:#f8fafc; border:1px solid var(--border-color); border-radius:var(--radius-lg); padding:16px 20px; margin-bottom:24px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
        <div style="display:flex; gap:20px; align-items:center;">
            <div>
                <span style="font-size:11px; color:var(--text-muted); text-transform:uppercase; font-weight:600;">License Tier</span>
                <div style="font-size:14px; font-weight:700; color:var(--text-light); text-transform:capitalize;"><?= htmlspecialchars($user['license_tier'] ?? 'starter') ?></div>
            </div>
            <div style="width:1px; height:30px; background:var(--border-color);"></div>
            <div>
                <span style="font-size:11px; color:var(--text-muted); text-transform:uppercase; font-weight:600;">Teams</span>
                <div style="font-size:14px; font-weight:700; color:var(--text-light);"><?= $teamCount ?> / <?= $maxTeams ?></div>
            </div>
            <div style="width:1px; height:30px; background:var(--border-color);"></div>
            <div>
                <span style="font-size:11px; color:var(--text-muted); text-transform:uppercase; font-weight:600;">Agents</span>
                <div style="font-size:14px; font-weight:700; color:var(--text-light);"><?= $totalAgentCount ?> / <?= $maxAgentsTotal ?></div>
            </div>
        </div>
        <?php if (!$canCreateAgent): ?>
        <div style="background:var(--warning-bg); color:#92400e; padding:6px 12px; border-radius:var(--radius-sm); font-size:12px; font-weight:600;">
            <i class="fas fa-exclamation-triangle"></i> License limit reached
        </div>
        <?php endif; ?>
    </div>

    <!-- Alerts -->
    <?php if (isset($_GET['success'])): ?>
        <?php if ($_GET['success'] === 'agent_created'): ?>
        <div class="alert-success"><i class="fas fa-check-circle"></i> Agent created successfully!</div>
        <?php elseif ($_GET['success'] === 'team_created'): ?>
        <div class="alert-success"><i class="fas fa-check-circle"></i> Team created successfully!</div>
        <?php elseif ($_GET['success'] === 'agent_updated'): ?>
        <div class="alert-success"><i class="fas fa-check-circle"></i> Agent updated successfully!</div>
        <?php elseif ($_GET['success'] === 'team_updated'): ?>
        <div class="alert-success"><i class="fas fa-check-circle"></i> Team updated successfully!</div>
        <?php endif; ?>
    <?php endif; ?>
    <?php if (isset($_GET['error']) && $_GET['error'] === 'limit'): ?>
    <div class="alert-warning"><i class="fas fa-ban"></i> Cannot add more. License limit reached.</div>
    <?php endif; ?>

    <!-- Tabs -->
    <div style="display:flex; gap:0; border-bottom:2px solid var(--border-color); margin-bottom:24px;">
        <a href="?tab=agents" style="padding:12px 24px; font-size:14px; font-weight:600; color:<?= $activeTab==='agents' ? 'var(--accent-blue)' : 'var(--text-muted)' ?>; border-bottom:2px solid <?= $activeTab==='agents' ? 'var(--accent-blue)' : 'transparent' ?>; text-decoration:none; transition:0.2s;">
            <i class="fas fa-users" style="margin-right:6px;"></i> Agents (<?= count($agents) ?>)
        </a>
        <a href="?tab=teams" style="padding:12px 24px; font-size:14px; font-weight:600; color:<?= $activeTab==='teams' ? 'var(--accent-blue)' : 'var(--text-muted)' ?>; border-bottom:2px solid <?= $activeTab==='teams' ? 'var(--accent-blue)' : 'transparent' ?>; text-decoration:none; transition:0.2s;">
            <i class="fas fa-sitemap" style="margin-right:6px;"></i> Teams (<?= count($teams) ?>)
        </a>
    </div>

    <!-- AGENTS TAB -->
    <?php if ($activeTab === 'agents'): ?>
    <div class="table-responsive">
        <table class="agents-table">
            <thead>
                <tr>
                    <th>Agent</th>
                    <th>Role</th>
                    <th>Team</th>
                    <th>Reply Mode</th>
                    <th>Status</th>
                    <th>Online</th>
                    <?php if ($canEditAll): ?><th style="text-align:right;">Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($agents as $ag): 
                    $isMe = ($ag['user_id'] == $user['id']);
                ?>
                <tr>
                    <td>
                        <div class="agent-info">
                            <div class="agent-avatar" style="background:<?= $isMe ? 'var(--accent-blue)' : 'linear-gradient(135deg,#6366f1,#8b5cf6)' ?>">
                                <?= strtoupper(substr($ag['display_name'] ?: 'A', 0, 1)) ?>
                            </div>
                            <div>
                                <div class="agent-name"><?= htmlspecialchars($ag['display_name']) ?> <?= $isMe ? '<span style="font-size:10px; background:var(--accent-blue); color:#fff; padding:1px 6px; border-radius:4px;">YOU</span>' : '' ?></div>
                                <div class="agent-email"><?= htmlspecialchars($ag['email']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span style="font-size:11px; font-weight:600; text-transform:uppercase; padding:3px 8px; border-radius:4px; background:<?= $ag['user_role']==='owner' ? '#fef3c7' : ($ag['user_role']==='admin' ? '#e0e7ff' : ($ag['user_role']==='team' ? '#fce7f3' : '#f3f4f6')) ?>; color:<?= $ag['user_role']==='owner' ? '#92400e' : ($ag['user_role']==='admin' ? '#3730a3' : ($ag['user_role']==='team' ? '#be185d' : '#374151')) ?>;">
                            <?= ucfirst($ag['user_role']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($ag['team_name']): ?>
                        <span class="team-badge">
                            <span style="background:<?= $ag['team_color'] ?? '#a1a1aa' ?>"></span>
                            <?= htmlspecialchars($ag['team_name']) ?>
                        </span>
                        <?php else: ?><span style="color:var(--text-muted); font-size:12px;">-</span><?php endif; ?>
                    </td>
                    <td>
                        <span class="reply-mode-badge mode-<?= $ag['reply_mode'] ?>">
                            <i class="fas fa-<?= $ag['reply_mode']==='manual'?'hand':($ag['reply_mode']==='bot'?'robot':($ag['reply_mode']==='ai'?'brain':'users-gear')) ?>"></i>
                            <?= ucfirst($ag['reply_mode']) ?>
                        </span>
                    </td>
                    <td>
                        <span class="status-badge <?= $ag['status'] ? 'status-active' : 'status-inactive' ?>">
                            <i class="fas fa-circle" style="font-size:6px;"></i> <?= $ag['status'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                    <td>
                        <span style="display:flex; align-items:center; gap:6px; font-size:12px; color:<?= $ag['is_online'] ? 'var(--success)' : 'var(--text-muted)' ?>;">
                            <span style="width:8px; height:8px; border-radius:50%; background:<?= $ag['is_online'] ? 'var(--success)' : '#d1d5db' ?>;"></span>
                            <?= $ag['is_online'] ? 'Online' : 'Offline' ?>
                        </span>
                    </td>
                    <?php if ($canEditAll): ?>
                    <td>
                        <div class="action-btns" style="justify-content:flex-end;">
                            <button class="btn-icon" onclick="editAgent(<?= $ag['id'] ?>)" title="Edit"><i class="fas fa-pen"></i></button>
                            <?php if (!$isMe): ?>
                            <a href="?toggle_agent=<?= $ag['id'] ?>" class="btn-icon" title="Toggle"><i class="fas fa-power-off"></i></a>
                            <a href="?delete_agent=<?= $ag['id'] ?>" class="btn-icon danger" title="Delete" onclick="return confirm('Delete <?= htmlspecialchars($ag['display_name']) ?>?')"><i class="fas fa-trash"></i></a>
                            <?php endif; ?>
                        </div>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($agents)): ?>
                <tr><td colspan="<?= $canEditAll ? 7 : 6 ?>" style="text-align:center; padding:40px; color:var(--text-muted);">No agents found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- TEAMS TAB -->
    <?php if ($activeTab === 'teams'): ?>
    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(300px, 1fr)); gap:20px;">
        <?php foreach ($teams as $tm): 
            $teamAgents = $db->fetchAll("SELECT COUNT(*) as cnt FROM agents WHERE team_id = ?", [$tm['id']])[0]['cnt'] ?? 0;
        ?>
        <div style="background:white; border:1px solid var(--border-color); border-radius:var(--radius-lg); padding:24px; position:relative;">
            <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px;">
                <div style="width:48px; height:48px; border-radius:12px; background:<?= $tm['color'] ?>; display:flex; align-items:center; justify-content:center; color:white; font-size:20px;">
                    <i class="fas fa-users"></i>
                </div>
                <div>
                    <div style="font-weight:700; font-size:16px; color:var(--text-light);"><?= htmlspecialchars($tm['name']) ?></div>
                    <div style="font-size:12px; color:var(--text-muted);"><?= $teamAgents ?> / <?= $tm['max_agents'] ?> agents</div>
                </div>
            </div>
            <div style="font-size:13px; color:var(--text-muted); margin-bottom:16px; line-height:1.5;">
                <?= htmlspecialchars($tm['description'] ?: 'No description') ?>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div style="display:flex; gap:6px;">
                    <?php for($i=0; $i<min($teamAgents, 5); $i++): ?>
                    <div style="width:28px; height:28px; border-radius:50%; background:<?= $tm['color'] ?>; opacity:<?= 1 - ($i*0.15) ?>; display:flex; align-items:center; justify-content:center; color:white; font-size:10px; font-weight:600;">
                        <?= chr(65+$i) ?>
                    </div>
                    <?php endfor; ?>
                    <?php if ($teamAgents > 5): ?><div style="width:28px; height:28px; border-radius:50%; background:var(--card-bg); display:flex; align-items:center; justify-content:center; color:var(--text-muted); font-size:10px; font-weight:600;">+<?= $teamAgents-5 ?></div><?php endif; ?>
                </div>
                <?php if ($canEditAll): ?>
                <div class="action-btns">
                    <button class="btn-icon" onclick="editTeam(<?= $tm['id'] ?>)" title="Edit"><i class="fas fa-pen"></i></button>
                    <a href="?delete_team=<?= $tm['id'] ?>" class="btn-icon danger" title="Delete" onclick="return confirm('Delete team <?= htmlspecialchars($tm['name']) ?>? Agents will be unassigned.')"><i class="fas fa-trash"></i></a>
                </div>
                <?php endif; ?>
            </div>
            <!-- Progress bar -->
            <div style="margin-top:16px; height:4px; background:var(--border-light); border-radius:2px; overflow:hidden;">
                <div style="width:<?= min(100, ($teamAgents / max(1, $tm['max_agents'])) * 100) ?>%; height:100%; background:<?= $tm['color'] ?>; border-radius:2px; transition:width 0.3s;"></div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($teams)): ?>
        <div style="grid-column:1/-1; text-align:center; padding:60px; color:var(--text-muted);">
            <i class="fas fa-sitemap" style="font-size:48px; margin-bottom:16px; opacity:0.3;"></i>
            <h3 style="font-size:18px; margin-bottom:8px;">No Teams Yet</h3>
            <p style="font-size:14px;">Create your first team to organize agents.</p>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ==========================================
     MODALS
     ========================================== -->

<!-- Add Agent Modal -->
<?php if ($canCreateAgent): ?>
<div class="modal" id="addAgentModal" style="display:none;">
    <div class="modal-overlay" onclick="document.getElementById('addAgentModal').style.display='none'"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-user-plus" style="color:var(--accent-blue);"></i> Add New Agent</h2>
            <button class="modal-close" onclick="document.getElementById('addAgentModal').style.display='none'"><i class="fas fa-xmark"></i></button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="create_agent">
            <?= $auth->csrfField() ?>
            <div class="form-row">
                <div class="form-group"><label>Username *</label><input type="text" name="username" required class="form-control"></div>
                <div class="form-group"><label>Email *</label><input type="email" name="email" required class="form-control"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Full Name *</label><input type="text" name="full_name" required class="form-control"></div>
                <div class="form-group"><label>Display Name</label><input type="text" name="display_name" placeholder="Same as full name" class="form-control"></div>
            </div>
            <div class="form-group"><label>Password</label><input type="password" name="password" placeholder="Default: password123" class="form-control"></div>
            <div class="form-row">
                <div class="form-group">
                    <label>Assign to Team</label>
                    <select name="team_id" class="form-control">
                        <option value="0">No Team</option>
                        <?php foreach ($teams as $team): ?>
                        <option value="<?= $team['id'] ?>"><?= htmlspecialchars($team['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select name="agent_role" class="form-control">
                        <option value="agent">Agent</option>
                        <?php if ($currentRole === 'owner' || $currentRole === 'admin'): ?>
                        <option value="team">Team Lead</option>
                        <?php endif; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Reply Mode</label>
                <select name="reply_mode" class="form-control">
                    <option value="manual">Manual</option>
                    <option value="bot">Bot Module</option>
                    <option value="ai">AI Assistant</option>
                    <option value="hybrid">Hybrid Team</option>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="document.getElementById('addAgentModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn-primary">Create Agent</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Add Team Modal -->
<?php if ($canCreateTeam): ?>
<div class="modal" id="addTeamModal" style="display:none;">
    <div class="modal-overlay" onclick="document.getElementById('addTeamModal').style.display='none'"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-users" style="color:var(--accent-blue);"></i> Create Team</h2>
            <button class="modal-close" onclick="document.getElementById('addTeamModal').style.display='none'"><i class="fas fa-xmark"></i></button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="create_team">
            <?= $auth->csrfField() ?>
            <div class="form-group"><label>Team Name *</label><input type="text" name="team_name" required class="form-control"></div>
            <div class="form-group"><label>Description</label><textarea name="team_description" rows="2" class="form-control"></textarea></div>
            <div class="form-row">
                <div class="form-group"><label>Color</label><input type="color" name="team_color" value="#2563eb" class="form-control" style="height:44px; padding:4px;"></div>
                <div class="form-group"><label>Max Agents</label><input type="number" name="max_agents" value="<?= $maxAgentsPerTeam ?>" min="1" max="<?= $maxAgentsPerTeam ?>" class="form-control"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="document.getElementById('addTeamModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn-primary">Create Team</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Edit Agent Modal (Dynamic) -->
<div class="modal" id="editAgentModal" style="display:none;">
    <div class="modal-overlay" onclick="document.getElementById('editAgentModal').style.display='none'"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-pen" style="color:var(--accent-blue);"></i> Edit Agent</h2>
            <button class="modal-close" onclick="document.getElementById('editAgentModal').style.display='none'"><i class="fas fa-xmark"></i></button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="update_agent">
            <?= $auth->csrfField() ?>
            <input type="hidden" name="agent_id" id="editAgentId">
            <div class="form-group"><label>Display Name</label><input type="text" name="display_name" id="editAgentName" class="form-control"></div>
            <div class="form-row">
                <div class="form-group">
                    <label>Team</label>
                    <select name="team_id" id="editAgentTeam" class="form-control">
                        <option value="0">No Team</option>
                        <?php foreach ($teams as $team): ?>
                        <option value="<?= $team['id'] ?>"><?= htmlspecialchars($team['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Reply Mode</label>
                    <select name="reply_mode" id="editAgentMode" class="form-control">
                        <option value="manual">Manual</option>
                        <option value="bot">Bot Module</option>
                        <option value="ai">AI Assistant</option>
                        <option value="hybrid">Hybrid Team</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                    <input type="checkbox" name="status" id="editAgentStatus" value="1"> Active
                </label>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="document.getElementById('editAgentModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
// Edit Agent Data (populated via PHP)
const agentsData = <?= json_encode(array_map(function($a) {
    return ['id'=>$a['id'], 'display_name'=>$a['display_name'], 'team_id'=>$a['team_id'], 'reply_mode'=>$a['reply_mode'], 'status'=>$a['status'], 'is_online'=>$a['is_online'] ?? 0];
}, $agents)) ?>;

// Real-time agent online status polling
let agentsPollTimer = null;
async function pollAgentStatus() {
    try {
        const res = await fetch('/api/api_check_notif.php?agent_id=<?= $myAgentId ?>');
        const data = await res.json();
        if (data.online_agents !== undefined) {
            // Update online status indicators
            document.querySelectorAll('.online-status').forEach(function(el) {
                // This is handled by the server-side rendered data
            });
        }
    } catch(e) {}
    
    agentsPollTimer = setTimeout(pollAgentStatus, 10000);
}

// Toggle online status via AJAX without page reload
function toggleOnlineAjax() {
    fetch('?toggle_online=1', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(d => {
            if (d.status === 'success') {
                // Update all online indicators for current user
                document.querySelectorAll('.online-status[data-agent="<?= $myAgentId ?>"]').forEach(function(el) {
                    const dot = el.querySelector('.status-dot');
                    if (dot) dot.style.background = d.is_online ? 'var(--success)' : '#d1d5db';
                    el.querySelector('span:last-child').textContent = d.is_online ? 'Online' : 'Offline';
                });
                
                // Reload page after short delay to update all data
                setTimeout(function() { location.reload(); }, 500);
            }
        })
        .catch(function() {});
}

// Start polling
setTimeout(pollAgentStatus, 3000);

function editAgent(id) {
    const agent = agentsData.find(a => a.id == id);
    if (!agent) return;
    document.getElementById('editAgentId').value = agent.id;
    document.getElementById('editAgentName').value = agent.display_name;
    document.getElementById('editAgentTeam').value = agent.team_id || 0;
    document.getElementById('editAgentMode').value = agent.reply_mode;
    document.getElementById('editAgentStatus').checked = agent.status == 1;
    document.getElementById('editAgentModal').style.display = 'flex';
}

function editTeam(id) {
    // Redirect to edit page or populate modal
    alert('Edit team ' + id + ' - Implement team edit modal similarly');
}
</script>

<?php include 'includes/layout-footer.php'; ?>