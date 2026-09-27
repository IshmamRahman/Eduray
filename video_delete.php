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
    die('Forbidden');
}

$userId = (int)$_SESSION['user_id'];
$id     = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    header('Location: ' . ($isInstructor && !$isAdmin ? 'instructor_dashboard.php' : 'courses.php'));
    exit;
}

// Get video info first
$stmt = $conn->prepare('SELECT v.video_type, v.video_path, v.thumb_url, v.course_id FROM videos v JOIN courses c ON c.id = v.course_id WHERE v.id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$video = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$video) {
    header('Location: ' . ($isInstructor && !$isAdmin ? 'instructor_dashboard.php' : 'courses.php'));
    exit;
}

// Ownership check for instructors
if ($isInstructor && !$isAdmin) {
    $chk = $conn->prepare("SELECT id FROM courses WHERE id=? AND instructor_id=?");
    $chk->bind_param("ii", $video['course_id'], $userId);
    $chk->execute();
    $chk->store_result();
    if ($chk->num_rows === 0) {
        http_response_code(403);
        die('Forbidden: This video does not belong to your course.');
    }
    $chk->close();
}

$redirectBack = ($isInstructor && !$isAdmin)
    ? 'instructor_dashboard.php?cid=' . $video['course_id']
    : 'courses.php';

// Delete video file if it's an upload
if ($video['video_type'] === 'upload' && !empty($video['video_path'])) {
    $videoFile = __DIR__ . '/' . $video['video_path'];
    if (file_exists($videoFile)) {
        unlink($videoFile);
    }
}

// Delete thumbnail
if (!empty($video['thumb_url'])) {
    $thumbFile = __DIR__ . '/' . $video['thumb_url'];
    if (file_exists($thumbFile)) {
        unlink($thumbFile);
    }
}

// Delete from database
$stmt = $conn->prepare('DELETE FROM videos WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->close();

header('Location: ' . $redirectBack);
exit;