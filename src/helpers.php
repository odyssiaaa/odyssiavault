<?php

declare(strict_types=1);

const AUTH_SESSION_KEY = 'auth_user_id';
const AUTH_TOKEN_COOKIE = 'odyssiavault_auth';
const AUTH_TOKEN_TTL_SECONDS = 2592000; // 30 hari
const ORDER_EXPIRE_SESSION_KEY = '__odyssiavault_expire_orders_at';
const ORDER_EXPIRE_MIN_INTERVAL_SECONDS = 30;

// Polyfill dasar untuk hosting yang tidak menyediakan ekstensi mbstring.
if (!function_exists('mb_strlen')) {
    function mb_strlen($string, $encoding = null): int
    {
        return strlen((string)$string);
    }
}

if (!function_exists('mb_substr')) {
    function mb_substr($string, $start, $length = null, $encoding = null): string
    {
        $value = (string)$string;
        $startPos = (int)$start;
        if ($length === null) {
            $result = substr($value, $startPos);
        } else {
            $result = substr($value, $startPos, (int)$length);
        }

        return is_string($result) ? $result : '';
    }
}

if (!function_exists('mb_strtolower')) {
    function mb_strtolower($string, $encoding = null): string
    {
        return strtolower((string)$string);
    }
}

if (!function_exists('mb_strtoupper')) {
    function mb_strtoupper($string, $encoding = null): string
    {
        return strtoupper((string)$string);
    }
}

// Polyfill untuk hosting PHP lama (< 8.0).
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle): bool
    {
        $haystack = (string)$haystack;
        $needle = (string)$needle;
        if ($needle === '') {
            return true;
        }

        return strpos($haystack, $needle) !== false;
    }
}

if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle): bool
    {
        $haystack = (string)$haystack;
        $needle = (string)$needle;
        if ($needle === '') {
            return true;
        }

        return strpos($haystack, $needle) === 0;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle): bool
    {
        $haystack = (string)$haystack;
        $needle = (string)$needle;
        if ($needle === '') {
            return true;
        }

        $needleLen = strlen($needle);
        if ($needleLen > strlen($haystack)) {
            return false;
        }

        return substr($haystack, -$needleLen) === $needle;
    }
}

function jsonResponse(array $payload, int $status = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
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

    $stmt = $pdo->prepare('SELECT id, username, email, full_name, balance, role, created_at, last_login_at FROM users WHERE id = :id AND is_active = 1 LIMIT 1');
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
    $https = mb_strtolower(trim((string)($_SERVER['HTTPS'] ?? '')));
    if ($https !== '' && $https !== 'off' && $https !== '0') {
        return true;
    }

    $forwardedProtoRaw = mb_strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
    if ($forwardedProtoRaw !== '') {
        $parts = array_map('trim', explode(',', $forwardedProtoRaw));
        foreach ($parts as $part) {
            if ($part === 'https') {
                return true;
            }
        }

        if (str_contains($forwardedProtoRaw, 'https')) {
            return true;
        }
    }

    $forwardedSsl = mb_strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')));
    if ($forwardedSsl === 'on' || $forwardedSsl === '1' || $forwardedSsl === 'true') {
        return true;
    }

    return (string)($_SERVER['SERVER_PORT'] ?? '') === '443';
}

function readEnvValue(string $name, string $default = ''): string
{
    $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);
    if ($value === false || $value === null) {
        return $default;
    }

    return trim((string)$value);
}

function envValueOrConfig(string $name, string $configValue = ''): string
{
    $envValue = readEnvValue($name, '');
    if ($envValue !== '') {
        return $envValue;
    }

    return trim($configValue);
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
    if (session_status() === PHP_SESSION_ACTIVE) {
        $nowTs = time();
        $lastRunTs = (int)($_SESSION[ORDER_EXPIRE_SESSION_KEY] ?? 0);
        if ($lastRunTs > 0 && ($nowTs - $lastRunTs) < ORDER_EXPIRE_MIN_INTERVAL_SECONDS) {
            return;
        }
        $_SESSION[ORDER_EXPIRE_SESSION_KEY] = $nowTs;
    }

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

        $gameStmt = $pdo->prepare(
            'UPDATE game_orders
             SET status = :cancelled_status, error_message = :error_message, updated_at = :updated_at
             WHERE status = :waiting_status
               AND payment_deadline_at IS NOT NULL
               AND payment_deadline_at < :now
               AND payment_confirmed_at IS NULL'
        );
        $gameStmt->execute([
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
    $payment = (array)($config['payment'] ?? []);
    $receiverName = trim((string)($payment['qris_receiver_name'] ?? 'Odyssiavault'));
    $accountNumber = trim((string)($payment['qris_account_number'] ?? 'Scan QRIS'));
    $note = trim((string)($payment['qris_note'] ?? 'Pembayaran hanya melalui QRIS.'));

    return [
        [
            'code' => 'qris',
            'name' => 'QRIS',
            'account_name' => $receiverName !== '' ? $receiverName : 'Odyssiavault',
            'account_number' => $accountNumber !== '' ? $accountNumber : 'Scan QRIS',
            'note' => $note,
        ],
    ];
}

function parseLooseBool($value, bool $default = false): bool
{
    if (is_bool($value)) {
        return $value;
    }

    if ($value === null) {
        return $default;
    }

    $normalized = mb_strtolower(trim((string)$value));
    if ($normalized === '') {
        return $default;
    }

    if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
        return true;
    }

    if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
        return false;
    }

    return $default;
}

function rateLimitAllow(string $key, int $maxRequests, int $windowSeconds, ?int &$retryAfterSeconds = null): bool
{
    $retryAfterSeconds = null;

    if (session_status() !== PHP_SESSION_ACTIVE) {
        return true;
    }

    $key = trim($key);
    if ($key === '') {
        return true;
    }

    $maxRequests = max(1, $maxRequests);
    $windowSeconds = max(1, $windowSeconds);
    $now = time();

    $bucketStore = $_SESSION['__rate_limit'] ?? [];
    if (!is_array($bucketStore)) {
        $bucketStore = [];
    }

    $bucket = $bucketStore[$key] ?? null;
    if (!is_array($bucket)) {
        $bucket = [
            'start' => $now,
            'count' => 0,
        ];
    }

    $start = (int)($bucket['start'] ?? $now);
    $count = (int)($bucket['count'] ?? 0);

    if (($now - $start) >= $windowSeconds) {
        $start = $now;
        $count = 0;
    }

    $count++;
    $bucketStore[$key] = [
        'start' => $start,
        'count' => $count,
    ];

    // Keep store compact.
    if (count($bucketStore) > 80) {
        $bucketStore = array_slice($bucketStore, -60, null, true);
    }

    $_SESSION['__rate_limit'] = $bucketStore;

    if ($count <= $maxRequests) {
        return true;
    }

    $retryAfterSeconds = max(1, $windowSeconds - ($now - $start));
    return false;
}

