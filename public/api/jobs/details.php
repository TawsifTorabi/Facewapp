<?php

require_once "../../../core/db.php";
require_once "../../../core/auth_middleware.php";

header("Content-Type: application/json");

auth();

$job_id = $_GET['job_id'] ?? 0;

$stmt = $conn->prepare("
    SELECT * FROM job_images
    WHERE job_id = ?
");

$stmt->bind_param("i", $job_id);
$stmt->execute();

$res = $stmt->get_result();

$images = [];

while ($row = $res->fetch_assoc()) {
    $images[] = $row;
}

echo json_encode([
    "images" => $images
]);