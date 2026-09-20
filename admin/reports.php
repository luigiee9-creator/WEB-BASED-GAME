<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();
$db = getDB();

// ── Filters ───────────────────────────────────────────────────
$filterDiff = sanitize($_GET['diff'] ?? '');
$filterFrom = sanitize($_GET['from'] ?? '');
$filterTo   = sanitize($_GET['to']   ?? '');
$searchQ    = sanitize($_GET['q']    ?? '');

// ── Per-student summary ──────────────────────────────────────
$sql = '
    SELECT
        u.id,
        u.username,
        u.email,
        u.created_at AS joined,
        COUNT(pr.id)                    AS sessions,
        COALESCE(SUM(pr.matched_pairs), 0)   AS total_matches,
        COALESCE(SUM(pr.correct_answers),0)  AS total_correct,
        COALESCE(SUM(pr.total_questions),0)  AS total_questions,
        COALESCE(MAX(pr.score), 0)           AS best_score,
        COALESCE(AVG(pr.score), 0)           AS avg_score,
        COALESCE(AVG(pr.time_seconds), 0)    AS avg_time
    FROM users u
    LEFT JOIN performance_records pr ON pr.user_id = u.id
';
$where  = ["u.role = 'student'"];
$params = [];

if ($searchQ)    { $where[] = '(u.username LIKE ? OR u.email LIKE ?)'; $params[] = "%{$searchQ}%"; $params[] = "%{$searchQ}%"; }
if ($filterDiff) { $where[] = 'pr.difficulty = ?'; $params[] = $filterDiff; }
if ($filterFrom) { $where[] = 'pr.completed_at >= ?'; $params[] = $filterFrom . ' 00:00:00'; }
if ($filterTo)   { $where[] = 'pr.completed_at <= ?'; $params[] = $filterTo   . ' 23:59:59'; }

$sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' GROUP BY u.id ORDER BY best_score DESC';

$stmt = $db->prepare($sql);
$stmt->execute($params);
$report = $stmt->fetchAll();

