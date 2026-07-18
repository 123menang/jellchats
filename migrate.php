<?php
/**
 * Database Migration Runner
 * 
 * Usage:
 *   php migrate.php            # Run pending migrations
 *   php migrate.php --status   # Show migration status
 *   php migrate.php --fresh    # Drop all tables and re-run all migrations
 *   php migrate.php --file=002_add_rate_limits.sql  # Run a specific migration
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';

$db = Database::getInstance();
$pdo = $db->getPdo();

// ── Parse arguments ──────────────────────────────────────────────────────────
$args = [];
foreach ($argv ?? [] as $a) {
    if (str_starts_with($a, '--')) {
        $parts = explode('=', substr($a, 2), 2);
        $args[$parts[0]] = $parts[1] ?? true;
    }
}

$mode = isset($args['fresh']) ? 'fresh' : (isset($args['status']) ? 'status' : 'migrate');
$specificFile = $args['file'] ?? null;

// ── Ensure migration tracking table ──────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS _migrations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    migration TEXT NOT NULL UNIQUE,
    batch INTEGER NOT NULL,
    executed_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// ── Get applied migrations ───────────────────────────────────────────────────
$applied = $pdo->query("SELECT migration FROM _migrations ORDER BY migration")->fetchAll(PDO::FETCH_COLUMN);

// ── Get available migration files ────────────────────────────────────────────
$migrationsDir = __DIR__ . '/database/migrations';
$files = glob($migrationsDir . '/*.sql');
sort($files);

// ── Status mode ──────────────────────────────────────────────────────────────
if ($mode === 'status') {
    echo "Migration Status:\n";
    echo str_repeat('-', 60) . "\n";
    foreach ($files as $file) {
        $name = basename($file);
        $isApplied = in_array($name, $applied, true);
        $status = $isApplied ? '  ✅ APPLIED' : '  ⏳ PENDING';
        echo sprintf("%-45s %s\n", $name, $status);
    }
    echo str_repeat('-', 60) . "\n";
    echo sprintf("Total: %d migrations (%d applied, %d pending)\n", count($files), count($applied), count($files) - count($applied));
    exit(0);
}

// ── Fresh mode ───────────────────────────────────────────────────────────────
if ($mode === 'fresh') {
    echo "⚠️  FRESH MODE: Dropping all tables...\n";
    $pdo->exec("PRAGMA foreign_keys = OFF");
    // Drop in reverse dependency order
    $dropOrder = [
        'rate_limits', 'typing_status', 'messages', 'chat_modules', 'ai_rules_templates',
        'canned_responses', 'conversations', 'subscriptions', 'activity_logs',
        'embed_codes', 'agents', 'visitors', 'teams', 'license_tiers', 'users',
    ];
    foreach ($dropOrder as $table) {
        $pdo->exec("DROP TABLE IF EXISTS \"$table\"");
        echo "  Dropped: $table\n";
    }
    $pdo->exec("PRAGMA foreign_keys = ON");
    $pdo->exec("DELETE FROM _migrations");
    $applied = [];
    echo "  Done. Re-running all migrations...\n\n";
}

// ── Run a specific file ──────────────────────────────────────────────────────
if ($specificFile) {
    $path = $migrationsDir . '/' . $specificFile;
    if (!file_exists($path)) {
        echo "❌ Migration file not found: $specificFile\n";
        exit(1);
    }
    $files = [$path];
}

// ── Get current batch number ─────────────────────────────────────────────────
$batch = (int)($pdo->query("SELECT COALESCE(MAX(batch), 0) FROM _migrations")->fetchColumn()) + 1;

// ── Run pending migrations ───────────────────────────────────────────────────
$ran = 0;
foreach ($files as $file) {
    $name = basename($file);

    if (isset($args['file'])) {
        // Force re-run if --file is specified
        $pdo->prepare("DELETE FROM _migrations WHERE migration = ?")->execute([$name]);
    } elseif (in_array($name, $applied, true)) {
        continue;
    }

    echo "Running: $name... ";

    $sql = file_get_contents($file);
    if ($sql === false || trim($sql) === '') {
        echo "⚠️  Empty file, skipped\n";
        continue;
    }

    try {
        $pdo->beginTransaction();
        $pdo->exec($sql);
        $pdo->prepare("INSERT INTO _migrations (migration, batch) VALUES (?, ?)")->execute([$name, $batch]);
        $pdo->commit();
        echo "✅\n";
        $ran++;
    } catch (\Exception $e) {
        $pdo->rollBack();
        echo "❌ Failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// ── Seed default data ────────────────────────────────────────────────────────
if ($ran > 0 || $mode === 'fresh') {
    $licenseCount = $pdo->query("SELECT COUNT(*) FROM license_tiers")->fetchColumn();
    if ((int)$licenseCount === 0) {
        $pdo->exec("INSERT INTO license_tiers (name, price_monthly, max_teams, max_agents_per_team, max_chats_monthly, max_modules, ai_enabled, custom_branding, priority_support, features) VALUES
            ('starter', 150000, 1, 1, 1000, 10, 0, 0, 0, '{\"widget_customization\":true,\"basic_analytics\":true}'),
            ('team', 450000, 3, 5, 10000, 50, 1, 1, 0, '{\"widget_customization\":true,\"advanced_analytics\":true,\"team_routing\":true}'),
            ('business', 1200000, 10, 20, 50000, 200, 1, 1, 1, '{\"widget_customization\":true,\"premium_analytics\":true,\"team_routing\":true,\"api_access\":true}')");
        echo "✅ Seeded: license_tiers\n";
    }

    $userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ((int)$userCount === 0) {
        $pdo->exec("INSERT INTO users (username, email, password_hash, full_name, role, license_tier, max_teams, max_agents, status) VALUES
            ('admin', 'admin@livechat.local', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'jellplay', 'owner', 'business', 999, 999, 1)");
        echo "✅ Seeded: admin user\n";
    }
}

echo "\n✅ Migration complete. $ran migration(s) executed.\n";
