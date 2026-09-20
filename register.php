<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$errors  = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission.';
    } else {
        $username   = sanitize($_POST['username'] ?? '');
        $email      = sanitize($_POST['email']    ?? '');
        $password   = $_POST['password']           ?? '';
        $password2  = $_POST['password2']          ?? '';

        // Validation
        if (empty($username) || empty($email) || empty($password) || empty($password2)) {
            $errors[] = 'All fields are required.';
        } elseif (strlen($username) < 3 || strlen($username) > 50) {
            $errors[] = 'Username must be between 3 and 50 characters.';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $errors[] = 'Username may only contain letters, numbers, and underscores.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } elseif (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        } elseif ($password !== $password2) {
            $errors[] = 'Passwords do not match.';
        } else {
            $db   = getDB();

            // Check duplicates
            $stmt = $db->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $errors[] = 'Username or email is already registered.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare(
                    'INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, "student")'
                );
                $stmt->execute([$username, $email, $hash]);
                $success = 'Account created successfully! You can now log in.';
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
  <title>Register — PH History Memory Card Game</title>
  <meta name="description" content="Create a free student account to play the Philippine History Memory Card Game.">
  <link rel="stylesheet" href="assets/css/main.css">
</head>
<body class="page-bg">
<div class="auth-page">
  <div class="auth-card">

    <div class="auth-logo">
      <span class="logo-icon">🎮</span>
      <h1>Create Account</h1>
      <p>Free student account — start playing instantly</p>
    </div>

    <?php if ($success): ?>
      <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
      <div style="text-align:center;margin-top:1rem;">
        <a href="login.php" class="btn btn-primary btn-block">Go to Login →</a>
      </div>
    <?php else: ?>

    <?php foreach ($errors as $err): ?>
      <div class="alert alert-error"><?= htmlspecialchars($err) ?></div>
    <?php endforeach; ?>

    <form method="POST" action="register.php" novalidate>
      <?= csrfField() ?>

      <div class="form-group">
        <label class="form-label" for="reg-username">Username</label>
        <input
          type="text"
          id="reg-username"
          name="username"
          class="form-control"
          placeholder="Choose a username"
          value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
          autocomplete="username"
          maxlength="50"
          required
        >
        <span class="form-hint">Letters, numbers, underscores only</span>
      </div>

      <div class="form-group">
        <label class="form-label" for="reg-email">Email</label>
        <input
          type="email"
          id="reg-email"
          name="email"
          class="form-control"
          placeholder="your@email.com"
          value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
          autocomplete="email"
          required
        >
      </div>

      <div class="form-group">
        <label class="form-label" for="reg-password">Password</label>
        <input
          type="password"
          id="reg-password"
          name="password"
          class="form-control"
          placeholder="At least 6 characters"
          autocomplete="new-password"
          minlength="6"
          required
        >
      </div>

      <div class="form-group">
        <label class="form-label" for="reg-password2">Confirm Password</label>
        <input
          type="password"
          id="reg-password2"
          name="password2"
          class="form-control"
          placeholder="Repeat password"
          autocomplete="new-password"
          required
        >
      </div>

      <button type="submit" id="register-btn" class="btn btn-primary btn-block btn-lg" style="margin-top:.5rem;">
        🚀 Create Account
      </button>
    </form>

    <div class="auth-footer">
      Already have an account? <a href="login.php" style="font-weight:700;">Log In</a>
    </div>

    <?php endif; ?>
  </div>
</div>
<script src="assets/js/main.js"></script>
</body>
</html>
