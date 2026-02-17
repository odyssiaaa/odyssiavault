<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$user = requireAuth($pdo);
$input = getRequestInput();

$retryAfter = null;
if (!rateLimitAllow('cs_chat_send_' . (string)($user['id'] ?? 0), 90, 300, $retryAfter)) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Terlalu banyak kirim pesan. Tunggu sebentar lalu coba lagi.'],
    ], 429);
}

if (!ensureTicketTables($pdo)) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Fitur customer service sedang tidak tersedia.'],
    ], 500);
}

$message = trim((string)($input['message'] ?? ''));
if ($message === '') {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Pesan tidak boleh kosong.'],
    ], 422);
}

if (mb_strlen($message) > 2000) {
    $message = mb_substr($message, 0, 2000);
}

$ticketIdInput = sanitizeQuantity($input['ticket_id'] ?? 0);

try {
    $ticket = null;
    if ($ticketIdInput > 0) {
        $ticketStmt = $pdo->prepare(
            'SELECT id, user_id, subject, category, priority, status, last_message_at, created_at, updated_at
             FROM tickets
             WHERE id = :id
               AND user_id = :user_id
               AND category LIKE :category_like
             LIMIT 1'
        );
        $ticketStmt->execute([
            'id' => $ticketIdInput,
            'user_id' => (int)$user['id'],
            'category_like' => 'Customer Service Chat%',
        ]);
        $ticket = $ticketStmt->fetch();
    }

    if (!is_array($ticket)) {
        $ticket = ensureCsChatTicketForUser($pdo, $user, $config);
    }

    $ticketId = (int)($ticket['id'] ?? 0);
    if ($ticketId <= 0) {
        throw new RuntimeException('ID tiket CS tidak valid.');
    }

    $modeBefore = resolveCsChatMode($pdo, $ticket);
    if ($modeBefore === 'closed') {
        $ticket = createCsChatTicketForUser($pdo, $user, $config);
        $ticketId = (int)($ticket['id'] ?? 0);
        $modeBefore = 'bot';
    }

    $now = nowDateTime();
    $modeAfter = $modeBefore;

    $pdo->beginTransaction();

    $insertUserMessage = $pdo->prepare(
        'INSERT INTO ticket_messages (ticket_id, user_id, sender_role, message, created_at)
         VALUES (:ticket_id, :user_id, :sender_role, :message, :created_at)'
    );
    $insertUserMessage->execute([
        'ticket_id' => $ticketId,
        'user_id' => (int)$user['id'],
        'sender_role' => 'user',
        'message' => $message,
        'created_at' => $now,
    ]);

    $ticketStatus = 'open';

    if ($modeBefore !== 'admin') {
        $botAction = buildCsBotReply($message, $user, $config);
        $botReply = withCsBotPrefix((string)($botAction['reply'] ?? 'Pesan diterima.'));
        $botSenderId = resolveCsBotSenderId($pdo, (int)$user['id']);

        $insertBotMessage = $pdo->prepare(
            'INSERT INTO ticket_messages (ticket_id, user_id, sender_role, message, created_at)
             VALUES (:ticket_id, :user_id, :sender_role, :message, :created_at)'
        );
        $insertBotMessage->execute([
            'ticket_id' => $ticketId,
            'user_id' => $botSenderId,
            'sender_role' => 'admin',
            'message' => $botReply,
            'created_at' => $now,
        ]);

        if (!empty($botAction['takeover'])) {
            $modeAfter = 'admin';
            updateCsChatMode($pdo, $ticketId, 'admin', $now);
        } else {
            $modeAfter = 'bot';
            updateCsChatMode($pdo, $ticketId, 'bot', $now);
        }

        $ticketStatus = 'answered';
    } else {
        $modeAfter = 'admin';
        updateCsChatMode($pdo, $ticketId, 'admin', $now);
    }

    $updateTicket = $pdo->prepare(
        'UPDATE tickets
         SET status = :status, last_message_at = :last_message_at, updated_at = :updated_at
         WHERE id = :id'
    );
    $updateTicket->execute([
        'status' => $ticketStatus,
        'last_message_at' => $now,
        'updated_at' => $now,
        'id' => $ticketId,
    ]);

    $pdo->commit();

    $messages = loadCsChatMessages($pdo, $ticketId, 0, 150);
    $lastMessageId = 0;
    foreach ($messages as $row) {
        $rowId = (int)($row['id'] ?? 0);
        if ($rowId > $lastMessageId) {
            $lastMessageId = $rowId;
        }
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('CS chat send failed: ' . $e->getMessage());
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Gagal mengirim pesan ke customer service.'],
    ], 500);
}

jsonResponse([
    'status' => true,
    'data' => [
        'ticket_id' => $ticketId,
        'mode' => $modeAfter,
        'messages' => $messages,
        'last_message_id' => $lastMessageId,
    ],
]);

