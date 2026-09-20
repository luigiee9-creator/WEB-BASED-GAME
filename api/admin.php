<?php
// api/admin.php — Admin CRUD API (JSON)
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isAdmin()) jsonError('Forbidden', 403);

$raw    = file_get_contents('php://input');
$input  = json_decode($raw, true) ?: array_merge($_GET, $_POST);
$action = sanitize($input['action'] ?? '');

switch ($action) {
    // Cards
    case 'add_card':    addCard($input);    break;
    case 'edit_card':   editCard($input);   break;
    case 'delete_card': deleteCard($input); break;
    // Questions
    case 'add_question':    addQuestion($input);    break;
    case 'edit_question':   editQuestion($input);   break;
    case 'delete_question': deleteQuestion($input); break;
    // Users
    case 'toggle_user':  toggleUser($input);  break;
    case 'promote_user': promoteUser($input); break;
    // Reports
    case 'get_report': getReport($input); break;
    default: jsonError('Unknown action');
}

function addCard(array $in): void {
    $db = getDB();
    $stmt = $db->prepare('INSERT INTO cards (title, description, icon, period_id, difficulty) VALUES (?,?,?,?,?)');
    $stmt->execute([
        sanitize($in['title']       ?? ''),
        sanitize($in['description'] ?? ''),
        sanitize($in['icon']        ?? '📜'),
        sanitizeInt($in['period_id'] ?? 0) ?: null,
        sanitize($in['difficulty']  ?? 'easy'),
    ]);
    jsonSuccess(['id' => (int)getDB()->lastInsertId()]);
}

function editCard(array $in): void {
    $db = getDB();
    $stmt = $db->prepare('UPDATE cards SET title=?, description=?, icon=?, period_id=?, difficulty=?, is_active=? WHERE id=?');
    $stmt->execute([
        sanitize($in['title']       ?? ''),
        sanitize($in['description'] ?? ''),
        sanitize($in['icon']        ?? '📜'),
        sanitizeInt($in['period_id'] ?? 0) ?: null,
        sanitize($in['difficulty']  ?? 'easy'),
        (int)($in['is_active'] ?? 1),
        sanitizeInt($in['id'] ?? 0),
    ]);
    jsonSuccess();
}

function deleteCard(array $in): void {
    $db = getDB();
    $stmt = $db->prepare('DELETE FROM cards WHERE id = ?');
    $stmt->execute([sanitizeInt($in['id'] ?? 0)]);
    jsonSuccess();
}

function addQuestion(array $in): void {
    $db = getDB();
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('INSERT INTO quiz_questions (card_id, question_text, difficulty, period_id) VALUES (?,?,?,?)');
        $stmt->execute([
            sanitizeInt($in['card_id']   ?? 0),
            sanitize($in['question_text'] ?? ''),
            sanitize($in['difficulty']    ?? 'easy'),
            sanitizeInt($in['period_id']  ?? 0) ?: null,
        ]);
        $qid = (int)$db->lastInsertId();

        $answers = $in['answers'] ?? [];
        $correct = (int)($in['correct_index'] ?? 0);
        foreach ($answers as $i => $ans) {
            $stmt = $db->prepare('INSERT INTO quiz_answers (question_id, answer_text, is_correct, display_order) VALUES (?,?,?,?)');
            $stmt->execute([$qid, sanitize($ans), ($i === $correct) ? 1 : 0, $i + 1]);
        }
        $db->commit();
        jsonSuccess(['id' => $qid]);
    } catch (Throwable $e) {
        $db->rollBack();
        jsonError('Failed to save question: ' . $e->getMessage());
    }
}

function editQuestion(array $in): void {
    $db  = getDB();
    $qid = sanitizeInt($in['id'] ?? 0);
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('UPDATE quiz_questions SET question_text=?, difficulty=? WHERE id=?');
        $stmt->execute([sanitize($in['question_text'] ?? ''), sanitize($in['difficulty'] ?? 'easy'), $qid]);

        // Replace answers
        $db->prepare('DELETE FROM quiz_answers WHERE question_id = ?')->execute([$qid]);
        $answers = $in['answers'] ?? [];
        $correct = (int)($in['correct_index'] ?? 0);
        foreach ($answers as $i => $ans) {
            $stmt = $db->prepare('INSERT INTO quiz_answers (question_id, answer_text, is_correct, display_order) VALUES (?,?,?,?)');
            $stmt->execute([$qid, sanitize($ans), ($i === $correct) ? 1 : 0, $i + 1]);
        }
        $db->commit();
        jsonSuccess();
    } catch (Throwable $e) {
        $db->rollBack();
        jsonError('Failed to update question: ' . $e->getMessage());
    }
}

function deleteQuestion(array $in): void {
    $db = getDB();
    $db->prepare('DELETE FROM quiz_questions WHERE id = ?')->execute([sanitizeInt($in['id'] ?? 0)]);
    jsonSuccess();
}

function toggleUser(array $in): void {
    $db = getDB();
    $id = sanitizeInt($in['id'] ?? 0);
    if ($id === $_SESSION['user_id']) jsonError('Cannot deactivate yourself');
    $stmt = $db->prepare('UPDATE users SET is_active = NOT is_active WHERE id = ?');
    $stmt->execute([$id]);
    jsonSuccess();
}

function promoteUser(array $in): void {
    $db = getDB();
    $id = sanitizeInt($in['id'] ?? 0);
    $stmt = $db->prepare("UPDATE users SET role = IF(role='admin','student','admin') WHERE id = ?");
    $stmt->execute([$id]);
    jsonSuccess();
}

function getReport(array $in): void {
    $db = getDB();
    $limit = min(sanitizeInt($in['limit'] ?? 100), 500);
    $stmt = $db->prepare('
        SELECT u.username, u.email,
               COUNT(pr.id)              AS sessions,
               SUM(pr.matched_pairs)     AS total_matches,
               SUM(pr.correct_answers)   AS total_correct,
               SUM(pr.total_questions)   AS total_questions,
               MAX(pr.score)             AS best_score,
               AVG(pr.score)             AS avg_score
        FROM users u
        LEFT JOIN performance_records pr ON pr.user_id = u.id
        WHERE u.role = "student"
        GROUP BY u.id
        ORDER BY best_score DESC
        LIMIT ?
    ');
    $stmt->execute([$limit]);
    jsonSuccess(['report' => $stmt->fetchAll()]);
}
