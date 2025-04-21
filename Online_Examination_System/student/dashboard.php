<?php
require_once '../includes/auth.php';
require_user_type('student');

// Get student details
$student_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT s.*, c.name as college_name FROM Student s LEFT JOIN College c ON s.college_id = c.college_id WHERE s.student_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

date_default_timezone_set('Asia/Kolkata');

// Get available exams
$available_exams = [];
$query = "
    SELECT e.*, f.name as faculty_name
    FROM Exam e
    JOIN Faculty f ON e.created_by = f.faculty_id
    JOIN Exam_Availability ea ON e.exam_id = ea.exam_id
    WHERE ea.available_from <= NOW() 
    AND ea.available_until >= NOW()
    AND (
        ea.student_group = 'all' 
        OR ea.student_group = CONCAT('college_', ?)
    )
    AND NOT EXISTS (
        SELECT 1 FROM Exam_Attempt att 
        WHERE att.exam_id = e.exam_id 
        AND att.student_id = ? 
        AND att.status = 'completed'
    )
    ORDER BY e.start_time
    LIMIT 5
";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $student['college_id'], $student_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $available_exams[] = $row;
}

date_default_timezone_set('Asia/Kolkata');

// Get recent exam attempts
$recent_attempts = [];
$stmt = $conn->prepare("
    SELECT ea.*, e.title, e.subject, e.total_marks
    FROM Exam_Attempt ea
    JOIN Exam e ON ea.exam_id = e.exam_id
    WHERE ea.student_id = ?
    ORDER BY ea.start_time DESC
    LIMIT 5
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $recent_attempts[] = $row;
}

$base_path = '../';
include '../includes/header.php';
?>

<h1 class="mb-4">Student Dashboard</h1>

<div class="row">
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Profile Information</h5>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($student['name']); ?></p>
                <p><strong>Registration No:</strong> <?php echo htmlspecialchars($student['reg_no']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($student['email']); ?></p>
                <p><strong>Contact:</strong> <?php echo htmlspecialchars($student['contact']); ?></p>
                <p><strong>College:</strong> <?php echo htmlspecialchars($student['college_name'] ?? 'Not Assigned'); ?></p>
                <a href="profile.php" class="btn btn-sm btn-primary">Edit Profile</a>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Available Exams</h5>
            </div>
            <div class="card-body">
                <?php if (empty($available_exams)): ?>
                    <div class="alert alert-info">No exams available at the moment.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Subject</th>
                                    <th>Faculty</th>
                                    <th>Duration</th>
                                    <th>Marks</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($available_exams as $exam): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($exam['title']); ?></td>
                                        <td><?php echo htmlspecialchars($exam['subject']); ?></td>
                                        <td><?php echo htmlspecialchars($exam['faculty_name']); ?></td>
                                        <td><?php echo $exam['duration']; ?> mins</td>
                                        <td><?php echo $exam['total_marks']; ?></td>
                                        <td>
                                            <?php if (strtotime($exam['start_time']) <= time() && strtotime($exam['end_time']) >= time()): ?>
                                                <a href="take_exam.php?exam_id=<?php echo $exam['exam_id']; ?>" class="btn btn-sm btn-success">
                                                    Take Exam
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-secondary" disabled>
                                                    <?php echo strtotime($exam['start_time']) > time() ? 'Not Started' : 'Ended'; ?>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        <a href="available_exams.php" class="btn btn-outline-primary">View All Available Exams</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">Recent Exam Results</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recent_attempts)): ?>
                    <div class="alert alert-info">You haven't taken any exams yet.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Exam</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Score</th>
                                    <th>Percentage</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_attempts as $attempt): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($attempt['title']); ?></td>
                                        <td><?php echo date('M d, Y h:i A', strtotime($attempt['start_time'])); ?></td>
                                        <td>
                                            <?php if ($attempt['status'] == 'completed'): ?>
                                                <span class="badge bg-success">Completed</span>
                                            <?php elseif ($attempt['status'] == 'in_progress'): ?>
                                                <span class="badge bg-warning">In Progress</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Timed Out</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $attempt['total_score']; ?> / <?php echo $attempt['total_marks']; ?></td>
                                        <td>
                                            <?php 
                                                $percentage = ($attempt['total_score'] / $attempt['total_marks']) * 100;
                                                echo number_format($percentage, 2) . '%';
                                            ?>
                                        </td>
                                        <td>
                                            <a href="view_result.php?attempt_id=<?php echo $attempt['attempt_id']; ?>" class="btn btn-sm btn-info">
                                                View Details
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        <a href="exam_results.php" class="btn btn-outline-info">View All Results</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
