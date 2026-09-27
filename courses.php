<?php
define('EDURAY_INCLUDED', true);
session_start();
require __DIR__ . '/db.php';

$userId  = $_SESSION['user_id'] ?? null;
$isAdmin = !empty($_SESSION['is_admin']);

if ($isAdmin) {
    header('Location: admin_dashboard.php');
    exit;
}

// Fetch courses
$courses = [];
$res = $conn->query('SELECT id, title, slug, thumbnail_url FROM courses ORDER BY id');
while ($row = $res->fetch_assoc()) { $courses[] = $row; }
$res->close();

// Fetch video counts
$videoCounts = [];
if ($courses) {
    $ids = implode(',', array_map('intval', array_column($courses, 'id')));
    $vr = $conn->query("SELECT course_id, COUNT(*) as count FROM videos WHERE course_id IN ($ids) GROUP BY course_id");
    while ($v = $vr->fetch_assoc()) { $videoCounts[$v['course_id']] = $v['count']; }
    $vr->close();
}

// Fetch quiz counts
$quizCounts = [];
if ($courses) {
    $qr = $conn->query("SELECT course_id, COUNT(*) as count FROM quizzes WHERE course_id IN ($ids) GROUP BY course_id");
    while ($q = $qr->fetch_assoc()) { $quizCounts[$q['course_id']] = $q['count']; }
    $qr->close();
}

