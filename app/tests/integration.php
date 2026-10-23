<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once __DIR__ . '/TestDatabase.php';

$passed = 0;
$failed = 0;

function integrationTest(string $name, callable $callback): void
{
    global $passed, $failed;
    try {
        $callback();
        $passed++;
        fwrite(STDOUT, "[PASS] {$name}\n");
    } catch (Throwable $exception) {
        $failed++;
        fwrite(STDOUT, "[FAIL] {$name}: {$exception->getMessage()}\n");
    }
}

function integrationAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $db = testDatabase();
} catch (Throwable $exception) {
    fwrite(STDERR, "Không thể chạy integration test: {$exception->getMessage()}\n");
    exit(1);
}

integrationTest('Đủ các bảng nghiệp vụ toàn dự án', function () use ($db): void {
    $required = [
        'users', 'events', 'skills', 'shifts', 'shift_skills', 'registrations',
        'volunteer_profiles', 'volunteer_skills', 'availability', 'registration_history',
        'schedule_guard', 'dataset_versions', 'allocation_runs', 'run_assignments',
        'assignments', 'replacement_requests', 'audit_logs',
        'login_attempts',
    ];
    $result = $db->query('SHOW TABLES');
    $actual = array_map(static fn (array $row): string => (string) array_values($row)[0], $result->fetch_all(MYSQLI_ASSOC));
    foreach ($required as $table) {
        integrationAssert(in_array($table, $actual, true), "Thiếu bảng {$table}");
    }
});

integrationTest('Dữ liệu chuẩn có 30 tình nguyện viên và 5 kỹ năng', function () use ($db): void {
    $volunteers = (int) $db->query("SELECT COUNT(*) AS total FROM users WHERE role = 'volunteer'")->fetch_assoc()['total'];
    $profiles = (int) $db->query('SELECT COUNT(*) AS total FROM volunteer_profiles')->fetch_assoc()['total'];
    $skills = (int) $db->query('SELECT COUNT(*) AS total FROM skills')->fetch_assoc()['total'];
    integrationAssert($volunteers >= 30, 'Thiếu 30 tài khoản tình nguyện viên');
    integrationAssert($profiles >= 30, 'Thiếu hồ sơ tình nguyện viên');
    integrationAssert($skills >= 5, 'Thiếu 5 kỹ năng');
});

integrationTest('Tài khoản dùng password_hash/password_verify', function () use ($db): void {
    $statement = $db->prepare("SELECT password_hash FROM users WHERE username = 'dieuphoi'");
    $statement->execute();
    $row = $statement->get_result()->fetch_assoc();
    integrationAssert((bool) $row, 'Thiếu tài khoản điều phối');
    integrationAssert(password_verify('Dieuphoi@123', $row['password_hash']), 'Hash mật khẩu điều phối không hợp lệ');
});

integrationTest('Có 3 hoạt động và dữ liệu ca mẫu', function () use ($db): void {
    $events = (int) $db->query('SELECT COUNT(*) AS total FROM events')->fetch_assoc()['total'];
    $shifts = (int) $db->query('SELECT COUNT(*) AS total FROM shifts')->fetch_assoc()['total'];
    integrationAssert($events >= 3, 'Cần ít nhất 3 hoạt động');
    integrationAssert($shifts >= 8, 'Cần ít nhất 8 ca mẫu');
});

integrationTest('Unique chặn đăng ký trùng', function () use ($db): void {
    $row = $db->query(
        "SELECT e.id AS event_id, u.id AS volunteer_id
         FROM events e CROSS JOIN users u
         WHERE u.username = 'tinhnguyen' ORDER BY e.id LIMIT 1"
    )->fetch_assoc();
    integrationAssert((bool) $row, 'Thiếu dữ liệu thử đăng ký');
    $db->begin_transaction();
    try {
        $insert = $db->prepare(
            "INSERT INTO registrations (event_id, volunteer_id, status, registered_at)
             VALUES (?, ?, 'registered', UTC_TIMESTAMP())"
        );
        $eventId = (int) $row['event_id'];
        $volunteerId = (int) $row['volunteer_id'];
        $insert->bind_param('ii', $eventId, $volunteerId);
        try {
            $insert->execute();
        } catch (mysqli_sql_exception $exception) {
            if ($exception->getCode() !== 1062) {
                throw $exception;
            }
        }
        $duplicateBlocked = false;
        try {
            $insert->execute();
        } catch (mysqli_sql_exception $exception) {
            $duplicateBlocked = $exception->getCode() === 1062;
        }
        integrationAssert($duplicateBlocked, 'Cơ sở dữ liệu không chặn đăng ký trùng');
    } finally {
        $db->rollback();
    }
});

