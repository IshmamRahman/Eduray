<?php
define('EDURAY_INCLUDED', true);
session_start();
require __DIR__ . '/db.php';

$userId = $_SESSION['user_id'] ?? null;
$userName = $_SESSION['name'] ?? null;
$userMail = $_SESSION['email'] ?? null;
$isAdmin = !empty($_SESSION['is_admin']);

if (!$userId) {
    header('Location: main.php');
    exit;
}

$quizId = (int)($_GET['quiz_id'] ?? 0);
if (!$quizId) die("Invalid quiz ID.");

define('PASS_PERCENTAGE', 60);

$qRes = $conn->prepare("SELECT id, title, course_id, time_limit FROM quizzes WHERE id=?");
$qRes->bind_param("i", $quizId);
$qRes->execute();
$quiz = $qRes->get_result()->fetch_assoc();
$qRes->close();
if (!$quiz) die("Quiz not found.");

$timeLimit = (int)($quiz['time_limit'] ?? 0);

$enrollCheck = $conn->prepare("SELECT id FROM enrollments WHERE user_id=? AND course_id=?");
$enrollCheck->bind_param("ii", $userId, $quiz['course_id']);
$enrollCheck->execute();
$enrollCheck->store_result();
if ($enrollCheck->num_rows === 0) {
    header('Location: my_courses.php');
    exit;
}
$enrollCheck->close();

$prevAttempt = null;
$paRes = $conn->prepare("SELECT score, total_questions, passed, attempted_at FROM quiz_attempts WHERE user_id=? AND quiz_id=?");
$paRes->bind_param("ii", $userId, $quizId);
$paRes->execute();
$prevAttempt = $paRes->get_result()->fetch_assoc();
$paRes->close();
$alreadyAttempted = !empty($prevAttempt);

function saveAnswerDetails($conn, $userId, $quizId, $questions, $answers) {
    $ins = $conn->prepare("INSERT INTO quiz_answer_details (user_id, quiz_id, question_id, selected_option)
        VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE selected_option=VALUES(selected_option)");
    foreach ($questions as $q) {
        $sel = $answers[$q['id']] ?? '';
        if ($sel !== '') {
            $ins->bind_param("iiis", $userId, $quizId, $q['id'], $sel);
            $ins->execute();
        }
    }
    $ins->close();
}

function loadAnswerDetails($conn, $userId, $quizId) {
    $map = [];
    $res = $conn->prepare("SELECT question_id, selected_option FROM quiz_answer_details WHERE user_id=? AND quiz_id=?");
    $res->bind_param("ii", $userId, $quizId);
    $res->execute();
    $rows = $res->get_result()->fetch_all(MYSQLI_ASSOC);
    $res->close();
    foreach ($rows as $row) $map[$row['question_id']] = $row['selected_option'];
    return $map;
}

if (isset($_GET['action']) && $_GET['action'] === 'back_submit' && !$alreadyAttempted) {
    $answers = $_POST['answer'] ?? [];

    $qsRes = $conn->prepare("SELECT id, correct_option FROM quiz_questions WHERE quiz_id=?");
    $qsRes->bind_param("i", $quizId);
    $qsRes->execute();
    $questions = $qsRes->get_result()->fetch_all(MYSQLI_ASSOC);
    $qsRes->close();

    $totalCorrect = 0;
    foreach ($questions as $q) {
        if (strcasecmp($answers[$q['id']] ?? '', $q['correct_option']) === 0) $totalCorrect++;
    }

    $totalQ = count($questions);
    $scorePercent = $totalQ > 0 ? round(($totalCorrect / $totalQ) * 100) : 0;
    $passed = $scorePercent >= PASS_PERCENTAGE ? 1 : 0;

    $ins = $conn->prepare("INSERT INTO quiz_attempts (user_id, quiz_id, score, total_questions, passed) VALUES (?,?,?,?,?)");
    $ins->bind_param("iiiii", $userId, $quizId, $totalCorrect, $totalQ, $passed);
    $ins->execute();
    $ins->close();

    saveAnswerDetails($conn, $userId, $quizId, $questions, $answers);

    header('Location: my_courses.php?course_id=' . $quiz['course_id'] . '&quiz_completed=1&score=' . $scorePercent);
    exit;
}

$qsRes = $conn->prepare("SELECT id, question, option_a, option_b, option_c, option_d, correct_option FROM quiz_questions WHERE quiz_id=? ORDER BY id");
$qsRes->bind_param("i", $quizId);
$qsRes->execute();
$questions = $qsRes->get_result()->fetch_all(MYSQLI_ASSOC);
$qsRes->close();

$submitted    = false;
$answers      = [];
$savedAnswers = [];
$totalCorrect = 0;
$scorePercent = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$alreadyAttempted && !isset($_GET['action'])) {
    $submitted = true;
    $answers   = $_POST['answer'] ?? [];

    foreach ($questions as $q) {
        if (strcasecmp($answers[$q['id']] ?? '', $q['correct_option']) === 0) $totalCorrect++;
    }

    $totalQ       = count($questions);
    $scorePercent = $totalQ > 0 ? round(($totalCorrect / $totalQ) * 100) : 0;
    $passed       = $scorePercent >= PASS_PERCENTAGE ? 1 : 0;

    $ins = $conn->prepare("INSERT INTO quiz_attempts (user_id, quiz_id, score, total_questions, passed) VALUES (?,?,?,?,?)");
    $ins->bind_param("iiiii", $userId, $quizId, $totalCorrect, $totalQ, $passed);
    $ins->execute();
    $ins->close();

    saveAnswerDetails($conn, $userId, $quizId, $questions, $answers);
    $savedAnswers = $answers;

    $r2 = $conn->prepare("SELECT score, total_questions, passed, attempted_at FROM quiz_attempts WHERE user_id=? AND quiz_id=?");
    $r2->bind_param("ii", $userId, $quizId);
    $r2->execute();
    $prevAttempt = $r2->get_result()->fetch_assoc();
    $r2->close();
    $alreadyAttempted = true;
}

