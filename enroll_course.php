<?php
session_start();
require __DIR__ . '/db.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

if (empty($_POST['course_id'])) {
    echo json_encode(['success' => false, 'message' => 'Course ID required']);
    exit;
}

$userId = intval($_SESSION['user_id']);
$courseId = intval($_POST['course_id']);

// Check if already enrolled
$check = $conn->query("SELECT id FROM enrollments WHERE user_id = $userId AND course_id = $courseId");
if ($check->num_rows > 0) {
    echo json_encode(['success' => true, 'message' => 'Already enrolled']);
    exit;
}

// Insert enrollment
$sql = "INSERT INTO enrollments (user_id, course_id, enrolled_at) VALUES ($userId, $courseId, NOW())";
if ($conn->query($sql)) {
    echo json_encode(['success' => true, 'message' => 'Enrolled successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}
?>