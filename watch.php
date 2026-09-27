<?php
session_start();
require __DIR__ . '/db.php';

$userId   = $_SESSION['user_id'] ?? null;
$userName = $_SESSION['name']    ?? null;
$userMail = $_SESSION['email']   ?? null;

if (!$userId) {
    header('Location: login.php');
    exit;
}

$videoId = isset($_GET['video_id']) ? intval($_GET['video_id']) : 0;

if (!$videoId) {
    header('Location: my_courses.php');
    exit;
}

// Get video details with course info
$stmt = $conn->prepare("
    SELECT v.*, c.id as course_id, c.title as course_title, c.thumbnail_url as course_thumb
    FROM videos v
    JOIN courses c ON v.course_id = c.id
    JOIN enrollments e ON c.id = e.course_id
    WHERE v.id = ? AND e.user_id = ?
");
$stmt->bind_param("ii", $videoId, $userId);
$stmt->execute();
$result = $stmt->get_result();
$video = $result->fetch_assoc();
$stmt->close();

if (!$video) {
    header('Location: my_courses.php');
    exit;
}

// Get all videos in this course with their completion status
$videos = [];
$vstmt = $conn->prepare("
    SELECT v.id, v.title, v.thumb_url, v.duration,
           COALESCE(vp.completed, 0) as completed,
           COALESCE(vp.progress_seconds, 0) as progress_seconds
    FROM videos v
    LEFT JOIN video_progress vp ON vp.video_id = v.id AND vp.user_id = ?
    WHERE v.course_id = ?
    ORDER BY v.position, v.id
");
$vstmt->bind_param("ii", $userId, $video['course_id']);
$vstmt->execute();
$vresult = $vstmt->get_result();
while ($v = $vresult->fetch_assoc()) {
    $videos[] = $v;
}
$vstmt->close();

// Get this video's saved progress
$currentProgress = 0;
$currentCompleted = 0;
foreach ($videos as $v) {
    if ($v['id'] == $videoId) {
        $currentProgress  = $v['progress_seconds'];
        $currentCompleted = $v['completed'];
        break;
    }
}

// Find current video index
$currentIndex = 0;
foreach ($videos as $i => $v) {
    if ($v['id'] == $videoId) {
        $currentIndex = $i;
        break;
    }
}

$prevVideo = $currentIndex > 0 ? $videos[$currentIndex - 1] : null;
$nextVideo = $currentIndex < count($videos) - 1 ? $videos[$currentIndex + 1] : null;

$totalVideos     = count($videos);
$completedVideos = count(array_filter($videos, fn($v) => $v['completed']));
$progressPct     = $totalVideos > 0 ? round(($completedVideos / $totalVideos) * 100) : 0;

$isYoutube = !empty($video['embed_url']) && strpos($video['embed_url'], 'youtube') !== false;

// ── Fetch video Q&A ────────────────────────────────────────────────────────
$videoQuestions = [];
$vqRes = $conn->prepare("
    SELECT q.id, q.question, q.created_at, q.student_id,
           u.name AS student_name,
           r.id AS reply_id, r.reply, r.created_at AS replied_at,
           ru.name AS instructor_name
    FROM course_questions q
    JOIN users u ON u.id = q.student_id
    LEFT JOIN course_question_replies r ON r.question_id = q.id
    LEFT JOIN users ru ON ru.id = r.instructor_id
    WHERE q.video_id = ?
    ORDER BY q.created_at ASC
");
$vqRes->bind_param("i", $videoId);
$vqRes->execute();
$videoQuestions = $vqRes->get_result()->fetch_all(MYSQLI_ASSOC);
$vqRes->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($video['title']) ?> - EDURAY</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root{
      --primary:#5f259f;
      --accent:#ff6b00;
      --gold:#ffe600;
      --bg:#0f0f0f;
    }
    body{
      margin:0; 
      font-family:'Poppins',sans-serif; 
      background:var(--bg); 
      color:#fff;
      min-height: 100vh;
    }
    
    .navbar{
      background: linear-gradient(135deg, var(--primary), var(--accent));
      padding: 15px 0;
    }
    .navbar-brand{ font-size:22px; font-weight:700; color:#fff !important; }
    .brand-accent{ color:var(--gold) }
    
    .btn-back{
      background: rgba(255,255,255,0.2);
      color: white; border: none;
      padding: 8px 20px; border-radius: 25px;
      font-weight: 600; text-decoration: none;
      display: inline-flex; align-items: center; gap: 8px;
      transition: all 0.3s;
    }
    .btn-back:hover{ background: rgba(255,255,255,0.3); color: white; transform: translateX(-3px); }
    
    /* Progress bar in navbar */
    .course-progress-bar {
      height: 4px;
      background: rgba(255,255,255,0.2);
      position: relative;
    }
    .course-progress-fill {
      height: 100%;
      background: var(--gold);
      transition: width 0.5s ease;
    }

    .player-layout { display: flex; min-height: calc(100vh - 70px); }
    .video-section  { flex: 1; display: flex; flex-direction: column; }
    
    .video-wrapper {
      position: relative; background: #000;
      width: 100%; aspect-ratio: 16/9; max-height: 70vh;
    }
    .video-wrapper iframe,
    .video-wrapper video { width: 100%; height: 100%; border: none; }

    /* Completion toast */
    #completionToast {
      position: fixed; top: 90px; right: 20px; z-index: 9999;
      background: linear-gradient(135deg, #28a745, #20c997);
      color: white; padding: 16px 24px; border-radius: 14px;
      font-weight: 600; font-size: 15px;
      box-shadow: 0 8px 25px rgba(40,167,69,0.4);
      display: flex; align-items: center; gap: 10px;
      opacity: 0; transform: translateX(100px);
      transition: all 0.4s ease;
      pointer-events: none;
    }
    #completionToast.show {
      opacity: 1; transform: translateX(0);
    }

    .video-controls {
      background: linear-gradient(135deg, #1a1a1a, #2d2d2d);
      padding: 20px 30px;
      display: flex; justify-content: space-between; align-items: center;
      border-bottom: 1px solid #333;
    }
    
    .nav-btn {
      background: linear-gradient(135deg, var(--primary), var(--accent));
      color: white; border: none; padding: 12px 25px; border-radius: 30px;
      font-weight: 600; text-decoration: none;
      display: inline-flex; align-items: center; gap: 10px;
      transition: all 0.3s;
    }
    .nav-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(95,37,159,0.4); color: white; }
    .nav-btn.disabled { opacity: 0.3; cursor: not-allowed; pointer-events: none; }
    
    .video-info-bar { text-align: center; flex: 1; padding: 0 30px; }
    .video-info-bar h4 { margin: 0; font-weight: 600; color: #fff; font-size: 18px; }
    .video-info-bar span { color: #888; font-size: 14px; }
    
    /* Mark complete button */
    .btn-mark-complete {
      background: transparent;
      border: 2px solid #28a745;
      color: #28a745;
      padding: 8px 20px; border-radius: 25px;
      font-weight: 600; font-size: 14px; cursor: pointer;
      display: inline-flex; align-items: center; gap: 8px;
      transition: all 0.3s; margin-top: 8px;
    }
    .btn-mark-complete:hover, .btn-mark-complete.done {
      background: #28a745; color: white;
    }
    .btn-mark-complete.done { cursor: default; pointer-events: none; }

    .video-details { padding: 30px; background: #1a1a1a; flex: 1; }

    /* ── Video Q&A ── */
    .qa-section { padding: 24px 30px; background: #111; border-top: 1px solid #2a2a2a; }
    .qa-section-title { font-size: 16px; font-weight: 800; color: #fff; margin-bottom: 18px; display: flex; align-items: center; gap: 10px; }
    .qa-section-title i { color: #a78bfa; }
    .qa-ask-form { background: #1a1a1a; border: 1.5px solid #2a2a2a; border-radius: 12px; padding: 16px; margin-bottom: 20px; }
    .qa-ask-form textarea { width: 100%; background: #111; border: 1.5px solid #333; border-radius: 8px; padding: 10px 14px; color: #eee; font-size: 13.5px; font-family: inherit; resize: vertical; min-height: 70px; outline: none; transition: border-color .2s; }
    .qa-ask-form textarea:focus { border-color: #7c3aed; }
    .qa-ask-form textarea::placeholder { color: #555; }
    .qa-submit-btn { margin-top: 10px; padding: 9px 20px; background: linear-gradient(135deg,#5f259f,#ff6b00); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; font-family: inherit; display: inline-flex; align-items: center; gap: 7px; transition: opacity .2s; }
    .qa-submit-btn:hover { opacity: .88; }
    .qa-list { display: flex; flex-direction: column; gap: 14px; }
    .qa-item { background: #1a1a1a; border: 1px solid #2a2a2a; border-radius: 10px; overflow: hidden; }
    .qa-item-question { padding: 14px 16px; }
    .qa-item-meta { display: flex; align-items: center; gap: 8px; margin-bottom: 7px; flex-wrap: wrap; }
    .qa-avatar { width: 28px; height: 28px; border-radius: 50%; background: linear-gradient(135deg,#5f259f,#ff6b00); display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800; color: #fff; flex-shrink: 0; }
    .qa-student-name { font-size: 12.5px; font-weight: 700; color: #ccc; }
    .qa-time { font-size: 11px; color: #555; margin-left: auto; }
    .qa-text { font-size: 13.5px; color: #ddd; line-height: 1.6; }
    .qa-item-reply { padding: 12px 16px; background: #141414; border-top: 1px solid #222; }
    .qa-reply-label { font-size: 11.5px; font-weight: 800; color: #a78bfa; text-transform: uppercase; letter-spacing: .6px; margin-bottom: 6px; display: flex; align-items: center; gap: 6px; }
    .qa-reply-text { font-size: 13.5px; color: #ccc; line-height: 1.6; }
    .qa-no-reply { font-size: 12px; color: #555; padding: 6px 0; font-style: italic; }
    .qa-success-banner { background: rgba(16,185,129,.12); border: 1px solid rgba(16,185,129,.3); color: #6ee7b7; border-radius: 8px; padding: 10px 14px; font-size: 13px; font-weight: 600; margin-bottom: 14px; display: flex; align-items: center; gap: 8px; }
    /* Edit/delete controls */
    .qa-actions { display:flex; gap:6px; margin-top:8px; }
    .qa-act-btn { display:inline-flex; align-items:center; gap:4px; padding:4px 10px; border-radius:6px; font-size:11.5px; font-weight:700; cursor:pointer; border:1.5px solid; background:transparent; font-family:inherit; transition:background .15s,color .15s; }
    .qa-act-btn-edit   { color:#a78bfa; border-color:#a78bfa; } .qa-act-btn-edit:hover   { background:#a78bfa; color:#111; }
    .qa-act-btn-delete { color:#f87171; border-color:#f87171; } .qa-act-btn-delete:hover { background:#f87171; color:#111; }
    .qa-edit-form { display:none; margin-top:10px; }
    .qa-edit-form.open { display:block; }
    .qa-edit-form textarea { width:100%; background:#1a1a1a; border:1.5px solid #444; border-radius:8px; padding:9px 12px; color:#eee; font-size:13px; font-family:inherit; resize:vertical; min-height:60px; outline:none; }
    .qa-edit-form textarea:focus { border-color:#7c3aed; }
    .qa-edit-save { padding:6px 16px; background:linear-gradient(135deg,#5f259f,#ff6b00); color:#fff; border:none; border-radius:7px; font-size:12.5px; font-weight:700; cursor:pointer; font-family:inherit; margin-top:6px; }
    .qa-edit-cancel { padding:6px 12px; background:#222; color:#888; border:1px solid #333; border-radius:7px; font-size:12.5px; font-weight:700; cursor:pointer; font-family:inherit; margin-top:6px; margin-left:6px; }
    
    .course-badge {
      display: inline-flex; align-items: center; gap: 8px;
      background: rgba(95,37,159,0.3); color: #fff;
      padding: 8px 16px; border-radius: 20px; font-size: 14px;
      margin-bottom: 15px; border: 1px solid var(--primary);
    }
    .video-title-large { font-size: 28px; font-weight: 700; margin-bottom: 15px; color: #fff; }
    .video-meta-large {
      display: flex; gap: 25px; color: #888; font-size: 15px; margin-bottom: 20px;
    }
    .video-meta-large span { display: flex; align-items: center; gap: 8px; }
    .video-meta-large i { color: var(--accent); }
    
    /* Playlist */
    .playlist-sidebar {
      width: 380px; background: #1a1a1a;
      border-left: 1px solid #333; display: flex; flex-direction: column;
    }
    .playlist-header {
      padding: 20px;
      background: linear-gradient(135deg, var(--primary), var(--accent));
      color: white;
    }
    .playlist-header h5 { margin: 0; font-weight: 600; display: flex; align-items: center; gap: 10px; }
    .playlist-header small { opacity: 0.9; }

    /* Course progress in playlist header */
    .playlist-progress {
      margin-top: 10px;
      background: rgba(255,255,255,0.2);
      border-radius: 10px; height: 6px;
    }
    .playlist-progress-fill {
      height: 100%; border-radius: 10px;
      background: var(--gold); transition: width 0.5s;
    }
    .playlist-progress-label {
      font-size: 12px; margin-top: 4px; opacity: 0.9;
    }

    .playlist-items { flex: 1; overflow-y: auto; padding: 15px; }
    
    .playlist-item {
      display: flex; align-items: center; padding: 12px; border-radius: 12px;
      margin-bottom: 10px; cursor: pointer; transition: all 0.3s;
      text-decoration: none; color: #ccc; background: #252525;
      border: 2px solid transparent;
    }
    .playlist-item:hover { background: #333; color: #fff; transform: translateX(5px); }
    .playlist-item.active {
      background: linear-gradient(135deg, rgba(95,37,159,0.3), rgba(255,107,0,0.2));
      border-color: var(--primary); color: #fff;
    }
    
    .playlist-thumb-wrap { position: relative; flex-shrink: 0; }
    .playlist-thumb { width: 100px; height: 60px; object-fit: cover; border-radius: 8px; display: block; }
    
    .playlist-number {
      position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%);
      width: 28px; height: 28px; background: rgba(0,0,0,0.7);
      border-radius: 50%; display: flex; align-items: center; justify-content: center;
      font-size: 12px; font-weight: 700; color: #fff;
    }
    /* Green tick overlay on completed videos */
    .completed-badge {
      position: absolute; bottom: 4px; right: 4px;
      width: 20px; height: 20px; background: #28a745;
      border-radius: 50%; display: flex; align-items: center; justify-content: center;
      font-size: 10px; color: white;
    }

    .playlist-info { flex: 1; padding: 0 10px; }
    .playlist-title {
      font-weight: 600; font-size: 14px; margin-bottom: 4px;
      display: -webkit-box; -webkit-line-clamp: 2;
      -webkit-box-orient: vertical; overflow: hidden;
    }
    .playlist-duration { font-size: 12px; color: #888; }
    .playing-icon { color: var(--accent); margin-left: auto; font-size: 20px; }
    
    @media (max-width: 992px) {
      .player-layout { flex-direction: column; }
      .playlist-sidebar { width: 100%; border-left: none; border-top: 1px solid #333; max-height: 400px; }
      .video-controls { flex-direction: column; gap: 15px; text-align: center; }
      .nav-btn { width: 100%; justify-content: center; }
    }
  </style>
</head>
<body>

  <!-- Completion Toast -->
  <div id="completionToast">
    <i class="fas fa-check-circle fa-lg"></i>
    <span>Video marked as complete! 🎉</span>
  </div>

  <!-- Navbar -->
  <nav class="navbar sticky-top">
    <div class="container-fluid px-4">
      <a class="navbar-brand" href="index.php">
        📚 <span class="brand-accent">EDURAY</span>
      </a>
      <a href="my_courses.php?course_id=<?= $video['course_id'] ?>" class="btn-back">
        <i class="fas fa-arrow-left"></i> Back to Course
      </a>
    </div>
    <div class="course-progress-bar">
      <div class="course-progress-fill" id="navProgressFill" style="width: <?= $progressPct ?>%"></div>
    </div>
  </nav>

  <div class="player-layout">
    <!-- Video Section -->
    <div class="video-section">

      <!-- Video Player -->
      <div class="video-wrapper" id="videoWrapper">
        <?php if ($isYoutube): ?>
          <!-- YouTube — we use the IFrame API to detect playback -->
          <div id="ytPlayer"></div>
        <?php elseif (!empty($video['video_path'])): ?>
          <!-- Local Video -->
          <video id="localVideo" controls <?= $currentCompleted ? '' : '' ?>>
            <source src="<?= htmlspecialchars($video['video_path']) ?>" type="video/mp4">
            Your browser does not support the video tag.
          </video>
        <?php else: ?>
          <div style="display:flex;align-items:center;justify-content:center;height:100%;color:#666;">
            <div class="text-center">
              <i class="fas fa-exclamation-circle fa-3x mb-3"></i>
              <p>Video not available</p>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <!-- Controls Bar -->
      <div class="video-controls">
        <?php if ($prevVideo): ?>
          <a href="?video_id=<?= $prevVideo['id'] ?>" class="nav-btn">
            <i class="fas fa-step-backward"></i> Previous
          </a>
        <?php else: ?>
          <span class="nav-btn disabled"><i class="fas fa-step-backward"></i> Previous</span>
        <?php endif; ?>

        <div class="video-info-bar">
          <h4><?= htmlspecialchars($video['title']) ?></h4>
          <span>Video <?= $currentIndex + 1 ?> of <?= count($videos) ?></span><br>
          <button class="btn-mark-complete <?= $currentCompleted ? 'done' : '' ?>" 
                  id="markCompleteBtn"
                  onclick="markComplete()">
            <i class="fas fa-<?= $currentCompleted ? 'check-circle' : 'circle' ?>"></i>
            <?= $currentCompleted ? 'Completed' : 'Mark as Complete' ?>
          </button>
        </div>

        <?php if ($nextVideo): ?>
          <a href="?video_id=<?= $nextVideo['id'] ?>" class="nav-btn" id="nextBtn">
            Next <i class="fas fa-step-forward"></i>
          </a>
        <?php else: ?>
          <span class="nav-btn disabled">Next <i class="fas fa-step-forward"></i></span>
        <?php endif; ?>
      </div>

      <!-- Video Details -->
      <div class="video-details">
        <div class="course-badge">
          <i class="fas fa-graduation-cap"></i>
          <?= htmlspecialchars($video['course_title']) ?>
        </div>
        <h1 class="video-title-large"><?= htmlspecialchars($video['title']) ?></h1>
        <div class="video-meta-large">
          <span><i class="fas fa-play-circle"></i> Video <?= $currentIndex + 1 ?> of <?= count($videos) ?></span>
          <span><i class="far fa-clock"></i> <?= !empty($video['duration']) ? formatDuration($video['duration']) : 'Unknown duration' ?></span>
          <span><i class="fas fa-list-ul"></i> <?= count($videos) ?> Videos in Course</span>
        </div>
        <p class="text-muted">
          <?php if (!empty($video['description'])): ?>
            <?= nl2br(htmlspecialchars($video['description'])) ?>
          <?php else: ?>
            Watch this video to continue your learning journey in <?= htmlspecialchars($video['course_title']) ?>.
          <?php endif; ?>
        </p>
      </div>

      <!-- Video Q&A Section -->
      <div class="qa-section">
        <div class="qa-section-title">
          <i class="fas fa-comments"></i>
          Questions about this video
          <?php if (count($videoQuestions) > 0): ?>
            <span style="background:#2a2a2a;color:#a78bfa;font-size:12px;padding:2px 10px;border-radius:20px;font-weight:700;"><?= count($videoQuestions) ?></span>
          <?php endif; ?>
        </div>

        <?php if (isset($_GET['question_sent'])): ?>
          <div class="qa-success-banner"><i class="fas fa-check-circle"></i> Your question was sent! The instructor will reply soon.</div>
        <?php endif; ?>

        <!-- Ask a question form -->
        <div class="qa-ask-form">
          <div style="font-size:13px;font-weight:700;color:#aaa;margin-bottom:10px;"><i class="fas fa-pen" style="color:#a78bfa;margin-right:6px;"></i>Ask a question about this video</div>
          <form action="ask_question.php" method="post">
            <input type="hidden" name="course_id" value="<?= (int)$video['course_id'] ?>">
            <input type="hidden" name="video_id"  value="<?= $videoId ?>">
            <textarea name="question" placeholder="Type your question here... e.g. 'I didn't understand the part at 2:30, can you explain?'" required></textarea>
            <button type="submit" class="qa-submit-btn"><i class="fas fa-paper-plane"></i> Ask Question</button>
          </form>
        </div>

        <!-- Existing questions -->
        <?php if (empty($videoQuestions)): ?>
          <div style="text-align:center;padding:28px;color:#444;font-size:13px;">
            <i class="fas fa-comment-slash" style="font-size:28px;display:block;margin-bottom:10px;color:#333;"></i>
            No questions yet for this video. Be the first to ask!
          </div>
        <?php else: ?>
          <div class="qa-list">
            <?php foreach ($videoQuestions as $q):
              $isMyQuestion = ((int)$q['student_id'] === (int)$userId);
              $watchRedirect = 'watch.php?video_id=' . $videoId;
            ?>
              <div class="qa-item" id="vqa-<?= (int)$q['id'] ?>">
                <div class="qa-item-question">
                  <div class="qa-item-meta">
                    <div class="qa-avatar"><?= strtoupper(substr($q['student_name'] ?: 'S', 0, 1)) ?></div>
                    <span class="qa-student-name"><?= htmlspecialchars($q['student_name'] ?: 'Student') ?></span>
                    <span class="qa-time"><?= date('M d, Y', strtotime($q['created_at'])) ?></span>
                  </div>
                  <div class="qa-text" id="vqa-text-<?= (int)$q['id'] ?>"><?= nl2br(htmlspecialchars($q['question'])) ?></div>

                  <?php if ($isMyQuestion): ?>
                    <div class="qa-actions">
                      <button class="qa-act-btn qa-act-btn-edit" onclick="toggleEditVQ(<?= (int)$q['id'] ?>)">
                        <i class="fas fa-pen"></i> Edit
                      </button>
                      <form action="delete_question.php" method="post" style="margin:0;"
                            onsubmit="return confirm('Delete this question?')">
                        <input type="hidden" name="id"       value="<?= (int)$q['id'] ?>">
                        <input type="hidden" name="redirect" value="<?= htmlspecialchars($watchRedirect) ?>">
                        <button type="submit" class="qa-act-btn qa-act-btn-delete"><i class="fas fa-trash"></i> Delete</button>
                      </form>
                    </div>
                    <div class="qa-edit-form" id="vqa-edit-<?= (int)$q['id'] ?>">
                      <form action="edit_question.php" method="post">
                        <input type="hidden" name="id"       value="<?= (int)$q['id'] ?>">
                        <input type="hidden" name="redirect" value="<?= htmlspecialchars($watchRedirect) ?>">
                        <textarea name="question" required><?= htmlspecialchars($q['question']) ?></textarea>
                        <button type="submit" class="qa-edit-save">Save</button>
                        <button type="button" class="qa-edit-cancel" onclick="toggleEditVQ(<?= (int)$q['id'] ?>)">Cancel</button>
                      </form>
                    </div>
                  <?php endif; ?>
                </div>

                <div class="qa-item-reply">
                  <?php if ($q['reply']): ?>
                    <div class="qa-reply-label"><i class="fas fa-chalkboard-teacher"></i> Instructor Reply</div>
                    <div class="qa-reply-text"><?= nl2br(htmlspecialchars($q['reply'])) ?></div>
                    <div style="font-size:11px;color:#444;margin-top:6px;"><?= date('M d, Y', strtotime($q['replied_at'])) ?> · <?= htmlspecialchars($q['instructor_name'] ?: 'Instructor') ?></div>
                  <?php else: ?>
                    <div class="qa-no-reply"><i class="fas fa-clock" style="margin-right:5px;"></i>Awaiting instructor reply…</div>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Playlist Sidebar -->
    <aside class="playlist-sidebar">
      <div class="playlist-header">
        <h5><i class="fas fa-list"></i> Course Playlist</h5>
        <small><?= count($videos) ?> videos • <?= htmlspecialchars($video['course_title']) ?></small>
        <div class="playlist-progress">
          <div class="playlist-progress-fill" id="sidebarProgressFill" style="width: <?= $progressPct ?>%"></div>
        </div>
        <div class="playlist-progress-label">
          <span id="completedCount"><?= $completedVideos ?></span> / <?= $totalVideos ?> completed
        </div>
      </div>

      <div class="playlist-items">
        <?php foreach ($videos as $index => $v):
          $isActive = $v['id'] == $videoId;
        ?>
          <a href="?video_id=<?= $v['id'] ?>" 
             class="playlist-item <?= $isActive ? 'active' : '' ?>"
             id="playlist-item-<?= $v['id'] ?>">
            <div class="playlist-thumb-wrap">
              <img src="<?= htmlspecialchars($v['thumb_url'] ?: 'https://via.placeholder.com/100x60?text=Video') ?>"
                   class="playlist-thumb" alt="">
              <span class="playlist-number"><?= $index + 1 ?></span>
              <?php if ($v['completed']): ?>
                <span class="completed-badge" id="badge-<?= $v['id'] ?>">
                  <i class="fas fa-check"></i>
                </span>
              <?php else: ?>
                <span class="completed-badge" id="badge-<?= $v['id'] ?>" style="display:none;">
                  <i class="fas fa-check"></i>
                </span>
              <?php endif; ?>
            </div>
            <div class="playlist-info">
              <div class="playlist-title"><?= htmlspecialchars($v['title']) ?></div>
              <div class="playlist-duration">
                <i class="far fa-clock me-1"></i>
                <?= !empty($v['duration']) ? formatDuration($v['duration']) : 'Unknown' ?>
              </div>
            </div>
            <?php if ($isActive): ?>
              <i class="fas fa-volume-up playing-icon"></i>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
    </aside>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    function toggleEditVQ(id) {
      const form = document.getElementById('vqa-edit-' + id);
      const text = document.getElementById('vqa-text-' + id);
      form.classList.toggle('open');
      if (text) text.style.display = form.classList.contains('open') ? 'none' : '';
    }
    // ─── Config passed from PHP ───────────────────────────────────────────────
    const VIDEO_ID        = <?= $videoId ?>;
    const IS_YOUTUBE      = <?= $isYoutube ? 'true' : 'false' ?>;
    const ALREADY_DONE    = <?= $currentCompleted ? 'true' : 'false' ?>;
    const SAVED_PROGRESS  = <?= $currentProgress ?>;   // seconds
    const TOTAL_VIDEOS    = <?= $totalVideos ?>;
    let   completedCount  = <?= $completedVideos ?>;
    let   isCompleted     = ALREADY_DONE;

    // ─── Save progress to server ──────────────────────────────────────────────
    function saveProgress(progressSeconds, completed) {
      fetch('save_progress.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          video_id: VIDEO_ID,
          progress_seconds: Math.floor(progressSeconds),
          completed: completed ? 1 : 0
        })
      })
      .then(r => r.json())
      .then(data => {
        if (data.success && data.completed && !isCompleted) {
          markCompletedUI();
        }
      })
      .catch(console.error);
    }

    // ─── Update UI when video is marked complete ──────────────────────────────
    function markCompletedUI() {
      if (isCompleted) return;
      isCompleted = true;

      // Mark Complete button
      const btn = document.getElementById('markCompleteBtn');
      btn.classList.add('done');
      btn.innerHTML = '<i class="fas fa-check-circle"></i> Completed';

      // Show green badge in playlist
      const badge = document.getElementById('badge-' + VIDEO_ID);
      if (badge) badge.style.display = 'flex';

      // Update counts
      completedCount++;
      document.getElementById('completedCount').textContent = completedCount;
      const pct = Math.round((completedCount / TOTAL_VIDEOS) * 100);
      document.getElementById('sidebarProgressFill').style.width = pct + '%';
      document.getElementById('navProgressFill').style.width     = pct + '%';

      // Show toast
      showToast();
    }

    function showToast() {
      const toast = document.getElementById('completionToast');
      toast.classList.add('show');
      setTimeout(() => toast.classList.remove('show'), 3500);
    }

    // ─── Manual "Mark as Complete" button ────────────────────────────────────
    function markComplete() {
      if (isCompleted) return;
      saveProgress(0, true);
      markCompletedUI();
    }

    // ─── YouTube IFrame API ───────────────────────────────────────────────────
    <?php if ($isYoutube): ?>
    let ytPlayer;
    let progressInterval;
    let ytCompleteTriggered = false;

    // Load YouTube IFrame API
    const tag = document.createElement('script');
    tag.src = 'https://www.youtube.com/iframe_api';
    document.head.appendChild(tag);

    function onYouTubeIframeAPIReady() {
      // Extract video ID from embed URL
      const embedUrl = '<?= addslashes($video['embed_url']) ?>';
      const match = embedUrl.match(/embed\/([^?&"'>]+)/);
      const ytVideoId = match ? match[1] : '';

      ytPlayer = new YT.Player('ytPlayer', {
        width: '100%',
        height: '100%',
        videoId: ytVideoId,
        playerVars: {
          autoplay: 1,
          rel: 0,
          start: SAVED_PROGRESS > 0 ? SAVED_PROGRESS : 0
        },
        events: {
          onReady: onPlayerReady,
          onStateChange: onPlayerStateChange
        }
      });
    }

    function onPlayerReady(event) {
      // Auto-save progress every 10 seconds while playing
      progressInterval = setInterval(() => {
        if (ytPlayer && ytPlayer.getPlayerState() === YT.PlayerState.PLAYING) {
          const current  = ytPlayer.getCurrentTime();
          const duration = ytPlayer.getDuration();
          // Mark complete if user watched >= 80%
          const pct = duration > 0 ? (current / duration) : 0;
          if (pct >= 0.80 && !isCompleted && !ytCompleteTriggered) {
            ytCompleteTriggered = true;
            saveProgress(current, true);
            markCompletedUI();
          } else {
            saveProgress(current, false);
          }
        }
      }, 10000);
    }

    function onPlayerStateChange(event) {
      // Also trigger on video end (state = 0)
      if (event.data === YT.PlayerState.ENDED) {
        const duration = ytPlayer.getDuration();
        saveProgress(duration, true);
        if (!isCompleted) markCompletedUI();
      }
    }
    <?php endif; ?>

    // ─── Local Video Tracking ─────────────────────────────────────────────────
    <?php if (!empty($video['video_path'])): ?>
    const localVideo = document.getElementById('localVideo');

    if (localVideo) {
      // Resume from saved position
      if (SAVED_PROGRESS > 10) {
        localVideo.currentTime = SAVED_PROGRESS;
      }

      // Save progress every 10 seconds during playback
      let localInterval;
      localVideo.addEventListener('play', () => {
        localInterval = setInterval(() => {
          const pct = localVideo.duration > 0 ? (localVideo.currentTime / localVideo.duration) : 0;
          if (pct >= 0.80 && !isCompleted) {
            saveProgress(localVideo.currentTime, true);
            markCompletedUI();
          } else {
            saveProgress(localVideo.currentTime, false);
          }
        }, 10000);
      });

      localVideo.addEventListener('pause', () => {
        clearInterval(localInterval);
        saveProgress(localVideo.currentTime, false);
      });

      localVideo.addEventListener('ended', () => {
        clearInterval(localInterval);
        saveProgress(localVideo.duration, true);
        if (!isCompleted) markCompletedUI();
      });
    }
    <?php endif; ?>

    // ─── Already completed on load ────────────────────────────────────────────
    if (ALREADY_DONE) {
      const btn = document.getElementById('markCompleteBtn');
      if (btn) {
        btn.classList.add('done');
        btn.innerHTML = '<i class="fas fa-check-circle"></i> Completed';
      }
    }
  </script>
</body>
</html>

<?php
function formatDuration($seconds) {
    if (!$seconds) return '0:00';
    $mins = floor($seconds / 60);
    $secs = $seconds % 60;
    return $mins . ':' . str_pad($secs, 2, '0', STR_PAD_LEFT);
}
?>