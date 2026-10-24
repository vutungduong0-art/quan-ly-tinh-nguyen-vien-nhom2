<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once __DIR__ . '/TestDatabase.php';

$database = testDatabase();
$events = array_map('intval', array_column(
    $database->query("SELECT id FROM events WHERE name LIKE 'Hoạt động kiểm thử HTTP %' ORDER BY id")->fetch_all(MYSQLI_ASSOC),
    'id'
));

foreach ($events as $eventId) {
    $datasetIds = array_map('intval', array_column(
        $database->query("SELECT dataset_version_id FROM allocation_runs WHERE event_id = $eventId")->fetch_all(MYSQLI_ASSOC),
        'dataset_version_id'
    ));
    $database->begin_transaction();
    try {
        $database->query("DELETE FROM audit_logs WHERE entity_type = 'event' AND entity_id = $eventId");
        $database->query("DELETE FROM audit_logs WHERE entity_type = 'shift' AND entity_id IN (SELECT id FROM shifts WHERE event_id = $eventId)");
        $database->query("DELETE FROM audit_logs WHERE entity_type = 'allocation_run' AND entity_id IN (SELECT id FROM allocation_runs WHERE event_id = $eventId)");
        $database->query("DELETE FROM audit_logs WHERE entity_type = 'assignment' AND entity_id IN (SELECT a.id FROM assignments a JOIN shifts s ON s.id = a.shift_id WHERE s.event_id = $eventId)");
        $database->query("DELETE rr FROM replacement_requests rr JOIN assignments a ON a.id = rr.assignment_id JOIN shifts s ON s.id = a.shift_id WHERE s.event_id = $eventId");
        $database->query("DELETE a FROM assignments a JOIN shifts s ON s.id = a.shift_id WHERE s.event_id = $eventId");
        $database->query("DELETE FROM allocation_runs WHERE event_id = $eventId");
        $database->query("DELETE rh FROM registration_history rh JOIN registrations r ON r.id = rh.registration_id WHERE r.event_id = $eventId");
        $database->query("DELETE FROM registrations WHERE event_id = $eventId");
        $database->query("DELETE FROM shifts WHERE event_id = $eventId");
        $database->query("DELETE FROM events WHERE id = $eventId AND name LIKE 'Hoạt động kiểm thử HTTP %'");
        foreach ($datasetIds as $datasetId) {
            $database->query("DELETE FROM dataset_versions WHERE id = $datasetId AND NOT EXISTS (SELECT 1 FROM allocation_runs WHERE dataset_version_id = $datasetId)");
        }
        $database->commit();
        fwrite(STDOUT, "Đã dọn hoạt động kiểm thử #$eventId.\n");
    } catch (Throwable $exception) {
        $database->rollback();
        fwrite(STDERR, "Không dọn được hoạt động #$eventId: {$exception->getMessage()}\n");
        exit(1);
    }
}

fwrite(STDOUT, 'Hoàn tất; đã dọn ' . count($events) . " hoạt động do HTTP test tạo.\n");
