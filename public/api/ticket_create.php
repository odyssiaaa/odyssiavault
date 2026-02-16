<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$user = requireAuth($pdo);
$isAdmin = (string)($user['role'] ?? 'user') === 'admin';
$input = getRequestInput();

$retryAfter = null;
if (!rateLimitAllow('ticket_create', 12, 300, $retryAfter)) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Terlalu banyak pembuatan tiket. Coba lagi nanti.'],
    ], 429);
}

if (!ensureTicketTables($pdo)) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Fitur tiket sedang tidak tersedia. Coba lagi beberapa saat.'],
    ], 500);
}

$subject = trim((string)($input['subject'] ?? ''));
$category = trim((string)($input['category'] ?? 'Laporan'));
$priority = mb_strtolower(trim((string)($input['priority'] ?? 'normal')));
$message = trim((string)($input['message'] ?? ''));
$orderId = sanitizeQuantity($input['order_id'] ?? '');

if ($subject === '' || mb_strlen($subject) < 5 || mb_strlen($subject) > 180) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Subjek tiket minimal 5 karakter dan maksimal 180 karakter.'],
    ], 422);
}

if ($message === '' || mb_strlen($message) < 5) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Pesan tiket minimal 5 karakter.'],
    ], 422);
}

if (!in_array($priority, ['low', 'normal', 'high', 'urgent'], true)) {
    $priority = 'normal';
}

if ($category === '') {
    $category = 'Laporan';
}
if (mb_strlen($category) > 80) {
    $category = mb_substr($category, 0, 80);
}

if ($orderId > 0) {
    try {
        if ($isAdmin) {
            $checkOrderStmt = $pdo->prepare('SELECT id FROM orders WHERE id = :id LIMIT 1');
            $checkOrderStmt->execute(['id' => $orderId]);
        } else {
            $checkOrderStmt = $pdo->prepare('SELECT id FROM orders WHERE id = :id AND user_id = :user_id LIMIT 1');
            $checkOrderStmt->execute([
                'id' => $orderId,
                'user_id' => (int)$user['id'],
            ]);
        }
        if (!$checkOrderStmt->fetch()) {
            jsonResponse([
                'status' => false,
                'data' => ['msg' => 'ID order tidak ditemukan atau bukan milik akun ini.'],
            ], 404);
        }
    } catch (Throwable $e) {
        error_log('Ticket create order check failed: ' . $e->getMessage());
        jsonResponse([
            'status' => false,
            'data' => ['msg' => 'Gagal memvalidasi ID order.'],
        ], 500);
    }
}

$now = nowDateTime();
$senderRole = $isAdmin ? 'admin' : 'user';

try {
    $pdo->beginTransaction();

    $ticketStmt = $pdo->prepare(
        'INSERT INTO tickets (user_id, order_id, subject, category, priority, status, last_message_at, created_at, updated_at)
         VALUES (:user_id, :order_id, :subject, :category, :priority, :status, :last_message_at, :created_at, :updated_at)'
    );
    $ticketStmt->execute([
        'user_id' => (int)$user['id'],
        'order_id' => $orderId > 0 ? $orderId : null,
        'subject' => $subject,
        'category' => $category,
        'priority' => $priority,
        'status' => 'open',
        'last_message_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $ticketId = (int)$pdo->lastInsertId();

    $messageStmt = $pdo->prepare(
        'INSERT INTO ticket_messages (ticket_id, user_id, sender_role, message, created_at)
         VALUES (:ticket_id, :user_id, :sender_role, :message, :created_at)'
    );
    $messageStmt->execute([
        'ticket_id' => $ticketId,
        'user_id' => (int)$user['id'],
        'sender_role' => $senderRole,
        'message' => $message,
        'created_at' => $now,
    ]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Ticket create failed: ' . $e->getMessage());
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Gagal membuat tiket. Silakan coba lagi.'],
    ], 500);
}

jsonResponse([
    'status' => true,
    'data' => [
        'msg' => 'Tiket berhasil dibuat.',
        'ticket' => [
            'id' => $ticketId,
            'subject' => $subject,
            'category' => $category,
            'priority' => $priority,
            'status' => 'open',
            'order_id' => $orderId > 0 ? $orderId : null,
            'created_at' => $now,
        ],
    ],
]);
