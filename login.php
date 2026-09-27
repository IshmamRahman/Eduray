<?php
session_start();
if (!empty($_SESSION['user_id'])) {
    if (!empty($_SESSION['is_admin']))      { header('Location: admin_dashboard.php');      exit; }
    if (!empty($_SESSION['is_instructor'])) { header('Location: instructor_dashboard.php'); exit; }
    header('Location: index.php'); exit;
}
$error        = htmlspecialchars($_GET['error'] ?? '');
$prefillEmail = htmlspecialchars($_GET['email'] ?? '');
$rawError     = $_GET['error'] ?? '';
$isInfo       = str_contains($rawError,'verify') || str_contains($rawError,'verification') || str_contains($rawError,'emailed') || str_contains($rawError,'sent');
$role         = $_GET['role'] ?? 'student';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>EDURAY — Sign In</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,600;0,9..144,700;1,9..144,500&display=swap" rel="stylesheet"/>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{
    --primary:#4a1a8c; --primary-mid:#6930c3; --primary-light:#ede6ff;
    --accent:#f5622e; --gold:#f5c518; --gold-light:#fffbea;
    --bg:#f3f0fa; --surface:#fff; --border:rgba(74,26,140,.12);
    --text:#1a1030; --text-secondary:#4a4466; --text-muted:#8b87a8;
    --error:#dc2626; --success:#16a34a;
    --r-md:14px; --r-lg:20px; --r-xl:28px; --r-full:9999px;
    --ease-out:cubic-bezier(.16,1,.3,1);
}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{
    margin:0; font-family:'DM Sans',sans-serif;
    min-height:100vh; display:flex;
    background:var(--bg);
    -webkit-font-smoothing:antialiased;
    overflow:hidden;
}
h1,h2,h3,h4,h5,h6{font-family:'Fraunces',serif;font-weight:600}

