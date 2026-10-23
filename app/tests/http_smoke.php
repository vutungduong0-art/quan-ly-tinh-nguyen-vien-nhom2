<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Database.php';
require_once __DIR__ . '/TestDatabase.php';

$baseUrl = $argv[1] ?? 'http://127.0.0.1:8106';
$cookie = '';
$passed = 0;
$failed = 0;

function httpRequest(string $method, string $url, array $data, string &$cookie): array
{
    $headers = ['Content-Type: application/x-www-form-urlencoded'];
    if ($cookie !== '') {
        $headers[] = 'Cookie: ' . $cookie;
    }
    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'content' => $method === 'POST' ? http_build_query($data) : '',
            'ignore_errors' => true,
            'follow_location' => 0,
            'timeout' => 10,
        ],
    ]);
    $body = file_get_contents($url, false, $context);
    $responseHeaders = $http_response_header ?? [];
    $status = 0;
    $location = null;
    foreach ($responseHeaders as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $match)) {
            $status = (int) $match[1];
        } elseif (stripos($header, 'Set-Cookie:') === 0) {
            $cookie = trim(explode(';', trim(substr($header, strlen('Set-Cookie:'))), 2)[0]);
        } elseif (stripos($header, 'Location:') === 0) {
            $location = trim(substr($header, strlen('Location:')));
        }
    }
    return ['status' => $status, 'body' => is_string($body) ? $body : '', 'location' => $location, 'headers' => $responseHeaders];
}

function csrfFrom(string $body): string
{
    if (!preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $body, $match)) {
        throw new RuntimeException('Không tìm thấy CSRF token trong trang.');
    }
    return $match[1];
}

