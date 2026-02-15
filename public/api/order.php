<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$user = requireAuth($pdo);

if (!$client->isConfigured()) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Konfigurasi provider belum diisi (config/env).'],
    ], 500);
}

$input = getRequestInput();
$serviceId = (int)($input['service'] ?? 0);
$dataTarget = trim((string)($input['data'] ?? ''));

if ($serviceId <= 0 || $dataTarget === '') {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'service dan data wajib diisi'],
    ], 422);
}

$service = $client->serviceById($serviceId, 'services');

if ($service === null) {
    $servicesResult = $client->services('services');
    if (!($servicesResult['status'] ?? false)) {
        jsonResponse($servicesResult, 400);
    }

    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Layanan tidak ditemukan'],
    ], 404);
}

$combinedInfo = implode(' | ', [
    (string)($service['name'] ?? ''),
    (string)($service['note'] ?? ''),
    (string)($service['category'] ?? ''),
]);

$komen = (string)($input['komen'] ?? '');
$comments = (string)($input['comments'] ?? '');
$usernames = (string)($input['usernames'] ?? '');
$usernameField = trim((string)($input['username'] ?? ''));
$hashtags = trim((string)($input['hashtags'] ?? ''));
$keywords = trim((string)($input['keywords'] ?? ''));

$isCommentLike = containsAny($combinedInfo, ['commentlike', 'comment like']);
$isCommentRepliesType = containsAny($combinedInfo, ['comment replies', 'comment reply', 'replies', 'reply']);
$isCommentType = !$isCommentLike && (containsAny($combinedInfo, ['comment', 'komen', 'komentar']) || $isCommentRepliesType);
$isMentionType = containsAny($combinedInfo, ['mentions custom list', 'mention custom', 'custom list', 'mentions', 'usernames']);

$orderPayload = [
    'service' => $serviceId,
    'data' => $dataTarget,
];

if ($isMentionType) {
    $mentionLines = normalizeLines($usernames !== '' ? $usernames : $komen);
    if ($mentionLines === []) {
        jsonResponse([
            'status' => false,
            'data' => ['msg' => 'usernames wajib diisi untuk layanan mention custom list.'],
        ], 422);
    }

    $qty = count($mentionLines);
    $joinedMentions = implode("\n", $mentionLines);
    $orderPayload['usernames'] = $joinedMentions;
    if ($komen !== '') {
        $orderPayload['komen'] = $komen;
    }
} elseif ($isCommentType) {
    $sourceComments = $isCommentRepliesType
        ? ($comments !== '' ? $comments : $komen)
        : ($komen !== '' ? $komen : $comments);

    $commentLines = normalizeLines($sourceComments);
    if ($commentLines === []) {
        jsonResponse([
            'status' => false,
            'data' => ['msg' => 'Komentar wajib diisi untuk layanan ini.'],
        ], 422);
    }

    $qty = count($commentLines);
    $joinedComments = implode("\n", $commentLines);
    $orderPayload['komen'] = $joinedComments;
    $orderPayload['comments'] = $joinedComments;
} else {
    $qty = sanitizeQuantity($input['quantity'] ?? '');
}

if ($qty <= 0) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Jumlah pesan tidak sesuai'],
    ], 422);
}

$min = (int)($service['min'] ?? 0);
$max = (int)($service['max'] ?? PHP_INT_MAX);
if ($qty < $min || $qty > $max) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => "Jumlah harus antara {$min} - {$max}"],
    ], 422);
}

$orderPayload['quantity'] = $qty;
if ($komen !== '' && !isset($orderPayload['komen'])) {
    $orderPayload['komen'] = $komen;
}
if ($comments !== '' && !isset($orderPayload['comments'])) {
    $orderPayload['comments'] = $comments;
}
if ($usernames !== '' && !isset($orderPayload['usernames'])) {
    $orderPayload['usernames'] = $usernames;
}
if ($usernameField !== '') {
    $orderPayload['username'] = $usernameField;
}
if ($hashtags !== '') {
    $orderPayload['hashtags'] = $hashtags;
}
if ($keywords !== '') {
    $orderPayload['keywords'] = $keywords;
}

$buyPricePer1000 = (int)($service['price'] ?? 0);
$sellPricePer1000 = $pricing->sellPricePer1000($service);
$sellUnitPrice = round($sellPricePer1000 / 1000, 3);
$totalBuy = $pricing->totalSell($buyPricePer1000, $qty);
$totalSell = $pricing->totalSell($sellPricePer1000, $qty);
$profit = max(0, $totalSell - $totalBuy);

if ($buyPricePer1000 <= 0 || $sellPricePer1000 <= 0 || $totalSell <= 0) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Harga layanan tidak valid.'],
    ], 422);
}

$checkoutConfig = (array)($config['checkout'] ?? []);
$timeoutMinutes = max(60, min(180, (int)($checkoutConfig['unpaid_timeout_minutes'] ?? 180)));
$paymentMethods = checkoutPaymentMethods($config);

$nowTs = time();
$deadlineTs = $nowTs + ($timeoutMinutes * 60);
$now = date('Y-m-d H:i:s', $nowTs);
$deadline = date('Y-m-d H:i:s', $deadlineTs);

try {
    $insertOrderStmt = $pdo->prepare('INSERT INTO orders (user_id, provider_order_id, service_id, service_name, category, target, quantity, unit_buy_price, unit_sell_price, total_buy_price, total_sell_price, profit, status, payment_deadline_at, payload_json, provider_response_json, created_at, updated_at) VALUES (:user_id, :provider_order_id, :service_id, :service_name, :category, :target, :quantity, :unit_buy_price, :unit_sell_price, :total_buy_price, :total_sell_price, :profit, :status, :payment_deadline_at, :payload_json, :provider_response_json, :created_at, :updated_at)');
    $insertOrderStmt->execute([
        'user_id' => (int)$user['id'],
        'provider_order_id' => null,
        'service_id' => $serviceId,
        'service_name' => (string)($service['name'] ?? ''),
        'category' => (string)($service['category'] ?? 'Lainnya'),
        'target' => $dataTarget,
        'quantity' => $qty,
        'unit_buy_price' => $buyPricePer1000,
        'unit_sell_price' => $sellPricePer1000,
        'total_buy_price' => $totalBuy,
        'total_sell_price' => $totalSell,
        'profit' => $profit,
        'status' => 'Menunggu Pembayaran',
        'payment_deadline_at' => $deadline,
        'payload_json' => json_encode($orderPayload, JSON_UNESCAPED_UNICODE),
        'provider_response_json' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $localOrderId = (int)$pdo->lastInsertId();

    jsonResponse([
        'status' => true,
        'data' => [
            'msg' => 'Checkout berhasil. Silakan lakukan pembayaran dan konfirmasi agar admin memproses order.',
            'order_id' => $localOrderId,
            'service' => [
                'id' => $serviceId,
                'name' => (string)($service['name'] ?? ''),
            ],
            'quantity' => $qty,
            'sell_price_per_1000' => $sellPricePer1000,
            'sell_unit_price' => $sellUnitPrice,
            'total_sell_price' => $totalSell,
            'target' => $dataTarget,
            'status' => 'Menunggu Pembayaran',
            'payment_deadline_at' => $deadline,
            'payment_methods' => $paymentMethods,
        ],
    ]);
} catch (Throwable $e) {
    error_log('Order checkout failed: ' . $e->getMessage());
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Gagal membuat checkout. Silakan coba lagi.'],
    ], 500);
}
