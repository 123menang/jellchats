<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
$auth->requireAuth();
$user = $auth->getCurrentUser();
$db = Database::getInstance();

// Ensure agent exists for this user
$agent = $db->fetch("SELECT * FROM agents WHERE user_id = ?", [$user['id']]);
if (!$agent) {
    $team = $db->fetch("SELECT * FROM teams WHERE user_id = ? LIMIT 1", [$user['id']]);
    if (!$team) {
        $teamId = $db->insert(
            "INSERT INTO teams (user_id, name, description, max_agents) VALUES (?, 'Default Team', 'Auto-created team', 1)",
            [$user['id']]
        );
    } else {
        $teamId = $team['id'];
    }
    $agentId = $db->insert(
        "INSERT INTO agents (team_id, user_id, display_name, reply_mode) VALUES (?, ?, ?, 'hybrid')",
        [$teamId, $user['id'], $user['full_name'] ?? $user['username']]
    );
    $agent = $db->fetch("SELECT * FROM agents WHERE id = ?", [$agentId]);
}
$agentId = $agent['id'];

$success = false;
$error = '';
$aiTestResult = $_SESSION['ai_test_result'] ?? null;
unset($_SESSION['ai_test_result']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        $fullName = sanitizeInput($_POST['full_name'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $db->update("UPDATE users SET full_name = ?, email = ? WHERE id = ?", [$fullName, $email, $user['id']]);
        $success = true;
        // Refresh user data
        $user = $db->fetch("SELECT * FROM users WHERE id = ?", [$user['id']]);
    }

    if ($action === 'ai_settings' && $agent) {
        $aiProvider = in_array($_POST['ai_provider'] ?? '', ['claude','gemini','openai']) ? $_POST['ai_provider'] : 'claude';
        $aiToken    = sanitizeInput($_POST['ai_api_token'] ?? '');
        $aiModel    = sanitizeInput($_POST['ai_model'] ?? '');
        $aiPrompt   = sanitizeInput($_POST['ai_system_prompt'] ?? '');
        $aiRules    = sanitizeInput($_POST['ai_rules'] ?? '');
        $aiFallback = sanitizeInput($_POST['ai_fallback_message'] ?? 'Maaf, saya tidak mengerti pertanyaan Anda. Silakan hubungi agen kami.');

        $db->update(
            "UPDATE agents SET ai_provider = ?, ai_api_token = ?, ai_model = ?, ai_system_prompt = ?, ai_rules = ?, ai_fallback_message = ? WHERE id = ?",
            [$aiProvider, $aiToken, $aiModel, $aiPrompt, $aiRules, $aiFallback, $agentId]
        );
        $success = true;
        // Refresh agent data
        $agent = $db->fetch("SELECT * FROM agents WHERE id = ?", [$agentId]);
    }

    // Test AI connection
    if ($action === 'test_ai' && $agent) {
        require_once 'includes/functions.php';
        $fakeHistory = [];
        $result = callAI($agent, $fakeHistory, 'Halo! Ini adalah pesan test koneksi AI.');
        if ($result['success']) {
            $success = true;
            $_SESSION['ai_test_result'] = '✅ Koneksi AI berhasil! Respons: ' . mb_substr($result['text'], 0, 200) . (mb_strlen($result['text']) > 200 ? '...' : '');
        } else {
            $error = '❌ Test AI gagal: ' . ($result['error'] ?? 'Unknown error');
        }
        header("Location: profile.php");
        exit;
    }

    if ($action === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!password_verify($current, $user['password_hash'])) {
            $error = 'Current password is incorrect';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match';
        } elseif (strlen($new) < 6) {
            $error = 'Password must be at least 6 characters';
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT);
            $db->update("UPDATE users SET password_hash = ? WHERE id = ?", [$hash, $user['id']]);
            $success = true;
        }
    }
}

$aiRulesList = $db->fetchAll("SELECT * FROM ai_rules_templates WHERE agent_id = ? ORDER BY created_at DESC", [$agentId]);
$activePage = 'profile';
?>
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

$agentRow = $db->fetch("SELECT id, team_id, is_online, display_name, reply_mode FROM agents WHERE user_id = ?", [$user['id']]);
$myAgentId   = $agentRow ? $agentRow['id'] : 0;
$myTeamId    = $agentRow ? $agentRow['team_id'] : -1;
$isOnline    = $agentRow ? $agentRow['is_online'] : 0;

