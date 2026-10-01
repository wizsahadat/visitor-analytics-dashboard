<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/track.php';

$pdo = getDbConnection();
trackVisit($pdo, 'blog-archive');

$category = $_GET['category'] ?? '';
$search = trim($_GET['search'] ?? '');

$whereClauses = [];
$params = [];

if ($category !== '') {
    $whereClauses[] = "category = :category";
    $params['category'] = $category;
}

if ($search !== '') {
    $whereClauses[] = "(title ILIKE :search OR excerpt ILIKE :search OR content ILIKE :search)";
    $params['search'] = "%{$search}%";
}

$whereSql = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

$stmt = $pdo->prepare("SELECT * FROM posts {$whereSql} ORDER BY created_at DESC");
$stmt->execute($params);
$posts = $stmt->fetchAll();

$categories = $pdo->query("SELECT DISTINCT category FROM posts WHERE category IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Articles | DevInsights Blog</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

    <nav>
        <a href="index.php" class="brand">⚡ DevInsights</a>
        <div class="nav-links">
            <a href="index.php">Home</a>
            <a href="blog.php" class="active">All Articles</a>
            <a href="about.php">About</a>
            <a href="contact.php">Contact</a>
        </div>
        <div class="nav-auth">
            <a href="../admin/login.php" class="btn btn-signin">Sign In</a>
            <a href="../admin/dashboard.php" class="btn btn-primary">📊 Dashboard</a>
        </div>
    </nav>

    <div class="container" style="margin-top:30px;">
        <div class="section-header">
            <div>
                <h2>📚 সমস্ত আর্টিকেল ও রিসোর্স</h2>
                <p style="color:var(--text-muted); font-size:0.95rem; margin-top:4px;">
                    <?= $category ? 'ক্যাটাগরি: <strong>' . htmlspecialchars($category) . '</strong>' : 'সকল ক্যাটাগরির ব্লগ পোস্ট' ?>
                </p>
            </div>
            <form action="blog.php" method="GET" style="display:flex; gap:8px;">
                <input type="text" name="search" placeholder="সার্চ করুন..." value="<?= htmlspecialchars($search) ?>" 
                    style="padding:8px 14px; border-radius:6px; border:1px solid var(--border); outline:none; font-family:inherit;">
                <button type="submit" class="btn btn-primary" style="padding:8px 14px;">খুঁজুন</button>
                <?php if ($category || $search): ?>
                    <a href="blog.php" class="btn btn-signin" style="color:var(--text-muted) !important;">Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:28px;">
            <a href="blog.php" class="cat-pill <?= empty($category) ? 'active' : '' ?>" style="background:#e2e8f0; color:#0f172a;">All (<?= count($posts) ?>)</a>
            <?php foreach ($categories as $cat): ?>
                <a href="blog.php?category=<?= urlencode($cat) ?>" class="cat-pill <?= $category === $cat ? 'active' : '' ?>" style="background:#e2e8f0; color:#0f172a;">
                    <?= htmlspecialchars($cat) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($posts)): ?>
            <div style="background:#fff; padding:60px 20px; text-align:center; border-radius:12px; border:1px solid var(--border);">
                <h3>কোনো পোস্ট খুঁজে পাওয়া যায়নি</h3>
                <p style="color:#94a3b8; margin-top:8px;">অন্য কোনো কি-ওয়ার্ড দিয়ে আবার চেষ্টা করুন।</p>
            </div>
        <?php else: ?>
            <div class="posts-grid">
                <?php foreach ($posts as $post): ?>
                    <article class="post-card">
                        <?php if ($post['image_url']): ?>
                            <img src="<?= htmlspecialchars($post['image_url']) ?>" alt="<?= htmlspecialchars($post['title']) ?>" class="post-card-img" loading="lazy">
                        <?php endif; ?>
                        
                        <div class="post-card-body">
                            <span class="post-category"><?= htmlspecialchars($post['category']) ?></span>
                            <a href="post.php?slug=<?= urlencode($post['slug']) ?>" class="post-card-title">
                                <?= htmlspecialchars($post['title']) ?>
                            </a>
                            <p class="post-card-excerpt">
                                <?= htmlspecialchars($post['excerpt']) ?>
                            </p>
                            
                            <div class="post-card-footer">
                                <span>📅 <?= date('M d, Y', strtotime($post['created_at'])) ?></span>
                                <span>👁️ <?= number_format($post['views_count'] ?? 0) ?> views</span>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <footer>
        <p>&copy; <?= date('Y') ?> DevInsights. Powered by PHP & PostgreSQL Realtime Analytics.</p>
    </footer>

</body>
</html>
