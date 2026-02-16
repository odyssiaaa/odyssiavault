<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$gatewayConfig = (array)($config['payment_gateway'] ?? []);
$enabled = parseLooseBool($gatewayConfig['enabled'] ?? false, false);
if (!$enabled) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Webhook payment gateway tidak aktif.'],
    ], 404);
}

$extractHeader = static function (string $name): string {
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', trim($name)));
    $value = $_SERVER[$key] ?? '';
    if (!is_string($value)) {
        return '';
    }

    return trim($value);
};

$rawBody = file_get_contents('php://input');
if (!is_string($rawBody)) {
    $rawBody = '';
}

$input = [];
if (is_array($_POST) && $_POST !== []) {
    $input = array_merge($input, $_POST);
}

$contentType = mb_strtolower(trim((string)($_SERVER['CONTENT_TYPE'] ?? '')));
if (str_contains($contentType, 'application/json')) {
    $decoded = json_decode($rawBody, true);
    if (is_array($decoded)) {
        $input = array_merge($input, $decoded);
    }
} elseif ($rawBody !== '') {
    $decoded = json_decode($rawBody, true);
    if (is_array($decoded)) {
        $input = array_merge($input, $decoded);
    } else {
        $formData = [];
        parse_str($rawBody, $formData);
        if (is_array($formData) && $formData !== []) {
            $input = array_merge($input, $formData);
        }
    }
}

$pickValue = static function (array $source, array $keys): string {
    $containers = [$source];
    foreach (['data', 'result', 'payload', 'transaction'] as $childKey) {
        if (isset($source[$childKey]) && is_array($source[$childKey])) {
            $containers[] = $source[$childKey];
        }
    }

    foreach ($containers as $container) {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $container)) {
                continue;
            }

            $value = $container[$key];
            if (is_scalar($value)) {
                $text = trim((string)$value);
                if ($text !== '') {
                    return $text;
                }
            }
        }
    }

    return '';
};

$providerLabel = trim((string)($gatewayConfig['provider'] ?? 'gateway'));
if ($providerLabel === '') {
    $providerLabel = 'gateway';
}
$providerKey = mb_strtolower($providerLabel);

