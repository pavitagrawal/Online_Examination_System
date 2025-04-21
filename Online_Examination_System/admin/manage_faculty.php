<?php
require_once '../includes/auth.php';
require_user_type('admin');

$success = $error = '';

// Handle faculty addition
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_faculty'])) {
    $name = sanitize_input($_POST['name']);
    $email = sanitize_input($_POST['email']);
    $contact = sanitize_input($_POST['contact']);
    $college_id = (int)$_POST['college_id'];
    
    // Generate a random password
    $password = generate_password();
    $hashed_password = hash_password($password);
    
    // Check if email already exists
    $stmt = $conn->prepare("SELECT * FROM Faculty WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $error = "Email already exists";
    } else {
        $stmt = $conn->prepare("INSERT INTO Faculty (name, email, password, contact, college_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $name, $email, $hashed_password, $contact, $college_id);
        
        if ($stmt->execute()) {
            $success = "Faculty added successfully. Temporary password: " . $password;
            log_activity($_SESSION['user_id'], 'admin', 'Added new faculty: ' . $email);
        } else {
            $error = "Failed to add faculty: " . $stmt->error;
        }
    }
}

// Handle faculty deletion
if (isset($_GET['delete'])) {
    $faculty_id = (int)$_GET['delete'];
    
    // Get faculty email for logging
    $stmt = $conn->prepare("SELECT email FROM Faculty WHERE faculty_id = ?");
    $stmt->bind_param("i", $faculty_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $faculty = $result->fetch_assoc();
    
    $stmt = $conn->prepare("DELETE FROM Faculty WHERE faculty_id = ?");
    $stmt->bind_param("i", $faculty_id);
    
    if ($stmt->execute()) {
        $success = "Faculty deleted successfully";
        log_activity($_SESSION['user_id'], 'admin', 'Deleted faculty: ' . $faculty['email']);
        header("Location: manage_faculty.php?success=" . urlencode($success));
        exit();
    } else {
        $error = "Failed to delete faculty: " . $stmt->error;
    }
}

// Get all faculty with college names using a nested query
$faculty = [];
$query = "
    SELECT f.*, 
           (SELECT c.name FROM College c WHERE c.college_id = f.college_id) as college_name 
    FROM Faculty f 
    ORDER BY f.name
";
$result = $conn->query($query);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $faculty[] = $row;
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
    <h1>Manage Faculty</h1>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addFacultyModal">
        <i class="fas fa-plus"></i> Add Faculty
    </button>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Contact</th>
                        <th>College</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($faculty as $f): ?>
                        <tr>
                            <td><?php echo $f['faculty_id']; ?></td>
                            <td><?php echo htmlspecialchars($f['name']); ?></td>
                            <td><?php echo htmlspecialchars($f['email']); ?></td>
                            <td><?php echo htmlspecialchars($f['contact']); ?></td>
                            <td><?php echo htmlspecialchars($f['college_name'] ?? 'Not Assigned'); ?></td>
                            <td>
                                <a href="#" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#editFacultyModal<?php echo $f['faculty_id']; ?>">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="manage_faculty.php?delete=<?php echo $f['faculty_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this faculty?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($faculty)): ?>
                        <tr>
                            <td colspan="6" class="text-center">No faculty found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Faculty Modal -->
<div class="modal fade" id="addFacultyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Faculty</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
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
                    <button type="submit" name="add_faculty" class="btn btn-primary">Add Faculty</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