if ($user['role'] === 'agent') {
    $onlineCount = $db->fetch("SELECT COUNT(*) as count FROM agents WHERE team_id = ? AND is_online = 1", [$myTeamId])['count'] ?? 0;
} else {
    $onlineCount = $db->fetch("SELECT COUNT(*) as count FROM agents WHERE is_online = 1")['count'] ?? 0;
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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title><?= htmlspecialchars($pageTitle) ?> - Profile Settings</title>
    <link rel="icon" href="https://cdn.livechat-files.com/api/file/lc/main/default/logo/sz2tt7jpJ6VJwBo.png"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">

<style>
/* ============================================
   PROFILE PAGE - COMPLETE CSS
   SCROLLABLE & RESPONSIVE
   ============================================ */
:root {
  --bg-dark: #09090b;
  --bg-dark-hover: #18181b;
  --bg-light: #ffffff;
  --bg-gray: #f8fafc;
  --border-color: #e4e4e7;
  --border-light: #f1f5f9;
  --text-dark: #fafafa;
  --text-light: #09090b;
  --text-muted: #71717a;
  --text-gray: #6b7280;
  --accent-blue: #2563eb;
  --accent-indigo: #4f46e5;
  --accent-violet: #8b5cf6;
  --danger: #ef4444;
  --danger-bg: #fef2f2;
  --success: #10b981;
  --success-bg: #ecfdf5;
  --warning: #fbbf24;
  --warning-bg: #fffbeb;
  --zinc-800: #27272a;
  --card-bg: #f4f4f5;
  --sidebar-width: 68px;
  --topbar-height: 60px;
  --radius-sm: 6px;
  --radius-md: 8px;
  --radius-lg: 12px;
  --radius-xl: 16px;
  --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
  --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1);
  --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1);
}

*, *::before, *::after {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

/* BASE HTML & BODY */
html {
  height: 100%;
  overflow: hidden;
}

body {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
  background: var(--bg-dark);
  color: var(--text-dark);
  height: 100vh;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

/* APP WRAPPER */
.app-wrapper {
  display: flex;
  flex-direction: column;
  height: 100vh;
  width: 100%;
  overflow: hidden;
}

/* LOADER */
#pageLoader {
  position: fixed;
  inset: 0;
  background: var(--bg-light);
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: opacity 0.4s ease;
}
#pageLoader.fade-out {
  opacity: 0;
  visibility: hidden;
}

/* TOPBAR */
.topbar-modern {
  height: var(--topbar-height);
  background: var(--bg-dark);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 24px;
  border-bottom: 1px solid var(--zinc-800);
  z-index: 100;
  flex-shrink: 0;
}

.search-box-wrap {
  background: var(--bg-dark-hover);
  border: 1px solid var(--zinc-800);
  border-radius: var(--radius-md);
  display: flex;
  align-items: center;
  padding: 6px 12px;
  width: 400px;
}

.search-box-wrap input {
  background: transparent;
  border: none;
  color: white;
  margin-left: 10px;
  flex: 1;
  outline: none;
  font-size: 14px;
}

.search-shortcut {
  color: var(--text-muted);
  font-size: 11px;
  border: 1px solid var(--zinc-800);
  padding: 2px 6px;
  border-radius: var(--radius-sm);
}

.topbar-right {
  display: flex;
  align-items: center;
  gap: 16px;
}

.online-badge {
  display: flex;
  align-items: center;
  gap: 8px;
  background: rgba(16, 185, 129, 0.1);
  color: var(--success);
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 13px;
  font-weight: 500;
}

.pulse-dot {
  width: 8px;
  height: 8px;
  background: var(--success);
  border-radius: 50%;
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4); }
  70% { box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
  100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}

/* Top Avatar & Dropdown */
.top-avatar-wrap {
  position: relative;
  cursor: pointer;
}

.top-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  overflow: hidden;
  border: 2px solid var(--zinc-800);
  background: var(--accent-blue);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 600;
  color: white;
}

.top-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.avatar-dropdown {
  position: absolute;
  right: 0;
  top: 45px;
  width: 220px;
  background: white;
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-lg);
  display: none;
  flex-direction: column;
  overflow: hidden;
  z-index: 1000;
}

.avatar-dropdown.show {
  display: flex;
}

.avatar-dropdown .dropdown-header {
  padding: 16px;
  border-bottom: 1px solid var(--card-bg);
}

.avatar-dropdown .dropdown-header .name {
  font-weight: 700;
  font-size: 14px;
  color: var(--bg-dark-hover);
}

.avatar-dropdown .dropdown-header .role {
  font-size: 12px;
  color: var(--text-muted);
}

.avatar-dropdown a {
  padding: 12px 16px;
  color: var(--text-light);
  font-size: 14px;
  display: flex;
  align-items: center;
  gap: 10px;
  transition: 0.2s;
}

.avatar-dropdown a:hover {
  background: var(--card-bg);
}

/* MAIN LAYOUT */
.app-body {
  display: flex;
  flex: 1;
  overflow: hidden;
  position: relative;
  min-height: 0;
}

.main-content-wrapper {
  flex: 1;
  background-color: var(--bg-light);
  color: var(--text-light);
  overflow-y: auto !important;
  overflow-x: hidden !important;
  display: block;
  min-height: 0;
}

