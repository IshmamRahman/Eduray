<?php
// navbar.php — shared navbar for all student-facing pages
// Include this file after session_start() and require db.php
?>

<?php
// Fetch profile data if user is logged in
$_nav_name = '';
$_nav_email = '';
$_nav_pic = '';
$_nav_enrolled = 0;
$_nav_completed = 0;

if (!empty($_SESSION['user_id']) && isset($conn)) {
    $_uid = (int) $_SESSION['user_id'];
    $_r = $conn->query("SELECT name, email, profile_pic FROM users WHERE id = $_uid LIMIT 1");
    if ($_r && $_row = $_r->fetch_assoc()) {
        $_nav_name  = $_row['name'];
        $_nav_email = $_row['email'];
        $_nav_pic   = $_row['profile_pic'] ?? '';
    }
    $_r2 = $conn->query("SELECT COUNT(*) AS t FROM enrollments WHERE user_id = $_uid");
    if ($_r2 && $_row2 = $_r2->fetch_assoc()) $_nav_enrolled = $_row2['t'];
    $_r3 = $conn->query("SELECT COUNT(*) AS t FROM enrollments WHERE user_id = $_uid AND completed_at IS NOT NULL");
    if ($_r3 && $_row3 = $_r3->fetch_assoc()) $_nav_completed = $_row3['t'];
}
?>

