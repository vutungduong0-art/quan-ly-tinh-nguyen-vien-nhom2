<?php

declare(strict_types=1);

final class EventRepository
{
    public function __construct(private readonly mysqli $db)
    {
    }

    public function upcoming(int $limit = 20): array
    {
        $statement = $this->db->prepare(
            "SELECT e.*,
                    (SELECT COUNT(*) FROM shifts s WHERE s.event_id = e.id) AS shift_count,
                    (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.status = 'registered') AS registration_count
             FROM events e
             WHERE e.status = 'published' AND e.end_at >= UTC_TIMESTAMP()
             ORDER BY e.start_at, e.id
             LIMIT ?"
        );
        $statement->bind_param('i', $limit);
        $statement->execute();
        return $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function all(int $limit = 200): array
    {
        $statement = $this->db->prepare(
            "SELECT e.*,
                    (SELECT COUNT(*) FROM shifts s WHERE s.event_id = e.id) AS shift_count,
                    (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.status = 'registered') AS registration_count
             FROM events e WHERE e.status = 'published' ORDER BY e.start_at DESC, e.id DESC LIMIT ?"
        );
        $statement->bind_param('i', $limit);
        $statement->execute();
        return $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function find(int $id): ?array
    {
        $statement = $this->db->prepare('SELECT * FROM events WHERE id = ? LIMIT 1');
        $statement->bind_param('i', $id);
        $statement->execute();
        $event = $statement->get_result()->fetch_assoc();
        return $event ?: null;
    }

    public function create(array $data, int $createdBy): int
    {
        $statement = $this->db->prepare(
            "INSERT INTO events (name, location, start_at, end_at, description, status, created_by)
             VALUES (?, ?, ?, ?, ?, 'published', ?)"
        );
        $statement->bind_param(
            'sssssi',
            $data['name'],
            $data['location'],
            $data['start_at'],
            $data['end_at'],
            $data['description'],
            $createdBy
        );
        $statement->execute();
        return (int) $this->db->insert_id;
    }

    public function updateEvent(int $eventId, array $data, int $actorId): void
    {
        $this->db->begin_transaction();
        try {
            $this->lockGuard();
            $select = $this->db->prepare('SELECT * FROM events WHERE id = ? FOR UPDATE');
            $select->bind_param('i', $eventId);
            $select->execute();
            $before = $select->get_result()->fetch_assoc();
            if (!$before) {
                throw new DomainException('Hoạt động không tồn tại.');
            }
            if ($before['status'] === 'closed') {
                throw new DomainException('Hoạt động đã đóng, không thể thay đổi lịch.');
            }
            $outside = $this->db->prepare(
                'SELECT name FROM shifts WHERE event_id = ? AND (start_at < ? OR end_at > ?) ORDER BY id LIMIT 1'
            );
            $outside->bind_param('iss', $eventId, $data['start_at'], $data['end_at']);
            $outside->execute();
            $invalidShift = $outside->get_result()->fetch_assoc();
            if ($invalidShift) {
                throw new DomainException('Không thể thu hẹp thời gian: ca "' . $invalidShift['name'] . '" sẽ nằm ngoài hoạt động.');
            }
            $update = $this->db->prepare(
                'UPDATE events SET name = ?, location = ?, start_at = ?, end_at = ?, description = ? WHERE id = ?'
            );
            $update->bind_param(
                'sssssi',
                $data['name'],
                $data['location'],
                $data['start_at'],
                $data['end_at'],
                $data['description'],
                $eventId
            );
            $update->execute();
            $after = array_merge($before, $data);
            $this->audit($actorId, 'event.update', 'event', $eventId, $before, $after);
            $this->bumpGuard();
            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        }
    }

    public function closeEvent(int $eventId, int $actorId): void
    {
        $this->db->begin_transaction();
        try {
            $this->lockGuard();
            $select = $this->db->prepare('SELECT * FROM events WHERE id = ? FOR UPDATE');
            $select->bind_param('i', $eventId);
            $select->execute();
            $before = $select->get_result()->fetch_assoc();
            if (!$before) {
                throw new DomainException('Hoạt động không tồn tại.');
            }
            if ($before['status'] === 'closed') {
                throw new DomainException('Hoạt động đã được đóng trước đó.');
            }
            $active = $this->db->prepare(
                "SELECT
                    (SELECT COUNT(*) FROM assignments a JOIN shifts s ON s.id = a.shift_id
                     WHERE s.event_id = ? AND a.status IN ('assigned','confirmed')) +
                    (SELECT COUNT(*) FROM replacement_requests rr JOIN assignments a ON a.id = rr.assignment_id
                     JOIN shifts s ON s.id = a.shift_id WHERE s.event_id = ? AND rr.status = 'open') AS total"
            );
            $active->bind_param('ii', $eventId, $eventId);
            $active->execute();
            if ((int) $active->get_result()->fetch_assoc()['total'] > 0) {
                throw new DomainException('Phải gỡ hoặc xử lý hết phân công đang hiệu lực trước khi đóng hoạt động.');
            }
            $update = $this->db->prepare("UPDATE events SET status = 'closed' WHERE id = ?");
            $update->bind_param('i', $eventId);
            $update->execute();
            $supersede = $this->db->prepare("UPDATE allocation_runs SET status = 'superseded' WHERE event_id = ? AND status = 'draft'");
            $supersede->bind_param('i', $eventId);
            $supersede->execute();
            $after = $before;
            $after['status'] = 'closed';
            $this->audit($actorId, 'event.close', 'event', $eventId, $before, $after);
            $this->bumpGuard();
            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        }
    }

    public function shifts(int $eventId): array
    {
        $statement = $this->db->prepare(
            "SELECT s.*,
                    GROUP_CONCAT(sk.name ORDER BY sk.name SEPARATOR ', ') AS skill_names,
                    GROUP_CONCAT(sk.id ORDER BY sk.id SEPARATOR ',') AS skill_ids_csv
             FROM shifts s
             LEFT JOIN shift_skills ss ON ss.shift_id = s.id
             LEFT JOIN skills sk ON sk.id = ss.skill_id
             WHERE s.event_id = ?
             GROUP BY s.id
             ORDER BY s.start_at, s.id"
        );
        $statement->bind_param('i', $eventId);
        $statement->execute();
        return $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function skills(): array
    {
        return $this->db->query('SELECT id, code, name FROM skills ORDER BY name')->fetch_all(MYSQLI_ASSOC);
    }

    public function createShift(int $eventId, array $data, array $skillIds): int
    {
        $this->db->begin_transaction();
        try {
            $this->db->query('SELECT schedule_revision FROM schedule_guard WHERE id = 1 FOR UPDATE')->fetch_assoc();
            $lock = $this->db->prepare('SELECT id, start_at, end_at, status FROM events WHERE id = ? FOR UPDATE');
            $lock->bind_param('i', $eventId);
            $lock->execute();
            $event = $lock->get_result()->fetch_assoc();
            if (!$event) {
                throw new DomainException('Hoạt động không tồn tại.');
            }
            if ($event['status'] !== 'published') {
                throw new DomainException('Không thể thêm ca vào hoạt động đã đóng.');
            }
            if ($data['start_at'] < $event['start_at'] || $data['end_at'] > $event['end_at']) {
                throw new DomainException('Thời gian ca phải nằm trong thời gian diễn ra hoạt động.');
            }

            $statement = $this->db->prepare(
                'INSERT INTO shifts (event_id, name, start_at, end_at, required_count) VALUES (?, ?, ?, ?, ?)'
            );
            $statement->bind_param(
                'isssi',
                $eventId,
                $data['name'],
                $data['start_at'],
                $data['end_at'],
                $data['required_count']
            );
            $statement->execute();
            $shiftId = (int) $this->db->insert_id;

            if ($skillIds !== []) {
                $skillCheck = $this->db->prepare('SELECT id FROM skills WHERE id = ?');
                $skillInsert = $this->db->prepare('INSERT INTO shift_skills (shift_id, skill_id) VALUES (?, ?)');
                foreach ($skillIds as $skillId) {
                    $skillCheck->bind_param('i', $skillId);
                    $skillCheck->execute();
                    if (!$skillCheck->get_result()->fetch_assoc()) {
                        throw new DomainException('Có kỹ năng được chọn không tồn tại.');
                    }
                    $skillInsert->bind_param('ii', $shiftId, $skillId);
                    $skillInsert->execute();
                }
            }

            $this->db->query('UPDATE schedule_guard SET schedule_revision = schedule_revision + 1 WHERE id = 1');

            $this->db->commit();
            return $shiftId;
        } catch (Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        }
    }

    public function updateShift(int $eventId, int $shiftId, array $data, array $skillIds, int $actorId): void
    {
        sort($skillIds, SORT_NUMERIC);
        $this->db->begin_transaction();
        try {
            $this->lockGuard();
            $eventStatement = $this->db->prepare('SELECT id, start_at, end_at, status FROM events WHERE id = ? FOR UPDATE');
            $eventStatement->bind_param('i', $eventId);
            $eventStatement->execute();
            $event = $eventStatement->get_result()->fetch_assoc();
            if (!$event || $event['status'] !== 'published') {
                throw new DomainException('Hoạt động không tồn tại hoặc đã đóng.');
            }
            if ($data['start_at'] < $event['start_at'] || $data['end_at'] > $event['end_at']) {
                throw new DomainException('Thời gian ca phải nằm trong thời gian diễn ra hoạt động.');
            }
            $shiftStatement = $this->db->prepare('SELECT * FROM shifts WHERE id = ? AND event_id = ? FOR UPDATE');
            $shiftStatement->bind_param('ii', $shiftId, $eventId);
            $shiftStatement->execute();
            $before = $shiftStatement->get_result()->fetch_assoc();
            if (!$before) {
                throw new DomainException('Ca không tồn tại trong hoạt động này.');
            }
            $oldSkillIds = $this->skillIdsForShift($shiftId);
            sort($oldSkillIds, SORT_NUMERIC);
            $scheduleChanged = $before['start_at'] !== $data['start_at']
                || $before['end_at'] !== $data['end_at']
                || (int) $before['required_count'] !== (int) $data['required_count']
                || $oldSkillIds !== $skillIds;
            if ($scheduleChanged) {
                $active = $this->db->prepare(
                    "SELECT COUNT(*) AS total FROM assignments WHERE shift_id = ? AND status IN ('assigned','confirmed')"
                );
                $active->bind_param('i', $shiftId);
                $active->execute();
                if ((int) $active->get_result()->fetch_assoc()['total'] > 0) {
                    throw new DomainException('Không thể đổi giờ, nhu cầu hoặc kỹ năng khi ca còn phân công hiệu lực. Hãy gỡ phân công trước.');
                }
            }
            $update = $this->db->prepare(
                'UPDATE shifts SET name = ?, start_at = ?, end_at = ?, required_count = ? WHERE id = ? AND event_id = ?'
            );
            $update->bind_param('sssiii', $data['name'], $data['start_at'], $data['end_at'], $data['required_count'], $shiftId, $eventId);
            $update->execute();
            $deleteSkills = $this->db->prepare('DELETE FROM shift_skills WHERE shift_id = ?');
            $deleteSkills->bind_param('i', $shiftId);
            $deleteSkills->execute();
            $insertSkill = $this->db->prepare('INSERT INTO shift_skills (shift_id, skill_id) SELECT ?, id FROM skills WHERE id = ?');
            foreach ($skillIds as $skillId) {
                $insertSkill->bind_param('ii', $shiftId, $skillId);
                $insertSkill->execute();
                if ($insertSkill->affected_rows !== 1) {
                    throw new DomainException('Có kỹ năng được chọn không tồn tại.');
                }
            }
            $after = array_merge($before, $data, ['skill_ids' => $skillIds]);
            $before['skill_ids'] = $oldSkillIds;
            $this->audit($actorId, 'shift.update', 'shift', $shiftId, $before, $after);
            $this->bumpGuard();
            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        }
    }

    public function deleteShift(int $eventId, int $shiftId, int $actorId): void
    {
        $this->db->begin_transaction();
        try {
            $this->lockGuard();
            $select = $this->db->prepare('SELECT * FROM shifts WHERE id = ? AND event_id = ? FOR UPDATE');
            $select->bind_param('ii', $shiftId, $eventId);
            $select->execute();
            $before = $select->get_result()->fetch_assoc();
            if (!$before) {
                throw new DomainException('Ca không tồn tại trong hoạt động này.');
            }
            $used = $this->db->prepare(
                'SELECT (SELECT COUNT(*) FROM assignments WHERE shift_id = ?) +
                        (SELECT COUNT(*) FROM run_assignments WHERE shift_id = ?) AS total'
            );
            $used->bind_param('ii', $shiftId, $shiftId);
            $used->execute();
            if ((int) $used->get_result()->fetch_assoc()['total'] > 0) {
                throw new DomainException('Không thể xóa ca đã có lịch sử phân công hoặc phương án.');
            }
            $delete = $this->db->prepare('DELETE FROM shifts WHERE id = ? AND event_id = ?');
            $delete->bind_param('ii', $shiftId, $eventId);
            $delete->execute();
            $this->audit($actorId, 'shift.delete', 'shift', $shiftId, $before, null);
            $this->bumpGuard();
            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        }
    }

    public function register(int $eventId, int $volunteerId): void
    {
        $event = $this->find($eventId);
        if ($event === null || $event['status'] !== 'published' || $event['end_at'] < gmdate('Y-m-d H:i:s')) {
            throw new DomainException('Hoạt động không tồn tại, đã kết thúc hoặc đã đóng.');
        }

        $this->db->begin_transaction();
        try {
            $this->db->query('SELECT schedule_revision FROM schedule_guard WHERE id = 1 FOR UPDATE')->fetch_assoc();
            $existing = $this->db->prepare('SELECT id, status FROM registrations WHERE event_id = ? AND volunteer_id = ? FOR UPDATE');
            $existing->bind_param('ii', $eventId, $volunteerId);
            $existing->execute();
            $registration = $existing->get_result()->fetch_assoc();
            $action = 'registered';
            if ($registration && $registration['status'] === 'registered') {
                throw new DomainException('Bạn đã đăng ký hoạt động này trước đó.');
            }
            if ($registration) {
                $registrationId = (int) $registration['id'];
                $update = $this->db->prepare("UPDATE registrations SET status = 'registered', registered_at = UTC_TIMESTAMP() WHERE id = ?");
                $update->bind_param('i', $registrationId);
                $update->execute();
                $action = 'reregistered';
            } else {
                $statement = $this->db->prepare(
                    "INSERT INTO registrations (event_id, volunteer_id, status, registered_at)
                     VALUES (?, ?, 'registered', UTC_TIMESTAMP())"
                );
                $statement->bind_param('ii', $eventId, $volunteerId);
                $statement->execute();
                $registrationId = (int) $this->db->insert_id;
            }
            $history = $this->db->prepare('INSERT INTO registration_history (registration_id, action, occurred_at, actor_id) VALUES (?, ?, UTC_TIMESTAMP(), ?)');
            $history->bind_param('isi', $registrationId, $action, $volunteerId);
            $history->execute();
            $this->db->query('UPDATE schedule_guard SET schedule_revision = schedule_revision + 1 WHERE id = 1');
            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        }
    }

    public function withdraw(int $eventId, int $volunteerId): void
    {
        $this->db->begin_transaction();
        try {
            $this->db->query('SELECT schedule_revision FROM schedule_guard WHERE id = 1 FOR UPDATE')->fetch_assoc();
            $active = $this->db->prepare(
                "SELECT COUNT(*) AS total FROM assignments a JOIN shifts s ON s.id = a.shift_id
                 WHERE s.event_id = ? AND a.volunteer_id = ? AND a.status IN ('assigned','confirmed')"
            );
            $active->bind_param('ii', $eventId, $volunteerId);
            $active->execute();
            if ((int) $active->get_result()->fetch_assoc()['total'] > 0) {
                throw new DomainException('Không thể rút đăng ký khi đang có ca được phân công. Hãy liên hệ điều phối.');
            }
            $select = $this->db->prepare("SELECT id FROM registrations WHERE event_id = ? AND volunteer_id = ? AND status = 'registered' FOR UPDATE");
            $select->bind_param('ii', $eventId, $volunteerId);
            $select->execute();
            $row = $select->get_result()->fetch_assoc();
            if (!$row) {
                throw new DomainException('Không có đăng ký đang hoạt động để rút.');
            }
            $registrationId = (int) $row['id'];
            $update = $this->db->prepare("UPDATE registrations SET status = 'withdrawn' WHERE id = ?");
            $update->bind_param('i', $registrationId);
            $update->execute();
            $history = $this->db->prepare("INSERT INTO registration_history (registration_id, action, occurred_at, actor_id) VALUES (?, 'withdrawn', UTC_TIMESTAMP(), ?)");
            $history->bind_param('ii', $registrationId, $volunteerId);
            $history->execute();
            $this->db->query('UPDATE schedule_guard SET schedule_revision = schedule_revision + 1 WHERE id = 1');
            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        }
    }

    public function registrants(int $eventId): array
    {
        $statement = $this->db->prepare(
            "SELECT u.id, u.username, u.full_name, r.registered_at
             FROM registrations r
             JOIN users u ON u.id = r.volunteer_id
             WHERE r.event_id = ? AND r.status = 'registered'
             ORDER BY r.registered_at, u.id"
        );
        $statement->bind_param('i', $eventId);
        $statement->execute();
        return $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function isRegistered(int $eventId, int $volunteerId): bool
    {
        $statement = $this->db->prepare(
            "SELECT 1 FROM registrations WHERE event_id = ? AND volunteer_id = ? AND status = 'registered' LIMIT 1"
        );
        $statement->bind_param('ii', $eventId, $volunteerId);
        $statement->execute();
        return (bool) $statement->get_result()->fetch_row();
    }

    private function skillIdsForShift(int $shiftId): array
    {
        $statement = $this->db->prepare('SELECT skill_id FROM shift_skills WHERE shift_id = ? ORDER BY skill_id');
        $statement->bind_param('i', $shiftId);
        $statement->execute();
        return array_map('intval', array_column($statement->get_result()->fetch_all(MYSQLI_ASSOC), 'skill_id'));
    }

    private function lockGuard(): void
    {
        $this->db->query('SELECT schedule_revision FROM schedule_guard WHERE id = 1 FOR UPDATE')->fetch_assoc();
    }

    private function bumpGuard(): void
    {
        $this->db->query('UPDATE schedule_guard SET schedule_revision = schedule_revision + 1 WHERE id = 1');
    }

    private function audit(int $actorId, string $action, string $entityType, int $entityId, ?array $before, ?array $after): void
    {
        $beforeJson = $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $afterJson = $after === null ? null : json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $statement = $this->db->prepare(
            'INSERT INTO audit_logs (actor_id, action, entity_type, entity_id, before_json, after_json) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $statement->bind_param('ississ', $actorId, $action, $entityType, $entityId, $beforeJson, $afterJson);
        $statement->execute();
    }
}
