<?php
// admin/_sidebar.php — Shared admin sidebar include
$currentFile = basename($_SERVER['PHP_SELF']);
function sidebarLink(string $file, string $icon, string $label, string $current): string {
    $active = ($current === $file) ? 'active' : '';
    $path   = ($file === 'index.php') ? '../admin/index.php' : "../admin/{$file}";
    return "<li><a href='{$path}' class='sidebar-link {$active}'><span class='s-icon'>{$icon}</span>{$label}</a></li>";
}
?>
<aside class="admin-sidebar" id="admin-sidebar">
  <a href="../admin/index.php" class="sidebar-brand">
    <span class="brand-icon">🇵🇭</span>
    <span>PH Game Admin</span>
  </a>

  <div class="sidebar-section">
    <div class="sidebar-section-title">Overview</div>
    <ul class="sidebar-nav">
      <?= sidebarLink('index.php', '📊', 'Dashboard', $currentFile) ?>
    </ul>
  </div>

  <div class="sidebar-section">
    <div class="sidebar-section-title">Content</div>
    <ul class="sidebar-nav">
      <?= sidebarLink('cards.php',     '🃏', 'Cards',          $currentFile) ?>
      <?= sidebarLink('questions.php', '❓', 'Quiz Questions', $currentFile) ?>
    </ul>
  </div>

  <div class="sidebar-section">
    <div class="sidebar-section-title">Users</div>
    <ul class="sidebar-nav">
      <?= sidebarLink('users.php', '👥', 'Students', $currentFile) ?>
    </ul>
  </div>

  <div class="sidebar-section">
    <div class="sidebar-section-title">Analytics</div>
    <ul class="sidebar-nav">
      <?= sidebarLink('reports.php', '📊', 'Reports & Export', $currentFile) ?>
    </ul>
  </div>

  <div class="sidebar-footer">
    <a href="../dashboard.php" class="sidebar-link"><span class="s-icon">👤</span>Student View</a>
    <a href="../logout.php"    class="sidebar-link" style="color:var(--red-400);"><span class="s-icon">🚪</span>Log Out</a>
  </div>
</aside>
