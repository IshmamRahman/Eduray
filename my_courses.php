<?php
define('EDURAY_INCLUDED', true);
session_start();
require __DIR__ . '/db.php';

$userId = $_SESSION['user_id'] ?? null;
$userName = $_SESSION['name'] ?? null;
$userMail = $_SESSION['email'] ?? null;
$isAdmin = !empty($_SESSION['is_admin']);

if (!$userId) { header('Location: login.php'); exit; }
if ($isAdmin) { header('Location: admin_dashboard.php'); exit; }

define('CERT_QUIZ_THRESHOLD', 70);

$unreadNotes = 0;
$recentNotes = [];
$notesRes = $conn->prepare("
    SELECT n.id, n.message, n.is_read, n.created_at,
           u.name AS instructor_name, c.title AS course_title
    FROM instructor_notes n
    JOIN users u ON u.id = n.instructor_id
    JOIN courses c ON c.id = n.course_id
    WHERE n.student_id = ?
    ORDER BY n.created_at DESC
    LIMIT 5
");
$notesRes->bind_param("i", $userId);
$notesRes->execute();
$recentNotes = $notesRes->get_result()->fetch_all(MYSQLI_ASSOC);
$notesRes->close();
foreach ($recentNotes as $n) { if (!$n['is_read']) $unreadNotes++; }

// ── Fetch enrolled courses ────────────────────────────────────────────────────
$enrolledCourses = [];
$sql = "
    SELECT c.*, e.enrolled_at, e.completed_at,
        (SELECT COUNT(*) FROM videos WHERE course_id = c.id) AS total_videos,
        (SELECT COUNT(*) FROM quizzes WHERE course_id = c.id) AS total_quizzes,
        (SELECT COUNT(*) FROM video_progress vp JOIN videos v ON vp.video_id = v.id
         WHERE v.course_id = c.id AND vp.user_id = ? AND vp.completed = 1) AS completed_videos,
        (SELECT COUNT(*) FROM quiz_attempts qa JOIN quizzes q ON qa.quiz_id = q.id
         WHERE q.course_id = c.id AND qa.user_id = ?) AS attempted_quizzes,
        (SELECT COUNT(*) FROM quiz_attempts qa JOIN quizzes q ON qa.quiz_id = q.id
         WHERE q.course_id = c.id AND qa.user_id = ? AND qa.passed = 1) AS passed_quizzes
    FROM courses c
    JOIN enrollments e ON c.id = e.course_id
    WHERE e.user_id = ?
    ORDER BY e.enrolled_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iiii", $userId, $userId, $userId, $userId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) { $enrolledCourses[] = $row; }
$stmt->close();

// ── Progress calculation ──────────────────────────────────────────────────────
foreach ($enrolledCourses as &$course) {
    $hasV = $course['total_videos'] > 0;
    $hasQ = $course['total_quizzes'] > 0;
    $videoPct = $hasV ? ($course['completed_videos'] / $course['total_videos']) * 100 : 100;
    $quizPct  = $hasQ ? ($course['attempted_quizzes'] / $course['total_quizzes']) * 100 : 100;

    if ($hasV && $hasQ) {
        $course['progress'] = round($videoPct * 0.5 + $quizPct * 0.5);
    } elseif ($hasV) {
        $course['progress'] = round($videoPct);
    } elseif ($hasQ) {
        $course['progress'] = round($quizPct);
    } else {
        $course['progress'] = 0;
    }
    $course['video_pct'] = round($videoPct);
    $course['quiz_pct']  = round($quizPct);
}
unset($course);

// ── Active course ─────────────────────────────────────────────────────────────
$activeCourseId  = isset($_GET['course_id']) ? intval($_GET['course_id']) : null;
$activeCourse    = null;
$courseVideos    = [];
$courseQuizzes   = [];
$liveclasses     = [];
$courseQuestions = [];
$quizAvg         = 0;
$certEligible    = false;
$courseComplete  = false;
$poorPerformance = false;

if ($activeCourseId && in_array($activeCourseId, array_column($enrolledCourses, 'id'))) {
    foreach ($enrolledCourses as $c) {
        if ($c['id'] == $activeCourseId) { $activeCourse = $c; break; }
    }

    // Videos
    $vstmt = $conn->prepare("
        SELECT v.id, v.title, v.thumb_url, v.duration, v.position, v.embed_url, v.video_path,
               COALESCE(vp.completed, 0) AS completed
        FROM videos v
        LEFT JOIN video_progress vp ON vp.video_id = v.id AND vp.user_id = ?
        WHERE v.course_id = ? ORDER BY v.position, v.id
    ");
    $vstmt->bind_param("ii", $userId, $activeCourseId);
    $vstmt->execute();
    $vr = $vstmt->get_result();
    while ($v = $vr->fetch_assoc()) $courseVideos[] = $v;
    $vstmt->close();

    // Quizzes
    $qstmt = $conn->prepare("
        SELECT q.id, q.title, qa.score, qa.total_questions, qa.passed, qa.attempted_at
        FROM quizzes q
        LEFT JOIN quiz_attempts qa ON qa.quiz_id = q.id AND qa.user_id = ?
        WHERE q.course_id = ? ORDER BY q.id
    ");
    $qstmt->bind_param("ii", $userId, $activeCourseId);
    $qstmt->execute();
    $qr = $qstmt->get_result();
    while ($q = $qr->fetch_assoc()) $courseQuizzes[] = $q;
    $qstmt->close();

    // Live classes
    $lstmt = $conn->prepare("
        SELECT id, title, description, meet_link, scheduled_at
        FROM live_classes WHERE course_id = ? ORDER BY scheduled_at ASC
    ");
    $lstmt->bind_param("i", $activeCourseId);
    $lstmt->execute();
    $lr = $lstmt->get_result();
    while ($l = $lr->fetch_assoc()) $liveclasses[] = $l;
    $lstmt->close();

    // Q&A
    $cqRes = $conn->prepare("
        SELECT q.id, q.question, q.created_at, q.student_id, u.name AS student_name,
               r.id AS reply_id, r.reply, r.created_at AS replied_at, ru.name AS instructor_name
        FROM course_questions q
        JOIN users u ON u.id = q.student_id
        LEFT JOIN course_question_replies r ON r.question_id = q.id
        LEFT JOIN users ru ON ru.id = r.instructor_id
        WHERE q.course_id = ? AND q.video_id IS NULL ORDER BY q.created_at ASC
    ");
    $cqRes->bind_param("i", $activeCourseId);
    $cqRes->execute();
    $courseQuestions = $cqRes->get_result()->fetch_all(MYSQLI_ASSOC);
    $cqRes->close();

    // ── Completion & eligibility ──────────────────────────────────────────────
    $allVideosComplete   = $activeCourse['total_videos']  === 0 || $activeCourse['completed_videos']  >= $activeCourse['total_videos'];
    $allQuizzesAttempted = $activeCourse['total_quizzes'] === 0 || $activeCourse['attempted_quizzes'] >= $activeCourse['total_quizzes'];

    if (!empty($courseQuizzes)) {
        $totalPct = 0; $count = 0;
        foreach ($courseQuizzes as $q) {
            if ($q['attempted_at'] !== null && $q['total_questions'] > 0) {
                $totalPct += round(($q['score'] / $q['total_questions']) * 100);
                $count++;
            }
        }
        $quizAvg = $count > 0 ? round($totalPct / $count) : 0;
    }

    $courseComplete  = $allVideosComplete && $allQuizzesAttempted;
    $certEligible    = $courseComplete && ($activeCourse['total_quizzes'] === 0 || $quizAvg >= CERT_QUIZ_THRESHOLD);
    $poorPerformance = $courseComplete && $activeCourse['total_quizzes'] > 0 && $quizAvg < CERT_QUIZ_THRESHOLD;

    // ── Auto-set completed_at when course is fully done ───────────────────────
    // Only write once (when completed_at is still NULL) and only when truly complete
    if ($courseComplete && empty($activeCourse['completed_at'])) {
        $upd = $conn->prepare("
            UPDATE enrollments
            SET completed_at = NOW()
            WHERE user_id = ? AND course_id = ? AND completed_at IS NULL
        ");
        $upd->bind_param("ii", $userId, $activeCourseId);
        $upd->execute();
        $upd->close();
        // Refresh the value so the banner below shows correctly
        $activeCourse['completed_at'] = date('Y-m-d H:i:s');
    }

    $activeCourse['quiz_avg']              = $quizAvg;
    $activeCourse['cert_eligible']         = $certEligible;
    $activeCourse['poor_performance']      = $poorPerformance;
    $activeCourse['all_videos_complete']   = $allVideosComplete;
    $activeCourse['all_quizzes_attempted'] = $allQuizzesAttempted;
}

function getPerformanceLabel(int $pct): array {
    if ($pct >= 90) return ['label' => 'Outstanding', 'color' => '#f7971e'];
    if ($pct >= 75) return ['label' => 'Excellent',   'color' => '#28a745'];
    if ($pct >= 60) return ['label' => 'Good',        'color' => '#17a2b8'];
    if ($pct >= 40) return ['label' => 'Average',     'color' => '#fd7e14'];
    if ($pct >= 20) return ['label' => 'Poor',        'color' => '#dc3545'];
    return                 ['label' => 'Very Poor',   'color' => '#6c757d'];
}

function formatDuration($s) {
    if (!$s) return '0:00';
    return floor($s / 60) . ':' . str_pad($s % 60, 2, '0', STR_PAD_LEFT);
}

$retaken = isset($_GET['retaken']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>EDURAY – My Courses</title>
    <?php include 'includes/head.php'; ?>
    <style>
        .dashboard-layout {
            display: flex;
            min-height: calc(100vh - 200px);
        }

        /* ===== Sidebar ===== */
        .course-sidebar {
            width: 350px;
            background: white;
            border-right: 1px solid var(--border);
            overflow-y: auto;
            max-height: calc(100vh - 80px);
            position: sticky;
            top: 80px;
            box-shadow: var(--shadow-sm);
        }
        .sidebar-header {
            padding: 25px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
        }
        .sidebar-header h5 { font-weight: 700; margin: 0; font-size: 18px; }
        .sidebar-header small { opacity: 0.9; font-size: 13px; }
        .course-list { padding: 15px; }
        .course-list-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.25s;
            text-decoration: none;
            color: inherit;
            border: 2px solid transparent;
        }
        .course-list-item:hover { background: #f8f9fa; transform: translateX(5px); box-shadow: var(--shadow-sm); }
        .course-list-item.active { background: rgba(95,37,159,.08); border-color: var(--primary); }
        .course-list-thumb { width: 60px; height: 45px; object-fit: cover; border-radius: 8px; margin-right: 12px; }
        .course-list-info { flex: 1; }
        .course-list-title { font-weight: 600; font-size: 14px; margin-bottom: 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 200px; }
        .course-list-progress { font-size: 12px; color: var(--text-muted); }
        .progress-sm { height: 5px; margin-top: 8px; background: #e9ecef; border-radius: 10px; overflow: hidden; }
        .progress-sm .progress-bar { background: linear-gradient(135deg, var(--primary), var(--accent)); border-radius: 10px; }

        /* ===== Content Area ===== */
        .content-area { flex: 1; padding: 30px; overflow-y: auto; }

        /* ===== Empty State ===== */
        .empty-state { text-align: center; padding: 100px 20px; animation: fadeIn 0.6s ease; }
        .empty-state i { font-size: 80px; color: #ddd; margin-bottom: 25px; }
        .empty-state h3 { color: #666; margin-bottom: 15px; font-weight: 700; }
        .btn-browse {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white; padding: 14px 35px; border-radius: 30px;
            text-decoration: none; display: inline-block; font-weight: 600;
            font-size: 16px; transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn-browse:hover { color: white; transform: translateY(-3px); box-shadow: var(--shadow-md); }

        /* ===== Cards ===== */
        .content-card {
            background: white; border-radius: 16px; padding: 25px;
            margin-bottom: 25px; box-shadow: var(--shadow-sm);
            border: 1px solid var(--border); animation: fadeIn 0.6s ease;
        }
        .section-title {
            font-size: 20px; font-weight: 700; color: var(--primary);
            margin-bottom: 25px; display: flex; align-items: center;
            gap: 12px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0;
        }

        /* ===== Status Banners ===== */
        .status-banner {
            border-radius: 16px; padding: 25px; margin-bottom: 25px;
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 20px; animation: fadeIn 0.6s ease;
        }
        .status-banner.eligible {
            background: linear-gradient(135deg, var(--gold), #ffaa00);
            box-shadow: 0 8px 25px rgba(255,170,0,.35);
        }
        .status-banner.poor {
            background: linear-gradient(135deg, #c0392b, #e74c3c);
            box-shadow: 0 8px 25px rgba(231,76,60,.35); color: white;
        }
        .status-banner.requirements {
            background: white; border: 2px solid var(--border); box-shadow: var(--shadow-sm);
        }
        .banner-icon { font-size: 36px; color: inherit; }
        .banner-body h4 { margin: 0 0 6px; font-weight: 700; font-size: 18px; }
        .banner-body p { margin: 0; font-size: 14px; opacity: 0.9; }
        .banner-left { display: flex; align-items: center; gap: 16px; }
        .req-list { display: flex; flex-direction: column; gap: 8px; margin-top: 10px; }
        .req-item { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #555; }
        .req-item i.ok { color: #28a745; }
        .req-item i.bad { color: #dc3545; }

        .btn-cert {
            background: #222; color: var(--gold); border: none;
            padding: 12px 25px; border-radius: 25px; font-weight: 700;
            text-decoration: none; display: inline-flex; align-items: center;
            gap: 8px; transition: all 0.2s;
        }
        .btn-cert:hover { transform: scale(1.05); color: var(--gold); }
        .btn-retake {
            background: white; color: #c0392b; border: none;
            padding: 12px 25px; border-radius: 25px; font-weight: 700;
            display: inline-flex; align-items: center; gap: 8px;
            transition: all 0.2s; cursor: pointer;
        }
        .btn-retake:hover { background: #fff0f0; transform: scale(1.05); }

        .retaken-alert {
            background: #d4edda; border: 2px solid #28a745; border-radius: 12px;
            padding: 15px 20px; margin-bottom: 20px; display: flex;
            align-items: center; gap: 12px; color: #155724;
            font-weight: 600; animation: fadeIn 0.6s ease;
        }

        /* ===== Course Header ===== */
        .course-header h2 { color: var(--primary); font-weight: 700; margin-bottom: 12px; font-size: 28px; }
        .course-stats { display: flex; gap: 25px; color: var(--text-muted); font-size: 14px; flex-wrap: wrap; margin-bottom: 20px; }
        .course-stats span { display: flex; align-items: center; gap: 8px; }
        .course-stats i { color: var(--accent); }
        .progress-breakdown { display: flex; gap: 12px; flex-wrap: wrap; margin: 15px 0; }
        .prog-pill { display: flex; align-items: center; gap: 8px; background: #f8f9fa; border-radius: 30px; padding: 8px 15px; font-size: 13px; font-weight: 600; }
        .prog-dot { width: 10px; height: 10px; border-radius: 50%; }

        /* ===== Videos ===== */
        .video-item {
            display: flex; align-items: center; padding: 18px;
            border-radius: 12px; margin-bottom: 15px; border: 2px solid #e9ecef;
            transition: all 0.3s; background: white;
        }
        .video-item:hover { border-color: var(--primary); transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .video-item.done { border-color: #28a745; background: #f8fff9; }
        .video-thumb { width: 140px; height: 80px; object-fit: cover; border-radius: 10px; margin-right: 18px; }
        .video-info { flex: 1; }
        .video-title { font-weight: 700; font-size: 16px; margin-bottom: 8px; color: var(--text); }
        .video-meta { font-size: 13px; color: #888; display: flex; gap: 20px; }
        .badge-done {
            background: #28a745; color: white; border-radius: 20px;
            padding: 4px 12px; font-size: 12px; font-weight: 600;
            display: inline-flex; align-items: center; gap: 6px; margin-left: 10px;
        }
        .btn-watch {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white; border: none; padding: 10px 22px; border-radius: 25px;
            font-weight: 600; text-decoration: none; display: inline-flex;
            align-items: center; gap: 8px; transition: all 0.3s;
        }
        .btn-watch:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); color: white; }

        /* ===== Quizzes ===== */
        .quiz-item {
            display: flex; justify-content: space-between; align-items: center;
            padding: 18px 20px; border-radius: 12px; margin-bottom: 15px;
            transition: all 0.3s; border-left: 4px solid var(--accent);
            background: linear-gradient(135deg, #f8f9fa, #f0f0f0);
        }
        .quiz-item:hover { transform: translateX(5px); box-shadow: var(--shadow-sm); }
        .quiz-item.attempted { border-left-color: var(--primary); background: linear-gradient(135deg, #f4f0ff, #ede8fa); }
        .quiz-info { display: flex; align-items: center; gap: 15px; }
        .quiz-icon {
            width: 48px; height: 48px; border-radius: 12px; display: flex;
            align-items: center; justify-content: center; color: white; font-size: 20px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
        }
        .quiz-name { font-weight: 700; font-size: 15px; color: var(--text); }
        .quiz-status { font-size: 13px; margin-top: 4px; }
        .btn-quiz-start, .btn-quiz-review {
            background: white; color: var(--primary); border: 2px solid var(--primary);
            padding: 10px 22px; border-radius: 25px; font-weight: 600;
            text-decoration: none; transition: all 0.3s;
        }
        .btn-quiz-start:hover, .btn-quiz-review:hover { background: var(--primary); color: white; }

        /* ===== Help Chat ===== */
        .help-chat-section {
            background: white; border-radius: 16px; padding: 25px;
            margin-bottom: 25px; box-shadow: var(--shadow-sm); border: 1px solid var(--border);
        }
        .help-ask-form { background: #faf8ff; border: 1.5px solid #e9d8fd; border-radius: 12px; padding: 20px; margin-bottom: 20px; }
        .help-ask-form textarea {
            width: 100%; background: white; border: 1.5px solid #e0d5f5;
            border-radius: 10px; padding: 12px; color: var(--text);
            font-size: 14px; font-family: 'Poppins', sans-serif;
            resize: vertical; min-height: 80px; outline: none;
        }
        .help-ask-form textarea:focus { border-color: var(--primary); }
        .help-submit-btn {
            margin-top: 12px; padding: 10px 25px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white; border: none; border-radius: 25px; font-size: 14px;
            font-weight: 600; cursor: pointer; display: inline-flex;
            align-items: center; gap: 8px; transition: all 0.2s;
        }
        .help-submit-btn:hover { transform: translateY(-2px); box-shadow: var(--shadow-sm); }
        .hq-list { display: flex; flex-direction: column; gap: 15px; }
        .hq-item { border: 1px solid #ede8fb; border-radius: 12px; overflow: hidden; }
        .hq-question { padding: 18px; background: #fdfbff; }
        .hq-meta { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
        .hq-avatar {
            width: 32px; height: 32px; border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; font-weight: 700; color: white;
        }
        .hq-name { font-size: 13px; font-weight: 600; color: #444; }
        .hq-time { font-size: 12px; color: #bbb; margin-left: auto; }
        .hq-text { font-size: 14px; color: var(--text); line-height: 1.6; }
        .hq-reply { padding: 15px 18px; background: #f5f0ff; border-top: 1px solid #ede8fb; }
        .hq-reply-label { font-size: 12px; font-weight: 700; color: var(--primary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
        .hq-reply-text { font-size: 14px; color: #444; line-height: 1.6; }
        .hq-no-reply { font-size: 13px; color: #aaa; font-style: italic; }
        .hq-success {
            background: #f0fdf4; border: 1px solid #86efac; color: #16a34a;
            border-radius: 10px; padding: 12px 16px; font-size: 14px;
            font-weight: 600; margin-bottom: 15px; display: flex; align-items: center; gap: 10px;
        }

        /* ===== Live Classes ===== */
        .live-card {
            display: flex; align-items: center; gap: 18px; padding: 20px;
            border-radius: 12px; border: 1.5px solid var(--border);
            background: white; margin-bottom: 15px; transition: all 0.25s;
        }
        .live-card-upcoming { border-left: 4px solid #00b894; }
        .live-card-upcoming:hover { background: #f0fff9; border-color: #00b894; }
        .live-card-live-now { border-left: 4px solid #e17055; background: #fff8f5; animation: cardPulse 2s ease-in-out infinite; }
        @keyframes cardPulse { 0%,100%{box-shadow:0 0 0 0 rgba(225,112,85,.1)} 50%{box-shadow:0 0 0 8px rgba(225,112,85,.1)} }
        .live-card-finished { border-left: 4px solid #ddd; opacity: 0.72; background: #fafafa; }
        .live-dot { width: 14px; height: 14px; border-radius: 50%; flex-shrink: 0; }
        .live-dot-upcoming { background: #00b894; }
        .live-dot-live-now { background: #e17055; animation: dotBlink 1.1s infinite; box-shadow: 0 0 0 4px rgba(225,112,85,.2); }
        @keyframes dotBlink { 0%,100%{opacity:1} 50%{opacity:.25} }
        .live-dot-finished { background: #ccc; }
        .live-info { flex: 1; }
        .live-card-top { display: flex; align-items: center; gap: 12px; margin-bottom: 6px; }
        .live-card-title { font-weight: 700; font-size: 16px; color: var(--text); }
        .live-card-desc { font-size: 14px; color: #666; margin-bottom: 8px; }
        .live-card-meta { display: flex; gap: 20px; font-size: 13px; color: #888; }
        .live-status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; }
        .live-status-live-now { background: #fff0eb; color: #e17055; }
        .live-status-upcoming { background: #e6fff5; color: #00b894; }
        .live-status-finished { background: #f0f0f0; color: #999; }
        .btn-join-live {
            display: inline-flex; align-items: center; gap: 8px; padding: 12px 25px;
            border-radius: 25px; background: linear-gradient(135deg, #e17055, #d63031);
            color: white; font-weight: 700; text-decoration: none;
            animation: btnPulse 1.5s ease-in-out infinite;
        }
        @keyframes btnPulse { 0%,100%{box-shadow:0 4px 14px rgba(225,112,85,.4)} 50%{box-shadow:0 4px 22px rgba(225,112,85,.7)} }
        .btn-live-waiting { padding: 12px 20px; border-radius: 25px; background: #e6fff5; color: #00b894; font-weight: 700; }
        .btn-live-finished { padding: 12px 20px; border-radius: 25px; background: #f0f0f0; color: #999; font-weight: 600; }

        /* ===== Instructor Notes ===== */
        .notes-section { background: #fdf8ff; border: 1.5px solid #e9d8fd; border-radius: 16px; padding: 25px; margin-bottom: 25px; }
        .note-item { background: white; border: 1px solid #e9d8fd; border-radius: 12px; padding: 18px; margin-bottom: 12px; }
        .note-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
        .note-badge { font-size: 12px; font-weight: 700; color: var(--primary); background: #f0ebff; padding: 4px 12px; border-radius: 20px; }
        .note-time { font-size: 12px; color: #bbb; }
        .note-text { font-size: 15px; color: #2d2d3a; line-height: 1.7; }

        @media (max-width:991.98px) {
            .dashboard-layout { flex-direction: column; }
            .course-sidebar { width: 100%; max-height: 350px; position: relative; top: 0; }
            .content-area { padding: 20px; }
            .video-thumb { width: 100px; height: 60px; }
            .status-banner { flex-direction: column; text-align: center; }
            .banner-left { flex-direction: column; }
        }
    </style>
</head>
<body>

<?php include 'includes/navbar.php'; ?>

<div class="dashboard-layout">
    <!-- Sidebar -->
    <aside class="course-sidebar">
        <div class="sidebar-header">
            <h5><i class="fas fa-graduation-cap me-2"></i>My Courses</h5>
            <small><?= count($enrolledCourses) ?> enrolled</small>
        </div>
        <div class="course-list">
            <?php if (empty($enrolledCourses)): ?>
                <div class="text-center p-4 text-muted">
                    <i class="fas fa-book-open fa-2x mb-2"></i>
                    <p>No courses yet</p>
                </div>
            <?php else: ?>
                <?php foreach ($enrolledCourses as $c): ?>
                    <a href="?course_id=<?= $c['id'] ?>" class="course-list-item <?= $activeCourseId == $c['id'] ? 'active' : '' ?>">
                        <img src="<?= htmlspecialchars($c['thumbnail_url'] ?: 'https://via.placeholder.com/60x40?text=Course') ?>" class="course-list-thumb" alt="">
                        <div class="course-list-info">
                            <div class="course-list-title"><?= htmlspecialchars($c['title']) ?></div>
                            <div class="course-list-progress"><?= $c['progress'] ?>% complete</div>
                            <div class="progress progress-sm">
                                <div class="progress-bar" style="width:<?= $c['progress'] ?>%"></div>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="content-area">
        <?php if (!$activeCourse): ?>
            <div class="empty-state">
                <i class="fas fa-graduation-cap"></i>
                <h3><?= empty($enrolledCourses) ? 'Start Your Learning Journey' : 'Select a Course to Continue' ?></h3>
                <p class="text-muted mb-4">
                    <?= empty($enrolledCourses)
                        ? "You haven't enrolled in any courses yet. Browse our catalog to get started!"
                        : 'Choose a course from the sidebar to view videos and quizzes.' ?>
                </p>
                <?php if (empty($enrolledCourses)): ?>
                    <a href="courses.php" class="btn-browse">Browse Courses</a>
                <?php endif; ?>
            </div>

        <?php else: ?>

            <!-- Retaken Alert -->
            <?php if ($retaken): ?>
                <div class="retaken-alert">
                    <i class="fas fa-check-circle fa-lg"></i>
                    Course progress has been reset. You can now start fresh — good luck!
                </div>
            <?php endif; ?>

            <!-- Certificate Banner -->
            <?php if ($certEligible): ?>
                <div class="status-banner eligible">
                    <div class="banner-left">
                        <div class="banner-icon">
                            <i class="fas fa-trophy" style="color:#222;font-size:36px;"></i>
                        </div>
                        <div class="banner-body">
                            <h4>Congratulations! Course Complete!</h4>
                            <p>You scored a <?= $quizAvg ?>% quiz average. Your certificate is ready!</p>
                        </div>
                    </div>
                    <a href="certificate.php?course_id=<?= $activeCourse['id'] ?>" class="btn-cert">
                        <i class="fas fa-certificate"></i> Download Certificate
                    </a>
                </div>

            <?php elseif ($poorPerformance): ?>
                <div class="status-banner poor">
                    <div class="banner-left">
                        <div class="banner-icon">
                            <i class="fas fa-exclamation-circle" style="font-size:36px;"></i>
                        </div>
                        <div class="banner-body">
                            <h4>Course Complete — Score Too Low</h4>
                            <p>Your quiz average is <?= $quizAvg ?>%, below the <?= CERT_QUIZ_THRESHOLD ?>% required for a certificate. Retake the course to improve your score.</p>
                        </div>
                    </div>
                    <form method="POST" action="retake_course.php" onsubmit="return confirm('This will reset ALL your progress for this course. Continue?')">
                        <input type="hidden" name="course_id" value="<?= $activeCourse['id'] ?>">
                        <button type="submit" class="btn-retake">
                            <i class="fas fa-redo"></i> Retake Course
                        </button>
                    </form>
                </div>

            <?php elseif ($activeCourse['progress'] > 0): ?>
                <div class="status-banner requirements">
                    <div class="banner-left">
                        <div class="banner-icon">
                            <i class="fas fa-graduation-cap" style="color:#aaa;font-size:36px;"></i>
                        </div>
                        <div class="banner-body">
                            <h4 style="color:#555;">Certificate Requirements</h4>
                            <div class="req-list">
                                <div class="req-item">
                                    <i class="fas <?= $activeCourse['all_videos_complete'] ? 'fa-check-circle ok' : 'fa-circle bad' ?>"></i>
                                    Watch all videos (<?= $activeCourse['completed_videos'] ?>/<?= $activeCourse['total_videos'] ?>)
                                </div>
                                <?php if ($activeCourse['total_quizzes'] > 0): ?>
                                <div class="req-item">
                                    <i class="fas <?= $activeCourse['all_quizzes_attempted'] ? 'fa-check-circle ok' : 'fa-circle bad' ?>"></i>
                                    Attempt all quizzes (<?= $activeCourse['attempted_quizzes'] ?>/<?= $activeCourse['total_quizzes'] ?>)
                                </div>
                                <div class="req-item">
                                    <i class="fas <?= $quizAvg >= CERT_QUIZ_THRESHOLD ? 'fa-check-circle ok' : 'fa-circle bad' ?>"></i>
                                    Quiz average &ge; <?= CERT_QUIZ_THRESHOLD ?>%
                                    <?= $activeCourse['attempted_quizzes'] > 0 ? "(yours: {$quizAvg}%)" : '' ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Course Header -->
            <div class="content-card course-header">
                <h2><?= htmlspecialchars($activeCourse['title']) ?></h2>
                <div class="course-stats">
                    <span><i class="fas fa-video"></i> <?= count($courseVideos) ?> Videos</span>
                    <span><i class="fas fa-question-circle"></i> <?= count($courseQuizzes) ?> Quizzes</span>
                    <span><i class="fas fa-chart-line"></i> <?= $activeCourse['progress'] ?>% Overall</span>
                    <span><i class="fas fa-calendar"></i> Enrolled <?= date('M d, Y', strtotime($activeCourse['enrolled_at'])) ?></span>
                    <?php if (!empty($activeCourse['completed_at'])): ?>
                    <span><i class="fas fa-check-circle" style="color:#28a745;"></i> Completed <?= date('M d, Y', strtotime($activeCourse['completed_at'])) ?></span>
                    <?php endif; ?>
                </div>
                <div class="progress-breakdown">
                    <div class="prog-pill">
                        <div class="prog-dot" style="background:var(--primary)"></div>
                        Videos <?= $activeCourse['video_pct'] ?>%
                    </div>
                    <?php if ($activeCourse['total_quizzes'] > 0): ?>
                    <div class="prog-pill">
                        <div class="prog-dot" style="background:var(--accent)"></div>
                        Quizzes <?= $activeCourse['quiz_pct'] ?>%
                    </div>
                    <?php if ($activeCourse['attempted_quizzes'] > 0): ?>
                    <div class="prog-pill">
                        <div class="prog-dot" style="background:<?= $quizAvg >= CERT_QUIZ_THRESHOLD ? '#28a745' : '#dc3545' ?>"></div>
                        Quiz Avg: <?= $quizAvg ?>%
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
                <div class="progress mt-3" style="height:10px;border-radius:10px;">
                    <div class="progress-bar" style="width:<?= $activeCourse['progress'] ?>%;background:linear-gradient(135deg,var(--primary),var(--accent));border-radius:10px;"></div>
                </div>
            </div>

            <!-- Videos -->
            <div class="content-card">
                <h3 class="section-title"><i class="fas fa-play-circle"></i>Course Videos</h3>
                <?php if (empty($courseVideos)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-video-slash fa-3x mb-3"></i>
                        <p>No videos available yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($courseVideos as $i => $video): ?>
                        <div class="video-item <?= $video['completed'] ? 'done' : '' ?>">
                            <img src="<?= htmlspecialchars($video['thumb_url'] ?: 'https://via.placeholder.com/150x85?text=Video') ?>" class="video-thumb" alt="">
                            <div class="video-info">
                                <div class="video-title">
                                    <?= $i + 1 ?>. <?= htmlspecialchars($video['title']) ?>
                                    <?php if ($video['completed']): ?>
                                        <span class="badge-done"><i class="fas fa-check"></i> Completed</span>
                                    <?php endif; ?>
                                </div>
                                <div class="video-meta">
                                    <span><i class="far fa-clock"></i> <?= formatDuration($video['duration']) ?></span>
                                    <span><i class="fas fa-play-circle"></i> Video <?= $i+1 ?> of <?= count($courseVideos) ?></span>
                                </div>
                            </div>
                            <a href="watch.php?video_id=<?= $video['id'] ?>" class="btn-watch">
                                <i class="fas fa-<?= $video['completed'] ? 'redo' : 'play' ?>"></i>
                                <?= $video['completed'] ? 'Rewatch' : 'Watch Now' ?>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Quizzes -->
            <?php if (!empty($courseQuizzes)): ?>
                <div class="content-card">
                    <h3 class="section-title"><i class="fas fa-question-circle"></i>Course Quizzes</h3>
                    <?php foreach ($courseQuizzes as $quiz): ?>
                        <?php
                            $attempted = $quiz['attempted_at'] !== null;
                            $scorePct  = $attempted && $quiz['total_questions'] > 0
                                ? round(($quiz['score'] / $quiz['total_questions']) * 100) : null;
                            $perf = $scorePct !== null ? getPerformanceLabel($scorePct) : null;
                        ?>
                        <div class="quiz-item <?= $attempted ? 'attempted' : '' ?>">
                            <div class="quiz-info">
                                <div class="quiz-icon">
                                    <i class="fas fa-<?= $attempted ? 'check-double' : 'clipboard-check' ?>"></i>
                                </div>
                                <div>
                                    <div class="quiz-name"><?= htmlspecialchars($quiz['title']) ?></div>
                                    <div class="quiz-status">
                                        <?php if ($attempted): ?>
                                            <span style="color:<?= $perf['color'] ?>;font-weight:700;">
                                                <i class="fas fa-chart-bar me-1"></i><?= $perf['label'] ?> — <?= $scorePct ?>%
                                            </span>
                                            <span class="text-muted ms-2" style="font-size:12px;">
                                                <?= $quiz['score'] ?>/<?= $quiz['total_questions'] ?> correct
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">Not attempted yet</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php if ($attempted): ?>
                                <a href="attempt_quiz.php?quiz_id=<?= $quiz['id'] ?>" class="btn-quiz-review">
                                    <i class="fas fa-eye"></i> Review
                                </a>
                            <?php else: ?>
                                <a href="attempt_quiz.php?quiz_id=<?= $quiz['id'] ?>" class="btn-quiz-start">
                                    Start Quiz <i class="fas fa-arrow-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Instructor Notes -->
            <?php if (!empty($recentNotes)): ?>
                <div class="notes-section">
                    <h3 class="section-title" style="color:var(--primary);border-color:#e9d8fd;">
                        <i class="fas fa-envelope"></i>Notes from Instructor
                    </h3>
                    <?php foreach ($recentNotes as $note): ?>
                        <div class="note-item" style="<?= !$note['is_read'] ? 'border-left:4px solid var(--primary);' : '' ?>">
                            <div class="note-header">
                                <span class="note-badge">
                                    <i class="fas fa-chalkboard-teacher me-1"></i><?= htmlspecialchars($note['instructor_name']) ?>
                                </span>
                                <span class="note-time">
                                    <?= date('M d, Y', strtotime($note['created_at'])) ?>
                                    <?php if (!$note['is_read']): ?>
                                        <span style="background:var(--primary);color:white;font-size:10px;padding:2px 8px;border-radius:10px;margin-left:8px;">New</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="note-text"><?= nl2br(htmlspecialchars($note['message'])) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Help Chat -->
            <div class="help-chat-section">
                <h3 class="section-title">
                    <i class="fas fa-comments" style="color:var(--primary)"></i>Help Chat
                </h3>
                <?php if (isset($_GET['question_sent'])): ?>
                    <div class="hq-success"><i class="fas fa-check-circle"></i> Question sent! Instructor will reply soon.</div>
                <?php endif; ?>
                <div class="help-ask-form">
                    <div style="font-size:14px;font-weight:600;color:#666;margin-bottom:12px;">
                        <i class="fas fa-pen" style="color:var(--primary);margin-right:8px;"></i>Ask the instructor
                    </div>
                    <form action="ask_question.php" method="post">
                        <input type="hidden" name="course_id" value="<?= (int)$activeCourse['id'] ?>">
                        <input type="hidden" name="redirect_back" value="my_courses.php?course_id=<?= (int)$activeCourse['id'] ?>">
                        <textarea name="question" placeholder="Type your question here..." required></textarea>
                        <button type="submit" class="help-submit-btn">
                            <i class="fas fa-paper-plane"></i> Send Question
                        </button>
                    </form>
                </div>
                <?php if (!empty($courseQuestions)): ?>
                    <div class="hq-list">
                        <?php foreach ($courseQuestions as $q): ?>
                            <div class="hq-item">
                                <div class="hq-question">
                                    <div class="hq-meta">
                                        <div class="hq-avatar"><?= strtoupper(substr($q['student_name'] ?: 'S', 0, 1)) ?></div>
                                        <span class="hq-name"><?= htmlspecialchars($q['student_name'] ?: 'Student') ?></span>
                                        <span class="hq-time"><?= date('M d, Y', strtotime($q['created_at'])) ?></span>
                                    </div>
                                    <div class="hq-text"><?= nl2br(htmlspecialchars($q['question'])) ?></div>
                                </div>
                                <div class="hq-reply">
                                    <?php if ($q['reply']): ?>
                                        <div class="hq-reply-label"><i class="fas fa-chalkboard-teacher me-1"></i>Instructor Reply</div>
                                        <div class="hq-reply-text"><?= nl2br(htmlspecialchars($q['reply'])) ?></div>
                                    <?php else: ?>
                                        <div class="hq-no-reply"><i class="fas fa-clock me-1"></i>Awaiting reply...</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-comment-dots fa-2x mb-2" style="color:#e0d5f5;"></i>
                        <p>No questions yet. Ask anything!</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Live Classes -->
            <?php if (!empty($liveclasses)): ?>
                <div class="content-card">
                    <h3 class="section-title"><i class="fas fa-broadcast-tower" style="color:#00b894;"></i>Live Classes</h3>
                    <?php
                        $now = time();
                        foreach ($liveclasses as $live):
                            $ts = strtotime($live['scheduled_at']);
                            $diffMins = ($ts - $now) / 60;
                            if ($diffMins >= -120 && $diffMins <= 15) $state = 'live-now';
                            elseif ($ts > $now) $state = 'upcoming';
                            else $state = 'finished';
                    ?>
                        <div class="live-card live-card-<?= $state ?>">
                            <div class="live-dot live-dot-<?= $state ?>"></div>
                            <div class="live-info">
                                <div class="live-card-top">
                                    <span class="live-card-title"><?= htmlspecialchars($live['title']) ?></span>
                                    <span class="live-status-badge live-status-<?= $state ?>">
                                        <?php if ($state === 'live-now'): ?>
                                            <i class="fas fa-circle" style="font-size:8px;"></i> Live Now
                                        <?php elseif ($state === 'upcoming'): ?>
                                            <i class="fas fa-clock"></i> Upcoming
                                        <?php else: ?>
                                            <i class="fas fa-check-circle"></i> Finished
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <?php if (!empty($live['description'])): ?>
                                    <div class="live-card-desc"><?= htmlspecialchars($live['description']) ?></div>
                                <?php endif; ?>
                                <div class="live-card-meta">
                                    <span><i class="fas fa-calendar-alt"></i> <?= date('D, M d Y', $ts) ?></span>
                                    <span><i class="fas fa-clock"></i> <?= date('h:i A', $ts) ?></span>
                                </div>
                            </div>
                            <?php if ($state === 'live-now'): ?>
                                <a href="<?= htmlspecialchars($live['meet_link']) ?>" target="_blank" class="btn-join-live">
                                    <i class="fas fa-video"></i> Join Now
                                </a>
                            <?php elseif ($state === 'upcoming'): ?>
                                <div class="btn-live-waiting"><i class="fas fa-hourglass-start"></i> Starting Soon</div>
                            <?php else: ?>
                                <div class="btn-live-finished"><i class="fas fa-flag-checkered"></i> Class Finished</div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </main>
</div>

<?php include 'includes/footer.php'; ?>

<script>
function toggleEditQ(id) {
    const form = document.getElementById('hq-edit-' + id);
    const text = document.getElementById('hq-text-' + id);
    if (form) form.classList.toggle('open');
    if (text) text.style.display = form.classList.contains('open') ? 'none' : '';
}
</script>
</body>
</html>