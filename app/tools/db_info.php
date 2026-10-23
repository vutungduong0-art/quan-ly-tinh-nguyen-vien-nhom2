<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Database.php';

$override = getenv('APP_DB_CONFIG');
$path = is_string($override) && $override !== '' ? $override : dirname(__DIR__) . '/.local/db.json';
try {
    $db = (new Database($path))->connection();
    $row = $db->query(
        'SELECT @@hostname AS hostname, @@port AS port, @@server_uuid AS server_uuid,
                DATABASE() AS db_name, CURRENT_USER() AS authenticated_user, VERSION() AS version'
    )->fetch_assoc();
    foreach ($row as $key => $value) {
        fwrite(STDOUT, sprintf("%-13s %s\n", $key . ':', (string) $value));
    }
} catch (Throwable $exception) {
    fwrite(STDERR, "Không đọc được thông tin MySQL: {$exception->getMessage()}\n");
    exit(1);
}
