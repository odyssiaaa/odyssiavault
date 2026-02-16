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
$mode = mb_strtolower(trim((string)($_GET['mode'] ?? 'all')));

$providerConfig = (array)($config['provider'] ?? []);
$mappedCacheTtl = max(0, (int)($providerConfig['services_mapped_cache_ttl'] ?? 300));
$defaultMappedCacheDir = dirname(__DIR__, 2) . '/storage/cache/api';
$mappedCacheDir = (string)($providerConfig['services_mapped_cache_dir'] ?? $defaultMappedCacheDir);
$safeAction = preg_replace('/[^a-z0-9_]+/i', '_', $action) ?: 'services';
$mappedCacheFile = rtrim($mappedCacheDir, '/\\') . DIRECTORY_SEPARATOR . 'mapped_services_' . $safeAction . '.json';

/**
 * @return array{status:bool,data:array}
 */
$loadMappedServices = static function () use (
    $mappedCacheTtl,
    $mappedCacheFile,
    $client,
    $action,
    $pricing,
    $mappedCacheDir
): array {
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
                    return [
                        'status' => true,
                        'data' => (array)$decodedCache['data'],
                    ];
                }

                @unlink($mappedCacheFile);
            }
        }
    }

    $result = $client->services($action);
    if (!($result['status'] ?? false)) {
        return [
            'status' => false,
            'data' => ['msg' => (string)(($result['data']['msg'] ?? '') ?: 'Gagal mengambil daftar layanan.')],
        ];
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

    if ($mappedCacheTtl > 0 && count($mapped) > 0) {
        if (!is_dir($mappedCacheDir)) {
            @mkdir($mappedCacheDir, 0777, true);
        }

        if (is_dir($mappedCacheDir)) {
            $encoded = json_encode([
                'status' => true,
                'data' => $mapped,
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            if (is_string($encoded) && $encoded !== '') {
                @file_put_contents($mappedCacheFile, $encoded, LOCK_EX);
            }
        }
    }

    return [
        'status' => true,
        'data' => $mapped,
    ];
};

$mappedResult = $loadMappedServices();
if (!($mappedResult['status'] ?? false)) {
    jsonResponse($mappedResult, 400);
}

$mapped = (array)($mappedResult['data'] ?? []);

$sanitizeService = static function (array $service): array {
    return [
        'id' => (int)($service['id'] ?? 0),
        'name' => (string)($service['name'] ?? ''),
        'category' => (string)($service['category'] ?? 'Lainnya'),
        'note' => (string)($service['note'] ?? ''),
        'type' => (string)($service['type'] ?? ''),
        'speed' => (string)($service['speed'] ?? ''),
        'provider_service_status' => (string)($service['provider_service_status'] ?? ''),
        'provider_cat_id' => array_key_exists('provider_cat_id', $service) && $service['provider_cat_id'] !== null
            ? (int)$service['provider_cat_id']
            : null,
        'min' => (int)($service['min'] ?? 0),
        'max' => (int)($service['max'] ?? 0),
        'sell_price' => (int)($service['sell_price'] ?? 0),
        'sell_price_per_1000' => (int)($service['sell_price_per_1000'] ?? 0),
        'sell_unit_price' => (float)($service['sell_unit_price'] ?? 0),
    ];
};

$categoriesPayload = static function (array $services): array {
    $seen = [];
    foreach ($services as $service) {
        $name = trim((string)($service['category'] ?? 'Lainnya'));
        if ($name === '') {
            $name = 'Lainnya';
        }

        $providerCatId = $service['provider_cat_id'] ?? null;
        $providerCatId = is_numeric($providerCatId) ? (int)$providerCatId : null;

        if (!isset($seen[$name])) {
            $seen[$name] = [
                'name' => $name,
                'provider_cat_id' => $providerCatId,
            ];
            continue;
        }

        if (($seen[$name]['provider_cat_id'] ?? null) === null && $providerCatId !== null) {
            $seen[$name]['provider_cat_id'] = $providerCatId;
        }
    }

    $categories = array_values($seen);
    usort($categories, static function (array $a, array $b): int {
        return strnatcasecmp((string)$a['name'], (string)$b['name']);
    });

    return $categories;
};

$sortByPriceThenName = static function (array &$services): void {
    usort($services, static function (array $a, array $b): int {
        $priceA = (int)($a['sell_price'] ?? 0);
        $priceB = (int)($b['sell_price'] ?? 0);
        if ($priceA !== $priceB) {
            return $priceA <=> $priceB;
        }

        $nameCmp = strnatcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
        if ($nameCmp !== 0) {
            return $nameCmp;
        }

        return ((int)($a['id'] ?? 0)) <=> ((int)($b['id'] ?? 0));
    });
};

if ($mode === 'categories') {
    jsonResponse([
        'status' => true,
        'data' => [
            'categories' => $categoriesPayload($mapped),
            'total_services' => count($mapped),
        ],
    ]);
}

if ($mode === 'detail') {
    $serviceId = (int)($_GET['id'] ?? 0);
    if ($serviceId <= 0) {
        jsonResponse([
            'status' => false,
            'data' => ['msg' => 'ID layanan tidak valid.'],
        ], 422);
    }

    foreach ($mapped as $service) {
        if ((int)($service['id'] ?? 0) === $serviceId) {
            jsonResponse([
                'status' => true,
                'data' => [
                    'service' => $sanitizeService($service),
                ],
            ]);
        }
    }

    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Layanan tidak ditemukan.'],
    ], 404);
}

