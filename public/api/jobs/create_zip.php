<?php

require_once "../../../core/db.php";
require_once "../../../core/auth.php";

error_reporting(0);
ini_set('display_errors', 0);
set_time_limit(0);

// =====================
// AUTH
// =====================
$token = getBearerToken();
$user = getUserFromToken($conn, $token);

if (!$user) {
    http_response_code(401);
    exit("Unauthorized");
}

$user_id = $user['id'];
$job_id = intval($_GET['job_id'] ?? 0);

// =====================
// VERIFY OWNERSHIP
// =====================
$stmt = $conn->prepare("SELECT id FROM jobs WHERE id=? AND user_id=?");
$stmt->bind_param("ii", $job_id, $user_id);
$stmt->execute();

if (!$stmt->get_result()->fetch_assoc()) {
    http_response_code(403);
    exit("Forbidden");
}

// =====================
// FETCH FILES
// =====================
$res = $conn->query("
    SELECT output_image
    FROM job_images
    WHERE job_id = $job_id AND status='completed'
");

// =====================
// CLEAN OUTPUT BUFFER SAFELY
// =====================
while (ob_get_level()) {
    ob_end_clean();
}

// =====================
// ZIP PATH
// =====================
$zipName = "job_$job_id.zip";
$zipPath = sys_get_temp_dir() . "/" . $zipName;

// =====================
// CHECK ZIP EXTENSION
// =====================
if (!class_exists('ZipArchive')) {
    http_response_code(500);
    exit("ZipArchive not installed");
}

// =====================
// CREATE ZIP
// =====================
$zip = new ZipArchive();

if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    http_response_code(500);
    exit("Cannot create zip");
}

$basePath = __DIR__ . "/../../../storage/results/";

while ($row = $res->fetch_assoc()) {

    $file = $row['output_image'];
    $path = $basePath . $file;

    if ($file && file_exists($path)) {
        $zip->addFile($path, basename($file));
    }
}

$zip->close();

// =====================
// SEND FILE
// =====================
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="'.$zipName.'"');
header('Content-Length: ' . filesize($zipPath));

readfile($zipPath);
unlink($zipPath);
exit;