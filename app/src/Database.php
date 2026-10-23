<?php

declare(strict_types=1);

final class Database
{
    private mysqli $connection;

    public function __construct(string $configPath)
    {
        if (!is_file($configPath)) {
            throw new RuntimeException('Chưa có cấu hình cơ sở dữ liệu. Hãy chạy php tools/configure.php.');
        }

        $raw = file_get_contents($configPath);
        $config = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($config)) {
            throw new RuntimeException('Tệp cấu hình cơ sở dữ liệu không hợp lệ.');
        }

        foreach (['host', 'database', 'user', 'password'] as $field) {
            if (!array_key_exists($field, $config) || !is_string($config[$field])) {
                throw new RuntimeException("Thiếu cấu hình cơ sở dữ liệu: {$field}.");
            }
        }

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $this->connection = new mysqli(
            $config['host'],
            $config['user'],
            $config['password'],
            $config['database'],
            (int) ($config['port'] ?? 3306),
            isset($config['socket']) && is_string($config['socket']) && $config['socket'] !== '' ? $config['socket'] : null
        );
        $this->connection->set_charset('utf8mb4');
        $this->connection->query("SET time_zone = '+00:00'");
        $this->connection->query(
            "SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'"
        );
    }

    public function connection(): mysqli
    {
        return $this->connection;
    }
}
