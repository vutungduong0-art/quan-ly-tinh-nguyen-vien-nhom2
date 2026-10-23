<?php

declare(strict_types=1);

final class ReportRepository
{
    public function __construct(private readonly mysqli $db)
    {
    }

    public function summary(): array
    {
        return $this->db->query(
            "SELECT e.id, e.name,
                    (SELECT COUNT(*) FROM shifts s WHERE s.event_id = e.id) AS shifts,
                    (SELECT COALESCE(SUM(s.required_count), 0) FROM shifts s WHERE s.event_id = e.id) AS required_positions,
                    (SELECT COUNT(*) FROM assignments a JOIN shifts s ON s.id = a.shift_id
                     WHERE s.event_id = e.id AND a.status IN ('assigned','confirmed')) AS assigned_positions,
                    (SELECT COUNT(*) FROM assignments a JOIN shifts s ON s.id = a.shift_id
                     WHERE s.event_id = e.id AND a.status = 'confirmed') AS confirmed_positions,
                    (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.status = 'registered') AS registrations
             FROM events e ORDER BY e.start_at, e.id"
        )->fetch_all(MYSQLI_ASSOC);
    }

    public function algorithmRuns(): array
    {
        return (new AllocationRepository($this->db))->runs();
    }

    public function shiftSummary(?int $eventId = null): array
    {
        $sql = "SELECT e.id AS event_id, e.name AS event_name, e.status AS event_status,
                       s.id AS shift_id, s.name AS shift_name, s.start_at, s.end_at, s.required_count,
                       (SELECT GROUP_CONCAT(sk.name ORDER BY sk.name SEPARATOR ', ')
                        FROM shift_skills ss JOIN skills sk ON sk.id = ss.skill_id WHERE ss.shift_id = s.id) AS required_skills,
                       (SELECT COUNT(*) FROM assignments a
                        WHERE a.shift_id = s.id AND a.status IN ('assigned','confirmed')) AS assigned_count,
                       (SELECT COUNT(*) FROM assignments a
                        WHERE a.shift_id = s.id AND a.status = 'confirmed') AS confirmed_count,
                       (SELECT COUNT(*) FROM replacement_requests rr JOIN assignments a ON a.id = rr.assignment_id
                        WHERE a.shift_id = s.id AND rr.status = 'open') AS open_replacements,
                       (SELECT COUNT(*) FROM replacement_requests rr JOIN assignments a ON a.id = rr.assignment_id
                        WHERE a.shift_id = s.id AND rr.status = 'unfilled') AS unfilled_replacements
                FROM shifts s JOIN events e ON e.id = s.event_id";
        if ($eventId !== null) {
            $sql .= ' WHERE e.id = ?';
        }
        $sql .= ' ORDER BY e.start_at DESC, e.id DESC, s.start_at, s.id';
        $statement = $this->db->prepare($sql);
        if ($eventId !== null) {
            $statement->bind_param('i', $eventId);
        }
        $statement->execute();
        $rows = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($rows as &$row) {
            $row['missing_count'] = max(0, (int) $row['required_count'] - (int) $row['assigned_count']);
        }
        unset($row);
        return $rows;
    }

    public function comparisonGroups(): array
    {
        $groups = [];
        foreach ($this->algorithmRuns() as $run) {
            $group = (string) ($run['comparison_group'] ?? '');
            if ($group === '') {
                continue;
            }
            if (!isset($groups[$group])) {
                $groups[$group] = [
                    'id' => $group,
                    'event_name' => $run['event_name'],
                    'snapshot_sha256' => $run['snapshot_sha256'],
                    'dataset_code' => $run['dataset_code'],
                    'dataset_version' => $run['dataset_version'],
                    'project_version' => $run['project_version'],
                    'created_at' => $run['created_at'],
                    'baseline' => null,
                    'improved' => null,
                ];
            }
            if ($run['algorithm'] === RegistrationOrderAllocator::NAME) {
                $groups[$group]['baseline'] = $run;
            } elseif ($run['algorithm'] === ScarcityBalancedAllocator::NAME) {
                $groups[$group]['improved'] = $run;
            }
        }
        return array_values($groups);
    }
}
