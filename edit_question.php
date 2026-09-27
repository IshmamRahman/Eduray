<?php
session_start();
require __DIR__ . '/db.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) { http_response_code(403); die('Forbidden'); }

$id       = (int)($_POST['id']       ?? 0);
$question = trim($_POST['question']  ?? '');
$redirect = $_POST['redirect']       ?? '';

if (!$id || !$question) {
    header('Location: ' . ($redirect ?: 'my_courses.php'));
    exit;
}

// Only the student who asked can edit
$chk = $conn->prepare("SELECT id FROM course_questions WHERE id=? AND student_id=?");
$chk->bind_param("ii", $id, $userId);
$chk->execute();
$chk->store_result();
if ($chk->num_rows === 0) { http_response_code(403); die('Forbidden'); }
$chk->close();

$upd = $conn->prepare("UPDATE course_questions SET question=? WHERE id=?");
$upd->bind_param("si", $question, $id);
$upd->execute();
$upd->close();

header('Location: ' . ($redirect ?: 'my_courses.php'));
exit;