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

// Toggle active
if ($action === 'toggle' && isset($_GET['id'])) {
    if (verifyCsrf($_GET['csrf'] ?? '')) {
        $id = sanitizeInt($_GET['id']);
        if ($id === $_SESSION['user_id']) {
            $error = 'You cannot deactivate your own account.';
        } else {
            $db->prepare('UPDATE users SET is_active = NOT is_active WHERE id = ?')->execute([$id]);
            header('Location: users.php'); exit;
        }
    }
}

// Promote/demote
if ($action === 'promote' && isset($_GET['id'])) {
    if (verifyCsrf($_GET['csrf'] ?? '')) {
        $id = sanitizeInt($_GET['id']);
        if ($id === $_SESSION['user_id']) {
            $error = 'You cannot change your own role.';
        } else {
            $db->prepare("UPDATE users SET role = IF(role='admin','student','admin') WHERE id=?")->execute([$id]);
            header('Location: users.php'); exit;
        }
    }
}

// Delete user
if ($action === 'delete' && isset($_GET['id'])) {
    if (verifyCsrf($_GET['csrf'] ?? '')) {
        $id = sanitizeInt($_GET['id']);
        if ($id === $_SESSION['user_id']) {
            $error = 'You cannot delete yourself.';
        } else {
            $db->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
            $msg = '✅ User deleted.';
        }
    }
}

// Change password (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $uid  = sanitizeInt($_POST['uid'] ?? 0);
    $pwd  = $_POST['new_password'] ?? '';
    if (strlen($pwd) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $hash = password_hash($pwd, PASSWORD_DEFAULT);
        $db->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([$hash, $uid]);
        $msg = '✅ Password changed.';
    }
}

// Fetch users
$searchQ    = sanitize($_GET['q']    ?? '');
$filterRole = sanitize($_GET['role'] ?? '');

$sql = 'SELECT u.*,
    (SELECT COUNT(*) FROM performance_records WHERE user_id=u.id) AS sessions,
    (SELECT COALESCE(MAX(score),0) FROM performance_records WHERE user_id=u.id) AS best_score
    FROM users u WHERE 1=1';
