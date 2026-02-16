<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$user = requireAuth($pdo);
requireAdmin($user);
expireUnpaidOrders($pdo);

$input = getRequestInput();
$retryAfter = null;
if (!rateLimitAllow('admin_order_verify', 40, 60, $retryAfter)) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Permintaan verifikasi terlalu cepat. Coba lagi beberapa saat.'],
    ], 429);
}

$orderId = sanitizeQuantity($input['order_id'] ?? '');
$action = mb_strtolower(trim((string)($input['action'] ?? '')));
$adminNote = trim((string)($input['admin_note'] ?? ''));

if ($orderId <= 0 || !in_array($action, ['verify', 'cancel'], true)) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'order_id dan action (verify/cancel) wajib diisi.'],
    ], 422);
}

$pdo->beginTransaction();

try {
    $orderStmt = $pdo->prepare('SELECT id, user_id, status, payload_json, provider_order_id FROM orders WHERE id = :id LIMIT 1 FOR UPDATE');
    $orderStmt->execute(['id' => $orderId]);
    $order = $orderStmt->fetch();

    if (!is_array($order)) {
        $pdo->rollBack();
        jsonResponse([
            'status' => false,
            'data' => ['msg' => 'Order tidak ditemukan.'],
        ], 404);
    }

    if ((string)$order['status'] !== 'Menunggu Pembayaran') {
        $pdo->rollBack();
        jsonResponse([
            'status' => false,
            'data' => ['msg' => 'Order ini tidak bisa diverifikasi lagi.'],
        ], 422);
    }

    $now = nowDateTime();

    if ($action === 'cancel') {
        $cancelStmt = $pdo->prepare('UPDATE orders SET status = :status, error_message = :error_message, updated_at = :updated_at WHERE id = :id');
        $cancelStmt->execute([
            'status' => 'Dibatalkan',
            'error_message' => $adminNote !== '' ? mb_substr($adminNote, 0, 1000) : 'Dibatalkan oleh admin',
            'updated_at' => $now,
            'id' => $orderId,
        ]);

        $pdo->commit();

        jsonResponse([
            'status' => true,
            'data' => [
                'msg' => 'Order dibatalkan.',
                'order_id' => $orderId,
                'status' => 'Dibatalkan',
            ],
        ]);
    }

    if (!$client->isConfigured()) {
        $pdo->rollBack();
        jsonResponse([
            'status' => false,
            'data' => ['msg' => 'Konfigurasi server layanan belum diisi (config/env).'],
        ], 500);
    }

    $payload = json_decode((string)($order['payload_json'] ?? ''), true);
    if (!is_array($payload) || $payload === []) {
        $pdo->rollBack();
        jsonResponse([
            'status' => false,
            'data' => ['msg' => 'Payload order server layanan tidak valid.'],
        ], 500);
    }

    $providerResult = $client->order($payload);
    if (!($providerResult['status'] ?? false)) {
        $providerMsg = (string)($providerResult['data']['msg'] ?? 'Server layanan gagal memproses order.');
        $pdo->rollBack();
        jsonResponse([
            'status' => false,
            'data' => ['msg' => $providerMsg],
        ], 400);
    }

    $providerOrderId = trim((string)($providerResult['data']['id'] ?? ''));
    if ($providerOrderId === '') {
        $pdo->rollBack();
        jsonResponse([
            'status' => false,
            'data' => ['msg' => 'Server layanan tidak mengembalikan ID order.'],
        ], 400);
    }

    $verifyStmt = $pdo->prepare('UPDATE orders SET provider_order_id = :provider_order_id, status = :status, provider_status = :provider_status, payment_confirmed_at = COALESCE(payment_confirmed_at, :payment_confirmed_at), payment_confirmed_by_admin_at = :payment_confirmed_by_admin_at, provider_response_json = :provider_response_json, updated_at = :updated_at WHERE id = :id');
    $verifyStmt->execute([
        'provider_order_id' => $providerOrderId,
        'status' => 'Diproses',
        'provider_status' => 'Processing',
        'payment_confirmed_at' => $now,
        'payment_confirmed_by_admin_at' => $now,
        'provider_response_json' => json_encode($providerResult, JSON_UNESCAPED_UNICODE),
        'updated_at' => $now,
        'id' => $orderId,
    ]);

    $pdo->commit();

    jsonResponse([
        'status' => true,
        'data' => [
            'msg' => 'Pembayaran diverifikasi. Order sedang diproses.',
            'order_id' => $orderId,
            'provider_order_id' => $providerOrderId,
            'status' => 'Diproses',
        ],
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Admin verify payment failed: ' . $e->getMessage());
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Gagal memproses verifikasi pembayaran.'],
    ], 500);
}
