<?php
// register.php — handles student signup, blocks if email already used as instructor
session_start();
require __DIR__ . '/db.php';

require_once __DIR__ . '/src/PHPMailer.php';
require_once __DIR__ . '/src/SMTP.php';
require_once __DIR__ . '/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function base_url(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path   = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    return "{$scheme}://{$host}{$path}";
}
$BASE_URL = base_url();

function fail_redirect(string $msg) {
    header("Location: " . base_url() . "/main.php?error=" . rawurlencode($msg));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: {$BASE_URL}/main.php");
    exit;
}

$name     = trim($_POST['name']    ?? '');
$email    = trim($_POST['email']   ?? '');
$mobile   = trim($_POST['mobile']  ?? '');
$password =        $_POST['password'] ?? '';

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    fail_redirect("Please provide valid name, email and password.");
}

$pass_hash = password_hash($password, PASSWORD_BCRYPT);

// Check if email already exists
$stmt = $conn->prepare("SELECT id, is_instructor FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existing) {
    if ($existing['is_instructor']) {
        fail_redirect("This email is already registered as an instructor account. Please use a different email.");
    } else {
        fail_redirect("This email is already registered. Please sign in or use a different email.");
    }
}

// token (valid 24h)
$token  = bin2hex(random_bytes(32));
$expiry = date('Y-m-d H:i:s', time() + 24*60*60);

// insert (unverified student)
$stmt = $conn->prepare(
  "INSERT INTO users (name, email, mobile, password_hash, is_verified, verify_token, verify_expires) 
   VALUES (?, ?, ?, ?, 0, ?, ?)"
);
$stmt->bind_param("ssssss", $name, $email, $mobile, $pass_hash, $token, $expiry);
if (!$stmt->execute()) {
    $stmt->close();
    fail_redirect("Registration failed. Please try again.");
}
$stmt->close();

$verify_link = "{$BASE_URL}/verify.php?token={$token}&email=" . rawurlencode($email);

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'jonaki2022@gmail.com';
    $mail->Password   = 'rcit hfik usxc uxzd';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom('jonaki2022@gmail.com', 'EDURAY');
    $mail->addAddress($email, $name);

    $mail->isHTML(true);
    $mail->Subject = 'Verify your EDURAY account';
    $mail->Body    = '
        <h2>Hi ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ',</h2>
        <p>Thanks for signing up. Please verify your email by clicking the link below (valid for 24 hours):</p>
        <p><a href="' . htmlspecialchars($verify_link, ENT_QUOTES, 'UTF-8') . '">Verify my email</a></p>
        <p>If the button doesn\'t work, copy this URL:<br>' . htmlspecialchars($verify_link, ENT_QUOTES, 'UTF-8') . '</p>
        <br><p>— Team EDURAY</p>
    ';
    $mail->AltBody = "Hi {$name},\n\nVerify your email: {$verify_link}\n\n— Team EDURAY";

    $mail->send();

    header("Location: {$BASE_URL}/login.php?error=" . rawurlencode("We emailed you a verification link. Please verify to sign in."));
    exit;
} catch (Exception $e) {
    $_SESSION['dev_verify_link'] = $verify_link;
    header("Location: {$BASE_URL}/login.php?error=" . rawurlencode("Could not send verification email (dev link saved)."));
    exit;
}