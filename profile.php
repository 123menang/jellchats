<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
$auth->requireAuth();
$db = Database::getInstance();
$user = $db->fetch("SELECT * FROM users WHERE id = ?", [$auth->getCurrentUser()['id']]);

// Variabel Penunjang untuk Include
$pageTitle = 'My Subscription & Profile';
$activePage = 'profile'; 
$onlineAgentsCount = 2; // Real hitung pake SQL Anda
$logs = $db->fetchAll("SELECT * FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 3", [$user['id']]);

include 'includes/layout-header.php'; 
?>

<!-- Page Content -->
<div class="page-content">
    <div class="page-header" style="text-align:center;">
        <h1>My Subscription & Profile</h1>
        <p style="opacity:0.7">Manage your account and license here.</p>
    </div>

    <div class="profile-grid">
        <!-- Kolom Kiri -->
        <div class="card">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="update_profile" value="1">
                <div class="avatar-edit-box">
                    <div class="avatar-container">
                        <img src="<?php echo !empty($user['avatar']) ? $user['avatar'] : 'assets/images/default-avatar.png'; ?>" id="p" class="avatar-lg">
                        <label for="f" class="ava-cam-btn"><i class="fas fa-camera"></i></label>
                        <input type="file" name="avatar" id="f" style="display:none" onchange="document.getElementById('p').src=window.URL.createObjectURL(this.files[0])">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Nama Lengkap</label>
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" class="form-control">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" disabled class="form-control" style="background:var(--bg-gray);">
                    </div>
                    <div class="form-group">
                        <label>Role</label>
                        <input type="text" value="<?php echo ucfirst($user['role']); ?>" disabled class="form-control" style="background:var(--bg-gray);">
                    </div>
                </div>
                <button type="submit" class="btn-save">Save All Settings</button>
            </form>
        </div>

        <!-- Kolom Kanan: Info Paket -->
        <div class="side-col">
            <div class="license-side">
                <div style="font-size:12px; text-transform:uppercase; opacity:0.6;">Sisa Lisensi</div>
                <div class="days-val">29 Hari</div>
                <div style="font-size:12px; opacity:0.8;">Hingga: 25 Mei 2026</div>
                <div style="margin-top:16px; padding-top:16px; border-top:1px solid rgba(255,255,255,0.1);">
                    <div style="font-size:12px; text-transform:uppercase; opacity:0.6; margin-bottom:8px;">Recent Activity</div>
                    <?php foreach($logs as $log): ?>
                    <div style="font-size:12px; opacity:0.8; margin-bottom:6px; display:flex; align-items:center; gap:6px;">
                        <i class="fas fa-circle" style="font-size:4px;"></i>
                        <?php echo htmlspecialchars($log['action'] ?? 'Activity'); ?>
                    </div>
                    <?php endforeach; ?>
                    <?php if(empty($logs)): ?>
                    <div style="font-size:12px; opacity:0.5;">No recent activity</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/layout-footer.php'; ?>