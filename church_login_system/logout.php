<?php
require_once __DIR__ . '/config.php';
if (isset($_GET['cancel'])) {
    unset($_SESSION['pending_2fa'], $_SESSION['dev_otp']);
    set_flash('success', 'Sign-in cancelled. Please start again.');
} else {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    session_start();
    set_flash('success', 'You have signed out securely.');
}
header('Location: index.php'); exit;
