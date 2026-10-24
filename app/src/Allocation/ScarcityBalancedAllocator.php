<?php

declare(strict_types=1);

final class ScarcityBalancedAllocator
{
    public const NAME = 'scarcity_balanced';
    public const VERSION = '1.0.0';

    public function __construct(private readonly EligibilityChecker $eligibility)
    {
    }

    public function allocate(array $snapshot, array $config = []): array
    {
        $maxRepair = max(0, (int) ($config['max_repair_iterations'] ?? 100));
        $shifts = Snapshot::shiftsById($snapshot);
        $volunteers = Snapshot::volunteersById($snapshot);
        $schedule = [];
        foreach ($snapshot['fixed_assignments'] ?? [] as $assignment) {
            if (isset($shifts[(int) $assignment['shift_id']])) {
                $shift = $shifts[(int) $assignment['shift_id']];
                $schedule[] = $assignment + ['start_at' => $shift['start_at'], 'end_at' => $shift['end_at'], 'fixed' => true];
            }
        }

        $pending = array_keys($shifts);
        while ($pending !== []) {
            $choices = [];
            foreach ($pending as $shiftId) {
                $shift = $shifts[$shiftId];
                $remaining = (int) $shift['required_count'] - $this->countShift($schedule, $shiftId);
                if ($remaining <= 0) {
                    continue;
                }
                $eligible = $this->eligibleVolunteers($volunteers, $shift, $schedule);
                $choices[] = [
                    'shift_id' => $shiftId,
                    'remaining' => $remaining,
                    'eligible' => $eligible,
                    'ratio' => count($eligible) / $remaining,
                    'start_at' => $shift['start_at'],
                ];
            }
            if ($choices === []) {
                break;
            }
            usort($choices, static fn (array $a, array $b): int => [
                $a['ratio'], count($a['eligible']), $a['start_at'], $a['shift_id'],
            ] <=> [
                $b['ratio'], count($b['eligible']), $b['start_at'], $b['shift_id'],
            ]);
            $choice = $choices[0];
            $shift = $shifts[$choice['shift_id']];
            if ($choice['eligible'] === []) {
                $pending = array_values(array_diff($pending, [$choice['shift_id']]));
                continue;
            }

            $eligible = $choice['eligible'];
            usort($eligible, function (array $a, array $b) use ($schedule, $pending, $shifts): int {
                return [
                    $this->load($schedule, (int) $a['id']),
                    $this->opportunities($a, $pending, $shifts, $schedule),
                    $this->registrationForPending($a, $pending, $shifts),
                    (int) $a['id'],
                ] <=> [
                    $this->load($schedule, (int) $b['id']),
                    $this->opportunities($b, $pending, $shifts, $schedule),
                    $this->registrationForPending($b, $pending, $shifts),
                    (int) $b['id'],
                ];
            });
            $volunteer = $eligible[0];
            $schedule[] = $this->assignment($shift, $volunteer);
            if ($this->countShift($schedule, (int) $shift['id']) >= (int) $shift['required_count']) {
                $pending = array_values(array_diff($pending, [(int) $shift['id']]));
            }
        }

        $schedule = $this->boundedRepair($snapshot, $schedule, $maxRepair);
        $validator = new ScheduleValidator($this->eligibility);
        $validation = $validator->validate($snapshot, $schedule);
        $metrics = Metrics::calculate($snapshot, $schedule, $validation);
        $assignedIds = array_unique(array_map(static fn (array $a): int => (int) $a['volunteer_id'], $schedule));
        $unassigned = [];
        foreach ($volunteers as $volunteer) {
            if (!in_array((int) $volunteer['id'], $assignedIds, true)) {
                $unassigned[] = ['volunteer_id' => (int) $volunteer['id'], 'name' => $volunteer['name'], 'reason' => $this->unassignedReason($volunteer, $snapshot['shifts'] ?? [], $schedule)];
            }
        }

        return [
            'algorithm' => self::NAME,
            'algorithm_version' => self::VERSION,
            'config' => $config + [
                'shift_priority' => 'eligible/remaining,eligible_count,start_at,id',
                'volunteer_priority' => 'load,opportunities,registered_at,id',
                'max_repair_iterations' => $maxRepair,
                'deterministic' => true,
            ],
            'assignments' => array_values($schedule),
            'validation' => $validation,
            'metrics' => $metrics,
            'unassigned_volunteers' => $unassigned,
        ];
    }