/* SIDEBAR */
.sidebar-left {
  width: var(--sidebar-width);
  background: var(--bg-dark);
  border-right: 1px solid var(--zinc-800);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: space-between;
  padding: 20px 0;
  z-index: 90;
  flex-shrink: 0;
}

.nav-top, .sidebar-bottom {
  display: flex;
  flex-direction: column;
  gap: 2px;
  align-items: center;
  width: 100%;
}

.sidebar-bottom {
  margin-top: auto;
}

.nav-item {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-muted);
  text-decoration: none;
  font-size: 20px;
  transition: 0.2s;
  margin-bottom: 12px;
  position: relative;
  border: none;
  background: transparent;
  cursor: pointer;
}

.nav-item:hover, .nav-item.active {
  color: white;
  background: var(--bg-dark-hover);
}

.nav-item.active::after {
  content: '';
  position: absolute;
  left: -12px;
  top: 12px;
  height: 20px;
  width: 4px;
  background: var(--accent-blue);
  border-radius: 0 4px 4px 0;
}

.bell-dot {
  position: absolute;
  top: 2px;
  right: 2px;
  width: 8px;
  height: 8px;
  background: var(--danger);
  border-radius: 50%;
  border: 2px solid var(--bg-dark);
}

.sidebar-badge {
  color: white;
  font-size: 10px;
  min-width: 16px;
  height: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  padding: 0 4px;
  position: absolute;
  top: 2px;
  right: 2px;
  border-radius: 50%;
  background: var(--danger);
  border: 2px solid var(--bg-dark);
}

.bottom-avatar {
  width: 34px;
  height: 34px;
  background: var(--accent-blue);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  font-weight: 600;
  color: white;
  position: relative;
  cursor: pointer;
  margin-top: 10px;
  overflow: hidden;
  border: 2px solid transparent;
}

.bottom-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.status-dot {
  position: absolute;
  bottom: 0;
  right: 0;
  width: 10px;
  height: 10px;
  background: var(--success);
  border-radius: 50%;
  border: 2px solid var(--bg-dark);
  display: none;
  z-index: 2;
}

.status-dot.online {
  display: block;
}

/* Notif Dropdown */
.notif-wrap {
  position: relative;
}

#sideNotifDrop {
  position: absolute;
  left: 100%;
  bottom: 0;
  margin-left: 12px;
  background: white;
  color: var(--text-light);
  width: 320px;
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-md);
  border: 1px solid var(--border-color);
  display: none;
  z-index: 200;
  overflow: hidden;
  text-align: left;
}

#sideNotifDrop.show {
  display: block;
}

.notif-header {
  padding: 14px 16px;
  font-weight: 600;
  border-bottom: 1px solid var(--border-color);
  font-size: 14px;
}

.notif-item {
  padding: 12px 16px;
  border-bottom: 1px solid var(--card-bg);
  display: flex;
  gap: 12px;
  align-items: flex-start;
  font-size: 13px;
}

.notif-item:hover {
  background: var(--card-bg);
}

.notif-item:last-child {
  border-bottom: none;
}

/* ============================================
   PROFILE PAGE SPECIFIC STYLES
   ============================================ */

.page-content {
  padding: 32px 40px;
  max-width: 1400px;
  margin: 0 auto;
  width: 100%;
}

.page-header {
  margin-bottom: 32px;
}

.page-header h1 {
  font-size: 28px;
  font-weight: 700;
  color: var(--text-light);
}

/* Alerts */
.alert {
  padding: 14px 18px;
  border-radius: var(--radius-md);
  margin-bottom: 24px;
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 14px;
}

.alert-success {
  background: var(--success-bg);
  border: 1px solid #a7f3d0;
  color: #065f46;
}

.alert-error {
  background: var(--danger-bg);
  border: 1px solid #fecaca;
  color: #991b1b;
}

/* Settings Layout */
.settings-layout {
  display: flex;
  gap: 32px;
  min-height: 500px;
}

/* Settings Sidebar */
.settings-sidebar {
  width: 260px;
  flex-shrink: 0;
  position: sticky;
  top: 20px;
  align-self: flex-start;
}

.settings-nav {
  list-style: none;
  background: white;
  border: 1px solid var(--border-color);
  border-radius: var(--radius-lg);
  overflow: hidden;
}

.settings-nav li a {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 20px;
  color: var(--text-muted);
  text-decoration: none;
  font-size: 14px;
  font-weight: 500;
  transition: all 0.2s;
  border-left: 3px solid transparent;
}

.settings-nav li a i {
  width: 20px;
  font-size: 16px;
}

.settings-nav li a:hover {
  background: var(--bg-gray);
  color: var(--text-light);
}

.settings-nav li a.active {
  background: #eff6ff;
  color: var(--accent-blue);
  border-left-color: var(--accent-blue);
}

