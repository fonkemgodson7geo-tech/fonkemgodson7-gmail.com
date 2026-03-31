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
define('SITE_NAME',               getenv('SITE_NAME')               ?: 'CSP DON DE SOINS (AWCD) MBANDJOCK');
define('PAYMENT_NUMBER',          getenv('PAYMENT_NUMBER')          ?: '681629394');
define('CUSTOMER_SERVICE_NUMBER', getenv('CUSTOMER_SERVICE_NUMBER') ?: '+237678612733');
$siteLogoFromEnv = trim((string)(getenv('SITE_LOGO_URL') ?: ''));
if ($siteLogoFromEnv === '') {
    $customLogoPointer = __DIR__ . '/../uploads/site-logo.path';
    if (is_readable($customLogoPointer)) {
        $customLogoPath = trim((string)file_get_contents($customLogoPointer));
        if ($customLogoPath !== '' && str_starts_with($customLogoPath, 'uploads/')) {
            $customLogoAbsPath = __DIR__ . '/../' . $customLogoPath;
            if (is_file($customLogoAbsPath)) {
                $siteLogoFromEnv = $customLogoPath;
            }
        }
        unset($customLogoPath, $customLogoAbsPath);
    }
    unset($customLogoPointer);
}
define('SITE_LOGO_URL', $siteLogoFromEnv !== '' ? $siteLogoFromEnv : 'assets/logo-default.svg');
unset($siteLogoFromEnv);

// ── Database Configuration ──────────────────────────────────────────────────
define('DB_TYPE', getenv('DB_TYPE') ?: 'sqlite');
$dbFile = getenv('DB_FILE') ?: (__DIR__ . '/../database/clinic.db');
if (DB_TYPE === 'sqlite') {
    // Ensure relative DB paths from env resolve from the project root.
    if (!preg_match('/^(?:[A-Za-z]:\\\\|\/)/', $dbFile)) {
        $dbFile = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $dbFile);
    }
}
define('DB_FILE', $dbFile);
unset($dbFile);

// Legacy MySQL constants (used only when DB_TYPE=mysql)
define('DB_HOST', getenv('DB_HOST') ?: (getenv('MYSQLHOST') ?: 'localhost'));
define('DB_NAME', getenv('DB_NAME') ?: (getenv('MYSQLDATABASE') ?: 'dondesionc_clinic'));
define('DB_USER', getenv('DB_USER') ?: (getenv('MYSQLUSER') ?: 'root'));
define('DB_PASS', getenv('DB_PASS') ?: (getenv('MYSQLPASSWORD') ?: ''));

// ── Security ────────────────────────────────────────────────────────────────
// REQUIRED: Set ENCRYPTION_KEY in your .env file (64-char hex string).
// Generate one with: php -r "echo bin2hex(random_bytes(32));"
define('ENCRYPTION_KEY', getenv('ENCRYPTION_KEY') ?: '');

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
