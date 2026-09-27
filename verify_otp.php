<?php
session_start();

// Redirect if session OTP is missing or expired
if (!isset($_SESSION['reset_otp'], $_SESSION['reset_email'], $_SESSION['reset_otp_expiry'])) {
    header("Location: forgetpassword.php?error=" . urlencode("Session ended. Please request OTP again."));
    exit;
}

if (time() > $_SESSION['reset_otp_expiry']) {
    unset($_SESSION['reset_otp'], $_SESSION['reset_email'], $_SESSION['reset_otp_expiry']);
    header("Location: forgetpassword.php?error=" . urlencode("OTP expired. Please request a new one."));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp_input = trim($_POST['otp'] ?? '');
    if ($otp_input == $_SESSION['reset_otp']) {
        $_SESSION['otp_verified'] = true; // mark OTP verified
        header("Location: reset_password.php");
        exit;
    } else {
        $error = "Invalid OTP. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>EDURAY – Verify OTP</title>
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
.field-wrap{margin-bottom:1rem;}
.field-wrap input{width:100%;height:3rem;text-align:center;font-size:1.1rem;border-radius:12px;border:1px solid rgba(46,139,87,.35);box-shadow:inset 0 1px 3px rgba(0,0,0,0.08);transition: all .25s ease;}
.field-wrap input:focus{outline:none;border-color:var(--seagreen);box-shadow:inset 0 1px 3px rgba(0,0,0,0.08),0 0 0 4px rgba(46,139,87,.25);}
.otp-error{color:#d9534f;font-size:.9rem;margin-top:.25rem;text-align:center;}
.btn-primary{background:linear-gradient(135deg,var(--gold),#ff8800);color:#222;border:none;border-radius:14px;padding:10px 14px;font-size:.95rem;font-weight:600;line-height:1.1;box-shadow:0 10px 22px rgba(255,136,0,.32);transition: transform .18s ease, filter .18s ease, box-shadow .18s ease;}
.btn-primary:hover{filter:brightness(.96);transform: translateY(-1px) scale(1.01);}
.btn-primary:active{transform: translateY(0);box-shadow:0 8px 18px rgba(255,136,0,.28);}
.btn-short{width: clamp(160px, 50%, 240px);}
.toast-zone{position: fixed; left: 50%; bottom: 16px; transform: translateX(-50%); z-index:1080; width:min(520px,92vw); display:flex;flex-direction:column;gap:8px;pointer-events:none;}
.toast-zone .toast{pointer-events:auto;}
@keyframes fadeUp{from{opacity:0;transform:translateY(18px) scale(.98)}to{opacity:1;transform:translateY(0) scale(1)}}
@keyframes gradientShift{0%{background-position:0% 50%}50%{background-position:100% 50%}100%{background-position:0% 50%}}
.anim{animation: fadeUp .55s ease both;}
.anim.d2{animation-delay:.08s;}
.anim.d3{animation-delay:.16s;}
</style>
</head>
<body>
<div class="toast-zone" id="toastZone"></div>
<div class="card-wrap">
  <div class="card-header">
    <div class="brand anim"><span class="logo-emoji">📚</span><span class="accent">EDURAY</span></div>
    <h3 class="mt-2 mb-0 anim d2">Verify OTP</h3>
    <p class="mb-0 anim d3" style="opacity:.9;">Enter the OTP sent to your email</p>
  </div>
  <div class="card-body">
    <form id="otpForm" method="POST" novalidate>
      <div class="field-wrap anim d2">
        <input type="tel" inputmode="numeric" name="otp" maxlength="6" class="form-control" placeholder="Enter 6-digit OTP" required>
      </div>
      <?php if($error): ?>
      <div class="otp-error anim d3"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>
      <div class="text-center mt-3">
        <button type="submit" class="btn btn-primary btn-short anim d3">Verify OTP</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const activeToasts = new Map();
function showToast(key,message,variant='danger',delay=3000){
  const zone=document.getElementById('toastZone');
  if(activeToasts.has(key)){ activeToasts.get(key).el.querySelector('.toast-body').textContent=message; return; }
  const el=document.createElement('div');
  el.className=`toast text-bg-${variant} border-0`;
  el.role='alert';
  el.innerHTML=`<div class="d-flex"><div class="toast-body">${message}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div>`;
  zone.appendChild(el);
  const inst=new bootstrap.Toast(el,{delay});
  activeToasts.set(key,{el,inst});
  el.addEventListener('hidden.bs.toast',()=>{el.remove(); activeToasts.delete(key);});
  inst.show();
}

// Form validation (client-side optional)
document.getElementById('otpForm').addEventListener('submit', e=>{
  const val=document.querySelector('input[name="otp"]').value.trim();
  if(val.length !== 6){ 
    e.preventDefault();
    showToast('otp',"Enter a valid 6-digit OTP");
  }
});

// Show server-side invalid OTP error
<?php if($error): ?>
showToast('server', <?php echo json_encode($error); ?>, 'danger', 4000);
<?php endif; ?>
</script>
</body>
</html>
