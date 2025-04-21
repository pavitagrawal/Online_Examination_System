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

// Get exam ID from query
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}
$exam_id = (int)$_GET['id'];

// Fetch exam details
$stmt = $conn->prepare("SELECT * FROM Exam WHERE exam_id = ? AND created_by = ?");
$stmt->bind_param("ii", $exam_id, $faculty_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    $error = "Exam not found or you do not have permission to edit this exam.";
} else {
    $exam = $result->fetch_assoc();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_exam'])) {
    $title = sanitize_input($_POST['title']);
    $subject = sanitize_input($_POST['subject']);
    $description = sanitize_input($_POST['description']);
    $total_marks = (int)$_POST['total_marks'];
    $duration = (int)$_POST['duration'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];

    // Basic validation
    if (empty($title) || empty($subject) || empty($total_marks) || empty($duration) || empty($start_time) || empty($end_time)) {
        $error = "All fields are required.";
    } else {
        $stmt = $conn->prepare("UPDATE Exam SET title=?, subject=?, description=?, total_marks=?, duration=?, start_time=?, end_time=? WHERE exam_id=? AND created_by=?");
        $stmt->bind_param("sssisssii", $title, $subject, $description, $total_marks, $duration, $start_time, $end_time, $exam_id, $faculty_id);
        if ($stmt->execute()) {
            $success = "Exam updated successfully.";
            // Refresh exam data
            $stmt = $conn->prepare("SELECT * FROM Exam WHERE exam_id = ? AND created_by = ?");
            $stmt->bind_param("ii", $exam_id, $faculty_id);
            $stmt->execute();
            $exam = $stmt->get_result()->fetch_assoc();
        } else {
            $error = "Failed to update exam: " . $stmt->error;
        }
    }
}

$base_path = '../';
include '../includes/header.php';
?>

<div class="container mt-4">
    <h2>Edit Exam</h2>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php elseif ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <?php if (!empty($exam)): ?>
    <form method="post" action="">
        <div class="mb-3">
            <label class="form-label">Title</label>
            <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($exam['title']); ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Subject</label>
            <input type="text" name="subject" class="form-control" value="<?php echo htmlspecialchars($exam['subject']); ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control"><?php echo htmlspecialchars($exam['description']); ?></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">Total Marks</label>
            <input type="number" name="total_marks" class="form-control" value="<?php echo $exam['total_marks']; ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Duration (minutes)</label>
            <input type="number" name="duration" class="form-control" value="<?php echo $exam['duration']; ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Start Time</label>
            <input type="datetime-local" name="start_time" class="form-control" value="<?php echo date('Y-m-d\TH:i', strtotime($exam['start_time'])); ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">End Time</label>
            <input type="datetime-local" name="end_time" class="form-control" value="<?php echo date('Y-m-d\TH:i', strtotime($exam['end_time'])); ?>" required>
        </div>
        <button type="submit" name="update_exam" class="btn btn-primary">Update Exam</button>
        <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
    </form>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
