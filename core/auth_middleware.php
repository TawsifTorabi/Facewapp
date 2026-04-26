<?php

function auth() {

    $headers = getallheaders();
    $auth = $headers['Authorization'] ?? '';

    if (!str_starts_with($auth, "Bearer ")) {
        http_response_code(401);
        echo json_encode(["error" => "Unauthorized"]);
        exit;
    }

    $token = str_replace("Bearer ", "", $auth);

    return $token;
}