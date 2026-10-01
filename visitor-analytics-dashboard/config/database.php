<?php
/**
 * PostgreSQL ডাটাবেস কানেকশন (PDO ব্যবহার করে)
 * এই ফাইলটি যেকোনো জায়গা থেকে require/include করে $pdo ভ্যারিয়েবল পাওয়া যাবে।
 */

require_once __DIR__ . '/env.php';

function getDbConnection(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo; // একই রিকোয়েস্টে বারবার নতুন কানেকশন না বানিয়ে পুরনোটাই রিইউজ করা
    }

    $host = $_ENV['DB_HOST'] ?? 'localhost';
    $port = $_ENV['DB_PORT'] ?? '5432';
    $dbname = $_ENV['DB_NAME'] ?? '';
    $user = $_ENV['DB_USER'] ?? '';
    $password = $_ENV['DB_PASSWORD'] ?? '';

    $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";

    try {
        $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,       // এরর হলে exception throw করবে
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,  // ফলাফল associative array আকারে পাবো
            PDO::ATTR_EMULATE_PREPARES => false,               // real prepared statements (SQL injection থেকে আরও সুরক্ষা)
        ]);
        return $pdo;
    } catch (PDOException $e) {
        // ডেভেলপমেন্টে আসল এরর দেখানো যাতে সহজে ডিবাগ করা যায়
        error_log('Database connection failed: ' . $e->getMessage());
        http_response_code(500);
        $appEnv = $_ENV['APP_ENV'] ?? 'development';
        if ($appEnv === 'development') {
            die('<h3>ডাটাবেস কানেকশন এরর:</h3><p style="color:red;">' . htmlspecialchars($e->getMessage()) . '</p>');
        }
        die('দুঃখিত, এই মুহূর্তে সার্ভারে সমস্যা হচ্ছে। একটু পরে আবার চেষ্টা করো।');
    }
}
