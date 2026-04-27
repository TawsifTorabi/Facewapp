<?php

require_once "../../core/db.php";
require_once "../../core/auth.php";

header("Content-Type: application/json");

$token = getBearerToken();
$user = getUserFromToken($conn, $token);

$user_id = $user['id'];

$file_id = $_POST['file_id'];
$file_name = $_POST['file_name'];

$chunkDir = __DIR__ . "/../../storage/tmp_uploads/user_$user_id/$file_id/";
$finalDir = __DIR__ . "/../../storage/uploads/user_$user_id/";

if (!file_exists($finalDir)) {
    mkdir($finalDir, 0777, true);
}

$finalPath = $finalDir . $file_name;

$out = fopen($finalPath, "wb");

$i = 0;
while (file_exists($chunkDir . "chunk_$i")) {

    $in = fopen($chunkDir . "chunk_$i", "rb");
    stream_copy_to_stream($in, $out);
    fclose($in);

    $i++;
}

fclose($out);

// cleanup
array_map('unlink', glob("$chunkDir/*"));
rmdir($chunkDir);

echo json_encode([
    "success" => true,
    "file" => $file_name
]);