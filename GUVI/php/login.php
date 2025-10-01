<?php
// ==============================================================================
// FILE: php/login.php
// PURPOSE: Handles user login, validates credentials against MySQL,
//          and creates a session token stored in Redis.
// CONSTRAINT: Must use Redis for session, MySQL Prepared Statements, and no PHP Session.
// ==============================================================================

// Set the response header to JSON format
header('Content-Type: application/json');

// --- 1. Configuration (UPDATE WITH YOUR CREDENTIALS) ---
$db_host = "localhost";
$db_user = "root";       // Common default for XAMPP
$db_pass = "";           // Common default for XAMPP
$db_name = "user_auth_db";
$db_port = 3307;         // MySQL Port (CRITICAL)

$redis_host = '127.0.0.1';
$redis_port = 6379;

// --- 2. Database & Redis Connections ---

// MySQL Connection
$mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
if ($mysqli->connect_error) {
    error_log("MySQL Connection Error: " . $mysqli->connect_error);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit();
}

// Redis Connection (Handle failure gracefully, though sessions won't work)
$redis = new Redis();
try {
    $redis->connect($redis_host, $redis_port);
    // Optional: Check if Redis is actually ready
    if ($redis->ping() !== '+PONG') {
        throw new Exception("Redis ping failed.");
    }
} catch (Exception $e) {
    error_log("Redis Connection Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Internal server error: Session service unavailable.']);
    $mysqli->close();
    exit();
}


// --- 3. Data Reception (From JQuery AJAX) ---
$input_data = file_get_contents('php://input');
$data = json_decode($input_data, true);

if (!$data || !isset($data['email'], $data['password'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data received.']);
    $mysqli->close();
    exit();
}

$email = $data['email'];
$password = $data['password'];

// --- 4. User Authentication (MySQL Prepared Statement) ---

// SQL query to fetch ID, username, and hashed password using Prepared Statements
$sql = "SELECT id, username, password FROM users WHERE email = ?";

$stmt = $mysqli->prepare($sql);
if ($stmt === false) {
    error_log("Prepare failed: " . $mysqli->error);
    echo json_encode(['success' => false, 'message' => 'Internal server error during authentication.']);
    $mysqli->close();
    exit();
}

// Bind parameter: 's' for email string
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$stmt->close();
$mysqli->close(); // Close MySQL connection as we only need Redis from now on

// Check if user exists and verify password
if ($user && password_verify($password, $user['password'])) {
    
    // --- 5. Session Creation (Redis & LocalStorage Token) ---

    $user_id = $user['id'];
    $username = $user['username'];
    
    // Generate a secure, unique token (Session ID)
    $session_token = bin2hex(random_bytes(32)); 
    
    // Data to store in Redis (ONLY essential, non-sensitive data)
    $session_data = json_encode([
        'user_id' => $user_id,
        'username' => $username,
        'login_time' => time()
    ]);
    
    // Store in Redis (Key: 'session:token', Value: JSON data, Expiry: 1 hour = 3600 seconds)
    // The frontend will receive this token and store it in localStorage.
    $redis_key = "session:{$session_token}";
    $redis->setex($redis_key, 3600, $session_data);

    // --- 6. Success Response ---
    echo json_encode([
        'success' => true,
        'message' => 'Login successful!',
        'username' => $username, 
        'session_token' => $session_token // CRITICAL: Frontend must save this to localStorage
    ]);

} else {
    // --- 7. Failure Response ---
    echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
}

// Note: No need to explicitly close Redis connection if using the default phpredis extension, 
// as it typically handles connections automatically when the script finishes.

?>