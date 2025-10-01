<?php
// ==============================================================================
// FILE: php/register.php
// PURPOSE: Handles user registration and inserts data into MySQL.
// ==============================================================================

// Set the response header to JSON format
header('Content-Type: application/json');

// --- 1. Configuration (UPDATE IF NECESSARY) ---
$db_host = "localhost";
$db_user = "root";       
$db_pass = "";           
$db_name = "user_auth_db";
$db_port = 3307;         // CRITICAL: Port configured in XAMPP

// --- 2. Database Connection (MySQL) ---
$mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);

if ($mysqli->connect_error) {
    // Log the error and fail gracefully
    error_log("MySQL Connection Error: " . $mysqli->connect_error);
    echo json_encode(['success' => false, 'message' => 'Database connection failed. Check XAMPP MySQL (Port 3307).']);
    exit();
}

// --- 3. Data Reception (From JQuery AJAX) ---
$input_data = file_get_contents('php://input');
$data = json_decode($input_data, true);

// If the AJAX failed, we won't get proper data here
if (!$data || !isset($data['username'], $data['email'], $data['password'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data received. Did your JavaScript run correctly?']);
    $mysqli->close();
    exit();
}

$username = $data['username'];
$email = $data['email'];
$password = $data['password'];

// --- 4. Validation and Security ---
if (empty($username) || empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    $mysqli->close();
    exit();
}

$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// --- 5. Database Insertion using Prepared Statements ---
$sql = "INSERT INTO users (username, email, password) VALUES (?, ?, ?)";
$stmt = $mysqli->prepare($sql);

if ($stmt === false) {
    error_log("Prepare failed: " . $mysqli->error);
    echo json_encode(['success' => false, 'message' => 'Internal server error.']);
    $mysqli->close();
    exit();
}

// Bind parameters: 'sss' for three string parameters
$stmt->bind_param("sss", $username, $email, $hashed_password);

// Execute and handle exceptions
try {
    $result = $stmt->execute();
} catch (mysqli_sql_exception $e) {
    // 1062 is the error code for duplicate key (UNIQUE constraint)
    if ($e->getCode() == 1062) {
         echo json_encode(['success' => false, 'message' => 'Username or Email already exists.']);
    } else {
        error_log("Registration SQL Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error.']);
    }
    $stmt->close();
    $mysqli->close();
    exit();
}

// --- 6. Final Response ---
if ($result) {
    echo json_encode(['success' => true, 'message' => 'Registration successful!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Registration failed due to an unknown error.']);
}

// Clean up
$stmt->close();
$mysqli->close();
?>