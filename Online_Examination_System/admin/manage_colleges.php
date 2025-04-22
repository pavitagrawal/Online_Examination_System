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

// Handle college addition
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_college'])) {
    $name = sanitize_input($_POST['name']);
    $address = sanitize_input($_POST['address']);
    
    // Check if college name already exists
    $stmt = $conn->prepare("SELECT * FROM College WHERE name = ?");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $error = "College with this name already exists";
    } else {
        $stmt = $conn->prepare("INSERT INTO College (name, address) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $address);
        
        if ($stmt->execute()) {
            $success = "College added successfully";
            log_activity($admin_id, 'admin', 'Added new college: ' . $name);
        } else {
            $error = "Failed to add college: " . $stmt->error;
        }
    }
}

// Handle college deletion
if (isset($_GET['delete'])) {
    $college_id = (int)$_GET['delete'];
    
    // Get college name for logging
    $stmt = $conn->prepare("SELECT name FROM College WHERE college_id = ?");
    $stmt->bind_param("i", $college_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $college = $result->fetch_assoc();
    
    // Check if there are any students or faculty associated with this college
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Student WHERE college_id = ?");
    $stmt->bind_param("i", $college_id);
    $stmt->execute();
    $student_count = $stmt->get_result()->fetch_assoc()['count'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Faculty WHERE college_id = ?");
    $stmt->bind_param("i", $college_id);
    $stmt->execute();
    $faculty_count = $stmt->get_result()->fetch_assoc()['count'];
    
    if ($student_count > 0 || $faculty_count > 0) {
        $error = "Cannot delete college because it has associated students or faculty members";
    } else {
        $stmt = $conn->prepare("DELETE FROM College WHERE college_id = ?");
        $stmt->bind_param("i", $college_id);
        
        if ($stmt->execute()) {
            $success = "College deleted successfully";
            log_activity($admin_id, 'admin', 'Deleted college: ' . $college['name']);
            header("Location: manage_colleges.php?success=" . urlencode($success));
            exit();
        } else {
            $error = "Failed to delete college: " . $stmt->error;
        }
    }
}

// Handle college edit
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_college'])) {
    $college_id = (int)$_POST['college_id'];
    $name = sanitize_input($_POST['name']);
    $address = sanitize_input($_POST['address']);
    
    // Check if college name already exists for other colleges
    $stmt = $conn->prepare("SELECT * FROM College WHERE name = ? AND college_id != ?");
    $stmt->bind_param("si", $name, $college_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $error = "Another college with this name already exists";
    } else {
        $stmt = $conn->prepare("UPDATE College SET name = ?, address = ? WHERE college_id = ?");
        $stmt->bind_param("ssi", $name, $address, $college_id);
        
        if ($stmt->execute()) {
            $success = "College updated successfully";
            log_activity($admin_id, 'admin', 'Updated college ID: ' . $college_id);
        } else {
            $error = "Failed to update college: " . $stmt->error;
        }
    }
}

// Get all colleges
$colleges = [];
$query = "SELECT c.*, 
          (SELECT COUNT(*) FROM Student WHERE college_id = c.college_id) as student_count,
          (SELECT COUNT(*) FROM Faculty WHERE college_id = c.college_id) as faculty_count
          FROM College c ORDER BY c.name";
$result = $conn->query($query);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $colleges[] = $row;
    }
}

$base_path = '../';
include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Manage Colleges</h1>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCollegeModal">
        <i class="fas fa-plus"></i> Add College
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
            <table class="table table-striped table-hover" id="collegesTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Address</th>
                        <th>Students</th>
                        <th>Faculty</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($colleges as $college): ?>
                        <tr>
                            <td><?php echo $college['college_id']; ?></td>
                            <td><?php echo htmlspecialchars($college['name']); ?></td>
                            <td><?php echo htmlspecialchars($college['address']); ?></td>
                            <td><?php echo $college['student_count']; ?></td>
                            <td><?php echo $college['faculty_count']; ?></td>
                            <td>
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#editCollegeModal<?php echo $college['college_id']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="manage_colleges.php?delete=<?php echo $college['college_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this college?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                    <a href="view_college.php?id=<?php echo $college['college_id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </div>
                                
                                <!-- Edit College Modal -->
                                <div class="modal fade" id="editCollegeModal<?php echo $college['college_id']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit College</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <form method="post" action="">
                                                <div class="modal-body">
                                                    <input type="hidden" name="college_id" value="<?php echo $college['college_id']; ?>">
                                                    
                                                    <div class="mb-3">
                                                        <label for="name<?php echo $college['college_id']; ?>" class="form-label">College Name</label>
                                                        <input type="text" class="form-control" id="name<?php echo $college['college_id']; ?>" name="name" value="<?php echo htmlspecialchars($college['name']); ?>" required>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="address<?php echo $college['college_id']; ?>" class="form-label">Address</label>
                                                        <textarea class="form-control" id="address<?php echo $college['college_id']; ?>" name="address" rows="3" required><?php echo htmlspecialchars($college['address']); ?></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                    <button type="submit" name="edit_college" class="btn btn-primary">Save Changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($colleges)): ?>
                        <tr>
                            <td colspan="6" class="text-center">No colleges found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add College Modal -->
<div class="modal fade" id="addCollegeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add College</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">College Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="add_college" class="btn btn-primary">Add College</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Initialize DataTable for better table functionality
document.addEventListener('DOMContentLoaded', function() {
    if (typeof $.fn.DataTable !== 'undefined') {
        $('#collegesTable').DataTable({
            "pageLength": 25,
            "order": [[1, "asc"]], // Sort by name by default
            "columnDefs": [
                { "orderable": false, "targets": 5 } // Disable sorting for actions column
            ]
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>
