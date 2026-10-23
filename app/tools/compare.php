<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Allocation/Snapshot.php';
require_once dirname(__DIR__) . '/src/Allocation/EligibilityChecker.php';
require_once dirname(__DIR__) . '/src/Allocation/ScheduleValidator.php';
require_once dirname(__DIR__) . '/src/Allocation/Metrics.php';
require_once dirname(__DIR__) . '/src/Allocation/RegistrationOrderAllocator.php';
require_once dirname(__DIR__) . '/src/Allocation/ScarcityBalancedAllocator.php';
require_once dirname(__DIR__) . '/src/Allocation/ComparisonService.php';

$options = getopt('', ['dataset:']);
$code = strtoupper(trim((string) ($options['dataset'] ?? '')));
if (!preg_match('/^[A-Z0-9_]+$/', $code)) {
    fwrite(STDERR, "Cách dùng: php tools/compare.php --dataset DU_NGUOI_V1\n");
    exit(2);
}

$fixture = __DIR__ . '/../database/fixtures/generated/' . $code . '.json';
if (!is_file($fixture)) {
    fwrite(STDERR, "Không tìm thấy fixture $code. Chạy php tools/generate_fixtures.php trước.\n");
    exit(2);
}
$snapshot = json_decode((string) file_get_contents($fixture), true, 512, JSON_THROW_ON_ERROR);
$report = (new ComparisonService())->compare($snapshot);
$versionPath = dirname(__DIR__, 2) . '/VERSION';
$report['project_version'] = is_file($versionPath) ? trim((string) file_get_contents($versionPath)) : 'unknown';
$targetDirectory = dirname(__DIR__, 2) . '/artifacts/comparison';
if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
    throw new RuntimeException('Không thể tạo thư mục artifacts/comparison.');
}
$target = $targetDirectory . '/' . $code . '.json';
file_put_contents($target, Snapshot::encode($report), LOCK_EX);

printf("Dataset: %s\nSnapshot: %s\n", $code, $report['snapshot_sha256']);
foreach (['baseline', 'improved'] as $key) {
    $result = $report[$key];
    printf(
        "%s: đủ ca %.2f%%, lấp vị trí %.2f%%, vi phạm %d, chênh tải %d\n",
        $result['algorithm'],
        (float) ($result['metrics']['full_shift_rate'] ?? 0),
        (float) ($result['metrics']['filled_position_rate'] ?? 0),
        (int) $result['metrics']['violation_count'],
        (int) $result['metrics']['load_gap']
    );
}
printf("Đã lưu: %s\n", $target);
