<?php
session_start();
require __DIR__ . '/db.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$isAdmin      = !empty($_SESSION['is_admin']);
$isInstructor = !empty($_SESSION['is_instructor']);

if (!$isAdmin && !$isInstructor) {
    http_response_code(403);
    die('Forbidden: Admins and Instructors only');
}

$course_id   = (int)($_POST['course_id'] ?? 0);
$title       = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$time_limit  = max(0, (int)($_POST['time_limit'] ?? 0));

if (!$course_id || !$title) {
    die('Course and title are required.');
}

if ($isInstructor && !$isAdmin) {
    $chk = $conn->prepare("SELECT id FROM courses WHERE id = ? AND instructor_id = ?");
    $chk->bind_param("ii", $course_id, $_SESSION['user_id']);
    $chk->execute();
    $chk->store_result();
    if ($chk->num_rows === 0) {
        http_response_code(403);
        die('Forbidden: This is not your course.');
    }
    $chk->close();
}

$stmt = $conn->prepare("INSERT INTO quizzes (course_id, title, description, time_limit) VALUES (?, ?, ?, ?)");
$stmt->bind_param('issi', $course_id, $title, $description, $time_limit);
$stmt->execute();
$stmt->close();

header('Location: ' . ($isInstructor ? 'instructor_dashboard.php?cid=' . $course_id : 'courses.php'));
exit;