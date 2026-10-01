<?php
/**
 * সাধারণ Helper & Security Functions
 */

/**
 * ডাটাবেস স্কিমা ও লোকেশন কোঅর্ডিনেট স্বয়ংক্রিয়ভাবে সিঙ্ক করা
 */
function ensureSchemaUpToDate(PDO $pdo): void
{
    static $checked = false;
    if ($checked) return;

    try {
        // Blocked IPs table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS blocked_ips (
                id SERIAL PRIMARY KEY,
                ip_address INET UNIQUE NOT NULL,
                reason VARCHAR(255) DEFAULT 'Suspicious activity',
                blocked_at TIMESTAMP NOT NULL DEFAULT NOW()
            );
            CREATE INDEX IF NOT EXISTS idx_blocked_ips_ip ON blocked_ips(ip_address);
        ");

        // Latitude, Longitude & is_bot columns
        $pdo->exec("
            ALTER TABLE visitor_logs ADD COLUMN IF NOT EXISTS latitude NUMERIC(10, 6);
            ALTER TABLE visitor_logs ADD COLUMN IF NOT EXISTS longitude NUMERIC(10, 6);
            ALTER TABLE visitor_logs ADD COLUMN IF NOT EXISTS is_bot BOOLEAN DEFAULT FALSE;
            ALTER TABLE ip_geo_cache ADD COLUMN IF NOT EXISTS latitude NUMERIC(10, 6);
            ALTER TABLE ip_geo_cache ADD COLUMN IF NOT EXISTS longitude NUMERIC(10, 6);
        ");

        // Posts table for Blog
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS posts (
                id SERIAL PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                slug VARCHAR(255) UNIQUE NOT NULL,
                excerpt TEXT,
                content TEXT NOT NULL,
                category VARCHAR(100) DEFAULT 'Technology',
                image_url VARCHAR(500),
                views_count INTEGER DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT NOW()
            );
            CREATE INDEX IF NOT EXISTS idx_posts_slug ON posts(slug);
            CREATE INDEX IF NOT EXISTS idx_posts_category ON posts(category);
        ");

        // পূর্বে তৈরি হওয়া খালি লোকেশন আপডেট
        $pdo->exec("
            UPDATE visitor_logs 
            SET latitude = 23.8103, longitude = 90.4125, country = 'Bangladesh', city = 'Dhaka' 
            WHERE latitude IS NULL OR longitude IS NULL;
        ");

        // প্রাথমিক ডেমো ব্লগ পোস্ট যুক্ত করা (যদি কোনো পোস্ট না থাকে)
        $count = $pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn();
        if ($count == 0) {
            $stmt = $pdo->prepare("
                INSERT INTO posts (title, slug, excerpt, content, category, image_url, views_count, created_at)
                VALUES 
                (:t1, :s1, :e1, :c1, :cat1, :img1, 142, NOW() - INTERVAL '2 days'),
                (:t2, :s2, :e2, :c2, :cat2, :img2, 98, NOW() - INTERVAL '1 day'),
                (:t3, :s3, :e3, :c3, :cat3, :img3, 215, NOW())
            ");
            $stmt->execute([
                't1' => 'PostgreSQL ও আধুনিক ওয়েব ডাটাবেস অপ্টিমাইজেশন',
                's1' => 'postgresql-database-optimization-guide',
                'e1' => 'কীভাবে PostgreSQL ইনডেক্সিং, INET ডেটা টাইপ এবং কানেকশন পুলিং দিয়ে মিলি-সেকেন্ডে লক্ষ লক্ষ কুয়েরি হ্যান্ডেল করবেন।',
                'c1' => "<p>PostgreSQL হলো বর্তমান পৃথিবীর সবচেয়ে শক্তিশালী এবং নির্ভরযোগ্য ওপেন সোর্স রিলেশনাল ডাটাবেস। যখন আপনার অ্যাপ্লিকেশনে ট্রাফিক বৃদ্ধি পায়, তখন সঠিক ইনডেক্সিং এবং কুয়েরি অপ্টিমাইজেশন অপরিহার্য হয়ে ওঠে।</p><h3>১. সঠিক ইনডেক্সিং (B-Tree & BRIN)</h3><p>ঘন ঘন সার্চ করা কলামগুলোতে (যেমন: <code>created_at</code>, <code>ip_address</code>, <code>slug</code>) ইনডেক্স ব্যবহার করলে কুয়েরি পারফরম্যান্স ১০০ গুণ পর্যন্ত বৃদ্ধি পায়।</p><h3>২. INET টাইপ ও আইপি কুয়েরি</h3><p>আইপি অ্যাড্রেস সংরক্ষণের জন্য সাধারণ VARCHAR ব্যবহার না করে PostgreSQL-এর বিল্ট-ইন <code>INET</code> টাইপ ব্যবহার করা উচিত। এতে সাবনেট সার্চিং এবং মেমরি সেভিংস অনেক ভালো হয়।</p>",
                'cat1' => 'Database',
                'img1' => 'https://images.unsplash.com/photo-1544383835-bda2bc66a55d?auto=format&fit=crop&w=800&q=80',

                't2' => 'ওয়েব সিকিউরিটি: রিয়েল-টাইম আইপি ব্লকিং ও থ্রেট শিল্ড',
                's2' => 'web-security-realtime-ip-blocking-firewall',
                'e2' => 'ক্ষতিকর বট, ব্রুট-ফোর্স অ্যাটাক ও স্ক্র্যাপার প্রতিরোধে অ্যাপ্লিকেশন-লেভেল ফায়ারওয়াল তৈরির পূর্ণাঙ্গ গাইড।',
                'c2' => "<p>ইন্টারনেটে প্রকাশিত প্রতিটি ওয়েবসাইটেই প্রতিদিন অসংখ্য স্বয়ংক্রিয় বট ও স্ক্র্যাপার ট্রাফিক পাঠায়। সার্ভারের রিসোর্স বাঁচাতে এবং সিকিউরিটি নিশ্চিত করতে রিয়েল-টাইম থ্রেট ম্যানেজমেন্ট অপরিহার্য।</p><h3>১. স্বয়ংক্রিয় বট ডিটেকশন</h3><p>User-Agent স্ট্রিং এবং রিকোয়েস্ট ফ্রিকোয়েন্সি অ্যানালাইসিস করে বট ট্রাফিক আলাদা করা যায়।</p><h3>২. ৪০৩ অ্যাক্সেস ডিনাইড ও আইপি ব্লকলিস্ট</h3><p>কোনো আইপি সন্দেহজনক আচরণ করলে অ্যাডমিন ড্যাশবোর্ড থেকে এক ক্লিকে ফায়ারওয়াল ব্লকলিস্টে যুক্ত করে তৎক্ষণাৎ প্রবেশ নিষিদ্ধ করা সম্ভব।</p>",
                'cat2' => 'Security',
                'img2' => 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?auto=format&fit=crop&w=800&q=80',

                't3' => 'আধুনিক ওয়েব আর্কিটেকচার ও ভিজিটর অ্যানালিটিক্স',
                's3' => 'modern-web-architecture-visitor-analytics',
                'e3' => 'গুগল অ্যানালিটিক্স ছাড়াও কীভাবে নিজস্ব প্রাইভেসি-ফার্স্ট রিয়েল-টাইম অ্যানালিটিক্স প্ল্যাটফর্ম তৈরি করবেন।',
                'c3' => "<p>থার্ড-পার্টি ট্র্যাকার যেমন Google Analytics অনেক সময় সাইট স্লো করে এবং ভিজিটরের প্রাইভেসি নষ্ট করে। নিজস্ব লাইটওয়েট ট্র্যাকিং সিস্টেম সাইটের গতি ঠিক রাখে এবং সম্পূর্ণ ডেটার ওপর নিজের নিয়ন্ত্রণ থাকে।</p><h3>১. লাইভ মানচিত্র ও জিওলোকেশন</h3><p>OpenStreetMap এবং Leaflet ব্যবহার করে পাঠকদের ভৌগোলিক অবস্থান লাইভ পর্যবেক্ষণ করা যায়।</p><h3>২. পারফরম্যান্স ফার্স্ট ট্র্যাকিং</h3><p>ক্যাশিং এবং স্মার্ট কোটা ম্যানেজমেন্টের মাধ্যমে বিনা খরচে ৫০,০০০+ ভিজিটর নিখুঁতভাবে ট্র্যাক করা যায়।</p>",
                'cat3' => 'Architecture',
                'img3' => 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=800&q=80',
            ]);
        }

        $checked = true;
    } catch (Throwable $e) {
        error_log('Schema update check: ' . $e->getMessage());
    }
}

/**
 * ইউজারের আসল IP address বের করা।
 */
function getUserIP(): string
{
    $headerPriority = [
        'HTTP_CF_CONNECTING_IP',   // Cloudflare
        'HTTP_X_FORWARDED_FOR',    // Proxy / Load balancer
        'HTTP_CLIENT_IP',
        'REMOTE_ADDR',             // Fallback
    ];

    foreach ($headerPriority as $header) {
        if (!empty($_SERVER[$header])) {
            $ipList = explode(',', $_SERVER[$header]);
            $ip = trim($ipList[0]);

            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }

    return '127.0.0.1'; // Fallback
}

/**
 * IP ব্লক করা আছে কি না চেক করা (Firewall)
 */
function isIpBlocked(PDO $pdo, string $ip): bool
{
    try {
        ensureSchemaUpToDate($pdo);
        $stmt = $pdo->prepare("SELECT id FROM blocked_ips WHERE ip_address = :ip LIMIT 1");
        $stmt->execute(['ip' => $ip]);
        return (bool)$stmt->fetch();
    } catch (Throwable $e) {
        error_log('IP block check failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * IP ব্লক করা
 */
function blockIp(PDO $pdo, string $ip, string $reason = 'Manual ban from dashboard'): bool
{
    try {
        ensureSchemaUpToDate($pdo);
        $stmt = $pdo->prepare("
            INSERT INTO blocked_ips (ip_address, reason, blocked_at)
            VALUES (:ip, :reason, NOW())
            ON CONFLICT (ip_address) DO UPDATE SET reason = :reason, blocked_at = NOW()
        ");
        return $stmt->execute(['ip' => $ip, 'reason' => $reason]);
    } catch (Throwable $e) {
        error_log('Block IP error: ' . $e->getMessage());
        return false;
    }
}

/**
 * IP আনব্লক করা
 */
function unblockIp(PDO $pdo, string $ip): bool
{
    try {
        ensureSchemaUpToDate($pdo);
        $stmt = $pdo->prepare("DELETE FROM blocked_ips WHERE ip_address = :ip");
        return $stmt->execute(['ip' => $ip]);
    } catch (Throwable $e) {
        error_log('Unblock IP error: ' . $e->getMessage());
        return false;
    }
}

/**
 * সব ব্লক করা IP এর তালিকা পাওয়া
 */
function getBlockedIps(PDO $pdo): array
{
    try {
        ensureSchemaUpToDate($pdo);
        return $pdo->query("SELECT * FROM blocked_ips ORDER BY blocked_at DESC")->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * User-Agent স্ট্রিং থেকে Browser, OS, Device Type এবং Bot চিহ্নিত করা।
 */
function parseUserAgent(string $userAgent): array
{
    $browser = 'Unknown';
    $os = 'Unknown';
    $deviceType = 'desktop';
    $isBot = false;

    // --- Bot & Crawler Detection ---
    $bots = [
        'Googlebot' => 'Googlebot',
        'Bingbot' => 'Bingbot',
        'Slurp' => 'Yahoo Slurp',
        'DuckDuckBot' => 'DuckDuckBot',
        'Baiduspider' => 'Baiduspider',
        'YandexBot' => 'YandexBot',
        'facebookexternalhit' => 'Facebook Bot',
        'Twitterbot' => 'Twitter Bot',
        'LinkedInBot' => 'LinkedIn Bot',
        'curl' => 'cURL Client',
        'python-requests' => 'Python Scraper',
        'PostmanRuntime' => 'Postman',
        'HeadlessChrome' => 'Headless Chrome',
        'bot' => 'General Bot',
        'crawler' => 'Crawler',
        'spider' => 'Spider',
    ];

    foreach ($bots as $pattern => $name) {
        if (stripos($userAgent, $pattern) !== false) {
            $isBot = true;
            $browser = $name;
            $deviceType = 'bot';
            break;
        }
    }

    if (!$isBot) {
        // --- Browser detection ---
        if (stripos($userAgent, 'Edg/') !== false) {
            $browser = 'Edge';
        } elseif (stripos($userAgent, 'Chrome') !== false && stripos($userAgent, 'OPR') === false) {
            $browser = 'Chrome';
        } elseif (stripos($userAgent, 'Firefox') !== false) {
            $browser = 'Firefox';
        } elseif (stripos($userAgent, 'Safari') !== false && stripos($userAgent, 'Chrome') === false) {
            $browser = 'Safari';
        } elseif (stripos($userAgent, 'OPR') !== false || stripos($userAgent, 'Opera') !== false) {
            $browser = 'Opera';
        }

        // --- OS detection ---
        if (stripos($userAgent, 'Windows') !== false) {
            $os = 'Windows';
        } elseif (stripos($userAgent, 'Android') !== false) {
            $os = 'Android';
        } elseif (stripos($userAgent, 'iPhone') !== false || stripos($userAgent, 'iPad') !== false) {
            $os = 'iOS';
        } elseif (stripos($userAgent, 'Mac OS') !== false) {
            $os = 'macOS';
        } elseif (stripos($userAgent, 'Linux') !== false) {
            $os = 'Linux';
        }

        // --- Device type detection ---
        if (stripos($userAgent, 'Mobile') !== false && stripos($userAgent, 'iPad') === false) {
            $deviceType = 'mobile';
        } elseif (stripos($userAgent, 'iPad') !== false || stripos($userAgent, 'Tablet') !== false) {
            $deviceType = 'tablet';
        }
    } else {
        $os = 'Bot/Server';
    }

    return [
        'browser' => $browser,
        'os' => $os,
        'device_type' => $deviceType,
        'is_bot' => $isBot,
    ];
}

/**
 * IP থেকে Geolocation বের করা।
 */
function getGeoData(PDO $pdo, string $ip): array
{
    $default = [
        'country' => 'Bangladesh',
        'country_code' => 'BD',
        'city' => 'Dhaka',
        'region' => 'Dhaka Division',
        'isp' => 'Localhost Development',
        'latitude' => 23.8103,
        'longitude' => 90.4125,
    ];

    $isPrivate = !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    $appEnv = $_ENV['APP_ENV'] ?? 'development';

    ensureSchemaUpToDate($pdo);

    // --- ১. ক্যাশ চেক করা ---
    try {
        $stmt = $pdo->prepare(
            "SELECT country, country_code, city, region, isp, latitude, longitude
             FROM ip_geo_cache
             WHERE ip_address = :ip AND cached_at > NOW() - INTERVAL '30 days'"
        );
        $stmt->execute(['ip' => $ip]);
        $cached = $stmt->fetch();

        if ($cached && !empty($cached['latitude']) && !empty($cached['longitude'])) {
            return $cached;
        }
    } catch (Throwable $e) {
        error_log('Cache fetch error: ' . $e->getMessage());
    }

    // লোকালহোস্ট টেস্টে ডেভেলপমেন্ট মোডে রিয়েল পাবলিক আইপি লোকেশন ব্যবহার করা
    $token = $_ENV['IPINFO_TOKEN'] ?? '';
    
    if ($isPrivate) {
        if ($appEnv === 'development') {
            $url = "https://ipinfo.io/json" . ($token ? "?token={$token}" : '');
        } else {
            return $default;
        }
    } else {
        $url = "https://ipinfo.io/{$ip}/json" . ($token ? "?token={$token}" : '');
    }

    $geo = $default;

    try {
        $context = stream_context_create([
            'http' => [
                'timeout' => 4,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'
            ]
        ]);
        $response = @file_get_contents($url, false, $context);

        if ($response !== false) {
            $data = json_decode($response, true);

            if (is_array($data) && !isset($data['error'])) {
                $lat = null;
                $lon = null;
                if (!empty($data['loc'])) {
                    $locParts = explode(',', $data['loc']);
                    if (count($locParts) === 2) {
                        $lat = (float)trim($locParts[0]);
                        $lon = (float)trim($locParts[1]);
                    }
                }

                $geo = [
                    'country' => $data['country'] ?? 'Bangladesh',
                    'country_code' => $data['country'] ?? 'BD',
                    'city' => $data['city'] ?? 'Dhaka',
                    'region' => $data['region'] ?? 'Dhaka Division',
                    'isp' => $data['org'] ?? 'Local ISP',
                    'latitude' => $lat ?? 23.8103,
                    'longitude' => $lon ?? 90.4125,
                ];
            }
        }
    } catch (Throwable $e) {
        error_log('ipinfo.io lookup failed: ' . $e->getMessage());
    }

    // --- ৩. ফলাফল ক্যাশে সেভ করা ---
    try {
        $upsert = $pdo->prepare(
            "INSERT INTO ip_geo_cache (ip_address, country, country_code, city, region, isp, latitude, longitude, cached_at)
             VALUES (:ip, :country, :country_code, :city, :region, :isp, :lat, :lon, NOW())
             ON CONFLICT (ip_address)
             DO UPDATE SET country = :country, country_code = :country_code, city = :city,
                            region = :region, isp = :isp, latitude = :lat, longitude = :lon, cached_at = NOW()"
        );
        $upsert->execute([
            'ip' => $ip,
            'country' => $geo['country'],
            'country_code' => $geo['country_code'],
            'city' => $geo['city'],
            'region' => $geo['region'],
            'isp' => $geo['isp'],
            'lat' => $geo['latitude'],
            'lon' => $geo['longitude'],
        ]);
    } catch (Throwable $e) {
        error_log('Cache insert error: ' . $e->getMessage());
    }

    return $geo;
}
