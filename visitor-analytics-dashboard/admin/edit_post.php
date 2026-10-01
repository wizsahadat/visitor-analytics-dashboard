<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/auth_check.php';

$pdo = getDbConnection();
ensureSchemaUpToDate($pdo);

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: posts.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM posts WHERE id = :id");
$stmt->execute(['id' => $id]);
$post = $stmt->fetch();

if (!$post) {
    header('Location: posts.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $category = trim($_POST['category'] ?? 'Technology');
    $image_url = trim($_POST['image_url'] ?? '');
    $excerpt = trim($_POST['excerpt'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if ($title === '' || $content === '') {
        $error = 'শিরোনাম এবং বিষয়বস্তু আবশ্যক।';
    } else {
        try {
            $upStmt = $pdo->prepare("
                UPDATE posts 
                SET title = :title, slug = :slug, excerpt = :excerpt, content = :content, category = :category, image_url = :image_url
                WHERE id = :id
            ");
            $upStmt->execute([
                'title' => $title,
                'slug' => $slug,
                'excerpt' => $excerpt,
                'content' => $content,
                'category' => $category,
                'image_url' => $image_url,
                'id' => $id
            ]);
            header('Location: posts.php');
            exit;
        } catch (PDOException $e) {
            $error = 'ত্রুটি হয়েছে: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="bn" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Blog Post | Admin</title>
    <style>
        :root { --bg: #0b0f19; --card-bg: #151c2e; --card-border: #222f49; --text-main: #f8fafc; --text-muted: #94a3b8; --primary: #3b82f6; --danger: #ef4444; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: var(--bg); color: var(--text-main); min-height: 100vh; padding-bottom: 60px; }
        header { background: #151c2e; border-bottom: 1px solid var(--card-border); padding: 14px 36px; display: flex; justify-content: space-between; align-items: center; }
        header .brand { font-size: 1.2rem; font-weight: 700; color: #fff; text-decoration: none; }
        .container { max-width: 900px; margin: 30px auto; padding: 0 20px; }
        .card { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px; padding: 32px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-size: 0.88rem; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; }
        input, select, textarea { width: 100%; padding: 11px 14px; background: #0b0f19; border: 1px solid var(--card-border); color: #fff; border-radius: 8px; font-family: inherit; font-size: 0.95rem; outline: none; }
        input:focus, textarea:focus { border-color: var(--primary); }
        .btn { display: inline-flex; padding: 10px 20px; border-radius: 8px; font-weight: 600; text-decoration: none; cursor: pointer; border: none; }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-outline { background: transparent; color: var(--text-muted); border: 1px solid var(--card-border); }
    </style>
</head>
<body>

    <header>
        <a href="dashboard.php" class="brand">⚡ DevInsights Admin</a>
        <div>
            <a href="posts.php" class="btn btn-outline">← All Posts</a>
        </div>
    </header>

    <div class="container">
        <div class="card">
            <h2 style="margin-bottom:24px; font-size:1.5rem;">✏️ পোস্ট এডিট করুন</h2>

            <?php if ($error): ?>
                <div style="background:rgba(239, 68, 68, 0.15); color:#f87171; padding:12px; border-radius:8px; margin-bottom:20px;">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>পোস্টের শিরোনাম (Title) *</label>
                    <input type="text" name="title" required value="<?= htmlspecialchars($post['title']) ?>">
                </div>

                <div class="form-group">
                    <label>ইউআরএল স্লাগ (Slug)</label>
                    <input type="text" name="slug" required value="<?= htmlspecialchars($post['slug']) ?>">
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                    <div class="form-group">
                        <label>ক্যাটাগরি (Category)</label>
                        <input type="text" name="category" value="<?= htmlspecialchars($post['category']) ?>">
                    </div>
                    <div class="form-group">
                        <label>কভার ছবি URL (Cover Image URL)</label>
                        <input type="text" name="image_url" value="<?= htmlspecialchars($post['image_url'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>সংক্ষিপ্ত বিবরণ (Excerpt)</label>
                    <textarea name="excerpt" rows="2"><?= htmlspecialchars($post['excerpt'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label>মূল বিষয়বস্তু (Full HTML Content) *</label>
                    <textarea name="content" rows="12" required><?= htmlspecialchars($post['content']) ?></textarea>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:24px;">
                    <a href="posts.php" class="btn btn-outline">বাতিল</a>
                    <button type="submit" class="btn btn-primary">💾 পরিবর্তন সংরক্ষণ করুন</button>
                </div>
            </form>
        </div>
    </div>

</body>
</html>
