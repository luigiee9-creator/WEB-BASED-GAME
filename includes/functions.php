<?php
require_once __DIR__ . '/db.php';

// ─── Input Sanitization ──────────────────────────────────────────────────────

function sanitize(mixed $val): string {
    return htmlspecialchars(trim((string)$val), ENT_QUOTES, 'UTF-8');
}

function sanitizeInt(mixed $val): int {
    return (int) filter_var($val, FILTER_SANITIZE_NUMBER_INT);
}

// ─── JSON Response Helpers ───────────────────────────────────────────────────

function jsonSuccess(array $data = []): void {
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => true], $data));
    exit;
}

function jsonError(string $message, int $code = 400): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

// ─── Score Calculation ───────────────────────────────────────────────────────

/**
 * Calculates final score.
 *
 * @param int    $matched         Number of pairs matched
 * @param int    $correctAnswers  Number of correct quiz answers
 * @param int    $totalQuestions  Total quiz questions shown
 * @param int    $elapsedSeconds  Time taken in seconds
 * @param string $difficulty      easy | medium | hard
 */
function calculateScore(
    int $matched,
    int $correctAnswers,
    int $totalQuestions,
    int $elapsedSeconds,
    string $difficulty
): int {
    $matchPoints = $matched * SCORE_MATCH;
    $quizPoints  = $correctAnswers * SCORE_CORRECT_QUIZ;

    $limits = [
        'easy'   => TIME_LIMIT_EASY,
        'medium' => TIME_LIMIT_MEDIUM,
        'hard'   => TIME_LIMIT_HARD,
    ];
    $limit    = $limits[$difficulty] ?? TIME_LIMIT_MEDIUM;
    $timeLeft = max(0, $limit - $elapsedSeconds);
    $timeBonus = (int) round(($timeLeft / $limit) * SCORE_TIME_BONUS);

    return $matchPoints + $quizPoints + $timeBonus;
}

// ─── Difficulty Labels ───────────────────────────────────────────────────────

function difficultyLabel(string $d): string {
    return match($d) {
        'easy'   => '🟢 Easy',
        'medium' => '🟡 Medium',
        'hard'   => '🔴 Hard',
        default  => ucfirst($d),
    };
}

function difficultyPairs(string $d): int {
    return match($d) {
        'easy'   => PAIRS_EASY,
        'medium' => PAIRS_MEDIUM,
        'hard'   => PAIRS_HARD,
        default  => PAIRS_EASY,
    };
}

// ─── Formatting ──────────────────────────────────────────────────────────────

function formatTime(int $seconds): string {
    $m = intdiv($seconds, 60);
    $s = $seconds % 60;
    return sprintf('%02d:%02d', $m, $s);
}

function formatAccuracy(int $correct, int $total): string {
    if ($total === 0) return '—';
    return round(($correct / $total) * 100) . '%';
}

function formatDate(string $dateStr): string {
    $ts = strtotime($dateStr);
    return $ts ? date('M d, Y g:i A', $ts) : $dateStr;
}

// ─── Flash Messages ──────────────────────────────────────────────────────────

function flashSet(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flashGet(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function flashHtml(): string {
    $flash = flashGet();
    if (!$flash) return '';
    $type = htmlspecialchars($flash['type']);
    $msg  = htmlspecialchars($flash['message']);
    return "<div class=\"alert alert-{$type}\">{$msg}</div>";
}

// ─── Pagination ──────────────────────────────────────────────────────────────

function paginate(int $total, int $perPage, int $currentPage): array {
    $totalPages = (int) ceil($total / $perPage);
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;
    return [
        'total'       => $total,
        'per_page'    => $perPage,
        'current'     => $currentPage,
        'total_pages' => $totalPages,
        'offset'      => $offset,
        'has_prev'    => $currentPage > 1,
        'has_next'    => $currentPage < $totalPages,
    ];
}
