<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$user = requireAuth($pdo);
requireAdmin($user);

$input = getRequestInput();

$newsId = sanitizeQuantity($input['id'] ?? '');
$title = trim((string)($input['title'] ?? ''));
$summary = trim((string)($input['summary'] ?? ''));
$content = trim((string)($input['content'] ?? ''));
$sourceName = trim((string)($input['source_name'] ?? 'Odyssiavault'));
$sourceUrl = trim((string)($input['source_url'] ?? ''));
$isPublishedRaw = $input['is_published'] ?? 1;
$publishedAtRaw = trim((string)($input['published_at'] ?? ''));

if (mb_strlen($title) < 5) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Judul berita minimal 5 karakter.'],
    ], 422);
}

if (mb_strlen($summary) < 10) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Ringkasan berita minimal 10 karakter.'],
    ], 422);
}

if (mb_strlen($content) < 20) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Konten lengkap berita minimal 20 karakter.'],
    ], 422);
}

if ($sourceName === '') {
    $sourceName = 'Odyssiavault';
}

if (preg_match('/buzzer\s*panel|buzzerpanel/i', $sourceName)) {
    $sourceName = 'Odyssiavault';
}

if ($sourceUrl !== '' && preg_match('/buzzerpanel\.id/i', $sourceUrl)) {
    $sourceUrl = '';
}

if ($sourceUrl !== '' && !filter_var($sourceUrl, FILTER_VALIDATE_URL)) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Format URL sumber berita tidak valid.'],
    ], 422);
}

$isPublished = in_array((string)$isPublishedRaw, ['1', 'true', 'yes', 'on'], true)
    || $isPublishedRaw === 1
    || $isPublishedRaw === true;

$publishedAt = nowDateTime();
if ($publishedAtRaw !== '') {
    $timestamp = strtotime($publishedAtRaw);
    if ($timestamp === false) {
        jsonResponse([
            'status' => false,
            'data' => ['msg' => 'Format tanggal publish tidak valid.'],
        ], 422);
    }
    $publishedAt = date('Y-m-d H:i:s', $timestamp);
}

$now = nowDateTime();

try {
    if ($newsId > 0) {
        $checkStmt = $pdo->prepare('SELECT id FROM news_posts WHERE id = :id LIMIT 1');
        $checkStmt->execute(['id' => $newsId]);
        if (!$checkStmt->fetch()) {
            jsonResponse([
                'status' => false,
                'data' => ['msg' => 'Data berita tidak ditemukan.'],
            ], 404);
        }

        $stmt = $pdo->prepare('UPDATE news_posts SET title = :title, summary = :summary, content = :content, source_name = :source_name, source_url = :source_url, is_published = :is_published, published_at = :published_at, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'title' => mb_substr($title, 0, 180),
            'summary' => mb_substr($summary, 0, 5000),
            'content' => mb_substr($content, 0, 65000),
            'source_name' => mb_substr($sourceName, 0, 120),
            'source_url' => $sourceUrl !== '' ? mb_substr($sourceUrl, 0, 500) : null,
            'is_published' => $isPublished ? 1 : 0,
            'published_at' => $publishedAt,
            'updated_at' => $now,
            'id' => $newsId,
        ]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO news_posts (title, summary, content, source_name, source_url, is_published, published_at, created_by, created_at, updated_at) VALUES (:title, :summary, :content, :source_name, :source_url, :is_published, :published_at, :created_by, :created_at, :updated_at)');
        $stmt->execute([
            'title' => mb_substr($title, 0, 180),
            'summary' => mb_substr($summary, 0, 5000),
            'content' => mb_substr($content, 0, 65000),
            'source_name' => mb_substr($sourceName, 0, 120),
            'source_url' => $sourceUrl !== '' ? mb_substr($sourceUrl, 0, 500) : null,
            'is_published' => $isPublished ? 1 : 0,
            'published_at' => $publishedAt,
            'created_by' => (int)$user['id'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $newsId = (int)$pdo->lastInsertId();
    }

    $resultStmt = $pdo->prepare('SELECT id, title, summary, content, source_name, source_url, is_published, published_at, created_at, updated_at FROM news_posts WHERE id = :id LIMIT 1');
    $resultStmt->execute(['id' => $newsId]);
    $news = $resultStmt->fetch();
} catch (Throwable $e) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Gagal menyimpan berita. Pastikan tabel news_posts sudah di-import.'],
    ], 500);
}

jsonResponse([
    'status' => true,
    'data' => [
        'msg' => 'Berita berhasil disimpan.',
        'news' => $news,
    ],
]);
