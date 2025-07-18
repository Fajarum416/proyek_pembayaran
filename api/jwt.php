<?php
// api/jwt.php

$jwt_secret_key = "ini_adalah_kunci_rahasia_yang_sangat_panjang_dan_aman_sekali";

function generate_jwt($payload)
{
    global $jwt_secret_key;
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $payload['iat'] = time();
    $payload['exp'] = time() + (60 * 60 * 24); // Token berlaku 24 jam
    $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($payload)));
    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $jwt_secret_key, true);
    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
}

function get_authorization_header()
{
    $headers = null;
    if (isset($_SERVER['Authorization'])) {
        $headers = trim($_SERVER["Authorization"]);
    } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) { // Nginx atau fast CGI
        $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
    } elseif (function_exists('apache_request_headers')) {
        $requestHeaders = apache_request_headers();
        $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
        if (isset($requestHeaders['Authorization'])) {
            $headers = trim($requestHeaders['Authorization']);
        }
    }
    return $headers;
}

function validate_jwt_from_request()
{
    global $jwt_secret_key;

    $jwt = null;
    $authHeader = get_authorization_header();

    // --- PERUBAHAN DIMULAI DI SINI ---
    // Coba ambil token dari header otorisasi terlebih dahulu
    if ($authHeader && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $jwt = $matches[1];
    }
    // Jika tidak ada di header, coba ambil dari parameter URL (untuk ekspor file)
    elseif (isset($_GET['token'])) {
        $jwt = $_GET['token'];
    }
    // --- PERUBAHAN SELESAI ---

    if (!$jwt) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Token otentikasi tidak ditemukan.']);
        exit;
    }

    $tokenParts = explode('.', $jwt);
    if (count($tokenParts) !== 3) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Format token tidak valid.']);
        exit;
    }

    $header = base64_decode($tokenParts[0]);
    $payload = base64_decode($tokenParts[1]);
    $signatureProvided = $tokenParts[2];
    $payloadData = json_decode($payload);

    if (!$payloadData || !isset($payloadData->exp)) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Payload token tidak valid.']);
        exit;
    }

    if (($payloadData->exp - time()) < 0) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Token sudah kedaluwarsa.']);
        exit;
    }

    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $jwt_secret_key, true);
    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

    if (hash_equals($base64UrlSignature, $signatureProvided)) {
        return json_decode($payload, true);
    } else {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Signature token tidak valid.']);
        exit;
    }
}
