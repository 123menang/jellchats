<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
$auth->requireAuth();
$user = $auth->getCurrentUser();
$db = Database::getInstance();

$tiers = $db->fetchAll("SELECT * FROM license_tiers ORDER BY price_monthly ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Pricing & License - LiveChat Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <style>
        .page-content { flex: 1; overflow-y: auto; padding: 24px 32px; }
        .page-header { text-align: center; margin-bottom: 40px; }
        .page-header h1 { font-size: 32px; font-weight: 700; margin-bottom: 8px; }
        .page-header p { font-size: 16px; color: var(--text-gray); }

        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            max-width: 1000px;
            margin: 0 auto;
        }

        .pricing-card {
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 20px;
            padding: 32px 24px;
            text-align: center;
            transition: var(--transition);
            position: relative;
        }

        .pricing-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
        }

        .pricing-card.popular {
            border-color: var(--accent-blue);
            transform: scale(1.05);
        }

        .pricing-card.popular:hover {
            transform: scale(1.05) translateY(-8px);
        }

        .popular-badge {
            position: absolute;
            top: -12px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--accent-blue);
            color: white;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }

        .pricing-icon {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 24px;
        }

        .pricing-card:nth-child(1) .pricing-icon { background: #fef3c7; color: #92400e; }
        .pricing-card:nth-child(2) .pricing-icon { background: #dbeafe; color: #1e40af; }
        .pricing-card:nth-child(3) .pricing-icon { background: #ede9fe; color: #5b21b6; }

        .pricing-name {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .pricing-price {
            font-size: 36px;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 4px;
        }

        .pricing-price span {
            font-size: 14px;
            color: var(--text-gray);
            font-weight: 400;
        }

        .pricing-period {
            font-size: 13px;
            color: var(--text-light);
            margin-bottom: 24px;
        }

        .pricing-features {
            text-align: left;
            list-style: none;
            margin-bottom: 24px;
        }

        .pricing-features li {
            padding: 8px 0;
            font-size: 14px;
            color: var(--text-gray);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .pricing-features li i {
            color: var(--accent-green);
            font-size: 12px;
        }

        .pricing-features li.disabled {
            color: var(--text-light);
            text-decoration: line-through;
        }

        .pricing-features li.disabled i {
            color: var(--text-light);
        }

        .btn-pricing {
            display: block;
            width: 100%;
            padding: 14px;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }

        .btn-pricing-outline {
            background: white;
            color: var(--accent-blue);
            border: 2px solid var(--accent-blue);
        }

        .btn-pricing-outline:hover {
            background: var(--accent-blue);
            color: white;
        }

        .btn-pricing-solid {
            background: var(--accent-blue);
            color: white;
            border: 2px solid var(--accent-blue);
        }

        .btn-pricing-solid:hover {
            background: #1a4fd1;
        }

        .current-plan {
            display: inline-block;
            padding: 4px 12px;
            background: var(--accent-green);
            color: white;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 12px;
        }

        @media (max-width: 900px) {
            .pricing-grid { grid-template-columns: 1fr; }
            .pricing-card.popular { transform: none; }
        }
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
                <a href="embeds.php" class="sidebar-item"><i class="fa-solid fa-code"></i><span>Embed Codes</span></a>
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
                    <h1>Choose Your License Plan</h1>
                    <p>Scale your team with the right plan for your business</p>
                </div>

                <div class="pricing-grid">
                    <?php foreach ($tiers as $index => $tier): 
                        $features = json_decode($tier['features'], true);
                        $isCurrent = $user['license_tier'] === $tier['name'];
                        $isPopular = $tier['name'] === 'team';
                    ?>
                    <div class="pricing-card <?php echo $isPopular ? 'popular' : ''; ?>">
                        <?php if ($isPopular): ?>
                        <div class="popular-badge">MOST POPULAR</div>
                        <?php endif; ?>

                        <div class="pricing-icon">
                            <i class="fa-solid fa-<?php echo $index === 0 ? 'rocket' : ($index === 1 ? 'users' : 'crown'); ?>"></i>
                        </div>

                        <h3 class="pricing-name"><?php echo ucfirst($tier['name']); ?></h3>
                        <div class="pricing-price">
                            <?php echo formatRupiah($tier['price_monthly']); ?>
                            <span>/bulan</span>
                        </div>
                        <div class="pricing-period">Billed monthly</div>

                        <ul class="pricing-features">
                            <li><i class="fa-solid fa-check"></i> <?php echo $tier['max_teams']; ?> Team<?php echo $tier['max_teams'] > 1 ? 's' : ''; ?></li>
                            <li><i class="fa-solid fa-check"></i> <?php echo $tier['max_agents_per_team']; ?> Agent<?php echo $tier['max_agents_per_team'] > 1 ? 's' : ''; ?> per team</li>
                            <li><i class="fa-solid fa-check"></i> <?php echo number_format($tier['max_chats_monthly']); ?> chats/month</li>
                            <li><i class="fa-solid fa-check"></i> <?php echo $tier['max_modules']; ?> modules</li>
                            <li class="<?php echo $tier['ai_enabled'] ? '' : 'disabled'; ?>">
                                <i class="fa-solid fa-<?php echo $tier['ai_enabled'] ? 'check' : 'xmark'; ?>"></i> AI Assistant
                            </li>
                            <li class="<?php echo $tier['custom_branding'] ? '' : 'disabled'; ?>">
                                <i class="fa-solid fa-<?php echo $tier['custom_branding'] ? 'check' : 'xmark'; ?>"></i> Custom Branding
                            </li>
                            <li class="<?php echo $tier['priority_support'] ? '' : 'disabled'; ?>">
                                <i class="fa-solid fa-<?php echo $tier['priority_support'] ? 'check' : 'xmark'; ?>"></i> Priority Support
                            </li>
                        </ul>

                        <?php if ($isCurrent): ?>
                        <span class="current-plan"><i class="fa-solid fa-check"></i> Current Plan</span>
                        <?php else: ?>
                        <a href="license-upgrade.php?plan=<?php echo $tier['name']; ?>" class="btn-pricing <?php echo $isPopular ? 'btn-pricing-solid' : 'btn-pricing-outline'; ?>">
                            <?php echo $isPopular ? 'Upgrade Now' : 'Select Plan'; ?>
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
