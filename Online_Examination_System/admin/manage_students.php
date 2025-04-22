<?php
session_start();
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Require admin login
require_user_type('admin');

$admin_id = $_SESSION['user_id'];
$error = "";
$success = "";

// Handle student addition
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_student'])) {
    $name = sanitize_input($_POST['name']);
    $email = sanitize_input($_POST['email']);
    $reg_no = sanitize_input($_POST['reg_no']);
    $contact = sanitize_input($_POST['contact']);
    $college_id = (int)$_POST['college_id'];
    
    // Generate a random password
    $password = generate_password();
    $hashed_password = hash_password($password);
    
    // Check if email already exists
    $stmt = $conn->prepare("SELECT * FROM Student WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $error = "Email already exists";
    } else {
        // Check if registration number already exists
        $stmt = $conn->prepare("SELECT * FROM Student WHERE reg_no = ?");
        $stmt->bind_param("s", $reg_no);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "Registration number already exists";
        } else {
            $stmt = $conn->prepare("INSERT INTO Student (reg_no, name, email, password, contact, college_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssi", $reg_no, $name, $email, $hashed_password, $contact, $college_id);
            
            if ($stmt->execute()) {
                $success = "Student added successfully. Temporary password: " . $password;
                log_activity($admin_id, 'admin', 'Added new student: ' . $email);
            } else {
                $error = "Failed to add student: " . $stmt->error;
            }
        }
    }
}

// Handle student deletion
if (isset($_GET['delete'])) {
    $student_id = (int)$_GET['delete'];
    
    // Get student email for logging
    $stmt = $conn->prepare("SELECT email FROM Student WHERE student_id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    
    // Check if student has any exam attempts
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Exam_Attempt WHERE student_id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $attempts = $result->fetch_assoc()['count'];
    
    if ($attempts > 0) {
        $error = "Cannot delete student because they have exam attempts. Consider deactivating instead.";
    } else {
        $stmt = $conn->prepare("DELETE FROM Student WHERE student_id = ?");
        $stmt->bind_param("i", $student_id);
        
        if ($stmt->execute()) {
            $success = "Student deleted successfully";
            log_activity($admin_id, 'admin', 'Deleted student: ' . $student['email']);
            header("Location: manage_students.php?success=" . urlencode($success));
            exit();
        } else {
            $error = "Failed to delete student: " . $stmt->error;
        }
    }
}

// Handle student edit
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_student'])) {
    $student_id = (int)$_POST['student_id'];
    $name = sanitize_input($_POST['name']);
    $email = sanitize_input($_POST['email']);
    $reg_no = sanitize_input($_POST['reg_no']);
    $contact = sanitize_input($_POST['contact']);
    $college_id = (int)$_POST['college_id'];
    
    // Check if email exists for other students
    $stmt = $conn->prepare("SELECT * FROM Student WHERE email = ? AND student_id != ?");
    $stmt->bind_param("si", $email, $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $error = "Email already exists for another student";
    } else {
        // Check if registration number exists for other students
        $stmt = $conn->prepare("SELECT * FROM Student WHERE reg_no = ? AND student_id != ?");
        $stmt->bind_param("si", $reg_no, $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "Registration number already exists for another student";
        } else {
            $stmt = $conn->prepare("UPDATE Student SET reg_no = ?, name = ?, email = ?, contact = ?, college_id = ? WHERE student_id = ?");
            $stmt->bind_param("ssssii", $reg_no, $name, $email, $contact, $college_id, $student_id);
            
            if ($stmt->execute()) {
                $success = "Student updated successfully";
                log_activity($admin_id, 'admin', 'Updated student ID: ' . $student_id);
            } else {
                $error = "Failed to update student: " . $stmt->error;
            }
        }
    }
}

// Handle password reset
if (isset($_GET['reset_password'])) {
    $student_id = (int)$_GET['reset_password'];
    
    // Generate a new password
    $new_password = generate_password();
    $hashed_password = hash_password($new_password);
    
    // Update the password
    $stmt = $conn->prepare("UPDATE Student SET password = ? WHERE student_id = ?");
    $stmt->bind_param("si", $hashed_password, $student_id);
    
    if ($stmt->execute()) {
        $success = "Password reset successfully. New password: " . $new_password;
        log_activity($admin_id, 'admin', 'Reset password for student ID: ' . $student_id);
    } else {
        $error = "Failed to reset password: " . $stmt->error;
    }
}

