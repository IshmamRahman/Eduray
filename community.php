<?php
define('EDURAY_INCLUDED', true);
session_start();
require __DIR__ . '/db.php';

$userId = $_SESSION['user_id'] ?? null;
$userName = $_SESSION['name'] ?? null;
$userMail = $_SESSION['email'] ?? null;
$isAdmin = !empty($_SESSION['is_admin']);

function ensureCommunityTables(mysqli $conn): void {
    $conn->query("
        CREATE TABLE IF NOT EXISTS community_posts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            body TEXT NOT NULL,
            status ENUM('open','closed') NOT NULL DEFAULT 'open',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX (user_id),
            INDEX (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $conn->query("
        CREATE TABLE IF NOT EXISTS community_answers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            post_id INT NOT NULL,
            user_id INT NOT NULL,
            body TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX (post_id),
            INDEX (user_id),
            CONSTRAINT fk_answers_post FOREIGN KEY (post_id) REFERENCES community_posts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}
ensureCommunityTables($conn);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $userId) {
    $action = $_POST['action'] ?? '';
    if ($action === 'create_post') {
        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['body'] ?? '');
        if ($title === '' || $body === '') {
            $error = 'Please provide both title and description.';
        } else {
            $stmt = $conn->prepare("INSERT INTO community_posts (user_id, title, body) VALUES (?, ?, ?)");
            $stmt->bind_param('iss', $userId, $title, $body);
            $stmt->execute();
            $stmt->close();
            $message = 'Your question has been posted.';
        }
    } elseif ($action === 'add_answer') {
        $postId = (int)($_POST['post_id'] ?? 0);
        $body = trim($_POST['body'] ?? '');
        if ($postId <= 0 || $body === '') {
            $error = 'Please write an answer.';
        } else {
            $stmt = $conn->prepare("INSERT INTO community_answers (post_id, user_id, body) VALUES (?, ?, ?)");
            $stmt->bind_param('iis', $postId, $userId, $body);
            $stmt->execute();
            $stmt->close();
            $message = 'Answer submitted.';
            header("Location: community.php?post_id={$postId}");
            exit;
        }
    }
}

$postId = isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0;
$viewPost = null;
$answers = [];

if ($postId > 0) {
    $stmt = $conn->prepare("
        SELECT p.*, u.name AS user_name, u.email AS user_email, u.is_admin AS user_is_admin
        FROM community_posts p
        LEFT JOIN users u ON u.id = p.user_id
        WHERE p.id = ?
    ");
    $stmt->bind_param('i', $postId);
    $stmt->execute();
    $viewPost = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($viewPost) {
        $stmt = $conn->prepare("
            SELECT a.*, u.name AS user_name, u.email AS user_email, u.is_admin AS user_is_admin
            FROM community_answers a
            LEFT JOIN users u ON u.id = a.user_id
            WHERE a.post_id = ?
            ORDER BY a.id ASC
        ");
        $stmt->bind_param('i', $postId);
        $stmt->execute();
        $answers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

// Fetch posts
$posts = [];
$res = $conn->query("
    SELECT p.id, p.title, p.created_at, p.status,
           COALESCE(u.name, '') AS user_name,
           (SELECT COUNT(*) FROM community_answers a WHERE a.post_id = p.id) AS answer_count
    FROM community_posts p
    LEFT JOIN users u ON u.id = p.user_id
    ORDER BY p.created_at DESC
");
while ($row = $res->fetch_assoc()) { $posts[] = $row; }
$res->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>EDURAY – Community</title>
    <?php include 'includes/head.php'; ?>
    <style>
        .page {
            padding: 40px 10%;
        }
        .grid {
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 24px;
        }
        @media (max-width: 992px) {
            .grid { grid-template-columns: 1fr; }
            .page { padding: 20px; }
        }
        .card {
            border-radius: 16px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            background: white;
        }
        .post-item {
            transition: all 0.2s;
            border: 1px solid var(--border);
        }
        .post-item:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            border-color: var(--primary);
        }
        .badge-soft {
            background: rgba(95, 37, 159, 0.1);
            color: var(--primary);
            border-radius: 50px;
            padding: 4px 12px;
            font-weight: 600;
            font-size: 12px;
        }
        .btn-dark-custom {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 25px;
            font-weight: 600;
        }
        .btn-dark-custom:hover {
            opacity: 0.9;
            color: white;
        }
    </style>
</head>
<body>

<?php include 'includes/navbar.php'; ?>

<div class="page">
    <div class="grid">
        <div>
            <?php if ($message): ?>
                <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($postId && $viewPost): ?>
                <!-- View Single Post -->
                <div class="card mb-3">
                    <div class="card-body">
                        <h3 class="h4 mb-2"><?= htmlspecialchars($viewPost['title']) ?></h3>
                        <div class="text-muted mb-3">
                            Asked by <?= htmlspecialchars($viewPost['user_name'] ?: 'Student') ?>
                            <?php if (!empty($viewPost['user_is_admin'])): ?>
                                <span class="badge-soft ms-1">Admin</span>
                            <?php endif; ?>
                            • <?= htmlspecialchars(date('M d, Y H:i', strtotime($viewPost['created_at']))) ?>
                        </div>
                        <p class="mb-0"><?= nl2br(htmlspecialchars($viewPost['body'])) ?></p>
                    </div>
                </div>

                <!-- Answers -->
                <div class="card mb-3">
                    <div class="card-header bg-white">
                        <strong>Answers (<?= count($answers) ?>)</strong>
                    </div>
                    <div class="card-body">
                        <?php if (!$answers): ?>
                            <p class="text-muted mb-0">No answers yet. Be the first to help.</p>
                        <?php else: ?>
                            <?php foreach ($answers as $a): ?>
                                <div class="mb-3 pb-3 border-bottom">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <strong><?= htmlspecialchars($a['user_name'] ?: 'Student') ?></strong>
                                        <?php if (!empty($a['user_is_admin'])): ?>
                                            <span class="badge-soft">Admin</span>
                                        <?php endif; ?>
                                        <span class="text-muted">• <?= htmlspecialchars(date('M d, Y H:i', strtotime($a['created_at']))) ?></span>
                                    </div>
                                    <div><?= nl2br(htmlspecialchars($a['body'])) ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Answer Form -->
                <?php if ($userId): ?>
                    <div class="card mb-3">
                        <div class="card-body">
                            <form method="post">
                                <input type="hidden" name="action" value="add_answer">
                                <input type="hidden" name="post_id" value="<?= (int)$postId ?>">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Your Answer</label>
                                    <textarea name="body" class="form-control" rows="4" placeholder="Write a helpful answer..." required></textarea>
                                </div>
                                <button class="btn btn-dark-custom">Submit Answer</button>
                                <a href="community.php" class="btn btn-outline-secondary ms-2 rounded-pill">Back to Community</a>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">Please <a href="main.php">login</a> to answer.</div>
                <?php endif; ?>

            <?php else: ?>
                <!-- Post List View -->
                <?php if ($userId): ?>
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5 class="card-title mb-3 fw-bold">Ask a Question</h5>
                            <form method="post">
                                <input type="hidden" name="action" value="create_post">
                                <div class="mb-2">
                                    <label class="form-label">Title</label>
                                    <input type="text" name="title" class="form-control" placeholder="Short summary of your problem" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Details</label>
                                    <textarea name="body" class="form-control" rows="4" placeholder="Explain your problem..." required></textarea>
                                </div>
                                <button class="btn btn-dark-custom">Post Question</button>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">Please <a href="main.php">login</a> to post a question.</div>
                <?php endif; ?>

                <!-- Questions List -->
                <div class="card">
                    <div class="card-header bg-white">
                        <strong>Latest Questions</strong>
                    </div>
                    <div class="card-body">
                        <?php if (!$posts): ?>
                            <p class="text-muted mb-0">No questions yet.</p>
                        <?php else: ?>
                            <?php foreach ($posts as $p): ?>
                                <a href="community.php?post_id=<?= (int)$p['id'] ?>" class="text-decoration-none text-dark">
                                    <div class="post-item p-3 rounded mb-2 bg-white">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <h6 class="mb-1 fw-bold"><?= htmlspecialchars($p['title']) ?></h6>
                                            <span class="badge-soft"><?= (int)$p['answer_count'] ?> answers</span>
                                        </div>
                                        <div class="text-muted small">
                                            <?= htmlspecialchars($p['user_name'] ?: 'Student') ?> • <?= htmlspecialchars(date('M d, Y', strtotime($p['created_at']))) ?>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <aside>
            <div class="card">
                <div class="card-body">
                    <h6 class="mb-3 fw-bold">Community Guidelines</h6>
                    <ul class="mb-0 small">
                        <li class="mb-2">Be respectful and helpful.</li>
                        <li class="mb-2">Share clear and concise questions.</li>
                        <li>No spam or promotional content.</li>
                    </ul>
                </div>
            </div>
        </aside>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

</body>
</html>