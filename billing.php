<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Cek apakah user sudah login
$isLoggedIn = isset($_SESSION['user_id']);
$currentUser = null;
$currentSubscription = null;

if ($isLoggedIn) {
    $auth->requireAuth();
    $currentUser = $auth->getCurrentUser();
    $db = Database::getInstance();
    
    // Ambil subscription aktif
    $currentSubscription = $db->fetch("
        SELECT s.*, lt.name as tier_name, lt.price_monthly, lt.max_teams, lt.max_agents_per_team, lt.max_chats_monthly, lt.max_modules, lt.ai_enabled, lt.custom_branding, lt.priority_support
        FROM subscriptions s
        JOIN license_tiers lt ON s.tier_name = lt.name
        WHERE s.user_id = ? AND s.status = 'active' AND s.end_date > datetime('now')
        ORDER BY s.end_date DESC LIMIT 1
    ", [$currentUser['id']]);
    
    $currentTier = $currentSubscription ? $currentSubscription['tier_name'] : ($currentUser['license_tier'] ?? 'starter');
} else {
    $currentTier = null;
}

// Ambil semua paket dari database
$db = Database::getInstance();
$packages = $db->fetchAll("SELECT * FROM license_tiers ORDER BY price_monthly ASC");

$activePage = 'billing';
$pageTitle = 'Pricing - LiveChat Admin';

// Handle subscription upgrade
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isLoggedIn) {
    $action = $_POST['action'] ?? '';
    $tierName = $_POST['tier_name'] ?? '';
    
    if ($action === 'upgrade' && $tierName) {
        $package = $db->fetch("SELECT * FROM license_tiers WHERE name = ?", [$tierName]);
        if ($package) {
            // Create subscription record
            $startDate = date('Y-m-d H:i:s');
            $endDate = date('Y-m-d H:i:s', strtotime('+30 days'));
            
            $db->insert("
                INSERT INTO subscriptions (user_id, tier_name, amount, duration_days, start_date, end_date, status, transaction_id)
                VALUES (?, ?, ?, 30, ?, ?, 'pending', ?)
            ", [
                $currentUser['id'],
                $package['name'],
                $package['price_monthly'],
                $startDate,
                $endDate,
                'SUB_' . time() . '_' . $currentUser['id']
            ]);
            
            // Update user license tier
            $db->update("UPDATE users SET license_tier = ? WHERE id = ?", [$package['name'], $currentUser['id']]);
            
            $success = "Berhasil upgrade ke paket " . ucfirst($package['name']) . "! Silakan selesaikan pembayaran.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title><?= htmlspecialchars($pageTitle) ?> - LiveChat</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Navigation */
        .navbar {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            padding: 16px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .logo {
            font-size: 24px;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            text-decoration: none;
        }

        .nav-links {
            display: flex;
            gap: 24px;
            align-items: center;
            flex-wrap: wrap;
        }

        .nav-links a {
            text-decoration: none;
            color: #4a5568;
            font-weight: 500;
            transition: color 0.2s;
        }

        .nav-links a:hover {
            color: #667eea;
        }

        .btn-login {
            padding: 8px 20px;
            border-radius: 8px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white !important;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        /* Hero Section */
        .hero {
            text-align: center;
            padding: 80px 24px 60px;
            color: white;
        }

        .hero-badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 24px;
        }

        .hero h1 {
            font-size: 48px;
            font-weight: 800;
            margin-bottom: 16px;
            letter-spacing: -0.02em;
        }

        .hero p {
            font-size: 18px;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Pricing Section */
        .pricing-section {
            padding: 40px 24px 80px;
        }

        .pricing-inner {
            max-width: 1200px;
            margin: 0 auto;
        }

        .section-eyebrow {
            text-align: center;
            color: rgba(255,255,255,0.8);
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 16px;
        }

        .section-title {
            text-align: center;
            font-size: 36px;
            font-weight: 700;
            color: white;
            margin-bottom: 16px;
        }

        .section-sub {
            text-align: center;
            color: rgba(255,255,255,0.8);
            font-size: 16px;
            margin-bottom: 48px;
        }

        /* Pricing Grid */
        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 32px;
            margin-top: 20px;
        }

        .p-card {
            background: white;
            border-radius: 24px;
            padding: 32px;
            position: relative;
            transition: all 0.3s ease;
            box-shadow: 0 20px 35px -10px rgba(0,0,0,0.2);
        }

        .p-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 30px 45px -15px rgba(0,0,0,0.3);
        }

        .p-card.featured {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9ff 100%);
            border: 2px solid #667eea;
            transform: scale(1.02);
        }

        .p-card.featured:hover {
            transform: scale(1.02) translateY(-8px);
        }

        .p-badge {
            position: absolute;
            top: -12px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .p-tier {
            font-size: 20px;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 8px;
        }

        .p-price {
            font-size: 42px;
            font-weight: 800;
            color: #1a202c;
            margin-bottom: 8px;
        }

        .p-price span {
            font-size: 16px;
            font-weight: 500;
            color: #718096;
        }

        .p-desc {
            color: #718096;
            font-size: 14px;
            margin-bottom: 24px;
            padding-bottom: 24px;
            border-bottom: 1px solid #e2e8f0;
        }

        .p-features {
            list-style: none;
            margin-bottom: 32px;
        }

        .p-features li {
            padding: 10px 0;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            color: #4a5568;
        }

        .p-features li i {
            color: #48bb78;
            font-size: 16px;
            width: 20px;
        }

        .p-features li i.fa-times {
            color: #fc8181;
        }

        .p-btn {
            width: 100%;
            padding: 14px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            font-family: inherit;
        }

        .p-btn-outline {
            background: white;
            border: 2px solid #e2e8f0;
            color: #4a5568;
        }

        .p-btn-outline:hover {
            border-color: #667eea;
            color: #667eea;
        }

        .p-btn-fill {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .p-btn-fill:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        /* FAQ Section */
        .faq-section {
            background: white;
            padding: 60px 24px;
        }

        .faq-inner {
            max-width: 800px;
            margin: 0 auto;
        }

        .faq-title {
            text-align: center;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 48px;
            color: #1a202c;
        }

        .faq-item {
            margin-bottom: 24px;
            border-bottom: 1px solid #e2e8f0;
        }

        .faq-question {
            padding: 20px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            font-weight: 600;
            font-size: 18px;
            color: #1a202c;
        }

        .faq-question i {
            transition: transform 0.3s;
        }

        .faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            padding: 0;
            color: #718096;
            line-height: 1.6;
        }

        .faq-item.active .faq-answer {
            max-height: 200px;
            padding-bottom: 20px;
        }

        .faq-item.active .faq-question i {
            transform: rotate(180deg);
        }

        /* Footer */
        .footer {
            background: #1a202c;
            color: #a0aec0;
            padding: 48px 24px;
            text-align: center;
        }

        /* Current Plan Banner */
        .current-plan-banner {
            max-width: 800px;
            margin: 0 auto 40px;
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 20px 28px;
            text-align: center;
            color: white;
        }

        .current-plan-banner strong {
            font-size: 20px;
            display: block;
            margin-top: 8px;
            color: #fbbf24;
        }

        /* Alert */
        .alert {
            max-width: 600px;
            margin: 20px auto 0;
            padding: 14px 20px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
        }
        .alert-success {
            background: #c6f6d5;
            color: #22543d;
        }
        .alert-error {
            background: #fed7d7;
            color: #742a2a;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                padding: 16px;
            }
            
            .hero h1 {
                font-size: 32px;
            }
            
            .hero p {
                font-size: 16px;
            }
            
            .section-title {
                font-size: 28px;
            }
            
            .pricing-grid {
                grid-template-columns: 1fr;
                gap: 24px;
            }
            
            .p-card.featured {
                transform: scale(1);
            }
            
            .p-card.featured:hover {
                transform: translateY(-8px);
            }
            
            .p-price {
                font-size: 36px;
            }
            
            .faq-question {
                font-size: 16px;
            }
        }

        @media (max-width: 480px) {
            .pricing-grid {
                grid-template-columns: 1fr;
            }
            
            .hero {
                padding: 48px 16px 32px;
            }
            
            .p-card {
                padding: 24px;
            }
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        ::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #667eea;
        }
    </style>
</head>
<body>

<!-- Navigation -->
<nav class="navbar">
    <a href="index.php" class="logo">LiveChat</a>
    <div class="nav-links">
        <a href="#harga">Pricing</a>
        <a href="#faq">FAQ</a>
        <a href="features.php">Features</a>
        <?php if ($isLoggedIn): ?>
            <a href="dashboard.php">Dashboard</a>
            <a href="logout.php" class="btn-login">Logout</a>
        <?php else: ?>
            <a href="login.php" class="btn-login">Sign In</a>
        <?php endif; ?>
    </div>
</nav>

<!-- Hero Section -->
<section class="hero">
    <div class="hero-badge">✨ Mulai 14 Hari Gratis</div>
    <h1>Pricing yang <span style="background: linear-gradient(135deg, #fbbf24, #f59e0b); -webkit-background-clip: text; background-clip: text; color: transparent;">Transparan</span></h1>
    <p>Pilih paket yang sesuai dengan kebutuhan bisnis Anda. Upgrade atau downgrade kapan saja.</p>
</section>

<!-- Pricing Section -->
<section class="pricing-section" id="harga">
    <div class="pricing-inner">
        <div class="section-eyebrow">Harga Transparan</div>
        <h2 class="section-title">Paket yang tumbuh bersama bisnis Anda</h2>
        <p class="section-sub">Semua paket sudah termasuk 14 hari uji coba gratis. Tidak ada biaya tersembunyi.</p>

        <?php if ($isLoggedIn && $currentSubscription): ?>
        <div class="current-plan-banner">
            <i class="fa-solid fa-crown" style="font-size: 24px; margin-bottom: 8px; display: inline-block;"></i>
            <div>Paket Anda Saat Ini</div>
            <strong><?= ucfirst($currentSubscription['tier_name']) ?></strong>
            <div style="font-size: 13px; margin-top: 8px;">
                Berlaku hingga: <?= date('d F Y', strtotime($currentSubscription['end_date'])) ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
        <div class="alert alert-success">
            <i class="fa-solid fa-check-circle"></i> <?= htmlspecialchars($success) ?>
        </div>
        <?php endif; ?>

        <div class="pricing-grid">
            <?php foreach ($packages as $package): 
                $isCurrent = ($isLoggedIn && $currentTier === $package['name']);
                $isPopular = ($package['name'] === 'team');
            ?>
            <div class="p-card <?= $isPopular ? 'featured' : '' ?>">
                <?php if ($isPopular): ?>
                <div class="p-badge">⭐ PALING POPULER</div>
                <?php endif; ?>
                
                <div class="p-tier"><?= ucfirst($package['name']) ?></div>
                <div class="p-price">Rp<?= number_format($package['price_monthly'], 0, ',', '.') ?><span>/bln</span></div>
                <p class="p-desc">
                    <?php if ($package['name'] === 'starter'): ?>
                    Cocok untuk bisnis baru dan toko online kecil.
                    <?php elseif ($package['name'] === 'team'): ?>
                    Ideal untuk tim customer service yang aktif.
                    <?php else: ?>
                    Solusi skala enterprise dengan semua fitur premium.
                    <?php endif; ?>
                </p>
                <ul class="p-features">
                    <li><i class="fa-solid fa-check"></i> <?= $package['max_teams'] ?> Tim, <?= $package['max_agents_per_team'] ?> Agent</li>
                    <li><i class="fa-solid fa-check"></i> <?= number_format($package['max_chats_monthly'], 0, ',', '.') ?> chat/bulan</li>
                    <li><i class="fa-solid fa-check"></i> <?= $package['max_modules'] ?> Keyword Modules</li>
                    <li><i class="fa-solid fa-check"></i> Widget kustomisasi</li>
                    <?php if ($package['ai_enabled']): ?>
                    <li><i class="fa-solid fa-check"></i> <strong>AI Bot (Claude/Gemini/OpenAI)</strong></li>
                    <li><i class="fa-solid fa-check"></i> Mode Hybrid Bot+AI</li>
                    <?php else: ?>
                    <li><i class="fa-solid fa-times" style="color: #fc8181;"></i> AI Bot (butuh upgrade)</li>
                    <?php endif; ?>
                    <?php if ($package['name'] === 'team' || $package['name'] === 'business'): ?>
                    <li><i class="fa-solid fa-check"></i> Analitik lanjutan</li>
                    <?php endif; ?>
                    <?php if ($package['name'] === 'business'): ?>
                    <li><i class="fa-solid fa-check"></i> Custom branding</li>
                    <li><i class="fa-solid fa-check"></i> API access & priority support</li>
                    <?php endif; ?>
                </ul>
                
                <?php if ($isLoggedIn): ?>
                    <?php if ($isCurrent): ?>
                    <button class="p-btn p-btn-outline" disabled style="opacity: 0.6; cursor: not-allowed;">
                        <i class="fa-solid fa-check"></i> Paket Aktif
                    </button>
                    <?php else: ?>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="upgrade">
                        <input type="hidden" name="tier_name" value="<?= $package['name'] ?>">
                        <button type="submit" class="p-btn <?= $isPopular ? 'p-btn-fill' : 'p-btn-outline' ?>">
                            <?= $package['price_monthly'] > (($currentSubscription['price_monthly'] ?? 0) + 1000) ? 'Upgrade Sekarang' : 'Pilih Paket' ?>
                        </button>
                    </form>
                    <?php endif; ?>
                <?php else: ?>
                <button class="p-btn <?= $isPopular ? 'p-btn-fill' : 'p-btn-outline' ?>" onclick="location.href='login.php?signup=1'">
                    Coba Gratis 14 Hari
                </button>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- FAQ Section -->
<section class="faq-section" id="faq">
    <div class="faq-inner">
        <h2 class="faq-title">Pertanyaan yang Sering Diajukan</h2>
        
        <div class="faq-item">
            <div class="faq-question">
                Apakah ada biaya setup awal?
                <i class="fa-solid fa-chevron-down"></i>
            </div>
            <div class="faq-answer">
                Tidak ada biaya setup awal. Anda hanya membayar sesuai paket bulanan yang dipilih. Semua paket sudah termasuk 14 hari uji coba gratis.
            </div>
        </div>
        
        <div class="faq-item">
            <div class="faq-question">
                Bisakah saya upgrade atau downgrade paket?
                <i class="fa-solid fa-chevron-down"></i>
            </div>
            <div class="faq-answer">
                Ya, Anda dapat mengupgrade atau mendowngrade paket kapan saja. Perubahan akan langsung berlaku dan biaya akan disesuaikan secara proporsional.
            </div>
        </div>
        
        <div class="faq-item">
            <div class="faq-question">
                Metode pembayaran apa saja yang diterima?
                <i class="fa-solid fa-chevron-down"></i>
            </div>
            <div class="faq-answer">
                Kami menerima pembayaran melalui transfer bank (BCA, Mandiri, BRI), kartu kredit (Visa, Mastercard), dan e-wallet (GoPay, OVO, Dana).
            </div>
        </div>
        
        <div class="faq-item">
            <div class="faq-question">
                Apakah ada batasan jumlah chat?
                <i class="fa-solid fa-chevron-down"></i>
            </div>
            <div class="faq-answer">
                Setiap paket memiliki batasan chat bulanan yang berbeda. Jika melebihi batas, Anda dapat mengupgrade paket atau membeli tambahan chat.
            </div>
        </div>
        
        <div class="faq-item">
            <div class="faq-question">
                Bagaimana cara cancel subscription?
                <i class="fa-solid fa-chevron-down"></i>
            </div>
            <div class="faq-answer">
                Anda dapat membatalkan subscription kapan saja dari halaman Settings. Tidak ada biaya pembatalan, dan Anda tetap dapat menggunakan layanan hingga akhir periode berlangganan.
            </div>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="footer">
    <div style="max-width: 600px; margin: 0 auto;">
        <div style="font-size: 20px; font-weight: 700; margin-bottom: 16px; color: white;">LiveChat</div>
        <div style="margin-bottom: 16px;">
            <a href="#" style="color: #a0aec0; text-decoration: none; margin: 0 12px;">Terms</a>
            <a href="#" style="color: #a0aec0; text-decoration: none; margin: 0 12px;">Privacy</a>
            <a href="#" style="color: #a0aec0; text-decoration: none; margin: 0 12px;">Contact</a>
        </div>
        <div style="font-size: 14px;">
            &copy; <?= date('Y') ?> LiveChat. All rights reserved.
        </div>
    </div>
</footer>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

<script>
    // FAQ Accordion
    document.querySelectorAll('.faq-question').forEach(question => {
        question.addEventListener('click', () => {
            const faqItem = question.parentElement;
            faqItem.classList.toggle('active');
        });
    });

    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    // Toastr setup
    toastr.options = {
        closeButton: true,
        positionClass: "toast-top-right",
        timeOut: "5000",
        progressBar: true
    };

    // Show success message if needed
    <?php if (isset($success) && !isset($_POST['action'])): ?>
    toastr.success("<?= addslashes($success) ?>");
    <?php endif; ?>
</script>

</body>
</html>