<style>
/* Profile-specific nav styles (supplements head.php) */
.profile-trigger {
    display:flex;align-items:center;gap:9px;
    background:rgba(255,255,255,.12);border:1.5px solid rgba(255,255,255,.25);
    border-radius:var(--r-full);padding:5px 14px 5px 5px;
    cursor:pointer;color:#fff;text-decoration:none;
    transition:background .2s,border-color .2s;
}
.profile-trigger:hover{background:rgba(255,255,255,.2);border-color:rgba(245,197,24,.6);color:#fff}
.profile-avatar{width:32px;height:32px;border-radius:50%;overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center}
.profile-avatar img{width:100%;height:100%;object-fit:cover}
.avatar-initials{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--gold),#f59300);color:#1a1030;font-weight:700;font-size:13px;display:flex;align-items:center;justify-content:center}
.profile-name{font-size:13px;font-weight:600;max-width:90px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.profile-chevron{font-size:10px;opacity:.75;transition:transform .25s}
.profile-dropdown{position:absolute;right:0;top:calc(100% + 12px);background:#fff;border-radius:var(--r-xl);box-shadow:0 20px 60px rgba(74,26,140,.2);width:300px;overflow:hidden;opacity:0;visibility:hidden;transform:translateY(-10px);transition:all .25s var(--ease-out);z-index:1000;border:1px solid var(--border)}
.profile-dropdown.open{opacity:1;visibility:visible;transform:translateY(0)}
.pd-header{background:linear-gradient(135deg,#1e0a40,#4a1a8c,#6930c3);padding:20px 18px;display:flex;align-items:center;gap:14px}
.pd-avatar{width:50px;height:50px;border-radius:50%;border:2px solid rgba(255,255,255,.4);overflow:hidden;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.15);position:relative;flex-shrink:0}
.pd-avatar img{width:100%;height:100%;object-fit:cover}
.pd-initials{width:50px;height:50px;border-radius:50%;background:linear-gradient(135deg,var(--gold),#f59300);color:#1a1030;font-weight:700;font-size:18px;display:flex;align-items:center;justify-content:center}
.pd-edit{position:absolute;bottom:-2px;right:-2px;width:20px;height:20px;border-radius:50%;background:var(--gold);color:#333;display:flex;align-items:center;justify-content:center;font-size:9px;cursor:pointer;border:2px solid #fff;transition:transform .2s}
.pd-edit:hover{transform:scale(1.2)}
.pd-info .pd-name{font-size:14px;font-weight:700;color:#fff;margin:0 0 3px;font-family:'Fraunces',serif;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:170px}
.pd-info .pd-email{font-size:11.5px;color:rgba(255,255,255,.65);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:170px}
.pd-stats{display:grid;grid-template-columns:1fr 1fr;border-bottom:1px solid var(--border)}
.pd-stat{padding:14px;text-align:center;background:#fff}
.pd-stat:first-child{border-right:1px solid var(--border)}
.pd-stat-num{font-family:'Fraunces',serif;font-size:24px;font-weight:700;display:block;line-height:1}
.pd-stat-lbl{font-size:11px;color:var(--text-muted);margin-top:3px;display:flex;align-items:center;justify-content:center;gap:4px}
.stat-enrolled{color:var(--primary)}.stat-completed{color:#16a34a}
.pd-upload-area{padding:12px 16px;border-bottom:1px solid var(--border)}
.pd-upload-lbl{font-size:11px;color:var(--text-muted);font-weight:700;text-transform:uppercase;letter-spacing:.7px;margin-bottom:8px;display:block}
.pd-upload-btn{display:flex;align-items:center;gap:8px;background:var(--surface-2);border:2px dashed var(--border-mid);border-radius:var(--r-md);padding:9px 13px;cursor:pointer;transition:all .2s;font-size:13px;color:var(--primary);font-weight:500;width:100%}
.pd-upload-btn:hover{background:var(--primary-light);border-color:var(--primary)}
.pd-upload-btn i{color:var(--accent)}
#navProfileInput{display:none}
.pd-links-area{padding:7px 9px}
.pd-nav-link{display:flex;align-items:center;gap:10px;padding:9px 10px;border-radius:var(--r-md);color:var(--text-secondary);font-size:13.5px;font-weight:500;text-decoration:none;transition:background .15s,color .15s}
.pd-nav-link:hover{background:var(--primary-light);color:var(--primary)}
.pd-nav-link i{width:18px;text-align:center;color:var(--primary);font-size:13px}
.pd-nav-link.logout{color:#dc2626}.pd-nav-link.logout i{color:#dc2626}.pd-nav-link.logout:hover{background:#fef2f2}
.upload-toast-nav{position:fixed;bottom:24px;right:24px;background:#1a1030;color:#fff;padding:12px 20px;border-radius:12px;font-size:13px;font-weight:500;opacity:0;transform:translateY(10px);transition:all .3s;z-index:9999;pointer-events:none;box-shadow:0 8px 24px rgba(0,0,0,.25)}
.upload-toast-nav.show{opacity:1;transform:translateY(0)}
</style>

<nav class="navbar navbar-expand-lg sticky-top">
  <div class="container">
    <a class="navbar-brand" href="index.php">
      <div class="brand-icon">📚</div>
      <span>EDU<span class="brand-accent">RAY</span></span>
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-lg-center gap-lg-1">
        <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
        <li class="nav-item"><a class="nav-link" href="index.php#services">Services</a></li>
        <li class="nav-item"><a class="nav-link" href="courses.php">Courses</a></li>
        <li class="nav-item"><a class="nav-link" href="community.php">Community</a></li>

        <?php if (!empty($_SESSION['user_id'])): ?>
          <li class="nav-item"><a class="nav-link" href="my_courses.php">My Courses</a></li>
        <?php endif; ?>

        <li class="nav-item"><a class="nav-link" href="index.php#about">About</a></li>
        <li class="nav-item me-lg-2"><a class="nav-link" href="authority.php">Contact</a></li>

        <?php if (!empty($_SESSION['user_id'])): ?>
          <li class="nav-item ms-lg-3" style="position:relative;">
            <a class="profile-trigger" href="#" id="navProfileTrigger">
              <div class="profile-avatar">
                <?php if (!empty($_nav_pic)): ?>
                  <img src="<?= htmlspecialchars($_nav_pic) ?>" alt="Profile" id="navAvatarImg">
                <?php else: ?>
                  <div class="avatar-initials" id="navAvatarInit"><?= strtoupper(substr($_nav_name, 0, 1)) ?></div>
                <?php endif; ?>
              </div>
              <span class="profile-name"><?= htmlspecialchars(explode(' ', $_nav_name)[0]) ?></span>
              <i class="fas fa-chevron-down profile-chevron" id="navChevron"></i>
            </a>

            <div class="profile-dropdown" id="navProfileDropdown">
              <div class="pd-header">
                <div class="pd-avatar" id="navPdAvatar">
                  <?php if (!empty($_nav_pic)): ?>
                    <img src="<?= htmlspecialchars($_nav_pic) ?>" alt="Avatar">
                  <?php else: ?>
                    <div class="pd-initials"><?= strtoupper(substr($_nav_name, 0, 1)) ?></div>
                  <?php endif; ?>
                  <label class="pd-edit" for="navProfileInput" title="Change photo"><i class="fas fa-camera"></i></label>
                </div>
                <div class="pd-info">
                  <p class="pd-name"><?= htmlspecialchars($_nav_name) ?></p>
                  <p class="pd-email"><?= htmlspecialchars($_nav_email) ?></p>
                </div>
              </div>
              <div class="pd-stats">
                <div class="pd-stat">
                  <span class="pd-stat-num stat-enrolled"><?= $_nav_enrolled ?></span>
                  <span class="pd-stat-lbl"><i class="fas fa-book-open"></i> Enrolled</span>
                </div>
                <div class="pd-stat">
                  <span class="pd-stat-num stat-completed"><?= $_nav_completed ?></span>
                  <span class="pd-stat-lbl"><i class="fas fa-check-circle"></i> Completed</span>
                </div>
              </div>
              <div class="pd-upload-area">
                <span class="pd-upload-lbl">Profile Photo</span>
                <label class="pd-upload-btn" for="navProfileInput">
                  <i class="fas fa-cloud-upload-alt"></i> Upload new photo
                </label>
                <input type="file" id="navProfileInput" accept="image/*">
              </div>
              <div class="pd-links-area">
                <a href="my_courses.php" class="pd-nav-link"><i class="fas fa-graduation-cap"></i> My Courses</a>
                <a href="logout.php" class="pd-nav-link logout"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
              </div>
            </div>
          </li>
        <?php else: ?>
          <li class="nav-item ms-lg-2">
            <a href="main.php" class="btn-nav"><i class="fas fa-rocket"></i> Get Started</a>
          </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<div class="upload-toast-nav" id="navUploadToast"></div>

<script>
(function(){
  const trigger  = document.getElementById('navProfileTrigger');
  const dropdown = document.getElementById('navProfileDropdown');
  const chevron  = document.getElementById('navChevron');
  if (trigger && dropdown) {
    trigger.addEventListener('click', e => {
      e.preventDefault(); e.stopPropagation();
      dropdown.classList.toggle('open');
      if (chevron) chevron.style.transform = dropdown.classList.contains('open') ? 'rotate(180deg)' : '';
    });
    document.addEventListener('click', e => {
      if (!trigger.contains(e.target) && !dropdown.contains(e.target)) {
        dropdown.classList.remove('open');
        if (chevron) chevron.style.transform = '';
      }
    });
  }
  const imgInput = document.getElementById('navProfileInput');
  const toast    = document.getElementById('navUploadToast');
  function showNavToast(msg) {
    if (!toast) return;
    toast.textContent = msg; toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3000);
  }
  if (imgInput) {
    imgInput.addEventListener('change', function() {
      const file = this.files[0]; if (!file) return;
      const reader = new FileReader();
      reader.onload = e => {
        const src = e.target.result;
        const pdAvatar = document.getElementById('navPdAvatar');
        if (pdAvatar) pdAvatar.innerHTML = `<img src="${src}" style="width:100%;height:100%;object-fit:cover;border-radius:50%;"><label class="pd-edit" for="navProfileInput" title="Change photo"><i class="fas fa-camera"></i></label>`;
        const pAvatar = trigger ? trigger.querySelector('.profile-avatar') : null;
        if (pAvatar) pAvatar.innerHTML = `<img src="${src}" style="width:32px;height:32px;border-radius:50%;object-fit:cover;">`;
      };
      reader.readAsDataURL(file);
      const fd = new FormData(); fd.append('profile_pic', file);
      fetch('upload_profile.php', { method:'POST', body:fd })
        .then(r => r.json())
        .then(d => showNavToast(d.success ? '✅ Photo updated!' : (d.error || 'Upload failed')))
        .catch(() => showNavToast('Upload failed. Try again.'));
    });
  }
})();
</script>
