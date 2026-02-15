<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$user = requireAuth($pdo);
requireAdmin($user);
expireUnpaidOrders($pdo);

$statusFilter = mb_strtolower(trim((string)($_GET['status'] ?? 'waiting')));
$limit = min(200, max(10, sanitizeQuantity($_GET['limit'] ?? 100)));

$statusMap = [
    'waiting' => 'Menunggu Pembayaran',
    'processing' => 'Diproses',
    'completed' => 'Selesai',
    'cancelled' => 'Dibatalkan',
];

$sql = 'SELECT o.id, o.user_id, u.username, o.service_id, o.service_name, o.category, o.target, o.quantity, o.total_sell_price, o.status, o.payment_deadline_at, o.payment_confirmed_at, o.payment_confirmed_by_admin_at, o.payment_method, o.payment_channel_name, o.payment_account_name, o.payment_account_number, o.payment_payer_name, o.payment_reference, o.payment_note, o.error_message, o.created_at, o.updated_at
        FROM orders o
        INNER JOIN users u ON u.id = o.user_id';

if (isset($statusMap[$statusFilter])) {
    $sql .= ' WHERE o.status = :status';
}

$sql .= ' ORDER BY o.id DESC LIMIT :limit';

try {
    $stmt = $pdo->prepare($sql);
    if (isset($statusMap[$statusFilter])) {
        $stmt->bindValue(':status', $statusMap[$statusFilter], PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $orders = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('Load admin payments failed: ' . $e->getMessage());
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Gagal memuat data pembayaran admin.'],
    ], 500);
}

jsonResponse([
    'status' => true,
    'data' => [
        'status' => $statusFilter,
        'orders' => $orders,
    ],
]);
