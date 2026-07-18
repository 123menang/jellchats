<?php
require_once 'includes/auth.php';
$auth->requireAuth();
$user = $auth->getCurrentUser();
$db = Database::getInstance();

// Ensure agent exists for this user
$agent = $db->fetch("SELECT * FROM agents WHERE user_id = ?", [$user['id']]);
if (!$agent) {
    // Auto-create agent for user if not exists
    // First check if there's a team for this user
    $team = $db->fetch("SELECT * FROM teams WHERE user_id = ? LIMIT 1", [$user['id']]);
    if (!$team) {
        // Create default team
        $teamId = $db->insert(
            "INSERT INTO teams (user_id, name, description, max_agents) VALUES (?, 'Default Team', 'Auto-created team', 1)",
            [$user['id']]
        );
    } else {
        $teamId = $team['id'];
    }

    // Create agent
    $agentId = $db->insert(
        "INSERT INTO agents (team_id, user_id, display_name, reply_mode) VALUES (?, ?, ?, 'manual')",
        [$teamId, $user['id'], $user['full_name'] ?? $user['username']]
    );
    $agent = $db->fetch("SELECT * FROM agents WHERE id = ?", [$agentId]);
}
$agentId = $agent['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $siteName = sanitizeInput($_POST['site_name'] ?? '');
    $siteUrl = sanitizeInput($_POST['site_url'] ?? '');
    $embedKey = generateEmbedKey();

    $config = json_encode([
        'primary_color' => $_POST['primary_color'] ?? '#1e62ff',
        'position' => $_POST['position'] ?? 'right',
        'welcome_message' => sanitizeInput($_POST['welcome_message'] ?? 'Hi there! How can we help you today?'),
        'pre_chat_form' => isset($_POST['pre_chat_form']) ? 1 : 0,
        'allow_upload' => isset($_POST['allow_upload']) ? 1 : 0,
        'show_typing' => isset($_POST['show_typing']) ? 1 : 0
    ]);

    $db->insert(
        "INSERT INTO embed_codes (agent_id, site_name, site_url, embed_key, widget_config) VALUES (?, ?, ?, ?, ?)",
        [$agentId, $siteName, $siteUrl, $embedKey, $config]
    );
    header('Location: embeds.php?success=1');
    exit;
}

if (isset($_GET['delete'])) {
    $embedId = $_GET['delete'];
    
    // Hapus data terkait secara berurutan (Cascade manually)
    // 1. Hapus messages dari conversations yang terkait embed_code
    $db->delete("DELETE FROM messages WHERE conversation_id IN (SELECT id FROM conversations WHERE embed_code_id = ?)", [$embedId]);
    
    // 2. Hapus conversations yang terkait embed_code
    $db->delete("DELETE FROM conversations WHERE embed_code_id = ?", [$embedId]);
    
    // 3. Hapus embed_code
    $db->delete("DELETE FROM embed_codes WHERE id = ? AND agent_id = ?", [$embedId, $agentId]);
    
    header('Location: embeds.php?deleted=1');
    exit;
}

