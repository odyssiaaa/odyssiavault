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
$webSourceUrl = trim((string)($newsConfig['web_source_url'] ?? 'https://buzzerpanel.id/'));
$webLimit = min(100, max(1, (int)($newsConfig['web_limit'] ?? 25)));
$webTimeout = min(30, max(3, (int)($newsConfig['web_timeout'] ?? 12)));
$webCacheTtl = min(86400, max(60, (int)($newsConfig['web_cache_ttl'] ?? 900)));
$webFailCacheTtl = min(3600, max(30, (int)($newsConfig['web_fail_cache_ttl'] ?? 300)));
$webUserAgent = trim((string)($newsConfig['web_user_agent'] ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36'));
$providerConfig = (array)($config['provider'] ?? []);

if (!in_array($providerVariant, ['services', 'services_1', 'services2', 'services3'], true)) {
    $providerVariant = 'services_1';
}

$manualEnabled = in_array($sourceMode, ['manual', 'hybrid', 'manual_provider', 'web_hybrid'], true);
$providerEnabled = in_array($sourceMode, ['provider', 'provider_only', 'hybrid', 'manual_provider', 'web_provider', 'web_hybrid'], true);
$webEnabled = $webSourceUrl !== '' && in_array($sourceMode, ['hybrid', 'web_only', 'web_provider', 'web_hybrid'], true);

$serviceVariantFallback = static function (string $variant): array {
    $variant = mb_strtolower(trim($variant));
    $map = [
        'services' => ['services', 'services_1', 'services2', 'services3'],
        'services_1' => ['services_1', 'services', 'services2', 'services3'],
        'services2' => ['services2', 'services_1', 'services', 'services3'],
        'services3' => ['services3', 'services2', 'services_1', 'services'],
    ];

    $variants = $map[$variant] ?? ['services_1', 'services', 'services2', 'services3'];
    $seen = [];
    $out = [];
    foreach ($variants as $item) {
        if (isset($seen[$item])) {
            continue;
        }
        $seen[$item] = true;
        $out[] = $item;
    }
    return $out;
};

$loadMappedServiceCache = static function (string $variant) use ($providerConfig): array {
    $defaultMappedCacheDir = dirname(__DIR__, 2) . '/storage/cache/api';
    $mappedCacheDir = (string)($providerConfig['services_mapped_cache_dir'] ?? $defaultMappedCacheDir);
    $safeVariant = preg_replace('/[^a-z0-9_]+/i', '_', $variant) ?: 'services';
    $mappedCacheFile = rtrim($mappedCacheDir, '/\\') . DIRECTORY_SEPARATOR . 'mapped_services_' . $safeVariant . '.json';
    $bootstrapCacheFile = dirname(__DIR__, 2) . '/storage/bootstrap/mapped_services_' . $safeVariant . '.json';
    $bootstrapDefaultFile = dirname(__DIR__, 2) . '/storage/bootstrap/mapped_services_services_1.json';
    $candidates = array_values(array_unique(array_filter([$mappedCacheFile, $bootstrapCacheFile, $bootstrapDefaultFile])));

    foreach ($candidates as $filePath) {
        if (!is_file($filePath)) {
            continue;
        }

        $raw = @file_get_contents($filePath);
        if (!is_string($raw) || $raw === '') {
            continue;
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded) && ($decoded['status'] ?? false) === true && is_array($decoded['data'] ?? null)) {
            return (array)$decoded['data'];
        }

        if (is_array($decoded) && count($decoded) > 0 && is_array($decoded[0] ?? null)) {
            return $decoded;
        }
    }

    return [];
};

$resolveBootstrapJsonlFiles = static function (string $variant): array {
    $safeVariant = preg_replace('/[^a-z0-9_]+/i', '_', $variant) ?: 'services';
    $baseDir = dirname(__DIR__, 2) . '/storage/bootstrap';

    $candidates = array_values(array_unique(array_filter([
        $baseDir . '/services_' . $safeVariant . '.jsonl',
        $baseDir . '/services_services_1.jsonl',
    ])));

    $partPrefixes = array_values(array_unique(array_filter([
        $baseDir . '/services_' . $safeVariant,
        $baseDir . '/services_services_1',
    ])));

    $files = [];
    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            $files[] = $candidate;
        }
    }

    foreach ($partPrefixes as $prefix) {
        for ($part = 1; $part <= 200; $part++) {
            $candidate = $prefix . '.part' . $part . '.jsonl';
            if (!is_file($candidate)) {
                if ($part > 1) {
                    break;
                }
                continue;
            }
            $files[] = $candidate;
        }
    }

    return array_values(array_unique($files));
};

