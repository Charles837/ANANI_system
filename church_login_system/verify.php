<?php
require_once __DIR__ . '/config.php';
if (empty($_SESSION['pending_2fa'])) { header('Location: index.php'); exit; }
$pending = &$_SESSION['pending_2fa'];
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $code = preg_replace('/\D/', '', (string)($_POST['code'] ?? ''));
    $pending['tries']++;
    if (time() > $pending['expires']) {
        unset($_SESSION['pending_2fa'], $_SESSION['dev_otp']);
        $error = 'Your code expired. Please sign in again.';
    } elseif ($pending['tries'] > 5) {
        unset($_SESSION['pending_2fa'], $_SESSION['dev_otp']);
        $error = 'Too many incorrect codes. Please sign in again.';
    } elseif (strlen($code) !== 6 || !password_verify($code, $pending['otp_hash'])) {
        $error = 'That code is not correct. Please try again.';
    } else {
        $stmt = db()->prepare("SELECT id, full_name, username, email, role FROM users WHERE id = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$pending['user_id']]);
        $u = $stmt->fetch();
        if (!$u) { unset($_SESSION['pending_2fa'], $_SESSION['dev_otp']); header('Location: index.php'); exit; }
        session_regenerate_id(true);
        $_SESSION['user'] = ['id'=>(int)$u['id'], 'full_name'=>$u['full_name'], 'username'=>$u['username'], 'email'=>$u['email'], 'role'=>$u['role']];
        unset($_SESSION['pending_2fa'], $_SESSION['dev_otp']);
        set_flash('success', 'Verification complete. Welcome to the church portal.');
        header('Location: dashboard.php'); exit;
    }
}
$devOtp = $_SESSION['dev_otp'] ?? null;
$maskedEmail = preg_replace('/(^.).*(@.*$)/', '$1••••$2', (string)$pending['email']);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Verify identity | GracePoint Church</title><link rel="stylesheet" href="assets/style.css"></head><body class="login-page verify-page"><main class="verify-card"><a class="back-link" href="logout.php?cancel=1">← Back to sign in</a><div class="cross-mark big-cross">✝</div><p class="eyebrow">SECURITY CHECK · STEP 2 OF 2</p><h1>Confirm it's you<span>.</span></h1><p class="intro">Enter the six-digit verification code for <b><?= e($maskedEmail) ?></b>.</p><?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?><?php if ($devOtp): ?><div class="dev-code"><span>DEVELOPMENT DEMO CODE</span><strong><?= e($devOtp) ?></strong><small>Shown on screen because email delivery is not configured.</small></div><?php endif; ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><label for="code">Verification code</label><input class="otp-input" id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="000000" autocomplete="one-time-code" required autofocus><p class="hint">This code expires in <?= OTP_VALID_MINUTES ?> minutes.</p><button class="primary-button" type="submit">Verify & enter <span>→</span></button></form><p class="tiny-note">Keep your code private. Church staff will never ask you to share it.</p></main></body></html>
