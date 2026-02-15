<?php

declare(strict_types=1);

// Vercel entrypoint: route all requests through this PHP function.
$apiPath = trim((string)($_GET['__api'] ?? ''));

if ($apiPath !== '') {
    $apiPath = str_replace('\\', '/', $apiPath);
    $apiPath = ltrim($apiPath, '/');
    $apiPath = preg_replace('/\?.*$/', '', $apiPath) ?? '';

    if ($apiPath === '' || str_contains($apiPath, '..')) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => false, 'data' => ['msg' => 'Endpoint API tidak ditemukan.']], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!str_ends_with($apiPath, '.php')) {
        $apiPath .= '.php';
    }

    $target = __DIR__ . '/../public/api/' . $apiPath;
    if (is_file($target)) {
        require $target;
        exit;
    }

    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => false, 'data' => ['msg' => 'Endpoint API tidak ditemukan.']], JSON_UNESCAPED_UNICODE);
    exit;
}

require __DIR__ . '/../public/index.php';
