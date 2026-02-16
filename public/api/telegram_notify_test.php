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

$disablePreviewRaw = readEnvValue('TELEGRAM_DISABLE_WEB_PAGE_PREVIEW', '');
$disablePreview = $disablePreviewRaw !== ''
    ? parseLooseBool($disablePreviewRaw, true)
    : parseLooseBool($tg['disable_web_page_preview'] ?? true, true);

$input = getRequestInput();
$customMessage = trim((string)($input['message'] ?? ''));
if ($customMessage === '') {
    $customMessage = 'Test notifikasi Telegram Odyssiavault pada ' . nowDateTime();
}
$message = trimTelegramMessage($customMessage, 3900);

$chatIdsRaw = trim(envValueOrConfig('TELEGRAM_ADMIN_CHAT_ID', (string)($tg['chat_id'] ?? '')));
$chatIds = normalizeTelegramChatIds($chatIdsRaw);
if ($chatIds === []) {
    $chatIds = discoverTelegramChatIds($botToken, $timeout);
}

if ($chatIds === []) {
    jsonResponse([
        'status' => false,
        'data' => [
            'msg' => 'Belum ada chat ID. Kirim /start ke bot dulu, lalu ulangi test.',
            'results' => [],
        ],
    ], 422);
}

$results = [];
$successCount = 0;
foreach ($chatIds as $chatId) {
    $chatId = (string)$chatId;
    $result = sendTelegramMessage($botToken, $chatId, $message, $disablePreview, $timeout);
    $ok = (bool)($result['ok'] ?? false);
    if ($ok) {
        $successCount++;
    }

    $results[] = [
        'chat_id' => $chatId,
        'ok' => $ok,
        'http_status' => (int)($result['status'] ?? 0),
        'description' => (string)($result['description'] ?? ''),
    ];
}

$allOk = $successCount > 0;
jsonResponse([
    'status' => $allOk,
    'data' => [
        'msg' => $allOk
            ? ('Berhasil kirim notifikasi test ke ' . $successCount . ' chat.')
            : 'Semua pengiriman test gagal. Cek chat_id/token.',
        'results' => $results,
    ],
], $allOk ? 200 : 500);
