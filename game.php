<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();

$difficulty = sanitize($_GET['difficulty'] ?? 'easy');
if (!in_array($difficulty, ['easy', 'medium', 'hard'])) {
    $difficulty = 'easy';
}

// ── Start game session via API (pre-load) ─────────────────────
// We call the backend directly here to embed data into the page
$db     = getDB();
$userId = $_SESSION['user_id'];
$pairs  = difficultyPairs($difficulty);

// Select random cards for this difficulty
$stmt = $db->prepare('SELECT id, title, description, icon, period_id FROM cards WHERE difficulty = ? AND is_active = 1 ORDER BY RAND() LIMIT ?');
$stmt->execute([$difficulty, $pairs]);
$cards = $stmt->fetchAll();

if (count($cards) < $pairs) {
    // Fallback: fill from any difficulty
    $have  = array_column($cards, 'id');
    $needs = $pairs - count($cards);
    $placeholders = implode(',', array_fill(0, count($have) ?: 1, '?'));
    $fallStmt = $db->prepare("SELECT id, title, description, icon, period_id FROM cards WHERE is_active = 1 AND id NOT IN ($placeholders) ORDER BY RAND() LIMIT ?");
    $fallStmt->execute(array_merge($have ?: [0], [$needs]));
    $cards = array_merge($cards, $fallStmt->fetchAll());
}

if (empty($cards)) {
    die('<p style="color:white;padding:2rem;">No cards found. Please run the database seeder.</p>');
}

$cardIds = json_encode(array_column($cards, 'id'));

// Create game session in DB
$stmt = $db->prepare('INSERT INTO game_sessions (user_id, difficulty, card_ids) VALUES (?, ?, ?)');
$stmt->execute([$userId, $difficulty, $cardIds]);
$sessionId = (int)$db->lastInsertId();

$timeLimits = ['easy' => 120, 'medium' => 240, 'hard' => 360];
$timeLimit  = $timeLimits[$difficulty];

$cardsJson = json_encode($cards, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= ucfirst($difficulty) ?> Game — PH History Memory Card Game</title>
  <link rel="stylesheet" href="assets/css/main.css">
  <link rel="stylesheet" href="assets/css/game.css">
</head>
<body>
<div class="game-page">

  <!-- HUD -->
  <div class="game-hud">
    <div class="hud-left">
      <div class="hud-stat">
        <span class="hud-val" id="hud-score">0</span>
        <span class="hud-lbl">Score</span>
      </div>
      <div class="hud-stat">
        <span class="hud-val" id="hud-matches">0/<?= count($cards) ?></span>
        <span class="hud-lbl">Pairs</span>
      </div>
    </div>

    <div class="hud-center">
      <div class="hud-progress-wrap">
        <div class="hud-progress-label" id="hud-progress-label">0 of <?= count($cards) ?> pairs found</div>
        <div class="progress" style="width:220px;">
          <div class="progress-bar" id="hud-progress-bar" style="width:0%"></div>
        </div>
      </div>
      <div class="hud-timer" id="hud-timer">00:00</div>
    </div>

    <div class="hud-right">
      <span class="badge badge-<?= $difficulty ?>"><?= ucfirst($difficulty) ?></span>
      <a href="dashboard.php" class="btn btn-secondary btn-sm"
         onclick="return confirm('Exit game? Progress will not be saved.')">✕ Exit</a>
    </div>
  </div>

  <!-- Card Board -->
  <div class="game-board">
    <div id="card-grid" class="card-grid"></div>
  </div>

</div>

<!-- Quiz Modal -->
<div id="quiz-modal" class="modal-overlay" style="display:none;">
  <div class="modal-box">
    <div class="modal-match-header">
      <span class="modal-match-icon">🎯</span>
      <div>
        <div class="modal-match-title">Match Found! Answer this question:</div>
        <div class="modal-match-card" id="qz-match-card"></div>
      </div>
    </div>

    <p class="quiz-question" id="qz-question"></p>

    <div class="quiz-answers" id="qz-answers"></div>

    <div class="quiz-feedback" id="qz-feedback"></div>

    <button class="btn-continue" id="btn-continue">Continue Playing →</button>
  </div>
</div>

<!-- End Game Modal -->
<div id="end-modal" class="modal-overlay" style="display:none;">
  <div class="modal-box">
    <div class="game-complete">
      <div class="trophy">🏆</div>
      <h2>Game Complete!</h2>
      <p style="color:var(--text-muted);margin-bottom:1rem;">
        You matched all <?= count($cards) ?> pairs in <?= ucfirst($difficulty) ?> mode.
      </p>

      <div class="final-score" id="end-score">0</div>
      <div style="font-size:.8rem;color:var(--text-muted);margin-bottom:1rem;">FINAL SCORE</div>

      <div class="score-breakdown">
        <div class="score-item"><div class="si-val" id="end-matches">—</div><div class="si-lbl">Pairs Matched</div></div>
        <div class="score-item"><div class="si-val" id="end-correct">—</div><div class="si-lbl">Correct Answers</div></div>
        <div class="score-item"><div class="si-val" id="end-accuracy">—</div><div class="si-lbl">Quiz Accuracy</div></div>
        <div class="score-item"><div class="si-val" id="end-time">—</div><div class="si-lbl">Time Taken</div></div>
        <div class="score-item" style="grid-column:1/-1;">
          <div class="si-val text-success" id="end-timebonus">+0</div>
          <div class="si-lbl">Time Bonus</div>
        </div>
      </div>

      <div style="display:flex;gap:.75rem;margin-top:1.5rem;justify-content:center;flex-wrap:wrap;">
        <a href="game.php?difficulty=<?= $difficulty ?>" class="btn btn-primary">🔄 Play Again</a>
        <a href="dashboard.php?completed=1"              class="btn btn-secondary">📊 Dashboard</a>
      </div>
    </div>
  </div>
</div>

<script src="assets/js/game.js"></script>
<script>
  // Bootstrap the game
  const GAME_DATA = {
    cards:      <?= $cardsJson ?>,
    sessionId:  <?= $sessionId ?>,
    difficulty: '<?= $difficulty ?>',
    pairsCount: <?= count($cards) ?>,
    timeLimit:  <?= $timeLimit ?>
  };

  document.addEventListener('DOMContentLoaded', () => {
    game = new MemoryGame(GAME_DATA);
    game.init();
  });
</script>
</body>
</html>
