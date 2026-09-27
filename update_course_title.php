<?php
session_start();
require __DIR__ . '/db.php';

$userId      = $_SESSION['user_id'] ?? null;
$isInstructor = !empty($_SESSION['is_instructor']);

if (!$userId || !$isInstructor) {
    header('Location: login.php'); exit;
}

$courseId = (int)($_POST['course_id'] ?? 0);
$title    = trim($_POST['title'] ?? '');

if (!$courseId || $title === '') {
    header('Location: instructor_dashboard.php'); exit;
}

// Verify the course belongs to this instructor
$check = $conn->prepare("SELECT id FROM courses WHERE id = ? AND instructor_id = ?");
$check->bind_param("ii", $courseId, $userId);
$check->execute();
$check->store_result();
if ($check->num_rows === 0) {
    $check->close();
    header('Location: instructor_dashboard.php'); exit;
}
$check->close();

// Update the title
$stmt = $conn->prepare("UPDATE courses SET title = ? WHERE id = ? AND instructor_id = ?");
$stmt->bind_param("sii", $title, $courseId, $userId);
$stmt->execute();
$stmt->close();

header("Location: instructor_dashboard.php?cid={$courseId}&title_updated=1");
exit;