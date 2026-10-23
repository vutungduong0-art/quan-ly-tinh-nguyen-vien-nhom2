<?php

declare(strict_types=1);

$requirements = [
    'PHP >= 8.1' => version_compare(PHP_VERSION, '8.1.0', '>='),
    'Extension mysqli' => extension_loaded('mysqli'),
    'Extension mysqlnd' => function_exists('mysqli_fetch_all'),
    'Thư mục public' => is_dir(dirname(__DIR__) . '/public'),
    'Migration nền' => is_file(dirname(__DIR__) . '/database/migrations/001_demo_core.sql'),
    'Migration toàn dự án' => is_file(dirname(__DIR__) . '/database/migrations/002_full_project.sql'),
    'Migration gia cố' => is_file(dirname(__DIR__) . '/database/migrations/003_hardening.sql'),
    'Migration vận hành 0.4' => is_file(dirname(__DIR__) . '/database/migrations/004_workflow_reporting.sql'),
    'Migration đăng ký tài khoản' => is_file(dirname(__DIR__) . '/database/migrations/005_self_registration.sql'),
    'Hai thuật toán phân công' => is_file(dirname(__DIR__) . '/src/Allocation/RegistrationOrderAllocator.php')
        && is_file(dirname(__DIR__) . '/src/Allocation/ScarcityBalancedAllocator.php'),
    'Ba fixture chuẩn' => count(glob(dirname(__DIR__) . '/database/fixtures/generated/*.json') ?: []) === 3,
];

$failed = false;
foreach ($requirements as $label => $passed) {
    fwrite(STDOUT, sprintf("[%s] %s\n", $passed ? 'OK' : 'FAIL', $label));
    $failed = $failed || !$passed;
}

$config = dirname(__DIR__) . '/.local/db.json';
if (!is_file($config)) {
    fwrite(STDOUT, "[WAIT] Chưa có app/.local/db.json; chạy php tools/configure.php sau khi MySQL hoạt động.\n");
} else {
    require_once dirname(__DIR__) . '/src/Database.php';
    try {
        $db = (new Database($config))->connection();
        $row = $db->query('SELECT VERSION() AS version, @@server_uuid AS uuid, DATABASE() AS db_name')->fetch_assoc();
        fwrite(STDOUT, "[OK] MySQL {$row['version']}; schema {$row['db_name']}; server_uuid {$row['uuid']}\n");
        require_once dirname(__DIR__) . '/src/DatabasePrivileges.php';
        $privileges = DatabasePrivileges::inspect($db);
        if ($privileges['missing'] !== []) {
            fwrite(STDOUT, '[FAIL] Tài khoản web thiếu quyền DML: ' . implode(', ', $privileges['missing']) . ".\n");
            $failed = true;
        } elseif ($privileges['excessive']) {
            fwrite(STDOUT, "[FAIL] Tài khoản web có quyền DDL/ALL; hãy cấu hình lại bằng volunteer_app chỉ có SELECT, INSERT, UPDATE, DELETE.\n");
            $failed = true;
        } else {
            fwrite(STDOUT, "[OK] Tài khoản web đạt nguyên tắc quyền tối thiểu.\n");
        }
    } catch (Throwable $exception) {
        fwrite(STDOUT, "[FAIL] Không kết nối được MySQL: {$exception->getMessage()}\n");
        $failed = true;
    }
}

exit($failed ? 1 : 0);
