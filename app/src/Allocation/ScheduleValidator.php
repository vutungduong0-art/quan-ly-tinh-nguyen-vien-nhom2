<?php

declare(strict_types=1);

final class ScheduleValidator
{
    public function __construct(private readonly EligibilityChecker $eligibility)
    {
    }

    public function validate(array $snapshot, array $assignments): array
    {
        $volunteers = Snapshot::volunteersById($snapshot);
        $shifts = Snapshot::shiftsById($snapshot);
        $violations = [];
        $seenPairs = [];
        $byShift = [];
        $byVolunteer = [];

        foreach ($assignments as $assignment) {
            $shiftId = (int) ($assignment['shift_id'] ?? 0);
            $volunteerId = (int) ($assignment['volunteer_id'] ?? 0);
            if (!isset($shifts[$shiftId], $volunteers[$volunteerId])) {
                $violations[] = ['type' => 'unknown_reference', 'shift_id' => $shiftId, 'volunteer_id' => $volunteerId];
                continue;
            }
            $pair = $shiftId . ':' . $volunteerId;
            if (isset($seenPairs[$pair])) {
                $violations[] = ['type' => 'duplicate_pair', 'shift_id' => $shiftId, 'volunteer_id' => $volunteerId];
            }
            $seenPairs[$pair] = true;
            $byShift[$shiftId][] = $volunteerId;
            $byVolunteer[$volunteerId][] = $shiftId;

            $result = $this->eligibility->check($volunteers[$volunteerId], $shifts[$shiftId], [], null);
            foreach ($result['reasons'] as $reason) {
                $type = str_starts_with($reason, 'Thiếu kỹ năng') ? 'missing_skill'
                    : ($reason === 'Chưa đăng ký hoạt động' ? 'not_registered'
                    : ($reason === 'Lịch rảnh không bao phủ toàn bộ ca' ? 'unavailable' : 'inactive'));
                $violations[] = ['type' => $type, 'shift_id' => $shiftId, 'volunteer_id' => $volunteerId, 'detail' => $reason];
            }
        }

        foreach ($byShift as $shiftId => $volunteerIds) {
            if (count($volunteerIds) > (int) $shifts[$shiftId]['required_count']) {
                $violations[] = [
                    'type' => 'over_capacity',
                    'shift_id' => $shiftId,
                    'excess' => count($volunteerIds) - (int) $shifts[$shiftId]['required_count'],
                ];
            }
        }

        foreach ($byVolunteer as $volunteerId => $shiftIds) {
            sort($shiftIds, SORT_NUMERIC);
            $total = count($shiftIds);
            for ($i = 0; $i < $total; $i++) {
                for ($j = $i + 1; $j < $total; $j++) {
                    $a = $shifts[$shiftIds[$i]];
                    $b = $shifts[$shiftIds[$j]];
                    if ($this->eligibility->overlaps($a['start_at'], $a['end_at'], $b['start_at'], $b['end_at'])) {
                        $violations[] = [
                            'type' => 'overlap',
                            'volunteer_id' => $volunteerId,
                            'shift_ids' => [(int) $a['id'], (int) $b['id']],
                        ];
                    }
                }
            }
        }

        return ['valid' => $violations === [], 'violations' => $violations, 'count' => count($violations)];
    }
}

