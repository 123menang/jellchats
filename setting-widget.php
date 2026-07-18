
<?php
/**
 * setting-widget.php
 * Widget Settings & Customization Page
 */

require_once 'includes/auth.php';
require_once 'includes/functions.php';
$auth->requireAuth();
$user = $auth->getCurrentUser();
$db = Database::getInstance();
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
$baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];

$agent = $db->fetch("SELECT * FROM agents WHERE user_id = ?", [$user['id']]);
if (!$agent) {
    die("Agent profile not found.");
}
$agentId = $agent['id'];

// Ambil data konfigurasi widget yang sudah ada
$config = $db->fetch("SELECT * FROM embed_codes WHERE agent_id = ?", [$agentId]);

// Subscription & License
$subscriptionData = getAgentSubscription($user['id']);
$subscription = $subscriptionData['subscription'] ?? null;
$licenseId = $config ? $config['embed_key'] : generateEmbedKey();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $siteName    = $_POST['site_name'] ?? 'My Website';
    $siteUrl     = $_POST['site_url'] ?? '';
    $preChat     = isset($_POST['pre_chat_form']) ? 1 : 0;
    $allowUpload = isset($_POST['allow_upload']) ? 1 : 0;
    $lcInfoBox   = $_POST['lc_info_box'] ?? '';
    $primaryColor = $_POST['primary_color'] ?? '#3dfc89';
    $widgetTheme = $_POST['widget_theme'] ?? 'light';

    $fields = [];
    if (isset($_POST['field_labels'])) {
        foreach ($_POST['field_labels'] as $index => $label) {
            if (!empty($label)) {
                $type = $_POST['field_types'][$index];
                $fieldOptions = [];
                if ($type === 'select' && isset($_POST['field_choices'][$index])) {
                    $fieldOptions = array_filter($_POST['field_choices'][$index], fn($v) => trim($v) !== '');
                }
                $fields[] = [
                    'label'    => $label,
                    'type'     => $type,
                    'required' => isset($_POST['field_required'][$index]) ? 1 : 0,
                    'options'  => array_values($fieldOptions)
                ];
            }
        }
    }

    // Handle avatar upload
    $avatarUrl = $widgetConfig['agent_avatar'] ?? '';
    if (isset($_FILES['agent_avatar']) && $_FILES['agent_avatar']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['agent_avatar']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            $dir = __DIR__ . '/assets/uploads/avatars';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $filename = 'agent_' . $agentId . '_' . time() . '.' . $ext;
            move_uploaded_file($_FILES['agent_avatar']['tmp_name'], $dir . '/' . $filename);
            $avatarUrl = $baseUrl . '/assets/uploads/avatars/' . $filename;
        }
    }

    $agentTitle = $_POST['agent_title'] ?? 'Customer Support';
    $agentStatus = $_POST['agent_status'] ?? 'Online';

    $widgetVersion = time();
    $widgetData = json_encode([
        'primary_color'   => $primaryColor,
        'widget_theme'    => $widgetTheme,
        'agent_name'      => $_POST['agent_name'] ?? $agent['display_name'],
        'agent_title'     => $agentTitle,
        'agent_status'    => $agentStatus,
        'agent_avatar'    => $avatarUrl,
        'welcome_msg'     => $_POST['welcome_msg'] ?? 'Halo! Ada yang bisa kami bantu?',
        'prechat_fields'  => $fields,
        'lc_info_box'     => $lcInfoBox,
        'widget_version'  => $widgetVersion
    ]);

    if ($config) {
        $db->query("UPDATE embed_codes SET site_name=?, site_url=?, widget_config=?, pre_chat_form=?, allow_upload=? WHERE agent_id=?",
            [$siteName, $siteUrl, $widgetData, $preChat, $allowUpload, $agentId]);
    } else {
        $db->query("INSERT INTO embed_codes (agent_id, site_name, site_url, embed_key, widget_config, pre_chat_form, allow_upload) VALUES (?,?,?,?,?,?,?)",
            [$agentId, $siteName, $siteUrl, $licenseId, $widgetData, $preChat, $allowUpload]);
    }
    header("Location: setting-widget?success=1");
    exit;
}

