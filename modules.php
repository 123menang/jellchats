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
        "INSERT INTO agents (team_id, user_id, display_name, reply_mode) VALUES (?, ?, ?, 'manual')",
        [$teamId, $user['id'], $user['full_name'] ?? $user['username']]
    );
    $agent = $db->fetch("SELECT * FROM agents WHERE id = ?", [$agentId]);
}
$agentId = $agent['id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeInput($_POST['name'] ?? '');
    $triggerType = $_POST['trigger_type'] ?? 'keyword';
    $triggerValue = sanitizeInput($_POST['trigger_value'] ?? '');
    $responseText = sanitizeInput($_POST['response_text'] ?? '');
    $priority = (int)($_POST['priority'] ?? 0);

    if ($name && $triggerValue && $responseText) {
        $db->insert(
            "INSERT INTO chat_modules (agent_id, name, trigger_type, trigger_value, response_text, priority) VALUES (?, ?, ?, ?, ?, ?)",
            [$agentId, $name, $triggerType, $triggerValue, $responseText, $priority]
        );
        header('Location: modules.php?success=1');
        exit;
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $db->delete("DELETE FROM chat_modules WHERE id = ? AND agent_id = ?", [$_GET['delete'], $agentId]);
    header('Location: modules.php');
    exit;
}

// Handle toggle
if (isset($_GET['toggle'])) {
    $module = $db->fetch("SELECT is_active FROM chat_modules WHERE id = ? AND agent_id = ?", [$_GET['toggle'], $agentId]);
    if ($module) {
        $newStatus = $module['is_active'] ? 0 : 1;
        $db->update("UPDATE chat_modules SET is_active = ? WHERE id = ?", [$newStatus, $_GET['toggle']]);
    }
    header('Location: modules.php');
    exit;
}

// Handle online toggle (AJAX / Redirect)
if (isset($_GET['toggle_online'])) {
    $newStatus = $agent['is_online'] ? 0 : 1;
    $db->update("UPDATE agents SET is_online = ? WHERE id = ?", [$newStatus, $agentId]);
    
    // Jika request dari fetch API, jangan redirect
    if(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo json_encode(['status' => 'success', 'is_online' => $newStatus]);
        exit;
    }
    header('Location: modules.php');
    exit;
}

$modules = $db->fetchAll("SELECT * FROM chat_modules WHERE agent_id = ? ORDER BY priority DESC, created_at DESC", [$agentId]);
$agentOnline = (bool)$agent['is_online'];

// Ambil avatar dari user (jika ada kolom avatar di tabel users)
$userAvatar = !empty($user['avatar']) ? htmlspecialchars($user['avatar']) : null;

// Notifikasi dari activity_logs
$notifications = $db->fetchAll("
    SELECT action, entity_type, details, created_at 
    FROM activity_logs 
    WHERE user_id = ? OR user_id IS NULL 
    ORDER BY created_at DESC LIMIT 5
", [$user['id']]);
if (!$notifications) {
    $notifications = [
        ['action' => 'New chat started', 'details' => 'Visitor from Jakarta', 'created_at' => date('Y-m-d H:i:s', strtotime('-5 minutes'))],
        ['action' => 'Module triggered', 'details' => 'Keyword "help" matched', 'created_at' => date('Y-m-d H:i:s', strtotime('-15 minutes'))],
        ['action' => 'Agent status changed', 'details' => 'You are now online', 'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))],
    ];
}
?>
    <?php include 'includes/layout-header.php'; ?>
    <style>
        /* CSS Reset & Variables */
        :root {
            --bg-dark: #09090b;
            --bg-dark-hover: #18181b;
            --bg-light: #ffffff;
            --border-color: #e4e4e7;
            --text-dark: #fafafa;
            --text-light: #09090b;
            --text-muted: #71717a;
            --accent-blue: #2563eb;
            --card-bg: #f4f4f5;
            --danger: #ef4444;
            --success: #10b981;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background-color: var(--bg-dark); color: var(--text-dark); height: 100vh; display: flex; flex-direction: column; overflow: hidden; }

        /* --- PAGE LOADER --- */
        #pageLoader {
            position: fixed;
            inset: 0;
            background-color: #fff;
            z-index: 99999;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: opacity 0.5s ease-out, visibility 0.5s ease-out;
        }
        #pageLoader img { width: 80px; height: 80px; }
        #pageLoader.fade-out { opacity: 0; visibility: hidden; }

        /* --- TOP BAR --- */
        .topbar { height: 56px; display: flex; align-items: center; justify-content: center; padding: 0 20px; background-color: var(--bg-dark); position: relative; flex-shrink: 0; z-index: 10; }
        .search-container { display: flex; align-items: center; background-color: #18181b; border: 1px solid #27272a; border-radius: 8px; padding: 6px 12px; width: 400px; transition: 0.3s; }
        .search-container i { color: #a1a1aa; font-size: 14px; margin-right: 8px; }
        .search-container input { background: transparent; border: none; color: white; outline: none; width: 100%; font-size: 14px; }
        .search-shortcut { background-color: #27272a; color: #a1a1aa; font-size: 11px; padding: 2px 6px; border-radius: 4px; display: flex; gap: 4px; }
        
        .topbar-right { position: absolute; right: 20px; display: flex; align-items: center; gap: 16px; }
        .btn-invite { background: transparent; border: 1px solid #27272a; color: white; padding: 6px 12px; border-radius: 20px; font-size: 13px; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: 0.2s; }
        .btn-invite:hover { background: #18181b; }
        
        .top-avatar { width: 28px; height: 28px; background-color: var(--accent-blue); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; overflow: hidden; color: white; }
        .top-avatar img { width: 100%; height: 100%; object-fit: cover; }

        /* --- MAIN LAYOUT --- */
        .app-body { display: flex; flex: 1; overflow: hidden; position: relative; }

        /* --- SIDEBAR --- */
        .sidebar-left { width: 64px; display: flex; flex-direction: column; align-items: center; padding: 10px 0 20px 0; background-color: var(--bg-dark); z-index: 50; flex-shrink: 0; border-right: 1px solid #27272a; }
        .nav-top, .sidebar-bottom { display: flex; flex-direction: column; gap: 12px; align-items: center; width: 100%; }
        .sidebar-bottom { margin-top: auto; }
        
        .nav-item { width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #a1a1aa; text-decoration: none; font-size: 18px; transition: 0.2s; position: relative; border: none; background: transparent; cursor: pointer; }
        .nav-item:hover { background-color: var(--bg-dark-hover); color: white; }
        .nav-item.active { background-color: #18181b; color: white; }
        .nav-item.active::before { content: ''; position: absolute; left: -12px; top: 50%; transform: translateY(-50%); height: 20px; width: 4px; background-color: var(--accent-blue); border-radius: 0 4px 4px 0; }
        
        .bell-dot { position: absolute; top: 6px; right: 8px; width: 8px; height: 8px; background-color: var(--danger); border-radius: 50%; border: 2px solid var(--bg-dark); }
        
        /* Bottom Avatar on Sidebar */
        .bottom-avatar { width: 34px; height: 34px; background-color: var(--accent-blue); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 600; color: white; position: relative; cursor: pointer; margin-top: 10px; overflow: hidden; }
        .bottom-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .status-dot { position: absolute; bottom: 0; right: 0; width: 10px; height: 10px; background-color: var(--success); border-radius: 50%; border: 2px solid var(--bg-dark); display: none; z-index: 2; }
        .status-dot.online { display: block; }

        /* --- NOTIFICATION DROPDOWN --- */
        .notif-wrapper { position: relative; }
        .notif-dropdown { position: absolute; left: 100%; bottom: 0; margin-left: 12px; background: white; color: var(--text-light); width: 320px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); border: 1px solid var(--border-color); display: none; z-index: 100; overflow: hidden; text-align: left; cursor: default; }
        .notif-dropdown.show { display: block; }
        .notif-header { padding: 14px 16px; font-weight: 600; border-bottom: 1px solid var(--border-color); font-size: 14px; }
        .notif-item { padding: 12px 16px; border-bottom: 1px solid var(--card-bg); display: flex; gap: 12px; }
        .notif-item:hover { background: var(--card-bg); }
        .notif-item:last-child { border-bottom: none; }

        /* --- MAIN WHITE CONTENT AREA --- */
        .main-content-wrapper { flex: 1; background-color: var(--bg-light); border-top-left-radius: 16px; color: var(--text-light); overflow-y: auto; padding: 32px 40px; }
        
        .page-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 32px; flex-wrap: wrap; gap: 16px; }
        .page-header h1 { font-size: 24px; font-weight: 600; margin-bottom: 6px; }
        .page-header p { color: var(--text-muted); font-size: 14px; }
        .btn-primary { background: var(--accent-blue); color: white; border: none; padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: 0.2s; white-space: nowrap; }
        .btn-primary:hover { background: #1d4ed8; }

        .alert { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 12px 16px; border-radius: 8px; margin-bottom: 24px; display: flex; align-items: center; gap: 10px; font-size: 14px; }

        /* MODULES GRID */
        .modules-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 24px; }
        .module-card { background: white; border: 1px solid var(--border-color); border-radius: 12px; padding: 24px; transition: 0.2s; display: flex; flex-direction: column; }
        .module-card:hover { border-color: #a1a1aa; box-shadow: 0 4px 12px rgba(0,0,0,0.03); }
        .module-card.inactive { background: #fafafa; opacity: 0.7; }
        
        .module-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
        .module-icon { width: 44px; height: 44px; background: #eff6ff; color: var(--accent-blue); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .module-actions { display: flex; gap: 8px; }
        .action-btn { background: transparent; border: 1px solid transparent; color: var(--text-muted); width: 32px; height: 32px; border-radius: 6px; display: flex; align-items: center; justify-content: center; cursor: pointer; text-decoration: none; transition: 0.2s; }
        .action-btn:hover { background: var(--card-bg); color: var(--text-light); }
        .action-btn.danger:hover { background: #fef2f2; color: var(--danger); }
        
        .module-title { font-size: 18px; font-weight: 600; margin-bottom: 12px; color: var(--text-light); }
        .module-trigger-wrap { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
        .badge-type { background: var(--card-bg); color: var(--text-muted); padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; }
        .badge-val { background: #eff6ff; color: var(--accent-blue); padding: 4px 10px; border-radius: 6px; font-size: 13px; font-family: monospace; }
        
        .module-response { flex: 1; background: var(--card-bg); padding: 12px 16px; border-radius: 8px; font-size: 13px; color: #52525b; margin-bottom: 20px; border: 1px solid var(--border-color); line-height: 1.5; }
        
        .module-footer { display: flex; justify-content: space-between; border-top: 1px solid var(--border-color); padding-top: 16px; font-size: 12px; color: var(--text-muted); }
        
        /* Empty State */
        .empty-state { grid-column: 1/-1; text-align: center; padding: 64px 20px; background: white; border: 1px dashed var(--border-color); border-radius: 12px; }
        .empty-state i { font-size: 48px; color: #a1a1aa; margin-bottom: 16px; }
        .empty-state h3 { font-size: 18px; font-weight: 600; margin-bottom: 8px; }
        .empty-state p { color: var(--text-muted); margin-bottom: 24px; }

        /* MODAL */
        .modal { display: none; position: fixed; inset: 0; align-items: center; justify-content: center; z-index: 1000; }
        .modal-overlay { position: absolute; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(2px); }
        .modal-content { position: relative; background: white; width: 90%; max-width: 500px; border-radius: 16px; padding: 32px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); max-height: 90vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .modal-header h2 { font-size: 20px; font-weight: 600; }
        .modal-close { background: transparent; border: none; font-size: 20px; color: var(--text-muted); cursor: pointer; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 14px; color: var(--text-light); }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 14px; outline: none; background: white; color: var(--text-light); }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: var(--accent-blue); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }
        .form-row { display: flex; gap: 16px; flex-wrap: wrap; }
        .form-row .form-group { flex: 1; min-width: 150px; }
        .form-hint { font-size: 12px; color: var(--text-muted); margin-top: 6px; display: block; }
        
        .modal-footer { display: flex; justify-content: flex-end; gap: 12px; margin-top: 32px; }
        .btn-secondary { background: white; border: 1px solid var(--border-color); color: var(--text-light); padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; transition: 0.2s; }
        .btn-secondary:hover { background: var(--card-bg); }

        /* =========================================
           RESPONSIVE MOBILE STYLES (BOTTOM NAV)
           ========================================= */
        @media (max-width: 768px) {
            .topbar { padding: 0 15px; justify-content: space-between; }
            .search-container { width: auto; flex: 1; margin-right: 15px; }
            .search-shortcut { display: none; }
            .topbar-right .btn-invite { display: none; } /* Hide invite on mobile to save space */
            
            .app-body { flex-direction: column; }
            
            /* Ubah Sidebar menjadi Bottom Nav */
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
                border-top: 1px solid #27272a;
            }
            .nav-top, .sidebar-bottom {
                flex-direction: row;
                width: auto;
                gap: 5px;
            }
            .sidebar-bottom { margin-top: 0; }
            
            /* Penyesuaian ikon navigasi di Bottom Nav */
            .nav-item { width: 45px; height: 45px; }
            .nav-item.active::before {
                left: 50%;
                top: 0;
                transform: translateX(-50%);
                height: 3px;
                width: 20px;
                border-radius: 0 0 4px 4px;
            }
            .bell-dot { top: 8px; right: 10px; }
            .bottom-avatar { margin-top: 0; margin-left: 5px; }
            
            /* Menyesuaikan area konten agar tidak tertutup Bottom Nav */
            .main-content-wrapper {
                border-radius: 0;
                padding: 20px 20px 85px 20px; /* Padding bawah extra 85px */
            }
            
            /* Dropdown Notifikasi saat mode mobile */
            .notif-dropdown {
                position: fixed;
                left: 10px;
                right: 10px;
                bottom: 75px;
                width: auto;
                margin-left: 0;
                box-shadow: 0 -4px 20px rgba(0,0,0,0.15);
            }
        }
    </style>

<div class="app-body">

        <!-- MAIN WHITE CONTENT WRAPPER -->
        <div class="main-content-wrapper">
            
            <div class="page-header">
                <div>
                    <h1>Chat Modules</h1>
                    <p>Create auto-reply rules based on keywords and triggers</p>
                </div>
                <button class="btn-primary" onclick="openModal()">
                    <i class="fas fa-plus"></i> Add Module
                </button>
            </div>

            <?php if (isset($_GET['success'])): ?>
            <div class="alert">
                <i class="fas fa-check-circle"></i> Module created successfully!
            </div>
            <?php endif; ?>

            <div class="modules-grid" id="modulesGrid">
                <?php foreach ($modules as $module): ?>
                <div class="module-card <?php echo $module['is_active'] ? '' : 'inactive'; ?>" data-name="<?php echo strtolower(htmlspecialchars($module['name'])); ?>" data-trigger="<?php echo strtolower(htmlspecialchars($module['trigger_value'])); ?>">
                    
                    <div class="module-header">
                        <div class="module-icon">
                            <i class="fas fa-<?php echo $module['trigger_type'] === 'regex' ? 'code' : ($module['trigger_type'] === 'exact' ? 'equals' : 'font'); ?>"></i>
                        </div>
                        <div class="module-actions">
                            <a href="?toggle=<?php echo $module['id']; ?>" class="action-btn" title="<?php echo $module['is_active'] ? 'Disable' : 'Enable'; ?>">
                                <i class="fas fa-<?php echo $module['is_active'] ? 'toggle-on' : 'toggle-off'; ?>"></i>
                            </a>
                            <a href="modules-edit.php?id=<?php echo $module['id']; ?>" class="action-btn" title="Edit"><i class="fas fa-pen"></i></a>
                            <a href="?delete=<?php echo $module['id']; ?>" class="action-btn danger" title="Delete" onclick="return confirm('Delete this module?')"><i class="fas fa-trash"></i></a>
                        </div>
                    </div>

                    <h3 class="module-title"><?php echo htmlspecialchars($module['name']); ?></h3>
                    
                    <div class="module-trigger-wrap">
                        <span class="badge-type"><?php echo strtoupper($module['trigger_type']); ?></span>
                        <span class="badge-val"><?php echo htmlspecialchars($module['trigger_value']); ?></span>
                    </div>

                    <div class="module-response">
                        <i class="fas fa-reply" style="color:#a1a1aa; margin-right:6px;"></i>
                        <?php echo nl2br(htmlspecialchars(substr($module['response_text'], 0, 100))); ?><?php echo strlen($module['response_text']) > 100 ? '...' : ''; ?>
                    </div>

                    <div class="module-footer">
                        <span><i class="fas fa-arrow-up-9-1"></i> Priority: <?php echo $module['priority']; ?></span>
                        <span><i class="fas fa-chart-simple"></i> <?php echo $module['match_count']; ?> matches</span>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($modules)): ?>
                <div class="empty-state">
                    <i class="fas fa-robot"></i>
                    <h3>No modules yet</h3>
                    <p>Create your first auto-reply module to automate responses.</p>
                    <button class="btn-primary" style="margin: 0 auto;" onclick="openModal()"><i class="fas fa-plus"></i> Add Module</button>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <!-- MODAL ADD MODULE -->
    <div class="modal" id="addModuleModal">
        <div class="modal-overlay" onclick="closeModal()"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Module</h2>
                <button class="modal-close" onclick="closeModal()"><i class="fas fa-xmark"></i></button>
            </div>
            <form method="POST" action="">
                <div class="form-group">
                    <label>Module Name</label>
                    <input type="text" name="name" placeholder="e.g., Greeting Response" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Trigger Type</label>
                        <select name="trigger_type" required>
                            <option value="keyword">Keyword (contains)</option>
                            <option value="exact">Exact Match</option>
                            <option value="regex">Regex Pattern</option>
                            <option value="starts_with">Starts With</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Priority</label>
                        <input type="number" name="priority" value="0" min="0" max="100">
                    </div>
                </div>
                <div class="form-group">
                    <label>Trigger Value</label>
                    <input type="text" name="trigger_value" placeholder="e.g., hello, hi, hey" required>
                    <span class="form-hint">Separate multiple keywords with commas</span>
                </div>
                <div class="form-group">
                    <label>Response Text</label>
                    <textarea name="response_text" rows="4" placeholder="Enter the auto-reply message..." required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-primary">Create Module</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // 0. Page Loader Handler
        window.addEventListener('load', () => {
            const loader = document.getElementById('pageLoader');
            if (loader) {
                loader.classList.add('fade-out');
                // Hapus element sepenuhnya setelah animasi transisi CSS (0.5s) selesai
                setTimeout(() => {
                    loader.style.display = 'none';
                }, 500);
            }
        });

        // 1. Search Filtering
        const searchInput = document.getElementById('globalSearchInput');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const query = this.value.toLowerCase();
                const cards = document.querySelectorAll('.module-card');
                cards.forEach(card => {
                    const name = card.getAttribute('data-name') || '';
                    const trigger = card.getAttribute('data-trigger') || '';
                    card.style.display = (name.includes(query) || trigger.includes(query)) ? '' : 'none';
                });
            });
            
            // Shortcut Ctrl K
            document.addEventListener('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                    e.preventDefault();
                    searchInput.focus();
                }
            });
        }

        // 2. Notifications Dropdown Handler (from Sidebar Bell)
        const notifBtn = document.getElementById('sidebarNotifBtn');
        const notifDropdown = document.getElementById('notificationDropdown');
        
        if (notifBtn && notifDropdown) {
            notifBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                notifDropdown.classList.toggle('show');
            });
            document.addEventListener('click', (e) => {
                if (!notifBtn.contains(e.target) && !notifDropdown.contains(e.target)) {
                    notifDropdown.classList.remove('show');
                }
            });
        }

        // 3. Online Toggle via Bottom Sidebar Avatar
        const onlineToggleBtn = document.getElementById('onlineToggleBtn');
        const statusDot = document.getElementById('statusDot');
        
        if (onlineToggleBtn) {
            onlineToggleBtn.addEventListener('click', () => {
                fetch(window.location.pathname + '?toggle_online=1', { 
                    method: 'GET',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(response => response.json())
                .then(data => {
                    if(data.status === 'success') {
                        if(data.is_online) {
                            statusDot.classList.add('online');
                        } else {
                            statusDot.classList.remove('online');
                        }
                    }
                })
                .catch(err => {
                    // Fallback
                    window.location.href = window.location.pathname + '?toggle_online=1';
                });
            });
        }

        // 4. Modal Helpers
        function openModal() { document.getElementById('addModuleModal').style.display = 'flex'; }
        function closeModal() { document.getElementById('addModuleModal').style.display = 'none'; }
        
        document.addEventListener('keydown', (e) => { 
            if (e.key === 'Escape') closeModal(); 
        });
    </script>
</body>
</html>