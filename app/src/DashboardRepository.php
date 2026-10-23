<?php

declare(strict_types=1);

final class DashboardRepository
{
    public function __construct(private readonly mysqli $db)
    {
    }

    public function counts(): array
    {
        $queries = [
            'events' => "SELECT COUNT(*) AS total FROM events WHERE status = 'published'",
            'volunteers' => "SELECT COUNT(*) AS total FROM users WHERE role = 'volunteer' AND active = 1",
            'shifts' => "SELECT COUNT(*) AS total FROM shifts s JOIN events e ON e.id = s.event_id WHERE e.status = 'published'",
            'registrations' => "SELECT COUNT(*) AS total FROM registrations WHERE status = 'registered'",
            'full_shifts' => "SELECT COUNT(*) AS total FROM shifts s JOIN events e ON e.id = s.event_id
                WHERE e.status = 'published' AND (SELECT COUNT(*) FROM assignments a WHERE a.shift_id = s.id AND a.status IN ('assigned','confirmed')) >= s.required_count",
            'short_shifts' => "SELECT COUNT(*) AS total FROM shifts s JOIN events e ON e.id = s.event_id
                WHERE e.status = 'published' AND (SELECT COUNT(*) FROM assignments a WHERE a.shift_id = s.id AND a.status IN ('assigned','confirmed')) < s.required_count",
            'missing_positions' => "SELECT COALESCE(SUM(GREATEST(s.required_count - (SELECT COUNT(*) FROM assignments a WHERE a.shift_id = s.id AND a.status IN ('assigned','confirmed')), 0)), 0) AS total
                FROM shifts s JOIN events e ON e.id = s.event_id WHERE e.status = 'published'",
            'open_replacements' => "SELECT COUNT(*) AS total FROM replacement_requests WHERE status = 'open'",
        ];
        $counts = [];
        foreach ($queries as $key => $sql) {
            $counts[$key] = (int) $this->db->query($sql)->fetch_assoc()['total'];
        }
        return $counts;
    }
}
