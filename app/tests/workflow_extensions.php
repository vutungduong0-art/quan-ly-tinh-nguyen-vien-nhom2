<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once __DIR__ . '/TestDatabase.php';

$eventId = 0;
$shiftIds = [];
$availabilityIds = [];
$datasetIds = [];
$runIds = [];

function workflowAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $db = testDatabase();
    $coordinator = $db->query("SELECT id FROM users WHERE role = 'coordinator' AND active = 1 ORDER BY id LIMIT 1")->fetch_assoc();
    $people = $db->query("SELECT id FROM users WHERE role = 'volunteer' AND active = 1 ORDER BY id LIMIT 3")->fetch_all(MYSQLI_ASSOC);
    workflowAssert((bool) $coordinator && count($people) === 3, 'Thiếu tài khoản seed để thử workflow mở rộng.');
    $actorId = (int) $coordinator['id'];
    $firstVolunteerId = (int) $people[0]['id'];
    $secondVolunteerId = (int) $people[1]['id'];
    $unregisteredVolunteerId = (int) $people[2]['id'];

    $name = 'TEST_WORKFLOW_' . bin2hex(random_bytes(5));
    $event = $db->prepare(
        "INSERT INTO events (name, location, start_at, end_at, description, status, created_by)
         VALUES (?, 'Test', '2037-01-20 01:00:00', '2037-01-20 05:00:00', 'Dữ liệu kiểm thử', 'published', ?)"
    );
    $event->bind_param('si', $name, $actorId);
    $event->execute();
    $eventId = (int) $db->insert_id;

    $shift = $db->prepare('INSERT INTO shifts (event_id, name, start_at, end_at, required_count) VALUES (?, ?, ?, ?, 1)');
    foreach ([
        ['Ca vắng', '2037-01-20 01:00:00', '2037-01-20 03:00:00'],
        ['Ca chuyển', '2037-01-20 03:00:00', '2037-01-20 05:00:00'],
    ] as $item) {
        $shift->bind_param('isss', $eventId, $item[0], $item[1], $item[2]);
        $shift->execute();
        $shiftIds[] = (int) $db->insert_id;
    }

    $registration = $db->prepare(
        "INSERT INTO registrations (event_id, volunteer_id, status, registered_at)
         VALUES (?, ?, 'registered', UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE status = 'registered'"
    );
    $availability = $db->prepare(
        "INSERT INTO availability (volunteer_id, start_at, end_at) VALUES (?, '2037-01-20 00:00:00', '2037-01-20 06:00:00')"
    );
    foreach ([$firstVolunteerId, $secondVolunteerId] as $volunteerId) {
        $registration->bind_param('ii', $eventId, $volunteerId);
        $registration->execute();
        $availability->bind_param('i', $volunteerId);
        $availability->execute();
        $availabilityIds[] = (int) $db->insert_id;
    }

    $assignments = new AssignmentRepository($db);
    $assignments->manualAssign($shiftIds[0], $firstVolunteerId, $actorId);
    $first = $db->query('SELECT id, version FROM assignments WHERE shift_id = ' . $shiftIds[0] . ' AND volunteer_id = ' . $firstVolunteerId)->fetch_assoc();
    $assignments->markAbsent((int) $first['id'], $actorId, (int) $first['version'], 'Kiểm thử báo vắng');

    $blocked = false;
    try {
        $assignments->manualAssign($shiftIds[0], $firstVolunteerId, $actorId);
    } catch (DomainException $exception) {
        $blocked = str_contains($exception->getMessage(), 'Đã báo vắng');
    }
    workflowAssert($blocked, 'Người đã báo vắng vẫn bị phân lại vào cùng ca.');

    $request = $db->query("SELECT id FROM replacement_requests WHERE assignment_id = {$first['id']} AND status = 'open'")->fetch_assoc();
    $assignments->markReplacementUnfilled((int) $request['id'], 'Không có ứng viên phù hợp', $actorId);
    $requestStatus = $db->query('SELECT status, resolution_note FROM replacement_requests WHERE id = ' . (int) $request['id'])->fetch_assoc();
    workflowAssert($requestStatus['status'] === 'unfilled', 'Không lưu được trạng thái chưa tìm được người thay.');
    workflowAssert($requestStatus['resolution_note'] === 'Không có ứng viên phù hợp', 'Không lưu đúng ghi chú xử lý.');
    $assignments->reopenReplacement((int) $request['id'], $actorId);
    $reopened = $db->query('SELECT status, resolution_note, processed_by, resolved_at FROM replacement_requests WHERE id = ' . (int) $request['id'])->fetch_assoc();
    workflowAssert($reopened['status'] === 'open', 'Không mở lại được yêu cầu unfilled.');
    workflowAssert($reopened['resolution_note'] === null && $reopened['processed_by'] === null && $reopened['resolved_at'] === null, 'Mở lại không xóa trạng thái xử lý cũ.');
    $assignments->markReplacementUnfilled((int) $request['id'], 'Đã tìm lại nhưng vẫn thiếu người', $actorId);

    $assignments->manualAssign($shiftIds[1], $firstVolunteerId, $actorId);
    $source = $db->query('SELECT id, version FROM assignments WHERE shift_id = ' . $shiftIds[1] . ' AND volunteer_id = ' . $firstVolunteerId)->fetch_assoc();
    $invalidTransferBlocked = false;
    try {
        $assignments->transfer((int) $source['id'], $unregisteredVolunteerId, (int) $source['version'], $actorId);
    } catch (DomainException $exception) {
        $invalidTransferBlocked = str_contains($exception->getMessage(), 'chưa đăng ký');
    }
    $sourceAfterFailure = $db->query('SELECT status, version FROM assignments WHERE id = ' . (int) $source['id'])->fetch_assoc();
    $invalidTargetCount = (int) $db->query(
        'SELECT COUNT(*) AS total FROM assignments WHERE shift_id = ' . $shiftIds[1]
        . ' AND volunteer_id = ' . $unregisteredVolunteerId
    )->fetch_assoc()['total'];
    workflowAssert($invalidTransferBlocked, 'Chuyển cho người chưa đăng ký không bị từ chối.');
    workflowAssert($sourceAfterFailure['status'] === 'assigned' && (int) $sourceAfterFailure['version'] === (int) $source['version'], 'Transfer lỗi không rollback phân công nguồn.');
    workflowAssert($invalidTargetCount === 0, 'Transfer lỗi vẫn tạo phân công đích.');
    $assignments->transfer((int) $source['id'], $secondVolunteerId, (int) $source['version'], $actorId);
    $transferred = $db->query('SELECT status FROM assignments WHERE shift_id = ' . $shiftIds[1] . ' AND volunteer_id = ' . $secondVolunteerId)->fetch_assoc();
    workflowAssert($transferred['status'] === 'assigned', 'Chuyển phân công không tạo phân công mới đang hiệu lực.');

    $activeBeforeComparison = (int) $db->query(
        "SELECT COUNT(*) AS total FROM assignments a JOIN shifts s ON s.id = a.shift_id
         WHERE s.event_id = $eventId AND a.status IN ('assigned','confirmed')"
    )->fetch_assoc()['total'];
    $comparison = (new AllocationRepository($db))->generateComparison($eventId, $actorId);
    $runIds = [(int) $comparison['baseline_run_id'], (int) $comparison['improved_run_id']];
    $runList = implode(',', $runIds);
    $runRows = $db->query("SELECT dataset_version_id, comparison_group, project_version, status, config_json FROM allocation_runs WHERE id IN ($runList) ORDER BY id")->fetch_all(MYSQLI_ASSOC);
    workflowAssert(count($runRows) === 2, 'Thiếu một bản chạy đối chứng.');
    workflowAssert($runRows[0]['dataset_version_id'] === $runRows[1]['dataset_version_id'], 'Hai thuật toán không dùng cùng snapshot.');
    workflowAssert($runRows[0]['comparison_group'] === $runRows[1]['comparison_group'], 'Hai bản chạy không cùng nhóm đối chứng.');
    workflowAssert($runRows[0]['project_version'] !== 'unknown', 'Bản chạy chưa lưu phiên bản mã nguồn.');
    workflowAssert($runRows[0]['status'] === 'experiment' && $runRows[1]['status'] === 'experiment', 'Cặp đối chứng không ở trạng thái experiment.');
    foreach ($runRows as $runRow) {
        workflowAssert(is_array(json_decode((string) $runRow['config_json'], true, 512, JSON_THROW_ON_ERROR)), 'Cấu hình thuật toán không phải JSON hợp lệ.');
    }
    $activeAfterComparison = (int) $db->query(
        "SELECT COUNT(*) AS total FROM assignments a JOIN shifts s ON s.id = a.shift_id
         WHERE s.event_id = $eventId AND a.status IN ('assigned','confirmed')"
    )->fetch_assoc()['total'];
    workflowAssert($activeAfterComparison === $activeBeforeComparison, 'Chạy đối chứng đã làm thay đổi lịch công bố.');
    $datasetIds = array_values(array_unique(array_map('intval', array_column($runRows, 'dataset_version_id'))));

    $shiftReport = (new ReportRepository($db))->shiftSummary($eventId);
    workflowAssert(count($shiftReport) === 2, 'Báo cáo theo ca không trả đủ ca.');
    workflowAssert((int) $shiftReport[0]['missing_count'] === 1, 'Báo cáo không nhận diện ca đang thiếu người.');

    $target = $db->query('SELECT id, version FROM assignments WHERE shift_id = ' . $shiftIds[1] . ' AND volunteer_id = ' . $secondVolunteerId)->fetch_assoc();
    $assignments->cancel((int) $target['id'], (int) $target['version'], $actorId);
    (new EventRepository($db))->closeEvent($eventId, $actorId);
    workflowAssert($db->query("SELECT status FROM events WHERE id = $eventId")->fetch_assoc()['status'] === 'closed', 'Yêu cầu unfilled vẫn chặn đóng hoạt động.');
    $reopenClosedBlocked = false;
    try {
        $assignments->reopenReplacement((int) $request['id'], $actorId);
    } catch (DomainException) {
        $reopenClosedBlocked = true;
    }
    $closedRequestStatus = $db->query('SELECT status FROM replacement_requests WHERE id = ' . (int) $request['id'])->fetch_assoc()['status'];
    workflowAssert($reopenClosedBlocked && $closedRequestStatus === 'unfilled', 'Vẫn mở lại được yêu cầu của hoạt động đã đóng.');

    $history = $assignments->history($eventId);
    $replacementHistory = $assignments->replacementHistory($eventId);
    workflowAssert(count($history) >= 3, 'Lịch sử phân công chưa giữ đủ absent/cancelled.');
    workflowAssert(count($replacementHistory) === 1 && $replacementHistory[0]['status'] === 'unfilled', 'Lịch sử thay người không phản ánh kết quả cuối.');
    $sourceAssignmentId = (int) $source['id'];
    $absentAssignmentId = (int) $first['id'];
    $auditActions = array_column($db->query(
        "SELECT action FROM audit_logs WHERE actor_id = $actorId AND (
            (action = 'assignment.transferred' AND entity_id = $sourceAssignmentId)
            OR (action = 'replacement.unfilled' AND entity_id = $absentAssignmentId)
            OR (action = 'replacement.reopened' AND entity_id = $absentAssignmentId)
         )"
    )->fetch_all(MYSQLI_ASSOC), 'action');
    workflowAssert(in_array('assignment.transferred', $auditActions, true), 'Thiếu audit chuyển phân công.');
    workflowAssert(in_array('replacement.unfilled', $auditActions, true) && in_array('replacement.reopened', $auditActions, true), 'Thiếu audit xử lý/mở lại yêu cầu.');

    fwrite(STDOUT, "[PASS] Workflow mở rộng: rollback chuyển người, unfilled/mở lại, A/B không đổi lịch, lịch sử/audit và đóng hoạt động.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, "[FAIL] Workflow mở rộng: {$exception->getMessage()}\n");
    $exitCode = 1;
} finally {
    if (isset($db) && $db instanceof mysqli) {
        try {
            if ($eventId > 0) {
                $db->query("DELETE FROM audit_logs WHERE entity_type = 'event' AND entity_id = $eventId");
                $db->query("DELETE FROM audit_logs WHERE entity_type = 'shift' AND entity_id IN (SELECT id FROM shifts WHERE event_id = $eventId)");
                $db->query("DELETE FROM audit_logs WHERE entity_type = 'allocation_run' AND entity_id IN (SELECT id FROM allocation_runs WHERE event_id = $eventId)");
                $db->query("DELETE FROM audit_logs WHERE entity_type = 'assignment' AND entity_id IN (SELECT a.id FROM assignments a JOIN shifts s ON s.id = a.shift_id WHERE s.event_id = $eventId)");
                $db->query("DELETE rr FROM replacement_requests rr JOIN assignments a ON a.id = rr.assignment_id JOIN shifts s ON s.id = a.shift_id WHERE s.event_id = $eventId");
                $db->query("DELETE a FROM assignments a JOIN shifts s ON s.id = a.shift_id WHERE s.event_id = $eventId");
                $db->query("DELETE FROM allocation_runs WHERE event_id = $eventId");
                foreach ($datasetIds as $datasetId) {
                    $db->query("DELETE FROM dataset_versions WHERE id = $datasetId AND NOT EXISTS (SELECT 1 FROM allocation_runs WHERE dataset_version_id = $datasetId)");
                }
                $db->query("DELETE FROM registrations WHERE event_id = $eventId");
                $db->query("DELETE FROM shifts WHERE event_id = $eventId");
                $db->query("DELETE FROM events WHERE id = $eventId");
            }
            foreach ($availabilityIds as $availabilityId) {
                $db->query("DELETE FROM availability WHERE id = $availabilityId");
            }
        } catch (Throwable $cleanupException) {
            fwrite(STDERR, "[WARN] Dọn dữ liệu workflow: {$cleanupException->getMessage()}\n");
            $exitCode = 1;
        }
    }
}

exit($exitCode ?? 0);
