<?php
session_start();
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Set timezone to IST
date_default_timezone_set('Asia/Kolkata');

// Require admin login
require_user_type('admin');

if (!isset($_GET['attempt_id']) || !is_numeric($_GET['attempt_id'])) {
    header("Location: manage_exams.php?error=No attempt selected");
    exit();
}

$attempt_id = (int)$_GET['attempt_id'];

// Fetch attempt, student, and exam info
$stmt = $conn->prepare("
    SELECT ea.*, s.name AS student_name, s.reg_no, s.email AS student_email, c.name AS college_name,
           e.title AS exam_title, e.subject AS exam_subject, e.total_marks, e.duration, e.start_time, e.end_time,
           f.name AS faculty_name
    FROM Exam_Attempt ea
    JOIN Student s ON ea.student_id = s.student_id
    LEFT JOIN College c ON s.college_id = c.college_id
    JOIN Exam e ON ea.exam_id = e.exam_id
    JOIN Faculty f ON e.created_by = f.faculty_id
    WHERE ea.attempt_id = ?
");
$stmt->bind_param("i", $attempt_id);
$stmt->execute();
$attempt = $stmt->get_result()->fetch_assoc();

if (!$attempt) {
    header("Location: manage_exams.php?error=Attempt not found");
    exit();
}

// Fetch all answers for this attempt, with question details
$stmt = $conn->prepare("
    SELECT a.*, q.question_text, q.question_type, q.marks, q.option_a, q.option_b, q.option_c, q.option_d, q.correct_answer
    FROM Answer a
    JOIN Question q ON a.question_id = q.question_id
    WHERE a.attempt_id = ?
    ORDER BY q.question_id
");
$stmt->bind_param("i", $attempt_id);
$stmt->execute();
$result = $stmt->get_result();
$answers = [];
while ($row = $result->fetch_assoc()) {
    $answers[] = $row;
}

$base_path = '../';
include '../includes/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Attempt Details</h1>
        <a href="view_exam_results.php?exam_id=<?php echo $attempt['exam_id']; ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Exam Results
        </a>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Student & Exam Info</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Student:</strong> <?php echo htmlspecialchars($attempt['student_name']); ?> (<?php echo htmlspecialchars($attempt['reg_no']); ?>)</p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($attempt['student_email']); ?></p>
                    <p><strong>College:</strong> <?php echo htmlspecialchars($attempt['college_name'] ?? 'Not Assigned'); ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Exam:</strong> <?php echo htmlspecialchars($attempt['exam_title']); ?></p>
                    <p><strong>Subject:</strong> <?php echo htmlspecialchars($attempt['exam_subject']); ?></p>
                    <p><strong>Faculty:</strong> <?php echo htmlspecialchars($attempt['faculty_name']); ?></p>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-md-4">
                    <strong>Attempt Start:</strong> <?php echo date('M d, Y h:i A', strtotime($attempt['start_time'])); ?>
                </div>
                <div class="col-md-4">
                    <strong>Attempt End:</strong> 
                    <?php echo $attempt['end_time'] ? date('M d, Y h:i A', strtotime($attempt['end_time'])) : '<span class="text-muted">In Progress</span>'; ?>
                </div>
                <div class="col-md-4">
                    <strong>Status:</strong>
                    <?php
                    if ($attempt['status'] == 'completed') {
                        echo '<span class="badge bg-success">Completed</span>';
                    } elseif ($attempt['status'] == 'in_progress') {
                        echo '<span class="badge bg-warning">In Progress</span>';
                    } else {
                        echo '<span class="badge bg-danger">Timed Out</span>';
                    }
                    ?>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-md-4">
                    <strong>Total Score:</strong> <?php echo $attempt['total_score']; ?> / <?php echo $attempt['total_marks']; ?>
                </div>
                <div class="col-md-4">
                    <strong>Percentage:</strong>
                    <?php
                    $percentage = $attempt['total_marks'] > 0 ? ($attempt['total_score'] / $attempt['total_marks']) * 100 : 0;
                    echo number_format($percentage, 1) . '%';
                    ?>
                </div>
                <div class="col-md-4">
                    <strong>Result:</strong>
                    <?php
                    if ($attempt['status'] == 'completed') {
                        echo $percentage >= 40
                            ? '<span class="badge bg-success">Pass</span>'
                            : '<span class="badge bg-danger">Fail</span>';
                    } else {
                        echo '<span class="badge bg-secondary">Pending</span>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Answers Table -->
    <div class="card mb-4">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0">Answers & Grading</h5>
        </div>
        <div class="card-body">
            <?php if (empty($answers)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> No answers found for this attempt.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Question</th>
                                <th>Type</th>
                                <th>Student Answer</th>
                                <th>Correct Answer</th>
                                <th>Marks</th>
                                <th>Result</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($answers as $idx => $ans): ?>
                                <tr>
                                    <td><?php echo $idx + 1; ?></td>
                                    <td><?php echo htmlspecialchars($ans['question_text']); ?></td>
                                    <td><?php echo ucfirst(str_replace('_', ' ', $ans['question_type'])); ?></td>
                                    <td>
                                        <?php
                                        if ($ans['question_type'] == 'multiple_choice') {
                                            $opt = $ans['answer_text'];
                                            echo $opt ? $opt . '. ' . htmlspecialchars($ans['option_' . strtolower($opt)]) : '<span class="text-muted">No answer</span>';
                                        } elseif ($ans['question_type'] == 'true_false') {
                                            echo htmlspecialchars($ans['answer_text']);
                                        } else {
                                            echo nl2br(htmlspecialchars($ans['answer_text']));
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        if ($ans['question_type'] == 'multiple_choice') {
                                            $opt = $ans['correct_answer'];
                                            echo $opt . '. ' . htmlspecialchars($ans['option_' . strtolower($opt)]);
                                        } elseif ($ans['question_type'] == 'true_false') {
                                            echo htmlspecialchars($ans['correct_answer']);
                                        } else {
                                            echo nl2br(htmlspecialchars($ans['correct_answer'] ?? 'N/A'));
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php echo $ans['marks_obtained'] . ' / ' . $ans['marks']; ?>
                                    </td>
                                    <td>
                                        <?php
                                        if ($ans['question_type'] == 'descriptive') {
                                            echo '<span class="badge bg-secondary">Pending</span>';
                                        } else {
                                            echo $ans['is_correct']
                                                ? '<span class="badge bg-success">Correct</span>'
                                                : '<span class="badge bg-danger">Incorrect</span>';
                                        }
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

<?php include '../includes/footer.php'; ?>