if ($mode === 'search') {
    $categoryFilter = trim((string)($_GET['category'] ?? ''));
    $query = trim((string)($_GET['q'] ?? ''));
    $queryLower = mb_strtolower($query);
    $queryDigits = preg_replace('/\D+/', '', $query) ?? '';
    $limit = (int)($_GET['limit'] ?? 300);
    if ($limit <= 0) {
        $limit = 300;
    }
    $limit = min($limit, 500);

    $ranked = [];
    foreach ($mapped as $service) {
        $category = (string)($service['category'] ?? 'Lainnya');
        if ($categoryFilter !== '' && strcasecmp($categoryFilter, $category) !== 0) {
            continue;
        }

        $serviceId = (string)($service['id'] ?? '');
        $name = (string)($service['name'] ?? '');
        $note = (string)($service['note'] ?? '');
        $nameLower = mb_strtolower($name);
        $categoryLower = mb_strtolower($category);
        $noteLower = mb_strtolower($note);

        $rank = 99;
        if ($queryLower === '') {
            $rank = 10;
        } elseif ($queryDigits !== '' && $serviceId === $queryDigits) {
            $rank = 0;
        } elseif ($queryDigits !== '' && str_starts_with($serviceId, $queryDigits)) {
            $rank = 1;
        } elseif (str_starts_with($nameLower, $queryLower)) {
            $rank = 2;
        } elseif (str_contains($nameLower, $queryLower)) {
            $rank = 3;
        } elseif (str_contains($categoryLower, $queryLower)) {
            $rank = 4;
        } elseif (str_contains($noteLower, $queryLower)) {
            $rank = 5;
        } else {
            continue;
        }

        $service['_rank'] = $rank;
        $ranked[] = $service;
    }

    usort($ranked, static function (array $a, array $b): int {
        $rankA = (int)($a['_rank'] ?? 99);
        $rankB = (int)($b['_rank'] ?? 99);
        if ($rankA !== $rankB) {
            return $rankA <=> $rankB;
        }

        $priceA = (int)($a['sell_price'] ?? 0);
        $priceB = (int)($b['sell_price'] ?? 0);
        if ($priceA !== $priceB) {
            return $priceA <=> $priceB;
        }

        $nameCmp = strnatcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
        if ($nameCmp !== 0) {
            return $nameCmp;
        }

        return ((int)($a['id'] ?? 0)) <=> ((int)($b['id'] ?? 0));
    });

    $ranked = array_slice($ranked, 0, $limit);
    $servicesPayload = array_map($sanitizeService, $ranked);

    jsonResponse([
        'status' => true,
        'data' => [
            'services' => $servicesPayload,
            'count' => count($servicesPayload),
        ],
    ]);
}

