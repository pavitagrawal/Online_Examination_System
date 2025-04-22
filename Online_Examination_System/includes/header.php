<?php
if (!isset($_SESSION)) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Examination System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo isset($base_path) ? $base_path : ''; ?>assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="<?php echo isset($base_path) ? $base_path : ''; ?>index.php">
                <i class="fas fa-graduation-cap"></i> Online Exam System
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php if ($_SESSION['user_type'] == 'admin'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo isset($base_path) ? $base_path : ''; ?>admin/dashboard.php">Dashboard</a>
                            </li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                    Management
                                </a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="<?php echo isset($base_path) ? $base_path : ''; ?>admin/manage_faculty.php">Faculty</a></li>
                                    <li><a class="dropdown-item" href="<?php echo isset($base_path) ? $base_path : ''; ?>admin/manage_students.php">Students</a></li>
                                    <li><a class="dropdown-item" href="<?php echo isset($base_path) ? $base_path : ''; ?>admin/manage_colleges.php">Colleges</a></li>
                                    <li><a class="dropdown-item" href="<?php echo isset($base_path) ? $base_path : ''; ?>admin/manage_venues.php">Venues</a></li>
                                </ul>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo isset($base_path) ? $base_path : ''; ?>admin/system_logs.php">System Logs</a>
                            </li>
                        <?php elseif ($_SESSION['user_type'] == 'faculty'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo isset($base_path) ? $base_path : ''; ?>faculty/dashboard.php">Dashboard</a>
                            </li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                    Exams
                                </a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="<?php echo isset($base_path) ? $base_path : ''; ?>faculty/create_exam.php">Create Exam</a></li>
                                    <li><a class="dropdown-item" href="<?php echo isset($base_path) ? $base_path : ''; ?>faculty/manage_exams.php">Manage Exams</a></li>
                                    <li><a class="dropdown-item" href="<?php echo isset($base_path) ? $base_path : ''; ?>faculty/view_results.php">View Results</a></li>
                                </ul>
                            </li>
                        <?php elseif ($_SESSION['user_type'] == 'student'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo isset($base_path) ? $base_path : ''; ?>student/dashboard.php">Dashboard</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo isset($base_path) ? $base_path : ''; ?>student/available_exams.php">Available Exams</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo isset($base_path) ? $base_path : ''; ?>student/exam_results.php">My Results</a>
                            </li>
                        <?php endif; ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user"></i> <?php echo $_SESSION['user_name']; ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li>
                                    <a class="dropdown-item" href="<?php echo isset($base_path) ? $base_path : ''; ?><?php echo $_SESSION['user_type']; ?>/profile.php">
                                        Profile
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo isset($base_path) ? $base_path : ''; ?>logout.php">
                                        Logout
                                    </a>
                                </li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo isset($base_path) ? $base_path : ''; ?>login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo isset($base_path) ? $base_path : ''; ?>register.php">Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container mt-4">
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_GET['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_GET['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
