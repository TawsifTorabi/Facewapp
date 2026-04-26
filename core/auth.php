<?php

function getBearerToken()
{

    $headers = [];

    if (function_exists('getallheaders')) {
        $headers = getallheaders();
    }

    // fallback for nginx/apache/docker variations
    if (empty($headers)) {
        $headers = $_SERVER;
    }

    $auth = $headers['Authorization']
        ?? $headers['authorization']
        ?? $_SERVER['HTTP_AUTHORIZATION']
        ?? '';

    if (!$auth) return null;

    if (preg_match('/Bearer\s(\S+)/', $auth, $matches)) {
        return $matches[1];
    }

    return null;
}

function getUserFromToken($conn, $token)
{
    $ch = curl_init("http://php-gallery-manager/api.php?action=verify_token");

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $token
    ]);

    $res = curl_exec($ch);
    curl_close($ch);

    if (!$res) return null;

    $data = json_decode($res, true);

    // invalid response safety check
    if (!isset($data['valid']) || $data['valid'] !== true) {
        return null;
    }

    return $data['user']; // 👈 THIS makes it compatible everywhere
}