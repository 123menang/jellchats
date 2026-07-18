<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Pastikan Auth sudah di-handle
$auth = Auth::getInstance();
$auth->requireAuth();

$db = Database::getInstance();

// 1. DATA LOGIC
$activePage = 'Traffic'; 
$pageTitle  = "Traffic - LiveChat Console";

// Ambil data statistik ringkas
$stats = [
    'total' => $db->fetch("SELECT COUNT(*) as c FROM visitors")['c'] ?? 0,
    'chatting' => $db->fetch("SELECT COUNT(DISTINCT visitor_id) as c FROM conversations WHERE status = 'active'")['c'] ?? 0,
];

// Ambil data visitor (Traffic)
$visitors = $db->fetchAll("SELECT * FROM visitors ORDER BY last_visit DESC");

// Helper Functions
if (!function_exists('getInitials')) {
    function getInitials($name) {
        return strtoupper(substr($name, 0, 1));
    }
}

if (!function_exists('getAvatarColor')) {
    function getAvatarColor($name) {
        $colors = ['#ef4444', '#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4', '#84cc16', '#d946ef', '#14b8a6'];
        return $colors[ord($name[0] ?? 'V') % count($colors)];
    }
}

if (!function_exists('formatDuration')) {
    function formatDuration($seconds) {
        if (!$seconds) return '-';
        if ($seconds < 60) return $seconds . "s";
        $m = floor($seconds / 60);
        $s = $seconds % 60;
        return "{$m}m {$s}s";
    }
}

include 'includes/layout-header.php';
?>

<style>
/* ========== RESPONSIVE TRAFFIC PAGE ========== */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

.engage-wrapper {
    display: flex;
    min-height: calc(100vh - 60px);
    background: #000;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

/* ========== SIDEBAR ========== */
.sidebar {
    width: 260px;
    background: #0a0a0a;
    border-right: 1px solid #1e1e1e;
    display: flex;
    flex-direction: column;
    transition: transform 0.3s ease;
    z-index: 100;
}

.sidebar-header {
    padding: 24px 20px;
    border-bottom: 1px solid #1e1e1e;
}

.sidebar-header h2 {
    font-size: 20px;
    font-weight: 700;
    background: linear-gradient(135deg, #fff 0%, #94a3b8 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.nav-item {
    padding: 12px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    transition: all 0.2s;
    color: #9ca3af;
    border-left: 3px solid transparent;
}

.nav-item:hover {
    background: rgba(255,255,255,0.05);
    color: #fff;
}

.nav-item.active {
    background: rgba(59,130,246,0.1);
    border-left-color: #3b82f6;
    color: #fff;
}

.nav-badge {
    font-size: 11px;
    background: #1e1e1e;
    padding: 2px 8px;
    border-radius: 12px;
}

/* ========== MAIN CONTENT ========== */
.main-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: #fff;
    overflow: hidden;
}

/* Header */
.content-header {
    padding: 16px 24px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #fff;
    flex-wrap: wrap;
    gap: 12px;
}

.header-title {
    display: flex;
    align-items: center;
    gap: 12px;
}

.header-title i {
    font-size: 20px;
    color: #64748b;
}

.header-title span {
    font-weight: 600;
    font-size: 18px;
    color: #1e293b;
}

/* Tabs */
.tabs-container {
    padding: 0 24px;
    border-bottom: 1px solid #e2e8f0;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.tabs {
    display: flex;
    gap: 24px;
    min-width: max-content;
}

.tab {
    padding: 14px 0;
    font-size: 13px;
    font-weight: 500;
    color: #64748b;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: all 0.2s;
    white-space: nowrap;
}

.tab:hover {
    color: #3b82f6;
}

.tab.active {
    color: #3b82f6;
    border-bottom-color: #3b82f6;
}

.tab-count {
    margin-left: 4px;
    color: #94a3b8;
    font-size: 12px;
}

/* Filter Bar */
.filter-bar {
    padding: 16px 24px;
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: center;
    border-bottom: 1px solid #f1f5f9;
    background: #fafbfc;
}

.filter-icon {
    background: #3b82f6;
    color: #fff;
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.filter-select {
    padding: 8px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 13px;
    background: #fff;
    color: #1e293b;
    cursor: pointer;
}

.filter-btn {
    padding: 8px 14px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #fff;
    font-size: 13px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
}

.filter-btn:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
}

/* ========== RESPONSIVE TABLE ========== */
.table-container {
    flex: 1;
    overflow-x: auto;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    min-width: 800px;
}

.data-table thead {
    background: #f8fafc;
    position: sticky;
    top: 0;
    z-index: 10;
}

.data-table th {
    padding: 14px 16px;
    text-align: left;
    font-weight: 600;
    color: #475569;
    border-bottom: 1px solid #e2e8f0;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.data-table td {
    padding: 14px 16px;
    border-bottom: 1px solid #f1f5f9;
    color: #334155;
    vertical-align: middle;
}

.data-table tr {
    cursor: pointer;
    transition: background 0.2s;
}

.data-table tr:hover {
    background: #f8fafc;
}

/* Visitor Cell */
.visitor-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}

