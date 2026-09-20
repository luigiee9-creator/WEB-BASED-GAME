<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();
$db = getDB();

// Quick stats
$stats = [];
foreach ([
    'users'         => 'SELECT COUNT(*) FROM users WHERE role="student"',
    'cards'         => 'SELECT COUNT(*) FROM cards WHERE is_active=1',
    'questions'     => 'SELECT COUNT(*) FROM quiz_questions WHERE is_active=1',
    'sessions'      => 'SELECT COUNT(*) FROM game_sessions WHERE is_completed=1',
    'avg_score'     => 'SELECT COALESCE(AVG(score),0) FROM performance_records',
    'total_matches' => 'SELECT COALESCE(SUM(matched_pairs),0) FROM performance_records',
] as $key => $sql) {
    $stats[$key] = $db->query($sql)->fetchColumn();
}

// Top 5 students
$top = $db->query('
    SELECT u.username, MAX(pr.score) AS best_score, COUNT(pr.id) AS sessions
    FROM performance_records pr JOIN users u ON u.id = pr.user_id
    GROUP BY pr.user_id ORDER BY best_score DESC LIMIT 5
')->fetchAll();

// Recent sessions (last 8)
$recent = $db->query('
    SELECT u.username, gs.difficulty, pr.score, pr.matched_pairs, pr.total_pairs, pr.completed_at
    FROM performance_records pr
    JOIN users u  ON u.id  = pr.user_id
    JOIN game_sessions gs ON gs.id = pr.session_id
    ORDER BY pr.completed_at DESC LIMIT 8
')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard — PH History Game</title>
  <link rel="stylesheet" href="../assets/css/main.css">
  <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="page-bg">
<div class="admin-layout">

<!-- Sidebar -->
<?php include '_sidebar.php'; ?>

<!-- Main -->
<div class="admin-main">
  <!-- Topbar -->
  <div class="admin-topbar">
    <div style="display:flex;align-items:center;gap:.75rem;">
      <button class="sidebar-toggle" id="sidebar-toggle">☰</button>
      <span class="topbar-title">📊 Admin Dashboard</span>
    </div>
    <div class="topbar-right">
      <div class="user-pill">👑 <?= htmlspecialchars($_SESSION['username']) ?></div>
      <a href="../logout.php" class="btn btn-secondary btn-sm">Log Out</a>
    </div>
  </div>

  <div class="admin-content">
    <div class="page-header">
      <div>
        <h1>Dashboard Overview</h1>
        <p class="page-subtitle">Philippine History Memory Card Game — Admin Panel</p>
      </div>
    </div>

    <!-- Stat Cards -->
    <div class="admin-stat-grid">
      <?php
      $items = [
        ['👨‍🎓', number_format($stats['users']),         'Students Registered', ''],
        ['🃏', number_format($stats['cards']),           'Active Cards',         ''],
        ['❓', number_format($stats['questions']),        'Quiz Questions',       ''],
        ['🎮', number_format($stats['sessions']),         'Games Completed',      ''],
        ['⭐', number_format($stats['avg_score'], 0),    'Average Score',        ''],
        ['🎯', number_format($stats['total_matches']),   'Total Pair Matches',   ''],
      ];
      foreach ($items as [$icon, $val, $lbl, $change]) {
          echo "<div class='admin-stat-card'>
            <div class='asc-icon'>{$icon}</div>
            <div class='asc-val'>{$val}</div>
            <div class='asc-label'>{$lbl}</div>
            " . ($change ? "<div class='asc-change'>{$change}</div>" : '') . "
          </div>";
      }
      ?>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-bottom:1.5rem;">

      <!-- Top Students -->
      <div class="card">
        <div class="card-header">
          <h4>🏆 Top Students</h4>
          <a href="reports.php" class="btn btn-secondary btn-sm">Full Report →</a>
        </div>
        <?php if ($top): ?>
        <div class="table-wrapper" style="border:none;">
          <table>
            <thead><tr><th>Rank</th><th>Student</th><th>Best Score</th><th>Sessions</th></tr></thead>
            <tbody>
              <?php foreach ($top as $i => $t): ?>
              <tr>
                <td><span class="rank-badge rank-<?= $i < 3 ? $i+1 : 'n' ?>"><?= $i+1 ?></span></td>
                <td><?= htmlspecialchars($t['username']) ?></td>
                <td class="text-gold fw-bold"><?= number_format($t['best_score']) ?></td>
                <td class="text-muted"><?= $t['sessions'] ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
          <p class="text-muted" style="padding:.75rem 0;">No game data yet.</p>
        <?php endif; ?>
      </div>

      <!-- Recent Games -->
      <div class="card">
        <div class="card-header">
          <h4>🕐 Recent Games</h4>
        </div>
        <?php if ($recent): ?>
        <div class="table-wrapper" style="border:none;">
          <table>
            <thead><tr><th>Student</th><th>Level</th><th>Score</th><th>Date</th></tr></thead>
            <tbody>
              <?php foreach ($recent as $r): ?>
              <tr>
                <td><?= htmlspecialchars($r['username']) ?></td>
                <td><span class="badge badge-<?= $r['difficulty'] ?>"><?= ucfirst($r['difficulty']) ?></span></td>
                <td class="text-gold fw-bold"><?= number_format($r['score']) ?></td>
                <td class="text-muted" style="font-size:.78rem;"><?= date('M d, g:i A', strtotime($r['completed_at'])) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
          <p class="text-muted" style="padding:.75rem 0;">No recent games.</p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Quick Links -->
    <div class="card">
      <div class="card-header"><h4>⚡ Quick Actions</h4></div>
      <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
        <a href="cards.php"     class="btn btn-secondary">🃏 Manage Cards</a>
        <a href="questions.php" class="btn btn-secondary">❓ Manage Questions</a>
        <a href="users.php"     class="btn btn-secondary">👥 Manage Users</a>
        <a href="reports.php"   class="btn btn-secondary">📊 View Reports</a>
        <a href="cards.php?action=add" class="btn btn-primary">+ Add Card</a>
      </div>
    </div>
  </div>
</div><!-- /.admin-main -->
</div>

<script src="../assets/js/main.js"></script>
<script src="../assets/js/admin.js"></script>
</body>
</html>
