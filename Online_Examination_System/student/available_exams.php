<?php
session_start();
require_once '../config/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

date_default_timezone_set('Asia/Kolkata');

// Require student login
require_user_type('student');

$student_id = $_SESSION['user_id'];

// Fetch student college id
$stmt = $conn->prepare("SELECT college_id FROM Student WHERE student_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$college_id = $student['college_id'];

// Fetch available exams for this student
$query = "
    SELECT e.*, f.name as faculty_name
    FROM Exam e
    JOIN Faculty f ON e.created_by = f.faculty_id
    JOIN Exam_Availability ea ON e.exam_id = ea.exam_id
    WHERE NOW() BETWEEN ea.available_from AND ea.available_until
    AND (ea.student_group = 'all' OR ea.student_group = CONCAT('college_', ?))
    AND NOT EXISTS (
        SELECT 1 FROM Exam_Attempt att 
        WHERE att.exam_id = e.exam_id 
        AND att.student_id = ? 
        AND att.status = 'completed'
    )
    ORDER BY e.start_time ASC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $college_id, $student_id);
$stmt->execute();
$result = $stmt->get_result();

$exams = [];
while ($row = $result->fetch_assoc()) {
    $exams[] = $row;
}

// Get completed exams
$completed_query = "
    SELECT e.*, ea.start_time as attempt_start, ea.end_time as attempt_end, ea.total_score, ea.status,
           (ea.total_score / e.total_marks * 100) as percentage
    FROM Exam e
    JOIN Exam_Attempt ea ON e.exam_id = ea.exam_id
    WHERE ea.student_id = ? AND ea.status = 'completed'
    ORDER BY ea.end_time DESC
";

$stmt = $conn->prepare($completed_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

$completed_exams = [];
while ($row = $result->fetch_assoc()) {
    $completed_exams[] = $row;
}

$base_path = '../';
include '../includes/header.php';
?>

<div class="container mt-4">
    <h1 class="mb-4">Available Exams</h1>
    
    <ul class="nav nav-tabs mb-4" id="examTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="available-tab" data-bs-toggle="tab" data-bs-target="#available" type="button" role="tab" aria-controls="available" aria-selected="true">
                Available Exams
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="completed-tab" data-bs-toggle="tab" data-bs-target="#completed" type="button" role="tab" aria-controls="completed" aria-selected="false">
                Completed Exams
            </button>
        </li>
    </ul>
    
    <div class="tab-content" id="examTabsContent">
        <div class="tab-pane fade show active" id="available" role="tabpanel" aria-labelledby="available-tab">
            <?php if (empty($exams)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> No exams are currently available for you.
                </div>
            <?php else: ?>
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                    <?php foreach ($exams as $exam): ?>
                        <div class="col">
                            <div class="card h-100 shadow-sm">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="card-title mb-0"><?php echo htmlspecialchars($exam['title']); ?></h5>
                                </div>
                                <div class="card-body">
                                    <h6 class="card-subtitle mb-2 text-muted"><?php echo htmlspecialchars($exam['subject']); ?></h6>
                                    <p class="card-text"><?php echo htmlspecialchars($exam['description'] ?? 'No description available'); ?></p>
                                    
                                    <ul class="list-group list-group-flush mb-3">
                                        <li class="list-group-item d-flex justify-content-between">
                                            <span>Duration:</span>
                                            <span class="fw-bold"><?php echo $exam['duration']; ?> minutes</span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between">
                                            <span>Total Marks:</span>
                                            <span class="fw-bold"><?php echo $exam['total_marks']; ?></span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between">
                                            <span>Faculty:</span>
                                            <span class="fw-bold"><?php echo htmlspecialchars($exam['faculty_name']); ?></span>
                                        </li>
                                    </ul>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <small class="text-muted">
                                                Start: <?php echo date('M d, Y h:i A', strtotime($exam['start_time'])); ?>
                                            </small><br>
                                            <small class="text-muted">
                                                End: <?php echo date('M d, Y h:i A', strtotime($exam['end_time'])); ?>
                                            </small>
                                        </div>
                                        
                                        <?php
                                        $now = new DateTime();
                                        $start = new DateTime($exam['start_time']);
                                        $end = new DateTime($exam['end_time']);
                                        if ($now >= $start && $now <= $end):
                                        ?>
                                            <a href="take_exam.php?exam_id=<?php echo $exam['exam_id']; ?>" class="btn btn-success">
                                                <i class="fas fa-pen-alt me-2"></i>Take Exam
                                            </a>
                                        <?php elseif ($now < $start): ?>
                                            <button class="btn btn-secondary" disabled>
                                                <i class="fas fa-clock me-2"></i>Not Started
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-danger" disabled>
                                                <i class="fas fa-times-circle me-2"></i>Expired
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="tab-pane fade" id="completed" role="tabpanel" aria-labelledby="completed-tab">
            <?php if (empty($completed_exams)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> You haven't completed any exams yet.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Exam Title</th>
                                <th>Subject</th>
                                <th>Attempt Date</th>
                                <th>Score</th>
                                <th>Percentage</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($completed_exams as $exam): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($exam['title']); ?></td>
                                    <td><?php echo htmlspecialchars($exam['subject']); ?></td>
                                    <td><?php echo date('M d, Y h:i A', strtotime($exam['attempt_start'])); ?></td>
                                    <td><?php echo $exam['total_score']; ?> / <?php echo $exam['total_marks']; ?></td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar <?php echo $exam['percentage'] >= 60 ? 'bg-success' : 'bg-danger'; ?>" 
                                                 role="progressbar" 
                                                 style="width: <?php echo $exam['percentage']; ?>%;" 
                                                 aria-valuenow="<?php echo $exam['percentage']; ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="100">
                                                <?php echo number_format($exam['percentage'], 1); ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($exam['status'] == 'completed'): ?>
                                            <span class="badge bg-success">Completed</span>
                                        <?php elseif ($exam['status'] == 'timed_out'): ?>
                                            <span class="badge bg-warning">Timed Out</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><?php echo ucfirst($exam['status']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="view_result.php?attempt_id=<?php echo $exam['attempt_id']; ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i> View Result
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

<script>
// Enable Bootstrap tabs
document.addEventListener('DOMContentLoaded', function() {
    var triggerTabList = [].slice.call(document.querySelectorAll('#examTabs button'))
    triggerTabList.forEach(function(triggerEl) {
        var tabTrigger = new bootstrap.Tab(triggerEl)
        triggerEl.addEventListener('click', function(event) {
            event.preventDefault()
            tabTrigger.show()
        })
    })
});
</script>

<?php include '../includes/footer.php'; ?>
