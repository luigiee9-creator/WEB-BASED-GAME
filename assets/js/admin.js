// ─── Admin Panel JavaScript ───────────────────────────────────
'use strict';

// ── Search / Filter ──────────────────────────────────────────
function initSearch(inputId, tableId, columnIndexes) {
  const input = document.getElementById(inputId);
  const tbody = document.querySelector(`#${tableId} tbody`);
  if (!input || !tbody) return;

  input.addEventListener('input', () => {
    const q = input.value.toLowerCase().trim();
    let count = 0;
    Array.from(tbody.rows).forEach(row => {
      const text = columnIndexes
        .map(i => (row.cells[i]?.textContent || '').toLowerCase())
        .join(' ');
      const show = !q || text.includes(q);
      row.style.display = show ? '' : 'none';
      if (show) count++;
    });
    const rc = document.getElementById('results-count');
    if (rc) rc.textContent = q ? `${count} result(s)` : '';
  });
}

function initFilter(selectId, tableId, columnIndex) {
  const sel   = document.getElementById(selectId);
  const tbody = document.querySelector(`#${tableId} tbody`);
  if (!sel || !tbody) return;

  sel.addEventListener('change', () => {
    const val = sel.value.toLowerCase();
    Array.from(tbody.rows).forEach(row => {
      const cell = (row.cells[columnIndex]?.textContent || '').toLowerCase();
      row.style.display = (!val || cell.includes(val)) ? '' : 'none';
    });
  });
}

// ── Modal Helpers ─────────────────────────────────────────────
function openModal(id) {
  const m = document.getElementById(id);
  if (m) { m.classList.remove('hidden'); }
}
function closeModal(id) {
  const m = document.getElementById(id);
  if (m) { m.classList.add('hidden'); }
}

// Close modal on backdrop click
document.addEventListener('click', e => {
  if (e.target.classList.contains('modal-backdrop')) {
    e.target.classList.add('hidden');
  }
});
// Close on Escape
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-backdrop:not(.hidden)').forEach(m => m.classList.add('hidden'));
  }
});

// ── Delete Confirmation ───────────────────────────────────────
let _pendingDeleteUrl = null;

function confirmDelete(url, label) {
  _pendingDeleteUrl = url;
  const lbl = document.getElementById('confirm-label');
  if (lbl) lbl.textContent = label || 'this item';
  openModal('confirm-modal');
}

document.addEventListener('DOMContentLoaded', () => {
  const confirmBtn = document.getElementById('confirm-delete-btn');
  if (confirmBtn) {
    confirmBtn.addEventListener('click', () => {
      if (_pendingDeleteUrl) window.location.href = _pendingDeleteUrl;
    });
  }

  // Sidebar toggle (mobile)
  const toggleBtn = document.getElementById('sidebar-toggle');
  const sidebar   = document.getElementById('admin-sidebar');
  if (toggleBtn && sidebar) {
    toggleBtn.addEventListener('click', () => sidebar.classList.toggle('open'));
    document.addEventListener('click', e => {
      if (!sidebar.contains(e.target) && e.target !== toggleBtn) {
        sidebar.classList.remove('open');
      }
    });
  }
});

// ── Correct-answer radio for question form ─────────────────────
function setCorrectAnswer(index) {
  document.querySelectorAll('.answer-field').forEach((f, i) => {
    f.classList.toggle('correct-field', i === index);
  });
}

// ── Export to CSV helper (client-side fallback) ───────────────
function downloadCSV(csvContent, filename) {
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const url  = URL.createObjectURL(blob);
  const a    = document.createElement('a');
  a.href     = url;
  a.download = filename;
  a.click();
  URL.revokeObjectURL(url);
}
