<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/ProviderClient.php';
require_once __DIR__ . '/../src/Pricing.php';
require_once __DIR__ . '/../src/Config.php';

function out(array $payload, int $code = 0): void
{
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit($code);
}

try {
    $config = Config::load();
    startAppSession((array)($config['app'] ?? []));

    $pdo = Database::connect((array)($config['db'] ?? []));
    Database::ensureSchema($pdo);

    $providerConfig = (array)($config['provider'] ?? []);
    $pricingConfig = (array)($config['pricing'] ?? []);
    $checkoutConfig = (array)($config['checkout'] ?? []);

    $client = new ProviderClient($providerConfig);
    $pricing = new Pricing($pricingConfig);

    if (!$client->isConfigured()) {
        out([
            'ok' => false,
            'error' => 'Provider API belum terkonfigurasi.',
        ], 1);
    }

    $username = 'simtg' . date('His') . random_int(10, 99);
    $email = $username . '@odyssiavault.local';
    $passwordHash = password_hash('Simulasi123!', PASSWORD_DEFAULT);
    $now = nowDateTime();

    $insertUser = $pdo->prepare(
        'INSERT INTO users (username, email, full_name, password_hash, balance, role, is_active, created_at, updated_at)
         VALUES (:username, :email, :full_name, :password_hash, :balance, :role, 1, :created_at, :updated_at)'
    );
    $insertUser->execute([
        'username' => $username,
        'email' => $email,
        'full_name' => $username,
        'password_hash' => $passwordHash,
        'balance' => 0,
        'role' => 'user',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $userId = (int)$pdo->lastInsertId();

    $variant = mb_strtolower(trim((string)($providerConfig['services_variant'] ?? 'services_1')));
    if (!in_array($variant, ['services', 'services_1', 'services2', 'services3'], true)) {
        $variant = 'services_1';
    }

    $servicesResult = $client->services($variant);
    if (($servicesResult['status'] ?? false) !== true) {
        out([
            'ok' => false,
            'error' => 'Gagal mengambil layanan.',
            'provider' => $servicesResult,
        ], 1);
    }

    $services = (array)($servicesResult['data'] ?? []);
    $selectedService = null;
    foreach ($services as $service) {
        if (!is_array($service)) {
            continue;
        }
        $name = (string)($service['name'] ?? '');
        $min = (int)($service['min'] ?? 0);
        if ($min <= 0) {
            continue;
        }
        if (preg_match('/comment|komen|komentar|mention|reply|replies/i', $name) === 1) {
            continue;
        }
        $selectedService = $service;
        break;
    }

    if (!is_array($selectedService)) {
        foreach ($services as $service) {
            if (is_array($service) && (int)($service['min'] ?? 0) > 0) {
                $selectedService = $service;
                break;
            }
        }
    }

    if (!is_array($selectedService)) {
        out([
            'ok' => false,
            'error' => 'Tidak ada layanan valid untuk simulasi.',
        ], 1);
    }

    $serviceId = (int)($selectedService['id'] ?? 0);
    $serviceName = (string)($selectedService['name'] ?? '');
    $category = (string)($selectedService['category'] ?? 'Lainnya');
    $qty = max(1, (int)($selectedService['min'] ?? 1));
    $target = 'https://vt.tiktok.com/ZSmhwC1FW/';

    $buyPricePer1000 = (int)($selectedService['price'] ?? 0);
    $sellPricePer1000 = $pricing->sellPricePer1000($selectedService);
    $sellUnitPrice = round($sellPricePer1000 / 1000, 3);
    $totalBuy = $pricing->totalSell($buyPricePer1000, $qty);
    $totalSell = $pricing->totalSell($sellPricePer1000, $qty);
    $profit = max(0, $totalSell - $totalBuy);

    if ($serviceId <= 0 || $buyPricePer1000 <= 0 || $sellPricePer1000 <= 0 || $totalSell <= 0) {
        out([
            'ok' => false,
            'error' => 'Data harga layanan tidak valid untuk simulasi.',
            'service_id' => $serviceId,
            'buy_price' => $buyPricePer1000,
            'sell_price' => $sellPricePer1000,
            'total_sell' => $totalSell,
        ], 1);
    }

    $orderPayload = [
        'service' => $serviceId,
        'data' => $target,
        'quantity' => $qty,
    ];

    $timeoutMinutes = max(60, min(180, (int)($checkoutConfig['unpaid_timeout_minutes'] ?? 180)));
    $deadlineTs = time() + ($timeoutMinutes * 60);
    $deadline = date('Y-m-d H:i:s', $deadlineTs);

    $insertOrder = $pdo->prepare(
        'INSERT INTO orders
        (user_id, provider_order_id, service_id, service_name, category, target, quantity, unit_buy_price, unit_sell_price, total_buy_price, total_sell_price, profit, status, payment_deadline_at, payload_json, provider_response_json, created_at, updated_at)
        VALUES
        (:user_id, :provider_order_id, :service_id, :service_name, :category, :target, :quantity, :unit_buy_price, :unit_sell_price, :total_buy_price, :total_sell_price, :profit, :status, :payment_deadline_at, :payload_json, :provider_response_json, :created_at, :updated_at)'
    );
    $insertOrder->execute([
        'user_id' => $userId,
        'provider_order_id' => null,
        'service_id' => $serviceId,
        'service_name' => $serviceName,
        'category' => $category,
        'target' => $target,
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
    $orderId = (int)$pdo->lastInsertId();

    notifyAdminPendingPaymentChannels($config, [
        'order_id' => $orderId,
        'username' => $username,
        'service_name' => $serviceName,
        'target' => $target,
        'quantity' => $qty,
        'total_sell_price' => $totalSell,
        'payment_method_name' => 'QRIS',
        'payment_state' => 'Belum konfirmasi',
        'payer_name' => '',
        'payment_reference' => '',
        'confirmed_at' => $now,
    ]);

    $paymentMethods = checkoutPaymentMethods($config);
    $method = $paymentMethods[0] ?? [
        'code' => 'qris',
        'name' => 'QRIS',
        'account_name' => 'Odyssiavault',
        'account_number' => 'Scan QRIS',
    ];

    $confirmedAt = nowDateTime();
    $updatePayment = $pdo->prepare(
        'UPDATE orders
         SET payment_method = :payment_method,
             payment_channel_name = :payment_channel_name,
             payment_account_name = :payment_account_name,
             payment_account_number = :payment_account_number,
             payment_payer_name = :payment_payer_name,
             payment_reference = :payment_reference,
             payment_note = :payment_note,
             payment_confirmed_at = :payment_confirmed_at,
             updated_at = :updated_at
         WHERE id = :id'
    );
    $updatePayment->execute([
        'payment_method' => (string)($method['code'] ?? 'qris'),
        'payment_channel_name' => (string)($method['name'] ?? 'QRIS'),
        'payment_account_name' => (string)($method['account_name'] ?? 'Odyssiavault'),
        'payment_account_number' => (string)($method['account_number'] ?? 'Scan QRIS'),
        'payment_payer_name' => 'Simulasi Bot',
        'payment_reference' => 'SIM-' . $orderId,
        'payment_note' => 'Simulasi notifikasi order',
        'payment_confirmed_at' => $confirmedAt,
        'updated_at' => $confirmedAt,
        'id' => $orderId,
    ]);

    notifyAdminPendingPaymentChannels($config, [
        'order_id' => $orderId,
        'username' => $username,
        'service_name' => $serviceName,
        'target' => $target,
        'quantity' => $qty,
        'total_sell_price' => $totalSell,
        'payment_method_name' => (string)($method['name'] ?? 'QRIS'),
        'payment_state' => 'Sudah konfirmasi buyer',
        'payer_name' => 'Simulasi Bot',
        'payment_reference' => 'SIM-' . $orderId,
        'confirmed_at' => $confirmedAt,
    ]);

    $telegram = (array)(($config['notifications'] ?? [])['telegram'] ?? []);
    $botToken = trim((string)($telegram['bot_token'] ?? ''));
    $chatIds = normalizeTelegramChatIds(trim((string)($telegram['chat_id'] ?? '')));
    $telegramProbe = null;
    if ($botToken !== '' && $chatIds !== []) {
        $telegramProbe = httpPostUrlEncoded(
            'https://api.telegram.org/bot' . $botToken . '/sendMessage',
            [
                'chat_id' => (string)$chatIds[0],
                'text' => "Simulasi Order Berhasil\\nOrder ID: #{$orderId}\\nUser: @{$username}\\nStatus: Menunggu verifikasi admin",
                'disable_web_page_preview' => 'true',
            ],
            [],
            10
        );
    }

    out([
        'ok' => true,
        'user' => [
            'id' => $userId,
            'username' => $username,
        ],
        'order' => [
            'id' => $orderId,
            'service_id' => $serviceId,
            'service_name' => $serviceName,
            'quantity' => $qty,
            'target' => $target,
            'sell_price_per_1000' => $sellPricePer1000,
            'sell_unit_price' => $sellUnitPrice,
            'total' => $totalSell,
            'status' => 'Menunggu Pembayaran',
            'payment_confirmed_at' => $confirmedAt,
        ],
        'telegram_probe' => $telegramProbe,
    ]);
} catch (Throwable $e) {
    out([
        'ok' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ], 1);
}

