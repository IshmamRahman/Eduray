<?php
session_start();
require __DIR__ . '/db.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) { http_response_code(403); die('Forbidden'); }

$id       = (int)($_POST['id']   ?? 0);
$redirect = $_POST['redirect']   ?? '';

if (!$id) {
    header('Location: ' . ($redirect ?: 'my_courses.php'));
    exit;
}

// Only the student who asked can delete
$chk = $conn->prepare("SELECT id FROM course_questions WHERE id=? AND student_id=?");
$chk->bind_param("ii", $id, $userId);
$chk->execute();
$chk->store_result();
if ($chk->num_rows === 0) { http_response_code(403); die('Forbidden'); }
$chk->close();

// Delete reply first (FK), then question
$conn->prepare("DELETE FROM course_question_replies WHERE question_id=?")->execute() === false ?: null;
$del1 = $conn->prepare("DELETE FROM course_question_replies WHERE question_id=?");
$del1->bind_param("i", $id);
$del1->execute();
$del1->close();

$del2 = $conn->prepare("DELETE FROM course_questions WHERE id=?");
$del2->bind_param("i", $id);
$del2->execute();
$del2->close();

header('Location: ' . ($redirect ?: 'my_courses.php'));
exit;