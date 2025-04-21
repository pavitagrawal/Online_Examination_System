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

// Handle venue addition
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_venue'])) {
    $name = sanitize_input($_POST['name']);
    $capacity = (int)$_POST['capacity'];
    
    // Check if venue name already exists
    $stmt = $conn->prepare("SELECT * FROM Venue WHERE name = ?");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $error = "Venue with this name already exists";
    } else {
        $stmt = $conn->prepare("INSERT INTO Venue (name, capacity) VALUES (?, ?)");
        $stmt->bind_param("si", $name, $capacity);
        
        if ($stmt->execute()) {
            $success = "Venue added successfully";
            log_activity($admin_id, 'admin', 'Added new venue: ' . $name);
        } else {
            $error = "Failed to add venue: " . $stmt->error;
        }
    }
}

// Handle venue deletion
if (isset($_GET['delete'])) {
    $venue_id = (int)$_GET['delete'];
    
    // Get venue name for logging
    $stmt = $conn->prepare("SELECT name FROM Venue WHERE venue_id = ?");
    $stmt->bind_param("i", $venue_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $venue = $result->fetch_assoc();
    
    // Check if there are any exams associated with this venue
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Exam WHERE venue_id = ?");
    $stmt->bind_param("i", $venue_id);
    $stmt->execute();
    $exam_count = 0;
    
    // Only proceed if the statement executed successfully
    if ($stmt) {
        $result = $stmt->get_result();
        if ($result) {
            $row = $result->fetch_assoc();
            if ($row) {
                $exam_count = $row['count'];
            }
        }
    }
    
    if ($exam_count > 0) {
        $error = "Cannot delete venue because it has associated exams";
    } else {
        $stmt = $conn->prepare("DELETE FROM Venue WHERE venue_id = ?");
        $stmt->bind_param("i", $venue_id);
        
        if ($stmt->execute()) {
            $success = "Venue deleted successfully";
            log_activity($admin_id, 'admin', 'Deleted venue: ' . $venue['name']);
            header("Location: manage_venues.php?success=" . urlencode($success));
            exit();
        } else {
            $error = "Failed to delete venue: " . $stmt->error;
        }
    }
}

// Handle venue edit
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_venue'])) {
    $venue_id = (int)$_POST['venue_id'];
    $name = sanitize_input($_POST['name']);
    $capacity = (int)$_POST['capacity'];
    
    // Check if venue name already exists for other venues
    $stmt = $conn->prepare("SELECT * FROM Venue WHERE name = ? AND venue_id != ?");
    $stmt->bind_param("si", $name, $venue_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $error = "Another venue with this name already exists";
    } else {
        $stmt = $conn->prepare("UPDATE Venue SET name = ?, capacity = ? WHERE venue_id = ?");
        $stmt->bind_param("sii", $name, $capacity, $venue_id);
        
        if ($stmt->execute()) {
            $success = "Venue updated successfully";
            log_activity($admin_id, 'admin', 'Updated venue ID: ' . $venue_id);
        } else {
            $error = "Failed to update venue: " . $stmt->error;
        }
    }
}

// Get all venues
$venues = [];
$query = "SELECT v.*, 
          (SELECT COUNT(*) FROM Exam WHERE venue_id = v.venue_id) as exam_count
          FROM Venue v ORDER BY v.name";
$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $venues[] = $row;
    }
}

$base_path = '../';
include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Manage Venues</h1>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addVenueModal">
        <i class="fas fa-plus"></i> Add Venue
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
            <table class="table table-striped table-hover" id="venuesTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Capacity</th>
                        <th>Exams</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($venues as $venue): ?>
                        <tr>
                            <td><?php echo $venue['venue_id']; ?></td>
                            <td><?php echo htmlspecialchars($venue['name']); ?></td>
                            <td><?php echo $venue['capacity']; ?> seats</td>
                            <td><?php echo $venue['exam_count']; ?></td>
                            <td>
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#editVenueModal<?php echo $venue['venue_id']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="manage_venues.php?delete=<?php echo $venue['venue_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this venue?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                    <a href="view_venue.php?id=<?php echo $venue['venue_id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </div>
                                
                                <!-- Edit Venue Modal -->
                                <div class="modal fade" id="editVenueModal<?php echo $venue['venue_id']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit Venue</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <form method="post" action="">
                                                <div class="modal-body">
                                                    <input type="hidden" name="venue_id" value="<?php echo $venue['venue_id']; ?>">
                                                    
                                                    <div class="mb-3">
                                                        <label for="name<?php echo $venue['venue_id']; ?>" class="form-label">Venue Name</label>
                                                        <input type="text" class="form-control" id="name<?php echo $venue['venue_id']; ?>" name="name" value="<?php echo htmlspecialchars($venue['name']); ?>" required>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="capacity<?php echo $venue['venue_id']; ?>" class="form-label">Capacity (seats)</label>
                                                        <input type="number" class="form-control" id="capacity<?php echo $venue['venue_id']; ?>" name="capacity" value="<?php echo $venue['capacity']; ?>" min="1" required>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                    <button type="submit" name="edit_venue" class="btn btn-primary">Save Changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($venues)): ?>
                        <tr>
                            <td colspan="5" class="text-center">No venues found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Venue Modal -->
<div class="modal fade" id="addVenueModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Venue</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Venue Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="capacity" class="form-label">Capacity (seats)</label>
                        <input type="number" class="form-control" id="capacity" name="capacity" min="1" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="add_venue" class="btn btn-primary">Add Venue</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Initialize DataTable for better table functionality
document.addEventListener('DOMContentLoaded', function() {
    if (typeof $.fn.DataTable !== 'undefined') {
        $('#venuesTable').DataTable({
            "pageLength": 25,
            "order": [[1, "asc"]], // Sort by name by default
            "columnDefs": [
                { "orderable": false, "targets": 4 } // Disable sorting for actions column
            ]
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>
