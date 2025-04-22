<?php
session_start();
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

date_default_timezone_set('Asia/Kolkata');

// Require admin login
require_user_type('admin');

$admin_id = $_SESSION['user_id'];
$error = "";
$success = "";

// Handle status filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$faculty_filter = isset($_GET['faculty_id']) ? (int)$_GET['faculty_id'] : 0;

// Prepare the query based on filters
$query = "
    SELECT e.*, 
           f.name as faculty_name,
           (SELECT COUNT(*) FROM Question WHERE exam_id = e.exam_id) as question_count,
           (SELECT COUNT(*) FROM Exam_Attempt WHERE exam_id = e.exam_id) as attempt_count
    FROM Exam e
    JOIN Faculty f ON e.created_by = f.faculty_id
    WHERE 1=1
";

// Add status filter if specified
if ($status_filter == 'active') {
    $query .= " AND NOW() BETWEEN e.start_time AND e.end_time";
} elseif ($status_filter == 'upcoming') {
    $query .= " AND e.start_time > NOW()";
} elseif ($status_filter == 'completed') {
    $query .= " AND e.end_time < NOW()";
}

// Add faculty filter if specified
if ($faculty_filter > 0) {
    $query .= " AND e.created_by = $faculty_filter";
}

$query .= " ORDER BY e.start_time DESC";

$result = $conn->query($query);
$exams = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $exams[] = $row;
    }
}

// Get faculty list for filter
$faculty_list = [];
$faculty_result = $conn->query("SELECT faculty_id, name FROM Faculty ORDER BY name");
if ($faculty_result && $faculty_result->num_rows > 0) {
    while ($row = $faculty_result->fetch_assoc()) {
        $faculty_list[] = $row;
    }
}

// Get exam statistics
$total_exams = count($exams);
$active_exams = 0;
$upcoming_exams = 0;
$completed_exams = 0;
$now = time();

foreach ($exams as $exam) {
    $start_time = strtotime($exam['start_time']);
    $end_time = strtotime($exam['end_time']);
    
    if ($now < $start_time) {
        $upcoming_exams++;
    } elseif ($now > $end_time) {
        $completed_exams++;
    } else {
        $active_exams++;
    }
}

$base_path = '../';
include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Exam Management</h1>
    <a href="dashboard.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
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

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <h5 class="card-title">Total Exams</h5>
                <h2 class="display-4"><?php echo $total_exams; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body text-center">
                <h5 class="card-title">Active</h5>
                <h2 class="display-4"><?php echo $active_exams; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-white">
            <div class="card-body text-center">
                <h5 class="card-title">Upcoming</h5>
                <h2 class="display-4"><?php echo $upcoming_exams; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-secondary text-white">
            <div class="card-body text-center">
                <h5 class="card-title">Completed</h5>
                <h2 class="display-4"><?php echo $completed_exams; ?></h2>
            </div>
        </div>
    </div>
</div>

<!-- Filter Form -->
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Filter Exams</h5>
    </div>
    <div class="card-body">
        <form method="get" action="" class="row g-3">
            <div class="col-md-6">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Exams</option>
                    <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="upcoming" <?php echo $status_filter == 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                    <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                </select>
            </div>
            <div class="col-md-6">
                <label for="faculty_id" class="form-label">Faculty</label>
                <select class="form-select" id="faculty_id" name="faculty_id">
                    <option value="0">All Faculty</option>
                    <?php foreach ($faculty_list as $faculty): ?>
                        <option value="<?php echo $faculty['faculty_id']; ?>" <?php echo $faculty_filter == $faculty['faculty_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($faculty['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <a href="manage_exams.php" class="btn btn-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Exams Table -->
<div class="card">
    <div class="card-header bg-dark text-white">
        <h5 class="mb-0">All Exams</h5>
    </div>
    <div class="card-body">
        <?php if (empty($exams)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i> No exams found matching the selected criteria.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="examsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Subject</th>
                            <th>Faculty</th>
                            <th>Questions</th>
                            <th>Duration</th>
                            <th>Start Time</th>
                            <th>End Time</th>
                            <th>Attempts</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($exams as $exam): ?>
                            <?php
                            $start_time = strtotime($exam['start_time']);
                            $end_time = strtotime($exam['end_time']);
                            $status = '';
                            $status_class = '';
                            
                            if ($now < $start_time) {
                                $status = 'Upcoming';
                                $status_class = 'bg-warning';
                            } elseif ($now > $end_time) {
                                $status = 'Completed';
                                $status_class = 'bg-secondary';
                            } else {
                                $status = 'Active';
                                $status_class = 'bg-success';
                            }
                            ?>
                            <tr>
                                <td><?php echo $exam['exam_id']; ?></td>
                                <td><?php echo htmlspecialchars($exam['title']); ?></td>
                                <td><?php echo htmlspecialchars($exam['subject']); ?></td>
                                <td><?php echo htmlspecialchars($exam['faculty_name']); ?></td>
                                <td><?php echo $exam['question_count']; ?></td>
                                <td><?php echo $exam['duration']; ?> mins</td>
                                <td><?php echo date('M d, Y h:i A', $start_time); ?></td>
                                <td><?php echo date('M d, Y h:i A', $end_time); ?></td>
                                <td>
                                    <?php if ($exam['attempt_count'] > 0): ?>
                                        <a href="view_exam_results.php?exam_id=<?php echo $exam['exam_id']; ?>" class="badge bg-info text-decoration-none">
                                            <?php echo $exam['attempt_count']; ?> attempts
                                        </a>
                                    <?php else: ?>
                                        <span class="badge bg-light text-dark">No attempts</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge <?php echo $status_class; ?>"><?php echo $status; ?></span></td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="view_exam_details.php?id=<?php echo $exam['exam_id']; ?>" class="btn btn-sm btn-info" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="view_exam_results.php?exam_id=<?php echo $exam['exam_id']; ?>" class="btn btn-sm btn-success" title="View Results">
                                            <i class="fas fa-chart-bar"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Initialize DataTable for better table functionality
document.addEventListener('DOMContentLoaded', function() {
    if (typeof $.fn.DataTable !== 'undefined') {
        $('#examsTable').DataTable({
            "pageLength": 25,
            "order": [[6, "desc"]], // Sort by start time by default
            "columnDefs": [
                { "orderable": false, "targets": 10 } // Disable sorting for actions column
            ]
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>
