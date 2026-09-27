<?php
session_start();
require __DIR__ . '/db.php';

$userId       = $_SESSION['user_id']       ?? null;
$isInstructor = !empty($_SESSION['is_instructor']);
if (!$userId || !$isInstructor) { http_response_code(403); exit; }

$quizId = (int)($_GET['quiz_id'] ?? 0);
if (!$quizId) { echo json_encode(['error' => 'Invalid quiz']); exit; }

// Verify quiz belongs to this instructor
$check = $conn->prepare("SELECT q.id, q.title, c.instructor_id FROM quizzes q JOIN courses c ON c.id = q.course_id WHERE q.id = ?");
$check->bind_param("i", $quizId);
$check->execute();
$quizInfo = $check->get_result()->fetch_assoc();
$check->close();
if (!$quizInfo || (int)$quizInfo['instructor_id'] !== (int)$userId) {
    echo json_encode(['error' => 'Unauthorized']); exit;
}

// Fetch questions
$qRes = $conn->prepare("SELECT id, question, option_a, option_b, option_c, option_d, correct_option FROM quiz_questions WHERE quiz_id = ? ORDER BY id");
$qRes->bind_param("i", $quizId);
$qRes->execute();
$questions = $qRes->get_result()->fetch_all(MYSQLI_ASSOC);
$qRes->close();

// Fetch all student attempts
$aRes = $conn->prepare("
    SELECT qa.user_id, qa.score, qa.total_questions, qa.passed, qa.attempted_at,
           u.name, u.email
    FROM quiz_attempts qa
    JOIN users u ON u.id = qa.user_id
    WHERE qa.quiz_id = ?
    ORDER BY qa.attempted_at DESC
");
$aRes->bind_param("i", $quizId);
$aRes->execute();
$attempts = $aRes->get_result()->fetch_all(MYSQLI_ASSOC);
$aRes->close();

// Fetch per-question selected answers
$savedAnswers = [];
if (!empty($attempts)) {
    $userIds = implode(',', array_map('intval', array_column($attempts, 'user_id')));
    $adRes = $conn->query("SELECT user_id, question_id, selected_option FROM quiz_answer_details WHERE quiz_id = $quizId AND user_id IN ($userIds)");
    while ($row = $adRes->fetch_assoc()) {
        $savedAnswers[$row['user_id']][$row['question_id']] = $row['selected_option'];
    }
    $adRes->close();
}

header('Content-Type: application/json');
echo json_encode([
    'quiz_title' => $quizInfo['title'],
    'questions'  => $questions,
    'attempts'   => $attempts,
    'answers'    => $savedAnswers,
]);