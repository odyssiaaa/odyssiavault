<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$user = requireAuth($pdo);
$input = getRequestInput();

$paymentConfig = (array)($config['payment'] ?? []);
$minDeposit = max(1000, (int)($paymentConfig['deposit_min'] ?? 10000));
$maxDeposit = max($minDeposit, (int)($paymentConfig['deposit_max'] ?? 10000000));

$amount = sanitizeQuantity($input['amount'] ?? '');
$payerName = trim((string)($input['payer_name'] ?? ''));
$payerNote = trim((string)($input['payer_note'] ?? ''));

if ($amount < $minDeposit || $amount > $maxDeposit) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => "Nominal deposit harus antara Rp " . number_format($minDeposit, 0, ',', '.') . " - Rp " . number_format($maxDeposit, 0, ',', '.')],
    ], 422);
}

$pendingStmt = $pdo->prepare('SELECT COUNT(*) FROM deposit_requests WHERE user_id = :user_id AND status = :status');
$pendingStmt->execute([
    'user_id' => (int)$user['id'],
    'status' => 'pending',
]);
$pendingCount = (int)$pendingStmt->fetchColumn();
if ($pendingCount >= 5) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Maksimal 5 deposit pending. Selesaikan/verifikasi deposit sebelumnya dulu.'],
    ], 422);
}

$useUniqueCode = !empty($paymentConfig['use_unique_code']);
$uniqueCode = 0;
if ($useUniqueCode) {
    $codeMin = max(0, (int)($paymentConfig['unique_code_min'] ?? 11));
    $codeMax = max($codeMin, min(999, (int)($paymentConfig['unique_code_max'] ?? 99)));
    try {
        $uniqueCode = random_int($codeMin, $codeMax);
    } catch (Throwable $e) {
        $uniqueCode = $codeMin;
    }
}

$amountFinal = $amount + $uniqueCode;
$now = nowDateTime();

$stmt = $pdo->prepare('INSERT INTO deposit_requests (user_id, amount, unique_code, amount_final, payment_method, payer_name, payer_note, status, created_at, updated_at) VALUES (:user_id, :amount, :unique_code, :amount_final, :payment_method, :payer_name, :payer_note, :status, :created_at, :updated_at)');
$stmt->execute([
    'user_id' => (int)$user['id'],
    'amount' => $amount,
    'unique_code' => $uniqueCode,
    'amount_final' => $amountFinal,
    'payment_method' => 'qris',
    'payer_name' => $payerName !== '' ? mb_substr($payerName, 0, 120) : null,
    'payer_note' => $payerNote !== '' ? mb_substr($payerNote, 0, 2000) : null,
    'status' => 'pending',
    'created_at' => $now,
    'updated_at' => $now,
]);

$depositId = (int)$pdo->lastInsertId();

jsonResponse([
    'status' => true,
    'data' => [
        'msg' => 'Permintaan deposit berhasil dibuat. Silakan transfer sesuai nominal lalu tunggu verifikasi admin.',
        'deposit' => [
            'id' => $depositId,
            'amount' => $amount,
            'unique_code' => $uniqueCode,
            'amount_final' => $amountFinal,
            'status' => 'pending',
            'created_at' => $now,
        ],
        'payment' => [
            'method' => 'qris',
            'qris_image' => (string)($paymentConfig['qris_image'] ?? 'assets/qris.png'),
            'receiver_name' => (string)($paymentConfig['qris_receiver_name'] ?? 'Odyssiavault'),
            'min_deposit' => $minDeposit,
            'max_deposit' => $maxDeposit,
        ],
    ],
]);
