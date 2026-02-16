<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$user = requireAuth($pdo);
requireAdmin($user);
expireUnpaidOrders($pdo);

$statusFilter = mb_strtolower(trim((string)($_GET['status'] ?? 'all')));
$query = trim((string)($_GET['q'] ?? ''));
$page = max(1, sanitizeQuantity($_GET['page'] ?? '1'));
$perPage = min(100, max(10, sanitizeQuantity($_GET['per_page'] ?? '25')));

$statusMap = [
    'waiting' => 'Menunggu Pembayaran',
    'processing' => 'Diproses',
    'success' => 'Selesai',
    'failed' => 'Dibatalkan',
];

if ($statusFilter !== 'all' && !array_key_exists($statusFilter, $statusMap)) {
    $statusFilter = 'all';
}

$conditions = [];
$params = [];

if ($statusFilter !== 'all') {
    $conditions[] = 'o.status = :status';
    $params['status'] = $statusMap[$statusFilter];
}

if ($query !== '') {
    $conditions[] = '(
        CAST(o.id AS CHAR) LIKE :query
        OR COALESCE(o.provider_order_id, \'\') LIKE :query
        OR u.username LIKE :query
        OR o.service_name LIKE :query
        OR o.target LIKE :query
    )';
    $params['query'] = '%' . $query . '%';
}

$whereSql = $conditions !== [] ? (' WHERE ' . implode(' AND ', $conditions)) : '';

$countSql = 'SELECT COUNT(*)
             FROM orders o
             INNER JOIN users u ON u.id = o.user_id' . $whereSql;

$rowsSql = 'SELECT
                o.id,
                o.user_id,
                u.username,
                o.provider_order_id,
                o.service_id,
                o.service_name,
                o.category,
                o.target,
                o.quantity,
                o.total_sell_price,
                o.status,
                o.provider_status,
                o.payment_method,
                o.payment_channel_name,
                o.payment_confirmed_at,
                o.payment_confirmed_by_admin_at,
                o.payment_deadline_at,
                o.error_message,
                o.created_at,
                o.updated_at
            FROM orders o
            INNER JOIN users u ON u.id = o.user_id' . $whereSql . '
            ORDER BY o.id DESC
            LIMIT :limit OFFSET :offset';

try {
    $countStmt = $pdo->prepare($countSql);
    foreach ($params as $key => $value) {
        $countStmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
    }
    $countStmt->execute();
    $total = (int)$countStmt->fetchColumn();

    $totalPages = max(1, (int)ceil($total / max(1, $perPage)));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;

    $stmt = $pdo->prepare($rowsSql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $orders = $stmt->fetchAll() ?: [];
} catch (Throwable $e) {
    error_log('Load admin order history failed: ' . $e->getMessage());
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Gagal memuat riwayat pembelian admin.'],
    ], 500);
}

jsonResponse([
    'status' => true,
    'data' => [
        'status' => $statusFilter,
        'query' => $query,
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'total_pages' => $totalPages,
        'orders' => $orders,
    ],
]);
