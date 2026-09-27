<?php
session_start();
require_once "db.php";

// Fetch available sectors dynamically
$sectors = [];
$sec_stmt = $conn->query("SELECT DISTINCT sector FROM roadmaps ORDER BY sector ASC");
if ($sec_stmt) {
    while ($row = $sec_stmt->fetch_assoc()) {
        $sectors[] = $row['sector'];
    }
}

// Handle form submission
$selected_sector = $_GET['sector'] ?? '';
$selected_duration = isset($_GET['duration']) ? (int)$_GET['duration'] : 6;

// Constraints
if ($selected_duration < 1) $selected_duration = 1;
if ($selected_duration > 12) $selected_duration = 12;

$roadmap_data = [];
if (!empty($selected_sector)) {
    $stmt = $conn->prepare("SELECT * FROM roadmaps WHERE sector = ? AND month_number <= ? ORDER BY month_number ASC");
    $stmt->bind_param("si", $selected_sector, $selected_duration);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $roadmap_data[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Your Roadmap - EDURAY</title>
  
  <!-- Google Font -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <style>
    :root {
      --primary: #5f259f;
      --accent: #ff6b00;
      --gold: #ffe600;
      --bg: #fdfaf6;
    }
    * { box-sizing: border-box; }
    html { scroll-behavior: smooth; }
    body { margin: 0; font-family: 'Poppins', sans-serif; background: var(--bg); color: #333; }

    /* ===== Navbar styling copied from index.php ===== */
    .navbar { background: linear-gradient(135deg, var(--primary), var(--accent)); border-bottom-left-radius: 30px; border-bottom-right-radius: 30px; box-shadow: 0 4px 20px rgba(0,0,0,.1); }
    .navbar .container { padding-left: 10%; padding-right: 10%; }
    .navbar-brand { font-size: 24px; font-weight: 700; letter-spacing: .5px; color: #fff !important; display: flex; align-items: center; gap: 8px; }
    .navbar-brand .brand-accent { color: var(--gold); }
    .navbar-nav .nav-link { color: #fff !important; font-size: 15px; font-weight: 500; position: relative; padding: .25rem .5rem; margin: 0 .35rem; }
    .navbar-nav .nav-link::after { content: ''; position: absolute; left: 0; bottom: -6px; height: 2px; width: 0; background: var(--gold); transition: width .25s ease; }
    .navbar-nav .nav-link:hover::after { width: 100%; }
    .btn-signup { --bs-btn-color: #222; --bs-btn-bg: transparent; --bs-btn-border-color: transparent; display: inline-block; text-decoration: none; padding: 10px 22px; border-radius: 25px; font-weight: 600; font-size: 14px; background: linear-gradient(135deg, var(--gold), #ff8800); box-shadow: 0 5px 15px rgba(255,136,0,.3); transition: transform .2s, box-shadow .2s; white-space: nowrap; color: #222 !important; }
    .btn-signup:hover { transform: translateY(-2px) scale(1.03); box-shadow: 0 10px 22px rgba(255,136,0,.45); }
    .navbar-toggler { border-color: rgba(255,255,255,.4); }
    .navbar-toggler-icon { filter: invert(1); }

    /* ===== Form Section ===== */
    .roadmap-header {
      text-align: center;
      padding: 60px 20px 30px;
      animation: fadeIn 0.8s ease-in;
    }
    @keyframes fadeIn{from{opacity:0; transform:translateY(20px)} to{opacity:1; transform:translateY(0)}}
    
    .roadmap-header h1 { font-size: 40px; font-weight: 700; margin-bottom: 15px; }
    .roadmap-header .highlight { color: var(--accent); }
    .roadmap-header p { font-size: 16px; color: #666; max-width: 600px; margin: 0 auto; line-height: 1.6; }

    .roadmap-form-card {
      background: #fff;
      max-width: 800px;
      margin: 0 auto 50px;
      padding: 30px;
      border-radius: 20px;
      box-shadow: 0 10px 30px rgba(0,0,0,.08);
      animation: fadeIn 1s ease-in;
    }



    .form-group label {
      font-weight: 600;
      color: var(--primary);
      margin-bottom: 8px;
      display: block;
    }



    .form-select, .form-control {
      border: 2px solid #eee;
      border-radius: 12px;
      padding: 12px 15px;
      font-size: 15px;
      transition: all 0.3s ease;
    }
    .form-select:focus, .form-control:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 0.25rem rgba(255, 107, 0, 0.25);
    }

    .btn-generate {
      background: linear-gradient(135deg, var(--primary), var(--accent));
      color: #fff;
      border: none;
      padding: 12px 30px;
      border-radius: 30px;
      font-weight: 600;
      font-size: 16px;
      width: 100%;
      transition: transform 0.2s, box-shadow 0.2s;
    }
    .btn-generate:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(95, 37, 159, 0.3);
      color: #fff;
    }
    html, body {
  height: 100%;
   }

   body {
  display: flex;
  flex-direction: column;
  }

.container {
  flex: 1;
  }

footer {
  margin-top: auto;
   }


    /* ===== Timeline Section ===== */
    .timeline-container {
      padding: 20px;
      background: #f8f6fb;
      border-radius: 30px;
      max-width: 900px;
      margin: 0 auto 60px;
      box-shadow: inset 0 0 20px rgba(0,0,0,0.02);
    }
    
    .timeline {
      position: relative;
      max-width: 800px;
      margin: 40px auto;
      padding-left: 30px;
    }
    .timeline::before {
      content: '';
      position: absolute;
      left: 10px;
      top: 0;
      bottom: 0;
      width: 4px;
      background: linear-gradient(to bottom, var(--primary), var(--accent));
      border-radius: 2px;
    }
    
    .timeline-item {
      position: relative;
      margin-bottom: 30px;
      background: #fff;
      padding: 25px 30px;
      border-radius: 16px;
      box-shadow: 0 8px 25px rgba(0,0,0,.06);
      border-left: 6px solid var(--accent);
      transition: transform 0.3s ease;
    }
    .timeline-item:hover {
      transform: translateX(5px);
    }
    .timeline-item::before {
      content: '';
      position: absolute;
      left: -33px;
      top: 25px;
      width: 20px;
      height: 20px;
      border-radius: 50%;
      background: var(--gold);
      border: 4px solid #fff;
      box-shadow: 0 0 0 3px var(--primary);
      z-index: 1;
    }
    
    .timeline-month {
      display: inline-block;
      font-size: 13px;
      font-weight: 700;
      color: #fff;
      background: var(--primary);
      padding: 4px 12px;
      border-radius: 20px;
      text-transform: uppercase;
      letter-spacing: 1px;
      margin-bottom: 15px;
    }
    .timeline-title {
      font-size: 22px;
      font-weight: 700;
      margin-bottom: 12px;
      color: #222;
    }
    .timeline-desc {
      color: #555;
      line-height: 1.7;
      margin: 0;
    }

    .empty-state {
      text-align: center;
      padding: 40px 20px;
      color: #777;
    }
    .empty-state i {
      font-size: 60px;
      color: #ddd;
      margin-bottom: 15px;
    }

    /* ===== Footer - Matching index.php ===== */
    footer {
      background: linear-gradient(135deg, var(--primary), var(--accent));
      color: #fff;
      padding: 30px 10%;
      box-shadow: 0 -4px 20px rgba(0,0,0,0.1);
      margin-top: 50px;
    }
    .footer-content { display: flex; justify-content: center; align-items: center; gap: 60px; flex-wrap: wrap; }
    .footer-item { display: flex; align-items: center; gap: 10px; font-size: 15px; }
    .footer-item i { color: var(--gold); font-size: 18px; }
    .footer-bottom { border-top: 1px solid rgba(255,255,255,.3); margin-top: 20px; padding-top: 15px; text-align: center; font-size: 13px; }

    @media (max-width: 768px) {
      .navbar .container { padding-left: 16px; padding-right: 16px; }
      .roadmap-header h1 { font-size: 32px; }
      .timeline-item { padding: 20px; }
      .timeline-title { font-size: 18px; }
      .footer-content { gap: 30px; flex-direction: column; }
    }
  </style>
</head>
<body>

  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg sticky-top">
    <div class="container">
      <a class="navbar-brand" href="index.php">
        📚 <span class="brand-accent">EDURAY</span>
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="mainNav">
        <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-lg-center">
          <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
          <li class="nav-item"><a class="nav-link" href="roadmap.php">Roadmap</a></li>
          <li class="nav-item"><a class="nav-link" href="courses.php">Courses</a></li>
          <li class="nav-item"><a class="nav-link" href="community.php">Community</a></li>
          
          <?php if (!empty($_SESSION['user_id'])): ?>
            <?php $isAdmin = !empty($_SESSION['is_admin']); ?>
            <li class="nav-item"><a class="nav-link" href="<?= $isAdmin ? 'admin_dashboard.php' : 'my_courses.php' ?>">My courses</a></li>
          <?php endif; ?>
          
          <li class="nav-item me-lg-2"><a class="nav-link" href="authority.php">Contact us</a></li>

          <?php if (!empty($_SESSION['user_id'])): ?>
            <li class="nav-item ms-lg-2"><a href="logout.php" class="btn btn-signup">Logout</a></li>
          <?php else: ?>
            <li class="nav-item ms-lg-2"><a href="main.php" class="btn btn-signup">Sign up</a></li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Header -->
  <div class="roadmap-header">
    <h1>Your <span class="highlight">Success</span> Roadmap</h1>
    <p>Select your desired career path and the duration you have planned. We'll generate a comprehensive learning journey tailored for you.</p>
  </div>

  <!-- Selection Form -->
   
  <div class="container">
    <div class="roadmap-form-card">
      <form action="roadmap.php" method="GET">
        <div class="row g-4 align-items-end">
          <div class="col-md-5">
            <div class="form-group">
              <label for="sector"><i class="fas fa-briefcase me-2"></i>Select Sector</label>
              <select name="sector" id="sector" class="form-select" required>
                <option value="" disabled <?= empty($selected_sector) ? 'selected' : '' ?>>Choose your path...</option>
                <?php foreach ($sectors as $sec): ?>
                  <option value="<?= htmlspecialchars($sec) ?>" <?= $selected_sector === $sec ? 'selected' : '' ?>>
                    <?= htmlspecialchars($sec) ?>
                  </option>
                <?php endforeach; ?>
                <?php if (empty($sectors)): ?>
                  <option value="Web Development" <?= $selected_sector === 'Web Development' ? 'selected' : '' ?>>Web Development</option>
                  <option value="App Development" <?= $selected_sector === 'App Development' ? 'selected' : '' ?>>App Development</option>
                <?php endif; ?>
              </select>
            </div>
          </div>
          
          <div class="col-md-4">
            <div class="form-group">
              <label for="duration"><i class="fas fa-calendar-alt me-2"></i>Duration (Months)</label>
              <select name="duration" id="duration" class="form-select" required>
                <?php for ($i = 1; $i <= 6; $i++): ?>
                  <option value="<?= $i ?>" <?= $selected_duration == $i ? 'selected' : '' ?>><?= $i ?> Month<?= $i > 1 ? 's' : '' ?></option>
                <?php endfor; ?>
              </select>
            </div>
          </div>

          <div class="col-md-3">
            <button type="submit" class="btn btn-generate">
              <i class="fas fa-magic me-2"></i>Generate
            </button>
          </div>
        </div>
      </form>
    </div>

    <!-- Timeline Chart -->
    <?php if (!empty($selected_sector)): ?>
      <div class="timeline-container">
        <?php if (count($roadmap_data) > 0): ?>
          <div class="timeline">
            <?php foreach ($roadmap_data as $item): ?>
              <div class="timeline-item">
                <span class="timeline-month">Month <?= (int)htmlspecialchars($item['month_number']) ?></span>
                <h3 class="timeline-title"><?= htmlspecialchars($item['title']) ?></h3>
                <p class="timeline-desc"><?= nl2br(htmlspecialchars($item['description'])) ?></p>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="empty-state">
            <i class="fas fa-search"></i>
            <h3>No Roadmap Found</h3>
            <p>We couldn't find a detailed roadmap for <strong><?= htmlspecialchars($selected_sector) ?></strong> matching your criteria. Try adjusting the duration or selecting a different sector.</p>
          </div>
        <?php endif; ?>
      </div>
    <?php elseif (isset($_GET['sector'])): ?>
       <div class="empty-state">
         <p>Please select a valid sector to generate your roadmap.</p>
       </div>
    <?php endif; ?>

  </div>

  <!-- Footer -->
  <footer>
    <div class="footer-content">
      <div class="footer-item">
        <i class="fas fa-map-marker-alt"></i>
        <span>Sylhet, Bangladesh</span>
      </div>
      <div class="footer-item">
        <i class="fas fa-phone"></i>
        <span>+880 1711 000000</span>
      </div>
      <div class="footer-item">
        <i class="fas fa-envelope"></i>
        <span>support@eduray.com</span>
      </div>
    </div>
    <div class="footer-bottom">
      © <?php echo date('Y'); ?> EDURAY. All rights reserved.
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
