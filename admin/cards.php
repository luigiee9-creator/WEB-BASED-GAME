<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();
$db = getDB();

// ── Handle form actions ──────────────────────────────────────
$msg   = '';
$error = '';
$action = sanitize($_GET['action'] ?? '');

// Delete card
if ($action === 'delete' && isset($_GET['id'])) {
    if (verifyCsrf($_GET['csrf'] ?? '')) {
        $db->prepare('DELETE FROM cards WHERE id = ?')->execute([sanitizeInt($_GET['id'])]);
        $msg = '✅ Card deleted successfully.';
    } else { $error = 'Invalid CSRF token.'; }
}

// Toggle active
if ($action === 'toggle' && isset($_GET['id'])) {
    if (verifyCsrf($_GET['csrf'] ?? '')) {
        $db->prepare('UPDATE cards SET is_active = NOT is_active WHERE id = ?')->execute([sanitizeInt($_GET['id'])]);
        header('Location: cards.php');
        exit;
    }
}

// Save (add/edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $id    = sanitizeInt($_POST['id'] ?? 0);
    $title = sanitize($_POST['title']       ?? '');
    $desc  = sanitize($_POST['description'] ?? '');
    $icon  = sanitize($_POST['icon']        ?? '📜');
    $per   = sanitizeInt($_POST['period_id'] ?? 0) ?: null;
    $diff  = sanitize($_POST['difficulty']  ?? 'easy');
    $active= (int)($_POST['is_active'] ?? 1);

    if (empty($title)) {
        $error = 'Title is required.';
    } elseif ($id) {
        $db->prepare('UPDATE cards SET title=?,description=?,icon=?,period_id=?,difficulty=?,is_active=?,updated_at=NOW() WHERE id=?')
           ->execute([$title,$desc,$icon,$per,$diff,$active,$id]);
        $msg = '✅ Card updated.';
    } else {
        $db->prepare('INSERT INTO cards (title,description,icon,period_id,difficulty) VALUES (?,?,?,?,?)')
           ->execute([$title,$desc,$icon,$per,$diff]);
        $msg = '✅ Card added successfully.';
    }
}

// Load card for edit
$editCard = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $s = $db->prepare('SELECT * FROM cards WHERE id = ?');
    $s->execute([sanitizeInt($_GET['id'])]);
    $editCard = $s->fetch();
}

// Fetch all cards with period names
$searchQ  = sanitize($_GET['q']      ?? '');
$filterD  = sanitize($_GET['diff']   ?? '');
$filterP  = sanitize($_GET['period'] ?? '');

$sql = 'SELECT c.*, p.name AS period_name FROM cards c LEFT JOIN periods p ON p.id = c.period_id WHERE 1=1';
$params = [];
if ($searchQ) { $sql .= ' AND c.title LIKE ?';       $params[] = "%{$searchQ}%"; }
if ($filterD) { $sql .= ' AND c.difficulty = ?';     $params[] = $filterD; }
if ($filterP) { $sql .= ' AND c.period_id = ?';      $params[] = $filterP; }
$sql .= ' ORDER BY c.period_id, c.difficulty, c.title';
$stmt = $db->prepare($sql); $stmt->execute($params);
$cards = $stmt->fetchAll();

