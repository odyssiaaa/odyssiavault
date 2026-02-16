<?php

declare(strict_types=1);

final class ProviderClient
{
    private string $apiUrl;
    private string $apiKey;
    private string $secretKey;
    private int $timeout;
    private string $requestContentType;
    private int $servicesCacheTtl;
    private string $cacheDir;
    private array $servicesMemoryCache = [];

    public function __construct(array $config)
    {
        $this->apiUrl = (string)($config['api_url'] ?? '');
        $this->apiKey = (string)($config['api_key'] ?? '');
        $this->secretKey = (string)($config['secret_key'] ?? '');
        $this->timeout = (int)($config['timeout'] ?? 30);
        $this->requestContentType = mb_strtolower(trim((string)($config['request_content_type'] ?? 'form')));
        if (!in_array($this->requestContentType, ['form', 'json'], true)) {
            $this->requestContentType = 'form';
        }
        $this->servicesCacheTtl = max(0, (int)($config['services_cache_ttl'] ?? 300));
        $this->cacheDir = (string)($config['cache_dir'] ?? (dirname(__DIR__) . '/storage/cache/provider'));
        if ($this->cacheDir === '') {
            $this->cacheDir = dirname(__DIR__) . '/storage/cache/provider';
        }

        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0777, true);
        }

        if (!is_dir($this->cacheDir) || !is_writable($this->cacheDir)) {
            $tmpCacheDir = rtrim((string)sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'odyssiavault_provider_cache';
            if (!is_dir($tmpCacheDir)) {
                @mkdir($tmpCacheDir, 0777, true);
            }
            if (is_dir($tmpCacheDir) && is_writable($tmpCacheDir)) {
                $this->cacheDir = $tmpCacheDir;
            }
        }
    }

    public function isConfigured(): bool
    {
        return $this->apiUrl !== ''
            && $this->apiKey !== ''
            && $this->secretKey !== ''
            && !str_contains($this->apiKey, 'GANTI_DENGAN')
            && !str_contains($this->secretKey, 'GANTI_DENGAN');
    }

    public function request(array $payload): array
    {
        $payload['api_key'] = $this->apiKey;
        $payload['secret_key'] = $this->secretKey;
        $action = mb_strtolower(trim((string)($payload['action'] ?? '')));
        $timeout = $this->timeout;
        if (str_starts_with($action, 'services')) {
            $timeout = max($timeout, 60);
        }

        $preferredType = $this->requestContentType;
        $alternateType = $preferredType === 'json' ? 'form' : 'json';
        $firstResult = $this->sendRequest($payload, $timeout, $preferredType);
        if (($firstResult['status'] ?? false) === true) {
            return $firstResult;
        }

        $firstMsg = mb_strtolower(trim((string)($firstResult['data']['msg'] ?? '')));
        $isMutatingAction = in_array($action, ['order', 'refill'], true);
        $shouldRetryWithAlternateType = $isMutatingAction
            ? $this->isRequestFormatError($firstMsg)
            : $this->isRetryableRequestFailure($firstMsg);

        if (!$shouldRetryWithAlternateType) {
            return $firstResult;
        }

        $secondResult = $this->sendRequest($payload, $timeout, $alternateType);
        if (($secondResult['status'] ?? false) === true) {
            return $secondResult;
        }

        return $firstResult;
    }

    public function services(string $action = 'services'): array
    {
        $variants = $this->serviceActionFallbackChain($action);
        $lastResult = [
            'status' => false,
            'data' => ['msg' => 'Gagal mengambil daftar layanan.'],
        ];

        foreach ($variants as $variant) {
            if ($this->servicesCacheTtl > 0 && isset($this->servicesMemoryCache[$variant])) {
                return $this->servicesMemoryCache[$variant];
            }

            if ($this->servicesCacheTtl > 0) {
                $cached = $this->readServicesCache($variant, true);
                if (is_array($cached)) {
                    $this->servicesMemoryCache[$variant] = $cached;
                    $this->servicesMemoryCache[$action] = $cached;
                    return $cached;
                }
            }

            $result = $this->request(['action' => $variant]);
            if (($result['status'] ?? false) === true) {
                if ($this->servicesCacheTtl > 0) {
                    $this->writeServicesCache($variant, $result);
                    if ($variant !== $action) {
                        $this->writeServicesCache($action, $result);
                    }
                }
                $this->servicesMemoryCache[$variant] = $result;
                $this->servicesMemoryCache[$action] = $result;
                return $result;
            }

            $lastResult = $result;
        }

        if ($this->servicesCacheTtl > 0) {
            foreach ($variants as $variant) {
                $stale = $this->readServicesCache($variant, false);
                if (is_array($stale)) {
                    $this->servicesMemoryCache[$variant] = $stale;
                    $this->servicesMemoryCache[$action] = $stale;
                    return $stale;
                }
            }
        }

        return $lastResult;
    }

    public function serviceById(int $serviceId, string $action = 'services'): ?array
    {
        if ($serviceId <= 0) {
            return null;
        }

        $services = $this->services($action);
        if (($services['status'] ?? false) !== true) {
            return null;
        }

        foreach ((array)($services['data'] ?? []) as $service) {
            if ((int)($service['id'] ?? 0) === $serviceId) {
                return is_array($service) ? $service : null;
            }
        }

        return null;
    }

    public function profile(): array
    {
        return $this->request(['action' => 'profile']);
    }

    public function order(array $payload): array
    {
        $payload['action'] = 'order';
        return $this->request($payload);
    }

    public function status(string $providerOrderId): array
    {
        $result = $this->request([
            'action' => 'status',
            'id' => $providerOrderId,
        ]);

        if (($result['status'] ?? false) === true) {
            return $result;
        }

        return $this->request([
            'action' => 'status',
            'order_id' => $providerOrderId,
        ]);
    }

    public function refill(string $providerOrderId): array
    {
        return $this->request([
            'action' => 'refill',
            'id' => $providerOrderId,
        ]);
    }

    public function refillStatus(string $providerRefillId): array
    {
        return $this->request([
            'action' => 'status_refill',
            'id' => $providerRefillId,
        ]);
    }

    private function sendRequest(array $payload, int $timeout, string $contentType): array
    {
        $headers = ['Accept: application/json'];
        $postBody = http_build_query($payload);

        if ($contentType === 'json') {
            $headers[] = 'Content-Type: application/json';
            $postBody = json_encode($payload, JSON_UNESCAPED_UNICODE);
        } else {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }

        if (!is_string($postBody)) {
            $postBody = '';
        }

        if (!function_exists('curl_init')) {
            return $this->sendRequestViaStream($headers, $postBody, $timeout);
        }

        $ch = curl_init($this->apiUrl);
        if ($ch === false) {
            return [
                'status' => false,
                'data' => ['msg' => 'Gagal koneksi ke layanan: tidak dapat menginisialisasi cURL.'],
            ];
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => min(8, max(3, (int)floor($timeout / 2))),
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $postBody,
        ]);

        $result = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($result === false) {
            return [
                'status' => false,
                'data' => ['msg' => 'Gagal koneksi ke layanan: ' . $error],
            ];
        }

        $decoded = json_decode($result, true);
        if (!is_array($decoded)) {
            return [
                'status' => false,
                'data' => ['msg' => 'Respon layanan bukan JSON valid. HTTP ' . $status],
            ];
        }

        return $decoded;
    }

    private function sendRequestViaStream(array $headers, string $postBody, int $timeout): array
    {
        $headerText = implode("\r\n", array_filter($headers, static fn ($line) => is_string($line) && trim($line) !== ''));
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => $headerText,
                'content' => $postBody,
                'timeout' => max(5, $timeout),
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $result = @file_get_contents($this->apiUrl, false, $context);
        $responseHeaders = isset($http_response_header) && is_array($http_response_header)
            ? $http_response_header
            : [];
        $status = $this->extractStatusFromHeaders($responseHeaders);

        if ($result === false) {
            $err = error_get_last();
            $msg = is_array($err) ? (string)($err['message'] ?? 'Unknown stream error.') : 'Unknown stream error.';
            return [
                'status' => false,
                'data' => ['msg' => 'Gagal koneksi ke layanan: ' . $msg],
            ];
        }

        $decoded = json_decode((string)$result, true);
        if (!is_array($decoded)) {
            return [
                'status' => false,
                'data' => ['msg' => 'Respon layanan bukan JSON valid. HTTP ' . $status],
            ];
        }

        return $decoded;
    }

    private function extractStatusFromHeaders(array $headers): int
    {
        foreach ($headers as $line) {
            if (!is_string($line)) {
                continue;
            }

            if (preg_match('/^HTTP\/\d+(?:\.\d+)?\s+(\d{3})/i', trim($line), $matches) === 1) {
                return (int)$matches[1];
            }
        }

        return 0;
    }

    private function serviceActionFallbackChain(string $action): array
    {
        $normalized = mb_strtolower(trim($action));
        $map = [
            'services' => ['services3', 'services_1', 'services2', 'services'],
            'services_1' => ['services_1', 'services3', 'services', 'services2'],
            'services2' => ['services2', 'services3', 'services_1', 'services'],
            'services3' => ['services3', 'services_1', 'services2', 'services'],
        ];

        $variants = $map[$normalized] ?? ['services3', 'services_1', 'services2', 'services'];
        $seen = [];
        $ordered = [];
        foreach ($variants as $variant) {
            if (isset($seen[$variant])) {
                continue;
            }
            $seen[$variant] = true;
            $ordered[] = $variant;
        }

        return $ordered;
    }

    private function isRequestFormatError(string $message): bool
    {
        if ($message === '') {
            return false;
        }

        $needles = [
            'permintaan tidak sesuai',
            'content-type',
            'json',
            'invalid',
            'malformed',
            'format',
        ];
        foreach ($needles as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function isRetryableRequestFailure(string $message): bool
    {
        if ($message === '') {
            return true;
        }

        if ($this->isRequestFormatError($message)) {
            return true;
        }

        $retryableNeedles = [
            'gagal koneksi',
            'timeout',
            'timed out',
            'http 5',
            'service unavailable',
            'internal server error',
            'bad gateway',
            'gateway timeout',
        ];
        foreach ($retryableNeedles as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function getServicesCacheFile(string $action): string
    {
        $safeAction = preg_replace('/[^a-z0-9_]+/i', '_', $action);
        if ($safeAction === null || $safeAction === '') {
            $safeAction = 'services';
        }

        return rtrim($this->cacheDir, '/\\') . DIRECTORY_SEPARATOR . 'services_' . $safeAction . '.json';
    }

    private function readServicesCache(string $action, bool $freshOnly): ?array
    {
        $cacheFile = $this->getServicesCacheFile($action);
        if (!is_file($cacheFile)) {
            return null;
        }

        $mtime = @filemtime($cacheFile);
        if ($freshOnly && is_int($mtime) && $mtime > 0 && (time() - $mtime) > $this->servicesCacheTtl) {
            return null;
        }

        $raw = @file_get_contents($cacheFile);
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || ($decoded['status'] ?? false) !== true) {
            return null;
        }

        return $decoded;
    }

    private function writeServicesCache(string $action, array $result): void
    {
        if (($result['status'] ?? false) !== true) {
            return;
        }

        $cacheFile = $this->getServicesCacheFile($action);
        $cacheDir = dirname($cacheFile);

        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0777, true);
        }

        if (!is_dir($cacheDir)) {
            return;
        }

        $payload = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if (!is_string($payload) || $payload === '') {
            return;
        }

        @file_put_contents($cacheFile, $payload, LOCK_EX);
    }
}
