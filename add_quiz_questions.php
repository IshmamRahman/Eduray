<?php
session_start();
require __DIR__ . '/db.php';

$userId       = $_SESSION['user_id']       ?? null;
$isInstructor = !empty($_SESSION['is_instructor']);
$isAdmin      = !empty($_SESSION['is_admin']);

if (!$userId || (!$isInstructor && !$isAdmin)) {
    http_response_code(403);
    die('Forbidden');
}

$quiz_id = (int)($_GET['quiz_id'] ?? 0);
if (!$quiz_id) die('Quiz ID missing.');

// ── Fetch quiz + verify ownership ─────────────────────────────────────────
if ($isAdmin) {
    $r = $conn->prepare("SELECT q.*, c.instructor_id FROM quizzes q JOIN courses c ON c.id=q.course_id WHERE q.id=?");
    $r->bind_param("i", $quiz_id);
} else {
    $r = $conn->prepare("SELECT q.*, c.instructor_id FROM quizzes q JOIN courses c ON c.id=q.course_id WHERE q.id=? AND c.instructor_id=?");
    $r->bind_param("ii", $quiz_id, $userId);
}
$r->execute();
$quiz = $r->get_result()->fetch_assoc();
$r->close();
if (!$quiz) { header('Location: instructor_dashboard.php'); exit; }
$courseId = (int)$quiz['course_id'];

$success = '';
$error   = '';

// ── DELETE QUIZ ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_quiz') {
    $conn->query("DELETE FROM quiz_answer_details WHERE quiz_id=$quiz_id");
    $conn->query("DELETE FROM quiz_attempts      WHERE quiz_id=$quiz_id");
    $conn->query("DELETE FROM quiz_questions     WHERE quiz_id=$quiz_id");
    $conn->query("DELETE FROM quizzes            WHERE id=$quiz_id");
    header("Location: instructor_dashboard.php?cid=$courseId&quiz_deleted=1");
    exit;
}

// ── DELETE QUESTION ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_question') {
    $qid = (int)($_POST['question_id'] ?? 0);
    if ($qid) {
        $conn->query("DELETE FROM quiz_answer_details WHERE question_id=$qid AND quiz_id=$quiz_id");
        $conn->query("DELETE FROM quiz_questions WHERE id=$qid AND quiz_id=$quiz_id");
    }
    header("Location: add_quiz_questions.php?quiz_id=$quiz_id&deleted=1");
    exit;
}

// ── EDIT QUESTION ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_question') {
    $qid     = (int)($_POST['question_id'] ?? 0);
    $qtext   = trim($_POST['question']      ?? '');
    $optA    = trim($_POST['option_a']      ?? '');
    $optB    = trim($_POST['option_b']      ?? '');
    $optC    = trim($_POST['option_c']      ?? '');
    $optD    = trim($_POST['option_d']      ?? '');
    $correct = strtolower(trim($_POST['correct_option'] ?? ''));

    if ($qid && $qtext && $optA && $optB && in_array($correct, ['a','b','c','d'])) {
        if (($correct === 'c' && $optC === '') || ($correct === 'd' && $optD === '')) {
            $error = "The selected correct option cannot be empty.";
        } else {
            $upd = $conn->prepare("UPDATE quiz_questions SET question=?,option_a=?,option_b=?,option_c=?,option_d=?,correct_option=? WHERE id=? AND quiz_id=?");
            $upd->bind_param("ssssssii", $qtext, $optA, $optB, $optC, $optD, $correct, $qid, $quiz_id);
            $upd->execute();
            $upd->close();
            header("Location: add_quiz_questions.php?quiz_id=$quiz_id&edited=1");
            exit;
        }
    } else {
        $error = "Question, Option A, Option B and a correct option are required.";
    }
}

// ── ADD QUESTION ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_question') {
    $qtext   = trim($_POST['question']       ?? '');
    $optA    = trim($_POST['opt_a']          ?? '');
    $optB    = trim($_POST['opt_b']          ?? '');
    $optC    = trim($_POST['opt_c']          ?? '');
    $optD    = trim($_POST['opt_d']          ?? '');
    $correct = strtolower(trim($_POST['correct_option'] ?? ''));

    if ($qtext && $optA && $optB && in_array($correct, ['a','b','c','d'])) {
        if (($correct === 'c' && $optC === '') || ($correct === 'd' && $optD === '')) {
            $error = "The selected correct option cannot be empty.";
        } else {
            $stmt = $conn->prepare("INSERT INTO quiz_questions (quiz_id,question,option_a,option_b,option_c,option_d,correct_option) VALUES (?,?,?,?,?,?,?)");
            $stmt->bind_param("issssss", $quiz_id, $qtext, $optA, $optB, $optC, $optD, $correct);
            $stmt->execute();
            $stmt->close();
            header("Location: add_quiz_questions.php?quiz_id=$quiz_id&added=1");
            exit;
        }
    } else {
        $error = "Question, Option A, Option B and a correct option are required.";
    }
}

