<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

$page = isset($_GET['page']) && is_string($_GET['page']) ? $_GET['page'] : 'dashboard';
$flash = null;
$fatalError = null;
$statusCode = 200;

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
        (new ActionController(db(), auth()))->dispatch($action);
    }
    $flash = take_flash();
} catch (HttpException $exception) {
    $statusCode = $exception->status;
    $fatalError = $exception->getMessage();
} catch (DomainException $exception) {
    $statusCode = 422;
    $fatalError = $exception->getMessage();
} catch (mysqli_sql_exception | RuntimeException $exception) {
    error_log($exception->__toString());
    $statusCode = 503;
    $fatalError = 'Không thể kết nối cơ sở dữ liệu. Hãy kiểm tra MySQL và cấu hình app/.local/db.json.';
}

http_response_code($statusCode);
$currentUser = null;
if ($fatalError === null) {
    try {
        $currentUser = auth()->user();
    } catch (Throwable) {
        $currentUser = null;
    }
}

if ($fatalError === null && $page === 'export') {
    try {
        auth()->requireRole('coordinator');
        $type = is_string($_GET['type'] ?? null) ? $_GET['type'] : 'summary';
        $eventId = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $filename = match ($type) {
            'assignments' => 'danh-sach-phan-cong.csv',
            'shift_status' => 'tinh-trang-tung-ca.csv',
            default => 'tong-hop-hoat-dong.csv',
        };
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $output = fopen('php://output', 'wb');
        fwrite($output, "\xEF\xBB\xBF");
        if ($type === 'assignments') {
            fputcsv($output, ['Hoạt động', 'Ca', 'Mã TNV', 'Tình nguyện viên', 'Điện thoại', 'Email', 'Kỹ năng ca', 'Bắt đầu (VN)', 'Kết thúc (VN)', 'Trạng thái'], ',', '"', '', "\r\n");
            foreach ((new AssignmentRepository(db()))->allActive($eventId ? (int) $eventId : null) as $row) {
                fputcsv($output, array_map([CsvValue::class, 'safe'], [
                    $row['event_name'], $row['shift_name'], $row['volunteer_code'], $row['volunteer_name'],
                    $row['phone'] ?? '', $row['email'] ?? '', $row['required_skills'] ?: 'Không yêu cầu',
                    format_local_datetime($row['start_at']), format_local_datetime($row['end_at']), $row['status'],
                ]), ',', '"', '', "\r\n");
            }
        } elseif ($type === 'shift_status') {
            fputcsv($output, ['Hoạt động', 'Ca', 'Bắt đầu (VN)', 'Kết thúc (VN)', 'Kỹ năng', 'Cần', 'Đã xếp', 'Đã xác nhận', 'Còn thiếu', 'Yêu cầu thay mở', 'Không tìm được người thay'], ',', '"', '', "\r\n");
            foreach ((new ReportRepository(db()))->shiftSummary($eventId ? (int) $eventId : null) as $row) {
                fputcsv($output, array_map([CsvValue::class, 'safe'], [
                    $row['event_name'], $row['shift_name'], format_local_datetime($row['start_at']),
                    format_local_datetime($row['end_at']), $row['required_skills'] ?: 'Không yêu cầu',
                    $row['required_count'], $row['assigned_count'], $row['confirmed_count'],
                    $row['missing_count'], $row['open_replacements'], $row['unfilled_replacements'],
                ]), ',', '"', '', "\r\n");
            }
        } else {
            fputcsv($output, ['Hoạt động', 'Số ca', 'Lượt đăng ký', 'Vị trí cần', 'Đã phân công', 'Đã xác nhận', 'Còn thiếu'], ',', '"', '', "\r\n");
            foreach ((new ReportRepository(db()))->summary() as $row) {
                $missing = max(0, (int) $row['required_positions'] - (int) $row['assigned_positions']);
                fputcsv($output, array_map([CsvValue::class, 'safe'], [$row['name'], $row['shifts'], $row['registrations'], $row['required_positions'], $row['assigned_positions'], $row['confirmed_positions'], $missing]), ',', '"', '', "\r\n");
            }
        }
        fclose($output);
        exit;
    } catch (HttpException $exception) {
        http_response_code($exception->status);
        echo h($exception->getMessage());
        exit;
    }
}

$counts = [];
$events = [];
$event = null;
$shifts = [];
$skills = [];
$registrants = [];
$registered = false;
$volunteers = [];
$volunteerProfile = null;
$assignments = [];
$myShifts = [];
$myShiftHistory = [];
$runs = [];
$reportRows = [];
$shiftReportRows = [];
$comparisonGroups = [];
$replacementRequests = [];
$replacementHistory = [];
$assignmentHistory = [];
$selectedEventId = null;
$old = is_array($flash['old'] ?? null) ? $flash['old'] : [];

