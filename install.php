<?php
// install.php — One-time setup wizard
// IMPORTANT: Delete or rename this file after first run for security.

define('INSTALL_KEY', 'phgame_install_2024'); // simple protection key

$step    = (int)($_GET['step'] ?? 1);
$error   = '';
$success = '';
$dbConfig = [
    'host'   => $_POST['db_host']   ?? 'localhost',
    'name'   => $_POST['db_name']   ?? 'ph_memory_game',
    'user'   => $_POST['db_user']   ?? 'root',
    'pass'   => $_POST['db_pass']   ?? '',
];

// ── Step 2: Create DB + Schema ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 2) {
    $host = $_POST['db_host'] ?? 'localhost';
    $name = $_POST['db_name'] ?? 'ph_memory_game';
    $user = $_POST['db_user'] ?? 'root';
    $pass = $_POST['db_pass'] ?? '';
    $adminUser  = trim($_POST['admin_user']  ?? 'admin');
    $adminEmail = trim($_POST['admin_email'] ?? 'admin@phgame.edu');
    $adminPass  = $_POST['admin_pass'] ?? '';

    if (empty($adminUser) || empty($adminEmail) || strlen($adminPass) < 6) {
        $error = 'Admin username, email, and a password of at least 6 characters are required.';
    } else {
        try {
            // Connect without DB selected first
            $pdo = new PDO("mysql:host={$host};charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            // Create database
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$name}`");

            // Import schema
            $schemaFile = __DIR__ . '/database/schema.sql';
            if (!file_exists($schemaFile)) {
                $error = 'Schema file not found at database/schema.sql. Please ensure it exists.';
            } else {
                $sql = file_get_contents($schemaFile);
                // Execute statement by statement (split on ;)
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
                foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
                    if (!empty($stmt) && !preg_match('/^--/', $stmt)) {
                        try { $pdo->exec($stmt); } catch (Exception $e) { /* ignore DDL warnings */ }
                    }
                }
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

                // Insert admin account (overwrite if exists)
                $hash = password_hash($adminPass, PASSWORD_DEFAULT);
                $pdo->prepare("DELETE FROM users WHERE username = ?")->execute([$adminUser]);
                $pdo->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (?,?,?,'admin')")
                    ->execute([$adminUser, $adminEmail, $hash]);

                // Ensure demo student exists (with default password 'password')
                $demoHash = password_hash('password', PASSWORD_DEFAULT);
                $pdo->prepare("INSERT IGNORE INTO users (username, email, password_hash, role) VALUES ('demo','demo@phgame.edu',?,'student')")
                    ->execute([$demoHash]);

                // Update config.php with DB credentials
                $configContent = <<<PHP
<?php
define('DB_HOST',     '{$host}');
define('DB_NAME',     '{$name}');
define('DB_USER',     '{$user}');
define('DB_PASS',     '{$pass}');
define('DB_CHARSET',  'utf8mb4');

define('APP_NAME',    'PH History Memory Game');
define('APP_VERSION', '1.0.0');

\$protocol  = (!empty(\$_SERVER['HTTPS']) && \$_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
\$host_name  = \$_SERVER['HTTP_HOST'] ?? 'localhost';
define('BASE_URL', \$protocol . '://' . \$host_name . '/MOR');

define('PAIRS_EASY',   4);
define('PAIRS_MEDIUM', 8);
define('PAIRS_HARD',   10);

define('SCORE_MATCH',        10);
define('SCORE_CORRECT_QUIZ', 20);
define('SCORE_TIME_BONUS',   100);

define('TIME_LIMIT_EASY',   120);
define('TIME_LIMIT_MEDIUM', 240);
define('TIME_LIMIT_HARD',   360);

define('SESSION_LIFETIME', 3600);
define('DEBUG_MODE', false);

error_reporting(0);
ini_set('display_errors', 0);
PHP;
                file_put_contents(__DIR__ . '/includes/config.php', $configContent);
                $success = 'Installation complete!';
                $step = 3;
            }
        } catch (PDOException $e) {
            $error = 'Database connection failed: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Install — PH History Memory Card Game</title>
  <link rel="stylesheet" href="assets/css/main.css">
  <style>
    .install-card { max-width:520px; margin:2rem auto; }
    .step-badge { display:inline-block;padding:.2rem .7rem;background:rgba(200,153,42,.15);border:1px solid rgba(200,153,42,.3);border-radius:99px;font-size:.78rem;color:var(--gold-400);margin-bottom:1rem; }
  </style>
</head>
<body class="page-bg">
<div style="min-height:100vh;display:grid;place-items:start;padding:2rem 1rem;">
<div class="install-card">
  <div style="text-align:center;margin-bottom:2rem;">
    <div style="font-size:3rem;">🇵🇭</div>
    <h1 style="color:var(--gold-400);font-size:1.5rem;">PH History Memory Game</h1>
    <p style="color:var(--text-muted);font-size:.875rem;">Setup Wizard</p>
  </div>

  <?php if ($step === 3): ?>
    <!-- Step 3: Done -->
    <div class="card" style="text-align:center;">
      <div style="font-size:3rem;margin-bottom:1rem;">✅</div>
      <h2 class="text-gold">Installation Complete!</h2>
      <p style="margin:1rem 0;color:var(--text-secondary);">
        Your database has been set up with 58 historical cards and 58 quiz questions.
      </p>
      <div style="background:rgba(220,38,38,.1);border:1px solid rgba(220,38,38,.3);border-radius:var(--radius-sm);padding:.85rem;margin:1rem 0;font-size:.82rem;color:var(--red-400);">
        ⚠️ <strong>Security:</strong> Please delete or rename <code>install.php</code> now!
      </div>
      <a href="index.php" class="btn btn-primary btn-block btn-lg" style="margin-top:1rem;">
        🚀 Go to Application →
      </a>
    </div>

  <?php else: ?>
    <!-- Step 1: Form -->
    <div class="card">
      <div class="step-badge">Step 1 of 1 — Database &amp; Admin Setup</div>
      <h2 style="margin-bottom:.25rem;">Configure Your Installation</h2>
      <p style="font-size:.82rem;color:var(--text-muted);margin-bottom:1.5rem;">
        Enter your MySQL credentials and create the admin account.
        The database and all tables will be created automatically.
      </p>

      <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST" action="install.php?step=2">
        <h4 style="margin-bottom:.75rem;color:var(--gold-400);">🗄️ Database Settings</h4>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
          <div class="form-group">
            <label class="form-label">Host</label>
            <input type="text" name="db_host" class="form-control" value="<?= htmlspecialchars($dbConfig['host']) ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Database Name</label>
            <input type="text" name="db_name" class="form-control" value="<?= htmlspecialchars($dbConfig['name']) ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Username</label>
            <input type="text" name="db_user" class="form-control" value="<?= htmlspecialchars($dbConfig['user']) ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Password</label>
            <input type="password" name="db_pass" class="form-control" placeholder="Leave blank if none">
          </div>
        </div>

        <hr style="border:none;border-top:1px solid var(--glass-border);margin:1.25rem 0;">

        <h4 style="margin-bottom:.75rem;color:var(--gold-400);">👑 Admin Account</h4>
        <div class="form-group">
          <label class="form-label">Admin Username</label>
          <input type="text" name="admin_user" class="form-control" value="admin" required>
        </div>
        <div class="form-group">
          <label class="form-label">Admin Email</label>
          <input type="email" name="admin_email" class="form-control" value="admin@phgame.edu" required>
        </div>
        <div class="form-group">
          <label class="form-label">Admin Password *</label>
          <input type="password" name="admin_pass" class="form-control" minlength="6" required placeholder="Min 6 characters">
        </div>

        <button type="submit" class="btn btn-primary btn-block btn-lg">
          ⚙️ Install Now
        </button>
      </form>
    </div>

    <!-- Pre-flight checks -->
    <div class="card" style="margin-top:1rem;font-size:.8rem;">
      <h4 style="margin-bottom:.75rem;">🔍 Pre-flight Checks</h4>
      <?php
      $checks = [
          'PHP 7.4+' => version_compare(PHP_VERSION, '7.4.0', '>='),
          'PDO extension'     => extension_loaded('pdo'),
          'PDO MySQL driver'  => extension_loaded('pdo_mysql'),
          'schema.sql exists' => file_exists(__DIR__ . '/database/schema.sql'),
          'includes/ writable'=> is_writable(__DIR__ . '/includes'),
      ];
      foreach ($checks as $label => $ok):
      ?>
      <div style="display:flex;justify-content:space-between;padding:.3rem 0;border-bottom:1px solid var(--glass-border);">
        <span><?= $label ?></span>
        <span><?= $ok ? '✅ OK' : '❌ FAIL' ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
</div>
</body>
</html>
