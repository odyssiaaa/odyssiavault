<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$user = requireAuth($pdo);
expireUnpaidOrders($pdo);

$input = getRequestInput();
$orderId = sanitizeQuantity($input['order_id'] ?? $input['id'] ?? '');
if ($orderId <= 0) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'order_id wajib diisi.'],
    ], 422);
}

$isAdmin = ((string)($user['role'] ?? 'user') === 'admin');
$scopeSql = $isAdmin ? 'id = :id' : 'id = :id AND user_id = :user_id';

$stmt = $pdo->prepare(
    'SELECT id, user_id, external_id, provider_order_id, service_name, status, provider_status, provider_sn, error_message, updated_at
     FROM game_orders
     WHERE ' . $scopeSql . '
     LIMIT 1'
);
$stmt->bindValue(':id', $orderId, PDO::PARAM_INT);
if (!$isAdmin) {
    $stmt->bindValue(':user_id', (int)$user['id'], PDO::PARAM_INT);
}
$stmt->execute();
$order = $stmt->fetch();

if (!is_array($order)) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Order game tidak ditemukan.'],
    ], 404);
}

$providerOrderId = trim((string)($order['provider_order_id'] ?? ''));
if ($providerOrderId === '') {
    jsonResponse([
        'status' => true,
        'data' => [
            'order_id' => (int)$order['id'],
            'provider_order_id' => '',
            'status' => (string)($order['status'] ?? 'Menunggu Pembayaran'),
            'provider_status' => (string)($order['provider_status'] ?? ''),
            'service_name' => (string)($order['service_name'] ?? ''),
            'sn' => (string)($order['provider_sn'] ?? ''),
            'updated_at' => (string)($order['updated_at'] ?? ''),
            'msg' => 'Order game belum masuk provider (menunggu verifikasi admin).',
        ],
    ]);
}

if (!$gameClient->isConfigured()) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'API Topup Game belum dikonfigurasi.'],
    ], 500);
}

$providerResult = $gameClient->status($providerOrderId);
if (($providerResult['status'] ?? false) !== true) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => (string)($providerResult['msg'] ?? $providerResult['data']['msg'] ?? 'Gagal cek status provider game.')],
    ], 400);
}

$providerData = (array)($providerResult['data'] ?? []);
$providerStatus = trim((string)($providerData['status'] ?? ''));
$providerSn = trim((string)($providerData['keterangan'] ?? ''));

$normalizeLifecycle = static function (string $status): string {
    $normalized = mb_strtolower(trim($status));
    if ($normalized === '') {
        return 'Diproses';
    }
    if (in_array($normalized, ['success', 'selesai', 'completed', 'done'], true)) {
        return 'Selesai';
    }
    if (in_array($normalized, ['cancel', 'canceled', 'cancelled', 'refund', 'error', 'failed', 'gagal'], true)) {
        return 'Dibatalkan';
    }
    if (in_array($normalized, ['pending', 'processing', 'process', 'in progress'], true)) {
        return 'Diproses';
    }
    return 'Diproses';
};

$lifecycleStatus = $normalizeLifecycle($providerStatus);
$updateStmt = $pdo->prepare(
    'UPDATE game_orders
     SET status = :status,
         provider_status = :provider_status,
         provider_sn = :provider_sn,
         provider_response_json = :provider_response_json,
         updated_at = :updated_at
     WHERE id = :id'
);
$now = nowDateTime();
$updateStmt->execute([
    'status' => $lifecycleStatus,
    'provider_status' => $providerStatus !== '' ? $providerStatus : (string)($order['provider_status'] ?? ''),
    'provider_sn' => $providerSn !== '' ? mb_substr($providerSn, 0, 2000) : (string)($order['provider_sn'] ?? ''),
    'provider_response_json' => json_encode($providerResult, JSON_UNESCAPED_UNICODE),
    'updated_at' => $now,
    'id' => (int)$order['id'],
]);

jsonResponse([
    'status' => true,
    'data' => [
        'order_id' => (int)$order['id'],
        'provider_order_id' => $providerOrderId,
        'status' => $lifecycleStatus,
        'provider_status' => $providerStatus,
        'service_name' => (string)($order['service_name'] ?? ''),
        'sn' => $providerSn,
        'updated_at' => $now,
    ],
]);

