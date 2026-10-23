<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Database.php';

function testDatabaseConfigPath(): string
{
    $path = getenv('APP_DB_CONFIG');
    if (!is_string($path) || $path === '') {
        throw new RuntimeException('Test có ghi dữ liệu bắt buộc dùng APP_DB_CONFIG trỏ tới app/.local/test-db.json.');
    }
    return $path;
}

function testDatabase(): mysqli
{
    $database = (new Database(testDatabaseConfigPath()))->connection();
    $row = $database->query('SELECT DATABASE() AS db_name')->fetch_assoc();
    $name = (string) ($row['db_name'] ?? '');
    if (!preg_match('/(?:^|_)test$/', $name) || $name === 'volunteer_app') {
        $database->close();
        throw new RuntimeException("Từ chối chạy test ghi dữ liệu trên schema '$name'; tên schema test phải kết thúc bằng _test.");
    }
    return $database;
}