/* Settings Content */
.settings-content {
  flex: 1;
  min-width: 0;
}

.settings-section {
  display: none;
  background: white;
  border: 1px solid var(--border-color);
  border-radius: var(--radius-lg);
  padding: 28px 32px;
  margin-bottom: 24px;
}

.settings-section.active {
  display: block;
  animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

.settings-section h2 {
  font-size: 20px;
  font-weight: 700;
  margin-bottom: 8px;
  color: var(--text-light);
  display: flex;
  align-items: center;
}

.settings-section > p {
  color: var(--text-muted);
  font-size: 14px;
  margin-bottom: 24px;
  padding-bottom: 16px;
  border-bottom: 1px solid var(--border-color);
}

/* Form Elements */
.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  font-size: 13px;
  font-weight: 600;
  color: var(--text-light);
  margin-bottom: 8px;
}

.form-group input, .form-group select, .form-group textarea {
  width: 100%;
  padding: 10px 14px;
  border: 1px solid var(--border-color);
  border-radius: var(--radius-md);
  font-size: 14px;
  outline: none;
  transition: all 0.2s;
  font-family: inherit;
}

.form-group input:focus, .form-group select:focus, .form-group textarea:focus {
  border-color: var(--accent-blue);
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.form-group input:disabled {
  background: var(--bg-gray);
  color: var(--text-muted);
}

.form-group small {
  display: block;
  margin-top: 6px;
  font-size: 12px;
  color: var(--text-muted);
}

.form-group small a {
  color: var(--accent-blue);
  text-decoration: none;
}

.form-group small a:hover {
  text-decoration: underline;
}

.form-divider {
  height: 1px;
  background: var(--border-color);
  margin: 24px 0;
}

/* Buttons */
.btn-primary {
  background: var(--accent-blue);
  color: white;
  border: none;
  padding: 10px 24px;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  transition: all 0.2s;
}

.btn-primary:hover {
  background: #1d4ed8;
  transform: translateY(-1px);
}

.btn-secondary {
  background: white;
  border: 1px solid var(--border-color);
  color: var(--text-light);
  padding: 10px 24px;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  transition: all 0.2s;
}

.btn-secondary:hover {
  background: var(--bg-gray);
}

.modal-footer {
  margin-top: 24px;
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}

/* Toggle Switch */
.toggle-switch {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 0;
  border-bottom: 1px solid var(--border-color);
}

.toggle-switch:last-child {
  border-bottom: none;
}

.toggle-info h4 {
  font-size: 15px;
  font-weight: 600;
  margin-bottom: 4px;
}

.toggle-info p {
  font-size: 13px;
  color: var(--text-muted);
}

.switch {
  position: relative;
  display: inline-block;
  width: 50px;
  height: 26px;
}

.switch input {
  opacity: 0;
  width: 0;
  height: 0;
}

.slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: #ccc;
  transition: 0.3s;
  border-radius: 34px;
}

.slider:before {
  position: absolute;
  content: "";
  height: 20px;
  width: 20px;
  left: 3px;
  bottom: 3px;
  background-color: white;
  transition: 0.3s;
  border-radius: 50%;
}

input:checked + .slider {
  background-color: var(--accent-blue);
}

input:checked + .slider:before {
  transform: translateX(24px);
}

/* Mode Reply Grid */
.mode-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
  margin-top: 12px;
}

.mode-card {
  padding: 12px;
  border-radius: 10px;
  border: 1.5px solid var(--border-color);
  background: white;
  transition: all 0.2s;
}

.mode-card.active {
  border-color: var(--accent-blue);
  background: #eff6ff;
}

.mode-card-title {
  font-weight: 600;
  font-size: 14px;
  display: flex;
  align-items: center;
  gap: 6px;
}

.mode-card-desc {
  color: var(--text-muted);
  font-size: 12px;
  margin-top: 4px;
}

/* Custom Scrollbar */
::-webkit-scrollbar {
  width: 8px;
  height: 8px;
}

::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 10px;
}

::-webkit-scrollbar-thumb {
  background: #c1c1c1;
  border-radius: 10px;
}

::-webkit-scrollbar-thumb:hover {
  background: var(--accent-blue);
}

/* ============================================
   MOBILE RESPONSIVE
   ============================================ */
@media (max-width: 992px) {
  .settings-layout {
    flex-direction: column;
    gap: 20px;
  }
  
  .settings-sidebar {
    width: 100%;
    position: relative;
    top: 0;
  }
  
  .settings-nav {
    display: flex;
    overflow-x: auto;
    white-space: nowrap;
  }
  
  .settings-nav li {
    flex-shrink: 0;
  }
  
  .settings-nav li a {
    border-left: none;
    border-bottom: 3px solid transparent;
  }
  
  .settings-nav li a.active {
    border-left-color: transparent;
    border-bottom-color: var(--accent-blue);
  }
}

