<?php
session_start();
require __DIR__ . '/db.php';

$userId   = $_SESSION['user_id'] ?? null;
$userName = $_SESSION['name']    ?? null;
$userMail = $_SESSION['email']   ?? null;

if (!$userId) { header('Location: login.php'); exit; }

$courseId = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
if (!$courseId) { header('Location: my_courses.php'); exit; }

define('CERT_QUIZ_THRESHOLD', 70);

// ── Enrollment check ──────────────────────────────────────────────────────────
$enrollCheck = $conn->prepare("SELECT id FROM enrollments WHERE user_id=? AND course_id=?");
$enrollCheck->bind_param("ii", $userId, $courseId);
$enrollCheck->execute();
$enrollCheck->store_result();
if ($enrollCheck->num_rows === 0) { header('Location: my_courses.php'); exit; }
$enrollCheck->close();

// ── Course ────────────────────────────────────────────────────────────────────
$cStmt = $conn->prepare("SELECT title FROM courses WHERE id=?");
$cStmt->bind_param("i", $courseId);
$cStmt->execute();
$course = $cStmt->get_result()->fetch_assoc();
$cStmt->close();
if (!$course) { header('Location: my_courses.php'); exit; }

// ── Videos check ─────────────────────────────────────────────────────────────
$vCheck = $conn->prepare("
    SELECT COUNT(*) AS total,
           SUM(CASE WHEN vp.completed=1 THEN 1 ELSE 0 END) AS done
    FROM videos v
    LEFT JOIN video_progress vp ON vp.video_id=v.id AND vp.user_id=?
    WHERE v.course_id=?
");
$vCheck->bind_param("ii", $userId, $courseId);
$vCheck->execute();
$vData = $vCheck->get_result()->fetch_assoc();
$vCheck->close();
$allVideosDone = ($vData['total'] == 0 || $vData['done'] >= $vData['total']);

// ── Quiz average ──────────────────────────────────────────────────────────────
$totalQStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM quizzes WHERE course_id=?");
$totalQStmt->bind_param("i", $courseId);
$totalQStmt->execute();
$totalQuizzes = $totalQStmt->get_result()->fetch_assoc()['cnt'];
$totalQStmt->close();

$qStmt = $conn->prepare("
    SELECT qa.score, qa.total_questions
    FROM quiz_attempts qa
    JOIN quizzes q ON qa.quiz_id=q.id
    WHERE q.course_id=? AND qa.user_id=?
");
$qStmt->bind_param("ii", $courseId, $userId);
$qStmt->execute();
$attempts = $qStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$qStmt->close();

$allQuizzesAttempted = count($attempts) >= $totalQuizzes;
$quizAvg = 0;
if (!empty($attempts)) {
    $sum = 0;
    foreach ($attempts as $a)
        if ($a['total_questions'] > 0)
            $sum += round(($a['score'] / $a['total_questions']) * 100);
    $quizAvg = round($sum / count($attempts));
}

// ── Eligibility ───────────────────────────────────────────────────────────────
$eligible = $allVideosDone && $allQuizzesAttempted
            && ($totalQuizzes === 0 || $quizAvg >= CERT_QUIZ_THRESHOLD);
if (!$eligible) { header("Location: my_courses.php?course_id=$courseId"); exit; }

// ── Performance ───────────────────────────────────────────────────────────────
function getPerformance(int $pct): array {
    if ($pct >= 90) return ['label' => 'Outstanding', 'color' => '#b8860b'];
    if ($pct >= 75) return ['label' => 'Excellent',   'color' => '#1a6b3c'];
    if ($pct >= 60) return ['label' => 'Good',        'color' => '#1a4d8f'];
    if ($pct >= 40) return ['label' => 'Average',     'color' => '#8f4e1a'];
    if ($pct >= 20) return ['label' => 'Poor',        'color' => '#8f1a1a'];
    return                  ['label' => 'Very Poor',  'color' => '#444444'];
}

$perf         = getPerformance($quizAvg);
$studentName  = $userName ?: $userMail;
$courseName   = $course['title'];
$issueDate    = date('F d, Y');
$certId       = strtoupper(substr(md5($userId . $courseId . $issueDate), 0, 10));
$fileName     = 'EDURAY_Certificate_' . preg_replace('/[^a-zA-Z0-9]/', '_', $courseName) . '.pdf';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Certificate – <?= htmlspecialchars($courseName) ?></title>

  <!-- Professional fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700;900&family=EB+Garamond:ital,wght@0,400;0,500;0,600;1,400;1,500&family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <style>
    :root { --primary:#5f259f; --accent:#ff6b00; --gold:#c9a84c; --dark-gold:#8B6914; }

    body {
      background: #1a1a2e;
      font-family: 'Montserrat', sans-serif;
      min-height: 100vh;
    }

    .navbar {
      background: linear-gradient(135deg, #5f259f, #ff6b00);
    }
    .navbar-brand { font-size:22px; font-weight:700; color:#fff!important; }
    .brand-accent  { color:#ffe600; }

    .page-wrap {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 40px 20px;
    }

    /* ── Download button ── */
    .btn-download {
      background: linear-gradient(135deg, #5f259f, #ff6b00);
      color: white; border: none;
      padding: 16px 50px; border-radius: 4px;
      font-size: 16px; font-weight: 600;
      font-family: 'Montserrat', sans-serif;
      letter-spacing: 1.5px; text-transform: uppercase;
      display: inline-flex; align-items: center; gap: 12px;
      cursor: pointer; transition: all 0.3s;
      box-shadow: 0 8px 25px rgba(95,37,159,0.4);
      margin-bottom: 12px;
    }
    .btn-download:hover {
      transform: translateY(-2px);
      box-shadow: 0 14px 30px rgba(95,37,159,0.5);
    }
    .btn-back {
      color: #aaa; text-decoration: none; font-size: 14px;
      display: inline-flex; align-items: center; gap: 8px;
      letter-spacing: 0.5px;
    }
    .btn-back:hover { color: #fff; }

    /* ════════════════════════════════════════════════
       CERTIFICATE CARD
    ════════════════════════════════════════════════ */
    #certPreview {
      width: 960px;
      height: 680px;
      position: relative;
      background: #fffef9;
      box-shadow: 0 30px 80px rgba(0,0,0,0.5);
      margin-bottom: 36px;
      overflow: hidden;
    }

    /* Outer decorative border — sharp rectangle */
    .c-outer-border {
      position: absolute;
      top: 14px; left: 14px; right: 14px; bottom: 14px;
      border: 2px solid #c9a84c;
    }

    /* Inner decorative border */
    .c-inner-border {
      position: absolute;
      top: 22px; left: 22px; right: 22px; bottom: 22px;
      border: 0.5px solid #c9a84c;
      opacity: 0.6;
    }

    /* Corner ornaments */
    .c-corner {
      position: absolute;
      width: 40px; height: 40px;
    }
    .c-corner::before,
    .c-corner::after {
      content: '';
      position: absolute;
      background: #c9a84c;
    }
    /* Top-left */
    .c-corner.tl { top: 10px; left: 10px; }
    .c-corner.tl::before { top:0; left:0; width:40px; height:3px; }
    .c-corner.tl::after  { top:0; left:0; width:3px;  height:40px; }
    /* Top-right */
    .c-corner.tr { top: 10px; right: 10px; }
    .c-corner.tr::before { top:0; right:0; width:40px; height:3px; left:auto; }
    .c-corner.tr::after  { top:0; right:0; width:3px;  height:40px; left:auto; }
    /* Bottom-left */
    .c-corner.bl { bottom: 10px; left: 10px; }
    .c-corner.bl::before { bottom:0; left:0; width:40px; height:3px; top:auto; }
    .c-corner.bl::after  { bottom:0; left:0; width:3px;  height:40px; top:auto; }
    /* Bottom-right */
    .c-corner.br { bottom: 10px; right: 10px; }
    .c-corner.br::before { bottom:0; right:0; width:40px; height:3px; top:auto; left:auto; }
    .c-corner.br::after  { bottom:0; right:0; width:3px;  height:40px; top:auto; left:auto; }

    /* Header band */
    .c-header {
      position: absolute;
      top: 0; left: 0; right: 0;
      height: 108px;
      background: linear-gradient(135deg, #3d1066 0%, #5f259f 50%, #3d1066 100%);
      display: flex; flex-direction: column;
      align-items: center; justify-content: center; gap: 4px;
    }
    .c-header-logo {
      font-family: 'Cinzel', serif;
      font-size: 30px; font-weight: 900;
      color: #c9a84c;
      letter-spacing: 8px;
      text-transform: uppercase;
    }
    .c-header-tagline {
      font-family: 'Montserrat', sans-serif;
      font-size: 10px; font-weight: 300;
      color: rgba(255,255,255,0.75);
      letter-spacing: 4px;
      text-transform: uppercase;
    }

    /* Gold rule under header */
    .c-rule {
      position: absolute;
      top: 108px; left: 0; right: 0;
      height: 3px;
      background: linear-gradient(90deg, transparent, #c9a84c 20%, #c9a84c 80%, transparent);
    }

    /* Footer band */
    .c-footer-band {
      position: absolute;
      bottom: 0; left: 0; right: 0;
      height: 64px;
      background: linear-gradient(135deg, #3d1066 0%, #5f259f 50%, #3d1066 100%);
    }
    /* Gold rule above footer */
    .c-rule-bottom {
      position: absolute;
      bottom: 64px; left: 0; right: 0;
      height: 3px;
      background: linear-gradient(90deg, transparent, #c9a84c 20%, #c9a84c 80%, transparent);
    }

    /* Body */
    .c-body {
      position: absolute;
      top: 118px; left: 50px; right: 50px; bottom: 70px;
      display: flex; flex-direction: column;
      align-items: center; justify-content: center;
      text-align: center;
    }

    .c-presents {
      font-family: 'Montserrat', sans-serif;
      font-size: 10px; font-weight: 500;
      color: #999;
      letter-spacing: 5px;
      text-transform: uppercase;
      margin-bottom: 8px;
    }

    .c-cert-title {
      font-family: 'Cinzel', serif;
      font-size: 28px; font-weight: 700;
      color: #2c1a4e;
      letter-spacing: 2px;
      text-transform: uppercase;
      margin-bottom: 4px;
    }

    /* Gold ornamental divider */
    .c-ornament {
      display: flex; align-items: center; gap: 10px;
      margin: 8px 0 14px;
    }
    .c-ornament-line {
      height: 1px; width: 80px;
      background: linear-gradient(90deg, transparent, #c9a84c);
    }
    .c-ornament-line.r {
      background: linear-gradient(90deg, #c9a84c, transparent);
    }
    .c-ornament-diamond {
      width: 8px; height: 8px;
      background: #c9a84c;
      transform: rotate(45deg);
    }

    .c-awarded {
      font-family: 'EB Garamond', serif;
      font-size: 14px; font-style: italic;
      color: #777; margin-bottom: 10px;
    }

    .c-name {
      font-family: 'Cinzel', serif;
      font-size: 40px; font-weight: 600;
      color: #3d1066;
      margin-bottom: 10px;
      line-height: 1.1;
    }

    /* Name underline */
    .c-name-rule {
      width: 420px; height: 1px;
      background: linear-gradient(90deg, transparent, #c9a84c 30%, #c9a84c 70%, transparent);
      margin: 0 auto 14px;
    }

    .c-completion {
      font-family: 'EB Garamond', serif;
      font-size: 15px; color: #555;
      margin-bottom: 6px;
    }

    .c-course {
      font-family: 'Cinzel', serif;
      font-size: 17px; font-weight: 600;
      color: #5f259f;
      margin-bottom: 16px;
      padding: 0 20px;
    }

    /* Performance + score row */
    .c-stats-row {
      display: flex; align-items: center; gap: 0;
      border: 1px solid #c9a84c;
      overflow: hidden;
      margin-bottom: 0;
    }
    .c-stat {
      padding: 8px 28px; text-align: center;
    }
    .c-stat + .c-stat {
      border-left: 1px solid #c9a84c;
    }
    .c-stat-label {
      font-family: 'Montserrat', sans-serif;
      font-size: 8.5px; font-weight: 600;
      color: #999; letter-spacing: 2.5px;
      text-transform: uppercase; margin-bottom: 3px;
    }
    .c-stat-value {
      font-family: 'Cinzel', serif;
      font-size: 15px; font-weight: 700;
      color: <?= $perf['color'] ?>;
    }
    .c-stat-value.score {
      color: #2c1a4e;
    }

    /* Footer content inside footer band */
    .c-footer-content {
      position: absolute;
      bottom: 0; left: 50px; right: 50px;
      height: 64px;
      display: flex; align-items: center;
      justify-content: space-between;
    }

    .c-footer-block { text-align: center; }
    .c-footer-label {
      font-family: 'Montserrat', sans-serif;
      font-size: 8px; font-weight: 500;
      color: rgba(255,255,255,0.55);
      letter-spacing: 2px; text-transform: uppercase;
      margin-bottom: 2px;
    }
    .c-footer-value {
      font-family: 'Montserrat', sans-serif;
      font-size: 10px; font-weight: 600;
      color: #c9a84c;
    }
    .c-footer-sig {
      font-family: 'EB Garamond', serif;
      font-size: 13px; font-style: italic;
      color: rgba(255,255,255,0.9);
    }

    /* Seal */
    .c-seal {
      width: 70px; height: 70px;
      border: 2px solid #c9a84c;
      display: flex; flex-direction: column;
      align-items: center; justify-content: center;
      background: rgba(201,168,76,0.12);
    }
    .c-seal-text {
      font-family: 'Cinzel', serif;
      font-size: 9px; font-weight: 700;
      color: #c9a84c; letter-spacing: 2px;
    }
    .c-seal-sub {
      font-family: 'Montserrat', sans-serif;
      font-size: 6px; color: rgba(255,255,255,0.6);
      letter-spacing: 1.5px; margin-top: 2px;
    }

    @media(max-width:1020px) {
      #certPreview { width:100%; height:auto; min-height:500px; }
    }
  </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar">
  <div class="container-fluid px-4 d-flex align-items-center justify-content-between">
    <a class="navbar-brand" href="index.php">📚 <span class="brand-accent">EDURAY</span></a>
    <a href="my_courses.php?course_id=<?= $courseId ?>" style="color:rgba(255,255,255,0.85);text-decoration:none;font-size:14px;font-weight:500;letter-spacing:0.5px;">
      <i class="fas fa-arrow-left me-2"></i>Back to Course
    </a>
  </div>
</nav>

<div class="page-wrap">

  <!-- ══════════════════════════════════════════════════════
       CERTIFICATE PREVIEW
  ══════════════════════════════════════════════════════ -->
  <div id="certPreview">

    <!-- Borders & corners -->
    <div class="c-outer-border"></div>
    <div class="c-inner-border"></div>
    <div class="c-corner tl"></div>
    <div class="c-corner tr"></div>
    <div class="c-corner bl"></div>
    <div class="c-corner br"></div>

    <!-- Header -->
    <div class="c-header">
      <div class="c-header-logo">EDURAY</div>
      <div class="c-header-tagline">Online Learning Platform</div>
    </div>
    <div class="c-rule"></div>

    <!-- Footer band -->
    <div class="c-footer-band"></div>
    <div class="c-rule-bottom"></div>

    <!-- Body -->
    <div class="c-body">

      <div class="c-presents">Proudly Presents</div>

      <div class="c-cert-title">Certificate of Completion</div>

      <div class="c-ornament">
        <div class="c-ornament-line"></div>
        <div class="c-ornament-diamond"></div>
        <div class="c-ornament-line r"></div>
      </div>

      <div class="c-awarded">This certificate is awarded to</div>

      <div class="c-name"><?= htmlspecialchars($studentName) ?></div>
      <div class="c-name-rule"></div>

      <div class="c-completion">
        in recognition of the successful completion of the course
      </div>

      <div class="c-course">&ldquo;<?= htmlspecialchars($courseName) ?>&rdquo;</div>

      <!-- Stats row -->
      <div class="c-stats-row">
        <div class="c-stat">
          <div class="c-stat-label">Performance</div>
          <div class="c-stat-value"><?= $perf['label'] ?></div>
        </div>
        <div class="c-stat">
          <div class="c-stat-label">Test Score</div>
          <div class="c-stat-value score"><?= $quizAvg ?>%</div>
        </div>
        <div class="c-stat">
          <div class="c-stat-label">Certificate ID</div>
          <div class="c-stat-value score" style="font-size:12px;"><?= $certId ?></div>
        </div>
      </div>

    </div>

    <!-- Footer content -->
    <div class="c-footer-content">
      <div class="c-footer-block">
        <div class="c-footer-label">Date of Issue</div>
        <div class="c-footer-value"><?= $issueDate ?></div>
      </div>
      <div class="c-seal">
        <div class="c-seal-text">EDURAY</div>
        <div class="c-seal-sub">CERTIFIED</div>
      </div>
      <div class="c-footer-block">
        <div class="c-footer-label">Authorized By</div>
        <div class="c-footer-sig">EDURAY Academy</div>
      </div>
    </div>

  </div>
  <!-- ══ End Certificate ══ -->

  <button class="btn-download" onclick="downloadCertificate()" id="dlBtn">
    <i class="fas fa-download"></i> Download Certificate
  </button>
  <a href="my_courses.php?course_id=<?= $courseId ?>" class="btn-back">
    <i class="fas fa-arrow-left me-1"></i> Back to Course
  </a>

</div>

<!-- jsPDF + html2canvas — zero install -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<script>
  const FILE_NAME = <?= json_encode($fileName) ?>;

  async function downloadCertificate() {
    const btn = document.getElementById('dlBtn');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>&nbsp; Generating PDF...';
    btn.disabled = true;

    try {
      const cert   = document.getElementById('certPreview');
      const canvas = await html2canvas(cert, {
        scale: 3,
        useCORS: true,
        allowTaint: true,
        backgroundColor: '#fffef9',
        logging: false,
        onclone: (doc) => {
          // Make sure cloned fonts are loaded
          doc.getElementById('certPreview').style.fontDisplay = 'swap';
        }
      });

      const imgData = canvas.toDataURL('image/jpeg', 1.0);
      const { jsPDF } = window.jspdf;
      const pdf = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });
      const pW  = pdf.internal.pageSize.getWidth();
      const pH  = pdf.internal.pageSize.getHeight();

      pdf.addImage(imgData, 'JPEG', 0, 0, pW, pH);
      pdf.save(FILE_NAME);

    } catch (err) {
      alert('Could not generate PDF. Please try again.');
      console.error(err);
    }

    btn.innerHTML = '<i class="fas fa-download"></i> Download Certificate';
    btn.disabled = false;
  }
</script>

</body>
</html>