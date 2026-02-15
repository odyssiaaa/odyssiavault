<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$user = requireAuth($pdo);
$limit = min(200, max(10, sanitizeQuantity($_GET['limit'] ?? 50)));

$stmt = $pdo->prepare('SELECT id, amount, unique_code, amount_final, payment_method, payer_name, payer_note, status, admin_note, created_at, updated_at, approved_at FROM deposit_requests WHERE user_id = :user_id ORDER BY id DESC LIMIT :limit');
$stmt->bindValue(':user_id', (int)$user['id'], PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();
$deposits = $stmt->fetchAll();

$paymentConfig = (array)($config['payment'] ?? []);
$minDeposit = max(1000, (int)($paymentConfig['deposit_min'] ?? 10000));
$maxDeposit = max($minDeposit, (int)($paymentConfig['deposit_max'] ?? 10000000));

jsonResponse([
    'status' => true,
    'data' => [
        'payment' => [
            'method' => 'qris',
            'qris_image' => (string)($paymentConfig['qris_image'] ?? 'assets/qris.png'),
            'receiver_name' => (string)($paymentConfig['qris_receiver_name'] ?? 'Odyssiavault'),
            'min_deposit' => $minDeposit,
            'max_deposit' => $maxDeposit,
        ],
        'deposits' => $deposits,
    ],
]);
