<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/track.php';

$pdo = getDbConnection();
trackVisit($pdo, 'home'); // ব্যাকগ্রাউন্ডে ভিজিট ট্র্যাক

// ক্যাটাগরি ও সার্চ ফিল্টার
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

$stmt = $pdo->prepare("SELECT * FROM posts {$whereSql} ORDER BY created_at DESC LIMIT 9");
$stmt->execute($params);
$posts = $stmt->fetchAll();

// সব ক্যাটাগরি পাওয়ার কুয়েরি
$categories = $pdo->query("SELECT DISTINCT category FROM posts WHERE category IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DevInsights Blog | Tech, Architecture & Web Security</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

    <!-- Navbar -->
    <nav>
        <a href="index.php" class="brand">⚡ DevInsights</a>
        <div class="nav-links">
            <a href="index.php" class="active">Home</a>
            <a href="blog.php">All Articles</a>
            <a href="about.php">About</a>
            <a href="contact.php">Contact</a>
        </div>
        <div class="nav-auth">
            <a href="../admin/login.php" class="btn btn-signin">Admin Sign In</a>
            <a href="../admin/dashboard.php" class="btn btn-primary">📊 Dashboard</a>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <h1>প্রযুক্তি, সিকিউরিটি ও আধুনিক ডেভেলপমেন্ট</h1>
        <p>ওয়েব আর্কিটেকচার, ডাটাবেস অপ্টিমাইজেশন এবং ক্লাউড সিকিউরিটির গভীর প্রযুক্তিগত আর্টিকেল।</p>
        
        <form action="blog.php" method="GET" class="search-box">
            <input type="text" name="search" placeholder="যেকোনো টপিক বা আর্টিকেল খুঁজুন..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit">খুঁজুন</button>
        </form>

        <div class="category-bar">
            <a href="blog.php" class="cat-pill <?= empty($category) ? 'active' : '' ?>">All Topics</a>
            <?php foreach ($categories as $cat): ?>
                <a href="blog.php?category=<?= urlencode($cat) ?>" class="cat-pill <?= $category === $cat ? 'active' : '' ?>">
                    <?= htmlspecialchars($cat) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Main Content -->
    <div class="container">
        <div class="section-header">
            <div>
                <h2>🔥 লেটেস্ট আর্টিকেলসমূহ</h2>
                <p style="color:var(--text-muted); font-size:0.95rem; margin-top:4px;">সাম্প্রতিক প্রকাশিত প্রযুক্তিগত ব্লগ পোস্ট</p>
            </div>
            <a href="blog.php" class="btn btn-signin" style="color:var(--primary) !important; border-color:var(--border);">সব আর্টিকেল দেখুন →</a>
        </div>

        <?php if (empty($posts)): ?>
            <div style="background:#fff; padding:60px 20px; text-align:center; border-radius:12px; border:1px solid var(--border);">
                <h3 style="color:#64748b; font-size:1.3rem; margin-bottom:8px;">কোনো পোস্ট পাওয়া যায়নি</h3>
                <p style="color:#94a3b8;">অন্য কোনো কি-ওয়ার্ড দিয়ে সার্চ করুন অথবা সব পোস্ট দেখুন।</p>
                <a href="index.php" class="btn btn-primary" style="margin-top:16px;">রিসেট ফিল্টার</a>
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

        <!-- Demo Credentials Notification Card -->
        <div class="demo-badge-card">
            <div>
                <strong style="color:#1e40af; font-size:1.05rem;">🔐 অ্যাডমিন ড্যাশবোর্ড ও ভিজিটর ট্র্যাকিং লাইভ সক্রিয়</strong>
                <p style="color:#3b82f6; font-size:0.9rem; margin-top:2px;">প্রতিটি পেজ ভিজিট ব্যাকগ্রাউন্ডে অ্যানালিটিক্স ও জিও ম্যাপে রেকর্ড হচ্ছে।</p>
            </div>
            <div>
                <a href="../admin/login.php" class="btn btn-primary">ড্যাশবোর্ডে লগইন করুন (admin / admin123) →</a>
            </div>
        </div>
    </div>

    <footer>
        <p>&copy; <?= date('Y') ?> DevInsights. Powered by PHP & PostgreSQL Realtime Analytics.</p>
    </footer>

</body>
</html>
