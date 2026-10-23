<?php

declare(strict_types=1);

function prompt(string $label, string $default = ''): string
{
    $suffix = $default !== '' ? " [{$default}]" : '';
    $value = readline($label . $suffix . ': ');
    $value = trim($value === false ? '' : $value);
    return $value !== '' ? $value : $default;
}

function promptSecret(string $label): string
{
    fwrite(STDOUT, $label . ': ');
    if (PHP_OS_FAMILY !== 'Windows') {
        shell_exec('stty -echo');
    }
    $value = trim((string) fgets(STDIN));
    if (PHP_OS_FAMILY !== 'Windows') {
        shell_exec('stty echo');
    }
    fwrite(STDOUT, PHP_EOL);
    return $value;
}

$target = $argv[1] ?? 'app';
if (!in_array($target, ['app', 'migration', 'test'], true)) {
    fwrite(STDERR, "Cách dùng: php tools/configure.php [app|migration|test]\n");
    exit(2);
}

$config = [
    'host' => prompt('Host', '127.0.0.1'),
    'port' => (int) prompt('Port', '3306'),
    'database' => prompt('Schema', $target === 'test' ? 'volunteer_test' : 'volunteer_app'),
    'user' => prompt(match ($target) {
        'app' => 'Tài khoản ứng dụng',
        'test' => 'Tài khoản kiểm thử',
        default => 'Tài khoản migration',
    }),
    'password' => promptSecret('Mật khẩu (không hiển thị)'),
];

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $connection = new mysqli(
        $config['host'],
        $config['user'],
        $config['password'],
        $config['database'],
        $config['port']
    );
    $connection->set_charset('utf8mb4');
    $connection->query('SELECT 1');
    if ($target === 'app') {
        require_once dirname(__DIR__) . '/src/DatabasePrivileges.php';
        $privileges = DatabasePrivileges::inspect($connection);
        if ($privileges['missing'] !== []) {
            throw new RuntimeException('Tài khoản ứng dụng thiếu quyền: ' . implode(', ', $privileges['missing']) . '.');
        }
        if ($privileges['excessive']) {
            throw new RuntimeException('Tài khoản ứng dụng có quyền DDL/ALL quá rộng; chỉ dùng SELECT, INSERT, UPDATE, DELETE.');
        }
    }
    $connection->close();
} catch (Throwable $exception) {
    fwrite(STDERR, "Không thể kết nối; cấu hình cũ được giữ nguyên. Chi tiết: {$exception->getMessage()}\n");
    exit(1);
}

$directory = dirname(__DIR__) . '/.local';
if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
    fwrite(STDERR, "Không thể tạo thư mục cấu hình riêng.\n");
    exit(1);
}
$filename = match ($target) {
    'migration' => 'migration-db.json',
    'test' => 'test-db.json',
    default => 'db.json',
};
$path = $directory . '/' . $filename;
$temporary = $path . '.tmp';
$json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if (!is_string($json) || file_put_contents($temporary, $json . PHP_EOL, LOCK_EX) === false) {
    fwrite(STDERR, "Không thể ghi cấu hình.\n");
    exit(1);
}
chmod($temporary, 0600);
rename($temporary, $path);
fwrite(STDOUT, "Đã kiểm tra kết nối và lưu cấu hình riêng tại {$path}.\n");
