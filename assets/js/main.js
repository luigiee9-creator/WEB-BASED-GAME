// ─── Main JS: shared utilities ───────────────────────────────
'use strict';

// Fade in all .animate-slide elements on load
document.addEventListener('DOMContentLoaded', () => {
  // Auto-dismiss alerts after 4s
  const alerts = document.querySelectorAll('.alert');
  alerts.forEach(el => {
    setTimeout(() => {
      el.style.transition = 'opacity 0.5s ease';
      el.style.opacity = '0';
      setTimeout(() => el.remove(), 500);
    }, 4000);
  });
});

// ─── Difficulty Card Click ────────────────────────────────────
function selectDifficulty(level) {
  window.location.href = `game.php?difficulty=${level}`;
}

// ─── Confirm Dialog ───────────────────────────────────────────
function confirmAction(message, callback) {
  if (window.confirm(message)) callback();
}
