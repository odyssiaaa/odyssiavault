<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$user = requireAuth($pdo);
requireAdmin($user);

$notifications = (array)($config['notifications'] ?? []);
$tg = (isset($notifications['telegram']) && is_array($notifications['telegram']))
    ? (array)$notifications['telegram']
    : $notifications;

$botToken = trim(envValueOrConfig('TELEGRAM_BOT_TOKEN', (string)($tg['bot_token'] ?? '')));
if ($botToken === '') {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Token Telegram belum diisi.'],
    ], 422);
}

$timeoutRaw = envValueOrConfig('TELEGRAM_TIMEOUT', (string)($tg['timeout'] ?? '6'));
$timeout = (int)$timeoutRaw;
if ($timeout <= 0) {
    $timeout = 6;
}

$chatIds = discoverTelegramChatIds($botToken, $timeout);
jsonResponse([
    'status' => true,
    'data' => [
        'chat_ids' => $chatIds,
        'count' => count($chatIds),
        'msg' => $chatIds !== []
            ? 'Chat ID ditemukan dari getUpdates.'
            : 'Belum ada chat ID. Kirim /start ke bot dulu, lalu coba lagi.',
    ],
]);

