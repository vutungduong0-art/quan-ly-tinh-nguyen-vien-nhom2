<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once __DIR__ . '/TestDatabase.php';

if (!function_exists('pcntl_fork')) {
    fwrite(STDERR, "[FAIL] PHP thiếu extension pcntl; không thể chạy kiểm thử cạnh tranh.\n");
    exit(1);
}

$setup = testDatabase();
$eventId = 0;
$shiftId = 0;
$resultFiles = [tempnam(sys_get_temp_dir(), 'vh-race-a-'), tempnam(sys_get_temp_dir(), 'vh-race-b-')];

try {
    $coordinatorId = (int) $setup->query("SELECT id FROM users WHERE role = 'coordinator' ORDER BY id LIMIT 1")->fetch_assoc()['id'];
    $volunteers = array_map('intval', array_column(
        $setup->query("SELECT id FROM users WHERE role = 'volunteer' AND active = 1 ORDER BY id LIMIT 2")->fetch_all(MYSQLI_ASSOC),
        'id'
    ));
    if (count($volunteers) !== 2) {
        throw new RuntimeException('Cần hai tình nguyện viên để thử cạnh tranh.');
    }
    $name = 'Kiểm thử cạnh tranh ' . str_replace('.', '', (string) microtime(true));
    $event = $setup->prepare(
        "INSERT INTO events (name, location, start_at, end_at, description, status, created_by)
         VALUES (?, 'Phòng kiểm thử', '2027-09-01 01:00:00', '2027-09-01 04:00:00', 'Dữ liệu tạm I05', 'published', ?)"
    );
    $event->bind_param('si', $name, $coordinatorId);
    $event->execute();
    $eventId = (int) $setup->insert_id;
    $shift = $setup->prepare(
        "INSERT INTO shifts (event_id, name, start_at, end_at, required_count)
         VALUES (?, 'Chỗ cuối', '2027-09-01 01:00:00', '2027-09-01 03:00:00', 1)"
    );
    $shift->bind_param('i', $eventId);
    $shift->execute();
    $shiftId = (int) $setup->insert_id;
    $registration = $setup->prepare(
        "INSERT INTO registrations (event_id, volunteer_id, status, registered_at) VALUES (?, ?, 'registered', UTC_TIMESTAMP())"
    );
    foreach ($volunteers as $volunteerId) {
        $registration->bind_param('ii', $eventId, $volunteerId);
        $registration->execute();
    }
    $setup->close();
    $setup = null;

    $children = [];
    foreach ($volunteers as $index => $volunteerId) {
        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new RuntimeException('Không thể tạo tiến trình con.');
        }
        if ($pid === 0) {
            try {
                usleep(50000);
                $childDb = testDatabase();
                (new AssignmentRepository($childDb))->manualAssign($shiftId, $volunteerId, $coordinatorId);
                file_put_contents($resultFiles[$index], 'success', LOCK_EX);
            } catch (DomainException $exception) {
                file_put_contents($resultFiles[$index], 'blocked:' . $exception->getMessage(), LOCK_EX);
            } catch (Throwable $exception) {
                file_put_contents($resultFiles[$index], 'error:' . $exception->getMessage(), LOCK_EX);
            }
            exit(0);
        }
        $children[] = $pid;
    }
    foreach ($children as $pid) {
        pcntl_waitpid($pid, $status);
    }

    $verify = testDatabase();
    $results = array_map(static fn (string $path): string => trim((string) file_get_contents($path)), $resultFiles);
    $active = (int) $verify->query(
        "SELECT COUNT(*) AS total FROM assignments WHERE shift_id = $shiftId AND status IN ('assigned','confirmed')"
    )->fetch_assoc()['total'];
    $successes = count(array_filter($results, static fn (string $result): bool => $result === 'success'));
    $blocked = count(array_filter($results, static fn (string $result): bool => str_starts_with($result, 'blocked:')));
    if ($active !== 1 || $successes !== 1 || $blocked !== 1) {
        throw new RuntimeException('Kết quả cạnh tranh sai: ' . json_encode(['active' => $active, 'results' => $results], JSON_UNESCAPED_UNICODE));
    }
    fwrite(STDOUT, "[PASS] I05: hai tiến trình tranh một chỗ, đúng một thành công và ca có đúng một người.\n");
    $setup = $verify;
} catch (Throwable $exception) {
    fwrite(STDERR, '[FAIL] I05: ' . $exception->getMessage() . PHP_EOL);
    $exitCode = 1;
} finally {
    foreach ($resultFiles as $path) {
        if (is_string($path) && is_file($path)) {
            unlink($path);
        }
    }
    try {
        if ((!isset($setup) || !$setup instanceof mysqli) && $eventId > 0) {
            $setup = testDatabase();
        }
        if (isset($setup) && $setup instanceof mysqli && $eventId > 0) {
            $setup->begin_transaction();
            $setup->query("DELETE FROM audit_logs WHERE action = 'assignment.manual' AND entity_id IN (SELECT id FROM assignments WHERE shift_id = $shiftId)");
            $setup->query("DELETE FROM assignments WHERE shift_id = $shiftId");
            $setup->query("DELETE FROM registrations WHERE event_id = $eventId");
            $setup->query("DELETE FROM shifts WHERE event_id = $eventId");
            $setup->query("DELETE FROM events WHERE id = $eventId");
            $setup->commit();
        }
    } catch (Throwable $cleanupException) {
        fwrite(STDERR, '[FAIL] Không dọn được dữ liệu tạm I05: ' . $cleanupException->getMessage() . PHP_EOL);
        $exitCode = 1;
    }
}

exit($exitCode ?? 0);