$totalCourses = count($courses);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <title>EDURAY — Courses</title>
  <?php include 'includes/head.php'; ?>
  <style>
    /* ── Page header ── */
    .page-hero {
        padding: 70px 5% 50px;
        text-align: center;
        background: linear-gradient(160deg, #faf7ff 0%, #f0e9ff 50%, #fdf5ec 100%);
        position: relative;
        overflow: hidden;
    }
    .page-hero::before {
        content: '';
        position: absolute; top: -80px; right: -80px;
        width: 400px; height: 400px; border-radius: 50%;
        background: radial-gradient(circle, rgba(105,48,195,.1) 0%, transparent 70%);
        pointer-events: none;
    }
    .page-hero-inner { position: relative; z-index: 1; }
    .page-hero .section-tag { margin-bottom: 16px; }
    .page-hero h1 {
        font-size: clamp(32px, 4vw, 52px);
        font-weight: 700;
        color: var(--text);
        margin-bottom: 14px;
    }
    .page-hero h1 .accent { color: var(--accent); font-style: italic; }
    .page-hero p {
        color: var(--text-muted);
        font-size: 16px;
        max-width: 520px;
        margin: 0 auto 32px;
        line-height: 1.7;
    }
    .course-count-pill {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        background: var(--primary-light);
        color: var(--primary);
        font-size: 13px;
        font-weight: 600;
        padding: 7px 18px;
        border-radius: var(--r-full);
        border: 1px solid rgba(74,26,140,.15);
        margin-bottom: 28px;
    }

    /* ── Search ── */
    .search-wrap {
        max-width: 480px;
        margin: 0 auto;
    }
    .search-box {
        display: flex;
        align-items: center;
        background: #fff;
        border-radius: var(--r-full);
        box-shadow: var(--shadow-md);
        border: 1.5px solid var(--border);
        overflow: hidden;
        padding: 5px 5px 5px 20px;
    }
    .search-box input {
        border: none;
        outline: none;
        flex: 1;
        font-family: 'DM Sans', sans-serif;
        font-size: 15px;
        color: var(--text);
        background: transparent;
        padding: 8px 0;
    }
    .search-box input::placeholder { color: var(--text-muted); }
    .search-box button {
        background: linear-gradient(135deg, var(--primary), var(--primary-mid));
        color: #fff;
        border: none;
        padding: 10px 24px;
        border-radius: var(--r-full);
        cursor: pointer;
        font-weight: 600;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 7px;
        transition: filter .2s;
        white-space: nowrap;
    }
    .search-box button:hover { filter: brightness(1.1); }

    /* ── Grid ── */
    .courses-section {
        padding: 56px 5% 80px;
    }
    .courses-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
        gap: 28px;
    }

    /* ── Course card ── */
    .course-card {
        background: #fff;
        border-radius: var(--r-xl);
        overflow: hidden;
        border: 1px solid var(--border);
        box-shadow: var(--shadow-sm);
        display: flex;
        flex-direction: column;
        transition: transform .3s var(--ease-out), box-shadow .3s, border-color .3s;
    }
    .course-card:hover {
        transform: translateY(-8px);
        box-shadow: var(--shadow-lg);
        border-color: rgba(74,26,140,.2);
    }
    .card-thumb {
        position: relative;
        height: 185px;
        overflow: hidden;
        background: var(--surface-2);
    }
    .card-thumb img {
        width: 100%; height: 100%;
        object-fit: cover;
        transition: transform .45s var(--ease-out);
    }
    .course-card:hover .card-thumb img { transform: scale(1.07); }
    .card-overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(to bottom, transparent 40%, rgba(26,16,48,.6) 100%);
        opacity: 0;
        transition: opacity .3s;
        display: flex;
        align-items: flex-end;
        padding: 16px;
    }
    .course-card:hover .card-overlay { opacity: 1; }
    .card-overlay-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: rgba(255,255,255,.95);
        color: var(--primary);
        font-size: 13px;
        font-weight: 700;
        padding: 8px 16px;
        border-radius: var(--r-full);
        text-decoration: none;
        transition: background .2s;
    }
    .card-overlay-btn:hover { background: #fff; color: var(--primary); }
    .card-rank {
        position: absolute;
        top: 12px;
        left: 12px;
        background: rgba(26,16,48,.75);
        backdrop-filter: blur(8px);
        color: #fff;
        font-size: 11px;
        font-weight: 700;
        padding: 4px 10px;
        border-radius: var(--r-full);
        border: 1px solid rgba(255,255,255,.2);
    }
    .card-body {
        padding: 22px 22px 20px;
        flex: 1;
        display: flex;
        flex-direction: column;
    }
    .card-title {
        font-family: 'Fraunces', serif;
        font-size: 17px;
        font-weight: 600;
        color: var(--text);
        line-height: 1.38;
        margin-bottom: 14px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .card-meta {
        display: flex;
        gap: 8px;
        margin-bottom: 18px;
        flex-wrap: wrap;
    }
    .meta-pill {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 5px 12px;
        border-radius: var(--r-full);
        font-size: 12.5px;
        font-weight: 600;
    }
    .meta-pill.videos { background: var(--primary-light); color: var(--primary); }
    .meta-pill.quizzes { background: var(--accent-light); color: var(--accent); }
    .btn-course {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        width: 100%;
        padding: 11px;
        border-radius: var(--r-md);
        background: linear-gradient(135deg, var(--primary), var(--primary-mid));
        color: #fff;
        text-decoration: none;
        font-weight: 700;
        font-size: 14px;
        margin-top: auto;
        transition: transform .2s var(--ease-out), box-shadow .2s, filter .2s;
        box-shadow: 0 4px 14px var(--primary-glow);
    }
    .btn-course:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(74,26,140,.3);
        filter: brightness(1.06);
        color: #fff;
    }

    /* No results */
    .empty-state {
        text-align: center;
        padding: 80px 20px;
        grid-column: 1 / -1;
    }
    .empty-state-icon {
        width: 80px; height: 80px;
        background: var(--primary-light);
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 36px;
        margin: 0 auto 20px;
    }
    .empty-state h3 { font-size: 22px; color: var(--text); margin-bottom: 8px; }
    .empty-state p { font-size: 15px; color: var(--text-muted); }

    @media (max-width: 991.98px) {
        .page-hero { padding: 50px 20px 40px; }
        .courses-section { padding: 40px 20px 60px; }
    }
  </style>
</head>
<body>

<?php include 'includes/navbar.php'; ?>

<!-- Page Hero -->
<section class="page-hero">
  <div class="page-hero-inner animate-fade-up">
    <div class="section-tag"><i class="fas fa-book-open"></i> Course Catalog</div>
    <h1>Explore Our <span class="accent">Courses</span></h1>
    <p>Discover expert-led programs designed to take you from beginner to professional. Start learning something new today.</p>
    <div class="course-count-pill">
      <i class="fas fa-layer-group"></i>
      <?= $totalCourses ?> courses available
    </div>
    <div class="search-wrap">
      <div class="search-box">
        <input type="text" id="courseSearch" placeholder="Search courses, skills, topics…">
        <button onclick="filterCourses()"><i class="fas fa-search"></i> Search</button>
      </div>
    </div>
  </div>
</section>

<!-- Courses Grid -->
<section class="courses-section">
  <div class="courses-grid" id="coursesGrid">

    <?php foreach ($courses as $index => $course):
      $videoCount = $videoCounts[$course['id']] ?? 0;
      $quizCount  = $quizCounts[$course['id']] ?? 0;
      $thumb = htmlspecialchars($course['thumbnail_url'] ?: 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=600&h=340&fit=crop');
      $title = htmlspecialchars($course['title']);
      $delay = ($index % 4) * 0.08;
    ?>
    <div class="course-card animate-fade-up" data-title="<?= strtolower($title) ?>" style="animation-delay:<?= $delay ?>s">
      <div class="card-thumb">
        <img src="<?= $thumb ?>" alt="<?= $title ?>" loading="lazy">
        <div class="card-overlay">
          <a href="course_page.php?course_id=<?= (int)$course['id'] ?>" class="card-overlay-btn">
            <i class="fas fa-eye"></i> Preview
          </a>
        </div>
        <span class="card-rank">#<?= $index + 1 ?></span>
      </div>
      <div class="card-body">
        <h3 class="card-title"><?= $title ?></h3>
        <div class="card-meta">
          <span class="meta-pill videos"><i class="fas fa-play-circle"></i> <?= $videoCount ?> Videos</span>
          <span class="meta-pill quizzes"><i class="fas fa-question-circle"></i> <?= $quizCount ?> Quizzes</span>
        </div>
        <a href="course_page.php?course_id=<?= (int)$course['id'] ?>" class="btn-course">
          Start Learning <i class="fas fa-arrow-right"></i>
        </a>
      </div>
    </div>
    <?php endforeach; ?>

    <?php if (empty($courses)): ?>
    <div class="empty-state">
      <div class="empty-state-icon">📚</div>
      <h3>No Courses Yet</h3>
      <p>New courses are being added regularly. Check back soon!</p>
    </div>
    <?php endif; ?>

  </div>
</section>

<?php include 'includes/footer.php'; ?>

<script>
function filterCourses() {
  const query = document.getElementById('courseSearch').value.toLowerCase().trim();
  document.querySelectorAll('.course-card').forEach(card => {
    card.style.display = card.dataset.title.includes(query) ? '' : 'none';
  });
}
document.getElementById('courseSearch').addEventListener('input', filterCourses);

// Trigger animations on scroll
const obs = new IntersectionObserver(entries => {
  entries.forEach(e => { if (e.isIntersecting) e.target.style.animationPlayState = 'running'; });
}, { threshold: 0.08 });
document.querySelectorAll('.animate-fade-up').forEach(el => {
  el.style.animationPlayState = 'paused';
  obs.observe(el);
});
</script>
</body>
</html>
