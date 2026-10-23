<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Validator.php';
require_once dirname(__DIR__) . '/src/Allocation/Snapshot.php';
require_once dirname(__DIR__) . '/src/Allocation/EligibilityChecker.php';
require_once dirname(__DIR__) . '/src/Allocation/ScheduleValidator.php';
require_once dirname(__DIR__) . '/src/Allocation/Metrics.php';
require_once dirname(__DIR__) . '/src/Allocation/RegistrationOrderAllocator.php';
require_once dirname(__DIR__) . '/src/Allocation/ScarcityBalancedAllocator.php';
require_once dirname(__DIR__) . '/src/Allocation/ComparisonService.php';

$passed = 0;
$failed = 0;

function test(string $name, callable $callback): void
{
    global $passed, $failed;
    try {
        $callback();
        $passed++;
        fwrite(STDOUT, "[PASS] {$name}\n");
    } catch (Throwable $exception) {
        $failed++;
        fwrite(STDOUT, "[FAIL] {$name}: {$exception->getMessage()}\n");
    }
}

function assertSameValue(mixed $expected, mixed $actual): void
{
    if ($expected !== $actual) {
        throw new RuntimeException('Mong đợi ' . var_export($expected, true) . ', nhận ' . var_export($actual, true));
    }
}

function assertTrueValue(bool $condition, string $message = 'Điều kiện không đúng'): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

test('Chuyển giờ Việt Nam sang UTC', function (): void {
    $errors = [];
    $actual = Validator::dateTimeToUtc('2027-03-13T08:00', 'Bắt đầu', $errors);
    assertSameValue('2027-03-13 01:00:00', $actual);
    assertSameValue([], $errors);
});

test('Từ chối ngày giờ sai định dạng', function (): void {
    $errors = [];
    assertSameValue(null, Validator::dateTimeToUtc('13/03/2027 08:00', 'Bắt đầu', $errors));
    assertTrueValue(count($errors) === 1);
});

test('Từ chối khoảng thời gian đảo', function (): void {
    $errors = [];
    Validator::orderedRange('2027-03-13 10:00:00', '2027-03-13 09:00:00', 'ca', $errors);
    assertTrueValue(count($errors) === 1);
});

test('Chấp nhận thời gian bắt đầu nhỏ hơn kết thúc', function (): void {
    $errors = [];
    Validator::orderedRange('2027-03-13 08:00:00', '2027-03-13 09:00:00', 'ca', $errors);
    assertSameValue([], $errors);
});

test('Số người phải là số nguyên dương', function (): void {
    foreach ([0, -1, '1.5', 'abc'] as $invalid) {
        $errors = [];
        assertSameValue(null, Validator::positiveInteger($invalid, 'Số người', $errors));
        assertTrueValue($errors !== []);
    }
});

test('Chấp nhận số người nguyên dương', function (): void {
    $errors = [];
    assertSameValue(3, Validator::positiveInteger('3', 'Số người', $errors));
    assertSameValue([], $errors);
});

test('Tên hoạt động là bắt buộc', function (): void {
    $errors = [];
    Validator::requiredText('   ', 'Tên hoạt động', 180, $errors);
    assertTrueValue($errors !== []);
});

test('Giới hạn độ dài dữ liệu', function (): void {
    $errors = [];
    Validator::requiredText(str_repeat('x', 11), 'Tên', 10, $errors);
    assertTrueValue($errors !== []);
});

test('Khoảng thời gian nửa mở cho phép hai ca chạm biên', function (): void {
    $checker = new EligibilityChecker();
    assertSameValue(false, $checker->overlaps('2027-01-01 08:00:00', '2027-01-01 10:00:00', '2027-01-01 10:00:00', '2027-01-01 12:00:00'));
    assertSameValue(true, $checker->overlaps('2027-01-01 08:00:00', '2027-01-01 10:01:00', '2027-01-01 10:00:00', '2027-01-01 12:00:00'));
});