// Get all students with college names
$students = [];
$query = "
    SELECT s.*, c.name as college_name 
    FROM Student s 
    LEFT JOIN College c ON s.college_id = c.college_id 
    ORDER BY s.name
";
$result = $conn->query($query);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
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

$base_path = '../';
include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Manage Students</h1>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal">
        <i class="fas fa-plus"></i> Add Student
    </button>
</div>

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

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover" id="studentsTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Reg. No.</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Contact</th>
                        <th>College</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td><?php echo $student['student_id']; ?></td>
                            <td><?php echo htmlspecialchars($student['reg_no']); ?></td>
                            <td><?php echo htmlspecialchars($student['name']); ?></td>
                            <td><?php echo htmlspecialchars($student['email']); ?></td>
                            <td><?php echo htmlspecialchars($student['contact']); ?></td>
                            <td><?php echo htmlspecialchars($student['college_name'] ?? 'Not Assigned'); ?></td>
                            <td>
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#editStudentModal<?php echo $student['student_id']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="manage_students.php?reset_password=<?php echo $student['student_id']; ?>" class="btn btn-sm btn-warning" onclick="return confirm('Are you sure you want to reset the password for this student?')">
                                        <i class="fas fa-key"></i>
                                    </a>
                                    <a href="manage_students.php?delete=<?php echo $student['student_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this student?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                    <a href="view_student.php?id=<?php echo $student['student_id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </div>
                                
                                <!-- Edit Student Modal -->
                                <div class="modal fade" id="editStudentModal<?php echo $student['student_id']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit Student</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <form method="post" action="">
                                                <div class="modal-body">
                                                    <input type="hidden" name="student_id" value="<?php echo $student['student_id']; ?>">
                                                    
                                                    <div class="mb-3">
                                                        <label for="reg_no<?php echo $student['student_id']; ?>" class="form-label">Registration Number</label>
                                                        <input type="text" class="form-control" id="reg_no<?php echo $student['student_id']; ?>" name="reg_no" value="<?php echo htmlspecialchars($student['reg_no']); ?>" required>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="name<?php echo $student['student_id']; ?>" class="form-label">Name</label>
                                                        <input type="text" class="form-control" id="name<?php echo $student['student_id']; ?>" name="name" value="<?php echo htmlspecialchars($student['name']); ?>" required>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="email<?php echo $student['student_id']; ?>" class="form-label">Email</label>
                                                        <input type="email" class="form-control" id="email<?php echo $student['student_id']; ?>" name="email" value="<?php echo htmlspecialchars($student['email']); ?>" required>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="contact<?php echo $student['student_id']; ?>" class="form-label">Contact</label>
                                                        <input type="text" class="form-control" id="contact<?php echo $student['student_id']; ?>" name="contact" value="<?php echo htmlspecialchars($student['contact']); ?>" required>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="college_id<?php echo $student['student_id']; ?>" class="form-label">College</label>
                                                        <select class="form-select" id="college_id<?php echo $student['student_id']; ?>" name="college_id">
                                                            <option value="">Select College</option>
                                                            <?php foreach ($colleges as $college): ?>
                                                                <option value="<?php echo $college['college_id']; ?>" <?php echo ($student['college_id'] == $college['college_id']) ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars($college['name']); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                    <button type="submit" name="edit_student" class="btn btn-primary">Save Changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($students)): ?>
                        <tr>
                            <td colspan="7" class="text-center">No students found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Student Modal -->
<div class="modal fade" id="addStudentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Student</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="reg_no" class="form-label">Registration Number</label>
                        <input type="text" class="form-control" id="reg_no" name="reg_no" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                        <small class="form-text text-muted">A temporary password will be generated automatically.</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="contact" class="form-label">Contact</label>
                        <input type="text" class="form-control" id="contact" name="contact" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="college_id" class="form-label">College</label>
                        <select class="form-select" id="college_id" name="college_id">
                            <option value="">Select College</option>
                            <?php foreach ($colleges as $college): ?>
                                <option value="<?php echo $college['college_id']; ?>">
                                    <?php echo htmlspecialchars($college['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="add_student" class="btn btn-primary">Add Student</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Initialize DataTable for better table functionality
document.addEventListener('DOMContentLoaded', function() {
    if (typeof $.fn.DataTable !== 'undefined') {
        $('#studentsTable').DataTable({
            "pageLength": 25,
            "order": [[2, "asc"]], // Sort by name by default
            "columnDefs": [
                { "orderable": false, "targets": 6 } // Disable sorting for actions column
            ]
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>
