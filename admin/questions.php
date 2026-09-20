<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();
$db = getDB();

$msg   = '';
$error = '';
$action = sanitize($_GET['action'] ?? '');

// Delete question
if ($action === 'delete' && isset($_GET['id'])) {
    if (verifyCsrf($_GET['csrf'] ?? '')) {
        $db->prepare('DELETE FROM quiz_questions WHERE id = ?')->execute([sanitizeInt($_GET['id'])]);
        $msg = '✅ Question deleted.';
    } else { $error = 'Invalid CSRF token.'; }
}

// Save question + answers (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $qid     = sanitizeInt($_POST['qid'] ?? 0);
    $cardId  = sanitizeInt($_POST['card_id'] ?? 0);
    $qtext   = sanitize($_POST['question_text'] ?? '');
    $diff    = sanitize($_POST['difficulty'] ?? 'easy');
    $correct = sanitizeInt($_POST['correct_index'] ?? 0);
    $answers = $_POST['answers'] ?? [];

    if (empty($qtext) || $cardId === 0 || count($answers) < 2) {
        $error = 'Question text, card, and at least 2 answers are required.';
    } else {
        $db->beginTransaction();
        try {
            if ($qid) {
                $db->prepare('UPDATE quiz_questions SET card_id=?,question_text=?,difficulty=?,updated_at=NOW() WHERE id=?')
                   ->execute([$cardId,$qtext,$diff,$qid]);
                // But quiz_questions doesn't have updated_at — add inline
                $db->prepare('DELETE FROM quiz_answers WHERE question_id = ?')->execute([$qid]);
            } else {
                $db->prepare('INSERT INTO quiz_questions (card_id,question_text,difficulty) VALUES (?,?,?)')
                   ->execute([$cardId,$qtext,$diff]);
                $qid = (int)$db->lastInsertId();
            }
            foreach ($answers as $i => $ans) {
                $ans = sanitize($ans);
                if (empty($ans)) continue;
                $db->prepare('INSERT INTO quiz_answers (question_id,answer_text,is_correct,display_order) VALUES (?,?,?,?)')
                   ->execute([$qid,$ans,($i == $correct)?1:0,$i+1]);
            }
            $db->commit();
            $msg = $qid ? '✅ Question updated.' : '✅ Question added.';
        } catch (Throwable $e) {
            $db->rollBack();
            $error = 'Error saving question: ' . $e->getMessage();
        }
    }
}

// Load question for edit
$editQ    = null;
$editAnss = [];
if ($action === 'edit' && isset($_GET['id'])) {
    $s = $db->prepare('SELECT * FROM quiz_questions WHERE id = ?');
    $s->execute([sanitizeInt($_GET['id'])]);
    $editQ = $s->fetch();
    if ($editQ) {
        $s2 = $db->prepare('SELECT * FROM quiz_answers WHERE question_id = ? ORDER BY display_order');
        $s2->execute([$editQ['id']]);
        $editAnss = $s2->fetchAll();
    }
}

// Filters
$filterCard   = sanitizeInt($_GET['card_id']   ?? 0);
$filterPeriod = sanitizeInt($_GET['period_id'] ?? 0);
$filterDiff   = sanitize($_GET['diff'] ?? '');
$searchQ      = sanitize($_GET['q']    ?? '');

$sql = '
    SELECT qq.*, c.title AS card_title, c.icon AS card_icon, c.difficulty AS card_diff,
           p.name AS period_name,
           (SELECT COUNT(*) FROM quiz_answers qa WHERE qa.question_id=qq.id) AS answer_count
    FROM quiz_questions qq
    JOIN cards c ON c.id = qq.card_id
    LEFT JOIN periods p ON p.id = qq.period_id
    WHERE 1=1';
