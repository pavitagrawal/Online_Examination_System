<?php
require_once '../includes/auth.php';
require_user_type('faculty');

$success = $error = '';
$faculty_id = $_SESSION['user_id'];

// Check if exam_id is provided
if (!isset($_GET['exam_id'])) {
    header("Location: manage_exams.php?error=No exam selected");
    exit();
}

$exam_id = (int)$_GET['exam_id'];

// Verify that this exam belongs to the faculty
$stmt = $conn->prepare("SELECT * FROM Exam WHERE exam_id = ? AND created_by = ?");
$stmt->bind_param("ii", $exam_id, $faculty_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: manage_exams.php?error=Unauthorized access to exam");
    exit();
}

$exam = $result->fetch_assoc();

// Handle question addition
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_question'])) {
    $question_text = sanitize_input($_POST['question_text']);
    $question_type = sanitize_input($_POST['question_type']);
    $marks = (float)$_POST['marks'];
    
    // For multiple-choice questions
    if ($question_type == 'multiple_choice') {
        $option_a = sanitize_input($_POST['option_a']);
        $option_b = sanitize_input($_POST['option_b']);
        $option_c = sanitize_input($_POST['option_c']);
        $option_d = sanitize_input($_POST['option_d']);
        $correct_answer = sanitize_input($_POST['correct_answer']);
        
        $stmt = $conn->prepare("CALL add_question(?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('issdsssss', $exam_id, $question_text, $question_type, $marks, 
                          $option_a, $option_b, $option_c, $option_d, $correct_answer);
    } 
    // For true/false questions
    else if ($question_type == 'true_false') {
        $correct_answer = sanitize_input($_POST['correct_answer']);
        
        $stmt = $conn->prepare("CALL add_question(?, ?, ?, ?, NULL, NULL, NULL, NULL, ?)");
        $stmt->bind_param('issds', $exam_id, $question_text, $question_type, $marks, $correct_answer);
    }
    // For descriptive questions
    else if ($question_type == 'descriptive') {
        $stmt = $conn->prepare("CALL add_question(?, ?, ?, ?, NULL, NULL, NULL, NULL, NULL)");
        $stmt->bind_param('issd', $exam_id, $question_text, $question_type, $marks);
    }
    
    if ($stmt->execute()) {
        $success = "Question added successfully";
        log_activity($faculty_id, 'faculty', 'Added question to exam ID: ' . $exam_id);
    } else {
        $error = "Failed to add question: " . $stmt->error;
    }
}

// Handle question deletion
if (isset($_GET['delete_question'])) {
    $question_id = (int)$_GET['delete_question'];
    
    // Verify that this question belongs to the exam
    $stmt = $conn->prepare("SELECT * FROM Question WHERE question_id = ? AND exam_id = ?");
    $stmt->bind_param("ii", $question_id, $exam_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $stmt = $conn->prepare("DELETE FROM Question WHERE question_id = ?");
        $stmt->bind_param("i", $question_id);
        
        if ($stmt->execute()) {
            $success = "Question deleted successfully";
            log_activity($faculty_id, 'faculty', 'Deleted question ID: ' . $question_id);
            header("Location: add_questions.php?exam_id=$exam_id&success=" . urlencode($success));
            exit();
        } else {
            $error = "Failed to delete question: " . $stmt->error;
        }
    } else {
        $error = "Question not found or unauthorized access";
    }
}

// Get all questions for this exam
$questions = [];
$stmt = $conn->prepare("SELECT * FROM Question WHERE exam_id = ? ORDER BY question_id");
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $questions[] = $row;
}

$base_path = '../';
include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Add Questions to Exam</h1>
    <a href="manage_exams.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Exams
    </a>
</div>

