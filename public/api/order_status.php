<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$user = requireAuth($pdo);
$isAdminUser = (string)($user['role'] ?? 'user') === 'admin';
expireUnpaidOrders($pdo);

$input = getRequestInput();
$orderId = sanitizeQuantity($input['order_id'] ?? '');

if ($orderId <= 0) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'order_id wajib diisi.'],
    ], 422);
}

$gatewayConfig = (array)($config['payment_gateway'] ?? []);
$gatewayEnabled = parseLooseBool($gatewayConfig['enabled'] ?? false, false);
$gatewayProvider = mb_strtolower(trim((string)($gatewayConfig['provider'] ?? '')));
$gatewaySuccessStatuses = isset($gatewayConfig['success_statuses']) && is_array($gatewayConfig['success_statuses'])
    ? $gatewayConfig['success_statuses']
    : ['PAID', 'SETTLEMENT', 'SUCCESS', 'COMPLETED'];
$gatewaySuccessSet = [];
foreach ($gatewaySuccessStatuses as $status) {
    $key = mb_strtoupper(trim((string)$status));
    if ($key !== '') {
        $gatewaySuccessSet[$key] = true;
    }
}
if ($gatewaySuccessSet === []) {
    $gatewaySuccessSet = ['PAID' => true, 'SETTLEMENT' => true, 'SUCCESS' => true, 'COMPLETED' => true];
}

$providerOrderId = '';
$currentStatus = 'Menunggu Pembayaran';
$gatewayTxnStatus = '';
$justProcessedFromGateway = false;
$justProcessedProviderOrderId = '';
$gatewayNotifyContext = null;
$orderOwnerUsername = (string)($user['username'] ?? '');
$updateWhereClause = $isAdminUser ? 'id = :id' : 'id = :id AND user_id = :user_id';
$withAccessParams = static function (array $params) use ($isAdminUser, $user): array {
    if (!$isAdminUser) {
        $params['user_id'] = (int)$user['id'];
    }
    return $params;
};

