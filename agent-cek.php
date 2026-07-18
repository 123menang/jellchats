<?php
/**
 * Halaman Cek Info Agent, Lisensi & Embed Code
 * Akses: http://domain-anda.com/agent-cek.php
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Pastikan pengguna sudah login
$auth->requireAuth();
$user = $auth->getCurrentUser();
$userId = $user['id'];

// Ambil data lengkap menggunakan fungsi baru
$agentData = getAgentSubscription($userId);

// Jika user bukan agen (data agent tidak ditemukan), tetap bisa menampilkan user & subscription
if (!$agentData) {
    die("Data tidak ditemukan. Pastikan Anda login sebagai user yang memiliki agen.");
}

// Ekstrak data
$userInfo        = $agentData['user'];
$agentInfo       = $agentData['agent'];
$teamInfo        = $agentData['team'];
$embedCodes      = $agentData['embed_codes'];
$subscription    = $agentData['subscription'];
$remainingDays   = $agentData['remaining_days'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Info Agent & Lisensi</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; margin: 2rem; background: #f3f4f6; }
        .container { max-width: 800px; margin: auto; }
        .card {
            background: white; border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        }
        h1 { color: #1e62ff; }
        h2 { font-size: 1.2rem; color: #374151; border-bottom: 2px solid #e5e7eb; padding-bottom: 0.5rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 0.5rem; }
        th, td { padding: 0.6rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f3f4f6; font-weight: 600; width: 35%; }
        .badge {
            display: inline-block; padding: 0.2rem 0.8rem; border-radius: 999px;
            font-size: 0.8rem; font-weight: 600;
        }
        .badge.active { background: #dcfce7; color: #15803d; }
        .badge.expired { background: #fee2e2; color: #b91c1c; }
        .badge.warning { background: #fef9c3; color: #a16207; }
        .embed-code {
            font-family: monospace; background: #f1f5f9; padding: 0.2rem 0.5rem;
            border-radius: 4px; font-size: 0.9rem;
        }
        .no-data { color: #9ca3af; font-style: italic; }
    </style>
</head>
<body>
<div class="container">
    <h1>🤖 Informasi Agent & Lisensi</h1>

    <!-- 1. User Info -->
    <div class="card">
        <h2>👤 Data Pengguna</h2>
        <table>
            <tr><th>Username</th><td><?= htmlspecialchars($userInfo['username']) ?></td></tr>
            <tr><th>Email</th><td><?= htmlspecialchars($userInfo['email']) ?></td></tr>
            <tr><th>Nama Lengkap</th><td><?= htmlspecialchars($userInfo['full_name'] ?? '-') ?></td></tr>
            <tr><th>Role</th><td><?= htmlspecialchars($userInfo['role']) ?></td></tr>
            <tr><th>License Tier</th><td><?= htmlspecialchars($userInfo['license_tier']) ?></td></tr>
        </table>
    </div>

    <!-- 2. Info Agent -->
    <div class="card">
        <h2>🧑‍💼 Data Agent</h2>
        <?php if ($agentInfo): ?>
        <table>
            <tr><th>Display Name</th><td><?= htmlspecialchars($agentInfo['display_name']) ?></td></tr>
            <tr><th>Reply Mode</th><td><?= htmlspecialchars($agentInfo['reply_mode']) ?></td></tr>
            <tr><th>AI Provider</th><td><?= htmlspecialchars($agentInfo['ai_provider'] ?? 'Tidak diatur') ?></td></tr>
            <tr><th>Online</th><td><?= $agentInfo['is_online'] ? '✅ Online' : '❌ Offline' ?></td></tr>
        </table>
        <?php else: ?>
        <p class="no-data">Tidak ada data agent.</p>
        <?php endif; ?>
    </div>

    <!-- 3. Team -->
    <div class="card">
        <h2>👥 Tim</h2>
        <?php if ($teamInfo): ?>
        <table>
            <tr><th>Nama Tim</th><td><?= htmlspecialchars($teamInfo['name']) ?></td></tr>
            <tr><th>Deskripsi</th><td><?= htmlspecialchars($teamInfo['description'] ?? '-') ?></td></tr>
            <tr><th>Max Agents</th><td><?= $teamInfo['max_agents'] ?></td></tr>
        </table>
        <?php else: ?>
        <p class="no-data">Belum bergabung dalam tim.</p>
        <?php endif; ?>
    </div>

    <!-- 4. Embed Codes -->
    <div class="card">
        <h2>🔗 Embed Codes</h2>
        <?php if (!empty($embedCodes)): ?>
            <table>
                <thead><tr><th>Site Name</th><th>Embed Key</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($embedCodes as $ec): ?>
                    <tr>
                        <td><?= htmlspecialchars($ec['site_name']) ?></td>
                        <td><span class="embed-code"><?= htmlspecialchars($ec['embed_key']) ?></span></td>
                        <td>
                            <span class="badge <?= $ec['status'] ? 'active' : 'expired' ?>">
                                <?= $ec['status'] ? 'Aktif' : 'Nonaktif' ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="no-data">Belum ada embed code dibuat.</p>
        <?php endif; ?>
    </div>

    <!-- 5. Subscription & Sisa Durasi -->
    <div class="card">
        <h2>💳 Langganan & Sisa Durasi</h2>
        <?php if ($subscription): ?>
            <table>
                <tr><th>Tier</th><td><?= htmlspecialchars($subscription['tier_name']) ?></td></tr>
                <tr><th>Jumlah Bayar</th><td>Rp <?= number_format($subscription['amount'],0,',','.') ?></td></tr>
                <tr><th>Durasi</th><td><?= $subscription['duration_days'] ?> hari</td></tr>
                <tr><th>Mulai</th><td><?= $subscription['start_date'] ?></td></tr>
                <tr><th>Berakhir</th><td><?= $subscription['end_date'] ?></td></tr>
                <tr><th>Status</th><td><?= htmlspecialchars($subscription['status']) ?></td></tr>
                <tr><th>Metode Bayar</th><td><?= htmlspecialchars($subscription['payment_method'] ?? '-') ?></td></tr>
            </table>

            <!-- Highlight Sisa Hari -->
            <?php if ($remainingDays !== null): ?>
                <div style="margin-top: 1rem; font-size: 1.1rem;">
                    <?php if ($remainingDays > 0): ?>
                        <span class="badge active">⏳ Sisa <strong><?= $remainingDays ?></strong> hari</span>
                    <?php elseif ($remainingDays == 0): ?>
                        <span class="badge warning">⚠️ Berakhir hari ini</span>
                    <?php else: ?>
                        <span class="badge expired">❌ Kadaluarsa <?= abs($remainingDays) ?> hari lalu</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p class="no-data">Belum ada data langganan.</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>