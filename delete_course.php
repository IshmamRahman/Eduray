<?php
session_start();
require __DIR__ . '/db.php';

if (empty($_SESSION['is_admin'])) { header('Location: courses.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: admin_dashboard.php'); exit; }

$courseId = (int)($_POST['course_id'] ?? 0);
if (!$courseId) { header('Location: admin_dashboard.php?error=invalid_id'); exit; }

// Verify course exists
$check = $conn->prepare("SELECT id, title, thumbnail_url FROM courses WHERE id = ?");
$check->bind_param("i", $courseId);
$check->execute();
$course = $check->get_result()->fetch_assoc();
$check->close();
if (!$course) { header('Location: admin_dashboard.php?error=not_found'); exit; }

// ── Helper: check if a table exists before querying it ────────────────────────
function tableExists($conn, $table) {
    $res = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
    return $res && $res->num_rows > 0;
}

// ── Collect video file paths BEFORE deleting ─────────────────────────────────
$videos = [];
$vr = $conn->prepare("SELECT video_path, thumb_url FROM videos WHERE course_id = ?");
$vr->bind_param("i", $courseId);
$vr->execute();
$videos = $vr->get_result()->fetch_all(MYSQLI_ASSOC);
$vr->close();

// ── Collect quiz IDs ──────────────────────────────────────────────────────────
$quizIds = [];
if (tableExists($conn, 'quizzes')) {
    $qr = $conn->prepare("SELECT id FROM quizzes WHERE course_id = ?");
    $qr->bind_param("i", $courseId);
    $qr->execute();
    $res = $qr->get_result();
    while ($row = $res->fetch_assoc()) { $quizIds[] = (int)$row['id']; }
    $qr->close();
}

// ── Turn off strict reporting so missing-table queries don't abort ─────────────
mysqli_report(MYSQLI_REPORT_OFF);

// ── Run all deletes in a transaction ─────────────────────────────────────────
$conn->begin_transaction();
$ok = true;

// 1. Quiz-related tables (only if they exist)
if ($quizIds) {
    $ph = implode(',', $quizIds);

    if (tableExists($conn, 'quiz_questions')) {
        // get question IDs first for answer deletion
        $qids = [];
        $res = $conn->query("SELECT id FROM quiz_questions WHERE quiz_id IN ($ph)");
        if ($res) { while ($r = $res->fetch_assoc()) $qids[] = (int)$r['id']; }

        if ($qids && tableExists($conn, 'quiz_answers')) {
            $qph = implode(',', $qids);
            $ok = $ok && ($conn->query("DELETE FROM quiz_answers WHERE question_id IN ($qph)") !== false);
        }
        $ok = $ok && ($conn->query("DELETE FROM quiz_questions WHERE quiz_id IN ($ph)") !== false);
    }

    foreach (['quiz_results', 'quiz_attempts', 'quiz_submissions'] as $t) {
        if (tableExists($conn, $t)) {
            $conn->query("DELETE FROM $t WHERE quiz_id IN ($ph)"); // non-fatal
        }
    }

    $ok = $ok && ($conn->query("DELETE FROM quizzes WHERE course_id = $courseId") !== false);
}

// 2. Course questions & replies
if (tableExists($conn, 'course_questions')) {
    $questionIds = [];
    $res = $conn->query("SELECT id FROM course_questions WHERE course_id = $courseId");
    if ($res) { while ($r = $res->fetch_assoc()) $questionIds[] = (int)$r['id']; }

    if ($questionIds && tableExists($conn, 'course_question_replies')) {
        $qph = implode(',', $questionIds);
        $ok = $ok && ($conn->query("DELETE FROM course_question_replies WHERE question_id IN ($qph)") !== false);
    }
    $ok = $ok && ($conn->query("DELETE FROM course_questions WHERE course_id = $courseId") !== false);
}

// 3. Live classes
if (tableExists($conn, 'live_classes')) {
    $ok = $ok && ($conn->query("DELETE FROM live_classes WHERE course_id = $courseId") !== false);
}

// 4. Videos
if (tableExists($conn, 'videos')) {
    $ok = $ok && ($conn->query("DELETE FROM videos WHERE course_id = $courseId") !== false);
}

// 5. Enrollments
if (tableExists($conn, 'enrollments')) {
    $ok = $ok && ($conn->query("DELETE FROM enrollments WHERE course_id = $courseId") !== false);
}

// 6. Progress tables (non-fatal — only delete if they exist)
foreach (['video_progress', 'course_progress', 'student_progress', 'user_progress'] as $t) {
    if (tableExists($conn, $t)) {
        $conn->query("DELETE FROM $t WHERE course_id = $courseId");
    }
}

// 7. Delete the course itself
if ($ok) {
    $del = $conn->prepare("DELETE FROM courses WHERE id = ?");
    $del->bind_param("i", $courseId);
    $ok = $del->execute();
    $del->close();
}

if ($ok) {
    $conn->commit();
} else {
    $conn->rollback();
    // Log the actual mysqli error for debugging
    error_log("delete_course.php failed for course_id=$courseId: " . $conn->error);
    header('Location: admin_dashboard.php?error=db_failed');
    exit;
}

// ── Delete physical files from disk ──────────────────────────────────────────
foreach ($videos as $vid) {
    if (!empty($vid['video_path']) && strpos($vid['video_path'], 'http') === false) {
        $abs = __DIR__ . '/' . ltrim($vid['video_path'], '/');
        if (file_exists($abs)) @unlink($abs);
    }
    if (!empty($vid['thumb_url']) && strpos($vid['thumb_url'], 'http') === false) {
        $abs = __DIR__ . '/' . ltrim($vid['thumb_url'], '/');
        if (file_exists($abs)) @unlink($abs);
    }
}
if (!empty($course['thumbnail_url']) && strpos($course['thumbnail_url'], 'http') === false) {
    $abs = __DIR__ . '/' . ltrim($course['thumbnail_url'], '/');
    if (file_exists($abs)) @unlink($abs);
}

header('Location: admin_dashboard.php?s=courses&deleted=' . urlencode($course['title']));
exit;