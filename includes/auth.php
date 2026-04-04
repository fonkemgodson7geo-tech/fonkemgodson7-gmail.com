<?php
require_once __DIR__ . '/../config/config.php';

// Prevent private authenticated pages from being indexed by search engines.
if (!headers_sent()) {
    header('X-Robots-Tag: noindex, nofollow, noarchive', true);
}

// Secure session configuration before session_start
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Session timeout: 30 minutes of inactivity
define('SESSION_TIMEOUT', 1800);

function _checkSessionTimeout(): void {
    if (isset($_SESSION['last_active']) && (time() - $_SESSION['last_active']) > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        session_start();
    }
    $_SESSION['last_active'] = time();
}
_checkSessionTimeout();

// Function to check if user is logged in
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

// Function to get current user
function getCurrentUser(): ?array {
    if (!isLoggedIn()) return null;
    return $_SESSION['user'];
}

// Function to require login
function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ../patient/login.php');
        exit;
    }
}

// Function to require a specific role
function requireRole(string $role): void {
    requireLogin();
    if (!hasRole($role)) {
        header('Location: ../index.php');
        exit;
    }
}

// Function to check role
function hasRole(string $role): bool {
    if (!isLoggedIn()) return false;
    return ($_SESSION['user']['role'] ?? '') === $role;
}

// Function to login user — regenerates session ID to prevent fixation
function loginUser(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user']       = $user;
    $_SESSION['last_active'] = time();
    // Generate per-session CSRF token
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

// Function to logout — fully destroys session
function logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// CSRF helpers
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function verifyCsrf(): void {
    $submitted = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrfToken(), $submitted)) {
        http_response_code(403);
        exit('Request validation failed. Please go back and try again.');
    }
}

// Function to hash password
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Function to verify password
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function _sqliteTableExists(PDO $pdo, string $tableName): bool {
    $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ? LIMIT 1");
    $stmt->execute([$tableName]);
    return (bool)$stmt->fetchColumn();
}

function _sqliteEnsureIdentitySchema(PDO $pdo): void {
    if (_sqliteTableExists($pdo, 'users') && _sqliteTableExists($pdo, 'doctors')) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        email TEXT UNIQUE NOT NULL,
        role TEXT NOT NULL CHECK (role IN ('patient', 'doctor', 'admin', 'staff', 'intern', 'trainee', 'pharmacist', 'nurse', 'manager', 'compliance_officer', 'qa_tester', 'developer', 'translator')),
        first_name TEXT,
        last_name TEXT,
        phone TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS patients (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        medical_record_number TEXT UNIQUE,
        date_of_birth DATE,
        gender TEXT CHECK (gender IN ('male', 'female', 'other')),
        address TEXT,
        emergency_contact TEXT,
        emergency_phone TEXT,
        blood_type TEXT,
        allergies TEXT,
        medical_history TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS doctors (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        specialization TEXT,
        license_number TEXT,
        availability TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS pharmacy_doctors (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        doctor_id INTEGER NOT NULL,
        added_by INTEGER,
        added_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
        FOREIGN KEY (added_by) REFERENCES users(id),
        UNIQUE(doctor_id)
    )");
}

// Function to get PDO connection
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            if (defined('DB_TYPE') && DB_TYPE === 'sqlite') {
                // SQLite connection
                $pdo = new PDO('sqlite:' . DB_FILE);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                // Enable foreign keys for SQLite
                $pdo->exec('PRAGMA foreign_keys = ON');
                _sqliteEnsureIdentitySchema($pdo);
            } else {
                // MySQL connection (legacy)
                $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            }
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    return $pdo;
}
?>