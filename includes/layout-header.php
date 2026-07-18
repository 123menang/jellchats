<?php
/**
 * INCLUDES/layout-header.php
 * TEMPLATE HEADER UNIVERSAL ADMIN PANEL
 */

if (!isset($user) || !isset($db)) {
    require_once __DIR__ . '/auth.php';
    $auth->requireAuth();
    $user = $auth->getCurrentUser();
    $db = Database::getInstance();
}

$agentRow = $db->fetch("SELECT id, team_id, is_online, display_name, reply_mode, avatar FROM agents WHERE user_id = ?", [$user['id']]);
$myAgentId   = $agentRow ? $agentRow['id'] : 0;
$myTeamId    = $agentRow ? $agentRow['team_id'] : -1;
$isOnline    = $agentRow ? $agentRow['is_online'] : 0;

// 🔥 Ambil daftar agent yang online (pakai kolom avatar, bukan avatar_url)
if ($user['role'] === 'agent') {
    $onlineAgents = $db->fetchAll("SELECT id, display_name, avatar FROM agents WHERE team_id = ? AND is_online = 1 LIMIT 5", [$myTeamId]);
    $onlineCount = count($onlineAgents);
} else {
    $onlineAgents = $db->fetchAll("SELECT id, display_name, avatar FROM agents WHERE is_online = 1 LIMIT 5");
    $onlineCount = count($onlineAgents);
}

// Ambil total semua agent online (untuk badge lebih)
if ($user['role'] === 'agent') {
    $totalOnline = $db->fetch("SELECT COUNT(*) as count FROM agents WHERE team_id = ? AND is_online = 1", [$myTeamId])['count'] ?? 0;
} else {
    $totalOnline = $db->fetch("SELECT COUNT(*) as count FROM agents WHERE is_online = 1")['count'] ?? 0;
}

$totalUnread = 0;
if ($myAgentId) {
    $totalUnread = $db->fetch("
        SELECT COUNT(DISTINCT c.id) as count 
        FROM conversations c 
        JOIN messages m ON c.id = m.conversation_id 
        WHERE c.agent_id = ? AND m.sender_type = 'visitor' AND m.is_read = 0 AND c.status != 'closed'
    ", [$myAgentId])['count'] ?? 0;
}

$uAva = !empty($user['avatar']) ? $user['avatar'] : 'assets/images/default-avatar.png';
$pageTitle = $pageTitle ?? 'LiveChat Admin';
$activePage = $activePage ?? 'chats';

// Fungsi helper untuk avatar color
function getAvatarColorByName($name) {
    $colors = ['#10b981', '#3b82f6', '#8b5cf6', '#ec489a', '#f59e0b', '#ef4444', '#06b6d4', '#84cc16', '#d946ef', '#14b8a6', '#f97316', '#6366f1'];
    $hash = 0;
    for ($i = 0; $i < strlen($name); $i++) {
        $hash += ord($name[$i]);
    }
    return $colors[$hash % count($colors)];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="icon" href="https://cdn.livechat-files.com/api/file/lc/main/default/logo/sz2tt7jpJ6VJwBo.png"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    

<style>
/* ============================================
   UNIVERSAL ADMIN LIVE CHAT - COMPLETE CSS
   ============================================ */
:root {
  --bg-dark: #09090b; --bg-dark-hover: #18181b; --bg-light: #f4f5f7; --bg-gray: #f8fafc;
  --border-color: #e4e4e7; --border-light: #f1f5f9; --text-dark: #fafafa; --text-light: #09090b;
  --text-muted: #71717a; --text-secondary: #94a3b8; --accent-blue: #2563eb; --accent-indigo: #4f46e5;
  --accent-violet: #8b5cf6; --danger: #ef4444; --danger-bg: #fef2f2; --success: #10b981;
  --success-bg: #ecfdf5; --warning: #fbbf24; --warning-bg: #fffbeb; --bot-chats: #14ff4f85;
  --zinc-800: #27272a; --card-bg: #f4f4f5; --sidebar-width: 68px; --chat-list-width: 320px;
  --details-width: 320px; --topbar-height: 60px; --radius-sm: 6px; --radius-md: 8px;
  --radius-lg: 12px; --radius-xl: 16px; --radius-2xl: 20px;
  --shadow-sm: 0 1px 2px rgba(0,0,0,0.02); --shadow-md: 0 10px 25px rgba(0,0,0,0.1);
  --shadow-lg: 0 10px 30px rgba(0,0,0,0.2); --shadow-xl: 0 20px 25px -5px rgba(0,0,0,0.1);
}
*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

html, body {
  font-family:'Inter',-apple-system,BlinkMacSystemFont,sans-serif; 
  background:var(--bg-dark);
  color:var(--text-dark); 
  height:100vh; 
  width:100vw;
  overflow: hidden;
  -webkit-font-smoothing:antialiased; -moz-osx-font-smoothing:grayscale;
}
a { text-decoration:none; color:inherit; }
button { font-family:inherit; }

/* LOADER */
#pageLoader { position:fixed; inset:0; background:var(--bg-light); z-index:9999; display:flex;
  align-items:center; justify-content:center; transition:opacity 0.4s ease; }
#pageLoader.fade-out { opacity:0; visibility:hidden; }

/* APP WRAPPER */
.app-wrapper {
  display: flex;
  flex-direction: column;
  height: 100vh;
  width: 100vw;
  overflow: hidden;
}

/* TOPBAR */
.topbar-modern {
  height:var(--topbar-height); 
  background:var(--bg-dark); 
  display:flex; 
  align-items:center;
  justify-content:space-between; 
  padding:0 24px; 
  border-bottom:1px solid var(--zinc-800);
  z-index:100; 
  flex-shrink:0; 
  position:sticky; 
  top:0;
}
/* Search Box */
.search-box-wrap {
    flex: 1;
    max-width: 380px;
    margin: 0 20px;
    background: #1e293b;
    border-radius: 12px;
    padding: 8px 16px;
    display: flex;
    align-items: center;
    gap: 10px;
    border: 1px solid #334155;
    transition: all 0.2s;
}

.search-box-wrap:focus-within {
    border-color: #3b82f6;
    background: #0f172a;
}

.search-box-wrap input {
    flex: 1;
    background: transparent;
    border: none;
    outline: none;
    color: #f1f5f9;
    font-size: 13px;
}

.search-box-wrap input::placeholder {
    color: #64748b;
}

.search-shortcut {
    font-size: 11px;
    color: #64748b;
    background: #334155;
    padding: 2px 6px;
    border-radius: 6px;
    font-family: monospace;
}

/* Topbar Right */
.topbar-right {
    display: flex;
    align-items: center;
    gap: 20px;
}

/* 🔥 ONLINE AGENTS AVATAR GROUP */
.online-agents-group {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #1e293b;
    padding: 4px 12px 4px 8px;
    border-radius: 40px;
    border: 1px solid #334155;
    cursor: pointer;
    transition: all 0.2s;
    position: relative;
}

.online-agents-group:hover {
    background: #334155;
    border-color: #475569;
}

.agents-avatars {
    display: flex;
    align-items: center;
}

.agent-avatar-item {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 700;
    color: #fff;
    border: 2px solid #0f172a;
    transition: transform 0.2s;
    position: relative;
    background-size: cover;
    background-position: center;
}

.agent-avatar-item:not(:first-child) {
    margin-left: -8px;
}

.online-agents-group:hover .agent-avatar-item {
    transform: translateY(-2px);
}

.agent-status-dot {
    position: absolute;
    bottom: -1px;
    right: -1px;
    width: 10px;
    height: 10px;
    background: #22c55e;
    border-radius: 50%;
    border: 2px solid #1e293b;
}

.online-count-badge {
    font-size: 12px;
    font-weight: 600;
    color: #f1f5f9;
    background: #334155;
    padding: 2px 8px;
    border-radius: 20px;
    margin-left: 4px;
}

/* Dropdown untuk list agent online */
.online-agents-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    margin-top: 12px;
    background: #1e293b;
    border-radius: 12px;
    min-width: 240px;
    box-shadow: 0 10px 25px -5px rgba(0,0,0,0.3);
    border: 1px solid #334155;
    overflow: hidden;
    display: none;
    z-index: 200;
}

