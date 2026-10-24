<?php

declare(strict_types=1);

final class AllocationRepository
{
    public function __construct(private readonly mysqli $db)
    {
    }

    public function generate(int $eventId, string $algorithm, int $actorId): int
    {
        $snapshot = (new SnapshotBuilder($this->db))->forEvent($eventId);
        $result = $this->allocate($snapshot, $algorithm);
        $this->db->begin_transaction();
        try {
            $runId = $this->persistRun($eventId, $snapshot, $result, $actorId, 'draft', null);
            $this->db->commit();
            return $runId;
        } catch (Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        }
    }

    public function generateComparison(int $eventId, int $actorId): array
    {
        $snapshot = (new SnapshotBuilder($this->db))->forEvent($eventId);
        $baseline = $this->allocate($snapshot, RegistrationOrderAllocator::NAME);
        $improved = $this->allocate($snapshot, ScarcityBalancedAllocator::NAME);
        $group = bin2hex(random_bytes(12));

        $this->db->begin_transaction();
        try {
            $baselineId = $this->persistRun($eventId, $snapshot, $baseline, $actorId, 'experiment', $group);
            $improvedId = $this->persistRun($eventId, $snapshot, $improved, $actorId, 'experiment', $group);
            $this->db->commit();
            return ['comparison_group' => $group, 'baseline_run_id' => $baselineId, 'improved_run_id' => $improvedId];
        } catch (Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        }
    }

