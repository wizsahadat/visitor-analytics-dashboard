<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/auth_check.php';

$pdo = getDbConnection();
ensureSchemaUpToDate($pdo);

$message = '';

// পোস্ট ডিলিট হ্যান্ডলিং
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleteId = (int)$_POST['delete_id'];
    $stmt = $pdo->prepare("DELETE FROM posts WHERE id = :id");
    if ($stmt->execute(['id' => $deleteId])) {
        $message = "পোস্টটি সফলভাবে মুছে ফেলা হয়েছে!";
    }
}

$posts = $pdo->query("SELECT * FROM posts ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="bn" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Blog Posts | Admin Panel</title>
    <style>
        :root[data-theme="dark"] {
            --bg: #0b0f19;
            --card-bg: #151c2e;
            --card-border: #222f49;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --header-bg: rgba(11, 15, 25, 0.92);
            --table-header: #0d1322;
            --primary: #3b82f6;
            --danger: #ef4444;
            --success: #10b981;
        }
        :root[data-theme="light"] {
            --bg: #f1f5f9;
            --card-bg: #ffffff;
            --card-border: #e2e8f0;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --header-bg: #ffffff;
            --table-header: #f8fafc;
            --primary: #2563eb;
            --danger: #dc2626;
            --success: #059669;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif; background: var(--bg); color: var(--text-main); min-height: 100vh; padding-bottom: 50px; }
        
        header { background: var(--header-bg); border-bottom: 1px solid var(--card-border); padding: 14px 36px; display: flex; justify-content: space-between; align-items: center; }
        header .brand { font-size: 1.25rem; font-weight: 700; color: var(--text-main); text-decoration: none; }
        header .nav-links { display: flex; align-items: center; gap: 14px; }
        
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 8px; font-size: 0.85rem; font-weight: 600; text-decoration: none; cursor: pointer; border: none; }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-danger { background: rgba(239, 68, 68, 0.15); color: var(--danger); border: 1px solid rgba(239, 68, 68, 0.3); }
        .btn-outline { background: transparent; color: var(--text-muted); border: 1px solid var(--card-border); }

        .container { max-width: 1240px; margin: 30px auto; padding: 0 24px; }
        .card { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px; padding: 24px; }
        
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem; }
        th { background: var(--table-header); color: var(--text-muted); padding: 12px 16px; border-bottom: 1px solid var(--card-border); }
        td { padding: 14px 16px; border-bottom: 1px solid var(--card-border); }
        
        .badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 0.78rem; font-weight: 600; background: var(--card-border); color: var(--text-main); }
    </style>
</head>
<body>

    <header>
        <a href="dashboard.php" class="brand">⚡ DevInsights Admin</a>
        <div class="nav-links">
            <a href="dashboard.php" class="btn btn-outline">📊 Analytics & Map</a>
            <a href="posts.php" class="btn btn-primary">✍️ Manage Posts</a>
            <a href="../public/index.php" class="btn btn-outline" target="_blank">🌐 Live Blog</a>
            <a href="logout.php" class="btn btn-danger">লগআউট</a>
        </div>
    </header>

    <div class="container">
        
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
            <div>
                <h2 style="font-size:1.6rem;">✍️ সমস্ত ব্লগ পোস্টসমূহ</h2>
                <p style="color:var(--text-muted); font-size:0.9rem; margin-top:4px;">পোস্ট তৈরি, এডিট এবং ভিজিটর ভিউ পর্যবেক্ষণ করুন</p>
            </div>
            <a href="create_post.php" class="btn btn-primary" style="padding:10px 20px; font-size:0.95rem;">+ নতুন ব্লগ লিখুন</a>
        </div>

        <?php if ($message): ?>
            <div style="background:rgba(16, 185, 129, 0.15); border:1px solid #059669; color:#34d399; padding:12px 20px; border-radius:8px; margin-bottom:20px;">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <?php if (empty($posts)): ?>
                <p style="color:var(--text-muted); text-align:center; padding:30px;">বর্তমানে কোনো পোস্ট নেই। নতুন পোস্ট লিখতে উপরের বাটনে ক্লিক করুন।</p>
            <?php else: ?>
                <table style="width:100%;">
                    <thead>
                        <tr>
                            <th>শিরোনাম (Title)</th>
                            <th>ক্যাটাগরি</th>
                            <th>পাঠক ভিউ</th>
                            <th>প্রকাশের তারিখ</th>
                            <th style="text-align:right;">পদক্ষেপ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($posts as $p): ?>
                            <tr>
                                <td>
                                    <a href="../public/post.php?slug=<?= urlencode($p['slug']) ?>" target="_blank" style="color:var(--text-main); font-weight:600; text-decoration:none;">
                                        <?= htmlspecialchars($p['title']) ?> ↗
                                    </a>
                                </td>
                                <td><span class="badge"><?= htmlspecialchars($p['category']) ?></span></td>
                                <td><strong style="color:#38bdf8;">👁️ <?= number_format($p['views_count']) ?></strong></td>
                                <td style="font-size:0.85rem; color:var(--text-muted);"><?= date('M d, Y', strtotime($p['created_at'])) ?></td>
                                <td style="text-align:right;">
                                    <a href="edit_post.php?id=<?= $p['id'] ?>" class="btn btn-outline" style="padding:4px 10px; font-size:0.78rem;">✏️ Edit</a>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('পোস্টটি মুছে ফেলতে চান?');">
                                        <input type="hidden" name="delete_id" value="<?= $p['id'] ?>">
                                        <button type="submit" class="btn btn-danger" style="padding:4px 10px; font-size:0.78rem;">🗑️ Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    </div>

</body>
</html>
