<?php
declare(strict_types=1);
session_start();

const DB_HOST = '127.0.0.1';
const DB_NAME = 'church_auth';
const DB_USER = 'root';
const DB_PASS = '';
const DB_DRIVER = 'auto'; // 'auto', 'mysql', or 'sqlite'

const APP_NAME = 'GracePoint Church';
const APP_ENV = 'development'; // Use 'production' only after configuring real email delivery.
const OTP_VALID_MINUTES = 5;

function db_driver_name(): string {
    static $driver = null;
    if ($driver !== null) return $driver;

    $preferred = strtoupper((string) DB_DRIVER);
    if (!in_array($preferred, ['AUTO', 'MYSQL', 'SQLITE'], true)) {
        $preferred = 'AUTO';
    }

    if ($preferred === 'AUTO') {
        try {
            $probe = new PDO(
                'mysql:host=' . DB_HOST . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
            $probe->query('SELECT 1');
            $driver = 'mysql';
            return $driver;
        } catch (Throwable $e) {
            $driver = 'sqlite';
            return $driver;
        }
    }

    $driver = strtolower($preferred);
    return $driver;
}

function db_is_sqlite(): bool {
    return db_driver_name() === 'sqlite';
}

function db_attempt_window_sql(int $minutes): string {
    $minutes = max(0, $minutes);
    if (db_is_sqlite()) {
        return "datetime('now', '-" . $minutes . " minutes')";
    }
    return '(NOW() - INTERVAL ' . $minutes . ' MINUTE)';
}

function initialize_database_schema(PDO $pdo): void {
    if (db_is_sqlite()) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            full_name VARCHAR(100) NOT NULL,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(190) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role TEXT NOT NULL DEFAULT 'Church Secretary' CHECK(role IN ('Admin','Pastor','Church Secretary')),
            is_active INTEGER NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username VARCHAR(190) NOT NULL,
            attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            was_successful INTEGER NOT NULL DEFAULT 0
        )");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_attempts_username_time ON login_attempts (username, attempted_at)");

        $count = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        if ($count === 0) {
            $demoAccounts = [
                ['Grace Admin', 'admin', 'admin@gracepoint.test', 'Admin123!', 'Admin'],
                ['Pastor Daniel', 'pastor', 'pastor@gracepoint.test', 'Pastor123!', 'Pastor'],
                ['Ama Secretary', 'secretary', 'secretary@gracepoint.test', 'Secretary123!', 'Church Secretary'],
            ];
            $insert = $pdo->prepare("INSERT INTO users (full_name, username, email, password_hash, role) VALUES (?, ?, ?, ?, ?)");
            foreach ($demoAccounts as [$name, $username, $email, $password, $role]) {
                $insert->execute([$name, $username, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
            }
        }
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(100) NOT NULL,
        username VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(190) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        role ENUM('Admin','Pastor','Church Secretary') NOT NULL DEFAULT 'Church Secretary',
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(190) NOT NULL,
        attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        was_successful TINYINT(1) NOT NULL DEFAULT 0,
        INDEX idx_attempts_username_time (username, attempted_at)
    )");

    $count = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($count === 0) {
        $demoAccounts = [
            ['Grace Admin', 'admin', 'admin@gracepoint.test', 'Admin123!', 'Admin'],
            ['Pastor Daniel', 'pastor', 'pastor@gracepoint.test', 'Pastor123!', 'Pastor'],
            ['Ama Secretary', 'secretary', 'secretary@gracepoint.test', 'Secretary123!', 'Church Secretary'],
        ];
        $insert = $pdo->prepare("INSERT INTO users (full_name, username, email, password_hash, role) VALUES (?, ?, ?, ?, ?)");
        foreach ($demoAccounts as [$name, $username, $email, $password, $role]) {
            $insert->execute([$name, $username, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
        }
    }
}

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $driver = db_driver_name();
    if ($driver === 'mysql') {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (Throwable $e) {
            $bootstrap = new PDO(
                'mysql:host=' . DB_HOST . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
            $bootstrap->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        }

        initialize_database_schema($pdo);
        return $pdo;
    }

    $sqlitePath = __DIR__ . '/church_auth.sqlite';
    $pdo = new PDO('sqlite:' . $sqlitePath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    initialize_database_schema($pdo);
    return $pdo;
}

function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function verify_csrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        exit('Your session token expired. Refresh the page and try again.');
    }
}
function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}
function take_flash(): ?array {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($f) ? $f : null;
}
function require_login(): void {
    if (empty($_SESSION['user'])) { header('Location: index.php'); exit; }
}
function require_role(array $roles): void {
    require_login();
    if (!in_array($_SESSION['user']['role'], $roles, true)) {
        http_response_code(403);
        include __DIR__ . '/forbidden.php';
        exit;
    }
}
function send_otp_email(string $email, string $code): bool {
    // Classroom demo: SMTP is not wired in. The code is shown on screen in development mode.
    // For production, integrate PHPMailer or another authenticated SMTP mail service.
    return false;
}