    public function publish(int $runId, int $actorId): void
    {
        $this->db->begin_transaction();
        try {
            $guard = $this->db->query('SELECT schedule_revision FROM schedule_guard WHERE id = 1 FOR UPDATE')->fetch_assoc();
            $currentRevision = (int) $guard['schedule_revision'];
            $statement = $this->db->prepare(
                "SELECT ar.*, dv.snapshot_json FROM allocation_runs ar JOIN dataset_versions dv ON dv.id = ar.dataset_version_id
                 WHERE ar.id = ? FOR UPDATE"
            );
            $statement->bind_param('i', $runId);
            $statement->execute();
            $run = $statement->get_result()->fetch_assoc();
            if (!$run || $run['status'] !== 'draft') {
                throw new DomainException('Bản chạy không tồn tại hoặc không còn ở trạng thái nháp.');
            }
            if ((int) $run['schedule_revision'] !== $currentRevision) {
                throw new DomainException('Dữ liệu lịch đã thay đổi. Hãy tạo lại phương án trước khi công bố.');
            }
            $assignments = $this->runAssignments($runId);
            $snapshot = json_decode((string) $run['snapshot_json'], true, 512, JSON_THROW_ON_ERROR);
            $validation = (new ScheduleValidator(new EligibilityChecker()))->validate($snapshot, $assignments);
            if (!$validation['valid']) {
                throw new DomainException('Phương án không còn hợp lệ nên không thể công bố.');
            }
            $eventId = (int) $run['event_id'];
            $supersede = $this->db->prepare("UPDATE allocation_runs SET status = 'superseded' WHERE event_id = ? AND status = 'published'");
            $supersede->bind_param('i', $eventId);
            $supersede->execute();
            $publish = $this->db->prepare("UPDATE allocation_runs SET status = 'published', published_at = UTC_TIMESTAMP() WHERE id = ?");
            $publish->bind_param('i', $runId);
            $publish->execute();

            $cancel = $this->db->prepare(
                "UPDATE assignments a JOIN shifts s ON s.id = a.shift_id
                 SET a.status = 'cancelled', a.version = a.version + 1
                 WHERE s.event_id = ? AND a.status IN ('assigned','confirmed')"
            );
            $cancel->bind_param('i', $eventId);
            $cancel->execute();
            $upsert = $this->db->prepare(
                "INSERT INTO assignments (shift_id, volunteer_id, source_run_id, status, assigned_by, assigned_at)
                 VALUES (?, ?, ?, 'assigned', ?, UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE source_run_id = VALUES(source_run_id), status = 'assigned', assigned_by = VALUES(assigned_by), assigned_at = UTC_TIMESTAMP(), version = version + 1"
            );
            foreach ($assignments as $item) {
                $shiftId = (int) $item['shift_id'];
                $volunteerId = (int) $item['volunteer_id'];
                $upsert->bind_param('iiii', $shiftId, $volunteerId, $runId, $actorId);
                $upsert->execute();
            }
            $this->db->query('UPDATE schedule_guard SET schedule_revision = schedule_revision + 1 WHERE id = 1');
            $this->audit($actorId, 'allocation.published', 'allocation_run', $runId, null, ['event_id' => $eventId]);
            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        }
    }

    public function runs(?int $eventId = null): array
    {
        $sql = "SELECT ar.id, ar.event_id, e.name AS event_name, ar.algorithm, ar.algorithm_version, ar.status,
                       ar.comparison_group, ar.project_version, ar.config_json,
                       ar.metrics_json, ar.unassigned_json, ar.schedule_revision, ar.created_at, ar.published_at,
                       dv.code AS dataset_code, dv.version AS dataset_version, dv.sha256 AS snapshot_sha256,
                       COUNT(ra.volunteer_id) AS assignment_count
                FROM allocation_runs ar JOIN events e ON e.id = ar.event_id
                JOIN dataset_versions dv ON dv.id = ar.dataset_version_id
                LEFT JOIN run_assignments ra ON ra.run_id = ar.id";
        if ($eventId !== null) {
            $sql .= ' WHERE ar.event_id = ?';
        }
        $sql .= ' GROUP BY ar.id ORDER BY ar.created_at DESC, ar.id DESC LIMIT 100';
        $statement = $this->db->prepare($sql);
        if ($eventId !== null) {
            $statement->bind_param('i', $eventId);
        }
        $statement->execute();
        $rows = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($rows as &$row) {
            $row['metrics'] = json_decode((string) $row['metrics_json'], true) ?: [];
            $row['unassigned'] = json_decode((string) $row['unassigned_json'], true) ?: [];
            $row['config'] = json_decode((string) $row['config_json'], true) ?: [];
        }
        unset($row);
        return $rows;
    }

    public function runAssignments(int $runId): array
    {
        $statement = $this->db->prepare(
            'SELECT ra.shift_id, ra.volunteer_id, s.start_at, s.end_at, ra.is_fixed AS fixed
             FROM run_assignments ra JOIN shifts s ON s.id = ra.shift_id WHERE ra.run_id = ? ORDER BY s.start_at, ra.shift_id, ra.volunteer_id'
        );
        $statement->bind_param('i', $runId);
        $statement->execute();
        return $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    private function allocate(array $snapshot, string $algorithm): array
    {
        $checker = new EligibilityChecker();
        $result = match ($algorithm) {
            RegistrationOrderAllocator::NAME => (new RegistrationOrderAllocator($checker))->allocate($snapshot),
            ScarcityBalancedAllocator::NAME => (new ScarcityBalancedAllocator($checker))->allocate($snapshot),
            default => throw new DomainException('Thuật toán không được hỗ trợ.'),
        };
        if (!$result['validation']['valid']) {
            throw new DomainException('Kết quả có vi phạm ràng buộc nên không được lưu.');
        }
        return $result;
    }

    private function persistRun(
        int $eventId,
        array $snapshot,
        array $result,
        int $actorId,
        string $status,
        ?string $comparisonGroup
    ): int {
        $json = Snapshot::encode($snapshot);
        $hash = hash('sha256', $json);
        $revision = (int) $snapshot['dataset']['schedule_revision'];
        $version = (string) $snapshot['dataset']['version'];
        $timezone = (string) $snapshot['dataset']['timezone'];
        $code = (string) $snapshot['dataset']['code'];
        $algorithm = (string) $result['algorithm'];
        $algorithmVersion = (string) $result['algorithm_version'];
        $projectVersion = $this->projectVersion();
        $configJson = json_encode($result['config'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $metricsJson = json_encode($result['metrics'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $unassignedJson = json_encode($result['unassigned_volunteers'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $dataset = $this->db->prepare(
            "INSERT INTO dataset_versions (code, version, sha256, seed, timezone, snapshot_json, source)
             VALUES (?, ?, ?, NULL, ?, ?, 'live') ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)"
        );
        $dataset->bind_param('sssss', $code, $version, $hash, $timezone, $json);
        $dataset->execute();
        $datasetId = (int) $this->db->insert_id;
        $run = $this->db->prepare(
            'INSERT INTO allocation_runs
             (event_id, dataset_version_id, algorithm, algorithm_version, comparison_group, project_version,
              config_json, schedule_revision, status, metrics_json, unassigned_json, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $run->bind_param(
            'iisssssisssi',
            $eventId,
            $datasetId,
            $algorithm,
            $algorithmVersion,
            $comparisonGroup,
            $projectVersion,
            $configJson,
            $revision,
            $status,
            $metricsJson,
            $unassignedJson,
            $actorId
        );
        $run->execute();
        $runId = (int) $this->db->insert_id;
        $assignment = $this->db->prepare(
            'INSERT INTO run_assignments (run_id, shift_id, volunteer_id, is_fixed, reason) VALUES (?, ?, ?, ?, NULL)'
        );
        foreach ($result['assignments'] as $item) {
            $shiftId = (int) $item['shift_id'];
            $volunteerId = (int) $item['volunteer_id'];
            $isFixed = !empty($item['fixed']) ? 1 : 0;
            $assignment->bind_param('iiii', $runId, $shiftId, $volunteerId, $isFixed);
            $assignment->execute();
        }
        $this->audit($actorId, 'allocation.generated', 'allocation_run', $runId, null, [
            'algorithm' => $algorithm,
            'snapshot_sha256' => $hash,
            'comparison_group' => $comparisonGroup,
            'project_version' => $projectVersion,
        ]);
        return $runId;
    }

    private function projectVersion(): string
    {
        $override = getenv('APP_CODE_VERSION');
        if (is_string($override) && trim($override) !== '') {
            return substr(trim($override), 0, 80);
        }
        $path = dirname(__DIR__, 3) . '/VERSION';
        $version = is_file($path) ? trim((string) file_get_contents($path)) : 'unknown';
        return $version !== '' ? substr($version, 0, 80) : 'unknown';
    }

    private function audit(int $actorId, string $action, string $entityType, int $entityId, ?array $before, array $after): void
    {
        $beforeJson = $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $afterJson = json_encode($after, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $statement = $this->db->prepare('INSERT INTO audit_logs (actor_id, action, entity_type, entity_id, before_json, after_json) VALUES (?, ?, ?, ?, ?, ?)');
        $statement->bind_param('ississ', $actorId, $action, $entityType, $entityId, $beforeJson, $afterJson);
        $statement->execute();
    }
}
