<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$user = requireAuth($pdo);

if (!$client->isConfigured()) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Konfigurasi server layanan belum diisi (config/env).'],
    ], 500);
}

$input = getRequestInput();
$retryAfter = null;
if (!rateLimitAllow('order_checkout', 20, 60, $retryAfter)) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Permintaan terlalu cepat. Tunggu sebentar lalu coba lagi.'],
    ], 429);
}

$serviceId = (int)($input['service'] ?? 0);
$dataTarget = trim((string)($input['data'] ?? ''));

if ($serviceId <= 0 || $dataTarget === '') {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'service dan data wajib diisi'],
    ], 422);
}

$providerConfig = (array)($config['provider'] ?? []);
$allowedVariants = ['services', 'services_1', 'services2', 'services3'];
$preferredVariant = mb_strtolower(trim((string)($providerConfig['services_variant'] ?? 'services_1')));
if (!in_array($preferredVariant, $allowedVariants, true)) {
    $preferredVariant = 'services_1';
}

$variants = array_values(array_unique(array_merge([$preferredVariant], $allowedVariants)));

$service = null;
foreach ($variants as $variant) {
    $candidate = $client->serviceById($serviceId, $variant);
    if (is_array($candidate)) {
        $service = $candidate;
        break;
    }
}

if ($service === null) {
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

$paymentGatewayConfig = (array)($config['payment_gateway'] ?? []);
$gatewayEnabled = parseLooseBool($paymentGatewayConfig['enabled'] ?? false, false);
$gatewayProvider = mb_strtolower(trim((string)($paymentGatewayConfig['provider'] ?? '')));

$decodeGatewayJson = static function (array $result): array {
    $status = (int)($result['status'] ?? 0);
    $body = (string)($result['body'] ?? '');
    $decoded = json_decode($body, true);
    return [
        'ok' => ($result['ok'] ?? false) === true,
        'http_status' => $status,
        'body' => $body,
        'json' => is_array($decoded) ? $decoded : null,
    ];
};

$createPakasirTransaction = static function (int $localOrderId, int $orderAmount) use ($paymentGatewayConfig, $decodeGatewayJson): array {
    $apiKey = trim((string)($paymentGatewayConfig['pakasir_api_key'] ?? ''));
    $project = trim((string)($paymentGatewayConfig['pakasir_project_slug'] ?? ''));
    $method = mb_strtolower(trim((string)($paymentGatewayConfig['pakasir_method'] ?? 'qris')));
    if ($method === '') {
        $method = 'qris';
    }

    $baseUrl = rtrim(trim((string)($paymentGatewayConfig['pakasir_base_url'] ?? 'https://app.pakasir.com')), '/');
    if ($baseUrl === '') {
        $baseUrl = 'https://app.pakasir.com';
    }

    $timeout = max(6, min(40, (int)($paymentGatewayConfig['pakasir_timeout'] ?? 20)));
    $minAmount = max(1, (int)($paymentGatewayConfig['pakasir_min_amount'] ?? 500));
    $qrisOnly = parseLooseBool($paymentGatewayConfig['pakasir_qris_only'] ?? true, true);

    if ($apiKey === '' || $project === '') {
        return [
            'status' => false,
            'msg' => 'Konfigurasi Pakasir belum lengkap (api key / project slug).',
        ];
    }

    if ($orderAmount < $minAmount) {
        return [
            'status' => false,
            'msg' => 'Minimum pembayaran Pakasir QRIS adalah Rp ' . number_format($minAmount, 0, ',', '.') . '. Silakan naikkan jumlah order.',
        ];
    }

    $payload = [
        'project' => $project,
        'order_id' => (string)$localOrderId,
        'amount' => max(1, $orderAmount),
        'api_key' => $apiKey,
    ];

    $endpoints = [
        $baseUrl . '/api/transactioncreate/' . rawurlencode($method),
        $baseUrl . '/api/v1/merchant/transactioncreate/' . rawurlencode($method),
    ];

    $lastMessage = 'Gagal membuat transaksi Pakasir.';
    foreach ($endpoints as $endpoint) {
        $jsonResult = $decodeGatewayJson(httpPostJson($endpoint, $payload, [], $timeout));
        $jsonData = is_array($jsonResult['json']) ? $jsonResult['json'] : [];

        if (!($jsonResult['ok'] ?? false) || $jsonData === []) {
            $formResult = $decodeGatewayJson(httpPostUrlEncoded($endpoint, $payload, [], $timeout));
            $jsonData = is_array($formResult['json']) ? $formResult['json'] : [];
            if (!($formResult['ok'] ?? false) || $jsonData === []) {
                $candidateMsg = trim((string)($jsonData['message'] ?? $jsonData['msg'] ?? ''));
                if ($candidateMsg === '') {
                    $candidateMsg = 'Gagal koneksi ke Pakasir (HTTP ' . (int)($formResult['http_status'] ?? 0) . ').';
                }
                $lastMessage = $candidateMsg;
                continue;
            }
        }

        $statusText = mb_strtolower(trim((string)($jsonData['status'] ?? '')));
        $payment = [];
        if (isset($jsonData['payment']) && is_array($jsonData['payment'])) {
            $payment = $jsonData['payment'];
        } elseif (isset($jsonData['transaction']) && is_array($jsonData['transaction'])) {
            $payment = $jsonData['transaction'];
        } elseif (
            isset($jsonData['payment_number'], $jsonData['amount'], $jsonData['order_id']) &&
            is_scalar($jsonData['payment_number']) &&
            is_scalar($jsonData['amount']) &&
            is_scalar($jsonData['order_id'])
        ) {
            $payment = $jsonData;
        }

        // Pakasir docs (2026) dapat merespons tanpa field "status",
        // jadi kita anggap valid jika object payment/transaction ada dan lengkap.
        if ($statusText !== '' && !in_array($statusText, ['success', 'ok', 'true'], true) && $payment === []) {
            $candidateMsg = trim((string)($jsonData['message'] ?? $jsonData['msg'] ?? ''));
            if ($candidateMsg !== '') {
                $lastMessage = $candidateMsg;
            }
            continue;
        }

        if ($payment === []) {
            $lastMessage = 'Response Pakasir tidak memuat data payment.';
            continue;
        }

        $amount = max(0, sanitizeQuantity($payment['amount'] ?? $orderAmount));
        $totalPayment = max($amount, sanitizeQuantity($payment['total_payment'] ?? $amount));
        $fee = max(0, sanitizeQuantity($payment['fee'] ?? max(0, $totalPayment - $amount)));
        $paymentNumber = trim((string)($payment['payment_number'] ?? ''));
        $gatewayOrderId = trim((string)($payment['order_id'] ?? $localOrderId));
        if ($gatewayOrderId === '') {
            $gatewayOrderId = (string)$localOrderId;
        }

        $expiredAtRaw = trim((string)($payment['expired_at'] ?? ''));
        $expiredAt = '';
        if ($expiredAtRaw !== '' && strtotime($expiredAtRaw) !== false) {
            $expiredAt = date('Y-m-d H:i:s', strtotime($expiredAtRaw));
        }

        $payUrl = $baseUrl . '/pay/' . rawurlencode($project) . '/' . max(1, $amount) . '?order_id=' . rawurlencode($gatewayOrderId);
        if ($qrisOnly) {
            $payUrl .= '&qris_only=1';
        }

        $qrImageUrl = $paymentNumber !== ''
            ? ('https://quickchart.io/qr?size=420&text=' . rawurlencode($paymentNumber))
            : '';

        return [
            'status' => true,
            'data' => [
                'provider' => 'pakasir',
                'project' => $project,
                'method' => (string)($payment['payment_method'] ?? strtoupper($method)),
                'order_id' => $gatewayOrderId,
                'status' => (string)($payment['status'] ?? 'pending'),
                'amount' => $amount,
                'fee' => $fee,
                'total_payment' => $totalPayment,
                'payment_number' => $paymentNumber,
                'expired_at' => $expiredAt,
                'created_at' => trim((string)($payment['created_at'] ?? $payment['completed_at'] ?? '')),
                'pay_url' => $payUrl,
                'qr_image_url' => $qrImageUrl,
            ],
        ];
    }

    return [
        'status' => false,
        'msg' => $lastMessage,
    ];
};

try {
    $pdo->beginTransaction();

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
    $gatewayPayment = null;
    $paymentMethodName = 'QRIS';

    if ($gatewayEnabled && $gatewayProvider === 'pakasir') {
        $gatewayResult = $createPakasirTransaction($localOrderId, $totalSell);
        if (!($gatewayResult['status'] ?? false)) {
            $pdo->rollBack();
            jsonResponse([
                'status' => false,
                'data' => [
                    'msg' => (string)($gatewayResult['msg'] ?? 'Gagal membuat transaksi Pakasir.'),
                ],
            ], 502);
        }

        $gatewayPayment = (array)($gatewayResult['data'] ?? []);
        $paymentMethodName = 'Pakasir QRIS';

        $gatewayDeadline = trim((string)($gatewayPayment['expired_at'] ?? ''));
        if ($gatewayDeadline !== '' && strtotime($gatewayDeadline) !== false) {
            $deadline = date('Y-m-d H:i:s', strtotime($gatewayDeadline));
        }

        $gatewayNotePayload = json_encode([
            'gateway' => 'pakasir',
            'project' => (string)($gatewayPayment['project'] ?? ''),
            'pay_url' => (string)($gatewayPayment['pay_url'] ?? ''),
            'method' => (string)($gatewayPayment['method'] ?? ''),
            'total_payment' => (int)($gatewayPayment['total_payment'] ?? 0),
            'fee' => (int)($gatewayPayment['fee'] ?? 0),
        ], JSON_UNESCAPED_UNICODE);
        if (!is_string($gatewayNotePayload)) {
            $gatewayNotePayload = '[PAKASIR] gateway metadata unavailable';
        }

        $updateGatewayStmt = $pdo->prepare('UPDATE orders SET payment_deadline_at = :payment_deadline_at, payment_method = :payment_method, payment_channel_name = :payment_channel_name, payment_account_name = :payment_account_name, payment_account_number = :payment_account_number, payment_reference = :payment_reference, payment_note = :payment_note, updated_at = :updated_at WHERE id = :id');
        $updateGatewayStmt->execute([
            'payment_deadline_at' => $deadline,
            'payment_method' => 'pakasir',
            'payment_channel_name' => 'Pakasir QRIS',
            'payment_account_name' => 'Pakasir',
            'payment_account_number' => 'Lihat QR Pakasir',
            'payment_reference' => mb_substr((string)($gatewayPayment['order_id'] ?? (string)$localOrderId), 0, 120),
            'payment_note' => mb_substr($gatewayNotePayload, 0, 2000),
            'updated_at' => $now,
            'id' => $localOrderId,
        ]);
    }

    $pdo->commit();

    notifyAdminPendingPaymentChannels($config, [
        'order_id' => $localOrderId,
        'username' => (string)($user['username'] ?? ''),
        'service_name' => (string)($service['name'] ?? ''),
        'target' => $dataTarget,
        'quantity' => $qty,
        'total_sell_price' => $totalSell,
        'payment_method_name' => $paymentMethodName,
        'payment_state' => 'Belum konfirmasi',
        'payer_name' => '',
        'payment_reference' => '',
        'confirmed_at' => $now,
    ]);

    jsonResponse([
        'status' => true,
        'data' => [
            'msg' => $gatewayPayment !== null
                ? 'Checkout berhasil. Lanjutkan pembayaran via Pakasir QRIS.'
                : 'Checkout berhasil. Silakan lakukan pembayaran dan konfirmasi agar admin memproses order.',
            'order_type' => 'ssm',
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
            'payment_gateway' => $gatewayPayment,
        ],
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Order checkout failed: ' . $e->getMessage());
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Gagal membuat checkout. Silakan coba lagi.'],
    ], 500);
}
