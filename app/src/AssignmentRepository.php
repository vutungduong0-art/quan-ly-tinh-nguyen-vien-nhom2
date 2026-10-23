<?php

declare(strict_types=1);

final class AssignmentRepository
{
    public function __construct(private readonly mysqli $db)
    {
    }

    public function allActive(?int $eventId = null): array
    {
        $sql = "SELECT a.id, a.shift_id, a.volunteer_id, a.status, a.version, a.assigned_at,
                       s.name AS shift_name, s.start_at, s.end_at, s.required_count,
                       e.id AS event_id, e.name AS event_name, u.full_name AS volunteer_name,
                       COALESCE(vp.volunteer_code, u.username) AS volunteer_code, vp.phone, vp.email,
                       (SELECT GROUP_CONCAT(sk.name ORDER BY sk.name SEPARATOR ', ')
                        FROM shift_skills ss JOIN skills sk ON sk.id = ss.skill_id
                        WHERE ss.shift_id = s.id) AS required_skills
                FROM assignments a JOIN shifts s ON s.id = a.shift_id JOIN events e ON e.id = s.event_id
                JOIN users u ON u.id = a.volunteer_id
                LEFT JOIN volunteer_profiles vp ON vp.user_id = u.id
                WHERE a.status IN ('assigned','confirmed')";
        if ($eventId !== null) {
            $sql .= ' AND e.id = ?';
        }
        $sql .= ' ORDER BY s.start_at, s.id, u.full_name';
        $statement = $this->db->prepare($sql);
        if ($eventId !== null) {
            $statement->bind_param('i', $eventId);
        }
        $statement->execute();
        return $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function mine(int $volunteerId): array
    {
        $statement = $this->db->prepare(
            "SELECT a.id, a.status, a.version, s.name AS shift_name, s.start_at, s.end_at,
                    e.name AS event_name, e.location
             FROM assignments a JOIN shifts s ON s.id = a.shift_id JOIN events e ON e.id = s.event_id
             WHERE a.volunteer_id = ? AND a.status IN ('assigned','confirmed') ORDER BY s.start_at, a.id"
        );
        $statement->bind_param('i', $volunteerId);
        $statement->execute();
        return $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function mineHistory(int $volunteerId): array
    {
        $statement = $this->db->prepare(
            "SELECT a.id, a.status, a.updated_at, s.name AS shift_name, s.start_at, s.end_at,
                    e.name AS event_name, e.location
             FROM assignments a JOIN shifts s ON s.id = a.shift_id JOIN events e ON e.id = s.event_id
             WHERE a.volunteer_id = ? AND a.status IN ('absent','cancelled','replaced')
             ORDER BY a.updated_at DESC, a.id DESC LIMIT 100"
        );
        $statement->bind_param('i', $volunteerId);
        $statement->execute();
        return $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function history(?int $eventId = null): array
    {
        $sql = "SELECT a.id, a.status, a.updated_at, s.name AS shift_name, s.start_at,
                       e.id AS event_id, e.name AS event_name, u.full_name AS volunteer_name
                FROM assignments a JOIN shifts s ON s.id = a.shift_id JOIN events e ON e.id = s.event_id
                JOIN users u ON u.id = a.volunteer_id
                WHERE a.status IN ('absent','cancelled','replaced')";
        if ($eventId !== null) {
            $sql .= ' AND e.id = ?';
        }
        $sql .= ' ORDER BY a.updated_at DESC, a.id DESC LIMIT 200';
        $statement = $this->db->prepare($sql);
        if ($eventId !== null) {
            $statement->bind_param('i', $eventId);
        }
        $statement->execute();
        return $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function confirm(int $assignmentId, int $volunteerId, int $version): void
    {
        $statement = $this->db->prepare(
            "UPDATE assignments SET status = 'confirmed', version = version + 1
             WHERE id = ? AND volunteer_id = ? AND version = ? AND status = 'assigned'"
        );
        $statement->bind_param('iii', $assignmentId, $volunteerId, $version);
        $statement->execute();
        if ($statement->affected_rows !== 1) {
            throw new DomainException('Ca đã thay đổi, không thuộc tài khoản này hoặc đã được xác nhận.');
        }
    }

    public function markAbsent(int $assignmentId, int $actorId, int $version, string $reason, ?int $requiredOwnerId = null): void
    {
        $this->db->begin_transaction();
        try {
            $this->db->query('SELECT schedule_revision FROM schedule_guard WHERE id = 1 FOR UPDATE')->fetch_assoc();
            if ($requiredOwnerId !== null) {
                $owner = $this->db->prepare('SELECT volunteer_id FROM assignments WHERE id = ? FOR UPDATE');
                $owner->bind_param('i', $assignmentId);
                $owner->execute();
                $ownerRow = $owner->get_result()->fetch_assoc();
                if (!$ownerRow || (int) $ownerRow['volunteer_id'] !== $requiredOwnerId) {
                    throw new DomainException('Bạn chỉ được báo vắng cho ca của chính mình.');
                }
            }
            $update = $this->db->prepare(
                "UPDATE assignments SET status = 'absent', version = version + 1
                 WHERE id = ? AND version = ? AND status IN ('assigned','confirmed')"
            );
            $update->bind_param('ii', $assignmentId, $version);
            $update->execute();
            if ($update->affected_rows !== 1) {
                throw new DomainException('Phân công đã thay đổi; hãy tải lại trang.');
            }
            $request = $this->db->prepare('INSERT INTO replacement_requests (assignment_id, reason) VALUES (?, ?)');
            $request->bind_param('is', $assignmentId, $reason);
            $request->execute();
            $this->db->query('UPDATE schedule_guard SET schedule_revision = schedule_revision + 1 WHERE id = 1');
            $this->audit($actorId, 'assignment.absent', $assignmentId, ['reason' => $reason]);
            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        }
    }

    public function manualAssign(int $shiftId, int $volunteerId, int $actorId): void
    {
        $this->db->begin_transaction();
        try {
            $this->db->query('SELECT schedule_revision FROM schedule_guard WHERE id = 1 FOR UPDATE')->fetch_assoc();
            $eventStatement = $this->db->prepare('SELECT event_id FROM shifts WHERE id = ? FOR UPDATE');
            $eventStatement->bind_param('i', $shiftId);
            $eventStatement->execute();
            $row = $eventStatement->get_result()->fetch_assoc();
            if (!$row) {
                throw new DomainException('Ca không tồn tại.');
            }
            $snapshot = (new SnapshotBuilder($this->db))->forEvent((int) $row['event_id']);
            $volunteers = Snapshot::volunteersById($snapshot);
            $shifts = Snapshot::shiftsById($snapshot);
            if (!isset($volunteers[$volunteerId])) {
                throw new DomainException('Tình nguyện viên chưa đăng ký hoạt động.');
            }
            $result = (new EligibilityChecker())->check($volunteers[$volunteerId], $shifts[$shiftId], $snapshot['fixed_assignments']);
            if (!$result['eligible']) {
                throw new DomainException(implode('; ', $result['reasons']));
            }
            $count = 0;
            foreach ($snapshot['fixed_assignments'] as $assignment) {
                $count += (int) $assignment['shift_id'] === $shiftId ? 1 : 0;
            }
            if ($count >= (int) $shifts[$shiftId]['required_count']) {
                throw new DomainException('Ca đã đủ số người cần.');
            }
            $insert = $this->db->prepare(
                "INSERT INTO assignments (shift_id, volunteer_id, status, assigned_by, assigned_at)
                 VALUES (?, ?, 'assigned', ?, UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE status = 'assigned', assigned_by = VALUES(assigned_by), assigned_at = UTC_TIMESTAMP(), version = version + 1"
            );
            $insert->bind_param('iii', $shiftId, $volunteerId, $actorId);
            $insert->execute();
            $assignmentId = (int) $this->db->insert_id;
            if ($assignmentId === 0) {
                $lookup = $this->db->prepare('SELECT id FROM assignments WHERE shift_id = ? AND volunteer_id = ?');
                $lookup->bind_param('ii', $shiftId, $volunteerId);
                $lookup->execute();
                $assignmentId = (int) $lookup->get_result()->fetch_assoc()['id'];
            }
            $this->db->query('UPDATE schedule_guard SET schedule_revision = schedule_revision + 1 WHERE id = 1');
            $this->audit($actorId, 'assignment.manual', $assignmentId, ['shift_id' => $shiftId, 'volunteer_id' => $volunteerId]);
            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        }
    }

    public function cancel(int $assignmentId, int $version, int $actorId): void
    {
        $this->db->begin_transaction();
        try {
            $this->db->query('SELECT schedule_revision FROM schedule_guard WHERE id = 1 FOR UPDATE')->fetch_assoc();
            $update = $this->db->prepare(
                "UPDATE assignments SET status = 'cancelled', version = version + 1
                 WHERE id = ? AND version = ? AND status IN ('assigned','confirmed')"
            );
            $update->bind_param('ii', $assignmentId, $version);
            $update->execute();
            if ($update->affected_rows !== 1) {
                throw new DomainException('Phân công đã thay đổi; hãy tải lại trang.');
            }
            $this->db->query('UPDATE schedule_guard SET schedule_revision = schedule_revision + 1 WHERE id = 1');
            $this->audit($actorId, 'assignment.cancelled', $assignmentId, []);
            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        }
    }

    public function transfer(int $assignmentId, int $replacementVolunteerId, int $version, int $actorId): void
    {
        $this->db->begin_transaction();
        try {
            $this->db->query('SELECT schedule_revision FROM schedule_guard WHERE id = 1 FOR UPDATE')->fetch_assoc();
            $source = $this->db->prepare(
                "SELECT a.id, a.shift_id, a.volunteer_id, s.event_id
                 FROM assignments a JOIN shifts s ON s.id = a.shift_id
                 WHERE a.id = ? AND a.version = ? AND a.status IN ('assigned','confirmed') FOR UPDATE"
            );
            $source->bind_param('ii', $assignmentId, $version);
            $source->execute();
            $row = $source->get_result()->fetch_assoc();
            if (!$row) {
                throw new DomainException('Phân công đã thay đổi; hãy tải lại trang.');
            }
            if ((int) $row['volunteer_id'] === $replacementVolunteerId) {
                throw new DomainException('Người nhận mới phải khác người đang được phân công.');
            }
            $snapshot = (new SnapshotBuilder($this->db))->forEvent((int) $row['event_id']);
            $volunteers = Snapshot::volunteersById($snapshot);
            $shifts = Snapshot::shiftsById($snapshot);
            if (!isset($volunteers[$replacementVolunteerId])) {
                throw new DomainException('Người nhận mới chưa đăng ký hoạt động.');
            }
            $eligibility = (new EligibilityChecker())->check(
                $volunteers[$replacementVolunteerId],
                $shifts[(int) $row['shift_id']],
                $snapshot['fixed_assignments']
            );
            if (!$eligibility['eligible']) {
                throw new DomainException('Không thể chuyển: ' . implode('; ', $eligibility['reasons']));
            }

            $cancel = $this->db->prepare("UPDATE assignments SET status = 'cancelled', version = version + 1 WHERE id = ? AND version = ?");
            $cancel->bind_param('ii', $assignmentId, $version);
            $cancel->execute();
            if ($cancel->affected_rows !== 1) {
                throw new DomainException('Phân công nguồn đã thay đổi; giao dịch được hủy.');
            }
            $shiftId = (int) $row['shift_id'];
            $insert = $this->db->prepare(
                "INSERT INTO assignments (shift_id, volunteer_id, status, assigned_by, assigned_at)
                 VALUES (?, ?, 'assigned', ?, UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE status = 'assigned', assigned_by = VALUES(assigned_by),
                    assigned_at = UTC_TIMESTAMP(), version = version + 1"
            );
            $insert->bind_param('iii', $shiftId, $replacementVolunteerId, $actorId);
            $insert->execute();
            $target = $this->db->prepare('SELECT id, status FROM assignments WHERE shift_id = ? AND volunteer_id = ?');
            $target->bind_param('ii', $shiftId, $replacementVolunteerId);
            $target->execute();
            $targetRow = $target->get_result()->fetch_assoc();
            if (!$targetRow || $targetRow['status'] !== 'assigned') {
                throw new DomainException('Không thể kích hoạt phân công mới; giao dịch được hủy.');
            }
            $this->db->query('UPDATE schedule_guard SET schedule_revision = schedule_revision + 1 WHERE id = 1');
            $this->audit($actorId, 'assignment.transferred', $assignmentId, [
                'from_volunteer_id' => (int) $row['volunteer_id'],
                'to_volunteer_id' => $replacementVolunteerId,
                'replacement_assignment_id' => (int) $targetRow['id'],
            ]);
            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        }
    }

    public function openReplacementRequests(?int $eventId = null): array
    {
        $sql = "SELECT rr.id, rr.assignment_id, rr.reason, rr.created_at, a.shift_id, a.volunteer_id AS absent_volunteer_id,
                       u.full_name AS absent_volunteer_name, s.name AS shift_name, s.start_at, s.end_at,
                       e.id AS event_id, e.name AS event_name
                FROM replacement_requests rr JOIN assignments a ON a.id = rr.assignment_id
                JOIN users u ON u.id = a.volunteer_id JOIN shifts s ON s.id = a.shift_id JOIN events e ON e.id = s.event_id
                WHERE rr.status = 'open'";
        if ($eventId !== null) {
            $sql .= ' AND e.id = ?';
        }
        $sql .= ' ORDER BY rr.created_at, rr.id';
        $statement = $this->db->prepare($sql);
        if ($eventId !== null) {
            $statement->bind_param('i', $eventId);
        }
        $statement->execute();
        return $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function replacementHistory(?int $eventId = null): array
    {
        $sql = "SELECT rr.id, rr.status, rr.reason, rr.resolution_note, rr.created_at, rr.resolved_at,
                       absent.full_name AS absent_volunteer_name, replacement.full_name AS replacement_volunteer_name,
                       processor.full_name AS processor_name, s.name AS shift_name, e.id AS event_id, e.name AS event_name
                FROM replacement_requests rr JOIN assignments a ON a.id = rr.assignment_id
                JOIN users absent ON absent.id = a.volunteer_id JOIN shifts s ON s.id = a.shift_id
                JOIN events e ON e.id = s.event_id
                LEFT JOIN assignments ra ON ra.id = rr.replacement_assignment_id
                LEFT JOIN users replacement ON replacement.id = ra.volunteer_id
                LEFT JOIN users processor ON processor.id = rr.processed_by
                WHERE rr.status <> 'open'";
        if ($eventId !== null) {
            $sql .= ' AND e.id = ?';
        }
        $sql .= ' ORDER BY rr.resolved_at DESC, rr.id DESC LIMIT 200';
        $statement = $this->db->prepare($sql);
        if ($eventId !== null) {
            $statement->bind_param('i', $eventId);
        }
        $statement->execute();
        return $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function markReplacementUnfilled(int $requestId, string $note, int $actorId): void
    {
        $this->db->begin_transaction();
        try {
            $this->db->query('SELECT schedule_revision FROM schedule_guard WHERE id = 1 FOR UPDATE')->fetch_assoc();
            $request = $this->db->prepare(
                "SELECT rr.id, rr.assignment_id FROM replacement_requests rr
                 JOIN assignments a ON a.id = rr.assignment_id
                 WHERE rr.id = ? AND rr.status = 'open' AND a.status = 'absent' FOR UPDATE"
            );
            $request->bind_param('i', $requestId);
            $request->execute();
            $row = $request->get_result()->fetch_assoc();
            if (!$row) {
                throw new DomainException('Yêu cầu không tồn tại, đã xử lý hoặc phân công gốc không còn vắng.');
            }
            $update = $this->db->prepare(
                "UPDATE replacement_requests SET status = 'unfilled', resolution_note = ?, processed_by = ?, resolved_at = UTC_TIMESTAMP()
                 WHERE id = ? AND status = 'open'"
            );
            $update->bind_param('sii', $note, $actorId, $requestId);
            $update->execute();
            if ($update->affected_rows !== 1) {
                throw new DomainException('Yêu cầu vừa được xử lý bởi người khác.');
            }
            $this->db->query('UPDATE schedule_guard SET schedule_revision = schedule_revision + 1 WHERE id = 1');
            $this->audit($actorId, 'replacement.unfilled', (int) $row['assignment_id'], ['request_id' => $requestId, 'note' => $note]);
            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        }
    }

    public function reopenReplacement(int $requestId, int $actorId): void
    {
        $this->db->begin_transaction();
        try {
            $this->db->query('SELECT schedule_revision FROM schedule_guard WHERE id = 1 FOR UPDATE')->fetch_assoc();
            $update = $this->db->prepare(
                "UPDATE replacement_requests rr JOIN assignments a ON a.id = rr.assignment_id
                 JOIN shifts s ON s.id = a.shift_id JOIN events e ON e.id = s.event_id
                 SET rr.status = 'open', rr.resolution_note = NULL, rr.processed_by = NULL, rr.resolved_at = NULL
                 WHERE rr.id = ? AND rr.status = 'unfilled' AND a.status = 'absent' AND e.status = 'published'"
            );
            $update->bind_param('i', $requestId);
            $update->execute();
            if ($update->affected_rows !== 1) {
                throw new DomainException('Chỉ có thể mở lại yêu cầu chưa tìm được người khi phân công gốc vẫn vắng.');
            }
            $lookup = $this->db->prepare('SELECT assignment_id FROM replacement_requests WHERE id = ?');
            $lookup->bind_param('i', $requestId);
            $lookup->execute();
            $assignmentId = (int) $lookup->get_result()->fetch_assoc()['assignment_id'];
            $this->db->query('UPDATE schedule_guard SET schedule_revision = schedule_revision + 1 WHERE id = 1');
            $this->audit($actorId, 'replacement.reopened', $assignmentId, ['request_id' => $requestId]);
            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        }
    }

    public function replaceAbsent(int $requestId, int $replacementVolunteerId, int $actorId): void
    {
        $this->db->begin_transaction();
        try {
            $this->db->query('SELECT schedule_revision FROM schedule_guard WHERE id = 1 FOR UPDATE')->fetch_assoc();
            $request = $this->db->prepare(
                "SELECT rr.id, rr.assignment_id, a.shift_id, a.volunteer_id AS absent_volunteer_id, s.event_id
                 FROM replacement_requests rr JOIN assignments a ON a.id = rr.assignment_id JOIN shifts s ON s.id = a.shift_id
                 WHERE rr.id = ? AND rr.status = 'open' FOR UPDATE"
            );
            $request->bind_param('i', $requestId);
            $request->execute();
            $row = $request->get_result()->fetch_assoc();
            if (!$row) {
                throw new DomainException('Yêu cầu thay người không tồn tại hoặc đã xử lý.');
            }
            $eventId = (int) $row['event_id'];
            $shiftId = (int) $row['shift_id'];
            if ($replacementVolunteerId === (int) $row['absent_volunteer_id']) {
                throw new DomainException('Người vắng không thể tự thay cho chính mình.');
            }
            $snapshot = (new SnapshotBuilder($this->db))->forEvent($eventId);
            $volunteers = Snapshot::volunteersById($snapshot);
            $shifts = Snapshot::shiftsById($snapshot);
            if (!isset($volunteers[$replacementVolunteerId])) {
                throw new DomainException('Người thay chưa đăng ký hoạt động.');
            }
            $eligibility = (new EligibilityChecker())->check($volunteers[$replacementVolunteerId], $shifts[$shiftId], $snapshot['fixed_assignments']);
            if (!$eligibility['eligible']) {
                throw new DomainException('Người thay không hợp lệ: ' . implode('; ', $eligibility['reasons']));
            }
            $currentCount = count(array_filter(
                $snapshot['fixed_assignments'],
                static fn (array $assignment): bool => (int) $assignment['shift_id'] === $shiftId
            ));
            if ($currentCount >= (int) $shifts[$shiftId]['required_count']) {
                throw new DomainException('Ca đã đủ người; yêu cầu thay được giữ lại để điều phối xem xét.');
            }
            $insert = $this->db->prepare(
                "INSERT INTO assignments (shift_id, volunteer_id, status, assigned_by, assigned_at)
                 VALUES (?, ?, 'assigned', ?, UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE status = 'assigned', assigned_by = VALUES(assigned_by), assigned_at = UTC_TIMESTAMP(), version = version + 1"
            );
            $insert->bind_param('iii', $shiftId, $replacementVolunteerId, $actorId);
            $insert->execute();
            $replacementAssignmentId = (int) $this->db->insert_id;
            if ($replacementAssignmentId === 0) {
                $lookup = $this->db->prepare('SELECT id FROM assignments WHERE shift_id = ? AND volunteer_id = ?');
                $lookup->bind_param('ii', $shiftId, $replacementVolunteerId);
                $lookup->execute();
                $replacementAssignmentId = (int) $lookup->get_result()->fetch_assoc()['id'];
            }
            $oldAssignmentId = (int) $row['assignment_id'];
            $old = $this->db->prepare("UPDATE assignments SET status = 'replaced', version = version + 1 WHERE id = ? AND status = 'absent'");
            $old->bind_param('i', $oldAssignmentId);
            $old->execute();
            if ($old->affected_rows !== 1) {
                throw new DomainException('Phân công gốc không còn ở trạng thái vắng; giao dịch đã hủy.');
            }
            $resolved = $this->db->prepare(
                "UPDATE replacement_requests SET status = 'resolved', replacement_assignment_id = ?, processed_by = ?, resolved_at = UTC_TIMESTAMP() WHERE id = ?"
            );
            $resolved->bind_param('iii', $replacementAssignmentId, $actorId, $requestId);
            $resolved->execute();
            $this->db->query('UPDATE schedule_guard SET schedule_revision = schedule_revision + 1 WHERE id = 1');
            $this->audit($actorId, 'assignment.replaced', $oldAssignmentId, ['replacement_assignment_id' => $replacementAssignmentId]);
            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        }
    }

    private function audit(int $actorId, string $action, int $entityId, array $after): void
    {
        $afterJson = json_encode($after, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $statement = $this->db->prepare("INSERT INTO audit_logs (actor_id, action, entity_type, entity_id, after_json) VALUES (?, ?, 'assignment', ?, ?)");
        $statement->bind_param('isis', $actorId, $action, $entityId, $afterJson);
        $statement->execute();
    }
}
