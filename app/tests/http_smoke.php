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
    $auditIds = array_map('intval', array_column($db->query(
        "SELECT al.id FROM audit_logs al WHERE al.entity_type = 'event' AND al.entity_id = $eventId
         UNION SELECT al.id FROM audit_logs al JOIN shifts s ON s.id = al.entity_id
               WHERE al.entity_type = 'shift' AND s.event_id = $eventId
         UNION SELECT al.id FROM audit_logs al JOIN allocation_runs ar ON ar.id = al.entity_id
               WHERE al.entity_type = 'allocation_run' AND ar.event_id = $eventId
         UNION SELECT al.id FROM audit_logs al JOIN assignments a ON a.id = al.entity_id
               JOIN shifts s ON s.id = a.shift_id WHERE al.entity_type = 'assignment' AND s.event_id = $eventId
         UNION SELECT al.id FROM audit_logs al JOIN replacement_requests rr ON rr.id = al.entity_id
               JOIN assignments a ON a.id = rr.assignment_id JOIN shifts s ON s.id = a.shift_id
               WHERE al.action = 'replacement.reopened' AND s.event_id = $eventId"
    )->fetch_all(MYSQLI_ASSOC), 'id'));
    $db->begin_transaction();
    try {
        if ($auditIds !== []) {
            $db->query('DELETE FROM audit_logs WHERE id IN (' . implode(',', $auditIds) . ')');
        }
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
        if ($auditIds !== []) {
            $remainingAudits = (int) $db->query(
                'SELECT COUNT(*) AS total FROM audit_logs WHERE id IN (' . implode(',', $auditIds) . ')'
            )->fetch_assoc()['total'];
            if ($remainingAudits !== 0) {
                throw new RuntimeException('Nhật ký audit của dữ liệu HTTP tạm chưa được dọn hết.');
            }
        }
    } catch (Throwable $exception) {
        $db->rollback();
        throw $exception;
    }
}

$testDatabase = testDatabase();
$testDatabase->close();
$config = getenv('APP_DB_CONFIG');
if (!is_string($config) || $config === '') {
    throw new RuntimeException('Thiếu APP_DB_CONFIG cho HTTP smoke test.');
}
$httpDb = (new Database($config))->connection();

$loginPage = httpRequest('GET', $baseUrl . '/?page=login', [], $cookie);
checkStep('Mở trang đăng nhập', function () use ($loginPage): void {
    expect($loginPage['status'] === 200, 'HTTP không phải 200');
    expect(str_contains($loginPage['body'], 'Đăng nhập hệ thống'), 'Thiếu biểu mẫu đăng nhập');
    expect(in_array('X-VolunteerHub-Environment: test', $loginPage['headers'], true), 'HTTP server không chạy với APP_ENV=test');
});

$signupPage = httpRequest('GET', $baseUrl . '/?page=signup', [], $cookie);
checkStep('Khách thấy đăng ký tài khoản và footer phiên bản', function () use ($signupPage): void {
    expect($signupPage['status'] === 200 && str_contains($signupPage['body'], 'Đăng ký tình nguyện viên'), 'Không mở được biểu mẫu đăng ký');
    expect(str_contains($signupPage['body'], 'Phiên bản 0.4.0-demo'), 'Footer thiếu phiên bản hiện tại');
    expect(!str_contains($signupPage['body'], '>Phân công</a>') && !str_contains($signupPage['body'], '>Thống kê</a>'), 'Khách vẫn thấy menu nội bộ');
});

$invalidSignup = httpRequest('POST', $baseUrl . '/', [
    'csrf_token' => csrfFrom($signupPage['body']),
    'action' => 'signup',
    'full_name' => 'Tài khoản kiểm thử',
    'email' => 'khong-hop-le',
    'username' => 'x',
    'password' => 'ngan',
    'password_confirmation' => 'khac',
], $cookie);
$invalidSignupPage = httpRequest('GET', $baseUrl . '/?page=signup', [], $cookie);
checkStep('Máy chủ từ chối dữ liệu đăng ký tài khoản sai', function () use ($invalidSignup, $invalidSignupPage): void {
    expect($invalidSignup['status'] === 303, 'Đăng ký sai không dùng PRG');
    expect(str_contains($invalidSignupPage['body'], 'Email không đúng định dạng')
        && str_contains($invalidSignupPage['body'], 'Mật khẩu nhập lại không khớp'), 'Thiếu thông báo validation đăng ký');
});

