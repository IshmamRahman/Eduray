<?php
session_start();
require __DIR__ . '/db.php';

$userId       = $_SESSION['user_id']       ?? null;
$isInstructor = !empty($_SESSION['is_instructor']);
if (!$userId || !$isInstructor) { http_response_code(403); die('Forbidden'); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: instructor_dashboard.php'); exit; }

$courseId  = (int)($_POST['course_id']  ?? 0);
$title     = trim($_POST['title']       ?? '');
$videoType = trim($_POST['video_type']  ?? '');
$embedUrl  = trim($_POST['embed_url']   ?? '');

if (!$courseId || !$title || $videoType !== 'youtube') {
    $_SESSION['error'] = 'Missing required fields.';
    header("Location: instructor_dashboard.php?cid=$courseId");
    exit;
}

// Verify this course belongs to this instructor
$chk = $conn->prepare("SELECT id FROM courses WHERE id = ? AND instructor_id = ?");
$chk->bind_param("ii", $courseId, $userId);
$chk->execute();
$chk->store_result();
if ($chk->num_rows === 0) { http_response_code(403); die('Forbidden'); }
$chk->close();

// ── Normalize YouTube URL ──────────────────────────────────────────────────
// Accept: watch?v=ID, youtu.be/ID, /embed/ID  → always store as embed URL
$videoId = '';

// Match embed URL
if (preg_match('/youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/', $embedUrl, $m)) {
    $videoId = $m[1];
}
// Match watch?v=
elseif (preg_match('/[?&]v=([a-zA-Z0-9_-]{11})/', $embedUrl, $m)) {
    $videoId = $m[1];
}
// Match youtu.be/
elseif (preg_match('/youtu\.be\/([a-zA-Z0-9_-]{11})/', $embedUrl, $m)) {
    $videoId = $m[1];
}

if (!$videoId) {
    $_SESSION['error'] = 'Invalid YouTube URL. Please paste a valid YouTube link.';
    header("Location: instructor_dashboard.php?cid=$courseId");
    exit;
}

$finalEmbedUrl = 'https://www.youtube.com/embed/' . $videoId;

// ── Handle thumbnail ───────────────────────────────────────────────────────
$thumbUrl = '';

if (isset($_FILES['thumb']) && $_FILES['thumb']['error'] === UPLOAD_ERR_OK) {
    $file         = $_FILES['thumb'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    $allowedExts  = ['jpg', 'jpeg', 'png', 'webp'];
    $ext          = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (in_array($file['type'], $allowedTypes) && in_array($ext, $allowedExts) && $file['size'] <= 2 * 1024 * 1024) {
        $uploadDir = __DIR__ . '/uploads/thumbs/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $filename = uniqid('thumb_') . '.' . $ext;
        $dest     = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $dest)) {
            $thumbUrl = 'uploads/thumbs/' . $filename;
        }
    }
}

// Fallback: use YouTube's auto-generated thumbnail
if (!$thumbUrl) {
    $thumbUrl = 'https://img.youtube.com/vi/' . $videoId . '/hqdefault.jpg';
}

// ── Get next position ──────────────────────────────────────────────────────
$posRes = $conn->query("SELECT COALESCE(MAX(position),0)+1 AS next_pos FROM videos WHERE course_id = $courseId");
$nextPos = (int)($posRes->fetch_assoc()['next_pos'] ?? 1);
$posRes->close();

// ── Insert ─────────────────────────────────────────────────────────────────
$stmt = $conn->prepare("
    INSERT INTO videos (course_id, title, embed_url, video_type, thumb_url, position)
    VALUES (?, ?, ?, 'youtube', ?, ?)
");
$stmt->bind_param("isssi", $courseId, $title, $finalEmbedUrl, $thumbUrl, $nextPos);

if ($stmt->execute()) {
    $_SESSION['success'] = 'YouTube video added successfully.';
} else {
    $_SESSION['error'] = 'Database error: ' . $stmt->error;
}
$stmt->close();

header("Location: instructor_dashboard.php?cid=$courseId");
exit;