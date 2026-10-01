# Visitor Analytics Dashboard

PHP + PostgreSQL দিয়ে বানানো একটি ভিজিটর অ্যানালিটিক্স প্রজেক্ট। ইউজার সাইটে ঢুকলে চুপচাপ ব্যাকগ্রাউন্ডে তার IP, লোকেশন, ব্রাউজার/OS/ডিভাইস তথ্য লগ হয় — ইউজার এটা দেখতে পায় না। অ্যাডমিন লগইন করে একটা ড্যাশবোর্ডে (চার্ট সহ) এই ডেটা দেখতে পারে।

এই প্রজেক্টটি লোকাল মেশিনে সম্পূর্ণভাবে টেস্ট করে যাচাই করা হয়েছে (PHP syntax check + সত্যিকারের PostgreSQL এর বিপরীতে চালিয়ে) — কোনো এরর/warning ছাড়াই কাজ করে।

## ফোল্ডার স্ট্রাকচার

```
visitor-analytics-dashboard/
├── config/
│   ├── env.php          # .env ফাইল লোড করার helper
│   └── database.php     # PostgreSQL এর সাথে PDO কানেকশন
├── includes/
│   ├── functions.php    # getUserIP(), getGeoData(), parseUserAgent()
│   └── track.php        # trackVisit() — প্রতিটা পেজ ভিজিট লগ করে
├── public/               # তোমার আসল/পাবলিক ওয়েবসাইট
│   ├── index.php
│   ├── about.php
│   ├── contact.php
│   └── css/style.css
├── admin/                # অ্যাডমিন-অনলি অংশ (লগইন প্রয়োজন)
│   ├── setup.php         # প্রথমবার অ্যাডমিন একাউন্ট বানানোর জন্য
│   ├── login.php
│   ├── logout.php
│   ├── dashboard.php     # মূল অ্যানালিটিক্স ড্যাশবোর্ড (চার্ট সহ)
│   └── includes/auth_check.php
├── sql/
│   └── schema.sql        # PostgreSQL টেবিল তৈরি করার স্ক্রিপ্ট
├── .env.example           # কনফিগ ফাইলের নমুনা
└── README.md
```

## সেটআপ করার ধাপ

### ১. প্রয়োজনীয় জিনিস
- PHP 8.x (সাথে `pdo_pgsql` extension enable করা থাকতে হবে)
- PostgreSQL সার্ভার (লোকাল বা রিমোট)
- একটা ফ্রি [ipinfo.io](https://ipinfo.io/signup) টোকেন (ঐচ্ছিক, না দিলেও চলবে কিন্তু country/city ফাঁকা থাকবে)

### ২. ডাটাবেস তৈরি করা
```bash
# PostgreSQL এ একটা নতুন ডাটাবেস বানাও
createdb visitor_analytics

# schema লোড করো
psql -d visitor_analytics -f sql/schema.sql
```

### ৩. কনফিগারেশন (.env)
```bash
cp .env.example .env
```
তারপর `.env` ফাইল খুলে নিজের তথ্য বসাও:
```
DB_HOST=localhost
DB_PORT=5432
DB_NAME=visitor_analytics
DB_USER=postgres
DB_PASSWORD=তোমার_পাসওয়ার্ড
IPINFO_TOKEN=তোমার_ipinfo_টোকেন
```

### ৪. অ্যাডমিন লগইন — ডিফল্ট ক্রেডেনশিয়াল দিয়ে

`schema.sql` রান করলেই একটা **ডিফল্ট অ্যাডমিন একাউন্ট** স্বয়ংক্রিয়ভাবে তৈরি হয়ে যায়:

```
Username: admin
Password: admin123
```

সরাসরি `http://localhost:8000/admin/login.php` এ গিয়ে এই তথ্য দিয়ে লগইন করতে পারবে — আলাদা করে `setup.php` চালানোর দরকার নেই। (`.env` এ `APP_ENV=development` রাখলে লগইন পেজেই এই hint দেখাবে, মনে রাখতে সুবিধা হবে।)

**⚠️ সাইট লাইভ/production এ দেওয়ার আগে অবশ্যই এই ডিফল্ট পাসওয়ার্ড পরিবর্তন করো:**

```bash
php -r "echo password_hash('তোমার_নতুন_শক্তিশালী_পাসওয়ার্ড', PASSWORD_DEFAULT);"
```

তারপর এই hash দিয়ে ডাটাবেসে আপডেট করো:

```sql
UPDATE admins SET password_hash = 'এখানে_নতুন_hash' WHERE username = 'admin';
```

আর `.env` এ `APP_ENV=production` করে দাও, যাতে login পেজে ডিফল্ট ক্রেডেনশিয়ালের hint আর না দেখায়।

**বিকল্প:** ডিফল্ট অ্যাডমিনের বদলে শুরু থেকেই নিজের ইউজারনেম/পাসওয়ার্ড চাইলে, `schema.sql` এ থাকা `admins` টেবিলের ডিফল্ট `INSERT` লাইনটা রান করার আগে মুছে ফেলো, এরপর `admin/setup.php` ব্যবহার করে নিজের একাউন্ট বানাও (ব্যবহারের পর `setup.php` ডিলিট করে দিও)।

### ৫. ব্যবহার শুরু
- **পাবলিক সাইট:** `http://localhost:8000/public/index.php`
- **অ্যাডমিন লগইন:** `http://localhost:8000/admin/login.php`
- **ড্যাশবোর্ড:** লগইন করার পর `http://localhost:8000/admin/dashboard.php`

## কীভাবে কাজ করে

1. পাবলিক সাইটের প্রতিটা পেজের উপরে `trackVisit($pdo, 'page_name')` কল করা হয়েছে
2. এটা ইউজারের IP বের করে (`getUserIP()`), তারপর সেই IP এর geolocation ডেটা লুকআপ করে (`getGeoData()`)
3. একই IP বারবার এলে যেন API quota নষ্ট না হয়, তাই একটা `ip_geo_cache` টেবিলে ৩০ দিনের জন্য ফলাফল ক্যাশ করা হয়
4. সব তথ্য (IP, country, city, browser, OS, device, page, session) `visitor_logs` টেবিলে সেভ হয়
5. অ্যাডমিন ড্যাশবোর্ডে PostgreSQL থেকে aggregate query চালিয়ে (আজকের ভিজিট, দেশ-ভিত্তিক ব্রেকডাউন, জনপ্রিয় পেজ ইত্যাদি) Chart.js দিয়ে গ্রাফ আকারে দেখানো হয়

## নিরাপত্তা সংক্রান্ত নোট

- পাসওয়ার্ড কখনো plain text এ সেভ হয় না — PHP এর `password_hash()`/`password_verify()` ব্যবহার করা হয়েছে
- সব ডাটাবেস কোয়েরি PDO prepared statements দিয়ে করা — SQL injection থেকে সুরক্ষিত
- অ্যাডমিন সেশন `session_regenerate_id()` ব্যবহার করে (session fixation থেকে সুরক্ষা)
- `.env` ফাইল `.gitignore` এ রাখা উচিত, কখনো GitHub এ পাবলিশ কোরো না
- production এ deploy করলে `error_reporting`/`display_errors` অফ করে দিও (php.ini তে), যাতে ইউজার কোনো ভুলবশত এরর মেসেজ না দেখে

## পরের ধাপে যা যোগ করা যায়
- বট ট্রাফিক ফিল্টার করা
- CSV export ফিচার
- ইমেইল অ্যালার্ট (নতুন suspicious activity হলে — PHPMailer দিয়ে)
- একাধিক অ্যাডমিন ইউজার ম্যানেজমেন্ট
