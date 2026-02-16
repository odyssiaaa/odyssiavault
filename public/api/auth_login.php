<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$input = getRequestInput();
$identity = trim((string)($input['identity'] ?? $input['username'] ?? $input['email'] ?? ''));
$password = (string)($input['password'] ?? '');

if ($identity === '' || $password === '') {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Username dan password wajib diisi.'],
    ], 422);
}

$stmt = $pdo->prepare('SELECT id, username, email, full_name, password_hash, balance, role, is_active FROM users WHERE username = :identity_username OR email = :identity_email LIMIT 1');
$stmt->execute([
    'identity_username' => $identity,
    'identity_email' => $identity,
]);
$user = $stmt->fetch();

if (!is_array($user) || (int)$user['is_active'] !== 1 || !password_verify($password, (string)$user['password_hash'])) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Username atau password salah.'],
    ], 401);
}

setAuthUser((int)$user['id']);

$updateStmt = $pdo->prepare('UPDATE users SET last_login_at = :last_login_at, updated_at = :updated_at WHERE id = :id');
$now = nowDateTime();
$updateStmt->execute([
    'last_login_at' => $now,
    'updated_at' => $now,
    'id' => (int)$user['id'],
]);

jsonResponse([
    'status' => true,
    'data' => [
        'msg' => 'Login berhasil.',
        'user' => [
            'id' => (int)$user['id'],
            'username' => (string)$user['username'],
            'email' => (string)$user['email'],
            'full_name' => (string)($user['full_name'] ?: $user['username']),
            'role' => (string)$user['role'],
            'balance' => (int)$user['balance'],
        ],
    ],
]);