$loadLatestBootstrapServices = static function (string $variant, int $take) use ($resolveBootstrapJsonlFiles): array {
    $files = $resolveBootstrapJsonlFiles($variant);
    if ($files === []) {
        return [];
    }

    $take = max(1, $take);
    $maxKeep = min(2400, max($take * 12, 120));
    $selected = [];
    $selectedIds = [];
    $minIndex = 0;
    $minId = PHP_INT_MAX;

    $pickMin = static function (array $rows): array {
        if ($rows === []) {
            return [0, PHP_INT_MAX];
        }

        $index = 0;
        $value = (int)($rows[0]['id'] ?? 0);
        $total = count($rows);
        for ($i = 1; $i < $total; $i++) {
            $candidate = (int)($rows[$i]['id'] ?? 0);
            if ($candidate < $value) {
                $value = $candidate;
                $index = $i;
            }
        }

        return [$index, $value];
    };

    foreach ($files as $filePath) {
        $handle = @fopen($filePath, 'rb');
        if (!is_resource($handle)) {
            continue;
        }

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $row = json_decode($line, true);
            if (!is_array($row)) {
                continue;
            }

            $id = (int)($row['id'] ?? 0);
            if ($id <= 0 || isset($selectedIds[$id])) {
                continue;
            }

            $candidate = [
                'id' => $id,
                'name' => (string)($row['name'] ?? ''),
                'category' => (string)($row['category'] ?? 'Lainnya'),
                'note' => (string)($row['note'] ?? ''),
                'speed' => (string)($row['speed'] ?? ''),
                'status' => (string)($row['provider_service_status'] ?? $row['status'] ?? ''),
                'provider_service_status' => (string)($row['provider_service_status'] ?? $row['status'] ?? ''),
                'min' => (int)($row['min'] ?? 0),
                'max' => (int)($row['max'] ?? 0),
                'sell_price' => (int)($row['sell_price'] ?? 0),
                'sell_price_per_1000' => (int)($row['sell_price_per_1000'] ?? $row['sell_price'] ?? 0),
            ];

            if (count($selected) < $maxKeep) {
                $selected[] = $candidate;
                $selectedIds[$id] = true;
                if ($id < $minId) {
                    $minId = $id;
                    $minIndex = count($selected) - 1;
                }
                continue;
            }

            if ($id <= $minId) {
                continue;
            }

            $replacedId = (int)($selected[$minIndex]['id'] ?? 0);
            if ($replacedId > 0) {
                unset($selectedIds[$replacedId]);
            }

            $selected[$minIndex] = $candidate;
            $selectedIds[$id] = true;
            [$minIndex, $minId] = $pickMin($selected);
        }

        fclose($handle);
    }

    if ($selected === []) {
        return [];
    }

    usort($selected, static function (array $a, array $b): int {
        return (int)($b['id'] ?? 0) <=> (int)($a['id'] ?? 0);
    });

    return array_slice($selected, 0, $take);
};

$webFetchMeta = [
    'enabled' => $webEnabled,
    'status' => 'skipped',
    'message' => '',
    'from_cache' => false,
];
$webNews = [];