$periods = $db->query('SELECT * FROM periods ORDER BY display_order')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Cards — PH History Game Admin</title>
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
      <span class="topbar-title">🃏 Manage Cards</span>
    </div>
    <div class="topbar-right">
      <div class="user-pill">👑 <?= htmlspecialchars($_SESSION['username']) ?></div>
    </div>
  </div>

  <div class="admin-content">
    <div class="page-header">
      <div>
        <h1>Cards</h1>
        <p class="page-subtitle"><?= count($cards) ?> card(s) found</p>
      </div>
      <button class="btn btn-primary" onclick="openModal('card-modal')">+ Add Card</button>
    </div>

    <?php if ($msg):   ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- Filters -->
    <form method="GET" class="toolbar" style="margin-bottom:1.25rem;">
      <div class="toolbar-left">
        <div class="search-wrap">
          <span class="search-icon">🔍</span>
          <input type="text" name="q" class="search-input" placeholder="Search cards…" value="<?= htmlspecialchars($searchQ) ?>">
        </div>
        <select name="diff" class="filter-select" onchange="this.form.submit()">
          <option value="">All Levels</option>
          <option value="easy"   <?= $filterD==='easy'   ? 'selected':'' ?>>Easy</option>
          <option value="medium" <?= $filterD==='medium' ? 'selected':'' ?>>Medium</option>
          <option value="hard"   <?= $filterD==='hard'   ? 'selected':'' ?>>Hard</option>
        </select>
        <select name="period" class="filter-select" onchange="this.form.submit()">
          <option value="">All Periods</option>
          <?php foreach ($periods as $p): ?>
          <option value="<?= $p['id'] ?>" <?= $filterP == $p['id'] ? 'selected':'' ?>>
            <?= htmlspecialchars($p['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-secondary btn-sm">Search</button>
        <a href="cards.php" class="btn btn-secondary btn-sm">Clear</a>
      </div>
    </form>

    <!-- Table -->
    <div class="table-wrapper">
      <table id="cards-table">
        <thead>
          <tr>
            <th>ID</th><th>Icon</th><th>Title</th><th>Period</th>
            <th>Difficulty</th><th>Status</th><th>Questions</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($cards as $c):
            $qCount = $db->prepare('SELECT COUNT(*) FROM quiz_questions WHERE card_id=? AND is_active=1');
            $qCount->execute([$c['id']]);
            $numQ = $qCount->fetchColumn();
          ?>
          <tr>
            <td class="text-muted"><?= $c['id'] ?></td>
            <td style="font-size:1.4rem;"><?= htmlspecialchars($c['icon']) ?></td>
            <td><strong><?= htmlspecialchars($c['title']) ?></strong>
              <?php if ($c['description']): ?>
                <br><small class="text-muted" style="font-size:.72rem;"><?= htmlspecialchars(mb_strimwidth($c['description'],0,60,'…')) ?></small>
              <?php endif; ?>
            </td>
            <td><span class="period-badge"><?= htmlspecialchars($c['period_name'] ?? '—') ?></span></td>
            <td><span class="badge badge-<?= $c['difficulty'] ?>"><?= ucfirst($c['difficulty']) ?></span></td>
            <td>
              <a href="cards.php?action=toggle&id=<?= $c['id'] ?>&csrf=<?= csrfToken() ?>" class="badge <?= $c['is_active'] ? 'badge-active' : 'badge-inactive' ?>">
                <?= $c['is_active'] ? 'Active' : 'Inactive' ?>
              </a>
            </td>
            <td class="text-center"><?= $numQ ?></td>
            <td>
              <div class="action-group">
                <a href="cards.php?action=edit&id=<?= $c['id'] ?>" class="btn btn-secondary btn-sm">✏️</a>
                <a href="questions.php?card_id=<?= $c['id'] ?>" class="btn btn-secondary btn-sm">❓</a>
                <a href="cards.php?action=delete&id=<?= $c['id'] ?>&csrf=<?= csrfToken() ?>"
                   class="btn btn-danger btn-sm"
                   onclick="return confirm('Delete this card and ALL its questions? This cannot be undone.')">🗑️</a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($cards)): ?>
          <tr><td colspan="8" class="history-empty" style="padding:2rem;">No cards found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</div>

<!-- Add/Edit Card Modal -->
<div id="card-modal" class="modal-backdrop hidden">
  <div class="admin-modal">
    <div class="modal-header">
      <h3><?= $editCard ? '✏️ Edit Card' : '+ Add New Card' ?></h3>
      <button class="modal-close" onclick="closeModal('card-modal')">✕</button>
    </div>
    <form method="POST" action="cards.php">
      <?= csrfField() ?>
      <?php if ($editCard): ?>
        <input type="hidden" name="id" value="<?= $editCard['id'] ?>">
      <?php endif; ?>
      <div class="form-group">
        <label class="form-label">Title *</label>
        <input type="text" name="title" class="form-control" required maxlength="150"
               value="<?= htmlspecialchars($editCard['title'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control"><?= htmlspecialchars($editCard['description'] ?? '') ?></textarea>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
        <div class="form-group">
          <label class="form-label">Icon (emoji)</label>
          <input type="text" name="icon" class="form-control" maxlength="10"
                 value="<?= htmlspecialchars($editCard['icon'] ?? '📜') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Difficulty</label>
          <select name="difficulty" class="form-control">
            <?php foreach (['easy','medium','hard'] as $d): ?>
            <option value="<?= $d ?>" <?= ($editCard['difficulty'] ?? 'easy') === $d ? 'selected':'' ?>><?= ucfirst($d) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Historical Period</label>
        <select name="period_id" class="form-control">
          <option value="">— None —</option>
          <?php foreach ($periods as $p): ?>
          <option value="<?= $p['id'] ?>" <?= ($editCard['period_id'] ?? '') == $p['id'] ? 'selected':'' ?>>
            <?= htmlspecialchars($p['icon'] . ' ' . $p['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if ($editCard): ?>
      <div class="form-group">
        <label class="form-label">Status</label>
        <select name="is_active" class="form-control">
          <option value="1" <?= $editCard['is_active'] ? 'selected':'' ?>>Active</option>
          <option value="0" <?= !$editCard['is_active'] ? 'selected':'' ?>>Inactive</option>
        </select>
      </div>
      <?php endif; ?>
      <div style="display:flex;gap:.75rem;justify-content:flex-end;margin-top:.5rem;">
        <button type="button" class="btn btn-secondary" onclick="closeModal('card-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">💾 Save Card</button>
      </div>
    </form>
  </div>
</div>

<script src="../assets/js/main.js"></script>
<script src="../assets/js/admin.js"></script>
<script>
  <?php if ($editCard): ?>
    // Auto-open modal if editing
    document.addEventListener('DOMContentLoaded', () => openModal('card-modal'));
  <?php endif; ?>
</script>
</body>
</html>