.visitor-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 14px;
    color: #fff;
    flex-shrink: 0;
}

.visitor-name {
    font-weight: 600;
    color: #1e293b;
    white-space: nowrap;
}

/* Status Badge */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
}

.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
}

.status-dot.online {
    background: #22c55e;
    box-shadow: 0 0 0 2px rgba(34,197,94,0.2);
    animation: pulse 2s infinite;
}

.status-dot.offline {
    background: #94a3b8;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.6; }
}

/* Country Flag */
.country-cell {
    display: flex;
    align-items: center;
    gap: 8px;
    white-space: nowrap;
}

.country-flag {
    width: 20px;
    height: 14px;
    border-radius: 2px;
    object-fit: cover;
}

/* Mobile Menu Button */
.mobile-menu-btn {
    display: none;
    position: fixed;
    bottom: 20px;
    right: 20px;
    width: 50px;
    height: 50px;
    background: #3b82f6;
    border-radius: 50%;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 20px;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    z-index: 200;
    border: none;
    transition: all 0.2s;
}

.mobile-menu-btn:hover {
    transform: scale(1.05);
    background: #2563eb;
}

.sidebar-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 90;
}

/* ========== RESPONSIVE BREAKPOINTS ========== */

/* Tablet (768px - 1024px) */
@media (max-width: 1024px) {
    .sidebar {
        width: 240px;
    }
    
    .data-table {
        min-width: 900px;
    }
    
    .data-table th,
    .data-table td {
        padding: 12px 12px;
    }
}

/* Mobile (< 768px) */
@media (max-width: 768px) {
    .engage-wrapper {
        position: relative;
    }
    
    /* Sidebar menjadi drawer */
    .sidebar {
        position: fixed;
        left: 0;
        top: 0;
        bottom: 0;
        width: 280px;
        transform: translateX(-100%);
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        z-index: 100;
    }
    
    .sidebar.open {
        transform: translateX(0);
    }
    
    .sidebar-overlay {
        display: none;
    }
    
    .sidebar-overlay.show {
        display: block;
    }
    
    /* Mobile menu button */
    .mobile-menu-btn {
        display: flex;
    }
    
    /* Header adjustments */
    .content-header {
        padding: 12px 16px;
    }
    
    .header-title span {
        font-size: 16px;
    }
    
    /* Tabs - horizontal scroll */
    .tabs-container {
        padding: 0 16px;
    }
    
    .tabs {
        gap: 16px;
    }
    
    .tab {
        padding: 12px 0;
        font-size: 12px;
    }
    
    /* Filter bar - wrap ke bawah */
    .filter-bar {
        padding: 12px 16px;
        gap: 8px;
    }
    
    .filter-icon {
        width: 32px;
        height: 32px;
        font-size: 14px;
    }
    
    .filter-select {
        font-size: 12px;
        padding: 6px 10px;
    }
    
    .filter-btn {
        font-size: 12px;
        padding: 6px 12px;
    }
    
    /* Table container dengan horizontal scroll */
    .table-container {
        overflow-x: auto;
    }
    
    .data-table {
        min-width: 700px;
    }
    
    .data-table th,
    .data-table td {
        padding: 10px 12px;
        font-size: 12px;
    }
    
    .visitor-avatar {
        width: 30px;
        height: 30px;
        font-size: 12px;
    }
    
    .visitor-name {
        font-size: 13px;
    }
    
    /* Hide kurang penting di mobile (opsional) */
    /* .data-table th:nth-child(6),
    .data-table td:nth-child(6) {
        display: none;
    } */
}