try {
    $pdo->beginTransaction();

    $orderSelectSql = '
        SELECT o.id, o.user_id, o.provider_order_id, o.status, o.payload_json, o.total_sell_price, o.payment_reference, o.payment_method, o.payment_channel_name, o.payment_note, o.service_name, o.target, o.quantity, u.username AS order_username
        FROM orders o
        INNER JOIN users u ON u.id = o.user_id
        WHERE o.id = :id';
    $orderSelectParams = ['id' => $orderId];
    if (!$isAdminUser) {
        $orderSelectSql .= ' AND o.user_id = :user_id';
        $orderSelectParams['user_id'] = (int)$user['id'];
    }
    $orderSelectSql .= ' LIMIT 1 FOR UPDATE';

    $orderStmt = $pdo->prepare($orderSelectSql);
    $orderStmt->execute($orderSelectParams);
    $order = $orderStmt->fetch();

    if (!is_array($order)) {
        $pdo->rollBack();
        jsonResponse([
            'status' => false,
            'data' => ['msg' => 'Order tidak ditemukan.'],
        ], 404);
    }

    $providerOrderId = trim((string)($order['provider_order_id'] ?? ''));
    $currentStatus = trim((string)($order['status'] ?? 'Menunggu Pembayaran'));
    $orderOwnerUsername = trim((string)($order['order_username'] ?? $orderOwnerUsername));

    // Fallback verifikasi otomatis untuk Pakasir ketika webhook eksternal tidak masuk.
    if (
        $providerOrderId === ''
        && $currentStatus === 'Menunggu Pembayaran'
        && $gatewayEnabled
        && $gatewayProvider === 'pakasir'
    ) {
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

        $gatewayOrderId = trim((string)($order['payment_reference'] ?? ''));
        if ($gatewayOrderId === '') {
            $gatewayOrderId = (string)$orderId;
        }

        $detailAmount = max(1, (int)($order['total_sell_price'] ?? 0));
        $detailUrl = $pakasirBaseUrl . '/api/transactiondetail?' . http_build_query([
            'project' => $pakasirProject,
            'amount' => $detailAmount,
            'order_id' => $gatewayOrderId,
            'api_key' => $pakasirApiKey,
        ]);

        $detailResult = httpGet($detailUrl, [], 18);
        if (!($detailResult['ok'] ?? false)) {
            $pdo->rollBack();
            jsonResponse([
                'status' => false,
                'data' => ['msg' => 'Gagal mengecek status pembayaran Pakasir.'],
            ], 502);
        }

        $detailJson = json_decode((string)($detailResult['body'] ?? ''), true);
        if (!is_array($detailJson)) {
            $pdo->rollBack();
            jsonResponse([
                'status' => false,
                'data' => ['msg' => 'Respon status pembayaran Pakasir tidak valid.'],
            ], 502);
        }

        $detailTxn = [];
        if (isset($detailJson['transaction']) && is_array($detailJson['transaction'])) {
            $detailTxn = $detailJson['transaction'];
        } elseif (isset($detailJson['payment']) && is_array($detailJson['payment'])) {
            $detailTxn = $detailJson['payment'];
        }

        if ($detailTxn === []) {
            $pdo->commit();
            jsonResponse([
                'status' => true,
                'data' => [
                    'order_id' => $orderId,
                    'provider_order_id' => null,
                    'status' => 'Menunggu Pembayaran',
                    'provider_status' => null,
                    'msg' => 'Pembayaran belum terdeteksi.',
                ],
            ]);
        }

        $detailTxnStatus = mb_strtoupper(trim((string)($detailTxn['status'] ?? 'PENDING')));
        $gatewayTxnStatus = $detailTxnStatus;
        $detailTxnOrderId = trim((string)($detailTxn['order_id'] ?? ''));
        $detailTxnOrderIdSanitized = (string)sanitizeQuantity($detailTxnOrderId);
        $localOrderIdSanitized = (string)$orderId;
        $referenceSanitized = (string)sanitizeQuantity($gatewayOrderId);

        if (
            $detailTxnOrderId !== ''
            && $detailTxnOrderIdSanitized !== $localOrderIdSanitized
            && $detailTxnOrderIdSanitized !== $referenceSanitized
        ) {
            $pdo->rollBack();
            jsonResponse([
                'status' => false,
                'data' => ['msg' => 'Order callback Pakasir tidak sesuai.'],
            ], 401);
        }

        if (!isset($gatewaySuccessSet[$detailTxnStatus])) {
            $pdo->commit();
            jsonResponse([
                'status' => true,
                'data' => [
                    'order_id' => $orderId,
                    'provider_order_id' => null,
                    'status' => 'Menunggu Pembayaran',
                    'provider_status' => $detailTxnStatus,
                    'msg' => 'Pembayaran belum terdeteksi atau masih pending.',
                ],
            ]);
        }

        if (!$client->isConfigured()) {
            $pdo->rollBack();
            jsonResponse([
                'status' => false,
                'data' => ['msg' => 'Konfigurasi server layanan belum diisi (config/env).'],
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

            $failedNote = trim((string)($order['payment_note'] ?? ''));
            $failedGatewayNote = '[PAKASIR] payment confirmed, provider order failed: ' . $providerMsg;
            $mergedNote = $failedNote !== '' ? ($failedNote . "\n" . $failedGatewayNote) : $failedGatewayNote;

            $failStmt = $pdo->prepare(
                'UPDATE orders
                 SET status = :status,
                     provider_status = :provider_status,
                     payment_method = :payment_method,
                     payment_channel_name = :payment_channel_name,
                     payment_reference = :payment_reference,
                     payment_note = :payment_note,
                     payment_confirmed_at = COALESCE(payment_confirmed_at, :payment_confirmed_at),
                     payment_confirmed_by_admin_at = COALESCE(payment_confirmed_by_admin_at, :payment_confirmed_by_admin_at),
                     error_message = :error_message,
                     provider_response_json = :provider_response_json,
                     updated_at = :updated_at
                 WHERE ' . $updateWhereClause
            );
            $now = nowDateTime();
            $failStmt->execute($withAccessParams([
                'status' => 'Error',
                'provider_status' => $detailTxnStatus,
                'payment_method' => 'gateway',
                'payment_channel_name' => 'Pakasir QRIS',
                'payment_reference' => mb_substr($gatewayOrderId, 0, 120),
                'payment_note' => mb_substr($mergedNote, 0, 2000),
                'payment_confirmed_at' => $now,
                'payment_confirmed_by_admin_at' => $now,
                'error_message' => mb_substr($providerMsg, 0, 1000),
                'provider_response_json' => json_encode($providerResult, JSON_UNESCAPED_UNICODE),
                'updated_at' => $now,
                'id' => $orderId,
            ]));

            $pdo->commit();
            jsonResponse([
                'status' => false,
                'data' => [
                    'msg' => $providerMsg,
                    'order_id' => $orderId,
                    'status' => 'Error',
                    'provider_status' => $detailTxnStatus,
                ],
            ], 400);
        }

        $providerOrderId = trim((string)($providerResult['data']['id'] ?? ''));
        if ($providerOrderId === '') {
            $pdo->rollBack();
            jsonResponse([
                'status' => false,
                'data' => ['msg' => 'Server layanan tidak mengembalikan ID order.'],
            ], 400);
        }

        $gatewayNote = trim((string)($order['payment_note'] ?? ''));
        $appendGatewayNote = '[PAKASIR] auto verified via order status check (' . $detailTxnStatus . ')';
        $mergedGatewayNote = $gatewayNote !== '' ? ($gatewayNote . "\n" . $appendGatewayNote) : $appendGatewayNote;

        $verifyStmt = $pdo->prepare(
            'UPDATE orders
             SET provider_order_id = :provider_order_id,
                 status = :status,
                 provider_status = :provider_status,
                 payment_method = :payment_method,
                 payment_channel_name = :payment_channel_name,
                 payment_reference = :payment_reference,
                 payment_note = :payment_note,
                 payment_confirmed_at = COALESCE(payment_confirmed_at, :payment_confirmed_at),
                 payment_confirmed_by_admin_at = COALESCE(payment_confirmed_by_admin_at, :payment_confirmed_by_admin_at),
                 provider_response_json = :provider_response_json,
                 updated_at = :updated_at
             WHERE ' . $updateWhereClause
        );
        $now = nowDateTime();
        $verifyStmt->execute($withAccessParams([
            'provider_order_id' => $providerOrderId,
            'status' => 'Diproses',
            'provider_status' => 'Processing',
            'payment_method' => 'gateway',
            'payment_channel_name' => 'Pakasir QRIS',
            'payment_reference' => mb_substr($gatewayOrderId, 0, 120),
            'payment_note' => mb_substr($mergedGatewayNote, 0, 2000),
            'payment_confirmed_at' => $now,
            'payment_confirmed_by_admin_at' => $now,
            'provider_response_json' => json_encode($providerResult, JSON_UNESCAPED_UNICODE),
            'updated_at' => $now,
            'id' => $orderId,
        ]));

        $currentStatus = 'Diproses';
        $justProcessedFromGateway = true;
        $justProcessedProviderOrderId = $providerOrderId;
        $gatewayNotifyContext = [
            'service_name' => (string)($order['service_name'] ?? ''),
            'target' => (string)($order['target'] ?? ''),
            'quantity' => (int)($order['quantity'] ?? 0),
            'total_sell_price' => (int)($order['total_sell_price'] ?? 0),
        ];
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Order status check failed: ' . $e->getMessage());
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Gagal mengecek status order.'],
    ], 500);
}

if ($justProcessedFromGateway) {
    $notifyServiceName = is_array($gatewayNotifyContext) ? (string)($gatewayNotifyContext['service_name'] ?? '') : '';
    $notifyTarget = is_array($gatewayNotifyContext) ? (string)($gatewayNotifyContext['target'] ?? '') : '';
    $notifyQty = is_array($gatewayNotifyContext) ? (int)($gatewayNotifyContext['quantity'] ?? 0) : 0;
    $notifyTotal = is_array($gatewayNotifyContext) ? (int)($gatewayNotifyContext['total_sell_price'] ?? 0) : 0;
    notifyAdminPendingPaymentChannels($config, [
        'order_id' => $orderId,
        'username' => $orderOwnerUsername,
        'service_name' => $notifyServiceName,
        'target' => $notifyTarget,
        'quantity' => $notifyQty,
        'total_sell_price' => $notifyTotal,
        'payment_method_name' => 'Pakasir QRIS',
        'payment_state' => 'Auto verified by status check',
        'payer_name' => '',
        'payment_reference' => '',
        'confirmed_at' => nowDateTime(),
    ]);

    jsonResponse([
        'status' => true,
        'data' => [
            'order_id' => $orderId,
            'provider_order_id' => $justProcessedProviderOrderId,
            'status' => 'Diproses',
            'provider_status' => 'Processing',
            'start_count' => null,
            'remains' => null,
            'msg' => 'Pembayaran terdeteksi otomatis dan order sudah diproses.',
        ],
    ]);
}

if ($providerOrderId === '') {
    jsonResponse([
        'status' => true,
        'data' => [
            'order_id' => $orderId,
            'provider_order_id' => null,
            'status' => $currentStatus !== '' ? $currentStatus : 'Menunggu Pembayaran',
            'provider_status' => $gatewayTxnStatus !== '' ? $gatewayTxnStatus : null,
            'msg' => 'Order belum diproses ke server layanan.',
        ],
    ], 200);
}

if (!$client->isConfigured()) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Konfigurasi server layanan belum diisi (config/env).'],
    ], 500);
}

