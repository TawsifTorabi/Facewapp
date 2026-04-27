<?php

require_once "../../../core/db.php";
require_once "../../../core/auth.php";

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
// QUERY JOBS
// =====================
$sql = "
SELECT 
    j.id,
    j.user_id,
    j.swap_image,
    j.status,
    j.progress,
    j.created_at,

    COUNT(ji.id) AS total_images,

    SUM(CASE 
        WHEN ji.status='completed' 
        THEN 1 ELSE 0 
    END) AS done_images,

    SUM(CASE 
        WHEN ji.status='failed' AND ji.retry_count >= 3
        THEN 1 ELSE 0 
    END) AS failed_images

FROM jobs j
LEFT JOIN job_images ji ON j.id = ji.job_id
WHERE j.user_id = ?
GROUP BY j.id
ORDER BY j.id DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

$jobs = [];

while ($row = $result->fetch_assoc()) {

    $total  = intval($row['total_images'] ?? 0);
    $done   = intval($row['done_images'] ?? 0);
    $failed = intval($row['failed_images'] ?? 0);

    // =====================
    // NEW PROGRESS LOGIC
    // =====================
    $processed = $done + $failed;

    $row['progress'] = $total > 0
        ? intval(($processed / $total) * 100)
        : 0;

    // normalize values
    $row['done_images']   = $done;
    $row['failed_images'] = $failed;

    $jobs[] = $row;
}

echo json_encode([
    "jobs" => $jobs
]);