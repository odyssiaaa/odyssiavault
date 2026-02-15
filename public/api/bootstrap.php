<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/helpers.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/ProviderClient.php';
require_once __DIR__ . '/../../src/Pricing.php';
require_once __DIR__ . '/../../src/Config.php';

$config = Config::load();
startAppSession($config['app'] ?? []);
registerApiErrorHandlers();

try {
    $pdo = Database::connect($config['db'] ?? []);
    Database::ensureSchema($pdo);
} catch (Throwable $e) {
    error_log('Database bootstrap failed: ' . $e->getMessage());
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Koneksi database gagal.'],
    ], 500);
}

$appConfig = (array)($config['app'] ?? []);
$providerConfig = (array)($config['provider'] ?? []);
$pricingConfig = (array)($config['pricing'] ?? []);

$client = new ProviderClient($providerConfig);
$pricing = new Pricing($pricingConfig);
