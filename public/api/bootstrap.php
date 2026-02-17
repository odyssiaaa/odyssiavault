<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/helpers.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/ProviderClient.php';
require_once __DIR__ . '/../../src/Pricing.php';
require_once __DIR__ . '/../../src/GameTopupClient.php';
require_once __DIR__ . '/../../src/GamePricing.php';
require_once __DIR__ . '/../../src/GameTopupCatalog.php';
require_once __DIR__ . '/../../src/Config.php';

$config = Config::load();
startAppSession($config['app'] ?? []);
registerApiErrorHandlers();

try {
    $pdo = Database::connect($config['db'] ?? []);
    Database::ensureSchema($pdo);
} catch (Throwable $e) {
    error_log('Database bootstrap failed: ' . $e->getMessage());
    $dbMsg = 'Koneksi database gagal. Cek DB_HOST/DB_DATABASE/DB_USERNAME/DB_PASSWORD.';
    $raw = mb_strtolower((string)$e->getMessage());
    if (str_contains($raw, 'getaddrinfo') || str_contains($raw, 'name or service not known') || str_contains($raw, 'no such host')) {
        $dbMsg = 'Koneksi database gagal. Host database tidak bisa dijangkau dari server.';
    } elseif (str_contains($raw, 'access denied')) {
        $dbMsg = 'Koneksi database gagal. Username/password database tidak valid.';
    } elseif (str_contains($raw, 'unknown database')) {
        $dbMsg = 'Koneksi database gagal. Nama database tidak ditemukan.';
    }
    jsonResponse([
        'status' => false,
        'data' => ['msg' => $dbMsg],
    ], 500);
}

$appConfig = (array)($config['app'] ?? []);
$providerConfig = (array)($config['provider'] ?? []);
$pricingConfig = (array)($config['pricing'] ?? []);
$gameProviderConfig = (array)($config['game_provider'] ?? []);
$gamePricingConfig = (array)($config['game_pricing'] ?? []);

$client = new ProviderClient($providerConfig);
$pricing = new Pricing($pricingConfig);
$gameClient = new GameTopupClient($gameProviderConfig);
$gamePricing = new GamePricing($gamePricingConfig);
$gameCatalog = new GameTopupCatalog($gameClient, $gamePricing);
