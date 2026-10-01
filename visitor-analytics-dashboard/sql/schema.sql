-- ============================================
-- Visitor Analytics & Blog Platform Schema
-- PostgreSQL
-- ============================================

-- ভিজিটর লগ টেবিল: প্রতিটা page visit এখানে সেভ হবে
CREATE TABLE IF NOT EXISTS visitor_logs (
    id SERIAL PRIMARY KEY,
    ip_address INET NOT NULL,
    country VARCHAR(100),
    country_code VARCHAR(10),
    city VARCHAR(100),
    region VARCHAR(100),
    isp VARCHAR(150),
    latitude NUMERIC(10, 6),
    longitude NUMERIC(10, 6),
    browser VARCHAR(50),
    os VARCHAR(50),
    device_type VARCHAR(20),          -- desktop / mobile / tablet
    is_bot BOOLEAN DEFAULT FALSE,
    page_visited VARCHAR(255) NOT NULL,
    referrer VARCHAR(255),
    session_id VARCHAR(100),
    visited_at TIMESTAMP NOT NULL DEFAULT NOW()
);

-- পারফরম্যান্সের জন্য ইনডেক্স
CREATE INDEX IF NOT EXISTS idx_visitor_logs_visited_at ON visitor_logs(visited_at);
CREATE INDEX IF NOT EXISTS idx_visitor_logs_ip ON visitor_logs(ip_address);
CREATE INDEX IF NOT EXISTS idx_visitor_logs_country ON visitor_logs(country);
CREATE INDEX IF NOT EXISTS idx_visitor_logs_page ON visitor_logs(page_visited);

-- IP জিওলোকেশন ক্যাশ টেবিল
CREATE TABLE IF NOT EXISTS ip_geo_cache (
    ip_address INET PRIMARY KEY,
    country VARCHAR(100),
    country_code VARCHAR(10),
    city VARCHAR(100),
    region VARCHAR(100),
    isp VARCHAR(150),
    latitude NUMERIC(10, 6),
    longitude NUMERIC(10, 6),
    cached_at TIMESTAMP NOT NULL DEFAULT NOW()
);

-- ব্লক করা আইপি টেবিল (Firewall)
CREATE TABLE IF NOT EXISTS blocked_ips (
    id SERIAL PRIMARY KEY,
    ip_address INET UNIQUE NOT NULL,
    reason VARCHAR(255) DEFAULT 'Suspicious activity',
    blocked_at TIMESTAMP NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_blocked_ips_ip ON blocked_ips(ip_address);

-- অ্যাডমিন টেবিল
CREATE TABLE IF NOT EXISTS admins (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT NOW()
);

-- ডিফল্ট অ্যাডমিন একাউন্ট
INSERT INTO admins (username, password_hash)
VALUES ('admin', '$2y$10$gS9C/H/Xeg9/sxmpUp55Bugr9qZICS7Yu4KRQt8UovljbpeKIXYyS')
ON CONFLICT (username) DO NOTHING;

-- ব্লগ পোস্ট টেবিল
CREATE TABLE IF NOT EXISTS posts (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    excerpt TEXT,
    content TEXT NOT NULL,
    category VARCHAR(100) DEFAULT 'General',
    image_url VARCHAR(500),
    views_count INTEGER DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_posts_slug ON posts(slug);
CREATE INDEX IF NOT EXISTS idx_posts_category ON posts(category);