// ── Fetch all questions ────────────────────────────────────────────────────
$questions = $conn->query("SELECT * FROM quiz_questions WHERE quiz_id=$quiz_id ORDER BY id")->fetch_all(MYSQLI_ASSOC);

$editId = (int)($_GET['edit'] ?? 0);

if (isset($_GET['added']))   $success = 'Question added successfully!';
if (isset($_GET['edited']))  $success = 'Question updated successfully!';
if (isset($_GET['deleted'])) $success = 'Question deleted.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Quiz Editor – <?= htmlspecialchars($quiz['title']) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root{
      --p:#5f259f;--p-dark:#3d1066;--p-lt:#f0ebff;
      --accent:#ff6b00;--gold:#ffe600;
      --bg:#f4f1fa;--white:#fff;--border:#e8e3f5;
      --text:#1e1a2e;--muted:#7b7a99;--green:#16a34a;

    }
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;}
    a{text-decoration:none;}

    /* ── Topbar ── */
    .site-navbar{background:linear-gradient(135deg,var(--p),var(--accent));padding:13px 28px;display:flex;align-items:center;justify-content:space-between;gap:14px;position:sticky;top:0;z-index:200;box-shadow:0 2px 12px rgba(0,0,0,.15);}
    .site-navbar .nav-brand{font-size:20px;font-weight:800;color:#fff;text-decoration:none;display:flex;align-items:center;gap:6px;}
    .site-navbar .brand-accent{color:var(--gold);}
    .btn-back-dash{display:inline-flex;align-items:center;gap:7px;padding:8px 20px;border-radius:25px;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);color:#fff;font-size:13px;font-weight:600;transition:background .2s;text-decoration:none;}
    .btn-back-dash:hover{background:rgba(255,255,255,.28);color:#fff;}

    .page-wrap{max-width:860px;margin:0 auto;padding:28px 20px;}

    /* ── Page header ── */
    .page-hd{background:var(--white);border:1px solid var(--border);border-radius:14px;padding:20px 24px;margin-bottom:22px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;}
    .page-hd-icon{width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,var(--p),var(--accent));display:flex;align-items:center;justify-content:center;font-size:20px;color:#fff;flex-shrink:0;}
    .page-hd-title{font-size:18px;font-weight:800;margin-bottom:3px;}
    .page-hd-meta{font-size:12.5px;color:var(--muted);display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
    .timer-badge{display:inline-flex;align-items:center;gap:5px;padding:2px 10px;border-radius:20px;font-size:11.5px;font-weight:700;background:#fef3c7;color:#92400e;border:1px solid #fde68a;}

    /* ── Card ── */
    .fcard{background:var(--white);border:1px solid var(--border);border-radius:12px;overflow:hidden;margin-bottom:20px;}
    .fcard-head{padding:14px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;}
    .fcard-hicon{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:13px;}
    .fcard-head h5{font-size:14.5px;font-weight:700;margin:0;}
    .fcard-body{padding:20px;}

    /* ── Form controls ── */
    .form-label{font-size:12.5px;font-weight:600;color:#444;margin-bottom:5px;display:block;}
    .form-control,.form-select{border-radius:7px;border:1.5px solid var(--border);font-size:13.5px;padding:9px 13px;font-family:'Plus Jakarta Sans',sans-serif;transition:border-color .2s;width:100%;background:#fff;}
    .form-control:focus,.form-select:focus{border-color:var(--p);box-shadow:0 0 0 3px rgba(95,37,159,.08);outline:none;}

    /* ── Option rows in form ── */
    .opt-row{display:flex;align-items:center;gap:10px;margin-bottom:9px;}
    .opt-pill{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:12px;color:#fff;flex-shrink:0;}
    .pill-a,.pill-b,.pill-c,.pill-d{background:var(--p);}

    /* ── Buttons ── */
    .abtn{display:inline-flex;align-items:center;gap:7px;padding:9px 20px;border-radius:7px;font-size:13.5px;font-weight:600;border:none;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;white-space:nowrap;transition:opacity .15s;text-decoration:none;}
    .abtn:hover{opacity:.88;}
    .abtn-purple{background:var(--p);color:#fff;}
    .abtn-green{background:var(--green);color:#fff;}
    .abtn-red{background:#dc2626;color:#fff;}
    .abtn-gray{background:#6b7280;color:#fff;}
    .abtn-sm{padding:6px 14px;font-size:12.5px;}
    .tbtn{display:inline-flex;align-items:center;gap:4px;padding:5px 11px;border-radius:6px;font-size:12px;font-weight:700;cursor:pointer;border:1.5px solid;background:transparent;font-family:'Plus Jakarta Sans',sans-serif;transition:background .15s,color .15s;text-decoration:none;}
    .tbtn-edit{color:var(--p);border-color:var(--p);} .tbtn-edit:hover{background:var(--p);color:#fff;}
    .tbtn-del{color:#dc2626;border-color:#dc2626;}    .tbtn-del:hover{background:#dc2626;color:#fff;}

    /* ── Question cards ── */
    .qcard{background:var(--white);border:1.5px solid var(--border);border-radius:12px;margin-bottom:12px;overflow:hidden;transition:border-color .2s,box-shadow .2s;}
    .qcard:hover{border-color:#c4b5fd;}
    .qcard.editing{border-color:var(--p);box-shadow:0 0 0 3px rgba(95,37,159,.1);}
    .qcard-top{padding:14px 16px;display:flex;align-items:flex-start;justify-content:space-between;gap:12px;}
    .q-num{width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,var(--p),var(--accent));color:#fff;font-size:12px;font-weight:800;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:2px;}
    .q-text{font-size:13.5px;font-weight:600;flex:1;line-height:1.55;}
    .qcard-btns{display:flex;gap:6px;flex-shrink:0;}

    /* ── Option display boxes ── */
    .qcard-opts{padding:0 16px 14px;display:grid;grid-template-columns:1fr 1fr;gap:8px;}
    @media(max-width:540px){.qcard-opts{grid-template-columns:1fr;}}
    .opt-box{display:flex;align-items:center;gap:9px;padding:9px 12px;border-radius:8px;border:1.5px solid var(--border);font-size:13px;color:#555;}
    .opt-box.is-correct{border-color:var(--green);background:#f0fdf4;font-weight:700;color:#15803d;}
    .opt-box-letter{width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:12px;color:#fff;flex-shrink:0;}
    .ol-a,.ol-b,.ol-c,.ol-d{background:var(--p);}
    .correct-tag{margin-left:auto;font-size:10.5px;font-weight:800;color:var(--green);white-space:nowrap;}

    /* ── Inline edit form ── */
    .edit-form-wrap{background:#f9f7ff;border-top:1.5px solid var(--border);padding:18px 16px;}

    /* ── Danger zone ── */
    .danger-zone{background:#fff5f5;border:1.5px solid #fecaca;border-radius:12px;padding:18px 20px;margin-top:28px;display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;}
    .danger-zone h6{font-size:14px;font-weight:700;color:#dc2626;margin-bottom:3px;}
    .danger-zone p{font-size:12.5px;color:#b91c1c;margin:0;}

    /* ── Modals ── */
    .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center;}
    .modal-overlay.show{display:flex;}
    .modal-box{background:#fff;border-radius:16px;padding:28px;max-width:400px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,.2);animation:modalIn .25s ease;text-align:center;}
    @keyframes modalIn{from{transform:scale(.92);opacity:0}to{transform:scale(1);opacity:1}}
    .modal-icon{width:54px;height:54px;border-radius:50%;background:#fee2e2;display:flex;align-items:center;justify-content:center;font-size:22px;margin:0 auto 14px;}
    .modal-title{font-size:16px;font-weight:800;margin-bottom:7px;}
    .modal-msg{font-size:13px;color:#666;line-height:1.6;margin-bottom:20px;}
    .modal-actions{display:flex;gap:10px;justify-content:center;flex-wrap:wrap;}

    /* ── Alerts ── */
    .alert{padding:12px 16px;border-radius:8px;font-size:13.5px;font-weight:600;margin-bottom:18px;display:flex;align-items:center;gap:9px;}
    .alert-success{background:#dcfce7;color:#16a34a;border:1px solid #86efac;}
    .alert-danger{background:#fee2e2;color:#dc2626;border:1px solid #fca5a5;}

    .empty-state{text-align:center;padding:42px 20px;color:var(--muted);}
    .empty-state i{font-size:30px;color:#ddd;display:block;margin-bottom:10px;}
    .empty-state p{font-size:13.5px;}
  </style>
</head>
<body>

<!-- ═══ DELETE QUIZ MODAL ═══ -->
<div class="modal-overlay" id="deleteQuizModal">
  <div class="modal-box">
    <div class="modal-icon">🗑️</div>
    <div class="modal-title">Delete Entire Quiz?</div>
    <div class="modal-msg">
      This will permanently delete <strong><?= htmlspecialchars($quiz['title']) ?></strong>,
      all <?= count($questions) ?> question<?= count($questions) !== 1 ? 's' : '' ?>,
      and every student attempt record. This <strong>cannot be undone</strong>.
    </div>
    <div class="modal-actions">
      <form method="post">
        <input type="hidden" name="action" value="delete_quiz">
        <button type="submit" class="abtn abtn-red"><i class="fas fa-trash"></i> Yes, Delete Quiz</button>
      </form>
      <button class="abtn abtn-gray" onclick="closeModal('deleteQuizModal')">Cancel</button>
    </div>
  </div>
</div>

<!-- ═══ DELETE QUESTION MODAL ═══ -->
<div class="modal-overlay" id="deleteQModal">
  <div class="modal-box">
    <div class="modal-icon">🗑️</div>
    <div class="modal-title">Delete Question?</div>
    <div class="modal-msg">This question and all student answers for it will be permanently removed.</div>
    <div class="modal-actions">
      <form method="post" id="deleteQForm">
        <input type="hidden" name="action" value="delete_question">
        <input type="hidden" name="question_id" id="deleteQId">
        <button type="submit" class="abtn abtn-red"><i class="fas fa-trash"></i> Yes, Delete</button>
      </form>
      <button class="abtn abtn-gray" onclick="closeModal('deleteQModal')">Cancel</button>
    </div>
  </div>
</div>

<nav class="site-navbar">
  <a href="index.php" class="nav-brand">📚 <span class="brand-accent">EDURAY</span></a>
  <a href="instructor_dashboard.php?cid=<?= $courseId ?>" class="btn-back-dash">
    <i class="fas fa-arrow-left"></i> Back to Dashboard
  </a>
</nav>

<div class="page-wrap">

  <?php if ($success): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- Page header -->
  <div class="page-hd">
    <div class="page-hd-icon"><i class="fas fa-clipboard-check"></i></div>
    <div>
      <div class="page-hd-title"><?= htmlspecialchars($quiz['title']) ?></div>
      <div class="page-hd-meta">
        <span><?= count($questions) ?> question<?= count($questions) !== 1 ? 's' : '' ?></span>
        <?php $tl = (int)($quiz['time_limit'] ?? 0); ?>
        <?php if ($tl > 0): ?>
          <span class="timer-badge"><i class="fas fa-clock"></i><?= $tl ?> min limit</span>
        <?php else: ?>
          <span style="color:#9ca3af;">No time limit</span>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ═══ ADD QUESTION ═══ -->
  <div class="fcard">
    <div class="fcard-head">
      <div class="fcard-hicon" style="background:#fef9c3;color:#b45309;"><i class="fas fa-plus"></i></div>
      <h5>Add New Question</h5>
    </div>
    <div class="fcard-body">
      <form method="post" id="addForm">
        <input type="hidden" name="action" value="add_question">

        <div style="margin-bottom:14px;">
          <label class="form-label">Question Text</label>
          <input type="text" name="question" class="form-control" placeholder="e.g. What does HTML stand for?" required>
        </div>

        <div style="margin-bottom:14px;">
          <label class="form-label">Options</label>
          <div class="opt-row"><div class="opt-pill pill-a">A</div><input type="text" name="opt_a" class="form-control add-opt" data-opt="a" placeholder="Option A" required></div>
          <div class="opt-row"><div class="opt-pill pill-b">B</div><input type="text" name="opt_b" class="form-control add-opt" data-opt="b" placeholder="Option B" required></div>
          <div class="opt-row"><div class="opt-pill pill-c">C</div><input type="text" name="opt_c" class="form-control add-opt" data-opt="c" placeholder="Option C (optional)"></div>
          <div class="opt-row"><div class="opt-pill pill-d">D</div><input type="text" name="opt_d" class="form-control add-opt" data-opt="d" placeholder="Option D (optional)"></div>
        </div>

        <div style="margin-bottom:18px;max-width:300px;">
          <label class="form-label">Correct Answer</label>
          <select name="correct_option" class="form-select" id="addCorrectSel" required>
            <option value="">— Select correct option —</option>
            <option value="a">A</option>
            <option value="b">B</option>
          </select>
        </div>

        <button type="submit" class="abtn abtn-purple"><i class="fas fa-plus"></i> Add Question</button>
      </form>
    </div>
  </div>

  <!-- ═══ EXISTING QUESTIONS ═══ -->
  <div class="fcard">
    <div class="fcard-head">
      <div class="fcard-hicon" style="background:var(--p-lt);color:var(--p);"><i class="fas fa-list"></i></div>
      <h5>All Questions <span style="color:var(--muted);font-weight:500;font-size:13px;">(<?= count($questions) ?>)</span></h5>
    </div>
    <div class="fcard-body" style="padding:16px;">

      <?php if (empty($questions)): ?>
        <div class="empty-state">
          <i class="fas fa-clipboard"></i>
          <p>No questions yet. Add your first question above.</p>
        </div>

      <?php else: ?>
        <?php foreach ($questions as $idx => $q):
          $correct   = strtolower($q['correct_option']);
          $isEditing = ($editId === (int)$q['id']);
          $optMeta   = [
            'a' => ['cls'=>'ol-a','label'=>'A'],
            'b' => ['cls'=>'ol-b','label'=>'B'],
            'c' => ['cls'=>'ol-c','label'=>'C'],
            'd' => ['cls'=>'ol-d','label'=>'D'],
          ];
        ?>
          <div class="qcard <?= $isEditing ? 'editing' : '' ?>" id="qcard-<?= $q['id'] ?>">

            <!-- Header row -->
            <div class="qcard-top">
              <span class="q-num"><?= $idx + 1 ?></span>
              <span class="q-text"><?= htmlspecialchars($q['question']) ?></span>
              <div class="qcard-btns">
                <a href="?quiz_id=<?= $quiz_id ?>&edit=<?= $q['id'] ?>#qcard-<?= $q['id'] ?>" class="tbtn tbtn-edit">
                  <i class="fas fa-pen"></i> Edit
                </a>
                <button type="button" class="tbtn tbtn-del" onclick="openDeleteQ(<?= $q['id'] ?>)">
                  <i class="fas fa-trash"></i>
                </button>
              </div>
            </div>

            <!-- Read-only options grid -->
            <?php if (!$isEditing): ?>
            <div class="qcard-opts">
              <?php foreach (['a','b','c','d'] as $opt):
                $text = $q['option_' . $opt];
                if ($text === '') continue; // skip empty options
              ?>
                <div class="opt-box <?= $correct === $opt ? 'is-correct' : '' ?>">
                  <div class="opt-box-letter <?= $optMeta[$opt]['cls'] ?>"><?= $optMeta[$opt]['label'] ?></div>
                  <span style="flex:1;"><?= htmlspecialchars($text) ?></span>
                  <?php if ($correct === $opt): ?>
                    <span class="correct-tag"><i class="fas fa-check"></i> Correct</span>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Inline edit form -->
            <?php if ($isEditing): ?>
            <div class="edit-form-wrap">
              <form method="post">
                <input type="hidden" name="action"      value="edit_question">
                <input type="hidden" name="question_id" value="<?= $q['id'] ?>">

                <div style="margin-bottom:12px;">
                  <label class="form-label">Question Text</label>
                  <input type="text" name="question" class="form-control"
                         value="<?= htmlspecialchars($q['question']) ?>" required>
                </div>

                <div style="margin-bottom:12px;">
                  <label class="form-label">Options</label>
                  <div class="opt-row"><div class="opt-pill pill-a">A</div><input type="text" name="option_a" class="form-control edit-opt" data-opt="a" value="<?= htmlspecialchars($q['option_a']) ?>" placeholder="Option A" required></div>
                  <div class="opt-row"><div class="opt-pill pill-b">B</div><input type="text" name="option_b" class="form-control edit-opt" data-opt="b" value="<?= htmlspecialchars($q['option_b']) ?>" placeholder="Option B" required></div>
                  <div class="opt-row"><div class="opt-pill pill-c">C</div><input type="text" name="option_c" class="form-control edit-opt" data-opt="c" value="<?= htmlspecialchars($q['option_c']) ?>" placeholder="Option C (optional)"></div>
                  <div class="opt-row"><div class="opt-pill pill-d">D</div><input type="text" name="option_d" class="form-control edit-opt" data-opt="d" value="<?= htmlspecialchars($q['option_d']) ?>" placeholder="Option D (optional)"></div>
                </div>

                <div style="margin-bottom:16px;max-width:300px;">
                  <label class="form-label">Correct Answer</label>
                  <select name="correct_option" class="form-select edit-correct-sel" required>
                    <option value="">— Select —</option>
                    <option value="a" <?= $correct==='a'?'selected':'' ?>>A – <?= htmlspecialchars($q['option_a']) ?></option>
                    <option value="b" <?= $correct==='b'?'selected':'' ?>>B – <?= htmlspecialchars($q['option_b']) ?></option>
                    <?php if ($q['option_c'] !== ''): ?><option value="c" <?= $correct==='c'?'selected':'' ?>>C – <?= htmlspecialchars($q['option_c']) ?></option><?php endif; ?>
                    <?php if ($q['option_d'] !== ''): ?><option value="d" <?= $correct==='d'?'selected':'' ?>>D – <?= htmlspecialchars($q['option_d']) ?></option><?php endif; ?>
                  </select>
                </div>

                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                  <button type="submit" class="abtn abtn-green abtn-sm"><i class="fas fa-save"></i> Save Changes</button>
                  <a href="?quiz_id=<?= $quiz_id ?>" class="abtn abtn-gray abtn-sm"><i class="fas fa-times"></i> Cancel</a>
                </div>
              </form>
            </div>
            <?php endif; ?>

          </div><!-- /qcard -->
        <?php endforeach; ?>
      <?php endif; ?>

    </div>
  </div>

  <!-- ═══ DANGER ZONE ═══ -->
  <div class="danger-zone">
    <div>
      <h6><i class="fas fa-exclamation-triangle"></i> Delete This Quiz</h6>
      <p>Permanently deletes the quiz, all its questions, and all student attempt records.</p>
    </div>
    <button class="abtn abtn-red abtn-sm" onclick="openModal('deleteQuizModal')">
      <i class="fas fa-trash"></i> Delete Quiz
    </button>
  </div>

</div><!-- /page-wrap -->

<script>
// ── Modal helpers ─────────────────────────────────────────────────────────
function openModal(id)  { document.getElementById(id).classList.add('show'); }
function closeModal(id) { document.getElementById(id).classList.remove('show'); }
['deleteQuizModal','deleteQModal'].forEach(id => {
  document.getElementById(id).addEventListener('click', function(e) {
    if (e.target === this) closeModal(id);
  });
});
function openDeleteQ(qid) {
  document.getElementById('deleteQId').value = qid;
  openModal('deleteQModal');
}

// ── Add form: rebuild correct-option dropdown as user types ───────────────
(function () {
  const inputs = document.querySelectorAll('.add-opt');
  const sel    = document.getElementById('addCorrectSel');
  const labs   = { a:'A', b:'B', c:'C', d:'D' };
  function rebuild() {
    const prev = sel.value;
    sel.innerHTML = '<option value="">— Select correct option —</option>';
    inputs.forEach(inp => {
      const val = inp.value.trim(), opt = inp.dataset.opt;
      if (val) {
        const o = document.createElement('option');
        o.value = opt; o.textContent = labs[opt] + ' – ' + val;
        if (prev === opt) o.selected = true;
        sel.appendChild(o);
      }
    });
  }
  inputs.forEach(i => i.addEventListener('input', rebuild));
  rebuild();
})();

// ── Edit form: rebuild correct-option dropdown as user types ──────────────
document.querySelectorAll('.edit-form-wrap').forEach(wrap => {
  const inputs = wrap.querySelectorAll('.edit-opt');
  const sel    = wrap.querySelector('.edit-correct-sel');
  if (!sel) return;
  const labs = { a:'A', b:'B', c:'C', d:'D' };
  function rebuild() {
    const prev = sel.value;
    sel.innerHTML = '<option value="">— Select —</option>';
    inputs.forEach(inp => {
      const val = inp.value.trim(), opt = inp.dataset.opt;
      if (val) {
        const o = document.createElement('option');
        o.value = opt; o.textContent = labs[opt] + ' – ' + val;
        if (prev === opt) o.selected = true;
        sel.appendChild(o);
      }
    });
  }
  inputs.forEach(i => i.addEventListener('input', rebuild));
});

// Scroll editing card into view on load
<?php if ($editId): ?>
const el = document.getElementById('qcard-<?= $editId ?>');
if (el) setTimeout(() => el.scrollIntoView({ behavior:'smooth', block:'start' }), 100);
<?php endif; ?>
</script>
</body>
</html>