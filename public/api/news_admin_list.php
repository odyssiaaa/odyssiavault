<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$user = requireAuth($pdo);
requireAdmin($user);

$limit = min(200, max(1, sanitizeQuantity($_GET['limit'] ?? 50)));
$status = trim((string)($_GET['status'] ?? 'all'));

$where = '';
if ($status === 'published') {
    $where = 'WHERE n.is_published = 1';
} elseif ($status === 'draft') {
    $where = 'WHERE n.is_published = 0';
}

$sql = sprintf(
    'SELECT n.id, n.title, n.summary, n.content, n.source_name, n.source_url, n.is_published, n.published_at, n.created_at, n.updated_at, u.username AS author
     FROM news_posts n
     INNER JOIN users u ON u.id = n.created_by
     %s
     ORDER BY n.published_at DESC, n.id DESC
     LIMIT :limit',
    $where
);

try {
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $news = $stmt->fetchAll();
} catch (Throwable $e) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Gagal memuat berita admin. Pastikan tabel news_posts sudah di-import.'],
    ], 500);
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

jsonResponse([
    'status' => true,
    'data' => [
        'status' => $status,
        'news' => $news,
    ],
]);