$parsePublishedAtFromText = static function (string $rawValue, int $fallbackTs): string {
    $value = trim($rawValue);
    if ($value === '') {
        return date('Y-m-d H:i:s', $fallbackTs);
    }

    $monthMap = [
        'januari' => 'January',
        'februari' => 'February',
        'maret' => 'March',
        'april' => 'April',
        'mei' => 'May',
        'juni' => 'June',
        'juli' => 'July',
        'agustus' => 'August',
        'september' => 'September',
        'oktober' => 'October',
        'november' => 'November',
        'desember' => 'December',
    ];

    $normalized = $value;
    foreach ($monthMap as $idMonth => $enMonth) {
        $normalized = preg_replace('/\b' . preg_quote($idMonth, '/') . '\b/iu', $enMonth, $normalized) ?? $normalized;
    }

    $normalized = preg_replace('/\s+/', ' ', trim($normalized)) ?? $normalized;
    $ts = strtotime($normalized);
    if ($ts === false) {
        $ts = strtotime($value);
    }

    if ($ts === false || $ts <= 0) {
        $ts = $fallbackTs;
    }

    return date('Y-m-d H:i:s', $ts);
};

$parseWebNewsFromHtml = static function (string $html, int $take, int $noteChars, int $fallbackNowTs) use ($parsePublishedAtFromText): array {
    $trimmedHtml = trim($html);
    if ($trimmedHtml === '') {
        return [
            'status' => false,
            'message' => 'Respon website kosong.',
            'items' => [],
            'reason' => 'empty_response',
        ];
    }

    $isCloudflareChallenge = preg_match('/just a moment|cf-mitigated|__cf_chl|challenge-platform|enable javascript and cookies to continue/i', $trimmedHtml) === 1;
    if ($isCloudflareChallenge) {
        return [
            'status' => false,
            'message' => 'Website sumber sedang menggunakan proteksi anti-bot.',
            'items' => [],
            'reason' => 'cloudflare_challenge',
        ];
    }

    $cleanHtml = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $trimmedHtml) ?? $trimmedHtml;
    $cleanHtml = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', $cleanHtml) ?? $cleanHtml;
    $cleanHtml = preg_replace('/<noscript\b[^>]*>.*?<\/noscript>/is', ' ', $cleanHtml) ?? $cleanHtml;
    $cleanHtml = preg_replace('/<(br|\/p|\/div|\/li|\/tr|\/h[1-6])[^>]*>/i', "\n", $cleanHtml) ?? $cleanHtml;

    $plainText = strip_tags($cleanHtml);
    $plainText = html_entity_decode($plainText, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $plainText = str_replace(["\r\n", "\r"], "\n", $plainText);
    $plainText = preg_replace("/\n{2,}/", "\n", $plainText) ?? $plainText;
    $plainText = preg_replace('/[ \t]{2,}/', ' ', $plainText) ?? $plainText;

    $newsMarkerPos = stripos($plainText, 'Berita Terbaru');
    if ($newsMarkerPos !== false) {
        $plainText = substr($plainText, $newsMarkerPos);
    }

    $lines = array_values(array_filter(array_map(static function ($line): string {
        return trim((string)$line);
    }, explode("\n", $plainText)), static function (string $line): bool {
        if ($line === '') {
            return false;
        }
        if (mb_strlen($line) < 3) {
            return false;
        }
        if (preg_match('/^(home|dashboard|close info|users online|halaman di load|penggunaan memory)$/i', $line) === 1) {
            return false;
        }
        return true;
    }));

    if (count($lines) === 0) {
        return [
            'status' => false,
            'message' => 'Konten website tidak memiliki baris berita yang bisa diproses.',
            'items' => [],
            'reason' => 'no_lines',
        ];
    }

    $datePattern = '/^\d{1,2}\s+(januari|februari|maret|april|mei|juni|juli|agustus|september|oktober|november|desember|january|february|march|april|may|june|july|august|september|october|november|december)\s+\d{4}(?:,\s*\d{1,2}:\d{2}(?::\d{2})?)?$/iu';
    $datePatternAlt = '/^\d{4}-\d{2}-\d{2}(?:\s+\d{2}:\d{2}(?::\d{2})?)?$/';

    $blocks = [];
    $currentDate = '';
    $currentLines = [];

    foreach ($lines as $line) {
        $isDateLine = preg_match($datePattern, $line) === 1 || preg_match($datePatternAlt, $line) === 1;
        if ($isDateLine) {
            if ($currentDate !== '' && count($currentLines) > 0) {
                $blocks[] = [
                    'date' => $currentDate,
                    'lines' => $currentLines,
                ];
            }
            $currentDate = $line;
            $currentLines = [];
            continue;
        }

        if ($currentDate !== '') {
            $currentLines[] = $line;
        }
    }
    if ($currentDate !== '' && count($currentLines) > 0) {
        $blocks[] = [
            'date' => $currentDate,
            'lines' => $currentLines,
        ];
    }

    $items = [];
    if (count($blocks) > 0) {
        foreach ($blocks as $index => $block) {
            $rowLines = array_values(array_filter(array_map(static function ($value): string {
                return trim((string)$value);
            }, (array)($block['lines'] ?? [])), static fn (string $value): bool => $value !== ''));
            if (count($rowLines) === 0) {
                continue;
            }

            $title = mb_substr($rowLines[0], 0, 160);
            $summarySource = array_slice($rowLines, 0, 3);
            $summary = mb_substr(implode(' | ', $summarySource), 0, 320);
            $content = mb_substr(implode("\n", array_slice($rowLines, 0, 20)), 0, $noteChars);
            $fallbackTs = $fallbackNowTs - $index;
            $publishedAt = $parsePublishedAtFromText((string)($block['date'] ?? ''), $fallbackTs);
            $idSeed = (string)($block['date'] ?? '') . '|' . $title;

            $items[] = [
                'id' => 'web-news-' . md5($idSeed),
                'title' => $title,
                'summary' => $summary,
                'content' => $content,
                'source_name' => 'Odyssiavault',
                'source_url' => '',
                'published_at' => $publishedAt,
                'created_at' => $publishedAt,
            ];

            if (count($items) >= $take) {
                break;
            }
        }
    }

    if (count($items) === 0) {
        $keywords = ['update', 'harga', 'refill', 'followers', 'likes', 'views', 'instagram', 'tiktok', 'youtube'];
        foreach ($lines as $index => $line) {
            $lineLower = mb_strtolower($line);
            $matched = false;
            foreach ($keywords as $keyword) {
                if (str_contains($lineLower, $keyword)) {
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                continue;
            }

            $publishedAt = date('Y-m-d H:i:s', $fallbackNowTs - $index);
            $items[] = [
                'id' => 'web-line-' . md5($line . '|' . $index),
                'title' => mb_substr($line, 0, 160),
                'summary' => mb_substr($line, 0, 320),
                'content' => mb_substr($line, 0, $noteChars),
                'source_name' => 'Odyssiavault',
                'source_url' => '',
                'published_at' => $publishedAt,
                'created_at' => $publishedAt,
            ];

            if (count($items) >= $take) {
                break;
            }
        }
    }

    if (count($items) === 0) {
        return [
            'status' => false,
            'message' => 'Struktur halaman berita belum cocok untuk parser otomatis.',
            'items' => [],
            'reason' => 'parse_failed',
        ];
    }

    return [
        'status' => true,
        'message' => 'Berhasil mengambil berita dari web sumber.',
        'items' => $items,
        'reason' => 'ok',
    ];
};

$fetchWebNews = static function () use (
    $webEnabled,
    $webSourceUrl,
    $webLimit,
    $providerNoteChars,
    $webTimeout,
    $webCacheTtl,
    $webFailCacheTtl,
    $webUserAgent,
    $parseWebNewsFromHtml
): array {
    if (!$webEnabled) {
        return [
            'status' => false,
            'message' => 'Mode web source tidak aktif.',
            'items' => [],
            'from_cache' => false,
            'reason' => 'disabled',
        ];
    }

    $cacheDir = dirname(__DIR__, 2) . '/storage/cache/news';
    $cacheFile = $cacheDir . '/buzzerpanel_web_news.json';
    $nowTs = time();

    $cachedPayload = [];
    if (is_file($cacheFile)) {
        $raw = @file_get_contents($cacheFile);
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $cachedPayload = $decoded;
            }
        }
    }

    if ($cachedPayload !== []) {
        $fetchedAt = (int)($cachedPayload['fetched_at'] ?? 0);
        $cachedStatus = (($cachedPayload['status'] ?? false) === true);
        $ttl = $cachedStatus ? $webCacheTtl : $webFailCacheTtl;
        $age = $fetchedAt > 0 ? ($nowTs - $fetchedAt) : PHP_INT_MAX;
        if ($fetchedAt > 0 && $age >= 0 && $age <= $ttl) {
            return [
                'status' => $cachedStatus,
                'message' => (string)($cachedPayload['message'] ?? ''),
                'items' => is_array($cachedPayload['items'] ?? null) ? (array)$cachedPayload['items'] : [],
                'from_cache' => true,
                'reason' => (string)($cachedPayload['reason'] ?? ($cachedStatus ? 'cache' : 'cache_failed')),
            ];
        }
    }

    $headers = [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Cache-Control: no-cache',
        'Pragma: no-cache',
    ];

    $html = '';
    if (function_exists('curl_init')) {
        $ch = curl_init($webSourceUrl);
        if ($ch !== false) {
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => $webTimeout,
                CURLOPT_CONNECTTIMEOUT => min(8, max(3, (int)floor($webTimeout / 2))),
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_USERAGENT => $webUserAgent !== '' ? $webUserAgent : 'Mozilla/5.0',
            ]);
            $result = curl_exec($ch);
            if (is_string($result)) {
                $html = $result;
            }
            curl_close($ch);
        }
    }

    if ($html === '') {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: " . ($webUserAgent !== '' ? $webUserAgent : 'Mozilla/5.0') . "\r\nAccept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8\r\nCache-Control: no-cache\r\nPragma: no-cache\r\n",
                'timeout' => max(3, $webTimeout),
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $result = @file_get_contents($webSourceUrl, false, $ctx);
        if (is_string($result)) {
            $html = $result;
        }
    }

    $parsed = $parseWebNewsFromHtml($html, $webLimit, $providerNoteChars, $nowTs);
    $payloadToCache = [
        'status' => (($parsed['status'] ?? false) === true),
        'message' => (string)($parsed['message'] ?? ''),
        'reason' => (string)($parsed['reason'] ?? ''),
        'items' => is_array($parsed['items'] ?? null) ? (array)$parsed['items'] : [],
        'fetched_at' => $nowTs,
    ];

    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0777, true);
    }
    if (is_dir($cacheDir)) {
        $encoded = json_encode($payloadToCache, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if (is_string($encoded) && $encoded !== '') {
            @file_put_contents($cacheFile, $encoded, LOCK_EX);
        }
    }

    if (($parsed['status'] ?? false) === true) {
        return [
            'status' => true,
            'message' => (string)($parsed['message'] ?? ''),
            'items' => is_array($parsed['items'] ?? null) ? (array)$parsed['items'] : [],
            'from_cache' => false,
            'reason' => (string)($parsed['reason'] ?? 'ok'),
        ];
    }

    if (
        ($cachedPayload['status'] ?? false) === true
        && is_array($cachedPayload['items'] ?? null)
        && count((array)$cachedPayload['items']) > 0
    ) {
        return [
            'status' => true,
            'message' => (string)($parsed['message'] ?? 'Menggunakan cache berita terakhir.'),
            'items' => (array)$cachedPayload['items'],
            'from_cache' => true,
            'reason' => 'stale_cache',
        ];
    }

    return [
        'status' => false,
        'message' => (string)($parsed['message'] ?? 'Gagal mengambil berita dari web sumber.'),
        'items' => [],
        'from_cache' => false,
        'reason' => (string)($parsed['reason'] ?? 'fetch_failed'),
    ];
};

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

