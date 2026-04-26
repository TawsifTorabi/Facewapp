<?php

require_once "../../core/db.php";
require_once "../../core/auth.php";

header("Content-Type: application/json");

// =====================
// AUTH (TOKEN BASED)
// =====================
$token = getBearerToken();

if (!$token) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$user = getUserFromToken($conn, $token);

if (!$user) {
    http_response_code(401);
    echo json_encode(["error" => "Invalid token"]);
    exit;
}

$user_id = $user['id'];

// =====================
// VALIDATE FILE INPUT
// =====================
if (!isset($_FILES['files'])) {
    http_response_code(400);
    echo json_encode(["error" => "No files uploaded"]);
    exit;
}

// =====================
// USER DIRECTORY
// =====================
$uploadDir = __DIR__ . "/../../storage/tmp_uploads/user_$user_id/";

if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$uploadedFiles = [];

// =====================
// PROCESS FILES
// =====================
foreach ($_FILES['files']['tmp_name'] as $i => $tmp) {

    if (!is_uploaded_file($tmp)) {
        continue;
    }

    $originalName = basename($_FILES['files']['name'][$i]);

    // prevent collisions + sanitize
    $name = uniqid() . "_" . preg_replace('/[^a-zA-Z0-9._-]/', '', $originalName);

    $destination = $uploadDir . $name;

    if (move_uploaded_file($tmp, $destination)) {
        $uploadedFiles[] = $name;
    }
}

// =====================
// RESPONSE
// =====================
echo json_encode([
    "success" => true,
    "user_id" => $user_id,
    "files" => $uploadedFiles
]);