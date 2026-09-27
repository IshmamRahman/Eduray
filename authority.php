<?php
define('EDURAY_INCLUDED', true);
session_start();
require __DIR__ . '/db.php';

$userId = $_SESSION['user_id'] ?? null;
$userName = $_SESSION['name'] ?? null;
$userMail = $_SESSION['email'] ?? null;
$isAdmin = !empty($_SESSION['is_admin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>EDURAY – Contact Authority</title>
    <?php include 'includes/head.php'; ?>
    <style>
        :root {
            --card-bg: #ffffff;
            --glow-a: rgba(255, 136, 0, 0.35);
            --glow-b: rgba(95, 37, 159, 0.40);
        }
        
        .page-head {
            padding: 60px 10% 20px;
            animation: fadeIn 0.6s ease;
        }
        .page-head h1 {
            font-weight: 700;
            font-size: 42px;
            margin-bottom: 10px;
        }
        .page-head p {
            color: #555;
            font-size: 17px;
            margin: 0;
        }

        /* Glowy cards grid */
        .grid {
            padding: 20px 10% 60px;
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 24px;
        }
        
        .a-card {
            grid-column: span 4;
            background: var(--card-bg);
            border-radius: 18px;
            padding: 28px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.08), 0 0 0 3px rgba(0, 0, 0, 0.02) inset;
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            isolation: isolate;
        }
        .a-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 36px rgba(0, 0, 0, 0.10), 0 0 0 4px rgba(0, 0, 0, 0.03) inset;
        }
        
        /* Animated glow */
        .a-card::before, .a-card::after {
            content: "";
            position: absolute;
            inset: -40%;
            z-index: -1;
            filter: blur(28px);
            background: radial-gradient(circle at 30% 30%, var(--glow-a), transparent 60%),
                        radial-gradient(circle at 70% 70%, var(--glow-b), transparent 55%);
            animation: floatGlow 8s ease-in-out infinite alternate;
            opacity: 0.75;
        }
        .a-card::after {
            animation-duration: 10s;
            animation-delay: -2s;
            opacity: 0.55;
        }
        @keyframes floatGlow {
            0% { transform: translate(-4%, -2%) rotate(0deg); }
            100% { transform: translate(4%, 3%) rotate(5deg); }
        }

        /* Avatar */
        .avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: #fff;
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
            flex: 0 0 auto;
        }

        .a-title { font-size: 18px; font-weight: 700; margin: 0; }
        .a-sub { color: #666; margin: 6px 0 0 0; font-size: 14px; }
        .a-body { margin-top: 18px; font-size: 15px; color: #444; }
        .a-body .row-line {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 10px 0;
        }
        .pill {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 50px;
            background: rgba(95, 37, 159, 0.08);
            color: var(--primary);
            font-weight: 600;
            font-size: 12px;
        }
        .cta-row {
            margin-top: 18px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn-soft {
            border: 1px solid var(--border);
            background: #fff;
            border-radius: 12px;
            padding: 10px 16px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            color: var(--text);
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-soft:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
            border-color: var(--primary);
            color: var(--primary);
        }

        @media (max-width: 991.98px) {
            .grid { grid-template-columns: repeat(6, 1fr); }
            .a-card { grid-column: span 6; }
            .page-head { padding: 40px 20px 20px; }
            .page-head h1 { font-size: 32px; }
        }
        @media (max-width: 767.98px) {
            .grid { grid-template-columns: 1fr; padding: 20px; }
            .a-card { grid-column: span 1; }
        }
    </style>
</head>
<body>

<?php include 'includes/navbar.php'; ?>

<!-- Page header -->
<header class="page-head">
    <h1>Contact <span style="color:var(--accent)">Authority</span></h1>
    <p>Reach out to the team. We'll get back as soon as possible.</p>
</header>

<!-- Cards -->
<section class="grid">
    <!-- 1) Annapurna Dey Jonaky -->
    <article class="a-card">
        <div class="d-flex align-items-center gap-3">
            <div class="avatar">AJ</div>
            <div>
                <h3 class="a-title">Annapurna Dey Jonaky</h3>
                <p class="a-sub">BSc in CSE · 4th year · Leading University</p>
            </div>
        </div>
        <div class="a-body">
            <div class="row-line">
                <span class="pill">Authority</span>
                <span style="color:#777;font-size:13px;">EDURAY</span>
            </div>
            <div class="row-line">
                <strong>Email:</strong>
                <a href="mailto:jonaki2022@gmail.com" style="color:var(--primary);text-decoration:none;">jonaki2022@gmail.com</a>
            </div>
            <div class="cta-row">
                <a class="btn-soft" href="mailto:jonaki2022@gmail.com"><i class="fas fa-envelope"></i> Send Email</a>
                <a class="btn-soft" href="courses.php"><i class="fas fa-book"></i> View Courses</a>
            </div>
        </div>
    </article>

    <!-- 2) Puspita Acherjee -->
    <article class="a-card">
        <div class="d-flex align-items-center gap-3">
            <div class="avatar">PA</div>
            <div>
                <h3 class="a-title">Puspita Acherjee</h3>
                <p class="a-sub">BSc in CSE · 4th year · Leading University</p>
            </div>
        </div>
        <div class="a-body">
            <div class="row-line">
                <span class="pill">Authority</span>
                <span style="color:#777;font-size:13px;">EDURAY</span>
            </div>
            <div class="row-line">
                <strong>Email:</strong>
                <a href="mailto:jonaki2022@gmail.com" style="color:var(--primary);text-decoration:none;">jonaki2022@gmail.com</a>
            </div>
            <div class="cta-row">
                <a class="btn-soft" href="mailto:jonaki2022@gmail.com"><i class="fas fa-envelope"></i> Send Email</a>
                <a class="btn-soft" href="courses.php"><i class="fas fa-book"></i> View Courses</a>
            </div>
        </div>
    </article>

    <!-- 3) Chamely Talukdar -->
    <article class="a-card">
        <div class="d-flex align-items-center gap-3">
            <div class="avatar">CT</div>
            <div>
                <h3 class="a-title">Chamely Talukdar</h3>
                <p class="a-sub">BSc in CSE · 4th year · Leading University</p>
            </div>
        </div>
        <div class="a-body">
            <div class="row-line">
                <span class="pill">Authority</span>
                <span style="color:#777;font-size:13px;">EDURAY</span>
            </div>
            <div class="row-line">
                <strong>Email:</strong>
                <a href="mailto:jonaki2022@gmail.com" style="color:var(--primary);text-decoration:none;">jonaki2022@gmail.com</a>
            </div>
            <div class="cta-row">
                <a class="btn-soft" href="mailto:jonaki2022@gmail.com"><i class="fas fa-envelope"></i> Send Email</a>
                <a class="btn-soft" href="courses.php"><i class="fas fa-book"></i> View Courses</a>
            </div>
        </div>
    </article>
</section>


</body>
</html>