if ($webEnabled) {
    $webResult = $fetchWebNews();
    if (($webResult['status'] ?? false) === true) {
        $webNews = is_array($webResult['items'] ?? null) ? (array)$webResult['items'] : [];
        $webFetchMeta['status'] = $webResult['from_cache'] ? 'ok_cache' : 'ok';
        $webFetchMeta['message'] = (string)($webResult['message'] ?? '');
        $webFetchMeta['from_cache'] = (bool)($webResult['from_cache'] ?? false);
    } else {
        $webFetchMeta['status'] = 'failed';
        $webFetchMeta['message'] = (string)($webResult['message'] ?? 'Gagal mengambil berita web.');
        $webFetchMeta['from_cache'] = (bool)($webResult['from_cache'] ?? false);
    }
}

if ($providerEnabled && $client->isConfigured()) {
    $useMappedCacheRows = false;
    $services = [];

    foreach ($serviceVariantFallback($providerVariant) as $variant) {
        $bootstrapRows = $loadLatestBootstrapServices($variant, $providerLimit);
        if ($bootstrapRows !== []) {
            $services = $bootstrapRows;
            $useMappedCacheRows = true;
            break;
        }

        $cachedRows = $loadMappedServiceCache($variant);
        if ($cachedRows !== []) {
            $services = $cachedRows;
            $useMappedCacheRows = true;
            break;
        }
    }

    if ($services === []) {
        $providerResult = $client->services($providerVariant);
        if (($providerResult['status'] ?? false) === true) {
            $services = (array)($providerResult['data'] ?? []);
        }
    }

    if ($services === []) {
        foreach ($serviceVariantFallback($providerVariant) as $variant) {
            $bootstrapRows = $loadLatestBootstrapServices($variant, $providerLimit);
            if ($bootstrapRows !== []) {
                $services = $bootstrapRows;
                $useMappedCacheRows = true;
                break;
            }

            $cachedRows = $loadMappedServiceCache($variant);
            if ($cachedRows !== []) {
                $services = $cachedRows;
                $useMappedCacheRows = true;
                break;
            }
        }
    }

    if ($services !== []) {
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
            $status = $useMappedCacheRows
                ? trim((string)($service['provider_service_status'] ?? $service['status'] ?? ''))
                : trim((string)($service['status'] ?? ''));
            $min = (int)($service['min'] ?? 0);
            $max = (int)($service['max'] ?? 0);
            $sellPrice = $useMappedCacheRows
                ? (int)($service['sell_price_per_1000'] ?? $service['sell_price'] ?? 0)
                : $pricing->sellPricePer1000($service);
            if ($sellPrice <= 0) {
                $sellPrice = $pricing->sellPricePer1000($service);
            }
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

$mergeNewsUnique = static function (array ...$groups): array {
    $seen = [];
    $out = [];
    foreach ($groups as $group) {
        foreach ($group as $item) {
            if (!is_array($item)) {
                continue;
            }

            $id = trim((string)($item['id'] ?? ''));
            $title = trim((string)($item['title'] ?? ''));
            $publishedAt = trim((string)($item['published_at'] ?? $item['created_at'] ?? ''));
            $fingerprint = $id !== '' ? $id : md5($title . '|' . $publishedAt);
            if (isset($seen[$fingerprint])) {
                continue;
            }

            $seen[$fingerprint] = true;
            $out[] = $item;
        }
    }

    return $out;
};

$news = [];
switch ($sourceMode) {
    case 'web':
    case 'web_only':
        $news = $webNews;
        break;
    case 'manual':
        $news = $manualNews;
        break;
    case 'web_provider':
        $news = $mergeNewsUnique($webNews, $providerNews, $manualNews);
        break;
    case 'web_hybrid':
        $news = $mergeNewsUnique($webNews, $providerNews, $manualNews);
        break;
    case 'provider':
    case 'provider_only':
        $news = $webNews !== [] ? $mergeNewsUnique($webNews, $providerNews) : $providerNews;
        break;
    case 'manual_provider':
        $news = $webNews !== [] ? $mergeNewsUnique($webNews, $providerNews, $manualNews) : $mergeNewsUnique($providerNews, $manualNews);
        break;
    case 'hybrid':
    default:
        $news = $mergeNewsUnique($webNews, $providerNews, $manualNews);
        break;
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
        'meta' => [
            'web_source_url' => $webEnabled ? $webSourceUrl : '',
            'web_fetch_status' => (string)($webFetchMeta['status'] ?? 'skipped'),
            'web_fetch_message' => (string)($webFetchMeta['message'] ?? ''),
            'web_from_cache' => (bool)($webFetchMeta['from_cache'] ?? false),
            'total_web_news' => count($webNews),
            'total_provider_news' => count($providerNews),
            'total_manual_news' => count($manualNews),
        ],
    ],
]);