test('Hai khoảng lịch rảnh liền nhau được hợp nhất', function (): void {
    $checker = new EligibilityChecker();
    assertTrueValue($checker->availabilityCovers([
        ['start_at' => '2027-01-01 08:00:00', 'end_at' => '2027-01-01 10:00:00'],
        ['start_at' => '2027-01-01 10:00:00', 'end_at' => '2027-01-01 12:00:00'],
    ], '2027-01-01 09:00:00', '2027-01-01 11:00:00'));
});

test('Điều kiện kỹ năng yêu cầu đủ toàn bộ, không phải chỉ một phần', function (): void {
    $checker = new EligibilityChecker();
    $volunteer = [
        'id' => 1, 'active' => true, 'skill_ids' => [1],
        'availability' => [['start_at' => '2027-01-01 08:00:00', 'end_at' => '2027-01-01 12:00:00']],
        'registrations' => [['event_id' => 1, 'status' => 'registered']],
    ];
    $shift = ['id' => 1, 'event_id' => 1, 'skill_ids' => [1, 2], 'start_at' => '2027-01-01 09:00:00', 'end_at' => '2027-01-01 10:00:00'];
    $result = $checker->check($volunteer, $shift);
    assertSameValue(false, $result['eligible']);
    assertTrueValue(str_contains(implode(' ', $result['reasons']), '2'));
});

test('Lịch rảnh không phủ trọn ca bị từ chối', function (): void {
    $checker = new EligibilityChecker();
    assertSameValue(false, $checker->availabilityCovers([
        ['start_at' => '2027-01-01 08:00:00', 'end_at' => '2027-01-01 09:00:00'],
    ], '2027-01-01 08:00:00', '2027-01-01 10:00:00'));
});

test('Ca không yêu cầu kỹ năng không tự loại ứng viên', function (): void {
    $checker = new EligibilityChecker();
    $result = $checker->check([
        'id' => 1, 'active' => true, 'skill_ids' => [],
        'availability' => [['start_at' => '2027-01-01 08:00:00', 'end_at' => '2027-01-01 10:00:00']],
        'registrations' => [['event_id' => 1, 'status' => 'registered']],
    ], ['id' => 1, 'event_id' => 1, 'skill_ids' => [], 'start_at' => '2027-01-01 08:00:00', 'end_at' => '2027-01-01 10:00:00']);
    assertSameValue(true, $result['eligible']);
});

test('Bộ kiểm tra phát hiện trùng lịch của cùng người', function (): void {
    $checker = new EligibilityChecker();
    $volunteer = [
        'id' => 1, 'active' => true, 'skill_ids' => [],
        'availability' => [['start_at' => '2027-01-01 07:00:00', 'end_at' => '2027-01-01 12:00:00']],
        'registrations' => [['event_id' => 1, 'status' => 'registered']],
    ];
    $result = $checker->check($volunteer, [
        'id' => 2, 'event_id' => 1, 'skill_ids' => [], 'start_at' => '2027-01-01 09:00:00', 'end_at' => '2027-01-01 11:00:00',
    ], [['shift_id' => 1, 'volunteer_id' => 1, 'start_at' => '2027-01-01 08:00:00', 'end_at' => '2027-01-01 10:00:00']]);
    assertSameValue(false, $result['eligible']);
    assertTrueValue(str_contains(implode(' ', $result['reasons']), 'Trùng lịch'));
});

test('Validator phát hiện vượt số người cần', function (): void {
    $snapshot = [
        'shifts' => [['id' => 1, 'event_id' => 1, 'name' => 'Ca', 'start_at' => '2027-01-01 08:00:00', 'end_at' => '2027-01-01 10:00:00', 'required_count' => 1, 'skill_ids' => []]],
        'volunteers' => [],
    ];
    for ($id = 1; $id <= 2; $id++) {
        $snapshot['volunteers'][] = [
            'id' => $id, 'active' => true, 'skill_ids' => [],
            'availability' => [['start_at' => '2027-01-01 08:00:00', 'end_at' => '2027-01-01 10:00:00']],
            'registrations' => [['event_id' => 1, 'status' => 'registered']],
        ];
    }
    $validation = (new ScheduleValidator(new EligibilityChecker()))->validate($snapshot, [
        ['shift_id' => 1, 'volunteer_id' => 1], ['shift_id' => 1, 'volunteer_id' => 2],
    ]);
    assertSameValue(false, $validation['valid']);
    assertTrueValue(in_array('over_capacity', array_column($validation['violations'], 'type'), true));
});

