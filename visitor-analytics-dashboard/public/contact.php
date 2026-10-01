<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/track.php';

$pdo = getDbConnection();
trackVisit($pdo, 'contact');

$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    if ($name && $email && $message) {
        $successMessage = "ধন্যবাদ {$name}! আপনার বার্তাটি সফলভাবে পাঠানো হয়েছে। আমরা শীঘ্রই যোগাযোগ করব।";
    }
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us | DevInsights Blog</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-size: 0.9rem; font-weight: 600; color: #334155; margin-bottom: 6px; }
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 0.95rem;
            font-family: inherit;
            outline: none;
            transition: border-color 0.2s;
        }
        .form-group input:focus, .form-group textarea:focus { border-color: var(--primary); }
    </style>
</head>
<body>

    <nav>
        <a href="index.php" class="brand">⚡ DevInsights</a>
        <div class="nav-links">
            <a href="index.php">Home</a>
            <a href="blog.php">All Articles</a>
            <a href="about.php">About</a>
            <a href="contact.php" class="active">Contact</a>
        </div>
        <div class="nav-auth">
            <a href="../admin/login.php" class="btn btn-signin">Sign In</a>
            <a href="../admin/dashboard.php" class="btn btn-primary">📊 Dashboard</a>
        </div>
    </nav>

    <div class="article-container" style="margin-top:50px;">
        <div class="article-content">
            <h1 style="font-size:2.2rem; font-weight:800; color:#0f172a; margin-bottom:12px;">যোগাযোগ করুন 📬</h1>
            <p style="color:var(--text-muted); margin-bottom:28px;">যেকোনো টেকনোলজি বিষয়ক আলোচনা, স্পন্সরশিপ বা প্রশ্নের জন্য মেসেজ দিন।</p>

            <?php if ($successMessage): ?>
                <div style="background:#dcfce7; color:#15803d; padding:14px 20px; border-radius:8px; margin-bottom:24px; border:1px solid #bbf7d0;">
                    <?= htmlspecialchars($successMessage) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>আপনার নাম (Name)</label>
                    <input type="text" name="name" required placeholder="আপনার পুরো নাম">
                </div>
                <div class="form-group">
                    <label>ইমেইল অ্যাড্রেস (Email)</label>
                    <input type="email" name="email" required placeholder="name@example.com">
                </div>
                <div class="form-group">
                    <label>বার্তা (Message)</label>
                    <textarea name="message" rows="5" required placeholder="আপনার মেসেজ লিখুন..."></textarea>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%; padding:12px; font-size:1rem;">বার্তা পাঠান →</button>
            </form>
        </div>
    </div>

    <footer>
        <p>&copy; <?= date('Y') ?> DevInsights. Powered by PHP & PostgreSQL Realtime Analytics.</p>
    </footer>

</body>
</html>
