<?php
session_start();
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Set timezone to IST for consistent time handling
date_default_timezone_set('Asia/Kolkata');

// Require student login
require_user_type('student');

$student_id = $_SESSION['user_id'];
$error = "";
$success = "";

// Get student details
$stmt = $conn->prepare("SELECT s.*, c.name as college_name FROM Student s LEFT JOIN College c ON s.college_id = c.college_id WHERE s.student_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $name = sanitize_input($_POST['name']);
    $email = sanitize_input($_POST['email']);
    $contact = sanitize_input($_POST['contact']);
    $college_id = (int)$_POST['college_id'];
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    } else {
        // Check if email exists for other students
        $stmt = $conn->prepare("SELECT student_id FROM Student WHERE email = ? AND student_id != ?");
        $stmt->bind_param("si", $email, $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "Email is already in use by another student";
        } else {
            // Update student profile
            $stmt = $conn->prepare("UPDATE Student SET name = ?, email = ?, contact = ?, college_id = ? WHERE student_id = ?");
            $stmt->bind_param("sssii", $name, $email, $contact, $college_id, $student_id);
            
            if ($stmt->execute()) {
                // Update session variables
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                
                $success = "Profile updated successfully";
                log_activity($student_id, 'student', 'Updated profile information');
                
                // Refresh student data
                $stmt = $conn->prepare("SELECT s.*, c.name as college_name FROM Student s LEFT JOIN College c ON s.college_id = c.college_id WHERE s.student_id = ?");
                $stmt->bind_param("i", $student_id);
                $stmt->execute();
                $student = $stmt->get_result()->fetch_assoc();
            } else {
                $error = "Failed to update profile: " . $stmt->error;
            }
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Get current password hash
    $stmt = $conn->prepare("SELECT password FROM Student WHERE student_id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $current_hash = $row['password'];
    
    // Validate passwords
    if (!password_verify($current_password, $current_hash)) {
        $error = "Current password is incorrect";
    } elseif ($new_password != $confirm_password) {
        $error = "New passwords do not match";
    } elseif (strlen($new_password) < 8) {
        $error = "New password must be at least 8 characters long";
    } else {
        // Hash the new password
        $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
        
        // Update password
        $stmt = $conn->prepare("UPDATE Student SET password = ? WHERE student_id = ?");
        $stmt->bind_param("si", $new_hash, $student_id);
        
        if ($stmt->execute()) {
            $success = "Password changed successfully";
            log_activity($student_id, 'student', 'Changed account password');
        } else {
            $error = "Failed to change password: " . $stmt->error;
        }
    }
}

// Get colleges for dropdown
$colleges = [];
$result = $conn->query("SELECT college_id, name FROM College ORDER BY name");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $colleges[] = $row;
    }
}

// Get exam statistics
$exams_taken_query = $conn->prepare("SELECT COUNT(*) as count FROM Exam_Attempt WHERE student_id = ?");
$exams_taken_query->bind_param("i", $student_id);
$exams_taken_query->execute();
$exams_taken = $exams_taken_query->get_result()->fetch_assoc()['count'];

