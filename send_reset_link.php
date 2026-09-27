<?php
// send_reset_link.php — send OTP for password reset
session_start();
require __DIR__ . '/db.php';

// PHPMailer manual include (like your login_action.php)
require __DIR__ . '/src/PHPMailer.php';
require __DIR__ . '/src/SMTP.php';
require __DIR__ . '/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function base_url(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path   = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    return "{$scheme}://{$host}{$path}";
}
$BASE_URL = base_url();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: {$BASE_URL}/forgetpassword.php");
    exit;
}

$email = trim($_POST['email'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: {$BASE_URL}/forgetpassword.php?error=" . rawurlencode("Invalid email address."));
    exit;
}

// Check if email exists in DB
$stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    header("Location: {$BASE_URL}/forgetpassword.php?error=" . rawurlencode("No user found with this email."));
    exit;
}

// Generate OTP
$otp = rand(100000, 999999);
$_SESSION['reset_otp'] = $otp;
$_SESSION['reset_email'] = $email;
$_SESSION['reset_otp_expiry'] = time() + 300; // OTP valid for 5 mins

// Send OTP via PHPMailer
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'jonaki2022@gmail.com';   // <-- your email
    $mail->Password   = 'rcit hfik usxc uxzd';    // <-- app password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom('no-reply@eduray.local', 'EDURAY');
    $mail->addAddress($email, $user['name']);
    $mail->isHTML(true);
    $mail->Subject = 'EDURAY Password Reset OTP';
    $mail->Body    = '
        <h2>Hello ' . htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') . '</h2>
        <p>You requested to reset your password. Use the OTP below to reset it (valid for 5 minutes):</p>
        <h3>' . $otp . '</h3>
        <p>If you did not request this, please ignore this email.</p>
        <br><p>— Team EDURAY</p>
    ';
    $mail->AltBody = "Hello {$user['name']},\nYour OTP: {$otp}\nValid for 5 minutes.\n— Team EDURAY";

    $mail->send();
    header("Location: verify_otp.php?msg=" . rawurlencode("OTP sent to {$email}"));
    exit;

} catch (Exception $e) {
    header("Location: {$BASE_URL}/forgetpassword.php?error=" . rawurlencode("OTP could not be sent. Try again."));
    exit;
}