function normalizeWhatsAppRecipient(string $raw): string
{
    $digits = preg_replace('/\D+/', '', trim($raw));
    if ($digits === null || $digits === '') {
        return '';
    }

    // Indonesia number normalization:
    // 0812...  -> 62812...
    // 812...   -> 62812...
    // +62812.. -> 62812...
    if (str_starts_with($digits, '00')) {
        $digits = substr($digits, 2);
    }

    if (str_starts_with($digits, '0')) {
        $digits = '62' . substr($digits, 1);
    } elseif (str_starts_with($digits, '8')) {
        $digits = '62' . $digits;
    }

    if (strlen($digits) < 10 || strlen($digits) > 18) {
        return '';
    }

    return $digits;
}

function normalizeWhatsAppRecipients(string $raw): array
{
    $raw = trim($raw);
    if ($raw === '') {
        return [];
    }

    $parts = preg_split('/[\s,;]+/', $raw) ?: [];
    $targets = [];
    foreach ($parts as $part) {
        $recipient = normalizeWhatsAppRecipient((string)$part);
        if ($recipient === '') {
            continue;
        }

        $targets[$recipient] = true;
    }

    return array_keys($targets);
}

function formatRupiahInt($amount): string
{
    return 'Rp ' . number_format((int)round((float)$amount), 0, ',', '.');
}

/**
 * @param list<string> $responseHeaders
 */
function extractHttpStatusFromHeaders(array $responseHeaders): int
{
    foreach ($responseHeaders as $headerLine) {
        if (!is_string($headerLine)) {
            continue;
        }

        if (preg_match('/^HTTP\/\d+(?:\.\d+)?\s+(\d{3})/i', trim($headerLine), $matches) === 1) {
            return (int)$matches[1];
        }
    }

    return 0;
}

function httpRequestViaStream(string $method, string $url, ?string $body, array $headers = [], int $timeoutSeconds = 6): array
{
    $normalizedMethod = strtoupper(trim($method));
    if ($normalizedMethod === '') {
        $normalizedMethod = 'GET';
    }

    $headerLines = [];
    foreach ($headers as $name => $value) {
        if (!is_string($name) || trim($name) === '') {
            continue;
        }
        $headerLines[] = trim($name) . ': ' . (string)$value;
    }

    $httpOptions = [
        'method' => $normalizedMethod,
        'timeout' => max(3, $timeoutSeconds),
        'ignore_errors' => true,
        'header' => implode("\r\n", $headerLines),
    ];

    if ($body !== null) {
        $httpOptions['content'] = $body;
    }

    $context = stream_context_create([
        'http' => $httpOptions,
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $result = @file_get_contents($url, false, $context);
    $responseHeaders = isset($http_response_header) && is_array($http_response_header)
        ? $http_response_header
        : [];
    $status = extractHttpStatusFromHeaders($responseHeaders);

    if ($result === false) {
        $err = error_get_last();
        $msg = is_array($err) ? (string)($err['message'] ?? 'Stream request failed.') : 'Stream request failed.';
        return ['ok' => false, 'status' => $status, 'body' => $msg];
    }

    $bodyText = is_string($result) ? $result : '';
    return ['ok' => $status >= 200 && $status < 300, 'status' => $status, 'body' => $bodyText];
}

function httpPostUrlEncoded(string $url, array $payload, array $headers = [], int $timeoutSeconds = 6): array
{
    $streamHeaders = array_merge($headers, [
        'Content-Type' => 'application/x-www-form-urlencoded',
        'Accept' => 'application/json',
    ]);
    $streamBody = http_build_query($payload);

    if (!function_exists('curl_init')) {
        return httpRequestViaStream('POST', $url, $streamBody, $streamHeaders, $timeoutSeconds);
    }

    $ch = curl_init($url);
    if ($ch === false) {
        return ['ok' => false, 'status' => 0, 'body' => 'Unable to initialize cURL'];
    }

    $normalizedHeaders = [];
    foreach ($headers as $name => $value) {
        $normalizedHeaders[] = $name . ': ' . $value;
    }
    $normalizedHeaders[] = 'Content-Type: application/x-www-form-urlencoded';

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => max(3, $timeoutSeconds),
        CURLOPT_HTTPHEADER => $normalizedHeaders,
        CURLOPT_POSTFIELDS => $streamBody,
    ]);
    if (defined('CURL_IPRESOLVE_V4')) {
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    }

    $result = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errNo = curl_errno($ch);
    $errMsg = curl_error($ch);
    curl_close($ch);

    if ($errNo !== 0) {
        $fallback = httpRequestViaStream('POST', $url, $streamBody, $streamHeaders, $timeoutSeconds);
        if (($fallback['ok'] ?? false) === true) {
            return $fallback;
        }
        return ['ok' => false, 'status' => $status, 'body' => $errMsg];
    }

    $body = is_string($result) ? $result : '';
    return ['ok' => $status >= 200 && $status < 300, 'status' => $status, 'body' => $body];
}

function httpPostJson(string $url, array $payload, array $headers = [], int $timeoutSeconds = 6): array
{
    $streamHeaders = array_merge($headers, [
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ]);
    $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if (!is_string($jsonPayload)) {
        $jsonPayload = '{}';
    }

    if (!function_exists('curl_init')) {
        return httpRequestViaStream('POST', $url, $jsonPayload, $streamHeaders, $timeoutSeconds);
    }

    $ch = curl_init($url);
    if ($ch === false) {
        return ['ok' => false, 'status' => 0, 'body' => 'Unable to initialize cURL'];
    }

    $normalizedHeaders = [];
    foreach ($headers as $name => $value) {
        $normalizedHeaders[] = $name . ': ' . $value;
    }
    $normalizedHeaders[] = 'Content-Type: application/json';

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => max(3, $timeoutSeconds),
        CURLOPT_HTTPHEADER => $normalizedHeaders,
        CURLOPT_POSTFIELDS => $jsonPayload,
    ]);
    if (defined('CURL_IPRESOLVE_V4')) {
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    }

    $result = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errNo = curl_errno($ch);
    $errMsg = curl_error($ch);
    curl_close($ch);

    if ($errNo !== 0) {
        $fallback = httpRequestViaStream('POST', $url, $jsonPayload, $streamHeaders, $timeoutSeconds);
        if (($fallback['ok'] ?? false) === true) {
            return $fallback;
        }
        return ['ok' => false, 'status' => $status, 'body' => $errMsg];
    }

    $body = is_string($result) ? $result : '';
    return ['ok' => $status >= 200 && $status < 300, 'status' => $status, 'body' => $body];
}

