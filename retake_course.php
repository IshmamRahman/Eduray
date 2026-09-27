<?php
session_start();
require __DIR__ . '/db.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    header('Location: login.php');
    exit;
}

$courseId = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;

if (!$courseId) {
    header('Location: my_courses.php');
    exit;
}

// Verify user is actually enrolled in this course
$check = $conn->prepare("SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?");
$check->bind_param("ii", $userId, $courseId);
$check->execute();
$check->store_result();
if ($check->num_rows === 0) {
    header('Location: my_courses.php');
    exit;
}
$check->close();

// Delete video progress for all videos in this course
$delVideos = $conn->prepare("
    DELETE vp FROM video_progress vp
    JOIN videos v ON vp.video_id = v.id
    WHERE v.course_id = ? AND vp.user_id = ?
");
$delVideos->bind_param("ii", $courseId, $userId);
$delVideos->execute();
$delVideos->close();

// Delete quiz attempts for all quizzes in this course
$delQuizzes = $conn->prepare("
    DELETE qa FROM quiz_attempts qa
    JOIN quizzes q ON qa.quiz_id = q.id
    WHERE q.course_id = ? AND qa.user_id = ?
");
$delQuizzes->bind_param("ii", $courseId, $userId);
$delQuizzes->execute();
$delQuizzes->close();

// Redirect back to the course
header("Location: my_courses.php?course_id=$courseId&retaken=1");
exit;