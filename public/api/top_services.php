<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

requireAuth($pdo);

$limit = min(10, max(1, sanitizeQuantity($_GET['limit'] ?? 5)));

$sql = '
    SELECT
        service_id,
        service_name,
        category,
        COUNT(*) AS total_orders,
        MAX(created_at) AS last_order_at
    FROM orders
    WHERE status IN (\'Selesai\', \'Completed\', \'Success\')
    GROUP BY service_id, service_name, category
    ORDER BY total_orders DESC, last_order_at DESC, service_name ASC
    LIMIT :limit
';

try {
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $services = $stmt->fetchAll();
} catch (Throwable $e) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Gagal memuat top layanan.'],
    ], 500);
}

jsonResponse([
    'status' => true,
    'data' => [
        'services' => $services,
    ],
]);