// ── Overview totals ──────────────────────────────────────────
$totals = [
    'students'  => $db->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn(),
    'sessions'  => $db->query("SELECT COUNT(*) FROM performance_records")->fetchColumn(),
    'avg_score' => $db->query("SELECT COALESCE(AVG(score),0) FROM performance_records")->fetchColumn(),
    'top_score' => $db->query("SELECT COALESCE(MAX(score),0) FROM performance_records")->fetchColumn(),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reports — PH History Game Admin</title>
  <link rel="stylesheet" href="../assets/css/main.css">
  <link rel="stylesheet" href="../assets/css/admin.css">
  <style>
    @media print {
      .admin-sidebar, .admin-topbar, .toolbar, .export-group,
      .page-header .btn, .sidebar-toggle { display: none !important; }
      .admin-main { margin-left: 0 !important; }
      body { background: white; color: black; }
      table { font-size: 10pt; }
      thead { background: #eee !important; }
    }
  </style>
</head>
<body class="page-bg">
<div class="admin-layout">
<?php include '_sidebar.php'; ?>
<div class="admin-main">

  <div class="admin-topbar">
    <div style="display:flex;align-items:center;gap:.75rem;">
      <button class="sidebar-toggle" id="sidebar-toggle">☰</button>
      <span class="topbar-title">📊 Reports</span>
    </div>
    <div class="topbar-right">
      <div class="user-pill">👑 <?= htmlspecialchars($_SESSION['username']) ?></div>
    </div>
  </div>

  <div class="admin-content">
    <div class="page-header">
      <div>
        <h1>Performance Reports</h1>
        <p class="page-subtitle">Student scores and game statistics</p>
      </div>
      <div class="export-group">
        <a href="export.php?format=csv<?= $filterDiff ? '&diff='.$filterDiff : '' ?>"
           class="btn btn-success btn-sm">📥 Export CSV</a>
        <button onclick="window.print()" class="btn btn-secondary btn-sm">🖨️ Print / PDF</button>
      </div>
    </div>

    <!-- Overview stats -->
    <div class="admin-stat-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:1.5rem;">
      <?php
      $ov = [
        ['👨‍🎓', number_format($totals['students']),             'Total Students'],
        ['🎮',   number_format($totals['sessions']),             'Total Games'],
        ['⭐',   number_format($totals['avg_score'], 0),        'Avg Score'],
        ['🏆',   number_format($totals['top_score']),           'Top Score'],
      ];
      foreach ($ov as [$i,$v,$l]) {
          echo "<div class='admin-stat-card'><div class='asc-icon'>{$i}</div><div class='asc-val'>{$v}</div><div class='asc-label'>{$l}</div></div>";
      }
      ?>
    </div>

    <!-- Filters -->
    <form method="GET" class="toolbar" style="margin-bottom:1.25rem;">
      <div class="toolbar-left" style="flex-wrap:wrap;gap:.5rem;">
        <div class="search-wrap">
          <span class="search-icon">🔍</span>
          <input type="text" name="q" class="search-input" placeholder="Search student…" value="<?= htmlspecialchars($searchQ) ?>">
        </div>
        <select name="diff" class="filter-select" onchange="this.form.submit()">
          <option value="">All Levels</option>
          <?php foreach (['easy','medium','hard'] as $d): ?>
          <option value="<?= $d ?>" <?= $filterDiff===$d?'selected':'' ?>><?= ucfirst($d) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="date" name="from" class="filter-select" value="<?= $filterFrom ?>" title="From date">
        <input type="date" name="to"   class="filter-select" value="<?= $filterTo   ?>" title="To date">
        <button type="submit" class="btn btn-secondary btn-sm">Apply</button>
        <a href="reports.php" class="btn btn-secondary btn-sm">Clear</a>
      </div>
    </form>

    <!-- Report Table -->
    <div class="table-wrapper">
      <table id="report-table">
        <thead>
          <tr>
            <th>Rank</th>
            <th>Student</th>
            <th>Email</th>
            <th>Sessions</th>
            <th>Total Matches</th>
            <th>Correct Answers</th>
            <th>Quiz Accuracy</th>
            <th>Best Score</th>
            <th>Avg Score</th>
            <th>Avg Time</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($report as $i => $r):
            $accuracy = $r['total_questions'] > 0
              ? round(($r['total_correct'] / $r['total_questions']) * 100) . '%' : '—';
          ?>
          <tr>
            <td><span class="rank-badge rank-<?= $i < 3 ? $i+1 : 'n' ?>"><?= $i+1 ?></span></td>
            <td><strong><?= htmlspecialchars($r['username']) ?></strong></td>
            <td class="text-muted"><?= htmlspecialchars($r['email']) ?></td>
            <td class="text-center"><?= $r['sessions'] ?></td>
            <td class="text-center"><?= number_format($r['total_matches']) ?></td>
            <td class="text-center"><?= number_format($r['total_correct']) ?></td>
            <td class="text-center"><?= $accuracy ?></td>
            <td class="text-gold fw-bold"><?= number_format($r['best_score']) ?></td>
            <td><?= number_format($r['avg_score'], 0) ?></td>
            <td class="font-mono"><?= formatTime((int)$r['avg_time']) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($report)): ?>
          <tr><td colspan="10" class="history-empty" style="padding:2rem;">No data found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <p class="text-muted" style="font-size:.75rem;margin-top:.75rem;">
      Report generated: <?= date('F d, Y g:i A') ?> &nbsp;|&nbsp;
      <?= count($report) ?> student(s) shown
      <?php if ($filterDiff): ?>&nbsp;| Filtered: <?= ucfirst($filterDiff) ?><?php endif; ?>
    </p>
  </div>
</div>
</div>

<script src="../assets/js/main.js"></script>
<script src="../assets/js/admin.js"></script>
</body>
</html>
