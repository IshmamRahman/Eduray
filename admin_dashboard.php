<?php
session_start();
require __DIR__ . '/db.php';

$userId   = $_SESSION['user_id'] ?? null;
$userName = $_SESSION['name']    ?? null;
$userMail = $_SESSION['email']   ?? null;
$isAdmin  = !empty($_SESSION['is_admin']);

if (!$isAdmin) {
    header('Location: courses.php');
    exit;
}

// ── Courses ───────────────────────────────────────────────────────────────────
$courses = [];
$res = $conn->query('SELECT id, title, slug, thumbnail_url FROM courses ORDER BY id');
while ($row = $res->fetch_assoc()) { $courses[] = $row; }
$res->close();

// ── Videos by course ──────────────────────────────────────────────────────────
$byCourse = [];
if ($courses) {
    $ids = implode(',', array_map('intval', array_column($courses, 'id')));
    $vr  = $conn->query("SELECT id, course_id, title, embed_url, video_type, video_path, thumb_url, position, duration FROM videos WHERE course_id IN ($ids) ORDER BY position, id");
    while ($v = $vr->fetch_assoc()) { $byCourse[$v['course_id']][] = $v; }
    $vr->close();
}

// ── Quizzes by course ─────────────────────────────────────────────────────────
$byCourseQuiz = [];
if ($courses) {
    $ids = implode(',', array_map('intval', array_column($courses, 'id')));
    $qr  = $conn->query("SELECT id, course_id, title FROM quizzes WHERE course_id IN ($ids) ORDER BY id");
    while ($q = $qr->fetch_assoc()) { $byCourseQuiz[$q['course_id']][] = $q; }
    $qr->close();
}

// ── Live classes by course ────────────────────────────────────────────────────
$byCourseLive = [];
if ($courses) {
    $ids = implode(',', array_map('intval', array_column($courses, 'id')));
    $lr  = $conn->query("SELECT id, course_id, title, description, meet_link, scheduled_at FROM live_classes WHERE course_id IN ($ids) ORDER BY scheduled_at ASC");
    while ($l = $lr->fetch_assoc()) { $byCourseLive[$l['course_id']][] = $l; }
    $lr->close();
}

