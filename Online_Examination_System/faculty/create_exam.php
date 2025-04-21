<?php
require_once '../includes/auth.php';
require_user_type('faculty');

$success = $error = '';
$faculty_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = sanitize_input($_POST['title']);
    $subject = sanitize_input($_POST['subject']);
    $description = sanitize_input($_POST['description']);
    $total_marks = (float)$_POST['total_marks'];
    $duration = (int)$_POST['duration'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    
    // Validate inputs
    if (empty($title) || empty($subject) || empty($start_time) || empty($end_time)) {
        $error = "All fields are required";
    } elseif ($total_marks <= 0) {
        $error = "Total marks must be greater than zero";
    } elseif ($duration <= 0) {
        $error = "Duration must be greater than zero";
    } elseif (strtotime($start_time) >= strtotime($end_time)) {
        $error = "End time must be after start time";
    } else {
        // Insert exam
        $stmt = $conn->prepare("INSERT INTO Exam (title, subject, description, total_marks, duration, start_time, end_time, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssdissi", $title, $subject, $description, $total_marks, $duration, $start_time, $end_time, $faculty_id);
        
        if ($stmt->execute()) {
            $exam_id = $stmt->insert_id;
            
            // Add exam availability for all students
            $stmt = $conn->prepare("INSERT INTO Exam_Availability (exam_id, student_group, available_from, available_until) VALUES (?, 'all', ?, ?)");
            $stmt->bind_param("iss", $exam_id, $start_time, $end_time);
            $stmt->execute();
            
            log_activity($faculty_id, 'faculty', 'Created new exam: ' . $title);
            
            $success = "Exam created successfully. <a href='add_questions.php?exam_id=$exam_id'>Add questions now</a>";
        } else {
            $error = "Failed to create exam: " . $stmt->error;
        }
    }
}

$base_path = '../';
include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Create New Exam</h1>
    <a href="manage_exams.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Exams
    </a>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="post" action="">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="title" class="form-label">Exam Title</label>
                    <input type="text" class="form-control" id="title" name="title" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="subject" class="form-label">Subject</label>
                    <input type="text" class="form-control" id="subject" name="subject" required>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
            </div>
            
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="total_marks" class="form-label">Total Marks</label>
                    <input type="number" class="form-control" id="total_marks" name="total_marks" min="1" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="duration" class="form-label">Duration (minutes)</label>
                    <input type="number" class="form-control" id="duration" name="duration" min="1" required>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="start_time" class="form-label">Start Time</label>
                    <input type="datetime-local" class="form-control" id="start_time" name="start_time" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="end_time" class="form-label">End Time</label>
                    <input type="datetime-local" class="form-control" id="end_time" name="end_time" required>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary">Create Exam</button>
        </form>
    </div>
</div>

<script>
// Set minimum datetime for start_time to current time
document.addEventListener('DOMContentLoaded', function() {
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    
    const startTimeInput = document.getElementById('start_time');
    const endTimeInput = document.getElementById('end_time');
    
    startTimeInput.min = now.toISOString().slice(0, 16);
    
    // Update end_time min when start_time changes
    startTimeInput.addEventListener('change', function() {
        if (startTimeInput.value) {
            endTimeInput.min = startTimeInput.value;
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
