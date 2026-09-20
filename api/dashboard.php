<?php
// api/dashboard.php — Student dashboard stats API
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) jsonError('Not authenticated', 401);

$db     = getDB();
$userId = $_SESSION['user_id'];
$action = sanitize($_GET['action'] ?? '');

switch ($action) {
    case 'stats':   getStats($db, $userId);   break;
    case 'history': getHistory($db, $userId); break;
    default:        jsonError('Unknown action');
}

function getStats(PDO $db, int $userId): void {
    $stmt = $db->prepare('
        SELECT
            COUNT(*)                          AS sessions_played,
            COALESCE(SUM(matched_pairs),0)   AS total_matches,
            COALESCE(SUM(correct_answers),0) AS total_correct,
            COALESCE(SUM(total_questions),0) AS total_questions,
            COALESCE(MAX(score),0)           AS best_score,
            COALESCE(AVG(score),0)           AS avg_score
        FROM performance_records WHERE user_id = ?
    ');
    $stmt->execute([$userId]);
    jsonSuccess($stmt->fetch());
}

function getHistory(PDO $db, int $userId): void {
    $stmt = $db->prepare('
        SELECT pr.*, gs.started_at
        FROM performance_records pr
        JOIN game_sessions gs ON gs.id = pr.session_id
        WHERE pr.user_id = ?
        ORDER BY pr.completed_at DESC
        LIMIT 20
    ');
    $stmt->execute([$userId]);
    jsonSuccess(['sessions' => $stmt->fetchAll()]);
}
