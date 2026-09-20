<?php
// api/game.php — Game session API (JSON)
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if (!isLoggedIn()) {
    jsonError('Not authenticated', 401);
}

$raw   = file_get_contents('php://input');
$input = json_decode($raw, true) ?: [];
$action = sanitize($input['action'] ?? $_GET['action'] ?? '');

switch ($action) {
    case 'get_question':  handleGetQuestion($input);  break;
    case 'submit_answer': handleSubmitAnswer($input); break;
    case 'end_session':   handleEndSession($input);   break;
    default: jsonError('Unknown action');
}

// ─── Get Quiz Question ────────────────────────────────────────
function handleGetQuestion(array $in): void {
    $db       = getDB();
    $cardId   = sanitizeInt($in['card_id']   ?? 0);
    $sessionId = sanitizeInt($in['session_id'] ?? 0);

    if (!$cardId || !$sessionId) jsonError('Missing parameters');

    // Verify session belongs to this user
    $stmt = $db->prepare('SELECT id FROM game_sessions WHERE id = ? AND user_id = ?');
    $stmt->execute([$sessionId, $_SESSION['user_id']]);
    if (!$stmt->fetch()) jsonError('Invalid session', 403);

    // Get a random active question for this card
    $stmt = $db->prepare('
        SELECT id, question_text, difficulty, period_id
        FROM quiz_questions
        WHERE card_id = ? AND is_active = 1
        ORDER BY RAND()
        LIMIT 1
    ');
    $stmt->execute([$cardId]);
    $question = $stmt->fetch();

    if (!$question) {
        // No question for this card — that's OK
        jsonSuccess(['question' => null, 'answers' => []]);
    }

    // Get shuffled answers
    $stmt = $db->prepare('SELECT id, answer_text FROM quiz_answers WHERE question_id = ? ORDER BY RAND()');
    $stmt->execute([$question['id']]);
    $answers = $stmt->fetchAll();

    // Record the match
    $stmt = $db->prepare('INSERT INTO session_matches (session_id, card_id, question_id) VALUES (?, ?, ?)');
    $stmt->execute([$sessionId, $cardId, $question['id']]);

    jsonSuccess(['question' => $question, 'answers' => $answers]);
}

// ─── Submit Answer ────────────────────────────────────────────
function handleSubmitAnswer(array $in): void {
    $db         = getDB();
    $sessionId  = sanitizeInt($in['session_id']  ?? 0);
    $questionId = sanitizeInt($in['question_id'] ?? 0);
    $answerId   = sanitizeInt($in['answer_id']   ?? 0);

    if (!$sessionId || !$questionId || !$answerId) jsonError('Missing parameters');

    // Verify session
    $stmt = $db->prepare('SELECT id FROM game_sessions WHERE id = ? AND user_id = ?');
    $stmt->execute([$sessionId, $_SESSION['user_id']]);
    if (!$stmt->fetch()) jsonError('Invalid session', 403);

    // Check correctness
    $stmt = $db->prepare('SELECT is_correct FROM quiz_answers WHERE id = ? AND question_id = ?');
    $stmt->execute([$answerId, $questionId]);
    $chosen = $stmt->fetch();
    if (!$chosen) jsonError('Invalid answer');

    $isCorrect = (int)$chosen['is_correct'];

    // Get correct answer info
    $stmt = $db->prepare('SELECT id, answer_text FROM quiz_answers WHERE question_id = ? AND is_correct = 1 LIMIT 1');
    $stmt->execute([$questionId]);
    $correct = $stmt->fetch();

    // Get card description as explanation
    $stmt = $db->prepare('
        SELECT c.description FROM quiz_questions qq
        JOIN cards c ON c.id = qq.card_id
        WHERE qq.id = ? LIMIT 1
    ');
    $stmt->execute([$questionId]);
    $cardRow = $stmt->fetch();

    // Record response
    $stmt = $db->prepare('
        INSERT INTO session_quiz_responses (session_id, question_id, answer_id, is_correct)
        VALUES (?, ?, ?, ?)
    ');
    $stmt->execute([$sessionId, $questionId, $answerId, $isCorrect]);

    jsonSuccess([
        'is_correct'          => $isCorrect,
        'correct_answer_id'   => (int)($correct['id'] ?? 0),
        'correct_answer_text' => $correct['answer_text'] ?? '',
        'explanation'         => $cardRow['description']  ?? '',
    ]);
}

// ─── End Session ──────────────────────────────────────────────
function handleEndSession(array $in): void {
    $db            = getDB();
    $sessionId     = sanitizeInt($in['session_id']      ?? 0);
    $totalTime     = sanitizeInt($in['total_time']       ?? 0);
    $rawScore      = sanitizeInt($in['score']            ?? 0);
    $correctAns    = sanitizeInt($in['correct_answers']  ?? 0);
    $totalQuestions = sanitizeInt($in['total_questions'] ?? 0);
    $matchedPairs  = sanitizeInt($in['matched_pairs']    ?? 0);

    if (!$sessionId) jsonError('Missing session_id');

    // Verify session
    $stmt = $db->prepare('SELECT id, difficulty, card_ids FROM game_sessions WHERE id = ? AND user_id = ?');
    $stmt->execute([$sessionId, $_SESSION['user_id']]);
    $session = $stmt->fetch();
    if (!$session) jsonError('Invalid session', 403);

    if ($session['id'] === null) jsonError('Session not found', 404);

    $difficulty = $session['difficulty'];
    $cardIds    = json_decode($session['card_ids'], true) ?: [];
    $totalPairs = count($cardIds);

    // Calculate time bonus
    $limits = ['easy' => TIME_LIMIT_EASY, 'medium' => TIME_LIMIT_MEDIUM, 'hard' => TIME_LIMIT_HARD];
    $limit  = $limits[$difficulty] ?? TIME_LIMIT_MEDIUM;
    $timeBonus = (int) round(max(0, ($limit - $totalTime) / $limit) * SCORE_TIME_BONUS);
    $finalScore = $rawScore + $timeBonus;

    // Update session
    $stmt = $db->prepare('
        UPDATE game_sessions
        SET is_completed = 1, completed_at = NOW(), total_time_seconds = ?
        WHERE id = ?
    ');
    $stmt->execute([$totalTime, $sessionId]);

    // Insert performance record
    $stmt = $db->prepare('
        INSERT INTO performance_records
            (user_id, session_id, difficulty, total_pairs, matched_pairs, correct_answers, total_questions, score, time_seconds)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $_SESSION['user_id'], $sessionId, $difficulty,
        $totalPairs, $matchedPairs,
        $correctAns, $totalQuestions,
        $finalScore, $totalTime,
    ]);

    jsonSuccess(['final_score' => $finalScore, 'time_bonus' => $timeBonus]);
}
