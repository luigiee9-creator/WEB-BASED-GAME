<?php
// index.php — Landing page / role router
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
    if (isAdmin()) {
        header('Location: ' . BASE_URL . '/admin/index.php');
    } else {
        header('Location: ' . BASE_URL . '/dashboard.php');
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>PH History Memory Card Game — Learn Philippine History Through Play</title>
  <meta name="description" content="An interactive memory card game that teaches Philippine history through matching pairs and quiz questions. Covering Spanish colonization, the Revolution, American period, World War II, and contemporary events.">
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
      <li><a href="login.php"    class="nav-link">Log In</a></li>
      <li><a href="register.php" class="nav-link btn-nav">Play Now</a></li>
    </ul>
  </div>
</nav>

<!-- Hero -->
<section class="hero">
  <div class="hero-badge">🏆 Educational Game for Filipino Students</div>
  <h1>Learn <span>Philippine History</span><br>One Card at a Time</h1>
  <p class="lead">
    Flip cards, find matching pairs, and answer quiz questions covering 5 eras
    of Philippine history — Spanish Colonization, the Revolution, the American Period,
    World War II, and the Contemporary era.
  </p>
  <div class="hero-actions">
    <a href="register.php" class="btn btn-primary btn-xl">🎮 Start Playing Free</a>
    <a href="login.php"    class="btn btn-secondary btn-xl">Log In</a>
  </div>
</section>

<!-- Feature Strip -->
<section style="padding: 0 1.5rem 4rem;">
  <div class="container">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1.25rem;">
      <?php
      $features = [
        ['⚡','Instant Quiz Feedback','Answer a question after every matched pair and see if you got it right immediately'],
        ['📊','Track Your Progress','Dashboard shows accuracy, best scores, and session history'],
        ['🎯','3 Difficulty Levels','Easy (4 pairs), Medium (8 pairs), or Hard (10 pairs)'],
        ['📚','58 History Topics','Spanning Spanish colonization, Revolution, American period, WWII & Contemporary'],
        ['👨‍💼','Admin Panel','Admins can manage content, view student stats, and export reports'],
        ['📱','Play on Any Device','Fully responsive — works on phones, tablets, and desktops'],
      ];
      foreach ($features as [$icon, $title, $desc]) {
        echo "<div class='card' style='text-align:center;'>
          <div style='font-size:2rem;margin-bottom:.75rem;'>{$icon}</div>
          <h4 style='margin-bottom:.4rem;color:var(--gold-400);'>{$title}</h4>
          <p style='font-size:.82rem;'>{$desc}</p>
        </div>";
      }
      ?>
    </div>
  </div>
</section>

<!-- Historical Periods -->
<section style="padding: 0 1.5rem 5rem;">
  <div class="container container-md">
    <h2 class="text-center mb-3">5 Historical Periods</h2>
    <?php
    $periods = [
      ['⚓','Spanish Colonization','1565 – 1898','Over 300 years of colonial rule, Galleon Trade, friar power, and resistance movements','#8B4513'],
      ['✊','Philippine Revolution','1896 – 1898','Katipunan, Andres Bonifacio, Jose Rizal, and the birth of Philippine independence','#DC143C'],
      ['🦅','American Period','1898 – 1946','Treaty of Paris, Philippine-American War, Commonwealth era, and the road to independence','#1E3A8A'],
      ['⚔️','World War II','1941 – 1945','Japanese occupation, Bataan Death March, MacArthur, and the liberation of the Philippines','#556B2F'],
      ['🇵🇭','Contemporary Period','1946 – Present','Independence, Martial Law, People Power Revolution, and modern Philippine democracy','#B8860B'],
    ];
    foreach ($periods as [$icon, $name, $years, $desc, $color]) {
      echo "<div class='card' style='display:flex;gap:1.25rem;align-items:flex-start;margin-bottom:.75rem;'>
        <div style='font-size:2rem;flex-shrink:0;'>{$icon}</div>
        <div>
          <div style='display:flex;align-items:center;gap:.5rem;margin-bottom:.25rem;'>
            <h4 style='color:var(--text-primary);'>{$name}</h4>
            <span class='badge badge-easy' style='background:transparent;border:1px solid;border-color:rgba(255,255,255,.15);color:var(--text-muted);'>{$years}</span>
          </div>
          <p style='font-size:.85rem;'>{$desc}</p>
        </div>
      </div>";
    }
    ?>
  </div>
</section>

<!-- CTA -->
<section style="text-align:center;padding:3rem 1.5rem 5rem;">
  <h2 style="margin-bottom:1rem;">Ready to Test Your Knowledge?</h2>
  <p style="color:var(--text-muted);max-width:400px;margin:0 auto 2rem;">
    Create a free account and start playing in under 60 seconds.
  </p>
  <a href="register.php" class="btn btn-primary btn-xl animate-pulse">
    🎮 Create Free Account
  </a>
</section>

<footer style="border-top:1px solid var(--glass-border);padding:1.5rem;text-align:center;color:var(--text-muted);font-size:.8rem;">
  Philippine History Memory Card Game &copy; <?php echo date('Y'); ?> — Built for Filipino Students
</footer>

<script src="assets/js/main.js"></script>
</body>
</html>
