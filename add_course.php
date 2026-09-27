<?php
session_start();
require __DIR__ . '/db.php';

// ── Access: admin OR instructor ───────────────────────────────────────────────
$isAdmin      = !empty($_SESSION['is_admin']);
$isInstructor = !empty($_SESSION['is_instructor']);
$userId       = $_SESSION['user_id'] ?? null;

if (!$userId || (!$isAdmin && !$isInstructor)) {
    http_response_code(403);
    die('Forbidden: Admins and Instructors only');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . ($isInstructor ? 'instructor_dashboard.php' : 'admin_dashboard.php'));
    exit;
}

$title       = trim($_POST['title']        ?? '');
$deadline    = trim($_POST['deadline']     ?? '') ?: null;  // optional
$instructorId = $isInstructor
    ? (int)$userId                                           // always the logged-in instructor
    : (isset($_POST['instructor_id']) ? (int)$_POST['instructor_id'] : null); // admin can pass one

if (!$title) {
    header('Location: ' . ($isInstructor ? 'instructor_dashboard.php?s=newcourse&err=notitle' : 'admin_dashboard.php?s=addcourse&err=notitle'));
    exit;
}

// ── Thumbnail upload ──────────────────────────────────────────────────────────
if (!isset($_FILES['thumbnail']) || $_FILES['thumbnail']['error'] !== UPLOAD_ERR_OK) {
    header('Location: ' . ($isInstructor ? 'instructor_dashboard.php?s=newcourse&err=nothumb' : 'admin_dashboard.php?s=addcourse&err=nothumb'));
    exit;
}

$file         = $_FILES['thumbnail'];
$allowedTypes = ['image/jpeg','image/png','image/webp'];
if (!in_array($file['type'], $allowedTypes) || $file['size'] > 2 * 1024 * 1024) {
    header('Location: ' . ($isInstructor ? 'instructor_dashboard.php?s=newcourse&err=badfile' : 'admin_dashboard.php?s=addcourse&err=badfile'));
    exit;
}

$ext       = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename  = uniqid('course_') . '.' . $ext;
$uploadDir = __DIR__ . '/uploads/covers/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
    header('Location: ' . ($isInstructor ? 'instructor_dashboard.php?s=newcourse&err=upload' : 'admin_dashboard.php?s=addcourse&err=upload'));
    exit;
}

$thumbnailPath = 'uploads/covers/' . $filename;

// ── Slug ──────────────────────────────────────────────────────────────────────
$slug = trim(strtolower(preg_replace('/[^a-z0-9]+/i', '-', $title)), '-');

// ── Course code: EDU-XXXX (unique 6 random uppercase chars) ──────────────────
do {
    $code = 'EDU-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    $chk  = $conn->prepare("SELECT id FROM courses WHERE course_code = ?");
    $chk->bind_param("s", $code);
    $chk->execute();
    $chk->store_result();
    $exists = $chk->num_rows > 0;
    $chk->close();
} while ($exists);

// ── Insert ────────────────────────────────────────────────────────────────────
$stmt = $conn->prepare("
    INSERT INTO courses (title, slug, thumbnail_url, instructor_id, course_code, deadline)
    VALUES (?, ?, ?, ?, ?, ?)
");
$stmt->bind_param("sssiss", $title, $slug, $thumbnailPath, $instructorId, $code, $deadline);
$stmt->execute();
$newCourseId = $conn->insert_id;
$stmt->close();

// ── Redirect back to the right dashboard ─────────────────────────────────────
if ($isInstructor) {
    header("Location: instructor_dashboard.php?cid={$newCourseId}&created=1");
} else {
    header("Location: admin_dashboard.php?cid={$newCourseId}");
}
exit;