function httpGet(string $url, array $headers = [], int $timeoutSeconds = 6): array
{
    $streamHeaders = array_merge($headers, [
        'Accept' => 'application/json,text/plain,*/*',
    ]);

    if (!function_exists('curl_init')) {
        return httpRequestViaStream('GET', $url, null, $streamHeaders, $timeoutSeconds);
    }

    $ch = curl_init($url);
    if ($ch === false) {
        return ['ok' => false, 'status' => 0, 'body' => 'Unable to initialize cURL'];
    }

    $normalizedHeaders = [];
    foreach ($headers as $name => $value) {
        $normalizedHeaders[] = $name . ': ' . $value;
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => max(3, $timeoutSeconds),
        CURLOPT_HTTPHEADER => $normalizedHeaders,
    ]);
    if (defined('CURL_IPRESOLVE_V4')) {
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    }

    $result = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errNo = curl_errno($ch);
    $errMsg = curl_error($ch);
    curl_close($ch);

    if ($errNo !== 0) {
        $fallback = httpRequestViaStream('GET', $url, null, $streamHeaders, $timeoutSeconds);
        if (($fallback['ok'] ?? false) === true) {
            return $fallback;
        }
        return ['ok' => false, 'status' => $status, 'body' => $errMsg];
    }

    $body = is_string($result) ? $result : '';
    return ['ok' => $status >= 200 && $status < 300, 'status' => $status, 'body' => $body];
}

function telegramLogPath(): string
{
    $baseDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($baseDir)) {
        @mkdir($baseDir, 0777, true);
    }

    return $baseDir . DIRECTORY_SEPARATOR . 'telegram_notify.log';
}

function logTelegramNotify(string $level, string $message, array $context = []): void
{
    $level = mb_strtoupper(trim($level));
    if ($level === '') {
        $level = 'INFO';
    }

    $line = '[' . nowDateTime() . '] [' . $level . '] ' . trim($message);
    if ($context !== []) {
        $json = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (is_string($json) && $json !== '') {
            $line .= ' ' . $json;
        }
    }

    $line .= PHP_EOL;

    @error_log($line, 3, telegramLogPath());
}

function telegramApiBaseUrls(): array
{
    $primary = trim(readEnvValue('TELEGRAM_API_BASE_URL', 'https://api.telegram.org'));
    if ($primary === '') {
        $primary = 'https://api.telegram.org';
    }

    $candidates = [
        rtrim($primary, '/'),
        'https://api.telegram.org',
    ];

    $normalized = [];
    foreach ($candidates as $candidate) {
        $candidate = trim((string)$candidate);
        if ($candidate === '') {
            continue;
        }
        $key = mb_strtolower($candidate);
        $normalized[$key] = $candidate;
    }

    return array_values($normalized);
}

function shouldRetryTelegramInsecure(array $result): bool
{
    if (($result['ok'] ?? false) === true) {
        return false;
    }

    $status = (int)($result['status'] ?? 0);
    if ($status >= 200 && $status < 300) {
        return false;
    }

    $raw = mb_strtolower(trim((string)($result['body'] ?? '')));
    if ($raw === '') {
        return $status === 0 || $status >= 500;
    }

    $keywords = [
        'ssl',
        'certificate',
        'handshake',
        'failed to enable crypto',
        'operation timed out',
        'timeout',
        'connection refused',
        'could not resolve',
        'network is unreachable',
    ];

    foreach ($keywords as $keyword) {
        if (str_contains($raw, $keyword)) {
            return true;
        }
    }

    return $status === 0 || $status >= 500;
}

function telegramHttpRequestUnsafe(string $method, string $url, array $payload = [], int $timeoutSeconds = 8): array
{
    $method = mb_strtoupper(trim($method));
    if ($method === '') {
        $method = 'GET';
    }

    $queryString = http_build_query($payload);

    if (function_exists('curl_init')) {
        $requestUrl = $url;
        $isPost = $method === 'POST';
        if (!$isPost && $queryString !== '') {
            $requestUrl .= (str_contains($requestUrl, '?') ? '&' : '?') . $queryString;
        }

        $runCurlRequest = static function (string $targetUrl, bool $isPostMode, string $payloadBody, int $timeout, array $extraOpts = []): array {
            $ch = curl_init($targetUrl);
            if ($ch === false) {
                return ['ok' => false, 'status' => 0, 'body' => 'Unable to initialize cURL'];
            }

            $opts = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_TIMEOUT => max(4, $timeout),
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_USERAGENT => 'OdyssiavaultTelegram/1.0',
            ];
            if ($isPostMode) {
                $opts[CURLOPT_POST] = true;
                $opts[CURLOPT_HTTPHEADER] = ['Content-Type: application/x-www-form-urlencoded'];
                $opts[CURLOPT_POSTFIELDS] = $payloadBody;
            }
            if (defined('CURL_IPRESOLVE_V4')) {
                $opts[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
            }
            foreach ($extraOpts as $key => $value) {
                $opts[$key] = $value;
            }

            curl_setopt_array($ch, $opts);

            $result = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            curl_close($ch);

            if ($errno === 0 && is_string($result)) {
                return ['ok' => $status >= 200 && $status < 300, 'status' => $status, 'body' => $result];
            }

            return ['ok' => false, 'status' => $status, 'body' => $error !== '' ? $error : 'Unsafe cURL request failed', 'errno' => $errno];
        };

        $first = $runCurlRequest($requestUrl, $isPost, $queryString, $timeoutSeconds);
        if (($first['ok'] ?? false) === true) {
            return $first;
        }

        $rawBody = mb_strtolower(trim((string)($first['body'] ?? '')));
        $curlErrNo = (int)($first['errno'] ?? 0);
        $host = mb_strtolower((string)(parse_url($url, PHP_URL_HOST) ?? ''));
        if (($curlErrNo === 6 || str_contains($rawBody, 'could not resolve host')) && $host === 'api.telegram.org') {
            $ipsRaw = trim(readEnvValue('TELEGRAM_API_IPS', '149.154.167.220,149.154.167.40,149.154.167.50'));
            $ips = preg_split('/[\s,;]+/', $ipsRaw) ?: [];
            $resolveTargets = [];
            foreach ($ips as $ip) {
                $ip = trim((string)$ip);
                if ($ip === '') {
                    continue;
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
                    continue;
                }
                $resolveTargets[] = $ip;
            }

            foreach ($resolveTargets as $ip) {
                $resolved = $runCurlRequest($requestUrl, $isPost, $queryString, $timeoutSeconds, [
                    CURLOPT_RESOLVE => ['api.telegram.org:443:' . $ip],
                ]);
                if (($resolved['ok'] ?? false) === true) {
                    logTelegramNotify('WARN', 'Telegram request succeeded via CURLOPT_RESOLVE fallback.', [
                        'ip' => $ip,
                        'url' => $requestUrl,
                    ]);
                    return $resolved;
                }
            }
        }

        return $first;
    }

    $requestUrl = $url;
    $body = null;
    if ($method === 'POST') {
        $body = $queryString;
    } elseif ($queryString !== '') {
        $requestUrl .= (str_contains($requestUrl, '?') ? '&' : '?') . $queryString;
    }

    return httpRequestViaStream($method, $requestUrl, $body, [], max(4, $timeoutSeconds));
}

