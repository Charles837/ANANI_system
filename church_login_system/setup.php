<?php
require_once __DIR__ . '/config.php';
try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `" . DB_NAME . "`");
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
    $demo = [
        ['Grace Admin', 'admin', 'admin@gracepoint.test', 'Admin123!', 'Admin'],
        ['Pastor Daniel', 'pastor', 'pastor@gracepoint.test', 'Pastor123!', 'Pastor'],
        ['Ama Secretary', 'secretary', 'secretary@gracepoint.test', 'Secretary123!', 'Church Secretary'],
    ];
    $stmt = $pdo->prepare("INSERT INTO users (full_name, username, email, password_hash, role)
        VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE full_name=VALUES(full_name), email=VALUES(email), role=VALUES(role)");
    foreach ($demo as [$name, $username, $email, $password, $role]) {
        $stmt->execute([$name, $username, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
    }
    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Setup complete</title><link rel="stylesheet" href="assets/style.css"></head><body class="setup-page"><main class="setup-card"><div class="cross-mark">✝</div><p class="eyebrow">GRACEPOINT CHURCH</p><h1>Setup complete<span>.</span></h1><p>The church authentication database and three demo accounts are ready.</p><div class="credentials"><p><b>Admin</b> — admin / Admin123!</p><p><b>Pastor</b> — pastor / Pastor123!</p><p><b>Church Secretary</b> — secretary / Secretary123!</p></div><p class="notice">Development mode: the one-time verification code will appear on the verification page.</p><a class="button-link" href="index.php">Open church portal</a><p class="small muted">Delete or restrict setup.php after installation and change demo passwords before real use.</p></main></body></html>';
} catch (Throwable $ex) {
    http_response_code(500);
    echo 'Setup failed. Check config.php database settings and ensure MySQL is running. Details: ' . htmlspecialchars($ex->getMessage(), ENT_QUOTES, 'UTF-8');
}
