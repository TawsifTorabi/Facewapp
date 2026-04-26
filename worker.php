<?php

require_once __DIR__ . "/core/db.php";

echo "Worker started v2...\n";

while (true) {

    // =========================
    // 1. ATOMIC CLAIM (SAFE)
    // =========================
    $conn->begin_transaction();

    // Claim ONE job_image safely
    $conn->query("
        UPDATE job_images
        SET status = 'processing'
        WHERE id = (
            SELECT id FROM (
                SELECT id 
                FROM job_images
                WHERE status = 'queued'
                ORDER BY id ASC
                LIMIT 1
            ) AS tmp
        )
    ");

    // If nothing was claimed
    if ($conn->affected_rows === 0) {
        $conn->commit();
        echo "No jobs... sleeping\n";
        sleep(2);
        continue;
    }

    // Fetch the claimed row
    $taskRes = $conn->query("
        SELECT id, job_id, input_image
        FROM job_images
        WHERE status = 'processing'
        ORDER BY id DESC
        LIMIT 1
    ");

    $task = $taskRes->fetch_assoc();

    $conn->commit();

    $image_id = $task['id'];
    $job_id = $task['job_id'];
    $input_image = $task['input_image'];

    echo "Processing image #$image_id (Job #$job_id)\n";

    // =========================
    // 2. PROCESS IMAGE
    // =========================
    try {

        // Get swap image
        $jobRes = $conn->query("
            SELECT swap_image 
            FROM jobs 
            WHERE id = $job_id
        ");

        $job = $jobRes->fetch_assoc();
        $swap_image = $job['swap_image'];

        // Call model
        $output_file = callFaceSwapAPI($swap_image, $input_image);

        // Save success
        $stmt = $conn->prepare("
            UPDATE job_images 
            SET status='completed', output_image=?
            WHERE id=?
        ");
        $stmt->bind_param("si", $output_file, $image_id);
        $stmt->execute();

        echo "Done image #$image_id\n";

    } catch (Exception $e) {

        echo "Error: " . $e->getMessage() . "\n";

        $stmt = $conn->prepare("
            UPDATE job_images 
            SET status='failed', error=?
            WHERE id=?
        ");

        $err = $e->getMessage();
        $stmt->bind_param("si", $err, $image_id);
        $stmt->execute();
    }

    // =========================
    // 3. UPDATE JOB PROGRESS
    // =========================
    updateJobProgress($conn, $job_id);
}

function callFaceSwapAPI($swap, $target)
{

    $api_url = "http://face-swap-model-v2:5000/predictions";

    $swapPath = __DIR__ . "/storage/uploads/" . $swap;
    $targetPath = __DIR__ . "/storage/uploads/" . $target;

    $swapBase64 = base64_encode(file_get_contents($swapPath));
    $targetBase64 = base64_encode(file_get_contents($targetPath));

    $payload = json_encode([
        "input" => [
            "swap_image" => "data:image/jpg;base64," . $swapBase64,
            "input_image" => "data:image/jpg;base64," . $targetBase64
        ]
    ]);

    $ch = curl_init($api_url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        throw new Exception(curl_error($ch));
    }

    curl_close($ch);

    $data = json_decode($response, true);

    if (!isset($data["output"])) {
        throw new Exception("Invalid API response");
    }

    $imgData = $data["output"];

    if (strpos($imgData, ",") !== false) {
        $imgData = explode(",", $imgData)[1];
    }

    $fileName = "result_" . uniqid() . ".jpg";
    $savePath = __DIR__ . "/storage/results/" . $fileName;

    if (!file_exists(__DIR__ . "/storage/results/")) {
        mkdir(__DIR__ . "/storage/results/", 0777, true);
    }

    file_put_contents($savePath, base64_decode($imgData));

    return $fileName;
}


function updateJobProgress($conn, $job_id)
{

    $res = $conn->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as done
        FROM job_images
        WHERE job_id=$job_id
    ");

    $row = $res->fetch_assoc();

    $total = $row['total'];
    $done = $row['done'];

    $progress = $total > 0 ? intval(($done / $total) * 100) : 0;

    $status = ($done == $total) ? 'completed' : 'processing';

    $conn->query("
        UPDATE jobs 
        SET progress=$progress, status='$status'
        WHERE id=$job_id
    ");
}