.online-agents-group:hover .online-agents-dropdown {
    display: block;
    animation: dropdownSlide 0.2s ease;
}

@keyframes dropdownSlide {
    from {
        opacity: 0;
        transform: translateY(-8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.dropdown-header-agent {
    padding: 12px 16px;
    border-bottom: 1px solid #334155;
    font-size: 12px;
    font-weight: 600;
    color: #94a3b8;
    display: flex;
    justify-content: space-between;
}

.dropdown-agent-list {
    max-height: 300px;
    overflow-y: auto;
}

.dropdown-agent-item {
    padding: 10px 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    transition: background 0.2s;
    cursor: pointer;
}

.dropdown-agent-item:hover {
    background: #334155;
}

.dropdown-agent-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 700;
    color: #fff;
    flex-shrink: 0;
    background-size: cover;
    background-position: center;
}

.dropdown-agent-info {
    flex: 1;
}

.dropdown-agent-name {
    font-size: 13px;
    font-weight: 600;
    color: #f1f5f9;
}

.dropdown-agent-status {
    font-size: 10px;
    color: #22c55e;
    display: flex;
    align-items: center;
    gap: 4px;
    margin-top: 2px;
}

.dropdown-agent-status .dot {
    width: 6px;
    height: 6px;
    background: #22c55e;
    border-radius: 50%;
    display: inline-block;
}

/* Top Avatar */
.top-avatar-wrap {
    position: relative;
    cursor: pointer;
}

.top-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #3b82f6;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border: 2px solid #475569;
    transition: transform 0.2s;
}

.top-avatar:hover {
    transform: scale(1.05);
    border-color: #3b82f6;
}

.top-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.avatar-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    margin-top: 12px;
    background: #1e293b;
    border-radius: 12px;
    min-width: 220px;
    box-shadow: 0 10px 25px -5px rgba(0,0,0,0.3);
    border: 1px solid #334155;
    overflow: hidden;
    display: none;
    z-index: 200;
}

.top-avatar-wrap:hover .avatar-dropdown {
    display: block;
    animation: dropdownSlide 0.2s ease;
}

.dropdown-header {
    padding: 16px;
    text-align: center;
    border-bottom: 1px solid #334155;
}

.dropdown-header .name {
    font-size: 14px;
    font-weight: 700;
    color: #f1f5f9;
}

.dropdown-header .role {
    font-size: 11px;
    color: #94a3b8;
    margin-top: 4px;
}

.avatar-dropdown a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    color: #cbd5e1;
    text-decoration: none;
    font-size: 13px;
    transition: background 0.2s;
}

.avatar-dropdown a:hover {
    background: #334155;
    color: #f1f5f9;
}

.avatar-dropdown a i {
    width: 18px;
    font-size: 14px;
}

/* Mobile responsive */
@media (max-width: 768px) {
    .topbar-modern {
        padding: 10px 16px;
    }
    
    .search-box-wrap {
        max-width: 200px;
        margin: 0 10px;
        padding: 6px 12px;
    }
    
    .search-shortcut {
        display: none;
    }
    
    .online-agents-group {
        padding: 2px 8px 2px 4px;
    }
    
    .agent-avatar-item {
        width: 28px;
        height: 28px;
        font-size: 10px;
    }
    
    .online-count-badge {
        font-size: 10px;
        padding: 1px 6px;
    }
    
    .top-avatar {
        width: 36px;
        height: 36px;
    }
}

@media (max-width: 480px) {
    .search-box-wrap {
        display: none;
    }
    
    .online-agents-group .online-count-badge {
        display: none;
    }
}
.search-box-wrap {
  background:var(--bg-dark-hover); 
  border:1px solid var(--zinc-800); 
  border-radius:var(--radius-md);
  display:flex; 
  align-items:center; 
  padding:6px 12px; 
  width:400px;
}
.search-box-wrap input {
  background:transparent; 
  border:none; 
  color:white; 
  margin-left:10px; 
  flex:1; 
  outline:none; 
  font-size:14px;
}
.search-shortcut { color:var(--text-muted); font-size:11px; border:1px solid var(--zinc-800); padding:2px 6px; border-radius:var(--radius-sm); }
.topbar-right { display:flex; align-items:center; gap:16px; }
.btn-invite { background:transparent; border:1px solid var(--zinc-800); color:white; padding:6px 12px;
  border-radius:20px; font-size:13px; cursor:pointer; transition:0.2s; }
.btn-invite:hover { background:var(--bg-dark-hover); }
.online-badge { display:flex; align-items:center; gap:8px; background:rgba(16,185,129,0.1);
  color:var(--success); padding:6px 12px; border-radius:20px; font-size:13px; font-weight:500; }
.pulse-dot { width:8px; height:8px; background:var(--success); border-radius:50%; animation:pulse 2s infinite; }
@keyframes pulse { 0%{box-shadow:0 0 0 0 rgba(16,185,129,0.4)} 70%{box-shadow:0 0 0 8px rgba(16,185,129,0)} 100%{box-shadow:0 0 0 0 rgba(16,185,129,0)} }

/* Top Avatar & Dropdown */
.top-avatar-wrap { position:relative; cursor:pointer; }
.top-avatar { width:32px; height:32px; border-radius:50%; overflow:hidden; border:2px solid var(--zinc-800);
  background:var(--accent-blue); display:flex; align-items:center; justify-content:center;
  font-size:12px; font-weight:600; color:white; }
.top-avatar img { width:100%; height:100%; object-fit:cover; }
.avatar-dropdown { position:absolute; right:0; top:45px; width:220px; background:white; border-radius:var(--radius-lg);
  box-shadow:var(--shadow-lg); display:none; flex-direction:column; overflow:hidden; z-index:1000; }
.avatar-dropdown.show { display:flex; }
.avatar-dropdown .dropdown-header { padding:16px; border-bottom:1px solid var(--card-bg); }
.avatar-dropdown .dropdown-header .name { font-weight:700; font-size:14px; color:var(--bg-dark-hover); }
.avatar-dropdown .dropdown-header .role { font-size:12px; color:var(--text-muted); }
.avatar-dropdown a { padding:12px 16px; color:var(--text-light); font-size:14px; display:flex; align-items:center; gap:10px; transition:0.2s; }
.avatar-dropdown a:hover { background:var(--card-bg); }

