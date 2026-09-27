<?php
session_start();
require __DIR__ . '/db.php';

if (!empty($_SESSION['user_id'])) {
    if (!empty($_SESSION['is_instructor'])) header('Location: instructor_dashboard.php');
    elseif (!empty($_SESSION['is_admin']))  header('Location: admin_dashboard.php');
    else                                    header('Location: my_courses.php');
    exit;
}

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

function sendVerificationEmail(string $email, string $name, string $verifyLink): bool {
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
        $mail->Subject = 'Verify your EDURAY Instructor Account';
        $mail->Body    = '
            <h2>Hi ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ',</h2>
            <p>Welcome to EDURAY as an Instructor! Please verify your email (valid for 24 hours):</p>
            <p><a href="' . htmlspecialchars($verifyLink, ENT_QUOTES, 'UTF-8') . '"
                style="background:#5f259f;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600;">
                Verify my Instructor Account
            </a></p>
            <p>Or copy: ' . htmlspecialchars($verifyLink, ENT_QUOTES, 'UTF-8') . '</p>
            <br><p>— Team EDURAY</p>
        ';
        $mail->AltBody = "Hi {$name},\n\nVerify: {$verifyLink}\n\n— Team EDURAY";
        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

$error    = '';
$BASE_URL = base_url();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $mobile   = trim($_POST['mobile']   ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm  = trim($_POST['confirm']  ?? '');

    $emailRegex    = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';
    $passwordRegex = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/';
    $mobileRegex   = '/^[0-9+\-\s]{7,15}$/';

    if (!$name || !$email || !$mobile || !$password || !$confirm) {
        $error = 'All fields are required.';
    } elseif (!preg_match($emailRegex, $email)) {
        $error = 'Please enter a valid email address.';
    } elseif (!preg_match($mobileRegex, $mobile)) {
        $error = 'Please enter a valid mobile number.';
    } elseif (!preg_match($passwordRegex, $password)) {
        $error = 'Password must be at least 8 characters with uppercase, lowercase, number, and special character.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $chk = $conn->prepare("SELECT id, is_instructor, is_verified FROM users WHERE email = ?");
        $chk->bind_param("s", $email); $chk->execute();
        $existing = $chk->get_result()->fetch_assoc(); $chk->close();

        $hash       = password_hash($password, PASSWORD_DEFAULT);
        $token      = bin2hex(random_bytes(32));
        $expiry     = date('Y-m-d H:i:s', time() + 86400);
        $verifyLink = "{$BASE_URL}/verify.php?token={$token}&email=" . rawurlencode($email);

        if ($existing) {
            if ($existing['is_instructor'] && $existing['is_verified']) {
                $error = 'An instructor account with this email already exists.';
            } elseif ($existing['is_instructor'] && !$existing['is_verified']) {
                $upd = $conn->prepare("UPDATE users SET name=?, password_hash=?, verify_token=?, verify_expires=? WHERE email=?");
                $upd->bind_param("sssss", $name, $hash, $token, $expiry, $email);
                $upd->execute(); $upd->close();
                if (!sendVerificationEmail($email, $name, $verifyLink)) $_SESSION['dev_verify_link'] = $verifyLink;
                header("Location: {$BASE_URL}/login.php?error=" . rawurlencode("A new verification link has been sent to your email.")); exit;
            } else {
                // Email already registered as a student — block it
                $error = 'This email is already registered as a student account. Please use a different email.';
            }
        } else {
            $ins = $conn->prepare("INSERT INTO users (name, email, mobile, password_hash, is_instructor, is_verified, verify_token, verify_expires) VALUES (?, ?, ?, ?, 1, 0, ?, ?)");
            $ins->bind_param("ssssss", $name, $email, $mobile, $hash, $token, $expiry);
            if (!$ins->execute()) { $ins->close(); $error = 'Registration failed. Please try again.'; }
            else {
                $ins->close();
                if (!sendVerificationEmail($email, $name, $verifyLink)) $_SESSION['dev_verify_link'] = $verifyLink;
                header("Location: {$BASE_URL}/login.php?error=" . rawurlencode("We emailed you a verification link. Please verify to sign in as instructor.")); exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>EDURAY – Instructor Sign Up</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet"/>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{--primary:#5f259f;--accent:#ff6b00;--gold:#ffe600;--error:#dc2626;--success:#16a34a;}
*{box-sizing:border-box}
body{margin:0;font-family:'Poppins',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;background:#ffffff;}
.card-wrap{width:100%;max-width:520px;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 20px 50px rgba(0,0,0,.12);}
.card-header{background:linear-gradient(135deg,var(--primary),var(--accent));color:#fff;padding:28px 24px;}
.brand{font-weight:700;display:flex;align-items:center;gap:12px;font-size:2rem;text-decoration:none;color:#fff;}
.brand .accent{color:var(--gold);}
.card-header p{margin:6px 0 0;opacity:.85;font-size:14px;}
.auth-tabs{display:flex;background:#f8f5ff;border-bottom:2px solid #ede9ff;}
.auth-tab{flex:1;text-align:center;padding:11px 6px;font-size:12.5px;font-weight:600;color:#888;border-bottom:3px solid transparent;margin-bottom:-2px;text-decoration:none;transition:color .2s,border-color .2s;display:flex;align-items:center;justify-content:center;gap:5px;}
.auth-tab:hover{text-decoration:none;}
.auth-tab.student:hover,.auth-tab.student.active{color:var(--primary);}
.auth-tab.student.active{border-bottom-color:var(--primary);}
.auth-tab.instructor:hover,.auth-tab.instructor.active{color:var(--accent);}
.auth-tab.instructor.active{border-bottom-color:var(--accent);}
.card-body{padding:28px}
.form-control{border-radius:14px;padding:12px 14px;border:1px solid rgba(46,139,87,.35);box-shadow:0 0 0 3px rgba(46,139,87,.25);transition:all 0.3s ease;}
.form-control:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(95,37,159,.25);}
.form-control.error{border-color:var(--error);box-shadow:0 0 0 3px rgba(220,38,38,.25);}
.form-control.success{border-color:var(--success);box-shadow:0 0 0 3px rgba(22,163,74,.25);}
.password-wrap{position:relative;display:flex;align-items:center;}
.password-wrap input{width:100%;padding-right:44px;}
.password-wrap .toggle-password{position:absolute;right:12px;top:50%;transform:translateY(-50%);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:18px;user-select:none;color:#666;width:32px;height:32px;border-radius:50%;transition:all 0.2s ease;}
.password-wrap .toggle-password:hover{color:var(--primary);background:rgba(95,37,159,.1);}
.field-hint{font-size:11px;color:#666;margin-top:4px;margin-left:2px;display:flex;align-items:center;gap:4px;}
.field-hint i{font-size:10px;color:var(--primary);}
.field-hint.error-hint{color:var(--error);}
.field-hint.success-hint{color:var(--success);}
.password-strength{height:4px;background:#e5e7eb;border-radius:2px;margin-top:6px;overflow:hidden;display:none;}
.password-strength.show{display:block;}
.password-strength-bar{height:100%;width:0;transition:all 0.3s ease;border-radius:2px;}
.strength-weak{background:var(--error);width:33%;}.strength-medium{background:var(--gold);width:66%;}.strength-strong{background:var(--success);width:100%;}
.btn-primary{background:linear-gradient(135deg,var(--gold),#ff8800);color:#222;border:none;border-radius:14px;padding:12px 16px;font-weight:600;width:auto;max-width:260px;display:block;margin:0 auto;box-shadow:0 12px 26px rgba(255,136,0,.35);transition:transform .18s ease,filter .18s ease;}
.btn-primary:hover{filter:brightness(.96);transform:translateY(-1px) scale(1.01);color:#222;}
.btn-primary:active{transform:translateY(0);}
.alert-err{background:rgba(220,38,38,.1);border:1px solid rgba(220,38,38,.3);color:var(--error);border-radius:12px;padding:12px 16px;font-size:14px;margin-bottom:16px;display:flex;align-items:center;gap:8px;}
.terms-box{background:#f9f9f9;border-radius:12px;padding:12px;font-size:13px;line-height:1.6;}
.form-label{font-weight:600;color:#333;font-size:14px;margin-bottom:6px;}
.required::after{content:" *";color:var(--error);}
.already-member{text-align:center;margin-top:20px;font-size:13.5px;color:#666;}
.already-member a{color:var(--primary);font-weight:700;text-decoration:none;}
.already-member a:hover{text-decoration:underline;}
</style>
</head>
<body>
<div class="card-wrap">
  <div class="card-header">
    <a href="index.php" class="brand">📚 <span class="accent">EDURAY</span></a>
    <p>Share your knowledge — become an instructor today</p>
  </div>

  <div class="auth-tabs">
    <a href="main.php" class="auth-tab student"><i class="fas fa-user-graduate"></i> Student Sign Up</a>
    <a href="instructor_register.php" class="auth-tab instructor active"><i class="fas fa-chalkboard-teacher"></i> Instructor Sign Up</a>
  </div>

  <div class="card-body">
    <?php if ($error): ?>
      <div class="alert-err"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form id="instructorForm" method="POST" action="" novalidate>
      <div class="mb-3">
        <label class="form-label required">Full Name</label>
        <input type="text" class="form-control" name="name" id="iName" required placeholder="Enter your full name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
      </div>
      <div class="mb-3">
        <label class="form-label required">Email Address</label>
        <input type="email" class="form-control" name="email" id="iEmail" required placeholder="name@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        <div class="field-hint" id="iEmailHint"><i class="fas fa-info-circle"></i><span>A verification link will be sent here</span></div>
      </div>
      <div class="mb-3">
        <label class="form-label required">Mobile Number</label>
        <input type="tel" class="form-control" name="mobile" id="iMobile" required placeholder="01712345678" value="<?= htmlspecialchars($_POST['mobile'] ?? '') ?>">
      </div>
      <div class="mb-3">
        <label class="form-label required">Password</label>
        <div class="password-wrap">
          <input type="password" id="iPassword" class="form-control" name="password" required placeholder="Create a strong password">
          <span class="toggle-password" onclick="togglePassword('iPassword',this)">👁️</span>
        </div>
        <div class="password-strength" id="iStrength"><div class="password-strength-bar" id="iStrengthBar"></div></div>
        <div class="field-hint" id="iPasswordHint"><i class="fas fa-info-circle"></i><span>Min 8 chars: uppercase, lowercase, number, special (@$!%*?&)</span></div>
      </div>
      <div class="mb-3">
        <label class="form-label required">Confirm Password</label>
        <div class="password-wrap">
          <input type="password" id="iConfirm" class="form-control" name="confirm" required placeholder="Re-enter your password">
          <span class="toggle-password" onclick="togglePassword('iConfirm',this)">👁️</span>
        </div>
        <div class="field-hint" id="iConfirmHint"><i class="fas fa-info-circle"></i><span>Must match the password above</span></div>
      </div>
      <div class="form-check mb-2">
        <input class="form-check-input" type="checkbox" id="iTerms" required>
        <label class="form-check-label" for="iTerms" style="font-size:14px;">I agree to the <a href="#" style="color:var(--primary);">Terms & Conditions</a></label>
      </div>
      <details class="mb-3">
        <summary style="cursor:pointer;font-size:13px;color:var(--primary);font-weight:500;">View Terms & Conditions</summary>
        <div class="terms-box mt-2">
          <ul class="mb-0 ps-3">
            <li>You must provide accurate and complete registration information.</li>
            <li>Your account credentials must be kept confidential.</li>
            <li>Instructor content must be original and educational.</li>
            <li>We may suspend accounts for violations or misuse.</li>
            <li>Your data is handled according to our Privacy Policy.</li>
          </ul>
        </div>
      </details>
      <button type="submit" class="btn btn-primary w-100">Create Instructor Account</button>
    </form>
    <p class="already-member">Already a member? <a href="login.php">Login here</a></p>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePassword(id,el){const i=document.getElementById(id);if(i.type==='password'){i.type='text';el.textContent='🙈';}else{i.type='password';el.textContent='👁️';}}
const emailPat=/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
const passPat=/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;
function setHint(id,cls,icon,msg){const h=document.getElementById(id);h.className='field-hint'+(cls?' '+cls:'');h.innerHTML=`<i class="fas fa-${icon}"></i><span>${msg}</span>`;}
function setField(id,state){const el=document.getElementById(id);el.classList.remove('error','success');if(state)el.classList.add(state);}
function strength(p){let s=0;if(p.length>=8)s++;if(/[a-z]/.test(p))s++;if(/[A-Z]/.test(p))s++;if(/\d/.test(p))s++;if(/[@$!%*?&]/.test(p))s++;const w=document.getElementById('iStrength'),b=document.getElementById('iStrengthBar');if(p){w.classList.add('show');b.className='password-strength-bar';if(s<=2)b.classList.add('strength-weak');else if(s<=4)b.classList.add('strength-medium');else b.classList.add('strength-strong');}else w.classList.remove('show');}
function checkConfirm(){const p=document.getElementById('iPassword').value,c=document.getElementById('iConfirm');if(!c.value){setField('iConfirm','');setHint('iConfirmHint','','info-circle','Must match the password above');return;}if(c.value===p){setField('iConfirm','success');setHint('iConfirmHint','success-hint','check-circle','Passwords match!');}else{setField('iConfirm','error');setHint('iConfirmHint','error-hint','exclamation-circle','Passwords do not match');}}
document.getElementById('iEmail').addEventListener('input',function(){if(!this.value){setField('iEmail','');setHint('iEmailHint','','info-circle','A verification link will be sent here');return;}if(emailPat.test(this.value)){setField('iEmail','success');setHint('iEmailHint','success-hint','check-circle','Valid email!');}else{setField('iEmail','error');setHint('iEmailHint','error-hint','exclamation-circle','Invalid email format');}});
document.getElementById('iPassword').addEventListener('input',function(){strength(this.value);if(!this.value){setField('iPassword','');setHint('iPasswordHint','','info-circle','Min 8 chars: uppercase, lowercase, number, special (@$!%*?&)');return;}if(passPat.test(this.value)){setField('iPassword','success');setHint('iPasswordHint','success-hint','check-circle','Strong password!');}else{setField('iPassword','error');setHint('iPasswordHint','error-hint','exclamation-circle','Need: 8+ chars, uppercase, lowercase, number, special');}if(document.getElementById('iConfirm').value)checkConfirm();});
document.getElementById('iConfirm').addEventListener('input',checkConfirm);
document.getElementById('instructorForm').addEventListener('submit',function(e){
  let ok=true;
  const n=document.getElementById('iName'),em=document.getElementById('iEmail'),m=document.getElementById('iMobile'),p=document.getElementById('iPassword'),c=document.getElementById('iConfirm');
  if(!n.value.trim()){setField('iName','error');ok=false;}else setField('iName','success');
  if(!emailPat.test(em.value)){setField('iEmail','error');setHint('iEmailHint','error-hint','exclamation-circle','Valid email required');ok=false;}
  if(!/^[0-9+\-\s]{7,15}$/.test(m.value)){setField('iMobile','error');ok=false;}else setField('iMobile','success');
  if(!passPat.test(p.value)){setField('iPassword','error');setHint('iPasswordHint','error-hint','exclamation-circle','Password does not meet requirements');ok=false;}
  if(p.value!==c.value){setField('iConfirm','error');setHint('iConfirmHint','error-hint','exclamation-circle','Passwords do not match');ok=false;}
  if(!document.getElementById('iTerms').checked){alert('Please agree to the Terms & Conditions');ok=false;}
  if(!ok){e.preventDefault();const f=document.querySelector('.form-control.error');if(f){f.scrollIntoView({behavior:'smooth',block:'center'});f.focus();}}
});
</script>
</body>
</html>