<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$user = requireAuth($pdo);
requireAdmin($user);

$input = getRequestInput();
$newsId = sanitizeQuantity($input['id'] ?? '');

if ($newsId <= 0) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'ID berita wajib diisi.'],
    ], 422);
}

try {
    $stmt = $pdo->prepare('DELETE FROM news_posts WHERE id = :id');
    $stmt->execute(['id' => $newsId]);
} catch (Throwable $e) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Gagal menghapus berita. Pastikan tabel news_posts sudah di-import.'],
    ], 500);
}

if ($stmt->rowCount() < 1) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Berita tidak ditemukan atau sudah dihapus.'],
    ], 404);
}

jsonResponse([
    'status' => true,
    'data' => [
        'msg' => 'Berita berhasil dihapus.',
    ],
]);
