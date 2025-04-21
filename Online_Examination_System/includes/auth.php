<?php
session_start();

// Include necessary files
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/functions.php';

// Function to check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_type']);
}

// Function to require login for a page
function require_login() {
    if (!is_logged_in()) {
        header("Location: login.php");
        exit();
    }
}

// Function to require specific user type
function require_user_type($required_type) {
    require_login();
    
    if ($_SESSION['user_type'] != $required_type) {
        header("Location: index.php?error=unauthorized");
        exit();
    }
}

// Function to authenticate user
function authenticate_user($email, $password) {
    global $conn;
    
    $email = sanitize_input($email);
    $user_type = get_role_from_email($email);
    
    if ($user_type == 'admin') {
        $table = 'Admin';
        $id_field = 'admin_id';
    } elseif ($user_type == 'faculty') {
        $table = 'Faculty';
        $id_field = 'faculty_id';
    } elseif ($user_type == 'student') {
        $table = 'Student';
        $id_field = 'student_id';
    } else {
        return false;
    }
    
    $query = "SELECT * FROM $table WHERE email = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        if (verify_password($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user[$id_field];
            $_SESSION['user_type'] = $user_type;
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            
            // Log the login activity
            log_activity($user[$id_field], $user_type, 'User logged in');
            
            return true;
        }
    }
    
    return false;
}

// Function to logout user
function logout_user() {
    // Log the logout activity if user is logged in
    if (is_logged_in()) {
        log_activity($_SESSION['user_id'], $_SESSION['user_type'], 'User logged out');
    }
    
    // Destroy the session
    session_unset();
    session_destroy();
    
    // Redirect to login page
    header("Location: login.php");
    exit();
}
?>