function assignmentFormForEvent(string $body, string $eventName): array
{
    foreach (explode('<article', $body) as $article) {
        if (!str_contains(html_entity_decode($article, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $eventName)) {
            continue;
        }
        if (preg_match('/name="assignment_id" value="(\d+)"/', $article, $idMatch)
            && preg_match('/name="version" value="(\d+)"/', $article, $versionMatch)) {
            return ['id' => (int) $idMatch[1], 'version' => (int) $versionMatch[1]];
        }
    }
    return ['id' => 0, 'version' => 0];
}

function checkStep(string $name, callable $callback): mixed
{
    global $passed, $failed;
    try {
        $result = $callback();
        $passed++;
        fwrite(STDOUT, "[PASS] {$name}\n");
        return $result;
    } catch (Throwable $exception) {
        $failed++;
        fwrite(STDOUT, "[FAIL] {$name}: {$exception->getMessage()}\n");
        return null;
    }
}

function expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function hasNestedForms(string $html): bool
{
    preg_match_all('/<\/?form\b[^>]*>/i', $html, $matches);
    $depth = 0;
    foreach ($matches[0] as $tag) {
        if (str_starts_with(strtolower($tag), '</form')) {
            $depth = max(0, $depth - 1);
            continue;
        }
        $depth++;
        if ($depth > 1) {
            return true;
        }
    }
    return false;
}

function cleanupHttpEvent(mysqli $db, int $eventId): void
{
    $datasetIds = array_map('intval', array_column(
        $db->query("SELECT dataset_version_id FROM allocation_runs WHERE event_id = $eventId")->fetch_all(MYSQLI_ASSOC),
        'dataset_version_id'
    ));
    $db->begin_transaction();
    try {
        $db->query("DELETE FROM audit_logs WHERE entity_type = 'event' AND entity_id = $eventId");
        $db->query("DELETE FROM audit_logs WHERE entity_type = 'shift' AND entity_id IN (SELECT id FROM shifts WHERE event_id = $eventId)");
        $db->query("DELETE FROM audit_logs WHERE entity_type = 'allocation_run' AND entity_id IN (SELECT id FROM allocation_runs WHERE event_id = $eventId)");
        $db->query("DELETE FROM audit_logs WHERE entity_type = 'assignment' AND entity_id IN (SELECT a.id FROM assignments a JOIN shifts s ON s.id = a.shift_id WHERE s.event_id = $eventId)");
        $db->query("DELETE rr FROM replacement_requests rr JOIN assignments a ON a.id = rr.assignment_id JOIN shifts s ON s.id = a.shift_id WHERE s.event_id = $eventId");
        $db->query("DELETE a FROM assignments a JOIN shifts s ON s.id = a.shift_id WHERE s.event_id = $eventId");
        $db->query("DELETE FROM allocation_runs WHERE event_id = $eventId");
        $db->query("DELETE rh FROM registration_history rh JOIN registrations r ON r.id = rh.registration_id WHERE r.event_id = $eventId");
        $db->query("DELETE FROM registrations WHERE event_id = $eventId");
        $db->query("DELETE FROM shifts WHERE event_id = $eventId");
        $db->query("DELETE FROM events WHERE id = $eventId AND name LIKE 'Hoạt động kiểm thử HTTP %'");
        foreach ($datasetIds as $datasetId) {
            $db->query("DELETE FROM dataset_versions WHERE id = $datasetId AND NOT EXISTS (SELECT 1 FROM allocation_runs WHERE dataset_version_id = $datasetId)");
        }
        $db->commit();
    } catch (Throwable $exception) {
        $db->rollback();
        throw $exception;
    }
}

$testDatabase = testDatabase();
$testDatabase->close();
$loginPage = httpRequest('GET', $baseUrl . '/?page=login', [], $cookie);
checkStep('Mở trang đăng nhập', function () use ($loginPage): void {
    expect($loginPage['status'] === 200, 'HTTP không phải 200');
    expect(str_contains($loginPage['body'], 'Đăng nhập hệ thống'), 'Thiếu biểu mẫu đăng nhập');
    expect(in_array('X-VolunteerHub-Environment: test', $loginPage['headers'], true), 'HTTP server không chạy với APP_ENV=test');
});

$login = httpRequest('POST', $baseUrl . '/', [
    'csrf_token' => csrfFrom($loginPage['body']),
    'action' => 'login',
    'username' => 'dieuphoi',
    'password' => 'Dieuphoi@123',
], $cookie);
checkStep('Điều phối đăng nhập', function () use ($login): void {
    expect($login['status'] === 303, 'Đăng nhập không chuyển hướng 303');
});

$newPage = httpRequest('GET', $baseUrl . '/?page=new_activity', [], $cookie);
$uniqueName = 'Hoạt động kiểm thử HTTP ' . gmdate('YmdHis');
$created = httpRequest('POST', $baseUrl . '/', [
    'csrf_token' => csrfFrom($newPage['body']),
    'action' => 'create_event',
    'name' => $uniqueName,
    'location' => 'Phòng kiểm thử',
    'start_at' => '2027-06-10T08:00',
    'end_at' => '2027-06-10T12:00',
    'description' => 'Dữ liệu tạo bởi HTTP smoke test trên schema tạm.',
], $cookie);
$createdQuery = [];
parse_str((string) parse_url((string) $created['location'], PHP_URL_QUERY), $createdQuery);
$createdId = (int) ($createdQuery['id'] ?? 0);
checkStep('Điều phối tạo hoạt động', function () use ($created, $createdId): void {
    expect($created['status'] === 303 && $createdId > 0, 'Không nhận được ID hoạt động sau khi tạo');
});

$detail = httpRequest('GET', $baseUrl . '/?page=activity&id=' . $createdId, [], $cookie);
$shift = httpRequest('POST', $baseUrl . '/', [
    'csrf_token' => csrfFrom($detail['body']),
    'action' => 'create_shift',
    'event_id' => $createdId,
    'name' => 'Ca kiểm thử',
    'start_at' => '2027-06-10T08:00',
    'end_at' => '2027-06-10T10:00',
    'required_count' => '2',
], $cookie);
checkStep('Điều phối tạo ca', function () use ($shift): void {
    expect($shift['status'] === 303, 'Tạo ca không chuyển hướng thành công');
});

$managedDetail = httpRequest('GET', $baseUrl . '/?page=activity&id=' . $createdId, [], $cookie);
$createdShiftIdFromPage = preg_match('/name="shift_id" value="(\d+)"/', $managedDetail['body'], $shiftIdMatch)
    ? (int) $shiftIdMatch[1] : 0;
$updatedEvent = httpRequest('POST', $baseUrl . '/', [
    'csrf_token' => csrfFrom($managedDetail['body']),
    'action' => 'update_event',
    'event_id' => $createdId,
    'name' => $uniqueName,
    'location' => 'Phòng kiểm thử đã sửa',
    'start_at' => '2027-06-10T08:00',
    'end_at' => '2027-06-10T12:00',
    'description' => 'Dữ liệu HTTP đã cập nhật.',
], $cookie);
$updatedShift = httpRequest('POST', $baseUrl . '/', [
    'csrf_token' => csrfFrom($managedDetail['body']),
    'action' => 'update_shift',
    'event_id' => $createdId,
    'shift_id' => $createdShiftIdFromPage,
    'name' => 'Ca kiểm thử đã sửa',
    'start_at' => '2027-06-10T08:00',
    'end_at' => '2027-06-10T10:00',
    'required_count' => '2',
], $cookie);
$updatedDetail = httpRequest('GET', $baseUrl . '/?page=activity&id=' . $createdId, [], $cookie);
checkStep('Điều phối sửa hoạt động và ca', function () use ($updatedEvent, $updatedShift, $updatedDetail, $createdShiftIdFromPage): void {
    expect($createdShiftIdFromPage > 0, 'Không tìm thấy ca để sửa');
    expect($updatedEvent['status'] === 303 && $updatedShift['status'] === 303, 'Sửa không qua PRG');
    expect(str_contains($updatedDetail['body'], 'Phòng kiểm thử đã sửa'), 'Thông tin hoạt động chưa đổi');
    expect(str_contains($updatedDetail['body'], 'Ca kiểm thử đã sửa'), 'Tên ca chưa đổi');
});

$invalid = httpRequest('POST', $baseUrl . '/', [
    'csrf_token' => csrfFrom($detail['body']),
    'action' => 'create_shift',
    'event_id' => $createdId,
    'name' => 'Ca dữ liệu sai',
    'start_at' => '2027-06-10T10:00',
    'end_at' => '2027-06-10T09:00',
    'required_count' => '0',
], $cookie);
$invalidPage = httpRequest('GET', $baseUrl . '/?page=activity&id=' . $createdId, [], $cookie);
checkStep('Máy chủ từ chối dữ liệu ca sai', function () use ($invalid, $invalidPage): void {
    expect($invalid['status'] === 303, 'Yêu cầu sai không đi qua PRG');
    expect(str_contains($invalidPage['body'], 'phải là số nguyên dương') && str_contains($invalidPage['body'], 'phải nhỏ hơn'), 'Không hiển thị đủ lỗi validation');
});

$logout = httpRequest('POST', $baseUrl . '/', [
    'csrf_token' => csrfFrom($detail['body']),
    'action' => 'logout',
], $cookie);
checkStep('Điều phối đăng xuất', function () use ($logout): void {
    expect($logout['status'] === 303, 'Đăng xuất không chuyển hướng');
});

$volunteerLoginPage = httpRequest('GET', $baseUrl . '/?page=login', [], $cookie);
$volunteerLogin = httpRequest('POST', $baseUrl . '/', [
    'csrf_token' => csrfFrom($volunteerLoginPage['body']),
    'action' => 'login',
    'username' => 'tinhnguyen',
    'password' => 'Tinhnguyen@123',
], $cookie);
checkStep('Tình nguyện viên đăng nhập', function () use ($volunteerLogin): void {
    expect($volunteerLogin['status'] === 303, 'Đăng nhập tình nguyện viên thất bại');
});

$volunteerDetail = httpRequest('GET', $baseUrl . '/?page=activity&id=' . $createdId, [], $cookie);
$registration = httpRequest('POST', $baseUrl . '/', [
    'csrf_token' => csrfFrom($volunteerDetail['body']),
    'action' => 'register',
    'event_id' => $createdId,
], $cookie);
checkStep('Tình nguyện viên đăng ký hoạt động', function () use ($registration): void {
    expect($registration['status'] === 303, 'Đăng ký không chuyển hướng thành công');
});

$afterRegistration = httpRequest('GET', $baseUrl . '/?page=activity&id=' . $createdId, [], $cookie);
$duplicate = httpRequest('POST', $baseUrl . '/', [
    'csrf_token' => csrfFrom($afterRegistration['body']),
    'action' => 'register',
    'event_id' => $createdId,
], $cookie);
$duplicatePage = httpRequest('GET', $baseUrl . '/?page=activity&id=' . $createdId, [], $cookie);
checkStep('Chặn đăng ký trùng', function () use ($duplicate, $duplicatePage): void {
    expect($duplicate['status'] === 303, 'Đăng ký trùng không dùng PRG');
    expect(str_contains($duplicatePage['body'], 'đã đăng ký hoạt động này trước đó'), 'Thiếu thông báo đăng ký trùng');
});

$forbidden = httpRequest('POST', $baseUrl . '/', [
    'csrf_token' => csrfFrom($duplicatePage['body']),
    'action' => 'create_event',
    'name' => 'Không được phép',
    'location' => 'Không hợp lệ',
    'start_at' => '2027-06-10T08:00',
    'end_at' => '2027-06-10T09:00',
    'description' => '',
], $cookie);
checkStep('Chặn tình nguyện viên gọi handler điều phối', function () use ($forbidden): void {
    expect($forbidden['status'] === 403, 'Handler không trả HTTP 403');
    expect(str_contains($forbidden['body'], 'không có quyền'), 'Thiếu thông báo từ chối quyền');
});

$config = getenv('APP_DB_CONFIG');
checkStep('Dữ liệu còn trong MySQL sau nhiều lượt tải trang', function () use ($config, $uniqueName): void {
    expect(is_string($config) && $config !== '', 'Thiếu APP_DB_CONFIG');
    $db = (new Database($config))->connection();
    $statement = $db->prepare(
        "SELECT COUNT(DISTINCT e.id) AS events, COUNT(DISTINCT s.id) AS shifts, COUNT(DISTINCT r.id) AS registrations
         FROM events e
         LEFT JOIN shifts s ON s.event_id = e.id
         LEFT JOIN registrations r ON r.event_id = e.id
         WHERE e.name = ?"
    );
    $statement->bind_param('s', $uniqueName);
    $statement->execute();
    $row = $statement->get_result()->fetch_assoc();
    expect((int) $row['events'] === 1 && (int) $row['shifts'] === 1 && (int) $row['registrations'] === 1, 'Dữ liệu HTTP không tồn tại đầy đủ trong MySQL');
});

$profilePage = httpRequest('GET', $baseUrl . '/?page=volunteers', [], $cookie);
checkStep('Tình nguyện viên quản lý hồ sơ và lịch rảnh', function () use ($profilePage): void {
    expect($profilePage['status'] === 200, 'Không mở được trang hồ sơ');
    expect(str_contains($profilePage['body'], 'Kỹ năng') && str_contains($profilePage['body'], 'Thêm lịch rảnh'), 'Thiếu chức năng hồ sơ');
    expect(!hasNestedForms($profilePage['body']), 'Trang hồ sơ còn form lồng nhau không hợp lệ');
});

$forbiddenAssignments = httpRequest('GET', $baseUrl . '/?page=assignments', [], $cookie);
checkStep('Chặn tình nguyện viên mở trang phân công', function () use ($forbiddenAssignments): void {
    expect($forbiddenAssignments['status'] === 403, 'Trang phân công không trả 403');
});

$volunteerLogout = httpRequest('POST', $baseUrl . '/', [
    'csrf_token' => csrfFrom($profilePage['body']),
    'action' => 'logout',
], $cookie);
$coordinatorLoginPage = httpRequest('GET', $baseUrl . '/?page=login', [], $cookie);
$coordinatorLogin = httpRequest('POST', $baseUrl . '/', [
    'csrf_token' => csrfFrom($coordinatorLoginPage['body']),
    'action' => 'login',
    'username' => 'dieuphoi',
    'password' => 'Dieuphoi@123',
], $cookie);
checkStep('Điều phối đăng nhập lại để lập lịch', function () use ($volunteerLogout, $coordinatorLogin): void {
    expect($volunteerLogout['status'] === 303 && $coordinatorLogin['status'] === 303, 'Không đổi được vai trò');
});

$assignmentPage = httpRequest('GET', $baseUrl . '/?page=assignments&event_id=' . $createdId, [], $cookie);
$generated = httpRequest('POST', $baseUrl . '/', [
    'csrf_token' => csrfFrom($assignmentPage['body']),
    'action' => 'generate_allocation',
    'event_id' => $createdId,
    'algorithm' => 'scarcity_balanced',
], $cookie);
$draftPage = httpRequest('GET', $baseUrl . '/?page=assignments&event_id=' . $createdId, [], $cookie);
$runId = preg_match('/name="run_id" value="(\d+)"/', $draftPage['body'], $runMatch) ? (int) $runMatch[1] : 0;
checkStep('Sinh phương án cải tiến và lưu snapshot', function () use ($generated, $draftPage, $runId): void {
    expect($generated['status'] === 303, 'Sinh phương án không dùng PRG');
    expect($runId > 0 && str_contains($draftPage['body'], 'scarcity_balanced'), 'Không thấy phương án nháp');
    expect(str_contains($draftPage['body'], '>0</td>') || str_contains($draftPage['body'], '0</td>'), 'Thiếu chỉ số vi phạm');
});

$published = httpRequest('POST', $baseUrl . '/', [
    'csrf_token' => csrfFrom($draftPage['body']),
    'action' => 'publish_allocation',
    'run_id' => $runId,
    'event_id' => $createdId,
], $cookie);
$publishedPage = httpRequest('GET', $baseUrl . '/?page=assignments&event_id=' . $createdId, [], $cookie);
checkStep('Công bố phương án thành lịch phân công', function () use ($published, $publishedPage): void {
    expect($published['status'] === 303, 'Công bố không dùng PRG');
    expect(str_contains($publishedPage['body'], 'published') && str_contains($publishedPage['body'], 'Ca kiểm thử'), 'Không thấy lịch đã công bố');
});

$statsPage = httpRequest('GET', $baseUrl . '/?page=stats', [], $cookie);
checkStep('Báo cáo hiển thị lần chạy và chỉ số', function () use ($statsPage, $uniqueName): void {
    expect($statsPage['status'] === 200, 'Không mở được thống kê');
    expect(str_contains($statsPage['body'], $uniqueName) && str_contains($statsPage['body'], 'scarcity_balanced'), 'Báo cáo thiếu dữ liệu vừa công bố');
});

$coordinatorLogout = httpRequest('POST', $baseUrl . '/', [
    'csrf_token' => csrfFrom($statsPage['body']),
    'action' => 'logout',
], $cookie);
$finalVolunteerLoginPage = httpRequest('GET', $baseUrl . '/?page=login', [], $cookie);
$finalVolunteerLogin = httpRequest('POST', $baseUrl . '/', [
    'csrf_token' => csrfFrom($finalVolunteerLoginPage['body']),
    'action' => 'login',
    'username' => 'tinhnguyen',
    'password' => 'Tinhnguyen@123',
], $cookie);
$myShiftsPage = httpRequest('GET', $baseUrl . '/?page=my_shifts', [], $cookie);
checkStep('Tình nguyện viên xem ca đã công bố', function () use ($coordinatorLogout, $finalVolunteerLogin, $myShiftsPage): void {
    expect($coordinatorLogout['status'] === 303 && $finalVolunteerLogin['status'] === 303, 'Không đăng nhập lại được');
    expect($myShiftsPage['status'] === 200 && str_contains($myShiftsPage['body'], 'Ca kiểm thử'), 'Ca chưa xuất hiện ở lịch cá nhân');
});

$currentAssignment = assignmentFormForEvent($myShiftsPage['body'], $uniqueName);
$confirmed = httpRequest('POST', $baseUrl . '/', [
    'csrf_token' => csrfFrom($myShiftsPage['body']),
    'action' => 'confirm_assignment',
    'assignment_id' => $currentAssignment['id'],
    'version' => $currentAssignment['version'],
], $cookie);
$confirmedPage = httpRequest('GET', $baseUrl . '/?page=my_shifts', [], $cookie);
$assertDb = (new Database((string) $config))->connection();
$confirmedStatus = $currentAssignment['id'] > 0
    ? $assertDb->query('SELECT status FROM assignments WHERE id = ' . (int) $currentAssignment['id'])->fetch_assoc()['status'] ?? null
    : null;
checkStep('Tình nguyện viên xác nhận ca với version chống ghi đè', function () use ($confirmed, $confirmedStatus): void {
    expect($confirmed['status'] === 303, 'Xác nhận ca không dùng PRG');
    expect($confirmedStatus === 'confirmed', 'Trạng thái ca chưa được xác nhận');
});

$withdrawBlocked = httpRequest('POST', $baseUrl . '/', [
    'csrf_token' => csrfFrom($confirmedPage['body']),
    'action' => 'withdraw',
    'event_id' => $createdId,
], $cookie);
$withdrawBlockedPage = httpRequest('GET', $baseUrl . '/?page=activity&id=' . $createdId, [], $cookie);
checkStep('Chặn rút đăng ký khi đang có ca hoạt động', function () use ($withdrawBlocked, $withdrawBlockedPage): void {
    expect($withdrawBlocked['status'] === 303, 'Rút đăng ký không dùng PRG');
    expect(str_contains($withdrawBlockedPage['body'], 'Không thể rút đăng ký'), 'Không chặn rút đăng ký đang có ca');
});

$confirmedAssignment = assignmentFormForEvent($confirmedPage['body'], $uniqueName);
$reportedAbsent = httpRequest('POST', $baseUrl . '/', [
    'csrf_token' => csrfFrom($confirmedPage['body']),
    'action' => 'report_absent',
    'assignment_id' => $confirmedAssignment['id'],
    'version' => $confirmedAssignment['version'],
    'reason' => 'Bận đột xuất trong kiểm thử',
], $cookie);
$afterAbsent = httpRequest('GET', $baseUrl . '/?page=my_shifts', [], $cookie);
$absentRow = $confirmedAssignment['id'] > 0
    ? $assertDb->query('SELECT status, (SELECT COUNT(*) FROM replacement_requests rr WHERE rr.assignment_id = assignments.id AND rr.status = "open") AS open_requests FROM assignments WHERE id = ' . (int) $confirmedAssignment['id'])->fetch_assoc()
    : null;
checkStep('Tình nguyện viên báo vắng ca của chính mình', function () use ($reportedAbsent, $absentRow): void {
    expect($reportedAbsent['status'] === 303, 'Báo vắng không dùng PRG');
    expect(($absentRow['status'] ?? null) === 'absent' && (int) ($absentRow['open_requests'] ?? 0) === 1, 'Ca chưa chuyển sang vắng hoặc thiếu yêu cầu thay');
});

$setupDb = (new Database((string) $config))->connection();
$candidateRows = $setupDb->query("SELECT id, username FROM users WHERE username IN ('tinhnguyen02','tinhnguyen03','tinhnguyen04') ORDER BY username")->fetch_all(MYSQLI_ASSOC);
$candidateIds = [];
$registerCandidate = $setupDb->prepare(
    "INSERT INTO registrations (event_id, volunteer_id, status, registered_at) VALUES (?, ?, 'registered', UTC_TIMESTAMP())
     ON DUPLICATE KEY UPDATE status = 'registered', registered_at = VALUES(registered_at)"
);
foreach ($candidateRows as $candidateRow) {
    $candidateId = (int) $candidateRow['id'];
    $candidateIds[$candidateRow['username']] = $candidateId;
    $registerCandidate->bind_param('ii', $createdId, $candidateId);
    $registerCandidate->execute();
}
$shiftRow = $setupDb->query('SELECT id FROM shifts WHERE event_id = ' . (int) $createdId . ' ORDER BY id LIMIT 1')->fetch_assoc();
$createdShiftId = (int) ($shiftRow['id'] ?? 0);

$absentLogout = httpRequest('POST', $baseUrl . '/', [
    'csrf_token' => csrfFrom($afterAbsent['body']),
    'action' => 'logout',
], $cookie);
$replacementLoginPage = httpRequest('GET', $baseUrl . '/?page=login', [], $cookie);
$replacementLogin = httpRequest('POST', $baseUrl . '/', [
    'csrf_token' => csrfFrom($replacementLoginPage['body']),
    'action' => 'login',
    'username' => 'dieuphoi',
    'password' => 'Dieuphoi@123',
], $cookie);
$replacementPage = httpRequest('GET', $baseUrl . '/?page=assignments&event_id=' . $createdId, [], $cookie);
$requestId = preg_match('/name="request_id" value="(\d+)"/', $replacementPage['body'], $requestMatch) ? (int) $requestMatch[1] : 0;
checkStep('Điều phối thấy yêu cầu thay người sau báo vắng', function () use ($absentLogout, $replacementLogin, $replacementPage, $requestId): void {
    expect($absentLogout['status'] === 303 && $replacementLogin['status'] === 303, 'Không đổi lại được vai trò điều phối');
    expect($requestId > 0 && str_contains($replacementPage['body'], 'Bận đột xuất trong kiểm thử'), 'Thiếu yêu cầu thay người');
});

$replaced = httpRequest('POST', $baseUrl . '/', [
    'csrf_token' => csrfFrom($replacementPage['body']),
    'action' => 'replace_absent',
    'request_id' => $requestId,
    'volunteer_id' => $candidateIds['tinhnguyen02'] ?? 0,
    'event_id' => $createdId,
], $cookie);
$afterReplacement = httpRequest('GET', $baseUrl . '/?page=assignments&event_id=' . $createdId, [], $cookie);
checkStep('Thay người vắng hợp lệ trong một giao dịch', function () use ($replaced, $afterReplacement): void {
    expect($replaced['status'] === 303, 'Thay người không dùng PRG');
    expect(str_contains($afterReplacement['body'], 'Lê Hoàng Nam'), 'Người thay chưa xuất hiện trong lịch');
    expect(str_contains($afterReplacement['body'], 'Không có yêu cầu thay người'), 'Yêu cầu thay chưa được đóng');
});

$manualSecond = httpRequest('POST', $baseUrl . '/', [
    'csrf_token' => csrfFrom($afterReplacement['body']),
    'action' => 'manual_assign',
    'event_id' => $createdId,
    'shift_id' => $createdShiftId,
    'volunteer_id' => $candidateIds['tinhnguyen03'] ?? 0,
], $cookie);
$afterSecond = httpRequest('GET', $baseUrl . '/?page=assignments&event_id=' . $createdId, [], $cookie);
$overCapacityAttempt = httpRequest('POST', $baseUrl . '/', [
    'csrf_token' => csrfFrom($afterSecond['body']),
    'action' => 'manual_assign',
    'event_id' => $createdId,
    'shift_id' => $createdShiftId,
    'volunteer_id' => $candidateIds['tinhnguyen04'] ?? 0,
], $cookie);
$overCapacityPage = httpRequest('GET', $baseUrl . '/?page=assignments&event_id=' . $createdId, [], $cookie);
$activeCountStatement = $setupDb->prepare(
    "SELECT COUNT(*) AS total FROM assignments WHERE shift_id = ? AND status IN ('assigned','confirmed')"
);
$activeCountStatement->bind_param('i', $createdShiftId);
$activeCountStatement->execute();
$activeCount = (int) $activeCountStatement->get_result()->fetch_assoc()['total'];
checkStep('POST thứ ba bị chặn khi ca cần hai người đã đủ', function () use ($manualSecond, $overCapacityAttempt, $overCapacityPage, $activeCount): void {
    expect($manualSecond['status'] === 303 && $overCapacityAttempt['status'] === 303, 'Phân công thủ công không dùng PRG');
    expect(str_contains($overCapacityPage['body'], 'Ca đã đủ số người cần'), 'Thiếu thông báo vượt sức chứa');
    expect($activeCount === 2, 'Dữ liệu đã vượt quá hai người cần');
});

$csv = httpRequest('GET', $baseUrl . '/?page=export&type=assignments&event_id=' . $createdId, [], $cookie);
checkStep('Xuất CSV UTF-8 khớp lịch đang hoạt động', function () use ($csv): void {
    expect($csv['status'] === 200, 'Xuất CSV không trả 200');
    expect(str_starts_with($csv['body'], "\xEF\xBB\xBF"), 'CSV thiếu UTF-8 BOM');
    expect(str_contains($csv['body'], 'Ca kiểm thử') && str_contains($csv['body'], 'Lê Hoàng Nam'), 'CSV thiếu dữ liệu phân công');
});

$badCsrf = httpRequest('POST', $baseUrl . '/', [
    'csrf_token' => 'sai-token',
    'action' => 'manual_assign',
    'event_id' => $createdId,
    'shift_id' => $createdShiftId,
    'volunteer_id' => $candidateIds['tinhnguyen04'] ?? 0,
], $cookie);
checkStep('POST sai CSRF bị từ chối trước khi đổi dữ liệu', function () use ($badCsrf, $setupDb, $createdShiftId): void {
    expect($badCsrf['status'] === 419, 'CSRF sai không trả 419');
    $count = (int) $setupDb->query("SELECT COUNT(*) AS total FROM assignments WHERE shift_id = $createdShiftId AND status IN ('assigned','confirmed')")->fetch_assoc()['total'];
    expect($count === 2, 'POST sai CSRF vẫn thay đổi dữ liệu');
});

checkStep('Dọn đúng dữ liệu tạm do HTTP test tạo', function () use ($setupDb, $createdId): void {
    cleanupHttpEvent($setupDb, $createdId);
    $remaining = (int) $setupDb->query("SELECT COUNT(*) AS total FROM events WHERE id = $createdId")->fetch_assoc()['total'];
    expect($remaining === 0, 'Dữ liệu tạm hiện tại chưa được dọn');
});

fwrite(STDOUT, "Kết quả HTTP: {$passed} đạt, {$failed} lỗi.\n");
exit($failed === 0 ? 0 : 1);
