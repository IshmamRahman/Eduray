<?php
session_start();
require __DIR__ . '/db.php';

$userId       = $_SESSION['user_id']       ?? null;
$isInstructor = !empty($_SESSION['is_instructor']);
$isAdmin      = !empty($_SESSION['is_admin']);

if (!$userId || (!$isInstructor && !$isAdmin)) {
    http_response_code(403); die('Forbidden');
}

$id       = (int)($_POST['id']    ?? 0);
$reply    = trim($_POST['reply']  ?? '');
$redirect = $_POST['redirect']    ?? '';

if (!$id || !$reply) {
    header('Location: ' . ($redirect ?: 'instructor_dashboard.php'));
    exit;
}

// Only the instructor who replied can edit
$chk = $conn->prepare("SELECT id FROM course_question_replies WHERE id=? AND instructor_id=?");
$chk->bind_param("ii", $id, $userId);
$chk->execute();
$chk->store_result();
if ($chk->num_rows === 0) { http_response_code(403); die('Forbidden'); }
$chk->close();

$upd = $conn->prepare("UPDATE course_question_replies SET reply=? WHERE id=?");
$upd->bind_param("si", $reply, $id);
$upd->execute();
$upd->close();

header('Location: ' . ($redirect ?: 'instructor_dashboard.php'));
exit;