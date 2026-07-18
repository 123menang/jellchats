<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
$auth->requireAuth();
$user = $auth->getCurrentUser();
$db = Database::getInstance();

// Hanya admin/owner yang bisa mengedit pengaturan agen
if ($user['role'] === 'agent') {
    header('Location: /');
    exit;
}

$agentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$success = false;
$error = '';

$agentData = $db->fetch("
    SELECT a.*, u.username, u.email, u.full_name, u.avatar as user_avatar, u.license_tier, u.created_at as user_joined
    FROM agents a
    JOIN users u ON a.user_id = u.id
    WHERE a.id = ?
", [$agentId]);

if (!$agentData) {
    die("Agent not found.");
}

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = sanitizeInput($_POST['full_name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $displayName = sanitizeInput($_POST['display_name'] ?? '');
    $teamId = (int)($_POST['team_id'] ?? 0);
    $replyMode = $_POST['reply_mode'] ?? 'manual';
    
    $avatarUrl = $agentData['user_avatar']; 
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/avatars/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        $fileName = 'agent_' . $agentData['user_id'] . '_' . time() . '.' . $ext;
        $targetPath = $uploadDir . $fileName;

        if (in_array(strtolower($ext),['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $targetPath)) {
                $avatarUrl = $targetPath;
            }
        }
    }

    if (empty($fullName) || empty($email) || empty($displayName)) {
        $error = 'Full Name, Email, and Display Name are required.';
    } else {
        try {
            $db->update("UPDATE users SET full_name = ?, email = ?, avatar = ? WHERE id = ?",[$fullName, $email, $avatarUrl, $agentData['user_id']]);
            $db->update("UPDATE agents SET display_name = ?, team_id = ?, reply_mode = ? WHERE id = ?",[$displayName, $teamId, $replyMode, $agentId]);
            $db->insert("INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)", [$user['id'], 'Updated Agent Profile', 'agent', $agentId, 'Updated profile for agent: ' . $displayName, $_SERVER['REMOTE_ADDR'] ?? '']);

            $success = true;
            $agentData = $db->fetch("SELECT a.*, u.username, u.email, u.full_name, u.avatar as user_avatar, u.license_tier, u.created_at as user_joined FROM agents a JOIN users u ON a.user_id = u.id WHERE a.id = ?", [$agentId]);
        } catch (Exception $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

$teams = $db->fetchAll("SELECT * FROM teams WHERE user_id = ? ORDER BY name", [$user['id']]);

$agentOnline = $agentData['is_online'] ? true : false;
$userAvatar = !empty($user['avatar']) ? htmlspecialchars($user['avatar']) : null;
$notifications = $db->fetchAll("SELECT action, entity_type, details, created_at FROM activity_logs WHERE user_id = ? OR user_id IS NULL ORDER BY created_at DESC LIMIT 5", [$user['id']]);
if (!$notifications) {
    $notifications = [['action' => 'New chat started', 'details' => 'Visitor from Jakarta', 'created_at' => date('Y-m-d H:i:s', strtotime('-5 minutes'))]];
}

// Menghitung Total Agent Online (Tim yang sama / Seluruhnya jika Admin)
$currentUserAgent = $db->fetch("SELECT team_id FROM agents WHERE user_id = ?", [$user['id']]);
$currentUserTeamId = $currentUserAgent ? $currentUserAgent['team_id'] : -1;
if ($user['role'] === 'agent') {
    $onlineAgentsCount = $db->fetch("SELECT COUNT(*) as count FROM agents WHERE team_id = ? AND is_online = 1", [$currentUserTeamId])['count'] ?? 0;
} else {
    $onlineAgentsCount = $db->fetch("SELECT COUNT(*) as count FROM agents WHERE is_online = 1")['count'] ?? 0;
}
?>
    <?php include 'includes/layout-header.php'; ?>

    <style>
        /* CSS Reset & Variables */
        :root {
            --bg-dark: #09090b; --bg-dark-hover: #18181b; --bg-light: #ffffff;
            --border-color: #e4e4e7; --text-dark: #fafafa; --text-light: #09090b;
            --text-muted: #71717a; --accent-blue: #2563eb; --card-bg: #f4f4f5;
            --danger: #ef4444; --success: #10b981;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background-color: var(--bg-dark); color: var(--text-dark); height: 100vh; display: flex; flex-direction: column; overflow: hidden; }

        #pageLoader { position: fixed; inset: 0; background-color: var(--bg-dark); z-index: 99999; display: flex; align-items: center; justify-content: center; transition: opacity 0.5s ease-out, visibility 0.5s ease-out; }
        #pageLoader img { width: 80px; height: 80px; }
        #pageLoader.fade-out { opacity: 0; visibility: hidden; }

        .topbar { height: 56px; display: flex; align-items: center; justify-content: center; padding: 0 20px; background-color: var(--bg-dark); position: relative; flex-shrink: 0; z-index: 10; border-bottom: 1px solid #27272a; }
        .search-container { display: flex; align-items: center; background-color: #18181b; border: 1px solid #27272a; border-radius: 8px; padding: 6px 12px; width: 400px; }
        .search-container i { color: #a1a1aa; font-size: 14px; margin-right: 8px; }
        .search-container input { background: transparent; border: none; color: white; outline: none; width: 100%; font-size: 14px; }
        .search-shortcut { background-color: #27272a; color: #a1a1aa; font-size: 11px; padding: 2px 6px; border-radius: 4px; display: flex; gap: 4px; }
        .topbar-right { position: absolute; right: 20px; display: flex; align-items: center; gap: 16px; }
        
        /* Online Agent Badge in Topbar */
        .online-badge { display: flex; align-items: center; gap: 8px; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: var(--success); padding: 6px 12px; border-radius: 20px; font-size: 13px; font-weight: 500; }
        .pulse-dot { width: 8px; height: 8px; background: var(--success); border-radius: 50%; box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); animation: pulse 2s infinite; }
        @keyframes pulse { 0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); } 70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); } 100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); } }

        /* Top Avatar Dropdown */
        .top-avatar-wrap { position: relative; cursor: pointer; }
        .top-avatar { width: 28px; height: 28px; background-color: var(--accent-blue); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; overflow: hidden; color: white; border: 1px solid var(--border-color); }
        .top-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .avatar-dropdown { position: absolute; right: 0; top: 100%; margin-top: 10px; background: white; border: 1px solid var(--border-color); border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); width: 160px; display: none; flex-direction: column; z-index: 100; overflow: hidden;}
        .avatar-dropdown.show { display: flex; }
        .avatar-dropdown a { padding: 12px 16px; color: var(--text-light); text-decoration: none; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 10px; transition: 0.2s; }
        .avatar-dropdown a:hover { background: var(--card-bg); }
        .avatar-dropdown .text-danger { color: var(--danger); }
        .dropdown-divider { height: 1px; background: var(--border-color); margin: 0; }

        .app-body { display: flex; flex: 1; overflow: hidden; position: relative; }

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

        .notif-wrapper { position: relative; }
        .notif-dropdown { position: absolute; left: 100%; bottom: 0; margin-left: 12px; background: white; color: var(--text-light); width: 320px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); border: 1px solid var(--border-color); display: none; z-index: 100; overflow: hidden; text-align: left; cursor: default; }
        .notif-dropdown.show { display: block; }
        .notif-header { padding: 14px 16px; font-weight: 600; border-bottom: 1px solid var(--border-color); font-size: 14px; }
        .notif-item { padding: 12px 16px; border-bottom: 1px solid var(--card-bg); display: flex; gap: 12px; }
        .notif-item:hover { background: var(--card-bg); }

        .main-content-wrapper { flex: 1; background-color: var(--bg-light); border-top-left-radius: 16px; color: var(--text-light); overflow-y: auto; padding: 32px 40px; }
        
        .back-link { display: inline-flex; align-items: center; gap: 8px; color: var(--text-muted); text-decoration: none; font-size: 14px; margin-bottom: 24px; transition: 0.2s; font-weight: 500; }
        .back-link:hover { color: var(--text-light); }
        
        .page-header { text-align: center; margin-bottom: 32px; }
        .page-header h1 { font-size: 24px; font-weight: 600; margin-bottom: 6px; }
        .page-header p { color: var(--text-muted); font-size: 14px; }
        
        .alert-success { max-width: 680px; margin: 0 auto 24px; background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 12px 16px; border-radius: 8px; display: flex; align-items: center; gap: 10px; font-size: 14px; }
        .alert-error { max-width: 680px; margin: 0 auto 24px; background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 12px 16px; border-radius: 8px; display: flex; align-items: center; gap: 10px; font-size: 14px; }

        /* =====================================
           CENTERED LAYOUT FOR EDIT AGENT
           ===================================== */
        .edit-container { max-width: 680px; margin: 0 auto; }
        
        .form-card { background: white; border: 1px solid var(--border-color); border-radius: 16px; padding: 32px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }

        .form-header-box { display: flex; flex-direction: column; align-items: center; text-align: center; gap: 16px; margin-bottom: 32px; padding-bottom: 24px; border-bottom: 1px solid var(--border-color); }
        .avatar-uploader-wrap { position: relative; width: 90px; height: 90px; flex-shrink: 0; margin: 0 auto; }
        .agent-avatar-preview { width: 90px; height: 90px; border-radius: 50%; background: linear-gradient(135deg, var(--accent-blue), #8b5cf6); color: white; display: flex; align-items: center; justify-content: center; font-size: 32px; font-weight: 700; overflow: hidden; border: 3px solid var(--card-bg); box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .agent-avatar-preview img { width: 100%; height: 100%; object-fit: cover; }
        .avatar-upload-btn { position: absolute; bottom: 0; right: 0; width: 32px; height: 32px; background: white; border-radius: 50%; border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center; font-size: 14px; cursor: pointer; color: var(--text-muted); transition: 0.2s; box-shadow: 0 2px 6px rgba(0,0,0,0.1); }
        .avatar-upload-btn:hover { color: var(--accent-blue); transform: scale(1.1); }
        
        .form-header-box h2 { font-size: 22px; font-weight: 600; margin: 0 0 4px 0; color: var(--text-light); }
        .form-header-box p { margin: 0; color: var(--text-muted); font-size: 14px; }

        .section-title { font-size: 13px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin: 24px 0 16px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 14px; color: var(--text-light); }
        .form-group input, .form-group select { width: 100%; padding: 12px 14px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 14px; outline: none; background: white; color: var(--text-light); font-family: inherit; transition: 0.2s; }
        .form-group input:focus, .form-group select:focus { border-color: var(--accent-blue); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }
        .form-group input:disabled { background: var(--card-bg); color: var(--text-muted); cursor: not-allowed; }
        .help-text { font-size: 12px; color: var(--text-muted); margin-top: 6px; display: block; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        
        .btn-primary { background: var(--accent-blue); color: white; border: none; padding: 12px 24px; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 8px; transition: 0.2s; text-decoration: none; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-secondary { background: white; border: 1px solid var(--border-color); color: var(--text-light); padding: 12px 24px; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; transition: 0.2s; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-secondary:hover { background: var(--card-bg); }

        /* =========================================
           RESPONSIVE MOBILE STYLES (BOTTOM NAV)
           ========================================= */
        @media (max-width: 768px) {
            .topbar { padding: 0 15px; justify-content: space-between; }
            .search-container { width: auto; flex: 1; margin-right: 15px; }
            .search-shortcut { display: none; }
            
            .app-body { flex-direction: column; }
            
            /* Konfigurasi eksklusif 5 menu di Mobile */
            .sidebar-left { position: fixed; bottom: 0; left: 0; right: 0; width: 100%; height: 65px; display: flex; flex-direction: row; justify-content: space-around; align-items: center; padding: 0; border-right: none; border-top: 1px solid #27272a; }
            .nav-top, .sidebar-bottom { display: contents; }
            
            /* Sembunyikan elemen sidebar yang bukan menu utama di mobile */
            .hide-mobile { display: none !important; }

            .nav-item { width: 45px; height: 45px; }
            .nav-item.active::before { left: 50%; top: 0; transform: translateX(-50%); height: 3px; width: 20px; border-radius: 0 0 4px 4px; }
            
            .main-content-wrapper { border-radius: 0; padding: 20px 20px 85px 20px; }
            .notif-dropdown { position: fixed; left: 10px; right: 10px; bottom: 75px; width: auto; margin-left: 0; box-shadow: 0 -4px 20px rgba(0,0,0,0.15); }
            .form-row { grid-template-columns: 1fr; gap: 0; }
        }
    </style>


    <!-- MAIN APP BODY -->
    <div class="app-body">
            <?php include 'includes/layout-sidebar.php'; ?>

        <div class="main-content-wrapper">
            
            <a href="agents.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Kembali ke Agents
            </a>

            <div class="page-header">
                <h1>Edit Agent</h1>
                <p>Ubah profil dan pengaturan agent di sini</p>
            </div>

            <?php if ($success): ?>
            <div class="alert-success">
                <i class="fas fa-check-circle"></i> Data agent berhasil diperbarui!
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert-error">
                <i class="fas fa-circle-exclamation"></i> <?php echo $error; ?>
            </div>
            <?php endif; ?>

            <div class="edit-container">
                <form method="POST" action="" class="form-card" enctype="multipart/form-data">
                    
                    <div class="form-header-box">
                        <div class="avatar-uploader-wrap">
                            <div class="agent-avatar-preview" id="agent-avatar-preview">
                                <?php if (!empty($agentData['user_avatar'])): ?>
                                    <img src="<?php echo htmlspecialchars($agentData['user_avatar']); ?>" alt="Agent">
                                <?php else: ?>
                                    <?php echo strtoupper(substr($agentData['display_name'] ?: 'A', 0, 1)); ?>
                                <?php endif; ?>
                            </div>
                            <label for="agent-avatar-input" class="avatar-upload-btn" title="Ubah Avatar Agen">
                                <i class="fas fa-camera"></i>
                            </label>
                            <input type="file" name="avatar" id="agent-avatar-input" style="display:none;" accept="image/png, image/jpeg, image/webp">
                        </div>
                        <div>
                            <h2><?php echo htmlspecialchars($agentData['display_name']); ?></h2>
                            <p>Tergabung sejak: <?php echo date('d M Y', strtotime($agentData['user_joined'])); ?></p>
                        </div>
                    </div>

                    <div class="section-title">Informasi Akun (Login)</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Username (System ID)</label>
                            <input type="text" value="<?php echo htmlspecialchars($agentData['username']); ?>" disabled>
                            <span class="help-text">Username tidak dapat diubah setelah dibuat.</span>
                        </div>
                        <div class="form-group">
                            <label>Alamat Email</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($agentData['email']); ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Nama Lengkap</label>
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($agentData['full_name']); ?>" required>
                    </div>

                    <div class="section-title">Pengaturan Obrolan</div>
                    <div class="form-group">
                        <label>Nama Tampilan (Terlihat oleh pengunjung)</label>
                        <input type="text" name="display_name" value="<?php echo htmlspecialchars($agentData['display_name']); ?>" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Tim</label>
                            <select name="team_id">
                                <option value="0">Tidak ada tim</option>
                                <?php foreach ($teams as $team): ?>
                                <option value="<?php echo $team['id']; ?>" <?php echo $agentData['team_id'] == $team['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($team['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Mode Balasan</label>
                            <select name="reply_mode">
                                <option value="manual" <?php echo $agentData['reply_mode'] === 'manual' ? 'selected' : ''; ?>>🖐 Manual (Agent Only)</option>
                                <option value="bot" <?php echo $agentData['reply_mode'] === 'bot' ? 'selected' : ''; ?>>🤖 Bot Module</option>
                                <option value="ai" <?php echo $agentData['reply_mode'] === 'ai' ? 'selected' : ''; ?>>🧠 AI Assistant</option>
                                <option value="hybrid" <?php echo $agentData['reply_mode'] === 'hybrid' ? 'selected' : ''; ?>>⚡ Hybrid</option>
                            </select>
                        </div>
                    </div>

                    <div style="display:flex; justify-content:center; gap:12px; margin-top:32px; padding-top:24px; border-top:1px solid var(--border-color);">
                        <a href="agents.php" class="btn-secondary" style="width: 140px;">Batal</a>
                        <button type="submit" class="btn-primary" style="width: 180px;">
                            <i class="fas fa-floppy-disk"></i> Simpan
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>

    <script>
        // Page Loader
        window.addEventListener('load', () => {
            const loader = document.getElementById('pageLoader');
            if (loader) {
                loader.classList.add('fade-out');
                setTimeout(() => { loader.style.display = 'none'; }, 500);
            }
        });

        // Notifications Toggle (Desktop Only)
        const notifBtn = document.getElementById('sidebarNotifBtn');
        const notifDropdown = document.getElementById('notificationDropdown');
        if (notifBtn && notifDropdown) {
            notifBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                notifDropdown.classList.toggle('show');
            });
        }

        // Topbar Avatar Dropdown Toggle
        const topAvatarBtn = document.getElementById('topAvatarBtn');
        const avatarDropdown = document.getElementById('avatarDropdown');
        if (topAvatarBtn && avatarDropdown) {
            topAvatarBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                avatarDropdown.classList.toggle('show');
            });
        }

        // Global Click to close dropdowns
        document.addEventListener('click', (e) => {
            if (notifBtn && notifDropdown && !notifBtn.contains(e.target) && !notifDropdown.contains(e.target)) {
                notifDropdown.classList.remove('show');
            }
            if (topAvatarBtn && avatarDropdown && !topAvatarBtn.contains(e.target) && !avatarDropdown.contains(e.target)) {
                avatarDropdown.classList.remove('show');
            }
        });

        // Live Avatar Preview for Agent Edit Form
        document.getElementById('agent-avatar-input').addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('agent-avatar-preview').innerHTML = `<img src="${e.target.result}" alt="Preview">`;
                }
                reader.readAsDataURL(this.files[0]);
            }
        });
    </script>
    <?php include 'includes/layout-footer.php'; ?>
