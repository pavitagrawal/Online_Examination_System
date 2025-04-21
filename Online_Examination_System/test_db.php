<?php
// Database connection parameters
$host = "localhost";
$username = "root";
$password = ""; // Change if your MySQL has a password
$database = "exam_system"; // Your database name

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} else {
    echo "Connected to database successfully!";
    
    // Test query to verify tables exist
    $tables = array("Admin", "Faculty", "Student");
    echo "<br><br>Checking tables:<br>";
    
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows > 0) {
            echo "✓ Table $table exists<br>";
        } else {
            echo "✗ Table $table does not exist<br>";
        }
    }
}
?>
