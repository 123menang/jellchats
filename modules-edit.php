<?php
/**
 * Module Editor - modules-edit.php
 */
require_once 'includes/auth.php';
require_once 'includes/functions.php';
$auth->requireAuth();
$user = $auth->getCurrentUser();
$db = Database::getInstance();

// Ensure agent exists
$agent = $db->fetch("SELECT * FROM agents WHERE user_id = ?", [$user['id']]);
if (!$agent) {
    header('Location: modules.php');
    exit;
}
$agentId = $agent['id'];

$moduleId = (int)($_GET['id'] ?? 0);
$module = null;
if ($moduleId) {
    $module = $db->fetch("SELECT * FROM chat_modules WHERE id = ? AND agent_id = ?", [$moduleId, $agentId]);
}
if (!$module) {
    header('Location: modules.php');
    exit;
}

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name         = sanitizeInput($_POST['name'] ?? '');
    $triggerType  = $_POST['trigger_type'] ?? 'keyword';
    $triggerValue = sanitizeInput($_POST['trigger_value'] ?? '');
    $responseText = sanitizeInput($_POST['response_text'] ?? '');
    $priority     = (int)($_POST['priority'] ?? 0);
    $isActive     = isset($_POST['is_active']) ? 1 : 0;

    if (!$name || !$triggerValue || !$responseText) {
        $error = 'Semua field bertanda * wajib diisi.';
    } elseif ($triggerType === 'regex' && @preg_match($triggerValue, '') === false) {
        $error = 'Format regex tidak valid.';
    } else {
        $db->update(
            "UPDATE chat_modules SET name = ?, trigger_type = ?, trigger_value = ?, response_text = ?, priority = ?, is_active = ? WHERE id = ? AND agent_id = ?",[$name, $triggerType, $triggerValue, $responseText, $priority, $isActive, $moduleId, $agentId]
        );
        header('Location: modules.php?updated=1');
        exit;
    }
}

