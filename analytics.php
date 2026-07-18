<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
$auth->requireAuth();
$user = $auth->getCurrentUser();
$db = Database::getInstance();

// Pastikan agent ada
$agent = $db->fetch("SELECT * FROM agents WHERE user_id = ?", [$user['id']]);
if (!$agent) {
    $team = $db->fetch("SELECT * FROM teams WHERE user_id = ? LIMIT 1", [$user['id']]);
    $teamId = $team ? $team['id'] : $db->insert("INSERT INTO teams (user_id, name, description) VALUES (?, 'Default', 'Auto')", [$user['id']]);
    $agentId = $db->insert("INSERT INTO agents (team_id, user_id, display_name, reply_mode) VALUES (?, ?, ?, 'manual')", [$teamId, $user['id'], $user['full_name'] ?? $user['username']]);
    $agent = $db->fetch("SELECT * FROM agents WHERE id = ?", [$agentId]);
}
$agentId = $agent['id'];

$activePage = 'analytics';
$pageTitle  = "Analytics - LiveChat Console";

// --- STATISTIK UTAMA ---
$totalChats      = $db->fetch("SELECT COUNT(*) as count FROM conversations")['count']; // semua chat
$totalAgentChats = $db->fetch("SELECT COUNT(*) as count FROM conversations WHERE agent_id = ?", [$agentId])['count']; // chat oleh agent ini
$totalTeamChats  = $db->fetch("SELECT COUNT(*) as count FROM conversations c JOIN agents a ON c.agent_id = a.id WHERE a.team_id = ?", [$agent['team_id']])['count']; // chat oleh tim
$activeChats     = $db->fetch("SELECT COUNT(*) as count FROM conversations WHERE status = 'active'")['count'];
$totalMessages   = $db->fetch("SELECT COUNT(*) as count FROM messages")['count'];
$totalVisitors   = $db->fetch("SELECT COUNT(DISTINCT visitor_id) as count FROM conversations")['count'];

// --- CHAT BERDASARKAN ISSUE TYPE ---
// Gunakan json_extract jika SQLite mendukung, jika tidak pakai LIKE
$issueStats = [];
$issueTypes = ['deposit', 'withdraw', 'reset_password', 'kendala_lainnya'];
foreach ($issueTypes as $type) {
    // Coba gunakan json_extract
    $count = $db->fetch(
        "SELECT COUNT(*) as cnt FROM conversations WHERE json_extract(tags, '$.issue_type') LIKE ?",
        ['%' . $type . '%']
    )['cnt'];
    $issueStats[$type] = $count;
}

// Atau jika json_extract tidak berfungsi, gunakan LIKE biasa (jangan keduanya)
if (empty(array_sum($issueStats))) {
    // fallback manual
    foreach ($issueTypes as $type) {
        $count = $db->fetch(
            "SELECT COUNT(*) as cnt FROM conversations WHERE tags LIKE ?",
            ['%' . $type . '%']
        )['cnt'];
        $issueStats[$type] = $count;
    }
}

// Total chat yang memiliki issue deposit spesifik dengan account_id = 'tes'
$depositWithTes = $db->fetch(
    "SELECT COUNT(*) as cnt FROM conversations WHERE tags LIKE ?",
    ['%"issue_type":["deposit"]%"account_id":"tes"%']
)['cnt'];

