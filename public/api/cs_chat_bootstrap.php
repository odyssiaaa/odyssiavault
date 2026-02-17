<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$user = requireAuth($pdo);

$retryAfter = null;
if (!rateLimitAllow('cs_chat_bootstrap_' . (string)($user['id'] ?? 0), 120, 300, $retryAfter)) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Terlalu banyak permintaan chat. Coba lagi sebentar.'],
    ], 429);
}

if (!ensureTicketTables($pdo)) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Fitur customer service sedang tidak tersedia.'],
    ], 500);
}

try {
    $ticket = ensureCsChatTicketForUser($pdo, $user, $config);
    $ticketId = (int)($ticket['id'] ?? 0);
    if ($ticketId <= 0) {
        throw new RuntimeException('ID tiket CS tidak valid.');
    }

    $messages = loadCsChatMessages($pdo, $ticketId, 0, 150);
    $lastMessageId = 0;
    foreach ($messages as $message) {
        $messageId = (int)($message['id'] ?? 0);
        if ($messageId > $lastMessageId) {
            $lastMessageId = $messageId;
        }
    }

    $mode = resolveCsChatMode($pdo, $ticket);
} catch (Throwable $e) {
    error_log('CS chat bootstrap failed: ' . $e->getMessage());
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Gagal memuat chat customer service.'],
    ], 500);
}

jsonResponse([
    'status' => true,
    'data' => [
        'ticket' => $ticket,
        'mode' => $mode,
        'messages' => $messages,
        'last_message_id' => $lastMessageId,
    ],
]);

