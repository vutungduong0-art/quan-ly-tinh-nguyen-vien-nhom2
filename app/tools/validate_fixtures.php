<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Allocation/Snapshot.php';
require_once dirname(__DIR__) . '/src/Allocation/EligibilityChecker.php';
require_once dirname(__DIR__) . '/src/Allocation/ScheduleValidator.php';
require_once dirname(__DIR__) . '/src/Allocation/Metrics.php';
require_once dirname(__DIR__) . '/src/Allocation/RegistrationOrderAllocator.php';
require_once dirname(__DIR__) . '/src/Allocation/ScarcityBalancedAllocator.php';

$files = glob(dirname(__DIR__) . '/database/fixtures/generated/*.json') ?: [];
sort($files, SORT_STRING);
if (count($files) !== 3) {
    fwrite(STDERR, 'Cần đúng 3 fixture được sinh; hiện có ' . count($files) . ".\n");
    exit(1);
}

$failed = 0;
foreach ($files as $file) {
    try {
        $snapshot = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        foreach (['volunteers' => 30, 'shifts' => 12, 'skills' => 5] as $key => $expected) {
            if (count($snapshot[$key] ?? []) !== $expected) {
                throw new RuntimeException("$key phải có $expected bản ghi.");
            }
        }
        foreach ($snapshot['shifts'] as $shift) {
            if (($shift['start_at'] ?? '') >= ($shift['end_at'] ?? '') || (int) ($shift['required_count'] ?? 0) < 1) {
                throw new RuntimeException('Có ca sai thời gian hoặc nhu cầu.');
            }
        }
        foreach ($snapshot['volunteers'] as $volunteer) {
            if (!(new EligibilityChecker())->hasRegistration($volunteer, 1)) {
                throw new RuntimeException('Có tình nguyện viên thiếu đăng ký hoạt động chuẩn.');
            }
        }
        $baseline = (new RegistrationOrderAllocator(new EligibilityChecker()))->allocate($snapshot);
        $improved = (new ScarcityBalancedAllocator(new EligibilityChecker()))->allocate($snapshot);
        if (!$baseline['validation']['valid'] || !$improved['validation']['valid']) {
            throw new RuntimeException('Thuật toán tạo phương án có vi phạm.');
        }
        $code = (string) $snapshot['dataset']['code'];
        if ($code === 'DU_NGUOI_V1' && (float) $improved['metrics']['full_shift_rate'] !== 100.0) {
            throw new RuntimeException('Bộ đủ người không lấp đủ 12 ca.');
        }
        if ($code === 'THIEU_NGUOI_V1') {
            $shortage = array_values(array_filter(
                $improved['metrics']['shortages'],
                static fn (array $item): bool => (int) $item['shift_id'] === 12
            ));
            if ($shortage === [] || (int) $shortage[0]['missing'] < 2) {
                throw new RuntimeException('Bộ thiếu người chưa chứng minh ca 12 thiếu ít nhất 2 người.');
            }
        }
        printf("[PASS] %s — %s\n", $code, Snapshot::hash($snapshot));
    } catch (Throwable $exception) {
        $failed++;
        printf("[FAIL] %s: %s\n", basename($file), $exception->getMessage());
    }
}
exit($failed === 0 ? 0 : 1);
