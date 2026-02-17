<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$user = requireAuth($pdo);
$isAdmin = (string)($user['role'] ?? 'user') === 'admin';
$input = getRequestInput();

$retryAfter = null;
if (!rateLimitAllow('ticket_reply', 30, 300, $retryAfter)) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Terlalu banyak balasan tiket. Tunggu sebentar lalu coba lagi.'],
    ], 429);
}

if (!ensureTicketTables($pdo)) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Fitur tiket sedang tidak tersedia. Coba lagi beberapa saat.'],
    ], 500);
}

$ticketId = sanitizeQuantity($input['id'] ?? 0);
$message = trim((string)($input['message'] ?? ''));

if ($ticketId <= 0) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'ID tiket tidak valid.'],
    ], 422);
}

if ($message === '' || mb_strlen($message) < 2) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Pesan balasan minimal 2 karakter.'],
    ], 422);
}

try {
    $ticketStmt = $pdo->prepare('SELECT id, user_id, category, status FROM tickets WHERE id = :id LIMIT 1');
    $ticketStmt->execute(['id' => $ticketId]);
    $ticket = $ticketStmt->fetch();
} catch (Throwable $e) {
    error_log('Ticket reply load failed: ' . $e->getMessage());
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

if (!$isAdmin && (int)$ticket['user_id'] !== (int)$user['id']) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Akses tiket ditolak.'],
    ], 403);
}

$currentStatus = (string)($ticket['status'] ?? 'open');
if ($currentStatus === 'closed' && !$isAdmin) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Tiket sudah ditutup. Buka kembali tiket untuk membalas.'],
    ], 422);
}

$now = nowDateTime();
$senderRole = $isAdmin ? 'admin' : 'user';
$nextStatus = $currentStatus;
if ($senderRole === 'admin' && $currentStatus !== 'closed') {
    $nextStatus = 'answered';
}
if ($senderRole === 'user') {
    $nextStatus = 'open';
}

$currentCategory = (string)($ticket['category'] ?? '');
$nextCategory = $currentCategory;
if ($senderRole === 'admin' && isCsChatCategory($currentCategory)) {
    $nextCategory = csChatCategoryAdmin();
}

try {
    $pdo->beginTransaction();

    $msgStmt = $pdo->prepare(
        'INSERT INTO ticket_messages (ticket_id, user_id, sender_role, message, created_at)
         VALUES (:ticket_id, :user_id, :sender_role, :message, :created_at)'
    );
    $msgStmt->execute([
        'ticket_id' => $ticketId,
        'user_id' => (int)$user['id'],
        'sender_role' => $senderRole,
        'message' => $message,
        'created_at' => $now,
    ]);

    $updateStmt = $pdo->prepare(
        'UPDATE tickets
         SET status = :status, category = :category, last_message_at = :last_message_at, updated_at = :updated_at
         WHERE id = :id'
    );
    $updateStmt->execute([
        'status' => $nextStatus,
        'category' => $nextCategory,
        'last_message_at' => $now,
        'updated_at' => $now,
        'id' => $ticketId,
    ]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Ticket reply failed: ' . $e->getMessage());
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Gagal mengirim balasan tiket.'],
    ], 500);
}

jsonResponse([
    'status' => true,
    'data' => [
        'msg' => 'Balasan tiket berhasil dikirim.',
        'status' => $nextStatus,
    ],
]);