integrationTest('Khóa ngoại chặn ca thuộc hoạt động không tồn tại', function () use ($db): void {
    $blocked = false;
    $db->begin_transaction();
    try {
        $statement = $db->prepare(
            "INSERT INTO shifts (event_id, name, start_at, end_at, required_count)
             VALUES (999999999, 'Ca lỗi', '2027-01-01 01:00:00', '2027-01-01 02:00:00', 1)"
        );
        try {
            $statement->execute();
        } catch (mysqli_sql_exception $exception) {
            $blocked = $exception->getCode() === 1452;
        }
        integrationAssert($blocked, 'Khóa ngoại không chặn event_id không tồn tại');
    } finally {
        $db->rollback();
    }
});

integrationTest('Kiểm tra quyền điều phối ở máy chủ', function () use ($db): void {
    $_SESSION['user'] = ['id' => 2, 'username' => 'tinhnguyen', 'full_name' => 'Tình nguyện viên', 'role' => 'volunteer'];
    $blocked = false;
    try {
        (new Auth($db))->requireRole('coordinator');
    } catch (HttpException $exception) {
        $blocked = $exception->status === 403;
    }
    integrationAssert($blocked, 'Tình nguyện viên không bị chặn khỏi quyền điều phối');
    unset($_SESSION['user']);
});

integrationTest('Khóa tạm đăng nhập sau năm lần sai', function () use ($db): void {
    $username = 'khong-ton-tai-' . str_replace('.', '', (string) microtime(true));
    $identityHash = hash('sha256', strtolower($username));
    $ipHash = hash('sha256', 'integration-test');
    $_SERVER['REMOTE_ADDR'] = 'integration-test';
    try {
        $auth = new Auth($db);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            integrationAssert($auth->attempt($username, 'sai-mat-khau') === false, 'Lần sai không bị ghi nhận');
        }
        $blocked = false;
        try {
            $auth->attempt($username, 'sai-mat-khau');
        } catch (HttpException $exception) {
            $blocked = $exception->status === 429;
        }
        integrationAssert($blocked, 'Không khóa tạm sau năm lần sai');
    } finally {
        $delete = $db->prepare('DELETE FROM login_attempts WHERE identity_hash = ? AND ip_hash = ?');
        $delete->bind_param('ss', $identityHash, $ipHash);
        $delete->execute();
        unset($_SERVER['REMOTE_ADDR']);
    }
});

integrationTest('Phiên cũ hết quyền ngay khi tài khoản bị khóa', function () use ($db): void {
    $row = $db->query("SELECT id, username, full_name, role FROM users WHERE role = 'volunteer' AND active = 1 ORDER BY id LIMIT 1")->fetch_assoc();
    integrationAssert((bool) $row, 'Thiếu tài khoản để thử phiên');
    $id = (int) $row['id'];
    $_SESSION['user'] = ['id' => $id, 'username' => $row['username'], 'full_name' => $row['full_name'], 'role' => $row['role']];
    $_SESSION['last_activity_at'] = time();
    $db->begin_transaction();
    try {
        $db->query("UPDATE users SET active = 0 WHERE id = $id");
        integrationAssert((new Auth($db))->user() === null, 'Phiên vẫn còn hiệu lực sau khi khóa tài khoản');
    } finally {
        $db->rollback();
        unset($_SESSION['user'], $_SESSION['last_activity_at']);
    }
});

integrationTest('Snapshot MySQL sinh lịch hợp lệ bằng cả hai thuật toán', function () use ($db): void {
    $eventId = (int) $db->query('SELECT id FROM events ORDER BY id LIMIT 1')->fetch_assoc()['id'];
    $snapshot = (new SnapshotBuilder($db))->forEvent($eventId);
    integrationAssert(count($snapshot['shifts']) > 0, 'Snapshot không có ca');
    integrationAssert(count($snapshot['volunteers']) > 0, 'Snapshot không có người đăng ký');
    $checker = new EligibilityChecker();
    $baseline = (new RegistrationOrderAllocator($checker))->allocate($snapshot);
    $improved = (new ScarcityBalancedAllocator($checker))->allocate($snapshot);
    integrationAssert($baseline['validation']['valid'], 'Baseline tạo lịch vi phạm');
    integrationAssert($improved['validation']['valid'], 'Thuật toán cải tiến tạo lịch vi phạm');
});

integrationTest('Tổng hợp báo cáo không nhân bản số vị trí do JOIN', function () use ($db): void {
    $rows = (new ReportRepository($db))->summary();
    integrationAssert($rows !== [], 'Báo cáo rỗng');
    foreach ($rows as $row) {
        integrationAssert((int) $row['required_positions'] >= (int) $row['assigned_positions'], 'Số phân công vượt số vị trí cần trong báo cáo');
    }
});

fwrite(STDOUT, "Kết quả integration: {$passed} đạt, {$failed} lỗi.\n");
exit($failed === 0 ? 0 : 1);
