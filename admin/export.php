<?php
// admin/export.php — CSV & printable HTML export handler
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();
$db     = getDB();
$format = sanitize($_GET['format'] ?? 'csv');
$diff   = sanitize($_GET['diff']   ?? '');
$from   = sanitize($_GET['from']   ?? '');
$to     = sanitize($_GET['to']     ?? '');

// ── Build report query ────────────────────────────────────────
$sql = '
    SELECT u.username, u.email,
        COUNT(pr.id)                      AS sessions,
        COALESCE(SUM(pr.matched_pairs),0) AS total_matches,
        COALESCE(SUM(pr.correct_answers),0) AS total_correct,
        COALESCE(SUM(pr.total_questions),0) AS total_questions,
        COALESCE(MAX(pr.score),0)           AS best_score,
        COALESCE(AVG(pr.score),0)           AS avg_score,
        COALESCE(AVG(pr.time_seconds),0)    AS avg_time,
        MAX(pr.completed_at)                AS last_played
    FROM users u
    LEFT JOIN performance_records pr ON pr.user_id = u.id
    WHERE u.role = "student"
';
$params = [];
if ($diff) { $sql .= ' AND pr.difficulty = ?'; $params[] = $diff; }
if ($from) { $sql .= ' AND pr.completed_at >= ?'; $params[] = $from . ' 00:00:00'; }
if ($to)   { $sql .= ' AND pr.completed_at <= ?'; $params[] = $to   . ' 23:59:59'; }
$sql .= ' GROUP BY u.id ORDER BY best_score DESC';

$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$timestamp = date('Ymd_His');

// ── CSV Export ────────────────────────────────────────────────
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"ph_game_report_{$timestamp}.csv\"");
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    // BOM for Excel UTF-8
    fputs($out, "\xEF\xBB\xBF");

    fputcsv($out, [
        'Rank', 'Username', 'Email', 'Sessions Played',
        'Total Matches', 'Correct Answers', 'Total Questions',
        'Quiz Accuracy (%)', 'Best Score', 'Avg Score',
        'Avg Time (mm:ss)', 'Last Played'
    ]);

    foreach ($rows as $i => $r) {
        $accuracy = $r['total_questions'] > 0
            ? round(($r['total_correct'] / $r['total_questions']) * 100)
            : 0;
        fputcsv($out, [
            $i + 1,
            $r['username'],
            $r['email'],
            $r['sessions'],
            $r['total_matches'],
            $r['total_correct'],
            $r['total_questions'],
            $accuracy . '%',
            $r['best_score'],
            round($r['avg_score']),
            formatTime((int)$r['avg_time']),
            $r['last_played'] ?? 'Never',
        ]);
    }
    fclose($out);
    exit;
}

// ── Printable HTML (for PDF via browser print) ────────────────
if ($format === 'print') {
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>PH History Game — Performance Report</title>
  <style>
    body { font-family: Arial, sans-serif; font-size: 10pt; color: #111; background: #fff; margin: 1cm; }
    h1 { font-size: 16pt; margin-bottom: 4px; }
    .meta { color: #555; font-size: 9pt; margin-bottom: 16px; }
    table { width: 100%; border-collapse: collapse; font-size: 9pt; }
    thead { background: #1a2445; color: white; }
    th, td { padding: 5px 8px; text-align: left; border: 1px solid #ddd; }
    tbody tr:nth-child(even) { background: #f5f5f5; }
    .gold { color: #b8860b; font-weight: bold; }
    .rank1 { background: #ffd700 !important; }
    .rank2 { background: #c0c0c0 !important; }
    .rank3 { background: #cd7f32 !important; }
    @media print { @page { margin: 1.5cm; } }
  </style>
</head>
<body onload="window.print()">
  <h1>🇵🇭 Philippine History Memory Card Game</h1>
  <h2 style="font-size:13pt;color:#555;font-weight:normal;">Student Performance Report</h2>
  <p class="meta">
    Generated: <?= date('F d, Y g:i A') ?>
    <?php if ($diff): ?> &nbsp;|&nbsp; Level: <?= ucfirst($diff) ?><?php endif; ?>
    <?php if ($from || $to): ?> &nbsp;|&nbsp; Period: <?= $from ?: '—' ?> to <?= $to ?: '—' ?><?php endif; ?>
    &nbsp;|&nbsp; <?= count($rows) ?> student(s)
  </p>
  <table>
    <thead>
      <tr>
        <th>#</th><th>Username</th><th>Email</th><th>Sessions</th>
        <th>Matches</th><th>Correct</th><th>Accuracy</th>
        <th>Best Score</th><th>Avg Score</th><th>Avg Time</th><th>Last Played</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $i => $r):
        $accuracy = $r['total_questions'] > 0 ? round(($r['total_correct']/$r['total_questions'])*100).'%' : '—';
        $cls = $i===0?'rank1':($i===1?'rank2':($i===2?'rank3':''));
      ?>
      <tr class="<?= $cls ?>">
        <td><?= $i+1 ?></td>
        <td><?= htmlspecialchars($r['username']) ?></td>
        <td><?= htmlspecialchars($r['email']) ?></td>
        <td><?= $r['sessions'] ?></td>
        <td><?= number_format($r['total_matches']) ?></td>
        <td><?= number_format($r['total_correct']) ?></td>
        <td><?= $accuracy ?></td>
        <td class="gold"><?= number_format($r['best_score']) ?></td>
        <td><?= round($r['avg_score']) ?></td>
        <td><?= formatTime((int)$r['avg_time']) ?></td>
        <td><?= $r['last_played'] ? date('M d, Y', strtotime($r['last_played'])) : 'Never' ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($rows)): ?>
      <tr><td colspan="11" style="text-align:center;padding:1rem;color:#999;">No data available.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
  <p style="margin-top:1rem;font-size:8pt;color:#999;">
    PH History Memory Card Game &copy; <?= date('Y') ?> — Confidential student report
  </p>
</body>
</html>
    <?php
    exit;
}

// Default: redirect back
header('Location: reports.php');
exit;
