<?php
session_start();
require_once 'config/db_connect.php';
require_once 'includes/functions.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: " . $_SESSION['user_type'] . "/dashboard.php");
    exit();
}

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = sanitize_input($_POST['name']);
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $contact = sanitize_input($_POST['contact']);
    $reg_no = isset($_POST['reg_no']) ? sanitize_input($_POST['reg_no']) : '';
    $college_id = isset($_POST['college_id']) ? (int)$_POST['college_id'] : 0;
    
    // Determine user type from email
    $user_type = get_role_from_email($email);
    
    // Validate password match
    if ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } 
    // Check if email already exists
    else {
        $stmt = $conn->prepare("SELECT 'admin' as user_type, admin_id as id, name, email, contact 
                        FROM Admin WHERE email = ? 
                        UNION 
                        SELECT 'faculty' as user_type, faculty_id as id, name, email, contact 
                        FROM Faculty WHERE email = ? 
                        UNION 
                        SELECT 'student' as user_type, student_id as id, name, email, contact 
                        FROM Student WHERE email = ?");
        $stmt->bind_param("sss", $email, $email, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "Email already exists";
        } else {
            // Hash the password
            $hashed_password = hash_password($password);
            
            // Insert based on user type
            if ($user_type == 'admin') {
                $stmt = $conn->prepare("INSERT INTO Admin (name, email, password, contact) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $name, $email, $hashed_password, $contact);
            } elseif ($user_type == 'faculty') {
                $stmt = $conn->prepare("INSERT INTO Faculty (name, email, password, contact, college_id) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssi", $name, $email, $hashed_password, $contact, $college_id);
            } elseif ($user_type == 'student') {
                if (empty($reg_no)) {
                    $error = "Registration number is required for students";
                } else {
                    $stmt = $conn->prepare("INSERT INTO Student (reg_no, name, email, password, contact, college_id) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssssi", $reg_no, $name, $email, $hashed_password, $contact, $college_id);
                }
            } else {
                $error = "Invalid email domain for registration";
            }
            
            if (empty($error)) {
                if ($stmt->execute()) {
                    $success = "Registration successful! You can now login.";
                } else {
                    $error = "Registration failed: " . $stmt->error;
                }
            }
        }
    }
}

// Get colleges for dropdown
$colleges = [];
$result = $conn->query("SELECT college_id, name FROM College ORDER BY name");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $colleges[] = $row;
    }
}

include 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4><i class="fas fa-user-plus"></i> Register</h4>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                    <p>Click <a href="login.php">here</a> to login.</p>
                <?php else: ?>
                    <form method="post" action="" id="registrationForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" required 
                                       onchange="checkEmailDomain()">
                                <small class="form-text text-muted">
                                    Your email domain will determine your role in the system.
                                </small>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="contact" class="form-label">Contact Number</label>
                                <input type="text" class="form-control" id="contact" name="contact" required>
                            </div>
                            <div class="col-md-6 mb-3" id="college_div">
                                <label for="college_id" class="form-label">College</label>
                                <select class="form-select" id="college_id" name="college_id">
                                    <option value="">Select College</option>
                                    <?php foreach ($colleges as $college): ?>
                                        <option value="<?php echo $college['college_id']; ?>">
                                            <?php echo htmlspecialchars($college['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3" id="reg_no_div" style="display: none;">
                            <label for="reg_no" class="form-label">Registration Number</label>
                            <input type="text" class="form-control" id="reg_no" name="reg_no">
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Register</button>
                    </form>
                    
                    <div class="mt-3">
                        <p>Already have an account? <a href="login.php">Login here</a></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function checkEmailDomain() {
    const email = document.getElementById('email').value;
    const regNoDiv = document.getElementById('reg_no_div');
    const collegeDiv = document.getElementById('college_div');
    
    if (email.includes('@')) {
        const domain = email.split('@')[1];
        
        // Simple domain check - in a real app, you'd use the server-side function
        if (domain.includes('student') || domain.endsWith('.edu')) {
            regNoDiv.style.display = 'block';
            collegeDiv.style.display = 'block';
            document.getElementById('reg_no').required = true;
            document.getElementById('college_id').required = true;
        } else if (domain.includes('faculty') || domain.includes('teacher') || domain.includes('prof')) {
            regNoDiv.style.display = 'none';
            collegeDiv.style.display = 'block';
            document.getElementById('reg_no').required = false;
            document.getElementById('college_id').required = true;
        } else if (domain.includes('admin')) {
            regNoDiv.style.display = 'none';
            collegeDiv.style.display = 'none';
            document.getElementById('reg_no').required = false;
            document.getElementById('college_id').required = false;
        }
    }
}
</script>

<?php include 'includes/footer.php'; ?>
