<?php
session_start();
require __DIR__ . '/db.php';

$userId       = $_SESSION['user_id']       ?? null;
$isInstructor = !empty($_SESSION['is_instructor']);
$isAdmin      = !empty($_SESSION['is_admin']);

if (!$userId || (!$isInstructor && !$isAdmin)) {
    http_response_code(403);
    die('Forbidden');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: instructor_dashboard.php');
    exit;
}

$courseId = (int)($_POST['course_id'] ?? 0);
if (!$courseId) {
    header('Location: instructor_dashboard.php');
    exit;
}

// Verify this course belongs to this instructor (admins can update any)
if (!$isAdmin) {
    $chk = $conn->prepare("SELECT id FROM courses WHERE id = ? AND instructor_id = ?");
    $chk->bind_param("ii", $courseId, $userId);
    $chk->execute();
    $chk->store_result();
    if ($chk->num_rows === 0) {
        http_response_code(403);
        die('Forbidden: This course does not belong to you.');
    }
    $chk->close();
}

// Handle file upload
if (!isset($_FILES['cover']) || $_FILES['cover']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['error'] = 'Please select an image file to upload.';
    header("Location: instructor_dashboard.php?cid=$courseId");
    exit;
}

$file         = $_FILES['cover'];
$allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
$allowedExts  = ['jpg', 'jpeg', 'png', 'webp'];
$ext          = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($file['type'], $allowedTypes) || !in_array($ext, $allowedExts)) {
    $_SESSION['error'] = 'Invalid file type. Only JPG, PNG, WebP allowed.';
    header("Location: instructor_dashboard.php?cid=$courseId");
    exit;
}

if ($file['size'] > 2 * 1024 * 1024) {
    $_SESSION['error'] = 'File too large. Max 2MB allowed.';
    header("Location: instructor_dashboard.php?cid=$courseId");
    exit;
}

$uploadDir = __DIR__ . '/uploads/covers/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$filename    = uniqid('cover_') . '.' . $ext;
$destination = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    $_SESSION['error'] = 'Failed to save file. Check folder permissions.';
    header("Location: instructor_dashboard.php?cid=$courseId");
    exit;
}

$newPath = 'uploads/covers/' . $filename;

$upd = $conn->prepare("UPDATE courses SET thumbnail_url = ? WHERE id = ?");
$upd->bind_param("si", $newPath, $courseId);
$upd->execute();
$upd->close();

header("Location: instructor_dashboard.php?cid=$courseId&cover_updated=1");
exit;