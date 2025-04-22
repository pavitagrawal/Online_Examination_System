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

// Get exam ID from URL if provided
$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;

// If specific exam is selected, get its details
$exam = null;
if ($exam_id > 0) {
    // Verify that this exam belongs to the faculty
    $stmt = $conn->prepare("SELECT * FROM Exam WHERE exam_id = ? AND created_by = ?");
    $stmt->bind_param("ii", $exam_id, $faculty_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $exam = $result->fetch_assoc();
    } else {
        $error = "Unauthorized access to exam or exam not found.";
        $exam_id = 0; // Reset exam_id if unauthorized
    }
}

// Get all exams created by this faculty for the dropdown
$exams_query = "SELECT exam_id, title, subject FROM Exam WHERE created_by = ? ORDER BY start_time DESC";
$stmt = $conn->prepare($exams_query);
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$result = $stmt->get_result();

$exams = [];
while ($row = $result->fetch_assoc()) {
    $exams[] = $row;
}

// Get attempt statistics for the selected exam
$attempts = [];
$students = [];
$statistics = [
    'total_attempts' => 0,
    'completed_attempts' => 0,
    'avg_score' => 0,
    'high_score' => 0,
    'low_score' => 100,
    'pass_count' => 0,
    'fail_count' => 0
];

if ($exam_id > 0) {
    // Get all attempts for this exam
    $attempts_query = "
        SELECT ea.*, s.name as student_name, s.reg_no, s.email, c.name as college_name,
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
    
    $total_score = 0;
    $completed_count = 0;
    
    while ($row = $result->fetch_assoc()) {
        $attempts[] = $row;
        
        // Collect statistics for completed attempts
        if ($row['status'] == 'completed') {
            $completed_count++;
            $total_score += $row['percentage'];
            
            // Update high and low scores
            if ($row['percentage'] > $statistics['high_score']) {
                $statistics['high_score'] = $row['percentage'];
            }
            if ($row['percentage'] < $statistics['low_score']) {
                $statistics['low_score'] = $row['percentage'];
            }
            
            // Count passes and fails (assuming 40% is pass mark)
            if ($row['percentage'] >= 40) {
                $statistics['pass_count']++;
            } else {
                $statistics['fail_count']++;
            }
        }
        
        // Add student to the list if not already there
        $found = false;
        foreach ($students as $student) {
            if ($student['student_id'] == $row['student_id']) {
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $students[] = [
                'student_id' => $row['student_id'],
                'name' => $row['student_name'],
                'reg_no' => $row['reg_no'],
                'email' => $row['email'],
                'college' => $row['college_name']
            ];
        }
    }
    
    // Calculate statistics
    $statistics['total_attempts'] = count($attempts);
    $statistics['completed_attempts'] = $completed_count;
    $statistics['avg_score'] = $completed_count > 0 ? $total_score / $completed_count : 0;
    
    // If no attempts, set low score to 0
    if ($completed_count == 0) {
        $statistics['low_score'] = 0;
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
}

$base_path = '../';
include '../includes/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?php echo $exam ? 'Exam Results: ' . htmlspecialchars($exam['title']) : 'View Exam Results'; ?></h1>
        <a href="manage_exams.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Exams
        </a>
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
    
    <!-- Exam Selection Form -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Select Exam</h5>
        </div>
        <div class="card-body">
            <form method="get" action="" class="row g-3">
                <div class="col-md-10">
                    <select name="exam_id" class="form-select" required>
                        <option value="">-- Select an Exam --</option>
                        <?php foreach ($exams as $e): ?>
                            <option value="<?php echo $e['exam_id']; ?>" <?php echo ($exam_id == $e['exam_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($e['title'] . ' (' . $e['subject'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">View Results</button>
                </div>
            </form>
        </div>
    </div>
    
    <?php if ($exam_id > 0 && $exam): ?>
        <!-- Exam Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white h-100">
                    <div class="card-body text-center">
                        <h5 class="card-title">Total Attempts</h5>
                        <h2 class="display-4"><?php echo $statistics['total_attempts']; ?></h2>
                        <p>Completed: <?php echo $statistics['completed_attempts']; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white h-100">
                    <div class="card-body text-center">
                        <h5 class="card-title">Average Score</h5>
                        <h2 class="display-4"><?php echo number_format($statistics['avg_score'], 1); ?>%</h2>
                        <p>High: <?php echo number_format($statistics['high_score'], 1); ?>% | Low: <?php echo number_format($statistics['low_score'], 1); ?>%</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white h-100">
                    <div class="card-body text-center">
                        <h5 class="card-title">Pass Rate</h5>
                        <?php 
                        $pass_rate = $statistics['completed_attempts'] > 0 
                            ? ($statistics['pass_count'] / $statistics['completed_attempts']) * 100 
                            : 0;
                        ?>
                        <h2 class="display-4"><?php echo number_format($pass_rate, 1); ?>%</h2>
                        <p>Passed: <?php echo $statistics['pass_count']; ?> | Failed: <?php echo $statistics['fail_count']; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white h-100">
                    <div class="card-body text-center">
                        <h5 class="card-title">Exam Details</h5>
                        <p class="mb-0"><strong>Subject:</strong> <?php echo htmlspecialchars($exam['subject']); ?></p>
                        <p class="mb-0"><strong>Total Marks:</strong> <?php echo $exam['total_marks']; ?></p>
                        <p class="mb-0"><strong>Duration:</strong> <?php echo $exam['duration']; ?> mins</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Student Attempts Table -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
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
                            <thead class="table-dark">
                                <tr>
                                    <th>Student Name</th>
                                    <th>Reg. No.</th>
                                    <th>College</th>
                                    <th>Start Time</th>
                                    <th>End Time</th>
                                    <th>Status</th>
                                    <th>Score</th>
                                    <th>Percentage</th>
                                    <th>Result</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attempts as $attempt): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($attempt['student_name']); ?></td>
                                        <td><?php echo htmlspecialchars($attempt['reg_no']); ?></td>
                                        <td><?php echo htmlspecialchars($attempt['college_name'] ?? 'N/A'); ?></td>
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
                                            <a href="view_attempt.php?attempt_id=<?php echo $attempt['attempt_id']; ?>" class="btn btn-sm btn-info">
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
        
        <!-- Question Analysis -->
        <?php if (!empty($question_stats)): ?>
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Question Analysis</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
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
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Performance Visualization -->
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Score Distribution</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="scoreDistributionChart" height="300"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Pass/Fail Ratio</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="passFailChart" height="300"></canvas>
                    </div>
                </div>
            </div>
        </div>
    <?php elseif ($exam_id > 0): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle me-2"></i> You do not have permission to view this exam or the exam does not exist.
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i> Please select an exam to view results.
        </div>
    <?php endif; ?>
</div>

<?php if ($exam_id > 0 && $exam && !empty($attempts)): ?>
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
    
    // Pass/Fail Chart
    const passFailData = {
        labels: ['Pass', 'Fail'],
        datasets: [{
            label: 'Students',
            data: [<?php echo $statistics['pass_count']; ?>, <?php echo $statistics['fail_count']; ?>],
            backgroundColor: [
                'rgba(75, 192, 192, 0.7)',
                'rgba(255, 99, 132, 0.7)'
            ],
            borderColor: [
                'rgb(75, 192, 192)',
                'rgb(255, 99, 132)'
            ],
            borderWidth: 1
        }]
    };
    
    const passFailCtx = document.getElementById('passFailChart').getContext('2d');
    new Chart(passFailCtx, {
        type: 'pie',
        data: passFailData,
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: 'Pass/Fail Distribution'
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
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
