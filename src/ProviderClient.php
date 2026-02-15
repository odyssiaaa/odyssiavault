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
            $timeout = max($timeout, 90);
        }

        $ch = curl_init($this->apiUrl);
        $headers = ['Accept: application/json'];
        $postBody = http_build_query($payload);

        if ($this->requestContentType === 'json') {
            $headers[] = 'Content-Type: application/json';
            $postBody = json_encode($payload, JSON_UNESCAPED_UNICODE);
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
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
                'data' => ['msg' => 'Gagal koneksi ke provider: ' . $error],
            ];
        }

        $decoded = json_decode($result, true);
        if (!is_array($decoded)) {
            return [
                'status' => false,
                'data' => ['msg' => 'Respon provider bukan JSON valid. HTTP ' . $status],
            ];
        }

        return $decoded;
    }

    public function services(string $action = 'services'): array
    {
        if ($this->servicesCacheTtl > 0 && isset($this->servicesMemoryCache[$action])) {
            return $this->servicesMemoryCache[$action];
        }

        if ($this->servicesCacheTtl > 0) {
            $cached = $this->readServicesCache($action, true);
            if (is_array($cached)) {
                $this->servicesMemoryCache[$action] = $cached;
                return $cached;
            }
        }

        $result = $this->request(['action' => $action]);
        if (($result['status'] ?? false) === true) {
            if ($this->servicesCacheTtl > 0) {
                $this->writeServicesCache($action, $result);
            }
            $this->servicesMemoryCache[$action] = $result;
            return $result;
        }

        if ($this->servicesCacheTtl > 0) {
            $stale = $this->readServicesCache($action, false);
            if (is_array($stale)) {
                $this->servicesMemoryCache[$action] = $stale;
                return $stale;
            }
        }

        return $result;
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
