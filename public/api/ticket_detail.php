<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$user = requireAuth($pdo);
$isAdmin = (string)($user['role'] ?? 'user') === 'admin';

if (!ensureTicketTables($pdo)) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Fitur tiket sedang tidak tersedia. Coba lagi beberapa saat.'],
    ], 500);
}

$ticketId = sanitizeQuantity($_GET['id'] ?? ($_POST['id'] ?? 0));
if ($ticketId <= 0) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'ID tiket tidak valid.'],
    ], 422);
}

try {
    $ticketStmt = $pdo->prepare(
        'SELECT t.id, t.user_id, u.username, t.order_id, t.subject, t.category, t.priority, t.status, t.last_message_at, t.created_at, t.updated_at
         FROM tickets t
         INNER JOIN users u ON u.id = t.user_id
         WHERE t.id = :id
         LIMIT 1'
    );
    $ticketStmt->execute(['id' => $ticketId]);
    $ticket = $ticketStmt->fetch();
} catch (Throwable $e) {
    error_log('Ticket detail load failed: ' . $e->getMessage());
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Gagal memuat detail tiket.'],
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

try {
    $messagesStmt = $pdo->prepare(
        'SELECT tm.id, tm.ticket_id, tm.user_id, tm.sender_role, u.username, tm.message, tm.created_at
         FROM ticket_messages tm
         INNER JOIN users u ON u.id = tm.user_id
         WHERE tm.ticket_id = :ticket_id
         ORDER BY tm.id ASC'
    );
    $messagesStmt->execute(['ticket_id' => $ticketId]);
    $messages = $messagesStmt->fetchAll() ?: [];
} catch (Throwable $e) {
    error_log('Ticket detail messages failed: ' . $e->getMessage());
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Gagal memuat percakapan tiket.'],
    ], 500);
}

jsonResponse([
    'status' => true,
    'data' => [
        'ticket' => $ticket,
        'messages' => $messages,
        'is_admin' => $isAdmin,
    ],
]);
