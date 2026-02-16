<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$user = requireAuth($pdo);
expireUnpaidOrders($pdo);

$input = getRequestInput();
$retryAfter = null;
if (!rateLimitAllow('order_payment_confirm', 20, 60, $retryAfter)) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Permintaan konfirmasi terlalu cepat. Coba lagi sebentar.'],
    ], 429);
}

$orderId = sanitizeQuantity($input['order_id'] ?? '');
$methodCode = mb_strtolower(trim((string)($input['method_code'] ?? '')));
$payerName = trim((string)($input['payer_name'] ?? ''));
$paymentReference = trim((string)($input['payment_reference'] ?? ''));
$paymentNote = trim((string)($input['payment_note'] ?? ''));

if ($orderId <= 0 || $methodCode === '') {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'order_id dan method_code wajib diisi.'],
    ], 422);
}

$paymentMethods = checkoutPaymentMethods($config);
$selectedMethod = null;
foreach ($paymentMethods as $method) {
    if (($method['code'] ?? '') === $methodCode) {
        $selectedMethod = $method;
        break;
    }
}

if ($selectedMethod === null) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Metode pembayaran tidak valid.'],
    ], 422);
}

$orderStmt = $pdo->prepare('SELECT id, user_id, status, payment_deadline_at, payment_confirmed_at, service_name, target, quantity, total_sell_price FROM orders WHERE id = :id AND user_id = :user_id LIMIT 1');
$orderStmt->execute([
    'id' => $orderId,
    'user_id' => (int)$user['id'],
]);
$order = $orderStmt->fetch();

if (!is_array($order)) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Order tidak ditemukan.'],
    ], 404);
}

if ((string)$order['status'] !== 'Menunggu Pembayaran') {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Order ini tidak bisa dikonfirmasi pembayaran lagi.'],
    ], 422);
}

$deadline = trim((string)($order['payment_deadline_at'] ?? ''));
if ($deadline !== '' && strtotime($deadline) !== false && strtotime($deadline) < time()) {
    $cancelStmt = $pdo->prepare('UPDATE orders SET status = :status, error_message = :error_message, updated_at = :updated_at WHERE id = :id AND user_id = :user_id');
    $cancelStmt->execute([
        'status' => 'Dibatalkan',
        'error_message' => 'Batas waktu pembayaran habis',
        'updated_at' => nowDateTime(),
        'id' => $orderId,
        'user_id' => (int)$user['id'],
    ]);

    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Order sudah dibatalkan karena melewati batas waktu pembayaran.'],
    ], 422);
}

$updateStmt = $pdo->prepare('UPDATE orders SET payment_method = :payment_method, payment_channel_name = :payment_channel_name, payment_account_name = :payment_account_name, payment_account_number = :payment_account_number, payment_payer_name = :payment_payer_name, payment_reference = :payment_reference, payment_note = :payment_note, payment_confirmed_at = :payment_confirmed_at, updated_at = :updated_at WHERE id = :id AND user_id = :user_id');
$now = nowDateTime();
$updateStmt->execute([
    'payment_method' => (string)$selectedMethod['code'],
    'payment_channel_name' => (string)$selectedMethod['name'],
    'payment_account_name' => (string)$selectedMethod['account_name'],
    'payment_account_number' => (string)$selectedMethod['account_number'],
    'payment_payer_name' => $payerName !== '' ? mb_substr($payerName, 0, 120) : null,
    'payment_reference' => $paymentReference !== '' ? mb_substr($paymentReference, 0, 120) : null,
    'payment_note' => $paymentNote !== '' ? mb_substr($paymentNote, 0, 2000) : null,
    'payment_confirmed_at' => $now,
    'updated_at' => $now,
    'id' => $orderId,
    'user_id' => (int)$user['id'],
]);

$alreadyConfirmedBefore = trim((string)($order['payment_confirmed_at'] ?? '')) !== '';
if (!$alreadyConfirmedBefore) {
    notifyAdminPendingPaymentChannels($config, [
        'order_id' => $orderId,
        'username' => (string)($user['username'] ?? ''),
        'service_name' => (string)($order['service_name'] ?? ''),
        'target' => (string)($order['target'] ?? ''),
        'quantity' => (int)($order['quantity'] ?? 0),
        'total_sell_price' => (int)($order['total_sell_price'] ?? 0),
        'payment_method_name' => (string)($selectedMethod['name'] ?? ''),
        'payment_state' => 'Sudah konfirmasi buyer',
        'payer_name' => $payerName,
        'payment_reference' => $paymentReference,
        'confirmed_at' => $now,
    ]);
}

jsonResponse([
    'status' => true,
    'data' => [
        'msg' => 'Konfirmasi pembayaran berhasil dikirim. Order akan diproses setelah admin verifikasi.',
        'order_id' => $orderId,
        'status' => 'Menunggu Pembayaran',
        'payment' => [
            'method_code' => (string)$selectedMethod['code'],
            'method_name' => (string)$selectedMethod['name'],
            'account_name' => (string)$selectedMethod['account_name'],
            'account_number' => (string)$selectedMethod['account_number'],
            'payer_name' => $payerName,
            'payment_reference' => $paymentReference,
        ],
    ],
]);
