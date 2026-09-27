<?php
session_start();
require __DIR__ . '/db.php';

$userId      = $_SESSION['user_id']       ?? null;
$isInstructor= !empty($_SESSION['is_instructor']);
if (!$userId || !$isInstructor) { header('Location: login.php'); exit; }

$liveId     = (int)($_POST['live_id']     ?? 0);
$courseId   = (int)($_POST['course_id']   ?? 0);
$title      = trim($_POST['title']        ?? '');
$meetLink   = trim($_POST['meet_link']    ?? '');
$scheduledAt= trim($_POST['scheduled_at'] ?? '');
$description= trim($_POST['description'] ?? '');

if (!$liveId || !$courseId || !$title || !$meetLink || !$scheduledAt) {
    header("Location: instructor_dashboard.php?cid=$courseId&live_error=1");
    exit;
}

// Verify this live class belongs to one of this instructor's courses
$check = $conn->prepare("
    SELECT lc.id FROM live_classes lc
    JOIN courses c ON c.id = lc.course_id
    WHERE lc.id = ? AND lc.course_id = ? AND c.instructor_id = ?
    LIMIT 1
");
$check->bind_param("iii", $liveId, $courseId, $userId);
$check->execute();
$check->store_result();
if ($check->num_rows === 0) {
    $check->close();
    header("Location: instructor_dashboard.php");
    exit;
}
$check->close();

$stmt = $conn->prepare("
    UPDATE live_classes
    SET title = ?, meet_link = ?, scheduled_at = ?, description = ?
    WHERE id = ? AND course_id = ?
");
$stmt->bind_param("sssiii", $title, $meetLink, $scheduledAt, $description, $liveId, $courseId);
$stmt->execute();
$stmt->close();

header("Location: instructor_dashboard.php?cid=$courseId&live_updated=1");
exit;