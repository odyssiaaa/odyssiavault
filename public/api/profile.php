<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$user = requireAuth($pdo);
requireAdmin($user);

if (!$client->isConfigured()) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Konfigurasi provider belum diisi (config/env).'],
    ], 500);
}

$result = $client->profile();
if (!($result['status'] ?? false)) {
    jsonResponse($result, 400);
}

jsonResponse($result);
