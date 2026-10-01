<?php
/**
 * ছোট্ট .env loader — Composer ছাড়াই কাজ চালানোর জন্য।
 * .env ফাইল থেকে key=value পড়ে $_ENV এবং getenv() এ সেট করে দেয়।
 */

function loadEnv(string $path): void
{
    if (!file_exists($path)) {
        // .env না থাকলে অ্যাপ বন্ধ করে দেওয়া ভালো, চুপচাপ চালানো নিরাপদ না
        die(".env ফাইল পাওয়া যায়নি। .env.example কপি করে .env বানাও এবং তথ্য পূরণ করো।");
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        // কমেন্ট লাইন (# দিয়ে শুরু) স্কিপ করো
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (!str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        // ভ্যালুর চারপাশে কোটেশন থাকলে বাদ দাও
        $value = trim($value, "\"'");

        if ($key !== '') {
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}

loadEnv(__DIR__ . '/../.env');
