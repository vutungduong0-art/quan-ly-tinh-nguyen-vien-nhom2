<?php

declare(strict_types=1);

final class RegistrationOrderAllocator
{
    public const NAME = 'registration_order';
    public const VERSION = '1.0.0';

    public function __construct(private readonly EligibilityChecker $eligibility)
    {
    }

    public function allocate(array $snapshot, array $config = []): array
    {
        $shifts = $snapshot['shifts'] ?? [];
        usort($shifts, static fn (array $a, array $b): int => [$a['start_at'], (int) $a['id']] <=> [$b['start_at'], (int) $b['id']]);
        $volunteers = $snapshot['volunteers'] ?? [];
        $schedule = $this->fixedSchedule($snapshot);

        foreach ($shifts as $shift) {
            usort($volunteers, function (array $a, array $b) use ($shift): int {
                return [
                    $this->eligibility->registrationTime($a, (int) $shift['event_id']),
                    (int) $a['id'],
                ] <=> [
                    $this->eligibility->registrationTime($b, (int) $shift['event_id']),
                    (int) $b['id'],
                ];
            });
            foreach ($volunteers as $volunteer) {
                if ($this->countShift($schedule, (int) $shift['id']) >= (int) $shift['required_count']) {
                    break;
                }
                $result = $this->eligibility->check($volunteer, $shift, $schedule);
                if ($result['eligible']) {
                    $schedule[] = $this->assignment($shift, $volunteer, false);
                }
            }
        }

        return $this->result($snapshot, $schedule, $config);
    }

    private function fixedSchedule(array $snapshot): array
    {
        $result = [];
        $shifts = Snapshot::shiftsById($snapshot);
        foreach ($snapshot['fixed_assignments'] ?? [] as $assignment) {
            if (!isset($shifts[(int) $assignment['shift_id']])) {
                continue;
            }
            $shift = $shifts[(int) $assignment['shift_id']];
            $result[] = $assignment + ['start_at' => $shift['start_at'], 'end_at' => $shift['end_at'], 'fixed' => true];
        }
        return $result;
    }

    private function countShift(array $schedule, int $shiftId): int
    {
        return count(array_filter($schedule, static fn (array $item): bool => (int) $item['shift_id'] === $shiftId));
    }

    private function assignment(array $shift, array $volunteer, bool $fixed): array
    {
        return [
            'shift_id' => (int) $shift['id'],
            'volunteer_id' => (int) $volunteer['id'],
            'start_at' => $shift['start_at'],
            'end_at' => $shift['end_at'],
            'fixed' => $fixed,
        ];
    }

    private function result(array $snapshot, array $schedule, array $config): array
    {
        $validator = new ScheduleValidator($this->eligibility);
        $validation = $validator->validate($snapshot, $schedule);
        $metrics = Metrics::calculate($snapshot, $schedule, $validation);
        $assignedIds = array_unique(array_map(static fn (array $a): int => (int) $a['volunteer_id'], $schedule));
        $unassigned = [];
        foreach ($snapshot['volunteers'] ?? [] as $volunteer) {
            if (!in_array((int) $volunteer['id'], $assignedIds, true)) {
                $unassigned[] = ['volunteer_id' => (int) $volunteer['id'], 'name' => $volunteer['name'], 'reason' => $this->unassignedReason($volunteer, $snapshot['shifts'] ?? [], $schedule)];
            }
        }
        return [
            'algorithm' => self::NAME,
            'algorithm_version' => self::VERSION,
            'config' => $config + ['shift_order' => 'start_at,id', 'volunteer_order' => 'registered_at,id'],
            'assignments' => array_values($schedule),
            'validation' => $validation,
            'metrics' => $metrics,
            'unassigned_volunteers' => $unassigned,
        ];
    }

    private function unassignedReason(array $volunteer, array $shifts, array $schedule): string
    {
        $reasons = [];
        foreach ($shifts as $shift) {
            $check = $this->eligibility->check($volunteer, $shift, $schedule);
            if ($check['eligible']) {
                return 'Các ca phù hợp đã đủ số người cần';
            }
            $reasons = array_merge($reasons, $check['reasons']);
        }
        $reasons = array_values(array_unique(array_filter($reasons, static fn (string $reason): bool => $reason !== 'Đã có trong ca này')));
        return $reasons === [] ? 'Không có ca mục tiêu' : implode('; ', $reasons);
    }
}
