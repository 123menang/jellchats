
    <div class="app-body" style="display: flex; flex: 1; overflow: hidden;">
        <aside class="sidebar-left">
            <div class="nav-top">
                <a href="chats.php" class="nav-item <?= ($activePage == 'chats') ? 'active' : ''; ?>" title="Chats">
                    <i class="fas fa-comment-dots"></i>
                </a>
                <a href="agents.php" class="nav-item <?= ($activePage == 'agents') ? 'active' : ''; ?>" title="Team">
                    <i class="fas fa-users"></i>
                </a>
                <a href="modules.php" class="nav-item <?= ($activePage == 'modules') ? 'active' : ''; ?>" title="Modules">
                    <i class="fas fa-robot"></i>
                </a>
                <a href="analytics.php" class="nav-item <?= ($activePage == 'analytics') ? 'active' : ''; ?>" title="Analytics">
                    <i class="fas fa-chart-line"></i>
                </a>

                <!-- Desktop Only Extra Menus -->
                <a href="setting-widget" class="nav-item hide-mobile <?= ($activePage == 'setting-widget') ? 'active' : ''; ?>" title="Widget Code">
                    <i class="fa-solid fa-code"></i>
                </a>
                <a href="archive.php" class="nav-item hide-mobile <?= ($activePage == 'archive') ? 'active' : ''; ?>" title="Archive">
                    <i class="fa-solid fa-box-archive"></i>
                </a>
                <a href="reports.php" class="nav-item hide-mobile <?= ($activePage == 'reports') ? 'active' : ''; ?>" title="Reports">
                    <i class="fa-solid fa-file-lines"></i>
                </a>

                <!-- Mobile Only Gear -->
                <a href="profile.php" class="nav-item hide-desktop <?= ($activePage == 'profile') ? 'active' : ''; ?>">
                    <i class="fas fa-gear"></i>
                </a>
            </div>

            <div class="sidebar-bottom hide-mobile" style="display: flex; flex-direction: column; align-items: center;">
                <a href="billing.php" class="nav-item <?= ($activePage == 'billing') ? 'active' : ''; ?>" title="Billing">
                    <i class="fas fa-credit-card"></i>
                </a>

                <div style="position:relative;">
                    <button id="notifBellBtn" class="nav-item" style="background:transparent; border:none; cursor:pointer;" title="Notifications">
                        <i class="fas fa-bell"></i>
                        <div style="position:absolute; top:8px; right:8px; width:8px; height:8px; background:var(--danger); border-radius:50%; border:2px solid var(--bg-dark);"></div>
                    </button>
              
                    <div id="sideNotifDrop">
                        <div style="padding:15px; font-weight:700; border-bottom:1px solid #eee;">Notifications</div>
                        <div style="max-height:300px; overflow-y:auto;" id="notifContainer">
                            <div style="padding:12px; font-size:12px; border-bottom:1px solid #f9f9f9;">Sistem Aktif - Sesi login terbaru dideteksi.</div>
                        </div>
                    </div>
                </div>

                <a href="settings.php" class="nav-item <?= ($activePage == 'settings') ? 'active' : ''; ?>" title="Settings">
                    <i class="fas fa-gear"></i>
                </a>

                <a href="profile.php" title="My Profile" style="margin-top: 10px; display: block;">
                    <div style="width:34px; height:34px; border-radius:50%; border: 2px solid <?= ($activePage == 'profile') ? 'var(--accent-blue)' : '#27272a'; ?>; overflow:hidden;">
                        <img src="<?= $uAva ?>" style="width:100%; height:100%; object-fit:cover;">
                    </div>
                </a>
            </div>
        </aside>

        <!--<main class="main-content-wrapper">-->