/* Small Mobile (< 480px) */
@media (max-width: 480px) {
    .content-header {
        padding: 10px 12px;
    }
    
    .header-title i {
        font-size: 16px;
    }
    
    .header-title span {
        font-size: 14px;
    }
    
    .tabs-container {
        padding: 0 12px;
    }
    
    .tab {
        font-size: 11px;
        padding: 10px 0;
    }
    
    .filter-bar {
        padding: 10px 12px;
    }
    
    .filter-select,
    .filter-btn {
        font-size: 11px;
    }
    
    .data-table th,
    .data-table td {
        padding: 8px 10px;
        font-size: 11px;
    }
    
    .visitor-avatar {
        width: 28px;
        height: 28px;
        font-size: 11px;
    }
    
    .visitor-name {
        font-size: 12px;
    }
    
    .status-badge {
        font-size: 11px;
    }
}

/* Desktop Large (> 1400px) */
@media (min-width: 1400px) {
    .data-table {
        min-width: auto;
    }
}
</style>


    <!-- Main Content -->
    <main class="main-content">

        <!-- Tabs -->
        <div class="tabs-container">
            <div class="tabs">
                <div class="tab active" data-filter="all">
                    All customers <span class="tab-count">(<?= $stats['total'] ?>)</span>
                </div>
                <div class="tab" data-filter="chatting">
                    Chatting <span class="tab-count">(<?= $stats['chatting'] ?>)</span>
                </div>
                <div class="tab" data-filter="invited">
                    Invited <span class="tab-count">(0)</span>
                </div>
                <div class="tab" data-filter="browsing">
                    Browsing <span class="tab-count">(<?= $stats['total'] - $stats['chatting'] ?>)</span>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <div class="filter-icon">
                <i class="fas fa-filter"></i>
            </div>
            <select class="filter-select" id="filterMatch">
                <option>Match all filters</option>
                <option>Match any filter</option>
            </select>
            <button class="filter-btn" onclick="addFilter()">
                <i class="fas fa-plus"></i> Add filter
            </button>
            <div style="flex: 1;"></div>
            <input type="text" id="searchInput" placeholder="Search visitor..." style="padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; width: 200px;">
        </div>

        <!-- Table Container -->
        <div class="table-container">
            <table class="data-table" id="visitorTable">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Actions</th>
                        <th>Activity</th>
                        <th>Chatting with</th>
                        <th>Time on pages</th>
                        <th>Country</th>
                        <th>City</th>
                        <th style="width: 40px;"></th>
                    </tr>
                </thead>
                <tbody id="visitorTableBody">
                    <?php if (empty($visitors)): ?>
                        <tr>
                            <td colspan="9" style="text-align:center; padding:60px; color:#94a3b8;">
                                <i class="fas fa-user-slash" style="font-size: 48px; margin-bottom: 16px; display: block;"></i>
                                No visitors found
                            </td>
                        </tr>
                    <?php else: foreach ($visitors as $v): 
                        $isOnline = (strtotime($v['last_visit'] ?? '') > strtotime('-3 minutes'));
                        $statusText = $isOnline ? 'Browsing' : 'Left website';
                        $statusColor = $isOnline ? '#22c55e' : '#94a3b8';
                        $avatarColor = getAvatarColor($v['username'] ?? 'V');
                        $initial = getInitials($v['username'] ?? 'V');
                        $cc = strtolower($v['country_code'] ?? 'id');
                    ?>
                    <tr data-id="<?= $v['id'] ?>" data-status="<?= $isOnline ? 'online' : 'offline' ?>"
                        onclick="window.location.href='archive?conv=<?= $v['id'] ?>'">
                        
                        <td>
                            <div class="visitor-cell">
                                <div class="visitor-avatar" style="background: <?= $avatarColor ?>;">
                                    <?= $initial ?>
                                </div>
                                <span class="visitor-name"><?= htmlspecialchars($v['username'] ?? 'Visitor') ?></span>
                            </div>
                        </td>
                        
                        <td style="color: #64748b;"><?= htmlspecialchars($v['email'] ?? '-') ?></td>
                        
                        <td style="color: #cbd5e1; text-align: center;">
                            <i class="fas fa-ellipsis-h"></i>
                        </td>
                        
                        <td>
                            <div class="status-badge">
                                <span class="status-dot <?= $isOnline ? 'online' : 'offline' ?>"></span>
                                <?= $statusText ?>
                            </div>
                        </td>
                        
                        <td style="color: #94a3b8;">-</td>
                        
                        <td style="font-family: monospace;"><?= formatDuration($v['total_time'] ?? 0) ?></td>
                        
                        <td>
                            <div class="country-cell">
                                <img src="https://flagcdn.com/w20/<?= $cc ?>.png" class="country-flag" alt="flag">
                                <?= htmlspecialchars($v['country'] ?? 'Indonesia') ?>
                            </div>
                        </td>
                        
                        <td style="color: #64748b;"><?= htmlspecialchars($v['city'] ?? $v['region'] ?? '-') ?></td>
                        
                        <td style="color: #cbd5e1; text-align: center;">
                            <i class="fas fa-chevron-right"></i>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<!-- Mobile Menu Button -->
