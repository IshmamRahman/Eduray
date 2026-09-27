<?php
session_start();
require __DIR__ . '/db.php';

$userId       = $_SESSION['user_id']  ?? null;
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

$courseId    = (int)($_POST['course_id']   ?? 0);
$title       = trim($_POST['title']        ?? '');
$meetLink    = trim($_POST['meet_link']    ?? '');
$scheduledAt = trim($_POST['scheduled_at'] ?? '');
$description = trim($_POST['description']  ?? '');

// ── Fix datetime format: "2025-01-15T14:30" → "2025-01-15 14:30:00" ──────────
// datetime-local inputs use a 'T' separator; MySQL needs a space
if ($scheduledAt) {
    $scheduledAt = str_replace('T', ' ', $scheduledAt);
    // Append seconds if missing (some browsers omit them)
    if (strlen($scheduledAt) === 16) {
        $scheduledAt .= ':00';
    }
    // Final validation — make sure it's a real date
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $scheduledAt);
    if (!$dt) {
        $scheduledAt = ''; // will trigger the validation error below
    }
}

// ── Redirect base depending on who's acting ───────────────────────────────────
$redirectBase = $isAdmin
    ? "admin_dashboard.php?cid=$courseId"
    : "instructor_dashboard.php?cid=$courseId";

if (!$courseId || !$title || !$meetLink || !$scheduledAt) {
    $_SESSION['flash_error'] = 'All required fields must be filled in.';
    header("Location: $redirectBase");
    exit;
}

// ── Verify course ownership (skip for admins) ─────────────────────────────────
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

// ── Insert ────────────────────────────────────────────────────────────────────
$stmt = $conn->prepare("
    INSERT INTO live_classes (course_id, title, meet_link, scheduled_at, description)
    VALUES (?, ?, ?, ?, ?)
");
$stmt->bind_param("issss", $courseId, $title, $meetLink, $scheduledAt, $description);

if ($stmt->execute()) {
    $stmt->close();
    header("Location: {$redirectBase}&live_added=1");
} else {
    $err = $stmt->error;
    $stmt->close();
    $_SESSION['flash_error'] = 'Database error: ' . $err;
    header("Location: $redirectBase");
}
exit;