$params = [];
if ($filterCard)   { $sql .= ' AND qq.card_id = ?';     $params[] = $filterCard; }
if ($filterPeriod) { $sql .= ' AND c.period_id = ?';    $params[] = $filterPeriod; }
if ($filterDiff)   { $sql .= ' AND qq.difficulty = ?';  $params[] = $filterDiff; }
if ($searchQ)      { $sql .= ' AND qq.question_text LIKE ?'; $params[] = "%{$searchQ}%"; }
$sql .= ' ORDER BY c.period_id, c.title, qq.id';

$stmt = $db->prepare($sql); $stmt->execute($params);
$questions = $stmt->fetchAll();

$cards   = $db->query('SELECT id,title,icon,difficulty FROM cards WHERE is_active=1 ORDER BY title')->fetchAll();
$periods = $db->query('SELECT * FROM periods ORDER BY display_order')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Quiz Questions — PH History Game Admin</title>
  <link rel="stylesheet" href="../assets/css/main.css">
  <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="page-bg">
<div class="admin-layout">
<?php include '_sidebar.php'; ?>
<div class="admin-main">

  <div class="admin-topbar">
    <div style="display:flex;align-items:center;gap:.75rem;">
      <button class="sidebar-toggle" id="sidebar-toggle">☰</button>
      <span class="topbar-title">❓ Quiz Questions</span>
    </div>
    <div class="topbar-right">
      <div class="user-pill">👑 <?= htmlspecialchars($_SESSION['username']) ?></div>
    </div>
  </div>

  <div class="admin-content">
    <div class="page-header">
      <div>
        <h1>Quiz Questions</h1>
        <p class="page-subtitle"><?= count($questions) ?> question(s) found</p>
      </div>
      <button class="btn btn-primary" onclick="openModal('q-modal')">+ Add Question</button>
    </div>

    <?php if ($msg):   ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- Filters -->
    <form method="GET" class="toolbar">
      <div class="toolbar-left" style="flex-wrap:wrap;gap:.5rem;">
        <div class="search-wrap">
          <span class="search-icon">🔍</span>
          <input type="text" name="q" class="search-input" placeholder="Search questions…" value="<?= htmlspecialchars($searchQ) ?>">
        </div>
        <select name="period_id" class="filter-select" onchange="this.form.submit()">
          <option value="">All Periods</option>
          <?php foreach ($periods as $p): ?>
          <option value="<?= $p['id'] ?>" <?= $filterPeriod==$p['id']?'selected':'' ?>><?= htmlspecialchars($p['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="diff" class="filter-select" onchange="this.form.submit()">
          <option value="">All Levels</option>
          <?php foreach (['easy','medium','hard'] as $d): ?>
          <option value="<?= $d ?>" <?= $filterDiff===$d?'selected':'' ?>><?= ucfirst($d) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-secondary btn-sm">Search</button>
        <a href="questions.php" class="btn btn-secondary btn-sm">Clear</a>
      </div>
    </form>

    <!-- Table -->
    <div class="table-wrapper">
      <table>
        <thead>
          <tr><th>ID</th><th>Card</th><th>Question</th><th>Level</th><th>Answers</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach ($questions as $q): ?>
          <tr>
            <td class="text-muted"><?= $q['id'] ?></td>
            <td>
              <span style="font-size:1.2rem;"><?= htmlspecialchars($q['card_icon']) ?></span>
              <span style="font-size:.8rem;margin-left:.3rem;"><?= htmlspecialchars(mb_strimwidth($q['card_title'],0,30,'…')) ?></span>
            </td>
            <td style="max-width:320px;font-size:.85rem;"><?= htmlspecialchars(mb_strimwidth($q['question_text'],0,80,'…')) ?></td>
            <td><span class="badge badge-<?= $q['difficulty'] ?>"><?= ucfirst($q['difficulty']) ?></span></td>
            <td class="text-center">
              <span class="badge <?= $q['answer_count'] >= 4 ? 'badge-active' : 'badge-inactive' ?>">
                <?= $q['answer_count'] ?> answer(s)
              </span>
            </td>
            <td>
              <div class="action-group">
                <a href="questions.php?action=edit&id=<?= $q['id'] ?>" class="btn btn-secondary btn-sm">✏️</a>
                <a href="questions.php?action=delete&id=<?= $q['id'] ?>&csrf=<?= csrfToken() ?>"
                   class="btn btn-danger btn-sm"
                   onclick="return confirm('Delete this question and all its answers?')">🗑️</a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($questions)): ?>
          <tr><td colspan="6" class="history-empty" style="padding:2rem;">No questions found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</div>

<!-- Add/Edit Question Modal -->
<div id="q-modal" class="modal-backdrop hidden">
  <div class="admin-modal" style="max-width:640px;">
    <div class="modal-header">
      <h3><?= $editQ ? '✏️ Edit Question' : '+ Add Question' ?></h3>
      <button class="modal-close" onclick="closeModal('q-modal')">✕</button>
    </div>
    <form method="POST" action="questions.php">
      <?= csrfField() ?>
      <?php if ($editQ): ?><input type="hidden" name="qid" value="<?= $editQ['id'] ?>"><?php endif; ?>

      <div class="form-group">
        <label class="form-label">Card *</label>
        <select name="card_id" class="form-control" required>
          <option value="">— Select Card —</option>
          <?php foreach ($cards as $c): ?>
          <option value="<?= $c['id'] ?>"
            <?= ($editQ['card_id'] ?? $filterCard) == $c['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($c['icon'] . ' ' . $c['title']) ?> (<?= $c['difficulty'] ?>)
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Question Text *</label>
        <textarea name="question_text" class="form-control" rows="3" required><?= htmlspecialchars($editQ['question_text'] ?? '') ?></textarea>
      </div>

      <div class="form-group">
        <label class="form-label">Difficulty</label>
        <select name="difficulty" class="form-control">
          <?php foreach (['easy','medium','hard'] as $d): ?>
          <option value="<?= $d ?>" <?= ($editQ['difficulty'] ?? 'easy') === $d ? 'selected' : '' ?>><?= ucfirst($d) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Answer Choices <small class="text-muted">(tick the correct answer)</small></label>
        <div class="answer-fields" id="answer-fields">
          <?php
          $letters = ['A','B','C','D'];
          for ($i = 0; $i < 4; $i++):
            $ans  = $editAnss[$i]['answer_text'] ?? '';
            $corr = isset($editAnss[$i]) && $editAnss[$i]['is_correct'] ? 'checked' : ($i === 0 && !$editQ ? 'checked' : '');
          ?>
          <div class="answer-field <?= ($corr ? 'correct-field' : '') ?>" id="af-<?= $i ?>">
            <span class="ans-tag"><?= $letters[$i] ?></span>
            <input type="text" name="answers[<?= $i ?>]" class="form-control"
                   placeholder="Answer <?= $letters[$i] ?>"
                   value="<?= htmlspecialchars($ans) ?>" required>
            <input type="radio" name="correct_index" value="<?= $i ?>"
                   class="correct-check" title="Mark as correct"
                   <?= $corr ?> onchange="setCorrectAnswer(<?= $i ?>)">
          </div>
          <?php endfor; ?>
        </div>
        <span class="form-hint">Radio button = correct answer</span>
      </div>

      <div style="display:flex;gap:.75rem;justify-content:flex-end;margin-top:.5rem;">
        <button type="button" class="btn btn-secondary" onclick="closeModal('q-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">💾 Save Question</button>
      </div>
    </form>
  </div>
</div>

<script src="../assets/js/main.js"></script>
<script src="../assets/js/admin.js"></script>
<script>
  <?php if ($editQ): ?>
    document.addEventListener('DOMContentLoaded', () => openModal('q-modal'));
  <?php endif; ?>
</script>
</body>
</html>
