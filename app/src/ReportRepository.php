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
}
