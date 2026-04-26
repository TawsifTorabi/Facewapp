<?php

// ======================================
// IMAGE PROXY (SAFE STORAGE ACCESS)
// ======================================

$baseDir = realpath(__DIR__ . "/../../storage");

// Get requested file
$file = $_GET['file'] ?? '';

if (!$file) {
    http_response_code(400);
    exit("Missing file");
}

// Prevent directory traversal attacks
$file = str_replace(["..", "\\"], "", $file);

// Full path
$path = realpath($baseDir . "/" . $file);

// Security check: must stay inside storage folder
if (!$path || strpos($path, $baseDir) !== 0) {
    http_response_code(403);
    exit("Access denied");
}

// File must exist
if (!file_exists($path)) {
    http_response_code(404);
    exit("File not found");
}

// Get mime type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $path);
finfo_close($finfo);

// Output headers
header("Content-Type: " . $mime);
header("Content-Length: " . filesize($path));
header("Cache-Control: public, max-age=86400");

// Output file
readfile($path);
exit;