    private function eligibleVolunteers(array $volunteers, array $shift, array $schedule): array
    {
        return array_values(array_filter($volunteers, fn (array $volunteer): bool => $this->eligibility->check($volunteer, $shift, $schedule)['eligible']));
    }

    private function countShift(array $schedule, int $shiftId): int
    {
        return count(array_filter($schedule, static fn (array $item): bool => (int) $item['shift_id'] === $shiftId));
    }

    private function load(array $schedule, int $volunteerId): int
    {
        return count(array_filter($schedule, static fn (array $item): bool => (int) $item['volunteer_id'] === $volunteerId));
    }

    private function opportunities(array $volunteer, array $pending, array $shifts, array $schedule): int
    {
        $count = 0;
        foreach ($pending as $shiftId) {
            if ($this->eligibility->check($volunteer, $shifts[$shiftId], $schedule)['eligible']) {
                $count++;
            }
        }
        return $count;
    }

    private function registrationForPending(array $volunteer, array $pending, array $shifts): string
    {
        $times = [];
        foreach ($pending as $shiftId) {
            $times[] = $this->eligibility->registrationTime($volunteer, (int) $shifts[$shiftId]['event_id']);
        }
        sort($times, SORT_STRING);
        return $times[0] ?? '9999-12-31 23:59:59';
    }

    private function assignment(array $shift, array $volunteer): array
    {
        return [
            'shift_id' => (int) $shift['id'],
            'volunteer_id' => (int) $volunteer['id'],
            'start_at' => $shift['start_at'],
            'end_at' => $shift['end_at'],
            'fixed' => false,
        ];
    }

    private function boundedRepair(array $snapshot, array $schedule, int $maxIterations): array
    {
        if ($maxIterations === 0) {
            return $schedule;
        }
        $shifts = Snapshot::shiftsById($snapshot);
        $volunteers = Snapshot::volunteersById($snapshot);
        $iterations = 0;
        foreach ($shifts as $target) {
            while ($this->countShift($schedule, (int) $target['id']) < (int) $target['required_count'] && $iterations < $maxIterations) {
                $iterations++;
                $changed = false;
                foreach ($volunteers as $candidateA) {
                    $withoutOwn = array_values(array_filter($schedule, static fn (array $a): bool => (int) $a['volunteer_id'] !== (int) $candidateA['id']));
                    $baseEligibility = $this->eligibility->check($candidateA, $target, $withoutOwn);
                    if (!$baseEligibility['eligible']) {
                        continue;
                    }
                    $conflicts = array_values(array_filter($schedule, function (array $a) use ($candidateA, $target): bool {
                        return (int) $a['volunteer_id'] === (int) $candidateA['id']
                            && $this->eligibility->overlaps($a['start_at'], $a['end_at'], $target['start_at'], $target['end_at']);
                    }));
                    if (count($conflicts) !== 1 || ($conflicts[0]['fixed'] ?? false)) {
                        continue;
                    }
                    $source = $shifts[(int) $conflicts[0]['shift_id']];
                    $scheduleWithoutA = array_values(array_filter($schedule, static fn (array $a): bool => !(
                        (int) $a['shift_id'] === (int) $source['id'] && (int) $a['volunteer_id'] === (int) $candidateA['id']
                    )));
                    foreach ($volunteers as $candidateB) {
                        if ((int) $candidateB['id'] === (int) $candidateA['id']) {
                            continue;
                        }
                        if ($this->eligibility->check($candidateB, $source, $scheduleWithoutA)['eligible']) {
                            $schedule = $scheduleWithoutA;
                            $schedule[] = $this->assignment($source, $candidateB);
                            $schedule[] = $this->assignment($target, $candidateA);
                            $changed = true;
                            break 2;
                        }
                    }
                }
                if (!$changed) {
                    break;
                }
            }
        }
        return $schedule;
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
