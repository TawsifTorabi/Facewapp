<?php

require_once __DIR__ . "/core/db.php";

echo "Worker started v4...\n";

$idleCounter = 0;

while (true) {

    // =========================
    // GLOBAL LOCK (STRICT SEQUENTIAL)
    // =========================
    $lockRes = $conn->query("SELECT GET_LOCK('facewapp_worker_lock', 0) as l");
    $lock = $lockRes->fetch_assoc()['l'];

    if (!$lock) {
        sleep(2);
        continue;
    }

    $conn->begin_transaction();

    // =========================
    // 1. CLAIM QUEUED
    // =========================
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

    // =========================
    // 2. RETRY FAILED (LIMITED)
    // =========================
    if ($conn->affected_rows === 0) {

        $conn->query("
            UPDATE job_images
            SET status = 'processing'
            WHERE id = (
                SELECT id FROM (
                    SELECT id 
                    FROM job_images
                    WHERE status = 'failed'
                    AND (
                        error IS NULL 
                        OR error = 'Invalid API response'
                    )
                    AND retry_count < 3
                    ORDER BY id ASC
                    LIMIT 1
                ) AS tmp
            )
        ");
    }

    // =========================
    // 3. NOTHING → IDLE
    // =========================
    if ($conn->affected_rows === 0) {
        $conn->commit();

        $conn->query("SELECT RELEASE_LOCK('facewapp_worker_lock')");

        $idleCounter++;

        if ($idleCounter % 10 === 0) {
            echo "[Idle] waiting...\n";
        }

        sleep(2);
        continue;
    }

    $idleCounter = 0;

    // =========================
    // 4. GET CLAIMED ROW
    // =========================
    $taskRes = $conn->query("
        SELECT id, job_id, input_image, retry_count
        FROM job_images
        WHERE status = 'processing'
        ORDER BY id DESC
        LIMIT 1
    ");

    $task = $taskRes->fetch_assoc();
    $conn->commit();

    if (!$task) {
        $conn->query("SELECT RELEASE_LOCK('facewapp_worker_lock')");
        continue;
    }

    $image_id    = $task['id'];
    $job_id      = $task['job_id'];
    $input_image = $task['input_image'];
    $retry_count = $task['retry_count'];

    echo "Processing image #$image_id (retry: $retry_count)\n";

    try {

        // =========================
        // GET JOB DATA
        // =========================
        $jobRes = $conn->query("
            SELECT swap_image, user_id 
            FROM jobs 
            WHERE id = $job_id
        ");

        $job = $jobRes->fetch_assoc();

        if (!$job) {
            throw new Exception("Job not found");
        }

        $swap_image = $job['swap_image'];
        $user_id    = $job['user_id'];

        // =========================
        // FILE PATHS
        // =========================
        $base = __DIR__ . "/storage/uploads/user_$user_id/";

        $swapPath   = $base . $swap_image;
        $targetPath = $base . $input_image;

        if (!file_exists($swapPath) || !file_exists($targetPath)) {
            throw new Exception("File not found");
        }

        // =========================
        // PROCESS
        // =========================
        $output_file = callFaceSwapAPI($swapPath, $targetPath);

        // =========================
        // SUCCESS
        // =========================
        $stmt = $conn->prepare("
            UPDATE job_images 
            SET status='completed', output_image=?, error=NULL
            WHERE id=?
        ");
        $stmt->bind_param("si", $output_file, $image_id);
        $stmt->execute();

        echo "Done image #$image_id\n";

    } catch (Exception $e) {

        $err = $e->getMessage();

        echo "Error #$image_id: $err\n";

        // increment retry count
        $stmt = $conn->prepare("
            UPDATE job_images 
            SET status='failed', error=?, retry_count = retry_count + 1
            WHERE id=?
        ");
        $stmt->bind_param("si", $err, $image_id);
        $stmt->execute();
    }

    // =========================
    // UPDATE JOB PROGRESS
    // =========================
    updateJobProgress($conn, $job_id);

    // =========================
    // RELEASE LOCK
    // =========================
    $conn->query("SELECT RELEASE_LOCK('facewapp_worker_lock')");
}


// =========================
// API CALL
// =========================
function callFaceSwapAPI($swapPath, $targetPath)
{
    $api_url = "http://face-swap-model-v2:5000/predictions";

    $swapBase64   = base64_encode(file_get_contents($swapPath));
    $targetBase64 = base64_encode(file_get_contents($targetPath));

    $payload = json_encode([
        "input" => [
            "swap_image"  => "data:image/jpg;base64," . $swapBase64,
            "input_image" => "data:image/jpg;base64," . $targetBase64
        ]
    ]);

    $ch = curl_init($api_url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 300
    ]);

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

    if (!file_exists(dirname($savePath))) {
        mkdir(dirname($savePath), 0777, true);
    }

    file_put_contents($savePath, base64_decode($imgData));

    return $fileName;
}


// =========================
// PROGRESS
// =========================
function updateJobProgress($conn, $job_id)
{
    $res = $conn->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as done,
            SUM(CASE WHEN status='failed' AND retry_count >= 3 THEN 1 ELSE 0 END) as failed_final
        FROM job_images
        WHERE job_id=$job_id
    ");

    $row = $res->fetch_assoc();

    $total = $row['total'];
    $done  = $row['done'];
    $failed = $row['failed_final'];

    $processed = $done + $failed;

    $progress = $total > 0 ? intval(($processed / $total) * 100) : 0;

    // Job is DONE if all images processed (success or final failure)
    if ($processed == $total) {
        $status = ($failed > 0) ? 'completed_with_errors' : 'completed';
    } else {
        $status = 'processing';
    }

    $conn->query("
        UPDATE jobs 
        SET progress=$progress, status='$status'
        WHERE id=$job_id
    ");
}