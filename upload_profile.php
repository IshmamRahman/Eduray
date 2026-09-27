<?php
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

if (empty($_FILES['profile_pic']) || $_FILES['profile_pic']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'No file received']);
    exit;
}

$file     = $_FILES['profile_pic'];
$maxSize  = 2 * 1024 * 1024; // 2MB
$allowed  = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

// Validate size
if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'error' => 'File too large (max 2MB)']);
    exit;
}

// Validate MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, $allowed)) {
    echo json_encode(['success' => false, 'error' => 'Invalid file type. Use JPG, PNG, GIF or WEBP.']);
    exit;
}

// Build upload path
$uploadDir = 'uploads/profiles/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'profile_' . (int)$_SESSION['user_id'] . '_' . time() . '.' . $ext;
$destPath = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    echo json_encode(['success' => false, 'error' => 'Failed to save file']);
    exit;
}

// Update DB
$conn = new mysqli("localhost", "root", "", "eduray_portal2");
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'DB connection failed']);
    exit;
}

$uid  = (int) $_SESSION['user_id'];
$path = $conn->real_escape_string($destPath);
$conn->query("UPDATE users SET profile_pic = '$path' WHERE id = $uid");
$conn->close();

echo json_encode(['success' => true, 'path' => $destPath]);