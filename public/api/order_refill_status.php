<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$user = requireAuth($pdo);

$input = getRequestInput();
$refillId = sanitizeQuantity($input['refill_id'] ?? '');

if ($refillId <= 0) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'refill_id wajib diisi.'],
    ], 422);
}

$refillStmt = $pdo->prepare('SELECT id, user_id, order_id, provider_order_id, provider_refill_id, status, provider_status FROM order_refills WHERE id = :id AND user_id = :user_id LIMIT 1');
$refillStmt->execute([
    'id' => $refillId,
    'user_id' => (int)$user['id'],
]);
$refill = $refillStmt->fetch();

if (!is_array($refill)) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Data refill tidak ditemukan.'],
    ], 404);
}

$providerRefillId = trim((string)($refill['provider_refill_id'] ?? ''));
if ($providerRefillId === '') {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Refill belum memiliki ID server.'],
    ], 422);
}

if (!$client->isConfigured()) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Konfigurasi server layanan belum diisi (config/env).'],
    ], 500);
}

$statusResult = $client->refillStatus($providerRefillId);
if (!($statusResult['status'] ?? false)) {
    $providerMsg = (string)($statusResult['data']['msg'] ?? 'Gagal cek status refill ke server layanan.');
    jsonResponse([
        'status' => false,
        'data' => ['msg' => $providerMsg],
    ], 400);
}

$providerData = (array)($statusResult['data'] ?? []);
$providerStatusRaw = trim((string)($providerData['status'] ?? $providerData['refill_status'] ?? ''));
$mappedStatus = mapProviderRefillStatusLifecycle($providerStatusRaw);

$updateStmt = $pdo->prepare('UPDATE order_refills SET status = :status, provider_status = :provider_status, provider_response_json = :provider_response_json, updated_at = :updated_at WHERE id = :id AND user_id = :user_id');
$updateStmt->execute([
    'status' => $mappedStatus,
    'provider_status' => $providerStatusRaw !== '' ? $providerStatusRaw : null,
    'provider_response_json' => json_encode($statusResult, JSON_UNESCAPED_UNICODE),
    'updated_at' => nowDateTime(),
    'id' => $refillId,
    'user_id' => (int)$user['id'],
]);

jsonResponse([
    'status' => true,
    'data' => [
        'refill_id' => $refillId,
        'order_id' => (int)$refill['order_id'],
        'provider_order_id' => (string)($refill['provider_order_id'] ?? ''),
        'provider_refill_id' => $providerRefillId,
        'status' => $mappedStatus,
        'provider_status' => $providerStatusRaw !== '' ? $providerStatusRaw : null,
    ],
]);
