<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once __DIR__ . '/TestDatabase.php';

$database = testDatabase();
$eventId = 0;
$shiftId = 0;
$assignmentId = 0;
$exitCode = 0;

try {
    $coordinatorId = (int) $database->query("SELECT id FROM users WHERE role = 'coordinator' ORDER BY id LIMIT 1")->fetch_assoc()['id'];
    $volunteerId = (int) $database->query("SELECT id FROM users WHERE role = 'volunteer' ORDER BY id LIMIT 1")->fetch_assoc()['id'];
    $repository = new EventRepository($database);
    $eventId = $repository->create([
        'name' => 'Quản lý hoạt động ' . str_replace('.', '', (string) microtime(true)),
        'location' => 'Khu kiểm thử',
        'start_at' => '2027-10-01 01:00:00',
        'end_at' => '2027-10-01 06:00:00',
        'description' => 'Dữ liệu tạm',
    ], $coordinatorId);
    $shiftId = $repository->createShift($eventId, [
        'name' => 'Ca ban đầu',
        'start_at' => '2027-10-01 02:00:00',
        'end_at' => '2027-10-01 04:00:00',
        'required_count' => 2,
    ], []);
    $repository->updateEvent($eventId, [
        'name' => 'Hoạt động đã sửa',
        'location' => 'Địa điểm mới',
        'start_at' => '2027-10-01 01:00:00',
        'end_at' => '2027-10-01 07:00:00',
        'description' => 'Nội dung đã sửa',
    ], $coordinatorId);
    $repository->updateShift($eventId, $shiftId, [
        'name' => 'Ca đã sửa',
        'start_at' => '2027-10-01 02:30:00',
        'end_at' => '2027-10-01 04:30:00',
        'required_count' => 1,
    ], [], $coordinatorId);

    $registration = $database->prepare(
        "INSERT INTO registrations (event_id, volunteer_id, status, registered_at) VALUES (?, ?, 'registered', UTC_TIMESTAMP())"
    );
    $registration->bind_param('ii', $eventId, $volunteerId);
    $registration->execute();
    $assignment = $database->prepare(
        "INSERT INTO assignments (shift_id, volunteer_id, status, version, assigned_by, assigned_at)
         VALUES (?, ?, 'assigned', 1, ?, UTC_TIMESTAMP())"
    );
    $assignment->bind_param('iii', $shiftId, $volunteerId, $coordinatorId);
    $assignment->execute();
    $assignmentId = (int) $database->insert_id;

    $blockedEdit = false;
    try {
        $repository->updateShift($eventId, $shiftId, [
            'name' => 'Ca không hợp lệ',
            'start_at' => '2027-10-01 03:00:00',
            'end_at' => '2027-10-01 05:00:00',
            'required_count' => 1,
        ], [], $coordinatorId);
    } catch (DomainException $exception) {
        $blockedEdit = str_contains($exception->getMessage(), 'phân công hiệu lực');
    }
    $blockedClose = false;
    try {
        $repository->closeEvent($eventId, $coordinatorId);
    } catch (DomainException $exception) {
        $blockedClose = str_contains($exception->getMessage(), 'phân công đang hiệu lực');
    }
    if (!$blockedEdit || !$blockedClose) {
        throw new RuntimeException('Không chặn sửa/đóng khi ca còn phân công.');
    }

    $database->query("DELETE FROM assignments WHERE id = $assignmentId");
    $assignmentId = 0;
    $database->query("DELETE FROM registrations WHERE event_id = $eventId");
    $repository->deleteShift($eventId, $shiftId, $coordinatorId);
    $repository->closeEvent($eventId, $coordinatorId);
    $row = $database->query("SELECT name, location, status FROM events WHERE id = $eventId")->fetch_assoc();
    if (($row['name'] ?? '') !== 'Hoạt động đã sửa' || ($row['location'] ?? '') !== 'Địa điểm mới' || ($row['status'] ?? '') !== 'closed') {
        throw new RuntimeException('Dữ liệu sửa/đóng không được lưu đúng.');
    }
    $auditCount = (int) $database->query(
        "SELECT COUNT(*) AS total FROM audit_logs WHERE
         (entity_type = 'event' AND entity_id = $eventId) OR (entity_type = 'shift' AND entity_id = $shiftId)"
    )->fetch_assoc()['total'];
    if ($auditCount < 4) {
        throw new RuntimeException('Thiếu audit cho thao tác sửa/xóa/đóng.');
    }
    fwrite(STDOUT, "[PASS] Quản lý hoạt động/ca: sửa, chặn xung đột, xóa ca chưa dùng, đóng và audit.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, '[FAIL] Quản lý hoạt động/ca: ' . $exception->getMessage() . PHP_EOL);
    $exitCode = 1;
} finally {
    try {
        if ($assignmentId > 0) {
            $database->query("DELETE FROM assignments WHERE id = $assignmentId");
        }
        if ($eventId > 0) {
            $database->query("DELETE FROM registrations WHERE event_id = $eventId");
            $database->query("DELETE FROM audit_logs WHERE (entity_type = 'event' AND entity_id = $eventId) OR (entity_type = 'shift' AND entity_id = $shiftId)");
            $database->query("DELETE FROM shifts WHERE event_id = $eventId");
            $database->query("DELETE FROM events WHERE id = $eventId");
        }
    } catch (Throwable $cleanupException) {
        fwrite(STDERR, '[FAIL] Không dọn được dữ liệu test quản lý: ' . $cleanupException->getMessage() . PHP_EOL);
        $exitCode = 1;
    }
}

exit($exitCode);
