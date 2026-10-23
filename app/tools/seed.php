<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Database.php';

$appRoot = dirname(__DIR__);
$override = getenv('MIGRATION_DB_CONFIG');
$migrationConfig = $appRoot . '/.local/migration-db.json';
$configPath = is_string($override) && $override !== ''
    ? $override
    : (is_file($migrationConfig) ? $migrationConfig : $appRoot . '/.local/db.json');

function ensureUser(mysqli $db, string $username, string $password, string $fullName, string $role): int
{
    $select = $db->prepare('SELECT id FROM users WHERE username = ?');
    $select->bind_param('s', $username);
    $select->execute();
    $existing = $select->get_result()->fetch_assoc();
    if ($existing) {
        return (int) $existing['id'];
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $insert = $db->prepare('INSERT INTO users (username, password_hash, full_name, role) VALUES (?, ?, ?, ?)');
    $insert->bind_param('ssss', $username, $hash, $fullName, $role);
    $insert->execute();
    return (int) $db->insert_id;
}

function ensureEvent(mysqli $db, string $name, string $location, string $start, string $end, string $description, int $creator): int
{
    $select = $db->prepare('SELECT id FROM events WHERE name = ? LIMIT 1');
    $select->bind_param('s', $name);
    $select->execute();
    $existing = $select->get_result()->fetch_assoc();
    if ($existing) {
        return (int) $existing['id'];
    }
    $insert = $db->prepare(
        "INSERT INTO events (name, location, start_at, end_at, description, status, created_by)
         VALUES (?, ?, ?, ?, ?, 'published', ?)"
    );
    $insert->bind_param('sssssi', $name, $location, $start, $end, $description, $creator);
    $insert->execute();
    return (int) $db->insert_id;
}

function ensureShift(mysqli $db, int $eventId, string $name, string $start, string $end, int $required, array $skillCodes): void
{
    $select = $db->prepare('SELECT id FROM shifts WHERE event_id = ? AND name = ? LIMIT 1');
    $select->bind_param('is', $eventId, $name);
    $select->execute();
    $existing = $select->get_result()->fetch_assoc();
    if ($existing) {
        return;
    }
    $insert = $db->prepare('INSERT INTO shifts (event_id, name, start_at, end_at, required_count) VALUES (?, ?, ?, ?, ?)');
    $insert->bind_param('isssi', $eventId, $name, $start, $end, $required);
    $insert->execute();
    $shiftId = (int) $db->insert_id;
    $link = $db->prepare(
        'INSERT INTO shift_skills (shift_id, skill_id) SELECT ?, id FROM skills WHERE code = ?'
    );
    foreach ($skillCodes as $skillCode) {
        $link->bind_param('is', $shiftId, $skillCode);
        $link->execute();
    }
}

try {
    $db = (new Database($configPath))->connection();
    $db->begin_transaction();

    $coordinatorId = ensureUser($db, 'dieuphoi', 'Dieuphoi@123', 'Nguyễn Minh Anh', 'coordinator');
    $volunteerId = ensureUser($db, 'tinhnguyen', 'Tinhnguyen@123', 'Trần Thanh Hà', 'volunteer');
    $volunteer2Id = ensureUser($db, 'tinhnguyen02', 'Tinhnguyen@123', 'Lê Hoàng Nam', 'volunteer');
    $volunteer3Id = ensureUser($db, 'tinhnguyen03', 'Tinhnguyen@123', 'Phạm Ngọc Mai', 'volunteer');
    $volunteerIds = [$volunteerId, $volunteer2Id, $volunteer3Id];
    for ($number = 4; $number <= 30; $number++) {
        $volunteerIds[] = ensureUser(
            $db,
            sprintf('tinhnguyen%02d', $number),
            'Tinhnguyen@123',
            sprintf('Tình nguyện viên %02d', $number),
            'volunteer'
        );
    }

    $skills = [
        'FIRST_AID' => 'Sơ cứu',
        'LOGISTICS' => 'Hậu cần',
        'MEDIA' => 'Truyền thông',
        'GUIDANCE' => 'Hướng dẫn',
        'IT' => 'Công nghệ thông tin',
    ];
    $skillInsert = $db->prepare('INSERT IGNORE INTO skills (code, name) VALUES (?, ?)');
    foreach ($skills as $code => $name) {
        $skillInsert->bind_param('ss', $code, $name);
        $skillInsert->execute();
    }

    $profile = $db->prepare(
        "INSERT INTO volunteer_profiles (user_id, volunteer_code, email)
         VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE volunteer_code = VALUES(volunteer_code)"
    );
    $attachSkill = $db->prepare(
        'INSERT IGNORE INTO volunteer_skills (volunteer_id, skill_id) SELECT ?, id FROM skills WHERE code = ?'
    );
    $availability = $db->prepare(
        "INSERT INTO availability (volunteer_id, start_at, end_at)
         SELECT ?, '2027-01-01 00:00:00', '2028-01-01 00:00:00'
         WHERE NOT EXISTS (
             SELECT 1 FROM availability WHERE volunteer_id = ? AND start_at = '2027-01-01 00:00:00' AND end_at = '2028-01-01 00:00:00'
         )"
    );
    foreach ($volunteerIds as $index => $seedVolunteerId) {
        $code = sprintf('TNV%02d', $index + 1);
        $email = strtolower($code) . '@example.test';
        $profile->bind_param('iss', $seedVolunteerId, $code, $email);
        $profile->execute();
        foreach (array_keys($skills) as $skillCode) {
            $attachSkill->bind_param('is', $seedVolunteerId, $skillCode);
            $attachSkill->execute();
        }
        $availability->bind_param('ii', $seedVolunteerId, $seedVolunteerId);
        $availability->execute();
    }

    $green = ensureEvent($db, 'Ngày hội Vì một Việt Nam xanh', 'Công viên Thống Nhất, Hà Nội', '2027-03-13 01:00:00', '2027-03-13 10:00:00', 'Trồng cây, thu gom rác và lan tỏa lối sống xanh đến cộng đồng.', $coordinatorId);
    ensureShift($db, $green, 'Chuẩn bị vật tư', '2027-03-13 01:00:00', '2027-03-13 03:00:00', 4, ['LOGISTICS']);
    ensureShift($db, $green, 'Hướng dẫn đội trồng cây', '2027-03-13 03:00:00', '2027-03-13 06:00:00', 6, ['GUIDANCE']);
    ensureShift($db, $green, 'Ghi hình và truyền thông', '2027-03-13 06:00:00', '2027-03-13 09:00:00', 3, ['MEDIA']);

    $children = ensureEvent($db, 'Vui hè cùng em', 'Nhà văn hóa Thanh Xuân, Hà Nội', '2027-04-04 00:00:00', '2027-04-04 10:00:00', 'Một ngày vui chơi, đọc sách và trải nghiệm công nghệ dành cho trẻ em.', $coordinatorId);
    ensureShift($db, $children, 'Đón tiếp và hướng dẫn', '2027-04-04 00:00:00', '2027-04-04 03:00:00', 5, ['GUIDANCE']);
    ensureShift($db, $children, 'Hỗ trợ trải nghiệm công nghệ', '2027-04-04 03:00:00', '2027-04-04 06:00:00', 4, ['IT']);
    ensureShift($db, $children, 'Trực sơ cứu', '2027-04-04 00:00:00', '2027-04-04 07:00:00', 2, ['FIRST_AID']);

    $blood = ensureEvent($db, 'Giọt hồng sẻ chia 2027', 'Trung tâm Văn hóa Sinh viên, Đà Nẵng', '2027-05-22 00:00:00', '2027-05-22 09:00:00', 'Hỗ trợ ngày hội hiến máu nhân đạo và chăm sóc người tham gia.', $coordinatorId);
    ensureShift($db, $blood, 'Bàn đăng ký', '2027-05-22 00:00:00', '2027-05-22 04:00:00', 4, ['IT', 'GUIDANCE']);
    ensureShift($db, $blood, 'Hỗ trợ sau hiến máu', '2027-05-22 04:00:00', '2027-05-22 08:00:00', 5, ['FIRST_AID']);

    $registration = $db->prepare(
        "INSERT IGNORE INTO registrations (event_id, volunteer_id, status, registered_at)
         VALUES (?, ?, 'registered', UTC_TIMESTAMP())"
    );
    $registration->bind_param('ii', $green, $volunteer2Id);
    $registration->execute();
    $registration->bind_param('ii', $children, $volunteerId);
    $registration->execute();
    foreach ([$green, $children, $blood] as $seedEventId) {
        foreach ($volunteerIds as $seedVolunteerId) {
            $registration->bind_param('ii', $seedEventId, $seedVolunteerId);
            $registration->execute();
        }
    }

    $db->commit();
    fwrite(STDOUT, "Đã nạp dữ liệu demo (không ghi đè tài khoản hoặc hoạt động đã có).\n");
} catch (Throwable $exception) {
    if (isset($db) && $db instanceof mysqli) {
        $db->rollback();
    }
    fwrite(STDERR, "Seed thất bại: {$exception->getMessage()}\n");
    exit(1);
}
