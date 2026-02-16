<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$input = getRequestInput();
$username = trim((string)($input['username'] ?? ''));
$password = (string)($input['password'] ?? '');

$retryAfter = null;
if (!rateLimitAllow('auth_register', 6, 300, $retryAfter)) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Terlalu banyak percobaan registrasi. Coba lagi nanti.'],
    ], 429);
}

if (!preg_match('/^[a-zA-Z0-9_]{4,30}$/', $username)) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Username minimal 4 karakter, hanya huruf/angka/underscore.'],
    ], 422);
}

if (mb_strlen($password) < 6) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Password minimal 6 karakter.'],
    ], 422);
}

// Keep compatibility with the existing schema by deriving profile fields from username.
$fullName = $username;
$email = sprintf('%s@odyssiavault.local', mb_strtolower($username));

$existsStmt = $pdo->prepare('SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1');
$existsStmt->execute([
    'username' => $username,
    'email' => $email,
]);

if ($existsStmt->fetch()) {
    jsonResponse([
        'status' => false,
        'data' => ['msg' => 'Username sudah terdaftar.'],
    ], 409);
}

$totalUsers = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$role = $totalUsers === 0 ? 'admin' : 'user';
$initialBalance = max(0, (int)($appConfig['default_new_user_balance'] ?? 0));

$insertStmt = $pdo->prepare('INSERT INTO users (username, email, full_name, password_hash, balance, role, is_active, created_at, updated_at) VALUES (:username, :email, :full_name, :password_hash, :balance, :role, 1, :created_at, :updated_at)');
$now = nowDateTime();
$insertStmt->execute([
    'username' => $username,
    'email' => $email,
    'full_name' => $fullName,
    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    'balance' => $initialBalance,
    'role' => $role,
    'created_at' => $now,
    'updated_at' => $now,
]);

$userId = (int)$pdo->lastInsertId();
setAuthUser($userId);

if ($initialBalance > 0) {
    insertBalanceTransaction(
        $pdo,
        $userId,
        'credit',
        $initialBalance,
        'Saldo awal pendaftaran',
        'register',
        $userId
    );
}

jsonResponse([
    'status' => true,
    'data' => [
        'msg' => 'Registrasi berhasil.',
        'user' => [
            'id' => $userId,
            'username' => $username,
            'email' => $email,
            'full_name' => $username,
            'role' => $role,
            'balance' => $initialBalance,
        ],
    ],
]);
