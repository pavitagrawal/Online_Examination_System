<?php
session_start();
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Set timezone to IST for consistent time handling
date_default_timezone_set('Asia/Kolkata');

// Require student login
require_user_type('student');

$student_id = $_SESSION['user_id'];
$error = "";
$success = "";

// Get student details including college
$stmt = $conn->prepare("SELECT s.*, c.name as college_name FROM Student s 
                        LEFT JOIN College c ON s.college_id = c.college_id 
                        WHERE s.student_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$college_id = $student['college_id'];

// Check if exam_id is provided
if (!isset($_GET['exam_id'])) {
    header("Location: available_exams.php");
    exit();
}

$exam_id = (int)$_GET['exam_id'];

// Verify exam exists and is available
$now = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
$now_str = $now->format('Y-m-d H:i:s');

// First check: Is this exam created by a faculty from the student's college?
$college_check_query = "
    SELECT e.*, f.name as faculty_name 
    FROM Exam e
    JOIN Faculty f ON e.created_by = f.faculty_id
    WHERE e.exam_id = ? AND f.college_id = ?
";
$stmt = $conn->prepare($college_check_query);
$stmt->bind_param("ii", $exam_id, $college_id);
$stmt->execute();
$result = $stmt->get_result();
$exam_from_college = ($result->num_rows > 0);

// Second check: Is this exam available through exam_availability?
$availability_check_query = "
    SELECT * FROM Exam_Availability 
    WHERE exam_id = ? 
    AND available_from <= ? 
    AND available_until >= ?
    AND (student_group = 'all' OR student_group = CONCAT('college_', ?))
";
$stmt = $conn->prepare($availability_check_query);
$stmt->bind_param("issi", $exam_id, $now_str, $now_str, $college_id);
$stmt->execute();
$result = $stmt->get_result();
$exam_in_availability = ($result->num_rows > 0);

// Third check: Is the exam currently active (between start and end time)?
$exam_active_query = "
    SELECT e.*, f.name as faculty_name 
    FROM Exam e
    JOIN Faculty f ON e.created_by = f.faculty_id
    WHERE e.exam_id = ? 
    AND e.start_time <= ? 
    AND e.end_time >= ?
";
$stmt = $conn->prepare($exam_active_query);
$stmt->bind_param("iss", $exam_id, $now_str, $now_str);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0 && !($exam_from_college || $exam_in_availability)) {
    $error = "This exam is not available or does not exist.";
} else {
    $exam = $result->fetch_assoc();
    
    // Check if student has already completed this exam
    $stmt = $conn->prepare("SELECT * FROM Exam_Attempt WHERE student_id = ? AND exam_id = ? AND status = 'completed'");
    $stmt->bind_param("ii", $student_id, $exam_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $error = "You have already completed this exam.";
    } else {
        // Check if student has an in-progress attempt
        $stmt = $conn->prepare("SELECT * FROM Exam_Attempt WHERE student_id = ? AND exam_id = ? AND status = 'in_progress'");
        $stmt->bind_param("ii", $student_id, $exam_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $attempt = $result->fetch_assoc();
            $attempt_id = $attempt['attempt_id'];
        } else {
            // Create a new attempt
            $stmt = $conn->prepare("INSERT INTO Exam_Attempt (student_id, exam_id, start_time, status) VALUES (?, ?, ?, 'in_progress')");
            $stmt->bind_param("iis", $student_id, $exam_id, $now_str);
            $stmt->execute();
            $attempt_id = $conn->insert_id;
        }
        
        // Get questions for this exam
        $stmt = $conn->prepare("SELECT * FROM Question WHERE exam_id = ? ORDER BY question_id");
        $stmt->bind_param("i", $exam_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $questions = [];
        
        while ($row = $result->fetch_assoc()) {
            $questions[] = $row;
        }
    }
}

// Handle form submission (when student submits answers)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_exam'])) {
    $attempt_id = (int)$_POST['attempt_id'];
    
    // Verify this attempt belongs to the current student
    $stmt = $conn->prepare("SELECT * FROM Exam_Attempt WHERE attempt_id = ? AND student_id = ?");
    $stmt->bind_param("ii", $attempt_id, $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        $error = "Invalid attempt.";
    } else {
        $attempt = $result->fetch_assoc();
        $exam_id = $attempt['exam_id'];
        
        // Get all questions for this exam
        $stmt = $conn->prepare("SELECT * FROM Question WHERE exam_id = ?");
        $stmt->bind_param("i", $exam_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $total_score = 0;
        
        // Process each question and save answers
        while ($question = $result->fetch_assoc()) {
            $question_id = $question['question_id'];
            $answer_text = isset($_POST['answer_'.$question_id]) ? $_POST['answer_'.$question_id] : '';
            $is_correct = false;
            $marks_obtained = 0;
            
            // Check if answer is correct based on question type
            if ($question['question_type'] == 'multiple_choice' || $question['question_type'] == 'true_false') {
                $is_correct = ($answer_text == $question['correct_answer']);
                $marks_obtained = $is_correct ? $question['marks'] : 0;
            } else if ($question['question_type'] == 'descriptive') {
                // For descriptive questions, mark as pending for manual grading
                $marks_obtained = 0; // Will be updated by faculty later
            }
            
            // Save the answer
            $stmt = $conn->prepare("INSERT INTO Answer (attempt_id, question_id, answer_text, is_correct, marks_obtained) 
                                   VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iisid", $attempt_id, $question_id, $answer_text, $is_correct, $marks_obtained);
            $stmt->execute();
            
            $total_score += $marks_obtained;
        }
        
        // Update the attempt as completed
        $end_time = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("UPDATE Exam_Attempt SET status = 'completed', end_time = ?, total_score = ? WHERE attempt_id = ?");
        $stmt->bind_param("sdi", $end_time, $total_score, $attempt_id);
        
        if ($stmt->execute()) {
            $success = "Exam submitted successfully!";
            // Redirect to results page
            header("Location: view_result.php?attempt_id=".$attempt_id);
            exit();
        } else {
            $error = "Failed to submit exam: " . $stmt->error;
        }
    }
}

$base_path = '../';
include '../includes/header.php';
?>

<div class="container mt-4">
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
            <div class="mt-3">
                <a href="available_exams.php" class="btn btn-primary">Back to Available Exams</a>
            </div>
        </div>
    <?php elseif ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
            <div class="mt-3">
                <a href="available_exams.php" class="btn btn-primary">Back to Available Exams</a>
            </div>
        </div>
    <?php else: ?>
        <div class="card mb-4">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><?php echo htmlspecialchars($exam['title']); ?></h5>
                <div id="timer" class="badge bg-warning text-dark fs-6 p-2"></div>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <p><strong>Subject:</strong> <?php echo htmlspecialchars($exam['subject']); ?></p>
                        <p><strong>Faculty:</strong> <?php echo htmlspecialchars($exam['faculty_name']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Duration:</strong> <?php echo $exam['duration']; ?> minutes</p>
                        <p><strong>Total Marks:</strong> <?php echo $exam['total_marks']; ?></p>
                    </div>
                </div>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> Please read all questions carefully before answering. Once submitted, you cannot change your answers.
                </div>
                
                <form method="post" action="" id="examForm">
                    <input type="hidden" name="attempt_id" value="<?php echo $attempt_id; ?>">
                    
                    <?php foreach ($questions as $index => $question): ?>
                        <div class="card mb-4">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">Question <?php echo $index + 1; ?> (<?php echo $question['marks']; ?> marks)</h5>
                            </div>
                            <div class="card-body">
                                <p class="mb-3"><?php echo htmlspecialchars($question['question_text']); ?></p>
                                
                                <?php if ($question['question_type'] == 'multiple_choice'): ?>
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="answer_<?php echo $question['question_id']; ?>" id="option_a_<?php echo $question['question_id']; ?>" value="A">
                                            <label class="form-check-label" for="option_a_<?php echo $question['question_id']; ?>">
                                                A. <?php echo htmlspecialchars($question['option_a']); ?>
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="answer_<?php echo $question['question_id']; ?>" id="option_b_<?php echo $question['question_id']; ?>" value="B">
                                            <label class="form-check-label" for="option_b_<?php echo $question['question_id']; ?>">
                                                B. <?php echo htmlspecialchars($question['option_b']); ?>
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="answer_<?php echo $question['question_id']; ?>" id="option_c_<?php echo $question['question_id']; ?>" value="C">
                                            <label class="form-check-label" for="option_c_<?php echo $question['question_id']; ?>">
                                                C. <?php echo htmlspecialchars($question['option_c']); ?>
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="answer_<?php echo $question['question_id']; ?>" id="option_d_<?php echo $question['question_id']; ?>" value="D">
                                            <label class="form-check-label" for="option_d_<?php echo $question['question_id']; ?>">
                                                D. <?php echo htmlspecialchars($question['option_d']); ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php elseif ($question['question_type'] == 'true_false'): ?>
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="answer_<?php echo $question['question_id']; ?>" id="true_<?php echo $question['question_id']; ?>" value="True">
                                            <label class="form-check-label" for="true_<?php echo $question['question_id']; ?>">
                                                True
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="answer_<?php echo $question['question_id']; ?>" id="false_<?php echo $question['question_id']; ?>" value="False">
                                            <label class="form-check-label" for="false_<?php echo $question['question_id']; ?>">
                                                False
                                            </label>
                                        </div>
                                    </div>
                                <?php elseif ($question['question_type'] == 'descriptive'): ?>
                                    <div class="mb-3">
                                        <textarea class="form-control" name="answer_<?php echo $question['question_id']; ?>" rows="5" placeholder="Type your answer here..."></textarea>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" name="submit_exam" class="btn btn-primary btn-lg" onclick="return confirm('Are you sure you want to submit your exam? You cannot change your answers after submission.')">
                            <i class="fas fa-paper-plane me-2"></i> Submit Exam
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// Exam timer
document.addEventListener('DOMContentLoaded', function() {
    const durationMinutes = <?php echo isset($exam) ? $exam['duration'] : 0; ?>;
    if (durationMinutes > 0) {
        const durationSeconds = durationMinutes * 60;
        const timerDisplay = document.getElementById('timer');
        
        let timeLeft = durationSeconds;
        
        // Update timer every second
        const timerInterval = setInterval(function() {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            
            timerDisplay.textContent = `Time Left: ${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
            
            if (timeLeft <= 300) { // 5 minutes or less
                timerDisplay.classList.remove('bg-warning', 'text-dark');
                timerDisplay.classList.add('bg-danger', 'text-white');
            }
            
            if (timeLeft <= 0) {
                clearInterval(timerInterval);
                alert('Time is up! Your exam will be submitted automatically.');
                document.getElementById('examForm').submit();
            }
            
            timeLeft--;
        }, 1000);
        
        // Prevent page refresh or navigation
        window.addEventListener('beforeunload', function(e) {
            e.preventDefault();
            e.returnValue = 'You are in the middle of an exam. Are you sure you want to leave?';
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>
