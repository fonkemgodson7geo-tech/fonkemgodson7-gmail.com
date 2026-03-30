<?php
// ── Environment overrides (.env file support) ──────────────────────────────
// If a .env file exists at the project root, load KEY=VALUE pairs from it.
// This keeps secrets out of source control.
$_envFile = __DIR__ . '/../.env';
if (is_readable($_envFile)) {
    foreach (file($_envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $_line) {
        if (str_starts_with(trim($_line), '#') || !str_contains($_line, '=')) continue;
        [$_k, $_v] = array_map('trim', explode('=', $_line, 2));
        if ($_k !== '' && !isset($_SERVER[$_k])) {
            $_SERVER[$_k] = $_v;
            putenv("$_k=$_v");
        }
    }
    unset($_envFile, $_line, $_k, $_v);
}

// ── Site Configuration ──────────────────────────────────────────────────────
define('SITE_NAME',               getenv('SITE_NAME')               ?: 'CENTRE MÉMICAL DONS DE SOINS');
define('PAYMENT_NUMBER',          getenv('PAYMENT_NUMBER')          ?: '681629394');
define('CUSTOMER_SERVICE_NUMBER', getenv('CUSTOMER_SERVICE_NUMBER') ?: '+237678612733');

// ── Database Configuration ──────────────────────────────────────────────────
define('DB_TYPE', getenv('DB_TYPE') ?: 'sqlite');
define('DB_FILE', getenv('DB_FILE') ?: __DIR__ . '/../database/clinic.db');

// Legacy MySQL constants (used only when DB_TYPE=mysql)
define('DB_HOST', getenv('DB_HOST') ?: (getenv('MYSQLHOST') ?: 'localhost'));
define('DB_NAME', getenv('DB_NAME') ?: (getenv('MYSQLDATABASE') ?: 'dondesionc_clinic'));
define('DB_USER', getenv('DB_USER') ?: (getenv('MYSQLUSER') ?: 'root'));
define('DB_PASS', getenv('DB_PASS') ?: (getenv('MYSQLPASSWORD') ?: ''));

// ── Security ────────────────────────────────────────────────────────────────
// Set via .env in production:  ENCRYPTION_KEY=<64-char hex>
define('ENCRYPTION_KEY', getenv('ENCRYPTION_KEY')
    ?: 'f3842c519f5518ccd5474d1b60189c2fa5224f270e26098a989c898f0ce406fd');

// ── Third-party API Keys (override via .env in production) ──────────────────
define('CINERPAY_API_KEY', getenv('CINERPAY_API_KEY') ?: '');
define('CINERPAY_SITE_ID', getenv('CINERPAY_SITE_ID') ?: '');
define('SMTP_HOST',        getenv('SMTP_HOST')        ?: 'smtp.gmail.com');
define('SMTP_USER',        getenv('SMTP_USER')        ?: '');
define('SMTP_PASS',        getenv('SMTP_PASS')        ?: '');
define('SMTP_PORT',        (int)(getenv('SMTP_PORT')  ?: 587));

// ── File uploads ────────────────────────────────────────────────────────────
define('UPLOAD_DIR',    getenv('UPLOAD_DIR')    ?: __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', (int)(getenv('MAX_FILE_SIZE') ?: 5 * 1024 * 1024));