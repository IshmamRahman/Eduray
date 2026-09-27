<?php
// TEMPORARY DEBUG FILE — DELETE AFTER FIXING
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require __DIR__ . '/db.php';

echo "<h3>Step 1: DB connected OK</h3>";

// Test if is_instructor column exists
$result = $conn->query("SHOW COLUMNS FROM users LIKE 'is_instructor'");
if ($result && $result->num_rows > 0) {
    echo "<h3 style='color:green'>Step 2: is_instructor column EXISTS ✓</h3>";
} else {
    echo "<h3 style='color:red'>Step 2: is_instructor column MISSING ✗ — Run the SQL below!</h3>";
    echo "<pre>ALTER TABLE users ADD COLUMN is_instructor TINYINT(1) DEFAULT 0;</pre>";
}

// Test if course columns exist
$r2 = $conn->query("SHOW COLUMNS FROM courses LIKE 'instructor_id'");
if ($r2 && $r2->num_rows > 0) {
    echo "<h3 style='color:green'>Step 3: instructor_id column in courses EXISTS ✓</h3>";
} else {
    echo "<h3 style='color:red'>Step 3: instructor_id column in courses MISSING ✗ — Run the SQL below!</h3>";
    echo "<pre>ALTER TABLE courses ADD COLUMN instructor_id INT DEFAULT NULL, ADD COLUMN course_code VARCHAR(20) DEFAULT NULL, ADD COLUMN deadline DATE DEFAULT NULL;</pre>";
}

// Test fetching a user
$email = 'test@test.com'; // doesn't matter, just testing the query
$stmt = $conn->prepare("SELECT id, name, email, password_hash, is_verified, is_admin, is_instructor FROM users WHERE email = ? LIMIT 1");
if ($stmt) {
    echo "<h3 style='color:green'>Step 4: Query prepared OK ✓</h3>";
    $stmt->close();
} else {
    echo "<h3 style='color:red'>Step 4: Query FAILED ✗ — " . $conn->error . "</h3>";
}

echo "<hr><p style='color:gray'>Delete this file (debug_login.php) after fixing.</p>";