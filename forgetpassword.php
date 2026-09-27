<!-- forgetpassword.php -->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>EDURAY – Forgot Password</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    /* Same EDURAY styles as login.php */
    :root{--primary:#5f259f;--accent:#ff6b00;--gold:#ffe600;--bg:#fdfaf6;--seagreen:#2e8b57;}
    *{box-sizing:border-box}
    body{margin:0;font-family:'Poppins',sans-serif;background:linear-gradient(135deg,#fdfaf6,#fff);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;overflow-x:hidden;}
    .card-wrap{width:100%;max-width:520px;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 20px 50px rgba(0,0,0,.12);border:1px solid rgba(95,37,159,.08);animation:fadeUp .6s ease-out both;}
    .card-header{background:linear-gradient(135deg,var(--primary),var(--accent));background-size:200% 200%;animation:gradientShift 8s ease infinite;color:#fff;padding:28px 24px;}
    .brand{font-weight:700;letter-spacing:.5px;display:flex;align-items:center;gap:10px;font-size: clamp(24px, 3vw, 36px);line-height:1.05;}
    .brand .logo-emoji{display:inline-block;font-size:1.1em;transform:translateY(2px);}
    .brand .accent{color:var(--gold);}
    .card-body{padding:28px;}
    .field-wrap{position:relative;border-radius:16px;}
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
    .small-link a{color:var(--accent);text-decoration:none;}
    .small-link a:hover{text-decoration:underline;}
    .toast-zone{position: fixed; left: 50%; bottom: 16px; transform: translateX(-50%); z-index:1080; width:min(520px,92vw); display:flex;flex-direction:column;gap:8px;pointer-events:none;}
    .toast-zone .toast{pointer-events:auto;}
    @keyframes fadeUp{from{opacity:0;transform:translateY(18px) scale(.98)}to{opacity:1;transform:translateY(0) scale(1)}}
    @keyframes gradientShift{0%{background-position:0% 50%}50%{background-position:100% 50%}100%{background-position:0% 50%}}
    @keyframes ringPulse{0%{box-shadow:0 0 0 0 rgba(46,139,87,.35)}70%{box-shadow:0 0 0 10px rgba(46,139,87,0)}100%{box-shadow:0 0 0 0 rgba(46,139,87,0)}}
    .anim{animation: fadeUp .55s ease both;}
    .anim.d2{animation-delay:.08s;}
    .anim.d3{animation-delay:.16s;}
    .anim.d4{animation-delay:.24s;}
  </style>
</head>
<body>
  <div class="toast-zone" id="toastZone"></div>

  <div class="card-wrap">
    <div class="card-header">
      <div class="brand anim"><span class="logo-emoji">📚</span><span class="accent">EDURAY</span></div>
      <h3 class="mt-2 mb-0 anim d2">Forgot Password?</h3>
      <p class="mb-0 anim d3" style="opacity:.9;">Enter your email to reset your password</p>
    </div>
    <div class="card-body">
      <form id="resetForm" method="POST" action="send_reset_link.php" novalidate>
        <div class="mb-3 anim d2 field-wrap">
          <label class="form-label" for="email">Email</label>
          <input type="email" id="email" name="email" class="form-control" placeholder="Enter your registered email" required>
        </div>
        <div class="text-center">
          <button type="submit" class="btn btn-primary btn-short anim d3">Send OTP</button>
        </div>
      </form>
      <p class="text-center mt-3 small-link anim d4">
        Remember your password? <a href="login.php">Sign In</a>
      </p>
    </div>
  </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const emailRegex = /^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/;
const activeToasts = new Map();
function showToast(key, message, variant='danger', delay=3000){
  const zone = document.getElementById('toastZone');
  if(activeToasts.has(key)){ activeToasts.get(key).el.querySelector('.toast-body').textContent = message; return; }
  const el = document.createElement('div');
  el.className = `toast text-bg-${variant} border-0`;
  el.role='alert';
  el.innerHTML=`<div class="d-flex"><div class="toast-body">${message}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div>`;
  zone.appendChild(el);
  const inst = new bootstrap.Toast(el,{delay});
  activeToasts.set(key,{el,inst});
  el.addEventListener('hidden.bs.toast',()=>{el.remove();activeToasts.delete(key);});
  inst.show();
}
function hideToast(key){ if(activeToasts.has(key)) activeToasts.get(key).inst.hide(); }

document.getElementById('resetForm').addEventListener('submit',(e)=>{
  e.preventDefault();
  const val = document.getElementById('email').value.trim();
  if(!emailRegex.test(val)){ showToast('email',"Please enter a valid email address."); return; }
  e.target.submit();
});

(function(){
  const p = new URLSearchParams(location.search);
  if(p.get('error')) showToast('server',decodeURIComponent(p.get('error')),'danger',4000);
  if(p.get('msg')) showToast('server',decodeURIComponent(p.get('msg')),'success',4000);
})();
</script>
</body>
</html>
