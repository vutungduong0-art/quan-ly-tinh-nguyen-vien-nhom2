<?php

declare(strict_types=1);

final class Metrics
{
    public static function calculate(array $snapshot, array $assignments, array $validation): array
    {
        $counts = [];
        $loads = [];
        foreach ($snapshot['volunteers'] ?? [] as $volunteer) {
            $loads[(int) $volunteer['id']] = 0;
        }
        foreach ($assignments as $assignment) {
            $shiftId = (int) $assignment['shift_id'];
            $volunteerId = (int) $assignment['volunteer_id'];
            $counts[$shiftId] = ($counts[$shiftId] ?? 0) + 1;
            $loads[$volunteerId] = ($loads[$volunteerId] ?? 0) + 1;
        }

        $full = 0;
        $filledPositions = 0;
        $requiredPositions = 0;
        $shortages = [];
        foreach ($snapshot['shifts'] ?? [] as $shift) {
            $assigned = $counts[(int) $shift['id']] ?? 0;
            $required = (int) $shift['required_count'];
            $requiredPositions += $required;
            $filledPositions += min($assigned, $required);
            if ($assigned === $required) {
                $full++;
            }
            if ($assigned < $required) {
                $shortages[] = [
                    'shift_id' => (int) $shift['id'],
                    'name' => $shift['name'],
                    'start_at' => $shift['start_at'],
                    'end_at' => $shift['end_at'],
                    'skill_ids' => array_map('intval', $shift['skill_ids'] ?? []),
                    'missing' => $required - $assigned,
                    'required_count' => $required,
                    'assigned_count' => $assigned,
                ];
            }
        }

        $shiftCount = count($snapshot['shifts'] ?? []);
        $loadValues = array_values($loads);
        return [
            'full_shift_rate' => $shiftCount === 0 ? null : round(100 * $full / $shiftCount, 2),
            'full_shifts' => $full,
            'total_shifts' => $shiftCount,
            'filled_position_rate' => $requiredPositions === 0 ? null : round(100 * $filledPositions / $requiredPositions, 2),
            'filled_positions' => $filledPositions,
            'required_positions' => $requiredPositions,
            'violation_count' => (int) ($validation['count'] ?? 0),
            'load_gap' => $loadValues === [] ? 0 : max($loadValues) - min($loadValues),
            'shortages' => $shortages,
            'volunteer_loads' => $loads,
        ];
    }
}
