<?php
session_start();
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Set timezone to IST for consistent time handling
date_default_timezone_set('Asia/Kolkata');

// Require admin login
require_user_type('admin');

$admin_id = $_SESSION['user_id'];
$error = "";
$success = "";

// Get admin details
$stmt = $conn->prepare("SELECT * FROM Admin WHERE admin_id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $name = sanitize_input($_POST['name']);
    $email = sanitize_input($_POST['email']);
    $contact = sanitize_input($_POST['contact']);
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    } else {
        // Check if email exists for other admins
        $stmt = $conn->prepare("SELECT admin_id FROM Admin WHERE email = ? AND admin_id != ?");
        $stmt->bind_param("si", $email, $admin_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "Email is already in use by another administrator";
        } else {
            // Update admin profile
            $stmt = $conn->prepare("UPDATE Admin SET name = ?, email = ?, contact = ? WHERE admin_id = ?");
            $stmt->bind_param("sssi", $name, $email, $contact, $admin_id);
            
            if ($stmt->execute()) {
                // Update session variables
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                
                $success = "Profile updated successfully";
                log_activity($admin_id, 'admin', 'Updated profile information');
                
                // Refresh admin data
                $stmt = $conn->prepare("SELECT * FROM Admin WHERE admin_id = ?");
                $stmt->bind_param("i", $admin_id);
                $stmt->execute();
                $admin = $stmt->get_result()->fetch_assoc();
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
    $stmt = $conn->prepare("SELECT password FROM Admin WHERE admin_id = ?");
    $stmt->bind_param("i", $admin_id);
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
        $stmt = $conn->prepare("UPDATE Admin SET password = ? WHERE admin_id = ?");
        $stmt->bind_param("si", $new_hash, $admin_id);
        
        if ($stmt->execute()) {
            $success = "Password changed successfully";
            log_activity($admin_id, 'admin', 'Changed account password');
        } else {
            $error = "Failed to change password: " . $stmt->error;
        }
    }
}

// Get admin statistics
$faculty_count = $conn->query("SELECT COUNT(*) as count FROM Faculty")->fetch_assoc()['count'];
$student_count = $conn->query("SELECT COUNT(*) as count FROM Student")->fetch_assoc()['count'];
$college_count = $conn->query("SELECT COUNT(*) as count FROM College")->fetch_assoc()['count'];
$venue_count = $conn->query("SELECT COUNT(*) as count FROM Venue")->fetch_assoc()['count'];

// Get recent logs for this admin
$logs = [];
$stmt = $conn->prepare("SELECT * FROM System_Logs WHERE user_id = ? AND user_type = 'admin' ORDER BY created_at DESC LIMIT 5");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $logs[] = $row;
}

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
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($admin['name']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <label for="email" class="col-sm-3 col-form-label">Email Address</label>
                            <div class="col-sm-9">
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <label for="contact" class="col-sm-3 col-form-label">Contact Number</label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" id="contact" name="contact" value="<?php echo htmlspecialchars($admin['contact']); ?>" required>
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
                    <p><strong>Admin ID:</strong> <?php echo $admin['admin_id']; ?></p>
                    <p><strong>Account Created:</strong> <?php echo isset($admin['created_at']) ? date('M d, Y', strtotime($admin['created_at'])) : 'N/A'; ?></p>
                    <p><strong>Last Updated:</strong> <?php echo isset($admin['updated_at']) ? date('M d, Y', strtotime($admin['updated_at'])) : 'N/A'; ?></p>
                    <p><strong>Account Status:</strong> 
                        <?php if (isset($admin['status']) && $admin['status'] == 1): ?>
                            <span class="badge bg-danger">Inactive</span>
                        <?php else: ?>
                            <span class="badge bg-success">Active</span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>System Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>Faculty Members</div>
                        <span class="badge bg-primary rounded-pill"><?php echo $faculty_count; ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>Students</div>
                        <span class="badge bg-primary rounded-pill"><?php echo $student_count; ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>Colleges</div>
                        <span class="badge bg-primary rounded-pill"><?php echo $college_count; ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <div>Venues</div>
                        <span class="badge bg-primary rounded-pill"><?php echo $venue_count; ?></span>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="dashboard.php" class="btn btn-sm btn-outline-success w-100">View Dashboard</a>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Activity</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($logs)): ?>
                        <p class="text-center p-3">No recent activity found.</p>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($logs as $log): ?>
                                <li class="list-group-item">
                                    <small class="text-muted"><?php echo date('M d, Y h:i A', strtotime($log['created_at'])); ?></small>
                                    <p class="mb-0"><?php echo htmlspecialchars($log['activity']); ?></p>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                <div class="card-footer">
                    <a href="system_logs.php" class="btn btn-sm btn-outline-secondary w-100">View All Logs</a>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header bg-danger text-white">
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
                            <a href="manage_faculty.php" class="text-decoration-none">
                                <i class="fas fa-chalkboard-teacher me-2"></i>Manage Faculty
                            </a>
                        </li>
                        <li class="list-group-item">
                            <a href="manage_students.php" class="text-decoration-none">
                                <i class="fas fa-user-graduate me-2"></i>Manage Students
                            </a>
                        </li>
                        <li class="list-group-item">
                            <a href="manage_colleges.php" class="text-decoration-none">
                                <i class="fas fa-university me-2"></i>Manage Colleges
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
