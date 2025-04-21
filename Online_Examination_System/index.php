<?php
session_start();
include 'includes/header.php';
?>

<div class="jumbotron bg-light p-5 rounded">
    <h1 class="display-4">Welcome to the Online Examination System</h1>
    <p class="lead">A comprehensive platform for creating, managing, and taking online exams.</p>
    <hr class="my-4">
    <p>This system provides a seamless experience for administrators, faculty, and students.</p>
    
    <?php if (!isset($_SESSION['user_id'])): ?>
        <div class="mt-4">
            <a href="login.php" class="btn btn-primary btn-lg me-2">Login</a>
            <a href="register.php" class="btn btn-secondary btn-lg">Register</a>
        </div>
    <?php else: ?>
        <div class="mt-4">
            <a href="<?php echo $_SESSION['user_type']; ?>/dashboard.php" class="btn btn-primary btn-lg">Go to Dashboard</a>
        </div>
    <?php endif; ?>
</div>

<div class="row mt-5">
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-body text-center">
                <i class="fas fa-user-shield fa-4x mb-3 text-primary"></i>
                <h3 class="card-title">Admin</h3>
                <p class="card-text">Manage the entire system, including users, colleges, and system settings.</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-body text-center">
                <i class="fas fa-chalkboard-teacher fa-4x mb-3 text-success"></i>
                <h3 class="card-title">Faculty</h3>
                <p class="card-text">Create and manage exams, add questions, and view student results.</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-body text-center">
                <i class="fas fa-user-graduate fa-4x mb-3 text-info"></i>
                <h3 class="card-title">Student</h3>
                <p class="card-text">Take exams, view available tests, and check your results.</p>
            </div>
        </div>
    </div>
</div>

<div class="row mt-3">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4>Features</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <ul class="list-group">
                            <li class="list-group-item"><i class="fas fa-check text-success me-2"></i> Multiple choice, true/false, and descriptive questions</li>
                            <li class="list-group-item"><i class="fas fa-check text-success me-2"></i> Automatic grading for objective questions</li>
                            <li class="list-group-item"><i class="fas fa-check text-success me-2"></i> Timed examinations with auto-submit</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <ul class="list-group">
                            <li class="list-group-item"><i class="fas fa-check text-success me-2"></i> Detailed result analysis</li>
                            <li class="list-group-item"><i class="fas fa-check text-success me-2"></i> Secure authentication system</li>
                            <li class="list-group-item"><i class="fas fa-check text-success me-2"></i> Mobile-friendly interface</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>