<?php

require_once __DIR__ . "/../core/db.php";

header("Content-Type: application/json");

// ==========================
// SECURITY
// ==========================
$KEY = $_GET['key'] ?? '';

if ($KEY !== '1234') {
    http_response_code(403);
    echo json_encode([
        "success" => false,
        "message" => "Unauthorized cleanup request"
    ]);
    exit;
}

// ==========================
// DELETE ONLY FILES (KEEP DIRS)
// ==========================
function deleteFilesOnly($dir, &$errors = [])
{
    if (!is_dir($dir)) return;

    $items = scandir($dir);

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;

        $path = $dir . DIRECTORY_SEPARATOR . $item;

        if (is_dir($path)) {
            deleteFilesOnly($path, $errors);
        } else {

            // try fixing permission first
            @chmod($path, 0777);

            if (!@unlink($path)) {
                $errors[] = $path;
            }
        }
    }
}

// ==========================
// 1. DB CLEANUP
// ==========================
$conn->query("SET FOREIGN_KEY_CHECKS=0");

$conn->query("DELETE FROM job_images");
$conn->query("DELETE FROM jobs");

$conn->query("SET FOREIGN_KEY_CHECKS=1");

// ==========================
// 2. STORAGE BASE PATH FIX
// ==========================
$base = realpath(__DIR__ . "/../storage");

if (!$base) {
    echo json_encode([
        "success" => false,
        "message" => "Storage path not found"
    ]);
    exit;
}

// ==========================
// 3. CLEAN FILES ONLY
// ==========================
$folders = [
    "tmp_uploads",
    "results",
    "uploads",
    "zips"
];

foreach ($folders as $folder) {
    deleteFilesOnly($base . "/" . $folder);
}

// ==========================
// RESPONSE
// ==========================
echo json_encode([
    "success" => true,
    "message" => "All files deleted, folder structure preserved"
]);