/* MAIN LAYOUT */
.app-body { 
  display:flex; 
  flex:1; 
  overflow:hidden; 
  position:relative; 
  min-height:0; 
}
.main-content-wrapper { 
  flex:1; 
  background-color:var(--bg-light); 
  border-top-left-radius:16px; 
  color:var(--text-light);
  overflow-y: auto;
  overflow-x:hidden;
  display:flex; 
  flex-direction:column; 
  min-height:0; 
}
.columns-container { 
  display:flex; 
  height:100%; 
  width:100%; 
  overflow:hidden; 
}

/* SIDEBAR */
.sidebar-left { 
  width:var(--sidebar-width); 
  background:var(--bg-dark); 
  border-right:1px solid var(--zinc-800);
  display:flex; 
  flex-direction:column; 
  align-items:center; 
  justify-content:space-between;
  padding:20px 0; 
  z-index:90; 
  flex-shrink:0; 
}
.nav-top, .sidebar-bottom { display:flex; flex-direction:column; gap:2px; align-items:center; width:100%; }
.sidebar-bottom { margin-top:auto; }
.nav-item { width:44px; height:44px; border-radius:10px; display:flex; align-items:center; justify-content:center;
  color:var(--text-muted); text-decoration:none; font-size:20px; transition:0.2s; margin-bottom:12px;
  position:relative; border:none; background:transparent; cursor:pointer; }
.nav-item:hover, .nav-item.active { color:white; background:var(--bg-dark-hover); }
.nav-item.active::after { content:''; position:absolute; left:-12px; top:12px; height:20px; width:4px;
  background:var(--accent-blue); border-radius:0 4px 4px 0; }
.bell-dot { position:absolute; top:2px; right:2px; width:8px; height:8px; background:var(--danger); border-radius:50%; border:2px solid var(--bg-dark); }
.sidebar-badge { color:white; font-size:10px; min-width:16px; height:16px; display:flex; align-items:center;
  justify-content:center; font-weight:700; padding:0 4px; position:absolute; top:2px; right:2px; border-radius:50%; background:var(--danger); border:2px solid var(--bg-dark); }
.bottom-avatar { width:34px; height:34px; background:var(--accent-blue); border-radius:50%; display:flex;
  align-items:center; justify-content:center; font-size:14px; font-weight:600; color:white;
  position:relative; cursor:pointer; margin-top:10px; overflow:hidden; border:2px solid transparent; }
.bottom-avatar img { width:100%; height:100%; object-fit:cover; }
.status-dot { position:absolute; bottom:0; right:0; width:10px; height:10px; background:var(--success);
  border-radius:50%; border:2px solid var(--bg-dark); display:none; z-index:2; }
.status-dot.online { display:block; }

/* Notif Dropdown */
.notif-wrap { position:relative; }
#sideNotifDrop { position:absolute; left:100%; bottom:0; margin-left:12px; background:white; color:var(--text-light);
  width:320px; border-radius:var(--radius-lg); box-shadow:var(--shadow-md); border:1px solid var(--border-color);
  display:none; z-index:200; overflow:hidden; text-align:left; }
#sideNotifDrop.show { display:block; }
.notif-header { padding:14px 16px; font-weight:600; border-bottom:1px solid var(--border-color); font-size:14px; }
.notif-item { padding:12px 16px; border-bottom:1px solid var(--card-bg); display:flex; gap:12px; align-items:flex-start; font-size:13px; }
.notif-item:hover { background:var(--card-bg); }
.notif-item:last-child { border-bottom:none; }

/* PAGE CONTENT */
.page-content {
  padding: 32px 40px;
  overflow-y: auto;
  flex: 1;
  min-height: 0;
}

/* PAGE HEADER & ALERTS */
.page-header { 
  display:flex; 
  justify-content:space-between; 
  align-items:flex-end; 
  margin-bottom:32px; 
  flex-wrap:wrap; 
  gap:16px; 
}
.page-header h1 { font-size:24px; font-weight:600; margin-bottom:6px; }
.page-header p { color:var(--text-muted); font-size:14px; }
.page-actions { display:flex; gap:10px; }