$params = [];
if ($searchQ)    { $sql .= ' AND (u.username LIKE ? OR u.email LIKE ?)'; $params[] = "%{$searchQ}%"; $params[] = "%{$searchQ}%"; }
if ($filterRole) { $sql .= ' AND u.role = ?'; $params[] = $filterRole; }
$sql .= ' ORDER BY u.created_at DESC';
$stmt = $db->prepare($sql); $stmt->execute($params);
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Users — PH History Game Admin</title>
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
      <span class="topbar-title">👥 Users</span>
    </div>
    <div class="topbar-right">
      <div class="user-pill">👑 <?= htmlspecialchars($_SESSION['username']) ?></div>
    </div>
  </div>

  <div class="admin-content">
    <div class="page-header">
      <div>
        <h1>User Management</h1>
        <p class="page-subtitle"><?= count($users) ?> user(s)</p>
      </div>
    </div>

    <?php if ($msg):   ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- Filters -->
    <form method="GET" class="toolbar">
      <div class="toolbar-left">
        <div class="search-wrap">
          <span class="search-icon">🔍</span>
          <input type="text" name="q" class="search-input" placeholder="Search by username/email…" value="<?= htmlspecialchars($searchQ) ?>">
        </div>
        <select name="role" class="filter-select" onchange="this.form.submit()">
          <option value="">All Roles</option>
          <option value="student" <?= $filterRole==='student'?'selected':'' ?>>Students</option>
          <option value="admin"   <?= $filterRole==='admin'  ?'selected':'' ?>>Admins</option>
        </select>
        <button type="submit" class="btn btn-secondary btn-sm">Search</button>
        <a href="users.php" class="btn btn-secondary btn-sm">Clear</a>
      </div>
    </form>

    <!-- Table -->
    <div class="table-wrapper">
      <table id="users-table">
        <thead>
          <tr>
            <th>ID</th><th>Username</th><th>Email</th><th>Role</th>
            <th>Status</th><th>Sessions</th><th>Best Score</th><th>Joined</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
          <tr>
            <td class="text-muted"><?= $u['id'] ?></td>
            <td><strong><?= htmlspecialchars($u['username']) ?></strong>
              <?php if ($u['id'] === $_SESSION['user_id']): ?>
                <span class="badge badge-admin" style="font-size:.62rem;">You</span>
              <?php endif; ?>
            </td>
            <td class="text-muted"><?= htmlspecialchars($u['email']) ?></td>
            <td><span class="badge badge-<?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span></td>
            <td><span class="badge <?= $u['is_active'] ? 'badge-active':'badge-inactive' ?>"><?= $u['is_active']?'Active':'Inactive' ?></span></td>
            <td class="text-center"><?= $u['sessions'] ?></td>
            <td class="text-gold fw-bold"><?= number_format($u['best_score']) ?></td>
            <td class="text-muted" style="font-size:.78rem;"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
            <td>
              <div class="action-group">
                <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                  <a href="users.php?action=toggle&id=<?= $u['id'] ?>&csrf=<?= csrfToken() ?>"
                     class="btn btn-secondary btn-sm"
                     title="<?= $u['is_active']?'Deactivate':'Activate' ?>">
                    <?= $u['is_active'] ? '🚫' : '✅' ?>
                  </a>
                  <a href="users.php?action=promote&id=<?= $u['id'] ?>&csrf=<?= csrfToken() ?>"
                     class="btn btn-secondary btn-sm"
                     title="<?= $u['role']==='admin'?'Demote to Student':'Promote to Admin' ?>"
                     onclick="return confirm('Change role for <?= htmlspecialchars($u['username']) ?>?')">
                    <?= $u['role']==='admin'?'👤':'👑' ?>
                  </a>
                  <button class="btn btn-secondary btn-sm"
                          title="Change Password"
                          onclick="openPwdModal(<?= $u['id'] ?>,'<?= htmlspecialchars($u['username']) ?>')">🔑</button>
                  <a href="users.php?action=delete&id=<?= $u['id'] ?>&csrf=<?= csrfToken() ?>"
                     class="btn btn-danger btn-sm"
                     onclick="return confirm('Delete user <?= htmlspecialchars($u['username']) ?> and ALL their game data?')">🗑️</a>
                <?php else: ?>
                  <span class="text-muted" style="font-size:.78rem;">Current user</span>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($users)): ?>
          <tr><td colspan="9" class="history-empty" style="padding:2rem;">No users found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</div>

<!-- Change Password Modal -->
<div id="pwd-modal" class="modal-backdrop hidden">
  <div class="admin-modal" style="max-width:420px;">
    <div class="modal-header">
      <h3>🔑 Change Password</h3>
      <button class="modal-close" onclick="closeModal('pwd-modal')">✕</button>
    </div>
    <p class="text-muted" id="pwd-user-label" style="margin-bottom:1rem;font-size:.875rem;"></p>
    <form method="POST" action="users.php">
      <?= csrfField() ?>
      <input type="hidden" name="uid" id="pwd-uid">
      <div class="form-group">
        <label class="form-label">New Password</label>
        <input type="password" name="new_password" class="form-control" minlength="6" required placeholder="At least 6 characters">
      </div>
      <div style="display:flex;gap:.75rem;justify-content:flex-end;">
        <button type="button" class="btn btn-secondary" onclick="closeModal('pwd-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Password</button>
      </div>
    </form>
  </div>
</div>

<script src="../assets/js/main.js"></script>
<script src="../assets/js/admin.js"></script>
<script>
function openPwdModal(uid, username) {
  document.getElementById('pwd-uid').value       = uid;
  document.getElementById('pwd-user-label').textContent = 'Changing password for: ' + username;
  openModal('pwd-modal');
}
</script>
</body>
</html>
