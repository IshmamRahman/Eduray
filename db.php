<?php
// db.php — single connection file
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host   = "localhost";
$user   = "root";
$pass   = "";              // XAMPP default
$dbname = "eduray_portal2"; // your DB name

try {
    $conn = new mysqli($host, $user, $pass, $dbname);
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    // Friendly error for local dev
    http_response_code(500);
    die("Database connection failed. Please check db.php settings. <br><small>Error: "
        . htmlspecialchars($e->getMessage()) . "</small>");
}