$signupSuffix = gmdate('His') . bin2hex(random_bytes(2));
$signupUsername = 'http_tnv_' . $signupSuffix;
$signupEmail = $signupUsername . '@example.test';
$signupPassword = 'TnvHttp@123';
$validSignupPage = httpRequest('GET', $baseUrl . '/?page=signup', [], $cookie);
$createdAccount = httpRequest('POST', $baseUrl . '/', [
    'csrf_token' => csrfFrom($validSignupPage['body']),
    'action' => 'signup',
    'full_name' => 'Tình nguyện viên HTTP',
    'email' => $signupEmail,
    'username' => $signupUsername,
    'password' => $signupPassword,
    'password_confirmation' => $signupPassword,
], $cookie);
$createdAccountRow = $httpDb->query(
    "SELECT u.id, u.role, u.password_hash, vp.volunteer_code, vp.email
     FROM users u JOIN volunteer_profiles vp ON vp.user_id = u.id
     WHERE u.username = '" . $httpDb->real_escape_string($signupUsername) . "'"
)->fetch_assoc();
checkStep('Tạo tài khoản volunteer với mật khẩu băm và hồ sơ mặc định', function () use ($createdAccount, $createdAccountRow, $signupEmail, $signupPassword): void {
    expect($createdAccount['status'] === 303 && str_contains((string) $createdAccount['location'], 'page=login'), 'Tạo tài khoản không chuyển về đăng nhập');
    expect(($createdAccountRow['role'] ?? null) === 'volunteer', 'Tài khoản tự đăng ký không có role volunteer');
    expect(password_verify($signupPassword, (string) ($createdAccountRow['password_hash'] ?? '')), 'Mật khẩu không được băm bằng password_hash');
    expect(str_starts_with((string) ($createdAccountRow['volunteer_code'] ?? ''), 'TNV'), 'Không sinh mã tình nguyện viên');
    expect(($createdAccountRow['email'] ?? null) === $signupEmail, 'Không lưu email hồ sơ');
});

$newLoginPage = httpRequest('GET', $baseUrl . '/?page=login', [], $cookie);
$newLogin = httpRequest('POST', $baseUrl . '/', [
    'csrf_token' => csrfFrom($newLoginPage['body']),
    'action' => 'login',
    'username' => $signupUsername,
    'password' => $signupPassword,
], $cookie);
$newVolunteerHome = httpRequest('GET', $baseUrl . '/', [], $cookie);
$newVolunteerForbiddenAssignments = httpRequest('GET', $baseUrl . '/?page=assignments', [], $cookie);
$newVolunteerForbiddenStats = httpRequest('GET', $baseUrl . '/?page=stats', [], $cookie);
checkStep('Menu volunteer đúng vai trò và route điều phối trả 403', function () use ($newLogin, $newVolunteerHome, $newVolunteerForbiddenAssignments, $newVolunteerForbiddenStats): void {
    expect($newLogin['status'] === 303 && $newVolunteerHome['status'] === 200, 'Tài khoản mới không đăng nhập được');
    expect(str_contains($newVolunteerHome['body'], '>Hồ sơ</a>') && str_contains($newVolunteerHome['body'], '>Ca của tôi</a>'), 'Menu volunteer thiếu chức năng cá nhân');
    expect(!str_contains($newVolunteerHome['body'], '>Phân công</a>') && !str_contains($newVolunteerHome['body'], '>Thống kê</a>'), 'Menu volunteer làm lộ chức năng điều phối');
    expect($newVolunteerForbiddenAssignments['status'] === 403 && $newVolunteerForbiddenStats['status'] === 403, 'Route điều phối không chặn volunteer');
});

