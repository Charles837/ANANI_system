<?php
require_once __DIR__ . '/config.php';
require_role(['Admin']);
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim((string)($_POST['full_name'] ?? ''));
    $username = trim((string)($_POST['username'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $role = (string)($_POST['role'] ?? 'Church Secretary');
    if ($name === '' || !preg_match('/^[a-zA-Z0-9_.-]{3,50}$/', $username) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 10 || !in_array($role, ['Admin','Pastor','Church Secretary'], true)) {
        $error = 'Check the details. Username must be 3–50 characters, email must be valid, and password must be at least 10 characters.';
    } else {
        try {
            $stmt = db()->prepare("INSERT INTO users (full_name, username, email, password_hash, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $username, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
            set_flash('success', 'Church user account created successfully.');
            header('Location: users.php'); exit;
        } catch (PDOException $ex) {
            $error = 'That username or email address is already in use.';
        }
    }
}
$users = db()->query("SELECT full_name, username, email, role, created_at FROM users ORDER BY created_at DESC")->fetchAll();
$flash = take_flash();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>User management | GracePoint Church</title><link rel="stylesheet" href="assets/style.css"></head><body class="app-page"><aside class="sidebar"><a class="sidebar-brand" href="dashboard.php"><span class="cross-mark">✝</span><span>GRACEPOINT<small>CHURCH COMMUNITY</small></span></a><p class="sidebar-label">CHURCH WORKSPACE</p><nav><a class="nav-item" href="dashboard.php"><span>⌂</span> Overview</a><a class="nav-item active" href="users.php"><span>♙</span> User management</a></nav><div class="sidebar-bottom"><a class="nav-item logout-link" href="logout.php"><span>↪</span> Sign out</a></div></aside><main class="main-content"><header class="topbar"><div><p class="eyebrow">ADMINISTRATION</p><h1>Church users<span>.</span></h1><p class="subhead">Create accounts and assign secure access roles.</p></div><a class="secondary-button" href="dashboard.php">← Dashboard</a></header><?php if ($flash): ?><div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?><?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?><div class="users-layout"><section class="panel create-user"><p class="eyebrow">ADD TO THE TEAM</p><h3>Create church account</h3><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><label>Full name</label><input name="full_name" maxlength="100" required placeholder="Full name"><label>Username</label><input name="username" minlength="3" maxlength="50" pattern="[a-zA-Z0-9_.-]+" required placeholder="e.g. ama.k"><label>Email address</label><input name="email" type="email" required placeholder="name@example.com"><label>Temporary password</label><input name="password" type="password" minlength="10" required placeholder="At least 10 characters"><label>Role</label><select name="role"><option>Church Secretary</option><option>Pastor</option><option>Admin</option></select><button class="primary-button" type="submit">Create account <span>→</span></button></form><p class="hint">Share temporary credentials privately. Ask users to change their password before production use.</p></section><section class="panel directory-panel"><div class="panel-heading"><div><p class="eyebrow">CHURCH STAFF</p><h3>Account directory</h3></div><span class="role-pill"><?= count($users) ?> accounts</span></div><div class="table-scroll"><table><thead><tr><th>Person</th><th>Username</th><th>Role</th></tr></thead><tbody><?php foreach ($users as $u): ?><tr><td><b><?= e($u['full_name']) ?></b><small><?= e($u['email']) ?></small></td><td><?= e($u['username']) ?></td><td><span class="table-role"><?= e($u['role']) ?></span></td></tr><?php endforeach; ?></tbody></table></div></section></div><footer class="app-footer"><span>GRACEPOINT CHURCH · USER MANAGEMENT</span><span>ADMIN ONLY</span></footer></main></body></html>
