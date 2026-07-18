<?php
/**
 * api/widget-config.php
 * Widget Configuration API with License Check & IP Ban Check
 */
require_once '../includes/db.php';
require_once '../includes/functions.php'; 
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
header('Content-Type: application/json');

$licenseKey = $_GET['license_key'] ?? '';
$db = Database::getInstance();

if (!$licenseKey) {
    jsonResponse(['success' => false, 'error' => 'Missing license_key'], 400);
}

// Domain validation
$origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
if ($origin) {
    $embedDomains = $db->fetch("SELECT site_url, allowed_domains FROM embed_codes WHERE embed_key = ? AND status = 1", [$licenseKey]);
    if ($embedDomains) {
        $allowed = [];
        if (!empty($embedDomains['allowed_domains'])) {
            $allowed = array_map('trim', explode(',', $embedDomains['allowed_domains']));
        }
        if (!empty($embedDomains['site_url'])) {
            $allowed[] = $embedDomains['site_url'];
        }
        $matched = false;
        $originClean = strtolower(preg_replace('#^https?://#', '', $origin));
        $originClean = preg_replace('#[:/].*$#', '', $originClean);
        foreach ($allowed as $d) {
            $d = strtolower(preg_replace('#^https?://#', '', trim($d)));
            $d = preg_replace('#[:/].*$#', '', $d);
            if ($d === $originClean) { $matched = true; break; }
            if (str_starts_with($d, '*.') && str_ends_with($originClean, substr($d, 1))) { $matched = true; break; }
        }
        if (!$matched) {
            jsonResponse(['success' => false, 'error' => 'Domain not allowed'], 403);
        }
    }
}

// === 1. LOGIKA CEK IP BANNED ===
$visitorIp = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];

try {
    // Gunakan pengecekan yang aman
    $bannedCheck = $db->fetch("SELECT is_banned FROM visitors WHERE ip_address = ? AND is_banned = 1 LIMIT 1", [$visitorIp]);

    if ($bannedCheck) {
        jsonResponse([
            'success' => false, 
            'error'   => 'Access denied', 
            'message' => 'Your IP is banned.'
        ], 403);
        exit;
    }
} catch (PDOException $e) {
    // Jika kolom belum ada, abaikan error agar widget tetap tampil 
    // atau log error-nya secara internal
    error_log("Ban Check Error: " . $e->getMessage());
}
// ===============================
// ===============================

$embed = $db->fetch("SELECT * FROM embed_codes WHERE embed_key = ? AND status = 1", [$licenseKey]);
if (!$embed) {
    jsonResponse(['success' => false, 'error' => 'Invalid license key'], 404);
}

// ★ CHECK LICENSE STATUS
$licenseStatus = 'active';
$remainingDays = null;

$agentData = $db->fetch("
    SELECT a.*, u.id as user_id, u.license_expires, u.license_tier 
    FROM agents a 
    JOIN users u ON a.user_id = u.id 
    WHERE a.id = ?", 
[$embed['agent_id']]);

if ($agentData) {
    // 1. Cek tabel subscriptions
    $subscription = $db->fetch(
        "SELECT * FROM subscriptions WHERE user_id = ? AND status = 'active' AND end_date >= date('now') ORDER BY end_date DESC LIMIT 1",
        [$agentData['user_id']]
    );
    
    if ($subscription) {
        $endDate = new DateTime($subscription['end_date']);
        $now = new DateTime();
        $interval = $now->diff($endDate);
        $remainingDays = $interval->invert ? -($interval->days) : $interval->days;
        
        if ($endDate < $now) {
            $licenseStatus = 'expired';
        }
    } else {
        // 2. Fallback ke user.license_expires
        if (!empty($agentData['license_expires'])) {
            $endDate = new DateTime($agentData['license_expires']);
            $now = new DateTime();
            $interval = $now->diff($endDate);
            $remainingDays = $interval->invert ? -($interval->days) : $interval->days;
            
            if ($endDate < $now) {
                $licenseStatus = 'expired';
            }
        } else {
            $licenseStatus = 'inactive';
        }
    }
}

// ★ STOP PROSES JIKA LISENSI TIDAK AKTIF
if ($licenseStatus !== 'active') {
    jsonResponse([
        'success'        => false,
        'license_status' => $licenseStatus,
        'message'        => 'License expired or inactive. Widget disabled.'
    ], 403);
}

// Lanjutkan jika lisensi ACTIVE
$agentInfo = $db->fetch("
    SELECT a.display_name, a.is_online, a.reply_mode, u.avatar 
    FROM agents a 
    JOIN users u ON a.user_id = u.id
    WHERE a.id = ?", 
[$embed['agent_id']]);

$config = json_decode($embed['widget_config'] ?? '{}', true) ?: [];


$baseUrl = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]/";

$rawAvatar = $agentInfo['avatar'] ?? '';
if (!empty($rawAvatar)) {
    $finalAvatar = (strpos($rawAvatar, 'http') === 0) ? $rawAvatar : $baseUrl . $rawAvatar;
} else {
    $finalAvatar = $baseUrl . "assets/images/default-avatar.png"; 
}

$prechatFields = $config['prechat_fields'] ?? [];
$lcInfoBox = $config['lc_info_box'] ?? 'Silakan isi form berikut untuk memulai chat dengan tim kami.';

$responseConfig = array_merge([
    'primary_color'   => '#1e62ff', 
    'widget_theme'   => 'transparent',
    'position'        => 'right',
    'welcome_message' => 'Halo! Ada yang bisa kami bantu?',
    'pre_chat_form'   => (int)($embed['pre_chat_form'] ?? 1),
    'allow_upload'    => (int)($embed['allow_upload'] ?? 1),
    'show_typing'     => 1,
    'agent_name'      => $agentInfo['display_name'] ?? 'Support',
    'agent_avatar'    => $finalAvatar,
    'is_online'       => $agentInfo['is_online'] ?? 0,
    'reply_mode'      => $agentInfo['reply_mode'] ?? 'hybrid',
    'prechat_fields'  => $prechatFields,
    'lc_info_box'     => $lcInfoBox,
    'widget_version'  => ''
], $config);

jsonResponse([
    'success'        => true,
    'license_status' => $licenseStatus,
    'remaining_days' => $remainingDays,
    'site_name'      => $embed['site_name'],
    'config'         => $responseConfig
]);