if ($providerKey === 'tripay') {
    $tripayPrivateKey = trim((string)($gatewayConfig['tripay_private_key'] ?? ''));
    if ($tripayPrivateKey === '') {
        jsonResponse([
            'status' => false,
            'data' => ['msg' => 'Tripay private key belum diisi.'],
        ], 500);
    }

    $incomingSignature = $extractHeader('X-Callback-Signature');
    if ($incomingSignature === '') {
        $incomingSignature = $pickValue($input, ['signature', 'x_callback_signature']);
    }
    $expectedSignature = hash_hmac('sha256', $rawBody, $tripayPrivateKey);
    if ($incomingSignature === '' || !hash_equals($expectedSignature, $incomingSignature)) {
        jsonResponse([
            'status' => false,
            'data' => ['msg' => 'Signature callback Tripay tidak valid.'],
        ], 401);
    }

    $callbackEvent = mb_strtolower($extractHeader('X-Callback-Event'));
    if ($callbackEvent !== '' && $callbackEvent !== 'payment_status') {
        jsonResponse([
            'status' => true,
            'data' => ['msg' => 'Callback event diabaikan.', 'event' => $callbackEvent],
        ]);
    }
} elseif ($providerKey === 'midtrans') {
    $midtransServerKey = trim((string)($gatewayConfig['midtrans_server_key'] ?? ''));
    if ($midtransServerKey === '') {
        jsonResponse([
            'status' => false,
            'data' => ['msg' => 'Midtrans server key belum diisi.'],
        ], 500);
    }

    $midtransOrderId = $pickValue($input, ['order_id']);
    $midtransStatusCode = $pickValue($input, ['status_code']);
    $midtransGrossAmount = $pickValue($input, ['gross_amount']);
    $midtransSignature = mb_strtolower($pickValue($input, ['signature_key']));
    $midtransExpected = mb_strtolower(hash('sha512', $midtransOrderId . $midtransStatusCode . $midtransGrossAmount . $midtransServerKey));
    if ($midtransOrderId === '' || $midtransStatusCode === '' || $midtransGrossAmount === '' || $midtransSignature === '' || !hash_equals($midtransExpected, $midtransSignature)) {
        jsonResponse([
            'status' => false,
            'data' => ['msg' => 'Signature callback Midtrans tidak valid.'],
        ], 401);
    }
} elseif ($providerKey === 'xendit') {
    $xenditToken = trim((string)($gatewayConfig['xendit_callback_token'] ?? ''));
    if ($xenditToken === '') {
        jsonResponse([
            'status' => false,
            'data' => ['msg' => 'Xendit callback token belum diisi.'],
        ], 500);
    }

    $incomingToken = $extractHeader('X-Callback-Token');
    if ($incomingToken === '' && isset($input['x_callback_token'])) {
        $incomingToken = trim((string)$input['x_callback_token']);
    }
    if ($incomingToken === '' || !hash_equals($xenditToken, $incomingToken)) {
        jsonResponse([
            'status' => false,
            'data' => ['msg' => 'Callback token Xendit tidak valid.'],
        ], 401);
    }
} else {
    $webhookSecret = trim((string)($gatewayConfig['webhook_secret'] ?? ''));
    if ($webhookSecret !== '') {
        $incomingSecret = $pickValue($input, ['secret', 'webhook_secret', 'token']);
        if ($incomingSecret === '') {
            $incomingSecret = $extractHeader('X-Webhook-Secret');
        }
        if ($incomingSecret === '' && isset($_GET['secret'])) {
            $incomingSecret = trim((string)$_GET['secret']);
        }

        if ($incomingSecret === '' || !hash_equals($webhookSecret, $incomingSecret)) {
            jsonResponse([
                'status' => false,
                'data' => ['msg' => 'Secret webhook tidak valid.'],
            ], 401);
        }
    }
}

$orderIdKeys = isset($gatewayConfig['order_id_keys']) && is_array($gatewayConfig['order_id_keys'])
    ? $gatewayConfig['order_id_keys']
    : ['order_id', 'merchant_ref', 'external_id', 'reference'];
$orderIdRaw = $pickValue($input, $orderIdKeys);
$orderId = sanitizeQuantity($orderIdRaw);
if ($orderId <= 0) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'order_id tidak ditemukan di payload callback.'],
    ], 422);
}

$statusRaw = $pickValue($input, ['status', 'payment_status', 'transaction_status', 'result', 'state']);
$statusNormalized = mb_strtoupper(trim($statusRaw));
$successStatuses = isset($gatewayConfig['success_statuses']) && is_array($gatewayConfig['success_statuses'])
    ? $gatewayConfig['success_statuses']
    : ['PAID', 'SETTLEMENT', 'SUCCESS'];
$successSet = [];
foreach ($successStatuses as $status) {
    $key = mb_strtoupper(trim((string)$status));
    if ($key === '') {
        continue;
    }
    $successSet[$key] = true;
}
if ($successSet === []) {
    $successSet = ['PAID' => true, 'SETTLEMENT' => true, 'SUCCESS' => true];
}
if ($providerKey === 'midtrans') {
    $successSet['CAPTURE'] = true;
}
if ($providerKey === 'xendit') {
    $successSet['SUCCEEDED'] = true;
    $successSet['COMPLETED'] = true;
}
if ($providerKey === 'pakasir') {
    $successSet['COMPLETED'] = true;
}

$fraudStatus = mb_strtoupper(trim($pickValue($input, ['fraud_status'])));
if ($providerKey === 'midtrans' && $statusNormalized === 'CAPTURE' && $fraudStatus !== '' && $fraudStatus !== 'ACCEPT') {
    jsonResponse([
        'status' => true,
        'data' => [
            'msg' => 'Callback capture Midtrans belum accepted.',
            'order_id' => $orderId,
            'fraud_status' => $fraudStatus,
        ],
    ]);
}