$widgetConfig = $config ? json_decode($config['widget_config'], true) : [];
$uAva = $widgetConfig['agent_avatar'] ?? '';
$prechatFields = $widgetConfig['prechat_fields'] ?? [
    ['label' => 'Username:', 'type' => 'text', 'required' => 1, 'options' => []],
    ['label' => 'Whatsapp:', 'type' => 'number', 'required' => 1, 'options' => []],
    ['label' => 'Pertanyaan:', 'type' => 'select', 'required' => 1, 'options' => ['Deposit / Withdraw', 'Lupa password', 'Kendala Lainnya']]
];

// Perbaikan variabel untuk embed key dan direct link
$currentEmbedKey = $config['embed_key'] ?? $licenseId;
$directChatUrl = $baseUrl . "/chat-with/" . $currentEmbedKey;

$pageTitle = 'Widget Settings - LiveChat Admin';
$activePage = 'settings';

include 'includes/layout-header.php';
?>
<style>
    
/* ============================================
   WIDGET SETTINGS PAGE - SPECIFIC STYLES
   ============================================ */
.widget-settings-page {
  padding: 0 !important;
  overflow: hidden;
}

.widget-builder-layout {
  display: flex;
  height: calc(100vh - 60px);
  overflow: hidden;
}

/* Sub Sidebar */
.sub-sidebar {
  width: 260px;
  border-right: 1px solid var(--border-color);
  background: var(--bg-dark);
  padding: 20px 0;
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
  overflow-y: auto;
}
.sub-sidebar-header {
  padding: 0 25px 20px;
  font-size: 12px;
  font-weight: 700;
  color: #9ca3af;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}
.sub-sidebar-item {
  padding: 12px 25px;
  color: #4b5563;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 12px;
  transition: all 0.2s;
  border: none;
  background: transparent;
  width: 100%;
  text-align: left;
}
.sub-sidebar-item:hover {
  background: #f9fafb;
  color: var(--text-light);
}
.sub-sidebar-item.active {
  background: #373748;
  color: #fff;
  border-right: 3px solid var(--accent-blue);
}
.sub-sidebar-item i {
  width: 20px;
  text-align: center;
}

/* Builder Column */
.builder-column {
  flex: 1;
  padding: 40px;
  border-right: 1px solid var(--border-color);
  box-sizing: border-box;
  overflow-y: auto;
  max-width: 900px;
  min-width: 0;
}

/* Preview Column */
.preview-column {
  flex: 1;
  background: #f0f2f5;
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding-top: 60px;
  position: sticky;
  top: 0;
  height: 100vh;
  overflow-y: auto;
  min-width: 380px;
}

/* Tab Content */
.tab-content {
  display: none;
  animation: fadeIn 0.3s ease-in-out;
}
.tab-content.active {
  display: block;
}
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(5px); }
  to { opacity: 1; transform: translateY(0); }
}

/* Field Row Wrapper */
.field-row-wrapper {
  background: #fff;
  border: 1px solid var(--border-color);
  border-radius: var(--radius-md);
  margin-bottom: 15px;
  overflow: hidden;
}
.field-header {
  padding: 12px 15px;
  border-bottom: 1px solid var(--border-light);
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #fff;
}
.field-body {
  padding: 15px;
}

/* Code Box */
.code-box {
  background: #1e1e1e;
  color: #d4d4d4;
  padding: 20px;
  border-radius: var(--radius-md);
  font-family: 'Courier New', monospace;
  font-size: 13px;
  line-height: 1.6;
  position: relative;
  overflow-x: auto;
}
.copy-btn {
  position: absolute;
  top: 10px;
  right: 10px;
  background: #444;
  color: #fff;
  border: none;
  padding: 5px 10px;
  border-radius: var(--radius-sm);
  cursor: pointer;
  font-size: 11px;
  transition: 0.2s;
}
.copy-btn:hover {
  background: #555;
}

