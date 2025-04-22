-- Create database
CREATE DATABASE exam_system;
USE exam_system;

-- College table
CREATE TABLE College (
    college_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    address TEXT NOT NULL
);

-- Venue table
CREATE TABLE Venue (
    venue_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    capacity INT NOT NULL
);

-- Admin table
CREATE TABLE Admin (
    admin_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    contact VARCHAR(20) NOT NULL
);

-- Faculty table
CREATE TABLE Faculty (
    faculty_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    contact VARCHAR(20) NOT NULL,
    college_id INT,
    FOREIGN KEY (college_id) REFERENCES College(college_id)
);

-- Student table
CREATE TABLE Student (
    student_id INT AUTO_INCREMENT PRIMARY KEY,
    reg_no VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    contact VARCHAR(20) NOT NULL,
    college_id INT,
    FOREIGN KEY (college_id) REFERENCES College(college_id)
);

-- Exam table
CREATE TABLE Exam (
    exam_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    subject VARCHAR(100) NOT NULL,
    description TEXT,
    total_marks INT NOT NULL,
    duration INT NOT NULL, -- in minutes
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    created_by INT NOT NULL,
    FOREIGN KEY (created_by) REFERENCES Faculty(faculty_id)
);

-- Question table
CREATE TABLE Question (
    question_id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('multiple_choice', 'true_false', 'descriptive') NOT NULL,
    marks DECIMAL(5,2) NOT NULL,
    option_a TEXT,
    option_b TEXT,
    option_c TEXT,
    option_d TEXT,
    correct_answer TEXT,
    FOREIGN KEY (exam_id) REFERENCES Exam(exam_id) ON DELETE CASCADE
);

-- Exam_Attempt table
CREATE TABLE Exam_Attempt (
    attempt_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    exam_id INT NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME,
    status ENUM('in_progress', 'completed', 'timed_out') NOT NULL DEFAULT 'in_progress',
    total_score DECIMAL(5,2) DEFAULT 0,
    FOREIGN KEY (student_id) REFERENCES Student(student_id),
    FOREIGN KEY (exam_id) REFERENCES Exam(exam_id) ON DELETE CASCADE
);

-- Answer table
CREATE TABLE Answer (
    answer_id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT NOT NULL,
    question_id INT NOT NULL,
    answer_text TEXT,
    is_correct BOOLEAN DEFAULT FALSE,
    marks_obtained DECIMAL(5,2) DEFAULT 0,
    FOREIGN KEY (attempt_id) REFERENCES Exam_Attempt(attempt_id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES Question(question_id) ON DELETE CASCADE
);

-- Exam_Availability table
CREATE TABLE Exam_Availability (
    availability_id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT NOT NULL,
    student_group VARCHAR(100) NOT NULL,
    available_from DATETIME NOT NULL,
    available_until DATETIME NOT NULL,
    FOREIGN KEY (exam_id) REFERENCES Exam(exam_id) ON DELETE CASCADE
);

-- System_Logs table
CREATE TABLE System_Logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    user_type ENUM('admin', 'faculty', 'student') NOT NULL,
    activity TEXT NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- BCNF entities for email normalization
CREATE TABLE Student_Email (
    email_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    is_primary BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (student_id) REFERENCES Student(student_id) ON DELETE CASCADE
);

CREATE TABLE Faculty_Email (
    email_id INT AUTO_INCREMENT PRIMARY KEY,
    faculty_id INT NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    is_primary BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (faculty_id) REFERENCES Faculty(faculty_id) ON DELETE CASCADE
);

CREATE TABLE Admin_Email (
    email_id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    is_primary BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (admin_id) REFERENCES Admin(admin_id) ON DELETE CASCADE
);

-- Stored procedures for common operations

-- Procedure to add a new question
DELIMITER //
CREATE PROCEDURE add_question(
    IN p_exam_id INT,
    IN p_question_text TEXT,
    IN p_question_type ENUM('multiple_choice', 'true_false', 'descriptive'),
    IN p_marks DECIMAL(5,2),
    IN p_option_a TEXT,
    IN p_option_b TEXT,
    IN p_option_c TEXT,
    IN p_option_d TEXT,
    IN p_correct_answer TEXT
)
BEGIN
    INSERT INTO Question (
        exam_id, question_text, question_type, marks, 
        option_a, option_b, option_c, option_d, correct_answer
    ) VALUES (
        p_exam_id, p_question_text, p_question_type, p_marks, 
        p_option_a, p_option_b, p_option_c, p_option_d, p_correct_answer
    );
END //
DELIMITER ;

-- Procedure to calculate exam score
DELIMITER //
CREATE PROCEDURE calculate_exam_score(IN p_attempt_id INT)
BEGIN
    DECLARE total DECIMAL(5,2) DEFAULT 0;
    
    -- Sum up marks obtained for all answers in this attempt
    SELECT SUM(marks_obtained) INTO total FROM Answer WHERE attempt_id = p_attempt_id;
    
    -- Update the total score in the Exam_Attempt table
    UPDATE Exam_Attempt SET total_score = total, status = 'completed' WHERE attempt_id = p_attempt_id;
END //
DELIMITER ;

-- Function to check if a user exists by email
DELIMITER //
CREATE FUNCTION user_exists(p_email VARCHAR(100)) 
RETURNS VARCHAR(20)
DETERMINISTIC
BEGIN
    DECLARE user_type VARCHAR(20);
    
    -- Check in Admin table
    IF EXISTS (SELECT 1 FROM Admin WHERE email = p_email) THEN
        RETURN 'admin';
    END IF;
    
    -- Check in Faculty table
    IF EXISTS (SELECT 1 FROM Faculty WHERE email = p_email) THEN
        RETURN 'faculty';
    END IF;
    
    -- Check in Student table
    IF EXISTS (SELECT 1 FROM Student WHERE email = p_email) THEN
        RETURN 'student';
    END IF;
    
    RETURN 'none';
END //
DELIMITER ;
