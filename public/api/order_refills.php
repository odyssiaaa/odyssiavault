<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$user = requireAuth($pdo);

$limit = max(1, min(100, (int)($_GET['limit'] ?? 50)));

$stmt = $pdo->prepare(
    'SELECT r.id, r.order_id, r.provider_order_id, r.provider_refill_id, r.status, r.provider_status, r.created_at, r.updated_at,
            o.service_name, o.category
     FROM order_refills r
     INNER JOIN orders o ON o.id = r.order_id
     WHERE r.user_id = :user_id
     ORDER BY r.id DESC
     LIMIT :limit'
);
$stmt->bindValue(':user_id', (int)$user['id'], PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();
$refills = $stmt->fetchAll();

jsonResponse([
    'status' => true,
    'data' => [
        'refills' => $refills,
    ],
]);
