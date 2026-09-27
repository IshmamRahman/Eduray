<?php
session_start();
require __DIR__ . '/db.php';

header('Content-Type: application/json');

// Must be logged in
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

// Accept JSON or POST
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) $data = $_POST;

$videoId         = isset($data['video_id'])         ? intval($data['video_id'])         : 0;
$progressSeconds = isset($data['progress_seconds']) ? intval($data['progress_seconds']) : 0;
$completed       = isset($data['completed'])        ? (int)(bool)$data['completed']     : 0;

if (!$videoId) {
    echo json_encode(['success' => false, 'error' => 'Invalid video ID']);
    exit;
}

// Make sure the user is actually enrolled in this video's course
$check = $conn->prepare("
    SELECT v.id FROM videos v
    JOIN enrollments e ON e.course_id = v.course_id
    WHERE v.id = ? AND e.user_id = ?
    LIMIT 1
");
$check->bind_param("ii", $videoId, $userId);
$check->execute();
$check->store_result();

if ($check->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Not enrolled']);
    $check->close();
    exit;
}
$check->close();

// Upsert progress — never overwrite completed=1 back to 0
$stmt = $conn->prepare("
    INSERT INTO video_progress (user_id, video_id, progress_seconds, completed)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        progress_seconds = GREATEST(progress_seconds, VALUES(progress_seconds)),
        completed        = GREATEST(completed, VALUES(completed))
");
$stmt->bind_param("iiii", $userId, $videoId, $progressSeconds, $completed);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'completed' => $completed]);
} else {
    echo json_encode(['success' => false, 'error' => 'DB error']);
}
$stmt->close();