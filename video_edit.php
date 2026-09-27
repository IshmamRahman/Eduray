<?php
session_start();
require __DIR__ . '/db.php';

if (empty($_SESSION['user_id'])) {
  http_response_code(403); die('Forbidden');
}

$isAdmin      = !empty($_SESSION['is_admin']);
$isInstructor = !empty($_SESSION['is_instructor']);

if (!$isAdmin && !$isInstructor) {
  http_response_code(403); die('Forbidden');
}

$userId = (int)$_SESSION['user_id'];

function fetch_courses(mysqli $conn, bool $isAdmin, int $userId): array {
  $arr = [];
  if ($isAdmin) {
    $res = $conn->query("SELECT id, title FROM courses ORDER BY title");
    while ($r = $res->fetch_assoc()) { $arr[] = $r; }
    $res->close();
  } else {
    $s = $conn->prepare("SELECT id, title FROM courses WHERE instructor_id=? ORDER BY title");
    $s->bind_param('i', $userId);
    $s->execute();
    $res = $s->get_result();
    while ($r = $res->fetch_assoc()) { $arr[] = $r; }
    $s->close();
  }
  return $arr;
}

function fetch_video(mysqli $conn, int $id): ?array {
  $s = $conn->prepare("SELECT id, course_id, title, embed_url, thumb_url, position FROM videos WHERE id=? LIMIT 1");
  $s->bind_param('i', $id);
  $s->execute();
  $r = $s->get_result()->fetch_assoc();
  $s->close();
  return $r ?: null;
}

function is_local_thumb(string $path): bool {
  return str_starts_with($path, 'uploads/video_thumbs/')
      || str_starts_with($path, 'uploads/videos/video_thumbs/');
}

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) { header('Location: ' . ($isInstructor && !$isAdmin ? 'instructor_dashboard.php' : 'courses.php')); exit; }

// Ownership check for instructors
if ($isInstructor && !$isAdmin) {
  $chk = $conn->prepare("SELECT v.id FROM videos v JOIN courses c ON c.id=v.course_id WHERE v.id=? AND c.instructor_id=?");
  $chk->bind_param("ii", $id, $userId);
  $chk->execute();
  $chk->store_result();
  if ($chk->num_rows === 0) { http_response_code(403); die('Forbidden: This video does not belong to your course.'); }
  $chk->close();
}

$video = fetch_video($conn, $id);
if (!$video) { header('Location: ' . ($isInstructor && !$isAdmin ? 'instructor_dashboard.php' : 'courses.php')); exit; }

$redirectBack = ($isInstructor && !$isAdmin)
  ? 'instructor_dashboard.php?cid=' . $video['course_id']
  : 'courses.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $course_id = $isAdmin ? (int)($_POST['course_id'] ?? 0) : (int)$video['course_id'];
  $title     = trim($_POST['title'] ?? '');
  $embed_url = trim($_POST['embed_url'] ?? '');
  $position  = (int)($_POST['position'] ?? 1);

  if ($course_id <= 0 || $title === '' || $embed_url === '' || strpos($embed_url, 'https://www.youtube.com/embed/') !== 0) {
    header('Location: video_edit.php?id=' . $id);
    exit;
  }

  // Handle optional thumbnail replace
  $thumbUrlNew = null;
  if (!empty($_FILES['thumb']['name'])) {
    $dir = __DIR__ . '/uploads/video_thumbs/';
    @mkdir($dir, 0777, true);

    $maxBytes    = 2 * 1024 * 1024;
    $allowedExt  = ['jpg','jpeg','png','webp'];
    $allowedMime = ['image/jpeg','image/png','image/webp'];

    $origName = $_FILES['thumb']['name'];
    $tmpPath  = $_FILES['thumb']['tmp_name'];
    $size     = (int)$_FILES['thumb']['size'];
    $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

    if (in_array($ext, $allowedExt) && $size > 0 && $size <= $maxBytes) {
      $finfo = new finfo(FILEINFO_MIME_TYPE);
      $mime  = $finfo->file($tmpPath);
      if (in_array($mime, $allowedMime)) {
        $basename = 'vthumb_' . $course_id . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $destFs   = $dir . $basename;
        if (move_uploaded_file($tmpPath, $destFs)) {
          $thumbUrlNew = 'uploads/video_thumbs/' . $basename;
        }
      }
    }
  }

  if ($thumbUrlNew !== null) {
    if (!empty($video['thumb_url']) && is_local_thumb($video['thumb_url'])) {
      @unlink(__DIR__ . '/' . $video['thumb_url']);
    }
    $u = $conn->prepare("UPDATE videos SET course_id=?, title=?, embed_url=?, thumb_url=?, position=? WHERE id=?");
    $u->bind_param('isssii', $course_id, $title, $embed_url, $thumbUrlNew, $position, $id);
    $u->execute(); $u->close();
  } else {
    $u = $conn->prepare("UPDATE videos SET course_id=?, title=?, embed_url=?, position=? WHERE id=?");
    $u->bind_param('issii', $course_id, $title, $embed_url, $position, $id);
    $u->execute(); $u->close();
  }

  header('Location: ' . $redirectBack);
  exit;
}

