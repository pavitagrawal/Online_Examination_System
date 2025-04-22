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

// Check if exam_id is provided
if (!isset($_GET['exam_id']) || !is_numeric($_GET['exam_id'])) {
    header("Location: manage_exams.php?error=Invalid exam ID");
    exit();
}

$exam_id = (int)$_GET['exam_id'];

// Get exam details with faculty information
$stmt = $conn->prepare("
    SELECT e.*, f.name as faculty_name, f.email as faculty_email, c.name as college_name,
           (SELECT COUNT(*) FROM Question WHERE exam_id = e.exam_id) as question_count,
           (SELECT COUNT(*) FROM Exam_Attempt WHERE exam_id = e.exam_id) as attempt_count,
           (SELECT COUNT(*) FROM Exam_Attempt WHERE exam_id = e.exam_id AND status = 'completed') as completed_count
    FROM Exam e
    JOIN Faculty f ON e.created_by = f.faculty_id
    LEFT JOIN College c ON f.college_id = c.college_id
    WHERE e.exam_id = ?
");
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: manage_exams.php?error=Exam not found");
    exit();
}

$exam = $result->fetch_assoc();

// Get all attempts for this exam
$attempts_query = "
    SELECT ea.*, s.name as student_name, s.reg_no, s.email as student_email, c.name as college_name,
           (ea.total_score / e.total_marks * 100) as percentage
    FROM Exam_Attempt ea
    JOIN Student s ON ea.student_id = s.student_id
    JOIN Exam e ON ea.exam_id = e.exam_id
    LEFT JOIN College c ON s.college_id = c.college_id
    WHERE ea.exam_id = ?
    ORDER BY ea.end_time DESC
";

$stmt = $conn->prepare($attempts_query);
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$result = $stmt->get_result();

$attempts = [];
$total_score = 0;
$completed_count = 0;
$pass_count = 0;
$fail_count = 0;
$highest_score = 0;
$lowest_score = 100;

while ($row = $result->fetch_assoc()) {
    $attempts[] = $row;
    
    // Collect statistics for completed attempts
    if ($row['status'] == 'completed') {
        $completed_count++;
        $total_score += $row['percentage'];
        
        // Update high and low scores
        if ($row['percentage'] > $highest_score) {
            $highest_score = $row['percentage'];
        }
        if ($row['percentage'] < $lowest_score) {
            $lowest_score = $row['percentage'];
        }
        
        // Count passes and fails (assuming 40% is pass mark)
        if ($row['percentage'] >= 40) {
            $pass_count++;
        } else {
            $fail_count++;
        }
    }
}

// Calculate statistics
$avg_score = $completed_count > 0 ? $total_score / $completed_count : 0;
if ($completed_count == 0) {
    $lowest_score = 0;
}

// Get question-wise statistics
$question_stats_query = "
    SELECT q.question_id, q.question_text, q.question_type, q.marks,
           COUNT(a.answer_id) as total_answers,
           SUM(CASE WHEN a.is_correct = 1 THEN 1 ELSE 0 END) as correct_answers,
           AVG(a.marks_obtained) as avg_marks
    FROM Question q
    LEFT JOIN Answer a ON q.question_id = a.question_id
    LEFT JOIN Exam_Attempt ea ON a.attempt_id = ea.attempt_id
    WHERE q.exam_id = ? AND (ea.status = 'completed' OR ea.status IS NULL)
    GROUP BY q.question_id
    ORDER BY q.question_id
";

$stmt = $conn->prepare($question_stats_query);
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$result = $stmt->get_result();

$question_stats = [];
while ($row = $result->fetch_assoc()) {
    $question_stats[] = $row;
}

// Get college-wise performance
$college_stats_query = "
    SELECT c.name as college_name, COUNT(ea.attempt_id) as attempt_count,
           AVG(ea.total_score / e.total_marks * 100) as avg_percentage,
           SUM(CASE WHEN (ea.total_score / e.total_marks * 100) >= 40 THEN 1 ELSE 0 END) as pass_count
    FROM Exam_Attempt ea
    JOIN Student s ON ea.student_id = s.student_id
    JOIN College c ON s.college_id = c.college_id
    JOIN Exam e ON ea.exam_id = e.exam_id
    WHERE ea.exam_id = ? AND ea.status = 'completed'
    GROUP BY c.college_id
    ORDER BY avg_percentage DESC
";

$stmt = $conn->prepare($college_stats_query);
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$result = $stmt->get_result();

$college_stats = [];
while ($row = $result->fetch_assoc()) {
    $college_stats[] = $row;
}