$exams_passed_query = $conn->prepare("
    SELECT COUNT(*) as count FROM Exam_Attempt ea
    JOIN Exam e ON ea.exam_id = e.exam_id
    WHERE ea.student_id = ? AND ea.status = 'completed' AND (ea.total_score / e.total_marks * 100) >= 40
");
$exams_passed_query->bind_param("i", $student_id);
$exams_passed_query->execute();
$exams_passed = $exams_passed_query->get_result()->fetch_assoc()['count'];

$avg_score_query = $conn->prepare("
    SELECT AVG(ea.total_score / e.total_marks * 100) as avg_score 
    FROM Exam_Attempt ea
    JOIN Exam e ON ea.exam_id = e.exam_id
    WHERE ea.student_id = ? AND ea.status = 'completed'
");
$avg_score_query->bind_param("i", $student_id);
$avg_score_query->execute();
$avg_score_result = $avg_score_query->get_result()->fetch_assoc();
$avg_score = $avg_score_result['avg_score'] ? round($avg_score_result['avg_score'], 2) : 0;

$base_path = '../';
include '../includes/header.php';
?>

<div class="container mt-4">
    <h1 class="mb-4">My Profile</h1>
    
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Profile Information Column -->
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-user-circle me-2"></i>Profile Information</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="">
                        <div class="row mb-3">
                            <label for="reg_no" class="col-sm-3 col-form-label">Registration Number</label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" id="reg_no" value="<?php echo htmlspecialchars($student['reg_no']); ?>" readonly>
                                <small class="form-text text-muted">Registration number cannot be changed.</small>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <label for="name" class="col-sm-3 col-form-label">Full Name</label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($student['name']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <label for="email" class="col-sm-3 col-form-label">Email Address</label>
                            <div class="col-sm-9">
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($student['email']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <label for="contact" class="col-sm-3 col-form-label">Contact Number</label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" id="contact" name="contact" value="<?php echo htmlspecialchars($student['contact']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <label for="college_id" class="col-sm-3 col-form-label">College</label>
                            <div class="col-sm-9">
                                <select class="form-select" id="college_id" name="college_id">
                                    <option value="">Select College</option>
                                    <?php foreach ($colleges as $college): ?>
                                        <option value="<?php echo $college['college_id']; ?>" <?php echo ($student['college_id'] == $college['college_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($college['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-sm-9 offset-sm-3">
                                <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="fas fa-key me-2"></i>Change Password</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="" id="passwordForm">
                        <div class="row mb-3">
                            <label for="current_password" class="col-sm-3 col-form-label">Current Password</label>
                            <div class="col-sm-9">
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <label for="new_password" class="col-sm-3 col-form-label">New Password</label>
                            <div class="col-sm-9">
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                <div class="form-text">Password must be at least 8 characters long.</div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <label for="confirm_password" class="col-sm-3 col-form-label">Confirm New Password</label>
                            <div class="col-sm-9">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-sm-9 offset-sm-3">
                                <button type="submit" name="change_password" class="btn btn-warning">Change Password</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Stats and Account Info Column -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Account Information</h5>
                </div>
                <div class="card-body">
                    <p><strong>Student ID:</strong> <?php echo $student['student_id']; ?></p>
                    <p><strong>Registration No:</strong> <?php echo htmlspecialchars($student['reg_no']); ?></p>
                    <p><strong>College:</strong> <?php echo htmlspecialchars($student['college_name'] ?? 'Not Assigned'); ?></p>
                    <p><strong>Account Created:</strong> <?php echo isset($student['created_at']) ? date('M d, Y', strtotime($student['created_at'])) : 'N/A'; ?></p>
                    <p><strong>Last Updated:</strong> <?php echo isset($student['updated_at']) ? date('M d, Y', strtotime($student['updated_at'])) : 'N/A'; ?></p>
                    <p><strong>Account Status:</strong> 
                        <?php if (isset($student['status']) && $student['status'] == 1): ?>
                            <span class="badge bg-danger">Inactive</span>
                        <?php else: ?>
                            <span class="badge bg-success">Active</span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Exam Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>Exams Taken</div>
                        <span class="badge bg-primary rounded-pill"><?php echo $exams_taken; ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>Exams Passed</div>
                        <span class="badge bg-success rounded-pill"><?php echo $exams_passed; ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>Pass Rate</div>
                        <span class="badge bg-info rounded-pill">
                            <?php echo $exams_taken > 0 ? round(($exams_passed / $exams_taken) * 100) : 0; ?>%
                        </span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <div>Average Score</div>
                        <span class="badge bg-warning rounded-pill"><?php echo $avg_score; ?>%</span>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="exam_results.php" class="btn btn-sm btn-outline-success w-100">View All Results</a>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="fas fa-link me-2"></i>Quick Links</h5>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">
                            <a href="dashboard.php" class="text-decoration-none">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="list-group-item">
                            <a href="available_exams.php" class="text-decoration-none">
                                <i class="fas fa-file-alt me-2"></i>Available Exams
                            </a>
                        </li>
                        <li class="list-group-item">
                            <a href="exam_results.php" class="text-decoration-none">
                                <i class="fas fa-chart-pie me-2"></i>Exam Results
                            </a>
                        </li>
                        <li class="list-group-item">
                            <a href="../logout.php" class="text-decoration-none text-danger">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Password validation
document.addEventListener('DOMContentLoaded', function() {
    const passwordForm = document.getElementById('passwordForm');
    const newPasswordInput = document.getElementById('new_password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    
    passwordForm.addEventListener('submit', function(event) {
        if (newPasswordInput.value.length < 8) {
            alert('New password must be at least 8 characters long');
            event.preventDefault();
            return false;
        }
        
        if (newPasswordInput.value !== confirmPasswordInput.value) {
            alert('New passwords do not match');
            event.preventDefault();
            return false;
        }
        
        return true;
    });
});
</script>

<?php include '../includes/footer.php'; ?>
