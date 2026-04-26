<?php

require_once __DIR__ . "/core/db.php";

echo "Worker started v2...\n";

while (true) {

    // =========================
    // 1. CLAIM NEXT JOB IMAGE (SAFE + USER AWARE)
    // =========================
    $conn->begin_transaction();

    $taskRes = $conn->query("
        SELECT 
            ji.id,
            ji.job_id,
            ji.input_image,
            j.swap_image,
            j.user_id
        FROM job_images ji
        JOIN jobs j ON j.id = ji.job_id
        WHERE ji.status = 'queued'
        ORDER BY ji.id ASC
        LIMIT 1
        FOR UPDATE
    ");

    $task = $taskRes ? $taskRes->fetch_assoc() : null;

    if (!$task) {
        $conn->commit();
        echo "No jobs... sleeping\n";
        sleep(2);
        continue;
    }

    $image_id   = $task['id'];
    $job_id     = $task['job_id'];
    $user_id    = $task['user_id'];
    $swap_image = $task['swap_image'];
    $input_image = $task['input_image'];

    // mark as processing
    $conn->query("
        UPDATE job_images
        SET status = 'processing'
        WHERE id = $image_id
    ");

    $conn->commit();

    echo "Processing image #$image_id (Job #$job_id, User #$user_id)\n";

    // =========================
    // 2. BUILD FILE PATHS (USER-SPECIFIC)
    // =========================
    $basePath = __DIR__ . "/storage/tmp_uploads/user_$user_id/";

    $swapPath   = $basePath . $swap_image;
    $targetPath = $basePath . $input_image;

    try {

        // =========================
        // 3. VALIDATE FILES
        // =========================
        if (!file_exists($swapPath)) {
            throw new Exception("Swap image not found: $swapPath");
        }

        if (!file_exists($targetPath)) {
            throw new Exception("Target image not found: $targetPath");
        }

        // =========================
        // 4. CALL MODEL API
        // =========================
        $output_file = callFaceSwapAPI($swapPath, $targetPath);

        // =========================
        // 5. SAVE RESULT
        // =========================
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
    // 6. UPDATE JOB PROGRESS
    // =========================
    updateJobProgress($conn, $job_id);
}


// =====================================================
// CALL MODEL API
// =====================================================
function callFaceSwapAPI($swapPath, $targetPath)
{
    $api_url = "http://face-swap-model-v2:5000/predictions";

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


// =====================================================
// UPDATE JOB PROGRESS
// =====================================================
function updateJobProgress($conn, $job_id)
{
    $res = $conn->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as done
        FROM job_images
        WHERE job_id = $job_id
    ");

    $row = $res->fetch_assoc();

    $total = $row['total'] ?? 0;
    $done  = $row['done'] ?? 0;

    $progress = $total > 0 ? intval(($done / $total) * 100) : 0;

    $status = ($done >= $total) ? 'completed' : 'processing';

    $conn->query("
        UPDATE jobs 
        SET progress = $progress, status = '$status'
        WHERE id = $job_id
    ");
}