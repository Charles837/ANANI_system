<?php
require_once __DIR__ . '/config.php';
if (!empty($_SESSION['user'])) { header('Location: dashboard.php'); exit; }
$flash = take_flash();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $identity = trim((string)($_POST['identity'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if ($identity === '' || $password === '') {
        $error = 'Enter your username or email and password.';
    } else {
        $cutoff = db_attempt_window_sql(15);
        $attempts = db()->prepare("SELECT COUNT(*) FROM login_attempts WHERE username = ? AND attempted_at > " . $cutoff . " AND was_successful = 0");
        $attempts->execute([$identity]);
        if ((int)$attempts->fetchColumn() >= 8) {
            $error = 'Too many failed attempts. Please wait 15 minutes before trying again.';
        } else {
            $stmt = db()->prepare("SELECT * FROM users WHERE (username = :username OR email = :email) AND is_active = 1 LIMIT 1");
            $stmt->execute(['username' => $identity, 'email' => $identity]);
            $user = $stmt->fetch();
            if (!$user || !password_verify($password, $user['password_hash'])) {
                db()->prepare("INSERT INTO login_attempts (username, was_successful) VALUES (?, 0)")->execute([$identity]);
                $error = 'The login details do not match our records.';
            } else {
                db()->prepare("INSERT INTO login_attempts (username, was_successful) VALUES (?, 1)")->execute([$identity]);
                session_regenerate_id(true);
                $otp = (string)random_int(100000, 999999);
                $_SESSION['pending_2fa'] = [
                    'user_id' => (int)$user['id'],
                    'otp_hash' => password_hash($otp, PASSWORD_DEFAULT),
                    'expires' => time() + (OTP_VALID_MINUTES * 60),
                    'tries' => 0,
                    'email' => $user['email']
                ];
                $sent = send_otp_email($user['email'], $otp);
                if (!$sent && APP_ENV === 'development') $_SESSION['dev_otp'] = $otp;
                elseif (!$sent) { unset($_SESSION['pending_2fa']); $error = 'Unable to send the verification code. Contact the system administrator.'; }
                if ($error === '') { header('Location: verify.php'); exit; }
            }
        }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Sign in | GracePoint Church</title><link rel="stylesheet" href="assets/style.css"></head>
<body class="login-page"><main class="login-shell"><section class="welcome-panel"><div class="panel-shade"></div><div class="welcome-content"><div class="church-brand"><span class="cross-mark">✝</span><span>GRACEPOINT<small>CHURCH COMMUNITY</small></span></div><div class="verse"><p class="eyebrow">FAITH · SERVICE · COMMUNITY</p><h1>Serving together.<br><em>Growing in grace.</em></h1><p>A secure space for the people who serve our church family.</p></div><div class="panel-footer"><span>ONE CHURCH. ONE FAMILY.</span><span>EST. IN FAITH</span></div></div></section>
<section class="form-panel"><div class="form-wrap"><div class="step-label"><span class="pulse-dot"></span> CHURCH STAFF PORTAL <i></i> SECURE ACCESS</div><h2>Welcome back<span>.</span></h2><p class="intro">Sign in to continue to your church workspace.</p>
<?php if ($flash): ?><div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?><?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><label for="identity">Username or email</label><div class="input-box"><span>♙</span><input id="identity" name="identity" placeholder="Enter your username or email" autocomplete="username" required value="<?= e($_POST['identity'] ?? '') ?>"></div><div class="label-line"><label for="password">Password</label><small>STEP 1 OF 2</small></div><div class="input-box"><span>◇</span><input id="password" name="password" type="password" placeholder="Enter your password" autocomplete="current-password" required><button type="button" onclick="togglePassword()" id="show-password">Show</button></div><button class="primary-button" type="submit">Continue securely <span>→</span></button></form>
<div class="security-box"><span class="security-icon">✓</span><p><b>Your account is protected</b><small>Two-step verification helps keep church records safe.</small></p></div><div class="form-footer"><span>GRACEPOINT CHURCH · STAFF ACCESS</span><span>© 2026</span></div></div></section></main><script>function togglePassword(){const p=document.getElementById('password'),b=document.getElementById('show-password');p.type=p.type==='password'?'text':'password';b.textContent=p.type==='password'?'Show':'Hide';}</script></body></html>
