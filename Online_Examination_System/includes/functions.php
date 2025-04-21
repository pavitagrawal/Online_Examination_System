<?php
// Include database connection
require_once __DIR__ . '/../config/db_connect.php';

// Function to sanitize user input
function sanitize_input($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $conn->real_escape_string($data);
}

// Function to hash passwords
function hash_password($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

// Function to verify password
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

// Function to log system activity
function log_activity($user_id, $user_type, $activity) {
    global $conn;
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    $stmt = $conn->prepare("INSERT INTO System_Logs (user_id, user_type, activity, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $user_type, $activity, $ip_address);
    $stmt->execute();
    $stmt->close();
}

// Function to determine user role from email domain
function get_role_from_email($email) {
    $domain = substr(strrchr($email, "@"), 1);
    
    // Example domain mapping - customize based on your requirements
    $domain_roles = [
        'admin.examportal.com' => 'admin',
        'faculty.examportal.com' => 'faculty',
        'student.examportal.com' => 'student'
    ];
    
    if (isset($domain_roles[$domain])) {
        return $domain_roles[$domain];
    }
    
    // Default role determination logic
    if (strpos($domain, 'admin') !== false) {
        return 'admin';
    } elseif (strpos($domain, 'faculty') !== false || strpos($domain, 'teacher') !== false || strpos($domain, 'prof') !== false) {
        return 'faculty';
    } elseif (strpos($domain, 'student') !== false || preg_match('/edu$/', $domain)) {
        return 'student';
    }
    
    // Use database to check
    global $conn;
    $stmt = $conn->prepare("SELECT user_exists(?) AS user_type");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if ($row && $row['user_type'] != 'none') {
        return $row['user_type'];
    }
    
    return 'unknown';
}

// Function to generate a random password
function generate_password($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*()';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $password;
}

// Function to check if an exam is available for a student
function is_exam_available($exam_id, $student_id) {
    global $conn;
    
    // Using nested SQL query to check availability
    $query = "
        SELECT 1 FROM Exam_Availability ea
        WHERE ea.exam_id = ? 
        AND ea.available_from <= NOW() 
        AND ea.available_until >= NOW()
        AND ea.student_group IN (
            SELECT CONCAT('college_', s.college_id) 
            FROM Student s 
            WHERE s.student_id = ?
        )
        LIMIT 1
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $exam_id, $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $is_available = $result->num_rows > 0;
    $stmt->close();
    
    return $is_available;
}

// Function to get exam details
function get_exam_details($exam_id) {
    global $conn;
    
    $query = "
        SELECT e.*, f.name as faculty_name 
        FROM Exam e
        JOIN Faculty f ON e.created_by = f.faculty_id
        WHERE e.exam_id = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $exam_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $exam = $result->fetch_assoc();
    $stmt->close();
    
    return $exam;
}

// Function to get all questions for an exam
function get_exam_questions($exam_id) {
    global $conn;
    
    $query = "SELECT * FROM Question WHERE exam_id = ? ORDER BY question_id";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $exam_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $questions = [];
    while ($row = $result->fetch_assoc()) {
        $questions[] = $row;
    }
    
    $stmt->close();
    return $questions;
}

// Function to start an exam attempt
function start_exam_attempt($student_id, $exam_id) {
    global $conn;
    
    $query = "
        INSERT INTO Exam_Attempt (student_id, exam_id, start_time, status)
        VALUES (?, ?, NOW(), 'in_progress')
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $student_id, $exam_id);
    $stmt->execute();
    $attempt_id = $stmt->insert_id;
    $stmt->close();
    
    return $attempt_id;
}

// Function to submit an answer
function submit_answer($attempt_id, $question_id, $answer_text) {
    global $conn;
    
    // Get the correct answer and marks for this question
    $query = "SELECT correct_answer, marks FROM Question WHERE question_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $question_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $question = $result->fetch_assoc();
    $stmt->close();
    
    // Check if the answer is correct
    $is_correct = ($answer_text == $question['correct_answer']) ? 1 : 0;
    $marks_obtained = $is_correct ? $question['marks'] : 0;
    
    // Insert the answer
    $query = "
        INSERT INTO Answer (attempt_id, question_id, answer_text, is_correct, marks_obtained)
        VALUES (?, ?, ?, ?, ?)
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iisid", $attempt_id, $question_id, $answer_text, $is_correct, $marks_obtained);
    $stmt->execute();
    $stmt->close();
    
    return $is_correct;
}

// Function to complete an exam attempt
function complete_exam_attempt($attempt_id) {
    global $conn;
    
    // Update the attempt status and end time
    $query = "
        UPDATE Exam_Attempt 
        SET status = 'completed', end_time = NOW() 
        WHERE attempt_id = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $attempt_id);
    $stmt->execute();
    $stmt->close();
    
    // Call the stored procedure to calculate the total score
    $stmt = $conn->prepare("CALL calculate_exam_score(?)");
    $stmt->bind_param("i", $attempt_id);
    $stmt->execute();
    $stmt->close();
}

// Function to get exam results for a student
function get_student_results($student_id) {
    global $conn;
    
    $query = "
        SELECT ea.*, e.title, e.subject, e.total_marks 
        FROM Exam_Attempt ea
        JOIN Exam e ON ea.exam_id = e.exam_id
        WHERE ea.student_id = ?
        ORDER BY ea.start_time DESC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $results = [];
    while ($row = $result->fetch_assoc()) {
        $results[] = $row;
    }
    
    $stmt->close();
    return $results;
}

// Function to get detailed result for an attempt
function get_attempt_details($attempt_id) {
    global $conn;
    
    // Get attempt and exam details
    $query = "
        SELECT ea.*, e.title, e.subject, e.total_marks 
        FROM Exam_Attempt ea
        JOIN Exam e ON ea.exam_id = e.exam_id
        WHERE ea.attempt_id = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $attempt_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $attempt = $result->fetch_assoc();
    $stmt->close();
    
    // Get answers with questions
    $query = "
        SELECT a.*, q.question_text, q.question_type, q.marks, q.correct_answer
        FROM Answer a
        JOIN Question q ON a.question_id = q.question_id
        WHERE a.attempt_id = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $attempt_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $answers = [];
    while ($row = $result->fetch_assoc()) {
        $answers[] = $row;
    }
    
    $stmt->close();
    
    return [
        'attempt' => $attempt,
        'answers' => $answers
    ];
}
?>