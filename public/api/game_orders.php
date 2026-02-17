<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$user = requireAuth($pdo);
expireUnpaidOrders($pdo);

$limit = min(200, max(10, sanitizeQuantity($_GET['limit'] ?? 50)));
$isAdmin = ((string)($user['role'] ?? 'user') === 'admin');
$scope = mb_strtolower(trim((string)($_GET['scope'] ?? 'user')));

if ($scope === 'admin' && $isAdmin) {
    $stmt = $pdo->prepare(
        'SELECT go.id, go.user_id, u.username, go.external_id, go.provider_order_id, go.service_code, go.service_name, go.category,
                go.target, go.contact, go.quantity, go.unit_sell_price, go.total_sell_price, go.status, go.provider_status,
                go.provider_sn, go.payment_deadline_at, go.payment_confirmed_at, go.payment_confirmed_by_admin_at,
                go.payment_method, go.payment_channel_name, go.payment_account_name, go.payment_account_number,
                go.payment_payer_name, go.payment_reference, go.payment_note, go.error_message, go.created_at, go.updated_at
         FROM game_orders go
         JOIN users u ON u.id = go.user_id
         ORDER BY go.id DESC
         LIMIT :limit'
    );
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $orders = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare(
        'SELECT id, user_id, external_id, provider_order_id, service_code, service_name, category, target, contact,
                quantity, unit_sell_price, total_sell_price, status, provider_status, provider_sn, payment_deadline_at,
                payment_confirmed_at, payment_confirmed_by_admin_at, payment_method, payment_channel_name,
                payment_account_name, payment_account_number, payment_payer_name, payment_reference, payment_note,
                error_message, created_at, updated_at
         FROM game_orders
         WHERE user_id = :user_id
         ORDER BY id DESC
         LIMIT :limit'
    );
    $stmt->bindValue(':user_id', (int)$user['id'], PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $orders = $stmt->fetchAll();
}

jsonResponse([
    'status' => true,
    'data' => [
        'orders' => $orders,
    ],
]);

