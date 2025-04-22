<?php
session_start();
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Require admin login
require_user_type('admin');

$admin_id = $_SESSION['user_id'];
$error = "";
$success = "";

// Handle log deletion if requested
if (isset($_GET['clear_logs']) && $_GET['clear_logs'] == 'all') {
    // Confirm with a token to prevent CSRF
    if (isset($_GET['token']) && $_GET['token'] === $_SESSION['csrf_token']) {
        $stmt = $conn->prepare("DELETE FROM System_Logs");
        
        if ($stmt->execute()) {
            $success = "All system logs have been cleared successfully";
            log_activity($admin_id, 'admin', 'Cleared all system logs');
        } else {
            $error = "Failed to clear logs: " . $stmt->error;
        }
    } else {
        $error = "Invalid security token";
    }
}

// Handle date range filtering
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$user_type_filter = isset($_GET['user_type']) ? $_GET['user_type'] : 'all';
$activity_filter = isset($_GET['activity']) ? $_GET['activity'] : '';

// Generate CSRF token for form security
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Prepare query with filters
$query = "SELECT l.*, 
          CASE 
            WHEN l.user_type = 'admin' THEN (SELECT name FROM Admin WHERE admin_id = l.user_id)
            WHEN l.user_type = 'faculty' THEN (SELECT name FROM Faculty WHERE faculty_id = l.user_id)
            WHEN l.user_type = 'student' THEN (SELECT name FROM Student WHERE student_id = l.user_id)
            ELSE 'Unknown'
          END as user_name
          FROM System_Logs l 
          WHERE created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)";

// Add user type filter if specified
if ($user_type_filter != 'all') {
    $query .= " AND user_type = ?";
}

// Add activity filter if specified
if (!empty($activity_filter)) {
    $query .= " AND activity LIKE ?";
}

$query .= " ORDER BY created_at DESC";

// Prepare and execute the query with appropriate parameters
$stmt = $conn->prepare($query);

if ($user_type_filter != 'all' && !empty($activity_filter)) {
    $activity_param = '%' . $activity_filter . '%';
    $stmt->bind_param("ssss", $start_date, $end_date, $user_type_filter, $activity_param);
} elseif ($user_type_filter != 'all') {
    $stmt->bind_param("sss", $start_date, $end_date, $user_type_filter);
} elseif (!empty($activity_filter)) {
    $activity_param = '%' . $activity_filter . '%';
    $stmt->bind_param("sss", $start_date, $end_date, $activity_param);
} else {
    $stmt->bind_param("ss", $start_date, $end_date);
}

$stmt->execute();
$result = $stmt->get_result();

$logs = [];
while ($row = $result->fetch_assoc()) {
    $logs[] = $row;
}

// Get distinct user types for filter dropdown
$user_types = [];
$type_result = $conn->query("SELECT DISTINCT user_type FROM System_Logs ORDER BY user_type");
if ($type_result->num_rows > 0) {
    while ($row = $type_result->fetch_assoc()) {
        $user_types[] = $row['user_type'];
    }
}

// Get common activities for filter suggestions
$activities = [];
$activity_result = $conn->query("SELECT activity, COUNT(*) as count FROM System_Logs GROUP BY activity ORDER BY count DESC LIMIT 10");
if ($activity_result->num_rows > 0) {
    while ($row = $activity_result->fetch_assoc()) {
        $activities[] = $row['activity'];
    }
}

$base_path = '../';
include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>System Logs</h1>
    <a href="dashboard.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Filter Form -->
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-filter"></i> Filter Logs</h5>
    </div>
    <div class="card-body">
        <form method="get" action="" class="row g-3">
            <div class="col-md-3">
                <label for="start_date" class="form-label">Start Date</label>
                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
            </div>
            <div class="col-md-3">
                <label for="end_date" class="form-label">End Date</label>
                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
            </div>
            <div class="col-md-3">
                <label for="user_type" class="form-label">User Type</label>
                <select class="form-select" id="user_type" name="user_type">
                    <option value="all" <?php echo $user_type_filter == 'all' ? 'selected' : ''; ?>>All Types</option>
                    <?php foreach ($user_types as $type): ?>
                        <option value="<?php echo $type; ?>" <?php echo $user_type_filter == $type ? 'selected' : ''; ?>>
                            <?php echo ucfirst($type); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="activity" class="form-label">Activity</label>
                <input type="text" class="form-control" id="activity" name="activity" value="<?php echo htmlspecialchars($activity_filter); ?>" list="activity-suggestions">
                <datalist id="activity-suggestions">
                    <?php foreach ($activities as $activity): ?>
                        <option value="<?php echo htmlspecialchars($activity); ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <a href="system_logs.php" class="btn btn-secondary">Reset</a>
                <a href="system_logs.php?clear_logs=all&token=<?php echo $_SESSION['csrf_token']; ?>" class="btn btn-danger float-end" onclick="return confirm('Are you sure you want to clear ALL system logs? This action cannot be undone.')">
                    <i class="fas fa-trash"></i> Clear All Logs
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Logs Table -->
<div class="card">
    <div class="card-header bg-dark text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-history"></i> Activity Logs</h5>
            <span class="badge bg-primary"><?php echo count($logs); ?> Records Found</span>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($logs)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i> No logs found for the selected criteria.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="logsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Timestamp</th>
                            <th>User</th>
                            <th>Type</th>
                            <th>Activity</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
                    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.7.0/jspdf.plugin.autotable.min.js"></script>

                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo $log['log_id']; ?></td>
                                <td><?php echo date('M d, Y h:i:s A', strtotime($log['created_at'])); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($log['user_name']); ?>
                                    <small class="text-muted d-block">(ID: <?php echo $log['user_id']; ?>)</small>
                                </td>
                                <td>
                                    <?php if ($log['user_type'] == 'admin'): ?>
                                        <span class="badge bg-danger">Admin</span>
                                    <?php elseif ($log['user_type'] == 'faculty'): ?>
                                        <span class="badge bg-success">Faculty</span>
                                    <?php elseif ($log['user_type'] == 'student'): ?>
                                        <span class="badge bg-info">Student</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Unknown</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($log['activity']); ?></td>
                                <td><?php echo $log['ip_address']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Export Buttons -->
            <div class="mt-3">
                <button class="btn btn-success" onclick="exportTableToCSV('system_logs.csv')">
                    <i class="fas fa-file-csv"></i> Export to CSV
                </button>
                <button class="btn btn-danger mb-3" id="exportPdfBtn">
                    <i class="fas fa-file-pdf"></i> Export Logs to PDF
                </button>

            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Log Statistics -->