function telegramRequest(string $method, string $botToken, string $path, array $payload, int $timeoutSeconds = 8): array
{
    $lastResult = ['ok' => false, 'status' => 0, 'description' => 'Telegram request not started', 'body' => ''];
    $method = mb_strtoupper(trim($method));
    if ($method === '') {
        $method = 'GET';
    }

    foreach (telegramApiBaseUrls() as $baseUrl) {
        $url = rtrim($baseUrl, '/') . '/bot' . $botToken . '/' . ltrim($path, '/');
        if ($method === 'POST') {
            $result = parseTelegramApiResponse(httpPostUrlEncoded($url, $payload, [], $timeoutSeconds));
        } else {
            $queryUrl = $url . '?' . http_build_query($payload);
            $result = parseTelegramApiResponse(httpGet($queryUrl, [], $timeoutSeconds));
        }

        if (($result['ok'] ?? false) === true) {
            return $result;
        }

        $lastResult = $result;

        if (shouldRetryTelegramInsecure($result)) {
            $unsafeResult = parseTelegramApiResponse(telegramHttpRequestUnsafe($method, $url, $payload, $timeoutSeconds));
            if (($unsafeResult['ok'] ?? false) === true) {
                logTelegramNotify('WARN', 'Telegram request succeeded via unsafe fallback.', [
                    'path' => $path,
                    'base_url' => $baseUrl,
                    'status' => (int)($unsafeResult['status'] ?? 0),
                ]);
                return $unsafeResult;
            }

            $lastResult = $unsafeResult;
        }
    }

    return $lastResult;
}

function discoverTelegramChatIds(string $botToken, int $timeoutSeconds = 6): array
{
    $botToken = trim($botToken);
    if ($botToken === '') {
        return [];
    }

    $result = telegramRequest('GET', $botToken, 'getUpdates', [
        'limit' => 20,
        'timeout' => 0,
    ], $timeoutSeconds);
    if (!($result['ok'] ?? false)) {
        logTelegramNotify('ERROR', 'discoverTelegramChatIds failed.', [
            'status' => (int)($result['status'] ?? 0),
            'description' => (string)($result['description'] ?? ''),
            'body' => mb_substr(trim((string)($result['body'] ?? '')), 0, 220),
        ]);
        return [];
    }

    $body = (string)($result['body'] ?? '');
    $decoded = json_decode($body, true);
    if (!is_array($decoded) || ($decoded['ok'] ?? false) !== true || !is_array($decoded['result'] ?? null)) {
        return [];
    }

    $chatIds = [];
    foreach ($decoded['result'] as $update) {
        if (!is_array($update)) {
            continue;
        }

        $containers = [];
        foreach (['message', 'edited_message', 'channel_post', 'edited_channel_post', 'my_chat_member', 'chat_member'] as $key) {
            if (isset($update[$key]) && is_array($update[$key])) {
                $containers[] = $update[$key];
            }
        }

        foreach ($containers as $container) {
            $chat = (isset($container['chat']) && is_array($container['chat'])) ? $container['chat'] : null;
            if (!$chat) {
                continue;
            }

            $candidate = isset($chat['id']) ? (string)$chat['id'] : '';
            if ($candidate === '') {
                continue;
            }

            $normalized = normalizeTelegramChatId($candidate);
            if ($normalized === '') {
                continue;
            }

            $chatIds[$normalized] = true;
        }
    }

    return array_values(array_map(static function ($value): string {
        return (string)$value;
    }, array_keys($chatIds)));
}

function buildAdminPendingPaymentMessage(array $context, array $config): string
{
    $orderId = (int)($context['order_id'] ?? 0);
    $username = trim((string)($context['username'] ?? '-'));
    $serviceName = trim((string)($context['service_name'] ?? '-'));
    $target = trim((string)($context['target'] ?? '-'));
    $quantity = (int)($context['quantity'] ?? 0);
    $total = (int)($context['total_sell_price'] ?? 0);
    $methodName = trim((string)($context['payment_method_name'] ?? 'QRIS'));
    $paymentState = trim((string)($context['payment_state'] ?? 'Menunggu Konfirmasi'));
    $payerName = trim((string)($context['payer_name'] ?? ''));
    $reference = trim((string)($context['payment_reference'] ?? ''));
    $confirmedAt = trim((string)($context['confirmed_at'] ?? nowDateTime()));

    $baseUrl = trim((string)(($config['app'] ?? [])['base_url'] ?? ''));
    $adminUrl = $baseUrl !== '' ? rtrim($baseUrl, '/') . '/?page=admin' : '';

    $lines = [
        'Notifikasi Admin - Konfirmasi Pembayaran Baru',
        'Order ID: #' . ($orderId > 0 ? (string)$orderId : '-'),
        'User: @' . ($username !== '' ? $username : '-'),
        'Layanan: ' . ($serviceName !== '' ? $serviceName : '-'),
        'Target: ' . ($target !== '' ? $target : '-'),
        'Jumlah: ' . number_format(max(0, $quantity), 0, ',', '.'),
        'Total: ' . formatRupiahInt($total),
        'Metode: ' . ($methodName !== '' ? $methodName : 'QRIS'),
        'Status Pembayaran: ' . ($paymentState !== '' ? $paymentState : 'Menunggu Konfirmasi'),
        'Nama Pengirim: ' . ($payerName !== '' ? $payerName : '-'),
        'Referensi: ' . ($reference !== '' ? $reference : '-'),
        'Waktu Konfirmasi: ' . $confirmedAt,
    ];

    if ($adminUrl !== '') {
        $lines[] = 'Panel Admin: ' . $adminUrl;
    }

    return implode("\n", $lines);
}

