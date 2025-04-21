<?php
session_start();
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Set timezone to IST for consistent time handling
date_default_timezone_set('Asia/Kolkata');

// Require faculty login
require_user_type('faculty');

$faculty_id = $_SESSION['user_id'];
$error = "";
$success = "";

// Get faculty details
$stmt = $conn->prepare("SELECT f.*, c.name as college_name FROM Faculty f LEFT JOIN College c ON f.college_id = c.college_id WHERE f.faculty_id = ?");
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$faculty = $stmt->get_result()->fetch_assoc();

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
        // Check if email exists for other users
        $stmt = $conn->prepare("SELECT faculty_id FROM Faculty WHERE email = ? AND faculty_id != ?");
        $stmt->bind_param("si", $email, $faculty_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "Email is already in use by another faculty member";
        } else {
            // Update faculty profile
            $stmt = $conn->prepare("UPDATE Faculty SET name = ?, email = ?, contact = ?, college_id = ? WHERE faculty_id = ?");
            $stmt->bind_param("sssii", $name, $email, $contact, $college_id, $faculty_id);
            
            if ($stmt->execute()) {
                // Update session variables
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                
                $success = "Profile updated successfully";
                log_activity($faculty_id, 'faculty', 'Updated profile information');
                
                // Refresh faculty data
                $stmt = $conn->prepare("SELECT f.*, c.name as college_name FROM Faculty f LEFT JOIN College c ON f.college_id = c.college_id WHERE f.faculty_id = ?");
                $stmt->bind_param("i", $faculty_id);
                $stmt->execute();
                $faculty = $stmt->get_result()->fetch_assoc();
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
    $stmt = $conn->prepare("SELECT password FROM Faculty WHERE faculty_id = ?");
    $stmt->bind_param("i", $faculty_id);
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
        $stmt = $conn->prepare("UPDATE Faculty SET password = ? WHERE faculty_id = ?");
        $stmt->bind_param("si", $new_hash, $faculty_id);
        
        if ($stmt->execute()) {
            $success = "Password changed successfully";
            log_activity($faculty_id, 'faculty', 'Changed account password');
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

// Get faculty statistics
$exams_query = $conn->prepare("SELECT COUNT(*) as count FROM Exam WHERE created_by = ?");
$exams_query->bind_param("i", $faculty_id);
$exams_query->execute();
$total_exams = $exams_query->get_result()->fetch_assoc()['count'];

$questions_query = $conn->prepare("
    SELECT COUNT(*) as count FROM Question q 
    JOIN Exam e ON q.exam_id = e.exam_id 
    WHERE e.created_by = ?
");
$questions_query->bind_param("i", $faculty_id);
$questions_query->execute();
$total_questions = $questions_query->get_result()->fetch_assoc()['count'];

$attempts_query = $conn->prepare("
    SELECT COUNT(*) as count FROM Exam_Attempt ea 
    JOIN Exam e ON ea.exam_id = e.exam_id 
    WHERE e.created_by = ?
");
$attempts_query->bind_param("i", $faculty_id);
$attempts_query->execute();
$total_attempts = $attempts_query->get_result()->fetch_assoc()['count'];

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
                            <label for="name" class="col-sm-3 col-form-label">Full Name</label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($faculty['name']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <label for="email" class="col-sm-3 col-form-label">Email Address</label>
                            <div class="col-sm-9">
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($faculty['email']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <label for="contact" class="col-sm-3 col-form-label">Contact Number</label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" id="contact" name="contact" value="<?php echo htmlspecialchars($faculty['contact']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <label for="college_id" class="col-sm-3 col-form-label">College</label>
                            <div class="col-sm-9">
                                <select class="form-select" id="college_id" name="college_id">
                                    <option value="">Select College</option>
                                    <?php foreach ($colleges as $college): ?>
                                        <option value="<?php echo $college['college_id']; ?>" <?php echo ($faculty['college_id'] == $college['college_id']) ? 'selected' : ''; ?>>
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
                    <form method="post" action="">
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
                    <p><strong>Faculty ID:</strong> <?php echo $faculty['faculty_id']; ?></p>
                    <p><strong>Account Created:</strong> <?php echo isset($faculty['created_at']) ? date('M d, Y', strtotime($faculty['created_at'])) : 'N/A'; ?></p>
                    <p><strong>Last Updated:</strong> <?php echo isset($faculty['updated_at']) ? date('M d, Y', strtotime($faculty['updated_at'])) : 'N/A'; ?></p>
                    <p><strong>Account Status:</strong> 
                        <?php if (isset($faculty['status']) && $faculty['status'] == 1): ?>
                            <span class="badge bg-danger">Inactive</span>
                        <?php else: ?>
                            <span class="badge bg-success">Active</span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Activity Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>Total Exams Created</div>
                        <span class="badge bg-primary rounded-pill"><?php echo $total_exams; ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>Total Questions Created</div>
                        <span class="badge bg-primary rounded-pill"><?php echo $total_questions; ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <div>Student Exam Attempts</div>
                        <span class="badge bg-primary rounded-pill"><?php echo $total_attempts; ?></span>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="manage_exams.php" class="btn btn-sm btn-outline-success w-100">View All Exams</a>
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
                            <a href="create_exam.php" class="text-decoration-none">
                                <i class="fas fa-plus-circle me-2"></i>Create New Exam
                            </a>
                        </li>
                        <li class="list-group-item">
                            <a href="view_results.php" class="text-decoration-none">
                                <i class="fas fa-chart-pie me-2"></i>View Exam Results
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
    const newPasswordInput = document.getElementById('new_password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const passwordForm = document.querySelector('form[name="change_password"]');
    
    if (passwordForm) {
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
    }
});
</script>

<?php include '../includes/footer.php'; ?>
