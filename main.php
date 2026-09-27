<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>EDURAY — Create Account</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,600;0,9..144,700;1,9..144,500&display=swap" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{
    --primary:#4a1a8c; --primary-mid:#6930c3; --primary-light:#ede6ff;
    --accent:#f5622e; --accent-light:#fff1ec; --gold:#f5c518; --gold-light:#fffbea;
    --bg:#f3f0fa; --surface:#fff; --border:rgba(74,26,140,.12); --border-mid:rgba(74,26,140,.2);
    --text:#1a1030; --text-secondary:#4a4466; --text-muted:#8b87a8;
    --error:#dc2626; --success:#16a34a;
    --r-md:14px; --r-lg:20px; --r-xl:28px; --r-full:9999px;
    --ease-out:cubic-bezier(.16,1,.3,1);
}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{
    margin:0; font-family:'DM Sans',sans-serif;
    min-height:100vh; background:var(--bg);
    display:flex; -webkit-font-smoothing:antialiased;
}
h1,h2,h3,h4,h5,h6{font-family:'Fraunces',serif;font-weight:600}
a{text-decoration:none}

/* Layout */
.auth-layout{display:flex;width:100%;min-height:100vh}

/* Left panel */
.auth-panel-left{
    flex:1; display:none;
    background:linear-gradient(155deg,#1e0a40 0%,#4a1a8c 45%,#6930c3 100%);
    position:relative; overflow:hidden; padding:60px 50px;
    flex-direction:column; justify-content:space-between;
}
@media(min-width:1024px){.auth-panel-left{display:flex}}
.auth-panel-left::before{content:'';position:absolute;top:-120px;right:-120px;width:500px;height:500px;border-radius:50%;background:radial-gradient(circle,rgba(245,197,24,.12) 0%,transparent 70%);pointer-events:none}
.auth-panel-left::after{content:'';position:absolute;bottom:-180px;left:-80px;width:450px;height:450px;border-radius:50%;background:radial-gradient(circle,rgba(245,98,46,.1) 0%,transparent 70%);pointer-events:none}
.left-brand{position:relative;z-index:1}
.left-brand .logo{font-family:'Fraunces',serif;font-size:26px;font-weight:700;color:var(--gold);display:flex;align-items:center;gap:12px;margin-bottom:36px}
.left-brand .logo-icon{width:42px;height:42px;background:linear-gradient(135deg,var(--gold),#f59300);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px}
.left-brand h2{font-size:36px;font-weight:700;color:#fff;line-height:1.22;margin-bottom:14px}
.left-brand h2 em{color:var(--gold);font-style:italic}
.left-brand p{font-size:15px;color:rgba(255,255,255,.7);line-height:1.7;max-width:360px}
.left-steps{position:relative;z-index:1;display:flex;flex-direction:column;gap:0}
.left-step{display:flex;align-items:flex-start;gap:16px;padding:16px 0;border-bottom:1px solid rgba(255,255,255,.07)}
.left-step:last-child{border-bottom:none}
.left-step-num{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--gold),#f59300);color:#1a1030;font-weight:800;font-size:13px;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:2px}
.left-step-text strong{display:block;font-size:14px;font-weight:600;color:#fff;margin-bottom:2px}
.left-step-text span{font-size:12.5px;color:rgba(255,255,255,.55)}
.left-bottom{position:relative;z-index:1;font-size:13px;color:rgba(255,255,255,.4)}

/* Right form panel */
.auth-panel-right{
    width:100%; max-width:580px; margin:0 auto;
    display:flex; align-items:flex-start; justify-content:center;
    padding:40px 24px; overflow-y:auto;
}
@media(min-width:1024px){.auth-panel-right{width:520px;max-width:520px;margin:0;flex-shrink:0;align-items:center}}

.auth-box{width:100%;max-width:460px;padding:8px 0}
.auth-box-header{margin-bottom:28px}
.back-link{display:inline-flex;align-items:center;gap:6px;font-size:13px;color:var(--text-muted);margin-bottom:22px;transition:color .15s;text-decoration:none}
.back-link:hover{color:var(--primary)}
.auth-box-header h1{font-size:28px;font-weight:700;color:var(--text);margin-bottom:5px}
.auth-box-header p{font-size:14.5px;color:var(--text-secondary)}

/* Role tabs */
.role-tabs{display:flex;background:var(--primary-light);border-radius:var(--r-full);padding:4px;margin-bottom:24px;gap:4px}
.role-tab{flex:1;text-align:center;padding:8px 6px;font-size:13px;font-weight:600;color:var(--text-muted);border-radius:var(--r-full);cursor:pointer;text-decoration:none;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:6px}
.role-tab.active{background:#fff;color:var(--primary);box-shadow:0 2px 8px rgba(74,26,140,.15)}
.role-tab:hover:not(.active){color:var(--primary)}

/* Form Fields */
.field-group{margin-bottom:16px}
.field-label{display:block;font-weight:600;color:var(--text);font-size:13.5px;margin-bottom:6px}
.field-label .req{color:var(--error)}
.field-input{
    width:100%; padding:11px 15px;
    border:1.5px solid var(--border); border-radius:var(--r-md);
    font-family:'DM Sans',sans-serif; font-size:14.5px; color:var(--text);
    background:#fff; transition:border-color .2s,box-shadow .2s; outline:none;
}
.field-input::placeholder{color:var(--text-muted)}
.field-input:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(74,26,140,.1)}
.field-input.is-error{border-color:var(--error);box-shadow:0 0 0 3px rgba(220,38,38,.1)}
.field-input.is-success{border-color:var(--success);box-shadow:0 0 0 3px rgba(22,163,74,.1)}
.field-input.has-right{padding-right:46px}
.pw-wrap{position:relative;display:flex;align-items:center}
.pw-wrap .field-input{width:100%}
.toggle-password{position:absolute;right:13px;top:50%;transform:translateY(-50%);cursor:pointer;color:var(--text-muted);font-size:15px;transition:color .15s;background:none;border:none;outline:none;padding:4px;display:flex;align-items:center}
.toggle-password:hover{color:var(--primary)}
.field-hint{font-size:12px;margin-top:5px;display:flex;align-items:center;gap:5px;color:var(--text-muted)}
.field-hint.hint-error{color:var(--error)}
.field-hint.hint-success{color:var(--success)}
.pw-strength{height:3px;background:#e9e5f5;border-radius:3px;margin-top:6px;overflow:hidden;display:none}
.pw-strength.show{display:block}
.pw-bar{height:100%;width:0;transition:width .3s,background .3s;border-radius:3px}
.pw-bar.weak{background:var(--error);width:33%}
.pw-bar.medium{background:var(--gold);width:66%}
.pw-bar.strong{background:var(--success);width:100%}

/* Submit */
.btn-submit{
    width:100%; padding:13px;
    border-radius:var(--r-full);
    background:linear-gradient(135deg,var(--primary),var(--primary-mid));
    color:#fff; border:none; font-family:'DM Sans',sans-serif;
    font-weight:700; font-size:15px; cursor:pointer;
    box-shadow:0 8px 24px rgba(74,26,140,.3);
    transition:transform .2s var(--ease-out),box-shadow .2s,filter .2s;
    margin-top:4px;
}
.btn-submit:hover{transform:translateY(-2px) scale(1.01);box-shadow:0 14px 36px rgba(74,26,140,.4);filter:brightness(1.06)}

/* Terms */
.terms-toggle{font-size:13px;color:var(--primary);font-weight:500;cursor:pointer;user-select:none;display:inline-flex;align-items:center;gap:5px;margin-bottom:6px}
.terms-box{background:var(--primary-light);border-radius:var(--r-md);padding:14px 16px;font-size:13px;line-height:1.65;color:var(--text-secondary);margin-bottom:14px;display:none}
.terms-box.open{display:block}
.terms-box ul{margin:0;padding-left:18px}
.terms-box li{margin-bottom:5px}

/* Checkbox */
.check-group{display:flex;align-items:center;gap:10px;margin-bottom:16px}
.check-group input[type=checkbox]{width:17px;height:17px;accent-color:var(--primary);flex-shrink:0;cursor:pointer}
.check-group label{font-size:13.5px;color:var(--text-secondary);cursor:pointer}
.check-group label a{color:var(--primary);font-weight:600}

.auth-footer-link{text-align:center;margin-top:20px;font-size:14px;color:var(--text-muted)}
.auth-footer-link a{color:var(--primary);font-weight:700}
.auth-footer-link a:hover{text-decoration:underline}

/* Panel */
.signup-panel{display:none}
.signup-panel.active{display:block}
</style>
</head>
<body>
<div class="auth-layout">

  <!-- Left Panel -->
  <div class="auth-panel-left">
    <div class="left-brand">
      <div class="logo">
        <div class="logo-icon">📚</div>
        EDURAY
      </div>
      <h2>Start Your<br><em>Learning Journey</em><br>Today</h2>
      <p>Join thousands of learners already transforming their careers with EDURAY.</p>
    </div>
    <div class="left-steps">
      <div class="left-step">
        <div class="left-step-num">1</div>
        <div class="left-step-text">
          <strong>Create your free account</strong>
          <span>Takes less than 2 minutes — no credit card needed</span>
        </div>
      </div>
      <div class="left-step">
        <div class="left-step-num">2</div>
        <div class="left-step-text">
          <strong>Browse 500+ courses</strong>
          <span>Expert-designed programs across every skill area</span>
        </div>
      </div>
      <div class="left-step">
        <div class="left-step-num">3</div>
        <div class="left-step-text">
          <strong>Learn and earn certificates</strong>
          <span>Track progress, pass quizzes, get certified</span>
        </div>
      </div>
    </div>
    <div class="left-bottom">© <?php echo date('Y'); ?> EDURAY. All rights reserved.</div>
  </div>

  <!-- Right form -->
  <div class="auth-panel-right">
    <div class="auth-box">
      <div class="auth-box-header">
        <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to home</a>
        <h1>Create Account</h1>
        <p>Already a member? <a href="login.php" style="color:var(--primary);font-weight:600;">Sign in here</a></p>
      </div>

      <div class="role-tabs">
        <a href="main.php" class="role-tab active"><i class="fas fa-user-graduate"></i> Student</a>
        <a href="instructor_register.php" class="role-tab"><i class="fas fa-chalkboard-teacher"></i> Instructor</a>
      </div>

      <form id="signupForm" method="POST" action="register.php" novalidate>
        <div class="field-group">
          <label class="field-label">Full Name <span class="req">*</span></label>
          <input type="text" class="field-input" name="name" id="sName" required placeholder="Enter your full name">
        </div>
        <div class="field-group">
          <label class="field-label">Email Address <span class="req">*</span></label>
          <input type="email" class="field-input" name="email" id="sEmail" required placeholder="name@example.com">
          <div class="field-hint" id="sEmailHint"><i class="fas fa-info-circle"></i> We'll send a verification link here</div>
        </div>
        <div class="field-group">
          <label class="field-label">Mobile Number <span class="req">*</span></label>
          <input type="tel" class="field-input" name="mobile" id="sMobile" required placeholder="01712345678">
        </div>
        <div class="field-group">
          <label class="field-label">Password <span class="req">*</span></label>
          <div class="pw-wrap">
            <input type="password" id="sPassword" class="field-input has-right" name="password" required placeholder="Create a strong password">
            <button type="button" class="toggle-password" onclick="togglePass('sPassword',this)"><i class="fas fa-eye"></i></button>
          </div>
          <div class="pw-strength" id="sStrength"><div class="pw-bar" id="sStrengthBar"></div></div>
          <div class="field-hint" id="sPasswordHint"><i class="fas fa-info-circle"></i> Min 8 chars: uppercase, lowercase, number & special (@$!%*?&)</div>
        </div>
        <div class="field-group">
          <label class="field-label">Confirm Password <span class="req">*</span></label>
          <div class="pw-wrap">
            <input type="password" id="sConfirm" class="field-input has-right" name="confirm" required placeholder="Re-enter your password">
            <button type="button" class="toggle-password" onclick="togglePass('sConfirm',this)"><i class="fas fa-eye"></i></button>
          </div>
          <div class="field-hint" id="sConfirmHint"><i class="fas fa-info-circle"></i> Must match the password above</div>
        </div>

        <div class="check-group">
          <input type="checkbox" id="sTerms" required>
          <label for="sTerms">I agree to the <a href="#" onclick="toggleTerms();return false;">Terms &amp; Conditions</a></label>
        </div>
        <div class="terms-box" id="termsBox">
          <ul>
            <li>You must provide accurate and complete registration information.</li>
            <li>Your account credentials must be kept confidential.</li>
            <li>EDURAY content is for personal, non-commercial use only.</li>
            <li>We may suspend accounts for violations or misuse.</li>
            <li>Your data is handled according to our Privacy Policy.</li>
          </ul>
        </div>

        <button type="submit" class="btn-submit"><i class="fas fa-user-plus" style="margin-right:7px"></i>Create Student Account</button>
      </form>

      <div class="auth-footer-link">Already a member? <a href="login.php">Sign in here</a></div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePass(id, btn) {
  const input = document.getElementById(id);
  const icon = btn.querySelector('i');
  if (input.type === 'password') { input.type='text'; icon.className='fas fa-eye-slash'; }
  else { input.type='password'; icon.className='fas fa-eye'; }
}
function toggleTerms() {
  document.getElementById('termsBox').classList.toggle('open');
}
function setHint(id, cls, icon, msg) {
  const h = document.getElementById(id);
  h.className = 'field-hint' + (cls ? ' ' + cls : '');
  h.innerHTML = `<i class="fas fa-${icon}"></i> ${msg}`;
}
function setField(id, state) {
  const el = document.getElementById(id);
  el.classList.remove('is-error','is-success');
  if (state) el.classList.add('is-' + state);
}
const emailPat = /^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/;
const passPat  = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;

function strengthCheck(p) {
  let s = 0;
  if (p.length >= 8) s++; if (/[a-z]/.test(p)) s++; if (/[A-Z]/.test(p)) s++;
  if (/\d/.test(p)) s++; if (/[@$!%*?&]/.test(p)) s++;
  const w = document.getElementById('sStrength'), b = document.getElementById('sStrengthBar');
  if (p) { w.classList.add('show'); b.className='pw-bar'; b.classList.add(s<=2?'weak':s<=4?'medium':'strong'); }
  else w.classList.remove('show');
}
function checkConfirm() {
  const p = document.getElementById('sPassword').value, c = document.getElementById('sConfirm');
  if (!c.value) { setField('sConfirm',''); setHint('sConfirmHint','','info-circle','Must match the password above'); return; }
  if (c.value === p) { setField('sConfirm','success'); setHint('sConfirmHint','hint-success','check-circle','Passwords match!'); }
  else { setField('sConfirm','error'); setHint('sConfirmHint','hint-error','exclamation-circle','Passwords do not match'); }
}
document.getElementById('sEmail').addEventListener('input', function() {
  if (!this.value) { setField('sEmail',''); setHint('sEmailHint','','info-circle',"We'll send a verification link here"); return; }
  if (emailPat.test(this.value)) { setField('sEmail','success'); setHint('sEmailHint','hint-success','check-circle','Valid email!'); }
  else { setField('sEmail','error'); setHint('sEmailHint','hint-error','exclamation-circle','Invalid email format'); }
});
document.getElementById('sPassword').addEventListener('input', function() {
  strengthCheck(this.value);
  if (!this.value) { setField('sPassword',''); setHint('sPasswordHint','','info-circle','Min 8 chars: uppercase, lowercase, number & special'); return; }
  if (passPat.test(this.value)) { setField('sPassword','success'); setHint('sPasswordHint','hint-success','check-circle','Strong password!'); }
  else { setField('sPassword','error'); setHint('sPasswordHint','hint-error','exclamation-circle','Need: 8+ chars, uppercase, lowercase, number, special'); }
  if (document.getElementById('sConfirm').value) checkConfirm();
});
document.getElementById('sConfirm').addEventListener('input', checkConfirm);
document.getElementById('signupForm').addEventListener('submit', function(e) {
  let ok = true;
  const n=document.getElementById('sName'), em=document.getElementById('sEmail'),
        m=document.getElementById('sMobile'), p=document.getElementById('sPassword'), c=document.getElementById('sConfirm');
  if (!n.value.trim()) { setField('sName','error'); ok=false; } else setField('sName','success');
  if (!emailPat.test(em.value)) { setField('sEmail','error'); setHint('sEmailHint','hint-error','exclamation-circle','Valid email required'); ok=false; }
  if (!/^[0-9+\-\s]{7,15}$/.test(m.value)) { setField('sMobile','error'); ok=false; } else setField('sMobile','success');
  if (!passPat.test(p.value)) { setField('sPassword','error'); setHint('sPasswordHint','hint-error','exclamation-circle','Password does not meet requirements'); ok=false; }
  if (p.value !== c.value) { setField('sConfirm','error'); setHint('sConfirmHint','hint-error','exclamation-circle','Passwords do not match'); ok=false; }
  if (!document.getElementById('sTerms').checked) { alert('Please agree to the Terms & Conditions'); ok=false; }
  if (!ok) { e.preventDefault(); const f=document.querySelector('.field-input.is-error'); if(f){f.scrollIntoView({behavior:'smooth',block:'center'});f.focus();} }
});
</script>
</body>
</html>