function notifyAdminWhatsAppPendingPayment(array $config, array $context): void
{
    try {
        $notifications = (array)($config['notifications'] ?? []);
        $wa = (isset($notifications['whatsapp']) && is_array($notifications['whatsapp']))
            ? (array)$notifications['whatsapp']
            : $notifications;

        $enabledRaw = readEnvValue('WHATSAPP_ADMIN_NOTIFY_ENABLED', '');
        $enabled = $enabledRaw !== ''
            ? parseLooseBool($enabledRaw, false)
            : parseLooseBool($wa['enabled'] ?? false, false);
        if (!$enabled) {
            return;
        }

        $provider = mb_strtolower(trim(envValueOrConfig('WHATSAPP_PROVIDER', (string)($wa['provider'] ?? 'fonnte'))));
        if ($provider === '') {
            $provider = 'fonnte';
        }

        $targetsRaw = trim(envValueOrConfig('WHATSAPP_ADMIN_PHONE', (string)($wa['admin_phone'] ?? '')));
        $targets = normalizeWhatsAppRecipients($targetsRaw);
        if ($targets === []) {
            error_log('WhatsApp admin notify skipped: WHATSAPP_ADMIN_PHONE empty/invalid.');
            return;
        }

        $message = buildAdminPendingPaymentMessage($context, $config);
        $timeoutRaw = envValueOrConfig('WHATSAPP_TIMEOUT', (string)($wa['timeout'] ?? '6'));
        $timeout = (int)$timeoutRaw;
        if ($timeout <= 0) {
            $timeout = 6;
        }

        if ($provider === 'fonnte') {
            $token = trim(envValueOrConfig('WHATSAPP_FONNTE_TOKEN', (string)($wa['fonnte_token'] ?? '')));
            if ($token === '') {
                error_log('WhatsApp admin notify skipped: WHATSAPP_FONNTE_TOKEN kosong.');
                return;
            }

            foreach ($targets as $target) {
                $result = httpPostUrlEncoded(
                    'https://api.fonnte.com/send',
                    [
                        'target' => $target,
                        'message' => $message,
                    ],
                    ['Authorization' => $token],
                    $timeout
                );
                if (!($result['ok'] ?? false)) {
                    error_log('WhatsApp Fonnte notify failed. Target: ' . $target . ' HTTP: ' . (string)($result['status'] ?? 0));
                }
            }

            return;
        }

        if ($provider === 'webhook') {
            $webhookUrl = trim(envValueOrConfig('WHATSAPP_WEBHOOK_URL', (string)($wa['webhook_url'] ?? '')));
            if ($webhookUrl === '') {
                error_log('WhatsApp admin notify skipped: WHATSAPP_WEBHOOK_URL kosong.');
                return;
            }

            $webhookSecret = trim(envValueOrConfig('WHATSAPP_WEBHOOK_SECRET', (string)($wa['webhook_secret'] ?? '')));
            $headers = [];
            if ($webhookSecret !== '') {
                $headers['X-Webhook-Secret'] = $webhookSecret;
            }

            foreach ($targets as $target) {
                $result = httpPostJson(
                    $webhookUrl,
                    [
                        'event' => 'order_waiting_admin_confirmation',
                        'to' => $target,
                        'message' => $message,
                        'order' => $context,
                    ],
                    $headers,
                    $timeout
                );
                if (!($result['ok'] ?? false)) {
                    error_log('WhatsApp webhook notify failed. Target: ' . $target . ' HTTP: ' . (string)($result['status'] ?? 0));
                }
            }

            return;
        }

        error_log('WhatsApp admin notify skipped: provider tidak didukung (' . $provider . ').');
    } catch (Throwable $e) {
        error_log('WhatsApp admin notify error: ' . $e->getMessage());
    }
}

function normalizeTelegramChatId(string $raw): string
{
    $value = trim($raw);
    if ($value === '') {
        return '';
    }

    if (preg_match('/^-?\d{5,25}$/', $value) === 1) {
        return $value;
    }

    if (preg_match('/^@[A-Za-z0-9_]{5,64}$/', $value) === 1) {
        return $value;
    }

    return '';
}

function normalizeTelegramChatIds(string $raw): array
{
    $raw = trim($raw);
    if ($raw === '') {
        return [];
    }

    $parts = preg_split('/[\s,;]+/', $raw) ?: [];
    $targets = [];
    foreach ($parts as $part) {
        $chatId = normalizeTelegramChatId((string)$part);
        if ($chatId === '') {
            continue;
        }

        $targets[$chatId] = true;
    }

    return array_values(array_map(static function ($value): string {
        return (string)$value;
    }, array_keys($targets)));
}

function trimTelegramMessage(string $message, int $maxLength = 3900): string
{
    $normalized = trim(str_replace(["\r\n", "\r"], "\n", $message));
    if ($normalized === '') {
        return '';
    }

    if ($maxLength < 200) {
        $maxLength = 200;
    }

    if (mb_strlen($normalized) <= $maxLength) {
        return $normalized;
    }

    $truncated = mb_substr($normalized, 0, $maxLength - 3);
    return rtrim($truncated) . '...';
}

function parseTelegramApiResponse(array $result): array
{
    $status = (int)($result['status'] ?? 0);
    $body = (string)($result['body'] ?? '');

    $decoded = json_decode($body, true);
    if (is_array($decoded)) {
        $ok = ($decoded['ok'] ?? false) === true;
        $description = trim((string)($decoded['description'] ?? ''));

        return [
            'ok' => $ok,
            'status' => $status,
            'description' => $description,
            'body' => $body,
        ];
    }

    return [
        'ok' => ($result['ok'] ?? false) === true,
        'status' => $status,
        'description' => '',
        'body' => $body,
    ];
}

function sendTelegramMessage(string $botToken, string $chatId, string $message, bool $disablePreview, int $timeout): array
{
    $payload = [
        'chat_id' => $chatId,
        'text' => $message,
        'disable_web_page_preview' => $disablePreview ? 'true' : 'false',
    ];
    $result = telegramRequest('POST', $botToken, 'sendMessage', $payload, $timeout);
    if (($result['ok'] ?? false) === true) {
        return $result;
    }

    // Fallback GET: beberapa hosting gratis sering bermasalah pada body POST.
    $fallback = telegramRequest('GET', $botToken, 'sendMessage', $payload, $timeout);
    if (($fallback['ok'] ?? false) === true) {
        return $fallback;
    }

    return $result;
}

