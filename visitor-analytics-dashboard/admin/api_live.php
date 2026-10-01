<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$pdo = getDbConnection();
ensureSchemaUpToDate($pdo);

// লাইভ ভিজিটর (গত ৫ মিনিট)
$liveVisitors = $pdo->query("
    SELECT COUNT(DISTINCT session_id) AS live_count
    FROM visitor_logs
    WHERE visited_at >= NOW() - INTERVAL '5 minutes'
")->fetch();

// আজকের মোট ভিজিট
$todayVisits = $pdo->query("
    SELECT COUNT(*) AS total, COUNT(DISTINCT ip_address) AS unique_ips
    FROM visitor_logs
    WHERE visited_at >= CURRENT_DATE
")->fetch();

// সর্বশেষ ১০টি ভিজিট লগ
$recentLogs = $pdo->query("
    SELECT ip_address, country, city, browser, os, device_type, is_bot, page_visited, visited_at
    FROM visitor_logs
    ORDER BY visited_at DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

$blockedIps = getBlockedIps($pdo);
$blockedIpMap = array_flip(array_column($blockedIps, 'ip_address'));

foreach ($recentLogs as &$log) {
    $log['is_blocked'] = isset($blockedIpMap[$log['ip_address']]);
    $log['visited_time'] = date('M d, H:i:s', strtotime($log['visited_at']));
}

echo json_encode([
    'live_count' => (int)($liveVisitors['live_count'] ?? 0),
    'today_total' => (int)($todayVisits['total'] ?? 0),
    'today_unique' => (int)($todayVisits['unique_ips'] ?? 0),
    'blocked_count' => count($blockedIps),
    'recent_logs' => $recentLogs,
    'timestamp' => date('H:i:s')
]);
