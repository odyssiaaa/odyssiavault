<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$user = requireAuth($pdo);
$isAdmin = (string)($user['role'] ?? 'user') === 'admin';
$input = getRequestInput();

if (!ensureTicketTables($pdo)) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Fitur tiket sedang tidak tersedia. Coba lagi beberapa saat.'],
    ], 500);
}

$ticketId = sanitizeQuantity($input['id'] ?? 0);
$action = mb_strtolower(trim((string)($input['action'] ?? '')));

if ($ticketId <= 0) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'ID tiket tidak valid.'],
    ], 422);
}

if (!in_array($action, ['close', 'reopen'], true)) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Aksi tiket tidak valid.'],
    ], 422);
}

try {
    $ticketStmt = $pdo->prepare('SELECT id, user_id, status FROM tickets WHERE id = :id LIMIT 1');
    $ticketStmt->execute(['id' => $ticketId]);
    $ticket = $ticketStmt->fetch();
} catch (Throwable $e) {
    error_log('Ticket update load failed: ' . $e->getMessage());
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Gagal memuat tiket.'],
    ], 500);
}

if (!is_array($ticket)) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Tiket tidak ditemukan.'],
    ], 404);
}

$isOwner = (int)$ticket['user_id'] === (int)$user['id'];
if (!$isAdmin && !$isOwner) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Akses tiket ditolak.'],
    ], 403);
}

$nextStatus = $action === 'close' ? 'closed' : 'open';
$now = nowDateTime();

try {
    $updateStmt = $pdo->prepare('UPDATE tickets SET status = :status, updated_at = :updated_at WHERE id = :id');
    $updateStmt->execute([
        'status' => $nextStatus,
        'updated_at' => $now,
        'id' => $ticketId,
    ]);
} catch (Throwable $e) {
    error_log('Ticket update failed: ' . $e->getMessage());
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Gagal memperbarui status tiket.'],
    ], 500);
}

jsonResponse([
    'status' => true,
    'data' => [
        'msg' => $action === 'close' ? 'Tiket berhasil ditutup.' : 'Tiket berhasil dibuka kembali.',
        'status' => $nextStatus,
    ],
]);
