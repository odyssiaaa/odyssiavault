<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

requireAuth($pdo);
header('Cache-Control: private, max-age=15');
@ini_set('memory_limit', '256M');

if (!$client->isConfigured()) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Konfigurasi server layanan belum diisi (config/env).'],
    ], 500);
}

$providerConfig = (array)($config['provider'] ?? []);
$allowed = ['services', 'services_1', 'services2', 'services3'];
$defaultVariant = mb_strtolower(trim((string)($providerConfig['services_variant'] ?? 'services_1')));
if (!in_array($defaultVariant, $allowed, true)) {
    $defaultVariant = 'services_1';
}

$action = mb_strtolower(trim((string)($_GET['variant'] ?? $defaultVariant)));
if (!in_array($action, $allowed, true)) {
    $action = $defaultVariant;
}

$mode = mb_strtolower(trim((string)($_GET['mode'] ?? 'all')));
$mappedCacheTtl = max(0, (int)($providerConfig['services_mapped_cache_ttl'] ?? 300));
$defaultMappedCacheDir = dirname(__DIR__, 2) . '/storage/cache/api';
$mappedCacheDir = (string)($providerConfig['services_mapped_cache_dir'] ?? $defaultMappedCacheDir);
$safeAction = preg_replace('/[^a-z0-9_]+/i', '_', $action) ?: 'services';
$mappedCacheFile = rtrim($mappedCacheDir, '/\\') . DIRECTORY_SEPARATOR . 'mapped_services_' . $safeAction . '.json';
$bootstrapMappedFiles = array_values(array_unique(array_filter([
    dirname(__DIR__, 2) . '/storage/bootstrap/mapped_services_' . $safeAction . '.json',
    dirname(__DIR__, 2) . '/storage/bootstrap/mapped_services_services_1.json',
])));

$readMappedDataFile = static function (string $filePath): ?array {
    if (!is_file($filePath)) {
        return null;
    }

    $raw = @file_get_contents($filePath);
    if (!is_string($raw) || $raw === '') {
        return null;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return null;
    }

    if (isset($decoded['status']) && array_key_exists('data', $decoded)) {
        $isValid = (($decoded['status'] ?? false) === true)
            && is_array($decoded['data'] ?? null)
            && count((array)$decoded['data']) > 0;
        if (!$isValid) {
            return null;
        }

        return (array)$decoded['data'];
    }

    if (count($decoded) > 0 && is_array($decoded[0] ?? null)) {
        return $decoded;
    }

    return null;
};

$readMappedCache = static function (bool $freshOnly) use ($mappedCacheTtl, $mappedCacheFile, $readMappedDataFile): ?array {
    if (!is_file($mappedCacheFile)) {
        return null;
    }

    $mtime = @filemtime($mappedCacheFile);
    if (
        $freshOnly
        && $mappedCacheTtl > 0
        && is_int($mtime)
        && $mtime > 0
        && (time() - $mtime) > $mappedCacheTtl
    ) {
        return null;
    }

    return $readMappedDataFile($mappedCacheFile);
};

$readBootstrapMapped = static function () use ($bootstrapMappedFiles, $readMappedDataFile): ?array {
    foreach ($bootstrapMappedFiles as $bootstrapFile) {
        $data = $readMappedDataFile($bootstrapFile);
        if (is_array($data) && count($data) > 0) {
            return $data;
        }
    }

    return null;
};

/**
 * @return array{status:bool,data:array}
 */