/* Color Picker */
.color-picker-wrapper {
  position: relative;
  display: flex;
  align-items: center;
  gap: 15px;
  background: #f9fafb;
  padding: 10px;
  border-radius: var(--radius-md);
  border: 1px solid var(--border-color);
}
input[type="color"] {
  border-radius: 50%;
  border: none;
  width: 40px;
  height: 40px;
  cursor: pointer;
  background: none;
  padding: 0;
}
input[type="color"]::-webkit-color-swatch-wrapper {
  padding: 0;
}
input[type="color"]::-webkit-color-swatch {
  border-radius: 50%;
  border: 2px solid var(--border-color);
}

/* Choice Items */
.choice-item {
  margin-bottom: 8px;
  display: flex;
  align-items: center;
  gap: 10px;
}
.btn-add-answer {
  background: none;
  border: 1px dashed #d1d5db;
  width: 100%;
  padding: 8px;
  border-radius: var(--radius-sm);
  color: #6b7280;
  font-size: 12px;
  cursor: pointer;
  transition: 0.2s;
}
.btn-add-answer:hover {
  border-color: var(--accent-blue);
  color: var(--accent-blue);
  background: #eff6ff;
}
.btn-remove-choice {
  background: none;
  border: none;
  color: #9ca3af;
  cursor: pointer;
  font-size: 18px;
  transition: 0.2s;
}
.btn-remove-choice:hover {
  color: var(--danger);
}

/* Add Field Button */
.btn-add-field {
  background: white;
  border: 2px solid var(--border-color);
  width: 100%;
  padding: 12px;
  border-radius: var(--radius-md);
  color: var(--text-light);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin-bottom: 20px;
}
.btn-add-field:hover {
  border-color: var(--accent-blue);
  color: var(--accent-blue);
  background: #eff6ff;
}

/* Mock Widget Preview */
.mock-widget {
  width: 340px;
  background: #fff;
  border-radius: 25px;
  box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
  overflow: hidden;
  position: relative;
  font-family: 'Inter', sans-serif;
  transition: background 0.3s;
  flex-shrink: 0;
}
.mock-widget.theme-dark {
  background: #1c1d1e;
  color: #fff;
}
.mock-widget.theme-dark .mock-header,
.mock-widget.theme-dark .mock-body,
.mock-widget.theme-dark .agent-pill,
.mock-widget.theme-dark .mock-info-box {
  background: #1c1d1e;
  color: #fff;
  border-color: #374151;
}
.mock-widget.theme-dark .mock-label {
  color: #fff;
}
.mock-widget.theme-dark .mock-input,
.mock-widget.theme-dark .mock-select {
  background: #282828;
  border-color: #333;
  color: #fff;
}
.mock-widget.theme-dark #previewAgentName {
  color: #fff !important;
}
.mock-widget.theme-dark #poweredBy {
  background: #111827 !important;
  color: #9ca3af;
}

.mock-header {
  background: #fff;
  padding: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  border-bottom: 1px solid #f3f4f6;
}
.agent-pill {
  background: #fff;
  border-radius: 50px;
  padding: 8px 20px;
  display: flex;
  align-items: center;
  gap: 12px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.05);
  border: 1px solid #f3f4f6;
}
.agent-avatar {
  width: 45px;
  height: 45px;
  border-radius: 50%;
  object-fit: cover;
  background: #eee;
}
.agent-avatar-fallback {
  width: 45px;
  height: 45px;
  border-radius: 50%;
  background: var(--accent-blue);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 18px;
}
.mock-body {
  padding: 20px;
  background: #fff;
}
.mock-info-box {
  background: #fff;
  border-left: 4px solid #3b82f6;
  border-radius: 12px;
  padding: 15px;
  font-size: 13px;
  font-weight: 600;
  line-height: 1.5;
  color: #1f2937;
  box-shadow: 0 4px 15px rgba(0,0,0,0.05);
  margin-bottom: 20px;
  display: flex;
  gap: 10px;
}
.mock-label {
  display: block;
  font-size: 13px;
  font-weight: 700;
  color: #1f2937;
  margin-bottom: 8px;
}
.mock-input {
  width: 100%;
  height: 45px;
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  margin-bottom: 15px;
}
.mock-select {
  width: 100%;
  height: 45px;
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  margin-bottom: 15px;
  padding: 0 15px;
  color: #6b7280;
  -webkit-appearance: none;
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236b7280' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 15px center;
}
.mock-btn {
  width: 100%;
  background: #3dfc89;
  color: #fff;
  border: none;
  padding: 14px;
  border-radius: 15px;
  font-weight: 700;
  font-size: 15px;
  cursor: pointer;
  margin-top: 10px;
  transition: 0.2s;
}
.mock-btn:hover {
  opacity: 0.9;
}

