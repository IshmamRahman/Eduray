<?php
session_start();

// ===== DB Connection =====
$conn = new mysqli("localhost", "root", "", "eduray_portal2");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// ===== Fetch user data if logged in =====
$user_name   = '';
$user_email  = '';
$profile_pic = '';
$enrolled    = 0;
$completed   = 0;

if (!empty($_SESSION['user_id'])) {
    $uid = (int) $_SESSION['user_id'];
    $res = $conn->query("SELECT name, email, profile_pic FROM users WHERE id = $uid LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) {
        $user_name   = $row['name'];
        $user_email  = $row['email'];
        $profile_pic = $row['profile_pic'] ?? '';
    }
    $res = $conn->query("SELECT COUNT(*) AS total FROM enrollments WHERE user_id = $uid");
    if ($res && $row = $res->fetch_assoc()) $enrolled = $row['total'];
    $res = $conn->query("SELECT COUNT(*) AS total FROM enrollments WHERE user_id = $uid AND completed_at IS NOT NULL");
    if ($res && $row = $res->fetch_assoc()) $completed = $row['total'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>EDURAY — Learn Without Limits</title>

  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,600;0,9..144,700;1,9..144,500&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <style>
    :root {
        --primary:#4a1a8c; --primary-mid:#6930c3; --primary-light:#ede6ff;
        --primary-glow:rgba(105,48,195,.15); --accent:#f5622e; --accent-light:#fff1ec;
        --gold:#f5c518; --gold-light:#fffbea; --bg:#f8f7fc; --surface:#ffffff;
        --surface-2:#f3f0fa; --border:rgba(74,26,140,.1); --border-mid:rgba(74,26,140,.18);
        --text:#1a1030; --text-secondary:#4a4466; --text-muted:#8b87a8;
        --shadow-sm:0 4px 12px rgba(74,26,140,.08); --shadow-md:0 8px 28px rgba(74,26,140,.12);
        --shadow-lg:0 20px 60px rgba(74,26,140,.16); --shadow-xl:0 32px 80px rgba(74,26,140,.22);
        --r-sm:8px; --r-md:14px; --r-lg:20px; --r-xl:28px; --r-full:9999px;
        --ease-out:cubic-bezier(0.16,1,0.3,1); --nav-bg:linear-gradient(135deg,#1e0a40 0%,#4a1a8c 50%,#6930c3 100%);
    }
    *,*::before,*::after{box-sizing:border-box}
    html{scroll-behavior:smooth}
    body{margin:0;font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);overflow-x:hidden;-webkit-font-smoothing:antialiased}
    h1,h2,h3,h4,h5,h6{font-family:'Fraunces',serif;font-weight:600;line-height:1.2}
    a{text-decoration:none}

    /* NAVBAR */
    .navbar{background:var(--nav-bg);padding:0;box-shadow:0 2px 24px rgba(0,0,0,.22);position:relative}
    .navbar::after{content:'';position:absolute;bottom:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,rgba(255,255,255,.15),transparent)}
    .navbar .container{padding-left:5%;padding-right:5%;min-height:68px}
    .navbar-brand{font-family:'Fraunces',serif;font-size:22px;font-weight:700;color:#fff!important;display:flex;align-items:center;gap:10px;padding:0}
    .navbar-brand .brand-icon{width:36px;height:36px;background:linear-gradient(135deg,var(--gold),#f59300);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;box-shadow:0 4px 12px rgba(245,197,24,.35);flex-shrink:0}
    .navbar-brand .brand-accent{color:var(--gold)}
    .navbar-nav .nav-link{color:rgba(255,255,255,.82)!important;font-size:14px;font-weight:500;padding:6px 14px!important;border-radius:var(--r-full);transition:color .2s,background .2s}
    .navbar-nav .nav-link:hover{color:#fff!important;background:rgba(255,255,255,.1)}
    .btn-signup,.btn-nav{display:inline-flex;align-items:center;gap:6px;padding:9px 22px;border-radius:var(--r-full);font-weight:600;font-size:13.5px;background:linear-gradient(135deg,var(--gold) 0%,#f59300 100%);color:#1a1030!important;border:none;cursor:pointer;box-shadow:0 4px 16px rgba(245,197,24,.4);transition:transform .2s var(--ease-out),box-shadow .2s,filter .2s;white-space:nowrap}
    .btn-signup:hover,.btn-nav:hover{transform:translateY(-2px) scale(1.02);box-shadow:0 8px 24px rgba(245,197,24,.5);filter:brightness(1.04);color:#1a1030!important}
    .navbar-toggler{border-color:rgba(255,255,255,.3);padding:6px 10px}
    .navbar-toggler:focus{box-shadow:0 0 0 2px rgba(255,255,255,.25);outline:none}
    .navbar-toggler-icon{filter:invert(1)}

    /* Profile dropdown */
    .profile-trigger{display:flex;align-items:center;gap:9px;background:rgba(255,255,255,.12);border:1.5px solid rgba(255,255,255,.25);border-radius:var(--r-full);padding:5px 14px 5px 5px;cursor:pointer;transition:background .2s,border-color .2s;color:#fff;text-decoration:none}
    .profile-trigger:hover{background:rgba(255,255,255,.2);border-color:rgba(245,197,24,.6);color:#fff}
    .profile-avatar{width:32px;height:32px;border-radius:50%;overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center}
    .profile-avatar img{width:100%;height:100%;object-fit:cover}
    .avatar-initials{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--gold),#f59300);color:#1a1030;font-weight:700;font-size:13px;display:flex;align-items:center;justify-content:center}
    .profile-name{font-size:13px;font-weight:600;max-width:90px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .profile-chevron{font-size:10px;opacity:.7;transition:transform .25s}
    .profile-dropdown{position:absolute;right:0;top:calc(100% + 12px);background:#fff;border-radius:var(--r-xl);box-shadow:0 20px 60px rgba(74,26,140,.2),0 4px 20px rgba(0,0,0,.08);width:300px;overflow:hidden;opacity:0;visibility:hidden;transform:translateY(-10px);transition:all .25s var(--ease-out);z-index:1000;border:1px solid var(--border)}
    .profile-dropdown.open{opacity:1;visibility:visible;transform:translateY(0)}
    .pd-header{background:var(--nav-bg);padding:22px 20px 18px;display:flex;align-items:center;gap:14px}
    .pd-avatar-wrap{position:relative;flex-shrink:0}
    .pd-avatar{width:52px;height:52px;border-radius:50%;border:2.5px solid rgba(255,255,255,.4);overflow:hidden;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.15)}
    .pd-avatar img{width:100%;height:100%;object-fit:cover}
    .pd-initials{width:52px;height:52px;border-radius:50%;background:linear-gradient(135deg,var(--gold),#f59300);color:#1a1030;font-weight:700;font-size:20px;display:flex;align-items:center;justify-content:center}
    .pd-edit-badge{position:absolute;bottom:0;right:0;width:20px;height:20px;border-radius:50%;background:var(--gold);color:#333;display:flex;align-items:center;justify-content:center;font-size:9px;cursor:pointer;border:2px solid #fff;transition:transform .2s}
    .pd-edit-badge:hover{transform:scale(1.2)}
    .pd-info{flex:1;min-width:0}
    .pd-username{font-size:15px;font-weight:700;color:#fff;margin:0 0 3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-family:'Fraunces',serif}
    .pd-email{font-size:12px;color:rgba(255,255,255,.7);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .pd-stats{display:grid;grid-template-columns:1fr 1fr;gap:1px;background:var(--border);border-bottom:1px solid var(--border)}
    .pd-stat{background:#fff;padding:16px;text-align:center}
    .pd-stat-num{font-size:26px;font-weight:700;line-height:1;display:block;font-family:'Fraunces',serif}
    .pd-stat-label{font-size:11px;color:var(--text-muted);font-weight:500;margin-top:4px;display:flex;align-items:center;justify-content:center;gap:4px}
    .enrolled-num{color:var(--primary)!important}
    .completed-num{color:#16a34a!important}
    .pd-upload{padding:14px 18px;border-bottom:1px solid var(--border)}
    .pd-upload-label{font-size:11px;color:var(--text-muted);font-weight:700;text-transform:uppercase;letter-spacing:.8px;margin-bottom:8px;display:block}
    .upload-btn{display:flex;align-items:center;gap:8px;background:var(--surface-2);border:2px dashed var(--border-mid);border-radius:var(--r-md);padding:10px 14px;cursor:pointer;transition:all .2s;font-size:13px;color:var(--primary);font-weight:500;width:100%}
    .upload-btn:hover{background:var(--primary-light);border-color:var(--primary)}
    .upload-btn i{font-size:16px;color:var(--accent)}
    #profileImageInput{display:none}
    .pd-links{padding:8px 10px}
    .pd-link{display:flex;align-items:center;gap:10px;padding:10px;border-radius:var(--r-md);color:var(--text-secondary);font-size:14px;font-weight:500;text-decoration:none;transition:background .15s,color .15s}
    .pd-link:hover{background:var(--primary-light);color:var(--primary)}
    .pd-link i{width:18px;text-align:center;color:var(--primary);font-size:13px}
    .pd-link.logout{color:#dc2626}
    .pd-link.logout i{color:#dc2626}
    .pd-link.logout:hover{background:#fef2f2}

    /* HERO */
    .hero-section{
        position:relative;
        padding:90px 5% 80px;
        overflow:hidden;
        background:linear-gradient(155deg,#faf7ff 0%,#f0e9ff 40%,#fdf5ec 100%);
    }
    .hero-section::before{
        content:'';position:absolute;top:-100px;right:-100px;
        width:600px;height:600px;border-radius:50%;
        background:radial-gradient(circle,rgba(105,48,195,.12) 0%,transparent 70%);
        pointer-events:none;
    }
    .hero-section::after{
        content:'';position:absolute;bottom:-150px;left:-80px;
        width:500px;height:500px;border-radius:50%;
        background:radial-gradient(circle,rgba(245,98,46,.08) 0%,transparent 70%);
        pointer-events:none;
    }
    .hero-inner{position:relative;z-index:1;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:50px}
    .hero-text{max-width:52%;min-width:300px}
    .hero-tag{display:inline-flex;align-items:center;gap:6px;background:var(--primary-light);color:var(--primary);font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;padding:7px 16px;border-radius:var(--r-full);margin-bottom:20px;border:1px solid rgba(74,26,140,.15)}
    .hero-tag i{font-size:11px}
    .hero-title{font-size:clamp(38px,4.5vw,62px);font-weight:700;line-height:1.12;margin-bottom:20px;color:var(--text)}
    .hero-title .line-accent{color:var(--accent);font-style:italic}
    .hero-title .line-primary{color:var(--primary)}
    .hero-desc{font-size:17px;color:var(--text-secondary);line-height:1.7;margin-bottom:32px;max-width:480px}
    .hero-actions{display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-bottom:40px}
    .btn-hero-primary{display:inline-flex;align-items:center;gap:8px;padding:14px 30px;border-radius:var(--r-full);background:linear-gradient(135deg,var(--primary),var(--primary-mid));color:#fff!important;font-weight:600;font-size:15px;border:none;cursor:pointer;box-shadow:0 8px 24px var(--primary-glow);transition:transform .22s var(--ease-out),box-shadow .22s,filter .2s;text-decoration:none}
    .btn-hero-primary:hover{transform:translateY(-3px) scale(1.02);box-shadow:0 16px 40px rgba(74,26,140,.3);filter:brightness(1.06);color:#fff!important}
    .btn-hero-secondary{display:inline-flex;align-items:center;gap:8px;padding:13px 26px;border-radius:var(--r-full);background:#fff;color:var(--primary)!important;font-weight:600;font-size:15px;border:1.5px solid var(--border-mid);box-shadow:var(--shadow-sm);transition:all .2s;text-decoration:none}
    .btn-hero-secondary:hover{background:var(--primary-light);border-color:var(--primary);color:var(--primary)!important}
    .hero-stats{display:flex;gap:32px;flex-wrap:wrap}
    .hero-stat-num{font-family:'Fraunces',serif;font-size:28px;font-weight:700;color:var(--primary);display:block;line-height:1}
    .hero-stat-label{font-size:13px;color:var(--text-muted);margin-top:3px;display:block}
    .hero-stat-divider{width:1px;background:var(--border);align-self:stretch}
    .hero-visual{max-width:44%;min-width:300px;position:relative}
    .hero-img-wrap{position:relative;animation:float 6s ease-in-out infinite}
    @keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-14px)}}
    .hero-img-wrap img{width:100%;display:block;filter:drop-shadow(0 30px 50px rgba(74,26,140,.25))}
    .hero-float-card{position:absolute;background:#fff;border-radius:16px;box-shadow:0 12px 40px rgba(74,26,140,.15);padding:14px 18px;display:flex;align-items:center;gap:12px;border:1px solid var(--border)}
    .hero-float-card.card-1{bottom:-20px;left:-30px;animation:floatCard1 5s ease-in-out infinite}
    .hero-float-card.card-2{top:30px;right:-20px;animation:floatCard2 5.5s ease-in-out infinite}
    @keyframes floatCard1{0%,100%{transform:translateY(0) rotate(-1deg)}50%{transform:translateY(-8px) rotate(-1deg)}}
    @keyframes floatCard2{0%,100%{transform:translateY(0) rotate(1deg)}50%{transform:translateY(-6px) rotate(1deg)}}
    .float-card-icon{width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
    .float-card-icon.purple{background:var(--primary-light)}
    .float-card-icon.orange{background:var(--accent-light)}
    .float-card-label{font-size:11px;color:var(--text-muted);font-weight:500}
    .float-card-value{font-family:'Fraunces',serif;font-size:16px;font-weight:700;color:var(--text);display:block}

    /* SEARCH BAR IN HERO */
    .hero-search{display:flex;align-items:center;gap:0;background:#fff;border-radius:var(--r-full);box-shadow:0 8px 32px rgba(74,26,140,.12);border:1.5px solid var(--border);overflow:hidden;max-width:460px;margin-bottom:28px}
    .hero-search input{border:none;outline:none;flex:1;padding:14px 20px;font-family:'DM Sans',sans-serif;font-size:15px;color:var(--text);background:transparent}
    .hero-search input::placeholder{color:var(--text-muted)}
    .hero-search button{background:linear-gradient(135deg,var(--primary),var(--primary-mid));color:#fff;border:none;padding:10px 22px;cursor:pointer;font-weight:600;font-size:14px;display:flex;align-items:center;gap:6px;margin:5px;border-radius:var(--r-full);transition:filter .2s}
    .hero-search button:hover{filter:brightness(1.1)}

    /* STATS STRIP */
    .stats-strip{background:#fff;border-bottom:1px solid var(--border);padding:0 5%}
    .stats-strip-inner{display:flex;align-items:stretch;max-width:1100px;margin:0 auto}
    .stat-item{flex:1;padding:22px 20px;display:flex;align-items:center;gap:14px;border-right:1px solid var(--border)}
    .stat-item:last-child{border-right:none}
    .stat-icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
    .stat-icon.purple{background:var(--primary-light);color:var(--primary)}
    .stat-icon.orange{background:var(--accent-light);color:var(--accent)}
    .stat-icon.gold{background:var(--gold-light);color:#b37800}
    .stat-icon.green{background:#edfdf5;color:#15803d}
    .stat-num{font-family:'Fraunces',serif;font-size:22px;font-weight:700;color:var(--text);line-height:1}
    .stat-lbl{font-size:12.5px;color:var(--text-muted);margin-top:2px}

    /* SERVICES */
    .services-section{padding:90px 5%;background:linear-gradient(180deg,#fff 0%,var(--bg) 100%)}
    .section-header{text-align:center;margin-bottom:56px}
    .section-tag{display:inline-flex;align-items:center;gap:6px;background:var(--primary-light);color:var(--primary);font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;padding:6px 14px;border-radius:var(--r-full);margin-bottom:14px}
    .section-tag.orange{background:var(--accent-light);color:var(--accent)}
    .section-title{font-size:clamp(28px,3.5vw,42px);font-weight:700;color:var(--text);margin-bottom:14px}
    .section-desc{font-size:16px;color:var(--text-muted);max-width:520px;margin:0 auto;line-height:1.7}
    .services-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:24px;max-width:1100px;margin:0 auto}
    .service-card{background:#fff;border-radius:var(--r-xl);border:1px solid var(--border);padding:32px 28px;text-align:center;transition:transform .3s var(--ease-out),box-shadow .3s,border-color .3s;box-shadow:var(--shadow-sm)}
    .service-card:hover{transform:translateY(-8px);box-shadow:var(--shadow-lg);border-color:rgba(74,26,140,.25)}
    .service-icon-wrap{width:70px;height:70px;border-radius:20px;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;position:relative}
    .service-icon-wrap.c1{background:linear-gradient(135deg,#ede6ff,#d9ccff)}
    .service-icon-wrap.c2{background:linear-gradient(135deg,#fff1ec,#ffd5c2)}
    .service-icon-wrap.c3{background:linear-gradient(135deg,#fffbea,#ffeaa0)}
    .service-icon-wrap.c4{background:linear-gradient(135deg,#edfdf5,#b7f5d5)}
    .service-icon-wrap img{width:40px;height:40px;object-fit:contain}
    .service-title{font-size:18px;font-weight:700;color:var(--text);margin-bottom:10px;font-family:'Fraunces',serif}
    .service-desc{font-size:14px;color:var(--text-muted);line-height:1.65}

    /* ABOUT */
    .about-section{padding:90px 5%;background:#fff;position:relative;overflow:hidden}
    .about-section::before{content:'';position:absolute;top:-200px;right:-200px;width:700px;height:700px;border-radius:50%;background:radial-gradient(circle,rgba(74,26,140,.04) 0%,transparent 70%);pointer-events:none}
    .about-inner{display:flex;flex-wrap:wrap;align-items:center;gap:60px;max-width:1200px;margin:0 auto}
    .about-visual{flex:1;min-width:300px;max-width:480px;position:relative}
    .about-img{width:100%;border-radius:var(--r-xl);display:block;box-shadow:var(--shadow-xl)}
    .about-badge{position:absolute;bottom:-20px;right:-20px;background:#fff;border-radius:var(--r-lg);padding:16px 20px;box-shadow:var(--shadow-lg);border:1px solid var(--border);display:flex;align-items:center;gap:12px}
    .about-badge-icon{width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,var(--primary),var(--primary-mid));display:flex;align-items:center;justify-content:center;color:#fff;font-size:20px}
    .about-badge-text strong{display:block;font-size:18px;font-weight:700;font-family:'Fraunces',serif;color:var(--primary)}
    .about-badge-text span{font-size:12px;color:var(--text-muted)}
    .about-content{flex:1;min-width:300px;max-width:540px}
    .about-title{font-size:clamp(30px,3.5vw,46px);font-weight:700;margin-bottom:20px;color:var(--text)}
    .about-title .accent{color:var(--accent);font-style:italic}
    .about-desc{font-size:16px;color:var(--text-secondary);line-height:1.75;margin-bottom:16px}
    .about-features{display:flex;flex-direction:column;gap:12px;margin-top:24px}
    .about-feature{display:flex;align-items:center;gap:12px;font-size:14.5px;color:var(--text-secondary)}
    .about-feature-icon{width:32px;height:32px;border-radius:10px;background:var(--primary-light);color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0}

    /* FOOTER */
    footer{background:linear-gradient(135deg,#1e0a40 0%,#4a1a8c 60%,#6930c3 100%);color:#fff;padding:56px 5% 30px;position:relative;overflow:hidden}
    footer::before{content:'';position:absolute;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,rgba(245,197,24,.4),transparent)}
    .footer-grid{display:grid;grid-template-columns:2fr 1fr 1fr;gap:40px;margin-bottom:40px}
    .footer-brand .footer-logo{font-family:'Fraunces',serif;font-size:24px;font-weight:700;color:var(--gold);margin-bottom:12px;display:flex;align-items:center;gap:10px}
    .footer-brand p{font-size:14px;color:rgba(255,255,255,.6);line-height:1.65;max-width:280px}
    .footer-col h4{font-family:'DM Sans',sans-serif;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:2px;color:rgba(255,255,255,.4);margin-bottom:16px}
    .footer-col a{display:block;color:rgba(255,255,255,.65);font-size:14px;padding:4px 0;transition:color .15s;text-decoration:none}
    .footer-col a:hover{color:var(--gold)}
    .footer-contact-item{display:flex;align-items:center;gap:10px;font-size:14px;color:rgba(255,255,255,.65);margin-bottom:10px}
    .footer-contact-item i{color:var(--gold);font-size:15px;width:18px}
    .footer-bottom{border-top:1px solid rgba(255,255,255,.1);padding-top:24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;font-size:13px;color:rgba(255,255,255,.4)}
    .footer-social{display:flex;gap:10px}
    .footer-social a{width:34px;height:34px;border-radius:50%;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);display:flex;align-items:center;justify-content:center;color:rgba(255,255,255,.6)!important;font-size:14px;transition:all .2s}
    .footer-social a:hover{background:rgba(245,197,24,.2);border-color:var(--gold);color:var(--gold)!important}

    .upload-toast{position:fixed;bottom:24px;right:24px;background:#1a1030;color:#fff;padding:12px 20px;border-radius:12px;font-size:13px;font-weight:500;opacity:0;transform:translateY(10px);transition:all .3s;z-index:9999;pointer-events:none;box-shadow:0 8px 24px rgba(0,0,0,.25)}
    .upload-toast.show{opacity:1;transform:translateY(0)}

    @keyframes fadeInUp{from{opacity:0;transform:translateY(28px)}to{opacity:1;transform:translateY(0)}}
    @keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-14px)}}
    .animate-fade-up{animation:fadeInUp .7s cubic-bezier(.16,1,.3,1) both}
    .delay-1{animation-delay:.1s}.delay-2{animation-delay:.2s}.delay-3{animation-delay:.3s}.delay-4{animation-delay:.4s}

    @media(max-width:991.98px){
        .hero-inner{flex-direction:column;text-align:center;padding:60px 0 40px}
        .hero-text{max-width:100%;min-width:unset}
        .hero-visual{max-width:340px;min-width:unset;margin:0 auto}
        .hero-actions{justify-content:center}
        .hero-stats{justify-content:center}
        .hero-search{max-width:100%;margin-left:auto;margin-right:auto}
        .hero-float-card.card-1{left:-10px}.hero-float-card.card-2{right:-10px}
        .stats-strip-inner{flex-wrap:wrap}
        .stat-item{flex:1 1 45%;border-right:none;border-bottom:1px solid var(--border)}
        .about-inner{flex-direction:column}
        .about-badge{bottom:-10px;right:10px}
        .footer-grid{grid-template-columns:1fr 1fr}
        .footer-brand{grid-column:1/-1}
        .navbar .container{padding-left:16px;padding-right:16px}
    }
    @media(max-width:575.98px){
        .hero-section{padding:60px 20px 50px}
        .stats-strip{padding:0 16px}
        .stat-item{flex:1 1 100%}
        .services-section,.about-section{padding:60px 20px}
        footer{padding:40px 20px 24px}
        .footer-grid{grid-template-columns:1fr}
        .footer-bottom{flex-direction:column;text-align:center}
        .hero-float-card{display:none}
    }
  </style>
</head>
<body>

<!-- NAVBAR -->
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
        <li class="nav-item"><a class="nav-link" href="courses.php">Courses</a></li>
        <li class="nav-item"><a class="nav-link" href="community.php">Community</a></li>
        <?php if (!empty($_SESSION['user_id'])): ?>
          <li class="nav-item"><a class="nav-link" href="my_courses.php">My Courses</a></li>
          <li class="nav-item"><a class="nav-link" href="roadmap.php">Roadmap</a></li>
        <?php endif; ?>
        <li class="nav-item"><a class="nav-link" href="#about">About</a></li>
        <li class="nav-item me-lg-2"><a class="nav-link" href="authority.php">Contact</a></li>

        <?php if (!empty($_SESSION['user_id'])): ?>
          <li class="nav-item ms-lg-3" style="position:relative;">
            <a class="profile-trigger" href="#" id="profileTrigger">
              <div class="profile-avatar">
                <?php if (!empty($profile_pic)): ?>
                  <img src="<?= htmlspecialchars($profile_pic) ?>" alt="Profile">
                <?php else: ?>
                  <div class="avatar-initials"><?= strtoupper(substr($user_name, 0, 1)) ?></div>
                <?php endif; ?>
              </div>
              <span class="profile-name"><?= htmlspecialchars(explode(' ', $user_name)[0]) ?></span>
              <i class="fas fa-chevron-down profile-chevron" id="profileChevron"></i>
            </a>
            <div class="profile-dropdown" id="profileDropdown">
              <div class="pd-header">
                <div class="pd-avatar-wrap">
                  <div class="pd-avatar" id="pdAvatarWrap">
                    <?php if (!empty($profile_pic)): ?>
                      <img src="<?= htmlspecialchars($profile_pic) ?>" alt="Avatar">
                    <?php else: ?>
                      <div class="pd-initials"><?= strtoupper(substr($user_name, 0, 1)) ?></div>
                    <?php endif; ?>
                  </div>
                  <label class="pd-edit-badge" for="profileImageInput" title="Change photo"><i class="fas fa-camera"></i></label>
                </div>
                <div class="pd-info">
                  <p class="pd-username"><?= htmlspecialchars($user_name) ?></p>
                  <p class="pd-email"><?= htmlspecialchars($user_email) ?></p>
                </div>
              </div>
              <div class="pd-stats">
                <div class="pd-stat">
                  <span class="pd-stat-num enrolled-num"><?= $enrolled ?></span>
                  <span class="pd-stat-label"><i class="fas fa-book-open"></i> Enrolled</span>
                </div>
                <div class="pd-stat">
                  <span class="pd-stat-num completed-num"><?= $completed ?></span>
                  <span class="pd-stat-label"><i class="fas fa-check-circle"></i> Completed</span>
                </div>
              </div>
              <div class="pd-upload">
                <span class="pd-upload-label">Profile Photo</span>
                <label class="upload-btn" for="profileImageInput">
                  <i class="fas fa-cloud-upload-alt"></i>
                  <span>Upload new photo</span>
                </label>
                <input type="file" id="profileImageInput" accept="image/*">
              </div>
              <div class="pd-links">
                <a href="logout.php" class="pd-link logout"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
              </div>
            </div>
          </li>
        <?php else: ?>
          <li class="nav-item ms-lg-2">
            <a href="main.php" class="btn-signup"><i class="fas fa-rocket"></i> Get Started</a>
          </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<!-- HERO -->
<section class="hero-section">
  <div class="hero-inner">
    <div class="hero-text animate-fade-up">
      <div class="hero-tag"><i class="fas fa-star"></i> Bangladesh's #1 EdTech Platform</div>
      <h1 class="hero-title">
        Learn Skills That<br>
        <span class="line-accent">Actually Matter</span><br>
        <span class="line-primary">For Your Future</span>
      </h1>
      <p class="hero-desc">EDURAY brings world-class learning to your fingertips. Expert-led courses, live sessions, certifications — all designed to accelerate your career.</p>

      <div class="hero-search">
        <input type="text" placeholder="Search courses, skills, topics…">
        <button><i class="fas fa-search"></i> Search</button>
      </div>

      <div class="hero-actions">
        <a href="courses.php" class="btn-hero-primary"><i class="fas fa-graduation-cap"></i> Explore Courses</a>
        <?php if (empty($_SESSION['user_id'])): ?>
        <a href="main.php" class="btn-hero-secondary"><i class="fas fa-user-plus"></i> Join Free</a>
        <?php endif; ?>
      </div>

      <div class="hero-stats">
        <div>
          <span class="hero-stat-num">10K+</span>
          <span class="hero-stat-label">Students Enrolled</span>
        </div>
        <div class="hero-stat-divider"></div>
        <div>
          <span class="hero-stat-num">500+</span>
          <span class="hero-stat-label">Video Lessons</span>
        </div>
        <div class="hero-stat-divider"></div>
        <div>
          <span class="hero-stat-num">95%</span>
          <span class="hero-stat-label">Satisfaction Rate</span>
        </div>
      </div>
    </div>

    <div class="hero-visual animate-fade-up delay-2">
      <div class="hero-img-wrap">
        <img src="OBJECTS.png" alt="Students Learning Illustration">
        <div class="hero-float-card card-1">
          <div class="float-card-icon orange">🏆</div>
          <div>
            <span class="float-card-label">Certifications Issued</span>
            <span class="float-card-value">2,400+</span>
          </div>
        </div>
        <div class="hero-float-card card-2">
          <div class="float-card-icon purple">⚡</div>
          <div>
            <span class="float-card-label">Active Learners</span>
            <span class="float-card-value">10,000+</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- STATS STRIP -->
<div class="stats-strip">
  <div class="stats-strip-inner">
    <div class="stat-item animate-fade-up delay-1">
      <div class="stat-icon purple"><i class="fas fa-book-open"></i></div>
      <div><div class="stat-num">500+</div><div class="stat-lbl">Courses Available</div></div>
    </div>
    <div class="stat-item animate-fade-up delay-2">
      <div class="stat-icon orange"><i class="fas fa-users"></i></div>
      <div><div class="stat-num">10,000+</div><div class="stat-lbl">Active Students</div></div>
    </div>
    <div class="stat-item animate-fade-up delay-3">
      <div class="stat-icon gold"><i class="fas fa-chalkboard-teacher"></i></div>
      <div><div class="stat-num">120+</div><div class="stat-lbl">Expert Instructors</div></div>
    </div>
    <div class="stat-item animate-fade-up delay-4">
      <div class="stat-icon green"><i class="fas fa-certificate"></i></div>
      <div><div class="stat-num">2,400+</div><div class="stat-lbl">Certificates Issued</div></div>
    </div>
  </div>
</div>

<!-- SERVICES -->
<section id="services" class="services-section">
  <div class="section-header animate-fade-up">
    <div class="section-tag orange"><i class="fas fa-sparkles"></i> What We Offer</div>
    <h2 class="section-title">Everything You Need to <span style="color:var(--accent);font-style:italic">Succeed</span></h2>
    <p class="section-desc">From bite-sized lessons to full certification programs — we have the tools to make you unstoppable.</p>
  </div>
  <div class="services-grid">
    <div class="service-card animate-fade-up delay-1">
      <div class="service-icon-wrap c1"><img src="online-test 1.png" alt="Online Courses"></div>
      <h3 class="service-title">Online Courses</h3>
      <p class="service-desc">Access thousands of expert-led courses across web dev, design, marketing, data science and more.</p>
    </div>
    <div class="service-card animate-fade-up delay-2">
      <div class="service-icon-wrap c2"><img src="exam 1.png" alt="Live Classes"></div>
      <h3 class="service-title">Live Classes</h3>
      <p class="service-desc">Join real-time interactive sessions with industry professionals. Ask questions, collaborate, grow.</p>
    </div>
    <div class="service-card animate-fade-up delay-3">
      <div class="service-icon-wrap c3"><img src="certification 1.png" alt="Certification"></div>
      <h3 class="service-title">Certification</h3>
      <p class="service-desc">Earn industry-recognized certificates that validate your expertise and boost your career prospects.</p>
    </div>
    <div class="service-card animate-fade-up delay-4">
      <div class="service-icon-wrap c4"><img src="online-test 1.png" alt="Quiz"></div>
      <h3 class="service-title">Quizzes & Tests</h3>
      <p class="service-desc">Test your knowledge with adaptive quizzes. Track your progress and identify areas to improve.</p>
    </div>
  </div>
</section>

<!-- ABOUT -->
<section id="about" class="about-section">
  <div class="about-inner">
    <div class="about-visual animate-fade-up">
      <img class="about-img" src="https://images.unsplash.com/photo-1522202176988-66273c2fd55f?w=600&h=450&fit=crop" alt="About EDURAY">
      <div class="about-badge">
        <div class="about-badge-icon"><i class="fas fa-award"></i></div>
        <div class="about-badge-text">
          <strong>10K+</strong>
          <span>Happy Students</span>
        </div>
      </div>
    </div>
    <div class="about-content animate-fade-up delay-2">
      <div class="section-tag"><i class="fas fa-heart"></i> Our Story</div>
      <h2 class="about-title">Transforming Education,<br><span class="accent">One Learner</span> at a Time</h2>
      <p class="about-desc">EDURAY was born from a simple belief: quality education should be accessible to everyone, everywhere. We bridge the gap between traditional learning and the skills the modern world demands.</p>
      <p class="about-desc">With 500+ carefully curated programs designed by industry experts, we've helped thousands build careers they love — from Sylhet to the world stage.</p>
      <div class="about-features">
        <div class="about-feature">
          <div class="about-feature-icon"><i class="fas fa-check"></i></div>
          Expert instructors from top global companies
        </div>
        <div class="about-feature">
          <div class="about-feature-icon"><i class="fas fa-check"></i></div>
          Flexible learning — study at your own pace
        </div>
        <div class="about-feature">
          <div class="about-feature-icon"><i class="fas fa-check"></i></div>
          Lifetime access to course materials
        </div>
        <div class="about-feature">
          <div class="about-feature-icon"><i class="fas fa-check"></i></div>
          Industry-recognized certifications
        </div>
      </div>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer>
  <div class="footer-grid">
    <div class="footer-brand">
      <div class="footer-logo">
        <div style="width:36px;height:36px;background:linear-gradient(135deg,var(--gold),#f59300);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;">📚</div>
        EDURAY
      </div>
      <p>Empowering the next generation of learners with world-class education. Wherever you are, we're with you.</p>
    </div>
    <div class="footer-col">
      <h4>Quick Links</h4>
      <a href="index.php">Home</a>
      <a href="courses.php">Courses</a>
      <a href="community.php">Community</a>
      <a href="authority.php">Contact</a>
    </div>
    <div class="footer-col">
      <h4>Contact</h4>
      <div class="footer-contact-item"><i class="fas fa-map-marker-alt"></i> Sylhet, Bangladesh</div>
      <div class="footer-contact-item"><i class="fas fa-phone"></i> +880 1711 000000</div>
      <div class="footer-contact-item"><i class="fas fa-envelope"></i> support@eduray.com</div>
    </div>
  </div>
  <div class="footer-bottom">
    <span>© <?php echo date('Y'); ?> EDURAY. All rights reserved.</span>
    <div class="footer-social">
      <a href="#"><i class="fab fa-facebook-f"></i></a>
      <a href="#"><i class="fab fa-twitter"></i></a>
      <a href="#"><i class="fab fa-instagram"></i></a>
      <a href="#"><i class="fab fa-linkedin-in"></i></a>
    </div>
  </div>
</footer>

<div class="upload-toast" id="uploadToast"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Profile dropdown
  const trigger  = document.getElementById('profileTrigger');
  const dropdown = document.getElementById('profileDropdown');
  const chevron  = document.getElementById('profileChevron');
  if (trigger && dropdown) {
    trigger.addEventListener('click', e => {
      e.preventDefault(); e.stopPropagation();
      dropdown.classList.toggle('open');
      if (chevron) chevron.style.transform = dropdown.classList.contains('open') ? 'rotate(180deg)' : 'rotate(0deg)';
    });
    document.addEventListener('click', e => {
      if (!trigger.contains(e.target) && !dropdown.contains(e.target)) {
        dropdown.classList.remove('open');
        if (chevron) chevron.style.transform = 'rotate(0deg)';
      }
    });
  }

  // Image upload
  const imgInput = document.getElementById('profileImageInput');
  const toast    = document.getElementById('uploadToast');
  function showToast(msg) {
    toast.textContent = msg;
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3000);
  }
  if (imgInput) {
    imgInput.addEventListener('change', function() {
      const file = this.files[0];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = e => {
        const src = e.target.result;
        document.getElementById('pdAvatarWrap').innerHTML =
          `<img src="${src}" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">`;
        document.querySelector('.profile-avatar').innerHTML =
          `<img src="${src}" style="width:32px;height:32px;border-radius:50%;object-fit:cover;">`;
      };
      reader.readAsDataURL(file);
      const formData = new FormData();
      formData.append('profile_pic', file);
      fetch('upload_profile.php', { method:'POST', body:formData })
        .then(r => r.json())
        .then(data => showToast(data.success ? '✅ Profile photo updated!' : (data.error || 'Upload failed')))
        .catch(() => showToast('Upload failed. Try again.'));
    });
  }

  // Intersection Observer for animations
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.style.animationPlayState = 'running';
      }
    });
  }, { threshold: 0.1 });
  document.querySelectorAll('.animate-fade-up').forEach(el => {
    el.style.animationPlayState = 'paused';
    observer.observe(el);
  });
</script>
</body>
</html>