if ($fatalError === null) {
    try {
        if ($page === 'dashboard') {
            $counts = (new DashboardRepository(db()))->counts();
            $events = (new EventRepository(db()))->upcoming(3);
        } elseif ($page === 'activities') {
            $events = ($currentUser['role'] ?? '') === 'coordinator'
                ? (new EventRepository(db()))->allForCoordinator(200)
                : (new EventRepository(db()))->upcoming(100);
        } elseif ($page === 'new_activity') {
            auth()->requireRole('coordinator');
        } elseif ($page === 'activity') {
            $eventId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $eventRepository = new EventRepository(db());
            $event = $eventId ? $eventRepository->find((int) $eventId) : null;
            if (!$event) {
                throw new HttpException(404, 'Không tìm thấy hoạt động.');
            }
            $shifts = $eventRepository->shifts((int) $event['id']);
            $registered = $currentUser && $currentUser['role'] === 'volunteer'
                ? $eventRepository->isRegistered((int) $event['id'], (int) $currentUser['id']) : false;
            if (($currentUser['role'] ?? '') === 'coordinator') {
                $skills = $eventRepository->skills();
                $registrants = $eventRepository->registrants((int) $event['id']);
            }
        } elseif ($page === 'volunteers') {
            $user = auth()->requireLogin();
            $volunteerRepository = new VolunteerRepository(db());
            $skills = (new EventRepository(db()))->skills();
            if ($user['role'] === 'coordinator') {
                $volunteers = $volunteerRepository->all();
                $requestedId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($requestedId) {
                    $volunteerProfile = $volunteerRepository->profile((int) $requestedId);
                }
            } else {
                $volunteerProfile = $volunteerRepository->profile((int) $user['id']);
            }
        } elseif ($page === 'assignments') {
            auth()->requireRole('coordinator');
            $events = (new EventRepository(db()))->all();
            $requestedEvent = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $selectedEventId = $requestedEvent ? (int) $requestedEvent : (isset($events[0]) ? (int) $events[0]['id'] : null);
            $assignments = (new AssignmentRepository(db()))->allActive($selectedEventId);
            $replacementRequests = (new AssignmentRepository(db()))->openReplacementRequests($selectedEventId);
            $replacementHistory = (new AssignmentRepository(db()))->replacementHistory($selectedEventId);
            $assignmentHistory = (new AssignmentRepository(db()))->history($selectedEventId);
            $runs = (new AllocationRepository(db()))->runs($selectedEventId);
            if ($selectedEventId !== null) {
                $shifts = (new EventRepository(db()))->shifts($selectedEventId);
                $registrants = (new EventRepository(db()))->registrants($selectedEventId);
            }
        } elseif ($page === 'my_shifts') {
            $user = auth()->requireRole('volunteer');
            $myShifts = (new AssignmentRepository(db()))->mine((int) $user['id']);
            $myShiftHistory = (new AssignmentRepository(db()))->mineHistory((int) $user['id']);
        } elseif ($page === 'stats') {
            auth()->requireRole('coordinator');
            $reportRows = (new ReportRepository(db()))->summary();
            $runs = (new ReportRepository(db()))->algorithmRuns();
            $shiftReportRows = (new ReportRepository(db()))->shiftSummary();
            $comparisonGroups = (new ReportRepository(db()))->comparisonGroups();
        }
    } catch (HttpException $exception) {
        $statusCode = $exception->status;
        $fatalError = $exception->getMessage();
    } catch (mysqli_sql_exception | RuntimeException $exception) {
        error_log($exception->__toString());
        $statusCode = 503;
        $fatalError = 'Không thể kết nối cơ sở dữ liệu. Hãy kiểm tra MySQL và cấu hình app/.local/db.json.';
    }
}

http_response_code($statusCode);

