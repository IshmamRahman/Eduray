<?php
session_start();
require __DIR__ . '/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_pass = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    if (!isset($_SESSION['reset_email'])) {
        $error = "Session expired. Try again.";
    } elseif ($new_pass !== $confirm_pass) {
        $error = "Passwords do not match.";
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $new_pass)) {
        $error = "Password must be at least 8 characters, include uppercase, lowercase, number, and special character.";
    } else {
        $hashed = password_hash($new_pass, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("UPDATE users SET password_hash=? WHERE email=?");
        $stmt->bind_param("ss", $hashed, $_SESSION['reset_email']);
        $stmt->execute();
        $stmt->close();

        session_unset();
        $success = "Password reset successfully!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>EDURAY – Reset Password</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root{--primary:#5f259f;--accent:#ff6b00;--gold:#ffe600;--bg:#fdfaf6;--seagreen:#2e8b57;}
*{box-sizing:border-box;}
body{margin:0;font-family:'Poppins',sans-serif;background:linear-gradient(135deg,#fdfaf6,#fff);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;overflow-x:hidden;}
.card-wrap{width:100%;max-width:520px;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 20px 50px rgba(0,0,0,.12);border:1px solid rgba(95,37,159,.08);animation:fadeUp .6s ease-out both;}
.card-header{background:linear-gradient(135deg,var(--primary),var(--accent));background-size:200% 200%;animation:gradientShift 8s ease infinite;color:#fff;padding:28px 24px;}
.brand{font-weight:700;letter-spacing:.5px;display:flex;align-items:center;gap:10px;font-size: clamp(24px, 3vw, 36px);line-height:1.05;}
.brand .logo-emoji{display:inline-block;font-size:1.1em;transform:translateY(2px);}
.brand .accent{color:var(--gold);}
.card-body{padding:28px;}
.field-wrap{position:relative;border-radius:16px;margin-bottom:1rem;}
.field-wrap::after{content:"";position:absolute;inset:-6px;border-radius:18px;border:2px solid rgba(46,139,87,0);opacity:0;transform:scale(.985);transition: opacity .25s, transform .25s, border-color .25s;pointer-events:none;z-index:1;}
.field-wrap:hover::after{opacity:1;transform:scale(1);border-color:rgba(46,139,87,.35);}
.field-wrap:has(input:focus)::after{border-color:rgba(46,139,87,.65);animation:ringPulse .7s ease;}
.form-control{position:relative;z-index:2;border-radius:12px;padding:12px 14px;border:1px solid rgba(46,139,87,.35);background:#fff;box-shadow:inset 0 1px 3px rgba(0,0,0,0.08);font-size:15px;transition: all .25s ease;}
.form-control:hover{border-color: rgba(46,139,87,.45);box-shadow:inset 0 1px 3px rgba(0,0,0,0.08), 0 0 0 4px rgba(46,139,87,.1);}
.form-control:focus{border-color: var(--seagreen);box-shadow:inset 0 1px 3px rgba(0,0,0,0.08),0 0 0 4px rgba(46,139,87,.25);outline:none;}
.btn-primary{background:linear-gradient(135deg,var(--gold),#ff8800);color:#222;border:none;border-radius:14px;padding:10px 14px;font-size:.95rem;font-weight:600;line-height:1.1;box-shadow:0 10px 22px rgba(255,136,0,.32);transition: transform .18s ease, filter .18s ease, box-shadow .18s ease;}
.btn-primary:hover{filter:brightness(.96);transform: translateY(-1px) scale(1.01);}
.btn-primary:active{transform: translateY(0);box-shadow:0 8px 18px rgba(255,136,0,.28);}
.btn-short{width: clamp(160px, 50%, 240px);}
.toast-zone{position: fixed; left: 50%; bottom: 16px; transform: translateX(-50%); z-index:1080; width:min(520px,92vw); display:flex;flex-direction:column;gap:8px;pointer-events:none;}
.toast-zone .toast{pointer-events:auto;}
@keyframes fadeUp{from{opacity:0;transform:translateY(18px) scale(.98)}to{opacity:1;transform:translateY(0) scale(1)}}
@keyframes gradientShift{0%{background-position:0% 50%}50%{background-position:100% 50%}100%{background-position:0% 50%}}
@keyframes ringPulse{0%{box-shadow:0 0 0 0 rgba(46,139,87,.35)}70%{box-shadow:0 0 0 10px rgba(46,139,87,0)}100%{box-shadow:0 0 0 0 rgba(46,139,87,0)}}
.anim{animation: fadeUp .55s ease both;}
.anim.d2{animation-delay:.08s;}
.anim.d3{animation-delay:.16s;}
.anim.d4{animation-delay:.24s;}
.otp-error{color:#d9534f;font-size:.9rem;margin-top:.25rem;text-align:center;}
.otp-success{color:#28a745;font-size:.95rem;margin-top:.25rem;text-align:center;font-weight:600;}
</style>
</head>
<body>
<div class="toast-zone" id="toastZone"></div>
<div class="card-wrap">
  <div class="card-header">
    <div class="brand anim"><span class="logo-emoji">📚</span><span class="accent">EDURAY</span></div>
    <h3 class="mt-2 mb-0 anim d2">Set New Password</h3>
    <p class="mb-0 anim d3" style="opacity:.9;">Enter your new password below</p>
  </div>
  <div class="card-body">
    <form method="POST" novalidate>
      <div class="field-wrap anim d2">
        <label class="form-label" for="new_password">New Password</label>
        <input type="password" id="new_password" name="new_password" class="form-control" placeholder="New Password" required>
      </div>
      <div class="field-wrap anim d3">
        <label class="form-label" for="confirm_password">Confirm Password</label>
        <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Confirm Password" required>
      </div>
      <?php if($error): ?>
        <div class="otp-error anim d4"><?php echo htmlspecialchars($error); ?></div>
      <?php elseif($success): ?>
        <div class="otp-success anim d4"><?php echo htmlspecialchars($success); ?></div>
      <?php endif; ?>
      <div class="text-center mt-3">
        <button type="submit" class="btn btn-primary btn-short anim d4">Reset Password</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const activeToasts = new Map();
function showToast(key,message,variant='danger',delay=3000){
  const zone=document.getElementById('toastZone');
  if(activeToasts.has(key)){activeToasts.get(key).el.querySelector('.toast-body').textContent=message;return;}
  const el=document.createElement('div');
  el.className=`toast text-bg-${variant} border-0`;
  el.role='alert';
  el.innerHTML=`<div class="d-flex"><div class="toast-body">${message}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div>`;
  zone.appendChild(el);
  const inst=new bootstrap.Toast(el,{delay});
  activeToasts.set(key,{el,inst});
  el.addEventListener('hidden.bs.toast',()=>{el.remove();activeToasts.delete(key);});
  inst.show();
}

// Client-side validation
const form = document.querySelector('form');
form.addEventListener('submit', e=>{
  const p1 = document.getElementById('new_password').value;
  const p2 = document.getElementById('confirm_password').value;
  const regex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/;
  if(p1!==p2){
    e.preventDefault();
    showToast('password','Passwords do not match');
  } else if(!regex.test(p1)){
    e.preventDefault();
    showToast('password','Password must be 8+ chars, include uppercase, lowercase, number & special char');
  }
});

// Redirect on success after 2s
<?php if($success): ?>
setTimeout(()=>{window.location.href='login.php';},2000);
<?php endif; ?>
</script>
</body>
</html>
