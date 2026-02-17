<?php

declare(strict_types=1);

if (!function_exists('mb_strtolower')) {
    function mb_strtolower($string, $encoding = null): string
    {
        return strtolower((string)$string);
    }
}

final class GameTopupClient
{
    private string $apiUrl;
    private string $apiKey;
    private int $timeout;
    private int $servicesCacheTtl;
    private string $cacheDir;
    /** @var array<string,mixed>|null */
    private ?array $servicesMemoryCache = null;

    public function __construct(array $config)
    {
        $this->apiUrl = trim((string)($config['api_url'] ?? 'https://api.mengtopup.id'));
        $this->apiKey = trim((string)($config['api_key'] ?? ''));
        $this->timeout = max(6, min(60, (int)($config['timeout'] ?? 20)));
        $this->servicesCacheTtl = max(0, (int)($config['services_cache_ttl'] ?? 600));
        $this->cacheDir = trim((string)($config['cache_dir'] ?? (dirname(__DIR__) . '/storage/cache/game')));
        if ($this->cacheDir === '') {
            $this->cacheDir = dirname(__DIR__) . '/storage/cache/game';
        }
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0777, true);
        }
    }

    public function isConfigured(): bool
    {
        return $this->apiUrl !== ''
            && $this->apiKey !== ''
            && !str_contains($this->apiKey, 'BGXXXX')
            && !str_contains($this->apiKey, 'API_KEY');
    }

    public function services(bool $forceRefresh = false): array
    {
        if (!$forceRefresh && is_array($this->servicesMemoryCache)) {
            return $this->servicesMemoryCache;
        }

        if (!$forceRefresh && $this->servicesCacheTtl > 0) {
            $cached = $this->readCache(true);
            if (is_array($cached)) {
                $this->servicesMemoryCache = $cached;
                return $cached;
            }
        }

        $result = $this->request('service', [
            'api_key' => $this->apiKey,
        ]);

        if (($result['status'] ?? false) === true) {
            if ($this->servicesCacheTtl > 0) {
                $this->writeCache($result);
            }
            $this->servicesMemoryCache = $result;
            return $result;
        }

        if ($this->servicesCacheTtl > 0) {
            $stale = $this->readCache(false);
            if (is_array($stale)) {
                $this->servicesMemoryCache = $stale;
                return $stale;
            }
        }

        return $result;
    }

    public function order(string $serviceId, string $target, string $contact, string $idtrx, string $callback = ''): array
    {
        $payload = [
            'api_key' => $this->apiKey,
            'service_id' => $serviceId,
            'target' => $target,
            'kontak' => $contact,
            'idtrx' => $idtrx,
        ];
        if (trim($callback) !== '') {
            $payload['callback'] = $callback;
        }

        return $this->request('order', $payload);
    }

    public function status(string $providerOrderId): array
    {
        return $this->request('status', [
            'api_key' => $this->apiKey,
            'order_id' => $providerOrderId,
        ]);
    }

    public function saldo(): array
    {
        return $this->request('saldo', [
            'api_key' => $this->apiKey,
        ]);
    }

    private function request(string $path, array $payload): array
    {
        if (!$this->isConfigured()) {
            return $this->error('Konfigurasi API Topup Game belum diisi.');
        }

        $url = rtrim($this->apiUrl, '/\\') . '/' . ltrim($path, '/\\');
        $http = httpPostJson($url, $payload, [], $this->timeout);
        $httpStatus = (int)($http['status'] ?? 0);
        $body = (string)($http['body'] ?? '');

        if ($body === '') {
            return $this->error('Tidak ada respon dari API Topup Game.', $httpStatus);
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            if (in_array($httpStatus, [401, 403], true)) {
                return $this->error(
                    'Akses API Topup Game ditolak (401/403). Pastikan API key benar dan IP server sudah di-whitelist di provider.',
                    $httpStatus,
                    $body
                );
            }
            return $this->error('Respon API Topup Game tidak valid.', $httpStatus, $body);
        }

        $status = (bool)($decoded['status'] ?? false);
        $msg = trim((string)($decoded['msg'] ?? ''));
        if ($msg === '' && is_array($decoded['data'] ?? null)) {
            $msg = trim((string)($decoded['data']['msg'] ?? ''));
        }
        if ($msg === '') {
            $msg = $status ? 'Berhasil.' : 'Permintaan gagal.';
        }

        if (!$status) {
            return $this->error($msg, $httpStatus, $body);
        }

        return [
            'status' => true,
            'msg' => $msg,
            'data' => $decoded['data'] ?? [],
            'http_status' => $httpStatus,
            'raw' => $decoded,
        ];
    }

    private function cacheFilePath(): string
    {
        return rtrim($this->cacheDir, '/\\') . DIRECTORY_SEPARATOR . 'game_services.json';
    }

    private function readCache(bool $freshOnly): ?array
    {
        $file = $this->cacheFilePath();
        if (!is_file($file)) {
            return null;
        }

        $mtime = @filemtime($file);
        if (
            $freshOnly
            && $this->servicesCacheTtl > 0
            && is_int($mtime)
            && $mtime > 0
            && (time() - $mtime) > $this->servicesCacheTtl
        ) {
            return null;
        }

        $raw = @file_get_contents($file);
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || ($decoded['status'] ?? false) !== true || !is_array($decoded['data'] ?? null)) {
            return null;
        }

        return $decoded;
    }

    private function writeCache(array $payload): void
    {
        $file = $this->cacheFilePath();
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        if (!is_dir($dir)) {
            return;
        }

        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if (!is_string($encoded) || $encoded === '') {
            return;
        }

        @file_put_contents($file, $encoded, LOCK_EX);
    }

    private function error(string $message, int $httpStatus = 0, string $body = ''): array
    {
        $message = trim($message);
        if ($message === '') {
            $message = 'Permintaan API Topup Game gagal.';
        }

        return [
            'status' => false,
            'msg' => $message,
            'data' => ['msg' => $message],
            'http_status' => $httpStatus,
            'body' => $body,
        ];
    }
}
