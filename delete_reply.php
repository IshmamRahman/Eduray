<?php
session_start();
require __DIR__ . '/db.php';

$userId       = $_SESSION['user_id']       ?? null;
$isInstructor = !empty($_SESSION['is_instructor']);
$isAdmin      = !empty($_SESSION['is_admin']);

if (!$userId || (!$isInstructor && !$isAdmin)) {
    http_response_code(403); die('Forbidden');
}

$id       = (int)($_POST['id']   ?? 0);
$redirect = $_POST['redirect']   ?? '';

if (!$id) {
    header('Location: ' . ($redirect ?: 'instructor_dashboard.php'));
    exit;
}

// Only the instructor who replied can delete
$chk = $conn->prepare("SELECT id FROM course_question_replies WHERE id=? AND instructor_id=?");
$chk->bind_param("ii", $id, $userId);
$chk->execute();
$chk->store_result();
if ($chk->num_rows === 0) { http_response_code(403); die('Forbidden'); }
$chk->close();

$del = $conn->prepare("DELETE FROM course_question_replies WHERE id=?");
$del->bind_param("i", $id);
$del->execute();
$del->close();

header('Location: ' . ($redirect ?: 'instructor_dashboard.php'));
exit;