<?php
session_start();
session_unset();   
session_destroy(); 
header("Location: login.php?error=" . rawurlencode("You have been logged out."));
exit;