if ($mode === 'catalog') {
    $categoryFilter = trim((string)($_GET['category'] ?? ''));
    $query = trim((string)($_GET['q'] ?? ''));
    $queryLower = mb_strtolower($query);
    $sortBy = mb_strtolower(trim((string)($_GET['sort_by'] ?? 'category_name')));
    $sortDir = mb_strtolower(trim((string)($_GET['sort_dir'] ?? 'asc'))) === 'desc' ? 'desc' : 'asc';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = (int)($_GET['per_page'] ?? 50);
    if ($perPage <= 0) {
        $perPage = 50;
    }
    $perPage = min($perPage, 200);

    $filtered = array_values(array_filter($mapped, static function (array $service) use ($categoryFilter, $queryLower): bool {
        $category = (string)($service['category'] ?? 'Lainnya');
        if ($categoryFilter !== '' && strcasecmp($categoryFilter, $category) !== 0) {
            return false;
        }

        if ($queryLower === '') {
            return true;
        }

        $id = (string)($service['id'] ?? '');
        $name = mb_strtolower((string)($service['name'] ?? ''));
        $note = mb_strtolower((string)($service['note'] ?? ''));
        $cat = mb_strtolower($category);
        $blob = trim($id . ' ' . $name . ' ' . $cat . ' ' . $note);

        return str_contains($blob, $queryLower);
    }));

    usort($filtered, static function (array $a, array $b) use ($sortBy, $sortDir): int {
        $direction = $sortDir === 'desc' ? -1 : 1;
        $result = 0;

        switch ($sortBy) {
            case 'price':
                $result = ((int)($a['sell_price'] ?? 0)) <=> ((int)($b['sell_price'] ?? 0));
                break;
            case 'id':
                $result = ((int)($a['id'] ?? 0)) <=> ((int)($b['id'] ?? 0));
                break;
            case 'name':
                $result = strnatcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
                break;
            case 'min':
                $result = ((int)($a['min'] ?? 0)) <=> ((int)($b['min'] ?? 0));
                break;
            case 'max':
                $result = ((int)($a['max'] ?? 0)) <=> ((int)($b['max'] ?? 0));
                break;
            case 'category_name':
            default:
                $result = strnatcasecmp((string)($a['category'] ?? ''), (string)($b['category'] ?? ''));
                if ($result === 0) {
                    $result = strnatcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
                }
                break;
        }

        if ($result === 0) {
            $result = ((int)($a['id'] ?? 0)) <=> ((int)($b['id'] ?? 0));
        }

        return $result * $direction;
    });

    $total = count($filtered);
    $totalPages = max(1, (int)ceil($total / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }

    $offset = ($page - 1) * $perPage;
    $rows = array_slice($filtered, $offset, $perPage);
    $rows = array_map(static function (array $service): array {
        return [
            'id' => (int)($service['id'] ?? 0),
            'name' => (string)($service['name'] ?? ''),
            'category' => (string)($service['category'] ?? 'Lainnya'),
            'sell_price' => (int)($service['sell_price'] ?? 0),
            'min' => (int)($service['min'] ?? 0),
            'max' => (int)($service['max'] ?? 0),
        ];
    }, $rows);

    jsonResponse([
        'status' => true,
        'data' => [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ],
    ]);
}

if ($mode === 'compact') {
    $compact = array_map(static function (array $service): array {
        return [
            'id' => (int)($service['id'] ?? 0),
            'name' => (string)($service['name'] ?? ''),
            'category' => (string)($service['category'] ?? 'Lainnya'),
            'min' => (int)($service['min'] ?? 0),
            'max' => (int)($service['max'] ?? 0),
            'sell_price' => (int)($service['sell_price'] ?? 0),
            'sell_price_per_1000' => (int)($service['sell_price_per_1000'] ?? 0),
        ];
    }, $mapped);
    $sortByPriceThenName($compact);
    jsonResponse([
        'status' => true,
        'data' => $compact,
    ]);
}

jsonResponse([
    'status' => true,
    'data' => $mapped,
]);
