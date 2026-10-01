<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/auth_check.php';

$pdo = getDbConnection();
ensureSchemaUpToDate($pdo);

// --- অ্যাকশন হ্যান্ডলিং (Block / Unblock IP) ---
$flashMessage = '';
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'block_ip') {
        $ipToBlock = trim($_POST['ip'] ?? '');
        $reason = trim($_POST['reason'] ?? 'Manual ban from dashboard');
        if (filter_var($ipToBlock, FILTER_VALIDATE_IP)) {
            if (blockIp($pdo, $ipToBlock, $reason)) {
                $flashMessage = "আইপি <strong>{$ipToBlock}</strong> সফলভাবে ব্লক করা হয়েছে!";
            } else {
                $flashMessage = "আইপি ব্লক করতে সমস্যা হয়েছে।";
                $flashType = 'error';
            }
        }
    } elseif ($action === 'unblock_ip') {
        $ipToUnblock = trim($_POST['ip'] ?? '');
        if (unblockIp($pdo, $ipToUnblock)) {
            $flashMessage = "আইপি <strong>{$ipToUnblock}</strong> সফলভাবে আনব্লক করা হয়েছে!";
        }
    }
}

// --- CSV এক্সপোর্ট হ্যান্ডলিং ---
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=visitor_logs_' . date('Y-m-d_H-i') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'IP Address', 'Country', 'City', 'Region', 'ISP', 'Browser', 'OS', 'Device', 'Is Bot', 'Page Visited', 'Referrer', 'Visited At']);
    
    $exportStmt = $pdo->query("SELECT id, ip_address, country, city, region, isp, browser, os, device_type, is_bot, page_visited, referrer, visited_at FROM visitor_logs ORDER BY visited_at DESC");
    while ($row = $exportStmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

// --- ফিল্টারিং লজিক (Date Range & Search) ---
$range = $_GET['range'] ?? 'all';
$searchIp = trim($_GET['search_ip'] ?? '');

$whereClauses = [];
$params = [];

if ($range === 'today') {
    $whereClauses[] = "visited_at >= CURRENT_DATE";
} elseif ($range === 'yesterday') {
    $whereClauses[] = "visited_at >= CURRENT_DATE - INTERVAL '1 day' AND visited_at < CURRENT_DATE";
} elseif ($range === 'week') {
    $whereClauses[] = "visited_at >= NOW() - INTERVAL '7 days'";
} elseif ($range === 'month') {
    $whereClauses[] = "visited_at >= NOW() - INTERVAL '30 days'";
}

if ($searchIp !== '') {
    $whereClauses[] = "CAST(ip_address AS TEXT) LIKE :search_ip";
    $params['search_ip'] = "%{$searchIp}%";
}

$whereSql = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// --- ১. মোট পরিসংখ্যান ---
$totals = $pdo->query("
    SELECT
        COUNT(*) FILTER (WHERE visited_at >= CURRENT_DATE) AS today,
        COUNT(*) FILTER (WHERE visited_at >= NOW() - INTERVAL '7 days') AS this_week,
        COUNT(*) FILTER (WHERE visited_at >= NOW() - INTERVAL '30 days') AS this_month,
        COUNT(*) AS all_time,
        COUNT(DISTINCT ip_address) AS unique_visitors,
        COUNT(*) FILTER (WHERE is_bot = true) AS bot_visits
    FROM visitor_logs
")->fetch();

// লাইভ ভিজিটর
$liveVisitors = $pdo->query("
    SELECT COUNT(DISTINCT session_id) AS live_count
    FROM visitor_logs
    WHERE visited_at >= NOW() - INTERVAL '5 minutes'
")->fetch();

// ব্লকড আইপি
$blockedIpsList = getBlockedIps($pdo);
$blockedCount = count($blockedIpsList);

// --- ২. টপ দেশ সমূহ ---
$countryStmt = $pdo->prepare("
    SELECT COALESCE(country, 'Unknown') AS country, COUNT(*) AS total
    FROM visitor_logs
    {$whereSql}
    GROUP BY country
    ORDER BY total DESC
    LIMIT 10
");
$countryStmt->execute($params);
$countryStats = $countryStmt->fetchAll();

// --- ৩. টপ পেজ সমূহ ---
$pageStmt = $pdo->prepare("
    SELECT page_visited, COUNT(*) AS total
    FROM visitor_logs
    {$whereSql}
    GROUP BY page_visited
    ORDER BY total DESC
    LIMIT 10
");
$pageStmt->execute($params);
$pageStats = $pageStmt->fetchAll();

// --- ৪. ডিভাইস ব্রেকডাউন ---
$deviceStmt = $pdo->prepare("
    SELECT COALESCE(device_type, 'Unknown') AS device_type, COUNT(*) AS total
    FROM visitor_logs
    {$whereSql}
    GROUP BY device_type
    ORDER BY total DESC
");
$deviceStmt->execute($params);
$deviceStats = $deviceStmt->fetchAll();

// --- ৫. মানচিত্রের জন্য কোঅর্ডিনেট ডেটা ---
$mapPointsStmt = $pdo->prepare("
    SELECT ip_address, city, country, latitude, longitude, COUNT(*) as visit_count
    FROM visitor_logs
    {$whereSql} " . ($whereSql ? "AND" : "WHERE") . " latitude IS NOT NULL AND longitude IS NOT NULL
    GROUP BY ip_address, city, country, latitude, longitude
    ORDER BY visit_count DESC
    LIMIT 100
");
$mapPointsStmt->execute($params);
$mapPoints = $mapPointsStmt->fetchAll();

// --- ৬. সাম্প্রতিক ৩০টি ভিজিট লগ ---
$logsStmt = $pdo->prepare("
    SELECT id, ip_address, country, country_code, city, region, isp, browser, os, device_type, is_bot, page_visited, visited_at
    FROM visitor_logs
    {$whereSql}
    ORDER BY visited_at DESC
    LIMIT 30
");
$logsStmt->execute($params);
$recentLogs = $logsStmt->fetchAll();

// ব্লকড আইপি সেট
$blockedIpMap = array_flip(array_column($blockedIpsList, 'ip_address'));

// Chart JSON
$countryLabels = json_encode(array_column($countryStats, 'country'));
$countryValues = json_encode(array_map('intval', array_column($countryStats, 'total')));
$pageLabels = json_encode(array_column($pageStats, 'page_visited'));
$pageValues = json_encode(array_map('intval', array_column($pageStats, 'total')));
$deviceLabels = json_encode(array_column($deviceStats, 'device_type'));
$deviceValues = json_encode(array_map('intval', array_column($deviceStats, 'total')));
$mapPointsJson = json_encode($mapPoints);
?>
<!DOCTYPE html>
<html lang="bn" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visitor Analytics & Realtime Threat Shield</title>
    
    <!-- Chart.js & Leaflet.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.4/chart.umd.min.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <style>
        :root[data-theme="dark"] {
            --bg: #0b0f19;
            --card-bg: #151c2e;
            --card-border: #222f49;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --header-bg: rgba(11, 15, 25, 0.92);
            --table-header: #0d1322;
            --table-hover: rgba(255, 255, 255, 0.03);
            --input-bg: #0b0f19;
            --primary: #3b82f6;
            --primary-hover: #2563eb;
            --danger: #ef4444;
            --success: #10b981;
            --warning: #f59e0b;
        }

        :root[data-theme="light"] {
            --bg: #f1f5f9;
            --card-bg: #ffffff;
            --card-border: #e2e8f0;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --header-bg: rgba(255, 255, 255, 0.95);
            --table-header: #f8fafc;
            --table-hover: #f8fafc;
            --input-bg: #f8fafc;
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --danger: #dc2626;
            --success: #059669;
            --warning: #d97706;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; transition: background-color 0.2s, color 0.2s; }
        body {
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, sans-serif;
            background: var(--bg);
            color: var(--text-main);
            min-height: 100vh;
            padding-bottom: 60px;
        }

        /* Top Header */
        header {
            background: var(--header-bg);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--card-border);
            padding: 14px 36px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        header .brand {
            font-size: 1.3rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-main);
        }

        header .nav-links {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }

        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { background: var(--primary-hover); }
        .btn-danger { background: rgba(239, 68, 68, 0.15); color: var(--danger); border: 1px solid rgba(239, 68, 68, 0.3); }
        .btn-danger:hover { background: var(--danger); color: #fff; }
        .btn-success { background: rgba(16, 185, 129, 0.15); color: var(--success); border: 1px solid rgba(16, 185, 129, 0.3); }
        .btn-success:hover { background: var(--success); color: #fff; }
        .btn-outline { background: transparent; color: var(--text-muted); border: 1px solid var(--card-border); }
        .btn-outline:hover { background: var(--card-border); color: var(--text-main); }

        /* Pulse Dot for Live Refresh */
        .live-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #10b981;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .pulse-dot {
            width: 8px;
            height: 8px;
            background: #10b981;
            border-radius: 50%;
            box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
            animation: pulse 1.8s infinite;
        }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
            70% { box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
            100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }

        .container {
            max-width: 1340px;
            margin: 24px auto;
            padding: 0 24px;
        }

        /* Flash Message */
        .flash {
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .flash.success { background: rgba(16, 185, 129, 0.15); border: 1px solid #059669; color: var(--success); }
        .flash.error { background: rgba(239, 68, 68, 0.15); border: 1px solid #dc2626; color: var(--danger); }

        /* Toolbar */
        .toolbar {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
        }

        .filter-group {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }

        .filter-btn {
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 0.85rem;
            text-decoration: none;
            color: var(--text-muted);
            background: var(--input-bg);
            border: 1px solid var(--card-border);
            transition: all 0.2s;
        }

        .filter-btn.active, .filter-btn:hover {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
        }

        .search-form { display: flex; gap: 8px; }
        .search-form input {
            background: var(--input-bg);
            border: 1px solid var(--card-border);
            color: var(--text-main);
            padding: 7px 14px;
            border-radius: 6px;
            font-size: 0.88rem;
            outline: none;
        }
        .search-form input:focus { border-color: var(--primary); }

        /* Stat Grid */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 6px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
        }

        .stat-card .label { font-size: 0.83rem; color: var(--text-muted); font-weight: 500; }
        .stat-card .value { font-size: 1.9rem; font-weight: 700; color: var(--text-main); }
        .stat-card.live .value { color: #10b981; }
        .stat-card.blocked .value { color: #ef4444; }

        .stat-card::after {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 3px;
            background: var(--primary);
        }
        .stat-card.live::after { background: #10b981; }
        .stat-card.blocked::after { background: #ef4444; }

        /* Section Cards */
        .section-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 14px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        }

        .section-card h3 {
            font-size: 1.15rem;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-main);
        }

        #map {
            height: 400px;
            width: 100%;
            border-radius: 10px;
            border: 1px solid var(--card-border);
            z-index: 10;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }

        @media (max-width: 900px) {
            .grid-2 { grid-template-columns: 1fr; }
        }

        /* Tables */
        .table-responsive { overflow-x: auto; }
        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.88rem;
        }
        th {
            background: var(--table-header);
            color: var(--text-muted);
            padding: 12px 16px;
            font-weight: 600;
            border-bottom: 1px solid var(--card-border);
        }
        td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--card-border);
            color: var(--text-main);
        }
        tr:hover td { background: var(--table-hover); }

        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.78rem;
            font-weight: 600;
            background: var(--card-border);
            color: var(--text-main);
        }
        .badge-ip {
            font-family: monospace;
            background: var(--input-bg);
            border: 1px solid var(--card-border);
            color: #38bdf8;
        }
        .badge-bot {
            background: rgba(245, 158, 11, 0.18);
            color: #f59e0b;
            border: 1px solid rgba(245, 158, 11, 0.4);
            font-size: 0.74rem;
            padding: 2px 6px;
            border-radius: 4px;
        }
        .blocked-pill {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
            border: 1px solid #ef4444;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.74rem;
        }

        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(4px);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }
        .modal {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            padding: 28px;
            border-radius: 12px;
            max-width: 440px;
            width: 90%;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .modal h3 { margin-bottom: 16px; color: #ef4444; }
        .modal label { font-size: 0.85rem; color: var(--text-muted); display: block; margin-bottom: 6px; }
        .modal input { width: 100%; padding: 10px; background: var(--input-bg); border: 1px solid var(--card-border); color: var(--text-main); border-radius: 6px; margin-bottom: 16px; }
        .modal-actions { display: flex; justify-content: flex-end; gap: 10px; }
    </style>
</head>
<body>

    <!-- Header -->
    <header>
        <div class="brand">
            <span>🛡️</span> Visitor Analytics & Threat Shield
        </div>
        <div class="nav-links">
            <div class="live-badge">
                <span class="pulse-dot"></span>
                <span>Live Feed</span>
            </div>
            <button type="button" class="btn btn-outline" id="themeToggle" onclick="toggleTheme()">🌙 Dark</button>
            <a href="posts.php" class="btn btn-outline" style="color:#38bdf8; border-color:#38bdf8;">✍️ Manage Blog Posts</a>
            <a href="../public/index.php" class="btn btn-outline" target="_blank">🌐 Live Blog</a>
            <a href="?export=csv" class="btn btn-outline">📥 Export CSV</a>
            <a href="logout.php" class="btn btn-danger">লগআউট</a>
        </div>
    </header>

    <div class="container">

        <!-- Flash Message -->
        <?php if ($flashMessage): ?>
            <div class="flash <?= $flashType ?>">
                <div><?= $flashMessage ?></div>
                <button onclick="this.parentElement.remove()" style="background:none;border:none;color:inherit;cursor:pointer;">✕</button>
            </div>
        <?php endif; ?>

        <!-- Filter & Search Toolbar -->
        <div class="toolbar">
            <div class="filter-group">
                <span style="font-size:0.85rem; color:var(--text-muted); margin-right:4px;">সময়কাল:</span>
                <a href="?range=all" class="filter-btn <?= $range === 'all' ? 'active' : '' ?>">All Time</a>
                <a href="?range=today" class="filter-btn <?= $range === 'today' ? 'active' : '' ?>">আজ (Today)</a>
                <a href="?range=yesterday" class="filter-btn <?= $range === 'yesterday' ? 'active' : '' ?>">গতকাল</a>
                <a href="?range=week" class="filter-btn <?= $range === 'week' ? 'active' : '' ?>">৭ দিন</a>
                <a href="?range=month" class="filter-btn <?= $range === 'month' ? 'active' : '' ?>">৩০ দিন</a>
            </div>

            <form method="GET" class="search-form">
                <?php if ($range !== 'all'): ?>
                    <input type="hidden" name="range" value="<?= htmlspecialchars($range) ?>">
                <?php endif; ?>
                <input type="text" name="search_ip" placeholder="IP অ্যাড্রেস খুঁজুন..." value="<?= htmlspecialchars($searchIp) ?>">
                <button type="submit" class="btn btn-primary">🔍 খুঁজুন</button>
                <?php if ($searchIp): ?>
                    <a href="?range=<?= htmlspecialchars($range) ?>" class="btn btn-outline">Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Metric Stat Cards -->
        <div class="stat-grid">
            <div class="stat-card live">
                <span class="label">🟢 লাইভ ভিজিটর (গত ৫ মিনিট)</span>
                <span class="value" id="statLiveCount"><?= number_format($liveVisitors['live_count'] ?? 0) ?></span>
            </div>
            <div class="stat-card">
                <span class="label">📅 আজকের মোট ভিজিট</span>
                <span class="value" id="statTodayTotal"><?= number_format($totals['today'] ?? 0) ?></span>
            </div>
            <div class="stat-card">
                <span class="label">📊 এই সপ্তাহের ভিজিট</span>
                <span class="value"><?= number_format($totals['this_week'] ?? 0) ?></span>
            </div>
            <div class="stat-card">
                <span class="label">🌐 ইউনিক আইপি ভিজিটর</span>
                <span class="value" id="statTodayUnique"><?= number_format($totals['unique_visitors'] ?? 0) ?></span>
            </div>
            <div class="stat-card">
                <span class="label">🤖 বট ও স্ক্র্যাপার ট্রাফিক</span>
                <span class="value" style="color:#f59e0b;"><?= number_format($totals['bot_visits'] ?? 0) ?></span>
            </div>
            <div class="stat-card blocked">
                <span class="label">🚫 ব্লক করা আইপি (Firewall)</span>
                <span class="value" id="statBlockedCount"><?= number_format($blockedCount) ?></span>
            </div>
        </div>

        <!-- 🗺️ Interactive World Map (Leaflet) -->
        <div class="section-card">
            <h3>🗺️ লাইভ ভিজিটর ভৌগোলিক মানচিত্র (Interactive Geo Map)</h3>
            <div id="map"></div>
        </div>

        <!-- Charts Grid -->
        <div class="grid-2">
            <div class="section-card">
                <h3>🌍 টপ ১০ দেশ</h3>
                <canvas id="countryChart" height="220"></canvas>
            </div>
            <div class="section-card">
                <h3>📄 জনপ্রিয় পেজসমূহ</h3>
                <canvas id="pageChart" height="220"></canvas>
            </div>
        </div>

        <!-- Recent Logs with 1-Click Ban Action -->
        <div class="section-card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h3>📋 সাম্প্রতিক ভিজিটর ট্র্যাকিং লগ (Live Visitor Stream)</h3>
                <span style="font-size:0.83rem; color:var(--text-muted);" id="lastUpdateTime">স্বয়ংক্রিয় রিয়েল-টাইম আপডেট সক্রিয়</span>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>IP Address</th>
                            <th>লোকেশন</th>
                            <th>ISP / Provider</th>
                            <th>ডিভাইস / ব্রাউজার</th>
                            <th>ভিজিট পেজ</th>
                            <th>সময়</th>
                            <th>নিরাপত্তা অ্যাকশন</th>
                        </tr>
                    </thead>
                    <tbody id="visitorLogsTableBody">
                        <?php if (empty($recentLogs)): ?>
                            <tr><td colspan="7" style="text-align:center; padding:30px; color:var(--text-muted);">কোনো ভিজিটর ডেটা পাওয়া যায়নি।</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentLogs as $log): 
                                $isBlocked = isset($blockedIpMap[$log['ip_address']]);
                            ?>
                                <tr>
                                    <td>
                                        <span class="badge badge-ip"><?= htmlspecialchars($log['ip_address']) ?></span>
                                        <?php if ($isBlocked): ?>
                                            <span class="blocked-pill">BLOCKED</span>
                                        <?php endif; ?>
                                        <?php if (!empty($log['is_bot'])): ?>
                                            <span class="badge-bot">🤖 Bot</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($log['city'] ? $log['city'] . ', ' : '') ?>
                                        <strong><?= htmlspecialchars($log['country'] ?? 'Unknown') ?></strong>
                                    </td>
                                    <td style="font-size:0.82rem; color:var(--text-muted);">
                                        <?= htmlspecialchars($log['isp'] ?? 'N/A') ?>
                                    </td>
                                    <td>
                                        <span class="badge"><?= htmlspecialchars($log['device_type'] ?? 'desktop') ?></span>
                                        <span style="font-size:0.85rem; color:var(--text-muted); margin-left:4px;">
                                            <?= htmlspecialchars($log['os'] ?? '') ?> &bull; <?= htmlspecialchars($log['browser'] ?? '') ?>
                                        </span>
                                    </td>
                                    <td><code style="color:#38bdf8;">/<?= htmlspecialchars($log['page_visited']) ?></code></td>
                                    <td style="font-size:0.82rem; color:var(--text-muted);">
                                        <?= date('M d, H:i:s', strtotime($log['visited_at'])) ?>
                                    </td>
                                    <td>
                                        <?php if ($isBlocked): ?>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('এই আইপিটি আনব্লক করতে চান?');">
                                                <input type="hidden" name="action" value="unblock_ip">
                                                <input type="hidden" name="ip" value="<?= htmlspecialchars($log['ip_address']) ?>">
                                                <button type="submit" class="btn btn-success" style="padding:4px 10px; font-size:0.75rem;">✅ Unblock</button>
                                            </form>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-danger" style="padding:4px 10px; font-size:0.75rem;" 
                                                onclick="openBlockModal('<?= htmlspecialchars($log['ip_address']) ?>')">
                                                🚫 Block IP
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Blocked IPs Section -->
        <div class="section-card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h3 style="color:#ef4444;">🚫 ব্লক করা আইপি তালিকা (Firewall Blacklist)</h3>
                <button type="button" class="btn btn-danger" onclick="openBlockModal('')">+ নতুন IP ব্লক করুন</button>
            </div>
            
            <?php if (empty($blockedIpsList)): ?>
                <p style="color:var(--text-muted); font-size:0.9rem;">বর্তমানে কোনো আইপি ব্লক তালিকায় নেই।</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Blocked IP</th>
                                <th>ব্লক করার কারণ</th>
                                <th>ব্লক করার সময়</th>
                                <th>পদক্ষেপ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($blockedIpsList as $b): ?>
                                <tr>
                                    <td><span class="badge badge-ip" style="color:#ef4444;"><?= htmlspecialchars($b['ip_address']) ?></span></td>
                                    <td><?= htmlspecialchars($b['reason'] ?? 'Suspicious activity') ?></td>
                                    <td style="font-size:0.82rem; color:var(--text-muted);"><?= date('Y-m-d H:i', strtotime($b['blocked_at'])) ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('আইপিটি আনব্লক করতে চান?');">
                                            <input type="hidden" name="action" value="unblock_ip">
                                            <input type="hidden" name="ip" value="<?= htmlspecialchars($b['ip_address']) ?>">
                                            <button type="submit" class="btn btn-success" style="padding:4px 12px; font-size:0.78rem;">Unblock</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- Block IP Modal -->
    <div id="blockModal" class="modal-overlay">
        <div class="modal">
            <h3>🚫 আইপি ব্লক নিশ্চিতকরণ</h3>
            <form method="POST">
                <input type="hidden" name="action" value="block_ip">
                
                <label>আইপি অ্যাড্রেস (IP Address):</label>
                <input type="text" id="modalIpInput" name="ip" required placeholder="যেমন: 192.168.1.1">
                
                <label>ব্লক করার কারণ (Reason):</label>
                <input type="text" name="reason" value="Suspicious bot activity / Manual Ban" required>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-outline" onclick="closeBlockModal()">বাতিল</button>
                    <button type="submit" class="btn btn-danger">🚫 ব্লক নিশ্চিত করুন</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Scripts for Leaflet Map, Charts, Dark/Light Theme & AJAX Polling -->
    <script>
        // --- Theme Management ---
        function toggleTheme() {
            const currentTheme = document.documentElement.getAttribute('data-theme') || 'dark';
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('dashboard_theme', newTheme);
            updateThemeButton(newTheme);
        }

        function updateThemeButton(theme) {
            const btn = document.getElementById('themeToggle');
            if (btn) {
                btn.innerHTML = theme === 'dark' ? '🌙 Dark' : '☀️ Light';
            }
        }

        const savedTheme = localStorage.getItem('dashboard_theme') || 'dark';
        document.documentElement.setAttribute('data-theme', savedTheme);
        updateThemeButton(savedTheme);

        // Modal Handlers
        function openBlockModal(ip) {
            document.getElementById('modalIpInput').value = ip;
            document.getElementById('blockModal').style.display = 'flex';
        }
        function closeBlockModal() {
            document.getElementById('blockModal').style.display = 'none';
        }

        // --- 🗺️ Leaflet World Map Initialization ---
        const map = L.map('map').setView([23.8103, 90.4125], 5);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            maxZoom: 18
        }).addTo(map);

        const mapPoints = <?= $mapPointsJson ?>;
        const latLngBounds = [];

        mapPoints.forEach(pt => {
            if (pt.latitude && pt.longitude) {
                const lat = parseFloat(pt.latitude);
                const lon = parseFloat(pt.longitude);
                latLngBounds.push([lat, lon]);

                const marker = L.circleMarker([lat, lon], {
                    radius: Math.min(10 + (pt.visit_count * 2), 24),
                    fillColor: '#ef4444',
                    color: '#b91c1c',
                    weight: 2,
                    opacity: 1,
                    fillOpacity: 0.8
                }).addTo(map);

                marker.bindPopup(`
                    <div style="color:#0f172a; font-family:sans-serif; min-width:140px;">
                        <strong style="font-size:1.05rem; color:#1e3a8a;">${pt.city || 'Unknown'}, ${pt.country || ''}</strong><br>
                        <span style="font-family:monospace; color:#2563eb; font-size:0.9rem;">${pt.ip_address}</span><br>
                        <div style="margin-top:4px; font-size:0.85rem;"><strong>মোট ভিজিট:</strong> ${pt.visit_count} বার</div>
                    </div>
                `);
            }
        });

        if (latLngBounds.length > 0) {
            map.fitBounds(latLngBounds, { padding: [50, 50], maxZoom: 12 });
        }

        // --- 📊 Chart.js: Countries ---
        new Chart(document.getElementById('countryChart'), {
            type: 'bar',
            data: {
                labels: <?= $countryLabels ?>,
                datasets: [{
                    label: 'ভিজিট সংখ্যা',
                    data: <?= $countryValues ?>,
                    backgroundColor: '#3b82f6',
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    x: { ticks: { color: '#94a3b8' }, grid: { display: false } },
                    y: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(148, 163, 184, 0.1)' } }
                }
            }
        });

        // --- 📊 Chart.js: Top Pages ---
        new Chart(document.getElementById('pageChart'), {
            type: 'doughnut',
            data: {
                labels: <?= $pageLabels ?>,
                datasets: [{
                    data: <?= $pageValues ?>,
                    backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6', '#06b6d4'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom', labels: { color: '#94a3b8', boxWidth: 12 } }
                }
            }
        });

        // --- ⚡ Real-Time Auto-Refresh (AJAX Polling every 8 seconds) ---
        setInterval(async () => {
            try {
                const res = await fetch('api_live.php');
                if (res.ok) {
                    const data = await res.json();
                    document.getElementById('statLiveCount').textContent = Number(data.live_count).toLocaleString();
                    document.getElementById('statTodayTotal').textContent = Number(data.today_total).toLocaleString();
                    document.getElementById('statTodayUnique').textContent = Number(data.today_unique).toLocaleString();
                    document.getElementById('statBlockedCount').textContent = Number(data.blocked_count).toLocaleString();
                    document.getElementById('lastUpdateTime').textContent = `সর্বশেষ আপডেট: ${data.timestamp}`;
                }
            } catch (err) {
                console.error('Polling error:', err);
            }
        }, 8000);
    </script>
</body>
</html>
