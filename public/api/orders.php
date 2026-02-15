<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$user = requireAuth($pdo);
expireUnpaidOrders($pdo);
$limit = min(200, max(10, sanitizeQuantity($_GET['limit'] ?? 50)));

try {
    $stmt = $pdo->prepare('SELECT id, provider_order_id, service_id, service_name, category, target, quantity, unit_sell_price, total_sell_price, status, provider_status, provider_start_count, provider_remains, payment_deadline_at, payment_confirmed_at, payment_confirmed_by_admin_at, payment_method, payment_channel_name, payment_account_name, payment_account_number, payment_payer_name, payment_reference, payment_note, error_message, created_at, updated_at FROM orders WHERE user_id = :user_id ORDER BY id DESC LIMIT :limit');
    $stmt->bindValue(':user_id', (int)$user['id'], PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $orders = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('Load orders failed: ' . $e->getMessage());
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Gagal memuat riwayat order.'],
    ], 500);
}

jsonResponse([
    'status' => true,
    'data' => [
        'orders' => $orders,
    ],
]);
