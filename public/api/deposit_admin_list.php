<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$user = requireAuth($pdo);
requireAdmin($user);

$statusFilter = trim((string)($_GET['status'] ?? 'pending'));
$allowedStatus = ['all', 'pending', 'approved', 'rejected', 'cancelled'];
if (!in_array($statusFilter, $allowedStatus, true)) {
    $statusFilter = 'pending';
}

$limit = min(200, max(10, sanitizeQuantity($_GET['limit'] ?? 50)));

if ($statusFilter === 'all') {
    $stmt = $pdo->prepare('SELECT d.id, d.user_id, u.username, d.amount, d.unique_code, d.amount_final, d.payment_method, d.payer_name, d.payer_note, d.status, d.admin_note, d.created_at, d.updated_at, d.approved_at FROM deposit_requests d INNER JOIN users u ON u.id = d.user_id ORDER BY d.id DESC LIMIT :limit');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
} else {
    $stmt = $pdo->prepare('SELECT d.id, d.user_id, u.username, d.amount, d.unique_code, d.amount_final, d.payment_method, d.payer_name, d.payer_note, d.status, d.admin_note, d.created_at, d.updated_at, d.approved_at FROM deposit_requests d INNER JOIN users u ON u.id = d.user_id WHERE d.status = :status ORDER BY d.id DESC LIMIT :limit');
    $stmt->bindValue(':status', $statusFilter, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
}

$stmt->execute();
$deposits = $stmt->fetchAll();

jsonResponse([
    'status' => true,
    'data' => [
        'status' => $statusFilter,
        'deposits' => $deposits,
    ],
]);
