<?php
// Prevent direct access
if (!defined('EDURAY_INCLUDED')) {
    die('Direct access not permitted');
}
?>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />

<!-- Google Fonts: DM Sans + Fraunces display -->
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,600;0,9..144,700;1,9..144,400&display=swap" rel="stylesheet" />
<!-- Bootstrap 5 CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* EDURAY DESIGN SYSTEM v3 */
:root {
    --primary:       #4a1a8c;
    --primary-mid:   #6930c3;
    --primary-light: #ede6ff;
    --primary-glow:  rgba(105, 48, 195, 0.15);
    --accent:        #f5622e;
    --accent-light:  #fff1ec;
    --gold:          #f5c518;
    --gold-light:    #fffbea;
    --bg:            #f8f7fc;
    --surface:       #ffffff;
    --surface-2:     #f3f0fa;
    --border:        rgba(74, 26, 140, 0.1);
    --border-mid:    rgba(74, 26, 140, 0.18);
    --text:          #1a1030;
    --text-secondary:#4a4466;
    --text-muted:    #8b87a8;
    --text-white:    #ffffff;
    --shadow-xs:  0 1px 3px rgba(74,26,140,.06);
    --shadow-sm:  0 4px 12px rgba(74,26,140,.08);
    --shadow-md:  0 8px 28px rgba(74,26,140,.12);
    --shadow-lg:  0 20px 60px rgba(74,26,140,.16);
    --shadow-xl:  0 32px 80px rgba(74,26,140,.22);
    --r-sm:  8px;
    --r-md:  14px;
    --r-lg:  20px;
    --r-xl:  28px;
    --r-full:9999px;
    --ease-out: cubic-bezier(0.16, 1, 0.3, 1);
    --nav-bg: linear-gradient(135deg, #1e0a40 0%, #4a1a8c 50%, #6930c3 100%);
}

*, *::before, *::after { box-sizing: border-box; }
html { scroll-behavior: smooth; }
body {
    margin: 0;
    font-family: 'DM Sans', sans-serif;
    background: var(--bg);
    color: var(--text);
    overflow-x: hidden;
    min-height: 100vh;
    -webkit-font-smoothing: antialiased;
}
h1,h2,h3,h4,h5,h6 { font-family: 'Fraunces', serif; font-weight: 600; line-height: 1.2; }
a { text-decoration: none; }

/* NAVBAR */
.navbar {
    background: var(--nav-bg);
    padding: 0;
    box-shadow: 0 2px 24px rgba(0,0,0,.22);
    position: relative;
}
.navbar::after {
    content: '';
    position: absolute;
    bottom: 0; left: 0; right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,.15), transparent);
}
.navbar .container { padding-left: 5%; padding-right: 5%; min-height: 68px; }
.navbar-brand {
    font-family: 'Fraunces', serif;
    font-size: 22px; font-weight: 700;
    color: #fff !important;
    display: flex; align-items: center; gap: 10px;
    padding: 0;
}
.navbar-brand .brand-icon {
    width: 36px; height: 36px;
    background: linear-gradient(135deg, var(--gold), #f59300);
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px;
    box-shadow: 0 4px 12px rgba(245,197,24,.35);
    flex-shrink: 0;
}
.navbar-brand .brand-accent { color: var(--gold); }
.navbar-nav .nav-link {
    color: rgba(255,255,255,.82) !important;
    font-size: 14px; font-weight: 500;
    padding: 6px 14px !important;
    border-radius: var(--r-full);
    transition: color .2s, background .2s;
}
.navbar-nav .nav-link:hover { color: #fff !important; background: rgba(255,255,255,.1); }
.navbar-nav .nav-link.active-link { color: var(--gold) !important; background: rgba(245,197,24,.12); }
.btn-signup, .btn-nav {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 9px 22px; border-radius: var(--r-full);
    font-weight: 600; font-size: 13.5px;
    background: linear-gradient(135deg, var(--gold) 0%, #f59300 100%);
    color: #1a1030 !important; border: none; cursor: pointer;
    box-shadow: 0 4px 16px rgba(245,197,24,.4);
    transition: transform .2s var(--ease-out), box-shadow .2s, filter .2s;
    white-space: nowrap;
}
.btn-signup:hover, .btn-nav:hover {
    transform: translateY(-2px) scale(1.02);
    box-shadow: 0 8px 24px rgba(245,197,24,.5);
    filter: brightness(1.04); color: #1a1030 !important;
}
.navbar-toggler { border-color: rgba(255,255,255,.3); padding: 6px 10px; }
.navbar-toggler:focus { box-shadow: 0 0 0 2px rgba(255,255,255,.25); outline: none; }
.navbar-toggler-icon { filter: invert(1); }

/* FOOTER */
footer {
    background: linear-gradient(135deg, #1e0a40 0%, #4a1a8c 60%, #6930c3 100%);
    color: #fff; padding: 50px 5% 30px; position: relative; overflow: hidden;
}
footer::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px;
    background: linear-gradient(90deg, transparent, rgba(245,197,24,.4), transparent);
}
.footer-grid { display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 40px; margin-bottom: 40px; }
.footer-brand .footer-logo {
    font-family: 'Fraunces', serif; font-size: 24px; font-weight: 700;
    color: var(--gold); margin-bottom: 12px; display: flex; align-items: center; gap: 10px;
}
.footer-brand p { font-size: 14px; color: rgba(255,255,255,.6); line-height: 1.6; max-width: 280px; }
.footer-col h4 {
    font-family: 'DM Sans', sans-serif; font-size: 11px; font-weight: 700;
    text-transform: uppercase; letter-spacing: 2px; color: rgba(255,255,255,.4); margin-bottom: 16px;
}
.footer-col a { display: block; color: rgba(255,255,255,.65); font-size: 14px; padding: 4px 0; transition: color .15s; }
.footer-col a:hover { color: var(--gold); }
.footer-contact-item { display: flex; align-items: center; gap: 10px; font-size: 14px; color: rgba(255,255,255,.65); margin-bottom: 10px; }
.footer-contact-item i { color: var(--gold); font-size: 15px; width: 18px; }
.footer-bottom {
    border-top: 1px solid rgba(255,255,255,.1); padding-top: 24px;
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 12px; font-size: 13px; color: rgba(255,255,255,.4);
}
.footer-social { display: flex; gap: 10px; }
.footer-social a {
    width: 34px; height: 34px; border-radius: 50%;
    background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.12);
    display: flex; align-items: center; justify-content: center;
    color: rgba(255,255,255,.6) !important; font-size: 14px;
    transition: background .2s, color .2s, border-color .2s;
}
.footer-social a:hover { background: rgba(245,197,24,.2); border-color: var(--gold); color: var(--gold) !important; }

/* UTILITY */
.highlight { color: var(--accent); }
.highlight-gold { color: var(--gold); }
.highlight-primary { color: var(--primary); }
.btn-primary-edu {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 13px 28px; border-radius: var(--r-full);
    background: linear-gradient(135deg, var(--primary), var(--primary-mid));
    color: #fff !important; font-weight: 600; font-size: 15px; border: none; cursor: pointer;
    box-shadow: 0 6px 20px var(--primary-glow); transition: transform .22s var(--ease-out), box-shadow .22s, filter .2s;
    text-decoration: none;
}
.btn-primary-edu:hover { transform: translateY(-2px) scale(1.02); box-shadow: 0 12px 32px rgba(74,26,140,.35); filter: brightness(1.06); color: #fff !important; }
.btn-outline-edu {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 12px 26px; border-radius: var(--r-full);
    background: transparent; color: var(--primary) !important;
    font-weight: 600; font-size: 14px; border: 2px solid rgba(74,26,140,.3); cursor: pointer; transition: all .2s;
}
.btn-outline-edu:hover { background: var(--primary-light); border-color: var(--primary); }
.edu-card {
    background: var(--surface); border-radius: var(--r-xl);
    border: 1px solid var(--border); box-shadow: var(--shadow-sm);
    transition: transform .3s var(--ease-out), box-shadow .3s; overflow: hidden;
}
.edu-card:hover { transform: translateY(-6px); box-shadow: var(--shadow-lg); }
.section-tag {
    display: inline-flex; align-items: center; gap: 6px;
    background: var(--primary-light); color: var(--primary);
    font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px;
    padding: 6px 14px; border-radius: var(--r-full); margin-bottom: 14px;
}
.section-tag.accent-tag { background: var(--accent-light); color: var(--accent); }
.edu-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 4px 10px; border-radius: var(--r-full); font-size: 12px; font-weight: 600;
}
.edu-badge.purple { background: var(--primary-light); color: var(--primary); }
.edu-badge.orange { background: var(--accent-light); color: var(--accent); }
.edu-badge.gold   { background: var(--gold-light); color: #b37800; }
.edu-badge.green  { background: #edfdf5; color: #15803d; }

/* ANIMATIONS */
@keyframes fadeInUp { from { opacity:0; transform:translateY(28px); } to { opacity:1; transform:translateY(0); } }
@keyframes fadeIn   { from { opacity:0; } to { opacity:1; } }
@keyframes scaleIn  { from { opacity:0; transform:scale(.92); } to { opacity:1; transform:scale(1); } }
@keyframes float    { 0%,100% { transform:translateY(0); } 50% { transform:translateY(-12px); } }
.animate-fade-up  { animation: fadeInUp  .7s var(--ease-out) both; }
.animate-fade-in  { animation: fadeIn    .7s var(--ease-out) both; }
.animate-scale-in { animation: scaleIn   .6s var(--ease-out) both; }
.delay-1 { animation-delay: .1s; } .delay-2 { animation-delay: .2s; }
.delay-3 { animation-delay: .3s; } .delay-4 { animation-delay: .4s; }

/* RESPONSIVE */
@media (max-width: 991.98px) {
    .navbar .container { padding-left: 16px; padding-right: 16px; }
    .footer-grid { grid-template-columns: 1fr 1fr; }
    .footer-brand { grid-column: 1 / -1; }
}
@media (max-width: 575.98px) {
    .footer-grid { grid-template-columns: 1fr; }
    .footer-bottom { flex-direction: column; text-align: center; }
}
</style>