test('Ví dụ khan hiếm A/B: cải tiến đủ hai ca, baseline chỉ đủ một', function (): void {
    $availability = [['start_at' => '2027-01-01 08:00:00', 'end_at' => '2027-01-01 10:00:00']];
    $snapshot = [
        'dataset' => ['code' => 'AB', 'version' => '1'],
        'shifts' => [
            ['id' => 1, 'event_id' => 1, 'name' => 'Hậu cần', 'start_at' => '2027-01-01 08:00:00', 'end_at' => '2027-01-01 10:00:00', 'required_count' => 1, 'skill_ids' => [2]],
            ['id' => 2, 'event_id' => 1, 'name' => 'Sơ cứu', 'start_at' => '2027-01-01 08:00:00', 'end_at' => '2027-01-01 10:00:00', 'required_count' => 1, 'skill_ids' => [1]],
        ],
        'volunteers' => [
            ['id' => 1, 'name' => 'A', 'active' => true, 'skill_ids' => [1, 2], 'availability' => $availability, 'registrations' => [['event_id' => 1, 'status' => 'registered', 'registered_at' => '2026-01-01 00:00:00']]],
            ['id' => 2, 'name' => 'B', 'active' => true, 'skill_ids' => [2], 'availability' => $availability, 'registrations' => [['event_id' => 1, 'status' => 'registered', 'registered_at' => '2026-01-02 00:00:00']]],
        ],
        'fixed_assignments' => [],
    ];
    $comparison = (new ComparisonService())->compare($snapshot);
    assertSameValue(50.0, $comparison['baseline']['metrics']['full_shift_rate']);
    assertSameValue(100.0, $comparison['improved']['metrics']['full_shift_rate']);
});

test('CSV vô hiệu hóa ô có tiền tố công thức', function (): void {
    require_once dirname(__DIR__) . '/src/CsvValue.php';
    foreach (['=1+1', '+cmd', '-2+3', '@SUM(A1)'] as $dangerous) {
        assertTrueValue(str_starts_with(CsvValue::safe($dangerous), "'"));
    }
    assertSameValue('Nội dung thường', CsvValue::safe('Nội dung thường'));
});

foreach (['DU_NGUOI_V1', 'THIEU_NGUOI_V1', 'TRUNG_LICH_V1'] as $fixtureCode) {
    test("Hai thuật toán hợp lệ và xác định trên fixture $fixtureCode", function () use ($fixtureCode): void {
        $path = dirname(__DIR__) . '/database/fixtures/generated/' . $fixtureCode . '.json';
        $snapshot = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        assertSameValue(30, count($snapshot['volunteers']));
        assertSameValue(12, count($snapshot['shifts']));
        assertSameValue(5, count($snapshot['skills']));
        $service = new ComparisonService();
        $first = $service->compare($snapshot);
        $second = $service->compare($snapshot);
        foreach (['baseline', 'improved'] as $key) {
            assertSameValue(0, $first[$key]['validation']['count']);
            assertSameValue($first[$key]['assignments'], $second[$key]['assignments']);
        }
        assertTrueValue($first['improved']['metrics']['load_gap'] <= $first['baseline']['metrics']['load_gap']);
        if ($fixtureCode === 'DU_NGUOI_V1') {
            assertSameValue(100.0, $first['improved']['metrics']['full_shift_rate']);
        }
        if ($fixtureCode === 'THIEU_NGUOI_V1') {
            assertTrueValue($first['improved']['metrics']['full_shift_rate'] < 100);
        }
    });
}

fwrite(STDOUT, "Kết quả: {$passed} đạt, {$failed} lỗi.\n");
exit($failed === 0 ? 0 : 1);
