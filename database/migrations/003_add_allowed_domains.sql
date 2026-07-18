-- 003: Add allowed_domains to embed_codes for domain locking
ALTER TABLE embed_codes ADD COLUMN allowed_domains TEXT DEFAULT '';
