<?php
require_once '../includes/auth.php';
require_user_type('student');

// Check if attempt_id is provided
if (!isset($_GET['attempt_id'])) {
    header("Location: exam_results.php?error=No attempt selected");
    exit();
}

$attempt_id = (int)$_GET['attempt_id'];
$student_id = $_SESSION['user_id'];

// Verify that this attempt belongs to the student
$stmt = $conn->prepare("SELECT * FROM Exam_Attempt WHERE attempt_id = ? AND student_id = ?");
$stmt->bind_param("ii", $attempt_id, $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: exam_results.php?error=Unauthorized access to result");
    exit();
}

// Get detailed result
$result_details = get_attempt_details($attempt_id);
$attempt = $result_details['attempt'];
$answers = $result_details['answers'];

// Calculate statistics
$total_questions = count($answers);
$correct_answers = 0;
$incorrect_answers = 0;
$unanswered = 0;

foreach ($answers as $answer) {
    if (empty($answer['answer_text'])) {
        $unanswered++;
    } elseif ($answer['is_correct']) {
        $correct_answers++;
    } else {
        $incorrect_answers++;
    }
}

$percentage = ($attempt['total_score'] / $attempt['total_marks']) * 100;

$base_path = '../';
include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Exam Result</h1>
    <a href="exam_results.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Results
    </a>
</div>

<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Result Summary</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <p><strong>Exam:</strong> <?php echo htmlspecialchars($attempt['title']); ?></p>
                <p><strong>Subject:</strong> <?php echo htmlspecialchars($attempt['subject']); ?></p>
                <p><strong>Date Taken:</strong> <?php echo date('M d, Y h:i A', strtotime($attempt['start_time'])); ?></p>
                <p><strong>Status:</strong> 
                    <?php if ($attempt['status'] == 'completed'): ?>
                        <span class="badge bg-success">Completed</span>
                    <?php elseif ($attempt['status'] == 'in_progress'): ?>
                        <span class="badge bg-warning">In Progress</span>
                    <?php else: ?>
                        <span class="badge bg-danger">Timed Out</span>
                    <?php endif; ?>
                </p>
            </div>
            <div class="col-md-6">
                <p><strong>Score:</strong> <?php echo $attempt['total_score']; ?> / <?php echo $attempt['total_marks']; ?></p>
                <p><strong>Percentage:</strong> <?php echo number_format($percentage, 2); ?>%</p>
                <p><strong>Result:</strong> 
                    <?php if ($percentage >= 40): ?>
                        <span class="badge bg-success">Pass</span>
                    <?php else: ?>
                        <span class="badge bg-danger">Fail</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <h5 class="card-title">Total Questions</h5>
                <h2 class="display-4"><?php echo $total_questions; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-success text-white">
            <div class="card-body text-center">
                <h5 class="card-title">Correct Answers</h5>
                <h2 class="display-4"><?php echo $correct_answers; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-danger text-white">
            <div class="card-body text-center">
                <h5 class="card-title">Incorrect Answers</h5>
                <h2 class="display-4"><?php echo $incorrect_answers; ?></h2>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header bg-info text-white">
        <h5 class="mb-0">Detailed Answers</h5>
    </div>
    <div class="card-body">
        <?php foreach ($answers as $index => $answer): ?>
            <div class="card mb-3 <?php echo $answer['is_correct'] ? 'border-success' : 'border-danger'; ?>">
                <div class="card-header <?php echo $answer['is_correct'] ? 'bg-success' : 'bg-danger'; ?> text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Question <?php echo $index + 1; ?></h5>
                        <span class="badge bg-light text-dark"><?php echo $answer['marks']; ?> marks</span>
                    </div>
                </div>
                <div class="card-body">
                    <p><strong>Question:</strong> <?php echo htmlspecialchars($answer['question_text']); ?></p>
                    
                    <?php if ($answer['question_type'] == 'multiple_choice'): ?>
                        <p><strong>Your Answer:</strong> <?php echo empty($answer['answer_text']) ? 'Not answered' : htmlspecialchars($answer['answer_text']); ?></p>
                        <p><strong>Correct Answer:</strong> <?php echo htmlspecialchars($answer['correct_answer']); ?></p>
                    <?php elseif ($answer['question_type'] == 'true_false'): ?>
                        <p><strong>Your Answer:</strong> <?php echo empty($answer['answer_text']) ? 'Not answered' : htmlspecialchars($answer['answer_text']); ?></p>
                        <p><strong>Correct Answer:</strong> <?php echo htmlspecialchars($answer['correct_answer']); ?></p>
                    <?php elseif ($answer['question_type'] == 'descriptive'): ?>
                        <p><strong>Your Answer:</strong> <?php echo empty($answer['answer_text']) ? 'Not answered' : htmlspecialchars($answer['answer_text']); ?></p>
                    <?php endif; ?>
                    
                    <p><strong>Marks Obtained:</strong> <?php echo $answer['marks_obtained']; ?> / <?php echo $answer['marks']; ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
