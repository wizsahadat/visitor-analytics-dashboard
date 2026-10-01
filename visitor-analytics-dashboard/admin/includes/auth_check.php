<?php
/**
 * এই ফাইলটা প্রতিটা admin পেজের একদম শুরুতে require করতে হবে।
 * লগইন করা না থাকলে সাথে সাথে login.php তে পাঠিয়ে দেবে।
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}
