<?php

declare(strict_types=1);

final class Database
{
    private static bool $schemaEnsured = false;

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

        return new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
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
}
