<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$user = requireAuth($pdo);
$input = getRequestInput();

$currentPassword = (string)($input['current_password'] ?? '');
$newPassword = (string)($input['new_password'] ?? '');
$confirmPassword = (string)($input['confirm_password'] ?? '');

if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Semua field password wajib diisi.'],
    ], 422);
}

if (mb_strlen($newPassword) < 6) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Password baru minimal 6 karakter.'],
    ], 422);
}

if ($newPassword !== $confirmPassword) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Konfirmasi password baru tidak sama.'],
    ], 422);
}

try {
    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => (int)$user['id']]);
    $row = $stmt->fetch();
} catch (Throwable $e) {
    error_log('Change password load user failed: ' . $e->getMessage());
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Gagal memproses pengaturan akun.'],
    ], 500);
}

if (!is_array($row) || !password_verify($currentPassword, (string)($row['password_hash'] ?? ''))) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Password saat ini tidak sesuai.'],
    ], 422);
}

if (password_verify($newPassword, (string)($row['password_hash'] ?? ''))) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Password baru tidak boleh sama dengan password lama.'],
    ], 422);
}

$now = nowDateTime();
$newHash = password_hash($newPassword, PASSWORD_DEFAULT);

try {
    $updateStmt = $pdo->prepare('UPDATE users SET password_hash = :password_hash, updated_at = :updated_at WHERE id = :id');
    $updateStmt->execute([
        'password_hash' => $newHash,
        'updated_at' => $now,
        'id' => (int)$user['id'],
    ]);
} catch (Throwable $e) {
    error_log('Change password update failed: ' . $e->getMessage());
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Gagal menyimpan password baru.'],
    ], 500);
}

jsonResponse([
    'status' => true,
    'data' => ['msg' => 'Password berhasil diperbarui.'],
]);