$newVolunteerLogout = httpRequest('POST', $baseUrl . '/', [
    'csrf_token' => csrfFrom($newVolunteerHome['body']),
    'action' => 'logout',
], $cookie);
$duplicateSignupPage = httpRequest('GET', $baseUrl . '/?page=signup', [], $cookie);
$duplicateSignup = httpRequest('POST', $baseUrl . '/', [
    'csrf_token' => csrfFrom($duplicateSignupPage['body']),
    'action' => 'signup',
    'full_name' => 'Email bị trùng',
    'email' => $signupEmail,
    'username' => $signupUsername . '_khac',
    'password' => $signupPassword,
    'password_confirmation' => $signupPassword,
], $cookie);
$duplicateSignupResult = httpRequest('GET', $baseUrl . '/?page=signup', [], $cookie);
checkStep('Chặn email trùng bằng ràng buộc cơ sở dữ liệu', function () use ($newVolunteerLogout, $duplicateSignup, $duplicateSignupResult): void {
    expect($newVolunteerLogout['status'] === 303 && $duplicateSignup['status'] === 303, 'Luồng đăng xuất/đăng ký trùng không qua PRG');
    expect(str_contains($duplicateSignupResult['body'], 'đã được sử dụng'), 'Thiếu thông báo email trùng');
});

$signupUserId = (int) ($createdAccountRow['id'] ?? 0);
if ($signupUserId > 0) {
    $httpDb->query("DELETE FROM volunteer_profiles WHERE user_id = $signupUserId");
    $httpDb->query("DELETE FROM users WHERE id = $signupUserId");
}

$login = httpRequest('POST', $baseUrl . '/', [
    'csrf_token' => csrfFrom($duplicateSignupResult['body']),
    'action' => 'login',
    'username' => 'dieuphoi',
    'password' => 'Dieuphoi@123',
], $cookie);
checkStep('Điều phối đăng nhập', function () use ($login): void {
    expect($login['status'] === 303, 'Đăng nhập không chuyển hướng 303');
});

