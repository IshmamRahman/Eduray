<?php
session_start();
header('Content-Type: text/plain; charset=utf-8');
echo "user_id: " . ($_SESSION['user_id'] ?? 'none') . PHP_EOL;
echo "is_admin: " . (isset($_SESSION['is_admin']) ? (int)$_SESSION['is_admin'] : 'not set') . PHP_EOL;
