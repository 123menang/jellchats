-- ==========================================
-- LiveChat Admin Database Schema
-- SQLite Database - FINAL CLEAN VERSION (UPDATED)
-- ==========================================

CREATE TABLE IF NOT EXISTS license_tiers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT UNIQUE NOT NULL,
    price_monthly INTEGER NOT NULL,
    max_teams INTEGER DEFAULT 1,
    max_agents_per_team INTEGER DEFAULT 1,
    max_chats_monthly INTEGER DEFAULT 1000,
    max_modules INTEGER DEFAULT 10,
    ai_enabled INTEGER DEFAULT 0,
    custom_branding INTEGER DEFAULT 0,
    priority_support INTEGER DEFAULT 0,
    features TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    email TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    full_name TEXT,
    avatar TEXT,
    role TEXT DEFAULT 'agent',
    license_tier TEXT DEFAULT 'starter',
    license_expires DATE,
    max_teams INTEGER DEFAULT 1,
    max_agents INTEGER DEFAULT 1,
    max_chats_monthly INTEGER DEFAULT 1000,
    status INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS teams (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    description TEXT,
    color TEXT DEFAULT '#1e62ff',
    max_agents INTEGER DEFAULT 1,
    status INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS agents (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    team_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    display_name TEXT NOT NULL,
    avatar TEXT,
    reply_mode TEXT DEFAULT 'manual',
    ai_provider TEXT DEFAULT 'claude',
    ai_api_token TEXT,
    ai_model TEXT DEFAULT 'claude-sonnet-4-20250514',
    ai_system_prompt TEXT,
    ai_rules TEXT,
    ai_fallback_message TEXT DEFAULT 'Maaf, saya tidak mengerti pertanyaan Anda. Silakan hubungi agen kami.',
    is_online INTEGER DEFAULT 0,
    status INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- TABEL BARU: SHORTCUTS / CANNED RESPONSES
CREATE TABLE IF NOT EXISTS canned_responses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    agent_id INTEGER NOT NULL,
    shortcut TEXT NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS embed_codes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    agent_id INTEGER NOT NULL,
    site_name TEXT NOT NULL,
    site_url TEXT,
    embed_key TEXT UNIQUE NOT NULL,
    widget_config TEXT,
    pre_chat_form INTEGER DEFAULT 1,
    allow_upload INTEGER DEFAULT 1,
    show_typing INTEGER DEFAULT 1,
    status INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS visitors (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL,
    phone TEXT NOT NULL,
    email TEXT,
    ip_address TEXT,
    country TEXT,
    city TEXT,
    region TEXT,
    user_agent TEXT,
    referrer_url TEXT,
    is_banned INTEGER DEFAULT '0',
    first_visit DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_visit DATETIME DEFAULT CURRENT_TIMESTAMP,
    visit_count INTEGER DEFAULT 1,
    UNIQUE(username, phone)
);

CREATE TABLE IF NOT EXISTS conversations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    agent_id INTEGER NOT NULL,
    visitor_id INTEGER NOT NULL,
    embed_code_id INTEGER NOT NULL,
    session_id TEXT UNIQUE NOT NULL,
    username TEXT,
    phone TEXT,
    subject TEXT,
    status TEXT DEFAULT 'active',
    priority TEXT DEFAULT 'normal',
    tags TEXT,
    assigned_to INTEGER,
    reply_mode TEXT DEFAULT 'manual',
    source_url TEXT,
    rating INTEGER DEFAULT 0,
    rating_comment TEXT,
    closed_at DATETIME,
    last_message_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE,
    FOREIGN KEY (visitor_id) REFERENCES visitors(id) ON DELETE CASCADE,
    FOREIGN KEY (embed_code_id) REFERENCES embed_codes(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    conversation_id INTEGER NOT NULL,
    sender_type TEXT NOT NULL,
    sender_id INTEGER,
    content TEXT NOT NULL,
    content_type TEXT DEFAULT 'text',
    file_url TEXT,
    is_read INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS chat_modules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    agent_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    trigger_type TEXT DEFAULT 'keyword',
    trigger_value TEXT NOT NULL,
    response_text TEXT NOT NULL,
    response_type TEXT DEFAULT 'text',
    response_action TEXT,
    priority INTEGER DEFAULT 0,
    is_active INTEGER DEFAULT 1,
    match_count INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS ai_rules_templates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    agent_id INTEGER NOT NULL,
    rule_name TEXT NOT NULL,
    rule_type TEXT NOT NULL,
    rule_value TEXT NOT NULL,
    response_override TEXT,
    is_active INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS typing_status (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    conversation_id INTEGER NOT NULL,
    user_type TEXT NOT NULL,
    user_id INTEGER,
    typing_text TEXT,
    is_typing INTEGER DEFAULT 0,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    UNIQUE(conversation_id, user_type)
);

CREATE TABLE IF NOT EXISTS activity_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    action TEXT NOT NULL,
    entity_type TEXT,
    entity_id INTEGER,
    details TEXT,
    ip_address TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS subscriptions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    tier_name TEXT NOT NULL,
    amount INTEGER NOT NULL,
    duration_days INTEGER DEFAULT 30,
    start_date DATETIME,
    end_date DATETIME,
    status TEXT DEFAULT 'pending',
    payment_method TEXT,
    transaction_id TEXT UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

INSERT OR IGNORE INTO license_tiers (name, price_monthly, max_teams, max_agents_per_team, max_chats_monthly, max_modules, ai_enabled, custom_branding, priority_support, features) VALUES
('starter', 150000, 1, 1, 1000, 10, 0, 0, 0, '{"widget_customization":true,"basic_analytics":true}'),
('team', 450000, 3, 5, 10000, 50, 1, 1, 0, '{"widget_customization":true,"advanced_analytics":true,"team_routing":true}'),
('business', 1200000, 10, 20, 50000, 200, 1, 1, 1, '{"widget_customization":true,"premium_analytics":true,"team_routing":true,"api_access":true}');

INSERT OR IGNORE INTO users (username, email, password_hash, full_name, role, license_tier, max_teams, max_agents, status) VALUES
('admin', 'admin@livechat.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'jellplay', 'owner', 'business', 999, 999, 1);

CREATE INDEX IF NOT EXISTS idx_conversations_agent ON conversations(agent_id);
CREATE INDEX IF NOT EXISTS idx_conversations_visitor ON conversations(visitor_id);
CREATE INDEX IF NOT EXISTS idx_conversations_status ON conversations(status);
CREATE INDEX IF NOT EXISTS idx_messages_conversation ON messages(conversation_id);
CREATE INDEX IF NOT EXISTS idx_modules_agent ON chat_modules(agent_id);
CREATE INDEX IF NOT EXISTS idx_visitors_phone ON visitors(phone);
CREATE INDEX IF NOT EXISTS idx_visitors_username ON visitors(username);
CREATE INDEX IF NOT EXISTS idx_embed_agent ON embed_codes(agent_id);
CREATE INDEX IF NOT EXISTS idx_subscriptions_user ON subscriptions(user_id);