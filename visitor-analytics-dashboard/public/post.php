<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/track.php';

$pdo = getDbConnection();

$slug = trim($_GET['slug'] ?? '');

if ($slug === '') {
    header('Location: blog.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM posts WHERE slug = :slug LIMIT 1");
$stmt->execute(['slug' => $slug]);
$post = $stmt->fetch();

if (!$post) {
    http_response_code(404);
    echo "<h1>৪০৪ - পোস্টটি পাওয়া যায়নি</h1><a href='blog.php'>ব্লগ পেজে ফিরে যান</a>";
    exit;
}

// ভিউ কাউন্ট ১ বৃদ্ধি করা
$pdo->prepare("UPDATE posts SET views_count = views_count + 1 WHERE id = :id")->execute(['id' => $post['id']]);

// ব্যাকগ্রাউন্ডে ভিজিটর লগ করা
trackVisit($pdo, 'article: ' . $post['slug']);

// পড়ার সময় হিসাব (Reading time)
$wordCount = str_word_count(strip_tags($post['content']));
$readTime = max(1, ceil($wordCount / 180));

// রিলেটেড পোস্ট আনা
$relStmt = $pdo->prepare("SELECT id, title, slug, image_url, created_at FROM posts WHERE category = :cat AND id != :id ORDER BY created_at DESC LIMIT 3");
$relStmt->execute(['cat' => $post['category'], 'id' => $post['id']]);
$relatedPosts = $relStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($post['title']) ?> | DevInsights Blog</title>
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

    <div class="article-container">
        
        <div style="margin-bottom:20px; font-size:0.9rem; color:var(--text-muted);">
            <a href="index.php" style="color:var(--text-muted); text-decoration:none;">Home</a> &rsaquo; 
            <a href="blog.php" style="color:var(--text-muted); text-decoration:none;">Articles</a> &rsaquo; 
            <a href="blog.php?category=<?= urlencode($post['category']) ?>" style="color:var(--primary); text-decoration:none;"><?= htmlspecialchars($post['category']) ?></a>
        </div>

        <header class="article-header">
            <span class="post-category"><?= htmlspecialchars($post['category']) ?></span>
            <h1><?= htmlspecialchars($post['title']) ?></h1>
            <div class="article-meta">
                <span>📅 <?= date('F d, Y', strtotime($post['created_at'])) ?></span>
                <span>⏱️ <?= $readTime ?> মিনিট রিড</span>
                <span>👁️ <?= number_format($post['views_count'] + 1) ?> ভিউ</span>
            </div>
        </header>

        <?php if ($post['image_url']): ?>
            <img src="<?= htmlspecialchars($post['image_url']) ?>" alt="<?= htmlspecialchars($post['title']) ?>" class="article-cover">
        <?php endif; ?>

        <div class="article-content">
            <?= $post['content'] ?>
        </div>

        <!-- রিলেটেড পোস্ট সেকশন -->
        <?php if (!empty($relatedPosts)): ?>
            <div style="margin-top:50px;">
                <h3 style="font-size:1.3rem; margin-bottom:20px; color:#0f172a;">🔗 সম্পর্কিত আরও আর্টিকেল:</h3>
                <div class="posts-grid" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));">
                    <?php foreach ($relatedPosts as $r): ?>
                        <div class="post-card">
                            <?php if ($r['image_url']): ?>
                                <img src="<?= htmlspecialchars($r['image_url']) ?>" style="height:140px; object-fit:cover;">
                            <?php endif; ?>
                            <div class="post-card-body" style="padding:16px;">
                                <a href="post.php?slug=<?= urlencode($r['slug']) ?>" style="font-weight:700; color:#0f172a; text-decoration:none; font-size:1.05rem;">
                                    <?= htmlspecialchars($r['title']) ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div style="margin-top:40px; text-align:center;">
            <a href="blog.php" class="btn btn-primary">← সব আর্টিকেলে ফিরে যান</a>
        </div>

    </div>

    <footer>
        <p>&copy; <?= date('Y') ?> DevInsights. Powered by PHP & PostgreSQL Realtime Analytics.</p>
    </footer>

</body>
</html>
