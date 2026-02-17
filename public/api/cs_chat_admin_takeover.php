<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$user = requireAuth($pdo);
requireAdmin($user);
$input = getRequestInput();

$retryAfter = null;
if (!rateLimitAllow('cs_chat_takeover_' . (string)($user['id'] ?? 0), 60, 300, $retryAfter)) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Terlalu sering ambil alih chat. Coba lagi sebentar.'],
    ], 429);
}

if (!ensureTicketTables($pdo)) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Fitur customer service sedang tidak tersedia.'],
    ], 500);
}

$ticketId = sanitizeQuantity($input['ticket_id'] ?? 0);
if ($ticketId <= 0) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'ID chat tidak valid.'],
    ], 422);
}

$message = trim((string)($input['message'] ?? ''));
if ($message === '') {
    $message = 'Halo, chat kamu sudah diambil alih admin Odyssiavault. Silakan lanjutkan detail kendalanya di sini.';
}
if (mb_strlen($message) > 2000) {
    $message = mb_substr($message, 0, 2000);
}

try {
    $ticketStmt = $pdo->prepare(
        'SELECT id, user_id, category, status
         FROM tickets
         WHERE id = :id
         LIMIT 1'
    );
    $ticketStmt->execute(['id' => $ticketId]);
    $ticket = $ticketStmt->fetch();

    if (!is_array($ticket)) {
        jsonResponse([
            'status' => false,
            'data' => ['msg' => 'Chat tidak ditemukan.'],
        ], 404);
    }

    if (!isCsChatCategory((string)($ticket['category'] ?? ''))) {
        jsonResponse([
            'status' => false,
            'data' => ['msg' => 'Tiket ini bukan chat customer service.'],
        ], 422);
    }

    $now = nowDateTime();

    $pdo->beginTransaction();

    updateCsChatMode($pdo, $ticketId, 'admin', $now);

    $insertMessageStmt = $pdo->prepare(
        'INSERT INTO ticket_messages (ticket_id, user_id, sender_role, message, created_at)
         VALUES (:ticket_id, :user_id, :sender_role, :message, :created_at)'
    );
    $insertMessageStmt->execute([
        'ticket_id' => $ticketId,
        'user_id' => (int)$user['id'],
        'sender_role' => 'admin',
        'message' => $message,
        'created_at' => $now,
    ]);

    $updateTicketStmt = $pdo->prepare(
        'UPDATE tickets
         SET status = :status, last_message_at = :last_message_at, updated_at = :updated_at
         WHERE id = :id'
    );
    $updateTicketStmt->execute([
        'status' => 'answered',
        'last_message_at' => $now,
        'updated_at' => $now,
        'id' => $ticketId,
    ]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('CS chat admin takeover failed: ' . $e->getMessage());
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Gagal mengambil alih chat customer service.'],
    ], 500);
}

jsonResponse([
    'status' => true,
    'data' => [
        'msg' => 'Chat berhasil diambil alih admin.',
        'ticket_id' => $ticketId,
    ],
]);