@media (max-width: 768px) {
  .hide-mobile { display: none !important; }
  .hide-desktop { display: flex !important; }
  
  .topbar-modern { padding: 0 15px; }
  .search-box-wrap { display: none !important; }
  
  /* Sidebar jadi bottom bar */
  .sidebar-left {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    width: 100%;
    height: 65px;
    flex-direction: row;
    justify-content: space-around;
    align-items: center;
    padding: 0 10px;
    border-right: none;
    border-top: 1px solid var(--zinc-800);
    z-index: 1000;
    background: var(--bg-dark);
  }
  
  .nav-top, .sidebar-bottom {
    flex-direction: row;
    width: auto;
    gap: 5px;
  }
  
  .sidebar-bottom { margin-top: 0; }
  .nav-item { width: 45px; height: 45px; margin-bottom: 0; }
  
  .nav-item.active::after {
    left: 50%;
    top: 0;
    transform: translateX(-50%);
    height: 3px;
    width: 20px;
    border-radius: 0 0 4px 4px;
  }
  
  .main-content-wrapper {
    margin-bottom: 65px;
  }
  
  .page-content {
    padding: 20px 16px;
  }
  
  .page-header h1 {
    font-size: 24px;
  }
  
  .settings-section {
    padding: 20px;
  }
  
  .mode-grid {
    grid-template-columns: 1fr;
  }
  
  .modal-footer {
    flex-direction: column;
  }
  
  .modal-footer button {
    width: 100%;
    justify-content: center;
  }
  
  .toggle-switch {
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
  }
}

@media (max-width: 480px) {
  .page-content {
    padding: 16px 12px;
  }
  
  .settings-section {
    padding: 16px;
  }
  
  .settings-section h2 {
    font-size: 18px;
  }
  
  .form-group input, 
  .form-group select, 
  .form-group textarea {
    font-size: 13px;
  }
  
  .btn-primary, .btn-secondary {
    padding: 8px 16px;
    font-size: 13px;
  }
}

/* Utility Classes */
.hide-desktop { display: none; }
.text-muted { color: var(--text-muted); }
.text-center { text-align: center; }
.w-full { width: 100%; }
.flex { display: flex; }
.flex-col { flex-direction: column; }
.items-center { align-items: center; }
.justify-between { justify-content: space-between; }
.gap-2 { gap: 8px; }
.gap-4 { gap: 16px; }
.mt-4 { margin-top: 16px; }
.mb-4 { margin-bottom: 16px; }
</style>
</head>
<body>

<div class="app-wrapper">

<div id="pageLoader">
    <img src="assets/images/logo-animated.webp" alt="Loading..." onerror="this.src='https://i.gifer.com/ZZ5H.gif';this.style.width='40px'">
</div>

<!-- TOPBAR -->
<header class="topbar-modern">
    <div style="font-weight:700; font-size:18px; color:white;">
        LiveChat <span style="font-weight:400; opacity:0.6;">Admin</span>
    </div>
    <div class="search-box-wrap hide-mobile">
        <i class="fas fa-search" style="color:#a1a1aa"></i>
        <input type="text" placeholder="Search settings..." id="globalSearch">
        <span class="search-shortcut">⌘ K</span>
    </div>
    <div class="topbar-right">
        <div class="online-badge hide-mobile">
            <span class="pulse-dot"></span> <?= $onlineCount ?> Online
        </div>
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
                <div style="height:1px; background:#f4f4f5;"></div>
                <a href="logout.php" style="color:var(--danger);"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
            </div>
        </div>
    </div>
</header>

