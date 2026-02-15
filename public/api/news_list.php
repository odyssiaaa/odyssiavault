<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

requireAuth($pdo);

$limit = min(100, max(1, sanitizeQuantity($_GET['limit'] ?? 20)));
$newsConfig = (array)($config['news'] ?? []);
$sourceMode = mb_strtolower(trim((string)($newsConfig['source_mode'] ?? 'hybrid')));
$providerVariant = trim((string)($newsConfig['provider_services_variant'] ?? 'services_1'));
$providerLimit = min(200, max(1, (int)($newsConfig['provider_limit'] ?? 25)));
$providerNoteChars = min(1200, max(100, (int)($newsConfig['provider_note_chars'] ?? 650)));

if (!in_array($providerVariant, ['services', 'services_1', 'services2', 'services3'], true)) {
    $providerVariant = 'services_1';
}

$manualEnabled = in_array($sourceMode, ['manual', 'hybrid', 'manual_provider'], true);
$providerEnabled = in_array($sourceMode, ['provider', 'provider_only', 'hybrid', 'manual_provider'], true);

$manualNews = [];
$providerNews = [];
$pickLatestServices = static function (array $services, int $take): array {
    $take = max(1, $take);
    $total = count($services);
    if ($total === 0) {
        return [];
    }

    $services = array_values($services);
    $firstId = (int)($services[0]['id'] ?? 0);
    $lastId = (int)($services[$total - 1]['id'] ?? 0);
    $picked = [];

    if ($lastId >= $firstId) {
        for ($i = $total - 1; $i >= 0 && count($picked) < $take; $i--) {
            if (!is_array($services[$i])) {
                continue;
            }
            $picked[] = $services[$i];
        }
        return $picked;
    }

    if ($firstId > $lastId) {
        for ($i = 0; $i < $total && count($picked) < $take; $i++) {
            if (!is_array($services[$i])) {
                continue;
            }
            $picked[] = $services[$i];
        }
        return $picked;
    }

    usort($services, static function (array $a, array $b): int {
        return (int)($b['id'] ?? 0) <=> (int)($a['id'] ?? 0);
    });

    return array_slice($services, 0, $take);
};

if ($manualEnabled) {
    try {
        $stmt = $pdo->prepare(
            'SELECT id, title, summary, content, source_name, source_url, published_at, created_at
             FROM news_posts
             WHERE is_published = 1
               AND published_at <= :now
             ORDER BY published_at DESC, id DESC
             LIMIT 100'
        );
        $stmt->bindValue(':now', nowDateTime(), PDO::PARAM_STR);
        $stmt->execute();
        $manualNews = $stmt->fetchAll() ?: [];
    } catch (Throwable $e) {
        $manualNews = [];
    }
}

if ($providerEnabled && $client->isConfigured()) {
    $providerResult = $client->services($providerVariant);
    if (($providerResult['status'] ?? false) === true) {
        $services = (array)($providerResult['data'] ?? []);
        $slice = $pickLatestServices($services, $providerLimit);
        $nowTs = time();

        foreach ($slice as $index => $service) {
            $serviceId = (int)($service['id'] ?? 0);
            if ($serviceId <= 0) {
                continue;
            }

            $name = trim((string)($service['name'] ?? 'Layanan'));
            $category = trim((string)($service['category'] ?? 'Lainnya'));
            $note = trim((string)($service['note'] ?? ''));
            $speed = trim((string)($service['speed'] ?? ''));
            $status = trim((string)($service['status'] ?? ''));
            $min = (int)($service['min'] ?? 0);
            $max = (int)($service['max'] ?? 0);
            $sellPrice = $pricing->sellPricePer1000($service);
            $publishedAt = date('Y-m-d H:i:s', $nowTs - $index);

            $summaryParts = [
                $category,
                'Harga/K ' . ('Rp ' . number_format($sellPrice, 0, ',', '.')),
                'Min ' . number_format($min, 0, ',', '.'),
                'Max ' . number_format($max, 0, ',', '.'),
            ];
            if ($speed !== '') {
                $summaryParts[] = 'Speed ' . $speed;
            }

            $contentParts = [
                '#' . $serviceId . ' - ' . $name,
                'Kategori: ' . $category,
                'Harga Jual / 1000: ' . ('Rp ' . number_format($sellPrice, 0, ',', '.')),
                'Minimum: ' . number_format($min, 0, ',', '.'),
                'Maximum: ' . number_format($max, 0, ',', '.'),
            ];
            if ($speed !== '') {
                $contentParts[] = 'Speed: ' . $speed;
            }
            if ($status !== '') {
                $contentParts[] = 'Status Layanan: ' . $status;
            }
            if ($note !== '') {
                $contentParts[] = 'Catatan: ' . mb_substr($note, 0, $providerNoteChars);
            }

            $providerNews[] = [
                'id' => 'provider-service-' . $serviceId,
                'title' => '#' . $serviceId . ' - ' . $name,
                'summary' => implode(' | ', $summaryParts),
                'content' => implode("\n", $contentParts),
                'source_name' => 'Odyssiavault',
                'source_url' => '',
                'published_at' => $publishedAt,
                'created_at' => $publishedAt,
            ];
        }
    }
}

$news = [];
if ($sourceMode === 'provider' || $sourceMode === 'provider_only') {
    $news = $providerNews;
} elseif ($sourceMode === 'manual') {
    $news = $manualNews;
} else {
    $news = array_merge($providerNews, $manualNews);
}

foreach ($news as &$item) {
    $sourceName = trim((string)($item['source_name'] ?? ''));
    if ($sourceName === '' || preg_match('/buzzer\s*panel|buzzerpanel/i', $sourceName)) {
        $item['source_name'] = 'Odyssiavault';
    }

    $sourceUrl = trim((string)($item['source_url'] ?? ''));
    if ($sourceUrl !== '' && preg_match('/buzzerpanel\.id/i', $sourceUrl)) {
        $item['source_url'] = '';
    }
}
unset($item);

usort($news, static function (array $a, array $b): int {
    $aTs = strtotime((string)($a['published_at'] ?? $a['created_at'] ?? '')) ?: 0;
    $bTs = strtotime((string)($b['published_at'] ?? $b['created_at'] ?? '')) ?: 0;
    if ($aTs !== $bTs) {
        return $bTs <=> $aTs;
    }

    return strcmp((string)($b['id'] ?? ''), (string)($a['id'] ?? ''));
});

$news = array_slice($news, 0, $limit);

jsonResponse([
    'status' => true,
    'data' => [
        'news' => $news,
        'source_mode' => $sourceMode,
    ],
]);
