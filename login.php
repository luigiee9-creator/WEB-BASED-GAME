<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Already logged in? Redirect
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . (isAdmin() ? '/admin/index.php' : '/dashboard.php'));
    exit;
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission. Please try again.';
    } else {
        $username = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $errors[] = 'Username and password are required.';
        } else {
            $db   = getDB();
            $stmt = $db->prepare('SELECT id, username, email, password_hash, role, is_active FROM users WHERE username = ? LIMIT 1');
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                if (!$user['is_active']) {
                    $errors[] = 'Your account has been deactivated. Please contact an administrator.';
                } else {
                    loginUser($user);
                    $redirect = isAdmin() ? '/admin/index.php' : '/dashboard.php';
                    header('Location: ' . BASE_URL . $redirect);
                    exit;
                }
            } else {
                $errors[] = 'Incorrect username or password.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Log In — PH History Memory Card Game</title>
  <meta name="description" content="Log in to your account to play the Philippine History Memory Card Game.">
  <link rel="stylesheet" href="assets/css/main.css">
</head>
<body class="page-bg">
<div class="auth-page">
  <div class="auth-card">

    <div class="auth-logo">
      <span class="logo-icon">🇵🇭</span>
      <h1>Welcome Back</h1>
      <p>PH History Memory Card Game</p>
    </div>

    <?php if ($_GET['logout'] ?? false): ?>
      <div class="alert alert-success">You have been logged out successfully.</div>
    <?php endif; ?>
    <?php if ($_GET['error'] ?? '' === 'access_denied'): ?>
      <div class="alert alert-error">Access denied. Please log in with the correct account.</div>
    <?php endif; ?>
    <?php foreach ($errors as $err): ?>
      <div class="alert alert-error"><?= htmlspecialchars($err) ?></div>
    <?php endforeach; ?>

    <form method="POST" action="login.php" novalidate>
      <?= csrfField() ?>

      <div class="form-group">
        <label class="form-label" for="username">Username</label>
        <input
          type="text"
          id="username"
          name="username"
          class="form-control"
          placeholder="Enter your username"
          value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
          autocomplete="username"
          required
        >
      </div>

      <div class="form-group">
        <label class="form-label" for="password">Password</label>
        <input
          type="password"
          id="password"
          name="password"
          class="form-control"
          placeholder="Enter your password"
          autocomplete="current-password"
          required
        >
      </div>

      <button type="submit" id="login-btn" class="btn btn-primary btn-block btn-lg" style="margin-top:.5rem;">
        🔐 Log In
      </button>
    </form>

    <div class="divider">or</div>

    <div class="auth-footer">
      Don't have an account?
      <a href="register.php" style="font-weight:700;">Create one free</a>
    </div>

    <div style="margin-top:1.5rem;padding:.85rem;background:rgba(200,153,42,.07);border:1px dashed rgba(200,153,42,.3);border-radius:var(--radius-sm);font-size:.78rem;color:var(--text-muted);">
      <strong style="color:var(--gold-400);">Demo Credentials</strong><br>
      Admin: <code>admin</code> / <code>password</code><br>
      Student: <code>demo</code> / <code>password</code>
    </div>
  </div>
</div>
<script src="assets/js/main.js"></script>
</body>
</html>
