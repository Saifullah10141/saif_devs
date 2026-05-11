<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Read JSON body
$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!$body) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON body']);
    exit;
}

// Sanitize inputs
$name    = htmlspecialchars(trim($body['name']    ?? ''), ENT_QUOTES, 'UTF-8');
$email   = htmlspecialchars(trim($body['email']   ?? ''), ENT_QUOTES, 'UTF-8');
$phone   = htmlspecialchars(trim($body['phone']   ?? ''), ENT_QUOTES, 'UTF-8');
$service = htmlspecialchars(trim($body['service'] ?? ''), ENT_QUOTES, 'UTF-8');
$budget  = htmlspecialchars(trim($body['budget']  ?? ''), ENT_QUOTES, 'UTF-8');
$message = htmlspecialchars(trim($body['message'] ?? ''), ENT_QUOTES, 'UTF-8');
$pageUrl = htmlspecialchars(trim($body['pageUrl'] ?? ''), ENT_QUOTES, 'UTF-8');

// Validate required fields
if (!$name || !$email || !$message) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Name, email and message are required']);
    exit;
}

// Read credentials from environment variables
$instanceId = getenv('API_INSTANCE_ID');
$apiToken   = getenv('API_API_TOKEN');
$waNumber   = getenv('API_WA_NUMBER');

if (!$instanceId || !$apiToken) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'API credentials not configured']);
    exit;
}

// Build WhatsApp message
$text  = "*New Project Inquiry — saif_devs*\n\n";
$text .= "*Name:* {$name}\n";
$text .= "*Email:* {$email}\n";
$text .= "*Phone:* " . ($phone ?: 'Not provided') . "\n";
$text .= "*Service:* " . ($service ?: 'Not specified') . "\n";
$text .= "*Budget:* " . ($budget ?: 'Not specified') . "\n";
$text .= "*Message:* {$message}\n";
if ($pageUrl) {
    $text .= "*Sent from:* {$pageUrl}";
}

// Call API
$url     = "https://api.green-api.com/waInstance{$instanceId}/sendMessage/{$apiToken}";
$payload = json_encode([
    'chatId'  => "{$waNumber}@c.us",
    'message' => $text,
]);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
]);

$response = curl_exec($ch);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => "cURL error: {$curlErr}"]);
    exit;
}

$result = json_decode($response, true);

if (isset($result['idMessage'])) {
    echo json_encode(['success' => true, 'idMessage' => $result['idMessage']]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $result]);
}
