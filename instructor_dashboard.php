<?php
session_start();
require __DIR__ . '/db.php';

$userId      = $_SESSION['user_id']       ?? null;
$userName    = $_SESSION['name']          ?? null;
$userMail    = $_SESSION['email']         ?? null;
$isInstructor= !empty($_SESSION['is_instructor']);

if (!$userId)       { header('Location: login.php'); exit; }
if (!$isInstructor) { header('Location: my_courses.php'); exit; }

$welcome = isset($_GET['welcome']);

// ── Instructor's courses only ──────────────────────────────────────────────
$courses = [];
$res = $conn->prepare("
    SELECT c.*,
        (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) AS student_count,
        (SELECT COUNT(*) FROM videos      WHERE course_id = c.id) AS video_count,
        (SELECT COUNT(*) FROM quizzes     WHERE course_id = c.id) AS quiz_count,
        (SELECT COUNT(*) FROM live_classes WHERE course_id = c.id) AS live_count
    FROM courses c
    WHERE c.instructor_id = ?
    ORDER BY c.id DESC
");
$res->bind_param("i", $userId);
$res->execute();
$courseRows = $res->get_result();
while ($row = $courseRows->fetch_assoc()) { $courses[] = $row; }
$res->close();

// ── Videos / Quizzes / Live by course ─────────────────────────────────────
$byCourse = $byCourseQuiz = $byCourseLive = [];
if ($courses) {
    $ids = implode(',', array_map('intval', array_column($courses, 'id')));

    $vr = $conn->query("SELECT id,course_id,title,embed_url,video_type,video_path,thumb_url,position,duration FROM videos WHERE course_id IN ($ids) ORDER BY position,id");
    while ($v = $vr->fetch_assoc()) { $byCourse[$v['course_id']][] = $v; }
    $vr->close();

    $qr = $conn->query("SELECT id,course_id,title,time_limit FROM quizzes WHERE course_id IN ($ids) ORDER BY id");
    while ($q = $qr->fetch_assoc()) { $byCourseQuiz[$q['course_id']][] = $q; }
    $qr->close();

    $lr = $conn->query("SELECT id,course_id,title,description,meet_link,scheduled_at FROM live_classes WHERE course_id IN ($ids) ORDER BY scheduled_at ASC");
    while ($l = $lr->fetch_assoc()) { $byCourseLive[$l['course_id']][] = $l; }
    $lr->close();
}

// ── Stats ──────────────────────────────────────────────────────────────────
$totalCourses  = count($courses);
$totalStudents = 0;
$totalVideos   = 0;
foreach ($courses as $c) {
    $totalStudents += (int)$c['student_count'];
    $totalVideos   += (int)$c['video_count'];
}

// ── Active section / course editor ────────────────────────────────────────
$section        = $_GET['s']   ?? 'overview';
$activeCourseId = (int)($_GET['cid'] ?? 0);
$activeCourse   = null;
if ($activeCourseId) {
    foreach ($courses as $c) {
        if ((int)$c['id'] === $activeCourseId) { $activeCourse = $c; break; }
    }
    if ($activeCourse) $section = 'courseeditor';
    else { header('Location: instructor_dashboard.php'); exit; }
}

// ── Student performance for active course ─────────────────────────────────
$studentPerformance = [];
if ($activeCourse) {
    $cid = (int)$activeCourse['id'];

    $sp = $conn->prepare("
        SELECT
            u.id, u.name, u.email,
            e.enrolled_at,
            (SELECT COUNT(*) FROM video_progress vp
             JOIN videos v ON vp.video_id=v.id
             WHERE v.course_id=? AND vp.user_id=u.id AND vp.completed=1
            ) AS videos_done,
            (SELECT COUNT(*) FROM videos WHERE course_id=?) AS total_videos,
            (SELECT COUNT(*) FROM quiz_attempts qa
             JOIN quizzes q ON qa.quiz_id=q.id
             WHERE q.course_id=? AND qa.user_id=u.id
            ) AS quizzes_attempted,
            (SELECT COUNT(*) FROM quizzes WHERE course_id=?) AS total_quizzes,
            (SELECT ROUND(AVG(qa2.score/qa2.total_questions*100))
             FROM quiz_attempts qa2
             JOIN quizzes q2 ON qa2.quiz_id=q2.id
             WHERE q2.course_id=? AND qa2.user_id=u.id AND qa2.total_questions>0
            ) AS avg_score
        FROM enrollments e
        JOIN users u ON u.id=e.user_id
        WHERE e.course_id=?
        ORDER BY e.enrolled_at DESC
    ");
    $sp->bind_param("iiiiii", $cid,$cid,$cid,$cid,$cid,$cid);
    $sp->execute();
    $studentPerformance = $sp->get_result()->fetch_all(MYSQLI_ASSOC);
    $sp->close();
}

function perfColor($pct) {
    if ($pct === null)  return '#bbb';
    if ($pct >= 90) return '#b8860b';
    if ($pct >= 75) return '#16a34a';
    if ($pct >= 60) return '#0284c7';
    if ($pct >= 40) return '#d97706';
    return '#dc2626';
}
function perfLabel($pct) {
    if ($pct === null)  return '—';
    if ($pct >= 90) return 'Outstanding';
    if ($pct >= 75) return 'Excellent';
    if ($pct >= 60) return 'Good';
    if ($pct >= 40) return 'Average';
    return 'Poor';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>EDURAY – Instructor Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --p:#5f259f; --p-dark:#3d1066; --p-lt:#f0ebff;
      --accent:#ff6b00; --gold:#ffe600;
      --bg:#f4f1fa; --white:#ffffff;
      --border:#e8e3f5; --text:#1e1a2e; --muted:#7b7a99;
      --green:#16a34a; --teal:#00b894;
      --sw:265px;
    }
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);display:flex;min-height:100vh;}

    /* ── Sidebar ── */
    .sidebar{width:var(--sw);position:fixed;top:0;left:0;bottom:0;z-index:200;background:var(--p-dark);display:flex;flex-direction:column;box-shadow:2px 0 12px rgba(0,0,0,.15);overflow-y:auto;}
    .sb-brand{padding:22px 20px 16px;border-bottom:1px solid rgba(255,255,255,.1);}
    .sb-brand .logo{font-size:20px;font-weight:800;color:var(--gold);letter-spacing:1px;}
    .sb-brand .logo span{color:#fff;}
    .sb-role{display:inline-block;margin-top:5px;background:rgba(0,184,148,.25);color:#00d4a8;font-size:9.5px;font-weight:700;letter-spacing:2px;text-transform:uppercase;padding:3px 10px;border-radius:20px;}
    .sb-nav{flex:1;padding:14px 10px;}
    .sb-lbl{font-size:9px;font-weight:800;letter-spacing:3px;text-transform:uppercase;color:rgba(255,255,255,.28);padding:14px 12px 5px;}
    .sb-link{display:flex;align-items:center;gap:11px;padding:10px 14px;border-radius:8px;color:rgba(255,255,255,.6)!important;font-size:13.5px;font-weight:500;text-decoration:none;transition:background .15s,color .15s;margin-bottom:2px;position:relative;}
    .sb-link i{width:17px;text-align:center;font-size:14px;}
    .sb-link:hover{background:rgba(255,255,255,.08);color:#fff!important;}
    .sb-link.active{background:rgba(255,230,0,.12);color:var(--gold)!important;font-weight:700;}
    .sb-link.active::before{content:'';position:absolute;left:0;top:6px;bottom:6px;width:3px;border-radius:0 3px 3px 0;background:var(--gold);}
    .sb-footer{padding:14px 10px;border-top:1px solid rgba(255,255,255,.1);}
    .sb-user{display:flex;align-items:center;gap:10px;background:rgba(255,255,255,.07);border-radius:8px;padding:10px 12px;margin-bottom:10px;}
    .sb-avatar{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--teal),#00cec9);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:14px;color:#fff;flex-shrink:0;}
    .sb-uname{font-size:13px;font-weight:700;color:#fff;}
    .sb-urole{font-size:10px;color:var(--teal);}
    .sb-logout{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:9px;border-radius:8px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);color:rgba(255,255,255,.6)!important;font-size:13px;font-weight:500;text-decoration:none;transition:background .2s;}
    .sb-logout:hover{background:rgba(220,38,38,.2);color:#fca5a5!important;}

    /* ── Main ── */
    .main-area{margin-left:var(--sw);flex:1;display:flex;flex-direction:column;}
    .topbar{background:var(--white);border-bottom:1px solid var(--border);padding:14px 28px;display:flex;align-items:center;justify-content:space-between;gap:12px;position:sticky;top:0;z-index:100;}
    .topbar-heading{font-size:18px;font-weight:800;}
    .topbar-heading span{color:var(--p);}
    .topbar-bc{font-size:12px;color:var(--muted);display:flex;align-items:center;gap:5px;margin-top:2px;}
    .topbar-bc a{color:var(--p);text-decoration:none;font-weight:600;}
    .btn-site{display:inline-flex;align-items:center;gap:7px;padding:8px 16px;border-radius:7px;background:var(--white);border:1.5px solid var(--border);color:var(--text);font-size:13px;font-weight:600;text-decoration:none;transition:border-color .2s,color .2s;}
    .btn-site:hover{border-color:var(--p);color:var(--p);}
    .content{padding:26px 28px;flex:1;}
    .panel{display:none;}.panel.active{display:block;}

    /* ── Welcome banner ── */
    .welcome-banner{background:linear-gradient(135deg,var(--p-dark),var(--p));border-radius:14px;padding:24px 28px;color:#fff;display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:24px;flex-wrap:wrap;}
    .wb-title{font-size:22px;font-weight:800;margin-bottom:6px;}
    .wb-sub{font-size:14px;color:rgba(255,255,255,.7);}
    .wb-close{background:rgba(255,255,255,.15);border:none;color:#fff;width:30px;height:30px;border-radius:50%;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center;}

    /* ── Stats ── */
    .stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:26px;}
    .stat-card{background:var(--white);border:1px solid var(--border);border-radius:12px;padding:18px 16px;display:flex;align-items:center;gap:12px;}
    .stat-icon{width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;}
    .stat-num{font-size:26px;font-weight:800;line-height:1;}
    .stat-lbl{font-size:11.5px;color:var(--muted);margin-top:3px;}

    /* ── Section heading ── */
    .sec-hd{margin-bottom:18px;}
    .sec-hd h3{font-size:19px;font-weight:800;}
    .sec-hd p{font-size:13px;color:var(--muted);margin-top:3px;}

    /* ── Cards ── */
    .fcard{background:var(--white);border:1px solid var(--border);border-radius:12px;overflow:hidden;margin-bottom:20px;}
    .fcard-head{padding:14px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px;}
    .fcard-hicon{width:34px;height:34px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:14px;}
    .fcard-head h5{font-size:15px;font-weight:700;margin:0;}
    .fcard-head p{font-size:11.5px;color:var(--muted);margin:2px 0 0;}
    .fcard-body{padding:20px;}

    /* ── Inputs ── */
    .form-label{font-size:12.5px;font-weight:600;color:#444;margin-bottom:5px;}
    .form-control,.form-select{border-radius:7px;border:1.5px solid var(--border);font-size:13.5px;padding:9px 13px;font-family:'Plus Jakarta Sans',sans-serif;transition:border-color .2s;}
    .form-control:focus,.form-select:focus{border-color:var(--p);box-shadow:0 0 0 3px rgba(95,37,159,.08);outline:none;}

    /* ── Buttons ── */
    .abtn{display:inline-flex;align-items:center;gap:7px;padding:9px 20px;border-radius:7px;font-size:13.5px;font-weight:600;border:none;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;text-decoration:none;white-space:nowrap;transition:opacity .15s;}
    .abtn:hover{opacity:.88;}
    .abtn-purple{background:var(--p);color:#fff;}.abtn-purple:hover{color:#fff;}
    .abtn-green{background:var(--green);color:#fff;}.abtn-green:hover{color:#fff;}
    .abtn-blue{background:#0284c7;color:#fff;}.abtn-blue:hover{color:#fff;}
    .abtn-amber{background:#b45309;color:#fff;}.abtn-amber:hover{color:#fff;}
    .abtn-teal{background:var(--teal);color:#fff;}.abtn-teal:hover{color:#fff;}
    .abtn-red{background:#dc2626;color:#fff;}.abtn-red:hover{color:#fff;}
    .abtn-sm{padding:7px 14px;font-size:12.5px;}

    /* ── Course cards ── */
    .course-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px;}
    .cc-card{background:var(--white);border:1px solid var(--border);border-radius:12px;overflow:hidden;transition:box-shadow .2s,border-color .2s;text-decoration:none;color:inherit;display:block;}
    .cc-card:hover{box-shadow:0 6px 20px rgba(0,0,0,.08);border-color:var(--p);}
    .cc-card img{width:100%;height:140px;object-fit:cover;display:block;}
    .cc-card-placeholder{width:100%;height:140px;background:var(--p-lt);display:flex;align-items:center;justify-content:center;color:#c5aeeb;font-size:36px;}
    .cc-card-body{padding:14px 16px;}
    .cc-card-title{font-size:14.5px;font-weight:700;margin-bottom:8px;}
    .cc-chip{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:20px;font-size:11.5px;font-weight:700;background:var(--p-lt);color:var(--p);}
    .cc-chip.amber{background:#fff3e0;color:#b45309;}
    .cc-chip.teal{background:#e6fff5;color:var(--teal);}
    .cc-chip.green{background:#dcfce7;color:var(--green);}
    .cc-chip-row{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px;}
    .deadline-badge{display:inline-flex;align-items:center;gap:5px;font-size:12px;color:#dc2626;font-weight:600;}

    /* ── Course Editor ── */
    .editor-hero{background:var(--white);border:1px solid var(--border);border-radius:12px;overflow:hidden;margin-bottom:20px;}
    .editor-hero-banner{height:150px;width:100%;object-fit:cover;display:block;}
    .editor-hero-banner-ph{height:150px;background:var(--p-dark);display:flex;align-items:center;justify-content:center;color:rgba(255,255,255,.2);font-size:48px;}
    .editor-hero-body{padding:18px 22px;display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;}
    .editor-hero-title{font-size:19px;font-weight:800;margin-bottom:4px;}
    .editor-hero-meta{display:flex;gap:14px;flex-wrap:wrap;align-items:center;margin-top:8px;}
    .editor-meta-item{display:flex;align-items:center;gap:5px;font-size:12.5px;color:var(--muted);}
    .editor-meta-item i{color:var(--p);}

    /* ── Editor Tabs ── */
    .editor-tabs{display:flex;gap:4px;background:var(--white);border:1px solid var(--border);border-radius:10px;padding:5px;margin-bottom:18px;flex-wrap:wrap;}
    .etab{flex:1;min-width:110px;display:flex;align-items:center;justify-content:center;gap:6px;padding:10px 12px;border-radius:7px;font-size:12.5px;font-weight:600;cursor:pointer;color:var(--muted);border:none;background:transparent;font-family:'Plus Jakarta Sans',sans-serif;transition:background .15s,color .15s;}
    .etab:hover{background:var(--bg);color:var(--text);}
    .etab.active{background:var(--p);color:#fff;}
    .etab.active.teal-tab{background:var(--teal);}
    .etab.active.green-tab{background:var(--green);}
    .etab-count{width:20px;height:20px;border-radius:5px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;background:rgba(255,255,255,.25);}
    .etab:not(.active) .etab-count{background:var(--p-lt);color:var(--p);}
    .etab:not(.active).teal-tab .etab-count{background:#e6fff5;color:var(--teal);}
    .etab:not(.active).green-tab .etab-count{background:#dcfce7;color:var(--green);}
    .etab-panel{display:none;}.etab-panel.active{display:block;}

    /* ── Quiz / Live rows ── */
    .qrow{display:flex;align-items:center;justify-content:space-between;padding:11px 14px;background:var(--bg);border:1px solid var(--border);border-radius:8px;margin-bottom:7px;transition:border-color .15s;}
    .qrow:hover{border-color:var(--p);}
    .qrow-left{display:flex;align-items:center;gap:8px;font-size:13.5px;font-weight:600;}
    .qrow-left i{color:var(--p);}

    /* ── Quiz timer badge ── */
    .timer-badge{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:700;background:#fef3c7;color:#92400e;border:1px solid #fde68a;}
    .timer-badge.no-timer{background:#f3f4f6;color:#9ca3af;border-color:#e5e7eb;}

    /* ── Live class rows ── */
    .lrow{display:flex;align-items:center;gap:14px;padding:16px 18px;border-radius:10px;border:1.5px solid var(--border);margin-bottom:10px;transition:all .2s;background:var(--white);}
    .lrow-upcoming{border-left:4px solid var(--teal);}.lrow-upcoming:hover{background:#f0fff9;}
    .lrow-live-now{border-left:4px solid #e17055;background:#fff8f5;}
    .lrow-finished{border-left:4px solid #ccc;opacity:.72;background:#fafafa;}
    .lrow-dot{width:12px;height:12px;border-radius:50%;}
    .live-dot-upcoming{background:var(--teal);}
    .live-dot-live-now{background:#e17055;animation:blink 1.2s infinite;box-shadow:0 0 0 4px rgba(225,112,85,.2);}
    .live-dot-finished{background:#ccc;}
    @keyframes blink{0%,100%{opacity:1}50%{opacity:.25}}
    .lrow-body{flex:1;min-width:0;}
    .lrow-top{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:4px;}
    .lrow-title-text{font-size:14px;font-weight:700;}
    .lrow-meta-row{display:flex;align-items:center;gap:14px;flex-wrap:wrap;font-size:12px;color:var(--muted);}
    .lrow-meta-row i{color:var(--teal);}
    .lrow-meta-row span{display:flex;align-items:center;gap:5px;}
    .lbadge{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;}
    .lbadge-upcoming{background:#e6fff5;color:var(--teal);}
    .lbadge-live-now{background:#fff0eb;color:#e17055;}
    .lbadge-finished{background:#f0f0f0;color:#999;}
    .lbtn-join{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;border-radius:8px;background:linear-gradient(135deg,#e17055,#d63031);color:#fff;font-size:13px;font-weight:700;text-decoration:none;white-space:nowrap;}
    .lbtn-waiting{display:inline-flex;align-items:center;gap:7px;padding:9px 16px;border-radius:8px;background:#e6fff5;color:var(--teal);font-size:13px;font-weight:700;}
    .lbtn-finished{display:inline-flex;align-items:center;gap:7px;padding:9px 16px;border-radius:8px;background:#f0f0f0;color:#999;font-size:13px;font-weight:600;}

    /* ── Video table ── */
    .vtbl{width:100%;border-collapse:collapse;}
    .vtbl th{background:var(--bg);padding:11px 14px;font-size:11px;font-weight:800;color:var(--muted);text-transform:uppercase;letter-spacing:.8px;border-bottom:2px solid var(--border);text-align:left;}
    .vtbl td{padding:12px 14px;border-bottom:1px solid var(--border);font-size:13.5px;vertical-align:middle;}
    .vtbl tr:last-child td{border-bottom:none;}
    .vtbl tr:hover td{background:#faf8ff;}
    .vtbl img{width:76px;height:48px;object-fit:cover;border-radius:6px;display:block;}
    .vtbl .no-thumb{width:76px;height:48px;border-radius:6px;background:var(--p-lt);display:flex;align-items:center;justify-content:center;color:#ccc;font-size:18px;}
    .vtype{display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:700;}
    .vtype.yt{background:#fee2e2;color:#dc2626;}
    .vtype.loc{background:#dcfce7;color:var(--green);}
    .tbtn{display:inline-flex;align-items:center;gap:4px;padding:5px 11px;border-radius:6px;font-size:12px;font-weight:700;cursor:pointer;text-decoration:none;border:1.5px solid;background:transparent;font-family:'Plus Jakarta Sans',sans-serif;transition:background .15s,color .15s;}
    .tbtn-edit{color:var(--p);border-color:var(--p);}.tbtn-edit:hover{background:var(--p);color:#fff;}
    .tbtn-del{color:#dc2626;border-color:#dc2626;}.tbtn-del:hover{background:#dc2626;color:#fff;}
    .tbtn-retake{color:#b45309;border-color:#b45309;}.tbtn-retake:hover{background:#b45309;color:#fff;}

    /* ── Drop zone ── */
    .drop-zone{border:2px dashed var(--border);border-radius:8px;padding:26px 18px;text-align:center;background:var(--bg);cursor:pointer;transition:border-color .2s,background .2s;}
    .drop-zone:hover,.drop-zone.dragover{border-color:var(--p);background:var(--p-lt);}
    .drop-zone i{font-size:28px;color:var(--muted);display:block;margin-bottom:8px;}
    .drop-zone p{font-size:13.5px;color:var(--muted);margin:0 0 4px;}
    .drop-zone small{font-size:11.5px;color:#bbb;}
    .fileInfo{font-size:12.5px;font-weight:700;color:var(--green);margin-top:8px;}
    .upload-progress{display:none;margin-top:14px;}
    .progress{height:8px;border-radius:8px;background:var(--border);overflow:hidden;}
    .progress-bar{border-radius:8px;background:var(--p);transition:width .3s;}

    /* ── Student performance table ── */
    .perf-table{width:100%;border-collapse:collapse;}
    .perf-table th{background:var(--bg);padding:11px 14px;font-size:11px;font-weight:800;color:var(--muted);text-transform:uppercase;letter-spacing:.8px;border-bottom:2px solid var(--border);text-align:left;}
    .perf-table td{padding:13px 14px;border-bottom:1px solid var(--border);font-size:13.5px;vertical-align:middle;}
    .perf-table tr:last-child td{border-bottom:none;}
    .perf-table tr:hover td{background:#faf8ff;}
    .progress-pill{height:8px;border-radius:8px;background:#eee;overflow:hidden;width:100px;}
    .progress-pill-fill{height:100%;border-radius:8px;background:linear-gradient(90deg,var(--p),var(--accent));}
    .score-badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700;}

    /* ── Toast ── */
    .toast-wrap{position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:8px;}
    .toast{background:#1e1a2e;color:#fff;padding:12px 18px;border-radius:10px;font-size:13.5px;font-weight:600;display:flex;align-items:center;gap:10px;box-shadow:0 8px 24px rgba(0,0,0,.2);animation:toastIn .3s ease;}
    @keyframes toastIn{from{transform:translateY(20px);opacity:0}to{transform:translateY(0);opacity:1}}
    .toast.success{border-left:4px solid var(--green);}
    .toast.info{border-left:4px solid var(--p);}
    .toast.warning{border-left:4px solid #f59e0b;}

    /* ── Empty state ── */
    .empty-state{text-align:center;padding:42px 20px;color:var(--muted);}
    .empty-state i{font-size:32px;color:#ddd;margin-bottom:10px;display:block;}
    .empty-state p{font-size:13.5px;}

    /* ── Edit Course Title Modal ── */
    .title-edit-modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1002;align-items:center;justify-content:center;}
    .title-edit-modal-overlay.show{display:flex;}
    .title-edit-modal-box{background:#fff;border-radius:16px;padding:28px;max-width:480px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,.2);animation:modalIn .25s ease;}
    .title-edit-modal-title{font-size:17px;font-weight:800;margin-bottom:20px;display:flex;align-items:center;gap:10px;color:var(--text);}
    .title-edit-modal-title i{color:var(--p);}

    /* ── Retake confirm modal ── */
    .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center;}
    .modal-overlay.show{display:flex;}
    .modal-box{background:#fff;border-radius:16px;padding:28px;max-width:420px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,.2);animation:modalIn .25s ease;}
    @keyframes modalIn{from{transform:scale(.92);opacity:0}to{transform:scale(1);opacity:1}}
    .modal-icon{width:56px;height:56px;border-radius:50%;background:#fff3cd;display:flex;align-items:center;justify-content:center;font-size:24px;margin:0 auto 16px;}
    .modal-title{font-size:17px;font-weight:800;text-align:center;margin-bottom:8px;}
    .modal-msg{font-size:13.5px;color:#666;text-align:center;line-height:1.6;margin-bottom:22px;}
    .modal-actions{display:flex;gap:10px;justify-content:center;}

    @media(max-width:900px){.stats-row{grid-template-columns:repeat(2,1fr);}.etab{min-width:unset;}}
    @media(max-width:600px){.sidebar{transform:translateX(-100%);}.main-area{margin-left:0;}.content{padding:16px;}}
  </style>
</head>
<body>

<!-- ═══════ EDIT COURSE TITLE MODAL ═══════ -->
<div class="title-edit-modal-overlay" id="editTitleModal">
  <div class="title-edit-modal-box">
    <div class="title-edit-modal-title">
      <i class="fas fa-pen"></i> Edit Course Title
    </div>
    <form method="post" action="update_course_title.php">
      <input type="hidden" name="course_id" value="<?= $activeCourseId ?>">
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label">Course Title</label>
          <input type="text" name="title" id="editTitleInput" class="form-control" required
                 placeholder="Enter course title…">
        </div>
        <div class="col-12" style="display:flex;gap:10px;justify-content:flex-end;margin-top:4px;">
          <button type="button" class="abtn" style="background:#e5e7eb;color:#333;" onclick="closeEditTitleModal()">
            Cancel
          </button>
          <button type="submit" class="abtn abtn-purple">
            <i class="fas fa-save"></i> Save Title
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- ═══════ RETAKE CONFIRM MODAL ═══════ -->
<div class="modal-overlay" id="retakeModal">
  <div class="modal-box">
    <div class="modal-icon">⚠️</div>
    <div class="modal-title">Allow Course Retake?</div>
    <div class="modal-msg">This will reset <strong id="retakeStudentName">the student</strong>'s entire progress for this course — all video progress, quiz attempts, and answers will be deleted. This cannot be undone.</div>
    <div class="modal-actions">
      <form method="post" action="allow_retake.php" id="retakeForm">
        <input type="hidden" name="student_id" id="retakeStudentId">
        <input type="hidden" name="course_id"  value="<?= $activeCourseId ?>">
        <button type="submit" class="abtn abtn-red"><i class="fas fa-redo"></i> Yes, Allow Retake</button>
      </form>
      <button class="abtn" style="background:#e5e7eb;color:#333;" onclick="closeRetakeModal()">Cancel</button>
    </div>
  </div>
</div>

<!-- ═══════ SIDEBAR ═══════ -->
<aside class="sidebar">
  <div class="sb-brand">
    <div class="logo">📚 <span>EDU</span>RAY</div>
    <div class="sb-role"><i class="fas fa-chalkboard-teacher" style="margin-right:4px;"></i>Instructor</div>
  </div>
  <nav class="sb-nav">
    <div class="sb-lbl">Main</div>
    <a href="?s=overview" class="sb-link <?= $section==='overview'?'active':'' ?>">
      <i class="fas fa-home"></i> Overview
    </a>
    <div class="sb-lbl">Courses</div>
    <a href="?s=mycourses" class="sb-link <?= $section==='mycourses'?'active':'' ?>">
      <i class="fas fa-th-large"></i> My Courses
    </a>
    <a href="?s=newcourse" class="sb-link <?= $section==='newcourse'?'active':'' ?>">
      <i class="fas fa-plus-circle"></i> Create New Course
    </a>
  </nav>
  <div class="sb-footer">
    <div class="sb-user">
      <div class="sb-avatar"><?= strtoupper(substr($userName ?: $userMail, 0, 1)) ?></div>
      <div>
        <div class="sb-uname"><?= htmlspecialchars($userName ?: 'Instructor') ?></div>
        <div class="sb-urole">Instructor</div>
      </div>
    </div>
    <a href="logout.php" class="sb-logout"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
  </div>
</aside>

<!-- ═══════ MAIN ═══════ -->
<div class="main-area">

  <!-- Topbar -->
  <div class="topbar">
    <div>
      <?php if ($section === 'courseeditor' && $activeCourse): ?>
        <div class="topbar-heading"><?= htmlspecialchars($activeCourse['title']) ?></div>
        <div class="topbar-bc">
          <a href="?s=mycourses">My Courses</a><span>›</span><span>Course Editor</span>
        </div>
      <?php else: ?>
        <?php $heads = ['overview'=>'<span>Instructor</span> Dashboard','mycourses'=>'My <span>Courses</span>','newcourse'=>'Create New <span>Course</span>']; ?>
        <div class="topbar-heading"><?= $heads[$section] ?? 'Instructor <span>Dashboard</span>' ?></div>
      <?php endif; ?>
    </div>
    <a href="index.php" class="btn-site"><i class="fas fa-external-link-alt"></i> View Site</a>
  </div>

  <div class="content">

    <!-- ═══ OVERVIEW ═══ -->
    <div class="panel <?= $section==='overview'?'active':'' ?>">
      <?php if ($welcome): ?>
        <div class="welcome-banner" id="welcomeBanner">
          <div>
            <div class="wb-title">🎉 Welcome to your Instructor Dashboard!</div>
            <div class="wb-sub">Start by creating your first course. Students can enroll and you'll see their progress here.</div>
          </div>
          <button class="wb-close" onclick="document.getElementById('welcomeBanner').remove()">✕</button>
        </div>
      <?php endif; ?>

      <div class="stats-row">
        <div class="stat-card">
          <div class="stat-icon" style="background:var(--p-lt);color:var(--p);"><i class="fas fa-book"></i></div>
          <div><div class="stat-num"><?= $totalCourses ?></div><div class="stat-lbl">My Courses</div></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon" style="background:#dcfce7;color:var(--green);"><i class="fas fa-users"></i></div>
          <div><div class="stat-num"><?= $totalStudents ?></div><div class="stat-lbl">Total Students</div></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon" style="background:#fff0e6;color:var(--accent);"><i class="fas fa-video"></i></div>
          <div><div class="stat-num"><?= $totalVideos ?></div><div class="stat-lbl">Total Videos</div></div>
        </div>
      </div>

      <div class="sec-hd"><h3>My Courses</h3><p>Click a course to edit or view student performance</p></div>
      <?php if (empty($courses)): ?>
        <div class="empty-state" style="background:var(--white);border:1px solid var(--border);border-radius:14px;">
          <i class="fas fa-book-open"></i>
          <p>No courses yet. <a href="?s=newcourse" style="color:var(--p);font-weight:700;">Create your first course →</a></p>
        </div>
      <?php else: ?>
        <div class="course-grid">
          <?php foreach ($courses as $c): ?>
            <a href="?cid=<?= (int)$c['id'] ?>" class="cc-card">
              <?php if (!empty($c['thumbnail_url'])): ?>
                <img src="<?= htmlspecialchars($c['thumbnail_url']) ?>" alt="">
              <?php else: ?>
                <div class="cc-card-placeholder"><i class="fas fa-photo-video"></i></div>
              <?php endif; ?>
              <div class="cc-card-body">
                <div class="cc-card-title"><?= htmlspecialchars($c['title']) ?></div>
                <div class="cc-chip-row">
                  <span class="cc-chip"><i class="fas fa-video" style="font-size:10px;"></i><?= $c['video_count'] ?> Videos</span>
                  <span class="cc-chip amber"><i class="fas fa-users" style="font-size:10px;"></i><?= $c['student_count'] ?> Students</span>
                </div>
                <?php if (!empty($c['deadline'])): ?>
                  <div class="deadline-badge"><i class="fas fa-calendar-times"></i>Deadline: <?= date('M d, Y', strtotime($c['deadline'])) ?></div>
                <?php endif; ?>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- ═══ MY COURSES LIST ═══ -->
    <div class="panel <?= $section==='mycourses'?'active':'' ?>">
      <div class="sec-hd"><h3>My Courses</h3><p>All courses you've created — click to open the editor</p></div>
      <?php if (empty($courses)): ?>
        <div class="empty-state" style="background:var(--white);border:1px solid var(--border);border-radius:14px;">
          <i class="fas fa-book-open"></i>
          <p>No courses yet. <a href="?s=newcourse" style="color:var(--p);font-weight:700;">Create your first course →</a></p>
        </div>
      <?php else: ?>
        <div class="course-grid">
          <?php foreach ($courses as $c): ?>
            <a href="?cid=<?= (int)$c['id'] ?>" class="cc-card">
              <?php if (!empty($c['thumbnail_url'])): ?>
                <img src="<?= htmlspecialchars($c['thumbnail_url']) ?>" alt="">
              <?php else: ?>
                <div class="cc-card-placeholder"><i class="fas fa-photo-video"></i></div>
              <?php endif; ?>
              <div class="cc-card-body">
                <div class="cc-card-title"><?= htmlspecialchars($c['title']) ?></div>
                <div class="cc-chip-row">
                  <span class="cc-chip"><i class="fas fa-video" style="font-size:10px;"></i><?= $c['video_count'] ?> Videos</span>
                  <span class="cc-chip amber"><i class="fas fa-clipboard-check" style="font-size:10px;"></i><?= $c['quiz_count'] ?> Quizzes</span>
                  <span class="cc-chip teal"><i class="fas fa-broadcast-tower" style="font-size:10px;"></i><?= $c['live_count'] ?> Live</span>
                  <span class="cc-chip green"><i class="fas fa-users" style="font-size:10px;"></i><?= $c['student_count'] ?> Students</span>
                </div>
                <?php if (!empty($c['deadline'])): ?>
                  <div style="margin-top:8px;">
                    <span class="deadline-badge"><i class="fas fa-calendar-times"></i><?= date('M d, Y', strtotime($c['deadline'])) ?></span>
                  </div>
                <?php endif; ?>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- ═══ CREATE NEW COURSE ═══ -->
    <div class="panel <?= $section==='newcourse'?'active':'' ?>">
      <div class="fcard" style="max-width:680px;">
        <div class="fcard-head">
          <div class="fcard-hicon" style="background:var(--p-lt);color:var(--p);"><i class="fas fa-plus"></i></div>
          <div><h5>Create a New Course</h5><p>Fill in the details below to create your course</p></div>
        </div>
        <div class="fcard-body">
          <form action="add_course.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="instructor_id" value="<?= $userId ?>">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label">Course Title</label>
                <input type="text" name="title" class="form-control" placeholder="e.g. Web Development Fundamentals" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Enrollment Deadline <small class="text-muted fw-normal">(optional)</small></label>
                <input type="date" name="deadline" class="form-control">
              </div>
              <div class="col-md-6">
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

    <!-- ═══ COURSE EDITOR ═══ -->
    <?php if ($section === 'courseeditor' && $activeCourse):
      $cid      = (int)$activeCourse['id'];
      $cVideos  = $byCourse[$cid]     ?? [];
      $cQuizzes = $byCourseQuiz[$cid] ?? [];
      $cLive    = $byCourseLive[$cid] ?? [];
    ?>
    <div class="panel active">

      <!-- Retake granted alert -->
      <?php if (isset($_GET['title_updated'])): ?>
        <div style="background:#ede9fe;border:1px solid #c4b5fd;color:#5f259f;border-radius:10px;padding:13px 18px;font-size:13.5px;font-weight:600;margin-bottom:18px;display:flex;align-items:center;gap:10px;">
          <i class="fas fa-check-circle fa-lg"></i> Course title updated successfully.
        </div>
      <?php endif; ?>

      <?php if (isset($_GET['retake_granted'])): ?>
        <div style="background:#dcfce7;border:1px solid #86efac;color:#16a34a;border-radius:10px;padding:13px 18px;font-size:13.5px;font-weight:600;margin-bottom:18px;display:flex;align-items:center;gap:10px;">
          <i class="fas fa-check-circle fa-lg"></i> Course retake granted successfully. The student's progress has been reset.
        </div>
      <?php endif; ?>

      <!-- Hero -->
      <div class="editor-hero">
        <?php if (!empty($activeCourse['thumbnail_url'])): ?>
          <img src="<?= htmlspecialchars($activeCourse['thumbnail_url']) ?>" class="editor-hero-banner" alt="">
        <?php else: ?>
          <div class="editor-hero-banner-ph"><i class="fas fa-photo-video"></i></div>
        <?php endif; ?>
        <div class="editor-hero-body">
          <div>
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
              <div class="editor-hero-title"><?= htmlspecialchars($activeCourse['title']) ?></div>
              <button type="button" class="abtn abtn-sm" style="background:var(--p-lt);color:var(--p);border:1.5px solid var(--p);"
                onclick="openEditTitleModal('<?= addslashes(htmlspecialchars($activeCourse['title'], ENT_QUOTES)) ?>')">
                <i class="fas fa-pen"></i> Edit Title
              </button>
            </div>
            <?php if (!empty($activeCourse['deadline'])): ?>
              <div style="margin-bottom:6px;">
                <span class="deadline-badge"><i class="fas fa-calendar-times"></i>Deadline: <?= date('M d, Y', strtotime($activeCourse['deadline'])) ?></span>
              </div>
            <?php endif; ?>
            <div class="editor-hero-meta">
              <div class="editor-meta-item"><i class="fas fa-video"></i><?= count($cVideos) ?> Videos</div>
              <div class="editor-meta-item"><i class="fas fa-clipboard-check"></i><?= count($cQuizzes) ?> Quizzes</div>
              <div class="editor-meta-item" style="color:var(--teal);"><i class="fas fa-broadcast-tower" style="color:var(--teal);"></i><?= count($cLive) ?> Live</div>
              <div class="editor-meta-item" style="color:var(--green);"><i class="fas fa-users" style="color:var(--green);"></i><?= (int)$activeCourse['student_count'] ?> Students enrolled</div>
            </div>
          </div>
          <form action="course_cover_upload.php" method="post" enctype="multipart/form-data"
                style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <input type="hidden" name="course_id" value="<?= $cid ?>">
            <input type="file" name="cover" accept=".jpg,.jpeg,.png,.webp" required
                   class="form-control form-control-sm" style="font-size:12px;max-width:200px;">
            <button type="submit" class="abtn abtn-blue abtn-sm"><i class="fas fa-image"></i> Update Cover</button>
          </form>
        </div>
      </div>

      <!-- Editor Tabs -->
      <div class="editor-tabs">
        <button class="etab active"      onclick="switchTab(this,'tab-yt')">       <i class="fab fa-youtube"></i> YouTube</button>
        <button class="etab"             onclick="switchTab(this,'tab-upload')">   <i class="fas fa-cloud-upload-alt"></i> Upload</button>
        <button class="etab"             onclick="switchTab(this,'tab-videos')">   <i class="fas fa-film"></i> Videos <span class="etab-count"><?= count($cVideos) ?></span></button>
        <button class="etab"             onclick="switchTab(this,'tab-quizzes')">  <i class="fas fa-clipboard-check"></i> Quizzes <span class="etab-count"><?= count($cQuizzes) ?></span></button>
        <button class="etab"             onclick="switchTab(this,'tab-live')">     <i class="fas fa-broadcast-tower"></i> Live <span class="etab-count"><?= count($cLive) ?></span></button>
        <button class="etab"             onclick="switchTab(this,'tab-students')"> <i class="fas fa-users"></i> Students <span class="etab-count"><?= (int)$activeCourse['student_count'] ?></span></button>
        <?php
          $openQCount = 0;
          $allQRes = $conn->prepare("SELECT COUNT(*) FROM course_questions q LEFT JOIN course_question_replies r ON r.question_id=q.id WHERE q.course_id=? AND r.id IS NULL");
          $allQRes->bind_param("i", $cid);
          $allQRes->execute();
          $allQRes->bind_result($openQCount);
          $allQRes->fetch();
          $allQRes->close();
        ?>
        <button class="etab" style="<?= $openQCount > 0 ? 'color:#dc2626;' : '' ?>" onclick="switchTab(this,'tab-questions')">
          <i class="fas fa-comments"></i> Questions
          <span class="etab-count" style="<?= $openQCount > 0 ? 'background:#fee2e2;color:#dc2626;' : '' ?>"><?= $openQCount ?></span>
        </button>
      </div>

      <!-- Tab: YouTube -->
      <div class="etab-panel active" id="tab-yt">
        <div class="fcard">
          <div class="fcard-head">
            <div class="fcard-hicon" style="background:#fee2e2;color:#dc2626;"><i class="fab fa-youtube"></i></div>
            <div><h5>Add YouTube Video</h5><p>Paste any YouTube URL to add a lesson</p></div>
          </div>
          <div class="fcard-body">
            <form action="upload_video.php" method="post" enctype="multipart/form-data">
              <input type="hidden" name="video_type" value="youtube">
              <input type="hidden" name="course_id"  value="<?= $cid ?>">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Video Title</label>
                  <input type="text" name="title" class="form-control" placeholder="e.g. Lesson 1 – Introduction" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">YouTube URL</label>
                  <input type="text" name="embed_url" class="form-control" placeholder="https://youtu.be/xxx  or  watch?v=xxx" required>
                  <small style="color:var(--muted);font-size:11.5px;">Any YouTube link format accepted</small>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Thumbnail <small class="text-muted fw-normal">(optional — auto-fetched if blank)</small></label>
                  <input type="file" name="thumb" class="form-control" accept=".jpg,.jpeg,.png,.webp">
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
            <div><h5>Upload Video File</h5><p>Upload MP4/WebM/MOV (max 500MB)</p></div>
          </div>
          <div class="fcard-body">
            <form id="uploadVideoForm" action="upload_video_file.php" method="post" enctype="multipart/form-data">
              <input type="hidden" name="video_type" value="upload">
              <input type="hidden" name="course_id"  value="<?= $cid ?>">
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
                    <input type="file" name="video_file" id="videoFile" accept="video/mp4,video/webm,video/quicktime" required style="display:none;">
                    <div class="fileInfo" id="fileInfo"></div>
                  </div>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Thumbnail <small class="text-muted fw-normal">(jpg/png/webp, max 2MB)</small></label>
                  <input type="file" name="thumb" class="form-control" accept=".jpg,.jpeg,.png,.webp" required>
                </div>
                <div class="col-12">
                  <button type="submit" class="abtn abtn-blue" id="uploadBtn">
                    <span class="spinner-border spinner-border-sm d-none" id="uploadSpinner"></span>
                    <i class="fas fa-upload" id="uploadIcon"></i> Upload Video
                  </button>
                </div>
              </div>
              <div class="upload-progress" id="uploadProgress">
                <div style="display:flex;justify-content:space-between;margin-bottom:5px;">
                  <small style="font-weight:600;color:var(--muted);">Uploading…</small>
                  <small id="progressPercent" style="font-weight:800;color:var(--p);">0%</small>
                </div>
                <div class="progress"><div class="progress-bar" id="progressBar" style="width:0%;"></div></div>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Tab: Videos -->
      <div class="etab-panel" id="tab-videos">
        <div class="fcard">
          <div class="fcard-head">
            <div class="fcard-hicon" style="background:var(--p-lt);color:var(--p);"><i class="fas fa-film"></i></div>
            <div><h5>Course Videos</h5><p><?= count($cVideos) ?> video<?= count($cVideos)!==1?'s':'' ?></p></div>
          </div>
          <div class="fcard-body" style="padding:0;">
            <?php if (empty($cVideos)): ?>
              <div class="empty-state"><i class="fas fa-video-slash"></i><p>No videos yet.</p></div>
            <?php else: ?>
              <div style="overflow-x:auto;">
                <table class="vtbl">
                  <thead><tr><th>Type</th><th>Thumb</th><th>Title</th><th>Pos</th><th>Actions</th></tr></thead>
                  <tbody>
                    <?php foreach ($cVideos as $row):
                      $thumb = $row['thumb_url'] ?? '';
                      if (!$thumb && $row['video_type']==='youtube' && $row['embed_url']) {
                          preg_match('/embed\/([a-zA-Z0-9_-]{11})/', $row['embed_url'], $m);
                          if (!empty($m[1])) $thumb = 'https://img.youtube.com/vi/'.$m[1].'/hqdefault.jpg';
                      }
                    ?>
                      <tr>
                        <td><span class="vtype <?= $row['video_type']==='youtube'?'yt':'loc' ?>"><i class="fas fa-<?= $row['video_type']==='youtube'?'play':'file-video' ?>"></i><?= ucfirst($row['video_type']) ?></span></td>
                        <td><?php if ($thumb): ?><img src="<?= htmlspecialchars($thumb) ?>" alt=""><?php else: ?><div class="no-thumb"><i class="fas fa-image"></i></div><?php endif; ?></td>
                        <td style="font-weight:500;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($row['title']) ?></td>
                        <td style="text-align:center;font-weight:700;color:var(--p);"><?= (int)$row['position'] ?></td>
                        <td>
                          <div style="display:flex;gap:6px;">
                            <a class="tbtn tbtn-edit" href="video_edit.php?id=<?= (int)$row['id'] ?>"><i class="fas fa-pen"></i> Edit</a>
                            <form action="video_delete.php" method="post" class="d-inline" onsubmit="return confirm('Delete this video?');">
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
        <div class="fcard" style="margin-bottom:16px;">
          <div class="fcard-head">
            <div class="fcard-hicon" style="background:#fef9c3;color:#b45309;"><i class="fas fa-plus"></i></div>
            <div><h5>Add New Quiz</h5><p>Set a time limit so students are auto-submitted when time runs out</p></div>
          </div>
          <div class="fcard-body">
            <form action="add_quiz.php" method="post">
              <input type="hidden" name="course_id" value="<?= $cid ?>">
              <div class="row g-3">
                <div class="col-md-5">
                  <label class="form-label">Quiz Title</label>
                  <input type="text" name="title" class="form-control" placeholder="e.g. Chapter 1 Quiz" required>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Description <small class="text-muted fw-normal">(optional)</small></label>
                  <input type="text" name="description" class="form-control" placeholder="What does this cover?">
                </div>
                <div class="col-md-3">
                  <label class="form-label">
                    <i class="fas fa-clock" style="color:#b45309;"></i> Time Limit
                    <small class="text-muted fw-normal">(minutes)</small>
                  </label>
                  <div style="display:flex;align-items:center;gap:8px;">
                    <input type="number" name="time_limit" class="form-control" placeholder="e.g. 10" min="0" max="300" style="width:90px;">
                    <small style="color:var(--muted);white-space:nowrap;">0 = no limit</small>
                  </div>
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
            <div><h5>All Quizzes</h5><p><?= count($cQuizzes) ?> quiz<?= count($cQuizzes)!==1?'zes':'' ?></p></div>
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
                    <?php $tl = (int)($qz['time_limit'] ?? 0); ?>
                    <?php if ($tl > 0): ?>
                      <span class="timer-badge"><i class="fas fa-clock"></i><?= $tl ?> min</span>
                    <?php else: ?>
                      <span class="timer-badge no-timer"><i class="fas fa-infinity"></i> No limit</span>
                    <?php endif; ?>
                  </div>
                  <a href="add_quiz_questions.php?quiz_id=<?= (int)$qz['id'] ?>" class="tbtn tbtn-edit" style="font-size:12px;"><i class="fas fa-plus"></i> Add / View Questions</a>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Tab: Live Classes -->
      <div class="etab-panel" id="tab-live">
        <div class="fcard" style="margin-bottom:16px;">
          <div class="fcard-head">
            <div class="fcard-hicon" style="background:#e6fff5;color:var(--teal);"><i class="fas fa-broadcast-tower"></i></div>
            <div><h5>Schedule a Live Class</h5><p>Add a Google Meet session for your students</p></div>
          </div>
          <div class="fcard-body">
            <form action="add_live_class.php" method="post">
              <input type="hidden" name="course_id" value="<?= $cid ?>">
              <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Class Title</label><input type="text" name="title" class="form-control" placeholder="e.g. Week 3 – Q&amp;A" required></div>
                <div class="col-md-6"><label class="form-label">Google Meet Link</label><input type="url" name="meet_link" class="form-control" placeholder="https://meet.google.com/xxx-xxxx-xxx" required></div>
                <div class="col-md-6"><label class="form-label">Date &amp; Time</label><input type="datetime-local" name="scheduled_at" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Description <small class="text-muted fw-normal">(optional)</small></label><input type="text" name="description" class="form-control" placeholder="What will be covered?"></div>
                <div class="col-12"><button type="submit" class="abtn abtn-teal"><i class="fas fa-broadcast-tower"></i> Schedule Live Class</button></div>
              </div>
            </form>
          </div>
        </div>
        <div class="fcard">
          <div class="fcard-head">
            <div class="fcard-hicon" style="background:#e6fff5;color:var(--teal);"><i class="fas fa-list"></i></div>
            <div><h5>Scheduled Live Classes</h5><p><?= count($cLive) ?> class<?= count($cLive)!==1?'es':'' ?></p></div>
          </div>
          <div class="fcard-body">
            <?php if (empty($cLive)): ?>
              <div class="empty-state"><i class="fas fa-video-slash"></i><p>No live classes yet.</p></div>
            <?php else:
              $now = time();
              foreach ($cLive as $live):
                $ts = strtotime($live['scheduled_at']); $dm = ($ts-$now)/60;
                if ($dm>=-120&&$dm<=15)  { $state='live-now'; }
                elseif ($ts>$now)        { $state='upcoming'; }
                else                     { $state='finished'; }
            ?>
              <div class="lrow lrow-<?= $state ?>">
                <div style="flex-shrink:0;"><div class="lrow-dot live-dot-<?= $state ?>"></div></div>
                <div class="lrow-body">
                  <div class="lrow-top">
                    <span class="lrow-title-text"><?= htmlspecialchars($live['title']) ?></span>
                    <span class="lbadge lbadge-<?= $state ?>">
                      <?php if($state==='live-now') echo '🔴 Live Now'; elseif($state==='upcoming') echo '🟢 Upcoming'; else echo '✅ Finished'; ?>
                    </span>
                  </div>
                  <div class="lrow-meta-row">
                    <span><i class="fas fa-calendar-alt"></i><?= date('D, M d Y', $ts) ?></span>
                    <span><i class="fas fa-clock"></i><?= date('h:i A', $ts) ?></span>
                    <?php if ($state==='upcoming'): ?>
                      <span class="lrow-countdown" style="color:var(--p);font-weight:700;" data-ts="<?= $ts ?>">
                        <i class="fas fa-hourglass-half"></i><span class="cd-text">…</span>
                      </span>
                    <?php endif; ?>
                  </div>
                </div>
                <div style="flex-shrink:0;">
                  <?php if ($state==='live-now'): ?>
                    <a href="<?= htmlspecialchars($live['meet_link']) ?>" target="_blank" class="lbtn-join"><i class="fas fa-video"></i> Join</a>
                  <?php elseif ($state==='upcoming'): ?>
                    <div class="lbtn-waiting"><i class="fas fa-hourglass-start"></i> Waiting</div>
                  <?php else: ?>
                    <div class="lbtn-finished"><i class="fas fa-flag-checkered"></i> Finished</div>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; endif; ?>
          </div>
        </div>
      </div>

      <!-- ═══ Tab: Students & Performance ═══ -->
      <div class="etab-panel" id="tab-students">
        <?php if (!empty($cQuizzes)): ?>
        <div class="fcard" style="margin-bottom:16px;">
          <div class="fcard-head">
            <div class="fcard-hicon" style="background:#fef9c3;color:#b45309;"><i class="fas fa-clipboard-check"></i></div>
            <div><h5>View Quiz Results</h5><p>Select a quiz to see each student's answers question by question</p></div>
          </div>
          <div class="fcard-body">
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
              <?php foreach ($cQuizzes as $qz): ?>
                <button class="abtn abtn-amber abtn-sm"
                    onclick="loadQuizResults(<?= (int)$qz['id'] ?>, '<?= htmlspecialchars($qz['title'], ENT_QUOTES) ?>')">
                  <i class="fas fa-eye"></i> <?= htmlspecialchars($qz['title']) ?>
                </button>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <div id="quizResultsPanel" style="display:none;margin-bottom:20px;">
          <div class="fcard">
            <div class="fcard-head">
              <div class="fcard-hicon" style="background:#fef9c3;color:#b45309;"><i class="fas fa-chart-bar"></i></div>
              <div style="flex:1;">
                <h5 id="quizResultsTitle">Quiz Results</h5>
                <p id="quizResultsSubtitle" style="font-size:11.5px;color:var(--muted);margin:2px 0 0;">Loading…</p>
              </div>
              <button onclick="document.getElementById('quizResultsPanel').style.display='none'"
                style="margin-left:auto;background:none;border:none;font-size:20px;cursor:pointer;color:#aaa;line-height:1;" title="Close">✕</button>
            </div>
            <div class="fcard-body" style="padding:0;">
              <div id="quizResultsBody">
                <div style="text-align:center;padding:40px;color:#aaa;"><i class="fas fa-spinner fa-spin fa-2x"></i></div>
              </div>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <div class="fcard">
          <div class="fcard-head">
            <div class="fcard-hicon" style="background:#dcfce7;color:var(--green);"><i class="fas fa-users"></i></div>
            <div><h5>Overall Student Performance</h5><p><?= count($studentPerformance) ?> student<?= count($studentPerformance)!==1?'s':'' ?> enrolled</p></div>
          </div>
          <div class="fcard-body" style="padding:0;">
            <?php if (empty($studentPerformance)): ?>
              <div class="empty-state"><i class="fas fa-user-slash"></i><p>No students enrolled yet.</p></div>
            <?php else: ?>
              <div style="overflow-x:auto;">
                <table class="perf-table">
                  <thead>
                    <tr>
                      <th>Student</th>
                      <th>Enrolled</th>
                      <th>Videos</th>
                      <th>Quizzes</th>
                      <th>Avg Score</th>
                      <th>Performance</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($studentPerformance as $s):
                      $vidPct   = $s['total_videos']  > 0 ? round($s['videos_done']      / $s['total_videos']  * 100) : 0;
                      $quizPct  = $s['total_quizzes'] > 0 ? round($s['quizzes_attempted'] / $s['total_quizzes'] * 100) : 0;
                      $avgScore = $s['avg_score'];
                      $color    = perfColor($avgScore);
                      $label    = perfLabel($avgScore);
                    ?>
                      <tr>
                        <td>
                          <div style="font-weight:700;"><?= htmlspecialchars($s['name'] ?: '—') ?></div>
                          <div style="font-size:12px;color:var(--muted);"><?= htmlspecialchars($s['email']) ?></div>
                        </td>
                        <td style="font-size:12.5px;color:var(--muted);"><?= date('M d, Y', strtotime($s['enrolled_at'])) ?></td>
                        <td>
                          <div style="display:flex;align-items:center;gap:8px;">
                            <div class="progress-pill"><div class="progress-pill-fill" style="width:<?= $vidPct ?>%;"></div></div>
                            <span style="font-size:12px;font-weight:600;"><?= $s['videos_done'] ?>/<?= $s['total_videos'] ?></span>
                          </div>
                        </td>
                        <td>
                          <div style="display:flex;align-items:center;gap:8px;">
                            <div class="progress-pill"><div class="progress-pill-fill" style="width:<?= $quizPct ?>%;background:linear-gradient(90deg,#b45309,#f59e0b);"></div></div>
                            <span style="font-size:12px;font-weight:600;"><?= $s['quizzes_attempted'] ?>/<?= $s['total_quizzes'] ?></span>
                          </div>
                        </td>
                        <td>
                          <?php if ($avgScore !== null): ?>
                            <span class="score-badge" style="background:<?= $color ?>20;color:<?= $color ?>;"><?= $avgScore ?>%</span>
                          <?php else: ?>
                            <span style="color:#bbb;font-size:13px;">No quizzes</span>
                          <?php endif; ?>
                        </td>
                        <td><span style="font-size:13px;font-weight:700;color:<?= $color ?>;"><?= $label ?></span></td>
                        <td>
                          <button class="tbtn tbtn-retake"
                            onclick="openRetakeModal(<?= (int)$s['id'] ?>, '<?= htmlspecialchars($s['name'] ?: 'this student', ENT_QUOTES) ?>')">
                            <i class="fas fa-redo"></i> Allow Retake
                          </button>
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

      <!-- Tab: Questions -->
      <div class="etab-panel" id="tab-questions">
        <?php if (isset($_GET['replied'])): ?>
          <div style="background:#dcfce7;border:1px solid #86efac;color:#16a34a;border-radius:8px;padding:11px 14px;font-size:13.5px;font-weight:600;margin-bottom:16px;display:flex;align-items:center;gap:8px;">
            <i class="fas fa-check-circle"></i> Reply sent successfully!
          </div>
        <?php endif; ?>
        <?php
          $allQuestions = [];
          $aqRes = $conn->prepare("
              SELECT q.id, q.question, q.created_at, q.video_id,
                     u.name AS student_name, u.email AS student_email,
                     v.title AS video_title,
                     r.reply, r.created_at AS replied_at
              FROM course_questions q
              JOIN users u ON u.id = q.student_id
              LEFT JOIN videos v ON v.id = q.video_id
              LEFT JOIN course_question_replies r ON r.question_id = q.id
              WHERE q.course_id = ?
              ORDER BY r.id IS NOT NULL ASC, q.created_at DESC
          ");
          $aqRes->bind_param("i", $cid);
          $aqRes->execute();
          $allQuestions = $aqRes->get_result()->fetch_all(MYSQLI_ASSOC);
          $aqRes->close();
          $unanswered = array_filter($allQuestions, fn($q) => !$q['reply']);
          $answered   = array_filter($allQuestions, fn($q) =>  $q['reply']);
        ?>
        <?php if (empty($allQuestions)): ?>
          <div class="fcard"><div class="fcard-body"><div class="empty-state"><i class="fas fa-comments"></i><p>No questions yet for this course.</p></div></div></div>
        <?php else: ?>
          <?php if (!empty($unanswered)): ?>
          <div class="fcard" style="margin-bottom:20px;">
            <div class="fcard-head">
              <div class="fcard-hicon" style="background:#fee2e2;color:#dc2626;"><i class="fas fa-clock"></i></div>
              <div><h5>Unanswered <span style="color:#dc2626;">(<?= count($unanswered) ?>)</span></h5><p>These questions are waiting for your reply</p></div>
            </div>
            <div class="fcard-body" style="padding:0 20px 20px;">
              <div style="display:flex;flex-direction:column;gap:14px;">
                <?php foreach ($unanswered as $q): ?>
                  <div style="background:#fff8f8;border:1.5px solid #fecaca;border-radius:10px;overflow:hidden;">
                    <div style="padding:14px 16px;">
                      <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;flex-wrap:wrap;">
                        <div style="width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,var(--p),var(--accent));display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;color:#fff;flex-shrink:0;"><?= strtoupper(substr($q['student_name'] ?: 'S', 0, 1)) ?></div>
                        <span style="font-size:12.5px;font-weight:700;color:var(--text);"><?= htmlspecialchars($q['student_name']) ?></span>
                        <span style="font-size:11px;color:var(--muted);"><?= htmlspecialchars($q['student_email']) ?></span>
                        <?php if ($q['video_title']): ?>
                          <span style="background:#ede9fe;color:#5f259f;font-size:11px;font-weight:700;padding:2px 9px;border-radius:20px;margin-left:auto;"><i class="fas fa-video" style="font-size:9px;"></i> <?= htmlspecialchars($q['video_title']) ?></span>
                        <?php else: ?>
                          <span style="background:#f0fdf4;color:#16a34a;font-size:11px;font-weight:700;padding:2px 9px;border-radius:20px;margin-left:auto;"><i class="fas fa-comments" style="font-size:9px;"></i> Help Chat</span>
                        <?php endif; ?>
                        <span style="font-size:11px;color:#bbb;"><?= date('M d, Y · h:i A', strtotime($q['created_at'])) ?></span>
                      </div>
                      <div style="font-size:13.5px;color:var(--text);line-height:1.6;background:#fff;border-radius:8px;padding:10px 12px;border:1px solid #fecaca;"><?= nl2br(htmlspecialchars($q['question'])) ?></div>
                    </div>
                    <div style="padding:12px 16px;background:#fff5f5;border-top:1px solid #fecaca;">
                      <form action="reply_question.php" method="post">
                        <input type="hidden" name="question_id" value="<?= (int)$q['id'] ?>">
                        <input type="hidden" name="course_id"   value="<?= $cid ?>">
                        <textarea name="reply" style="width:100%;background:#fff;border:1.5px solid #e5e7eb;border-radius:8px;padding:10px 12px;font-size:13px;font-family:'Plus Jakarta Sans',sans-serif;resize:vertical;min-height:68px;outline:none;" onfocus="this.style.borderColor='#5f259f'" onblur="this.style.borderColor='#e5e7eb'" placeholder="Type your reply…" required></textarea>
                        <button type="submit" style="margin-top:8px;padding:8px 20px;background:linear-gradient(135deg,var(--p),var(--accent));color:#fff;border:none;border-radius:7px;font-size:13px;font-weight:700;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;display:inline-flex;align-items:center;gap:6px;"><i class="fas fa-paper-plane"></i> Send Reply</button>
                      </form>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
          <?php endif; ?>
          <?php if (!empty($answered)): ?>
          <div class="fcard">
            <div class="fcard-head">
              <div class="fcard-hicon" style="background:#dcfce7;color:var(--green);"><i class="fas fa-check-circle"></i></div>
              <div><h5>Answered <span style="color:var(--green);">(<?= count($answered) ?>)</span></h5><p>You have replied to these questions</p></div>
            </div>
            <div class="fcard-body" style="padding:0 20px 20px;">
              <div style="display:flex;flex-direction:column;gap:12px;">
                <?php foreach ($answered as $q): ?>
                  <div style="background:#fafafa;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">
                    <div style="padding:13px 15px;">
                      <div style="display:flex;align-items:center;gap:8px;margin-bottom:7px;flex-wrap:wrap;">
                        <div style="width:26px;height:26px;border-radius:50%;background:var(--p-lt);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;color:var(--p);flex-shrink:0;"><?= strtoupper(substr($q['student_name'] ?: 'S', 0, 1)) ?></div>
                        <span style="font-size:12px;font-weight:700;color:var(--text);"><?= htmlspecialchars($q['student_name']) ?></span>
                        <?php if ($q['video_title']): ?>
                          <span style="background:#ede9fe;color:#5f259f;font-size:11px;font-weight:600;padding:1px 8px;border-radius:20px;"><i class="fas fa-video" style="font-size:9px;"></i> <?= htmlspecialchars($q['video_title']) ?></span>
                        <?php else: ?>
                          <span style="background:#f0fdf4;color:#16a34a;font-size:11px;font-weight:600;padding:1px 8px;border-radius:20px;"><i class="fas fa-comments" style="font-size:9px;"></i> Help Chat</span>
                        <?php endif; ?>
                        <span style="font-size:11px;color:#bbb;margin-left:auto;"><?= date('M d, Y', strtotime($q['created_at'])) ?></span>
                      </div>
                      <div style="font-size:13px;color:#555;line-height:1.6;"><?= nl2br(htmlspecialchars($q['question'])) ?></div>
                    </div>
                    <div style="padding:11px 15px;background:#f0fdf4;border-top:1px solid #dcfce7;">
                      <div style="font-size:11px;font-weight:800;color:var(--green);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;"><i class="fas fa-reply"></i> Your Reply</div>
                      <div style="font-size:13px;color:#444;line-height:1.6;"><?= nl2br(htmlspecialchars($q['reply'])) ?></div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>

    </div><!-- /course editor -->
    <?php endif; ?>

  </div><!-- /content -->
</div><!-- /main-area -->

<div class="toast-wrap" id="toastWrap"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function switchTab(btn, id) {
  document.querySelectorAll('.etab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.etab-panel').forEach(p => p.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById(id).classList.add('active');
}

function showToast(msg, type='success') {
  const wrap = document.getElementById('toastWrap');
  const t = document.createElement('div');
  t.className = 'toast ' + type;
  t.innerHTML = `<i class="fas fa-${type==='success'?'check-circle':type==='warning'?'exclamation-triangle':'info-circle'}"></i> ${msg}`;
  wrap.appendChild(t);
  setTimeout(() => { t.style.opacity='0'; t.style.transition='opacity .4s'; setTimeout(()=>t.remove(),400); }, 3500);
}

// ── Edit Course Title Modal ───────────────────────────────────────────────────
function openEditTitleModal(currentTitle) {
  document.getElementById('editTitleInput').value = currentTitle;
  document.getElementById('editTitleModal').classList.add('show');
  setTimeout(() => document.getElementById('editTitleInput').focus(), 100);
}
function closeEditTitleModal() {
  document.getElementById('editTitleModal').classList.remove('show');
}
document.getElementById('editTitleModal').addEventListener('click', function(e) {
  if (e.target === this) closeEditTitleModal();
});

function openRetakeModal(studentId, studentName) {
  document.getElementById('retakeStudentId').value = studentId;
  document.getElementById('retakeStudentName').textContent = studentName;
  document.getElementById('retakeModal').classList.add('show');
}
function closeRetakeModal() {
  document.getElementById('retakeModal').classList.remove('show');
}
document.getElementById('retakeModal').addEventListener('click', function(e) {
  if (e.target === this) closeRetakeModal();
});

document.addEventListener('DOMContentLoaded', () => {
  <?php if (isset($_GET['title_updated'])): ?>
  showToast('Course title updated successfully.', 'success');
  <?php endif; ?>
  <?php if (isset($_GET['retake_granted'])): ?>
  showToast('Course retake granted — student progress has been reset.', 'success');
  <?php endif; ?>

  const dz=document.getElementById('dropZone'), inp=document.getElementById('videoFile'), info=document.getElementById('fileInfo');
  if (dz) {
    dz.addEventListener('click', () => inp.click());
    dz.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('dragover'); });
    dz.addEventListener('dragleave', () => dz.classList.remove('dragover'));
    dz.addEventListener('drop', e => { e.preventDefault(); dz.classList.remove('dragover'); if(e.dataTransfer.files.length){inp.files=e.dataTransfer.files;showF(e.dataTransfer.files[0]);} });
    inp.addEventListener('change', e => { if(e.target.files.length) showF(e.target.files[0]); });
    function showF(f){ info.textContent='✔ '+f.name+' ('+(f.size/1024/1024).toFixed(2)+' MB)'; }
  }
  const form=document.getElementById('uploadVideoForm');
  if (form) {
    const bar=document.getElementById('progressBar'), pct=document.getElementById('progressPercent'),
          wrap=document.getElementById('uploadProgress'), btn=document.getElementById('uploadBtn'),
          spin=document.getElementById('uploadSpinner'), icon=document.getElementById('uploadIcon');
    form.addEventListener('submit', e => {
      e.preventDefault(); const fd=new FormData(form), xhr=new XMLHttpRequest();
      wrap.style.display='block'; btn.disabled=true; spin.classList.remove('d-none'); icon.classList.add('d-none');
      xhr.upload.addEventListener('progress', e => { if(e.lengthComputable){ const p=Math.round(e.loaded/e.total*100); bar.style.width=p+'%'; pct.textContent=p+'%'; } });
      xhr.addEventListener('load', () => { if(xhr.status===200){ try{ const r=JSON.parse(xhr.responseText); if(r.success){ showToast('Video uploaded!'); window.location.reload(); }else{ alert('Error: '+r.message); reset(); } }catch(e){ window.location.reload(); } }else{ alert('Failed.'); reset(); } });
      xhr.addEventListener('error', () => { alert('Failed.'); reset(); });
      xhr.open('POST','upload_video_file.php',true); xhr.send(fd);
      function reset(){ btn.disabled=false; spin.classList.add('d-none'); icon.classList.remove('d-none'); wrap.style.display='none'; bar.style.width='0%'; pct.textContent='0%'; }
    });
  }
});

function updateCountdowns() {
  document.querySelectorAll('.lrow-countdown[data-ts]').forEach(el => {
    const diff=parseInt(el.dataset.ts)*1000-Date.now(), txt=el.querySelector('.cd-text');
    if(!txt)return;
    if(diff<=0){txt.textContent='Starting now!';return;}
    const d=Math.floor(diff/86400000),h=Math.floor(diff%86400000/3600000),
          m=Math.floor(diff%3600000/60000),s=Math.floor(diff%60000/1000);
    txt.textContent=d>0?`${d}d ${h}h ${m}m`:h>0?`${h}h ${m}m ${s}s`:m>0?`${m}m ${s}s`:`${s}s`;
  });
}
updateCountdowns();
setInterval(updateCountdowns, 1000);

function escHtml(str) {
  if (!str) return '';
  return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function loadQuizResults(quizId, quizTitle) {
  const panel = document.getElementById('quizResultsPanel');
  const body  = document.getElementById('quizResultsBody');
  const title = document.getElementById('quizResultsTitle');
  const sub   = document.getElementById('quizResultsSubtitle');

  panel.style.display = 'block';
  title.textContent   = quizTitle + ' — Results';
  sub.textContent     = 'Loading…';
  body.innerHTML      = '<div style="text-align:center;padding:40px;color:#aaa;"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';
  panel.scrollIntoView({ behavior: 'smooth', block: 'start' });

  fetch('get_quiz_results.php?quiz_id=' + quizId)
    .then(r => r.json())
    .then(data => {
      if (data.error) { body.innerHTML = `<div style="padding:20px;color:#dc2626;font-weight:600;">${data.error}</div>`; return; }
      const questions = data.questions, attempts = data.attempts, answers = data.answers;
      sub.textContent = attempts.length + ' student' + (attempts.length !== 1 ? 's' : '') + ' attempted';
      if (attempts.length === 0) { body.innerHTML = '<div style="text-align:center;padding:40px;color:#aaa;">No students have attempted this quiz yet.</div>'; return; }
      const stateStyles = {
        correct_picked:  { bg:'#d4edda', border:'#28a745', text:'#155724', badgeBg:'#28a745', badgeLabel:'Your Answer ✓' },
        wrong_picked:    { bg:'#f8d7da', border:'#dc3545', text:'#721c24', badgeBg:'#dc3545', badgeLabel:'Wrong Answer'   },
        missed_correct:  { bg:'#e8f5e9', border:'#28a745', text:'#2e7d32', badgeBg:'#28a745', badgeLabel:'Correct Answer' },
        skipped_correct: { bg:'#e8f5e9', border:'#28a745', text:'#2e7d32', badgeBg:'#28a745', badgeLabel:'Correct Answer' },
        neutral:         { bg:'#fff',    border:'#e9ecef', text:'#333',    badgeBg:null,       badgeLabel:null             }
      };
      let html = '';
      attempts.forEach(att => {
        const uid=att.user_id, scorePct=att.total_questions>0?Math.round((att.score/att.total_questions)*100):0, userAnsw=answers[uid]||{};
        const pColor=scorePct>=90?'#b8860b':scorePct>=75?'#16a34a':scorePct>=60?'#0284c7':scorePct>=40?'#d97706':'#dc2626';
        const pLabel=scorePct>=90?'Outstanding':scorePct>=75?'Excellent':scorePct>=60?'Good':scorePct>=40?'Average':'Poor';
        html += `<div style="border:1.5px solid #e5e7eb;border-radius:12px;overflow:hidden;margin:16px;">
          <div style="padding:16px 20px;background:linear-gradient(135deg,#f8f6ff,#f0ebff);border-bottom:1px solid #e9d8fd;display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
            <div style="width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,#5f259f,#ff6b00);display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:800;color:#fff;flex-shrink:0;">${escHtml((att.name||'S')[0].toUpperCase())}</div>
            <div style="flex:1;min-width:0;"><div style="font-weight:700;font-size:14px;">${escHtml(att.name||'Unknown')}</div><div style="font-size:12px;color:#888;">${escHtml(att.email)}</div></div>
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
              <div style="text-align:center;"><div style="font-size:24px;font-weight:800;color:${pColor};line-height:1;">${scorePct}%</div><div style="font-size:11px;color:#888;">${att.score}/${att.total_questions} correct</div></div>
              <span style="background:${pColor}20;color:${pColor};font-size:12px;font-weight:700;padding:4px 12px;border-radius:20px;">${pLabel}</span>
              <span style="background:${att.passed?'#dcfce7':'#fee2e2'};color:${att.passed?'#16a34a':'#dc3545'};font-size:12px;font-weight:700;padding:4px 12px;border-radius:20px;">${att.passed?'✓ Passed':'✗ Failed'}</span>
              <span style="font-size:11px;color:#aaa;">${new Date(att.attempted_at).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'})}</span>
            </div>
          </div>
          <div style="padding:16px 20px;">`;
        questions.forEach((q,idx) => {
          const correct=q.correct_option.toUpperCase(), picked=userAnsw[q.id]?userAnsw[q.id].toUpperCase():'', skipped=picked==='', gotRight=picked===correct;
          const qBg=skipped?'#f0f0f0':gotRight?'#d4edda':'#f8d7da', qColor=skipped?'#6c757d':gotRight?'#155724':'#721c24', qText=skipped?'— Skipped':gotRight?'✓ Correct':'✗ Wrong';
          html += `<div style="margin-bottom:12px;border:1px solid #f0f0f0;border-radius:10px;overflow:hidden;">
            <div style="padding:10px 14px;background:#fafafa;border-bottom:1px solid #f0f0f0;display:flex;align-items:flex-start;gap:10px;">
              <span style="width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,#5f259f,#ff6b00);color:#fff;font-size:12px;font-weight:700;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;">${idx+1}</span>
              <span style="font-size:13.5px;font-weight:600;flex:1;line-height:1.5;">${escHtml(q.question)}</span>
              <span style="background:${qBg};color:${qColor};font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;white-space:nowrap;flex-shrink:0;">${qText}</span>
            </div>
            <div style="padding:10px 14px;display:flex;flex-direction:column;gap:6px;">`;
          ['A','B','C','D'].forEach(opt => {
            const text=q['option_'+opt.toLowerCase()], isCorrect=opt===correct, isPicked=opt===picked;
            let state;
            if(skipped&&isCorrect) state='skipped_correct';
            else if(!skipped&&isPicked&&isCorrect) state='correct_picked';
            else if(!skipped&&isPicked&&!isCorrect) state='wrong_picked';
            else if(!skipped&&!isPicked&&isCorrect) state='missed_correct';
            else state='neutral';
            const s=stateStyles[state];
            html+=`<div style="display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:8px;border:2px solid ${s.border};background:${s.bg};color:${s.text};">
              <span style="width:26px;height:26px;border-radius:50%;background:${s.border};color:#fff;font-size:12px;font-weight:700;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;">${opt}</span>
              <span style="font-size:13px;flex:1;">${escHtml(text)}</span>
              ${s.badgeBg?`<span style="background:${s.badgeBg};color:#fff;font-size:11px;font-weight:700;padding:2px 9px;border-radius:20px;white-space:nowrap;">${s.badgeLabel}</span>`:''}
            </div>`;
          });
          html += `</div></div>`;
        });
        html += `</div></div>`;
      });
      body.innerHTML = html;
    })
    .catch(() => { body.innerHTML = '<div style="padding:20px;color:#dc2626;font-weight:600;">Failed to load results. Please try again.</div>'; });
}
</script>
</body>
</html>