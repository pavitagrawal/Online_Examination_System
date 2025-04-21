<?php
session_start();
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Require faculty login
require_user_type('faculty');

$faculty_id = $_SESSION['user_id'];
$error = "";
$success = "";


date_default_timezone_set('Asia/Kolkata');

// Handle exam deletion
if (isset($_GET['delete'])) {
    $exam_id = (int)$_GET['delete'];
    
    // Verify that this exam belongs to the faculty
    $stmt = $conn->prepare("SELECT * FROM Exam WHERE exam_id = ? AND created_by = ?");
    $stmt->bind_param("ii", $exam_id, $faculty_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Check if any students have attempted this exam
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Exam_Attempt WHERE exam_id = ?");
        $stmt->bind_param("i", $exam_id);
        $stmt->execute();
        $attempts = $stmt->get_result()->fetch_assoc()['count'];
        
        if ($attempts > 0) {
            $error = "Cannot delete exam because it has been attempted by students.";
        } else {
            // Delete exam questions first
            $stmt = $conn->prepare("DELETE FROM Question WHERE exam_id = ?");
            $stmt->bind_param("i", $exam_id);
            $stmt->execute();
            
            // Delete exam availability
            $stmt = $conn->prepare("DELETE FROM Exam_Availability WHERE exam_id = ?");
            $stmt->bind_param("i", $exam_id);
            $stmt->execute();
            
            // Delete the exam
            $stmt = $conn->prepare("DELETE FROM Exam WHERE exam_id = ?");
            $stmt->bind_param("i", $exam_id);
            
            if ($stmt->execute()) {
                $success = "Exam deleted successfully.";
                log_activity($faculty_id, 'faculty', 'Deleted exam ID: ' . $exam_id);
            } else {
                $error = "Failed to delete exam: " . $stmt->error;
            }
        }
    } else {
        $error = "Unauthorized access to exam or exam not found.";
    }
}

// Handle exam status filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Get all exams created by this faculty
$query = "
    SELECT e.*, 
           (SELECT COUNT(*) FROM Question WHERE exam_id = e.exam_id) as question_count,
           (SELECT COUNT(*) FROM Exam_Attempt WHERE exam_id = e.exam_id) as attempt_count
    FROM Exam e
    WHERE e.created_by = ?
";

// Add status filter if specified
if ($status_filter == 'active') {
    $query .= " AND NOW() BETWEEN e.start_time AND e.end_time";
} elseif ($status_filter == 'upcoming') {
    $query .= " AND e.start_time > NOW()";
} elseif ($status_filter == 'completed') {
    $query .= " AND e.end_time < NOW()";
}

$query .= " ORDER BY e.start_time DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$result = $stmt->get_result();

$exams = [];
while ($row = $result->fetch_assoc()) {
    $exams[] = $row;
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

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Manage Exams</h1>
        <a href="create_exam.php" class="btn btn-primary">
            <i class="fas fa-plus-circle"></i> Create New Exam
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
    
    <div class="card">
        <div class="card-header bg-primary text-white">
            <ul class="nav nav-tabs card-header-tabs">
                <li class="nav-item">
                    <a class="nav-link <?php echo $status_filter == 'all' ? 'active bg-white text-primary' : 'text-white'; ?>" href="manage_exams.php">
                        All Exams
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $status_filter == 'active' ? 'active bg-white text-primary' : 'text-white'; ?>" href="manage_exams.php?status=active">
                        Active
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $status_filter == 'upcoming' ? 'active bg-white text-primary' : 'text-white'; ?>" href="manage_exams.php?status=upcoming">
                        Upcoming
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $status_filter == 'completed' ? 'active bg-white text-primary' : 'text-white'; ?>" href="manage_exams.php?status=completed">
                        Completed
                    </a>
                </li>
            </ul>
        </div>
        <div class="card-body">
            <?php if (empty($exams)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> No exams found. <a href="create_exam.php" class="alert-link">Create your first exam</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Title</th>
                                <th>Subject</th>
                                <th>Questions</th>
                                <th>Total Marks</th>
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
                                    <td><?php echo htmlspecialchars($exam['title']); ?></td>
                                    <td><?php echo htmlspecialchars($exam['subject']); ?></td>
                                    <td><?php echo $exam['question_count']; ?></td>
                                    <td><?php echo $exam['total_marks']; ?></td>
                                    <td><?php echo $exam['duration']; ?> mins</td>
                                    <td><?php echo date('M d, Y h:i A', $start_time); ?></td>
                                    <td><?php echo date('M d, Y h:i A', $end_time); ?></td>
                                    <td>
                                        <?php if ($exam['attempt_count'] > 0): ?>
                                            <a href="view_results.php?exam_id=<?php echo $exam['exam_id']; ?>" class="badge bg-info text-decoration-none">
                                                <?php echo $exam['attempt_count']; ?> attempts
                                            </a>
                                        <?php else: ?>
                                            <span class="badge bg-light text-dark">No attempts</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge <?php echo $status_class; ?>"><?php echo $status; ?></span></td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="add_questions.php?exam_id=<?php echo $exam['exam_id']; ?>" class="btn btn-sm btn-primary" title="Manage Questions">
                                                <i class="fas fa-question-circle"></i>
                                            </a>
                                            <?php if ($status == 'Upcoming'): ?>
                                                <a href="edit_exam.php?id=<?php echo $exam['exam_id']; ?>" class="btn btn-sm btn-info" title="Edit Exam">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="manage_exams.php?delete=<?php echo $exam['exam_id']; ?>" class="btn btn-sm btn-danger" title="Delete Exam" onclick="return confirm('Are you sure you want to delete this exam?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="view_results.php?exam_id=<?php echo $exam['exam_id']; ?>" class="btn btn-sm btn-success" title="View Results">
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
    
    <div class="card mt-4">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0">Exam Management Tips</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6><i class="fas fa-lightbulb text-warning me-2"></i>Creating Effective Exams</h6>
                    <ul>
                        <li>Include a mix of question types (multiple choice, true/false, descriptive)</li>
                        <li>Set appropriate time limits based on the number and complexity of questions</li>
                        <li>Provide clear instructions for each section</li>
                        <li>Distribute marks according to question difficulty</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h6><i class="fas fa-shield-alt text-success me-2"></i>Exam Security</h6>
                    <ul>
                        <li>Set availability windows carefully to control access</li>
                        <li>Use randomized questions when possible</li>
                        <li>Monitor unusual patterns in student responses</li>
                        <li>Keep exam details confidential until release</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Enable Bootstrap tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });
});
</script>

<?php include '../includes/footer.php'; ?>