// Data tambahan untuk konsistensi Header/Sidebar
$agentOnline = (bool)$agent['is_online'];
$userAvatar = !empty($user['avatar']) ? htmlspecialchars($user['avatar']) : null;
$notifications = $db->fetchAll("
    SELECT action, entity_type, details, created_at 
    FROM activity_logs 
    WHERE user_id = ? OR user_id IS NULL 
    ORDER BY created_at DESC LIMIT 5
", [$user['id']]);
if (!$notifications) {
    $notifications = [['action' => 'New chat started', 'details' => 'Visitor from Jakarta', 'created_at' => date('Y-m-d H:i:s', strtotime('-5 minutes'))],['action' => 'Module triggered', 'details' => 'Keyword "help" matched', 'created_at' => date('Y-m-d H:i:s', strtotime('-15 minutes'))],
    ];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Edit Module - LiveChat Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* CSS Reset & Variables (Sama dengan modules.php) */
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
            position: fixed; inset: 0; background-color: var(--bg-dark); z-index: 99999;
            display: flex; align-items: center; justify-content: center;
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
        
        .back-link { display: inline-flex; align-items: center; gap: 8px; color: var(--text-muted); text-decoration: none; font-size: 14px; margin-bottom: 24px; transition: 0.2s; font-weight: 500; }
        .back-link:hover { color: var(--text-light); }
        
        .page-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 32px; flex-wrap: wrap; gap: 16px; }
        .page-header h1 { font-size: 24px; font-weight: 600; margin-bottom: 6px; display: flex; align-items: center; gap: 12px; }
        .page-header p { color: var(--text-muted); font-size: 14px; }
        
        .badge-count { background: #eff6ff; color: var(--accent-blue); font-size: 12px; font-weight: 600; padding: 4px 10px; border-radius: 20px; margin-left: 8px; vertical-align: middle; }
        .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 24px; display: flex; align-items: center; gap: 10px; font-size: 14px; }

        /* --- EDIT FORM CARDS --- */
        .edit-container { max-width: 680px; }
        .form-card { background: white; border: 1px solid var(--border-color); border-radius: 12px; padding: 28px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
        .form-card h2 { font-size: 18px; font-weight: 600; margin-bottom: 24px; display: flex; align-items: center; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 14px; color: var(--text-light); }
        .form-group input[type="text"], .form-group input[type="number"], .form-group select, .form-group textarea { 
            width: 100%; padding: 12px 14px; border: 1px solid var(--border-color); border-radius: 8px; 
            font-size: 14px; outline: none; background: white; color: var(--text-light); font-family: inherit; transition: 0.2s;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: var(--accent-blue); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }
        
        .help-text { font-size: 12px; color: var(--text-muted); margin-top: 6px; display: block; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        
        /* Buttons */
        .btn-primary { background: var(--accent-blue); color: white; border: none; padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: 0.2s; text-decoration: none; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-secondary { background: white; border: 1px solid var(--border-color); color: var(--text-light); padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; transition: 0.2s; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .btn-secondary:hover { background: var(--card-bg); }

        /* Switch Toggle Status */
        .status-toggle-wrap { display: flex; align-items: center; gap: 10px; margin-top: 8px; cursor: pointer; user-select: none; }
        .status-toggle-wrap input { width: 18px; height: 18px; accent-color: var(--accent-blue); cursor: pointer; }

        /* =========================================
           RESPONSIVE MOBILE STYLES (BOTTOM NAV)
           ========================================= */
        @media (max-width: 768px) {
            .topbar { padding: 0 15px; justify-content: space-between; }
            .search-container { width: auto; flex: 1; margin-right: 15px; }
            .search-shortcut { display: none; }
            .topbar-right .btn-invite { display: none; }
            
            .app-body { flex-direction: column; }
            
            .sidebar-left { position: fixed; bottom: 0; left: 0; right: 0; width: 100%; height: 65px; flex-direction: row; justify-content: space-around; align-items: center; padding: 0 10px; border-right: none; border-top: 1px solid #27272a; }
            .nav-top, .sidebar-bottom { flex-direction: row; width: auto; gap: 5px; }
            .sidebar-bottom { margin-top: 0; }
            
            .nav-item { width: 45px; height: 45px; }
            .nav-item.active::before { left: 50%; top: 0; transform: translateX(-50%); height: 3px; width: 20px; border-radius: 0 0 4px 4px; }
            .bell-dot { top: 8px; right: 10px; }
            .bottom-avatar { margin-top: 0; margin-left: 5px; }
            
            .main-content-wrapper { border-radius: 0; padding: 20px 20px 85px 20px; }
            .notif-dropdown { position: fixed; left: 10px; right: 10px; bottom: 75px; width: auto; margin-left: 0; box-shadow: 0 -4px 20px rgba(0,0,0,0.15); }
            
            .form-row { grid-template-columns: 1fr; gap: 0; }
        }
    </style>
</head>
<body>

    <!-- LOADING SCREEN -->
    <div id="pageLoader">
        <img src="assets/images/logo-animated.webp" alt="Loading...">
    </div>

    <!-- TOP BAR -->
    <div class="topbar">
        <div class="search-container">
            <i class="fas fa-search"></i>
            <input type="text" id="globalSearchInput" placeholder="Search modules..." disabled title="Search disabled in edit mode">
            <div class="search-shortcut">
                <span>Ctrl</span><span>K</span>
            </div>
        </div>
        
        <div class="topbar-right">
            <div class="top-avatar">
                <?php if ($userAvatar): ?>
                    <img src="<?php echo $userAvatar; ?>" alt="avatar">
                <?php else: ?>
                    <?php echo strtoupper(substr($user['full_name'] ?? $user['username'], 0, 1)); ?>
                <?php endif; ?>
            </div>
            <button class="btn-invite"><i class="fas fa-plus"></i> Invite</button>
        </div>
    </div>

    <!-- MAIN APP BODY -->
    <div class="app-body">
        
        <!-- SIDEBAR / BOTTOM NAV (On Mobile) -->
        <div class="sidebar-left">
            <div class="nav-top">
                <a href="index.php" class="nav-item"><i class="fas fa-comment-dots"></i></a>
                <a href="modules.php" class="nav-item active"><i class="fas fa-robot"></i></a>
                <a href="agents.php" class="nav-item hide-mobile"><i class="fas fa-users"></i></a>
                <a href="analytics.php" class="nav-item hide-mobile"><i class="fas fa-chart-line"></i></a>
            </div>
            
            <div class="sidebar-bottom">
                <a href="settings.php" class="nav-item"><i class="fas fa-gear"></i></a>
                
                <!-- Notification Bell -->
                <div class="notif-wrapper">
                    <button class="nav-item" id="sidebarNotifBtn">
                        <i class="fas fa-bell"></i>
                        <?php if(count($notifications) > 0): ?>
                            <div class="bell-dot"></div>
                        <?php endif; ?>
                    </button>
                    
                    <div class="notif-dropdown" id="notificationDropdown">
                        <div class="notif-header">Notifications</div>
                        <div class="notif-body">
                            <?php foreach ($notifications as $notif): ?>
                            <div class="notif-item">
                                <div style="color: var(--accent-blue); padding-top: 2px;"><i class="fas fa-circle-info"></i></div>
                                <div>
                                    <div style="font-size: 13px; font-weight: 500;"><?php echo htmlspecialchars($notif['action']); ?></div>
                                    <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;"><?php echo htmlspecialchars($notif['details']); ?></div>
                                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 6px;"><?php echo timeAgo(strtotime($notif['created_at'])); ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Avatar Status -->
                <div class="bottom-avatar" id="onlineToggleBtn" title="Click to toggle online status">
                    <?php if ($userAvatar): ?>
                        <img src="<?php echo $userAvatar; ?>" alt="avatar">
                    <?php else: ?>
                        <?php echo strtoupper(substr($user['full_name'] ?? $user['username'], 0, 1)); ?>
                    <?php endif; ?>
                    <div class="status-dot <?php echo $agentOnline ? 'online' : ''; ?>" id="statusDot"></div>
                </div>
            </div>
        </div>

        <!-- MAIN CONTENT -->
        <div class="main-content-wrapper">
            
            <a href="modules.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Kembali ke Modules
            </a>

            <div class="page-header">
                <div>
                    <h1>Edit Module <span class="badge-count"><?php echo $module['match_count']; ?> matches</span></h1>
                    <p>Ubah aturan auto-reply <strong><?php echo htmlspecialchars($module['name']); ?></strong></p>
                </div>
            </div>

            <?php if ($error): ?>
            <div class="alert-error">
                <i class="fas fa-circle-exclamation"></i> <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <div class="edit-container">
                
                <!-- FORM CARD -->
                <form method="POST" class="form-card">
                    <h2><i class="fas fa-pen-to-square" style="color:var(--accent-blue);margin-right:10px;"></i> Detail Module</h2>

                    <div class="form-group">
                        <label>Nama Module <span style="color:var(--danger)">*</span></label>
                        <input type="text" name="name" required value="<?php echo htmlspecialchars($module['name']); ?>" placeholder="cth: Harga Produk">
                    </div>

                    <div class="form-group">
                        <label>Tipe Trigger <span style="color:var(--danger)">*</span></label>
                        <select name="trigger_type" id="triggerTypeSelect">
                            <?php foreach (['keyword'=>'Keyword (contains)','exact'=>'Exact Match','starts_with'=>'Starts With','regex'=>'Regex'] as $val => $label): ?>
                            <option value="<?php echo $val; ?>" <?php echo $module['trigger_type'] === $val ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="help-text" id="triggerHelp"></span>
                    </div>

                    <div class="form-group">
                        <label>Nilai Trigger <span style="color:var(--danger)">*</span></label>
                        <input type="text" name="trigger_value" required value="<?php echo htmlspecialchars($module['trigger_value']); ?>" id="triggerValueInput" placeholder="cth: harga, berapa harga, price">
                        <span class="help-text">Untuk keyword: pisahkan dengan koma (harga, price, cost)</span>
                    </div>

                    <div class="form-group">
                        <label>Teks Respons <span style="color:var(--danger)">*</span></label>
                        <textarea name="response_text" rows="5" required placeholder="Tulis respons yang akan dikirim ke user..."><?php echo htmlspecialchars($module['response_text']); ?></textarea>
                        <span class="help-text">Gunakan Shift+Enter untuk baris baru dalam respons.</span>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Prioritas</label>
                            <input type="number" name="priority" min="0" max="100" value="<?php echo $module['priority']; ?>">
                            <span class="help-text">Angka lebih tinggi = diproses lebih dulu (0-100)</span>
                        </div>
                        <div class="form-group">
                            <label>Status Aktif</label>
                            <label class="status-toggle-wrap">
                                <input type="checkbox" name="is_active" <?php echo $module['is_active'] ? 'checked' : ''; ?>>
                                <span style="font-size:14px; font-weight:500;">Aktifkan module ini</span>
                            </label>
                        </div>
                    </div>

                    <div style="display:flex;gap:12px;margin-top:16px;">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-floppy-disk"></i> Simpan Perubahan
                        </button>
                        <a href="modules.php" class="btn-secondary">
                            Batal
                        </a>
                    </div>
                </form>

                <!-- TEST MODULE CARD -->
                <div class="form-card">
                    <h2><i class="fas fa-flask" style="color:var(--success);margin-right:10px;"></i> Test Module</h2>
                    <p style="font-size:14px;color:var(--text-muted);margin-bottom:20px;">
                        Simulasikan kecocokan teks user dengan pengaturan trigger module ini.
                    </p>
                    
                    <div style="display:flex;gap:12px;">
                        <input type="text" id="testInput" placeholder="Ketik simulasi pesan pengunjung..." style="flex:1; padding: 12px 14px; border: 1px solid var(--border-color); border-radius: 8px; outline: none;">
                        <button type="button" onclick="testModule()" class="btn-primary" style="white-space:nowrap; background-color: var(--text-light);">
                            <i class="fas fa-play"></i> Uji
                        </button>
                    </div>
                    
                    <div id="testResult" style="display:none; margin-top:20px; padding:16px; border-radius:8px; font-size:14px; line-height:1.5;"></div>
                </div>

            </div>
        </div>
    </div>

    <script>
        // 0. Page Loader Handler
        window.addEventListener('load', () => {
            const loader = document.getElementById('pageLoader');
            if (loader) {
                loader.classList.add('fade-out');
                setTimeout(() => { loader.style.display = 'none'; }, 500);
            }
        });

        // 1. Notification Toggle
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

        // 2. Online Toggle (AJAX Simulation for UI)
        const onlineToggleBtn = document.getElementById('onlineToggleBtn');
        const statusDot = document.getElementById('statusDot');
        if (onlineToggleBtn) {
            onlineToggleBtn.addEventListener('click', () => {
                fetch('modules.php?toggle_online=1', { headers: { 'X-Requested-With': 'XMLHttpRequest' }})
                .then(r => r.json())
                .then(d => { if(d.status === 'success') statusDot.classList.toggle('online', d.is_online); })
                .catch(e => console.error('Error toggling status', e));
            });
        }

        // 3. Edit Form Help Text Dynamic Update
        const triggerHelps = {
            keyword: 'Cocok jika pesan MENGANDUNG salah satu keyword. Pisahkan dengan koma.',
            exact: 'Cocok jika pesan SAMA PERSIS dengan trigger (case-insensitive).',
            starts_with: 'Cocok jika pesan DIAWALI dengan trigger.',
            regex: 'Cocok berdasarkan pola regular expression. Contoh: /harga|price/i'
        };
        const sel = document.getElementById('triggerTypeSelect');
        const help = document.getElementById('triggerHelp');
        function updateHelp() { help.textContent = triggerHelps[sel.value] || ''; }
        sel.onchange = updateHelp;
        updateHelp();

        // 4. Test Module Simulator Logic
        function testModule() {
            const msg = document.getElementById('testInput').value.trim();
            if (!msg) return;
            
            const trigger = <?php echo json_encode($module['trigger_value']); ?>;
            const type    = <?php echo json_encode($module['trigger_type']); ?>;
            const response = <?php echo json_encode($module['response_text']); ?>;
            const result  = document.getElementById('testResult');

            let match = false;
            const msgLower = msg.toLowerCase();
            const trigLower = trigger.toLowerCase();

            switch (type) {
                case 'keyword':
                    match = trigLower.split(',').map(k => k.trim()).some(k => k && msgLower.includes(k));
                    break;
                case 'exact':
                    match = msgLower === trigLower;
                    break;
                case 'starts_with':
                    match = msgLower.startsWith(trigLower);
                    break;
                case 'regex':
                    try { match = new RegExp(trigger, 'i').test(msg); } catch(e) { match = false; }
                    break;
            }

            result.style.display = 'block';
            if (match) {
                result.style.background = '#ecfdf5'; // Light green
                result.style.color = '#065f46';
                result.style.border = '1px solid #a7f3d0';
                result.innerHTML = `<strong><i class="fas fa-check-circle"></i> MATCH!</strong><br><br>Respons yang akan dikirim:<br><em style="white-space:pre-wrap; display:block; margin-top:8px; padding-left:10px; border-left:3px solid #10b981;">${escHtml(response)}</em>`;
            } else {
                result.style.background = '#fef2f2'; // Light red
                result.style.color = '#991b1b';
                result.style.border = '1px solid #fecaca';
                result.innerHTML = `<strong><i class="fas fa-xmark-circle"></i> Tidak cocok</strong><br>Pesan ini tidak akan memicu module ini berdasarkan aturan saat ini.`;
            }
        }
        function escHtml(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
    </script>
</body>
</html>