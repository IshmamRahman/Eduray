<?php
session_start();
require __DIR__ . '/db.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in.']);
    exit;
}

$isAdmin      = !empty($_SESSION['is_admin']);
$isInstructor = !empty($_SESSION['is_instructor']);

if (!$isAdmin && !$isInstructor) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admins and Instructors only.']);
    exit;
}

$course_id  = (int)($_POST['course_id'] ?? 0);
$title      = trim($_POST['title'] ?? '');
$duration   = !empty($_POST['duration']) ? (int)$_POST['duration'] : null;
$video_type = 'upload';

// Instructors can only upload to their own courses
if ($isInstructor && !$isAdmin) {
    $chk = $conn->prepare("SELECT id FROM courses WHERE id = ? AND instructor_id = ?");
    $chk->bind_param("ii", $course_id, $_SESSION['user_id']);
    $chk->execute();
    $chk->store_result();
    if ($chk->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Forbidden: This is not your course.']);
        exit;
    }
    $chk->close();
}

if ($course_id <= 0 || $title === '') {
    echo json_encode(['success' => false, 'message' => 'Course and title are required.']);
    exit;
}

if (empty($_FILES['video_file']['name'])) {
    echo json_encode(['success' => false, 'message' => 'Video file is required.']);
    exit;
}

if (empty($_FILES['thumb']['name'])) {
    echo json_encode(['success' => false, 'message' => 'Thumbnail is required.']);
    exit;
}

// Video validation
$videoFile       = $_FILES['video_file'];
$maxVideoSize    = 500 * 1024 * 1024;
$allowedVideoExt = ['mp4', 'webm', 'mov', 'mkv'];
$allowedVideoMime= ['video/mp4', 'video/webm', 'video/quicktime', 'video/x-matroska'];

$videoExt  = strtolower(pathinfo($videoFile['name'], PATHINFO_EXTENSION));
$videoSize = $videoFile['size'];

if (!in_array($videoExt, $allowedVideoExt)) {
    echo json_encode(['success' => false, 'message' => 'Invalid video format. Allowed: MP4, WebM, MOV, MKV']);
    exit;
}
if ($videoSize <= 0 || $videoSize > $maxVideoSize) {
    echo json_encode(['success' => false, 'message' => 'Video size must be between 1 byte and 500MB.']);
    exit;
}

$finfo     = new finfo(FILEINFO_MIME_TYPE);
$videoMime = $finfo->file($videoFile['tmp_name']);
if (!in_array($videoMime, $allowedVideoMime)) {
    echo json_encode(['success' => false, 'message' => 'Invalid video file type detected.']);
    exit;
}

// Thumbnail validation
$thumbFile       = $_FILES['thumb'];
$maxThumbSize    = 2 * 1024 * 1024;
$allowedThumbExt = ['jpg', 'jpeg', 'png', 'webp'];
$allowedThumbMime= ['image/jpeg', 'image/png', 'image/webp'];

$thumbExt  = strtolower(pathinfo($thumbFile['name'], PATHINFO_EXTENSION));
$thumbMime = $finfo->file($thumbFile['tmp_name']);

if (!in_array($thumbExt, $allowedThumbExt) || $thumbFile['size'] > $maxThumbSize) {
    echo json_encode(['success' => false, 'message' => 'Invalid thumbnail. Max 2MB, formats: JPG, PNG, WebP']);
    exit;
}
if (!in_array($thumbMime, $allowedThumbMime)) {
    echo json_encode(['success' => false, 'message' => 'Invalid thumbnail image type.']);
    exit;
}

// Next position
$pos  = 1;
$stmt = $conn->prepare('SELECT COALESCE(MAX(position),0)+1 FROM videos WHERE course_id=?');
$stmt->bind_param('i', $course_id);
$stmt->execute();
$stmt->bind_result($pos);
$stmt->fetch();
$stmt->close();

// Directories
$videoDir = __DIR__ . '/uploads/videos/';
$thumbDir = __DIR__ . '/uploads/videos/video_thumbs/';
if (!is_dir($videoDir)) mkdir($videoDir, 0777, true);
if (!is_dir($thumbDir)) mkdir($thumbDir, 0777, true);

// Unique filenames
$uniqueId      = bin2hex(random_bytes(8));
$videoBasename = 'video_' . $course_id . '_' . $uniqueId . '.' . $videoExt;
$thumbBasename = 'vthumb_' . $course_id . '_' . $uniqueId . '.' . $thumbExt;
$videoDest     = $videoDir . $videoBasename;
$thumbDest     = $thumbDir . $thumbBasename;

if (!move_uploaded_file($videoFile['tmp_name'], $videoDest)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save video file.']);
    exit;
}
if (!move_uploaded_file($thumbFile['tmp_name'], $thumbDest)) {
    unlink($videoDest);
    echo json_encode(['success' => false, 'message' => 'Failed to save thumbnail.']);
    exit;
}

$videoPath = 'uploads/videos/' . $videoBasename;
$thumbUrl  = 'uploads/videos/video_thumbs/' . $thumbBasename;
$embed_url = '';

$stmt = $conn->prepare('INSERT INTO videos (course_id, title, embed_url, video_type, video_path, thumb_url, position, duration, file_size) VALUES (?,?,?,?,?,?,?,?,?)');
$stmt->bind_param('isssssiis', $course_id, $title, $embed_url, $video_type, $videoPath, $thumbUrl, $pos, $duration, $videoSize);

if ($stmt->execute()) {
    $videoId = $stmt->insert_id;
    $stmt->close();
    echo json_encode([
        'success'    => true,
        'message'    => 'Video uploaded successfully!',
        'video_id'   => $videoId,
        'video_path' => $videoPath
    ]);
} else {
    unlink($videoDest);
    unlink($thumbDest);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}