/* Floating Launcher Preview */
#floatingLauncher {
  position: fixed;
  bottom: 30px;
  right: 30px;
  width: 70px;
  height: 70px;
  background: #3dfc89;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 32px;
  box-shadow: 0 10px 25px rgba(61, 252, 137, 0.4);
  border: 4px solid #fff;
  z-index: 100;
  transition: 0.3s;
}
#floatingLauncher:hover {
  transform: scale(1.1);
}

/* ============================================
   MOBILE RESPONSIVE - WIDGET SETTINGS
   ============================================ */
@media (max-width: 768px) {
  .widget-builder-layout {
    flex-direction: column;
    height: auto;
    overflow: visible;
  }

  .sub-sidebar {
    width: 100%;
    flex-direction: row;
    padding: 10px;
    gap: 8px;
    overflow-x: auto;
    border-right: none;
    border-bottom: 1px solid var(--border-color);
  }
  .sub-sidebar-header {
    display: none;
  }
  .sub-sidebar-item {
    padding: 8px 16px;
    white-space: nowrap;
    border-radius: var(--radius-sm);
  }
  .sub-sidebar-item.active {
    border-right: none;
    border-bottom: 3px solid var(--accent-blue);
  }

  .builder-column {
    padding: 20px;
    border-right: none;
    max-width: 100%;
    overflow: visible;
  }

  .preview-column {
    position: static;
    height: auto;
    min-height: 400px;
    padding: 40px 20px;
    min-width: auto;
  }

  .mock-widget {
    width: 100%;
    max-width: 340px;
  }

  #floatingLauncher {
    position: relative;
    bottom: auto;
    right: auto;
    margin: 20px auto 0;
  }

  .field-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 10px;
  }

  .choice-item {
    flex-direction: column;
    align-items: stretch;
  }
  .choice-item .btn-remove-choice {
    align-self: flex-end;
  }
}

@media (max-width: 480px) {
  .builder-column {
    padding: 15px;
  }

  .field-body {
    padding: 10px;
  }

  .mock-widget {
    border-radius: 16px;
  }

  .mock-header {
    padding: 15px;
  }

  .mock-body {
    padding: 15px;
  }
}
</style>

<div class="page-content widget-settings-page">
    <div class="widget-builder-layout">
        <!-- Sub Sidebar -->
        <div class="sub-sidebar">
            <div class="sub-sidebar-header">Installation & Setup</div>
            <div class="sub-sidebar-item" onclick="switchTab('script-tab', this)">
                <i class="fas fa-code"></i> Installation Script
            </div>
            <div class="sub-sidebar-item" onclick="switchTab('chatpage-tab', this)">
                <i class="fas fa-link"></i> Chat Page Link
            </div>
            <div class="sub-sidebar-item active" onclick="switchTab('widget-tab', this)">
                <i class="fas fa-sliders-h"></i> Widget Customization
            </div>
        </div>

        <!-- Builder Column -->
        <div class="builder-column">
            <div id="script-tab" class="tab-content">
                <h2 style="font-size: 22px; font-weight: 800; margin-bottom: 10px;">Connect your website</h2>
                <p style="color: #6b7280; font-size: 14px; margin-bottom: 30px;">Copy and paste this code into the <code>&lt;head&gt;</code> of every page you want the widget to appear on.</p>
                <div class="code-box">
                    <button class="copy-btn" onclick="copyCode(this)">Copy</button>
                    <code id="scriptCode">
