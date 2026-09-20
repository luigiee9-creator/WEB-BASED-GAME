<?php
// ─── Database Configuration ───────────────────────────────────────────────────
define('DB_HOST',     'localhost');
define('DB_NAME',     'ph_memory_game');
define('DB_USER',     'root');
define('DB_PASS',     '');
define('DB_CHARSET',  'utf8mb4');

// ─── Application Settings ────────────────────────────────────────────────────
define('APP_NAME',    'PH History Memory Game');
define('APP_VERSION', '1.0.0');

// Detect base URL dynamically
$protocol  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host      = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
// Walk up to find the MOR root
$scriptDir = rtrim($scriptDir, '/');
define('BASE_URL', $protocol . '://' . $host . '/MOR');

// ─── Game Settings ───────────────────────────────────────────────────────────
define('PAIRS_EASY',   4);   // 4 pairs  → 8  cards on board
define('PAIRS_MEDIUM', 8);   // 8 pairs  → 16 cards on board
define('PAIRS_HARD',   10);  // 10 pairs → 20 cards on board

// Scoring
define('SCORE_MATCH',        10);   // points per pair matched
define('SCORE_CORRECT_QUIZ', 20);   // bonus per correct quiz answer
define('SCORE_TIME_BONUS',   100);  // max time-bonus points

// Time limits per difficulty (seconds) — used for time-bonus calculation
define('TIME_LIMIT_EASY',   120);
define('TIME_LIMIT_MEDIUM', 240);
define('TIME_LIMIT_HARD',   360);

// ─── Session Settings ────────────────────────────────────────────────────────
define('SESSION_LIFETIME', 3600); // 1 hour

// ─── Error Reporting ─────────────────────────────────────────────────────────
// Set to false in production
define('DEBUG_MODE', true);

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}
