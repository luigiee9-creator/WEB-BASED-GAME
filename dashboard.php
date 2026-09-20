<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();

$db     = getDB();
$userId = $_SESSION['user_id'];

// ── Fetch aggregate stats ────────────────────────────────────
$stmt = $db->prepare('
    SELECT
        COUNT(*)                          AS sessions_played,
        COALESCE(SUM(matched_pairs), 0)  AS total_matches,
        COALESCE(SUM(correct_answers),0) AS total_correct,
        COALESCE(SUM(total_questions),0) AS total_questions,
        COALESCE(MAX(score), 0)          AS best_score,
        COALESCE(AVG(score), 0)          AS avg_score
    FROM performance_records
    WHERE user_id = ?
');
$stmt->execute([$userId]);
$stats = $stmt->fetch();

// ── Recent sessions ─────────────────────────────────────────
$stmt = $db->prepare('
    SELECT pr.*, gs.started_at
    FROM performance_records pr
    JOIN game_sessions gs ON gs.id = pr.session_id
    WHERE pr.user_id = ?
    ORDER BY pr.completed_at DESC
    LIMIT 10
');
$stmt->execute([$userId]);
$sessions = $stmt->fetchAll();

$flash = flashGet();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — PH History Memory Card Game</title>
  <link rel="stylesheet" href="assets/css/main.css">
</head>
<body class="page-bg">

<!-- Navbar -->
<nav class="navbar">
  <div class="navbar-inner">
    <a href="index.php" class="navbar-brand">
      <span class="brand-icon">🇵🇭</span>
      <span>PH History Game</span>
    </a>
    <ul class="navbar-nav">
      <li><a href="dashboard.php" class="nav-link active">Dashboard</a></li>
      <li><a href="logout.php"    class="nav-link">Log Out</a></li>
    </ul>
  </div>
</nav>

<div class="container" style="padding-top:2rem;padding-bottom:4rem;">

  <!-- Welcome -->
  <div style="margin-bottom:2rem;">
    <h1>Welcome back, <span class="text-gold"><?= htmlspecialchars($_SESSION['username']) ?></span>! 👋</h1>
    <p style="color:var(--text-muted);margin-top:.25rem;">Choose a difficulty level to start a new game.</p>
  </div>

  <?php if ($flash): ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['message']) ?></div>
  <?php endif; ?>
  <?php if (($_GET['completed'] ?? '') === '1'): ?>
    <div class="alert alert-success">🎉 Game complete! Your results have been saved. Start another round below.</div>
  <?php endif; ?>

  <!-- Stat Cards -->
  <div class="stat-grid" style="margin-bottom:2rem;">
    <?php
    $accuracy = $stats['total_questions'] > 0
        ? round(($stats['total_correct'] / $stats['total_questions']) * 100) . '%'
        : '—';
    $statItems = [
      ['🎮', number_format($stats['sessions_played']), 'Games Played'],
      ['🃏', number_format($stats['total_matches']),   'Total Matches'],
      ['✅', number_format($stats['total_correct']),   'Correct Answers'],
      ['🎯', $accuracy,                                 'Quiz Accuracy'],
      ['🏆', number_format($stats['best_score']),      'Best Score'],
      ['📈', number_format($stats['avg_score'], 0),    'Avg Score'],
    ];
    foreach ($statItems as [$icon, $val, $lbl]) {
        echo "<div class='stat-card'>
          <div class='stat-icon'>{$icon}</div>
          <div class='stat-value'>{$val}</div>
          <div class='stat-label'>{$lbl}</div>
        </div>";
    }
    ?>
  </div>

  <!-- Difficulty Selection -->
  <h2 style="margin-bottom:1rem;">🎮 Start a New Game</h2>
  <div class="difficulty-grid" style="margin-bottom:3rem;">

    <div class="difficulty-card easy" onclick="selectDifficulty('easy')" id="diff-easy" role="button" tabindex="0">
      <div class="diff-icon">🟢</div>
      <h3>Easy</h3>
      <p>Great for beginners — 4 matching pairs</p>
      <div class="diff-meta">
        <span>📇 4 Pairs</span>
        <span>⏱ 2 min</span>
        <span>❓ 4 Questions</span>
      </div>
      <div style="margin-top:1rem;">
        <span class="badge badge-easy">Recommended</span>
      </div>
    </div>

    <div class="difficulty-card medium" onclick="selectDifficulty('medium')" id="diff-medium" role="button" tabindex="0">
      <div class="diff-icon">🟡</div>
      <h3>Medium</h3>
      <p>A solid challenge — 8 matching pairs</p>
      <div class="diff-meta">
        <span>📇 8 Pairs</span>
        <span>⏱ 4 min</span>
        <span>❓ 8 Questions</span>
      </div>
      <div style="margin-top:1rem;">
        <span class="badge badge-medium">Popular</span>
      </div>
    </div>

    <div class="difficulty-card hard" onclick="selectDifficulty('hard')" id="diff-hard" role="button" tabindex="0">
      <div class="diff-icon">🔴</div>
      <h3>Hard</h3>
      <p>Expert level — 10 matching pairs</p>
      <div class="diff-meta">
        <span>📇 10 Pairs</span>
        <span>⏱ 6 min</span>
        <span>❓ 10 Questions</span>
      </div>
      <div style="margin-top:1rem;">
        <span class="badge badge-hard">Challenge</span>
      </div>
    </div>
  </div>

  <!-- Session History -->
  <div class="flex-between" style="margin-bottom:1rem;">
    <h2>📜 Recent Sessions</h2>
  </div>

  <?php if (empty($sessions)): ?>
    <div class="card history-empty">
      <div class="empty-icon">🃏</div>
      <h3 style="margin-bottom:.5rem;">No games played yet</h3>
      <p>Select a difficulty above and play your first game!</p>
    </div>
  <?php else: ?>
    <div class="table-wrapper">
      <table id="history-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Date</th>
            <th>Difficulty</th>
            <th>Matches</th>
            <th>Quiz</th>
            <th>Accuracy</th>
            <th>Time</th>
            <th>Score</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($sessions as $i => $s): ?>
          <tr>
            <td class="text-muted"><?= $i + 1 ?></td>
            <td><?= htmlspecialchars(formatDate($s['completed_at'] ?: $s['started_at'])) ?></td>
            <td>
              <span class="badge badge-<?= htmlspecialchars($s['difficulty']) ?>">
                <?= ucfirst(htmlspecialchars($s['difficulty'])) ?>
              </span>
            </td>
            <td><?= (int)$s['matched_pairs'] ?> / <?= (int)$s['total_pairs'] ?></td>
            <td><?= (int)$s['correct_answers'] ?> / <?= (int)$s['total_questions'] ?></td>
            <td><?= htmlspecialchars(formatAccuracy($s['correct_answers'], $s['total_questions'])) ?></td>
            <td class="font-mono"><?= htmlspecialchars(formatTime($s['time_seconds'])) ?></td>
            <td class="text-gold fw-bold"><?= number_format($s['score']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

</div>

<footer style="border-top:1px solid var(--glass-border);padding:1.25rem;text-align:center;color:var(--text-muted);font-size:.8rem;">
  PH History Memory Card Game &copy; <?= date('Y') ?>
</footer>

<script src="assets/js/main.js"></script>
<script>
  // Keyboard accessibility for difficulty cards
  document.querySelectorAll('.difficulty-card').forEach(card => {
    card.addEventListener('keydown', e => {
      if (e.key === 'Enter' || e.key === ' ') card.click();
    });
  });
</script>
</body>
</html>
