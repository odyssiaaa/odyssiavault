<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$user = requireAuth($pdo);
expireUnpaidOrders($pdo);

$input = getRequestInput();
$orderId = sanitizeQuantity($input['order_id'] ?? '');

if ($orderId <= 0) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'order_id wajib diisi.'],
    ], 422);
}

if (!$client->isConfigured()) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Konfigurasi server layanan belum diisi (config/env).'],
    ], 500);
}

$orderStmt = $pdo->prepare('SELECT id, user_id, provider_order_id, service_name, status FROM orders WHERE id = :id AND user_id = :user_id LIMIT 1');
$orderStmt->execute([
    'id' => $orderId,
    'user_id' => (int)$user['id'],
]);
$order = $orderStmt->fetch();

if (!is_array($order)) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Order tidak ditemukan.'],
    ], 404);
}

$providerOrderId = trim((string)($order['provider_order_id'] ?? ''));
if ($providerOrderId === '') {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Order belum memiliki ID server, refill belum bisa diajukan.'],
    ], 422);
}

$pendingRefillStmt = $pdo->prepare('SELECT COUNT(*) FROM order_refills WHERE order_id = :order_id AND user_id = :user_id AND status = :status');
$pendingRefillStmt->execute([
    'order_id' => $orderId,
    'user_id' => (int)$user['id'],
    'status' => 'Diproses',
]);
$pendingRefillCount = (int)$pendingRefillStmt->fetchColumn();
if ($pendingRefillCount > 0) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Masih ada refill yang sedang diproses untuk order ini.'],
    ], 422);
}

$providerResult = $client->refill($providerOrderId);
if (!($providerResult['status'] ?? false)) {
    $providerMsg = (string)($providerResult['data']['msg'] ?? 'Server layanan gagal memproses refill.');
    jsonResponse([
        'status' => false,
        'data' => ['msg' => $providerMsg],
    ], 400);
}

$providerRefillId = trim((string)($providerResult['data']['id'] ?? ''));
if ($providerRefillId === '') {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Server layanan tidak mengembalikan ID refill.'],
    ], 400);
}

$providerStatusRaw = trim((string)($providerResult['data']['status'] ?? ''));
$mappedStatus = mapProviderRefillStatusLifecycle($providerStatusRaw);
$now = nowDateTime();

try {
    $insertStmt = $pdo->prepare('INSERT INTO order_refills (order_id, user_id, provider_order_id, provider_refill_id, status, provider_status, provider_response_json, created_at, updated_at) VALUES (:order_id, :user_id, :provider_order_id, :provider_refill_id, :status, :provider_status, :provider_response_json, :created_at, :updated_at)');
    $insertStmt->execute([
        'order_id' => $orderId,
        'user_id' => (int)$user['id'],
        'provider_order_id' => $providerOrderId,
        'provider_refill_id' => $providerRefillId,
        'status' => $mappedStatus,
        'provider_status' => $providerStatusRaw !== '' ? $providerStatusRaw : null,
        'provider_response_json' => json_encode($providerResult, JSON_UNESCAPED_UNICODE),
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $localRefillId = (int)$pdo->lastInsertId();

    jsonResponse([
        'status' => true,
        'data' => [
            'msg' => 'Refill berhasil diajukan ke server layanan.',
            'refill_id' => $localRefillId,
            'order_id' => $orderId,
            'provider_order_id' => $providerOrderId,
            'provider_refill_id' => $providerRefillId,
            'status' => $mappedStatus,
            'provider_status' => $providerStatusRaw !== '' ? $providerStatusRaw : null,
        ],
    ]);
} catch (Throwable $e) {
    error_log('Save refill failed: ' . $e->getMessage());
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Gagal menyimpan data refill.'],
    ], 500);
}
