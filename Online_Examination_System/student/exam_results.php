<?php
session_start();
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Require student login
require_user_type('student');

$student_id = $_SESSION['user_id'];
$error = "";
$success = "";

// Get student details
$stmt = $conn->prepare("SELECT s.*, c.name as college_name FROM Student s LEFT JOIN College c ON s.college_id = c.college_id WHERE s.student_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

// Get all exam attempts for this student
$query = "
    SELECT ea.*, e.title, e.subject, e.total_marks, e.duration, f.name as faculty_name,
           (ea.total_score / e.total_marks * 100) as percentage
    FROM Exam_Attempt ea
    JOIN Exam e ON ea.exam_id = e.exam_id
    JOIN Faculty f ON e.created_by = f.faculty_id
    WHERE ea.student_id = ?
    ORDER BY ea.start_time DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

$attempts = [];
while ($row = $result->fetch_assoc()) {
    $attempts[] = $row;
}

// Get statistics
$total_exams = count($attempts);
$completed_exams = 0;
$passed_exams = 0;
$total_score_percentage = 0;

foreach ($attempts as $attempt) {
    if ($attempt['status'] == 'completed') {
        $completed_exams++;
        if ($attempt['percentage'] >= 40) { // Assuming 40% is pass mark
            $passed_exams++;
        }
        $total_score_percentage += $attempt['percentage'];
    }
}

$average_score = $completed_exams > 0 ? $total_score_percentage / $completed_exams : 0;

$base_path = '../';
include '../includes/header.php';
?>

<div class="container mt-4">
    <h1 class="mb-4">My Exam Results</h1>
    
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
                    <h5 class="card-title">Completed</h5>
                    <h2 class="display-4"><?php echo $completed_exams; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Passed</h5>
                    <h2 class="display-4"><?php echo $passed_exams; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Average Score</h5>
                    <h2 class="display-4"><?php echo number_format($average_score, 1); ?>%</h2>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Exam History</h5>
        </div>
        <div class="card-body">
            <?php if (empty($attempts)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> You haven't taken any exams yet.
                    <a href="available_exams.php" class="alert-link">View available exams</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Exam Title</th>
                                <th>Subject</th>
                                <th>Faculty</th>
                                <th>Date</th>
                                <th>Duration</th>
                                <th>Score</th>
                                <th>Percentage</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attempts as $attempt): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($attempt['title']); ?></td>
                                    <td><?php echo htmlspecialchars($attempt['subject']); ?></td>
                                    <td><?php echo htmlspecialchars($attempt['faculty_name']); ?></td>
                                    <td><?php echo date('M d, Y h:i A', strtotime($attempt['start_time'])); ?></td>
                                    <td><?php echo $attempt['duration']; ?> mins</td>
                                    <td><?php echo $attempt['total_score']; ?> / <?php echo $attempt['total_marks']; ?></td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar <?php echo $attempt['percentage'] >= 40 ? 'bg-success' : 'bg-danger'; ?>" 
                                                 role="progressbar" 
                                                 style="width: <?php echo $attempt['percentage']; ?>%;" 
                                                 aria-valuenow="<?php echo $attempt['percentage']; ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="100">
                                                <?php echo number_format($attempt['percentage'], 1); ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($attempt['status'] == 'completed'): ?>
                                            <span class="badge bg-success">Completed</span>
                                        <?php elseif ($attempt['status'] == 'in_progress'): ?>
                                            <span class="badge bg-warning">In Progress</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Timed Out</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="view_result.php?attempt_id=<?php echo $attempt['attempt_id']; ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i> View
                                        </a>
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
            <h5 class="mb-0">Performance Analysis</h5>
        </div>
        <div class="card-body">
            <?php if (count($attempts) > 0): ?>
                <div class="row">
                    <div class="col-md-6">
                        <h5>Subject Performance</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-secondary">
                                    <tr>
                                        <th>Subject</th>
                                        <th>Exams Taken</th>
                                        <th>Average Score</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $subjects = [];
                                    foreach ($attempts as $attempt) {
                                        if ($attempt['status'] == 'completed') {
                                            if (!isset($subjects[$attempt['subject']])) {
                                                $subjects[$attempt['subject']] = [
                                                    'count' => 0,
                                                    'total_percentage' => 0
                                                ];
                                            }
                                            $subjects[$attempt['subject']]['count']++;
                                            $subjects[$attempt['subject']]['total_percentage'] += $attempt['percentage'];
                                        }
                                    }
                                    
                                    foreach ($subjects as $subject => $data): 
                                        $avg = $data['count'] > 0 ? $data['total_percentage'] / $data['count'] : 0;
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($subject); ?></td>
                                            <td><?php echo $data['count']; ?></td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar <?php echo $avg >= 40 ? 'bg-success' : 'bg-danger'; ?>" 
                                                         role="progressbar" 
                                                         style="width: <?php echo $avg; ?>%;" 
                                                         aria-valuenow="<?php echo $avg; ?>" 
                                                         aria-valuemin="0" 
                                                         aria-valuemax="100">
                                                        <?php echo number_format($avg, 1); ?>%
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h5>Recent Performance Trend</h5>
                        <div class="chart-container" style="position: relative; height:300px;">
                            <canvas id="performanceChart"></canvas>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> No data available for analysis. Take some exams to see your performance statistics.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (count($attempts) > 0): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Get the last 5 completed exams (or fewer if not available)
    const examData = <?php 
        $chartData = [];
        $count = 0;
        foreach ($attempts as $attempt) {
            if ($attempt['status'] == 'completed' && $count < 5) {
                $chartData[] = [
                    'title' => $attempt['title'],
                    'percentage' => $attempt['percentage']
                ];
                $count++;
            }
        }
        echo json_encode(array_reverse($chartData));
    ?>;
    
    const labels = examData.map(item => item.title);
    const data = examData.map(item => item.percentage);
    
    const ctx = document.getElementById('performanceChart').getContext('2d');
    const myChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Score Percentage',
                data: data,
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 2,
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    title: {
                        display: true,
                        text: 'Percentage (%)'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Exams'
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + context.parsed.y.toFixed(1) + '%';
                        }
                    }
                }
            }
        }
    });
});
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