<div class="app-body">
    <aside class="sidebar-left">
        <div class="nav-top">
            <a href="chats.php" class="nav-item <?= ($activePage == 'chats') ? 'active' : '' ?>" title="Chats">
                <i class="fas fa-comment-dots"></i>
                <?php if($totalUnread > 0 && $activePage != 'chats'): ?><span class="sidebar-badge"><?= $totalUnread ?></span><?php endif; ?>
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
        <div class="page-content">
            <div class="page-header">
                <h1>Settings</h1>
            </div>

            <?php if ($success && !isset($_POST['action']) && $_POST['action'] !== 'test_ai'): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-check-circle"></i> Settings saved successfully!
            </div>
            <?php endif; ?>
            
            <?php if ($aiTestResult): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-check-circle"></i> <?= htmlspecialchars($aiTestResult) ?>
            </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <div class="settings-layout">
                <div class="settings-sidebar">
                    <ul class="settings-nav">
                        <li><a href="#" class="active" onclick="showSection('profile', this); return false;"><i class="fa-solid fa-user"></i> Profile</a></li>
                        <li><a href="#" onclick="showSection('ai', this); return false;"><i class="fa-solid fa-brain"></i> AI Settings</a></li>
                        <li><a href="#" onclick="showSection('notifications', this); return false;"><i class="fa-solid fa-bell"></i> Notifications</a></li>
                        <li><a href="#" onclick="showSection('security', this); return false;"><i class="fa-solid fa-shield-halved"></i> Security</a></li>
                        <li><a href="license.php"><i class="fa-solid fa-crown"></i> License</a></li>
                    </ul>
                </div>

                <div class="settings-content">
                    <!-- Profile Section -->
                    <div class="settings-section active" id="profile-section">
                        <h2>Profile Settings</h2>
                        <p>Manage your personal information and preferences</p>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="profile">
                            <div class="form-group">
                                <label>Full Name</label>
                                <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>">
                            </div>
                            <div class="form-group">
                                <label>Username</label>
                                <input type="text" value="<?= htmlspecialchars($user['username']) ?>" disabled>
                            </div>
                            <div class="form-group">
                                <label>License Tier</label>
                                <input type="text" value="<?= ucfirst($user['license_tier'] ?? 'starter') ?>" disabled>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
                            </div>
                        </form>
                    </div>

                    <!-- AI Settings Section -->
                    <div class="settings-section" id="ai-section">
                        <h2><i class="fa-solid fa-brain" style="color:var(--accent-blue);margin-right:8px;"></i> AI Assistant Settings</h2>
                        <p>Konfigurasi AI Chatbot — dukung Claude, Gemini, dan OpenAI</p>

                        <form method="POST" action="" id="aiForm">
                            <input type="hidden" name="action" value="ai_settings">

                            <div class="form-group">
                                <label class="form-label">AI Provider</label>
                                <select name="ai_provider" class="form-input" id="aiProviderSelect" onchange="updateAiProvider()">
                                    <option value="claude" <?= ($agent['ai_provider'] ?? 'claude') === 'claude' ? 'selected' : '' ?>>🤖 Claude (Anthropic)</option>
                                    <option value="gemini" <?= ($agent['ai_provider'] ?? '') === 'gemini' ? 'selected' : '' ?>>✨ Gemini (Google)</option>
                                    <option value="openai" <?= ($agent['ai_provider'] ?? '') === 'openai' ? 'selected' : '' ?>>⚡ OpenAI (ChatGPT)</option>
                                </select>
                                <small id="providerKeyLink">Dapatkan API key di: <a href="https://console.anthropic.com" target="_blank">console.anthropic.com</a></small>
                            </div>

                            <div class="form-group">
                                <label class="form-label">API Token / Key</label>
                                <input type="password" name="ai_api_token" class="form-input"
                                       value="<?= htmlspecialchars($agent['ai_api_token'] ?? '') ?>"
                                       placeholder="Masukkan API key...">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Model AI</label>
                                <select name="ai_model" class="form-input" id="aiModelSelect">
                                    <optgroup label="Claude (Anthropic)">
                                        <option value="claude-haiku-4-5-20251001" <?= ($agent['ai_model'] ?? '') === 'claude-haiku-4-5-20251001' ? 'selected' : '' ?>>Claude Haiku 4.5 (Cepat & Hemat) ⭐</option>
                                        <option value="claude-sonnet-4-20250514" <?= ($agent['ai_model'] ?? '') === 'claude-sonnet-4-20250514' ? 'selected' : '' ?>>Claude Sonnet 4 (Seimbang)</option>
                                    </optgroup>
                                    <optgroup label="Gemini (Google)">
                                        <option value="gemini-1.5-flash" <?= ($agent['ai_model'] ?? '') === 'gemini-1.5-flash' ? 'selected' : '' ?>>Gemini 1.5 Flash (Cepat) ⭐</option>
                                        <option value="gemini-1.5-pro" <?= ($agent['ai_model'] ?? '') === 'gemini-1.5-pro' ? 'selected' : '' ?>>Gemini 1.5 Pro (Canggih)</option>
                                        <option value="gemini-2.0-flash" <?= ($agent['ai_model'] ?? '') === 'gemini-2.0-flash' ? 'selected' : '' ?>>Gemini 2.0 Flash</option>
                                    </optgroup>
                                    <optgroup label="OpenAI">
                                        <option value="gpt-3.5-turbo" <?= ($agent['ai_model'] ?? '') === 'gpt-3.5-turbo' ? 'selected' : '' ?>>GPT-3.5 Turbo</option>
                                        <option value="gpt-4o-mini" <?= ($agent['ai_model'] ?? '') === 'gpt-4o-mini' ? 'selected' : '' ?>>GPT-4o Mini ⭐</option>
                                        <option value="gpt-4o" <?= ($agent['ai_model'] ?? '') === 'gpt-4o' ? 'selected' : '' ?>>GPT-4o</option>
                                    </optgroup>
                                </select>
                                <small>⭐ = Direkomendasikan. Model lebih canggih = lebih mahal per token.</small>
                            </div>

                            <div class="form-group">
                                <label class="form-label">System Prompt (Instruksi AI)</label>
                                <textarea name="ai_system_prompt" class="form-input" rows="5"
                                    placeholder="Contoh: Kamu adalah asisten customer service PT Maju Bersama. Jawab dengan ramah dan profesional. Fokus hanya pada pertanyaan seputar produk dan layanan kami. Gunakan bahasa Indonesia."><?= htmlspecialchars($agent['ai_system_prompt'] ?? '') ?></textarea>
                                <small>Instruksi peran dan perilaku AI. Semakin detail, semakin baik hasilnya.</small>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Pesan Fallback (jika AI gagal)</label>
                                <input type="text" name="ai_fallback_message" class="form-input"
                                       value="<?= htmlspecialchars($agent['ai_fallback_message'] ?? 'Maaf, saya tidak mengerti pertanyaan Anda. Silakan hubungi agen kami.') ?>"
                                       placeholder="Pesan jika AI tidak merespons...">
                            </div>

                            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                                <button type="submit" class="btn-primary" onclick="document.getElementById('aiForm').querySelector('[name=action]').value='ai_settings'">
                                    <i class="fa-solid fa-floppy-disk"></i> Simpan Pengaturan AI
                                </button>
                                <button type="submit" class="btn-secondary" onclick="document.getElementById('aiForm').querySelector('[name=action]').value='test_ai'; document.getElementById('aiForm').submit(); return false;">
                                    <i class="fa-solid fa-plug-circle-check"></i> Test Koneksi AI
                                </button>
                            </div>
                        </form>

                        <div class="form-divider"></div>

                        <h3 style="font-size:15px;font-weight:700;margin-bottom:8px;">Mode Reply</h3>
                        <p style="font-size:13px;color:var(--text-gray);margin-bottom:12px;">
                            Mode saat ini: <strong style="color:var(--accent-blue);"><?= ucfirst($agent['reply_mode'] ?? 'manual') ?></strong>.
                            Ubah di halaman <a href="agents.php">Agents</a>.
                        </p>
                        <div class="mode-grid">
                            <?php foreach ([
                                'manual'=>['🖐','Manual','Agent balas sendiri'],
                                'bot'   =>['🤖','Bot Module','Keyword rules saja'],
                                'ai'    =>['🧠','AI Only','Hanya pakai AI'],
                                'hybrid'=>['⚡','Hybrid','Module dulu, AI sebagai fallback'],
                            ] as $mode=>[$icon,$label,$desc]): ?>
                            <div class="mode-card <?= ($agent['reply_mode'] ?? 'manual') === $mode ? 'active' : '' ?>">
                                <div class="mode-card-title"><?= $icon ?> <?= $label ?></div>
                                <div class="mode-card-desc"><?= $desc ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Notifications Section -->
                    <div class="settings-section" id="notifications-section">
                        <h2>Notification Preferences</h2>
                        <p>Choose how you want to be notified</p>
                        <div class="toggle-switch">
                            <div class="toggle-info">
                                <h4>New Message Alerts</h4>
                                <p>Get notified when a visitor sends a new message</p>
                            </div>
                            <label class="switch">
                                <input type="checkbox" checked id="notifNewMsg">
                                <span class="slider"></span>
                            </label>
                        </div>
                        <div class="toggle-switch">
                            <div class="toggle-info">
                                <h4>Sound Notifications</h4>
                                <p>Play a sound when new messages arrive</p>
                            </div>
                            <label class="switch">
                                <input type="checkbox" checked id="notifSound">
                                <span class="slider"></span>
                            </label>
                        </div>
                        <div class="toggle-switch">
                            <div class="toggle-info">
                                <h4>Email Notifications</h4>
                                <p>Receive email for offline messages</p>
                            </div>
                            <label class="switch">
                                <input type="checkbox" id="notifEmail">
                                <span class="slider"></span>
                            </label>
                        </div>
                        <div class="toggle-switch">
                            <div class="toggle-info">
                                <h4>Browser Push</h4>
                                <p>Show browser notifications</p>
                            </div>
                            <label class="switch">
                                <input type="checkbox" checked id="notifBrowser">
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>

                    <!-- Security Section -->
                    <div class="settings-section" id="security-section">
                        <h2>Security Settings</h2>
                        <p>Update your password and security preferences</p>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="password">
                            <div class="form-group">
                                <label>Current Password</label>
                                <input type="password" name="current_password" required>
                            </div>
                            <div class="form-group">
                                <label>New Password</label>
                                <input type="password" name="new_password" required>
                            </div>
                            <div class="form-group">
                                <label>Confirm New Password</label>
                                <input type="password" name="confirm_password" required>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" class="btn-primary"><i class="fa-solid fa-key"></i> Update Password</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

