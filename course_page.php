<?php
define('EDURAY_INCLUDED', true);
session_start();
require __DIR__ . '/db.php';

$userId = $_SESSION['user_id'] ?? null;
$userName = $_SESSION['name'] ?? null;
$isAdmin = !empty($_SESSION['is_admin']);

if ($isAdmin) {
    header('Location: admin_dashboard.php');
    exit;
}

$courseId = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
if (!$courseId) {
    header('Location: courses.php');
    exit;
}

// Fetch course
$stmt = $conn->prepare("SELECT * FROM courses WHERE id = ?");
$stmt->bind_param("i", $courseId);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$course) {
    header('Location: courses.php');
    exit;
}

// Count videos & quizzes
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM videos WHERE course_id = ?");
$stmt->bind_param("i", $courseId);
$stmt->execute();
$videoCount = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM quizzes WHERE course_id = ?");
$stmt->bind_param("i", $courseId);
$stmt->execute();
$quizCount = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Check enrollment
$isEnrolled = false;
if ($userId) {
    $stmt = $conn->prepare("SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?");
    $stmt->bind_param("ii", $userId, $courseId);
    $stmt->execute();
    $isEnrolled = $stmt->get_result()->num_rows > 0;
    $stmt->close();
}

// Handle enrollment
$enrollMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll']) && $userId) {
    if (!$isEnrolled) {
        $stmt = $conn->prepare("INSERT INTO enrollments (user_id, course_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $userId, $courseId);
        if ($stmt->execute()) {
            $isEnrolled = true;
            $enrollMessage = 'success';
        } else {
            $enrollMessage = 'error';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title><?= htmlspecialchars($course['title']) ?> - EDURAY</title>
    <?php include 'includes/head.php'; ?>
    <style>
        .course-header {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            padding: 60px 10%;
            margin-bottom: 40px;
        }
        .course-header h1 {
            font-size: 42px;
            font-weight: 700;
            margin-bottom: 20px;
        }
        .course-cover-large {
            width: 100%;
            max-width: 500px;
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
        }
        .stats-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            text-align: center;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
        }
        .stats-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary);
        }
        .btn-enroll {
            background: linear-gradient(135deg, var(--gold), #ff8800);
            border: none;
            color: #222;
            font-weight: 600;
            padding: 15px 40px;
            font-size: 1.1rem;
            border-radius: 50px;
            box-shadow: 0 5px 20px rgba(255,136,0,.4);
            transition: all 0.3s;
        }
        .btn-enroll:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(255,136,0,.6);
        }
        .btn-enrolled {
            background: #28a745;
            color: white;
            padding: 15px 40px;
            font-size: 1.1rem;
            border-radius: 50px;
            border: none;
        }
        .content-card {
            background: white;
            border-radius: 16px;
            padding: 40px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
        }
        @media (max-width: 991.98px) {
            .course-header { padding: 40px 20px; }
            .course-header h1 { font-size: 32px; }
        }
    </style>
</head>
<body>

<?php include 'includes/navbar.php'; ?>

<!-- Course Header -->
<div class="course-header">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h1><?= htmlspecialchars($course['title']) ?></h1>
                <p class="lead mb-4">Master the concepts with our comprehensive course content. Learn at your own pace with expert-curated materials.</p>
                
                <?php if ($enrollMessage === 'success'): ?>
                    <div class="alert alert-success alert-dismissible fade show d-inline-block" role="alert">
                        🎉 Successfully enrolled! <a href="my_courses.php" class="alert-link">Go to My Courses</a>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php elseif ($enrollMessage === 'error'): ?>
                    <div class="alert alert-danger alert-dismissible fade show d-inline-block" role="alert">
                        ❌ Enrollment failed. Please try again.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($isEnrolled): ?>
                    <button class="btn btn-enrolled" disabled>✓ Already Enrolled</button>
                    <a href="my_courses.php" class="btn btn-light btn-lg ms-3">Go to Course</a>
                <?php elseif ($userId): ?>
                    <form method="POST" class="d-inline">
                        <button type="submit" name="enroll" class="btn btn-enroll">🚀 Enroll Now - It's Free!</button>
                    </form>
                <?php else: ?>
                    <a href="main.php?redirect=<?= urlencode('course_page.php?course_id=' . $courseId) ?>" class="btn btn-enroll">🔒 Login to Enroll</a>
                <?php endif; ?>
            </div>
            <div class="col-lg-6 text-center mt-4 mt-lg-0">
                <img src="<?= htmlspecialchars($course['thumbnail_url'] ?: 'https://via.placeholder.com/600x340?text=Course+Cover') ?>" 
                     alt="<?= htmlspecialchars($course['title']) ?>" 
                     class="course-cover-large">
            </div>
        </div>
    </div>
</div>

<!-- Stats -->
<div class="container-fluid mb-5" style="padding: 0 10%;">
    <div class="row g-4">
        <div class="col-md-4">
            <div class="stats-card">
                <div class="stats-number"><?= $videoCount ?></div>
                <div class="text-muted">Video Lessons</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stats-card">
                <div class="stats-number"><?= $quizCount ?></div>
                <div class="text-muted">Interactive Quizzes</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stats-card">
                <div class="stats-number">∞</div>
                <div class="text-muted">Lifetime Access</div>
            </div>
        </div>
    </div>
</div>

<!-- Description -->
<div class="container-fluid mb-5" style="padding: 0 10%;">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="content-card">
                <h3 class="mb-4">About This Course</h3>
                <p class="text-muted lead">
                    This comprehensive course covers everything you need to know about <?= htmlspecialchars($course['title']) ?>. 
                    With <?= $videoCount ?> video lessons and <?= $quizCount ?> interactive quizzes, you'll gain hands-on experience.
                </p>
                <hr class="my-4">
                <h5 class="mb-3">What You'll Learn:</h5>
                <ul class="list-unstyled">
                    <li class="mb-2">✅ Core concepts and fundamentals</li>
                    <li class="mb-2">✅ Practical hands-on exercises</li>
                    <li class="mb-2">✅ Real-world applications</li>
                    <li class="mb-2">✅ Assessment through interactive quizzes</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

</body>
</html>