<?php

declare(strict_types=1);

final class VolunteerRepository
{
    public function __construct(private readonly mysqli $db)
    {
    }

    public function all(): array
    {
        return $this->db->query(
            "SELECT u.id, u.username, u.full_name, u.active, vp.volunteer_code, vp.phone, vp.email,
                    GROUP_CONCAT(DISTINCT sk.name ORDER BY sk.name SEPARATOR ', ') AS skill_names,
                    COUNT(DISTINCT CASE WHEN a.status IN ('assigned','confirmed') THEN a.id END) AS active_assignments
             FROM users u
             LEFT JOIN volunteer_profiles vp ON vp.user_id = u.id
             LEFT JOIN volunteer_skills vs ON vs.volunteer_id = u.id
             LEFT JOIN skills sk ON sk.id = vs.skill_id
             LEFT JOIN assignments a ON a.volunteer_id = u.id
             WHERE u.role = 'volunteer'
             GROUP BY u.id, vp.user_id
             ORDER BY COALESCE(vp.volunteer_code, u.username), u.id"
        )->fetch_all(MYSQLI_ASSOC);
    }

    public function profile(int $userId): ?array
    {
        $statement = $this->db->prepare(
            "SELECT u.id, u.username, u.full_name, u.active, vp.volunteer_code, vp.phone, vp.email, vp.notes
             FROM users u LEFT JOIN volunteer_profiles vp ON vp.user_id = u.id
             WHERE u.id = ? AND u.role = 'volunteer' LIMIT 1"
        );
        $statement->bind_param('i', $userId);
        $statement->execute();
        $profile = $statement->get_result()->fetch_assoc();
        if (!$profile) {
            return null;
        }
        $skillStatement = $this->db->prepare('SELECT skill_id FROM volunteer_skills WHERE volunteer_id = ? ORDER BY skill_id');
        $skillStatement->bind_param('i', $userId);
        $skillStatement->execute();
        $profile['skill_ids'] = array_map('intval', array_column($skillStatement->get_result()->fetch_all(MYSQLI_ASSOC), 'skill_id'));
        $availability = $this->db->prepare('SELECT id, start_at, end_at FROM availability WHERE volunteer_id = ? ORDER BY start_at, id');
        $availability->bind_param('i', $userId);
        $availability->execute();
        $profile['availability'] = $availability->get_result()->fetch_all(MYSQLI_ASSOC);
        return $profile;
    }

    public function saveProfile(int $userId, array $data): void
    {
        $this->db->begin_transaction();
        try {
            $user = $this->db->prepare("UPDATE users SET full_name = ? WHERE id = ? AND role = 'volunteer'");
            $user->bind_param('si', $data['full_name'], $userId);
            $user->execute();
            if ($user->affected_rows === 0 && $this->profile($userId) === null) {
                throw new DomainException('Không tìm thấy hồ sơ tình nguyện viên.');
            }
            $profile = $this->db->prepare(
                'INSERT INTO volunteer_profiles (user_id, volunteer_code, phone, email, notes) VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE volunteer_code = VALUES(volunteer_code), phone = VALUES(phone), email = VALUES(email), notes = VALUES(notes)'
            );
            $email = $data['email'] === '' ? null : $data['email'];
            $profile->bind_param('issss', $userId, $data['volunteer_code'], $data['phone'], $email, $data['notes']);
            $profile->execute();
            $this->db->commit();
        } catch (mysqli_sql_exception $exception) {
            $this->db->rollback();
            if ($exception->getCode() === 1062) {
                throw new DomainException('Mã tình nguyện viên hoặc email đã được sử dụng.');
            }
            throw $exception;
        } catch (Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        }
    }

    public function replaceSkills(int $userId, array $skillIds): void
    {
        $this->db->begin_transaction();
        try {
            $this->lockGuard();
            $impact = $this->db->prepare(
                "SELECT COUNT(*) AS total FROM assignments a
                 JOIN shift_skills ss ON ss.shift_id = a.shift_id
                 WHERE a.volunteer_id = ? AND a.status IN ('assigned','confirmed')
                   AND ss.skill_id NOT IN (" . ($skillIds === [] ? '0' : implode(',', array_fill(0, count($skillIds), '?'))) . ')'
            );
            $types = 'i' . str_repeat('i', count($skillIds));
            $params = array_merge([$userId], $skillIds);
            $impact->bind_param($types, ...$params);
            $impact->execute();
            if ((int) $impact->get_result()->fetch_assoc()['total'] > 0) {
                throw new DomainException('Không thể bỏ kỹ năng đang cần cho một ca đã phân công.');
            }
            $delete = $this->db->prepare('DELETE FROM volunteer_skills WHERE volunteer_id = ?');
            $delete->bind_param('i', $userId);
            $delete->execute();
            $insert = $this->db->prepare('INSERT INTO volunteer_skills (volunteer_id, skill_id) VALUES (?, ?)');
            foreach ($skillIds as $skillId) {
                $insert->bind_param('ii', $userId, $skillId);
                $insert->execute();
            }
            $this->bumpGuard();
            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        }
    }

    public function addAvailability(int $userId, string $start, string $end): void
    {
        $this->db->begin_transaction();
        try {
            $this->lockGuard();
            $statement = $this->db->prepare('INSERT INTO availability (volunteer_id, start_at, end_at) VALUES (?, ?, ?)');
            $statement->bind_param('iss', $userId, $start, $end);
            $statement->execute();
            $this->bumpGuard();
            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        }
    }

    public function deleteAvailability(int $slotId, int $userId): void
    {
        $this->db->begin_transaction();
        try {
            $this->lockGuard();
            $slot = $this->db->prepare('SELECT id FROM availability WHERE id = ? AND volunteer_id = ? FOR UPDATE');
            $slot->bind_param('ii', $slotId, $userId);
            $slot->execute();
            if (!$slot->get_result()->fetch_assoc()) {
                throw new DomainException('Khoảng lịch rảnh không tồn tại hoặc không thuộc hồ sơ này.');
            }
            $remaining = $this->db->prepare('SELECT start_at, end_at FROM availability WHERE volunteer_id = ? AND id <> ? ORDER BY start_at');
            $remaining->bind_param('ii', $userId, $slotId);
            $remaining->execute();
            $intervals = $remaining->get_result()->fetch_all(MYSQLI_ASSOC);
            $active = $this->db->prepare(
                "SELECT s.name, s.start_at, s.end_at FROM assignments a JOIN shifts s ON s.id = a.shift_id
                 WHERE a.volunteer_id = ? AND a.status IN ('assigned','confirmed')"
            );
            $active->bind_param('i', $userId);
            $active->execute();
            $checker = new EligibilityChecker();
            foreach ($active->get_result()->fetch_all(MYSQLI_ASSOC) as $shift) {
                if (!$checker->availabilityCovers($intervals, $shift['start_at'], $shift['end_at'])) {
                    throw new DomainException('Không thể xóa vì lịch rảnh này đang bao phủ ca: ' . $shift['name']);
                }
            }
            $delete = $this->db->prepare('DELETE FROM availability WHERE id = ? AND volunteer_id = ?');
            $delete->bind_param('ii', $slotId, $userId);
            $delete->execute();
            $this->bumpGuard();
            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        }
    }

    private function lockGuard(): void
    {
        $this->db->query('SELECT schedule_revision FROM schedule_guard WHERE id = 1 FOR UPDATE')->fetch_assoc();
    }

    private function bumpGuard(): void
    {
        $this->db->query('UPDATE schedule_guard SET schedule_revision = schedule_revision + 1 WHERE id = 1');
    }
}