$courses = fetch_courses($conn, $isAdmin, $userId);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Edit Video – EDURAY</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
  <style>
    body { background:#f4f1fa; font-family:'Plus Jakarta Sans',sans-serif; }
    .card { border:none; border-radius:16px; box-shadow:0 8px 32px rgba(95,37,159,.1); }
    .card-body { padding:32px; }
    .form-label { font-weight:700; font-size:13px; color:#5f259f; }
    .form-control, .form-select { border-radius:8px; border:1.5px solid #e0d5f5; font-size:14px; }
    .form-control:focus, .form-select:focus { border-color:#5f259f; box-shadow:0 0 0 3px rgba(95,37,159,.1); }
    .btn-save { background:linear-gradient(135deg,#5f259f,#ff6b00); color:#fff; border:none; border-radius:8px; padding:10px 28px; font-weight:700; font-size:14px; }
    .btn-save:hover { opacity:.9; color:#fff; }
    .page-title { font-size:22px; font-weight:800; color:#1e1a2e; margin-bottom:6px; }
    .thumb-preview { width:160px; height:100px; object-fit:cover; border-radius:8px; border:2px solid #e0d5f5; }
    #thumb-new-preview { display:none; width:160px; height:100px; object-fit:cover; border-radius:8px; border:2px dashed #5f259f; margin-top:8px; }
  </style>
</head>
<body>
<div class="container py-5" style="max-width:700px;">
  <a href="<?= htmlspecialchars($redirectBack) ?>" class="btn btn-link text-decoration-none mb-3" style="color:#5f259f;font-weight:700;">
    ← Back
  </a>
  <div class="card">
    <div class="card-body">
      <div class="page-title">Edit Video</div>
      <p style="color:#888;font-size:13.5px;margin-bottom:24px;">Update the video details below.</p>

      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?= (int)$video['id'] ?>">

        <div class="row g-3">

          <?php if ($isAdmin): ?>
          <div class="col-md-6">
            <label class="form-label">Course</label>
            <select name="course_id" class="form-select" required>
              <?php foreach ($courses as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= $c['id']==$video['course_id']?'selected':'' ?>>
                  <?= htmlspecialchars($c['title']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
          <?php else: ?>
            <input type="hidden" name="course_id" value="<?= (int)$video['course_id'] ?>">
          <div class="col-md-10">
          <?php endif; ?>
            <label class="form-label">Title</label>
            <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($video['title']) ?>" required>
          </div>

          <div class="col-md-2">
            <label class="form-label">Position</label>
            <input type="number" name="position" class="form-control" value="<?= (int)$video['position'] ?>" min="1" required>
          </div>

          <div class="col-12">
            <label class="form-label">YouTube Embed URL</label>
            <input type="url" name="embed_url" class="form-control"
                   value="<?= htmlspecialchars($video['embed_url']) ?>"
                   placeholder="https://www.youtube.com/embed/..." required>
            <div class="form-text">Must start with <code>https://www.youtube.com/embed/</code></div>
          </div>

          <div class="col-md-6">
            <label class="form-label">Current Thumbnail</label><br>
            <?php if (!empty($video['thumb_url'])): ?>
              <img src="<?= htmlspecialchars($video['thumb_url']) ?>" class="thumb-preview" id="thumb-current">
            <?php else: ?>
              <div style="width:160px;height:100px;border-radius:8px;background:#f0ebff;display:flex;align-items:center;justify-content:center;color:#bbb;font-size:12px;">No thumbnail</div>
            <?php endif; ?>
          </div>

          <div class="col-md-6">
            <label class="form-label">Replace Thumbnail <span style="font-weight:400;color:#aaa;">(optional)</span></label>
            <input type="file" name="thumb" id="thumbInput" class="form-control" accept=".jpg,.jpeg,.png,.webp">
            <div class="form-text">Max 2MB · JPG, PNG, WebP</div>
            <img id="thumb-new-preview" src="" alt="Preview">
          </div>

          <div class="col-12 mt-2">
            <button class="btn btn-save" type="submit">Save Changes</button>
            <a href="<?= htmlspecialchars($redirectBack) ?>" class="btn btn-secondary ms-2">Cancel</a>
          </div>

        </div>
      </form>
    </div>
  </div>
</div>
<script>
document.getElementById('thumbInput').addEventListener('change', function() {
  const file = this.files[0];
  if (!file) return;
  const preview = document.getElementById('thumb-new-preview');
  const current = document.getElementById('thumb-current');
  const reader  = new FileReader();
  reader.onload  = e => {
    preview.src          = e.target.result;
    preview.style.display = 'block';
    if (current) current.style.opacity = '.4';
  };
  reader.readAsDataURL(file);
});
</script>
</body>
</html>