function notifyAdminTelegramPendingPayment(array $config, array $context): void
{
    try {
        $notifications = (array)($config['notifications'] ?? []);
        $tg = (isset($notifications['telegram']) && is_array($notifications['telegram']))
            ? (array)$notifications['telegram']
            : $notifications;

        $enabledRaw = readEnvValue('TELEGRAM_ADMIN_NOTIFY_ENABLED', '');
        $enabled = $enabledRaw !== ''
            ? parseLooseBool($enabledRaw, false)
            : parseLooseBool($tg['enabled'] ?? false, false);
        if (!$enabled) {
            return;
        }

        $botToken = trim(envValueOrConfig('TELEGRAM_BOT_TOKEN', (string)($tg['bot_token'] ?? '')));
        if ($botToken === '') {
            error_log('Telegram admin notify skipped: TELEGRAM_BOT_TOKEN kosong.');
            return;
        }

        $timeoutRaw = envValueOrConfig('TELEGRAM_TIMEOUT', (string)($tg['timeout'] ?? '12'));
        $timeout = (int)$timeoutRaw;
        if ($timeout <= 0) {
            $timeout = 12;
        }
        if ($timeout < 6) {
            $timeout = 6;
        }

        $chatIdsRaw = trim(envValueOrConfig('TELEGRAM_ADMIN_CHAT_ID', (string)($tg['chat_id'] ?? '')));
        $chatIds = normalizeTelegramChatIds($chatIdsRaw);

        $autoDiscoverRaw = readEnvValue('TELEGRAM_AUTO_DISCOVER_CHAT_IDS', '1');
        $autoDiscover = parseLooseBool($autoDiscoverRaw, true);
        if ($autoDiscover) {
            $discoveredChatIds = discoverTelegramChatIds($botToken, $timeout);
            if ($discoveredChatIds !== []) {
                $merged = [];
                foreach ($chatIds as $chatId) {
                    $merged[(string)$chatId] = true;
                }
                foreach ($discoveredChatIds as $chatId) {
                    $merged[(string)$chatId] = true;
                }
                $chatIds = array_values(array_map(static function ($value): string {
                    return (string)$value;
                }, array_keys($merged)));
            }
        }

        if ($chatIds === []) {
            error_log('Telegram admin notify skipped: TELEGRAM_ADMIN_CHAT_ID kosong/invalid dan auto-detect belum menemukan chat.');
            logTelegramNotify('ERROR', 'Telegram notify skipped: no valid chat id.', [
                'order_id' => (int)($context['order_id'] ?? 0),
            ]);
            return;
        }

        $disablePreviewRaw = readEnvValue('TELEGRAM_DISABLE_WEB_PAGE_PREVIEW', '');
        $disablePreview = $disablePreviewRaw !== ''
            ? parseLooseBool($disablePreviewRaw, true)
            : parseLooseBool($tg['disable_web_page_preview'] ?? true, true);

        $message = trimTelegramMessage(buildAdminPendingPaymentMessage($context, $config), 3900);
        if ($message === '') {
            error_log('Telegram admin notify skipped: message empty after normalization.');
            return;
        }

        $sent = false;
        $failedChatIds = [];

        foreach ($chatIds as $chatId) {
            $chatId = (string)$chatId;
            $result = sendTelegramMessage($botToken, $chatId, $message, $disablePreview, $timeout);
            if (!($result['ok'] ?? false)) {
                $description = trim((string)($result['description'] ?? ''));
                $status = (int)($result['status'] ?? 0);
                $snippet = mb_substr(trim((string)($result['body'] ?? '')), 0, 220);
                $failedChatIds[$chatId] = true;
                error_log(
                    'Telegram notify failed. Chat: ' . $chatId
                    . ' HTTP: ' . $status
                    . ($description !== '' ? ' Desc: ' . $description : '')
                    . ($snippet !== '' ? ' Body: ' . $snippet : '')
                );
                logTelegramNotify('ERROR', 'Telegram notify failed.', [
                    'chat_id' => $chatId,
                    'status' => $status,
                    'description' => $description,
                    'body' => $snippet,
                    'order_id' => (int)($context['order_id'] ?? 0),
                ]);
                continue;
            }

            $sent = true;
            logTelegramNotify('INFO', 'Telegram notify delivered.', [
                'chat_id' => $chatId,
                'status' => (int)($result['status'] ?? 0),
                'order_id' => (int)($context['order_id'] ?? 0),
            ]);
        }

        // Jika semua chat_id gagal (umumnya chat_id salah / bot belum start),
        // coba fallback auto-discovery dari getUpdates agar notifikasi tetap terkirim.
        if (!$sent && $chatIdsRaw !== '') {
            $fallbackChatIds = discoverTelegramChatIds($botToken, $timeout);
            if ($fallbackChatIds !== []) {
                foreach ($fallbackChatIds as $chatId) {
                    $chatId = (string)$chatId;
                    if (isset($failedChatIds[$chatId])) {
                        continue;
                    }

                    $result = sendTelegramMessage($botToken, $chatId, $message, $disablePreview, $timeout);
                    if (!($result['ok'] ?? false)) {
                        $description = trim((string)($result['description'] ?? ''));
                        $status = (int)($result['status'] ?? 0);
                        $snippet = mb_substr(trim((string)($result['body'] ?? '')), 0, 220);
                        error_log(
                            'Telegram notify fallback failed. Chat: ' . $chatId
                            . ' HTTP: ' . $status
                            . ($description !== '' ? ' Desc: ' . $description : '')
                            . ($snippet !== '' ? ' Body: ' . $snippet : '')
                        );
                        logTelegramNotify('ERROR', 'Telegram notify fallback failed.', [
                            'chat_id' => $chatId,
                            'status' => $status,
                            'description' => $description,
                            'body' => $snippet,
                            'order_id' => (int)($context['order_id'] ?? 0),
                        ]);
                        continue;
                    }

                    $sent = true;
                    logTelegramNotify('INFO', 'Telegram notify delivered via fallback chat discovery.', [
                        'chat_id' => $chatId,
                        'status' => (int)($result['status'] ?? 0),
                        'order_id' => (int)($context['order_id'] ?? 0),
                    ]);
                }
            }
        }

        if (!$sent) {
            error_log('Telegram admin notify failed: no successful deliveries.');
            logTelegramNotify('ERROR', 'Telegram admin notify failed: no successful deliveries.', [
                'order_id' => (int)($context['order_id'] ?? 0),
            ]);
        }
    } catch (Throwable $e) {
        error_log('Telegram admin notify error: ' . $e->getMessage());
        logTelegramNotify('ERROR', 'Telegram admin notify exception.', [
            'order_id' => (int)($context['order_id'] ?? 0),
            'error' => $e->getMessage(),
        ]);
    }
}

