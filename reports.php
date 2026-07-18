<?php
/**
 * Reports Dashboard
 * Statistik team, laporan harian, performa agent
 */

require_once 'includes/auth.php';
require_once 'includes/functions.php';
$auth->requireAuth();
$user = $auth->getCurrentUser();
$db = Database::getInstance();

$pageTitle = 'Reports Dashboard';
$activePage = 'reports';

$agent = $db->fetch("SELECT * FROM agents WHERE user_id = ?", [$user['id']]);
$isAgent = ($user['role'] === 'agent');
$isOwner = ($user['role'] === 'owner');

// Get date range
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Get statistics
if ($isAgent) {
    // For agent: show their own stats
    $stats = $db->fetch("
        SELECT 
            COUNT(DISTINCT c.id) as total_chats,
            COUNT(m.id) as total_messages,
            AVG(CASE WHEN m.sender_type != 'visitor' THEN TIMESTAMPDIFF(SECOND, 
                (SELECT created_at FROM messages WHERE conversation_id = m.conversation_id AND sender_type = 'visitor' ORDER BY created_at LIMIT 1),
                m.created_at) END) as avg_response_time,
            SUM(CASE WHEN m.sender_type = 'ai' THEN 1 ELSE 0 END) as ai_responses,
            SUM(CASE WHEN m.sender_type = 'bot' THEN 1 ELSE 0 END) as bot_responses,
            AVG(c.rating) as avg_rating
        FROM conversations c
        LEFT JOIN messages m ON c.id = m.conversation_id
        WHERE c.agent_id = ? AND DATE(c.created_at) BETWEEN ? AND ?
    ", [$agent['id'], $startDate, $endDate]);
    
    // Daily report
    $dailyReports = $db->fetchAll("
        SELECT * FROM daily_reports 
        WHERE agent_id = ? AND report_date BETWEEN ? AND ?
        ORDER BY report_date DESC
    ", [$agent['id'], $startDate, $endDate]);
} else {
    // For owner: show all agents stats
    $stats = $db->fetch("
        SELECT 
            COUNT(DISTINCT c.id) as total_chats,
            COUNT(m.id) as total_messages,
            AVG(c.rating) as avg_rating,
            COUNT(DISTINCT a.id) as active_agents,
            COUNT(DISTINCT t.id) as total_teams
        FROM conversations c
        LEFT JOIN messages m ON c.id = m.conversation_id
        LEFT JOIN agents a ON c.agent_id = a.id
        LEFT JOIN teams t ON a.id = t.agent_id
        WHERE DATE(c.created_at) BETWEEN ? AND ?
    ", [$startDate, $endDate]);
    
    // Agent performance
    $agentPerformance = $db->fetchAll("
        SELECT 
            a.display_name,
            a.id as agent_id,
            COUNT(DISTINCT c.id) as chats_handled,
            COUNT(m.id) as messages_sent,
            AVG(c.rating) as satisfaction_rate,
            SUM(CASE WHEN m.sender_type = 'ai' THEN 1 ELSE 0 END) as ai_usage
        FROM agents a
        LEFT JOIN conversations c ON a.id = c.agent_id AND DATE(c.created_at) BETWEEN ? AND ?
        LEFT JOIN messages m ON c.id = m.conversation_id
        WHERE a.status = 1
        GROUP BY a.id
        ORDER BY chats_handled DESC
    ", [$startDate, $endDate]);
}

include 'includes/layout-header.php';
?>

<div class="container-fluid" style="max-width:1400px; margin:0 auto; padding:20px;">
    
    <!-- Header -->
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:20px; margin-bottom:30px;">
        <div>
            <h1 style="font-size:28px; margin-bottom:5px;">📊 Reports Dashboard</h1>
            <p style="color:var(--text-muted);">Performance analytics and insights</p>
        </div>
        
        <!-- Date Filter -->
        <form method="GET" style="display:flex; gap:10px; align-items:center;">
            <input type="date" name="start_date" value="<?= $startDate ?>" class="form-control" style="width:auto;">
            <span>to</span>
            <input type="date" name="end_date" value="<?= $endDate ?>" class="form-control" style="width:auto;">
            <button type="submit" class="btn-primary">Apply</button>
        </form>
    </div>
    
    <!-- Summary Cards -->
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:20px; margin-bottom:40px;">
        <div class="card" style="border-left:4px solid #3b82f6;">
            <div style="color:var(--text-muted); font-size:13px;">Total Chats</div>
            <div style="font-size:32px; font-weight:700;"><?= number_format($stats['total_chats'] ?? 0) ?></div>
            <div style="font-size:12px; margin-top:5px;">in selected period</div>
        </div>
        <div class="card" style="border-left:4px solid #10b981;">
            <div style="color:var(--text-muted); font-size:13px;">Total Messages</div>
            <div style="font-size:32px; font-weight:700;"><?= number_format($stats['total_messages'] ?? 0) ?></div>
        </div>
        <div class="card" style="border-left:4px solid #f59e0b;">
            <div style="color:var(--text-muted); font-size:13px;">Avg Response Time</div>
            <div style="font-size:32px; font-weight:700;"><?= round(($stats['avg_response_time'] ?? 0) / 60, 1) ?> min</div>
        </div>
        <div class="card" style="border-left:4px solid #8b5cf6;">
            <div style="color:var(--text-muted); font-size:13px;">Satisfaction Rate</div>
            <div style="font-size:32px; font-weight:700;"><?= round(($stats['avg_rating'] ?? 0) * 20) ?>%</div>
            <div style="font-size:12px;">★ <?= number_format($stats['avg_rating'] ?? 0, 1) ?> / 5</div>
        </div>
    </div>
    
    <?php if ($isAgent): ?>
    <!-- Agent Detailed Stats -->
    <div class="card" style="margin-bottom:30px;">
        <h3><i class="fas fa-brain"></i> AI & Bot Performance</h3>
        <div style="display:flex; gap:30px; flex-wrap:wrap; margin-top:20px;">
            <div>
                <div style="font-size:13px; color:var(--text-muted);">AI Responses</div>
                <div style="font-size:28px; font-weight:700;"><?= number_format($stats['ai_responses'] ?? 0) ?></div>
                <progress value="<?= ($stats['ai_responses'] ?? 0) / max($stats['total_messages'] ?? 1, 1) * 100 ?>" max="100" style="width:150px;"></progress>
            </div>
            <div>
                <div style="font-size:13px; color:var(--text-muted);">Bot Responses</div>
                <div style="font-size:28px; font-weight:700;"><?= number_format($stats['bot_responses'] ?? 0) ?></div>
            </div>
        </div>
    </div>
    
    <!-- Daily Report Table -->
    <div class="card">
        <h3><i class="fas fa-calendar-day"></i> Daily Summary</h3>
        <div style="overflow-x:auto; margin-top:20px;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Total Chats</th>
                        <th>Avg Response Time</th>
                        <th>Satisfaction</th>
                        <th>Messages</th>
                        <th>AI Assists</th>
                        <th>Bot Assists</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dailyReports as $report): ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($report['report_date'])) ?></td>
                        <td><?= $report['total_chats'] ?></td>
                        <td><?= round($report['avg_response_time'] / 60, 1) ?> min</td>
                        <td><?= $report['satisfaction_rate'] ?>%</td>
                        <td><?= $report['total_messages'] ?></td>
                        <td><?= $report['ai_assists'] ?></td>
                        <td><?= $report['bot_assists'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($dailyReports)): ?>
                    <tr><td colspan="7" style="text-align:center;">No data available</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <?php else: ?>
    <!-- Owner: Agent Performance Table -->
    <div class="card">
        <h3><i class="fas fa-users"></i> Agent Performance</h3>
        <div style="overflow-x:auto; margin-top:20px;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Agent Name</th>
                        <th>Chats Handled</th>
                        <th>Messages Sent</th>
                        <th>Satisfaction Rate</th>
                        <th>AI Usage</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($agentPerformance as $agent): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($agent['display_name']) ?></strong></td>
                        <td><?= number_format($agent['chats_handled'] ?? 0) ?></td>
                        <td><?= number_format($agent['messages_sent'] ?? 0) ?></td>
                        <td>
                            <?php $rate = round(($agent['satisfaction_rate'] ?? 0) * 20); ?>
                            <div class="progress-bar">
                                <div style="width: <?= $rate ?>%; background: <?= $rate >= 80 ? '#10b981' : ($rate >= 60 ? '#f59e0b' : '#ef4444') ?>;"></div>
                            </div>
                            <span style="font-size:12px;"><?= $rate ?>%</span>
                        </td>
                        <td><?= number_format($agent['ai_usage'] ?? 0) ?></td>
                        <td><a href="agent-detail.php?id=<?= $agent['agent_id'] ?>" class="btn-link">View Details →</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Top Performing Teams -->
    <div class="card" style="margin-top:30px;">
        <h3><i class="fas fa-trophy"></i> Top Performing Teams</h3>
        <div style="overflow-x:auto; margin-top:20px;">
            <table class="data-table">
                <thead>
                    <tr><th>Team Name</th><th>Chats</th><th>Resolution Rate</th><th>Satisfaction</th></tr>
                </thead>
                <tbody>
                    <?php
                    $topTeams = $db->fetchAll("
                        SELECT t.name, COUNT(c.id) as chats, AVG(c.rating) as satisfaction
                        FROM teams t
                        JOIN conversations c ON t.id = c.team_id
                        WHERE DATE(c.created_at) BETWEEN ? AND ?
                        GROUP BY t.id
                        ORDER BY chats DESC LIMIT 10
                    ", [$startDate, $endDate]);
                    ?>
                    <?php foreach ($topTeams as $team): ?>
                    <tr>
                        <td><?= htmlspecialchars($team['name']) ?></td>
                        <td><?= $team['chats'] ?></td>
                        <td>-</td>
                        <td>★ <?= number_format($team['satisfaction'] ?? 0, 1) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
    
</div>

<style>
.progress-bar {
    width: 100px;
    height: 6px;
    background: #e2e8f0;
    border-radius: 3px;
    overflow: hidden;
    display: inline-block;
    margin-right: 8px;
}
.progress-bar div {
    height: 100%;
    border-radius: 3px;
}
.data-table {
    width: 100%;
    border-collapse: collapse;
}
.data-table th, .data-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}
.data-table th {
    background: var(--bg-light);
    font-weight: 600;
}
</style>

<?php include 'includes/layout-footer.php'; ?>