// --- MOST ACTIVE VISITOR ---
$topVisitors = $db->fetchAll("
    SELECT v.username, v.phone, COUNT(c.id) as chat_count
    FROM conversations c
    JOIN visitors v ON c.visitor_id = v.id
    GROUP BY c.visitor_id
    ORDER BY chat_count DESC
    LIMIT 5
");

// --- MOST COMMON ISSUE (dari tags) ---
$issueSummary = [];
$allTags = $db->fetchAll("SELECT tags FROM conversations WHERE tags IS NOT NULL AND tags != ''");
foreach ($allTags as $row) {
    $tags = json_decode($row['tags'], true);
    if (isset($tags['issue_type']) && is_array($tags['issue_type'])) {
        foreach ($tags['issue_type'] as $issue) {
            $label = ucfirst(str_replace('_', ' ', $issue));
            if (!isset($issueSummary[$label])) $issueSummary[$label] = 0;
            $issueSummary[$label]++;
        }
    } elseif (isset($tags['issue_text']) && !empty($tags['issue_text'])) {
        $label = $tags['issue_text'];
        if (!isset($issueSummary[$label])) $issueSummary[$label] = 0;
        $issueSummary[$label]++;
    }
}
arsort($issueSummary);

// --- REKAP AGENT & TEAM ---
$teamAgents = $db->fetchAll("SELECT a.display_name, COUNT(c.id) as chat_count FROM agents a LEFT JOIN conversations c ON a.id = c.agent_id WHERE a.team_id = ? GROUP BY a.id ORDER BY chat_count DESC", [$agent['team_id']]);

include 'includes/layout-header.php';
?>

<style>
    .stats-grid {margin : center ; display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; }
    .stat-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; display: flex; flex-direction: column; gap: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
    .stat-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
    .stat-icon.blue { background: #eff6ff; color: #2563eb; }
    .stat-icon.green { background: #ecfdf5; color: #10b981; }
    .stat-icon.orange { background: #fff7ed; color: #f97316; }
    .stat-icon.purple { background: #f5f3ff; color: #8b5cf6; }
    .stat-value { font-size: 28px; font-weight: 700; color: #1e293b; }
    .stat-label { font-size: 14px; color: #64748b; }

    .analytics-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
    @media (max-width: 768px) { .analytics-grid { grid-template-columns: 1fr; } }
    .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
    .card h3 { font-size: 16px; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }

    .activity-item { display: flex; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px solid #f8f8f8; }
    .activity-avatar { width: 36px; height: 36px; border-radius: 50%; background: #2563eb; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 14px; }
    .rank-number { width: 28px; height: 28px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: 700; background: #f1f5f9; color: #64748b; }
    .rank-number.top { background: #2563eb; color: #fff; }

    .progress-bar-bg { background: #f1f5f9; border-radius: 4px; height: 6px; margin: 8px 0; }
    .progress-bar-fill { height: 100%; border-radius: 4px; }

    .detail-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .detail-table th, .detail-table td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #f1f5f9; }
    .detail-table th { color: #64748b; font-weight: 600; }

    .full-width { grid-column: 1 / -1; }
</style>

<div class="main-content-wrapper">
   
    <div class="main-content-wrapper" style="background: #fff; min-height: 100vh; padding: 50px;">
        <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <div>
                <h1 style="font-size: 24px; font-weight: 700; color: #18181b;">Analytics</h1>
                <p style="color: #71717a; font-size: 14px;">Manage your auto-reply rules in a list view</p>
            </div>
        
        </div>
    <!-- STATS ROW -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fa-solid fa-comments"></i></div>
            <div class="stat-value"><?= number_format($totalChats) ?></div>
            <div class="stat-label">Total Chats</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fa-solid fa-circle-check"></i></div>
            <div class="stat-value"><?= number_format($activeChats) ?></div>
            <div class="stat-label">Active Chats</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon orange"><i class="fa-solid fa-message"></i></div>
            <div class="stat-value"><?= number_format($totalMessages) ?></div>
            <div class="stat-label">Total Messages</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon purple"><i class="fa-solid fa-users"></i></div>
            <div class="stat-value"><?= number_format($totalVisitors) ?></div>
            <div class="stat-label">Unique Visitors</div>
        </div>
    </div>

    <!-- DETAIL ANALYTICS -->
    <div class="analytics-grid">
        
        <!-- ISSUE DISTRIBUTION -->
        <div class="card">
            <h3><i class="fa-solid fa-chart-pie"></i> Chats by Issue Type</h3>
            <?php 
            $maxIssue = max($issueStats) ?: 1;
            foreach ($issueStats as $label => $count): 
                $percent = round(($count / max($totalChats, 1)) * 100, 1);
            ?>
                <div style="margin-bottom:15px;">
                    <div style="display:flex; justify-content:space-between; font-size:13px;">
                        <span><?= ucfirst(str_replace('_', ' ', $label)) ?></span>
                        <strong><?= $count ?></strong>
                    </div>
                    <div class="progress-bar-bg">
                        <div class="progress-bar-fill" style="width:<?= ($count / $maxIssue) * 100 ?>%; background:#2563eb;"></div>
                    </div>
                </div>
            <?php endforeach; ?>
            <div style="margin-top:10px; font-size:12px; color:#64748b;">
                <i class="fas fa-info-circle"></i> Deposit with account "tes": <strong><?= $depositWithTes ?></strong> chats
            </div>
        </div>

        <!-- TOP VISITORS -->
        <div class="card">
            <h3><i class="fa-solid fa-user-group"></i> Most Active Visitors</h3>
            <?php foreach ($topVisitors as $index => $vis): ?>
                <div style="display:flex; align-items:center; gap:12px; padding:10px 0; border-bottom:1px solid #f8f8f8;">
                    <div class="rank-number <?= $index < 3 ? 'top' : '' ?>"><?= $index + 1 ?></div>
                    <div style="flex:1">
                        <div style="font-weight:600; font-size:13px;"><?= htmlspecialchars($vis['username']) ?></div>
                        <div style="font-size:11px; color:#64748b;"><?= htmlspecialchars($vis['phone']) ?></div>
                    </div>
                    <div style="font-weight:700; color:#2563eb; font-size:14px;"><?= $vis['chat_count'] ?></div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($topVisitors)): ?>
                <p style="color:#64748b; text-align:center;">No visitors yet</p>
            <?php endif; ?>
        </div>

        <!-- MOST COMMON ISSUE TEXT -->
        <div class="card">
            <h3><i class="fa-solid fa-tags"></i> Most Common Issues</h3>
            <?php $i = 1; foreach ($issueSummary as $issue => $count): ?>
                <div style="display:flex; align-items:center; gap:12px; padding:10px 0; border-bottom:1px solid #f8f8f8;">
                    <div class="rank-number <?= $i <= 3 ? 'top' : '' ?>"><?= $i ?></div>
                    <div style="flex:1"><?= htmlspecialchars($issue) ?></div>
                    <div style="font-weight:700;"><?= $count ?></div>
                </div>
            <?php $i++; endforeach; ?>
            <?php if (empty($issueSummary)): ?>
                <p style="color:#64748b; text-align:center;">No issue data</p>
            <?php endif; ?>
        </div>

        <!-- AGENT & TEAM PERFORMANCE -->
        <div class="card">
            <h3><i class="fa-solid fa-users-gear"></i> Agent & Team Chats</h3>
            <p style="font-size:13px; margin-bottom:10px;">
                Total chats by this agent: <strong><?= $totalAgentChats ?></strong><br>
                Total chats by team (<?= htmlspecialchars($agent['display_name'] ?? 'Team') ?>): <strong><?= $totalTeamChats ?></strong>
            </p>
            <table class="detail-table">
                <thead><tr><th>Agent</th><th>Chats</th></tr></thead>
                <tbody>
                    <?php foreach ($teamAgents as $ta): ?>
                    <tr>
                        <td><?= htmlspecialchars($ta['display_name']) ?></td>
                        <td><?= $ta['chat_count'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/layout-footer.php'; ?>