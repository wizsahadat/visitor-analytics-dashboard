<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/track.php';

$pdo = getDbConnection();
trackVisit($pdo, 'about');
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About | DevInsights Blog</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

    <nav>
        <a href="index.php" class="brand">⚡ DevInsights</a>
        <div class="nav-links">
            <a href="index.php">Home</a>
            <a href="blog.php">All Articles</a>
            <a href="about.php" class="active">About</a>
            <a href="contact.php">Contact</a>
        </div>
        <div class="nav-auth">
            <a href="../admin/login.php" class="btn btn-signin">Sign In</a>
            <a href="../admin/dashboard.php" class="btn btn-primary">📊 Dashboard</a>
        </div>
    </nav>

    <div class="article-container" style="margin-top:50px;">
        <div class="article-content">
            <h1 style="font-size:2.2rem; font-weight:800; color:#0f172a; margin-bottom:16px;">আমাদের সম্পর্কে 👋</h1>
            <p><strong>DevInsights</strong> হলো আধুনিক ওয়েব প্রযুক্তি, ডাটাবেস আর্কিটেকচার এবং ক্লাউড সিকিউরিটি নিয়ে একটি বিশেষায়িত জ্ঞানচর্চার প্ল্যাটফর্ম।</p>
            
            <h2>আমাদের লক্ষ্য 🎯</h2>
            <p>আমাদের মূল উদ্দেশ্য হলো ডেভেলপার ও প্রযুক্তিপ্রেমীদের জন্য রিয়েল-ওয়ার্ল্ড সিস্টেম ডিজাইন, হাই-পারফরম্যান্স ব্যাকএন্ড এবং প্রোডাকশন-লেভেল সিকিউরিটি নিয়ে সহজ বাংলায় গভীর প্রযুক্তিগত জ্ঞান পৌঁছে দেওয়া।</p>

            <h2>টেকনোলজি স্ট্যাক 🛠️</h2>
            <ul style="margin-left:24px; margin-bottom:20px; color:#475569;">
                <li><strong>Backend:</strong> PHP 8.x + Modern PDO Architecture</li>
                <li><strong>Database:</strong> PostgreSQL (High Performance Indexed Schema)</li>
                <li><strong>Analytics Engine:</strong> Native Privacy-First Geolocation & IP Threat Shield</li>
                <li><strong>Mapping:</strong> Leaflet.js & OpenStreetMap Integration</li>
            </ul>

            <div style="margin-top:30px;">
                <a href="blog.php" class="btn btn-primary">📖 আর্টিকেলসমূহ পড়ুন</a>
                <a href="contact.php" class="btn btn-signin" style="margin-left:8px; border-color:var(--border);">যোগাযোগ করুন</a>
            </div>
        </div>
    </div>

    <footer>
        <p>&copy; <?= date('Y') ?> DevInsights. Powered by PHP & PostgreSQL Realtime Analytics.</p>
    </footer>

</body>
</html>
