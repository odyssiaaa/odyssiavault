<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$user = requireAuth($pdo);
requireAdmin($user);

$limitRaw = sanitizeQuantity($_GET['limit'] ?? 120);
$limit = max(20, min(500, $limitRaw > 0 ? $limitRaw : 120));

$logPath = telegramLogPath();
if (!is_file($logPath)) {
    jsonResponse([
        'status' => true,
        'data' => [
            'msg' => 'Belum ada log notifikasi Telegram.',
            'lines' => [],
            'path' => 'storage/logs/telegram_notify.log',
        ],
    ]);
}

$content = @file($logPath, FILE_IGNORE_NEW_LINES);
if (!is_array($content)) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Gagal membaca log Telegram.'],
    ], 500);
}

$total = count($content);
$lines = $total > $limit ? array_slice($content, -$limit) : $content;

jsonResponse([
    'status' => true,
    'data' => [
        'msg' => 'Log notifikasi Telegram berhasil dimuat.',
        'lines' => $lines,
        'count' => count($lines),
        'total' => $total,
        'path' => 'storage/logs/telegram_notify.log',
    ],
]);

