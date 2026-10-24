<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Allocation/Snapshot.php';

const OUTPUT_DIRECTORY = __DIR__ . '/../database/fixtures/generated';

function baseSnapshot(string $code): array
{
    $skills = [
        ['id' => 1, 'code' => 'SO_CUU', 'name' => 'Sơ cứu'],
        ['id' => 2, 'code' => 'GIAO_TIEP', 'name' => 'Giao tiếp'],
        ['id' => 3, 'code' => 'HAU_CAN', 'name' => 'Hậu cần'],
        ['id' => 4, 'code' => 'TRUYEN_THONG', 'name' => 'Truyền thông'],
        ['id' => 5, 'code' => 'DIEU_PHOI', 'name' => 'Điều phối'],
    ];
    $volunteers = [];
    for ($id = 1; $id <= 30; $id++) {
        $volunteers[] = [
            'id' => $id,
            'code' => sprintf('TNV%02d', $id),
            'name' => sprintf('Tình nguyện viên %02d', $id),
            'active' => true,
            'skill_ids' => [1, 2, 3, 4, 5],
            'availability' => [
                ['start_at' => '2027-01-10 07:00:00', 'end_at' => '2027-01-10 18:00:00'],
                ['start_at' => '2027-01-11 07:00:00', 'end_at' => '2027-01-11 18:00:00'],
                ['start_at' => '2027-01-12 07:00:00', 'end_at' => '2027-01-12 18:00:00'],
            ],
            'registrations' => [[
                'event_id' => 1,
                'status' => 'registered',
                'registered_at' => sprintf('2026-12-%02d 01:00:00', (($id - 1) % 28) + 1),
            ]],
        ];
    }

    return [
        'dataset' => [
            'code' => $code,
            'version' => '1.0.0',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'seed' => 62027,
            'description' => 'Bộ dữ liệu chuẩn phục vụ so sánh hai thuật toán trên cùng snapshot.',
        ],
        'skills' => $skills,
        'events' => [[
            'id' => 1,
            'name' => 'Chiến dịch tình nguyện chuẩn',
            'location' => 'Thành phố Hồ Chí Minh',
            'starts_at' => '2027-01-10 07:00:00',
        ]],
        'shifts' => sequentialShifts(),
        'volunteers' => $volunteers,
        'fixed_assignments' => [],
        'expected' => ['volunteers' => 30, 'shifts' => 12, 'skills' => 5, 'valid_schedule_required' => true],
    ];
}

function sequentialShifts(): array
{
    $times = [['08:00:00', '10:00:00'], ['10:00:00', '12:00:00'], ['13:00:00', '15:00:00'], ['15:00:00', '17:00:00']];
    $shifts = [];
    $id = 1;
    for ($day = 10; $day <= 12; $day++) {
        foreach ($times as $index => [$start, $end]) {
            $shifts[] = [
                'id' => $id,
                'event_id' => 1,
                'name' => sprintf('Ngày %d - Ca %d', $day - 9, $index + 1),
                'start_at' => sprintf('2027-01-%02d %s', $day, $start),
                'end_at' => sprintf('2027-01-%02d %s', $day, $end),
                'required_count' => 3,
                'skill_ids' => [(($id - 1) % 5) + 1],
            ];
            $id++;
        }
    }
    return $shifts;
}

function enoughDataset(): array
{
    $snapshot = baseSnapshot('DU_NGUOI_V1');
    $snapshot['dataset']['description'] = 'Đủ nguồn lực: mọi tình nguyện viên đáp ứng mọi kỹ năng và lịch rảnh.';
    $snapshot['expected']['full_shift_rate'] = 100;
    return $snapshot;
}

function shortageDataset(): array
{
    $snapshot = baseSnapshot('THIEU_NGUOI_V1');
    $snapshot['dataset']['description'] = 'Thiếu nguồn lực: ca cuối cần sơ cứu nhưng chỉ một người có kỹ năng.';
    $snapshot['expected']['unavoidable_shortage'] = ['shift_id' => 12, 'minimum_missing' => 2];
    foreach ($snapshot['volunteers'] as &$volunteer) {
        $volunteer['skill_ids'] = (int) $volunteer['id'] === 1 ? [1, 2, 3, 4, 5] : [2, 3, 4, 5];
    }
    unset($volunteer);
    foreach ($snapshot['shifts'] as &$shift) {
        $shift['skill_ids'] = (int) $shift['id'] === 12 ? [1] : [2];
    }
    unset($shift);
    return $snapshot;
}

function overlapDataset(): array
{
    $snapshot = baseSnapshot('TRUNG_LICH_V1');
    $snapshot['dataset']['description'] = 'Trùng lịch: nhiều ca chồng lấn để kiểm tra ràng buộc thời gian.';
    $snapshot['expected']['assignment_overlap_count'] = 0;
    $starts = ['08:00:00', '09:00:00', '10:00:00', '11:00:00'];
    $ends = ['11:00:00', '12:00:00', '13:00:00', '14:00:00'];
    foreach ($snapshot['shifts'] as $index => &$shift) {
        $day = 10 + intdiv($index, 4);
        $slot = $index % 4;
        $shift['start_at'] = sprintf('2027-01-%02d %s', $day, $starts[$slot]);
        $shift['end_at'] = sprintf('2027-01-%02d %s', $day, $ends[$slot]);
        $shift['skill_ids'] = [($slot % 3) + 1];
    }
    unset($shift);
    foreach ($snapshot['volunteers'] as &$volunteer) {
        $id = (int) $volunteer['id'];
        $volunteer['skill_ids'] = array_values(array_unique([1 + ($id % 3), 1 + (($id + 1) % 3), 4, 5]));
    }
    unset($volunteer);
    return $snapshot;
}

if (!is_dir(OUTPUT_DIRECTORY) && !mkdir(OUTPUT_DIRECTORY, 0775, true) && !is_dir(OUTPUT_DIRECTORY)) {
    throw new RuntimeException('Không thể tạo thư mục fixture.');
}

foreach ([enoughDataset(), shortageDataset(), overlapDataset()] as $snapshot) {
    $code = (string) $snapshot['dataset']['code'];
    $path = OUTPUT_DIRECTORY . '/' . $code . '.json';
    if (file_put_contents($path, Snapshot::encode($snapshot), LOCK_EX) === false) {
        throw new RuntimeException('Không thể ghi ' . $path);
    }
    printf("Đã tạo %s — hash %s\n", $path, Snapshot::hash($snapshot));
}
