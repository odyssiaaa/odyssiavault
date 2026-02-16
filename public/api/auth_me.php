<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$user = authUser($pdo);
if ($user === null) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Belum login.'],
    ]);
}

expireUnpaidOrders($pdo);

$statsStmt = $pdo->prepare(
    'SELECT
        COUNT(*) AS total_orders,
        COALESCE(SUM(CASE WHEN status = \'Selesai\' THEN total_sell_price ELSE 0 END), 0) AS total_spent,
        COALESCE(SUM(CASE WHEN status = \'Menunggu Pembayaran\' THEN 1 ELSE 0 END), 0) AS waiting_orders,
        COALESCE(SUM(CASE WHEN status = \'Diproses\' THEN 1 ELSE 0 END), 0) AS processing_orders,
        COALESCE(SUM(CASE WHEN status = \'Selesai\' THEN 1 ELSE 0 END), 0) AS completed_orders
     FROM orders
     WHERE user_id = :user_id'
);
$statsStmt->execute(['user_id' => (int)$user['id']]);
$stats = $statsStmt->fetch() ?: ['total_orders' => 0, 'total_spent' => 0];

jsonResponse([
    'status' => true,
    'data' => [
        'user' => [
            'id' => (int)$user['id'],
            'username' => (string)$user['username'],
            'email' => (string)$user['email'],
            'full_name' => (string)($user['full_name'] ?: $user['username']),
            'role' => (string)$user['role'],
            'balance' => (int)$user['balance'],
        ],
        'stats' => [
            'total_orders' => (int)($stats['total_orders'] ?? 0),
            'total_spent' => (int)($stats['total_spent'] ?? 0),
            'waiting_orders' => (int)($stats['waiting_orders'] ?? 0),
            'processing_orders' => (int)($stats['processing_orders'] ?? 0),
            'completed_orders' => (int)($stats['completed_orders'] ?? 0),
        ],
    ],
]);