$seedVolunteerId = (int) $httpDb->query("SELECT id FROM users WHERE role = 'volunteer' ORDER BY id LIMIT 1")->fetch_assoc()['id'];
$coordinatorVolunteerPage = httpRequest('GET', $baseUrl . '/?page=volunteers&id=' . $seedVolunteerId, [], $cookie);
$coordinatorSignupPage = httpRequest('GET', $baseUrl . '/?page=signup', [], $cookie);
checkStep('Điều phối thấy menu nội bộ và hồ sơ trong modal', function () use ($coordinatorVolunteerPage, $coordinatorSignupPage): void {
    expect($coordinatorVolunteerPage['status'] === 200, 'Không mở được danh sách tình nguyện viên');
    expect($coordinatorSignupPage['status'] === 403, 'Tài khoản đã đăng nhập vẫn mở được trang tự đăng ký');
    expect(str_contains($coordinatorVolunteerPage['body'], '>Tình nguyện viên</a>')
        && str_contains($coordinatorVolunteerPage['body'], '>Phân công</a>')
        && str_contains($coordinatorVolunteerPage['body'], '>Thống kê</a>'), 'Menu điều phối thiếu chức năng nội bộ');
    expect(!str_contains($coordinatorVolunteerPage['body'], '>Ca của tôi</a>'), 'Điều phối vẫn thấy menu Ca của tôi');
    expect(str_contains($coordinatorVolunteerPage['body'], '<dialog class="profile-dialog"')
        && str_contains($coordinatorVolunteerPage['body'], 'data-profile-dialog'), 'Hồ sơ chưa được render trong modal');
    expect(!hasNestedForms($coordinatorVolunteerPage['body']), 'Modal hồ sơ có form lồng nhau không hợp lệ');
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
$profileDb = (new Database((string) $config))->connection();
$profile = $profileDb->query(
    "SELECT u.id, u.full_name, COALESCE(vp.volunteer_code, u.username) AS volunteer_code,
            COALESCE(vp.phone, '') AS phone, COALESCE(vp.email, '') AS email, COALESCE(vp.notes, '') AS notes
     FROM users u LEFT JOIN volunteer_profiles vp ON vp.user_id = u.id WHERE u.username = 'tinhnguyen'"
)->fetch_assoc();
$profileSkillIds = array_map('intval', array_column(
    $profileDb->query('SELECT skill_id FROM volunteer_skills WHERE volunteer_id = ' . (int) $profile['id'])->fetch_all(MYSQLI_ASSOC),
    'skill_id'
));
$savedProfile = httpRequest('POST', $baseUrl . '/', [
    'csrf_token' => csrfFrom($profilePage['body']),
    'action' => 'save_profile',
    'full_name' => $profile['full_name'],
    'volunteer_code' => $profile['volunteer_code'],
    'phone' => $profile['phone'],
    'email' => $profile['email'],
    'notes' => $profile['notes'],
], $cookie);
$savedSkills = httpRequest('POST', $baseUrl . '/', [
    'csrf_token' => csrfFrom($profilePage['body']),
    'action' => 'save_skills',
    'skill_ids' => $profileSkillIds,
], $cookie);
$addedAvailability = httpRequest('POST', $baseUrl . '/', [
    'csrf_token' => csrfFrom($profilePage['body']),
    'action' => 'add_availability',
    'start_at' => '2038-02-01T08:00',
    'end_at' => '2038-02-01T10:00',
], $cookie);
$temporaryAvailability = $profileDb->query(
    "SELECT id FROM availability WHERE volunteer_id = " . (int) $profile['id']
    . " AND start_at = '2038-02-01 01:00:00' AND end_at = '2038-02-01 03:00:00' ORDER BY id DESC LIMIT 1"
)->fetch_assoc();
$profileAfterAdd = httpRequest('GET', $baseUrl . '/?page=volunteers', [], $cookie);
$deletedAvailability = httpRequest('POST', $baseUrl . '/', [
    'csrf_token' => csrfFrom($profileAfterAdd['body']),
    'action' => 'delete_availability',
    'slot_id' => (int) ($temporaryAvailability['id'] ?? 0),
], $cookie);
$temporaryAvailabilityCount = (int) $profileDb->query(
    "SELECT COUNT(*) AS total FROM availability WHERE volunteer_id = " . (int) $profile['id']
    . " AND start_at = '2038-02-01 01:00:00' AND end_at = '2038-02-01 03:00:00'"
)->fetch_assoc()['total'];
checkStep('Tình nguyện viên thật sự lưu hồ sơ, kỹ năng và thêm/xóa lịch rảnh', function () use (
    $profilePage,
    $savedProfile,
    $savedSkills,
    $addedAvailability,
    $deletedAvailability,
    $temporaryAvailability,
    $temporaryAvailabilityCount
): void {
    expect($profilePage['status'] === 200, 'Không mở được trang hồ sơ');
    expect(str_contains($profilePage['body'], 'Kỹ năng') && str_contains($profilePage['body'], 'Thêm lịch rảnh'), 'Thiếu chức năng hồ sơ');
    expect(!hasNestedForms($profilePage['body']), 'Trang hồ sơ còn form lồng nhau không hợp lệ');
    expect($savedProfile['status'] === 303 && $savedSkills['status'] === 303, 'Lưu hồ sơ/kỹ năng không qua PRG');
    expect($addedAvailability['status'] === 303 && (int) ($temporaryAvailability['id'] ?? 0) > 0, 'Không lưu được lịch rảnh qua HTTP');
    expect($deletedAvailability['status'] === 303 && $temporaryAvailabilityCount === 0, 'Không xóa được lịch rảnh vừa tạo');
});

$forbiddenAssignments = httpRequest('GET', $baseUrl . '/?page=assignments', [], $cookie);
checkStep('Chặn tình nguyện viên mở trang phân công', function () use ($forbiddenAssignments): void {
    expect($forbiddenAssignments['status'] === 403, 'Trang phân công không trả 403');
});

$forbiddenComparison = httpRequest('POST', $baseUrl . '/', [
    'csrf_token' => csrfFrom($profilePage['body']),
    'action' => 'compare_allocation',
    'event_id' => $createdId,
], $cookie);
checkStep('Chặn tình nguyện viên gọi action đối chứng mới', function () use ($forbiddenComparison): void {
    expect($forbiddenComparison['status'] === 403, 'Action đối chứng không kiểm tra vai trò ở máy chủ');
});

$forbiddenShiftExport = httpRequest('GET', $baseUrl . '/?page=export&type=shift_status&event_id=' . $createdId, [], $cookie);
checkStep('Chặn tình nguyện viên xuất báo cáo tình trạng ca', function () use ($forbiddenShiftExport): void {
    expect($forbiddenShiftExport['status'] === 403, 'Xuất báo cáo ca không kiểm tra vai trò');
    expect(!str_starts_with($forbiddenShiftExport['body'], "\xEF\xBB\xBF"), 'Phản hồi 403 vẫn làm lộ nội dung CSV');
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
$compared = httpRequest('POST', $baseUrl . '/', [
    'csrf_token' => csrfFrom($assignmentPage['body']),
    'action' => 'compare_allocation',
    'event_id' => $createdId,
], $cookie);
$comparisonPage = httpRequest('GET', $baseUrl . '/?page=assignments&event_id=' . $createdId, [], $cookie);
$comparisonDb = (new Database((string) $config))->connection();
$comparisonRows = $comparisonDb->query(
    "SELECT dataset_version_id, comparison_group, project_version, algorithm, status
     FROM allocation_runs WHERE event_id = $createdId AND comparison_group IS NOT NULL ORDER BY id"
)->fetch_all(MYSQLI_ASSOC);
checkStep('HTTP tạo cặp đối chứng cùng snapshot và không cho công bố experiment', function () use ($compared, $comparisonPage, $comparisonRows): void {
    expect($compared['status'] === 303, 'Đối chứng không dùng PRG');
    expect(count($comparisonRows) === 2, 'Không lưu đúng hai bản chạy đối chứng');
    expect($comparisonRows[0]['dataset_version_id'] === $comparisonRows[1]['dataset_version_id'], 'Hai bản chạy không dùng cùng dataset');
    expect($comparisonRows[0]['comparison_group'] !== null && $comparisonRows[0]['comparison_group'] === $comparisonRows[1]['comparison_group'], 'Hai bản chạy không cùng comparison_group');
    expect($comparisonRows[0]['status'] === 'experiment' && $comparisonRows[1]['status'] === 'experiment', 'Bản đối chứng không ở trạng thái experiment');
    expect($comparisonRows[0]['project_version'] !== 'unknown', 'Không lưu phiên bản mã');
    expect(str_contains($comparisonPage['body'], 'registration_order') && str_contains($comparisonPage['body'], 'scarcity_balanced'), 'UI không hiển thị đủ hai thuật toán');
    expect(substr_count($comparisonPage['body'], 'name="action" value="publish_allocation"') === 0, 'UI cho phép công bố bản experiment');
});

$generated = httpRequest('POST', $baseUrl . '/', [
    'csrf_token' => csrfFrom($comparisonPage['body']),
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
    expect(str_contains($statsPage['body'], 'Cặp đối chứng hợp lệ') && str_contains($statsPage['body'], 'điểm %'), 'Báo cáo chưa ghép cặp A/B cùng snapshot');
    expect(str_contains($statsPage['body'], 'Tình trạng từng ca'), 'Thiếu báo cáo vận hành theo ca');
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

$transferSource = $setupDb->query(
    'SELECT id, version FROM assignments WHERE shift_id = ' . $createdShiftId
    . ' AND volunteer_id = ' . (int) ($candidateIds['tinhnguyen02'] ?? 0)
    . " AND status IN ('assigned','confirmed')"
)->fetch_assoc();
$transferredHttp = httpRequest('POST', $baseUrl . '/', [
    'csrf_token' => csrfFrom($overCapacityPage['body']),
    'action' => 'transfer_assignment',
    'assignment_id' => (int) ($transferSource['id'] ?? 0),
    'version' => (int) ($transferSource['version'] ?? 0),
    'volunteer_id' => $candidateIds['tinhnguyen04'] ?? 0,
    'event_id' => $createdId,
], $cookie);
$afterTransferPage = httpRequest('GET', $baseUrl . '/?page=assignments&event_id=' . $createdId, [], $cookie);
$transferSourceStatus = $transferSource
    ? $setupDb->query('SELECT status FROM assignments WHERE id = ' . (int) $transferSource['id'])->fetch_assoc()['status'] ?? null
    : null;
$transferTarget = $setupDb->query(
    'SELECT id, version, status FROM assignments WHERE shift_id = ' . $createdShiftId
    . ' AND volunteer_id = ' . (int) ($candidateIds['tinhnguyen04'] ?? 0)
)->fetch_assoc();
$activeAfterTransfer = (int) $setupDb->query(
    "SELECT COUNT(*) AS total FROM assignments WHERE shift_id = $createdShiftId AND status IN ('assigned','confirmed')"
)->fetch_assoc()['total'];
checkStep('HTTP chuyển phân công nguyên tử và giữ nguyên sức chứa', function () use ($transferredHttp, $transferSourceStatus, $transferTarget, $activeAfterTransfer): void {
    expect($transferredHttp['status'] === 303, 'Chuyển phân công không dùng PRG');
    expect($transferSourceStatus === 'cancelled', 'Phân công nguồn chưa được hủy');
    expect(($transferTarget['status'] ?? null) === 'assigned', 'Phân công đích chưa được kích hoạt');
    expect($activeAfterTransfer === 2, 'Chuyển người làm thay đổi số người đang hiệu lực');
});

$markedAbsentByCoordinator = httpRequest('POST', $baseUrl . '/', [
    'csrf_token' => csrfFrom($afterTransferPage['body']),
    'action' => 'mark_absent',
    'assignment_id' => (int) ($transferTarget['id'] ?? 0),
    'version' => (int) ($transferTarget['version'] ?? 0),
    'event_id' => $createdId,
    'reason' => 'Vắng lần hai để kiểm thử unfilled',
], $cookie);
$afterCoordinatorAbsent = httpRequest('GET', $baseUrl . '/?page=assignments&event_id=' . $createdId, [], $cookie);
$secondRequest = $transferTarget
    ? $setupDb->query('SELECT id, status FROM replacement_requests WHERE assignment_id = ' . (int) $transferTarget['id'] . ' ORDER BY id DESC LIMIT 1')->fetch_assoc()
    : null;
checkStep('Điều phối báo vắng tạo yêu cầu thay người thứ hai', function () use ($markedAbsentByCoordinator, $secondRequest): void {
    expect($markedAbsentByCoordinator['status'] === 303, 'Báo vắng của điều phối không dùng PRG');
    expect((int) ($secondRequest['id'] ?? 0) > 0 && ($secondRequest['status'] ?? null) === 'open', 'Không tạo yêu cầu thay người mở');
});

$reassignAbsent = httpRequest('POST', $baseUrl . '/', [
    'csrf_token' => csrfFrom($afterCoordinatorAbsent['body']),
    'action' => 'manual_assign',
    'event_id' => $createdId,
    'shift_id' => $createdShiftId,
    'volunteer_id' => $candidateIds['tinhnguyen04'] ?? 0,
], $cookie);
$reassignAbsentPage = httpRequest('GET', $baseUrl . '/?page=assignments&event_id=' . $createdId, [], $cookie);
$absentAssignmentStatus = $transferTarget
    ? $setupDb->query('SELECT status FROM assignments WHERE id = ' . (int) $transferTarget['id'])->fetch_assoc()['status'] ?? null
    : null;
checkStep('HTTP chặn phân lại người đã báo vắng vào cùng ca', function () use ($reassignAbsent, $reassignAbsentPage, $absentAssignmentStatus): void {
    expect($reassignAbsent['status'] === 303, 'Phân lại người vắng không dùng PRG');
    expect(str_contains($reassignAbsentPage['body'], 'Đã báo vắng ca này'), 'Thiếu lý do từ chối người đã báo vắng');
    expect($absentAssignmentStatus === 'absent', 'Phân công vắng bị kích hoạt lại');
});

$markedUnfilled = httpRequest('POST', $baseUrl . '/', [
    'csrf_token' => csrfFrom($reassignAbsentPage['body']),
    'action' => 'mark_replacement_unfilled',
    'request_id' => (int) ($secondRequest['id'] ?? 0),
    'event_id' => $createdId,
    'resolution_note' => 'Không còn ứng viên phù hợp qua HTTP',
], $cookie);
$unfilledPage = httpRequest('GET', $baseUrl . '/?page=assignments&event_id=' . $createdId, [], $cookie);
$unfilledRow = $secondRequest
    ? $setupDb->query('SELECT status, resolution_note, processed_by, resolved_at FROM replacement_requests WHERE id = ' . (int) $secondRequest['id'])->fetch_assoc()
    : null;
checkStep('HTTP kết thúc yêu cầu với trạng thái unfilled và lưu lịch sử', function () use ($markedUnfilled, $unfilledPage, $unfilledRow): void {
    expect($markedUnfilled['status'] === 303, 'Đánh dấu unfilled không dùng PRG');
    expect(($unfilledRow['status'] ?? null) === 'unfilled', 'Yêu cầu chưa chuyển sang unfilled');
    expect(($unfilledRow['resolution_note'] ?? null) === 'Không còn ứng viên phù hợp qua HTTP', 'Không lưu đúng ghi chú unfilled');
    expect(!empty($unfilledRow['processed_by']) && !empty($unfilledRow['resolved_at']), 'Thiếu người/thời điểm xử lý unfilled');
    expect(str_contains($unfilledPage['body'], 'Không còn ứng viên phù hợp qua HTTP') && str_contains($unfilledPage['body'], 'Mở lại'), 'UI lịch sử chưa hiển thị yêu cầu unfilled');
});

$reopenedHttp = httpRequest('POST', $baseUrl . '/', [
    'csrf_token' => csrfFrom($unfilledPage['body']),
    'action' => 'reopen_replacement',
    'request_id' => (int) ($secondRequest['id'] ?? 0),
    'event_id' => $createdId,
], $cookie);
$reopenedPage = httpRequest('GET', $baseUrl . '/?page=assignments&event_id=' . $createdId, [], $cookie);
$reopenedRow = $secondRequest
    ? $setupDb->query('SELECT status, resolution_note, processed_by, resolved_at FROM replacement_requests WHERE id = ' . (int) $secondRequest['id'])->fetch_assoc()
    : null;
checkStep('HTTP mở lại yêu cầu và xóa metadata xử lý cũ', function () use ($reopenedHttp, $reopenedPage, $reopenedRow): void {
    expect($reopenedHttp['status'] === 303, 'Mở lại yêu cầu không dùng PRG');
    expect(($reopenedRow['status'] ?? null) === 'open', 'Yêu cầu chưa được mở lại');
    expect(($reopenedRow['resolution_note'] ?? null) === null && ($reopenedRow['processed_by'] ?? null) === null && ($reopenedRow['resolved_at'] ?? null) === null, 'Metadata xử lý cũ chưa được xóa');
    expect(str_contains($reopenedPage['body'], 'Vắng lần hai để kiểm thử unfilled'), 'Yêu cầu mở lại chưa trở về hàng đợi');
});

$markedUnfilledAgain = httpRequest('POST', $baseUrl . '/', [
    'csrf_token' => csrfFrom($reopenedPage['body']),
    'action' => 'mark_replacement_unfilled',
    'request_id' => (int) ($secondRequest['id'] ?? 0),
    'event_id' => $createdId,
    'resolution_note' => 'Kết thúc lần hai',
], $cookie);
$finalWorkflowPage = httpRequest('GET', $baseUrl . '/?page=assignments&event_id=' . $createdId, [], $cookie);
checkStep('Có thể kết thúc lại yêu cầu sau khi mở lại', function () use ($markedUnfilledAgain, $setupDb, $secondRequest): void {
    expect($markedUnfilledAgain['status'] === 303, 'Kết thúc lại không dùng PRG');
    $status = $secondRequest
        ? $setupDb->query('SELECT status FROM replacement_requests WHERE id = ' . (int) $secondRequest['id'])->fetch_assoc()['status'] ?? null
        : null;
    expect($status === 'unfilled', 'Yêu cầu không trở lại unfilled');
});

$csv = httpRequest('GET', $baseUrl . '/?page=export&type=assignments&event_id=' . $createdId, [], $cookie);
$csvExpectedName = $setupDb->query("SELECT full_name FROM users WHERE username = 'tinhnguyen03'")->fetch_assoc()['full_name'] ?? '';
checkStep('Xuất CSV phân công có liên hệ, kỹ năng và giờ Việt Nam', function () use ($csv, $csvExpectedName): void {
    expect($csv['status'] === 200, 'Xuất CSV không trả 200');
    expect(str_starts_with($csv['body'], "\xEF\xBB\xBF"), 'CSV thiếu UTF-8 BOM');
    expect(str_contains($csv['body'], 'Mã TNV') && str_contains($csv['body'], 'Bắt đầu (VN)') && str_contains($csv['body'], 'Kỹ năng ca'), 'CSV thiếu cột mở rộng');
    expect(str_contains($csv['body'], 'Ca kiểm thử') && str_contains($csv['body'], $csvExpectedName), 'CSV thiếu dữ liệu phân công đang hiệu lực');
});

$shiftCsv = httpRequest('GET', $baseUrl . '/?page=export&type=shift_status&event_id=' . $createdId, [], $cookie);
checkStep('Xuất CSV tình trạng từng ca và số thiếu', function () use ($shiftCsv, $uniqueName): void {
    expect($shiftCsv['status'] === 200 && str_starts_with($shiftCsv['body'], "\xEF\xBB\xBF"), 'CSV tình trạng ca không hợp lệ');
    expect(str_contains($shiftCsv['body'], 'Còn thiếu') && str_contains($shiftCsv['body'], 'Không tìm được người thay'), 'CSV tình trạng ca thiếu cột vận hành');
    expect(str_contains($shiftCsv['body'], $uniqueName) && str_contains($shiftCsv['body'], 'Ca kiểm thử'), 'CSV tình trạng ca thiếu dữ liệu hoạt động');
});

$badCsrf = httpRequest('POST', $baseUrl . '/', [
    'csrf_token' => 'sai-token',
    'action' => 'manual_assign',
    'event_id' => $createdId,
    'shift_id' => $createdShiftId,
    'volunteer_id' => $candidateIds['tinhnguyen04'] ?? 0,
], $cookie);
$activeBeforeBadCsrf = (int) $setupDb->query("SELECT COUNT(*) AS total FROM assignments WHERE shift_id = $createdShiftId AND status IN ('assigned','confirmed')")->fetch_assoc()['total'];
checkStep('POST sai CSRF bị từ chối trước khi đổi dữ liệu', function () use ($badCsrf, $setupDb, $createdShiftId, $activeBeforeBadCsrf): void {
    expect($badCsrf['status'] === 419, 'CSRF sai không trả 419');
    $count = (int) $setupDb->query("SELECT COUNT(*) AS total FROM assignments WHERE shift_id = $createdShiftId AND status IN ('assigned','confirmed')")->fetch_assoc()['total'];
    expect($count === $activeBeforeBadCsrf, 'POST sai CSRF vẫn thay đổi dữ liệu');
});

$activeToCancel = $setupDb->query(
    "SELECT a.id, a.version FROM assignments a JOIN shifts s ON s.id = a.shift_id
     WHERE s.event_id = $createdId AND a.status IN ('assigned','confirmed') ORDER BY a.id"
)->fetch_all(MYSQLI_ASSOC);
$cancelResponses = [];
foreach ($activeToCancel as $activeAssignment) {
    $cancelResponses[] = httpRequest('POST', $baseUrl . '/', [
        'csrf_token' => csrfFrom($finalWorkflowPage['body']),
        'action' => 'cancel_assignment',
        'assignment_id' => (int) $activeAssignment['id'],
        'version' => (int) $activeAssignment['version'],
        'event_id' => $createdId,
    ], $cookie);
}
$closeDetail = httpRequest('GET', $baseUrl . '/?page=activity&id=' . $createdId, [], $cookie);
$closedHttp = httpRequest('POST', $baseUrl . '/', [
    'csrf_token' => csrfFrom($closeDetail['body']),
    'action' => 'close_event',
    'event_id' => $createdId,
], $cookie);
$archivePage = httpRequest('GET', $baseUrl . '/?page=activities', [], $cookie);
$closedStatus = $setupDb->query("SELECT status FROM events WHERE id = $createdId")->fetch_assoc()['status'] ?? null;
checkStep('Đóng hoạt động sau unfilled và vẫn thấy trong kho lịch sử', function () use ($cancelResponses, $closedHttp, $archivePage, $closedStatus, $uniqueName): void {
    foreach ($cancelResponses as $response) {
        expect($response['status'] === 303, 'Gỡ phân công trước khi đóng không dùng PRG');
    }
    expect($closedHttp['status'] === 303 && $closedStatus === 'closed', 'Không đóng được hoạt động khi yêu cầu đã unfilled');
    expect(str_contains($archivePage['body'], $uniqueName) && str_contains($archivePage['body'], 'Đã đóng'), 'Hoạt động đóng không còn trong kho điều phối');
});

checkStep('Dọn đúng dữ liệu tạm do HTTP test tạo', function () use ($setupDb, $createdId): void {
    cleanupHttpEvent($setupDb, $createdId);
    $remaining = (int) $setupDb->query("SELECT COUNT(*) AS total FROM events WHERE id = $createdId")->fetch_assoc()['total'];
    expect($remaining === 0, 'Dữ liệu tạm hiện tại chưa được dọn');
});

fwrite(STDOUT, "Kết quả HTTP: {$passed} đạt, {$failed} lỗi.\n");
exit($failed === 0 ? 0 : 1);
