<?php

require_once "../../../core/db.php";
require_once "../../../core/auth.php";

header("Content-Type: application/json");

try {

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

    $input = json_decode(file_get_contents("php://input"), true);

    if (!$input) {
        throw new Exception("Invalid JSON input");
    }

    $swap_image = $input['swap_image'] ?? null;
    $targets = $input['targets'] ?? [];

    if (!$swap_image || empty($targets)) {
        throw new Exception("Missing swap or targets");
    }

    // CREATE JOB
    $stmt = $conn->prepare("
        INSERT INTO jobs (user_id, swap_image, status, progress)
        VALUES (?, ?, 'queued', 0)
    ");

    $stmt->bind_param("is", $user_id, $swap_image);
    $stmt->execute();

    $job_id = $stmt->insert_id;

    // INSERT IMAGES
    $stmt2 = $conn->prepare("
        INSERT INTO job_images (job_id, input_image, status)
        VALUES (?, ?, 'queued')
    ");

    foreach ($targets as $img) {
        $stmt2->bind_param("is", $job_id, $img);
        $stmt2->execute();
    }

    echo json_encode([
        "success" => true,
        "job_id" => $job_id
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}