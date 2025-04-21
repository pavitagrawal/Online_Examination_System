<?php
session_start();
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Require admin login
require_user_type('admin');

// Set timezone to IST for consistency
date_default_timezone_set('Asia/Kolkata');

$admin_id = $_SESSION['user_id'];

// Get counts for dashboard
$faculty_count = $conn->query("SELECT COUNT(*) as count FROM Faculty")->fetch_assoc()['count'];
$student_count = $conn->query("SELECT COUNT(*) as count FROM Student")->fetch_assoc()['count'];
$college_count = $conn->query("SELECT COUNT(*) as count FROM College")->fetch_assoc()['count'];
$exam_count = $conn->query("SELECT COUNT(*) as count FROM Exam")->fetch_assoc()['count'];

// Accurate Active/Upcoming/Completed exam counts
$now = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i:s');

$active_exams = $conn->prepare("SELECT COUNT(*) as count FROM Exam WHERE start_time <= ? AND end_time >= ?");
$active_exams->bind_param("ss", $now, $now);
$active_exams->execute();
$active_count = $active_exams->get_result()->fetch_assoc()['count'];

$completed_exams = $conn->prepare("SELECT COUNT(*) as count FROM Exam WHERE end_time < ?");
$completed_exams->bind_param("s", $now);
$completed_exams->execute();
$completed_count = $completed_exams->get_result()->fetch_assoc()['count'];

$upcoming_exams = $conn->prepare("SELECT COUNT(*) as count FROM Exam WHERE start_time > ?");
$upcoming_exams->bind_param("s", $now);
$upcoming_exams->execute();
$upcoming_count = $upcoming_exams->get_result()->fetch_assoc()['count'];

// Get recent logs
$logs = [];
$result = $conn->query("SELECT * FROM System_Logs ORDER BY created_at DESC LIMIT 10");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
}

$base_path = '../';
include '../includes/header.php';
?>

<h1 class="mb-4">Admin Dashboard</h1>

<div class="row">
    <div class="col-md-3">
        <div class="card bg-primary text-white mb-4">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-chalkboard-teacher"></i> Faculty</h5>
                <h2 class="display-4"><?php echo $faculty_count; ?></h2>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a class="small text-white stretched-link" href="manage_faculty.php">View Details</a>
                <div class="small text-white"><i class="fas fa-angle-right"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white mb-4">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-user-graduate"></i> Students</h5>
                <h2 class="display-4"><?php echo $student_count; ?></h2>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a class="small text-white stretched-link" href="manage_students.php">View Details</a>
                <div class="small text-white"><i class="fas fa-angle-right"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-white mb-4">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-university"></i> Colleges</h5>
                <h2 class="display-4"><?php echo $college_count; ?></h2>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a class="small text-white stretched-link" href="manage_colleges.php">View Details</a>
                <div class="small text-white"><i class="fas fa-angle-right"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-danger text-white mb-4">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-file-alt"></i> Exams</h5>
                <h2 class="display-4"><?php echo $exam_count; ?></h2>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a class="small text-white stretched-link" href="manage_exams.php">View Details</a>
                <div class="small text-white"><i class="fas fa-angle-right"></i></div>
            </div>
        </div>
    </div>
</div>

<!-- Add exam status summary -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card bg-success text-white mb-4">
            <div class="card-body text-center">
                <h5 class="card-title">Active Exams</h5>
                <h2 class="display-4"><?php echo $active_count; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-warning text-white mb-4">
            <div class="card-body text-center">
                <h5 class="card-title">Upcoming Exams</h5>
                <h2 class="display-4"><?php echo $upcoming_count; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-secondary text-white mb-4">
            <div class="card-body text-center">
                <h5 class="card-title">Completed Exams</h5>
                <h2 class="display-4"><?php echo $completed_count; ?></h2>
            </div>
        </div>
    </div>
</div>

<!-- Quick Action Buttons -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-tasks"></i> Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <a href="manage_faculty.php" class="btn btn-primary btn-lg w-100">
                            <i class="fas fa-chalkboard-teacher me-2"></i> Manage Faculty
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="manage_students.php" class="btn btn-success btn-lg w-100">
                            <i class="fas fa-user-graduate me-2"></i> Manage Students
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="manage_colleges.php" class="btn btn-warning btn-lg w-100">
                            <i class="fas fa-university me-2"></i> Manage Colleges
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="manage_exams.php" class="btn btn-danger btn-lg w-100">
                            <i class="fas fa-file-alt me-2"></i> Manage Exams
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- System Overview -->
<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-chart-pie"></i> System Statistics</h5>
            </div>
            <div class="card-body">
                <canvas id="statsChart" height="250"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="fas fa-cog"></i> System Information</h5>
            </div>
            <div class="card-body">
                <p><strong>Current Date/Time:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
                <p><strong>Server IP:</strong> <?php echo $_SERVER['SERVER_ADDR']; ?></p>
                <p><strong>PHP Version:</strong> <?php echo phpversion(); ?></p>
                <p><strong>Database:</strong> MySQL</p>
                <p><strong>Last System Update:</strong> <?php echo date('Y-m-d'); ?></p>
                <a href="system_logs.php" class="btn btn-outline-secondary">View All System Logs</a>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for Chart -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Create a pie chart for system statistics
    const ctx = document.getElementById('statsChart').getContext('2d');
    const statsChart = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: ['Faculty', 'Students', 'Colleges', 'Exams'],
            datasets: [{
                data: [<?php echo $faculty_count; ?>, <?php echo $student_count; ?>, <?php echo $college_count; ?>, <?php echo $exam_count; ?>],
                backgroundColor: [
                    'rgba(13, 110, 253, 0.8)',  // Primary (Faculty)
                    'rgba(25, 135, 84, 0.8)',   // Success (Students)
                    'rgba(255, 193, 7, 0.8)',   // Warning (Colleges)
                    'rgba(220, 53, 69, 0.8)'    // Danger (Exams)
                ],
                borderColor: [
                    'rgba(13, 110, 253, 1)',
                    'rgba(25, 135, 84, 1)',
                    'rgba(255, 193, 7, 1)',
                    'rgba(220, 53, 69, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((value / total) * 100);
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
