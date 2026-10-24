<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Database.php';

$appRoot = dirname(__DIR__);
$override = getenv('MIGRATION_DB_CONFIG');
$migrationConfig = $appRoot . '/.local/migration-db.json';
$configPath = is_string($override) && $override !== ''
    ? $override
    : (is_file($migrationConfig) ? $migrationConfig : $appRoot . '/.local/db.json');

try {
    $db = (new Database($configPath))->connection();
    $db->query(
        'CREATE TABLE IF NOT EXISTS schema_migrations (
            version VARCHAR(120) NOT NULL PRIMARY KEY,
            checksum CHAR(64) NULL,
            applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $checksumColumn = $db->query(
        "SELECT 1 FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = 'schema_migrations' AND column_name = 'checksum'"
    )->fetch_row();
    if (!$checksumColumn) {
        $db->query('ALTER TABLE schema_migrations ADD COLUMN checksum CHAR(64) NULL AFTER version');
    }

    $files = glob($appRoot . '/database/migrations/*.sql') ?: [];
    sort($files, SORT_STRING);
    foreach ($files as $file) {
        $version = basename($file);
        $sql = file_get_contents($file);
        if (!is_string($sql) || trim($sql) === '') {
            throw new RuntimeException("Migration {$version} rỗng hoặc không đọc được.");
        }
        $checksum = hash('sha256', $sql);
        $check = $db->prepare('SELECT checksum FROM schema_migrations WHERE version = ?');
        $check->bind_param('s', $version);
        $check->execute();
        $applied = $check->get_result()->fetch_assoc();
        if ($applied) {
            if (is_string($applied['checksum']) && $applied['checksum'] !== '' && !hash_equals($applied['checksum'], $checksum)) {
                throw new RuntimeException("Migration {$version} đã bị thay đổi sau khi áp dụng.");
            }
            if ($applied['checksum'] === null || $applied['checksum'] === '') {
                $backfill = $db->prepare('UPDATE schema_migrations SET checksum = ? WHERE version = ?');
                $backfill->bind_param('ss', $checksum, $version);
                $backfill->execute();
            }
            fwrite(STDOUT, "Bỏ qua {$version} (đã áp dụng, checksum hợp lệ).\n");
            continue;
        }

        $db->multi_query($sql);
        do {
            if ($result = $db->store_result()) {
                $result->free();
            }
        } while ($db->more_results() && $db->next_result());

        $insert = $db->prepare('INSERT INTO schema_migrations (version, checksum) VALUES (?, ?)');
        $insert->bind_param('ss', $version, $checksum);
        $insert->execute();
        fwrite(STDOUT, "Đã áp dụng {$version}.\n");
    }
    fwrite(STDOUT, "Migration hoàn tất.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, "Migration thất bại: {$exception->getMessage()}\n");
    exit(1);
}
