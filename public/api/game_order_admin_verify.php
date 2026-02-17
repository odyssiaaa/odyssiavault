<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$user = requireAuth($pdo);
requireAdmin($user);
expireUnpaidOrders($pdo);

$input = getRequestInput();
$retryAfter = null;
if (!rateLimitAllow('admin_game_order_verify', 40, 60, $retryAfter)) {
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
    $orderStmt = $pdo->prepare(
        'SELECT id, user_id, status, external_id, service_code, target, contact
         FROM game_orders
         WHERE id = :id
         LIMIT 1
         FOR UPDATE'
    );
    $orderStmt->execute(['id' => $orderId]);
    $order = $orderStmt->fetch();

    if (!is_array($order)) {
        $pdo->rollBack();
        jsonResponse([
            'status' => false,
            'data' => ['msg' => 'Order game tidak ditemukan.'],
        ], 404);
    }

    if ((string)$order['status'] !== 'Menunggu Pembayaran') {
        $pdo->rollBack();
        jsonResponse([
            'status' => false,
            'data' => ['msg' => 'Order game ini tidak bisa diverifikasi lagi.'],
        ], 422);
    }

    $now = nowDateTime();

    if ($action === 'cancel') {
        $cancelStmt = $pdo->prepare(
            'UPDATE game_orders
             SET status = :status, error_message = :error_message, updated_at = :updated_at
             WHERE id = :id'
        );
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
                'msg' => 'Order game dibatalkan.',
                'order_id' => $orderId,
                'status' => 'Dibatalkan',
            ],
        ]);
    }

    if (!$gameClient->isConfigured()) {
        $pdo->rollBack();
        jsonResponse([
            'status' => false,
            'data' => ['msg' => 'API Topup Game belum dikonfigurasi.'],
        ], 500);
    }

    $providerResult = $gameClient->order(
        (string)($order['service_code'] ?? ''),
        (string)($order['target'] ?? ''),
        (string)($order['contact'] ?? ''),
        (string)($order['external_id'] ?? '')
    );

    if (($providerResult['status'] ?? false) !== true) {
        $providerMsg = (string)($providerResult['msg'] ?? $providerResult['data']['msg'] ?? 'Provider game gagal memproses order.');
        $pdo->rollBack();
        jsonResponse([
            'status' => false,
            'data' => ['msg' => $providerMsg],
        ], 400);
    }

    $providerData = (array)($providerResult['data'] ?? []);
    $providerOrderId = trim((string)($providerData['id'] ?? ''));
    if ($providerOrderId === '') {
        $providerOrderId = (string)($order['external_id'] ?? '');
    }
    $providerStatus = trim((string)($providerData['status'] ?? 'pending'));
    if ($providerStatus === '') {
        $providerStatus = 'pending';
    }

    $updateStmt = $pdo->prepare(
        'UPDATE game_orders
         SET provider_order_id = :provider_order_id,
             status = :status,
             provider_status = :provider_status,
             payment_confirmed_at = COALESCE(payment_confirmed_at, :payment_confirmed_at),
             payment_confirmed_by_admin_at = :payment_confirmed_by_admin_at,
             provider_response_json = :provider_response_json,
             updated_at = :updated_at
         WHERE id = :id'
    );
    $updateStmt->execute([
        'provider_order_id' => $providerOrderId,
        'status' => 'Diproses',
        'provider_status' => $providerStatus,
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
            'msg' => 'Pembayaran game diverifikasi. Order sedang diproses.',
            'order_id' => $orderId,
            'provider_order_id' => $providerOrderId,
            'status' => 'Diproses',
        ],
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Admin verify game payment failed: ' . $e->getMessage());
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Gagal memproses verifikasi pembayaran game.'],
    ], 500);
}

