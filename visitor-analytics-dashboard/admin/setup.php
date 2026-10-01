<?php
/**
 * ⚠️ schema.sql রান করলেই একটা ডিফল্ট অ্যাডমিন (username: admin, password: admin123)
 * এমনিতেই তৈরি হয়ে যায় — তাই সাধারণত এই ফাইলটা লাগবে না।
 *
 * এই ফাইলটা তখনই দরকার হবে যদি তুমি ডিফল্ট অ্যাডমিনের বদলে নিজের
 * পছন্দমতো ইউজারনেম/পাসওয়ার্ড দিয়ে শুরু করতে চাও (schema.sql এর
 * admins টেবিল থেকে ডিফল্ট রো ডিলিট করে তারপর এই ফাইল চালাও)।
 *
 * ব্যবহারের পর এই ফাইলটি ডিলিট করে দাও অথবা সার্ভার থেকে সরিয়ে ফেলো —
 * নাহলে যে কেউ নতুন অ্যাডমিন বানিয়ে ফেলতে পারবে।
 */

require_once __DIR__ . '/../config/database.php';

$pdo = getDbConnection();
$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $message = 'ইউজারনেম এবং পাসওয়ার্ড দুটোই দিতে হবে।';
    } elseif (strlen($password) < 6) {
        $message = 'পাসওয়ার্ড কমপক্ষে ৬ ক্যারেক্টার হতে হবে।';
    } else {
        // ইতিমধ্যে কোনো অ্যাডমিন আছে কিনা চেক করা (একাধিকবার এই ফাইল চালিয়ে যেন ঝামেলা না হয়)
        $check = $pdo->query("SELECT COUNT(*) AS total FROM admins")->fetch();

        if ($check['total'] > 0) {
            $message = 'ইতিমধ্যে একটি অ্যাডমিন একাউন্ট আছে। নিরাপত্তার জন্য এই ফাইলটি এখনই ডিলিট করো।';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("INSERT INTO admins (username, password_hash) VALUES (:username, :hash)");
            $stmt->execute(['username' => $username, 'hash' => $hash]);

            $success = true;
            $message = 'অ্যাডমিন একাউন্ট তৈরি হয়ে গেছে! এখন login.php দিয়ে লগইন করো, আর এই setup.php ফাইলটি অবশ্যই ডিলিট করে দাও।';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <title>Admin Setup</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f6fa; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .box { background: #fff; padding: 32px; border-radius: 10px; width: 320px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        h2 { margin-bottom: 16px; }
        input { width: 100%; padding: 10px; margin-bottom: 12px; border: 1px solid #dfe6e9; border-radius: 6px; }
        button { width: 100%; padding: 10px; background: #2d3436; color: #fff; border: none; border-radius: 6px; cursor: pointer; }
        .msg { margin-bottom: 12px; padding: 10px; border-radius: 6px; font-size: 0.9rem; }
        .msg.error { background: #ffeaa7; color: #d63031; }
        .msg.success { background: #d1f2eb; color: #00875a; }
    </style>
</head>
<body>
    <div class="box">
        <h2>প্রথম অ্যাডমিন তৈরি করো</h2>

        <?php if ($message): ?>
            <div class="msg <?= $success ? 'success' : 'error' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if (!$success): ?>
            <form method="POST">
                <input type="text" name="username" placeholder="Username" required>
                <input type="password" name="password" placeholder="Password (min 6 characters)" required>
                <button type="submit">অ্যাডমিন তৈরি করো</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