<?php if (!empty($logs)): ?>
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-pie"></i> User Type Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="userTypeChart" height="300"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-line"></i> Activity Timeline</h5>
                </div>
                <div class="card-body">
                    <canvas id="activityTimelineChart" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Initialize DataTable for better table functionality
document.addEventListener('DOMContentLoaded', function() {
    if (typeof $.fn.DataTable !== 'undefined') {
        $('#logsTable').DataTable({
            "pageLength": 25,
            "order": [[0, "desc"]], // Sort by ID by default (newest first)
            "dom": 'Bfrtip',
            "buttons": [
                'copy', 'excel', 'pdf'
            ]
        });
    }
    
    <?php if (!empty($logs)): ?>
    // User Type Distribution Chart
    const userTypeData = {
        admin: 0,
        faculty: 0,
        student: 0,
        other: 0
    };
    
    <?php
    $user_type_counts = [];
    foreach ($logs as $log) {
        if (!isset($user_type_counts[$log['user_type']])) {
            $user_type_counts[$log['user_type']] = 0;
        }
        $user_type_counts[$log['user_type']]++;
    }
    
    foreach ($user_type_counts as $type => $count) {
        echo "userTypeData['" . ($type ?: 'other') . "'] = " . $count . ";\n";
    }
    ?>
    
    const userTypeCtx = document.getElementById('userTypeChart').getContext('2d');
    new Chart(userTypeCtx, {
        type: 'pie',
        data: {
            labels: ['Admin', 'Faculty', 'Student', 'Other'],
            datasets: [{
                data: [
                    userTypeData.admin || 0,
                    userTypeData.faculty || 0,
                    userTypeData.student || 0,
                    userTypeData.other || 0
                ],
                backgroundColor: [
                    'rgba(255, 99, 132, 0.7)',
                    'rgba(75, 192, 192, 0.7)',
                    'rgba(54, 162, 235, 0.7)',
                    'rgba(153, 102, 255, 0.7)'
                ],
                borderColor: [
                    'rgb(255, 99, 132)',
                    'rgb(75, 192, 192)',
                    'rgb(54, 162, 235)',
                    'rgb(153, 102, 255)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
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
    
    // Activity Timeline Chart
    const timelineData = {};
    const dates = [];
    
    <?php
    $timeline_data = [];
    foreach ($logs as $log) {
        $date = date('Y-m-d', strtotime($log['created_at']));
        if (!isset($timeline_data[$date])) {
            $timeline_data[$date] = 0;
        }
        $timeline_data[$date]++;
    }
    
    // Sort by date
    ksort($timeline_data);
    
    // Output the data for JavaScript
    foreach ($timeline_data as $date => $count) {
        echo "timelineData['" . $date . "'] = " . $count . ";\n";
        echo "dates.push('" . $date . "');\n";
    }
    ?>
    
    const timelineCtx = document.getElementById('activityTimelineChart').getContext('2d');
    new Chart(timelineCtx, {
        type: 'line',
        data: {
            labels: dates,
            datasets: [{
                label: 'Number of Activities',
                data: dates.map(date => timelineData[date] || 0),
                fill: false,
                borderColor: 'rgb(75, 192, 192)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Number of Activities'
                    },
                    ticks: {
                        stepSize: 1
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Date'
                    }
                }
            }
        }
    });
    <?php endif; ?>
});

// Function to export table to CSV
function exportTableToCSV(filename) {
    const table = document.getElementById('logsTable');
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = [], cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length; j++) {
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
    downloadCSV(csv.join('\n'), filename);
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

// Function to export table to PDF (requires jsPDF library)
function exportTableToPDF() {
    // This is a placeholder - in a real implementation, you would use a library like jsPDF
    alert('PDF export functionality requires additional libraries like jsPDF. Please implement this based on your project requirements.');
}
</script>
<script>
document.getElementById('exportPdfBtn').addEventListener('click', function () {
    // Reference to table
    var doc = new jspdf.jsPDF('l', 'pt', 'a4');
    doc.setFontSize(14);
    doc.text("System Activity Logs", 40, 40);

    // Prepare the table for export
    doc.autoTable({
        html: '#logsTable', // Use your table's ID
        startY: 60,
        styles: { fontSize: 8 },
        headStyles: { fillColor: [41, 128, 185] },
        theme: 'striped',
        didDrawPage: function (data) {
            doc.setFontSize(10);
            doc.text('Exported: ' + new Date().toLocaleString(), 40, doc.internal.pageSize.height - 10);
        }
    });

    doc.save('system_logs_' + new Date().toISOString().slice(0,10) + '.pdf');
});
</script>

<?php include '../includes/footer.php'; ?>