/* Two-column layout */
.auth-layout{display:flex;width:100%;min-height:100vh}
.auth-panel-left{
    flex:1; display:none;
    background:linear-gradient(155deg,#1e0a40 0%,#4a1a8c 45%,#6930c3 100%);
    position:relative; overflow:hidden; padding:60px 50px;
    flex-direction:column; justify-content:space-between;
}
@media(min-width:1024px){.auth-panel-left{display:flex}}
.auth-panel-left::before{
    content:''; position:absolute; top:-120px; right:-120px;
    width:500px; height:500px; border-radius:50%;
    background:radial-gradient(circle,rgba(245,197,24,.12) 0%,transparent 70%);
    pointer-events:none;
}
.auth-panel-left::after{
    content:''; position:absolute; bottom:-180px; left:-80px;
    width:450px; height:450px; border-radius:50%;
    background:radial-gradient(circle,rgba(245,98,46,.1) 0%,transparent 70%);
    pointer-events:none;
}
.left-brand{position:relative;z-index:1}
.left-brand .logo{font-family:'Fraunces',serif;font-size:28px;font-weight:700;color:var(--gold);display:flex;align-items:center;gap:12px;margin-bottom:40px}
.left-brand .logo-icon{width:44px;height:44px;background:linear-gradient(135deg,var(--gold),#f59300);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:22px}
.left-brand h2{font-size:38px;font-weight:700;color:#fff;line-height:1.2;margin-bottom:16px}
.left-brand h2 em{color:var(--gold);font-style:italic}
.left-brand p{font-size:16px;color:rgba(255,255,255,.7);line-height:1.7;max-width:360px}
.left-features{position:relative;z-index:1;display:flex;flex-direction:column;gap:16px}
.left-feature{display:flex;align-items:center;gap:14px;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.1);border-radius:14px;padding:14px 18px}
.left-feature-icon{width:40px;height:40px;border-radius:12px;background:rgba(255,255,255,.12);display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.left-feature-text strong{display:block;font-size:14px;font-weight:600;color:#fff;margin-bottom:2px}
.left-feature-text span{font-size:12.5px;color:rgba(255,255,255,.6)}
.left-bottom{position:relative;z-index:1;font-size:13px;color:rgba(255,255,255,.4)}

.auth-panel-right{
    width:100%; max-width:520px; margin:0 auto;
    display:flex; align-items:center; justify-content:center;
    padding:40px 24px; overflow-y:auto;
}
@media(min-width:1024px){.auth-panel-right{width:480px;max-width:480px;margin:0;flex-shrink:0}}

.auth-box{width:100%;max-width:420px}
.auth-box-header{margin-bottom:32px}
.auth-box-header .back-link{display:inline-flex;align-items:center;gap:6px;font-size:13px;color:var(--text-muted);margin-bottom:24px;transition:color .15s;text-decoration:none}
.auth-box-header .back-link:hover{color:var(--primary)}
.auth-box-header h1{font-size:30px;font-weight:700;color:var(--text);margin-bottom:6px}
.auth-box-header p{font-size:15px;color:var(--text-secondary)}

/* Role tabs */
.role-tabs{display:flex;background:var(--primary-light);border-radius:var(--r-full);padding:4px;margin-bottom:28px;gap:4px}
.role-tab{flex:1;text-align:center;padding:9px 8px;font-size:13px;font-weight:600;color:var(--text-muted);border-radius:var(--r-full);cursor:pointer;text-decoration:none;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:6px}
.role-tab.active{background:#fff;color:var(--primary);box-shadow:0 2px 8px rgba(74,26,140,.15)}
.role-tab:hover:not(.active){color:var(--primary)}

/* Alerts */
.alert-err{background:#fef2f2;border:1px solid #fca5a5;color:#dc2626;border-radius:var(--r-md);padding:12px 16px;font-size:13.5px;margin-bottom:20px;display:flex;align-items:center;gap:8px}
.alert-info{background:#f0fdf4;border:1px solid #86efac;color:#15803d;border-radius:var(--r-md);padding:12px 16px;font-size:13.5px;margin-bottom:20px;display:flex;align-items:center;gap:8px}

/* Form */
.field-group{margin-bottom:18px}
.field-label{display:block;font-weight:600;color:var(--text);font-size:14px;margin-bottom:7px}
.field-input{
    width:100%; padding:12px 16px;
    border:1.5px solid var(--border);
    border-radius:var(--r-md);
    font-family:'DM Sans',sans-serif;
    font-size:15px; color:var(--text);
    background:#fff;
    transition:border-color .2s,box-shadow .2s;
    outline:none;
}
.field-input::placeholder{color:var(--text-muted)}
.field-input:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(74,26,140,.12)}
.field-input.has-right{padding-right:46px}
.field-wrap{position:relative}
.toggle-password{position:absolute;right:14px;top:50%;transform:translateY(-50%);cursor:pointer;color:var(--text-muted);font-size:16px;transition:color .15s;background:none;border:none;outline:none;padding:4px}
.toggle-password:hover{color:var(--primary)}

.btn-submit{
    width:100%; padding:13px;
    border-radius:var(--r-full);
    background:linear-gradient(135deg,var(--primary),var(--primary-mid));
    color:#fff; border:none; font-family:'DM Sans',sans-serif;
    font-weight:700; font-size:15px; cursor:pointer;
    box-shadow:0 8px 24px rgba(74,26,140,.3);
    transition:transform .2s var(--ease-out),box-shadow .2s,filter .2s;
    letter-spacing:0.2px;
}
.btn-submit:hover{transform:translateY(-2px) scale(1.01);box-shadow:0 14px 36px rgba(74,26,140,.4);filter:brightness(1.06)}
.btn-submit:active{transform:translateY(0) scale(1)}

.auth-footer-link{text-align:center;margin-top:22px;font-size:14px;color:var(--text-muted)}
.auth-footer-link a{color:var(--primary);font-weight:700;text-decoration:none}
.auth-footer-link a:hover{text-decoration:underline}
.forgot-link{text-align:right;margin-top:-10px;margin-bottom:18px}
.forgot-link a{font-size:13px;color:var(--primary);font-weight:500;text-decoration:none}
.forgot-link a:hover{text-decoration:underline}

/* Panel show/hide */
.login-panel{display:none}
.login-panel.active{display:block}

/* Divider */
.auth-divider{display:flex;align-items:center;gap:12px;margin:20px 0;color:var(--text-muted);font-size:13px}
.auth-divider::before,.auth-divider::after{content:'';flex:1;height:1px;background:var(--border)}
</style>
</head>
<body>

<div class="auth-layout">
  <!-- Left decorative panel -->
  <div class="auth-panel-left">
    <div class="left-brand">
      <div class="logo">
        <div class="logo-icon">📚</div>
        EDURAY
      </div>
      <h2>Welcome back to<br><em>Your Learning Hub</em></h2>
      <p>Pick up right where you left off. Your courses, progress, and community are all waiting for you.</p>
    </div>
    <div class="left-features">
      <div class="left-feature">
        <div class="left-feature-icon">🎓</div>
        <div class="left-feature-text">
          <strong>500+ Courses</strong>
          <span>Expert-designed curriculum across all skills</span>
        </div>
      </div>
      <div class="left-feature">
        <div class="left-feature-icon">🏆</div>
        <div class="left-feature-text">
          <strong>Earn Certificates</strong>
          <span>Industry-recognized credentials for your career</span>
        </div>
      </div>
      <div class="left-feature">
        <div class="left-feature-icon">🌐</div>
        <div class="left-feature-text">
          <strong>Live Community</strong>
          <span>Connect with 10,000+ learners worldwide</span>
        </div>
      </div>
    </div>
    <div class="left-bottom">© <?php echo date('Y'); ?> EDURAY. All rights reserved.</div>
  </div>

  <!-- Right form panel -->
  <div class="auth-panel-right">
    <div class="auth-box">
      <div class="auth-box-header">
        <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to home</a>
        <h1>Sign In</h1>
        <p>Don't have an account? <a href="main.php" style="color:var(--primary);font-weight:600;">Create one free</a></p>
      </div>

      <!-- Role tabs -->
      <div class="role-tabs">
        <a href="#" class="role-tab student <?= $role === 'student' ? 'active' : '' ?>" onclick="switchRole('student');return false;">
          <i class="fas fa-user-graduate"></i> Student
        </a>
        <a href="#" class="role-tab instructor <?= $role === 'instructor' ? 'active' : '' ?>" onclick="switchRole('instructor');return false;">
          <i class="fas fa-chalkboard-teacher"></i> Instructor
        </a>
      </div>

      <?php if ($error): ?>
        <?php if ($isInfo): ?>
          <div class="alert-info"><i class="fas fa-envelope-circle-check"></i> <?= $error ?></div>
        <?php else: ?>
          <div class="alert-err"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
        <?php endif; ?>
      <?php endif; ?>

      <!-- Student form -->
      <div class="login-panel <?= $role === 'student' ? 'active' : '' ?>" id="panel-student">
        <form method="POST" action="login_action.php">
          <input type="hidden" name="role" value="student">
          <div class="field-group">
            <label class="field-label">Email Address</label>
            <input type="email" name="email" class="field-input" placeholder="you@example.com" value="<?= $prefillEmail ?>" required autocomplete="email">
          </div>
          <div class="field-group">
            <label class="field-label">Password</label>
            <div class="field-wrap">
              <input type="password" id="passStudent" name="password" class="field-input has-right" placeholder="Enter your password" required>
              <button type="button" class="toggle-password" onclick="togglePass('passStudent',this)">
                <i class="fas fa-eye"></i>
              </button>
            </div>
          </div>
          <div class="forgot-link"><a href="forgetpassword.php">Forgot password?</a></div>
          <button type="submit" class="btn-submit"><i class="fas fa-sign-in-alt" style="margin-right:6px"></i>Sign In as Student</button>
        </form>
      </div>

      <!-- Instructor form -->
      <div class="login-panel <?= $role === 'instructor' ? 'active' : '' ?>" id="panel-instructor">
        <form method="POST" action="login_action.php">
          <input type="hidden" name="role" value="instructor">
          <div class="field-group">
            <label class="field-label">Instructor Email</label>
            <input type="email" name="email" class="field-input" placeholder="instructor@example.com" value="<?= $prefillEmail ?>" required autocomplete="email">
          </div>
          <div class="field-group">
            <label class="field-label">Password</label>
            <div class="field-wrap">
              <input type="password" id="passInstructor" name="password" class="field-input has-right" placeholder="Enter your password" required>
              <button type="button" class="toggle-password" onclick="togglePass('passInstructor',this)">
                <i class="fas fa-eye"></i>
              </button>
            </div>
          </div>
          <div class="forgot-link"><a href="forgetpassword.php">Forgot password?</a></div>
          <button type="submit" class="btn-submit"><i class="fas fa-chalkboard-teacher" style="margin-right:6px"></i>Sign In as Instructor</button>
        </form>
      </div>

      <div class="auth-footer-link">New to EDURAY? <a href="main.php">Create a free account</a></div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function switchRole(role) {
  document.querySelectorAll('.role-tab').forEach(c => c.classList.remove('active'));
  document.querySelectorAll('.login-panel').forEach(p => p.classList.remove('active'));
  document.querySelector('.role-tab.'+role).classList.add('active');
  document.getElementById('panel-'+role).classList.add('active');
}
function togglePass(id, btn) {
  const input = document.getElementById(id);
  const icon = btn.querySelector('i');
  if (input.type === 'password') {
    input.type = 'text';
    icon.className = 'fas fa-eye-slash';
  } else {
    input.type = 'password';
    icon.className = 'fas fa-eye';
  }
}
</script>
</body>
</html>
