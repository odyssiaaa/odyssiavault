<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$user = requireAuth($pdo);

$retryAfter = null;
if (!rateLimitAllow('cs_chat_poll_' . (string)($user['id'] ?? 0), 240, 300, $retryAfter)) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Terlalu sering refresh chat.'],
    ], 429);
}

if (!ensureTicketTables($pdo)) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Fitur customer service sedang tidak tersedia.'],
    ], 500);
}

$ticketId = sanitizeQuantity($_GET['ticket_id'] ?? ($_POST['ticket_id'] ?? 0));
$afterId = sanitizeQuantity($_GET['after_id'] ?? ($_POST['after_id'] ?? 0));

try {
    $ticket = null;
    if ($ticketId > 0) {
        $ticketStmt = $pdo->prepare(
            'SELECT id, user_id, subject, category, priority, status, last_message_at, created_at, updated_at
             FROM tickets
             WHERE id = :id
               AND user_id = :user_id
               AND category LIKE :category_like
             LIMIT 1'
        );
        $ticketStmt->execute([
            'id' => $ticketId,
            'user_id' => (int)$user['id'],
            'category_like' => 'Customer Service Chat%',
        ]);
        $ticket = $ticketStmt->fetch();
    }

    if (!is_array($ticket)) {
        $ticket = ensureCsChatTicketForUser($pdo, $user, $config);
        $ticketId = (int)($ticket['id'] ?? 0);
    }

    if ($ticketId <= 0) {
        throw new RuntimeException('ID tiket CS tidak valid.');
    }

    $mode = resolveCsChatMode($pdo, $ticket);
    $messages = loadCsChatMessages($pdo, $ticketId, $afterId, 150);
    $lastMessageId = $afterId;
    foreach ($messages as $message) {
        $messageId = (int)($message['id'] ?? 0);
        if ($messageId > $lastMessageId) {
            $lastMessageId = $messageId;
        }
    }
} catch (Throwable $e) {
    error_log('CS chat poll failed: ' . $e->getMessage());
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Gagal memuat update chat customer service.'],
    ], 500);
}

jsonResponse([
    'status' => true,
    'data' => [
        'ticket_id' => $ticketId,
        'mode' => $mode,
        'messages' => $messages,
        'last_message_id' => $lastMessageId,
    ],
]);

