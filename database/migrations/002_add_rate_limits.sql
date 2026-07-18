-- 002: Add rate_limits table (used by RateLimiterService)

CREATE TABLE IF NOT EXISTS rate_limits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    key_name TEXT NOT NULL,
    ip_address TEXT NOT NULL,
    window_start INTEGER NOT NULL,
    hit_count INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_rate_limits_lookup ON rate_limits(key_name, ip_address, window_start);
