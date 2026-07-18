<?php
/**
 * API: License Management
 * Membeli lisensi, aktivasi, dan manajemen
 */

require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
$auth = new Auth();
$user = $auth->getCurrentUser();

if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'purchase':
        purchaseLicense($db, $user);
        break;
    case 'activate':
        activateLicense($db, $user);
        break;
    case 'get_plans':
        getPlans($db);
        break;
    case 'my_license':
        getMyLicense($db, $user);
        break;
    case 'check_widget':
        checkWidgetAccess($db);
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

function purchaseLicense($db, $user) {
    $tier = $_POST['tier'] ?? '';
    $paymentProof = $_FILES['payment_proof'] ?? null;
    
    $plan = $db->fetch("SELECT * FROM license_tiers WHERE name = ?", [$tier]);
    if (!$plan) {
        echo json_encode(['success' => false, 'error' => 'Invalid plan']);
        return;
    }
    
    // Upload payment proof
    $proofUrl = null;
    if ($paymentProof && $paymentProof['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/payments/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $filename = 'payment_' . $user['id'] . '_' . time() . '.jpg';
        move_uploaded_file($paymentProof['tmp_name'], $uploadDir . $filename);
        $proofUrl = 'uploads/payments/' . $filename;
    }
    
    // Generate license key
    $licenseKey = 'LC_' . strtoupper(bin2hex(random_bytes(8)));
    
    // Insert purchase
    $db->insert("
        INSERT INTO subscriptions (user_id, license_key, tier, amount, status, start_date, end_date, payment_proof)
        VALUES (?, ?, ?, ?, 'pending', NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), ?)
    ", [$user['id'], $licenseKey, $tier, $plan['monthly_price'], $proofUrl]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Purchase submitted for approval',
        'license_key' => $licenseKey
    ]);
}

function activateLicense($db, $user) {
    $licenseKey = $_POST['license_key'] ?? '';
    $subscription = $db->fetch("
        SELECT * FROM subscriptions 
        WHERE license_key = ? AND status = 'active'
    ", [$licenseKey]);
    
    if (!$subscription) {
        echo json_encode(['success' => false, 'error' => 'Invalid or inactive license']);
        return;
    }
    
    // Update user license
    $db->update("
        UPDATE users SET license_tier = ?, license_end_date = ? WHERE id = ?
    ", [$subscription['tier'], $subscription['end_date'], $user['id']]);
    
    // Update agent license if exists
    $agent = $db->fetch("SELECT id FROM agents WHERE user_id = ?", [$user['id']]);
    if ($agent) {
        $db->update("
            UPDATE agents SET license_tier = ?, license_end_date = ? WHERE id = ?
        ", [$subscription['tier'], $subscription['end_date'], $agent['id']]);
    }
    
    echo json_encode(['success' => true, 'message' => 'License activated']);
}

function getPlans($db) {
    $plans = $db->fetchAll("SELECT * FROM license_tiers ORDER BY monthly_price ASC");
    echo json_encode(['success' => true, 'plans' => $plans]);
}

function getMyLicense($db, $user) {
    $subscription = $db->fetch("
        SELECT s.*, lt.max_agents, lt.max_teams_per_agent, lt.ai_enabled
        FROM subscriptions s
        JOIN license_tiers lt ON s.tier = lt.name
        WHERE s.user_id = ? AND s.status = 'active'
        ORDER BY s.created_at DESC LIMIT 1
    ", [$user['id']]);
    
    $remainingDays = 0;
    if ($subscription && $subscription['end_date']) {
        $remainingDays = ceil((strtotime($subscription['end_date']) - time()) / 86400);
    }
    
    echo json_encode([
        'success' => true,
        'license' => $subscription,
        'remaining_days' => $remainingDays
    ]);
}

function checkWidgetAccess($db) {
    $licenseKey = $_GET['license_key'] ?? '';
    if (!$licenseKey) {
        echo json_encode(['success' => false, 'error' => 'License required']);
        return;
    }
    
    $subscription = $db->fetch("
        SELECT s.*, lt.max_teams_per_agent, lt.ai_enabled
        FROM subscriptions s
        JOIN license_tiers lt ON s.tier = lt.name
        WHERE s.license_key = ? AND s.status = 'active' AND s.end_date > NOW()
    ", [$licenseKey]);
    
    if (!$subscription) {
        echo json_encode(['success' => false, 'error' => 'License expired or inactive']);
        return;
    }
    
    // Get widget config
    $widget = $db->fetch("SELECT * FROM widget_configs WHERE license_key = ?", [$licenseKey]);
    
    echo json_encode([
        'success' => true,
        'license' => $subscription,
        'widget' => $widget
    ]);
}