$pageTitles = [
    'dashboard' => 'Tổng quan',
    'activities' => 'Hoạt động',
    'activity' => 'Chi tiết hoạt động',
    'new_activity' => 'Tạo hoạt động',
    'login' => 'Đăng nhập',
    'volunteers' => 'Tình nguyện viên',
    'assignments' => 'Phân công',
    'my_shifts' => 'Ca của tôi',
    'stats' => 'Thống kê',
];
$title = $pageTitles[$page] ?? 'Không tìm thấy';
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Hệ thống tổ chức hoạt động và phân công tình nguyện viên">
    <title><?= h($title) ?> · VolunteerHub</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<header class="site-header">
    <div class="nav-shell">
        <a class="brand" href="<?= h(route_url('dashboard')) ?>" aria-label="Về trang tổng quan">
            <span class="brand-mark" aria-hidden="true">V</span>
            <span><strong>VolunteerHub</strong><small>Kết nối để sẻ chia</small></span>
        </a>
        <button class="nav-toggle" type="button" aria-label="Mở trình đơn" aria-expanded="false" data-nav-toggle>☰</button>
        <nav class="main-nav" data-nav>
            <a class="<?= $page === 'dashboard' ? 'active' : '' ?>" href="<?= h(route_url('dashboard')) ?>">Tổng quan</a>
            <a class="<?= in_array($page, ['activities', 'activity', 'new_activity'], true) ? 'active' : '' ?>" href="<?= h(route_url('activities')) ?>">Hoạt động</a>
            <a class="<?= $page === 'volunteers' ? 'active' : '' ?>" href="<?= h(route_url('volunteers')) ?>">Tình nguyện viên</a>
            <a class="<?= $page === 'assignments' ? 'active' : '' ?>" href="<?= h(route_url('assignments')) ?>">Phân công</a>
            <a class="<?= $page === 'my_shifts' ? 'active' : '' ?>" href="<?= h(route_url('my_shifts')) ?>">Ca của tôi</a>
            <a class="<?= $page === 'stats' ? 'active' : '' ?>" href="<?= h(route_url('stats')) ?>">Thống kê</a>
        </nav>
        <div class="account-area">
            <?php if ($currentUser): ?>
                <span class="user-chip"><span class="user-dot"></span><?= h($currentUser['full_name']) ?></span>
                <form method="post" class="inline-form">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="logout">
                    <button class="link-button" type="submit">Đăng xuất</button>
                </form>
            <?php else: ?>
                <a class="button button-small" href="<?= h(route_url('login')) ?>">Đăng nhập</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<main class="page-shell">
    <?php if ($flash): ?>
        <div class="alert alert-<?= h($flash['type']) ?>" role="status"><?= h($flash['message']) ?></div>
    <?php endif; ?>

    <?php if ($fatalError !== null): ?>
        <section class="empty-state error-state">
            <span class="empty-icon">!</span>
            <p class="eyebrow">Lỗi <?= h((string) $statusCode) ?></p>
            <h1>Chưa thể hoàn thành yêu cầu</h1>
            <p><?= h($fatalError) ?></p>
            <a class="button" href="<?= h(route_url('dashboard')) ?>">Về tổng quan</a>
        </section>
    <?php elseif ($page === 'login'): ?>
        <section class="auth-layout">
            <div class="auth-message">
                <span class="eyebrow">Cùng tạo nên điều tốt đẹp</span>
                <h1>Mỗi đóng góp nhỏ tạo nên một cộng đồng lớn.</h1>
                <p>Đăng nhập để quản lý hoạt động hoặc đăng ký tham gia các chương trình tình nguyện sắp tới.</p>
                <div class="demo-accounts">
                    <p><strong>Tài khoản demo</strong></p>
                    <p>Điều phối: <code>dieuphoi</code> / <code>Dieuphoi@123</code></p>
                    <p>Tình nguyện viên: <code>tinhnguyen</code> / <code>Tinhnguyen@123</code></p>
                </div>
            </div>
            <form class="panel auth-card" method="post">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="login">
                <div>
                    <span class="eyebrow">Chào mừng trở lại</span>
                    <h2>Đăng nhập hệ thống</h2>
                </div>
                <label>Tên đăng nhập
                    <input type="text" name="username" value="<?= h($old['username'] ?? '') ?>" autocomplete="username" required autofocus>
                </label>
                <label>Mật khẩu
                    <input type="password" name="password" autocomplete="current-password" required>
                </label>
                <button class="button button-wide" type="submit">Đăng nhập</button>
            </form>
        </section>
    <?php elseif ($page === 'dashboard'): ?>
        <section class="hero">
            <div>
                <span class="eyebrow">Nền tảng điều phối tình nguyện</span>
                <h1>Chung tay hành động,<br><em>lan tỏa yêu thương.</em></h1>
                <p>Quản lý hoạt động, kết nối tình nguyện viên và theo dõi tiến độ trong một không gian thống nhất.</p>
                <div class="hero-actions">
                    <a class="button" href="<?= h(route_url('activities')) ?>">Khám phá hoạt động</a>
                    <?php if (($currentUser['role'] ?? '') === 'coordinator'): ?>
                        <a class="button button-secondary" href="<?= h(route_url('new_activity')) ?>">+ Tạo hoạt động</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="hero-art" aria-hidden="true">
                <div class="leaf leaf-one"></div><div class="leaf leaf-two"></div>
                <div class="heart">♥</div>
                <div class="people"><span></span><span></span><span></span></div>
            </div>
        </section>

        <section class="section-block">
            <div class="section-heading"><div><span class="eyebrow">Số liệu hệ thống</span><h2>Tổng quan nhanh</h2></div></div>
            <div class="metric-grid">
                <article class="metric-card"><span class="metric-icon">◎</span><div><strong><?= h($counts['events']) ?></strong><span>Hoạt động</span></div></article>
                <article class="metric-card"><span class="metric-icon">♧</span><div><strong><?= h($counts['volunteers']) ?></strong><span>Tình nguyện viên</span></div></article>
                <article class="metric-card"><span class="metric-icon">◷</span><div><strong><?= h($counts['shifts']) ?></strong><span>Ca hoạt động</span></div></article>
                <article class="metric-card"><span class="metric-icon">✓</span><div><strong><?= h($counts['registrations']) ?></strong><span>Lượt đăng ký</span></div></article>
            </div>
            <?php if (($currentUser['role'] ?? '') === 'coordinator'): ?>
                <div class="metric-grid metric-grid-secondary">
                    <article class="metric-card"><span class="metric-icon">●</span><div><strong><?= h($counts['full_shifts']) ?></strong><span>Ca đã đủ người</span></div></article>
                    <article class="metric-card"><span class="metric-icon">△</span><div><strong><?= h($counts['short_shifts']) ?></strong><span>Ca còn thiếu</span></div></article>
                    <article class="metric-card"><span class="metric-icon">−</span><div><strong><?= h($counts['missing_positions']) ?></strong><span>Vị trí còn thiếu</span></div></article>
                    <article class="metric-card"><span class="metric-icon">↻</span><div><strong><?= h($counts['open_replacements']) ?></strong><span>Yêu cầu thay mở</span></div></article>
                </div>
            <?php endif; ?>
        </section>

        <section class="section-block">
            <div class="section-heading">
                <div><span class="eyebrow">Cơ hội đóng góp</span><h2>Hoạt động sắp diễn ra</h2></div>
                <a class="text-link" href="<?= h(route_url('activities')) ?>">Xem tất cả →</a>
            </div>
            <?php require __DIR__ . '/partials/event_cards.php'; ?>
        </section>
    <?php elseif ($page === 'activities'): ?>
        <section class="page-heading">
            <div><span class="eyebrow">Cùng tham gia</span><h1><?= ($currentUser['role'] ?? '') === 'coordinator' ? 'Kho hoạt động' : 'Hoạt động sắp diễn ra' ?></h1><p><?= ($currentUser['role'] ?? '') === 'coordinator' ? 'Theo dõi cả hoạt động đang mở, đã kết thúc và đã đóng.' : 'Chọn một hoạt động phù hợp để xem ca và đăng ký đóng góp.' ?></p></div>
            <?php if (($currentUser['role'] ?? '') === 'coordinator'): ?><a class="button" href="<?= h(route_url('new_activity')) ?>">+ Tạo hoạt động</a><?php endif; ?>
        </section>
        <?php require __DIR__ . '/partials/event_cards.php'; ?>
    <?php elseif ($page === 'new_activity'): ?>
        <section class="form-page">
            <div class="page-heading"><div><span class="eyebrow">Dành cho điều phối</span><h1>Tạo hoạt động mới</h1><p>Thông tin sẽ được lưu trực tiếp vào MySQL và hiển thị cho tình nguyện viên.</p></div></div>
            <form class="panel form-card" method="post">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="create_event">
                <div class="form-grid">
                    <label class="span-two">Tên hoạt động<input type="text" name="name" maxlength="180" value="<?= h($old['name'] ?? '') ?>" required></label>
                    <label class="span-two">Địa điểm<input type="text" name="location" maxlength="255" value="<?= h($old['location'] ?? '') ?>" required></label>
                    <label>Bắt đầu <span>(giờ Việt Nam)</span><input type="datetime-local" name="start_at" value="<?= h($old['start_at'] ?? '') ?>" required></label>
                    <label>Kết thúc <span>(giờ Việt Nam)</span><input type="datetime-local" name="end_at" value="<?= h($old['end_at'] ?? '') ?>" required></label>
                    <label class="span-two">Mô tả<textarea name="description" rows="5" maxlength="3000"><?= h($old['description'] ?? '') ?></textarea></label>
                </div>
                <div class="form-actions"><a class="button button-ghost" href="<?= h(route_url('activities')) ?>">Hủy</a><button class="button" type="submit">Lưu hoạt động</button></div>
            </form>
        </section>
    <?php elseif ($page === 'activity'): ?>
        <section class="event-banner">
            <a class="back-link" href="<?= h(route_url('activities')) ?>">← Quay lại danh sách</a>
            <div class="event-banner-grid">
                <div><span class="status-pill"><?= $event['status'] === 'closed' ? 'Đã đóng' : 'Đang mở đăng ký' ?></span><h1><?= h($event['name']) ?></h1><p><?= nl2br(h($event['description'])) ?></p></div>
                <dl class="event-facts"><div><dt>Thời gian</dt><dd><?= h(format_local_datetime($event['start_at'])) ?><br>đến <?= h(format_local_datetime($event['end_at'])) ?></dd></div><div><dt>Địa điểm</dt><dd><?= h($event['location']) ?></dd></div></dl>
            </div>
            <div class="banner-actions">
                <?php if (($currentUser['role'] ?? '') === 'volunteer' && $event['status'] === 'published' && $event['end_at'] >= gmdate('Y-m-d H:i:s')): ?>
                    <?php if ($registered): ?><span class="registered-badge">✓ Bạn đã đăng ký</span>
                        <form method="post" class="inline-form"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="withdraw"><input type="hidden" name="event_id" value="<?= h($event['id']) ?>"><button class="button button-ghost button-small" type="submit">Rút đăng ký</button></form>
                    <?php else: ?><form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="register"><input type="hidden" name="event_id" value="<?= h($event['id']) ?>"><button class="button" type="submit">Đăng ký tham gia</button></form><?php endif; ?>
                <?php elseif (!$currentUser): ?><a class="button" href="<?= h(route_url('login')) ?>">Đăng nhập để đăng ký</a><?php endif; ?>
            </div>
        </section>

        <?php if (($currentUser['role'] ?? '') === 'coordinator' && $event['status'] === 'published'): ?>
            <section class="section-block action-grid">
                <form method="post" class="panel form-card stack-form">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="update_event"><input type="hidden" name="event_id" value="<?= h($event['id']) ?>">
                    <div><span class="eyebrow">Quản lý hoạt động</span><h2>Chỉnh sửa thông tin</h2></div>
                    <label>Tên hoạt động<input name="name" maxlength="180" value="<?= h($event['name']) ?>" required></label>
                    <label>Địa điểm<input name="location" maxlength="255" value="<?= h($event['location']) ?>" required></label>
                    <div class="form-grid"><label>Bắt đầu <span>(giờ Việt Nam)</span><input type="datetime-local" name="start_at" value="<?= h(format_local_input($event['start_at'])) ?>" required></label><label>Kết thúc <span>(giờ Việt Nam)</span><input type="datetime-local" name="end_at" value="<?= h(format_local_input($event['end_at'])) ?>" required></label></div>
                    <label>Mô tả<textarea name="description" maxlength="3000" rows="4"><?= h($event['description']) ?></textarea></label>
                    <button class="button button-secondary" type="submit">Lưu thay đổi</button>
                </form>
                <div class="panel form-card stack-form danger-panel">
                    <div><span class="eyebrow">Kết thúc nghiệp vụ</span><h2>Đóng hoạt động</h2></div>
                    <p>Giữ nguyên đăng ký và lịch sử. Hệ thống sẽ từ chối nếu còn phân công hiệu lực.</p>
                    <form method="post" data-confirm="Đóng hoạt động này?"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="close_event"><input type="hidden" name="event_id" value="<?= h($event['id']) ?>"><button class="button button-danger" type="submit">Đóng hoạt động</button></form>
                </div>
            </section>
        <?php endif; ?>

        <section class="section-block detail-grid">
            <div>
                <div class="section-heading"><div><span class="eyebrow">Lịch hoạt động</span><h2>Các ca cần hỗ trợ</h2></div><span class="count-badge"><?= count($shifts) ?> ca</span></div>
                <?php if ($shifts === []): ?><div class="panel empty-inline">Chưa có ca nào cho hoạt động này.</div><?php else: ?>
                    <div class="shift-list">
                    <?php foreach ($shifts as $shift): $shiftSkillIds = array_map('intval', array_filter(explode(',', (string) ($shift['skill_ids_csv'] ?? '')))); ?>
                        <article class="shift-card"><div class="shift-time"><strong><?= h((new DateTimeImmutable($shift['start_at'], new DateTimeZone('UTC')))->setTimezone(new DateTimeZone(Validator::DISPLAY_TIMEZONE))->format('H:i')) ?></strong><span><?= h((new DateTimeImmutable($shift['end_at'], new DateTimeZone('UTC')))->setTimezone(new DateTimeZone(Validator::DISPLAY_TIMEZONE))->format('H:i')) ?></span></div><div class="shift-main"><h3><?= h($shift['name']) ?></h3><p><?= h(format_local_datetime($shift['start_at'])) ?></p><div class="tags"><span><?= h($shift['required_count']) ?> người</span><span><?= h($shift['skill_names'] ?: 'Không yêu cầu kỹ năng') ?></span></div>
                        <?php if (($currentUser['role'] ?? '') === 'coordinator' && $event['status'] === 'published'): ?><details class="shift-editor"><summary>Chỉnh sửa ca</summary><form method="post" class="stack-form compact-form"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="update_shift"><input type="hidden" name="event_id" value="<?= h($event['id']) ?>"><input type="hidden" name="shift_id" value="<?= h($shift['id']) ?>"><label>Tên ca<input name="name" maxlength="160" value="<?= h($shift['name']) ?>" required></label><div class="form-grid"><label>Bắt đầu<input type="datetime-local" name="start_at" value="<?= h(format_local_input($shift['start_at'])) ?>" required></label><label>Kết thúc<input type="datetime-local" name="end_at" value="<?= h(format_local_input($shift['end_at'])) ?>" required></label></div><label>Số người cần<input type="number" name="required_count" min="1" max="999" value="<?= h($shift['required_count']) ?>" required></label><fieldset><legend>Kỹ năng yêu cầu</legend><div class="check-grid"><?php foreach ($skills as $skill): ?><label class="check-label"><input type="checkbox" name="skill_ids[]" value="<?= h($skill['id']) ?>" <?= in_array((int) $skill['id'], $shiftSkillIds, true) ? 'checked' : '' ?>> <span><?= h($skill['name']) ?></span></label><?php endforeach; ?></div></fieldset><button class="button button-small" type="submit">Lưu ca</button></form><form method="post" class="delete-shift-form" data-confirm="Xóa ca chưa được sử dụng này?"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="delete_shift"><input type="hidden" name="event_id" value="<?= h($event['id']) ?>"><input type="hidden" name="shift_id" value="<?= h($shift['id']) ?>"><button class="link-button danger-link" type="submit">Xóa ca</button></form></details><?php endif; ?>
                        </div></article>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (($currentUser['role'] ?? '') === 'coordinator' && $event['status'] === 'published'): ?>
                <aside class="panel side-panel">
                    <span class="eyebrow">Điều phối</span><h2>Thêm ca hoạt động</h2>
                    <form method="post" class="stack-form">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="create_shift"><input type="hidden" name="event_id" value="<?= h($event['id']) ?>">
                        <label>Tên ca<input type="text" name="name" maxlength="160" value="<?= h($old['name'] ?? '') ?>" required></label>
                        <label>Bắt đầu <span>(giờ Việt Nam)</span><input type="datetime-local" name="start_at" value="<?= h($old['start_at'] ?? '') ?>" required></label>
                        <label>Kết thúc <span>(giờ Việt Nam)</span><input type="datetime-local" name="end_at" value="<?= h($old['end_at'] ?? '') ?>" required></label>
                        <label>Số người cần<input type="number" name="required_count" min="1" max="999" value="<?= h($old['required_count'] ?? '1') ?>" required></label>
                        <fieldset><legend>Kỹ năng yêu cầu</legend><div class="check-grid"><?php foreach ($skills as $skill): ?><label class="check-label"><input type="checkbox" name="skill_ids[]" value="<?= h($skill['id']) ?>"> <span><?= h($skill['name']) ?></span></label><?php endforeach; ?></div></fieldset>
                        <button class="button button-wide" type="submit">Thêm ca</button>
                    </form>
                </aside>
            <?php endif; ?>
        </section>

        <?php if (($currentUser['role'] ?? '') === 'coordinator'): ?>
            <section class="section-block">
                <div class="section-heading"><div><span class="eyebrow">Danh sách đã đăng ký</span><h2>Tình nguyện viên tham gia</h2></div><span class="count-badge"><?= count($registrants) ?> người</span></div>
                <div class="panel table-wrap"><table><thead><tr><th>Họ tên</th><th>Tài khoản</th><th>Thời điểm đăng ký</th></tr></thead><tbody><?php if ($registrants === []): ?><tr><td colspan="3" class="empty-cell">Chưa có tình nguyện viên đăng ký.</td></tr><?php else: foreach ($registrants as $registrant): ?><tr><td><strong><?= h($registrant['full_name']) ?></strong></td><td><?= h($registrant['username']) ?></td><td><?= h(format_local_datetime($registrant['registered_at'])) ?></td></tr><?php endforeach; endif; ?></tbody></table></div>
            </section>
        <?php endif; ?>
    <?php elseif ($page === 'volunteers'): ?>
        <section class="page-heading"><div><span class="eyebrow">Nguồn lực</span><h1>Tình nguyện viên</h1><p>Quản lý hồ sơ, kỹ năng và lịch rảnh dùng trực tiếp khi kiểm tra điều kiện phân công.</p></div></section>
        <?php if (($currentUser['role'] ?? '') === 'coordinator'): ?>
            <div class="panel table-wrap"><table><thead><tr><th>Mã</th><th>Họ tên</th><th>Kỹ năng</th><th>Ca hiện tại</th><th></th></tr></thead><tbody>
            <?php foreach ($volunteers as $item): ?><tr><td><?= h($item['volunteer_code'] ?: $item['username']) ?></td><td><strong><?= h($item['full_name']) ?></strong><br><small><?= h($item['email'] ?? '') ?></small></td><td><?= h($item['skill_names'] ?: 'Chưa khai báo') ?></td><td><?= h($item['active_assignments']) ?></td><td><a class="text-link" href="<?= h(route_url('volunteers', ['id' => $item['id']])) ?>">Mở hồ sơ →</a></td></tr><?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>

        <?php if ($volunteerProfile): ?>
            <section class="section-block detail-grid">
                <form method="post" class="panel form-card stack-form">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="save_profile"><input type="hidden" name="volunteer_id" value="<?= h($volunteerProfile['id']) ?>">
                    <div><span class="eyebrow">Hồ sơ</span><h2><?= h($volunteerProfile['full_name']) ?></h2></div>
                    <label>Họ tên<input name="full_name" maxlength="160" value="<?= h($volunteerProfile['full_name']) ?>" required></label>
                    <label>Mã tình nguyện viên<input name="volunteer_code" maxlength="30" value="<?= h($volunteerProfile['volunteer_code'] ?? '') ?>" required></label>
                    <label>Điện thoại<input name="phone" maxlength="30" value="<?= h($volunteerProfile['phone'] ?? '') ?>"></label>
                    <label>Email<input type="email" name="email" maxlength="190" value="<?= h($volunteerProfile['email'] ?? '') ?>"></label>
                    <label>Ghi chú<textarea name="notes" maxlength="1000" rows="3"><?= h($volunteerProfile['notes'] ?? '') ?></textarea></label>
                    <button class="button" type="submit">Lưu hồ sơ</button>
                </form>
                <div class="stack-sections">
                    <form method="post" class="panel form-card stack-form">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="save_skills"><input type="hidden" name="volunteer_id" value="<?= h($volunteerProfile['id']) ?>">
                        <div><span class="eyebrow">Năng lực</span><h2>Kỹ năng</h2></div>
                        <div class="check-grid"><?php foreach ($skills as $skill): ?><label class="check-label"><input type="checkbox" name="skill_ids[]" value="<?= h($skill['id']) ?>" <?= in_array((int) $skill['id'], $volunteerProfile['skill_ids'], true) ? 'checked' : '' ?>> <span><?= h($skill['name']) ?></span></label><?php endforeach; ?></div>
                        <button class="button button-secondary" type="submit">Cập nhật kỹ năng</button>
                    </form>
                    <section class="panel form-card stack-form">
                        <div><span class="eyebrow">Khả dụng</span><h2>Thêm lịch rảnh</h2></div>
                        <?php foreach ($volunteerProfile['availability'] as $slot): ?><div class="compact-row"><span>✓ <?= h(format_local_datetime($slot['start_at'])) ?> — <?= h(format_local_datetime($slot['end_at'])) ?></span><form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="delete_availability"><input type="hidden" name="volunteer_id" value="<?= h($volunteerProfile['id']) ?>"><input type="hidden" name="slot_id" value="<?= h($slot['id']) ?>"><button class="link-button danger-link" type="submit">Xóa</button></form></div><?php endforeach; ?>
                        <form method="post" class="stack-form"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="add_availability"><input type="hidden" name="volunteer_id" value="<?= h($volunteerProfile['id']) ?>"><label>Bắt đầu <span>(giờ Việt Nam)</span><input type="datetime-local" name="start_at" required></label><label>Kết thúc <span>(giờ Việt Nam)</span><input type="datetime-local" name="end_at" required></label><button class="button button-secondary" type="submit">Thêm khoảng rảnh</button></form>
                    </section>
                </div>
            </section>
        <?php elseif (($currentUser['role'] ?? '') === 'coordinator'): ?><div class="panel empty-inline">Chọn “Mở hồ sơ” để xem và chỉnh sửa chi tiết.</div><?php endif; ?>
    <?php elseif ($page === 'assignments'): ?>
        <section class="page-heading"><div><span class="eyebrow">Điều phối có kiểm soát</span><h1>Phân công</h1><p>Tạo phương án bằng hai thuật toán, xem chỉ số rồi công bố khi snapshot còn mới.</p></div></section>
        <form class="panel filter-bar" method="get"><input type="hidden" name="page" value="assignments"><label>Hoạt động<select name="event_id"><?php foreach ($events as $item): ?><option value="<?= h($item['id']) ?>" <?= (int) $item['id'] === (int) $selectedEventId ? 'selected' : '' ?>><?= h($item['name']) ?></option><?php endforeach; ?></select></label><button class="button button-secondary" type="submit">Xem</button></form>
        <?php if ($selectedEventId): ?>
            <section class="section-block action-grid">
                <form method="post" class="panel form-card stack-form"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="generate_allocation"><input type="hidden" name="event_id" value="<?= h($selectedEventId) ?>"><span class="eyebrow">Sinh phương án</span><h2>Chạy thuật toán</h2><label>Thuật toán<select name="algorithm"><option value="scarcity_balanced">Cải tiến — ưu tiên khan hiếm & cân bằng</option><option value="registration_order">Baseline — thứ tự đăng ký</option></select></label><button class="button" type="submit">Tạo phương án nháp</button></form>
                <form method="post" class="panel form-card stack-form"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="manual_assign"><input type="hidden" name="event_id" value="<?= h($selectedEventId) ?>"><span class="eyebrow">Can thiệp thủ công</span><h2>Thêm phân công</h2><label>Ca<select name="shift_id"><?php foreach ($shifts as $shift): ?><option value="<?= h($shift['id']) ?>"><?= h($shift['name']) ?> · <?= h(format_local_datetime($shift['start_at'])) ?></option><?php endforeach; ?></select></label><label>Tình nguyện viên<select name="volunteer_id"><?php foreach ($registrants as $person): ?><option value="<?= h($person['id']) ?>"><?= h($person['full_name']) ?></option><?php endforeach; ?></select></label><button class="button button-secondary" type="submit">Kiểm tra & phân công</button></form>
                <form method="post" class="panel form-card stack-form"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="compare_allocation"><input type="hidden" name="event_id" value="<?= h($selectedEventId) ?>"><span class="eyebrow">Đối chứng công bằng</span><h2>So sánh cùng snapshot</h2><p>Chạy baseline và thuật toán cải tiến trên đúng cùng dữ liệu/phiên bản lịch; cấu hình từng thuật toán được lưu.</p><button class="button button-secondary" type="submit">Chạy cặp đối chứng</button></form>
            </section>
            <section class="section-block"><div class="section-heading"><div><span class="eyebrow">Phương án</span><h2>Lịch sử chạy</h2></div></div><div class="panel table-wrap"><table><thead><tr><th>Mã</th><th>Thuật toán</th><th>Trạng thái</th><th>Đủ ca</th><th>Lấp vị trí</th><th>Vi phạm</th><th>Giải thích</th><th></th></tr></thead><tbody>
            <?php if ($runs === []): ?><tr><td class="empty-cell" colspan="8">Chưa có phương án.</td></tr><?php else: foreach ($runs as $run): ?>
                <tr><td>#<?= h($run['id']) ?></td><td><?= h($run['algorithm']) ?> v<?= h($run['algorithm_version']) ?><br><small><?= h($run['project_version']) ?> · <?= h(substr($run['snapshot_sha256'], 0, 10)) ?>…</small></td><td><span class="status-pill"><?= h($run['status']) ?></span></td><td><?= h($run['metrics']['full_shift_rate'] ?? '—') ?>%</td><td><?= h($run['metrics']['filled_position_rate'] ?? '—') ?>%</td><td><?= h($run['metrics']['violation_count'] ?? '—') ?></td><td><details><summary><?= count($run['metrics']['shortages'] ?? []) ?> ca thiếu · <?= count($run['unassigned']) ?> người 0 ca</summary><div class="detail-popover"><?php foreach (($run['metrics']['shortages'] ?? []) as $shortage): ?><p><strong><?= h($shortage['name']) ?></strong>: thiếu <?= h($shortage['missing']) ?>/<?= h($shortage['required_count']) ?></p><?php endforeach; ?><?php foreach ($run['unassigned'] as $person): ?><p><?= h($person['name']) ?>: <?= h($person['reason']) ?></p><?php endforeach; ?></div></details></td><td><?php if ($run['status'] === 'draft'): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="publish_allocation"><input type="hidden" name="run_id" value="<?= h($run['id']) ?>"><input type="hidden" name="event_id" value="<?= h($selectedEventId) ?>"><button class="button button-small" type="submit">Công bố</button></form><?php endif; ?></td></tr>
            <?php endforeach; endif; ?></tbody></table></div></section>
            <section class="section-block">
                <div class="section-heading"><div><span class="eyebrow">Lịch đang công bố</span><h2>Phân công hiện tại</h2></div><a class="button button-secondary button-small" href="<?= h(route_url('export', ['type' => 'assignments', 'event_id' => $selectedEventId])) ?>">Xuất CSV</a></div>
                <div class="panel table-wrap"><table><thead><tr><th>Hoạt động / ca</th><th>Tình nguyện viên</th><th>Thời gian</th><th>Trạng thái</th><th>Xử lý</th></tr></thead><tbody>
                <?php if ($assignments === []): ?><tr><td colspan="5" class="empty-cell">Chưa có phân công đang hoạt động.</td></tr><?php else: foreach ($assignments as $assignment): ?>
                    <tr><td><strong><?= h($assignment['shift_name']) ?></strong><br><small><?= h($assignment['event_name']) ?></small></td><td><?= h($assignment['volunteer_name']) ?></td><td><?= h(format_local_datetime($assignment['start_at'])) ?></td><td><?= h($assignment['status']) ?></td><td>
                        <form method="post" class="inline-action"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="mark_absent"><input type="hidden" name="assignment_id" value="<?= h($assignment['id']) ?>"><input type="hidden" name="version" value="<?= h($assignment['version']) ?>"><input type="hidden" name="event_id" value="<?= h($selectedEventId) ?>"><input name="reason" aria-label="Lý do vắng" placeholder="Lý do vắng" required><button class="button button-small button-ghost" type="submit">Báo vắng</button></form>
                        <form method="post" class="inline-action"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="transfer_assignment"><input type="hidden" name="assignment_id" value="<?= h($assignment['id']) ?>"><input type="hidden" name="version" value="<?= h($assignment['version']) ?>"><input type="hidden" name="event_id" value="<?= h($selectedEventId) ?>"><select name="volunteer_id" aria-label="Người nhận phân công mới"><?php foreach ($registrants as $person): ?><option value="<?= h($person['id']) ?>"><?= h($person['full_name']) ?></option><?php endforeach; ?></select><button class="button button-small button-secondary" type="submit">Chuyển</button></form>
                        <form method="post" class="inline-form"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="cancel_assignment"><input type="hidden" name="assignment_id" value="<?= h($assignment['id']) ?>"><input type="hidden" name="version" value="<?= h($assignment['version']) ?>"><input type="hidden" name="event_id" value="<?= h($selectedEventId) ?>"><button class="link-button danger-link" type="submit">Gỡ phân công</button></form>
                    </td></tr>
                <?php endforeach; endif; ?></tbody></table></div>
            </section>
            <section class="section-block">
                <div class="section-heading"><div><span class="eyebrow">Thay người</span><h2>Yêu cầu đang chờ</h2></div><span class="count-badge"><?= count($replacementRequests) ?> yêu cầu</span></div>
                <div class="panel table-wrap"><table><thead><tr><th>Người vắng</th><th>Ca</th><th>Lý do</th><th>Chọn người thay</th></tr></thead><tbody>
                <?php if ($replacementRequests === []): ?><tr><td colspan="4" class="empty-cell">Không có yêu cầu thay người.</td></tr><?php else: foreach ($replacementRequests as $request): ?>
                    <tr><td><?= h($request['absent_volunteer_name']) ?></td><td><strong><?= h($request['shift_name']) ?></strong><br><?= h(format_local_datetime($request['start_at'])) ?></td><td><?= h($request['reason']) ?></td><td><form method="post" class="inline-action"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="replace_absent"><input type="hidden" name="request_id" value="<?= h($request['id']) ?>"><input type="hidden" name="event_id" value="<?= h($selectedEventId) ?>"><select name="volunteer_id"><?php foreach ($registrants as $person): ?><option value="<?= h($person['id']) ?>"><?= h($person['full_name']) ?></option><?php endforeach; ?></select><button class="button button-small" type="submit">Kiểm tra & thay</button></form><form method="post" class="inline-action"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="mark_replacement_unfilled"><input type="hidden" name="request_id" value="<?= h($request['id']) ?>"><input type="hidden" name="event_id" value="<?= h($selectedEventId) ?>"><input name="resolution_note" maxlength="1000" placeholder="Lý do chưa tìm được người"><button class="button button-small button-ghost" type="submit">Kết thúc chưa có người</button></form></td></tr>
                <?php endforeach; endif; ?></tbody></table></div>
            </section>
            <section class="section-block action-grid">
                <div><div class="section-heading"><div><span class="eyebrow">Lưu vết</span><h2>Lịch sử phân công</h2></div></div><div class="panel table-wrap"><table><thead><tr><th>Người</th><th>Ca</th><th>Trạng thái</th><th>Cập nhật</th></tr></thead><tbody><?php if ($assignmentHistory === []): ?><tr><td colspan="4" class="empty-cell">Chưa có phân công đã kết thúc.</td></tr><?php else: foreach ($assignmentHistory as $item): ?><tr><td><?= h($item['volunteer_name']) ?></td><td><?= h($item['shift_name']) ?></td><td><?= h($item['status']) ?></td><td><?= h(format_local_datetime($item['updated_at'])) ?></td></tr><?php endforeach; endif; ?></tbody></table></div></div>
                <div><div class="section-heading"><div><span class="eyebrow">Thay người</span><h2>Lịch sử xử lý</h2></div></div><div class="panel table-wrap"><table><thead><tr><th>Người vắng / ca</th><th>Kết quả</th><th>Ghi chú</th><th></th></tr></thead><tbody><?php if ($replacementHistory === []): ?><tr><td colspan="4" class="empty-cell">Chưa có yêu cầu đã xử lý.</td></tr><?php else: foreach ($replacementHistory as $item): ?><tr><td><?= h($item['absent_volunteer_name']) ?><br><small><?= h($item['shift_name']) ?></small></td><td><?= h($item['status']) ?><?= $item['replacement_volunteer_name'] ? '<br><small>' . h($item['replacement_volunteer_name']) . '</small>' : '' ?></td><td><?= h($item['resolution_note'] ?: '—') ?></td><td><?php if ($item['status'] === 'unfilled'): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="reopen_replacement"><input type="hidden" name="request_id" value="<?= h($item['id']) ?>"><input type="hidden" name="event_id" value="<?= h($selectedEventId) ?>"><button class="button button-small button-ghost" type="submit">Mở lại</button></form><?php endif; ?></td></tr><?php endforeach; endif; ?></tbody></table></div></div>
            </section>
        <?php else: ?><div class="panel empty-inline">Chưa có hoạt động để phân công.</div><?php endif; ?>
    <?php elseif ($page === 'my_shifts'): ?>
        <section class="page-heading"><div><span class="eyebrow">Lịch cá nhân</span><h1>Ca của tôi</h1><p>Xem và xác nhận các ca đã được điều phối công bố.</p></div></section>
        <div class="event-grid"><?php if ($myShifts === []): ?><div class="panel empty-inline">Bạn chưa có ca được phân công.</div><?php else: foreach ($myShifts as $item): ?><article class="panel form-card"><span class="status-pill"><?= h($item['status']) ?></span><h3><?= h($item['shift_name']) ?></h3><p><strong><?= h($item['event_name']) ?></strong><br><?= h($item['location']) ?><br><?= h(format_local_datetime($item['start_at'])) ?> — <?= h(format_local_datetime($item['end_at'])) ?></p><div class="card-actions"><?php if ($item['status'] === 'assigned'): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="confirm_assignment"><input type="hidden" name="assignment_id" value="<?= h($item['id']) ?>"><input type="hidden" name="version" value="<?= h($item['version']) ?>"><button class="button button-small" type="submit">Xác nhận tham gia</button></form><?php endif; ?><form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="report_absent"><input type="hidden" name="assignment_id" value="<?= h($item['id']) ?>"><input type="hidden" name="version" value="<?= h($item['version']) ?>"><input name="reason" maxlength="1000" placeholder="Lý do báo vắng" required><button class="button button-small button-ghost" type="submit">Báo vắng</button></form></div></article><?php endforeach; endif; ?></div>
        <section class="section-block"><div class="section-heading"><div><span class="eyebrow">Lưu vết cá nhân</span><h2>Lịch sử ca</h2></div></div><div class="panel table-wrap"><table><thead><tr><th>Hoạt động</th><th>Ca</th><th>Thời gian</th><th>Trạng thái</th></tr></thead><tbody><?php if ($myShiftHistory === []): ?><tr><td colspan="4" class="empty-cell">Chưa có ca trong lịch sử.</td></tr><?php else: foreach ($myShiftHistory as $item): ?><tr><td><?= h($item['event_name']) ?></td><td><?= h($item['shift_name']) ?></td><td><?= h(format_local_datetime($item['start_at'])) ?></td><td><?= h($item['status']) ?></td></tr><?php endforeach; endif; ?></tbody></table></div></section>
    <?php elseif ($page === 'stats'): ?>
        <section class="page-heading"><div><span class="eyebrow">Báo cáo</span><h1>Thống kê & so sánh</h1><p>Số liệu lấy trực tiếp từ MySQL; kết quả thuật toán gắn với snapshot và phiên bản.</p></div><a class="button button-secondary" href="<?= h(route_url('export', ['type' => 'summary'])) ?>">Xuất tổng hợp CSV</a></section>
        <div class="panel table-wrap"><table><thead><tr><th>Hoạt động</th><th>Ca</th><th>Đăng ký</th><th>Vị trí cần</th><th>Đã phân công</th><th>Đã xác nhận</th><th>Còn thiếu</th><th>Tỷ lệ lấp</th></tr></thead><tbody><?php foreach ($reportRows as $row): $fill = (int) $row['required_positions'] > 0 ? round(100 * (int) $row['assigned_positions'] / (int) $row['required_positions'], 1) : 0; $missing = max(0, (int) $row['required_positions'] - (int) $row['assigned_positions']); ?><tr><td><strong><?= h($row['name']) ?></strong></td><td><?= h($row['shifts']) ?></td><td><?= h($row['registrations']) ?></td><td><?= h($row['required_positions']) ?></td><td><?= h($row['assigned_positions']) ?></td><td><?= h($row['confirmed_positions']) ?></td><td><?= h($missing) ?></td><td><?= h($fill) ?>%</td></tr><?php endforeach; ?></tbody></table></div>
        <section class="section-block"><div class="section-heading"><div><span class="eyebrow">Vận hành trực tiếp</span><h2>Tình trạng từng ca</h2></div><a class="button button-secondary button-small" href="<?= h(route_url('export', ['type' => 'shift_status'])) ?>">Xuất tình trạng ca</a></div><div class="panel table-wrap"><table><thead><tr><th>Hoạt động / ca</th><th>Thời gian</th><th>Kỹ năng</th><th>Cần</th><th>Đã xếp</th><th>Xác nhận</th><th>Còn thiếu</th><th>Thay người</th></tr></thead><tbody><?php if ($shiftReportRows === []): ?><tr><td colspan="8" class="empty-cell">Chưa có ca để thống kê.</td></tr><?php else: foreach ($shiftReportRows as $row): ?><tr><td><strong><?= h($row['shift_name']) ?></strong><br><small><?= h($row['event_name']) ?></small></td><td><?= h(format_local_datetime($row['start_at'])) ?></td><td><?= h($row['required_skills'] ?: 'Không yêu cầu') ?></td><td><?= h($row['required_count']) ?></td><td><?= h($row['assigned_count']) ?></td><td><?= h($row['confirmed_count']) ?></td><td><span class="status-pill <?= (int) $row['missing_count'] > 0 ? 'status-warning' : '' ?>"><?= h($row['missing_count']) ?></span></td><td><?= h($row['open_replacements']) ?> mở · <?= h($row['unfilled_replacements']) ?> chưa tìm được</td></tr><?php endforeach; endif; ?></tbody></table></div></section>
        <section class="section-block"><div class="section-heading"><div><span class="eyebrow">A/B cùng dữ liệu</span><h2>Cặp đối chứng hợp lệ</h2></div></div><div class="panel table-wrap"><table><thead><tr><th>Hoạt động / nhóm</th><th>Snapshot / mã nguồn</th><th>Baseline</th><th>Cải tiến</th><th>Chênh lệch</th></tr></thead><tbody><?php if ($comparisonGroups === []): ?><tr><td colspan="5" class="empty-cell">Chưa có cặp đối chứng. Chạy tại trang Phân công.</td></tr><?php else: foreach ($comparisonGroups as $group): $baseline = $group['baseline']; $improved = $group['improved']; $baseFill = $baseline ? (float) ($baseline['metrics']['filled_position_rate'] ?? 0) : 0; $improvedFill = $improved ? (float) ($improved['metrics']['filled_position_rate'] ?? 0) : 0; ?><tr><td><strong><?= h($group['event_name']) ?></strong><br><small><?= h(substr($group['id'], 0, 12)) ?></small></td><td><code><?= h(substr($group['snapshot_sha256'], 0, 12)) ?>…</code><br><small><?= h($group['project_version']) ?> · <?= h($group['dataset_code']) ?></small></td><td><?= $baseline ? h($baseFill) . '% lấp · tải ' . h($baseline['metrics']['load_gap'] ?? '—') : 'Thiếu bản chạy' ?></td><td><?= $improved ? h($improvedFill) . '% lấp · tải ' . h($improved['metrics']['load_gap'] ?? '—') : 'Thiếu bản chạy' ?></td><td><?= ($baseline && $improved) ? h(round($improvedFill - $baseFill, 1)) . ' điểm %' : '—' ?></td></tr><?php endforeach; endif; ?></tbody></table></div></section>
        <section class="section-block"><div class="section-heading"><div><span class="eyebrow">Đối chứng</span><h2>Kết quả các lần chạy</h2></div></div><div class="panel table-wrap"><table><thead><tr><th>Hoạt động</th><th>Thuật toán / phiên bản</th><th>Trạng thái</th><th>Đủ ca</th><th>Lấp vị trí</th><th>Chênh tải</th><th>Vi phạm</th></tr></thead><tbody><?php if ($runs === []): ?><tr><td class="empty-cell" colspan="7">Chưa có bản chạy trong MySQL. Có thể chạy fixture bằng lệnh trong README.</td></tr><?php else: foreach ($runs as $run): ?><tr><td><?= h($run['event_name']) ?></td><td><?= h($run['algorithm']) ?> v<?= h($run['algorithm_version']) ?></td><td><?= h($run['status']) ?></td><td><?= h($run['metrics']['full_shift_rate'] ?? '—') ?>%</td><td><?= h($run['metrics']['filled_position_rate'] ?? '—') ?>%</td><td><?= h($run['metrics']['load_gap'] ?? '—') ?></td><td><?= h($run['metrics']['violation_count'] ?? '—') ?></td></tr><?php endforeach; endif; ?></tbody></table></div></section>
    <?php else: ?>
        <?php http_response_code(404); ?>
        <section class="empty-state"><span class="empty-icon">?</span><p class="eyebrow">404</p><h1>Không tìm thấy trang</h1><p>Đường dẫn bạn mở không tồn tại trong hệ thống.</p><a class="button" href="<?= h(route_url('dashboard')) ?>">Về tổng quan</a></section>
    <?php endif; ?>
</main>

<footer class="site-footer"><div><strong>VolunteerHub</strong><span>Hệ thống tổ chức & phân công · Đề tài 6</span></div><p>Giờ hiển thị: Việt Nam (UTC+7)</p></footer>
<script src="/assets/app.js"></script>
</body>
</html>