function notifyAdminPendingPaymentChannels(array $config, array $context): void
{
    notifyAdminWhatsAppPendingPayment($config, $context);
    notifyAdminTelegramPendingPayment($config, $context);
}

function ensureTicketTables(PDO $pdo): bool
{
    $tableExists = static function (string $table) use ($pdo): bool {
        $table = trim($table);
        if ($table === '') {
            return false;
        }

        try {
            $stmt = $pdo->prepare(
                'SELECT 1
                 FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                   AND table_name = :table
                 LIMIT 1'
            );
            $stmt->execute(['table' => $table]);
            if ((bool)$stmt->fetchColumn()) {
                return true;
            }
        } catch (Throwable $e) {
            // Ignore and continue with fallback checks.
        }

        try {
            $stmt = $pdo->prepare('SHOW TABLES LIKE :table');
            $stmt->execute(['table' => $table]);
            if ($stmt->fetchColumn() !== false) {
                return true;
            }
        } catch (Throwable $e) {
            // Ignore and continue with final fallback.
        }

        try {
            $safeTable = str_replace('`', '``', $table);
            $pdo->query("SELECT 1 FROM `{$safeTable}` LIMIT 1");
            return true;
        } catch (Throwable $e) {
            return false;
        }
    };

    try {
        $hasTickets = $tableExists('tickets');
        $hasTicketMessages = $tableExists('ticket_messages');
        if ($hasTickets && $hasTicketMessages) {
            return true;
        }

        // Use a compatible schema first (without FK) so older/limited DB setups can still run tickets.
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS tickets (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                order_id BIGINT UNSIGNED NULL,
                subject VARCHAR(180) NOT NULL,
                category VARCHAR(80) NOT NULL DEFAULT 'Laporan',
                priority ENUM('low', 'normal', 'high', 'urgent') NOT NULL DEFAULT 'normal',
                status ENUM('open', 'answered', 'closed') NOT NULL DEFAULT 'open',
                last_message_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_tickets_user_created (user_id, created_at),
                INDEX idx_tickets_status_updated (status, updated_at),
                INDEX idx_tickets_last_message (last_message_at)
            ) ENGINE=InnoDB"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS ticket_messages (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                ticket_id BIGINT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                sender_role ENUM('user', 'admin') NOT NULL DEFAULT 'user',
                message TEXT NOT NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_ticket_messages_ticket_created (ticket_id, created_at),
                INDEX idx_ticket_messages_user_created (user_id, created_at)
            ) ENGINE=InnoDB"
        );

        $hasTickets = $tableExists('tickets');
        $hasTicketMessages = $tableExists('ticket_messages');
        return $hasTickets && $hasTicketMessages;
    } catch (Throwable $e) {
        error_log('Ensure ticket tables failed: ' . $e->getMessage());
        return false;
    }
}

function csChatCategoryBot(): string
{
    return 'Customer Service Chat BOT';
}

function csChatCategoryAdmin(): string
{
    return 'Customer Service Chat ADMIN';
}

function isCsChatCategory(string $category): bool
{
    $normalized = mb_strtolower(trim($category));
    return str_starts_with($normalized, mb_strtolower('Customer Service Chat'));
}

function isCsBotMessage(string $message): bool
{
    return str_starts_with(trim($message), '[BOT]');
}

function withCsBotPrefix(string $message): string
{
    $clean = trim($message);
    if ($clean === '') {
        $clean = 'Pesan bot kosong.';
    }
    if (isCsBotMessage($clean)) {
        return $clean;
    }
    return '[BOT] ' . $clean;
}

function resolveCsBotSenderId(PDO $pdo, int $fallbackUserId = 0): int
{
    try {
        $stmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY id ASC LIMIT 1");
        $id = (int)($stmt->fetchColumn() ?: 0);
        if ($id > 0) {
            return $id;
        }
    } catch (Throwable $e) {
        // Ignore and continue fallback.
    }

    if ($fallbackUserId > 0) {
        return $fallbackUserId;
    }

    try {
        $stmt = $pdo->query('SELECT id FROM users ORDER BY id ASC LIMIT 1');
        $id = (int)($stmt->fetchColumn() ?: 0);
        if ($id > 0) {
            return $id;
        }
    } catch (Throwable $e) {
        // Ignore and continue fallback.
    }

    return 1;
}

function findActiveCsChatTicketForUser(PDO $pdo, int $userId): ?array
{
    if ($userId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id, user_id, subject, category, priority, status, last_message_at, created_at, updated_at
         FROM tickets
         WHERE user_id = :user_id
           AND category LIKE :category_like
           AND status <> :closed_status
         ORDER BY updated_at DESC, id DESC
         LIMIT 1'
    );
    $stmt->execute([
        'user_id' => $userId,
        'category_like' => 'Customer Service Chat%',
        'closed_status' => 'closed',
    ]);
    $ticket = $stmt->fetch();

    return is_array($ticket) ? $ticket : null;
}

function loadCsChatMessages(PDO $pdo, int $ticketId, int $afterId = 0, int $limit = 120): array
{
    if ($ticketId <= 0) {
        return [];
    }

    $limit = max(10, min(300, $limit));
    $afterId = max(0, $afterId);

    $stmt = $pdo->prepare(
        'SELECT
            tm.id,
            tm.ticket_id,
            tm.user_id,
            tm.sender_role,
            COALESCE(u.username, CONCAT("user#", tm.user_id)) AS username,
            tm.message,
            tm.created_at
         FROM ticket_messages tm
         LEFT JOIN users u ON u.id = tm.user_id
         WHERE tm.ticket_id = :ticket_id
           AND tm.id > :after_id
         ORDER BY tm.id ASC
         LIMIT :limit'
    );
    $stmt->bindValue(':ticket_id', $ticketId, PDO::PARAM_INT);
    $stmt->bindValue(':after_id', $afterId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    return is_array($rows) ? $rows : [];
}

function resolveCsChatMode(PDO $pdo, array $ticket): string
{
    $status = mb_strtolower(trim((string)($ticket['status'] ?? 'open')));
    if ($status === 'closed') {
        return 'closed';
    }

    $category = (string)($ticket['category'] ?? '');
    if (stripos($category, 'ADMIN') !== false) {
        return 'admin';
    }

    $ticketId = (int)($ticket['id'] ?? 0);
    if ($ticketId > 0) {
        try {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM ticket_messages
                 WHERE ticket_id = :ticket_id
                   AND sender_role = 'admin'
                   AND message NOT LIKE '[BOT]%'"
            );
            $stmt->execute(['ticket_id' => $ticketId]);
            $humanAdminReplies = (int)($stmt->fetchColumn() ?: 0);
            if ($humanAdminReplies > 0) {
                return 'admin';
            }
        } catch (Throwable $e) {
            // Ignore fallback and continue BOT mode.
        }
    }

    return 'bot';
}

