<?php

declare(strict_types=1);

final class Config
{
    public static function load(): array
    {
        $rootDir = dirname(__DIR__);
        $primary = $rootDir . '/config/config.php';
        $fallback = $rootDir . '/config/config.example.php';

        $config = [];

        foreach ([$primary, $fallback] as $file) {
            if (!is_file($file)) {
                continue;
            }

            $loaded = require $file;
            if (is_array($loaded)) {
                $config = $loaded;
                break;
            }
        }

        $config = self::ensureSections($config);
        return self::applyEnvOverrides($config);
    }

    private static function ensureSections(array $config): array
    {
        $sections = ['app', 'db', 'provider', 'pricing', 'checkout', 'payment', 'news'];
        foreach ($sections as $section) {
            if (!isset($config[$section]) || !is_array($config[$section])) {
                $config[$section] = [];
            }
        }

        return $config;
    }

    private static function applyEnvOverrides(array $config): array
    {
        $isVercel = self::isVercel();
        $tmpRoot = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'odyssiavault-cache';

        $app = (array)$config['app'];
        $db = (array)$config['db'];
        $provider = (array)$config['provider'];
        $pricing = (array)$config['pricing'];
        $checkout = (array)$config['checkout'];
        $payment = (array)$config['payment'];
        $news = (array)$config['news'];

        $app['name'] = self::env('APP_NAME', (string)($app['name'] ?? 'Odyssiavault'));
        $baseUrlFromEnv = self::env('APP_BASE_URL', '');
        if ($baseUrlFromEnv !== '') {
            $app['base_url'] = $baseUrlFromEnv;
        } else {
            $app['base_url'] = (string)($app['base_url'] ?? '');
            if ($isVercel && ($app['base_url'] === '' || str_contains($app['base_url'], 'localhost'))) {
                $vercelUrl = self::env('VERCEL_URL', '');
                if ($vercelUrl !== '') {
                    $app['base_url'] = 'https://' . $vercelUrl;
                }
            }
        }
        $app['session_name'] = self::env('SESSION_NAME', (string)($app['session_name'] ?? 'odyssiavault_session'));
        $app['logo_path'] = self::env('APP_LOGO_PATH', (string)($app['logo_path'] ?? 'assets/logo.png'));
        $app['default_new_user_balance'] = self::envInt(
            'APP_DEFAULT_NEW_USER_BALANCE',
            (int)($app['default_new_user_balance'] ?? 0)
        );
        $app['session_save_path'] = self::env('SESSION_SAVE_PATH', (string)($app['session_save_path'] ?? ''));
        if ($app['session_save_path'] === '' && $isVercel) {
            $app['session_save_path'] = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'odyssiavault-sessions';
        }

        $db['host'] = self::env('DB_HOST', (string)($db['host'] ?? '127.0.0.1'));
        $db['port'] = self::envInt('DB_PORT', (int)($db['port'] ?? 3306));
        $db['database'] = self::env('DB_DATABASE', (string)($db['database'] ?? ''));
        $db['username'] = self::env('DB_USERNAME', (string)($db['username'] ?? 'root'));
        $db['password'] = self::env('DB_PASSWORD', (string)($db['password'] ?? ''));
        $db['charset'] = self::env('DB_CHARSET', (string)($db['charset'] ?? 'utf8mb4'));

        $provider['api_url'] = self::env('PROVIDER_API_URL', (string)($provider['api_url'] ?? ''));
        $provider['api_key'] = self::env('PROVIDER_API_KEY', (string)($provider['api_key'] ?? ''));
        $provider['secret_key'] = self::env('PROVIDER_SECRET_KEY', (string)($provider['secret_key'] ?? ''));
        $provider['timeout'] = self::envInt('PROVIDER_TIMEOUT', (int)($provider['timeout'] ?? 30));
        $provider['request_content_type'] = self::env(
            'PROVIDER_REQUEST_CONTENT_TYPE',
            (string)($provider['request_content_type'] ?? 'form')
        );
        $provider['services_cache_ttl'] = self::envInt(
            'PROVIDER_SERVICES_CACHE_TTL',
            (int)($provider['services_cache_ttl'] ?? 300)
        );
        $provider['services_mapped_cache_ttl'] = self::envInt(
            'PROVIDER_SERVICES_MAPPED_CACHE_TTL',
            (int)($provider['services_mapped_cache_ttl'] ?? 300)
        );
        $provider['cache_dir'] = self::env('PROVIDER_CACHE_DIR', (string)($provider['cache_dir'] ?? ''));
        if ($provider['cache_dir'] === '' && $isVercel) {
            $provider['cache_dir'] = $tmpRoot . DIRECTORY_SEPARATOR . 'provider';
        }
        $provider['services_mapped_cache_dir'] = self::env(
            'PROVIDER_MAPPED_CACHE_DIR',
            (string)($provider['services_mapped_cache_dir'] ?? '')
        );
        if ($provider['services_mapped_cache_dir'] === '' && $isVercel) {
            $provider['services_mapped_cache_dir'] = $tmpRoot . DIRECTORY_SEPARATOR . 'api';
        }

        $pricing['default_markup_percent'] = self::envFloat(
            'PRICING_DEFAULT_MARKUP_PERCENT',
            (float)($pricing['default_markup_percent'] ?? 0)
        );
        $pricing['fixed_markup'] = self::envInt('PRICING_FIXED_MARKUP', (int)($pricing['fixed_markup'] ?? 0));
        $pricing['round_to'] = self::envInt('PRICING_ROUND_TO', (int)($pricing['round_to'] ?? 100));

        $categoryMarkupRaw = self::env('PRICING_CATEGORY_MARKUP_JSON', '');
        if ($categoryMarkupRaw !== '') {
            $categoryMarkup = json_decode($categoryMarkupRaw, true);
            if (is_array($categoryMarkup)) {
                $pricing['category_markup_percent'] = $categoryMarkup;
            }
        }

        $checkout['unpaid_timeout_minutes'] = self::envInt(
            'CHECKOUT_UNPAID_TIMEOUT_MINUTES',
            (int)($checkout['unpaid_timeout_minutes'] ?? 180)
        );

        $paymentMethodsRaw = self::env('PAYMENT_METHODS_JSON', '');
        if ($paymentMethodsRaw !== '') {
            $decodedMethods = json_decode($paymentMethodsRaw, true);
            if (is_array($decodedMethods)) {
                $normalized = [];
                foreach ($decodedMethods as $method) {
                    if (!is_array($method)) {
                        continue;
                    }

                    $code = trim((string)($method['code'] ?? ''));
                    $name = trim((string)($method['name'] ?? ''));
                    $accountNumber = trim((string)($method['account_number'] ?? ''));
                    if ($code === '' || $name === '' || $accountNumber === '') {
                        continue;
                    }

                    $normalized[] = [
                        'code' => $code,
                        'name' => $name,
                        'account_name' => trim((string)($method['account_name'] ?? '')),
                        'account_number' => $accountNumber,
                        'note' => trim((string)($method['note'] ?? '')),
                    ];
                }

                if ($normalized !== []) {
                    $checkout['payment_methods'] = $normalized;
                }
            }
        }

        $payment['deposit_min'] = self::envInt('PAYMENT_DEPOSIT_MIN', (int)($payment['deposit_min'] ?? 10000));
        $payment['deposit_max'] = self::envInt('PAYMENT_DEPOSIT_MAX', (int)($payment['deposit_max'] ?? 10000000));
        $payment['qris_image'] = self::env('PAYMENT_QRIS_IMAGE', (string)($payment['qris_image'] ?? 'assets/qris.png'));
        $payment['qris_receiver_name'] = self::env(
            'PAYMENT_QRIS_RECEIVER_NAME',
            (string)($payment['qris_receiver_name'] ?? 'Odyssiavault')
        );
        $payment['use_unique_code'] = self::envBool('PAYMENT_USE_UNIQUE_CODE', (bool)($payment['use_unique_code'] ?? true));
        $payment['unique_code_min'] = self::envInt('PAYMENT_UNIQUE_CODE_MIN', (int)($payment['unique_code_min'] ?? 11));
        $payment['unique_code_max'] = self::envInt('PAYMENT_UNIQUE_CODE_MAX', (int)($payment['unique_code_max'] ?? 99));

        $news['source_mode'] = self::env('NEWS_SOURCE_MODE', (string)($news['source_mode'] ?? 'provider_only'));
        $news['provider_services_variant'] = self::env(
            'NEWS_PROVIDER_VARIANT',
            (string)($news['provider_services_variant'] ?? 'services_1')
        );
        $news['provider_limit'] = self::envInt('NEWS_PROVIDER_LIMIT', (int)($news['provider_limit'] ?? 30));
        $news['provider_note_chars'] = self::envInt(
            'NEWS_PROVIDER_NOTE_CHARS',
            (int)($news['provider_note_chars'] ?? 650)
        );

        $config['app'] = $app;
        $config['db'] = $db;
        $config['provider'] = $provider;
        $config['pricing'] = $pricing;
        $config['checkout'] = $checkout;
        $config['payment'] = $payment;
        $config['news'] = $news;

        return $config;
    }

    private static function env(string $name, string $default = ''): string
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);
        if ($value === false || $value === null) {
            return $default;
        }

        return trim((string)$value);
    }

    private static function envInt(string $name, int $default): int
    {
        $value = self::env($name, '');
        if ($value === '' || !preg_match('/^-?\d+$/', $value)) {
            return $default;
        }

        return (int)$value;
    }

    private static function envFloat(string $name, float $default): float
    {
        $value = self::env($name, '');
        if ($value === '' || !is_numeric($value)) {
            return $default;
        }

        return (float)$value;
    }

    private static function envBool(string $name, bool $default): bool
    {
        $value = self::env($name, '');
        if ($value === '') {
            return $default;
        }

        $normalized = mb_strtolower($value);
        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        return $default;
    }

    private static function isVercel(): bool
    {
        return self::env('VERCEL', '') === '1' || self::env('VERCEL_ENV', '') !== '';
    }
}