$loadMappedServices = static function () use (
    $readMappedCache,
    $readBootstrapMapped,
    $client,
    $action,
    $pricing,
    $mappedCacheDir,
    $mappedCacheTtl,
    $mappedCacheFile
): array {
    $freshCache = $readMappedCache(true);
    if (is_array($freshCache)) {
        return [
            'status' => true,
            'data' => $freshCache,
        ];
    }

    // Fast path: pakai cache stale dulu agar UI tetap tampil cepat di hosting gratis.
    $staleCache = $readMappedCache(false);
    if (is_array($staleCache)) {
        return [
            'status' => true,
            'data' => $staleCache,
        ];
    }

    $bootstrapCache = $readBootstrapMapped();
    if (is_array($bootstrapCache)) {
        if ($mappedCacheTtl > 0) {
            if (!is_dir($mappedCacheDir)) {
                @mkdir($mappedCacheDir, 0777, true);
            }
            if (is_dir($mappedCacheDir)) {
                $encoded = json_encode([
                    'status' => true,
                    'data' => $bootstrapCache,
                ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
                if (is_string($encoded) && $encoded !== '') {
                    @file_put_contents($mappedCacheFile, $encoded, LOCK_EX);
                }
            }
        }

        return [
            'status' => true,
            'data' => $bootstrapCache,
        ];
    }

    $result = $client->services($action);
    if (!($result['status'] ?? false)) {
        $staleCacheAfterFail = $readMappedCache(false);
        if (is_array($staleCacheAfterFail)) {
            return [
                'status' => true,
                'data' => $staleCacheAfterFail,
            ];
        }

        $bootstrapAfterFail = $readBootstrapMapped();
        if (is_array($bootstrapAfterFail)) {
            return [
                'status' => true,
                'data' => $bootstrapAfterFail,
            ];
        }

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

$bootstrapJsonlCandidates = array_values(array_unique(array_filter([
    dirname(__DIR__, 2) . '/storage/bootstrap/services_' . $safeAction . '.jsonl',
    dirname(__DIR__, 2) . '/storage/bootstrap/services_services_1.jsonl',
])));

$bootstrapJsonlPartPatterns = array_values(array_unique(array_filter([
    dirname(__DIR__, 2) . '/storage/bootstrap/services_' . $safeAction . '.part*.jsonl',
    dirname(__DIR__, 2) . '/storage/bootstrap/services_services_1.part*.jsonl',
])));

$bootstrapJsonlFiles = [];
foreach ($bootstrapJsonlCandidates as $candidateFile) {
    if (is_file($candidateFile)) {
        $bootstrapJsonlFiles[] = $candidateFile;
    }
}
foreach ($bootstrapJsonlPartPatterns as $pattern) {
    $prefix = '';
    if (preg_match('/^(.*)\.part\*\.jsonl$/', $pattern, $matches) === 1) {
        $prefix = (string)($matches[1] ?? '');
    }
    if ($prefix === '') {
        continue;
    }

    for ($part = 1; $part <= 200; $part++) {
        $candidate = $prefix . '.part' . $part . '.jsonl';
        if (!is_file($candidate)) {
            if ($part > 1) {
                break;
            }
            continue;
        }
        $bootstrapJsonlFiles[] = $candidate;
    }
}
$bootstrapJsonlFiles = array_values(array_unique($bootstrapJsonlFiles));

$sanitizeBootstrapService = static function (array $service): array {
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

$iterateBootstrapJsonl = static function ($filePaths, callable $onRow): int {
    $paths = is_array($filePaths) ? $filePaths : [$filePaths];
    $rows = 0;
    foreach ($paths as $filePath) {
        if (!is_string($filePath) || $filePath === '' || !is_file($filePath)) {
            continue;
        }

        $handle = @fopen($filePath, 'rb');
        if (!is_resource($handle)) {
            continue;
        }

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $decoded = json_decode($line, true);
            if (!is_array($decoded)) {
                continue;
            }

            $rows++;
            $keepGoing = $onRow($decoded, $rows);
            if ($keepGoing === false) {
                fclose($handle);
                return $rows;
            }
        }

        fclose($handle);
    }

    return $rows;
};

$serviceSearchRank = static function (array $service, string $queryLower, string $queryDigits): int {
    $serviceId = (string)($service['id'] ?? '');
    $name = mb_strtolower((string)($service['name'] ?? ''));
    $category = mb_strtolower((string)($service['category'] ?? ''));
    $note = mb_strtolower((string)($service['note'] ?? ''));

    if ($queryDigits !== '' && $serviceId === $queryDigits) {
        return 0;
    }
    if ($queryDigits !== '' && str_starts_with($serviceId, $queryDigits)) {
        return 1;
    }
    if ($queryLower !== '' && str_starts_with($name, $queryLower)) {
        return 2;
    }
    if ($queryLower !== '' && str_contains($name, $queryLower)) {
        return 3;
    }
    if ($queryLower !== '' && str_contains($category, $queryLower)) {
        return 4;
    }
    if ($queryLower !== '' && str_contains($note, $queryLower)) {
        return 5;
    }

    return 99;
};

$comparePriceThenName = static function (array $a, array $b): int {
    $priceCmp = ((int)($a['sell_price'] ?? 0)) <=> ((int)($b['sell_price'] ?? 0));
    if ($priceCmp !== 0) {
        return $priceCmp;
    }

    $nameCmp = strnatcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
    if ($nameCmp !== 0) {
        return $nameCmp;
    }

    return ((int)($a['id'] ?? 0)) <=> ((int)($b['id'] ?? 0));
};

$jsonlPreferredModes = ['categories', 'detail', 'search', 'catalog', 'highlights', 'compact'];
$hasLargeMappedBootstrap = false;
foreach ($bootstrapMappedFiles as $bootstrapMappedFile) {
    if (is_file($bootstrapMappedFile) && (int)@filesize($bootstrapMappedFile) > 2000000) {
        $hasLargeMappedBootstrap = true;
        break;
    }
}

if (count($bootstrapJsonlFiles) === 0 && $hasLargeMappedBootstrap && in_array($mode, $jsonlPreferredModes, true)) {
    jsonResponse([
        'status' => false,
        'data' => [
            'msg' => 'Data layanan bootstrap belum lengkap. Upload file storage/bootstrap/services_services_1.jsonl lalu coba lagi.',
        ],
    ], 503);
}

if (count($bootstrapJsonlFiles) > 0 && in_array($mode, $jsonlPreferredModes, true)) {
    if ($mode === 'categories') {
        $seen = [];
        $totalServices = 0;

        $iterateBootstrapJsonl($bootstrapJsonlFiles, static function (array $row) use (&$seen, &$totalServices): bool {
            $totalServices++;
            $categoryName = trim((string)($row['category'] ?? 'Lainnya'));
            if ($categoryName === '') {
                $categoryName = 'Lainnya';
            }

            $providerCatId = $row['provider_cat_id'] ?? null;
            $providerCatId = is_numeric($providerCatId) ? (int)$providerCatId : null;

            if (!isset($seen[$categoryName])) {
                $seen[$categoryName] = [
                    'name' => $categoryName,
                    'provider_cat_id' => $providerCatId,
                ];
            } elseif (($seen[$categoryName]['provider_cat_id'] ?? null) === null && $providerCatId !== null) {
                $seen[$categoryName]['provider_cat_id'] = $providerCatId;
            }

            return true;
        });

        $categories = array_values($seen);
        usort($categories, static function (array $a, array $b): int {
            return strnatcasecmp((string)$a['name'], (string)$b['name']);
        });

        jsonResponse([
            'status' => true,
            'data' => [
                'categories' => $categories,
                'total_services' => $totalServices,
                'meta' => [
                    'total_services' => $totalServices,
                    'total_categories' => count($categories),
                    'synced_at' => nowDateTime(),
                ],
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

        $found = null;
        $iterateBootstrapJsonl($bootstrapJsonlFiles, static function (array $row) use (&$found, $serviceId): bool {
            if ((int)($row['id'] ?? 0) !== $serviceId) {
                return true;
            }

            $found = $row;
            return false;
        });

        if (!is_array($found)) {
            jsonResponse([
                'status' => false,
                'data' => ['msg' => 'Layanan tidak ditemukan.'],
            ], 404);
        }

        jsonResponse([
            'status' => true,
            'data' => [
                'service' => $sanitizeBootstrapService($found),
            ],
        ]);
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

        $results = [];

        if ($queryLower === '') {
            $iterateBootstrapJsonl($bootstrapJsonlFiles, static function (array $row) use (&$results, $limit, $categoryFilter, $sanitizeBootstrapService): bool {
                $category = (string)($row['category'] ?? 'Lainnya');
                if ($categoryFilter !== '' && strcasecmp($categoryFilter, $category) !== 0) {
                    return true;
                }

                $results[] = $sanitizeBootstrapService($row);
                if (count($results) >= $limit) {
                    return false;
                }

                return true;
            });

            jsonResponse([
                'status' => true,
                'data' => [
                    'services' => $results,
                    'count' => count($results),
                ],
            ]);
        }

        $compareSearch = static function (array $a, array $b) use ($comparePriceThenName): int {
            $rankA = (int)($a['_rank'] ?? 99);
            $rankB = (int)($b['_rank'] ?? 99);
            if ($rankA !== $rankB) {
                return $rankA <=> $rankB;
            }

            return $comparePriceThenName($a, $b);
        };

        $iterateBootstrapJsonl($bootstrapJsonlFiles, static function (array $row) use (
            &$results,
            $limit,
            $categoryFilter,
            $queryLower,
            $queryDigits,
            $sanitizeBootstrapService,
            $serviceSearchRank,
            $compareSearch
        ): bool {
            $category = (string)($row['category'] ?? 'Lainnya');
            if ($categoryFilter !== '' && strcasecmp($categoryFilter, $category) !== 0) {
                return true;
            }

            $rank = $serviceSearchRank($row, $queryLower, $queryDigits);
            if ($rank >= 99) {
                return true;
            }

            $candidate = $sanitizeBootstrapService($row);
            $candidate['_rank'] = $rank;

            if (count($results) < $limit) {
                $results[] = $candidate;
                return true;
            }

            $worstIndex = 0;
            $maxIndex = count($results) - 1;
            for ($i = 1; $i <= $maxIndex; $i++) {
                if ($compareSearch($results[$i], $results[$worstIndex]) > 0) {
                    $worstIndex = $i;
                }
            }

            if ($compareSearch($candidate, $results[$worstIndex]) < 0) {
                $results[$worstIndex] = $candidate;
            }

            return true;
        });

        usort($results, $compareSearch);
        $servicesPayload = array_map(static function (array $item): array {
            unset($item['_rank']);
            return $item;
        }, $results);

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
        $perPage = min($perPage, 100);
        $page = min($page, 200);

        $offset = ($page - 1) * $perPage;
        $keepTop = $offset + $perPage;

        $compareCatalog = static function (array $a, array $b) use ($sortBy, $sortDir): int {
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
        };

        $total = 0;
        $selected = [];

        $iterateBootstrapJsonl($bootstrapJsonlFiles, static function (array $row) use (
            &$total,
            &$selected,
            $keepTop,
            $categoryFilter,
            $queryLower,
            $compareCatalog
        ): bool {
            $category = (string)($row['category'] ?? 'Lainnya');
            if ($categoryFilter !== '' && strcasecmp($categoryFilter, $category) !== 0) {
                return true;
            }

            if ($queryLower !== '') {
                $id = (string)($row['id'] ?? '');
                $name = mb_strtolower((string)($row['name'] ?? ''));
                $note = mb_strtolower((string)($row['note'] ?? ''));
                $cat = mb_strtolower($category);
                $blob = trim($id . ' ' . $name . ' ' . $cat . ' ' . $note);
                if (!str_contains($blob, $queryLower)) {
                    return true;
                }
            }

            $total++;
            $entry = [
                'id' => (int)($row['id'] ?? 0),
                'name' => (string)($row['name'] ?? ''),
                'category' => $category,
                'sell_price' => (int)($row['sell_price'] ?? 0),
                'min' => (int)($row['min'] ?? 0),
                'max' => (int)($row['max'] ?? 0),
            ];

            if (count($selected) < $keepTop) {
                $selected[] = $entry;
                return true;
            }

            $worstIndex = 0;
            $maxIndex = count($selected) - 1;
            for ($i = 1; $i <= $maxIndex; $i++) {
                if ($compareCatalog($selected[$i], $selected[$worstIndex]) > 0) {
                    $worstIndex = $i;
                }
            }

            if ($compareCatalog($entry, $selected[$worstIndex]) < 0) {
                $selected[$worstIndex] = $entry;
            }

            return true;
        });

        usort($selected, $compareCatalog);

        $totalPages = max(1, (int)ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
            $offset = ($page - 1) * $perPage;
        }

        $rows = array_slice($selected, $offset, $perPage);

        jsonResponse([
            'status' => true,
            'data' => [
                'rows' => $rows,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => $totalPages,
                'meta' => [
                    'total_services' => $total,
                    'total_categories' => 0,
                    'synced_at' => nowDateTime(),
                ],
            ],
        ]);
    }

    if ($mode === 'highlights') {
        $limit = min(20, max(1, (int)($_GET['limit'] ?? 8)));
        $rows = [];

        $iterateBootstrapJsonl($bootstrapJsonlFiles, static function (array $row) use (&$rows, $limit): bool {
            $entry = [
                'id' => (int)($row['id'] ?? 0),
                'name' => (string)($row['name'] ?? ''),
                'category' => (string)($row['category'] ?? 'Lainnya'),
                'sell_price' => (int)($row['sell_price'] ?? 0),
                'min' => (int)($row['min'] ?? 0),
                'max' => (int)($row['max'] ?? 0),
                'speed' => (string)($row['speed'] ?? ''),
                'note' => (string)($row['note'] ?? ''),
            ];

            if (count($rows) < $limit) {
                $rows[] = $entry;
                return true;
            }

            $minIndex = 0;
            $maxIndex = count($rows) - 1;
            for ($i = 1; $i <= $maxIndex; $i++) {
                if ((int)$rows[$i]['id'] < (int)$rows[$minIndex]['id']) {
                    $minIndex = $i;
                }
            }

            if ((int)$entry['id'] > (int)$rows[$minIndex]['id']) {
                $rows[$minIndex] = $entry;
            }

            return true;
        });

        usort($rows, static function (array $a, array $b): int {
            return ((int)($b['id'] ?? 0)) <=> ((int)($a['id'] ?? 0));
        });

        jsonResponse([
            'status' => true,
            'data' => [
                'services' => $rows,
                'meta' => [
                    'total_services' => 0,
                    'total_categories' => 0,
                    'synced_at' => nowDateTime(),
                ],
            ],
        ]);
    }

    if ($mode === 'compact') {
        $limit = (int)($_GET['limit'] ?? 3000);
        if ($limit <= 0) {
            $limit = 3000;
        }
        $limit = min($limit, 5000);

        $rows = [];
        $iterateBootstrapJsonl($bootstrapJsonlFiles, static function (array $row) use (&$rows, $limit): bool {
            $rows[] = [
                'id' => (int)($row['id'] ?? 0),
                'name' => (string)($row['name'] ?? ''),
                'category' => (string)($row['category'] ?? 'Lainnya'),
                'min' => (int)($row['min'] ?? 0),
                'max' => (int)($row['max'] ?? 0),
                'sell_price' => (int)($row['sell_price'] ?? 0),
                'sell_price_per_1000' => (int)($row['sell_price_per_1000'] ?? 0),
            ];

            return count($rows) < $limit;
        });

        jsonResponse([
            'status' => true,
            'data' => $rows,
        ]);
    }
}

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

$categories = $categoriesPayload($mapped);
$metaPayload = [
    'total_services' => count($mapped),
    'total_categories' => count($categories),
    'synced_at' => nowDateTime(),
];

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
            'categories' => $categories,
            'total_services' => count($mapped),
            'meta' => $metaPayload,
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

    if ($queryLower === '') {
        $filtered = array_values(array_filter($mapped, static function (array $service) use ($categoryFilter): bool {
            $category = (string)($service['category'] ?? 'Lainnya');
            if ($categoryFilter !== '' && strcasecmp($categoryFilter, $category) !== 0) {
                return false;
            }

            return true;
        }));

        $sortByPriceThenName($filtered);
        $filtered = array_slice($filtered, 0, $limit);
        $servicesPayload = array_map($sanitizeService, $filtered);

        jsonResponse([
            'status' => true,
            'data' => [
                'services' => $servicesPayload,
                'count' => count($servicesPayload),
            ],
        ]);
    }

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
        if ($queryDigits !== '' && $serviceId === $queryDigits) {
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
            'meta' => $metaPayload,
        ],
    ]);
}

if ($mode === 'highlights') {
    $limit = min(20, max(1, (int)($_GET['limit'] ?? 8)));
    $highlights = $mapped;
    usort($highlights, static function (array $a, array $b): int {
        return ((int)($b['id'] ?? 0)) <=> ((int)($a['id'] ?? 0));
    });

    $highlights = array_slice($highlights, 0, $limit);
    $highlights = array_map(static function (array $service): array {
        return [
            'id' => (int)($service['id'] ?? 0),
            'name' => (string)($service['name'] ?? ''),
            'category' => (string)($service['category'] ?? 'Lainnya'),
            'sell_price' => (int)($service['sell_price'] ?? 0),
            'min' => (int)($service['min'] ?? 0),
            'max' => (int)($service['max'] ?? 0),
            'speed' => (string)($service['speed'] ?? ''),
            'note' => (string)($service['note'] ?? ''),
        ];
    }, $highlights);

    jsonResponse([
        'status' => true,
        'data' => [
            'services' => $highlights,
            'meta' => $metaPayload,
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
