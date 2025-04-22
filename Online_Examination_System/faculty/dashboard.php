<?php
require_once '../includes/auth.php';
require_user_type('faculty');

// Set timezone to IST
date_default_timezone_set('Asia/Kolkata');

// Get faculty details
$faculty_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT f.*, c.name as college_name FROM Faculty f LEFT JOIN College c ON f.college_id = c.college_id WHERE f.faculty_id = ?");
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$faculty = $stmt->get_result()->fetch_assoc();

// Get exam statistics
$total_exams = $conn->prepare("SELECT COUNT(*) as count FROM Exam WHERE created_by = ?");
$total_exams->bind_param("i", $faculty_id);
$total_exams->execute();
$exam_count = $total_exams->get_result()->fetch_assoc()['count'];

$active_exams = $conn->prepare("SELECT COUNT(*) as count FROM Exam WHERE created_by = ? AND start_time <= NOW() AND end_time >= NOW()");
$active_exams->bind_param("i", $faculty_id);
$active_exams->execute();
$active_count = $active_exams->get_result()->fetch_assoc()['count'];

$completed_exams = $conn->prepare("SELECT COUNT(*) as count FROM Exam WHERE created_by = ? AND end_time < NOW()");
$completed_exams->bind_param("i", $faculty_id);
$completed_exams->execute();
$completed_count = $completed_exams->get_result()->fetch_assoc()['count'];

// Get recent exams
$recent_exams = [];
$stmt = $conn->prepare("SELECT * FROM Exam WHERE created_by = ? ORDER BY start_time DESC LIMIT 5");
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $recent_exams[] = $row;
}

$base_path = '../';
include '../includes/header.php';
?>

<h1 class="mb-4">Faculty Dashboard</h1>

<div class="row">
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Profile Information</h5>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($faculty['name']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($faculty['email']); ?></p>
                <p><strong>Contact:</strong> <?php echo htmlspecialchars($faculty['contact']); ?></p>
                <p><strong>College:</strong> <?php echo htmlspecialchars($faculty['college_name'] ?? 'Not Assigned'); ?></p>
                <a href="profile.php" class="btn btn-sm btn-primary">Edit Profile</a>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="row">
            <div class="col-md-4">
                <div class="card bg-primary text-white mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Total Exams</h5>
                        <h2 class="display-4"><?php echo $exam_count; ?></h2>
                    </div>
                    <div class="card-footer d-flex align-items-center justify-content-between">
                        <a class="small text-white stretched-link" href="manage_exams.php">View Details</a>
                        <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-success text-white mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Active Exams</h5>
                        <h2 class="display-4"><?php echo $active_count; ?></h2>
                    </div>
                    <div class="card-footer d-flex align-items-center justify-content-between">
                        <a class="small text-white stretched-link" href="manage_exams.php?status=active">View Details</a>
                        <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-info text-white mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Completed Exams</h5>
                        <h2 class="display-4"><?php echo $completed_count; ?></h2>
                    </div>
                    <div class="card-footer d-flex align-items-center justify-content-between">
                        <a class="small text-white stretched-link" href="manage_exams.php?status=completed">View Details</a>
                        <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-file-alt"></i> Recent Exams
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Subject</th>
                                <th>Start Time</th>
                                <th>End Time</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_exams as $exam): ?>
                                <?php
                                $now = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
                                $start = new DateTime($exam['start_time'], new DateTimeZone('Asia/Kolkata'));
                                $end = new DateTime($exam['end_time'], new DateTimeZone('Asia/Kolkata'));
                                if ($now < $start) {
                                    $status = '<span class="badge bg-warning">Upcoming</span>';
                                } elseif ($now > $end) {
                                    $status = '<span class="badge bg-secondary">Completed</span>';
                                } else {
                                    $status = '<span class="badge bg-success">Active</span>';
                                }
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($exam['title']); ?></td>
                                    <td><?php echo htmlspecialchars($exam['subject']); ?></td>
                                    <td><?php echo date('M d, Y h:i A', strtotime($exam['start_time'])); ?></td>
                                    <td><?php echo date('M d, Y h:i A', strtotime($exam['end_time'])); ?></td>
                                    <td><?php echo $status; ?></td>
                                    <td>
                                        <a href="add_questions.php?exam_id=<?php echo $exam['exam_id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-question-circle"></i>
                                        </a>
                                        <a href="view_results.php?exam_id=<?php echo $exam['exam_id']; ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-chart-bar"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recent_exams)): ?>
                                <tr>
                                    <td colspan="6" class="text-center">No exams found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-tasks"></i> Quick Actions
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <a href="create_exam.php" class="btn btn-primary btn-lg w-100">
                            <i class="fas fa-plus-circle"></i> Create New Exam
                        </a>
                    </div>
                    <div class="col-md-4 mb-3">
                        <a href="manage_exams.php" class="btn btn-success btn-lg w-100">
                            <i class="fas fa-list"></i> Manage Exams
                        </a>
                    </div>
                    <div class="col-md-4 mb-3">
                        <a href="view_results.php" class="btn btn-info btn-lg w-100">
                            <i class="fas fa-chart-line"></i> View Results
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
