
<?php
// ==============================================================================
// FILE: php/profile.php
// PURPOSE: Manages profile data (read/write) using MongoDB, authenticated via Redis session token.
// CONSTRAINT: Validates session via Redis, uses MongoDB for profile storage.
// ==============================================================================

// Set the response header to JSON format
header('Content-Type: application/json');

// --- 1. Configuration (UPDATE WITH YOUR CREDENTIALS) ---
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "user_auth_db";
$db_port = 3307; // MySQL Port

$redis_host = '127.0.0.1';
$redis_port = 6379;

$mongo_uri = "mongodb://localhost:27017";
$mongo_db_name = 'user_profiles_db'; // Database name for MongoDB
$mongo_collection_name = 'profiles'; // Collection name for MongoDB


// --- 2. Connection Setup ---

// Redis Connection
$redis = new Redis();
try {
    $redis->connect($redis_host, $redis_port);
    if ($redis->ping() !== '+PONG') {
        throw new Exception("Redis connection failed.");
    }
} catch (Exception $e) {
    // Session service failure means we can't authenticate, so we exit.
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Session service unavailable.']);
    exit();
}

// MongoDB Connection
try {
    // Requires the MongoDB PHP Library (Composer) and extension
    $mongoClient = new MongoDB\Client($mongo_uri);
    $profileCollection = $mongoClient->selectDatabase($mongo_db_name)->selectCollection($mongo_collection_name);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Profile database service unavailable.']);
    exit();
}


// --- 3. Session Validation (Retrieve Token from Header) ---

// Get all request headers
$headers = getallheaders();
$session_token = null;

// The frontend (profile.js) is sending the token as 'Authorization: Bearer <token>'
if (isset($headers['Authorization']) && preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
    $session_token = $matches[1];
}

// Check Redis for the session token
$redis_key = "session:{$session_token}";
$session_data_json = $redis->get($redis_key);

if (!$session_data_json) {
    // Session is invalid, expired, or missing
    http_response_code(401); // Unauthorized
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Session expired or invalid.']);
    exit();
}

// Session is valid. Retrieve user data (ID and username)
$session_data = json_decode($session_data_json, true);
$user_id = $session_data['user_id'];
$logged_in_username = $session_data['username']; // Get username from Redis for quick access


// --- 4. Handle Request: LOAD Profile (GET) ---

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    
    // Find the profile data in MongoDB using the user_id
    $profile = $profileCollection->findOne(['user_id' => $user_id]);

    $response_data = [
        'success' => true,
        // The username comes from the session (Redis)
        'username' => $logged_in_username
    ];

    if ($profile) {
        // Profile exists in MongoDB
        $response_data['profile'] = [
            // MongoDB fields
            'age' => $profile['age'] ?? '',
            'dob' => $profile['dob'] ?? '',
            'contact' => $profile['contact'] ?? ''
        ];
    } else {
        // No profile document found in MongoDB yet
        $response_data['message'] = 'Profile not yet completed.';
        $response_data['profile'] = [];
    }

    echo json_encode($response_data);


// --- 5. Handle Request: UPDATE Profile (POST) ---

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $input_data = file_get_contents('php://input');
    $update_data = json_decode($input_data, true);

    if (!$update_data || !isset($update_data['age'], $update_data['dob'], $update_data['contact'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid data received for update.']);
        exit();
    }

    // Data to be updated in MongoDB
    $update_document = [
        'user_id' => $user_id, // Ensure we tag the document with the MySQL ID
        'age' => (int)$update_data['age'],
        'dob' => $update_data['dob'],
        'contact' => $update_data['contact']
    ];
    
    // Define the filter (who to update) and the update action
    $filter = ['user_id' => $user_id];
    $update = ['$set' => $update_document];
    
    // Use the upsert option: insert if no matching document found, otherwise update it.
    $updateResult = $profileCollection->updateOne(
        $filter,
        $update,
        ['upsert' => true]
    );

    if ($updateResult) {
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully!']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to save profile to database.']);
    }

} else {
    // Handle invalid request method
    http_response_code(405);
    header('Allow: GET, POST');
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
}

?>