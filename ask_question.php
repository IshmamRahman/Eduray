<?php
session_start();
require __DIR__ . '/db.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) { header('Location: login.php'); exit; }

$courseId  = (int)($_POST['course_id']  ?? 0);
$videoId   = !empty($_POST['video_id']) ? (int)$_POST['video_id'] : null;
$question  = trim($_POST['question']    ?? '');
$redirectBack = $_POST['redirect_back'] ?? '';

if (!$courseId || !$question) {
    header('Location: my_courses.php');
    exit;
}

// Verify student is enrolled
$chk = $conn->prepare("SELECT id FROM enrollments WHERE user_id=? AND course_id=?");
$chk->bind_param("ii", $userId, $courseId);
$chk->execute();
$chk->store_result();
if ($chk->num_rows === 0) {
    header('Location: my_courses.php');
    exit;
}
$chk->close();

$ins = $conn->prepare("INSERT INTO course_questions (course_id, video_id, student_id, question) VALUES (?,?,?,?)");
$ins->bind_param("iiis", $courseId, $videoId, $userId, $question);
$ins->execute();
$ins->close();

// Redirect back to where they came from
if ($redirectBack) {
    header('Location: ' . $redirectBack . '&question_sent=1');
} elseif ($videoId) {
    header("Location: watch.php?video_id=$videoId&question_sent=1");
} else {
    header("Location: my_courses.php?course_id=$courseId&question_sent=1");
}
exit;