$statusResult = $client->status($providerOrderId);
if (!($statusResult['status'] ?? false)) {
    jsonResponse($statusResult, 400);
}

$providerData = (array)($statusResult['data'] ?? []);
$rawProviderStatus = (string)($providerData['status'] ?? $providerData['order_status'] ?? '');
$mappedStatus = mapProviderStatusLifecycle($rawProviderStatus);

$startCount = array_key_exists('start_count', $providerData)
    ? sanitizeQuantity($providerData['start_count'])
    : null;

$remains = array_key_exists('remains', $providerData)
    ? sanitizeQuantity($providerData['remains'])
    : null;

$updateStmt = $pdo->prepare('UPDATE orders SET status = :status, provider_status = :provider_status, provider_start_count = :provider_start_count, provider_remains = :provider_remains, provider_response_json = :provider_response_json, updated_at = :updated_at WHERE ' . $updateWhereClause);
$updateStmt->execute($withAccessParams([
    'status' => $mappedStatus,
    'provider_status' => $rawProviderStatus,
    'provider_start_count' => $startCount,
    'provider_remains' => $remains,
    'provider_response_json' => json_encode($statusResult, JSON_UNESCAPED_UNICODE),
    'updated_at' => nowDateTime(),
    'id' => $orderId,
]));

jsonResponse([
    'status' => true,
    'data' => [
        'order_id' => $orderId,
        'provider_order_id' => $providerOrderId,
        'status' => $mappedStatus,
        'provider_status' => $rawProviderStatus,
        'start_count' => $startCount,
        'remains' => $remains,
    ],
]);