function updateCsChatMode(PDO $pdo, int $ticketId, string $mode, string $updatedAt): void
{
    if ($ticketId <= 0) {
        return;
    }

    $modeNormalized = mb_strtolower(trim($mode));
    $category = $modeNormalized === 'admin' ? csChatCategoryAdmin() : csChatCategoryBot();

    $stmt = $pdo->prepare(
        'UPDATE tickets
         SET category = :category, updated_at = :updated_at
         WHERE id = :id'
    );
    $stmt->execute([
        'category' => $category,
        'updated_at' => $updatedAt,
        'id' => $ticketId,
    ]);
}

function createCsChatTicketForUser(PDO $pdo, array $user, array $config): array
{
    $userId = (int)($user['id'] ?? 0);
    if ($userId <= 0) {
        throw new RuntimeException('User ID tidak valid untuk membuat chat CS.');
    }

    $username = trim((string)($user['username'] ?? 'buyer'));
    $now = nowDateTime();

    $subject = 'Live Chat Customer Service';
    if ($username !== '') {
        $subject .= ' - @' . $username;
    }

    $insertTicketStmt = $pdo->prepare(
        'INSERT INTO tickets (user_id, order_id, subject, category, priority, status, last_message_at, created_at, updated_at)
         VALUES (:user_id, :order_id, :subject, :category, :priority, :status, :last_message_at, :created_at, :updated_at)'
    );
    $insertTicketStmt->execute([
        'user_id' => $userId,
        'order_id' => null,
        'subject' => mb_substr($subject, 0, 180),
        'category' => csChatCategoryBot(),
        'priority' => 'normal',
        'status' => 'open',
        'last_message_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $ticketId = (int)$pdo->lastInsertId();

    $botSenderId = resolveCsBotSenderId($pdo, $userId);
    $welcome = withCsBotPrefix(
        'Halo, saya bot Customer Service Odyssiavault. '
        . 'Saya bantu dulu ya. Kamu bisa tanya seputar order, pembayaran, refill, atau ketik "admin" untuk minta CS manusia.'
    );

    $insertMsgStmt = $pdo->prepare(
        'INSERT INTO ticket_messages (ticket_id, user_id, sender_role, message, created_at)
         VALUES (:ticket_id, :user_id, :sender_role, :message, :created_at)'
    );
    $insertMsgStmt->execute([
        'ticket_id' => $ticketId,
        'user_id' => $botSenderId,
        'sender_role' => 'admin',
        'message' => $welcome,
        'created_at' => $now,
    ]);

    $selectStmt = $pdo->prepare(
        'SELECT id, user_id, subject, category, priority, status, last_message_at, created_at, updated_at
         FROM tickets
         WHERE id = :id
         LIMIT 1'
    );
    $selectStmt->execute(['id' => $ticketId]);
    $ticket = $selectStmt->fetch();
    if (!is_array($ticket)) {
        throw new RuntimeException('Gagal mengambil data tiket CS yang baru dibuat.');
    }

    return $ticket;
}

function ensureCsChatTicketForUser(PDO $pdo, array $user, array $config): array
{
    $userId = (int)($user['id'] ?? 0);
    if ($userId <= 0) {
        throw new RuntimeException('User ID tidak valid.');
    }

    $ticket = findActiveCsChatTicketForUser($pdo, $userId);
    if (is_array($ticket)) {
        return $ticket;
    }

    return createCsChatTicketForUser($pdo, $user, $config);
}

function buildCsBotReply(string $message, array $user, array $config): array
{
    $raw = trim($message);
    $text = mb_strtolower($raw);
    $username = trim((string)($user['username'] ?? 'buyer'));
    $greetName = $username !== '' ? '@' . $username : 'kamu';

    $takeoverKeywords = [
        'admin',
        'cs manusia',
        'operator',
        'human',
        'komplain',
        'refund',
        'lapor',
        'error',
        'gagal',
        'belum masuk',
        'tidak masuk',
        'belum diproses',
        'lama',
    ];
    $shouldTakeover = false;
    foreach ($takeoverKeywords as $keyword) {
        if (str_contains($text, $keyword)) {
            $shouldTakeover = true;
            break;
        }
    }

    if ($shouldTakeover) {
        return [
            'reply' => 'Siap, saya teruskan ke admin ya. '
                . 'Admin akan ambil alih chat ini dan balas secepatnya. '
                . 'Sambil menunggu, kamu bisa tulis detail order/masalah biar proses lebih cepat.',
            'takeover' => true,
        ];
    }

    if ($text === '' || preg_match('/^(hai|halo|hallo|p|ping|assalamualaikum)/u', $text) === 1) {
        return [
            'reply' => 'Halo ' . $greetName . '! Saya bot CS Odyssiavault. '
                . 'Kamu bisa tanya: status order, cara pembayaran, refill, atau ketik "admin" jika ingin dibantu CS manusia.',
            'takeover' => false,
        ];
    }

    if (str_contains($text, 'status') || str_contains($text, 'order')) {
        return [
            'reply' => 'Untuk cek status order: buka menu Pembelian > Riwayat, lalu cari ID order kamu. '
                . 'Kalau status tidak berubah >24 jam, ketik "admin" biar langsung ditangani.',
            'takeover' => false,
        ];
    }

    if (str_contains($text, 'bayar') || str_contains($text, 'pembayaran') || str_contains($text, 'qris')) {
        return [
            'reply' => 'Alur pembayaran: checkout > scan QRIS > klik "Saya Sudah Bayar". '
                . 'Setelah itu order masuk antrean verifikasi admin.',
            'takeover' => false,
        ];
    }

    if (str_contains($text, 'refill')) {
        return [
            'reply' => 'Untuk refill: buka menu Refill, masukkan ID order yang sudah selesai, lalu ajukan refill.',
            'takeover' => false,
        ];
    }

    if (str_contains($text, 'layanan') || str_contains($text, 'harga') || str_contains($text, 'service')) {
        return [
            'reply' => 'Kamu bisa lihat semua layanan di menu Pembelian atau Daftar Layanan. '
                . 'Gunakan kolom search untuk cari berdasarkan nama atau ID layanan.',
            'takeover' => false,
        ];
    }

    return [
        'reply' => 'Saya sudah terima pesan kamu. Kalau butuh bantuan cepat dari CS manusia, ketik "admin". '
            . 'Kalau tidak, kamu juga bisa jelaskan lagi masalahnya dengan lebih detail.',
        'takeover' => false,
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
