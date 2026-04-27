<?php

require_once "../../core/db.php";
require_once "../../core/auth.php";

header("Content-Type: application/json");

$token = getBearerToken();
$user = getUserFromToken($conn, $token);

if (!$user) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$user_id = $user['id'];

$file_id = $_POST['file_id'];
$chunk_index = $_POST['chunk_index'];
$file_name = $_POST['file_name'];

$baseDir = __DIR__ . "/../../storage/tmp_uploads/user_$user_id/$file_id/";

if (!file_exists($baseDir)) {
    mkdir($baseDir, 0777, true);
}

$tmpPath = $baseDir . "chunk_$chunk_index";

move_uploaded_file($_FILES['chunk']['tmp_name'], $tmpPath);

echo json_encode([
    "success" => true,
    "chunk" => $chunk_index
]);