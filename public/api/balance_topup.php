<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$user = requireAuth($pdo);
requireAdmin($user);

$input = getRequestInput();
$targetIdentity = trim((string)($input['username'] ?? ''));
$amount = sanitizeQuantity($input['amount'] ?? '');
$description = trim((string)($input['description'] ?? 'Topup saldo admin'));

if ($targetIdentity === '' || $amount <= 0) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'username dan amount wajib diisi.'],
    ], 422);
}

$pdo->beginTransaction();

try {
    $targetStmt = $pdo->prepare('SELECT id, username, full_name, balance FROM users WHERE username = :identity OR email = :identity LIMIT 1 FOR UPDATE');
    $targetStmt->execute(['identity' => $targetIdentity]);
    $target = $targetStmt->fetch();

    if (!is_array($target)) {
        $pdo->rollBack();
        jsonResponse([
            'status' => false,
            'data' => ['msg' => 'User target tidak ditemukan.'],
        ], 404);
    }

    $newBalance = (int)$target['balance'] + $amount;

    $updateStmt = $pdo->prepare('UPDATE users SET balance = :balance, updated_at = :updated_at WHERE id = :id');
    $updateStmt->execute([
        'balance' => $newBalance,
        'updated_at' => nowDateTime(),
        'id' => (int)$target['id'],
    ]);

    insertBalanceTransaction(
        $pdo,
        (int)$target['id'],
        'credit',
        $amount,
        $description,
        'admin_topup',
        (int)$user['id']
    );

    $pdo->commit();

    jsonResponse([
        'status' => true,
        'data' => [
            'msg' => 'Topup berhasil.',
            'user' => [
                'id' => (int)$target['id'],
                'username' => (string)$target['username'],
                'full_name' => (string)$target['full_name'],
                'balance' => $newBalance,
            ],
        ],
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Admin topup failed: ' . $e->getMessage());
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Gagal melakukan topup saldo.'],
    ], 500);
}
