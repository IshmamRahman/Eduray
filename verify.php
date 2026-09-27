<?php
// verify.php — confirm account by token
session_start();
require __DIR__ . '/db.php';

function base_url(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path   = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    return "{$scheme}://{$host}{$path}";
}
$BASE_URL = base_url();

$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[A-Fa-f0-9]{64}$/', $token)) {
    header("Location: {$BASE_URL}/login.php?error=" . rawurlencode("Invalid verification link."));
    exit;
}

$stmt = $conn->prepare("SELECT id, is_verified, verify_expires FROM users WHERE email = ? AND verify_token = ? LIMIT 1");
$stmt->bind_param("ss", $email, $token);
$stmt->execute();
$res  = $stmt->get_result();
$user = $res->fetch_assoc();
$stmt->close();

if (!$user) {
    header("Location: {$BASE_URL}/login.php?error=" . rawurlencode("Invalid or already used verification link."));
    exit;
}

if ((int)$user['is_verified'] === 1) {
    header("Location: {$BASE_URL}/login.php?error=" . rawurlencode("Your account is already verified. Please sign in."));
    exit;
}

if (new DateTime() > new DateTime($user['verify_expires'])) {
    header("Location: {$BASE_URL}/login.php?error=" . rawurlencode("Verification link expired. Please sign up again or request a new link."));
    exit;
}

$upd = $conn->prepare("UPDATE users SET is_verified = 1, verified_at = NOW(), verify_token = NULL, verify_expires = NULL WHERE email = ?");
$upd->bind_param("s", $email);
$ok = $upd->execute();
$upd->close();

if ($ok) {
    header("Location: {$BASE_URL}/login.php?error=" . rawurlencode("Email verified! Please sign in."));
    exit;
}
header("Location: {$BASE_URL}/login.php?error=" . rawurlencode("Verification failed. Please try again."));
exit;
