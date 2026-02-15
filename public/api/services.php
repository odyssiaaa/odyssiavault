<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

requireAuth($pdo);

if (!$client->isConfigured()) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Konfigurasi provider belum diisi (config/env).'],
    ], 500);
}

$action = $_GET['variant'] ?? 'services';
$allowed = ['services', 'services_1', 'services2', 'services3'];
if (!in_array($action, $allowed, true)) {
    $action = 'services';
}

$providerConfig = (array)($config['provider'] ?? []);
$mappedCacheTtl = max(0, (int)($providerConfig['services_mapped_cache_ttl'] ?? 300));
$defaultMappedCacheDir = dirname(__DIR__, 2) . '/storage/cache/api';
$mappedCacheDir = (string)($providerConfig['services_mapped_cache_dir'] ?? $defaultMappedCacheDir);
$safeAction = preg_replace('/[^a-z0-9_]+/i', '_', $action) ?: 'services';
$mappedCacheFile = rtrim($mappedCacheDir, '/\\') . DIRECTORY_SEPARATOR . 'mapped_services_' . $safeAction . '.json';

if ($mappedCacheTtl > 0 && is_file($mappedCacheFile)) {
    $mtime = @filemtime($mappedCacheFile);
    if (is_int($mtime) && $mtime > 0 && (time() - $mtime) <= $mappedCacheTtl) {
        $rawCache = @file_get_contents($mappedCacheFile);
        if (is_string($rawCache) && $rawCache !== '') {
            $decodedCache = json_decode($rawCache, true);
            $isValidCache = is_array($decodedCache)
                && (($decodedCache['status'] ?? false) === true)
                && is_array($decodedCache['data'] ?? null)
                && count((array)$decodedCache['data']) > 0;

            if ($isValidCache) {
                http_response_code(200);
                header('Content-Type: application/json; charset=utf-8');
                echo $rawCache;
                exit;
            }

            @unlink($mappedCacheFile);
        }
    }
}

$result = $client->services($action);
if (!($result['status'] ?? false)) {
    jsonResponse($result, 400);
}

$services = (array)($result['data'] ?? []);
$mapped = [];
$maxNoteChars = 280;

foreach ($services as $service) {
    $sellPricePer1000 = $pricing->sellPricePer1000($service);
    $sellUnitPrice = $pricing->sellUnitPrice($service);
    $note = trim((string)($service['note'] ?? ''));
    if (mb_strlen($note) > $maxNoteChars) {
        $note = mb_substr($note, 0, $maxNoteChars) . '...';
    }

    $mapped[] = [
        'id' => (int)($service['id'] ?? 0),
        'name' => (string)($service['name'] ?? ''),
        'category' => (string)($service['category'] ?? 'Lainnya'),
        'note' => $note,
        'type' => (string)($service['jenis'] ?? ''),
        'speed' => (string)($service['speed'] ?? ''),
        'provider_service_status' => (string)($service['status'] ?? ''),
        'provider_cat_id' => array_key_exists('cat_id', $service) ? (int)$service['cat_id'] : null,
        'min' => (int)($service['min'] ?? 0),
        'max' => (int)($service['max'] ?? 0),
        'sell_price' => $sellPricePer1000,
        'sell_price_per_1000' => $sellPricePer1000,
        'sell_unit_price' => round($sellUnitPrice, 3),
    ];
}

$payload = [
    'status' => true,
    'data' => $mapped,
];

if ($mappedCacheTtl > 0) {
    if (!is_dir($mappedCacheDir)) {
        @mkdir($mappedCacheDir, 0777, true);
    }

    if (is_dir($mappedCacheDir)) {
        $hasMappedData = count($mapped) > 0;
        if ($hasMappedData) {
            $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            if (is_string($encoded) && $encoded !== '') {
                @file_put_contents($mappedCacheFile, $encoded, LOCK_EX);
            }
        }
    }
}

jsonResponse($payload);
