<?php

declare(strict_types=1);

final class EligibilityChecker
{
    public function check(array $volunteer, array $shift, array $schedule = [], ?int $ignoreShiftId = null): array
    {
        $reasons = [];
        if (!($volunteer['active'] ?? false)) {
            $reasons[] = 'Tài khoản không hoạt động';
        }

        if (!$this->hasRegistration($volunteer, (int) $shift['event_id'])) {
            $reasons[] = 'Chưa đăng ký hoạt động';
        }

        $requiredSkills = array_map('intval', $shift['skill_ids'] ?? []);
        $volunteerSkills = array_map('intval', $volunteer['skill_ids'] ?? []);
        $missing = array_values(array_diff($requiredSkills, $volunteerSkills));
        if ($missing !== []) {
            $reasons[] = 'Thiếu kỹ năng: ' . implode(', ', $missing);
        }

        if (!$this->availabilityCovers($volunteer['availability'] ?? [], (string) $shift['start_at'], (string) $shift['end_at'])) {
            $reasons[] = 'Lịch rảnh không bao phủ toàn bộ ca';
        }

        foreach (array_merge($volunteer['busy_assignments'] ?? [], $schedule) as $assignment) {
            if ((int) ($assignment['volunteer_id'] ?? 0) !== (int) $volunteer['id']) {
                continue;
            }
            if ($ignoreShiftId !== null && (int) ($assignment['shift_id'] ?? 0) === $ignoreShiftId) {
                continue;
            }
            if ((int) ($assignment['shift_id'] ?? 0) === (int) $shift['id']) {
                $reasons[] = 'Đã có trong ca này';
                continue;
            }
            if ($this->overlaps(
                (string) $assignment['start_at'],
                (string) $assignment['end_at'],
                (string) $shift['start_at'],
                (string) $shift['end_at']
            )) {
                $reasons[] = 'Trùng lịch với ca ' . (string) ($assignment['shift_id'] ?? 'khác');
            }
        }

        return ['eligible' => $reasons === [], 'reasons' => array_values(array_unique($reasons))];
    }

    public function hasRegistration(array $volunteer, int $eventId): bool
    {
        foreach ($volunteer['registrations'] ?? [] as $registration) {
            if ((int) ($registration['event_id'] ?? 0) === $eventId
                && ($registration['status'] ?? '') === 'registered') {
                return true;
            }
        }
        return false;
    }

    public function registrationTime(array $volunteer, int $eventId): string
    {
        foreach ($volunteer['registrations'] ?? [] as $registration) {
            if ((int) ($registration['event_id'] ?? 0) === $eventId
                && ($registration['status'] ?? '') === 'registered') {
                return (string) ($registration['registered_at'] ?? '9999-12-31 23:59:59');
            }
        }
        return '9999-12-31 23:59:59';
    }

    public function availabilityCovers(array $intervals, string $start, string $end): bool
    {
        $normalized = [];
        foreach ($intervals as $interval) {
            if (!isset($interval['start_at'], $interval['end_at']) || $interval['start_at'] >= $interval['end_at']) {
                continue;
            }
            $normalized[] = ['start_at' => (string) $interval['start_at'], 'end_at' => (string) $interval['end_at']];
        }
        usort($normalized, static fn (array $a, array $b): int => [$a['start_at'], $a['end_at']] <=> [$b['start_at'], $b['end_at']]);

        $merged = [];
        foreach ($normalized as $interval) {
            $lastIndex = count($merged) - 1;
            if ($lastIndex >= 0 && $interval['start_at'] <= $merged[$lastIndex]['end_at']) {
                if ($interval['end_at'] > $merged[$lastIndex]['end_at']) {
                    $merged[$lastIndex]['end_at'] = $interval['end_at'];
                }
            } else {
                $merged[] = $interval;
            }
        }

        foreach ($merged as $interval) {
            if ($interval['start_at'] <= $start && $interval['end_at'] >= $end) {
                return true;
            }
        }
        return false;
    }

    public function overlaps(string $aStart, string $aEnd, string $bStart, string $bEnd): bool
    {
        return $aStart < $bEnd && $bStart < $aEnd;
    }
}
