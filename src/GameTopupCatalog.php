<?php

declare(strict_types=1);

if (!function_exists('mb_strtolower')) {
    function mb_strtolower($string, $encoding = null): string
    {
        return strtolower((string)$string);
    }
}

final class GameTopupCatalog
{
    private GameTopupClient $client;
    private GamePricing $pricing;
    /** @var array<int,array<string,mixed>>|null */
    private ?array $mappedMemory = null;

    public function __construct(GameTopupClient $client, GamePricing $pricing)
    {
        $this->client = $client;
        $this->pricing = $pricing;
    }

    public function all(bool $forceRefresh = false): array
    {
        if (!$forceRefresh && is_array($this->mappedMemory)) {
            return [
                'status' => true,
                'data' => $this->mappedMemory,
                'meta' => $this->meta($this->mappedMemory),
            ];
        }

        $result = $this->client->services($forceRefresh);
        if (($result['status'] ?? false) !== true) {
            return [
                'status' => false,
                'data' => ['msg' => (string)($result['msg'] ?? $result['data']['msg'] ?? 'Gagal memuat layanan topup game.')],
            ];
        }

        $rows = (array)($result['data'] ?? []);
        $mapped = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized = $this->mapService($row);
            if ($normalized === null) {
                continue;
            }
            $mapped[] = $normalized;
        }

        if ($mapped === []) {
            return [
                'status' => false,
                'data' => ['msg' => 'Layanan topup game tidak ditemukan.'],
            ];
        }

        usort($mapped, static function (array $a, array $b): int {
            $catCmp = strnatcasecmp((string)($a['category'] ?? ''), (string)($b['category'] ?? ''));
            if ($catCmp !== 0) {
                return $catCmp;
            }
            $nameCmp = strnatcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
            if ($nameCmp !== 0) {
                return $nameCmp;
            }
            return strnatcasecmp((string)($a['id'] ?? ''), (string)($b['id'] ?? ''));
        });

        $this->mappedMemory = $mapped;
        return [
            'status' => true,
            'data' => $mapped,
            'meta' => $this->meta($mapped),
        ];
    }

    public function categories(bool $forceRefresh = false): array
    {
        $all = $this->all($forceRefresh);
        if (($all['status'] ?? false) !== true) {
            return $all;
        }

        $services = (array)($all['data'] ?? []);
        $categories = [];
        foreach ($services as $service) {
            $category = trim((string)($service['category'] ?? 'Lainnya'));
            if ($category === '') {
                $category = 'Lainnya';
            }
            $categories[$category] = true;
        }

        $list = array_keys($categories);
        usort($list, static fn (string $a, string $b): int => strnatcasecmp($a, $b));

        return [
            'status' => true,
            'data' => $list,
            'meta' => $all['meta'] ?? [],
        ];
    }

    public function find(string $serviceId, bool $forceRefresh = false): ?array
    {
        $serviceId = trim($serviceId);
        if ($serviceId === '') {
            return null;
        }

        $all = $this->all($forceRefresh);
        if (($all['status'] ?? false) !== true) {
            return null;
        }

        foreach ((array)($all['data'] ?? []) as $service) {
            if ((string)($service['id'] ?? '') === $serviceId) {
                return $service;
            }
        }

        return null;
    }

    public function search(string $query = '', string $category = '', int $limit = 60, bool $forceRefresh = false): array
    {
        $all = $this->all($forceRefresh);
        if (($all['status'] ?? false) !== true) {
            return $all;
        }

        $query = trim($query);
        $queryNorm = mb_strtolower($query);
        $queryDigits = preg_replace('/\D+/', '', $query) ?: '';
        $category = trim($category);
        $limit = max(1, min(200, $limit));

        $rows = (array)($all['data'] ?? []);
        $filtered = [];
        foreach ($rows as $service) {
            if (!is_array($service)) {
                continue;
            }

            if ($category !== '' && strcasecmp((string)($service['category'] ?? ''), $category) !== 0) {
                continue;
            }

            if ($queryNorm !== '') {
                $id = mb_strtolower((string)($service['id'] ?? ''));
                $name = mb_strtolower((string)($service['name'] ?? ''));
                $cat = mb_strtolower((string)($service['category'] ?? ''));
                if (!str_contains($id, $queryNorm) && !str_contains($name, $queryNorm) && !str_contains($cat, $queryNorm)) {
                    continue;
                }
            }

            $filtered[] = $service;
        }

        usort($filtered, static function (array $a, array $b) use ($queryNorm, $queryDigits): int {
            $rankA = GameTopupCatalog::searchRank($a, $queryNorm, $queryDigits);
            $rankB = GameTopupCatalog::searchRank($b, $queryNorm, $queryDigits);
            if ($rankA !== $rankB) {
                return $rankA <=> $rankB;
            }

            $priceCmp = ((int)($a['sell_price'] ?? 0)) <=> ((int)($b['sell_price'] ?? 0));
            if ($priceCmp !== 0) {
                return $priceCmp;
            }

            return strnatcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
        });

        if (count($filtered) > $limit) {
            $filtered = array_slice($filtered, 0, $limit);
        }

        return [
            'status' => true,
            'data' => $filtered,
            'meta' => $all['meta'] ?? [],
        ];
    }

    private function mapService(array $row): ?array
    {
        $id = trim((string)($row['id'] ?? ''));
        $name = trim((string)($row['nama_layanan'] ?? $row['name'] ?? ''));
        $category = trim((string)($row['kategori'] ?? $row['category'] ?? 'Lainnya'));
        $statusRaw = trim((string)($row['status'] ?? ''));
        $statusNorm = mb_strtolower($statusRaw);
        $buyPrice = max(0, (int)($row['harga'] ?? 0));

        if ($id === '' || $name === '' || $buyPrice <= 0) {
            return null;
        }
        if ($category === '') {
            $category = 'Lainnya';
        }

        $sellPrice = $this->pricing->sellPrice($buyPrice, [
            'category' => $category,
        ]);

        return [
            'id' => $id,
            'name' => $name,
            'category' => $category,
            'status' => $statusRaw,
            'status_normalized' => $statusNorm,
            'is_active' => in_array($statusNorm, ['aktif', 'active'], true),
            'buy_price' => $buyPrice,
            'sell_price' => $sellPrice,
            'option_label' => '#' . $id . ' - ' . $name,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     */
    private function meta(array $rows): array
    {
        $categories = [];
        foreach ($rows as $row) {
            $category = trim((string)($row['category'] ?? ''));
            if ($category !== '') {
                $categories[$category] = true;
            }
        }

        return [
            'total_services' => count($rows),
            'total_categories' => count($categories),
            'synced_at' => date('Y-m-d H:i:s'),
        ];
    }

    private static function searchRank(array $service, string $queryLower, string $queryDigits): int
    {
        if ($queryLower === '') {
            return 10;
        }

        $id = mb_strtolower((string)($service['id'] ?? ''));
        $name = mb_strtolower((string)($service['name'] ?? ''));
        $category = mb_strtolower((string)($service['category'] ?? ''));

        if ($queryDigits !== '' && $id === $queryDigits) {
            return 0;
        }
        if ($queryDigits !== '' && str_starts_with($id, $queryDigits)) {
            return 1;
        }
        if (str_starts_with($name, $queryLower)) {
            return 2;
        }
        if (str_contains($name, $queryLower)) {
            return 3;
        }
        if (str_starts_with($category, $queryLower)) {
            return 4;
        }
        if (str_contains($category, $queryLower)) {
            return 5;
        }

        return 9;
    }
}

