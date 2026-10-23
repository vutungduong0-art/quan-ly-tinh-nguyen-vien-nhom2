<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once __DIR__ . '/TestDatabase.php';

$database = testDatabase();
$runId = 0;
$datasetId = 0;
try {
    $coordinatorId = (int) $database->query("SELECT id FROM users WHERE role = 'coordinator' ORDER BY id LIMIT 1")->fetch_assoc()['id'];
    $eventId = (int) $database->query(
        "SELECT e.id FROM events e
         WHERE e.name NOT LIKE 'Hoạt động kiểm thử HTTP %' AND EXISTS (SELECT 1 FROM shifts s WHERE s.event_id = e.id)
         ORDER BY e.id LIMIT 1"
    )->fetch_assoc()['id'];
    $beforeAssignments = (int) $database->query(
        "SELECT COUNT(*) AS total FROM assignments a JOIN shifts s ON s.id = a.shift_id
         WHERE s.event_id = $eventId AND a.status IN ('assigned','confirmed')"
    )->fetch_assoc()['total'];
    $runId = (new AllocationRepository($database))->generate($eventId, ScarcityBalancedAllocator::NAME, $coordinatorId);
    $runRow = $database->query("SELECT dataset_version_id FROM allocation_runs WHERE id = $runId")->fetch_assoc();
    $datasetId = (int) $runRow['dataset_version_id'];

    $database->query('UPDATE schedule_guard SET schedule_revision = schedule_revision + 1 WHERE id = 1');
    $blocked = false;
    try {
        (new AllocationRepository($database))->publish($runId, $coordinatorId);
    } catch (DomainException $exception) {
        $blocked = str_contains($exception->getMessage(), 'Dữ liệu lịch đã thay đổi');
    }
    $status = (string) $database->query("SELECT status FROM allocation_runs WHERE id = $runId")->fetch_assoc()['status'];
    $afterAssignments = (int) $database->query(
        "SELECT COUNT(*) AS total FROM assignments a JOIN shifts s ON s.id = a.shift_id
         WHERE s.event_id = $eventId AND a.status IN ('assigned','confirmed')"
    )->fetch_assoc()['total'];
    if (!$blocked || $status !== 'draft' || $beforeAssignments !== $afterAssignments) {
        throw new RuntimeException('Phương án cũ không bị chặn an toàn.');
    }
    fwrite(STDOUT, "[PASS] I04: phương án stale bị từ chối và lịch công bố không đổi.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, '[FAIL] I04: ' . $exception->getMessage() . PHP_EOL);
    $exitCode = 1;
} finally {
    try {
        if ($runId > 0) {
            $database->begin_transaction();
            $database->query("DELETE FROM audit_logs WHERE action = 'allocation.generated' AND entity_type = 'allocation_run' AND entity_id = $runId");
            $database->query("DELETE FROM allocation_runs WHERE id = $runId");
            if ($datasetId > 0) {
                $database->query("DELETE FROM dataset_versions WHERE id = $datasetId AND NOT EXISTS (SELECT 1 FROM allocation_runs WHERE dataset_version_id = $datasetId)");
            }
            $database->commit();
        }
    } catch (Throwable $cleanupException) {
        fwrite(STDERR, '[FAIL] Không dọn được phương án tạm I04: ' . $cleanupException->getMessage() . PHP_EOL);
        $exitCode = 1;
    }
}
exit($exitCode ?? 0);
