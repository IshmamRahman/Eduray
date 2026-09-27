<?php
session_start();
require __DIR__ . '/db.php';

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

function resend_verification_link(mysqli $conn, int $user_id, string $email, string $name, string $baseUrl): void {
    $token  = bin2hex(random_bytes(32));
    $expiry = date('Y-m-d H:i:s', time() + 24*60*60);

    $u = $conn->prepare("UPDATE users SET verify_token=?, verify_expires=? WHERE id=?");
    $u->bind_param("ssi", $token, $expiry, $user_id);
    $u->execute();
    $u->close();

    $link = "{$baseUrl}/verify.php?token={$token}&email=" . rawurlencode($email);

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
        $mail->Subject = 'Verify your EDURAY account (resend)';
        $mail->Body    = '
            <h2>Hi ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ',</h2>
            <p>Please verify your email by clicking the link below (valid for 24 hours):</p>
            <p><a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">Verify my email</a></p>
            <p>Or copy this URL:<br>' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '</p>
            <br><p>— Team EDURAY</p>
        ';
        $mail->AltBody = "Hi {$name},\n\nVerify your email: {$link}\n\n— Team EDURAY";
        $mail->send();
    } catch (Exception $e) {
        $_SESSION['dev_verify_link'] = $link;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: {$BASE_URL}/login.php");
    exit;
}

$email    = trim($_POST['email']    ?? '');
$password =        $_POST['password'] ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: {$BASE_URL}/login.php?error=" . rawurlencode("Invalid email address.") . "&email=" . rawurlencode($email));
    exit;
}
if ($password === '') {
    header("Location: {$BASE_URL}/login.php?error=" . rawurlencode("Password is required.") . "&email=" . rawurlencode($email));
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT id, name, email, password_hash,
               is_verified, verify_token, verify_expires,
               is_admin, is_instructor
        FROM users
        WHERE email = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        header("Location: {$BASE_URL}/login.php?error=" . rawurlencode("Incorrect email or password.") . "&email=" . rawurlencode($email));
        exit;
    }

    $loginRole    = trim($_POST['role'] ?? '');
    $isInstructor = (int)($user['is_instructor'] ?? 0) === 1;
    $isAdmin      = (int)($user['is_admin']      ?? 0) === 1;

    // Admin: skip ALL other checks and redirect immediately to admin dashboard
    if ($isAdmin) {
        session_regenerate_id(true);
        $_SESSION['user_id']       = (int)$user['id'];
        $_SESSION['name']          = $user['name'];
        $_SESSION['email']         = $user['email'];
        $_SESSION['is_admin']      = true;
        $_SESSION['is_instructor'] = false;
        header("Location: {$BASE_URL}/admin_dashboard.php");
        exit;
    }

    // Non-admin role mismatch check
    if ($loginRole === 'student' && $isInstructor) {
        header("Location: {$BASE_URL}/login.php?role=student&error=" . rawurlencode("This is an instructor account. Please use Instructor Login.") . "&email=" . rawurlencode($email));
        exit;
    }
    if ($loginRole === 'instructor' && !$isInstructor) {
        header("Location: {$BASE_URL}/login.php?role=instructor&error=" . rawurlencode("This is a student account. Please use Student Login.") . "&email=" . rawurlencode($email));
        exit;
    }

    if ((int)$user['is_verified'] !== 1) {
        resend_verification_link($conn, (int)$user['id'], $user['email'], $user['name'], $BASE_URL);
        header("Location: {$BASE_URL}/login.php?error=" . rawurlencode("Please verify your email. We've sent you a new verification link.") . "&email=" . rawurlencode($email));
        exit;
    }

    session_regenerate_id(true);
    $_SESSION['user_id']       = (int)$user['id'];
    $_SESSION['name']          = $user['name'];
    $_SESSION['email']         = $user['email'];
    $_SESSION['is_admin']      = ((int)$user['is_admin']      === 1);
    $_SESSION['is_instructor'] = ((int)$user['is_instructor'] === 1);

    if ((int)$user['is_admin'] === 1) {
        header("Location: {$BASE_URL}/admin_dashboard.php");
    } elseif ((int)$user['is_instructor'] === 1) {
        header("Location: {$BASE_URL}/instructor_dashboard.php");
    } else {
        header("Location: {$BASE_URL}/index.php");
    }
    exit;

} catch (Throwable $e) {
    header("Location: {$BASE_URL}/login.php?error=" . rawurlencode("Server error. Please try again.") . "&email=" . rawurlencode($email));
    exit;
}