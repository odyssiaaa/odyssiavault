<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$user = requireAuth($pdo);
expireUnpaidOrders($pdo);

if (!$gameClient->isConfigured()) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'API Topup Game belum dikonfigurasi oleh admin.'],
    ], 500);
}

$input = getRequestInput();
$retryAfter = null;
if (!rateLimitAllow('game_order_checkout', 20, 60, $retryAfter)) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Permintaan terlalu cepat. Coba lagi sebentar.'],
    ], 429);
}

$serviceId = trim((string)($input['service_id'] ?? $input['service'] ?? ''));
$target = trim((string)($input['target'] ?? $input['data'] ?? ''));
$contact = normalizeWhatsAppRecipient((string)($input['kontak'] ?? $input['contact'] ?? $input['phone'] ?? ''));

if ($serviceId === '' || $target === '' || $contact === '') {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'service_id, target, dan kontak wajib diisi dengan benar.'],
    ], 422);
}

$service = $gameCatalog->find($serviceId, false);
if (!is_array($service)) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Layanan topup game tidak ditemukan.'],
    ], 404);
}

if (!($service['is_active'] ?? false)) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Layanan topup game sedang tidak aktif.'],
    ], 422);
}

$buyPrice = (int)($service['buy_price'] ?? 0);
$sellPrice = (int)($service['sell_price'] ?? 0);
if ($buyPrice <= 0 || $sellPrice <= 0) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Harga layanan game tidak valid.'],
    ], 422);
}

$quantity = 1;
$totalBuy = $gamePricing->totalSell($buyPrice, $quantity);
$totalSell = $gamePricing->totalSell($sellPrice, $quantity);
$profit = max(0, $totalSell - $totalBuy);

$checkoutConfig = (array)($config['checkout'] ?? []);
$timeoutMinutes = max(60, min(180, (int)($checkoutConfig['unpaid_timeout_minutes'] ?? 180)));
$nowTs = time();
$deadlineTs = $nowTs + ($timeoutMinutes * 60);
$now = date('Y-m-d H:i:s', $nowTs);
$deadline = date('Y-m-d H:i:s', $deadlineTs);

$buildExternalId = static function () use ($user): string {
    $prefix = 'OVG' . date('YmdHis');
    $userPart = str_pad((string)((int)($user['id'] ?? 0)), 4, '0', STR_PAD_LEFT);
    $randPart = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    return $prefix . $userPart . $randPart;
};

$externalId = '';
for ($attempt = 0; $attempt < 4; $attempt++) {
    $candidate = $buildExternalId();
    $existsStmt = $pdo->prepare('SELECT id FROM game_orders WHERE external_id = :external_id LIMIT 1');
    $existsStmt->execute(['external_id' => $candidate]);
    if (!$existsStmt->fetch()) {
        $externalId = $candidate;
        break;
    }
}
if ($externalId === '') {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Gagal membuat ID transaksi game. Coba ulangi checkout.'],
    ], 500);
}

$insertStmt = $pdo->prepare(
    'INSERT INTO game_orders (
        user_id, provider_order_id, external_id, service_code, service_name, category, target, contact, quantity,
        unit_buy_price, unit_sell_price, total_buy_price, total_sell_price, profit, status, payment_deadline_at,
        payment_method, payment_channel_name, payment_account_name, payment_account_number, created_at, updated_at
     ) VALUES (
        :user_id, :provider_order_id, :external_id, :service_code, :service_name, :category, :target, :contact, :quantity,
        :unit_buy_price, :unit_sell_price, :total_buy_price, :total_sell_price, :profit, :status, :payment_deadline_at,
        :payment_method, :payment_channel_name, :payment_account_name, :payment_account_number, :created_at, :updated_at
     )'
);

$paymentMethods = checkoutPaymentMethods($config);
$defaultMethod = $paymentMethods[0] ?? [
    'code' => 'qris',
    'name' => 'QRIS',
    'account_name' => 'Odyssiavault',
    'account_number' => 'Scan QRIS',
];

$insertStmt->execute([
    'user_id' => (int)$user['id'],
    'provider_order_id' => null,
    'external_id' => $externalId,
    'service_code' => (string)($service['id'] ?? ''),
    'service_name' => (string)($service['name'] ?? ''),
    'category' => (string)($service['category'] ?? 'Game'),
    'target' => mb_substr($target, 0, 120),
    'contact' => mb_substr($contact, 0, 40),
    'quantity' => $quantity,
    'unit_buy_price' => $buyPrice,
    'unit_sell_price' => $sellPrice,
    'total_buy_price' => $totalBuy,
    'total_sell_price' => $totalSell,
    'profit' => $profit,
    'status' => 'Menunggu Pembayaran',
    'payment_deadline_at' => $deadline,
    'payment_method' => (string)($defaultMethod['code'] ?? 'qris'),
    'payment_channel_name' => (string)($defaultMethod['name'] ?? 'QRIS'),
    'payment_account_name' => (string)($defaultMethod['account_name'] ?? 'Odyssiavault'),
    'payment_account_number' => (string)($defaultMethod['account_number'] ?? 'Scan QRIS'),
    'created_at' => $now,
    'updated_at' => $now,
]);

$orderId = (int)$pdo->lastInsertId();

notifyAdminPendingPaymentChannels($config, [
    'order_id' => $orderId,
    'username' => (string)($user['username'] ?? ''),
    'service_name' => '[GAME] ' . (string)($service['name'] ?? ''),
    'target' => $target,
    'quantity' => $quantity,
    'total_sell_price' => $totalSell,
    'payment_method_name' => (string)($defaultMethod['name'] ?? 'QRIS'),
    'payment_state' => 'Belum konfirmasi',
    'payer_name' => '',
    'payment_reference' => '',
    'confirmed_at' => $now,
]);

jsonResponse([
    'status' => true,
    'data' => [
        'msg' => 'Checkout topup game berhasil. Lanjutkan pembayaran lalu konfirmasi ke admin.',
        'order_type' => 'game',
        'order_id' => $orderId,
        'external_id' => $externalId,
        'status' => 'Menunggu Pembayaran',
        'target' => $target,
        'contact' => $contact,
        'quantity' => $quantity,
        'total_sell_price' => $totalSell,
        'payment_deadline_at' => $deadline,
        'payment_methods' => $paymentMethods,
        'service' => [
            'id' => (string)($service['id'] ?? ''),
            'name' => (string)($service['name'] ?? ''),
            'category' => (string)($service['category'] ?? ''),
        ],
    ],
]);