<div class="alert alert-info">
    <h5>Exam Details:</h5>
    <p><strong>Title:</strong> <?php echo htmlspecialchars($exam['title']); ?></p>
    <p><strong>Subject:</strong> <?php echo htmlspecialchars($exam['subject']); ?></p>
    <p><strong>Total Marks:</strong> <?php echo $exam['total_marks']; ?></p>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-5">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Add New Question</h5>
            </div>
            <div class="card-body">
                <form method="post" action="">
                    <div class="mb-3">
                        <label for="question_text" class="form-label">Question Text</label>
                        <textarea class="form-control" id="question_text" name="question_text" rows="3" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="question_type" class="form-label">Question Type</label>
                        <select class="form-select" id="question_type" name="question_type" required onchange="toggleOptions()">
                            <option value="multiple_choice">Multiple Choice</option>
                            <option value="true_false">True/False</option>
                            <option value="descriptive">Descriptive</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="marks" class="form-label">Marks</label>
                        <input type="number" class="form-control" id="marks" name="marks" min="0.5" step="0.5" required>
                    </div>
                    
                    <div id="options_div">
                        <div class="mb-3">
                            <label for="option_a" class="form-label">Option A</label>
                            <input type="text" class="form-control" id="option_a" name="option_a">
                        </div>
                        
                        <div class="mb-3">
                            <label for="option_b" class="form-label">Option B</label>
                            <input type="text" class="form-control" id="option_b" name="option_b">
                        </div>
                        
                        <div class="mb-3">
                            <label for="option_c" class="form-label">Option C</label>
                            <input type="text" class="form-control" id="option_c" name="option_c">
                        </div>
                        
                        <div class="mb-3">
                            <label for="option_d" class="form-label">Option D</label>
                            <input type="text" class="form-control" id="option_d" name="option_d">
                        </div>
                        
                        <div class="mb-3">
                            <label for="correct_answer" class="form-label">Correct Answer</label>
                            <select class="form-select" id="correct_answer" name="correct_answer">
                                <option value="A">Option A</option>
                                <option value="B">Option B</option>
                                <option value="C">Option C</option>
                                <option value="D">Option D</option>
                            </select>
                        </div>
                    </div>
                    
                    <div id="true_false_div" style="display: none;">
                        <div class="mb-3">
                            <label for="tf_correct_answer" class="form-label">Correct Answer</label>
                            <select class="form-select" id="tf_correct_answer" name="tf_correct_answer">
                                <option value="True">True</option>
                                <option value="False">False</option>
                            </select>
                        </div>
                    </div>
                    
                    <button type="submit" name="add_question" class="btn btn-primary">Add Question</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-7">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">Questions (<?php echo count($questions); ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (empty($questions)): ?>
                    <div class="alert alert-warning">No questions added yet.</div>
                <?php else: ?>
                    <div class="accordion" id="questionsAccordion">
                        <?php foreach ($questions as $index => $question): ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="heading<?php echo $question['question_id']; ?>">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" 
                                            data-bs-target="#collapse<?php echo $question['question_id']; ?>">
                                        <span class="me-2"><?php echo $index + 1; ?>.</span>
                                        <?php echo htmlspecialchars(substr($question['question_text'], 0, 100) . (strlen($question['question_text']) > 100 ? '...' : '')); ?>
                                        <span class="ms-auto badge bg-primary"><?php echo $question['marks']; ?> marks</span>
                                    </button>
                                </h2>
                                <div id="collapse<?php echo $question['question_id']; ?>" class="accordion-collapse collapse" 
                                     aria-labelledby="heading<?php echo $question['question_id']; ?>" data-bs-parent="#questionsAccordion">
                                    <div class="accordion-body">
                                        <p><strong>Question:</strong> <?php echo htmlspecialchars($question['question_text']); ?></p>
                                        <p><strong>Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $question['question_type'])); ?></p>
                                        
                                        <?php if ($question['question_type'] == 'multiple_choice'): ?>
                                            <p><strong>Options:</strong></p>
                                            <ul>
                                                <li>A: <?php echo htmlspecialchars($question['option_a']); ?></li>
                                                <li>B: <?php echo htmlspecialchars($question['option_b']); ?></li>
                                                <li>C: <?php echo htmlspecialchars($question['option_c']); ?></li>
                                                <li>D: <?php echo htmlspecialchars($question['option_d']); ?></li>
                                            </ul>
                                            <p><strong>Correct Answer:</strong> <?php echo htmlspecialchars($question['correct_answer']); ?></p>
                                        <?php elseif ($question['question_type'] == 'true_false'): ?>
                                            <p><strong>Correct Answer:</strong> <?php echo htmlspecialchars($question['correct_answer']); ?></p>
                                        <?php endif; ?>
                                        
                                        <div class="mt-3">
                                            <a href="add_questions.php?exam_id=<?php echo $exam_id; ?>&delete_question=<?php echo $question['question_id']; ?>" 
                                               class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this question?')">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function toggleOptions() {
    const questionType = document.getElementById('question_type').value;
    const optionsDiv = document.getElementById('options_div');
    const trueFalseDiv = document.getElementById('true_false_div');
    
    if (questionType === 'multiple_choice') {
        optionsDiv.style.display = 'block';
        trueFalseDiv.style.display = 'none';
        document.getElementById('correct_answer').name = 'correct_answer';
        document.getElementById('tf_correct_answer').name = 'tf_correct_answer';
    } else if (questionType === 'true_false') {
        optionsDiv.style.display = 'none';
        trueFalseDiv.style.display = 'block';
        document.getElementById('correct_answer').name = 'unused';
        document.getElementById('tf_correct_answer').name = 'correct_answer';
    } else {
        optionsDiv.style.display = 'none';
        trueFalseDiv.style.display = 'none';
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', toggleOptions);
</script>

<?php include '../includes/footer.php'; ?>
