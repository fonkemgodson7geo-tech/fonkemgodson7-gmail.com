<?php
require_once __DIR__ . '/../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', '1');
    session_start();
}

require_once __DIR__ . '/language.php';

// Prevent private authenticated pages from being indexed by search engines.
if (!headers_sent()) {
    header('X-Robots-Tag: noindex, nofollow, noarchive', true);
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

function hasDesignatedAdminAccess(): bool {
    if (!isLoggedIn()) {
        return false;
    }

    $role = (string)($_SESSION['user']['role'] ?? '');
    $username = (string)($_SESSION['user']['username'] ?? '');
    return $role === 'admin' && strcasecmp($username, ADMIN_LOGIN_USERNAME) === 0;
}

function requireDesignatedAdmin(): void {
    requireLogin();
    if (!hasDesignatedAdminAccess()) {
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

function _auditJsonEncode($value): ?string {
    if ($value === null) {
        return null;
    }

    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return $json === false ? null : $json;
}

function getClientIpAddress(): string {
    $keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    foreach ($keys as $key) {
        $value = trim((string)($_SERVER[$key] ?? ''));
        if ($value === '') {
            continue;
        }
        if ($key === 'HTTP_X_FORWARDED_FOR') {
            $parts = array_map('trim', explode(',', $value));
            return (string)($parts[0] ?? '');
        }
        return $value;
    }
    return '';
}

function writeAuditLog(string $action, ?string $tableName = null, ?int $recordId = null, $oldValues = null, $newValues = null): void {
    try {
        $pdo = getDB();
        $userId = isLoggedIn() ? (int)($_SESSION['user_id'] ?? 0) : null;
        if ($userId !== null && $userId <= 0) {
            $userId = null;
        }

        $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $userId,
            $action,
            $tableName,
            $recordId,
            _auditJsonEncode($oldValues),
            _auditJsonEncode($newValues),
            getClientIpAddress(),
            substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 65535),
        ]);
    } catch (Throwable $e) {
        error_log('Audit log write error: ' . $e->getMessage());
    }
}

function _sqliteEnsureColumn(PDO $pdo, string $table, string $column, string $definition): void {
    $stmt = $pdo->query("PRAGMA table_info(" . $table . ")");
    $cols = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'name');
    if (!in_array($column, $cols, true)) {
        $pdo->exec("ALTER TABLE " . $table . " ADD COLUMN " . $column . " " . $definition);
    }
}

function _sqliteTableExists(PDO $pdo, string $tableName): bool {
    $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ? LIMIT 1");
    $stmt->execute([$tableName]);
    return (bool)$stmt->fetchColumn();
}

function _sqliteEnsureIdentitySchema(PDO $pdo): void {
    $hasCoreIdentity = _sqliteTableExists($pdo, 'users') && _sqliteTableExists($pdo, 'doctors');
    if (!$hasCoreIdentity) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            email TEXT UNIQUE NOT NULL,
            role TEXT NOT NULL CHECK (role IN ('patient', 'doctor', 'admin', 'staff', 'intern', 'trainee', 'pharmacist', 'nurse', 'manager', 'compliance_officer', 'qa_tester', 'developer', 'translator')),
            first_name TEXT,
            last_name TEXT,
            phone TEXT,
            photo TEXT,
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

        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS attendance (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            check_in DATETIME,
            check_out DATETIME,
            date DATE,
            status TEXT DEFAULT 'present' CHECK (status IN ('present', 'absent', 'late')),
            notes TEXT,
            FOREIGN KEY (user_id) REFERENCES users(id)
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

    $pdo->exec("CREATE TABLE IF NOT EXISTS pharmacy_sales (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        prescription_id INTEGER,
        patient_id INTEGER,
        inventory_id INTEGER,
        quantity_sold INTEGER NOT NULL,
        unit_price REAL NOT NULL DEFAULT 0,
        total_amount REAL NOT NULL DEFAULT 0,
        payment_status TEXT NOT NULL DEFAULT 'unpaid' CHECK (payment_status IN ('paid', 'unpaid', 'partial')),
        has_debt INTEGER NOT NULL DEFAULT 1,
        sold_by INTEGER,
        sold_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        note TEXT,
        FOREIGN KEY (prescription_id) REFERENCES prescriptions(id),
        FOREIGN KEY (patient_id) REFERENCES patients(id),
        FOREIGN KEY (inventory_id) REFERENCES pharmacy_inventory(id),
        FOREIGN KEY (sold_by) REFERENCES users(id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS shift_events (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        event_type TEXT NOT NULL CHECK (event_type IN ('sign_in', 'sign_out', 'shift_change', 'shift_swap')),
        event_time DATETIME DEFAULT CURRENT_TIMESTAMP,
        shift_date DATE,
        partner_user_id INTEGER,
        note TEXT,
        status TEXT DEFAULT 'recorded' CHECK (status IN ('recorded', 'requested', 'approved', 'rejected')),
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (partner_user_id) REFERENCES users(id)
    )");

    // Ensure photo column exists on pre-existing users tables
    if (_sqliteTableExists($pdo, 'users')) {
        _sqliteEnsureColumn($pdo, 'users', 'photo', 'TEXT');
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS audit_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        action TEXT NOT NULL,
        table_name TEXT,
        record_id INTEGER,
        old_values TEXT,
        new_values TEXT,
        ip_address TEXT,
        user_agent TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");
}

function _syncDesignatedAdminCredentials(PDO $pdo): void {
    $username = trim((string)(defined('ADMIN_LOGIN_USERNAME') ? ADMIN_LOGIN_USERNAME : ''));
    if ($username === '') {
        return;
    }

    $plainPassword = trim((string)(defined('ADMIN_LOGIN_PASSWORD') ? ADMIN_LOGIN_PASSWORD : ''));
    $email = trim((string)(defined('ADMIN_LOGIN_EMAIL') ? ADMIN_LOGIN_EMAIL : 'admin@clinic.com'));
    if ($email === '') {
        $email = 'admin@clinic.com';
    }

    try {
        $stmt = $pdo->prepare('SELECT id, password, role FROM users WHERE lower(username) = lower(?) LIMIT 1');
        $stmt->execute([$username]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($existing) {
            $set = [];
            $params = [];

            if ((string)($existing['role'] ?? '') !== 'admin') {
                $set[] = 'role = ?';
                $params[] = 'admin';
            }

            if ($plainPassword !== '' && !password_verify($plainPassword, (string)($existing['password'] ?? ''))) {
                $set[] = 'password = ?';
                $params[] = password_hash($plainPassword, PASSWORD_DEFAULT);
            }

            if ($set) {
                $params[] = (int)$existing['id'];
                $update = $pdo->prepare('UPDATE users SET ' . implode(', ', $set) . ' WHERE id = ?');
                $update->execute($params);
            }
            return;
        }

        if ($plainPassword === '') {
            return;
        }

        $insert = $pdo->prepare('INSERT INTO users (username, password, email, role, first_name, last_name) VALUES (?, ?, ?, ?, ?, ?)');
        $insert->execute([
            $username,
            password_hash($plainPassword, PASSWORD_DEFAULT),
            $email,
            'admin',
            'Admin',
            'User',
        ]);
    } catch (Throwable $e) {
        error_log('Designated admin sync error: ' . $e->getMessage());
    }
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
                _syncDesignatedAdminCredentials($pdo);
            } else {
                // MySQL connection (legacy)
                $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                _syncDesignatedAdminCredentials($pdo);
            }
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            http_response_code(500);
            exit('Service temporarily unavailable. Please try again later.');
        }
    }
    return $pdo;
}
?>