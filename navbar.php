<?php
// navbar.php — shared navbar for all student-facing pages
// Include this file after session_start() and require db.php
// Usage: <?php include 'navbar.php'; ?>
?>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{
  --primary:#5f259f;
  --accent:#ff6b00;
  --gold:#ffe600;
  --bg:#fdfaf6;
}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{margin:0; font-family:'Poppins',sans-serif; background:var(--bg); color:#333; overflow-x:hidden}

.navbar{
  background: linear-gradient(135deg, var(--primary), var(--accent));
  border-bottom-left-radius: 30px;
  border-bottom-right-radius: 30px;
  box-shadow: 0 4px 20px rgba(0,0,0,.1);
}
.navbar .container{padding-left:10%; padding-right:10%;}
.navbar-brand{
  font-size:24px;
  font-weight:700;
  letter-spacing:.5px;
  color:#fff !important;
  display:flex; align-items:center; gap:8px;
  text-decoration:none;
}
.navbar-brand .brand-accent{color:var(--gold)}
.navbar-nav .nav-link{
  color:#fff !important;
  font-size:15px;
  font-weight:500;
  position:relative;
  padding:.25rem .5rem;
  margin:0 .35rem;
  text-decoration:none;
}
.navbar-nav .nav-link::after{
  content:''; position:absolute; left:0; bottom:-6px; height:2px; width:0;
  background:var(--gold); transition:width .25s ease;
}
.navbar-nav .nav-link:hover::after{width:100%}
.navbar-nav .nav-link.active-link::after{width:100%}

.btn-nav{
  display:inline-block; text-decoration:none;
  padding:10px 22px; border-radius:25px; font-weight:600; font-size:14px;
  background:linear-gradient(135deg, var(--gold), #ff8800);
  box-shadow:0 5px 15px rgba(255,136,0,.3);
  transition:transform .2s, box-shadow .2s;
  white-space:nowrap; color:#222 !important;
  border:none; cursor:pointer;
}
.btn-nav:hover{transform:translateY(-2px) scale(1.03); box-shadow:0 10px 22px rgba(255,136,0,.45)}
.navbar-toggler{border-color:rgba(255,255,255,.4)}
.navbar-toggler:focus{box-shadow:0 0 0 .2rem rgba(255,255,255,.2)}
.navbar-toggler-icon{filter:invert(1)}
</style>

<nav class="navbar navbar-expand-lg sticky-top">
  <div class="container">
    <a class="navbar-brand" href="index.php">
      📚 <span class="brand-accent">EDURAY</span>
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
            aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-lg-center">
        <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
        <li class="nav-item"><a class="nav-link" href="index.php#services">Our Service</a></li>
        <li class="nav-item"><a class="nav-link" href="courses.php">Courses</a></li>
        <li class="nav-item"><a class="nav-link" href="community.php">Community</a></li>

        <?php if (!empty($_SESSION['user_id'])): ?>
          <li class="nav-item"><a class="nav-link" href="my_courses.php">My Courses</a></li>
        <?php endif; ?>

        <li class="nav-item"><a class="nav-link" href="index.php#about">About Us</a></li>
        <li class="nav-item me-lg-2"><a class="nav-link" href="authority.php">Contact Us</a></li>

        <?php if (!empty($_SESSION['user_id'])): ?>
          <li class="nav-item ms-lg-2">
            <a href="logout.php" class="btn-nav">Logout</a>
          </li>
        <?php else: ?>
          <li class="nav-item ms-lg-2">
            <a href="main.php" class="btn-nav">Sign Up</a>
          </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>