<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$user = requireAuth($pdo);
requireAdmin($user);
expireUnpaidOrders($pdo);

$status = mb_strtolower(trim((string)($_GET['status'] ?? 'waiting')));
$limit = min(200, max(10, sanitizeQuantity($_GET['limit'] ?? 100)));

$whereSql = 'go.status = :status_waiting';
$params = [
    'status_waiting' => 'Menunggu Pembayaran',
];

if ($status === 'all') {
    $whereSql = '1=1';
    $params = [];
}

$sql = 'SELECT go.id, go.user_id, u.username, go.external_id, go.provider_order_id, go.service_code, go.service_name,
               go.category, go.target, go.contact, go.quantity, go.total_sell_price, go.status, go.provider_status,
               go.payment_deadline_at, go.payment_confirmed_at, go.payment_confirmed_by_admin_at,
               go.payment_method, go.payment_channel_name, go.payment_payer_name, go.payment_reference,
               go.error_message, go.created_at, go.updated_at
        FROM game_orders go
        JOIN users u ON u.id = go.user_id
        WHERE ' . $whereSql . '
        ORDER BY go.id DESC
        LIMIT :limit';

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue(':' . $key, $value);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();
$orders = $stmt->fetchAll();

jsonResponse([
    'status' => true,
    'data' => [
        'orders' => $orders,
    ],
]);

