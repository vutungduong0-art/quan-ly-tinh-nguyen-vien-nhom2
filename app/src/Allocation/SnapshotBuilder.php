<?php

declare(strict_types=1);

final class SnapshotBuilder
{
    public function __construct(private readonly mysqli $db)
    {
    }

    public function forEvent(int $eventId): array
    {
        $eventStatement = $this->db->prepare('SELECT id, name, location, start_at, end_at, status FROM events WHERE id = ? LIMIT 1');
        $eventStatement->bind_param('i', $eventId);
        $eventStatement->execute();
        $event = $eventStatement->get_result()->fetch_assoc();
        if (!$event || $event['status'] !== 'published') {
            throw new DomainException('Hoạt động không tồn tại hoặc đã đóng.');
        }
        unset($event['status']);

        $shiftStatement = $this->db->prepare(
            'SELECT s.id, s.event_id, s.name, s.start_at, s.end_at, s.required_count,
                    GROUP_CONCAT(ss.skill_id ORDER BY ss.skill_id) AS skill_ids
             FROM shifts s LEFT JOIN shift_skills ss ON ss.shift_id = s.id
             WHERE s.event_id = ? GROUP BY s.id ORDER BY s.start_at, s.id'
        );
        $shiftStatement->bind_param('i', $eventId);
        $shiftStatement->execute();
        $shifts = $shiftStatement->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($shifts as &$shift) {
            $shift['id'] = (int) $shift['id'];
            $shift['event_id'] = (int) $shift['event_id'];
            $shift['required_count'] = (int) $shift['required_count'];
            $shift['skill_ids'] = $shift['skill_ids'] === null ? [] : array_map('intval', explode(',', $shift['skill_ids']));
        }
        unset($shift);

        $volunteerStatement = $this->db->prepare(
            "SELECT u.id, u.username AS code, u.full_name AS name, u.active, r.registered_at
             FROM registrations r JOIN users u ON u.id = r.volunteer_id
             WHERE r.event_id = ? AND r.status = 'registered' AND u.role = 'volunteer'
             ORDER BY r.registered_at, u.id"
        );
        $volunteerStatement->bind_param('i', $eventId);
        $volunteerStatement->execute();
        $volunteers = $volunteerStatement->get_result()->fetch_all(MYSQLI_ASSOC);
        $skillQuery = $this->db->prepare('SELECT skill_id FROM volunteer_skills WHERE volunteer_id = ? ORDER BY skill_id');
        $availabilityQuery = $this->db->prepare('SELECT start_at, end_at FROM availability WHERE volunteer_id = ? ORDER BY start_at, id');
        $busyQuery = $this->db->prepare(
            "SELECT a.shift_id, s.start_at, s.end_at FROM assignments a JOIN shifts s ON s.id = a.shift_id
             WHERE a.volunteer_id = ? AND s.event_id <> ? AND a.status IN ('assigned','confirmed') ORDER BY s.start_at, a.id"
        );
        foreach ($volunteers as &$volunteer) {
            $id = (int) $volunteer['id'];
            $volunteer['id'] = $id;
            $volunteer['active'] = (bool) $volunteer['active'];
            $skillQuery->bind_param('i', $id);
            $skillQuery->execute();
            $volunteer['skill_ids'] = array_map('intval', array_column($skillQuery->get_result()->fetch_all(MYSQLI_ASSOC), 'skill_id'));
            $availabilityQuery->bind_param('i', $id);
            $availabilityQuery->execute();
            $volunteer['availability'] = $availabilityQuery->get_result()->fetch_all(MYSQLI_ASSOC);
            $busyQuery->bind_param('ii', $id, $eventId);
            $busyQuery->execute();
            $volunteer['busy_assignments'] = $busyQuery->get_result()->fetch_all(MYSQLI_ASSOC);
            $volunteer['registrations'] = [['event_id' => $eventId, 'status' => 'registered', 'registered_at' => $volunteer['registered_at']]];
            unset($volunteer['registered_at']);
        }
        unset($volunteer);

        $fixed = $this->db->prepare(
            "SELECT a.shift_id, a.volunteer_id, s.start_at, s.end_at, 1 AS fixed
             FROM assignments a JOIN shifts s ON s.id = a.shift_id
             WHERE s.event_id = ? AND a.status IN ('assigned','confirmed') ORDER BY s.start_at, a.id"
        );
        $fixed->bind_param('i', $eventId);
        $fixed->execute();
        $fixedAssignments = $fixed->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($fixedAssignments as &$assignment) {
            $assignment['shift_id'] = (int) $assignment['shift_id'];
            $assignment['volunteer_id'] = (int) $assignment['volunteer_id'];
            $assignment['fixed'] = true;
        }
        unset($assignment);

        $revision = (int) $this->db->query('SELECT schedule_revision FROM schedule_guard WHERE id = 1')->fetch_assoc()['schedule_revision'];
        return [
            'dataset' => [
                'code' => 'LIVE_EVENT_' . $eventId,
                'version' => '1.0.0',
                'timezone' => 'Asia/Ho_Chi_Minh',
                'seed' => null,
                'schedule_revision' => $revision,
            ],
            'events' => [$event],
            'shifts' => $shifts,
            'volunteers' => $volunteers,
            'fixed_assignments' => $fixedAssignments,
        ];
    }
}
