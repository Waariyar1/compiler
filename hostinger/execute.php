<?php
// ----------------------------------------------------
// 1. SECURITY & CONFIGURATION
// ----------------------------------------------------

// 🛑 IMPORTANT: Hiding the API Key. 
// For a Hostinger environment, the safest way is to define it here
// and ensure this file is protected, as it's not exposed to the client.
// This is your SECRET KEY. DO NOT put this in index.html.
$compiler_api_key = "dcacac3881025cf5dfd901ea2d15aac7"; 
$compiler_api_url = "https://emkc.org/api/v2/piston/execute";

// Allow requests ONLY from your domain to prevent abuse.
// IMPORTANT: Change this to your actual domain.
$allowed_origin = 'https://code.downverse.in'; 

// Set CORS Headers
if (isset($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN'] === $allowed_origin) {
    header('Access-Control-Allow-Origin: ' . $allowed_origin);
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
} else if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // Handle CORS preflight request
    http_response_code(200);
    exit();
} else {
    // Block requests from unauthorized origins
    http_response_code(403);
    echo json_encode(["error" => "Forbidden: Unauthorized origin."]);
    exit();
}

// ----------------------------------------------------
// 2. PROCESS INCOMING DATA
// ----------------------------------------------------
header('Content-Type: application/json');

// Get the raw JSON data from the request body
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// Check for required data
if (json_last_error() !== JSON_ERROR_NONE || empty($data['language']) || empty($data['version']) || !isset($data['files'])) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid or missing data in request."]);
    exit();
}

// ----------------------------------------------------
// 3. FORWARD REQUEST TO COMPILER API
// ----------------------------------------------------

// Prepare the payload for the external API (Piston)
$payload = [
    'language' => $data['language'],
    'version' => $data['version'],
    'files' => $data['files'],
    'stdin' => $data['stdin'] ?? "",
    'args' => $data['args'] ?? []
];

$ch = curl_init($compiler_api_url);

// Configure cURL options
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    // 🛑 The secret key is safely passed in the server-to-server request
    'Authorization: Bearer ' . $compiler_api_key 
]);

// Execute the request
$api_response = curl_exec($ch);
$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// Check for cURL errors
if (curl_errno($ch)) {
    http_response_code(500);
    echo json_encode(["error" => "Server error: Failed to connect to compiler service.", "details" => curl_error($ch)]);
} else {
    // Pass the external API's status and response back to the frontend
    http_response_code($http_status);
    echo $api_response;
}

curl_close($ch);
?>