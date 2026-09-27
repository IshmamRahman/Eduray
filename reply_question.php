<?php
session_start();
require __DIR__ . '/db.php';

$userId       = $_SESSION['user_id']       ?? null;
$isInstructor = !empty($_SESSION['is_instructor']);
$isAdmin      = !empty($_SESSION['is_admin']);

// Allow both instructors AND admins — block everyone else
if (!$userId || (!$isInstructor && !$isAdmin)) {
    http_response_code(403);
    die('Forbidden');
}

$action     = trim($_POST['action']       ?? 'add');
$courseId   = (int)($_POST['course_id']   ?? 0);
$questionId = (int)($_POST['question_id'] ?? 0);
$replyId    = (int)($_POST['reply_id']    ?? 0);
$replyText  = trim($_POST['reply']        ?? '');

// Verify course ownership — admins can reply on any course, instructors only their own
if ($courseId && $isInstructor && !$isAdmin) {
    $chk = $conn->prepare("SELECT id FROM courses WHERE id = ? AND instructor_id = ?");
    $chk->bind_param("ii", $courseId, $userId);
    $chk->execute();
    $chk->store_result();
    if ($chk->num_rows === 0) {
        http_response_code(403);
        die('Forbidden');
    }
    $chk->close();
}

// Determine redirect target based on who is replying
$redirectBase = $isAdmin
    ? "admin_dashboard.php?cid=$courseId"
    : "instructor_dashboard.php?cid=$courseId";

// ── ADD / UPDATE reply ────────────────────────────────────────────────────────
if ($action === 'add' && $questionId && $replyText) {

    // Check if a reply already exists for this question
    $chkReply = $conn->prepare("SELECT id FROM course_question_replies WHERE question_id = ?");
    $chkReply->bind_param("i", $questionId);
    $chkReply->execute();
    $chkReply->store_result();
    $exists = $chkReply->num_rows > 0;
    $chkReply->close();

    if ($exists) {
        // Overwrite existing reply, also store who last replied
        $upd = $conn->prepare("
            UPDATE course_question_replies
            SET reply = ?, instructor_id = ?, created_at = NOW()
            WHERE question_id = ?
        ");
        $upd->bind_param("sii", $replyText, $userId, $questionId);
        $upd->execute();
        $upd->close();
    } else {
        // Insert new reply
        $ins = $conn->prepare("
            INSERT INTO course_question_replies (question_id, instructor_id, reply, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $ins->bind_param("iis", $questionId, $userId, $replyText);
        $ins->execute();
        $ins->close();
    }

    header("Location: {$redirectBase}&replied=1");
    exit;
}

// ── EDIT existing reply ───────────────────────────────────────────────────────
if ($action === 'edit' && $replyId && $replyText) {
    $upd = $conn->prepare("
        UPDATE course_question_replies
        SET reply = ?, instructor_id = ?, created_at = NOW()
        WHERE id = ?
    ");
    $upd->bind_param("sii", $replyText, $userId, $replyId);
    $upd->execute();
    $upd->close();

    // Support custom redirect (e.g. from admin dashboard ?tab=questions)
    $redirect = !empty($_POST['redirect']) ? $_POST['redirect'] : "{$redirectBase}&reply_edited=1";
    header("Location: $redirect");
    exit;
}

// ── DELETE reply ──────────────────────────────────────────────────────────────
if ($action === 'delete' && $replyId) {
    $del = $conn->prepare("DELETE FROM course_question_replies WHERE id = ?");
    $del->bind_param("i", $replyId);
    $del->execute();
    $del->close();

    $redirect = !empty($_POST['redirect']) ? $_POST['redirect'] : "{$redirectBase}&reply_deleted=1";
    header("Location: $redirect");
    exit;
}

// Fallback
header("Location: $redirectBase");
exit;