<button class="mobile-menu-btn" id="mobileMenuBtn" onclick="toggleSidebar()">
    <i class="fas fa-chart-simple"></i>
</button>

<script>
// ========== RESPONSIVE FUNCTIONS ==========
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('open');
    overlay.classList.toggle('show');
    document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
}

function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.remove('open');
    overlay.classList.remove('show');
    document.body.style.overflow = '';
}

// Close sidebar saat ukuran layar berubah ke desktop
window.addEventListener('resize', function() {
    if (window.innerWidth > 768) {
        closeSidebar();
    }
});

// ========== TAB FILTERING ==========
document.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', function() {
        // Update active tab
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        
        const filter = this.getAttribute('data-filter');
        const rows = document.querySelectorAll('#visitorTableBody tr');
        let visibleCount = 0;
        
        rows.forEach(row => {
            if (row.querySelector('td[colspan]')) return;
            
            const status = row.getAttribute('data-status');
            let show = true;
            
            if (filter === 'chatting') {
                show = false; // Implement sesuai data chatting
            } else if (filter === 'browsing') {
                show = (status === 'online');
            } else if (filter === 'invited') {
                show = false;
            }
            
            if (show) visibleCount++;
            row.style.display = show ? '' : 'none';
        });
    });
});

// ========== SEARCH FILTER ==========
const searchInput = document.getElementById('searchInput');
if (searchInput) {
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const rows = document.querySelectorAll('#visitorTableBody tr');
        
        rows.forEach(row => {
            if (row.querySelector('td[colspan]')) return;
            
            const name = row.querySelector('.visitor-name')?.textContent.toLowerCase() || '';
            const email = row.cells[1]?.textContent.toLowerCase() || '';
            const country = row.cells[6]?.textContent.toLowerCase() || '';
            
            const matches = name.includes(searchTerm) || 
                          email.includes(searchTerm) || 
                          country.includes(searchTerm);
            
            row.style.display = matches ? '' : 'none';
        });
    });
}

// ========== REFRESH FUNCTION ==========
function refreshData() {
    location.reload();
}

function addFilter() {
    alert('Filter feature coming soon');
}

// Close sidebar on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && window.innerWidth <= 768) {
        closeSidebar();
    }
});
</script>

<?php include 'includes/layout-footer.php'; ?>