if ($statusNormalized !== '' && !isset($successSet[$statusNormalized])) {
    jsonResponse([
        'status' => true,
        'data' => [
            'msg' => 'Callback diterima, status belum paid.',
            'order_id' => $orderId,
            'gateway_status' => $statusRaw,
        ],
    ]);
}
$now = nowDateTime();

$paymentMethod = $pickValue($input, ['payment_method', 'payment_type', 'channel', 'payment_channel', 'method']);
$paymentReference = $pickValue($input, ['reference', 'trx_id', 'transaction_id', 'payment_reference']);
$payerName = $pickValue($input, ['payer_name', 'customer_name', 'buyer_name', 'name']);

$pdo->beginTransaction();
try {
    $orderStmt = $pdo->prepare(
        'SELECT o.id, o.user_id, o.status, o.service_name, o.target, o.quantity, o.total_sell_price, o.payload_json, o.payment_note, u.username
         FROM orders o
         INNER JOIN users u ON u.id = o.user_id
         WHERE o.id = :id
         LIMIT 1
         FOR UPDATE'
    );
    $orderStmt->execute(['id' => $orderId]);
    $order = $orderStmt->fetch();

    if (!is_array($order)) {
        $pdo->rollBack();
        jsonResponse([
            'status' => false,
            'data' => ['msg' => 'Order tidak ditemukan.'],
        ], 404);
    }

    $currentStatus = trim((string)($order['status'] ?? ''));
    if ($currentStatus !== 'Menunggu Pembayaran') {
        $pdo->commit();
        jsonResponse([
            'status' => true,
            'data' => [
                'msg' => 'Order sudah diproses sebelumnya.',
                'order_id' => $orderId,
                'status' => $currentStatus,
            ],
        ]);
    }

    if ($providerKey === 'pakasir') {
        $pakasirApiKey = trim((string)($gatewayConfig['pakasir_api_key'] ?? ''));
        $pakasirProject = trim((string)($gatewayConfig['pakasir_project_slug'] ?? ''));
        $pakasirBaseUrl = rtrim(trim((string)($gatewayConfig['pakasir_base_url'] ?? 'https://app.pakasir.com')), '/');
        if ($pakasirBaseUrl === '') {
            $pakasirBaseUrl = 'https://app.pakasir.com';
        }

        if ($pakasirApiKey === '' || $pakasirProject === '') {
            $pdo->rollBack();
            jsonResponse([
                'status' => false,
                'data' => ['msg' => 'Konfigurasi Pakasir belum lengkap (api key/project slug).'],
            ], 500);
        }

        $incomingProject = trim($pickValue($input, ['project']));
        if ($incomingProject !== '' && strcasecmp($incomingProject, $pakasirProject) !== 0) {
            $pdo->rollBack();
            jsonResponse([
                'status' => false,
                'data' => ['msg' => 'Project callback Pakasir tidak sesuai.'],
            ], 401);
        }

        $detailAmount = sanitizeQuantity($pickValue($input, ['amount', 'gross_amount', 'total', 'total_amount']));
        if ($detailAmount <= 0) {
            $detailAmount = (int)($order['total_sell_price'] ?? 0);
        }
        if ($detailAmount <= 0) {
            $detailAmount = 1;
        }

        $detailUrl = $pakasirBaseUrl . '/api/transactiondetail?' . http_build_query([
            'project' => $pakasirProject,
            'amount' => $detailAmount,
            'order_id' => (string)$orderId,
            'api_key' => $pakasirApiKey,
        ]);
        $detailResult = httpGet($detailUrl, [], 18);
        if (!($detailResult['ok'] ?? false)) {
            $pdo->rollBack();
            jsonResponse([
                'status' => false,
                'data' => ['msg' => 'Gagal validasi callback Pakasir (detail request failed).'],
            ], 502);
        }

        $detailBody = (string)($detailResult['body'] ?? '');
        $detailJson = json_decode($detailBody, true);
        if (!is_array($detailJson)) {
            $pdo->rollBack();
            jsonResponse([
                'status' => false,
                'data' => ['msg' => 'Gagal validasi callback Pakasir (detail response invalid).'],
            ], 502);
        }

        $detailStatus = mb_strtolower(trim((string)($detailJson['status'] ?? '')));
        $detailTxn = [];
        if (isset($detailJson['transaction']) && is_array($detailJson['transaction'])) {
            $detailTxn = $detailJson['transaction'];
        } elseif (isset($detailJson['payment']) && is_array($detailJson['payment'])) {
            $detailTxn = $detailJson['payment'];
        }
        $detailTxnStatus = mb_strtoupper(trim((string)($detailTxn['status'] ?? '')));
        $detailOrderId = trim((string)($detailTxn['order_id'] ?? ''));
        $allowedTxnStatuses = ['COMPLETED', 'SUCCESS', 'PAID', 'SETTLEMENT'];
        $detailStatusLooksError = $detailStatus !== '' && !in_array($detailStatus, ['success', 'ok', 'true'], true);

        if ($detailStatusLooksError || $detailTxn === [] || $detailOrderId === '' || (string)sanitizeQuantity($detailOrderId) !== (string)$orderId || !in_array($detailTxnStatus, $allowedTxnStatuses, true)) {
            $pdo->rollBack();
            jsonResponse([
                'status' => false,
                'data' => ['msg' => 'Validasi callback Pakasir gagal (status transaksi tidak valid).'],
            ], 401);
        }

        if ($statusNormalized === '') {
            $statusNormalized = $detailTxnStatus;
            $statusRaw = $detailTxnStatus;
        }
        if ($paymentMethod === '') {
            $paymentMethod = trim((string)($detailTxn['payment_method'] ?? 'Pakasir QRIS'));
        }
        if ($paymentReference === '') {
            $paymentReference = mb_substr($detailOrderId, 0, 120);
        }
    }

    if (!$client->isConfigured()) {
        $pdo->rollBack();
        jsonResponse([
            'status' => false,
            'data' => ['msg' => 'Konfigurasi server layanan belum diisi.'],
        ], 500);
    }

    $payload = json_decode((string)($order['payload_json'] ?? ''), true);
    if (!is_array($payload) || $payload === []) {
        $pdo->rollBack();
        jsonResponse([
            'status' => false,
            'data' => ['msg' => 'Payload order server layanan tidak valid.'],
        ], 500);
    }

    $providerResult = $client->order($payload);
    if (!($providerResult['status'] ?? false)) {
        $providerMsg = trim((string)($providerResult['data']['msg'] ?? 'Server layanan gagal memproses order.'));
        if ($providerMsg === '') {
            $providerMsg = 'Server layanan gagal memproses order.';
        }

        $existingNote = trim((string)($order['payment_note'] ?? ''));
        $webhookNote = '[' . strtoupper($providerLabel) . '] Payment callback accepted, provider order failed: ' . $providerMsg;
        $mergedNote = $existingNote !== '' ? ($existingNote . "\n" . $webhookNote) : $webhookNote;

        $failedUpdateStmt = $pdo->prepare(
            'UPDATE orders
             SET status = :status,
                 error_message = :error_message,
                 payment_method = :payment_method,
                 payment_channel_name = :payment_channel_name,
                 payment_payer_name = :payment_payer_name,
                 payment_reference = :payment_reference,
                 payment_note = :payment_note,
                 payment_confirmed_at = COALESCE(payment_confirmed_at, :payment_confirmed_at),
                 payment_confirmed_by_admin_at = COALESCE(payment_confirmed_by_admin_at, :payment_confirmed_by_admin_at),
                 provider_response_json = :provider_response_json,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $failedUpdateStmt->execute([
            'status' => 'Error',
            'error_message' => mb_substr($providerMsg, 0, 1000),
            'payment_method' => 'gateway',
            'payment_channel_name' => $paymentMethod !== '' ? mb_substr($paymentMethod, 0, 120) : strtoupper($providerLabel),
            'payment_payer_name' => $payerName !== '' ? mb_substr($payerName, 0, 120) : null,
            'payment_reference' => $paymentReference !== '' ? mb_substr($paymentReference, 0, 120) : null,
            'payment_note' => mb_substr($mergedNote, 0, 2000),
            'payment_confirmed_at' => $now,
            'payment_confirmed_by_admin_at' => $now,
            'provider_response_json' => json_encode($providerResult, JSON_UNESCAPED_UNICODE),
            'updated_at' => $now,
            'id' => $orderId,
        ]);

        $pdo->commit();
        jsonResponse([
            'status' => false,
            'data' => [
                'msg' => $providerMsg,
                'order_id' => $orderId,
                'status' => 'Error',
            ],
        ], 400);
    }

    $providerOrderId = trim((string)($providerResult['data']['id'] ?? ''));
    if ($providerOrderId === '') {
        $pdo->rollBack();
        jsonResponse([
            'status' => false,
            'data' => ['msg' => 'Provider tidak mengembalikan ID order.'],
        ], 400);
    }

    $paymentChannelName = $paymentMethod !== '' ? $paymentMethod : strtoupper($providerLabel);
    $gatewayNote = '[' . strtoupper($providerLabel) . '] Payment callback accepted' . ($statusRaw !== '' ? (' (' . $statusRaw . ')') : '');

    $successUpdateStmt = $pdo->prepare(
        'UPDATE orders
         SET provider_order_id = :provider_order_id,
             status = :status,
             provider_status = :provider_status,
             payment_method = :payment_method,
             payment_channel_name = :payment_channel_name,
             payment_payer_name = :payment_payer_name,
             payment_reference = :payment_reference,
             payment_note = :payment_note,
             payment_confirmed_at = COALESCE(payment_confirmed_at, :payment_confirmed_at),
             payment_confirmed_by_admin_at = COALESCE(payment_confirmed_by_admin_at, :payment_confirmed_by_admin_at),
             provider_response_json = :provider_response_json,
             updated_at = :updated_at
         WHERE id = :id'
    );
    $successUpdateStmt->execute([
        'provider_order_id' => $providerOrderId,
        'status' => 'Diproses',
        'provider_status' => 'Processing',
        'payment_method' => 'gateway',
        'payment_channel_name' => mb_substr($paymentChannelName, 0, 120),
        'payment_payer_name' => $payerName !== '' ? mb_substr($payerName, 0, 120) : null,
        'payment_reference' => $paymentReference !== '' ? mb_substr($paymentReference, 0, 120) : null,
        'payment_note' => mb_substr($gatewayNote, 0, 2000),
        'payment_confirmed_at' => $now,
        'payment_confirmed_by_admin_at' => $now,
        'provider_response_json' => json_encode($providerResult, JSON_UNESCAPED_UNICODE),
        'updated_at' => $now,
        'id' => $orderId,
    ]);

    $pdo->commit();

    notifyAdminPendingPaymentChannels($config, [
        'order_id' => $orderId,
        'username' => (string)($order['username'] ?? ''),
        'service_name' => (string)($order['service_name'] ?? ''),
        'target' => (string)($order['target'] ?? ''),
        'quantity' => (int)($order['quantity'] ?? 0),
        'total_sell_price' => (int)($order['total_sell_price'] ?? 0),
        'payment_method_name' => $paymentChannelName,
        'payment_state' => 'Auto verified by webhook',
        'payer_name' => $payerName,
        'payment_reference' => $paymentReference,
        'confirmed_at' => $now,
    ]);

    jsonResponse([
        'status' => true,
        'data' => [
            'msg' => 'Pembayaran tervalidasi via webhook. Order diproses otomatis.',
            'order_id' => $orderId,
            'provider_order_id' => $providerOrderId,
            'status' => 'Diproses',
        ],
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Payment gateway webhook error: ' . $e->getMessage());
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Gagal memproses callback payment gateway.'],
    ], 500);
}