if ($alreadyAttempted && empty($savedAnswers)) {
    $savedAnswers = loadAnswerDetails($conn, $userId, $quizId);
}

if ($alreadyAttempted) {
    $scorePercent = $prevAttempt['total_questions'] > 0
        ? round(($prevAttempt['score'] / $prevAttempt['total_questions']) * 100)
        : 0;
    $totalCorrect = $prevAttempt['score'];
}

function getPerformance(int $pct): array {
    if ($pct >= 90) return ['label'=>'Outstanding','icon'=>'fa-star','bg'=>'linear-gradient(135deg,#f7971e,#ffd200)','text'=>'#333',
        'msg'=>'Absolutely outstanding! A perfect display of knowledge and skill.'];
    if ($pct >= 75) return ['label'=>'Excellent','icon'=>'fa-trophy','bg'=>'linear-gradient(135deg,#11998e,#38ef7d)','text'=>'#fff',
        'msg'=>'Excellent work! You have a strong grasp of the material.'];
    if ($pct >= 60) return ['label'=>'Good','icon'=>'fa-thumbs-up','bg'=>'linear-gradient(135deg,#4facfe,#00f2fe)','text'=>'#fff',
        'msg'=>'Good job! A solid performance — keep building on this foundation.'];
    if ($pct >= 40) return ['label'=>'Average','icon'=>'fa-minus-circle','bg'=>'linear-gradient(135deg,#f7971e,#ffd200)','text'=>'#333',
        'msg'=>'Not bad, but there is room to improve. Review the material and keep going.'];
    if ($pct >= 20) return ['label'=>'Poor','icon'=>'fa-exclamation-triangle','bg'=>'linear-gradient(135deg,#f85032,#e73827)','text'=>'#fff',
        'msg'=>'Keep pushing! Review the topics carefully and strengthen your understanding.'];
    return ['label'=>'Very Poor','icon'=>'fa-times-circle','bg'=>'linear-gradient(135deg,#373b44,#4286f4)','text'=>'#fff',
        'msg'=>"Don't give up! Every expert was once a beginner. Study the material and try harder next time."];
}
$perf = getPerformance($scorePercent);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Quiz – <?= htmlspecialchars($quiz['title']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #5f259f; --accent: #ff6b00; --gold: #ffe600; --bg: #fdfaf6; }
        body { background: var(--bg); font-family: 'Poppins', sans-serif; }

        .quiz-navbar {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            padding: 15px 10%;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .quiz-navbar-brand { color: white; font-size: 22px; font-weight: 700; text-decoration: none; }
        .quiz-navbar-brand span { color: var(--gold); }
        .btn-back-nav {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            padding: 8px 20px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s;
        }
        .btn-back-nav:hover { background: rgba(255,255,255,0.25); color: white; }

        .timer-bar {
            position: sticky;
            top: 0;
            z-index: 50;
            background: #fff;
            border-bottom: 2px solid #e9ecef;
            padding: 10px 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
        }
        .timer-inner {
            max-width: 780px;
            margin: 0 auto;
            padding: 0 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
        }
        .timer-display {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 20px;
            font-weight: 800;
            color: #5f259f;
            background: #f0ebff;
            border: 2px solid #c4b5fd;
            border-radius: 12px;
            padding: 8px 20px;
            transition: color 0.3s, background 0.3s, border-color 0.3s;
        }
        .timer-display.warning { color: #b45309; background: #fff3cd; border-color: #fcd34d; }
        .timer-display.danger  { color: #dc2626; background: #fee2e2; border-color: #fca5a5; animation: pulse 0.8s infinite; }
        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.65} }
        .timer-label { font-size: 12px; font-weight: 600; color: #888; }
        .timer-progress-wrap { flex: 1; height: 8px; background: #e9ecef; border-radius: 8px; overflow: hidden; }
        .timer-progress-fill { height: 100%; border-radius: 8px; background: linear-gradient(90deg, #5f259f, #ff6b00); transition: width 1s linear, background 0.5s; }

        .result-banner {
            border-radius: 20px;
            padding: 40px 30px;
            margin-bottom: 30px;
            text-align: center;
        }
        .score-ring-wrap { position: relative; width: 140px; height: 140px; margin: 0 auto 22px; }
        .score-ring-wrap svg { transform: rotate(-90deg); }
        .score-ring-bg { fill: none; stroke: rgba(255,255,255,.2); stroke-width: 10; }
        .score-ring-fill { fill: none; stroke-width: 10; stroke-linecap: round; stroke-dasharray: 339.3; stroke-dashoffset: 339.3; transition: stroke-dashoffset 1.6s ease; }
        .score-ring-center { position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%); text-align: center; }
        .score-ring-pct { font-size: 28px; font-weight: 800; display: block; }
        .perf-label { font-size: 28px; font-weight: 800; margin-bottom: 8px; }
        .perf-msg { font-size: 15px; opacity: 0.9; max-width: 480px; margin: 0 auto 20px; }
        .score-chips { display: flex; justify-content: center; gap: 12px; flex-wrap: wrap; margin-bottom: 24px; }
        .score-chip { background: rgba(255,255,255,0.2); border-radius: 30px; padding: 8px 18px; font-size: 13px; font-weight: 600; border: 1px solid rgba(255,255,255,0.3); }
        .btn-back-course { background: white; color: var(--primary); border: 2px solid var(--primary); padding: 12px 30px; border-radius: 30px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; }
        .btn-back-course:hover { background: var(--primary); color: white; }

        .locked-notice { background: #fff8e1; border: 2px solid #ffc107; border-radius: 14px; padding: 16px 22px; margin-bottom: 24px; display: flex; align-items: center; gap: 14px; }

        .review-legend {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 20px;
            padding: 14px 18px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            font-size: 13px;
        }
        .review-legend strong { color: #555; margin-right: 4px; }
        .legend-item { display: flex; align-items: center; gap: 7px; font-weight: 600; }
        .legend-dot { width: 14px; height: 14px; border-radius: 3px; flex-shrink: 0; }
        .legend-dot.l-correct  { background: #28a745; }
        .legend-dot.l-wrong    { background: #dc3545; }
        .legend-dot.l-missed   { background: #e8f5e9; border: 2px solid #28a745; }
        .legend-dot.l-skipped  { background: #6c757d; }

        .quiz-card { background: white; border-radius: 16px; padding: 28px; margin-bottom: 18px; box-shadow: 0 4px 18px rgba(0,0,0,0.06); }

        .option-box {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 14px 18px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .option-box:hover:not(.readonly) { border-color: var(--primary); background: rgba(95,37,159,0.05); }
        .option-selected { border-color: var(--primary) !important; background: rgba(95,37,159,0.08) !important; }
        .readonly { cursor: default; pointer-events: none; }

        .opt-correct-selected { background: #d4edda !important; border-color: #28a745 !important; color: #155724; font-weight: 600; }
        .opt-wrong-selected   { background: #f8d7da !important; border-color: #dc3545 !important; color: #721c24; font-weight: 600; }
        .opt-correct-missed   { background: #e8f5e9 !important; border-color: #28a745 !important; color: #2e7d32; font-weight: 600; }
        .opt-skipped-correct  { background: #e8f5e9 !important; border-color: #28a745 !important; color: #2e7d32; font-weight: 600; }

        .opt-letter { width: 32px; height: 32px; border-radius: 50%; background: #e9ecef; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; flex-shrink: 0; }
        .option-selected     .opt-letter { background: var(--primary); color: white; }
        .opt-correct-selected .opt-letter { background: #28a745; color: white; }
        .opt-wrong-selected   .opt-letter { background: #dc3545; color: white; }
        .opt-correct-missed   .opt-letter { background: #28a745; color: white; }
        .opt-skipped-correct  .opt-letter { background: #28a745; color: white; }

        .opt-badge { margin-left: auto; font-size: 12px; font-weight: 700; padding: 4px 11px; border-radius: 20px; white-space: nowrap; flex-shrink: 0; }
        .badge-your-correct { background: #28a745; color: white; }
        .badge-your-answer  { background: #dc3545; color: white; }
        .badge-correct-ans  { background: #28a745; color: white; }

        .q-result-badge { font-size: 12px; font-weight: 700; padding: 4px 12px; border-radius: 20px; display: inline-flex; align-items: center; gap: 5px; }
        .q-result-correct { background: #d4edda; color: #155724; }
        .q-result-wrong   { background: #f8d7da; color: #721c24; }
        .q-result-skipped { background: #e9ecef; color: #6c757d; }

        .q-num { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--accent)); color: white; font-size: 14px; font-weight: 700; margin-right: 10px; flex-shrink: 0; }

        .btn-submit { background: linear-gradient(135deg, var(--primary), var(--accent)); color: white; border: none; padding: 14px 42px; border-radius: 30px; font-weight: 700; font-size: 16px; transition: all 0.3s; }
        .btn-submit:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 8px 22px rgba(95,37,159,0.3); }
        .btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }

        .back-warning { background: #fff3cd; border: 2px solid #ffc107; border-radius: 16px; padding: 20px; margin: 20px 10%; display: none; }
        .back-warning.show { display: block; }
        .back-warning h5 { color: #856404; margin-bottom: 10px; }
        .back-warning .btn-confirm { background: #dc3545; color: white; border: none; padding: 10px 24px; border-radius: 25px; font-weight: 600; margin-right: 10px; }
        .back-warning .btn-cancel  { background: #6c757d; color: white; border: none; padding: 10px 24px; border-radius: 25px; font-weight: 600; }

        .timeout-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center; }
        .timeout-overlay.show { display:flex; }
        .timeout-box { background:#fff; border-radius:20px; padding:36px 32px; max-width:400px; width:90%; text-align:center; box-shadow:0 20px 60px rgba(0,0,0,0.3); animation:popIn .3s ease; }
        @keyframes popIn { from{transform:scale(.88);opacity:0} to{transform:scale(1);opacity:1} }
        .timeout-icon { font-size:48px; margin-bottom:14px; }
        .timeout-title { font-size:20px; font-weight:800; margin-bottom:8px; color:#dc2626; }
        .timeout-msg { font-size:14px; color:#666; margin-bottom:22px; line-height:1.6; }
        .timeout-submitting { display:flex; align-items:center; justify-content:center; gap:10px; font-size:14px; font-weight:700; color:#5f259f; }

        @media (max-width: 768px) {
            .quiz-navbar { padding: 15px 20px; }
            .back-warning { margin: 20px; }
            .review-legend { gap: 10px; }
            .opt-badge { font-size: 11px; padding: 3px 8px; }
            .timer-display { font-size: 17px; padding: 7px 14px; }
        }
    </style>
</head>
<body>

<div class="timeout-overlay" id="timeoutOverlay">
    <div class="timeout-box">
        <div class="timeout-icon">⏰</div>
        <div class="timeout-title">Time's Up!</div>
        <div class="timeout-msg">Your quiz time has expired. Your answers are being submitted automatically.</div>
        <div class="timeout-submitting"><i class="fas fa-spinner fa-spin"></i> Submitting…</div>
    </div>
</div>

<nav class="quiz-navbar">
    <a href="index.php" class="quiz-navbar-brand">📚 <span>EDURAY</span></a>
    <a href="#" class="btn-back-nav" id="backToCourseBtn">
        <i class="fas fa-arrow-left me-1"></i> Back to Course
    </a>
</nav>

<?php if (!$alreadyAttempted && $timeLimit > 0): ?>
<div class="timer-bar" id="timerBar">
    <div class="timer-inner">
        <div class="timer-label">Time Remaining</div>
        <div class="timer-display" id="timerDisplay">
            <i class="fas fa-clock"></i>
            <span id="timerText">--:--</span>
        </div>
        <div class="timer-progress-wrap">
            <div class="timer-progress-fill" id="timerProgressFill" style="width:100%;"></div>
        </div>
    </div>
</div>
<?php endif; ?>

<div id="backWarning" class="back-warning">
    <h5><i class="fas fa-exclamation-triangle me-2"></i>Warning: Quiz Will Be Submitted</h5>
    <p>If you go back now, this quiz will be marked as <strong>completed</strong> with your current answers (or 0% if none selected). You cannot retake it.</p>
    <form method="post" action="?quiz_id=<?= $quizId ?>&action=back_submit" id="backForm">
        <button type="submit" class="btn-confirm"><i class="fas fa-check me-1"></i>Yes, Submit & Go Back</button>
        <button type="button" class="btn-cancel" onclick="hideWarning()"><i class="fas fa-times me-1"></i>Cancel, Stay Here</button>
    </form>
</div>

<div class="container py-4" style="max-width: 780px;">

    <div class="d-flex align-items-center gap-3 mb-4">
        <div style="width:52px;height:52px;border-radius:14px;background:linear-gradient(135deg,var(--primary),var(--accent));display:flex;align-items:center;justify-content:center;color:white;font-size:22px;">
            <i class="fas fa-clipboard-check"></i>
        </div>
        <div>
            <h3 class="mb-0 fw-bold"><?= htmlspecialchars($quiz['title']) ?></h3>
            <small class="text-muted">
                <?= count($questions) ?> questions
                <?php if ($timeLimit > 0 && !$alreadyAttempted): ?>
                    &nbsp;·&nbsp;<i class="fas fa-clock" style="color:#b45309;"></i> <?= $timeLimit ?> minute<?= $timeLimit !== 1 ? 's' : '' ?> time limit
                <?php endif; ?>
            </small>
        </div>
    </div>

    <?php if ($alreadyAttempted): ?>

        <div class="result-banner" style="background:<?= $perf['bg'] ?>;color:<?= $perf['text'] ?>;">
            <div class="score-ring-wrap">
                <svg width="140" height="140" viewBox="0 0 140 140">
                    <circle class="score-ring-bg" cx="70" cy="70" r="54"/>
                    <circle class="score-ring-fill" cx="70" cy="70" r="54" id="scoreRing"
                        style="stroke:<?= $perf['text']==='#333' ? 'rgba(0,0,0,0.6)' : 'rgba(255,255,255,0.9)' ?>"/>
                </svg>
                <div class="score-ring-center" style="color:<?= $perf['text'] ?>">
                    <span class="score-ring-pct"><?= $scorePercent ?>%</span>
                    <span style="font-size:12px;display:block;">Score</span>
                </div>
            </div>
            <div class="perf-label"><i class="fas <?= $perf['icon'] ?> me-2"></i><?= $perf['label'] ?> Performance</div>
            <div class="perf-msg"><?= $perf['msg'] ?></div>
            <div class="score-chips">
                <span class="score-chip"><i class="fas fa-check me-1"></i><?= $totalCorrect ?> Correct</span>
                <span class="score-chip"><i class="fas fa-times me-1"></i><?= ($prevAttempt['total_questions'] - $totalCorrect) ?> Wrong</span>
                <span class="score-chip"><i class="fas fa-list me-1"></i><?= $prevAttempt['total_questions'] ?> Total</span>
            </div>
            <a href="my_courses.php?course_id=<?= $quiz['course_id'] ?>" class="btn-back-course">
                <i class="fas fa-arrow-left"></i> Back to Course
            </a>
        </div>

        <div class="locked-notice">
            <i class="fas fa-lock fa-2x text-warning"></i>
            <div>
                <h6 class="mb-1">Quiz Locked — One Attempt Only</h6>
                <p class="mb-0" style="font-size:13px;">You've already submitted this quiz. Your score is recorded.</p>
            </div>
        </div>

        <div class="review-legend">
            <strong>Legend:</strong>
            <div class="legend-item"><div class="legend-dot l-correct"></div> Your correct answer</div>
            <div class="legend-item"><div class="legend-dot l-wrong"></div> Your wrong answer</div>
            <div class="legend-item"><div class="legend-dot l-missed"></div> Correct answer you missed</div>
            <div class="legend-item"><div class="legend-dot l-skipped"></div> Skipped</div>
        </div>

    <?php endif; ?>

    <form method="post" id="quizForm">
        <?php foreach ($questions as $idx => $q):
            $qid      = $q['id'];
            $correct  = strtoupper($q['correct_option']);
            $userPick = isset($savedAnswers[$qid]) ? strtoupper($savedAnswers[$qid]) : '';
            $skipped  = ($alreadyAttempted && $userPick === '');
            $gotRight = ($alreadyAttempted && $userPick === $correct);
        ?>
            <div class="quiz-card">
                <p class="fw-semibold mb-3 d-flex align-items-start flex-wrap gap-2">
                    <span class="q-num"><?= $idx + 1 ?></span>
                    <span style="flex:1;"><?= htmlspecialchars($q['question']) ?></span>
                    <?php if ($alreadyAttempted): ?>
                        <?php if ($skipped): ?>
                            <span class="q-result-badge q-result-skipped"><i class="fas fa-minus"></i> Skipped</span>
                        <?php elseif ($gotRight): ?>
                            <span class="q-result-badge q-result-correct"><i class="fas fa-check"></i> Correct</span>
                        <?php else: ?>
                            <span class="q-result-badge q-result-wrong"><i class="fas fa-times"></i> Wrong</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </p>

                <?php foreach (['A','B','C','D'] as $opt):
                    $text      = $q['option_' . strtolower($opt)];
                    $isCorrect = ($opt === $correct);
                    $isPicked  = ($opt === $userPick);

                    if ($alreadyAttempted) {
                        if ($skipped) {
                            $class = 'option-box readonly' . ($isCorrect ? ' opt-skipped-correct' : '');
                        } elseif ($isPicked && $isCorrect) {
                            $class = 'option-box readonly opt-correct-selected';
                        } elseif ($isPicked && !$isCorrect) {
                            $class = 'option-box readonly opt-wrong-selected';
                        } elseif (!$isPicked && $isCorrect) {
                            $class = 'option-box readonly opt-correct-missed';
                        } else {
                            $class = 'option-box readonly';
                        }
                    } else {
                        $class = 'option-box';
                    }
                ?>
                    <div class="<?= $class ?>" data-qid="<?= $qid ?>" data-opt="<?= $opt ?>">
                        <div class="opt-letter"><?= $opt ?></div>
                        <span><?= htmlspecialchars($text) ?></span>
                        <?php if ($alreadyAttempted): ?>
                            <?php if ($isPicked && $isCorrect): ?>
                                <span class="opt-badge badge-your-correct"><i class="fas fa-check me-1"></i>Your Answer ✓</span>
                            <?php elseif ($isPicked && !$isCorrect): ?>
                                <span class="opt-badge badge-your-answer"><i class="fas fa-times me-1"></i>Your Answer</span>
                            <?php elseif (!$isPicked && $isCorrect): ?>
                                <span class="opt-badge badge-correct-ans"><i class="fas fa-check me-1"></i>Correct Answer</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <input type="hidden" name="answer[<?= $qid ?>]" id="answer-<?= $qid ?>" value="">
            </div>
        <?php endforeach; ?>

        <?php if (!$alreadyAttempted): ?>
            <div class="text-center pb-4">
                <button class="btn-submit" type="submit" id="submitBtn" disabled>
                    <i class="fas fa-paper-plane me-2"></i>Submit Quiz
                </button>
                <p class="text-muted mt-2" id="submitHint">Answer all questions to submit</p>
            </div>
        <?php endif; ?>
    </form>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    const alreadyAttempted = <?= $alreadyAttempted ? 'true' : 'false' ?>;
    const totalQ = <?= count($questions) ?>;
    const timeLimitSeconds = <?= $timeLimit * 60 ?>;
    const quizId = <?= $quizId ?>;
    const storageKey = 'quiz_start_' + quizId + '_<?= $userId ?>';
    const answered = new Set();

    const ring = document.getElementById('scoreRing');
    if (ring) {
        const circumference = 2 * Math.PI * 54;
        const scorePct = <?= $scorePercent ?>;
        ring.style.strokeDasharray = circumference;
        ring.style.strokeDashoffset = circumference;
        setTimeout(() => {
            ring.style.strokeDashoffset = circumference - (scorePct / 100) * circumference;
        }, 250);
    }

    if (!alreadyAttempted) {
        const btn  = document.getElementById('submitBtn');
        const hint = document.getElementById('submitHint');

        document.querySelectorAll('.option-box').forEach(box => {
            box.addEventListener('click', () => {
                const qid = box.dataset.qid;
                document.querySelectorAll(`.option-box[data-qid="${qid}"]`).forEach(b => b.classList.remove('option-selected'));
                box.classList.add('option-selected');
                document.getElementById('answer-' + qid).value = box.dataset.opt;
                answered.add(qid);

                if (answered.size === totalQ) {
                    btn.disabled = false;
                    hint.textContent = 'All questions answered — ready to submit!';
                    hint.style.color = '#28a745';
                } else {
                    const rem = totalQ - answered.size;
                    hint.textContent = rem + ' question' + (rem > 1 ? 's' : '') + ' remaining';
                }
            });
        });
    }

    if (!alreadyAttempted && timeLimitSeconds > 0) {
        let startTime = parseInt(sessionStorage.getItem(storageKey) || '0');
        if (!startTime) {
            startTime = Math.floor(Date.now() / 1000);
            sessionStorage.setItem(storageKey, startTime);
        }

        const display   = document.getElementById('timerDisplay');
        const timerText = document.getElementById('timerText');
        const fillBar   = document.getElementById('timerProgressFill');
        let submitted   = false;

        function formatTime(secs) {
            const m = Math.floor(secs / 60);
            const s = secs % 60;
            return String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');
        }

        function doAutoSubmit() {
            if (submitted) return;
            submitted = true;
            sessionStorage.removeItem(storageKey);
            document.getElementById('timeoutOverlay').classList.add('show');
            const form = document.getElementById('quizForm');
            setTimeout(() => { form.submit(); }, 1800);
        }

        function tick() {
            const elapsed = Math.floor(Date.now() / 1000) - startTime;
            const remaining = timeLimitSeconds - elapsed;

            if (remaining <= 0) {
                timerText.textContent = '00:00';
                if (fillBar) fillBar.style.width = '0%';
                doAutoSubmit();
                return;
            }

            timerText.textContent = formatTime(remaining);

            const pct = (remaining / timeLimitSeconds) * 100;
            if (fillBar) {
                fillBar.style.width = pct + '%';
                if (pct <= 20) {
                    fillBar.style.background = '#dc2626';
                } else if (pct <= 40) {
                    fillBar.style.background = 'linear-gradient(90deg,#b45309,#f59e0b)';
                } else {
                    fillBar.style.background = 'linear-gradient(90deg,#5f259f,#ff6b00)';
                }
            }

            if (display) {
                display.classList.remove('warning','danger');
                if (remaining <= 30) {
                    display.classList.add('danger');
                } else if (remaining <= timeLimitSeconds * 0.25) {
                    display.classList.add('warning');
                }
            }

            setTimeout(tick, 1000);
        }

        tick();
    }
})();

function showWarning() {
    document.getElementById('backWarning').classList.add('show');
    window.scrollTo(0, 0);
}
function hideWarning() {
    document.getElementById('backWarning').classList.remove('show');
}

document.getElementById('backToCourseBtn').addEventListener('click', function (e) {
    <?php if (!$alreadyAttempted): ?>
    e.preventDefault();
    showWarning();
    const form = document.getElementById('backForm');
    const quizForm = document.getElementById('quizForm');
    const formData = new FormData(quizForm);
    form.querySelectorAll('input[name^="answer"]').forEach(i => i.remove());
    for (let [key, value] of formData.entries()) {
        if (key.startsWith('answer')) {
            const input = document.createElement('input');
            input.type  = 'hidden';
            input.name  = key;
            input.value = value;
            form.appendChild(input);
        }
    }
    <?php else: ?>
    window.location.href = 'my_courses.php?course_id=<?= $quiz['course_id'] ?>';
    <?php endif; ?>
});
</script>
</body>
</html>