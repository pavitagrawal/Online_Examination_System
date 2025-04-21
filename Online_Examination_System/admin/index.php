<?php
require_once '../includes/auth.php';
require_user_type('admin');

// Redirect to dashboard
header("Location: dashboard.php");
exit();
?>