<script>
// ==========================================
// PROFILE PAGE SCRIPTS
// ==========================================

// Page Loader
window.addEventListener('load', () => {
    const loader = document.getElementById('pageLoader');
    if (loader) {
        loader.classList.add('fade-out');
        setTimeout(() => loader.remove(), 500);
    }
});

// Toastr Setup
toastr.options = {
    closeButton: true,
    positionClass: "toast-top-right",
    timeOut: "5000",
    progressBar: true
};

// Topbar Avatar Dropdown
const topAvaBtn = document.getElementById('topAvaBtn');
const topAvaDrop = document.getElementById('topAvaDrop');
if (topAvaBtn && topAvaDrop) {
    topAvaBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        topAvaDrop.classList.toggle('show');
    });
    document.addEventListener('click', (e) => {
        if (!topAvaBtn.contains(e.target)) topAvaDrop.classList.remove('show');
    });
}

// Sidebar Notif Dropdown
const notifBellBtn = document.getElementById('notifBellBtn');
const sideNotifDrop = document.getElementById('sideNotifDrop');
if (notifBellBtn && sideNotifDrop) {
    notifBellBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        sideNotifDrop.classList.toggle('show');
    });
    document.addEventListener('click', (e) => {
        if (!notifBellBtn.contains(e.target)) sideNotifDrop.classList.remove('show');
    });
}

