<?php

declare(strict_types=1);

const AUTH_SESSION_KEY = 'auth_user_id';
const AUTH_TOKEN_COOKIE = 'odyssiavault_auth';
const AUTH_TOKEN_TTL_SECONDS = 2592000; // 30 hari

function jsonResponse(array $payload, int $status = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function registerApiErrorHandlers(): void
{
    static $registered = false;
    if ($registered) {
        return;
    }
    $registered = true;

    @ini_set('display_errors', '0');
    @ini_set('html_errors', '0');
    if (ob_get_level() === 0) {
        ob_start();
    }

    set_error_handler(static function (
        int $severity,
        string $message,
        string $file = '',
        int $line = 0
    ): bool {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        throw new ErrorException($message, 0, $severity, $file, $line);
    });

    set_exception_handler(static function (Throwable $e): void {
        error_log('Unhandled API exception: ' . $e->getMessage());

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }

        echo json_encode([
            'status' => false,
            'data' => ['msg' => 'Terjadi kesalahan server.'],
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    });

    register_shutdown_function(static function (): void {
        $error = error_get_last();
        if (!is_array($error)) {
            return;
        }

        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array((int)($error['type'] ?? 0), $fatalTypes, true)) {
            return;
        }

        if (headers_sent()) {
            return;
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => false,
            'data' => ['msg' => 'Terjadi kesalahan server.'],
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    });
}

function getJsonInput(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function getRequestInput(): array
{
    return array_merge($_POST, getJsonInput());
}

function normalizeLines(?string $input): array
{
    if ($input === null) {
        return [];
    }

    $input = str_replace(["\r\n", "\r"], "\n", trim($input));
    if ($input === '') {
        return [];
    }

    $lines = array_map('trim', explode("\n", $input));
    return array_values(array_filter($lines, static fn ($line) => $line !== ''));
}

function sanitizeQuantity($value): int
{
    $digits = preg_replace('/\D+/', '', (string)$value);
    if ($digits === null || $digits === '') {
        return 0;
    }

    return (int)$digits;
}

function containsAny(string $haystack, array $needles): bool
{
    $haystack = mb_strtolower($haystack);
    foreach ($needles as $needle) {
        if (str_contains($haystack, mb_strtolower((string)$needle))) {
            return true;
        }
    }

    return false;
}

function nowDateTime(): string
{
    return date('Y-m-d H:i:s');
}

function startAppSession(array $appConfig): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $sessionSavePath = trim((string)($appConfig['session_save_path'] ?? ''));
    if ($sessionSavePath !== '') {
        if (!is_dir($sessionSavePath)) {
            @mkdir($sessionSavePath, 0777, true);
        }

        if (is_dir($sessionSavePath) && is_writable($sessionSavePath)) {
            @ini_set('session.save_path', $sessionSavePath);
        }
    }

    $sessionName = (string)($appConfig['session_name'] ?? 'odyssiavault_session');
    if ($sessionName !== '') {
        session_name($sessionName);
    }

    $isHttps = isSecureRequest();
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function authUser(PDO $pdo): ?array
{
    $userId = (int)($_SESSION[AUTH_SESSION_KEY] ?? 0);
    $fromPersistentCookie = false;

    if ($userId <= 0) {
        $tokenCookieName = authTokenCookieName();
        $token = trim((string)($_COOKIE[$tokenCookieName] ?? ''));
        $parsedToken = parseAuthToken($token);
        if (is_array($parsedToken)) {
            $candidateId = (int)($parsedToken['user_id'] ?? 0);
            if ($candidateId > 0) {
                $userId = $candidateId;
                $_SESSION[AUTH_SESSION_KEY] = $userId;
                $fromPersistentCookie = true;
            }
        }
    }

    if ($userId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT id, username, email, full_name, balance, role FROM users WHERE id = :id AND is_active = 1 LIMIT 1');
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch();

    if (!is_array($user)) {
        unset($_SESSION[AUTH_SESSION_KEY]);
        clearAuthTokenCookie();
        return null;
    }

    if ($fromPersistentCookie) {
        issueAuthTokenCookie((int)$user['id']);
    }

    return $user;
}

function requireAuth(PDO $pdo): array
{
    $user = authUser($pdo);
    if ($user === null) {
        jsonResponse([
            'status' => false,
            'data' => ['msg' => 'Silakan login terlebih dahulu.'],
        ], 401);
    }

    return $user;
}

function requireAdmin(array $user): void
{
    if ((string)($user['role'] ?? 'user') !== 'admin') {
        jsonResponse([
            'status' => false,
            'data' => ['msg' => 'Akses ditolak. Hanya admin.'],
        ], 403);
    }
}

function setAuthUser(int $userId): void
{
    $_SESSION[AUTH_SESSION_KEY] = $userId;
    session_regenerate_id(true);
    issueAuthTokenCookie($userId);
}

function logoutAuthUser(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }

    clearAuthTokenCookie();

    session_destroy();
}

function isSecureRequest(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }

    $forwardedProto = mb_strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    return $forwardedProto === 'https';
}

function readEnvValue(string $name, string $default = ''): string
{
    $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);
    if ($value === false || $value === null) {
        return $default;
    }

    return trim((string)$value);
}

function authTokenCookieName(): string
{
    $name = readEnvValue('AUTH_TOKEN_COOKIE_NAME', AUTH_TOKEN_COOKIE);
    if (preg_match('/^[A-Za-z0-9_-]{3,64}$/', $name) !== 1) {
        return AUTH_TOKEN_COOKIE;
    }

    return $name;
}

function authTokenSecret(): string
{
    $secret = readEnvValue('APP_AUTH_SECRET', '');
    if ($secret !== '') {
        return $secret;
    }

    // fallback supaya tetap jalan di local/dev meskipun secret belum diset
    return hash('sha256', AUTH_TOKEN_COOKIE . '::odyssiavault-fallback-secret');
}

function base64UrlEncode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function base64UrlDecode(string $value): ?string
{
    if ($value === '') {
        return null;
    }

    $decoded = strtr($value, '-_', '+/');
    $padding = strlen($decoded) % 4;
    if ($padding > 0) {
        $decoded .= str_repeat('=', 4 - $padding);
    }

    $result = base64_decode($decoded, true);
    return is_string($result) ? $result : null;
}

function buildAuthToken(int $userId, int $expiresAt): string
{
    $message = sprintf('%d|%d', $userId, $expiresAt);
    $signature = hash_hmac('sha256', $message, authTokenSecret());
    return base64UrlEncode($message . '|' . $signature);
}

function parseAuthToken(string $token): ?array
{
    $decoded = base64UrlDecode($token);
    if (!is_string($decoded) || $decoded === '') {
        return null;
    }

    $parts = explode('|', $decoded, 3);
    if (count($parts) !== 3) {
        return null;
    }

    [$userIdRaw, $expiresRaw, $signature] = $parts;
    if (!preg_match('/^\d+$/', $userIdRaw) || !preg_match('/^\d+$/', $expiresRaw)) {
        return null;
    }

    $userId = (int)$userIdRaw;
    $expiresAt = (int)$expiresRaw;
    if ($userId <= 0 || $expiresAt <= time()) {
        return null;
    }

    $message = $userIdRaw . '|' . $expiresRaw;
    $expectedSignature = hash_hmac('sha256', $message, authTokenSecret());
    if (!hash_equals($expectedSignature, $signature)) {
        return null;
    }

    return [
        'user_id' => $userId,
        'expires_at' => $expiresAt,
    ];
}

function issueAuthTokenCookie(int $userId): void
{
    if ($userId <= 0 || headers_sent()) {
        return;
    }

    $expiresAt = time() + AUTH_TOKEN_TTL_SECONDS;
    $token = buildAuthToken($userId, $expiresAt);

    setcookie(authTokenCookieName(), $token, [
        'expires' => $expiresAt,
        'path' => '/',
        'secure' => isSecureRequest(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function clearAuthTokenCookie(): void
{
    if (headers_sent()) {
        return;
    }

    setcookie(authTokenCookieName(), '', [
        'expires' => time() - 42000,
        'path' => '/',
        'secure' => isSecureRequest(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function mapProviderStatus(?string $status): string
{
    $status = trim((string)$status);
    if ($status === '') {
        return 'Pending';
    }

    $lower = mb_strtolower($status);

    if (containsAny($lower, ['success', 'completed', 'complete', 'done'])) {
        return 'Completed';
    }

    if (containsAny($lower, ['partial'])) {
        return 'Partial';
    }

    if (containsAny($lower, ['cancel', 'failed', 'fail', 'error', 'refund'])) {
        return 'Failed';
    }

    if (containsAny($lower, ['process', 'progress', 'pending', 'queue'])) {
        return 'Processing';
    }

    return ucwords($status);
}

function mapProviderStatusLifecycle(?string $status): string
{
    $status = trim((string)$status);
    if ($status === '') {
        return 'Diproses';
    }

    $lower = mb_strtolower($status);

    if (containsAny($lower, ['success', 'completed', 'complete', 'done', 'selesai'])) {
        return 'Selesai';
    }

    if (containsAny($lower, ['cancel', 'failed', 'fail', 'error', 'refund', 'partial', 'dibatalkan'])) {
        return 'Dibatalkan';
    }

    return 'Diproses';
}

function mapProviderRefillStatusLifecycle(?string $status): string
{
    $status = trim((string)$status);
    if ($status === '') {
        return 'Diproses';
    }

    $lower = mb_strtolower($status);

    if (containsAny($lower, ['success', 'completed', 'complete', 'done', 'selesai'])) {
        return 'Selesai';
    }

    if (containsAny($lower, ['cancel', 'failed', 'fail', 'error', 'reject', 'partial', 'dibatalkan'])) {
        return 'Dibatalkan';
    }

    if (containsAny($lower, ['pending', 'process', 'progress', 'queue', 'wait'])) {
        return 'Diproses';
    }

    return 'Diproses';
}

function expireUnpaidOrders(PDO $pdo): void
{
    try {
        $stmt = $pdo->prepare(
            'UPDATE orders
             SET status = :cancelled_status, error_message = :error_message, updated_at = :updated_at
             WHERE status = :waiting_status
               AND payment_deadline_at IS NOT NULL
               AND payment_deadline_at < :now
               AND payment_confirmed_at IS NULL'
        );
        $stmt->execute([
            'cancelled_status' => 'Dibatalkan',
            'error_message' => 'Batas waktu pembayaran habis',
            'updated_at' => nowDateTime(),
            'waiting_status' => 'Menunggu Pembayaran',
            'now' => nowDateTime(),
        ]);
    } catch (Throwable $e) {
        // Ignore auto-expire failures to avoid blocking core requests.
    }
}

function checkoutPaymentMethods(array $config): array
{
    $checkout = (array)($config['checkout'] ?? []);
    $rawMethods = (array)($checkout['payment_methods'] ?? []);
    $normalized = [];

    foreach ($rawMethods as $method) {
        if (!is_array($method)) {
            continue;
        }

        $code = mb_strtolower(trim((string)($method['code'] ?? '')));
        $name = trim((string)($method['name'] ?? ''));
        $accountName = trim((string)($method['account_name'] ?? ''));
        $accountNumber = trim((string)($method['account_number'] ?? ''));

        if ($code === '' || $name === '' || $accountNumber === '') {
            continue;
        }

        $normalized[] = [
            'code' => $code,
            'name' => $name,
            'account_name' => $accountName,
            'account_number' => $accountNumber,
            'note' => trim((string)($method['note'] ?? '')),
        ];
    }

    if ($normalized !== []) {
        return $normalized;
    }

    return [
        [
            'code' => 'bca',
            'name' => 'Bank BCA',
            'account_name' => 'Odyssiavault',
            'account_number' => 'ISI_REKENING_BCA',
            'note' => '',
        ],
        [
            'code' => 'dana',
            'name' => 'DANA',
            'account_name' => 'Odyssiavault',
            'account_number' => 'ISI_NOMOR_DANA',
            'note' => '',
        ],
        [
            'code' => 'gopay',
            'name' => 'GoPay',
            'account_name' => 'Odyssiavault',
            'account_number' => 'ISI_NOMOR_GOPAY',
            'note' => '',
        ],
    ];
}

function insertBalanceTransaction(
    PDO $pdo,
    int $userId,
    string $type,
    int $amount,
    string $description,
    ?string $referenceType = null,
    ?int $referenceId = null
): void {
    $stmt = $pdo->prepare('INSERT INTO balance_transactions (user_id, type, amount, description, reference_type, reference_id, created_at) VALUES (:user_id, :type, :amount, :description, :reference_type, :reference_id, :created_at)');
    $stmt->execute([
        'user_id' => $userId,
        'type' => $type,
        'amount' => $amount,
        'description' => $description,
        'reference_type' => $referenceType,
        'reference_id' => $referenceId,
        'created_at' => nowDateTime(),
    ]);
}
