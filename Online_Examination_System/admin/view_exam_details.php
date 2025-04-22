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

// Check if exam ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_exams.php?error=Invalid exam ID");
    exit();
}

$exam_id = (int)$_GET['id'];

// Get exam details with faculty information
$stmt = $conn->prepare("
    SELECT e.*, f.name as faculty_name, f.email as faculty_email, f.contact as faculty_contact,
           c.name as college_name,
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

// Get exam questions
$stmt = $conn->prepare("SELECT * FROM Question WHERE exam_id = ? ORDER BY question_id");
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$result = $stmt->get_result();
$questions = [];
while ($row = $result->fetch_assoc()) {
    $questions[] = $row;
}

// Get exam availability settings
$stmt = $conn->prepare("SELECT * FROM Exam_Availability WHERE exam_id = ?");
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$result = $stmt->get_result();
$availability = [];
while ($row = $result->fetch_assoc()) {
    $availability[] = $row;
}

// Get recent attempts
$stmt = $conn->prepare("
    SELECT ea.*, s.name as student_name, s.reg_no, s.email as student_email,
           (ea.total_score / e.total_marks * 100) as percentage
    FROM Exam_Attempt ea
    JOIN Student s ON ea.student_id = s.student_id
    JOIN Exam e ON ea.exam_id = e.exam_id
    WHERE ea.exam_id = ?
    ORDER BY ea.start_time DESC
    LIMIT 10
");
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$result = $stmt->get_result();
$attempts = [];
while ($row = $result->fetch_assoc()) {
    $attempts[] = $row;
}

$base_path = '../';
include '../includes/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Exam Details</h1>
        <div>
            <a href="manage_exams.php" class="btn btn-secondary me-2">
                <i class="fas fa-arrow-left"></i> Back to Exams
            </a>
            <a href="view_exam_results.php?exam_id=<?php echo $exam_id; ?>" class="btn btn-success">
                <i class="fas fa-chart-bar"></i> View Results
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

    <!-- Exam Overview Card -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Exam Overview</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h2><?php echo htmlspecialchars($exam['title']); ?></h2>
                    <p class="text-muted"><?php echo htmlspecialchars($exam['subject']); ?></p>
                    <p><?php echo htmlspecialchars($exam['description'] ?? 'No description available.'); ?></p>
                    
                    <h5 class="mt-4">Exam Settings</h5>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Total Marks
                            <span class="badge bg-primary rounded-pill"><?php echo $exam['total_marks']; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Duration
                            <span class="badge bg-primary rounded-pill"><?php echo $exam['duration']; ?> minutes</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Questions
                            <span class="badge bg-primary rounded-pill"><?php echo $exam['question_count']; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Total Attempts
                            <span class="badge bg-primary rounded-pill"><?php echo $exam['attempt_count']; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Completed Attempts
                            <span class="badge bg-primary rounded-pill"><?php echo $exam['completed_count']; ?></span>
                        </li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">Schedule Information</h5>
                        </div>
                        <div class="card-body">
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
                            <p><strong>Status:</strong> <?php echo $status; ?></p>
                            <p><strong>Start Time:</strong> <?php echo date('M d, Y h:i A', strtotime($exam['start_time'])); ?></p>
                            <p><strong>End Time:</strong> <?php echo date('M d, Y h:i A', strtotime($exam['end_time'])); ?></p>
                            
                            <h6 class="mt-4">Faculty Information</h6>
                            <p><strong>Created By:</strong> <?php echo htmlspecialchars($exam['faculty_name']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($exam['faculty_email']); ?></p>
                            <p><strong>Contact:</strong> <?php echo htmlspecialchars($exam['faculty_contact']); ?></p>
                            <p><strong>College:</strong> <?php echo htmlspecialchars($exam['college_name'] ?? 'Not Assigned'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Exam Availability Card -->
    <div class="card mb-4">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0">Exam Availability</h5>
        </div>
        <div class="card-body">
            <?php if (empty($availability)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> No specific availability settings found. This exam may be available to all students.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Student Group</th>
                                <th>Available From</th>
                                <th>Available Until</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($availability as $avail): ?>
                                <tr>
                                    <td>
                                        <?php 
                                        if ($avail['student_group'] == 'all') {
                                            echo 'All Students';
                                        } elseif (strpos($avail['student_group'], 'college_') === 0) {
                                            $college_id = substr($avail['student_group'], 8);
                                            $stmt = $conn->prepare("SELECT name FROM College WHERE college_id = ?");
                                            $stmt->bind_param("i", $college_id);
                                            $stmt->execute();
                                            $result = $stmt->get_result();
                                            if ($result->num_rows > 0) {
                                                $college = $result->fetch_assoc();
                                                echo 'College: ' . htmlspecialchars($college['name']);
                                            } else {
                                                echo htmlspecialchars($avail['student_group']);
                                            }
                                        } else {
                                            echo htmlspecialchars($avail['student_group']);
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo date('M d, Y h:i A', strtotime($avail['available_from'])); ?></td>
                                    <td><?php echo date('M d, Y h:i A', strtotime($avail['available_until'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Questions Card -->
    <div class="card mb-4">
        <div class="card-header bg-warning text-dark">
            <h5 class="mb-0">Exam Questions</h5>
        </div>
        <div class="card-body">
            <?php if (empty($questions)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> No questions have been added to this exam yet.
                </div>
            <?php else: ?>
                <div class="accordion" id="questionsAccordion">
                    <?php foreach ($questions as $index => $question): ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="heading<?php echo $question['question_id']; ?>">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $question['question_id']; ?>" aria-expanded="false" aria-controls="collapse<?php echo $question['question_id']; ?>">
                                    <div class="d-flex justify-content-between align-items-center w-100 me-3">
                                        <span>Question <?php echo $index + 1; ?>: <?php echo htmlspecialchars(substr($question['question_text'], 0, 100)) . (strlen($question['question_text']) > 100 ? '...' : ''); ?></span>
                                        <span class="badge bg-info ms-2"><?php echo $question['marks']; ?> marks</span>
                                    </div>
                                </button>
                            </h2>
                            <div id="collapse<?php echo $question['question_id']; ?>" class="accordion-collapse collapse" aria-labelledby="heading<?php echo $question['question_id']; ?>" data-bs-parent="#questionsAccordion">
                                <div class="accordion-body">
                                    <p><strong>Question:</strong> <?php echo htmlspecialchars($question['question_text']); ?></p>
                                    <p><strong>Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $question['question_type'])); ?></p>
                                    <p><strong>Marks:</strong> <?php echo $question['marks']; ?></p>
                                    
                                    <?php if ($question['question_type'] == 'multiple_choice'): ?>
                                        <div class="mb-3">
                                            <p><strong>Options:</strong></p>
                                            <ul class="list-group">
                                                <li class="list-group-item <?php echo $question['correct_answer'] == 'A' ? 'list-group-item-success' : ''; ?>">
                                                    A. <?php echo htmlspecialchars($question['option_a']); ?>
                                                    <?php if ($question['correct_answer'] == 'A'): ?>
                                                        <span class="badge bg-success float-end">Correct Answer</span>
                                                    <?php endif; ?>
                                                </li>
                                                <li class="list-group-item <?php echo $question['correct_answer'] == 'B' ? 'list-group-item-success' : ''; ?>">
                                                    B. <?php echo htmlspecialchars($question['option_b']); ?>
                                                    <?php if ($question['correct_answer'] == 'B'): ?>
                                                        <span class="badge bg-success float-end">Correct Answer</span>
                                                    <?php endif; ?>
                                                </li>
                                                <li class="list-group-item <?php echo $question['correct_answer'] == 'C' ? 'list-group-item-success' : ''; ?>">
                                                    C. <?php echo htmlspecialchars($question['option_c']); ?>
                                                    <?php if ($question['correct_answer'] == 'C'): ?>
                                                        <span class="badge bg-success float-end">Correct Answer</span>
                                                    <?php endif; ?>
                                                </li>
                                                <li class="list-group-item <?php echo $question['correct_answer'] == 'D' ? 'list-group-item-success' : ''; ?>">
                                                    D. <?php echo htmlspecialchars($question['option_d']); ?>
                                                    <?php if ($question['correct_answer'] == 'D'): ?>
                                                        <span class="badge bg-success float-end">Correct Answer</span>
                                                    <?php endif; ?>
                                                </li>
                                            </ul>
                                        </div>
                                    <?php elseif ($question['question_type'] == 'true_false'): ?>
                                        <div class="mb-3">
                                            <p><strong>Correct Answer:</strong> <?php echo $question['correct_answer']; ?></p>
                                        </div>
                                    <?php elseif ($question['question_type'] == 'descriptive'): ?>
                                        <div class="mb-3">
                                            <p><strong>Answer Guidelines:</strong> <?php echo htmlspecialchars($question['correct_answer'] ?? 'No guidelines provided'); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Attempts Card -->
    <div class="card mb-4">
        <div class="card-header bg-secondary text-white">
            <h5 class="mb-0">Recent Attempts</h5>
        </div>
        <div class="card-body">
            <?php if (empty($attempts)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> No attempts have been made for this exam yet.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Reg. No.</th>
                                <th>Start Time</th>
                                <th>End Time</th>
                                <th>Status</th>
                                <th>Score</th>
                                <th>Percentage</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attempts as $attempt): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($attempt['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($attempt['reg_no']); ?></td>
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
                                        <a href="view_attempt_details.php?attempt_id=<?php echo $attempt['attempt_id']; ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($exam['attempt_count'] > 10): ?>
                    <div class="text-center mt-3">
                        <a href="view_exam_results.php?exam_id=<?php echo $exam_id; ?>" class="btn btn-primary">
                            View All Attempts
                        </a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