.alert { 
  max-width:680px; 
  margin:0 auto 24px; 
  padding:12px 16px; 
  border-radius:var(--radius-md); 
  display:flex; 
  align-items:center; 
  gap:10px; 
  font-size:14px; 
}
.alert-success { background:#f0fdf4; border:1px solid #bbf7d0; color:#166534; }
.alert-error { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; }
.alert-warning { background:var(--warning-bg); border:1px solid #fde68a; color:#92400e; }

/* TABS */
.page-tabs {
  display:flex; 
  gap:0; 
  border-bottom:2px solid var(--border-color); 
  margin-bottom:24px;
}
.tab-item {
  padding:12px 24px; 
  font-size:14px; 
  font-weight:600; 
  color:var(--text-muted);
  border-bottom:2px solid transparent;
  text-decoration:none; 
  transition:0.2s;
  display: flex;
  align-items: center;
  gap: 6px;
}
.tab-item.active {
  color: var(--accent-blue);
  border-bottom-color: var(--accent-blue);
}
.tab-item:hover {
  color: var(--text-light);
}

/* LICENSE BAR */
.license-bar {
  background:#f8fafc; 
  border:1px solid var(--border-color); 
  border-radius:var(--radius-lg); 
  padding:16px 20px; 
  margin-bottom:24px; 
  display:flex; 
  justify-content:space-between; 
  align-items:center; 
  flex-wrap:wrap; 
  gap:10px;
}
.license-stats {
  display:flex; 
  gap:20px; 
  align-items:center;
}
.license-stat {
  display: flex;
  flex-direction: column;
}
.stat-label {
  font-size:11px; 
  color:var(--text-muted); 
  text-transform:uppercase; 
  font-weight:600;
}
.stat-value {
  font-size:14px; 
  font-weight:700; 
  color:var(--text-light); 
  text-transform:capitalize;
}
.license-divider {
  width:1px; 
  height:30px; 
  background:var(--border-color);
}
.license-warning {
  background:var(--warning-bg); 
  color:#92400e; 
  padding:6px 12px; 
  border-radius:var(--radius-sm); 
  font-size:12px; 
  font-weight:600;
}

/* BUTTONS */
.btn-primary { background:var(--accent-blue); color:white; border:none; padding:10px 20px; border-radius:var(--radius-md);
  font-size:14px; font-weight:500; cursor:pointer; display:inline-flex; align-items:center; gap:8px; transition:0.2s; }
.btn-primary:hover { background:#1d4ed8; }
.btn-primary:disabled { opacity:0.5; cursor:not-allowed; }
.btn-secondary { background:white; border:1px solid var(--border-color); color:var(--text-light); padding:10px 20px;
  border-radius:var(--radius-md); font-size:14px; font-weight:500; cursor:pointer; transition:0.2s; }
.btn-secondary:hover { background:var(--card-bg); }
.btn-icon { width:32px; height:32px; border-radius:var(--radius-sm); display:inline-flex; align-items:center;
  justify-content:center; color:var(--text-muted); text-decoration:none; transition:0.2s; background:transparent; border:1px solid transparent; cursor:pointer; }
.btn-icon:hover { background:var(--card-bg); color:var(--text-light); }
.btn-icon.danger:hover { background:var(--danger-bg); color:var(--danger); }

/* TABLES */
.table-responsive { 
  overflow-x:auto; 
  background:white; 
  border-radius:var(--radius-lg); 
  border:1px solid var(--border-color); 
}
.agents-table { width:100%; border-collapse:collapse; min-width:800px; }
.agents-table th { background:#f8fafc; padding:14px 20px; text-align:left; font-size:12px; font-weight:600;
  color:var(--text-muted); text-transform:uppercase; letter-spacing:0.5px; border-bottom:1px solid var(--border-color); }
.agents-table td { padding:16px 20px; border-bottom:1px solid var(--card-bg); font-size:14px; vertical-align:middle; }
.agents-table tr:last-child td { border-bottom:none; }
.agents-table tr:hover { background:#f8fafc; }

/* AGENT COMPONENTS */
.agent-info { display:flex; align-items:center; gap:12px; }
.agent-avatar { width:40px; height:40px; border-radius:50%; background:linear-gradient(135deg,var(--accent-blue),var(--accent-violet));
  color:white; display:flex; align-items:center; justify-content:center; font-weight:600; font-size:14px; flex-shrink:0; }
.agent-name { font-weight:600; }
.agent-email { font-size:12px; color:var(--text-muted); margin-top:2px; }
.you-badge {
  font-size:10px; 
  background:var(--accent-blue); 
  color:#fff; 
  padding:1px 6px; 
  border-radius:4px;
  margin-left: 4px;
}

/* Role Badges */
.role-badge {
  font-size:11px; 
  font-weight:600; 
  text-transform:uppercase; 
  padding:3px 8px; 
  border-radius:4px;
}
.role-owner { background:#fef3c7; color:#92400e; }
.role-admin { background:#e0e7ff; color:#3730a3; }
.role-team { background:#fce7f3; color:#be185d; }
.role-agent { background:#f3f4f6; color:#374151; }

.status-badge { display:inline-flex; align-items:center; gap:6px; padding:4px 12px; border-radius:20px; font-size:12px; font-weight:600; }
.status-badge i { font-size:6px; }
.status-active { background:var(--success-bg); color:#065f46; border:1px solid #a7f3d0; }
.status-inactive { background:var(--card-bg); color:var(--text-muted); border:1px solid var(--border-color); }

.reply-mode-badge { display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:var(--radius-sm); font-size:12px; font-weight:500; }
.mode-manual { background:#eff6ff; color:#1e40af; border:1px solid #bfdbfe; }
.mode-bot { background:var(--success-bg); color:#065f46; border:1px solid #a7f3d0; }
.mode-ai { background:#f5f3ff; color:#5b21b6; border:1px solid #ddd6fe; }
.mode-hybrid { background:var(--warning-bg); color:#92400e; border:1px solid #fde68a; }

.team-badge { display:inline-flex; align-items:center; gap:6px; padding:4px 12px; background:var(--card-bg);
  border-radius:20px; font-size:12px; font-weight:500; color:var(--text-muted); border:1px solid var(--border-color); }
.team-dot { width:8px; height:8px; border-radius:50%; }

.online-status {
  display:flex; 
  align-items:center; 
  gap:6px; 
  font-size:12px;
}
.online-status.online { color: var(--success); }
.online-status.offline { color: var(--text-muted); }
.online-status .status-dot {
  width:8px; 
  height:8px; 
  border-radius:50%;
}
.online-status.online .status-dot { background: var(--success); }
.online-status.offline .status-dot { background: #d1d5db; }

.action-btns { display:flex; gap:4px; justify-content:flex-end; }

/* Empty States */
.empty-state {
  text-align:center; 
  padding:40px; 
  color:var(--text-muted);
}
.empty-state-wide {
  grid-column:1/-1; 
  text-align:center; 
  padding:60px; 
  color:var(--text-muted);
}

/* TEAMS GRID */
.teams-grid {
  display:grid; 
  grid-template-columns:repeat(auto-fill, minmax(300px, 1fr)); 
  gap:20px;
}
.team-card {
  background:white; 
  border:1px solid var(--border-color); 
  border-radius:var(--radius-lg); 
  padding:24px; 
  position:relative;
}
.team-card-header {
  display:flex; 
  align-items:center; 
  gap:12px; 
  margin-bottom:16px;
}
.team-icon {
  width:48px; 
  height:48px; 
  border-radius:12px; 
  display:flex; 
  align-items:center; 
  justify-content:center; 
  color:white; 
  font-size:20px;
}
.team-name {
  font-weight:700; 
  font-size:16px; 
  color:var(--text-light);
}
.team-meta {
  font-size:12px; 
  color:var(--text-muted);
}
.team-description {
  font-size:13px; 
  color:var(--text-muted); 
  margin-bottom:16px; 
  line-height:1.5;
}
.team-card-footer {
  display:flex; 
  justify-content:space-between; 
  align-items:center;
}
.team-avatars {
  display:flex; 
  gap:6px;
}
.team-avatar-mini {
  width:28px; 
  height:28px; 
  border-radius:50%; 
  display:flex; 
  align-items:center; 
  justify-content:center; 
  color:white; 
  font-size:10px; 
  font-weight:600;
}
.team-avatar-more {
  width:28px; 
  height:28px; 
  border-radius:50%; 
  background:var(--card-bg); 
  display:flex; 
  align-items:center; 
  justify-content:center; 
  color:var(--text-muted); 
  font-size:10px; 
  font-weight:600;
}
.team-progress {
  margin-top:16px; 
  height:4px; 
  background:var(--border-light); 
  border-radius:2px; 
  overflow:hidden;
}
.progress-bar {
  height:100%; 
  border-radius:2px; 
  transition:width 0.3s;
}

/* ============================================
   CHAT LAYOUT (3-COLUMN)
   ============================================ */
.chat-list-col { width:var(--chat-list-width); border-right:1px solid var(--border-light); display:flex;
  flex-direction:column; background:var(--bg-light); flex-shrink:0; transition:transform 0.3s ease; }
.chat-middle-col { flex:1; display:flex; flex-direction:column; background:var(--bg-gray); position:relative; min-width:0; }
.details-col { width:var(--details-width); border-left:1px solid var(--border-light); background:var(--bg-light);
  overflow-y:auto; flex-shrink:0; transition:transform 0.3s ease; }

/* INBOX */
.inbox-controls { padding:15px; border-bottom:1px solid var(--border-color); display:flex; flex-direction:column; gap:10px; background:var(--bg-gray); }
.inbox-search { display:flex; background:var(--bg-light); border:1px solid var(--border-color); border-radius:var(--radius-sm);
  overflow:hidden; padding:6px 10px; align-items:center; }
.inbox-search input { border:none; outline:none; width:100%; font-size:13px; margin-left:8px; background:transparent; color:var(--text-light); }
.inbox-sort { padding:6px; font-size:12px; border:1px solid var(--border-color); border-radius:var(--radius-sm);
  background:var(--bg-light); outline:none; cursor:pointer; color:var(--text-muted); }
.chat-items { overflow-y:auto; flex:1; }
.chat-item { display:flex; padding:15px; text-decoration:none; color:inherit; border-bottom:1px solid var(--bg-gray); gap:12px; transition:background 0.2s; }
.chat-item:hover { background:var(--bg-gray); }
.chat-item.active { background:#eff6ff; border-left:3px solid var(--accent-blue); }
.badge-unread { background:var(--danger); color:white; font-size:10px; padding:2px 6px; border-radius:10px; font-weight:bold; }

/* CHAT HEADER */
.chat-header { padding:15px 25px; background:var(--bg-light); border-bottom:1px solid var(--border-color);
  display:flex; justify-content:space-between; align-items:center; }
.chat-actions button { background:var(--border-light); color:var(--text-muted); border:none; padding:8px 12px;
  border-radius:var(--radius-sm); font-size:13px; font-weight:600; cursor:pointer; transition:0.2s; margin-left:5px; }
.chat-actions button:hover { background:#e2e8f0; }
.chat-actions .btn-danger { color:var(--danger); background:var(--danger-bg); }
.chat-actions .btn-danger:hover { background:#fee2e2; }

/* MESSAGES AREA */
.messages-area { flex:1; padding:25px; overflow-y:auto; display:flex; flex-direction:column; gap:20px; }
.message-row { display:flex; gap:10px; align-items:flex-end; max-width:75%; }
.message-row.visitor { align-self:flex-start; flex-direction:row; }
.message-row.agent { align-self:flex-end; flex-direction:row-reverse; }
.message-row.bot, .message-row.ai { align-self:flex-end; flex-direction:row-reverse; }
.chat-avatar { width:28px; height:28px; border-radius:50%; background:var(--success); color:white; display:flex;
  align-items:center; justify-content:center; font-size:12px; font-weight:bold; flex-shrink:0; overflow:hidden; }
.chat-avatar img { width:100%; height:100%; object-fit:cover; }
.chat-avatar.agent-ava { background:var(--accent-blue); }
.message-content { display:flex; flex-direction:column; }
.visitor .message-content { align-items:flex-start; }
.agent .message-content, .bot .message-content, .ai .message-content { align-items:flex-end; }
.message-bubble { padding:10px 15px; border-radius:12px; font-size:14px; word-wrap:break-word; line-height:1.5; position:relative; }
.visitor .message-bubble { background:var(--bg-light); border:1px solid var(--border-color); color:var(--text-light); border-bottom-left-radius:2px; box-shadow:var(--shadow-sm); }
.agent .message-bubble { background:var(--accent-blue); color:white; border-bottom-right-radius:2px; }
.bot .message-bubble, .ai .message-bubble { background:var(--bot-chats); color:var(--text-light); border:1px solid var(--border-color); border-bottom-right-radius:2px; }
.msg-img { max-width:200px; border-radius:8px; cursor:pointer; }
.time-stamp { font-size:10px; color:#94a3b8; margin-top:4px; display:flex; align-items:center; gap:4px; }

/* PRE-CHAT CARD */
.pre-chat-card { background:var(--border-light); border-radius:var(--radius-lg); padding:15px 20px; font-size:13px;
  color:var(--text-light); width:fit-content; max-width:350px; align-self:flex-start; margin-bottom:10px; margin-left:38px; }
.pre-chat-card strong { display:block; margin-bottom:12px; color:#0f172a; font-size:14px; font-weight:700; }
.pre-chat-item { margin-bottom:10px; }
.pre-chat-label { color:var(--text-muted); font-size:12px; display:block; margin-bottom:2px; }
.pre-chat-value { color:var(--accent-blue); font-weight:500; }
.pre-chat-value.dark { color:var(--text-light); font-weight:400; }

/* TYPING */
.typing-preview-wrap { display:none; align-self:flex-start; gap:10px; margin-top:5px; align-items:flex-end; }
.typing-preview-bubble { background:var(--border-light); color:var(--text-secondary); font-style:italic; padding:10px 15px;
  border-radius:18px; border-bottom-left-radius:2px; font-size:13px; display:flex; align-items:center; gap:8px; border:1px solid #e2e8f0; }
.typing-dots { display:inline-flex; gap:3px; }
.typing-dots span { width:4px; height:4px; background:var(--text-secondary); border-radius:50%; animation:blink 1.4s infinite both; }
.typing-dots span:nth-child(2){animation-delay:0.2s} .typing-dots span:nth-child(3){animation-delay:0.4s}
@keyframes blink{0%,80%,100%{opacity:0.3}40%{opacity:1}}

/* ============================================
   COMPOSER - PROFESIONAL + EMOJI
   ============================================ */
.input-wrapper { background:var(--bg-light); padding:15px 25px; border-top:1px solid var(--border-color);
  display:flex; flex-direction:column; gap:10px; position:relative; }
.composer-box { border:1px solid var(--border-color); border-radius:var(--radius-lg); background:var(--bg-light);
  display:flex; flex-direction:column; overflow:hidden; box-shadow:var(--shadow-sm); }
.composer-box textarea { width:100%; border:none; padding:15px; font-size:14px; outline:none; resize:none; min-height:50px; max-height:120px; }
.composer-toolbar { display:flex; justify-content:space-between; align-items:center; padding:8px 15px; background:#fbfbfc; border-top:1px solid var(--border-light); }
.composer-tools { display:flex; gap:15px; color:var(--text-secondary); font-size:16px; align-items:center; }
.composer-tools i { cursor:pointer; transition:0.2s; }
.composer-tools i:hover { color:var(--accent-blue); }
.btn-send { background:var(--border-color); color:#fff; border:none; padding:10px 20px; border-radius:var(--radius-sm);
  cursor:not-allowed; font-weight:600; transition:0.2s; }
.btn-send.active { background:var(--accent-blue); cursor:pointer; }
.canned-list { display:flex; gap:8px; overflow-x:auto; padding-bottom:5px; scrollbar-width:none; }
.canned-list::-webkit-scrollbar { display:none; }
.canned-pill { background:var(--bg-light); border:1px solid var(--border-color); padding:6px 12px; border-radius:20px;
  font-size:12px; color:var(--text-light); white-space:nowrap; cursor:pointer; transition:0.2s; box-shadow:var(--shadow-sm); }
.canned-pill:hover { background:var(--bg-gray); border-color:#cbd5e1; }
.canned-pill.btn-add { background:var(--bg-dark); color:white; border:none; }

/* EMOJI PICKER */
.emoji-picker-wrap { position:absolute; bottom:100%; left:0; margin-bottom:10px; background:white; border:1px solid var(--border-color);
  border-radius:var(--radius-lg); box-shadow:var(--shadow-lg); padding:10px; display:none; z-index:100; width:280px; }
.emoji-picker-wrap.show { display:block; }
.emoji-categories { display:flex; gap:10px; border-bottom:1px solid var(--border-light); padding-bottom:8px; margin-bottom:8px; }
.emoji-cat { font-size:18px; cursor:pointer; padding:4px; border-radius:4px; transition:0.2s; }
.emoji-cat:hover, .emoji-cat.active { background:var(--bg-gray); }
.emoji-grid { display:grid; grid-template-columns:repeat(8, 1fr); gap:4px; max-height:200px; overflow-y:auto; }
.emoji-item { font-size:20px; cursor:pointer; padding:4px; text-align:center; border-radius:4px; transition:0.2s; }
.emoji-item:hover { background:var(--bg-gray); transform:scale(1.2); }

/* DETAILS SIDEBAR */
.visitor-profile-head { text-align:center; padding:25px 20px 15px; border-bottom:1px solid var(--border-color); }
.vp-avatar { width:60px; height:60px; background:var(--success); color:white; border-radius:50%; display:flex;
  align-items:center; justify-content:center; font-size:24px; margin:0 auto 10px; font-weight:bold; }
#visitorMap { height:120px; width:100%; border:none; margin-top:15px; z-index:1; }
.accordion-item { border-bottom:1px solid var(--border-color); }
.accordion-header { padding:15px 20px; display:flex; justify-content:space-between; align-items:center;
  cursor:pointer; font-weight:600; font-size:14px; color:#1e293b; transition:background 0.2s; }
.accordion-header:hover { background:var(--bg-gray); }
.accordion-header i { transition:transform 0.3s; color:var(--text-secondary); font-size:12px; }
.accordion-body { padding:0 20px 15px; display:none; font-size:13px; color:var(--text-muted); }
.accordion-item.open .accordion-body { display:block; }
.accordion-item.open .accordion-header i { transform:rotate(180deg); }
.info-row { display:flex; justify-content:space-between; margin-bottom:8px; }
.info-label { color:var(--text-muted); }
.info-value { color:var(--text-light); font-weight:500; text-align:right; }
.visited-page-item { position:relative; padding-left:15px; margin-bottom:10px; }
.visited-page-item::before { content:''; position:absolute; left:0; top:6px; width:6px; height:6px; border-radius:50%; background:var(--text-secondary); }
.vp-title { color:var(--accent-blue); text-decoration:underline; display:block; margin-bottom:2px; word-break:break-all; }
.vp-time { font-size:11px; color:var(--text-secondary); }

/* ============================================
   MODALS - FIXED FOR MOBILE
   ============================================ */
.modal { 
  display:none; 
  position:fixed; 
  inset:0; 
  align-items:center; 
  justify-content:center; 
  z-index:1000; 
  padding: 20px;
}
.modal.show {
  display: flex;
}
.modal-overlay { position:absolute; inset:0; background:rgba(0,0,0,0.5); backdrop-filter:blur(2px); }
.modal-content { 
  position:relative; 
  background:white; 
  width:100%; 
  max-width:500px; 
  border-radius:var(--radius-xl);
  padding:32px; 
  box-shadow:var(--shadow-xl); 
  max-height:90vh; 
  overflow-y:auto;
}
.modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; }
.modal-header h2 { font-size:20px; font-weight:600; display:flex; align-items:center; gap:10px; }
.modal-close { background:transparent; border:none; font-size:20px; color:var(--text-muted); cursor:pointer; }
.modal-footer { display:flex; justify-content:flex-end; gap:12px; margin-top:32px; }
.form-group { margin-bottom:20px; }
.form-group label { display:block; margin-bottom:8px; font-weight:500; font-size:14px; color:var(--text-light); }
.form-group input, .form-group select, .form-group textarea, .form-control {
  width:100%; padding:10px 14px; border:1px solid var(--border-color); border-radius:var(--radius-md);
  font-size:14px; outline:none; background:white; color:var(--text-light); transition:0.2s; font-family:inherit; }
.form-group input:focus, .form-group select:focus, .form-group textarea:focus, .form-control:focus {
  border-color:var(--accent-blue); box-shadow:0 0 0 3px rgba(37,99,235,0.1); }
.form-row { display:flex; gap:16px; flex-wrap:wrap; }
.form-row .form-group { flex:1; min-width:150px; }
.checkbox-group label {
  display: flex !important;
  align-items: center;
  gap: 8px;
  cursor: pointer;
}
.checkbox-group input[type="checkbox"] {
  width: auto;
}
.color-input {
  height: 44px;
  padding: 4px;
  cursor: pointer;
}

/* REPLY MODE TOGGLE */
.mode-toggle-bar { display:flex; gap:8px; padding:8px 15px; background:#f8fafc; border-bottom:1px solid var(--border-color); align-items:center; }
.mode-toggle-bar label { font-size:12px; font-weight:600; color:var(--text-muted); }
.mode-select { padding:6px 12px; border:1px solid var(--border-color); border-radius:var(--radius-sm);
  font-size:13px; background:white; color:var(--text-light); cursor:pointer; outline:none; }
.mode-select:focus { border-color:var(--accent-blue); }

/* ============================================
   PROFILE PAGE - NEW STYLES
   ============================================ */
.profile-grid {
  display: grid;
  grid-template-columns: 1fr 320px;
  gap: 24px;
  max-width: 1200px;
  margin: 0 auto;
}
.profile-grid .card {
  background: white;
  border: 1px solid var(--border-color);
  border-radius: var(--radius-lg);
  padding: 32px;
}
.avatar-edit-box {
  display: flex;
  justify-content: center;
  margin-bottom: 24px;
}
.avatar-container {
  position: relative;
  width: 120px;
  height: 120px;
}
.avatar-lg {
  width: 120px;
  height: 120px;
  border-radius: 50%;
  object-fit: cover;
  border: 3px solid var(--border-color);
}
.ava-cam-btn {
  position: absolute;
  bottom: 0;
  right: 0;
  width: 36px;
  height: 36px;
  background: var(--accent-blue);
  color: white;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  border: 3px solid white;
  font-size: 14px;
}
.ava-cam-btn:hover {
  background: #1d4ed8;
}
.btn-save {
  background: var(--accent-blue);
  color: white;
  border: none;
  padding: 12px 24px;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  width: 100%;
  margin-top: 16px;
  transition: 0.2s;
}
.btn-save:hover {
  background: #1d4ed8;
}
.license-side {
  background: #1e293b;
  color: white;
  padding: 24px;
  border-radius: var(--radius-lg);
  text-align: center;
}
.days-val {
  font-size: 48px;
  font-weight: 700;
  margin: 8px 0;
  color: var(--success);
}

/* ============================================
   SETTINGS PAGE STYLES
   ============================================ */
.settings-layout {
  display: grid;
  grid-template-columns: 240px 1fr;
  gap: 24px;
  max-width: 1200px;
  margin: 0 auto;
}
.settings-sidebar {
  background: white;
  border: 1px solid var(--border-color);
  border-radius: var(--radius-lg);
  padding: 16px;
  height: fit-content;
  position: sticky;
  top: 20px;
}
.settings-nav {
  list-style: none;
}
.settings-nav li {
  margin-bottom: 4px;
}
.settings-nav a {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border-radius: var(--radius-sm);
  color: var(--text-muted);
  font-size: 14px;
  font-weight: 500;
  transition: 0.2s;
}
.settings-nav a:hover,
.settings-nav a.active {
  background: #eff6ff;
  color: var(--accent-blue);
}
.settings-content {
  background: white;
  border: 1px solid var(--border-color);
  border-radius: var(--radius-lg);
  padding: 32px;
}
.settings-section {
  display: none;
}
.settings-section.active {
  display: block;
}
.toggle-switch {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 0;
  border-bottom: 1px solid var(--border-light);
}
.toggle-switch:last-child {
  border-bottom: none;
}
.toggle-info h4 {
  font-size: 14px;
  font-weight: 600;
  color: var(--text-light);
  margin-bottom: 4px;
}
.toggle-info p {
  font-size: 13px;
  color: var(--text-muted);
}
.switch {
  position: relative;
  width: 44px;
  height: 24px;
}
.switch input {
  opacity: 0;
  width: 0;
  height: 0;
}
.slider {
  position: absolute;
  cursor: pointer;
  inset: 0;
  background: #e4e4e7;
  border-radius: 24px;
  transition: 0.3s;
}
.slider:before {
  content: "";
  position: absolute;
  height: 18px;
  width: 18px;
  left: 3px;
  bottom: 3px;
  background: white;
  border-radius: 50%;
  transition: 0.3s;
}
input:checked + .slider {
  background: var(--accent-blue);
}
input:checked + .slider:before {
  transform: translateX(20px);
}
.form-divider {
  height: 1px;
  background: var(--border-color);
  margin: 24px 0;
}
.form-label {
  display: block;
  margin-bottom: 8px;
  font-weight: 500;
  font-size: 14px;
  color: var(--text-light);
}
.form-input {
  width: 100%;
  padding: 10px 14px;
  border: 1px solid var(--border-color);
  border-radius: var(--radius-md);
  font-size: 14px;
  outline: none;
  background: white;
  color: var(--text-light);
  transition: 0.2s;
  font-family: inherit;
}
.form-input:focus {
  border-color: var(--accent-blue);
  box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
}
.form-input optgroup {
  font-weight: 600;
  color: var(--text-muted);
}

/* ============================================
   MOBILE RESPONSIVE - COMPLETE FIX
   ============================================ */
@media (max-width: 768px) {
  .hide-mobile { display:none !important; }
  .hide-desktop { display:flex !important; }

  .topbar-modern { padding:0 15px; }
  .search-box-wrap { display:none !important; }
  .search-container { width:auto; flex:1; margin-right:15px; }
  .topbar-right .btn-invite { display:none; }

  .app-body { flex-direction:column; }

  /* Sidebar jadi bottom bar */
  .sidebar-left { 
    position:fixed; 
    bottom:0; 
    left:0; 
    right:0; 
    width:100%; 
    height:65px;
    flex-direction:row; 
    justify-content:space-around; 
    align-items:center; 
    padding:0 10px;
    border-right:none; 
    border-top:1px solid var(--zinc-800); 
    z-index:50; 
  }
  .nav-top, .sidebar-bottom { flex-direction:row; width:auto; gap:5px; }
  .sidebar-bottom { margin-top:0; }
  .nav-item { width:45px; height:45px; margin-bottom:0; }
  .nav-item.active::after { 
    left:50%; 
    top:0; 
    transform:translateX(-50%); 
    height:3px; 
    width:20px; 
    border-radius:0 0 4px 4px; 
  }
  .bell-dot { top:8px; right:10px; }
  .bottom-avatar { margin-top:0; margin-left:5px; }

  #sideNotifDrop { 
    position:fixed; 
    left:10px; 
    right:10px; 
    bottom:75px; 
    width:auto; 
    margin-left:0; 
    box-shadow:0 -4px 20px rgba(0,0,0,0.15); 
  }

  .main-content-wrapper { 
    border-radius:0; 
    overflow-y:auto;
    padding-bottom: 80px;
  }
  .columns-container { position:relative; }

  .chat-list-col { 
    position:absolute; 
    top:0; 
    left:0; 
    width:100%; 
    height:100%; 
    z-index:20;
    transform:translateX(-100%); 
    background:var(--bg-light); 
  }
  .chat-list-col.mobile-show { transform:translateX(0); }

  .details-col { 
    position:absolute; 
    top:0; 
    right:0; 
    width:100%; 
    height:100%; 
    z-index:20;
    transform:translateX(100%); 
    background:var(--bg-light); 
  }
  .details-col.mobile-show { transform:translateX(0); }

  .chat-middle-col { width:100%; }

  .chat-header { padding:12px 16px; }
  .messages-area { padding:16px; }
  .input-wrapper { padding:12px 16px; }
  
  /* Page content mobile */
  .page-content {
    padding: 20px 16px;
    padding-bottom: 100px;
  }
  .page-header {
    flex-direction: column;
    align-items: flex-start;
  }
  .page-actions {
    width: 100%;
  }
  .page-actions button {
    width: 100%;
    justify-content: center;
  }
  
  /* Table mobile */
  .table-responsive {
    border-radius: var(--radius-md);
  }
  .agents-table {
    min-width: 600px;
  }
  
  /* License bar mobile */
  .license-bar {
    flex-direction: column;
    align-items: flex-start;
  }
  .license-stats {
    width: 100%;
    justify-content: space-between;
  }
  
  /* Teams grid mobile */
  .teams-grid {
    grid-template-columns: 1fr;
  }
  
  /* Modal mobile */
  .modal {
    padding: 10px;
  }
  .modal-content {
    padding: 24px;
    width: 100%;
    max-height: 95vh;
  }
  .form-row {
    flex-direction: column;
    gap: 0;
  }
  .form-row .form-group {
    min-width: 100%;
  }

  /* Profile page mobile */
  .profile-grid {
    grid-template-columns: 1fr;
  }
  .profile-grid .card {
    padding: 20px;
  }
  .avatar-lg {
    width: 100px;
    height: 100px;
  }
  .days-val {
    font-size: 36px;
  }
.logo-img {
    height: 60px;
    width: auto;
    object-fit: contain;
    display: block;
    transition: height 0.3s ease;
}
  /* Settings page mobile */
  .settings-layout {
    grid-template-columns: 1fr;
  }
  .settings-sidebar {
    position: static;
    margin-bottom: 16px;
  }
  .settings-nav {
    display: flex;
    gap: 8px;
    overflow-x: auto;
    padding-bottom: 8px;
  }
  .settings-nav li {
    margin-bottom: 0;
    flex-shrink: 0;
  }
  .settings-nav a {
    white-space: nowrap;
  }
}

@media (max-width: 480px) {
  .page-header { flex-direction:column; align-items:flex-start; }
  .license-stats {
    flex-wrap: wrap;
    gap: 10px;
  }
  .license-divider {
    display: none;
  }
}

/* Utility classes */
.hide-desktop { display:none; }
.text-muted { color:var(--text-muted); }
.text-center { text-align:center; }
.text-right { text-align:right; }
.w-full { width:100%; }
.flex { display:flex; } .flex-col { flex-direction:column; }
.items-center { align-items:center; } .justify-between { justify-content:space-between; }
.gap-2 { gap:8px; } .gap-4 { gap:16px; }
</style>
</head>
<body>

<div class="app-wrapper">

<div id="pageLoader">
    <img src="https://cdn.livechat-static.com/webapp/img/logo-animated.webp?d1ffd7cb94478560fcdfc5fc0e21dce9" alt="Loading..." style="height:50px;">
</div>

<!-- TOPBAR -->
<header class="topbar-modern">
    <div style="font-weight:700; font-size:18px; color:white;">
        <?php if (isset($activePage) && $activePage === 'chats'):?>
            <button onclick="togglePanel('list')" style="margin-top:10px;padding:10px 0px;background:transparent;color:#96969a;border:none;border-radius:8px;font-size:19px;font-weight:600;cursor:pointer;">
                <i class="fas fa-list"></i>
            </button>
        <?php endif; ?>
    </div>
    
    <div class="search-box-wrap">
        <i class="fas fa-search" style="color:#a1a1aa"></i>
        <input type="text" placeholder="Search for customers..." id="globalSearch">
        <span class="search-shortcut">⌘ K</span>
    </div>
    
    <div class="topbar-right">
        <!-- 🔥 ONLINE AGENTS AVATAR GROUP -->
        <div class="online-agents-group">
            <div class="agents-avatars">
                <?php if (!empty($onlineAgents)): ?>
                    <?php foreach ($onlineAgents as $index => $agent): 
                        $avatarData = !empty($agent['avatar']) ? $agent['avatar'] : null;
                        $displayName = $agent['display_name'] ?? 'Agent';
                        $initial = strtoupper(substr($displayName, 0, 1));
                        $avatarColor = getAvatarColorByName($displayName);
                        if ($index >= 3) break;
                    ?>
                        <div class="agent-avatar-item" style="background: <?= $avatarColor ?>; <?= $avatarData ? 'background-image: url(' . $avatarData . '); background-size: cover;' : '' ?>" title="<?= htmlspecialchars($displayName) ?>">
                            <?php if (!$avatarData): ?>
                                <?= $initial ?>
                            <?php endif; ?>
                            <span class="agent-status-dot"></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="agent-avatar-item" style="background: #64748b;" title="No agents online">
                        <i class="fas fa-user" style="font-size: 12px;"></i>
                    </div>
                <?php endif; ?>
            </div>
            <?php if ($totalOnline > 3): ?>
                <span class="online-count-badge">+<?= $totalOnline - 3 ?></span>
            <?php elseif ($totalOnline > 0 && $totalOnline <= 3): ?>
                <span class="online-count-badge"><?= $totalOnline ?></span>
            <?php endif; ?>
            
            <!-- Dropdown list semua agent online -->
            <div class="online-agents-dropdown">
                <div class="dropdown-header-agent">
                    <span>Agents Online</span>
                    <span><?= $totalOnline ?> active</span>
                </div>
                <div class="dropdown-agent-list">
                    <?php foreach ($onlineAgents as $agent): 
                        $avatarData = !empty($agent['avatar']) ? $agent['avatar'] : null;
                        $displayName = $agent['display_name'] ?? 'Agent';
                        $initial = strtoupper(substr($displayName, 0, 1));
                        $avatarColor = getAvatarColorByName($displayName);
                    ?>
                        <div class="dropdown-agent-item" onclick="window.location.href='chats.php?agent=<?= $agent['id'] ?>'">
                            <div class="dropdown-agent-avatar" style="background: <?= $avatarColor ?>; <?= $avatarData ? 'background-image: url(' . $avatarData . '); background-size: cover;' : '' ?>">
                                <?php if (!$avatarData): ?>
                                    <?= $initial ?>
                                <?php endif; ?>
                            </div>
                            <div class="dropdown-agent-info">
                                <div class="dropdown-agent-name"><?= htmlspecialchars($displayName) ?></div>
                                <div class="dropdown-agent-status">
                                    <span class="dot"></span> Online
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($onlineAgents)): ?>
                        <div style="padding: 20px; text-align: center; color: #64748b; font-size: 12px;">
                            <i class="fas fa-user-slash"></i> No agents online
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- User Avatar Dropdown -->
        <div class="top-avatar-wrap" id="topAvaBtn">
            <div class="top-avatar">
                <img src="<?= $uAva ?>" alt="Profile" onerror="this.style.display='none';this.parentNode.innerText='<?= strtoupper(substr($user['full_name'] ?? 'A',0,1)) ?>'">
            </div>
            <div class="avatar-dropdown" id="topAvaDrop">
                <div class="dropdown-header">
                    <div class="name"><?= htmlspecialchars($user['full_name'] ?? $user['username']) ?></div>
                    <div class="role"><?= ucfirst($user['role']) ?> Access</div>
                </div>
                <a href="profile.php"><i class="far fa-user-circle"></i> Profile Settings</a>
                <a href="billing.php"><i class="far fa-credit-card"></i> Billing & Plan</a>
                <div style="height:1px; background:#334155;"></div>
                <a href="logout.php" style="color:#ef4444;"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
            </div>
        </div>
    </div>
</header>
<div class="app-body">
    <aside class="sidebar-left">
        <div class="nav-top">
<a href="chats" class="nav-item <?= ($activePage == 'chats') ? 'active' : '' ?>" title="Chats">
    <i class="fas fa-comment-dots"></i>
    <?php if($totalUnread > 0): ?>
        <span class="sidebar-badge"><?= $totalUnread > 99 ? '99+' : $totalUnread ?></span>
    <?php endif; ?>
</a>
          <a href="traffic" class="nav-item <?= ($activePage == 'agents') ? 'active' : '' ?>" title="Team">
             <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" width="24" height="24"><path fill="currentcolor" fill-rule="evenodd" stroke="currentcolor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12h3m6-9v3M7.8 7.8 5.6 5.6m10.6 2.2 2.2-2.2M7.8 16.2l-2.2 2.2M12 12l9 3-4 2-2 4z" clip-rule="evenodd"></path></svg>
            </a>

            <a href="agents.php" class="nav-item <?= ($activePage == 'agents') ? 'active' : '' ?>" title="Team">
                <i class="fas fa-users"></i>
            </a>
            <a href="modules.php" class="nav-item <?= ($activePage == 'modules') ? 'active' : '' ?>" title="Modules">
                <i class="fas fa-robot"></i>
            </a>
            <a href="analytics.php" class="nav-item <?= ($activePage == 'analytics') ? 'active' : '' ?>" title="Analytics">
                <i class="fas fa-chart-line"></i>
            </a>
            <a href="setting-widget.php" class="nav-item hide-mobile <?= ($activePage == 'settings') ? 'active' : '' ?>" title="Widget Code">
                <i class="fa-solid fa-code"></i>
            </a>
            <a href="archive.php" class="nav-item hide-mobile <?= ($activePage == 'archive') ? 'active' : '' ?>" title="Archive">
                <i class="fa-solid fa-box-archive"></i>
            </a>
            <a href="profile.php" class="nav-item hide-desktop <?= ($activePage == 'profile') ? 'active' : '' ?>">
                <i class="fas fa-gear"></i>
            </a>
        </div>
        <div class="sidebar-bottom hide-mobile">
            <a href="billing.php" class="nav-item <?= ($activePage == 'billing') ? 'active' : '' ?>" title="Billing">
                <i class="fas fa-credit-card"></i>
            </a>
            <div class="notif-wrap">
                <button id="notifBellBtn" class="nav-item" title="Notifications">
                    <i class="fas fa-bell"></i>
                    <div class="bell-dot"></div>
                </button>
                <div id="sideNotifDrop">
                    <div class="notif-header">Notifications</div>
                    <div style="max-height:300px; overflow-y:auto;" id="notifContainer">
                        <div class="notif-item">System Active - Latest login session detected.</div>
                    </div>
                </div>
            </div>
            <a href="settings.php" class="nav-item <?= ($activePage == 'settings') ? 'active' : '' ?>" title="Settings">
                <i class="fas fa-gear"></i>
            </a>
            <a href="profile.php" title="My Profile" style="margin-top:10px; display:block;">
                <div class="bottom-avatar" style="border: 2px solid <?= ($activePage == 'profile') ? 'var(--accent-blue)' : '#27272a' ?>;">
                    <img src="<?= $uAva ?>" alt="Avatar" onerror="this.style.display='none';this.parentNode.innerText='<?= strtoupper(substr($user['full_name'] ?? 'A',0,1)) ?>'">
                    <div class="status-dot <?= $isOnline ? 'online' : '' ?>" id="sidebarStatusDot"></div>
                </div>
            </a>
        </div>
    </aside>

    <!-- MAIN CONTENT WRAPPER -->
    <div class="main-content-wrapper">