// Show Section Function
function showSection(section, link) {
    // Hide all sections
    document.querySelectorAll('.settings-section').forEach(s => s.classList.remove('active'));
    // Remove active class from all nav links
    document.querySelectorAll('.settings-nav a').forEach(a => a.classList.remove('active'));
    // Show selected section
    const targetId = section + '-section';
    const targetSection = document.getElementById(targetId);
    if (targetSection) targetSection.classList.add('active');
    // Add active class to clicked link
    if (link) link.classList.add('active');
    
    // Update URL hash
    window.location.hash = section;
}

// Check URL hash on load
function checkHash() {
    const hash = window.location.hash.slice(1);
    if (hash) {
        const validSections = ['profile', 'ai', 'notifications', 'security'];
        if (validSections.includes(hash)) {
            const link = document.querySelector(`.settings-nav a[onclick*="showSection('${hash}'"]`);
            if (link) showSection(hash, link);
        }
    }
}

// Update AI Provider Link
function updateAiProvider() {
    const p = document.getElementById('aiProviderSelect').value;
    const links = {
        claude: 'Dapatkan API key di: <a href="https://console.anthropic.com" target="_blank">console.anthropic.com</a>',
        gemini: 'Dapatkan API key di: <a href="https://aistudio.google.com/app/apikey" target="_blank">aistudio.google.com</a>',
        openai: 'Dapatkan API key di: <a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com</a>'
    };
    const providerKeyLink = document.getElementById('providerKeyLink');
    if (providerKeyLink) providerKeyLink.innerHTML = links[p] || '';
}

// Save notification preferences to localStorage
function saveNotificationPrefs() {
    const prefs = {
        newMsg: document.getElementById('notifNewMsg')?.checked || false,
        sound: document.getElementById('notifSound')?.checked || false,
        email: document.getElementById('notifEmail')?.checked || false,
        browser: document.getElementById('notifBrowser')?.checked || false
    };
    localStorage.setItem('notificationPrefs', JSON.stringify(prefs));
}

// Load notification preferences from localStorage
function loadNotificationPrefs() {
    const saved = localStorage.getItem('notificationPrefs');
    if (saved) {
        const prefs = JSON.parse(saved);
        if (document.getElementById('notifNewMsg')) document.getElementById('notifNewMsg').checked = prefs.newMsg || false;
        if (document.getElementById('notifSound')) document.getElementById('notifSound').checked = prefs.sound || false;
        if (document.getElementById('notifEmail')) document.getElementById('notifEmail').checked = prefs.email || false;
        if (document.getElementById('notifBrowser')) document.getElementById('notifBrowser').checked = prefs.browser || false;
    }
}

// Add event listeners for notification toggles
document.querySelectorAll('.toggle-switch input').forEach(toggle => {
    toggle.addEventListener('change', saveNotificationPrefs);
});

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    updateAiProvider();
    checkHash();
    loadNotificationPrefs();
});

// Global search functionality
const searchInput = document.getElementById('globalSearch');
if (searchInput) {
    searchInput.addEventListener('keyup', (e) => {
        const searchTerm = e.target.value.toLowerCase();
        // Search through all settings sections
        document.querySelectorAll('.settings-section').forEach(section => {
            const text = section.innerText.toLowerCase();
            if (text.includes(searchTerm) && searchTerm.length > 2) {
                section.style.display = 'block';
            } else if (searchTerm.length > 2) {
                section.style.display = 'none';
            } else {
                // Only show active section when search is cleared
                if (!section.classList.contains('active')) {
                    section.style.display = 'none';
                } else {
                    section.style.display = 'block';
                }
            }
        });
    });
}
</script>

</body>
</html>