&lt;script src="<?= $baseUrl ?>/widget/widget.js?v=<?= $widgetConfig['widget_version'] ?? time() ?>" license="<?= $currentEmbedKey ?>" async&gt;&lt;/script&gt;
						
                    </code>
                </div>
            </div>

            <div id="chatpage-tab" class="tab-content">
                <h2 style="font-size: 22px; font-weight: 800; margin-bottom: 10px;">Direct Chat Link</h2>
                <p style="color: #6b7280; font-size: 14px; margin-bottom: 30px;">Share this link directly with your customers.</p>
                <div class="field-row-wrapper" style="padding: 20px;">
                    <div class="form-group">
                        <?php if (isset($_GET['success'])): ?>
                        <div class="alert alert-success" style="margin-bottom:24px;">
                            <i class="fas fa-check-circle"></i> Widget settings saved successfully!
                        </div>
                        <?php endif; ?>
                        <label>Public Chat URL</label>
                        <div style="display:flex; gap:10px;">
                            <input type="text" class="form-control" value="<?= $directChatUrl ?>" readonly>
                            <button type="button" class="btn-primary" style="width:auto; margin:0;" onclick="copyToClipboard('<?= $directChatUrl ?>')">Copy</button>
                        </div>
                    </div>
                </div>
            </div>

            <div id="widget-tab" class="tab-content active">
                <div style="margin-bottom: 30px;">
                    <h2 style="font-size: 22px; font-weight: 800; margin-bottom: 5px;">Widget Settings</h2>
                    <p style="color: #6b7280; font-size: 14px;">Personalize the look and feel of your chat widget.</p>
                </div>

                <form method="POST" id="mainForm" enctype="multipart/form-data">
                    <div class="field-row-wrapper">
                        <div class="field-header">
                            <span style="font-size: 12px; font-weight: 700;"><i class="fas fa-palette"></i> Widget Visual</span>
                        </div>
                        <div class="field-body">
                            <div class="form-group" style="margin-bottom: 15px;">
                                <label>Widget Theme</label>
                                <select name="widget_theme" class="form-control" onchange="updatePreview()">
                                    <option value="light" <?= ($widgetConfig['widget_theme'] ?? 'light') == 'light' ? 'selected' : '' ?>>Light Theme</option>
                                    <option value="dark" <?= ($widgetConfig['widget_theme'] ?? 'light') == 'dark' ? 'selected' : '' ?>>Dark Theme</option>
                                </select>
                            </div>
                            <div class="color-picker-wrapper">
                                <input type="color" name="primary_color" value="<?= $widgetConfig['primary_color'] ?? '#3dfc89' ?>" oninput="updatePreview()">
                                <div>
                                    <div style="font-size: 13px; font-weight: 700;">Accent Color</div>
                                    <div style="font-size: 11px; color: #6b7280;">Applies to buttons and launcher.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="field-row-wrapper">
                        <div class="field-header">
                            <span style="font-size: 12px; font-weight: 700;"><i class="fas fa-info-circle"></i> Information Box</span>
                        </div>
                        <div class="field-body">
                            <div class="form-group">
                                <label>Announcement / Guide Message</label>
                                <textarea name="lc_info_box" class="form-control" rows="2" oninput="updatePreview()"><?= htmlspecialchars($widgetConfig['lc_info_box'] ?? 'Info : Silahkan isi form dibawah ini untuk memulai chat.') ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div id="fieldsContainer">
                        <?php foreach ($prechatFields as $idx => $field): ?>
                        <div class="field-row-wrapper" data-index="<?= $idx ?>">
                            <div class="field-header">
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <i class="fa-solid fa-grip-vertical" style="color:#d1d5db;"></i>
                                    <select name="field_types[]" class="form-control" style="width:110px; height:30px; padding:0 8px; font-size:11px;" onchange="toggleFieldType(this)">
                                        <option value="text" <?= $field['type'] == 'text' ? 'selected' : '' ?>>Text</option>
                                        <option value="number" <?= $field['type'] == 'number' ? 'selected' : '' ?>>Number</option>
                                        <option value="select" <?= $field['type'] == 'select' ? 'selected' : '' ?>>Dropdown</option>
                                    </select>
                                </div>
                                <div style="display:flex; align-items:center; gap:15px;">
                                    <label style="font-size:11px; display:flex; align-items:center; gap:5px; cursor:pointer;">
                                        <input type="checkbox" name="field_required[<?= $idx ?>]" value="1" <?= ($field['required'] ?? 0) ? 'checked' : '' ?>> Required
                                    </label>
                                    <button type="button" class="btn-remove-choice" onclick="this.closest('.field-row-wrapper').remove(); updatePreview();"><i class="fa-solid fa-trash-can" style="font-size:14px;"></i></button>
                                </div>
                            </div>
                            <div class="field-body">
                                <div class="form-group">
                                    <label>Field Label</label>
                                    <input type="text" name="field_labels[]" class="form-control" value="<?= htmlspecialchars($field['label']) ?>" oninput="updatePreview()">
                                </div>
                                <div class="choices-container" style="display: <?= $field['type'] == 'select' ? 'block' : 'none' ?>; margin-top:15px;">
                                    <div class="choices-list">
                                        <?php if(!empty($field['options'])): foreach($field['options'] as $cIdx => $opt): ?>
                                        <div class="choice-item">
                                            <input type="text" name="field_choices[<?= $idx ?>][]" class="form-control" value="<?= htmlspecialchars($opt) ?>" oninput="updatePreview()">
                                            <button type="button" class="btn-remove-choice" onclick="this.closest('.choice-item').remove(); updatePreview();">&times;</button>
                                        </div>
                                        <?php endforeach; endif; ?>
                                    </div>
                                    <button type="button" class="btn-add-answer" onclick="addChoice(this, <?= $idx ?>)" style="margin-top:10px;">+ Add choice</button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <button type="button" class="btn-add-field" onclick="addNewField()"><i class="fas fa-plus"></i> Add Field Element</button>

                    <div class="field-row-wrapper" style="margin-top: 20px;">
                        <div class="field-header">
                            <span style="font-size: 12px; font-weight: 700;"><i class="fas fa-globe"></i> Website & Agent Identity</span>
                        </div>
                        <div class="field-body">
                            <div class="form-group" style="margin-bottom: 15px;">
                                <label>Website Name</label>
                                <input type="text" name="site_name" class="form-control" value="<?= htmlspecialchars($config['site_name'] ?? 'My Website') ?>">
                            </div>
                            <div class="form-group">
                                <label>Agent Name in Widget</label>
                                <input type="text" name="agent_name" class="form-control" value="<?= htmlspecialchars($widgetConfig['agent_name'] ?? $agent['display_name']) ?>" oninput="updatePreview()">
                            </div>
                            <div class="form-group" style="margin-top:15px;">
                                <label>Agent Avatar</label>
                                <input type="file" name="agent_avatar" class="form-control" accept="image/*" onchange="previewAvatar(this)">
                                <div style="margin-top:8px;">
                                    <img id="avatarPreview" src="<?= htmlspecialchars($widgetConfig['agent_avatar'] ?? '/assets/images/default-avatar.png') ?>" style="width:48px;height:48px;border-radius:50%;object-fit:cover;border:2px solid var(--border-color);" onerror="this.src='/assets/images/default-avatar.png'">
                                </div>
                            </div>
                            <div class="form-group" style="margin-top:15px;">
                                <label>Agent Title <small style="color:#999;">(shown under name in widget)</small></label>
                                <input type="text" name="agent_title" class="form-control" value="<?= htmlspecialchars($widgetConfig['agent_title'] ?? 'Customer Support') ?>" oninput="updatePreview()" placeholder="e.g. Customer Support">
                            </div>
                            <div class="form-group" style="margin-top:15px;">
                                <label>Online Status Text</label>
                                <input type="text" name="agent_status" class="form-control" value="<?= htmlspecialchars($widgetConfig['agent_status'] ?? 'Online') ?>" oninput="updatePreview()" placeholder="e.g. Online">
                            </div>
                            <div class="form-group" style="margin-top:15px;">
                                <label>Welcome Message <small style="color:#999;">(shown in pre-chat form)</small></label>
                                <textarea name="welcome_msg" class="form-control" rows="2" placeholder="Halo! Ada yang bisa kami bantu?"><?= htmlspecialchars($widgetConfig['welcome_msg'] ?? 'Halo! Ada yang bisa kami bantu?') ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div style="margin-top:40px; border-top: 1px solid var(--border-color); padding-top: 20px; text-align:right;">
                        <button type="submit" class="btn-primary" style="width: auto; padding: 10px 30px;">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Preview Column -->
        <div class="preview-column">
            <div class="mock-widget" id="widgetPreview">
                <div class="mock-header">
                    <div class="agent-pill">
                        <img src="<?= htmlspecialchars($uAva ?: '/assets/images/default-avatar.png') ?>" class="agent-avatar" alt="Agent" onerror="this.style.display='none';this.parentNode.innerHTML='<div class=\'agent-avatar-fallback\'>'+'<?= strtoupper(substr($agent['display_name'] ?? 'A',0,1)) ?>'+'</div>'">
                        <div>
                            <div id="previewAgentName" style="font-weight:800; font-size:14px; color:#1f2937;"><?= htmlspecialchars($widgetConfig['agent_name'] ?? $agent['display_name']) ?></div>
                            <div id="previewAgentTitle" style="font-size:11px; color:#9ca3af; font-weight:500;"><?= htmlspecialchars($widgetConfig['agent_title'] ?? 'Customer Support') ?> &middot; <?= htmlspecialchars($widgetConfig['agent_status'] ?? 'Online') ?></div>
                        </div>
                    </div>
                </div>

                <div class="mock-body">
                    <div id="previewInfo" class="mock-info-box">
                        <i class="fas fa-info-circle" style="color:#3b82f6; font-size:18px;"></i>
                        <span id="infoTextContainer"></span>
                    </div>
                    <div id="previewFields"></div>
                    <button type="button" class="mock-btn" id="submitBtnPreview">Mulai Chat</button>
                </div>

                <div style="padding:15px; text-align:center; font-size:11px; color:#9ca3af; background: #f9fafb;" id="poweredBy">
                    Powered by <span style="font-weight:700; color:#4b5563;">LiveChat</span>
                </div>
            </div>

            <div id="floatingLauncher" style="position:fixed; bottom: 30px; right: 30px; width: 70px; height: 70px; background: #3dfc89; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 32px; box-shadow: 0 10px 25px rgba(61, 252, 137, 0.4); border: 4px solid #fff;">
                <i class="fa-solid fa-comment-dots"></i>
            </div>
        </div>
    </div>