// ── All videos for manage table ───────────────────────────────────────────────
$allVideos = [];
$av = $conn->query("
    SELECT v.id, v.course_id, v.title, v.embed_url, v.video_type, v.video_path,
           v.thumb_url, v.position, v.duration, c.title AS course_title
    FROM videos v
    JOIN courses c ON c.id = v.course_id
    ORDER BY c.title, v.position, v.id
");
while ($row = $av->fetch_assoc()) { $allVideos[] = $row; }
$av->close();

// ── Stats ─────────────────────────────────────────────────────────────────────
$totalCourses     = count($courses);
$totalVideos      = (int)$conn->query("SELECT COUNT(*) AS c FROM videos")->fetch_assoc()['c'];
$totalQuizzes     = (int)$conn->query("SELECT COUNT(*) AS c FROM quizzes")->fetch_assoc()['c'];
$totalEnrolls     = (int)$conn->query("SELECT COUNT(*) AS c FROM enrollments")->fetch_assoc()['c'];

$colCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'is_instructor'");
$hasInstructorCol = ($colCheck && $colCheck->num_rows > 0);

if ($hasInstructorCol) {
    $totalStudents    = (int)$conn->query("SELECT COUNT(*) AS c FROM users WHERE is_admin=0 AND is_instructor=0")->fetch_assoc()['c'];
    $totalInstructors = (int)$conn->query("SELECT COUNT(*) AS c FROM users WHERE is_instructor=1")->fetch_assoc()['c'];
} else {
    $totalStudents    = (int)$conn->query("SELECT COUNT(*) AS c FROM users WHERE is_admin=0")->fetch_assoc()['c'];
    $totalInstructors = 0;
}

// ── Users data ────────────────────────────────────────────────────────────────
$allStudents     = [];
$allInstructors  = [];

if ($hasInstructorCol) {
    $sr = $conn->query("
        SELECT u.id, u.name, u.email, u.created_at,
               COUNT(DISTINCT e.course_id) AS enrolled_courses
        FROM users u
        LEFT JOIN enrollments e ON e.user_id = u.id
        WHERE u.is_admin=0 AND u.is_instructor=0
        GROUP BY u.id
        ORDER BY u.created_at DESC
    ");
    while ($row = $sr->fetch_assoc()) { $allStudents[] = $row; }
    $sr->close();

    $ir = $conn->query("
        SELECT u.id, u.name, u.email, u.created_at,
               COUNT(DISTINCT c.id) AS course_count
        FROM users u
        LEFT JOIN courses c ON c.instructor_id = u.id
        WHERE u.is_instructor=1
        GROUP BY u.id
        ORDER BY u.created_at DESC
    ");
    while ($row = $ir->fetch_assoc()) { $allInstructors[] = $row; }
    $ir->close();
} else {
    $sr = $conn->query("
        SELECT u.id, u.name, u.email, u.created_at,
               COUNT(DISTINCT e.course_id) AS enrolled_courses
        FROM users u
        LEFT JOIN enrollments e ON e.user_id = u.id
        WHERE u.is_admin=0
        GROUP BY u.id
        ORDER BY u.created_at DESC
    ");
    while ($row = $sr->fetch_assoc()) { $allStudents[] = $row; }
    $sr->close();
}

// ── Active section + active course ───────────────────────────────────────────
$section        = $_GET['s']  ?? 'overview';
$activeCourseId = (int)($_GET['cid'] ?? 0);
$activeCourse   = null;
if ($activeCourseId) {
    foreach ($courses as $c) {
        if ((int)$c['id'] === $activeCourseId) { $activeCourse = $c; break; }
    }
    if ($activeCourse) $section = 'courseeditor';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>EDURAY – Admin Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

  <style>
    :root {
      --p:      #5f259f;
      --p-dark: #3d1066;
      --p-lt:   #f0ebff;
      --accent: #ff6b00;
      --gold:   #ffe600;
      --bg:     #f4f1fa;
      --white:  #ffffff;
      --border: #e8e3f5;
      --text:   #1e1a2e;
      --muted:  #7b7a99;
      --sw:     260px;
      --green:  #00b894;
      --red:    #dc2626;
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      background: var(--bg); color: var(--text);
      display: flex; min-height: 100vh;
    }

    /* ── Sidebar ── */
    .sidebar {
      width: var(--sw); position: fixed; top: 0; left: 0; bottom: 0; z-index: 200;
      background: var(--p-dark);
      display: flex; flex-direction: column;
      box-shadow: 2px 0 12px rgba(0,0,0,.15); overflow-y: auto;
    }
    .sb-brand { padding: 22px 20px 16px; border-bottom: 1px solid rgba(255,255,255,.1); }
    .sb-brand .logo-text { font-size: 21px; font-weight: 800; color: var(--gold); letter-spacing: 1px; }
    .sb-brand .logo-text span { color: #fff; }
    .sb-pill {
      display: inline-block; margin-top: 6px;
      background: rgba(255,255,255,.12); color: rgba(255,255,255,.7);
      font-size: 9.5px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase;
      padding: 3px 10px; border-radius: 20px;
    }
    .sb-nav { flex: 1; padding: 14px 10px; }
    .sb-section-lbl {
      font-size: 9px; font-weight: 800; letter-spacing: 3px; text-transform: uppercase;
      color: rgba(255,255,255,.3); padding: 14px 12px 5px;
    }
    .sb-link {
      display: flex; align-items: center; gap: 11px;
      padding: 10px 14px; border-radius: 8px;
      color: rgba(255,255,255,.6) !important;
      font-size: 13.5px; font-weight: 500; text-decoration: none;
      transition: background .15s, color .15s; margin-bottom: 2px; position: relative;
    }
    .sb-link i { width: 17px; text-align: center; font-size: 14px; }
    .sb-link:hover { background: rgba(255,255,255,.08); color: #fff !important; }
    .sb-link.active { background: rgba(255,230,0,.12); color: var(--gold) !important; font-weight: 700; }
    .sb-link.active::before {
      content: ''; position: absolute; left: 0; top: 6px; bottom: 6px;
      width: 3px; border-radius: 0 3px 3px 0; background: var(--gold);
    }
    .sb-badge {
      margin-left: auto; background: rgba(255,230,0,.2); color: var(--gold);
      font-size: 10px; font-weight: 800; padding: 2px 7px; border-radius: 20px;
    }
    .sb-footer { padding: 14px 10px; border-top: 1px solid rgba(255,255,255,.1); }
    .sb-user {
      display: flex; align-items: center; gap: 10px;
      background: rgba(255,255,255,.07); border-radius: 8px; padding: 10px 12px; margin-bottom: 10px;
    }
    .sb-avatar {
      width: 34px; height: 34px; border-radius: 50%; background: var(--gold);
      display: flex; align-items: center; justify-content: center;
      font-weight: 800; font-size: 14px; color: #222; flex-shrink: 0;
    }
    .sb-uname { font-size: 13px; font-weight: 700; color: #fff; }
    .sb-urole { font-size: 10px; color: rgba(255,255,255,.4); }
    .sb-logout {
      display: flex; align-items: center; justify-content: center; gap: 8px;
      width: 100%; padding: 9px; border-radius: 8px;
      background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.1);
      color: rgba(255,255,255,.6) !important; font-size: 13px; font-weight: 500; text-decoration: none;
      transition: background .2s;
    }
    .sb-logout:hover { background: rgba(220,38,38,.2); color: #fca5a5 !important; }

    /* ── Main ── */
    .main-area { margin-left: var(--sw); flex: 1; display: flex; flex-direction: column; }
    .topbar {
      background: var(--white); border-bottom: 1px solid var(--border);
      padding: 15px 30px; display: flex; align-items: center; justify-content: space-between;
      gap: 12px; position: sticky; top: 0; z-index: 100;
    }
    .topbar-heading { font-size: 19px; font-weight: 800; }
    .topbar-heading span { color: var(--p); }
    .topbar-breadcrumb { font-size: 12px; color: var(--muted); display: flex; align-items: center; gap: 5px; margin-top: 2px; }
    .topbar-breadcrumb a { color: var(--p); text-decoration: none; font-weight: 600; }
    .btn-site {
      display: inline-flex; align-items: center; gap: 7px;
      padding: 8px 16px; border-radius: 7px;
      background: var(--white); border: 1.5px solid var(--border);
      color: var(--text); font-size: 13px; font-weight: 600; text-decoration: none;
      transition: border-color .2s, color .2s;
    }
    .btn-site:hover { border-color: var(--p); color: var(--p); }
    .content { padding: 28px 30px; flex: 1; }
    .panel { display: none; }
    .panel.active { display: block; }

    /* ── Flash alerts ── */
    .flash-success {
      background: #dcfce7; border: 1px solid #86efac; color: #16a34a;
      border-radius: 10px; padding: 12px 18px; font-size: 13.5px; font-weight: 600;
      margin-bottom: 20px; display: flex; align-items: center; gap: 10px;
    }
    .flash-error {
      background: #fee2e2; border: 1px solid #fca5a5; color: #dc2626;
      border-radius: 10px; padding: 12px 18px; font-size: 13.5px; font-weight: 600;
      margin-bottom: 20px; display: flex; align-items: center; gap: 10px;
    }

    /* ── Stats ── */
    .stats-row { display: grid; grid-template-columns: repeat(6,1fr); gap: 14px; margin-bottom: 28px; }
    .stat-card {
      background: var(--white); border: 1px solid var(--border); border-radius: 12px;
      padding: 18px 16px; display: flex; align-items: center; gap: 12px;
    }
    .stat-icon { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
    .stat-num { font-size: 24px; font-weight: 800; line-height: 1; }
    .stat-lbl { font-size: 11.5px; color: var(--muted); margin-top: 3px; }

    /* ── Section heading ── */
    .sec-hd { margin-bottom: 20px; }
    .sec-hd h3 { font-size: 20px; font-weight: 800; }
    .sec-hd p { font-size: 13px; color: var(--muted); margin-top: 3px; }

    /* ── Cards ── */
    .fcard { background: var(--white); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; margin-bottom: 22px; }
    .fcard-head { padding: 14px 20px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 12px; }
    .fcard-hicon { width: 34px; height: 34px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink:0; }
    .fcard-head h5 { font-size: 15px; font-weight: 700; margin: 0; }
    .fcard-head p { font-size: 11.5px; color: var(--muted); margin: 2px 0 0; }
    .fcard-body { padding: 20px; }

    /* ── Inputs ── */
    .form-label { font-size: 12.5px; font-weight: 600; color: #444; margin-bottom: 5px; }
    .form-control, .form-select {
      border-radius: 7px; border: 1.5px solid var(--border);
      font-size: 13.5px; padding: 9px 13px;
      font-family: 'Plus Jakarta Sans', sans-serif;
      transition: border-color .2s;
    }
    .form-control:focus, .form-select:focus { border-color: var(--p); box-shadow: 0 0 0 3px rgba(95,37,159,.08); outline: none; }

    /* ── Buttons ── */
    .abtn {
      display: inline-flex; align-items: center; gap: 7px;
      padding: 9px 20px; border-radius: 7px;
      font-size: 13.5px; font-weight: 600; border: none; cursor: pointer;
      font-family: 'Plus Jakarta Sans', sans-serif; text-decoration: none; white-space: nowrap;
      transition: opacity .15s;
    }
    .abtn:hover { opacity: .88; }
    .abtn-purple { background: var(--p); color: #fff; }
    .abtn-purple:hover { color: #fff; }
    .abtn-green  { background: #16a34a; color: #fff; }
    .abtn-green:hover  { color: #fff; }
    .abtn-blue   { background: #0284c7; color: #fff; }
    .abtn-blue:hover   { color: #fff; }
    .abtn-amber  { background: #b45309; color: #fff; }
    .abtn-amber:hover  { color: #fff; }
    .abtn-teal   { background: #00b894; color: #fff; }
    .abtn-teal:hover   { color: #fff; }
    .abtn-red    { background: var(--red); color: #fff; }
    .abtn-red:hover    { color: #fff; }
    .abtn-sm { padding: 7px 14px; font-size: 12.5px; }

    /* ── Drop zone ── */
    .drop-zone {
      border: 2px dashed var(--border); border-radius: 8px;
      padding: 26px 18px; text-align: center; background: var(--bg);
      cursor: pointer; transition: border-color .2s, background .2s;
    }
    .drop-zone:hover, .drop-zone.dragover { border-color: var(--p); background: var(--p-lt); }
    .drop-zone i { font-size: 28px; color: var(--muted); display: block; margin-bottom: 8px; }
    .drop-zone p { font-size: 13.5px; color: var(--muted); margin: 0 0 4px; }
    .drop-zone small { font-size: 11.5px; color: #bbb; }
    .fileInfo { font-size: 12.5px; font-weight: 700; color: #16a34a; margin-top: 8px; }

    /* Upload progress */
    .upload-progress { display: none; margin-top: 14px; }
    .progress { height: 8px; border-radius: 8px; background: var(--border); overflow: hidden; }
    .progress-bar { border-radius: 8px; background: var(--p); transition: width .3s; }

    /* ── Video table / generic table ── */
    .vtbl, .utbl { width: 100%; border-collapse: collapse; }
    .vtbl th, .utbl th {
      background: var(--bg); padding: 11px 14px;
      font-size: 11px; font-weight: 800; color: var(--muted);
      text-transform: uppercase; letter-spacing: .8px;
      border-bottom: 2px solid var(--border); text-align: left;
    }
    .vtbl td, .utbl td { padding: 12px 14px; border-bottom: 1px solid var(--border); font-size: 13.5px; vertical-align: middle; }
    .vtbl tr:last-child td, .utbl tr:last-child td { border-bottom: none; }
    .vtbl tr:hover td, .utbl tr:hover td { background: #faf8ff; }
    .vtbl img { width: 76px; height: 48px; object-fit: cover; border-radius: 6px; display: block; }
    .vtbl .no-thumb { width: 76px; height: 48px; border-radius: 6px; background: var(--p-lt); display: flex; align-items: center; justify-content: center; color: #ccc; font-size: 18px; }
    .vtype { display: inline-flex; align-items: center; gap: 5px; padding: 3px 9px; border-radius: 20px; font-size: 11px; font-weight: 700; }
    .vtype.yt  { background: #fee2e2; color: #dc2626; }
    .vtype.loc { background: #dcfce7; color: #16a34a; }

    /* User avatar circle */
    .u-avatar {
      width: 34px; height: 34px; border-radius: 50%;
      display: inline-flex; align-items: center; justify-content: center;
      font-size: 13px; font-weight: 800; flex-shrink: 0;
    }

    /* ── Table buttons ── */
    .tbtn {
      display: inline-flex; align-items: center; gap: 4px;
      padding: 5px 11px; border-radius: 6px; font-size: 12px; font-weight: 700;
      cursor: pointer; text-decoration: none; border: 1.5px solid; background: transparent;
      font-family: 'Plus Jakarta Sans', sans-serif; transition: background .15s, color .15s;
    }
    .tbtn-edit  { color: var(--p); border-color: var(--p); }
    .tbtn-edit:hover  { background: var(--p); color: #fff; }
    .tbtn-del   { color: #dc2626; border-color: #dc2626; }
    .tbtn-del:hover   { background: #dc2626; color: #fff; }
    .tbtn-teal  { color: #00b894; border-color: #00b894; }
    .tbtn-teal:hover  { background: #00b894; color: #fff; }

    /* ── Search bar for tables ── */
    .tbl-search {
      display: flex; align-items: center; gap: 8px;
      background: var(--bg); border: 1.5px solid var(--border); border-radius: 8px;
      padding: 8px 14px; margin-bottom: 16px; max-width: 320px;
    }
    .tbl-search i { color: var(--muted); font-size: 13px; }
    .tbl-search input {
      border: none; background: transparent; font-size: 13.5px;
      font-family: 'Plus Jakarta Sans', sans-serif; outline: none; width: 100%;
    }

    /* ── Tab pills for Users panel ── */
    .user-tabs { display: flex; gap: 6px; margin-bottom: 20px; flex-wrap: wrap; }
    .utab {
      display: inline-flex; align-items: center; gap: 7px;
      padding: 9px 20px; border-radius: 8px; font-size: 13.5px; font-weight: 700;
      cursor: pointer; border: 1.5px solid var(--border); background: var(--white);
      color: var(--muted); font-family: 'Plus Jakarta Sans', sans-serif;
      transition: all .15s;
    }
    .utab:hover { border-color: var(--p); color: var(--p); }
    .utab.active { background: var(--p); border-color: var(--p); color: #fff; }
    .utab-count { background: rgba(255,255,255,.3); padding: 1px 7px; border-radius: 12px; font-size: 11px; }
    .utab:not(.active) .utab-count { background: var(--p-lt); color: var(--p); }
    .upanel { display: none; }
    .upanel.active { display: block; }

    /* ── Course grids ── */
    .course-grid, .course-cards-grid {
      display: grid; grid-template-columns: repeat(auto-fill, minmax(260px,1fr)); gap: 14px;
    }

    .cc-wrap { position: relative; }
    .cc-del-btn {
      position: absolute; top: 8px; right: 8px; z-index: 10;
      display: inline-flex; align-items: center; gap: 5px;
      padding: 5px 10px; background: rgba(220,38,38,.85);
      color: #fff; border: none; border-radius: 6px;
      font-size: 11.5px; font-weight: 700; cursor: pointer;
      font-family: 'Plus Jakarta Sans', sans-serif;
      backdrop-filter: blur(4px); transition: background .15s;
    }
    .cc-del-btn:hover { background: #dc2626; }

    .cov-card, .cc-card {
      background: var(--white); border: 1px solid var(--border); border-radius: 12px;
      overflow: hidden; transition: box-shadow .2s, border-color .2s;
      text-decoration: none; color: inherit; display: block;
    }
    .cov-card:hover, .cc-card:hover { box-shadow: 0 6px 20px rgba(0,0,0,.08); border-color: var(--p); }
    .cov-card img, .cc-card-thumb { width: 100%; height: 140px; object-fit: cover; display: block; }
    .cc-card-thumb-placeholder {
      width: 100%; height: 140px; background: var(--p-lt);
      display: flex; align-items: center; justify-content: center; color: #c5aeeb; font-size: 36px;
    }
    .cov-card-body, .cc-card-body { padding: 14px 16px; }
    .cov-card-title, .cc-card-title { font-size: 14.5px; font-weight: 700; margin-bottom: 8px; }
    .cov-meta, .cc-card-meta { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 10px; }
    .cov-meta-item { display: flex; align-items: center; gap: 5px; font-size: 12px; color: var(--muted); }
    .cov-meta-item i { color: var(--p); font-size: 11px; }
    .cc-chip { display: inline-flex; align-items: center; gap: 4px; padding: 3px 9px; border-radius: 20px; font-size: 11.5px; font-weight: 700; background: var(--p-lt); color: var(--p); }
    .cc-chip.amber { background: #fff3e0; color: #b45309; }
    .cc-chip.teal  { background: #e6fff5; color: #00b894; }
    .cc-chip.red   { background: #fee2e2; color: #dc2626; }
    .cc-edit-btn {
      display: flex; align-items: center; justify-content: center; gap: 6px;
      width: 100%; padding: 9px; background: var(--p); color: #fff;
      border-radius: 7px; font-size: 13px; font-weight: 700;
    }

    /* ── Course Editor ── */
    .editor-hero { background: var(--white); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; margin-bottom: 22px; }
    .editor-hero-banner { height: 150px; width: 100%; object-fit: cover; display: block; }
    .editor-hero-banner-placeholder { height: 150px; width: 100%; background: var(--p-dark); display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,.2); font-size: 48px; }
    .editor-hero-body { padding: 18px 22px; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
    .editor-hero-title { font-size: 20px; font-weight: 800; margin-bottom: 6px; }
    .editor-hero-meta { display: flex; gap: 16px; flex-wrap: wrap; }
    .editor-meta-item { display: flex; align-items: center; gap: 5px; font-size: 13px; color: var(--muted); }
    .editor-meta-item i { color: var(--p); }

    /* ── Editor Tabs ── */
    .editor-tabs {
      display: flex; gap: 4px; background: var(--white);
      border: 1px solid var(--border); border-radius: 10px;
      padding: 5px; margin-bottom: 20px; flex-wrap: wrap;
    }
    .etab {
      flex: 1; min-width: 120px; display: flex; align-items: center; justify-content: center; gap: 7px;
      padding: 10px 14px; border-radius: 7px; font-size: 13px; font-weight: 600;
      cursor: pointer; color: var(--muted); border: none; background: transparent;
      font-family: 'Plus Jakarta Sans', sans-serif; transition: background .15s, color .15s;
    }
    .etab:hover { background: var(--bg); color: var(--text); }
    .etab.active { background: var(--p); color: #fff; }
    .etab.active.teal-tab { background: #00b894; }
    .etab-count {
      width: 20px; height: 20px; border-radius: 5px;
      display: flex; align-items: center; justify-content: center;
      font-size: 11px; font-weight: 800;
      background: rgba(255,255,255,.25);
    }
    .etab:not(.active) .etab-count { background: var(--p-lt); color: var(--p); }
    .etab:not(.active).teal-tab .etab-count { background: #e6fff5; color: #00b894; }
    .etab-panel { display: none; }
    .etab-panel.active { display: block; }

    /* ── Quiz rows ── */
    .qrow {
      display: flex; align-items: center; justify-content: space-between;
      padding: 11px 14px; background: var(--bg); border: 1px solid var(--border);
      border-radius: 8px; margin-bottom: 7px; transition: border-color .15s;
    }
    .qrow:hover { border-color: var(--p); }
    .qrow-left { display: flex; align-items: center; gap: 8px; font-size: 13.5px; font-weight: 600; }
    .qrow-left i { color: var(--p); }

    /* ── Live class rows ── */
    .lrow {
      display: flex; align-items: center; gap: 14px;
      padding: 16px 18px; border-radius: 10px;
      border: 1.5px solid var(--border);
      margin-bottom: 10px; transition: all .2s;
      background: var(--white);
    }
    .lrow-upcoming { border-left: 4px solid #00b894; }
    .lrow-upcoming:hover { background: #f0fff9; border-color: #00b894; }
    .lrow-live-now {
      border-left: 4px solid #e17055; background: #fff8f5;
      animation: pulseRow 2s ease-in-out infinite;
    }
    .lrow-finished { border-left: 4px solid #ccc; opacity: .75; background: #fafafa; }
    @keyframes pulseRow {
      0%,100% { box-shadow: 0 0 0 0 rgba(225,112,85,.15); }
      50%      { box-shadow: 0 0 0 6px rgba(225,112,85,.15); }
    }
    @keyframes blink { 0%,100%{opacity:1} 50%{opacity:.3} }
    .lrow-icon-wrap { display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .lrow-dot { width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0; }
    .live-dot     { background:#e17055; animation:blink 1.2s infinite; box-shadow:0 0 0 4px rgba(225,112,85,.2); }
    .upcoming-dot { background:#00b894; }
    .finished-dot { background:#ccc; }
    .lrow-body { flex: 1; min-width: 0; }
    .lrow-top  { display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:4px; }
    .lrow-title-text { font-size:14.5px; font-weight:700; color:var(--text); }
    .lrow-desc { font-size:12.5px; color:var(--muted); margin-bottom:5px; }
    .lrow-meta-row {
      display:flex; align-items:center; gap:14px; flex-wrap:wrap;
      font-size:12px; color:var(--muted);
    }
    .lrow-meta-row i { color:#00b894; }
    .lrow-meta-row span { display:flex; align-items:center; gap:5px; }
    .lbadge { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; white-space:nowrap; }
    .lbadge-upcoming { background:#e6fff5; color:#00b894; }
    .lbadge-live-now { background:#fff0eb; color:#e17055; }
    .lbadge-finished { background:#f0f0f0; color:#999; }
    .lrow-countdown { font-size:12px; color:var(--p); font-weight:600; }
    .lrow-action { flex-shrink:0; }
    .lbtn-join {
      display: inline-flex; align-items:center; gap:7px; padding: 9px 20px; border-radius:8px;
      background: linear-gradient(135deg,#e17055,#d63031); color: white; font-size:13px; font-weight:700;
      text-decoration:none; white-space:nowrap; animation: pulseBtn 1.5s ease-in-out infinite;
    }
    @keyframes pulseBtn {
      0%,100%{ box-shadow:0 4px 12px rgba(225,112,85,.4); }
      50%{ box-shadow:0 4px 20px rgba(225,112,85,.7); }
    }
    .lbtn-waiting { display:inline-flex; align-items:center; gap:7px; padding: 9px 18px; border-radius:8px; background: #e6fff5; color:#00b894; font-size:13px; font-weight:700; white-space:nowrap; }
    .lbtn-finished { display:inline-flex; align-items:center; gap:7px; padding: 9px 18px; border-radius:8px; background:#f0f0f0; color:#999; font-size:13px; font-weight:600; white-space:nowrap; }

    /* ── Delete Quiz Modal ── */
    .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1000; align-items:center; justify-content:center; }
    .modal-overlay.show { display:flex; }
    .modal-box { background:#fff; border-radius:16px; padding:28px; max-width:400px; width:90%; box-shadow:0 20px 60px rgba(0,0,0,.2); animation:modalIn .25s ease; text-align:center; }
    @keyframes modalIn { from{transform:scale(.92);opacity:0} to{transform:scale(1);opacity:1} }
    .modal-icon { width:54px; height:54px; border-radius:50%; background:#fee2e2; display:flex; align-items:center; justify-content:center; font-size:22px; margin:0 auto 14px; }
    .modal-title { font-size:16px; font-weight:800; margin-bottom:7px; }
    .modal-msg { font-size:13px; color:#666; line-height:1.6; margin-bottom:20px; }
    .modal-actions { display:flex; gap:10px; justify-content:center; flex-wrap:wrap; }

    /* ── Empty state ── */
    .empty-state { text-align: center; padding: 48px 20px; color: var(--muted); }
    .empty-state i { font-size: 36px; color: #ddd; margin-bottom: 10px; display: block; }
    .empty-state p { font-size: 13.5px; }

    @media(max-width:1100px) { .stats-row { grid-template-columns: repeat(3,1fr); } }
    @media(max-width:900px) {
      .stats-row { grid-template-columns: repeat(3,1fr); }
      .etab { min-width: unset; }
    }
    @media(max-width:600px) {
      .sidebar { transform: translateX(-100%); }
      .main-area { margin-left: 0; }
      .stats-row { grid-template-columns: repeat(2,1fr); }
      .content { padding: 18px 14px; }
    }
  </style>
</head>
<body>

<!-- ════════ DELETE QUIZ MODAL ════════ -->
<div class="modal-overlay" id="deleteQuizModal">
  <div class="modal-box">
    <div class="modal-icon"><i class="fas fa-trash" style="color:#dc2626;"></i></div>
    <div class="modal-title">Delete This Quiz?</div>
    <div class="modal-msg" id="deleteQuizMsg">This will permanently delete the quiz, all its questions, and all student attempt records. This cannot be undone.</div>
    <div class="modal-actions">
      <form method="post" action="add_quiz_questions.php" id="deleteQuizForm">
        <input type="hidden" name="action" value="delete_quiz">
        <input type="hidden" name="quiz_id" id="deleteQuizId">
        <input type="hidden" name="course_id" id="deleteQuizCourseId">
        <button type="submit" class="abtn abtn-red"><i class="fas fa-trash"></i> Yes, Delete Quiz</button>
      </form>
      <button class="abtn" style="background:#6b7280;color:#fff;" onclick="closeDeleteQuizModal()">Cancel</button>
    </div>
  </div>
</div>

<!-- ════════ SIDEBAR ════════ -->
<aside class="sidebar">
  <div class="sb-brand">
    <div class="logo-text">📚 <span>EDU</span>RAY</div>
    <div class="sb-pill">Admin Panel</div>
  </div>

  <nav class="sb-nav">
    <div class="sb-section-lbl">Main</div>
    <a href="?s=overview" class="sb-link <?= ($section==='overview')?'active':'' ?>">
      <i class="fas fa-home"></i> Overview
    </a>

    <div class="sb-section-lbl">Courses</div>
    <a href="?s=courses" class="sb-link <?= ($section==='courses')?'active':'' ?>">
      <i class="fas fa-th-large"></i> All Courses
      <span class="sb-badge"><?= $totalCourses ?></span>
    </a>
    <a href="?s=addcourse" class="sb-link <?= ($section==='addcourse')?'active':'' ?>">
      <i class="fas fa-plus-circle"></i> Add New Course
    </a>

    <div class="sb-section-lbl">People</div>
    <a href="?s=users" class="sb-link <?= ($section==='users')?'active':'' ?>">
      <i class="fas fa-users"></i> Users & Instructors
      <span class="sb-badge"><?= $totalStudents + $totalInstructors ?></span>
    </a>

    <div class="sb-section-lbl">Site</div>
    <a href="index.php" class="sb-link">
      <i class="fas fa-globe"></i> View Student Site
    </a>
  </nav>

  <div class="sb-footer">
    <div class="sb-user">
      <div class="sb-avatar"><?= strtoupper(substr($userName ?: $userMail, 0, 1)) ?></div>
      <div>
        <div class="sb-uname"><?= htmlspecialchars($userName ?: 'Admin') ?></div>
        <div class="sb-urole">Administrator</div>
      </div>
    </div>
    <a href="logout.php" class="sb-logout">
      <i class="fas fa-sign-out-alt"></i> Sign Out
    </a>
  </div>
</aside>

<!-- ════════ MAIN AREA ════════ -->
<div class="main-area">

  <div class="topbar">
    <div class="topbar-left">
      <?php if ($section === 'courseeditor' && $activeCourse): ?>
        <div>
          <div class="topbar-heading"><?= htmlspecialchars($activeCourse['title']) ?></div>
          <div class="topbar-breadcrumb">
            <a href="?s=courses">Courses</a>
            <span>›</span>
            <span>Course Editor</span>
          </div>
        </div>
      <?php else: ?>
        <?php
          $heads = [
            'overview'  => 'Dashboard <span>Overview</span>',
            'courses'   => 'All <span>Courses</span>',
            'addcourse' => 'Add New <span>Course</span>',
            'users'     => 'Users &amp; <span>Instructors</span>',
          ];
        ?>
        <div class="topbar-heading"><?= $heads[$section] ?? 'Admin <span>Dashboard</span>' ?></div>
      <?php endif; ?>
    </div>
    <a href="index.php" class="btn-site">
      <i class="fas fa-external-link-alt"></i> View Student Site
    </a>
  </div>

  <div class="content">

    <!-- ── Global flash messages ── -->
    <?php if (!empty($_GET['deleted'])): ?>
      <div class="flash-success">
        <i class="fas fa-check-circle" style="font-size:16px;"></i>
        Course <strong>"<?= htmlspecialchars($_GET['deleted']) ?>"</strong> was permanently deleted along with all its content.
      </div>
    <?php endif; ?>
    <?php if (!empty($_GET['quiz_deleted'])): ?>
      <div class="flash-success">
        <i class="fas fa-check-circle" style="font-size:16px;"></i>
        Quiz was permanently deleted along with all its questions and student attempts.
      </div>
    <?php endif; ?>
    <?php if (!empty($_GET['error'])): ?>
      <div class="flash-error">
        <i class="fas fa-exclamation-circle" style="font-size:16px;"></i>
        <?php
          $msgs = [
            'invalid_id' => 'Invalid course ID.',
            'not_found'  => 'Course not found.',
            'db_failed'  => 'Database error — deletion failed. Please try again.',
          ];
          echo $msgs[$_GET['error']] ?? 'Something went wrong.';
        ?>
      </div>
    <?php endif; ?>

    <!-- ════════ OVERVIEW ════════ -->
    <div class="panel <?= $section==='overview'?'active':'' ?>">
      <div class="stats-row">
        <div class="stat-card">
          <div class="stat-icon" style="background:#f0ebff;color:var(--p);"><i class="fas fa-book"></i></div>
          <div><div class="stat-num"><?= $totalCourses ?></div><div class="stat-lbl">Courses</div></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon" style="background:#fff0e6;color:var(--accent);"><i class="fas fa-video"></i></div>
          <div><div class="stat-num"><?= $totalVideos ?></div><div class="stat-lbl">Videos</div></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon" style="background:#fef9c3;color:#b45309;"><i class="fas fa-clipboard-check"></i></div>
          <div><div class="stat-num"><?= $totalQuizzes ?></div><div class="stat-lbl">Quizzes</div></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon" style="background:#dcfce7;color:#16a34a;"><i class="fas fa-user-graduate"></i></div>
          <div><div class="stat-num"><?= $totalStudents ?></div><div class="stat-lbl">Students</div></div>
        </div>
        <?php if ($hasInstructorCol): ?>
        <div class="stat-card">
          <div class="stat-icon" style="background:#fce7f3;color:#be185d;"><i class="fas fa-chalkboard-teacher"></i></div>
          <div><div class="stat-num"><?= $totalInstructors ?></div><div class="stat-lbl">Instructors</div></div>
        </div>
        <?php endif; ?>
        <div class="stat-card">
          <div class="stat-icon" style="background:#e0f2fe;color:#0284c7;"><i class="fas fa-graduation-cap"></i></div>
          <div><div class="stat-num"><?= $totalEnrolls ?></div><div class="stat-lbl">Enrollments</div></div>
        </div>
      </div>

      <div class="sec-hd">
        <h3>All Courses</h3>
        <p>Click a course to open its editor — hover to reveal the delete button</p>
      </div>
      <?php if (empty($courses)): ?>
        <div class="empty-state">
          <i class="fas fa-book-open"></i>
          <p>No courses yet. <a href="?s=addcourse" style="color:var(--p);font-weight:700;">Add your first course →</a></p>
        </div>
      <?php else: ?>
        <div class="course-grid">
          <?php foreach ($courses as $c): ?>
            <div class="cc-wrap">
              <a href="?cid=<?= (int)$c['id'] ?>" class="cov-card">
                <img src="<?= htmlspecialchars($c['thumbnail_url'] ?: 'https://via.placeholder.com/400x130?text=No+Image') ?>" alt="">
                <div class="cov-card-body">
                  <div class="cov-card-title"><?= htmlspecialchars($c['title']) ?></div>
                  <div class="cov-meta">
                    <div class="cov-meta-item"><i class="fas fa-video"></i><?= count($byCourse[$c['id']] ?? []) ?> Videos</div>
                    <div class="cov-meta-item"><i class="fas fa-clipboard-check"></i><?= count($byCourseQuiz[$c['id']] ?? []) ?> Quizzes</div>
                    <div class="cov-meta-item"><i class="fas fa-broadcast-tower" style="color:#00b894;"></i><?= count($byCourseLive[$c['id']] ?? []) ?> Live</div>
                  </div>
                </div>
              </a>
              <form action="delete_course.php" method="post"
                    onsubmit="return confirmDelete('<?= htmlspecialchars(addslashes($c['title'])) ?>')">
                <input type="hidden" name="course_id" value="<?= (int)$c['id'] ?>">
                <button type="submit" class="cc-del-btn"><i class="fas fa-trash"></i> Delete</button>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- ════════ ALL COURSES ════════ -->
    <div class="panel <?= $section==='courses'?'active':'' ?>">
      <div class="sec-hd">
        <h3>All Courses</h3>
        <p>Click a course to open its editor — use the red button to permanently delete it</p>
      </div>
      <?php if (empty($courses)): ?>
        <div class="empty-state" style="background:var(--white);border:1px solid var(--border);border-radius:14px;">
          <i class="fas fa-book-open"></i>
          <p>No courses yet. <a href="?s=addcourse" style="color:var(--p);font-weight:700;">Add your first course →</a></p>
        </div>
      <?php else: ?>
        <div class="course-cards-grid">
          <?php foreach ($courses as $c): ?>
            <div class="cc-wrap">
              <a href="?cid=<?= (int)$c['id'] ?>" class="cc-card">
                <?php if (!empty($c['thumbnail_url'])): ?>
                  <img src="<?= htmlspecialchars($c['thumbnail_url']) ?>" class="cc-card-thumb" alt="">
                <?php else: ?>
                  <div class="cc-card-thumb-placeholder"><i class="fas fa-photo-video"></i></div>
                <?php endif; ?>
                <div class="cc-card-body">
                  <div class="cc-card-title"><?= htmlspecialchars($c['title']) ?></div>
                  <div class="cc-card-meta">
                    <span class="cc-chip"><i class="fas fa-video" style="font-size:10px;"></i><?= count($byCourse[$c['id']] ?? []) ?> Videos</span>
                    <span class="cc-chip amber"><i class="fas fa-clipboard-check" style="font-size:10px;"></i><?= count($byCourseQuiz[$c['id']] ?? []) ?> Quizzes</span>
                    <span class="cc-chip teal"><i class="fas fa-broadcast-tower" style="font-size:10px;"></i><?= count($byCourseLive[$c['id']] ?? []) ?> Live</span>
                  </div>
                  <div class="cc-edit-btn"><i class="fas fa-pen-to-square"></i> Open Course Editor</div>
                </div>
              </a>
              <form action="delete_course.php" method="post"
                    onsubmit="return confirmDelete('<?= htmlspecialchars(addslashes($c['title'])) ?>')">
                <input type="hidden" name="course_id" value="<?= (int)$c['id'] ?>">
                <button type="submit" class="cc-del-btn"><i class="fas fa-trash"></i> Delete</button>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- ════════ ADD COURSE ════════ -->
    <div class="panel <?= $section==='addcourse'?'active':'' ?>">
      <div class="fcard" style="max-width:680px;">
        <div class="fcard-head">
          <div class="fcard-hicon" style="background:var(--p-lt);color:var(--p);"><i class="fas fa-plus"></i></div>
          <div><h5>Add New Course</h5><p>Create a new course on your platform</p></div>
        </div>
        <div class="fcard-body">
          <form action="add_course.php" method="post" enctype="multipart/form-data">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label">Course Title</label>
                <input type="text" name="title" class="form-control" placeholder="e.g. Introduction to Python" required>
              </div>
              <div class="col-12">
                <label class="form-label">Banner Image <small class="text-muted fw-normal">(jpg/png/webp, max 2MB)</small></label>
                <input type="file" name="thumbnail" class="form-control" accept=".jpg,.jpeg,.png,.webp" required>
              </div>
              <div class="col-12">
                <button type="submit" class="abtn abtn-purple"><i class="fas fa-plus"></i> Create Course</button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- ════════ USERS & INSTRUCTORS ════════ -->
    <div class="panel <?= $section==='users'?'active':'' ?>">

      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;margin-bottom:24px;">
        <div class="stat-card">
          <div class="stat-icon" style="background:#dcfce7;color:#16a34a;"><i class="fas fa-user-graduate"></i></div>
          <div><div class="stat-num"><?= $totalStudents ?></div><div class="stat-lbl">Total Students</div></div>
        </div>
        <?php if ($hasInstructorCol): ?>
        <div class="stat-card">
          <div class="stat-icon" style="background:#fce7f3;color:#be185d;"><i class="fas fa-chalkboard-teacher"></i></div>
          <div><div class="stat-num"><?= $totalInstructors ?></div><div class="stat-lbl">Instructors</div></div>
        </div>
        <?php endif; ?>
        <div class="stat-card">
          <div class="stat-icon" style="background:#e0f2fe;color:#0284c7;"><i class="fas fa-graduation-cap"></i></div>
          <div><div class="stat-num"><?= $totalEnrolls ?></div><div class="stat-lbl">Enrollments</div></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon" style="background:#fef9c3;color:#b45309;"><i class="fas fa-users"></i></div>
          <div><div class="stat-num"><?= $totalStudents + $totalInstructors ?></div><div class="stat-lbl">Total Users</div></div>
        </div>
      </div>

      <div class="user-tabs">
        <button class="utab active" onclick="switchUserTab(this,'upanel-students')">
          <i class="fas fa-user-graduate"></i> Students
          <span class="utab-count"><?= $totalStudents ?></span>
        </button>
        <?php if ($hasInstructorCol): ?>
        <button class="utab" onclick="switchUserTab(this,'upanel-instructors')">
          <i class="fas fa-chalkboard-teacher"></i> Instructors
          <span class="utab-count"><?= $totalInstructors ?></span>
        </button>
        <?php endif; ?>
      </div>

      <div class="upanel active" id="upanel-students">
        <div class="fcard">
          <div class="fcard-head">
            <div class="fcard-hicon" style="background:#dcfce7;color:#16a34a;"><i class="fas fa-user-graduate"></i></div>
            <div>
              <h5>All Students</h5>
              <p><?= $totalStudents ?> registered student<?= $totalStudents!==1?'s':'' ?></p>
            </div>
          </div>
          <div class="fcard-body" style="padding-bottom:4px;">
            <div class="tbl-search">
              <i class="fas fa-search"></i>
              <input type="text" id="studentSearch" placeholder="Search by name or email…" oninput="filterTable('studentTbl',this.value)">
            </div>
          </div>
          <div style="overflow-x:auto;">
            <?php if (empty($allStudents)): ?>
              <div class="empty-state"><i class="fas fa-user-slash"></i><p>No students registered yet.</p></div>
            <?php else: ?>
              <table class="utbl" id="studentTbl">
                <thead>
                  <tr>
                    <th>#</th><th>Student</th><th>Email</th>
                    <th style="text-align:center;">Enrolled Courses</th>
                    <th>Joined</th><th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($allStudents as $i => $u): ?>
                    <tr>
                      <td style="color:var(--muted);font-size:12px;font-weight:700;"><?= $i+1 ?></td>
                      <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                          <div class="u-avatar" style="background:<?= sprintf('hsl(%d,55%%,60%%)', crc32($u['email']) % 360) ?>;color:#fff;">
                            <?= strtoupper(substr($u['name'] ?: $u['email'], 0, 1)) ?>
                          </div>
                          <span style="font-weight:600;"><?= htmlspecialchars($u['name'] ?: '—') ?></span>
                        </div>
                      </td>
                      <td style="color:var(--muted);"><?= htmlspecialchars($u['email']) ?></td>
                      <td style="text-align:center;">
                        <span class="cc-chip"><?= (int)$u['enrolled_courses'] ?> course<?= $u['enrolled_courses']!=1?'s':'' ?></span>
                      </td>
                      <td style="color:var(--muted);font-size:12.5px;"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                      <td>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;">
                          <a href="mailto:<?= htmlspecialchars($u['email']) ?>" class="tbtn tbtn-teal" title="Send email">
                            <i class="fas fa-envelope"></i>
                          </a>
                          <form action="delete_user.php" method="post" style="margin:0;"
                                onsubmit="return confirm('Delete student <?= htmlspecialchars(addslashes($u['name'] ?: $u['email'])) ?>?\n\nThis will remove all their enrollments and progress.');">
                            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                            <button type="submit" class="tbtn tbtn-del" title="Delete user"><i class="fas fa-trash"></i></button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <?php if ($hasInstructorCol): ?>
      <div class="upanel" id="upanel-instructors">
        <div class="fcard">
          <div class="fcard-head">
            <div class="fcard-hicon" style="background:#fce7f3;color:#be185d;"><i class="fas fa-chalkboard-teacher"></i></div>
            <div>
              <h5>All Instructors</h5>
              <p><?= $totalInstructors ?> registered instructor<?= $totalInstructors!==1?'s':'' ?></p>
            </div>
          </div>
          <div class="fcard-body" style="padding-bottom:4px;">
            <div class="tbl-search">
              <i class="fas fa-search"></i>
              <input type="text" id="instructorSearch" placeholder="Search by name or email…" oninput="filterTable('instructorTbl',this.value)">
            </div>
          </div>
          <div style="overflow-x:auto;">
            <?php if (empty($allInstructors)): ?>
              <div class="empty-state"><i class="fas fa-chalkboard"></i><p>No instructors yet.</p></div>
            <?php else: ?>
              <table class="utbl" id="instructorTbl">
                <thead>
                  <tr><th>#</th><th>Instructor</th><th>Email</th><th style="text-align:center;">Courses Created</th><th>Joined</th><th>Actions</th></tr>
                </thead>
                <tbody>
                  <?php foreach ($allInstructors as $i => $u): ?>
                    <tr>
                      <td style="color:var(--muted);font-size:12px;font-weight:700;"><?= $i+1 ?></td>
                      <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                          <div class="u-avatar" style="background:<?= sprintf('hsl(%d,55%%,55%%)', crc32($u['email']) % 360) ?>;color:#fff;">
                            <?= strtoupper(substr($u['name'] ?: $u['email'], 0, 1)) ?>
                          </div>
                          <div>
                            <div style="font-weight:700;"><?= htmlspecialchars($u['name'] ?: '—') ?></div>
                            <div style="font-size:11px;color:#be185d;font-weight:600;">Instructor</div>
                          </div>
                        </div>
                      </td>
                      <td style="color:var(--muted);"><?= htmlspecialchars($u['email']) ?></td>
                      <td style="text-align:center;">
                        <span class="cc-chip" style="background:#fce7f3;color:#be185d;">
                          <?= (int)$u['course_count'] ?> course<?= $u['course_count']!=1?'s':'' ?>
                        </span>
                      </td>
                      <td style="color:var(--muted);font-size:12.5px;"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                      <td>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;">
                          <a href="mailto:<?= htmlspecialchars($u['email']) ?>" class="tbtn tbtn-teal" title="Send email">
                            <i class="fas fa-envelope"></i>
                          </a>
                          <form action="delete_user.php" method="post" style="margin:0;"
                                onsubmit="return confirm('Remove instructor <?= htmlspecialchars(addslashes($u['name'] ?: $u['email'])) ?>?\n\nTheir courses will remain but instructor access will be revoked.');">
                            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                            <button type="submit" class="tbtn tbtn-del" title="Delete instructor"><i class="fas fa-trash"></i></button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>

    </div><!-- /users panel -->

    <!-- ════════ COURSE EDITOR ════════ -->
    <?php if ($section === 'courseeditor' && $activeCourse):
      $cid      = (int)$activeCourse['id'];
      $cVideos  = $byCourse[$cid]      ?? [];
      $cQuizzes = $byCourseQuiz[$cid]  ?? [];
      $cLive    = $byCourseLive[$cid]  ?? [];
    ?>
    <div class="panel active">

      <!-- Flash: quiz deleted -->
      <?php if (isset($_GET['quiz_deleted'])): ?>
        <div class="flash-success"><i class="fas fa-check-circle fa-lg"></i> Quiz deleted successfully.</div>
      <?php endif; ?>

      <!-- Course Hero -->
      <div class="editor-hero">
        <?php if (!empty($activeCourse['thumbnail_url'])): ?>
          <img src="<?= htmlspecialchars($activeCourse['thumbnail_url']) ?>" class="editor-hero-banner" alt="">
        <?php else: ?>
          <div class="editor-hero-banner-placeholder"><i class="fas fa-photo-video"></i></div>
        <?php endif; ?>
        <div class="editor-hero-body">
          <div>
            <div class="editor-hero-title"><?= htmlspecialchars($activeCourse['title']) ?></div>
            <div class="editor-hero-meta">
              <div class="editor-meta-item"><i class="fas fa-video"></i><?= count($cVideos) ?> Videos</div>
              <div class="editor-meta-item"><i class="fas fa-clipboard-check"></i><?= count($cQuizzes) ?> Quizzes</div>
              <div class="editor-meta-item" style="color:#00b894;"><i class="fas fa-broadcast-tower" style="color:#00b894;"></i><?= count($cLive) ?> Live Classes</div>
              <div class="editor-meta-item"><i class="fas fa-hashtag"></i>ID: <?= $cid ?></div>
            </div>
          </div>
          <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <form action="course_cover_upload.php" method="post" enctype="multipart/form-data"
                  style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
              <input type="hidden" name="course_id" value="<?= $cid ?>">
              <input type="file" name="cover" accept=".jpg,.jpeg,.png,.webp" required
                     style="font-size:12px;max-width:200px;" class="form-control form-control-sm">
              <button type="submit" class="abtn abtn-blue abtn-sm">
                <i class="fas fa-image"></i> Update Cover
              </button>
            </form>
            <form action="delete_course.php" method="post"
                  onsubmit="return confirmDelete('<?= htmlspecialchars(addslashes($activeCourse['title'])) ?>')">
              <input type="hidden" name="course_id" value="<?= $cid ?>">
              <button type="submit" class="abtn abtn-red abtn-sm">
                <i class="fas fa-trash"></i> Delete Course
              </button>
            </form>
          </div>
        </div>
      </div>

      <!-- Editor Tabs -->
      <div class="editor-tabs">
        <button class="etab active" onclick="switchTab(this,'tab-yt')">
          <i class="fab fa-youtube"></i> YouTube Video
        </button>
        <button class="etab" onclick="switchTab(this,'tab-upload')">
          <i class="fas fa-cloud-upload-alt"></i> Upload File
        </button>
        <button class="etab" onclick="switchTab(this,'tab-videos')">
          <i class="fas fa-film"></i> Videos
          <span class="etab-count"><?= count($cVideos) ?></span>
        </button>
        <button class="etab" onclick="switchTab(this,'tab-quizzes')">
          <i class="fas fa-clipboard-check"></i> Quizzes
          <span class="etab-count"><?= count($cQuizzes) ?></span>
        </button>
        <button class="etab teal-tab" onclick="switchTab(this,'tab-live')">
          <i class="fas fa-broadcast-tower"></i> Live Classes
          <span class="etab-count"><?= count($cLive) ?></span>
        </button>
        <?php
          $unansweredCount = 0;
          $uqRes = $conn->prepare("SELECT COUNT(*) FROM course_questions q LEFT JOIN course_question_replies r ON r.question_id=q.id WHERE q.course_id=? AND r.id IS NULL");
          $uqRes->bind_param("i", $cid);
          $uqRes->execute();
          $uqRes->bind_result($unansweredCount);
          $uqRes->fetch();
          $uqRes->close();
        ?>
        <button class="etab" style="<?= $unansweredCount > 0 ? 'color:#dc2626;' : '' ?>" onclick="switchTab(this,'tab-questions')">
          <i class="fas fa-comments"></i> Questions
          <span class="etab-count" style="<?= $unansweredCount > 0 ? 'background:#fee2e2;color:#dc2626;' : '' ?>"><?= $unansweredCount ?></span>
        </button>
      </div>

      <!-- Tab: YouTube -->
      <div class="etab-panel active" id="tab-yt">
        <div class="fcard">
          <div class="fcard-head">
            <div class="fcard-hicon" style="background:#fee2e2;color:#dc2626;"><i class="fab fa-youtube"></i></div>
            <div><h5>Add YouTube Video</h5><p>Paste a YouTube embed URL to add a new lesson</p></div>
          </div>
          <div class="fcard-body">
            <form action="upload_video.php" method="post" enctype="multipart/form-data">
              <input type="hidden" name="video_type" value="youtube">
              <input type="hidden" name="course_id" value="<?= $cid ?>">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Video Title</label>
                  <input type="text" name="title" class="form-control" placeholder="e.g. Lesson 1 – Introduction" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">YouTube Embed URL</label>
                  <input type="url" name="embed_url" class="form-control" placeholder="https://www.youtube.com/embed/..." required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Video Thumbnail <small class="text-muted fw-normal">(jpg/png/webp, max 2MB)</small></label>
                  <input type="file" name="thumb" class="form-control" accept=".jpg,.jpeg,.png,.webp" required>
                </div>
                <div class="col-12">
                  <button type="submit" class="abtn abtn-green"><i class="fab fa-youtube"></i> Add YouTube Video</button>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Tab: Upload File -->
      <div class="etab-panel" id="tab-upload">
        <div class="fcard">
          <div class="fcard-head">
            <div class="fcard-hicon" style="background:#e0f2fe;color:#0284c7;"><i class="fas fa-cloud-upload-alt"></i></div>
            <div><h5>Upload Video File</h5><p>Upload an MP4/WebM/MOV directly to the server (max 500MB)</p></div>
          </div>
          <div class="fcard-body">
            <form id="uploadVideoForm" action="upload_video_file.php" method="post" enctype="multipart/form-data">
              <input type="hidden" name="video_type" value="upload">
              <input type="hidden" name="course_id" value="<?= $cid ?>">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Video Title</label>
                  <input type="text" name="title" class="form-control" placeholder="e.g. Lesson 2 – Variables" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Duration <small class="text-muted fw-normal">(seconds, optional)</small></label>
                  <input type="number" name="duration" class="form-control" placeholder="e.g. 300">
                </div>
                <div class="col-12">
                  <label class="form-label">Video File</label>
                  <div class="drop-zone" id="dropZone">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <p>Drag &amp; drop your video here, or click to browse</p>
                    <small>MP4, WebM, MOV — max 500MB</small>
                    <input type="file" name="video_file" id="videoFile"
                           accept="video/mp4,video/webm,video/quicktime" required style="display:none;">
                    <div class="fileInfo" id="fileInfo"></div>
                  </div>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Thumbnail <small class="text-muted fw-normal">(jpg/png/webp, max 2MB)</small></label>
                  <input type="file" name="thumb" class="form-control" accept=".jpg,.jpeg,.png,.webp" required>
                </div>
                <div class="col-12">
                  <button type="submit" class="abtn abtn-blue" id="uploadBtn">
                    <span class="spinner-border spinner-border-sm d-none" id="uploadSpinner" role="status"></span>
                    <i class="fas fa-upload" id="uploadIcon"></i> Upload Video
                  </button>
                </div>
              </div>
              <div class="upload-progress" id="uploadProgress">
                <div style="display:flex;justify-content:space-between;margin-bottom:5px;">
                  <small style="font-weight:600;color:var(--muted);">Uploading, please wait…</small>
                  <small id="progressPercent" style="font-weight:800;color:var(--p);">0%</small>
                </div>
                <div class="progress">
                  <div class="progress-bar" id="progressBar" style="width:0%;"></div>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Tab: Videos list -->
      <div class="etab-panel" id="tab-videos">
        <div class="fcard">
          <div class="fcard-head">
            <div class="fcard-hicon" style="background:var(--p-lt);color:var(--p);"><i class="fas fa-film"></i></div>
            <div><h5>Course Videos</h5><p><?= count($cVideos) ?> video<?= count($cVideos)!==1?'s':'' ?> in this course</p></div>
          </div>
          <div class="fcard-body" style="padding:0;">
            <?php if (empty($cVideos)): ?>
              <div class="empty-state">
                <i class="fas fa-video-slash"></i>
                <p>No videos yet. Add a YouTube video or upload a file using the tabs above.</p>
              </div>
            <?php else: ?>
              <div style="overflow-x:auto;">
                <table class="vtbl">
                  <thead>
                    <tr><th>Type</th><th>Thumbnail</th><th>Title</th><th>Position</th><th>Actions</th></tr>
                  </thead>
                  <tbody>
                    <?php foreach ($cVideos as $row): ?>
                      <tr>
                        <td>
                          <span class="vtype <?= $row['video_type']==='youtube'?'yt':'loc' ?>">
                            <i class="fas fa-<?= $row['video_type']==='youtube'?'play':'file-video' ?>"></i>
                            <?= ucfirst($row['video_type']) ?>
                          </span>
                        </td>
                        <td>
                          <?php if (!empty($row['thumb_url'])): ?>
                            <img src="<?= htmlspecialchars($row['thumb_url']) ?>" alt="">
                          <?php else: ?>
                            <div class="no-thumb"><i class="fas fa-image"></i></div>
                          <?php endif; ?>
                        </td>
                        <td style="font-weight:500;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                          <?= htmlspecialchars($row['title']) ?>
                        </td>
                        <td style="text-align:center;font-weight:700;color:var(--p);"><?= (int)$row['position'] ?></td>
                        <td>
                          <div style="display:flex;gap:6px;">
                            <a class="tbtn tbtn-edit" href="video_edit.php?id=<?= (int)$row['id'] ?>"><i class="fas fa-pen"></i> Edit</a>
                            <form action="video_delete.php" method="post" class="d-inline"
                                  onsubmit="return confirm('Delete this video?');">
                              <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                              <button type="submit" class="tbtn tbtn-del"><i class="fas fa-trash"></i></button>
                            </form>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Tab: Quizzes -->
      <div class="etab-panel" id="tab-quizzes">
        <div class="fcard" style="margin-bottom:18px;">
          <div class="fcard-head">
            <div class="fcard-hicon" style="background:#fef9c3;color:#b45309;"><i class="fas fa-plus"></i></div>
            <div><h5>Add New Quiz</h5><p>Create a quiz for this course</p></div>
          </div>
          <div class="fcard-body">
            <form action="add_quiz.php" method="post">
              <input type="hidden" name="course_id" value="<?= $cid ?>">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Quiz Title</label>
                  <input type="text" name="title" class="form-control" placeholder="e.g. Chapter 1 Quiz" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Description <small class="text-muted fw-normal">(optional)</small></label>
                  <input type="text" name="description" class="form-control" placeholder="What does this quiz cover?">
                </div>
                <div class="col-12">
                  <button type="submit" class="abtn abtn-amber"><i class="fas fa-plus"></i> Add Quiz</button>
                </div>
              </div>
            </form>
          </div>
        </div>

        <div class="fcard">
          <div class="fcard-head">
            <div class="fcard-hicon" style="background:#fef9c3;color:#b45309;"><i class="fas fa-list"></i></div>
            <div><h5>Existing Quizzes</h5><p><?= count($cQuizzes) ?> quiz<?= count($cQuizzes)!==1?'zes':'' ?> in this course</p></div>
          </div>
          <div class="fcard-body">
            <?php if (empty($cQuizzes)): ?>
              <div class="empty-state"><i class="fas fa-clipboard"></i><p>No quizzes yet.</p></div>
            <?php else: ?>
              <?php foreach ($cQuizzes as $qz): ?>
                <div class="qrow">
                  <div class="qrow-left">
                    <i class="fas fa-clipboard-check"></i>
                    <?= htmlspecialchars($qz['title']) ?>
                  </div>
                  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                    <a href="add_quiz_questions.php?quiz_id=<?= (int)$qz['id'] ?>" class="tbtn tbtn-edit" style="font-size:12px;">
                      <i class="fas fa-plus"></i> Add / View Questions
                    </a>
                    <button type="button" class="tbtn tbtn-del" style="font-size:12px;"
                      onclick="openDeleteQuizModal(<?= (int)$qz['id'] ?>, <?= $cid ?>, '<?= htmlspecialchars(addslashes($qz['title']), ENT_QUOTES) ?>')">
                      <i class="fas fa-trash"></i> Delete
                    </button>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Tab: Live Classes -->
      <div class="etab-panel" id="tab-live">
        <div class="fcard" style="margin-bottom:18px;">
          <div class="fcard-head">
            <div class="fcard-hicon" style="background:#e6fff5;color:#00b894;"><i class="fas fa-broadcast-tower"></i></div>
            <div><h5>Schedule a Live Class</h5><p>Add a Google Meet session for students enrolled in this course</p></div>
          </div>
          <div class="fcard-body">
            <form action="add_live_class.php" method="post">
              <input type="hidden" name="course_id" value="<?= $cid ?>">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Class Title</label>
                  <input type="text" name="title" class="form-control" placeholder="e.g. Week 3 – Q&amp;A Session" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Google Meet Link</label>
                  <input type="url" name="meet_link" class="form-control" placeholder="https://meet.google.com/xxx-xxxx-xxx" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Date &amp; Time</label>
                  <input type="datetime-local" name="scheduled_at" class="form-control" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Description <small class="text-muted fw-normal">(optional)</small></label>
                  <input type="text" name="description" class="form-control" placeholder="Brief description of what will be covered">
                </div>
                <div class="col-12">
                  <button type="submit" class="abtn abtn-teal">
                    <i class="fas fa-broadcast-tower"></i> Schedule Live Class
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>

        <div class="fcard">
          <div class="fcard-head">
            <div class="fcard-hicon" style="background:#e6fff5;color:#00b894;"><i class="fas fa-list"></i></div>
            <div><h5>All Live Classes</h5><p><?= count($cLive) ?> class<?= count($cLive)!==1?'es':'' ?> — states update automatically</p></div>
          </div>
          <div class="fcard-body">
            <?php if (empty($cLive)): ?>
              <div class="empty-state">
                <i class="fas fa-video-slash"></i>
                <p>No live classes scheduled yet.</p>
              </div>
            <?php else: ?>
              <?php
                $now = time();
                foreach ($cLive as $live):
                  $ts       = strtotime($live['scheduled_at']);
                  $diffSecs = $ts - $now;
                  $diffMins = $diffSecs / 60;
                  if ($diffMins >= -120 && $diffMins <= 15) { $state='live-now'; }
                  elseif ($ts > $now)                       { $state='upcoming'; }
                  else                                      { $state='finished'; }
              ?>
                <div class="lrow lrow-<?= $state ?>" data-ts="<?= $ts ?>" data-id="<?= (int)$live['id'] ?>">
                  <div class="lrow-icon-wrap">
                    <?php if ($state==='live-now'): ?>
                      <div class="lrow-dot live-dot"></div>
                    <?php elseif ($state==='upcoming'): ?>
                      <div class="lrow-dot upcoming-dot"></div>
                    <?php else: ?>
                      <div class="lrow-dot finished-dot"></div>
                    <?php endif; ?>
                  </div>
                  <div class="lrow-body">
                    <div class="lrow-top">
                      <span class="lrow-title-text"><?= htmlspecialchars($live['title']) ?></span>
                      <span class="lbadge lbadge-<?= $state ?>">
                        <?php
                          if ($state==='live-now') echo '<i class="fas fa-circle" style="font-size:8px;animation:blink 1s infinite;"></i> Live Now';
                          elseif ($state==='upcoming') echo '<i class="fas fa-clock"></i> Upcoming';
                          else echo '<i class="fas fa-check-circle"></i> Class Finished';
                        ?>
                      </span>
                    </div>
                    <?php if (!empty($live['description'])): ?>
                      <div class="lrow-desc"><?= htmlspecialchars($live['description']) ?></div>
                    <?php endif; ?>
                    <div class="lrow-meta-row">
                      <span><i class="fas fa-calendar-alt"></i><?= date('D, M d Y', $ts) ?></span>
                      <span><i class="fas fa-clock"></i><?= date('h:i A', $ts) ?></span>
                      <?php if ($state === 'upcoming'): ?>
                        <span class="lrow-countdown" data-ts="<?= $ts ?>">
                          <i class="fas fa-hourglass-half"></i>
                          <span class="countdown-text">calculating…</span>
                        </span>
                      <?php endif; ?>
                      <?php if ($state !== 'finished'): ?>
                        <span>
                          <i class="fab fa-google" style="color:#00b894;"></i>
                          <a href="<?= htmlspecialchars($live['meet_link']) ?>" target="_blank" style="color:#00b894;font-weight:700;">meet.google.com</a>
                        </span>
                      <?php endif; ?>
                    </div>
                  </div>
                  <div class="lrow-action">
                    <?php if ($state === 'live-now'): ?>
                      <a href="<?= htmlspecialchars($live['meet_link']) ?>" target="_blank" class="lbtn-join">
                        <i class="fas fa-video"></i> Join Now
                      </a>
                    <?php elseif ($state === 'upcoming'): ?>
                      <div class="lbtn-waiting"><i class="fas fa-hourglass-start"></i> Waiting</div>
                    <?php else: ?>
                      <div class="lbtn-finished"><i class="fas fa-flag-checkered"></i> Finished</div>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div><!-- /tab-live -->

      <!-- Tab: Questions -->
      <div class="etab-panel" id="tab-questions">
        <?php
          $allQ = [];
          $aqRes = $conn->prepare("
              SELECT q.id, q.question, q.created_at, q.video_id,
                     u.name AS student_name, u.email AS student_email,
                     v.title AS video_title,
                     r.id AS reply_id, r.reply, r.created_at AS replied_at,
                     ru.name AS instructor_name
              FROM course_questions q
              JOIN users u ON u.id = q.student_id
              LEFT JOIN videos v ON v.id = q.video_id
              LEFT JOIN course_question_replies r ON r.question_id = q.id
              LEFT JOIN users ru ON ru.id = r.instructor_id
              WHERE q.course_id = ?
              ORDER BY q.video_id IS NULL ASC, q.video_id ASC, q.created_at ASC
          ");
          $aqRes->bind_param("i", $cid);
          $aqRes->execute();
          $allQ = $aqRes->get_result()->fetch_all(MYSQLI_ASSOC);
          $aqRes->close();
          $grouped = [];
          foreach ($allQ as $q) {
              $key = $q['video_id'] ?? 'help';
              $grouped[$key][] = $q;
          }
        ?>

        <?php if (isset($_GET['replied'])): ?>
          <div class="flash-success"><i class="fas fa-check-circle"></i> Reply sent!</div>
        <?php endif; ?>

        <?php if (empty($allQ)): ?>
          <div style="text-align:center;padding:48px 20px;color:#bbb;">
            <i class="fas fa-comments" style="font-size:36px;display:block;margin-bottom:12px;color:#e0d5f5;"></i>
            <p style="font-size:14px;">No questions yet for this course.</p>
          </div>
        <?php else: ?>
          <?php foreach ($grouped as $groupKey => $questions):
            $isHelpChat = ($groupKey === 'help');
            $groupTitle = $isHelpChat ? 'Help Chat' : ($questions[0]['video_title'] ?? 'Video');
            $groupIcon  = $isHelpChat ? 'fa-comments' : 'fa-video';
            $groupColor = $isHelpChat ? '#16a34a' : '#5f259f';
            $groupBg    = $isHelpChat ? '#f0fdf4' : '#f0ebff';
          ?>
            <div style="display:flex;align-items:center;gap:10px;padding:14px 18px;background:<?= $groupBg ?>;border-radius:12px;margin-bottom:12px;margin-top:20px;border:1.5px solid <?= $groupColor ?>22;">
              <div style="width:34px;height:34px;border-radius:8px;background:<?= $groupColor ?>;display:flex;align-items:center;justify-content:center;color:#fff;flex-shrink:0;">
                <i class="fas <?= $groupIcon ?>"></i>
              </div>
              <div>
                <div style="font-size:14px;font-weight:800;color:<?= $groupColor ?>;"><?= htmlspecialchars($groupTitle) ?></div>
                <div style="font-size:12px;color:#888;"><?= count($questions) ?> question<?= count($questions)!==1?'s':'' ?></div>
              </div>
              <?php if (!$isHelpChat): ?>
                <a href="watch.php?video_id=<?= (int)$groupKey ?>" target="_blank"
                   style="margin-left:auto;font-size:12px;color:#5f259f;font-weight:700;text-decoration:none;background:#fff;border:1px solid #e0d5f5;padding:5px 12px;border-radius:20px;">
                  <i class="fas fa-play-circle"></i> Watch Video
                </a>
              <?php endif; ?>
            </div>

            <div style="display:flex;flex-direction:column;gap:12px;padding-left:8px;border-left:3px solid <?= $groupColor ?>33;margin-bottom:8px;">
              <?php foreach ($questions as $q): ?>
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">
                  <div style="padding:14px 16px;">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;flex-wrap:wrap;">
                      <div style="width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#5f259f,#ff6b00);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;color:#fff;flex-shrink:0;">
                        <?= strtoupper(substr($q['student_name'] ?: 'S', 0, 1)) ?>
                      </div>
                      <span style="font-size:12.5px;font-weight:700;color:#333;"><?= htmlspecialchars($q['student_name']) ?></span>
                      <span style="font-size:11.5px;color:#aaa;"><?= htmlspecialchars($q['student_email']) ?></span>
                      <span style="font-size:11px;color:#bbb;margin-left:auto;"><?= date('M d, Y · h:i A', strtotime($q['created_at'])) ?></span>
                    </div>
                    <div style="font-size:13.5px;color:#333;line-height:1.65;background:#faf8ff;border-radius:8px;padding:10px 13px;border-left:3px solid #a78bfa;">
                      <?= nl2br(htmlspecialchars($q['question'])) ?>
                    </div>
                  </div>
                  <?php if ($q['reply']): ?>
                    <div style="padding:12px 16px;background:#f0fdf4;border-top:1px solid #dcfce7;">
                      <div style="font-size:11px;font-weight:800;color:#16a34a;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">
                        <i class="fas fa-reply"></i> Reply · <?= htmlspecialchars($q['instructor_name'] ?: 'Instructor') ?> · <?= date('M d, Y', strtotime($q['replied_at'])) ?>
                      </div>
                      <div style="font-size:13.5px;color:#444;line-height:1.65;" id="admin-reply-text-<?= (int)$q['reply_id'] ?>"><?= nl2br(htmlspecialchars($q['reply'])) ?></div>
                      <div style="display:flex;gap:6px;margin-top:8px;">
                        <button onclick="toggleAdminEditReply(<?= (int)$q['reply_id'] ?>)"
                          style="display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:6px;font-size:11.5px;font-weight:700;cursor:pointer;border:1.5px solid #5f259f;color:#5f259f;background:transparent;font-family:inherit;">
                          <i class="fas fa-pen"></i> Edit
                        </button>
                        <form action="delete_reply.php" method="post" style="margin:0;" onsubmit="return confirm('Delete this reply?')">
                          <input type="hidden" name="id" value="<?= (int)$q['reply_id'] ?>">
                          <input type="hidden" name="redirect" value="admin_dashboard.php?cid=<?= $cid ?>&tab=questions">
                          <button type="submit" style="display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:6px;font-size:11.5px;font-weight:700;cursor:pointer;border:1.5px solid #dc2626;color:#dc2626;background:transparent;font-family:inherit;">
                            <i class="fas fa-trash"></i> Delete
                          </button>
                        </form>
                      </div>
                      <div id="admin-reply-edit-<?= (int)$q['reply_id'] ?>" style="display:none;margin-top:10px;">
                        <form action="edit_reply.php" method="post">
                          <input type="hidden" name="id" value="<?= (int)$q['reply_id'] ?>">
                          <input type="hidden" name="redirect" value="admin_dashboard.php?cid=<?= $cid ?>&tab=questions">
                          <textarea name="reply" required
                            style="width:100%;border:1.5px solid #e5e7eb;border-radius:8px;padding:9px 12px;font-size:13px;font-family:inherit;resize:vertical;min-height:60px;outline:none;"
                            onfocus="this.style.borderColor='#5f259f'" onblur="this.style.borderColor='#e5e7eb'"><?= htmlspecialchars($q['reply']) ?></textarea>
                          <button type="submit" style="margin-top:6px;padding:6px 16px;background:linear-gradient(135deg,#5f259f,#ff6b00);color:#fff;border:none;border-radius:7px;font-size:12.5px;font-weight:700;cursor:pointer;font-family:inherit;">Save</button>
                          <button type="button" onclick="toggleAdminEditReply(<?= (int)$q['reply_id'] ?>)"
                            style="margin-top:6px;margin-left:6px;padding:6px 12px;background:#f4f1fa;color:#888;border:1px solid #e0d5f5;border-radius:7px;font-size:12.5px;font-weight:700;cursor:pointer;font-family:inherit;">Cancel</button>
                        </form>
                      </div>
                    </div>
                  <?php else: ?>
                    <div style="padding:12px 16px;background:#fff8f8;border-top:1px solid #fecaca;">
                      <div style="font-size:11.5px;font-weight:700;color:#dc2626;margin-bottom:8px;">
                        <i class="fas fa-clock"></i> Awaiting reply
                      </div>
                      <form action="reply_question.php" method="post">
                        <input type="hidden" name="question_id" value="<?= (int)$q['id'] ?>">
                        <input type="hidden" name="course_id" value="<?= $cid ?>">
                        <textarea name="reply" required
                          style="width:100%;background:#fff;border:1.5px solid #e5e7eb;border-radius:8px;padding:10px 12px;font-size:13px;font-family:inherit;resize:vertical;min-height:64px;outline:none;"
                          onfocus="this.style.borderColor='#5f259f'" onblur="this.style.borderColor='#e5e7eb'"
                          placeholder="Type your reply…"></textarea>
                        <button type="submit"
                          style="margin-top:8px;padding:8px 20px;background:linear-gradient(135deg,#5f259f,#ff6b00);color:#fff;border:none;border-radius:7px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;display:inline-flex;align-items:center;gap:6px;">
                          <i class="fas fa-paper-plane"></i> Send Reply
                        </button>
                      </form>
                    </div>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div><!-- /tab-questions -->

    </div><!-- /course editor -->
    <?php endif; ?>

  </div><!-- /content -->
</div><!-- /main-area -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function switchTab(btn, targetId) {
  document.querySelectorAll('.etab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.etab-panel').forEach(p => p.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById(targetId).classList.add('active');
}
function switchUserTab(btn, targetId) {
  document.querySelectorAll('.utab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.upanel').forEach(p => p.classList.remove('active'));
  btn.classList.add('active');
  const panel = document.getElementById(targetId);
  if (panel) panel.classList.add('active');
}
function confirmDelete(title) {
  return confirm('⚠️ Permanently delete "' + title + '"?\n\nThis will delete ALL videos, quizzes, live classes and enrollments.\nThis action CANNOT be undone.');
}
function toggleAdminEditReply(id) {
  const form = document.getElementById('admin-reply-edit-' + id);
  const text = document.getElementById('admin-reply-text-' + id);
  const isOpen = form.style.display === 'block';
  form.style.display = isOpen ? 'none' : 'block';
  if (text) text.style.display = isOpen ? '' : 'none';
}
function filterTable(tableId, query) {
  const q = query.toLowerCase().trim();
  document.querySelectorAll('#' + tableId + ' tbody tr').forEach(row => {
    row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}

// ── Delete Quiz Modal ────────────────────────────────────────────────────────
function openDeleteQuizModal(quizId, courseId, quizTitle) {
  document.getElementById('deleteQuizId').value = quizId;
  document.getElementById('deleteQuizCourseId').value = courseId;
  document.getElementById('deleteQuizMsg').innerHTML =
    'This will permanently delete <strong>' + quizTitle + '</strong>, all its questions, and all student attempt records. This <strong>cannot be undone</strong>.';
  document.getElementById('deleteQuizModal').classList.add('show');
}
function closeDeleteQuizModal() {
  document.getElementById('deleteQuizModal').classList.remove('show');
}
document.getElementById('deleteQuizModal').addEventListener('click', function(e) {
  if (e.target === this) closeDeleteQuizModal();
});

document.addEventListener('DOMContentLoaded', () => {
  const params = new URLSearchParams(window.location.search);
  if (params.get('tab') === 'questions') {
    const btn = document.querySelector('[onclick*="tab-questions"]');
    if (btn) btn.click();
  }
  if (params.get('quiz_deleted')) {
    const btn = document.querySelector('[onclick*="tab-quizzes"]');
    if (btn) btn.click();
  }
});

document.addEventListener('DOMContentLoaded', () => {
  const dz   = document.getElementById('dropZone');
  const inp  = document.getElementById('videoFile');
  const info = document.getElementById('fileInfo');
  if (!dz) return;
  dz.addEventListener('click', () => inp.click());
  dz.addEventListener('dragover',  e => { e.preventDefault(); dz.classList.add('dragover'); });
  dz.addEventListener('dragleave', () => dz.classList.remove('dragover'));
  dz.addEventListener('drop', e => {
    e.preventDefault(); dz.classList.remove('dragover');
    if (e.dataTransfer.files.length) { inp.files = e.dataTransfer.files; showFile(e.dataTransfer.files[0]); }
  });
  inp.addEventListener('change', e => { if (e.target.files.length) showFile(e.target.files[0]); });
  function showFile(f) { info.textContent = '✔ ' + f.name + ' (' + (f.size/1024/1024).toFixed(2) + ' MB)'; }

  const form    = document.getElementById('uploadVideoForm');
  if (!form) return;
  const bar     = document.getElementById('progressBar');
  const pct     = document.getElementById('progressPercent');
  const wrap    = document.getElementById('uploadProgress');
  const btn     = document.getElementById('uploadBtn');
  const spinner = document.getElementById('uploadSpinner');
  const icon    = document.getElementById('uploadIcon');
  form.addEventListener('submit', e => {
    e.preventDefault();
    const fd  = new FormData(form);
    const xhr = new XMLHttpRequest();
    wrap.style.display = 'block'; btn.disabled = true;
    spinner.classList.remove('d-none'); icon.classList.add('d-none');
    xhr.upload.addEventListener('progress', e => {
      if (e.lengthComputable) { const p = Math.round(e.loaded/e.total*100); bar.style.width=p+'%'; pct.textContent=p+'%'; }
    });
    xhr.addEventListener('load', () => {
      if (xhr.status===200) {
        try { const res=JSON.parse(xhr.responseText); if(res.success){alert('Video uploaded!');window.location.reload();}else{alert('Error: '+res.message);reset();} }
        catch(e){window.location.reload();}
      } else { alert('Upload failed.'); reset(); }
    });
    xhr.addEventListener('error', () => { alert('Upload failed.'); reset(); });
    xhr.open('POST','upload_video_file.php',true); xhr.send(fd);
    function reset() { btn.disabled=false; spinner.classList.add('d-none'); icon.classList.remove('d-none'); wrap.style.display='none'; bar.style.width='0%'; pct.textContent='0%'; }
  });

  function updateCountdowns() {
    document.querySelectorAll('.lrow-countdown[data-ts]').forEach(el => {
      const ts   = parseInt(el.dataset.ts) * 1000;
      const diff = ts - Date.now();
      const txt  = el.querySelector('.countdown-text');
      if (!txt) return;
      if (diff <= 0) { txt.textContent = 'Starting now'; return; }
      const d = Math.floor(diff / 86400000);
      const h = Math.floor((diff % 86400000) / 3600000);
      const m = Math.floor((diff % 3600000)  / 60000);
      const s = Math.floor((diff % 60000)    / 1000);
      if (d > 0)      txt.textContent = `Starts in ${d}d ${h}h`;
      else if (h > 0) txt.textContent = `Starts in ${h}h ${m}m`;
      else if (m > 0) txt.textContent = `Starts in ${m}m ${s}s`;
      else            txt.textContent = `Starts in ${s}s`;
    });
  }
  updateCountdowns();
  setInterval(updateCountdowns, 1000);
});
</script>
</body>
</html>