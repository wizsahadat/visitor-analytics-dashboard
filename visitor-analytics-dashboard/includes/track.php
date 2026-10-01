<?php
/**
 * এই ফাইলটা প্রতিটা পাবলিক পেজের শুরুতে include করা হবে।
 * ব্যাকগ্রাউন্ডে ভিজিট লগ করে এবং ব্লকড আইপি হ্যান্ডেল করে।
 */

require_once __DIR__ . '/functions.php';

function trackVisit(PDO $pdo, string $pageName): void
{
    $ip = getUserIP();

    // ১. আইপি ব্লকিং চেক (Firewall)
    if (isIpBlocked($pdo, $ip)) {
        http_response_code(403);
        ?>
        <!DOCTYPE html>
        <html lang="bn">
        <head>
            <meta charset="UTF-8">
            <title>403 - এক্সেস নিষিদ্ধ (Access Denied)</title>
            <style>
                body { font-family: 'Segoe UI', Arial, sans-serif; background: #0f172a; color: #f8fafc; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
                .box { background: #1e293b; padding: 40px; border-radius: 12px; max-width: 480px; text-align: center; border: 1px solid #334155; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
                h1 { color: #ef4444; font-size: 2rem; margin-bottom: 12px; }
                p { color: #94a3b8; line-height: 1.6; margin-bottom: 20px; }
                .ip-badge { display: inline-block; background: #334155; color: #38bdf8; padding: 6px 14px; border-radius: 6px; font-family: monospace; font-size: 0.95rem; }
            </style>
        </head>
        <body>
            <div class="box">
                <h1>⛔ 403 Access Denied</h1>
                <p>আপনার IP অ্যাড্রেসটি নিরাপত্তা কারণে এই ওয়েবসাইটে প্রবেশ করা থেকে সাময়িকভাবে ব্লক করা হয়েছে।</p>
                <div class="ip-badge">Your IP: <?= htmlspecialchars($ip) ?></div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }

    // ২. সেশন শুরু করা
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $referrer = $_SERVER['HTTP_REFERER'] ?? null;
    $sessionId = session_id();

    $uaInfo = parseUserAgent($userAgent);
    $geo = getGeoData($pdo, $ip);

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO visitor_logs
                (ip_address, country, country_code, city, region, isp, latitude, longitude,
                 browser, os, device_type, is_bot, page_visited, referrer, session_id, visited_at)
             VALUES
                (:ip, :country, :country_code, :city, :region, :isp, :lat, :lon,
                 :browser, :os, :device_type, :is_bot, :page, :referrer, :session_id, NOW())"
        );

        $stmt->execute([
            'ip' => $ip,
            'country' => $geo['country'],
            'country_code' => $geo['country_code'],
            'city' => $geo['city'],
            'region' => $geo['region'],
            'isp' => $geo['isp'],
            'lat' => $geo['latitude'],
            'lon' => $geo['longitude'],
            'browser' => $uaInfo['browser'],
            'os' => $uaInfo['os'],
            'device_type' => $uaInfo['device_type'],
            'is_bot' => $uaInfo['is_bot'] ? 1 : 0,
            'page' => $pageName,
            'referrer' => $referrer,
            'session_id' => $sessionId,
        ]);
    } catch (Throwable $e) {
        error_log('Visitor tracking failed: ' . $e->getMessage());
    }
}
