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

$orderStmt = $pdo->prepare('SELECT id, user_id, provider_order_id, status FROM orders WHERE id = :id AND user_id = :user_id LIMIT 1');
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
    $currentStatus = (string)($order['status'] ?? 'Menunggu Pembayaran');
    jsonResponse([
        'status' => true,
        'data' => [
            'order_id' => $orderId,
            'provider_order_id' => null,
            'status' => $currentStatus,
            'provider_status' => null,
            'msg' => 'Order belum diproses ke provider.',
        ],
    ], 200);
}

if (!$client->isConfigured()) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Konfigurasi provider belum diisi (config/env).'],
    ], 500);
}

$statusResult = $client->status($providerOrderId);
if (!($statusResult['status'] ?? false)) {
    jsonResponse($statusResult, 400);
}

$providerData = (array)($statusResult['data'] ?? []);
$rawProviderStatus = (string)($providerData['status'] ?? $providerData['order_status'] ?? '');
$mappedStatus = mapProviderStatusLifecycle($rawProviderStatus);

$startCount = array_key_exists('start_count', $providerData)
    ? sanitizeQuantity($providerData['start_count'])
    : null;

$remains = array_key_exists('remains', $providerData)
    ? sanitizeQuantity($providerData['remains'])
    : null;

$updateStmt = $pdo->prepare('UPDATE orders SET status = :status, provider_status = :provider_status, provider_start_count = :provider_start_count, provider_remains = :provider_remains, provider_response_json = :provider_response_json, updated_at = :updated_at WHERE id = :id AND user_id = :user_id');
$updateStmt->execute([
    'status' => $mappedStatus,
    'provider_status' => $rawProviderStatus,
    'provider_start_count' => $startCount,
    'provider_remains' => $remains,
    'provider_response_json' => json_encode($statusResult, JSON_UNESCAPED_UNICODE),
    'updated_at' => nowDateTime(),
    'id' => $orderId,
    'user_id' => (int)$user['id'],
]);

jsonResponse([
    'status' => true,
    'data' => [
        'order_id' => $orderId,
        'provider_order_id' => $providerOrderId,
        'status' => $mappedStatus,
        'provider_status' => $rawProviderStatus,
        'start_count' => $startCount,
        'remains' => $remains,
    ],
]);
