<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

requireAuth($pdo);

if (!$gameClient->isConfigured()) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'API Topup Game belum dikonfigurasi. Isi GAME_PROVIDER_API_KEY terlebih dahulu.'],
    ], 500);
}

$mode = mb_strtolower(trim((string)($_GET['mode'] ?? 'list')));
$force = parseLooseBool($_GET['force'] ?? false, false);

$toCategories = static function (array $services): array {
    $categories = [];
    foreach ($services as $service) {
        if (!is_array($service)) {
            continue;
        }
        $category = trim((string)($service['category'] ?? 'Lainnya'));
        if ($category === '') {
            $category = 'Lainnya';
        }
        $categories[$category] = true;
    }

    $list = array_keys($categories);
    usort($list, static fn (string $a, string $b): int => strnatcasecmp($a, $b));
    return $list;
};

switch ($mode) {
    case 'categories': {
        $result = $gameCatalog->categories($force);
        if (($result['status'] ?? false) !== true) {
            jsonResponse([
                'status' => false,
                'data' => ['msg' => (string)($result['data']['msg'] ?? 'Gagal memuat kategori topup game.')],
            ], 502);
        }

        jsonResponse([
            'status' => true,
            'data' => [
                'categories' => (array)($result['data'] ?? []),
                'meta' => (array)($result['meta'] ?? []),
            ],
        ]);
        break;
    }

    case 'detail': {
        $id = trim((string)($_GET['id'] ?? ''));
        if ($id === '') {
            jsonResponse([
                'status' => false,
                'data' => ['msg' => 'ID layanan game wajib diisi.'],
            ], 422);
        }

        $service = $gameCatalog->find($id, $force);
        if (!is_array($service)) {
            jsonResponse([
                'status' => false,
                'data' => ['msg' => 'Layanan topup game tidak ditemukan.'],
            ], 404);
        }

        jsonResponse([
            'status' => true,
            'data' => [
                'service' => $service,
            ],
        ]);
        break;
    }

    case 'search': {
        $query = trim((string)($_GET['q'] ?? ''));
        $category = trim((string)($_GET['category'] ?? ''));
        $limit = max(1, min(200, sanitizeQuantity($_GET['limit'] ?? 40)));
        $result = $gameCatalog->search($query, $category, $limit, $force);

        if (($result['status'] ?? false) !== true) {
            jsonResponse([
                'status' => false,
                'data' => ['msg' => (string)($result['data']['msg'] ?? 'Gagal mencari layanan topup game.')],
            ], 502);
        }

        $services = (array)($result['data'] ?? []);
        jsonResponse([
            'status' => true,
            'data' => [
                'services' => $services,
                'categories' => $toCategories($services),
                'meta' => (array)($result['meta'] ?? []),
            ],
        ]);
        break;
    }

    case 'list':
    default: {
        $query = trim((string)($_GET['q'] ?? ''));
        $category = trim((string)($_GET['category'] ?? ''));
        $page = max(1, sanitizeQuantity($_GET['page'] ?? 1));
        $perPage = max(10, min(200, sanitizeQuantity($_GET['per_page'] ?? 50)));

        $all = $gameCatalog->all($force);
        if (($all['status'] ?? false) !== true) {
            jsonResponse([
                'status' => false,
                'data' => ['msg' => (string)($all['data']['msg'] ?? 'Gagal memuat layanan topup game.')],
            ], 502);
        }

        $rows = (array)($all['data'] ?? []);
        if ($category !== '') {
            $rows = array_values(array_filter($rows, static function (array $service) use ($category): bool {
                return strcasecmp((string)($service['category'] ?? ''), $category) === 0;
            }));
        }
        if ($query !== '') {
            $queryNorm = mb_strtolower($query);
            $rows = array_values(array_filter($rows, static function (array $service) use ($queryNorm): bool {
                $haystack = mb_strtolower(
                    (string)($service['id'] ?? '')
                    . ' '
                    . (string)($service['name'] ?? '')
                    . ' '
                    . (string)($service['category'] ?? '')
                );
                return str_contains($haystack, $queryNorm);
            }));
        }

        $total = count($rows);
        $totalPages = max(1, (int)ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;
        $paged = array_slice($rows, $offset, $perPage);

        jsonResponse([
            'status' => true,
            'data' => [
                'services' => $paged,
                'categories' => $toCategories((array)($all['data'] ?? [])),
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => $totalPages,
                ],
                'meta' => (array)($all['meta'] ?? []),
            ],
        ]);
        break;
    }
}

