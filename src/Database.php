<?php

declare(strict_types=1);

final class Database
{
    private static bool $schemaEnsured = false;
    private static array $tempSslFiles = [];

    public static function connect(array $config): PDO
    {
        $host = (string)($config['host'] ?? '127.0.0.1');
        $port = (int)($config['port'] ?? 3306);
        $dbName = (string)($config['database'] ?? '');
        $username = (string)($config['username'] ?? 'root');
        $password = (string)($config['password'] ?? '');
        $charset = (string)($config['charset'] ?? 'utf8mb4');

        if ($dbName === '') {
            throw new RuntimeException('Konfigurasi database belum lengkap (nama database kosong).');
        }

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $dbName, $charset);
        $pdoOptions = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $sslMode = strtoupper(trim((string)($config['ssl_mode'] ?? 'DISABLED')));
        if ($sslMode !== '' && $sslMode !== 'DISABLED') {
            self::applySslOptions($pdoOptions, $config);
        }

        return new PDO($dsn, $username, $password, $pdoOptions);
    }

    public static function ensureSchema(PDO $pdo): void
    {
        if (self::$schemaEnsured) {
            return;
        }
        self::$schemaEnsured = true;

        try {
            self::ensureOrdersPaymentColumns($pdo);
        } catch (Throwable $e) {
            error_log('Database schema ensure failed: ' . $e->getMessage());
        }
    }

    private static function ensureOrdersPaymentColumns(PDO $pdo): void
    {
        if (!self::tableExists($pdo, 'orders')) {
            return;
        }

        $requiredColumns = [
            'payment_deadline_at' => 'DATETIME NULL',
            'payment_confirmed_at' => 'DATETIME NULL',
            'payment_confirmed_by_admin_at' => 'DATETIME NULL',
            'payment_method' => 'VARCHAR(30) NULL',
            'payment_channel_name' => 'VARCHAR(120) NULL',
            'payment_account_name' => 'VARCHAR(120) NULL',
            'payment_account_number' => 'VARCHAR(80) NULL',
            'payment_payer_name' => 'VARCHAR(120) NULL',
            'payment_reference' => 'VARCHAR(120) NULL',
            'payment_note' => 'TEXT NULL',
        ];

        foreach ($requiredColumns as $column => $definition) {
            self::ensureColumn($pdo, 'orders', $column, $definition);
        }
    }

    private static function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare(
            'SELECT 1
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = :table
             LIMIT 1'
        );
        $stmt->execute(['table' => $table]);

        return (bool)$stmt->fetchColumn();
    }

    private static function columnExists(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare(
            'SELECT 1
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = :table
               AND column_name = :column
             LIMIT 1'
        );
        $stmt->execute([
            'table' => $table,
            'column' => $column,
        ]);

        return (bool)$stmt->fetchColumn();
    }

    private static function ensureColumn(PDO $pdo, string $table, string $column, string $definition): void
    {
        if (self::columnExists($pdo, $table, $column)) {
            return;
        }

        $sql = sprintf(
            'ALTER TABLE `%s` ADD COLUMN `%s` %s',
            str_replace('`', '``', $table),
            str_replace('`', '``', $column),
            $definition
        );
        $pdo->exec($sql);
    }

    private static function applySslOptions(array &$pdoOptions, array $config): void
    {
        if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
            $pdoOptions[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = (bool)($config['ssl_verify_server_cert'] ?? false);
        }

        $sslCaPath = self::resolveSslFilePath(
            (string)($config['ssl_ca'] ?? ''),
            (string)($config['ssl_ca_base64'] ?? ''),
            'ca'
        );
        if ($sslCaPath !== '' && defined('PDO::MYSQL_ATTR_SSL_CA')) {
            $pdoOptions[PDO::MYSQL_ATTR_SSL_CA] = $sslCaPath;
        }

        $sslCertPath = self::resolveSslFilePath(
            (string)($config['ssl_cert'] ?? ''),
            (string)($config['ssl_cert_base64'] ?? ''),
            'cert'
        );
        if ($sslCertPath !== '' && defined('PDO::MYSQL_ATTR_SSL_CERT')) {
            $pdoOptions[PDO::MYSQL_ATTR_SSL_CERT] = $sslCertPath;
        }

        $sslKeyPath = self::resolveSslFilePath(
            (string)($config['ssl_key'] ?? ''),
            (string)($config['ssl_key_base64'] ?? ''),
            'key'
        );
        if ($sslKeyPath !== '' && defined('PDO::MYSQL_ATTR_SSL_KEY')) {
            $pdoOptions[PDO::MYSQL_ATTR_SSL_KEY] = $sslKeyPath;
        }
    }

    private static function resolveSslFilePath(string $pathValue, string $base64Value, string $type): string
    {
        $pathValue = trim($pathValue);
        if ($pathValue !== '' && is_file($pathValue)) {
            return $pathValue;
        }

        $base64Value = trim($base64Value);
        if ($base64Value === '') {
            return '';
        }

        $decoded = base64_decode($base64Value, true);
        if ($decoded === false || trim($decoded) === '') {
            return '';
        }

        $hash = sha1($decoded);
        if (isset(self::$tempSslFiles[$hash]) && is_file(self::$tempSslFiles[$hash])) {
            return self::$tempSslFiles[$hash];
        }

        $tmpDir = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'odyssiavault-db-ssl';
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0775, true);
        }

        $safeType = preg_replace('/[^a-z0-9_-]/i', '', $type) ?: 'ssl';
        $tmpFile = $tmpDir . DIRECTORY_SEPARATOR . $safeType . '-' . $hash . '.pem';
        if (!is_file($tmpFile)) {
            @file_put_contents($tmpFile, $decoded);
        }
        if (!is_file($tmpFile)) {
            return '';
        }

        self::$tempSslFiles[$hash] = $tmpFile;
        return $tmpFile;
    }
}