$base_path = '../';
include '../includes/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Exam Results: <?php echo htmlspecialchars($exam['title']); ?></h1>
        <div>
            <a href="manage_exams.php" class="btn btn-secondary me-2">
                <i class="fas fa-arrow-left"></i> Back to Exams
            </a>
            <a href="view_exam_details.php?id=<?php echo $exam_id; ?>" class="btn btn-primary">
                <i class="fas fa-info-circle"></i> View Exam Details
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Exam Overview -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Exam Overview</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Title:</strong> <?php echo htmlspecialchars($exam['title']); ?></p>
                    <p><strong>Subject:</strong> <?php echo htmlspecialchars($exam['subject']); ?></p>
                    <p><strong>Faculty:</strong> <?php echo htmlspecialchars($exam['faculty_name']); ?> (<?php echo htmlspecialchars($exam['faculty_email']); ?>)</p>
                    <p><strong>College:</strong> <?php echo htmlspecialchars($exam['college_name'] ?? 'Not Assigned'); ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Total Marks:</strong> <?php echo $exam['total_marks']; ?></p>
                    <p><strong>Duration:</strong> <?php echo $exam['duration']; ?> minutes</p>
                    <p><strong>Start Time:</strong> <?php echo date('M d, Y h:i A', strtotime($exam['start_time'])); ?></p>
                    <p><strong>End Time:</strong> <?php echo date('M d, Y h:i A', strtotime($exam['end_time'])); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Results Summary -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white h-100">
                <div class="card-body text-center">
                    <h5 class="card-title">Total Attempts</h5>
                    <h2 class="display-4"><?php echo count($attempts); ?></h2>
                    <p>Completed: <?php echo $completed_count; ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white h-100">
                <div class="card-body text-center">
                    <h5 class="card-title">Average Score</h5>
                    <h2 class="display-4"><?php echo number_format($avg_score, 1); ?>%</h2>
                    <p>High: <?php echo number_format($highest_score, 1); ?>% | Low: <?php echo number_format($lowest_score, 1); ?>%</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white h-100">
                <div class="card-body text-center">
                    <h5 class="card-title">Pass Rate</h5>
                    <?php 
                    $pass_rate = $completed_count > 0 
                        ? ($pass_count / $completed_count) * 100 
                        : 0;
                    ?>
                    <h2 class="display-4"><?php echo number_format($pass_rate, 1); ?>%</h2>
                    <p>Passed: <?php echo $pass_count; ?> | Failed: <?php echo $fail_count; ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white h-100">
                <div class="card-body text-center">
                    <h5 class="card-title">Completion Rate</h5>
                    <?php 
                    $completion_rate = count($attempts) > 0 
                        ? ($completed_count / count($attempts)) * 100 
                        : 0;
                    ?>
                    <h2 class="display-4"><?php echo number_format($completion_rate, 1); ?>%</h2>
                    <p>In Progress: <?php echo count($attempts) - $completed_count; ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Results Visualization -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Score Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="scoreDistributionChart" height="300"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">College-wise Performance</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($college_stats)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> No college-wise data available.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>College</th>
                                        <th>Attempts</th>
                                        <th>Avg. Score</th>
                                        <th>Pass Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($college_stats as $stat): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($stat['college_name']); ?></td>
                                            <td><?php echo $stat['attempt_count']; ?></td>
                                            <td><?php echo number_format($stat['avg_percentage'], 1); ?>%</td>
                                            <td>
                                                <?php 
                                                $college_pass_rate = $stat['attempt_count'] > 0 
                                                    ? ($stat['pass_count'] / $stat['attempt_count']) * 100 
                                                    : 0;
                                                echo number_format($college_pass_rate, 1) . '%'; 
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Question Analysis -->
    <div class="card mb-4">
        <div class="card-header bg-warning text-dark">
            <h5 class="mb-0">Question Analysis</h5>
        </div>
        <div class="card-body">
            <?php if (empty($question_stats)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> No question data available.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Question</th>
                                <th>Type</th>
                                <th>Max Marks</th>
                                <th>Attempts</th>
                                <th>Correct</th>
                                <th>Success Rate</th>
                                <th>Avg. Marks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($question_stats as $index => $question): ?>
                                <?php 
                                $success_rate = $question['total_answers'] > 0 
                                    ? ($question['correct_answers'] / $question['total_answers']) * 100 
                                    : 0;
                                $avg_marks_percentage = $question['marks'] > 0 
                                    ? ($question['avg_marks'] / $question['marks']) * 100 
                                    : 0;
                                ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars(substr($question['question_text'], 0, 100) . (strlen($question['question_text']) > 100 ? '...' : '')); ?></td>
                                    <td><?php echo ucfirst(str_replace('_', ' ', $question['question_type'])); ?></td>
                                    <td><?php echo $question['marks']; ?></td>
                                    <td><?php echo $question['total_answers']; ?></td>
                                    <td><?php echo $question['correct_answers']; ?></td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar <?php echo $success_rate >= 50 ? 'bg-success' : ($success_rate >= 30 ? 'bg-warning' : 'bg-danger'); ?>" 
                                                 role="progressbar" 
                                                 style="width: <?php echo $success_rate; ?>%;" 
                                                 aria-valuenow="<?php echo $success_rate; ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="100">
                                                <?php echo number_format($success_rate, 1); ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-info" 
                                                 role="progressbar" 
                                                 style="width: <?php echo $avg_marks_percentage; ?>%;" 
                                                 aria-valuenow="<?php echo $avg_marks_percentage; ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="100">
                                                <?php echo number_format($question['avg_marks'], 2); ?> / <?php echo $question['marks']; ?>
                                            </div>
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

    <!-- Student Attempts Table -->
    <div class="card mb-4">
        <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Student Attempts</h5>
            <button class="btn btn-sm btn-light" onclick="exportToCSV()">
                <i class="fas fa-download"></i> Export to CSV
            </button>
        </div>
        <div class="card-body">
            <?php if (empty($attempts)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> No attempts have been made for this exam yet.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="attemptsTable">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Reg. No.</th>
                                <th>College</th>
                                <th>Start Time</th>
                                <th>End Time</th>
                                <th>Status</th>
                                <th>Score</th>
                                <th>Percentage</th>
                                <th>Result</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attempts as $attempt): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($attempt['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($attempt['reg_no']); ?></td>
                                    <td><?php echo htmlspecialchars($attempt['college_name'] ?? 'Not Assigned'); ?></td>
                                    <td><?php echo date('M d, Y h:i A', strtotime($attempt['start_time'])); ?></td>
                                    <td>
                                        <?php if ($attempt['end_time']): ?>
                                            <?php echo date('M d, Y h:i A', strtotime($attempt['end_time'])); ?>
                                        <?php else: ?>
                                            <span class="text-muted">In Progress</span>
                                        <?php endif; ?>
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
                                    <td><?php echo $attempt['total_score']; ?> / <?php echo $exam['total_marks']; ?></td>
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
                                            <?php if ($attempt['percentage'] >= 40): ?>
                                                <span class="badge bg-success">Pass</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Fail</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="view_attempt_details.php?attempt_id=<?php echo $attempt['attempt_id']; ?>" class="btn btn-sm btn-info">
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
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Score Distribution Chart
    const scoreData = <?php 
        $score_ranges = [
            '0-20' => 0,
            '21-40' => 0,
            '41-60' => 0,
            '61-80' => 0,
            '81-100' => 0
        ];
        
        foreach ($attempts as $attempt) {
            if ($attempt['status'] == 'completed') {
                $percentage = $attempt['percentage'];
                if ($percentage <= 20) {
                    $score_ranges['0-20']++;
                } elseif ($percentage <= 40) {
                    $score_ranges['21-40']++;
                } elseif ($percentage <= 60) {
                    $score_ranges['41-60']++;
                } elseif ($percentage <= 80) {
                    $score_ranges['61-80']++;
                } else {
                    $score_ranges['81-100']++;
                }
            }
        }
        
        echo json_encode($score_ranges);
    ?>;
    
    const scoreCtx = document.getElementById('scoreDistributionChart').getContext('2d');
    new Chart(scoreCtx, {
        type: 'bar',
        data: {
            labels: Object.keys(scoreData),
            datasets: [{
                label: 'Number of Students',
                data: Object.values(scoreData),
                backgroundColor: [
                    'rgba(255, 99, 132, 0.7)',
                    'rgba(255, 159, 64, 0.7)',
                    'rgba(255, 205, 86, 0.7)',
                    'rgba(75, 192, 192, 0.7)',
                    'rgba(54, 162, 235, 0.7)'
                ],
                borderColor: [
                    'rgb(255, 99, 132)',
                    'rgb(255, 159, 64)',
                    'rgb(255, 205, 86)',
                    'rgb(75, 192, 192)',
                    'rgb(54, 162, 235)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Number of Students'
                    },
                    ticks: {
                        stepSize: 1
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Score Range (%)'
                    }
                }
            },
            plugins: {
                title: {
                    display: true,
                    text: 'Student Score Distribution'
                },
                legend: {
                    display: false
                }
            }
        }
    });
});

// Function to export table to CSV
function exportToCSV() {
    const table = document.getElementById('attemptsTable');
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = [], cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length - 1; j++) { // Skip the Actions column
            // Get the text content and clean it up
            let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, '').replace(/(\s\s)/gm, ' ');
            // Escape double quotes
            data = data.replace(/"/g, '""');
            // Add the data to the row array
            row.push('"' + data + '"');
        }
        csv.push(row.join(','));
    }
    
    // Download CSV file
    downloadCSV(csv.join('\n'), 'exam_results_<?php echo $exam_id; ?>.csv');
}

function downloadCSV(csv, filename) {
    const csvFile = new Blob([csv], {type: 'text/csv'});
    const downloadLink = document.createElement('a');
    
    // Create a download link
    downloadLink.download = filename;
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = 'none';
    
    // Add the link to the DOM and trigger the download
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}
</script>

<?php include '../includes/footer.php'; ?>
