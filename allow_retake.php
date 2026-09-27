<?php
session_start();
require __DIR__ . '/db.php';

$userId       = $_SESSION['user_id']       ?? null;
$isInstructor = !empty($_SESSION['is_instructor']);
if (!$userId || !$isInstructor) { http_response_code(403); exit; }

$studentId = (int)($_POST['student_id'] ?? 0);
$courseId  = (int)($_POST['course_id']  ?? 0);

if (!$studentId || !$courseId) {
    header('Location: instructor_dashboard.php'); exit;
}

// Verify this course belongs to this instructor
$check = $conn->prepare("SELECT id FROM courses WHERE id = ? AND instructor_id = ?");
$check->bind_param("ii", $courseId, $userId);
$check->execute();
$check->store_result();
if ($check->num_rows === 0) {
    header('Location: instructor_dashboard.php'); exit;
}
$check->close();

// Delete quiz attempts for all quizzes in this course
$conn->query("
    DELETE qa FROM quiz_attempts qa
    JOIN quizzes q ON qa.quiz_id = q.id
    WHERE q.course_id = $courseId AND qa.user_id = $studentId
");

// Delete saved answer details
$conn->query("
    DELETE qad FROM quiz_answer_details qad
    JOIN quizzes q ON qad.quiz_id = q.id
    WHERE q.course_id = $courseId AND qad.user_id = $studentId
");

// Delete video progress
$conn->query("
    DELETE vp FROM video_progress vp
    JOIN videos v ON vp.video_id = v.id
    WHERE v.course_id = $courseId AND vp.user_id = $studentId
");

header('Location: instructor_dashboard.php?cid=' . $courseId . '&retake_granted=1');
exit;