</div>

<script>
let fieldCount = <?= count($prechatFields) ?>;

function switchTab(tabId, el) {
    document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
    document.querySelectorAll('.sub-sidebar-item').forEach(item => item.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    el.classList.add('active');
}

function updatePreview() {
    const colorInput = document.getElementsByName('primary_color')[0];
    if(!colorInput) return;
    const color = colorInput.value;
    const theme = document.getElementsByName('widget_theme')[0].value;
    const btn = document.getElementById('submitBtnPreview');
    const launcher = document.getElementById('floatingLauncher');
    const widget = document.getElementById('widgetPreview');
    const powered = document.getElementById('poweredBy');

    // Update Theme
    if(theme === 'dark') {
        widget.classList.add('theme-dark');
        powered.style.background = '#111827';
    } else {
        widget.classList.remove('theme-dark');
        powered.style.background = '#f9fafb';
    }

    // Update Colors
    btn.style.backgroundColor = color;
    launcher.style.backgroundColor = color;
    launcher.style.boxShadow = `0 10px 25px ${color}66`;

    // Update Agent Name
    const agentName = document.getElementsByName('agent_name')[0].value;
    document.getElementById('previewAgentName').innerText = agentName;

    // Update Agent Title & Status
    const agentTitle = document.getElementsByName('agent_title')[0].value;
    const agentStatus = document.getElementsByName('agent_status')[0].value;
    const previewTitle = document.getElementById('previewAgentTitle');
    if (previewTitle) previewTitle.innerText = (agentTitle || 'Customer Support') + ' \u00B7 ' + (agentStatus || 'Online');

    const infoText = document.getElementsByName('lc_info_box')[0].value;
    const infoPreview = document.getElementById('previewInfo');
    document.getElementById('infoTextContainer').innerText = infoText;
    infoPreview.style.display = infoText.trim() ? 'flex' : 'none';

    const fieldContainer = document.getElementById('previewFields');
    fieldContainer.innerHTML = '';

    document.querySelectorAll('.field-row-wrapper[data-index]').forEach(wrap => {
        const labelInput = wrap.querySelector('[name="field_labels[]"]');
        if(!labelInput) return;
        const label = labelInput.value;
        const type = wrap.querySelector('[name="field_types[]"]').value;

        if(label.trim()) {
            const lbl = document.createElement('label');
            lbl.className = 'mock-label';
            lbl.innerText = label;
            fieldContainer.appendChild(lbl);

            if(type === 'select') {
                const sel = document.createElement('select');
                sel.className = 'mock-select';
                sel.innerHTML = '<option>-- pilih --</option>';
                wrap.querySelectorAll('.choices-list input').forEach(c => {
                    if(c.value.trim()) {
                        const opt = document.createElement('option');
                        opt.text = c.value;
                        sel.appendChild(opt);
                    }
                });
                fieldContainer.appendChild(sel);
            } else {
                const inp = document.createElement('div');
                inp.className = 'mock-input';
                fieldContainer.appendChild(inp);
            }
        }
    });
}

function addNewField() {
    const idx = fieldCount++;
    const html = `
    <div class="field-row-wrapper" data-index="${idx}">
        <div class="field-header">
            <div style="display:flex; align-items:center; gap:10px;">
                <i class="fa-solid fa-grip-vertical" style="color:#d1d5db;"></i>
                <select name="field_types[]" class="form-control" style="width:110px; height:30px; padding:0 8px; font-size:11px;" onchange="toggleFieldType(this)">
                    <option value="text">Text</option>
                    <option value="number">Number</option>
                    <option value="select">Dropdown</option>
                </select>
            </div>
            <div style="display:flex; align-items:center; gap:15px;">
                <label style="font-size:11px; display:flex; align-items:center; gap:5px; cursor:pointer;">
                    <input type="checkbox" name="field_required[${idx}]" value="1" checked> Required
                </label>
                <button type="button" class="btn-remove-choice" onclick="this.closest('.field-row-wrapper').remove(); updatePreview();"><i class="fa-solid fa-trash-can" style="font-size:14px;"></i></button>
            </div>
        </div>
        <div class="field-body">
            <div class="form-group">
                <label>Field Label</label>
                <input type="text" name="field_labels[]" class="form-control" placeholder="Enter label..." oninput="updatePreview()">
            </div>
            <div class="choices-container" style="display:none; margin-top:15px;">
                <div class="choices-list"></div>
                <button type="button" class="btn-add-answer" onclick="addChoice(this, ${idx})" style="margin-top:10px;">+ Add choice</button>
            </div>
        </div>
    </div>`;
    document.getElementById('fieldsContainer').insertAdjacentHTML('beforeend', html);
}

function addChoice(btn, fieldIdx) {
    const list = btn.previousElementSibling;
    const html = `
    <div class="choice-item">
        <input type="text" name="field_choices[${fieldIdx}][]" class="form-control" placeholder="Option value" oninput="updatePreview()">
        <button type="button" class="btn-remove-choice" onclick="this.closest('.choice-item').remove(); updatePreview();">&times;</button>
    </div>`;
    list.insertAdjacentHTML('beforeend', html);
}

function toggleFieldType(select) {
    const wrapper = select.closest('.field-row-wrapper');
    wrapper.querySelector('.choices-container').style.display = (select.value === 'select') ? 'block' : 'none';
    updatePreview();
}

function previewAvatar(input) {
    const file = input.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('avatarPreview').src = e.target.result;
    };
    reader.readAsDataURL(file);
}

function copyCode(btn) {
    const code = document.getElementById('scriptCode').innerText;
    navigator.clipboard.writeText(code).then(() => {
        const oldText = btn.innerText;
        btn.innerText = 'Copied!';
        setTimeout(() => btn.innerText = oldText, 2000);
    });
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        alert('Copied to clipboard!');
    });
}

document.addEventListener('DOMContentLoaded', updatePreview);
</script>

<?php include 'includes/layout-footer.php'; ?>