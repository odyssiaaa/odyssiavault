<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$user = requireAuth($pdo);
requireAdmin($user);

$input = getRequestInput();
$depositId = sanitizeQuantity($input['deposit_id'] ?? '');
$action = trim((string)($input['action'] ?? ''));
$adminNote = trim((string)($input['admin_note'] ?? ''));

if ($depositId <= 0 || !in_array($action, ['approve', 'reject'], true)) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'deposit_id dan action (approve/reject) wajib diisi.'],
    ], 422);
}

$pdo->beginTransaction();

try {
    $depositStmt = $pdo->prepare('SELECT id, user_id, amount_final, status FROM deposit_requests WHERE id = :id LIMIT 1 FOR UPDATE');
    $depositStmt->execute(['id' => $depositId]);
    $deposit = $depositStmt->fetch();

    if (!is_array($deposit)) {
        $pdo->rollBack();
        jsonResponse([
            'status' => false,
            'data' => ['msg' => 'Data deposit tidak ditemukan.'],
        ], 404);
    }

    if ((string)$deposit['status'] !== 'pending') {
        $pdo->rollBack();
        jsonResponse([
            'status' => false,
            'data' => ['msg' => 'Deposit ini sudah diproses sebelumnya.'],
        ], 422);
    }

    $now = nowDateTime();
    $adminNoteValue = $adminNote !== '' ? mb_substr($adminNote, 0, 255) : null;

    if ($action === 'approve') {
        $targetUserStmt = $pdo->prepare('SELECT id, username, balance FROM users WHERE id = :id LIMIT 1 FOR UPDATE');
        $targetUserStmt->execute(['id' => (int)$deposit['user_id']]);
        $targetUser = $targetUserStmt->fetch();

        if (!is_array($targetUser)) {
            $pdo->rollBack();
            jsonResponse([
                'status' => false,
                'data' => ['msg' => 'User pemilik deposit tidak ditemukan.'],
            ], 404);
        }

        $creditAmount = (int)$deposit['amount_final'];
        $newBalance = (int)$targetUser['balance'] + $creditAmount;

        $updateUserStmt = $pdo->prepare('UPDATE users SET balance = :balance, updated_at = :updated_at WHERE id = :id');
        $updateUserStmt->execute([
            'balance' => $newBalance,
            'updated_at' => $now,
            'id' => (int)$targetUser['id'],
        ]);

        insertBalanceTransaction(
            $pdo,
            (int)$targetUser['id'],
            'credit',
            $creditAmount,
            'Topup QRIS #' . $depositId,
            'deposit',
            $depositId
        );

        $updateDepositStmt = $pdo->prepare('UPDATE deposit_requests SET status = :status, admin_note = :admin_note, approved_by = :approved_by, approved_at = :approved_at, updated_at = :updated_at WHERE id = :id');
        $updateDepositStmt->execute([
            'status' => 'approved',
            'admin_note' => $adminNoteValue,
            'approved_by' => (int)$user['id'],
            'approved_at' => $now,
            'updated_at' => $now,
            'id' => $depositId,
        ]);

        $pdo->commit();

        jsonResponse([
            'status' => true,
            'data' => [
                'msg' => 'Deposit berhasil di-approve dan saldo user sudah ditambahkan.',
                'deposit_id' => $depositId,
                'status' => 'approved',
                'credited_amount' => $creditAmount,
                'user' => [
                    'id' => (int)$targetUser['id'],
                    'username' => (string)$targetUser['username'],
                    'balance' => $newBalance,
                ],
            ],
        ]);
    }

    $updateDepositStmt = $pdo->prepare('UPDATE deposit_requests SET status = :status, admin_note = :admin_note, approved_by = :approved_by, approved_at = :approved_at, updated_at = :updated_at WHERE id = :id');
    $updateDepositStmt->execute([
        'status' => 'rejected',
        'admin_note' => $adminNoteValue,
        'approved_by' => (int)$user['id'],
        'approved_at' => $now,
        'updated_at' => $now,
        'id' => $depositId,
    ]);

    $pdo->commit();

    jsonResponse([
        'status' => true,
        'data' => [
            'msg' => 'Deposit ditolak.',
            'deposit_id' => $depositId,
            'status' => 'rejected',
        ],
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Deposit admin update failed: ' . $e->getMessage());
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Gagal memproses deposit.'],
    ], 500);
}
