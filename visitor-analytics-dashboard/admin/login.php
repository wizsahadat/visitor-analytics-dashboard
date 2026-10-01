<?php
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// আগে থেকেই লগইন থাকলে সরাসরি ড্যাশবোর্ডে পাঠিয়ে দাও
if (!empty($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

$pdo = getDbConnection();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'ইউজারনেম এবং পাসওয়ার্ড দুটোই দাও।';
    } else {
        $stmt = $pdo->prepare("SELECT id, username, password_hash FROM admins WHERE username = :username");
        $stmt->execute(['username' => $username]);
        $admin = $stmt->fetch();

        // password_verify() ব্যবহার করা হচ্ছে
        if ($admin && password_verify($password, $admin['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];

            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'ইউজারনেম অথবা পাসওয়ার্ড ভুল।';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Visitor Analytics</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, sans-serif; 
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            min-height: 100vh; 
            padding: 20px;
        }
        .box { 
            background: #ffffff; 
            padding: 36px 32px; 
            border-radius: 14px; 
            width: 100%;
            max-width: 380px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.25); 
        }
        .logo { font-size: 1.5rem; font-weight: 700; color: #0f172a; margin-bottom: 6px; }
        p.subtitle { color: #64748b; font-size: 0.9rem; margin-bottom: 24px; }
        label { display: block; font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 6px; }
        input { 
            width: 100%; 
            padding: 11px 14px; 
            margin-bottom: 16px; 
            border: 1px solid #cbd5e1; 
            border-radius: 8px; 
            font-size: 0.95rem;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        input:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.15); }
        button.btn-submit { 
            width: 100%; 
            padding: 12px; 
            background: linear-gradient(135deg, #2563eb, #1d4ed8); 
            color: #fff; 
            border: none; 
            border-radius: 8px; 
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer; 
            transition: opacity 0.2s;
        }
        button.btn-submit:hover { opacity: 0.92; }
        .error { 
            background: #fef2f2; 
            color: #dc2626; 
            border: 1px solid #fecaca;
            padding: 10px 14px; 
            border-radius: 8px; 
            margin-bottom: 16px; 
            font-size: 0.88rem; 
        }
        .demo-box {
            margin-top: 20px;
            background: #eff6ff;
            border: 1px dashed #93c5fd;
            border-radius: 8px;
            padding: 12px 14px;
            font-size: 0.85rem;
            color: #1e40af;
        }
        .demo-box strong { color: #1e3a8a; }
        .fill-btn {
            display: inline-block;
            margin-top: 8px;
            background: #2563eb;
            color: #fff;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 0.78rem;
            text-decoration: none;
            cursor: pointer;
            border: none;
        }
        .footer-link {
            text-align: center;
            margin-top: 18px;
            font-size: 0.88rem;
        }
        .footer-link a { color: #64748b; text-decoration: none; }
        .footer-link a:hover { color: #2563eb; text-decoration: underline; }
    </style>
</head>
<body>
    <div class="box">
        <div class="logo">📊 Admin Login</div>
        <p class="subtitle">অ্যানালিটিক্স ড্যাশবোর্ডে প্রবেশ করতে লগইন করুন</p>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" id="loginForm">
            <label>Username</label>
            <input type="text" id="username" name="username" placeholder="Username" required autofocus>
            
            <label>Password</label>
            <input type="password" id="password" name="password" placeholder="Password" required>
            
            <button type="submit" class="btn-submit">লগইন করুন</button>
        </form>

        <?php if (($_ENV['APP_ENV'] ?? 'development') === 'development'): ?>
            <div class="demo-box">
                🔑 <strong>ডেমো ক্রেডেনশিয়াল:</strong><br>
                Username: <code>admin</code> | Password: <code>admin123</code><br>
                <button type="button" class="fill-btn" onclick="fillDemo()">⚡ Auto Fill Demo Data</button>
            </div>
        <?php endif; ?>

        <div class="footer-link">
            <a href="../public/index.php">← মূল ওয়েবসাইটে ফিরে যান</a>
        </div>
    </div>

    <script>
        function fillDemo() {
            document.getElementById('username').value = 'admin';
            document.getElementById('password').value = 'admin123';
        }
    </script>
</body>
</html>