$embeds = $db->fetchAll("SELECT * FROM embed_codes WHERE agent_id = ? ORDER BY created_at DESC", [$agentId]);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Embed Codes - LiveChat Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/modules.css">
    <style>
        .embed-card {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 20px;
        }
        .embed-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        .embed-site {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .embed-site-icon {
            width: 44px;
            height: 44px;
            background: #eff6ff;
            color: var(--accent-blue);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        .embed-site h3 {
            font-size: 16px;
            font-weight: 600;
        }
        .embed-site span {
            font-size: 13px;
            color: var(--text-gray);
        }
        .embed-code-box {
            background: #1a1a2e;
            color: #a0a0b8;
            padding: 16px;
            border-radius: 12px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.6;
            position: relative;
            overflow-x: auto;
        }
        .embed-code-box code {
            color: #10b981;
        }
        .btn-copy {
            position: absolute;
            top: 12px;
            right: 12px;
            padding: 6px 12px;
            background: rgba(255,255,255,0.1);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            transition: var(--transition);
        }
        .btn-copy:hover {
            background: rgba(255,255,255,0.2);
        }
        .embed-stats {
            display: flex;
            gap: 24px;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--border-color);
        }
        .embed-stat {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: var(--text-gray);
        }
        .embed-stat i {
            color: var(--accent-blue);
        }
        .status-active { color: var(--accent-green); }
        .status-inactive { color: var(--text-light); }
    </style>
</head>
<body>
    <div class="app-container">
        <aside class="sidebar expanded" id="sidebar">
            <div class="sidebar-logo"><i class="fa-solid fa-comment-dots"></i></div>
            <div class="sidebar-brand">LiveChat</div>
            <nav class="sidebar-nav">
                <a href="index.php" class="sidebar-item"><i class="fa-solid fa-comments"></i><span>Chats</span></a>
                <a href="modules.php" class="sidebar-item"><i class="fa-solid fa-robot"></i><span>Modules</span></a>
                <a href="agents.php" class="sidebar-item"><i class="fa-solid fa-users"></i><span>Agents</span></a>
                <a href="analytics.php" class="sidebar-item"><i class="fa-solid fa-chart-line"></i><span>Analytics</span></a>
                <a href="embeds.php" class="sidebar-item active"><i class="fa-solid fa-code"></i><span>Embed Codes</span></a>
                <a href="settings.php" class="sidebar-item"><i class="fa-solid fa-gear"></i><span>Settings</span></a>
            </nav>
            <div class="sidebar-bottom">
                <a href="profile.php" class="sidebar-item">
                    <div class="sidebar-avatar online"><?php echo strtoupper(substr($user['full_name'] ?? $user['username'], 0, 1)); ?></div>
                    <span>Profile</span>
                </a>
                <a href="logout.php" class="sidebar-item"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></a>
            </div>
        </aside>

        <main class="main-content">
            <header class="top-header">
                <div class="header-search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" placeholder="Search...">
                </div>
                <div class="header-actions">
                    <button class="header-btn"><i class="fa-solid fa-bell"></i></button>
                    <div class="user-menu">
                        <div class="info">
                            <div class="name"><?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></div>
                            <div class="role"><?php echo ucfirst($user['role']); ?></div>
                        </div>
                        <div style="width:32px;height:32px;border-radius:50%;background:var(--accent-blue);display:flex;align-items:center;justify-content:center;color:white;font-weight:600;font-size:14px;">
                            <?php echo strtoupper(substr($user['full_name'] ?? $user['username'], 0, 1)); ?>
                        </div>
                    </div>
                </div>
            </header>

            <div class="page-content">
                <div class="page-header">
                    <div>
                        <h1>Embed Codes</h1>
                        <p>Generate and manage widget embed codes for your websites</p>
                    </div>
                    <button class="btn-primary" onclick="document.getElementById('addEmbedModal').style.display='flex'">
                        <i class="fa-solid fa-plus"></i> New Embed
                    </button>
                </div>

                <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    <i class="fa-solid fa-check-circle"></i> Embed code created successfully!
                </div>
                <?php endif; ?>

                <?php foreach ($embeds as $embed): 
                    $config = json_decode($embed['widget_config'], true);
                ?>
                <div class="embed-card">
                    <div class="embed-header">
                        <div class="embed-site">
                            <div class="embed-site-icon"><i class="fa-solid fa-globe"></i></div>
                            <div>
                                <h3><?php echo htmlspecialchars($embed['site_name']); ?></h3>
                                <span><?php echo htmlspecialchars($embed['site_url'] ?? 'No URL set'); ?></span>
                            </div>
                        </div>
                        <div class="module-actions">
                            <span class="status-<?php echo $embed['status'] ? 'active' : 'inactive'; ?>">
                                <i class="fa-solid fa-circle" style="font-size:8px;"></i> 
                                <?php echo $embed['status'] ? 'Active' : 'Inactive'; ?>
                            </span>
                            <a href="?delete=<?php echo $embed['id']; ?>" class="btn-icon danger" onclick="return confirm('Delete this embed code?')"><i class="fa-solid fa-trash"></i></a>
                        </div>
                    </div>

                    <div class="embed-code-box">
                        <button class="btn-copy" onclick="copyCode(this)"><i class="fa-regular fa-copy"></i> Copy</button>
                        <code>&lt;script src="<?php echo (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']; ?>/widget/widget.js?v=<?php echo $config['widget_version'] ?? time(); ?>" license="<?php echo $embed['embed_key']; ?>" async&gt;&lt;/script&gt;</code>
                    </div>

                    <div class="embed-stats">
                        <div class="embed-stat"><i class="fa-solid fa-palette"></i> <?php echo $config['primary_color'] ?? '#1e62ff'; ?></div>
                        <div class="embed-stat"><i class="fa-solid fa-location-arrow"></i> <?php echo ucfirst($config['position'] ?? 'right'); ?></div>
                        <div class="embed-stat"><i class="fa-solid fa-calendar"></i> <?php echo date('M d, Y', strtotime($embed['created_at'])); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php if (empty($embeds)): ?>
                <div style="text-align:center;padding:60px 20px;color:var(--text-light);">
                    <i class="fa-solid fa-code" style="font-size:48px;margin-bottom:16px;opacity:0.3;"></i>
                    <h3 style="font-size:18px;margin-bottom:8px;color:var(--text-gray);">No embed codes yet</h3>
                    <p style="font-size:14px;">Create your first embed code to start using the chat widget</p>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <div class="modal" id="addEmbedModal" style="display:none;">
        <div class="modal-overlay" onclick="document.getElementById('addEmbedModal').style.display='none'"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fa-solid fa-code"></i> New Embed Code</h2>
                <button class="modal-close" onclick="document.getElementById('addEmbedModal').style.display='none'"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form method="POST" action="">
                <div class="form-group">
                    <label>Site Name</label>
                    <input type="text" name="site_name" placeholder="e.g., My Online Store" required>
                </div>
                <div class="form-group">
                    <label>Site URL</label>
                    <input type="url" name="site_url" placeholder="https://example.com">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Primary Color</label>
                        <input type="color" name="primary_color" value="#1e62ff" style="height:44px;padding:4px;">
                    </div>
                    <div class="form-group">
                        <label>Position</label>
                        <select name="position">
                            <option value="right">Bottom Right</option>
                            <option value="left">Bottom Left</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Welcome Message</label>
                    <input type="text" name="welcome_message" value="Hi there! How can we help you today?">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label><input type="checkbox" name="pre_chat_form" checked> Pre-chat Form</label>
                    </div>
                    <div class="form-group">
                        <label><input type="checkbox" name="allow_upload" checked> Allow Upload</label>
                    </div>
                    <div class="form-group">
                        <label><input type="checkbox" name="show_typing" checked> Show Typing</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="document.getElementById('addEmbedModal').style.display='none'">Cancel</button>
                    <button type="submit" class="btn-primary">Generate Code</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function copyCode(btn) {
            const code = btn.parentElement.querySelector('code').textContent;
            navigator.clipboard.writeText(code).then(() => {
                btn.innerHTML = '<i class="fa-solid fa-check"></i> Copied!';
                setTimeout(() => btn.innerHTML = '<i class="fa-regular fa-copy"></i> Copy', 2000);
            });
        }
    </script>
</body>
</html>
