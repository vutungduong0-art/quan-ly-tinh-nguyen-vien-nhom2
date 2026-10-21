<?php
require __DIR__ . '/../src/Input.php';
$events = require __DIR__ . '/../data/events.php';

$result = null;
// Lấy event_id được chọn: ưu tiên POST khi submit, hoặc GET khi click từ trang chủ
$selectedEventId = field($_POST, 'event_id') ?: field($_GET, 'event_id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = validateRegistration($_POST, $events);
}

require __DIR__ . '/../templates/header.php';
?>

<div class="page-heading">
    <h1>Đăng Ký Tham Dự Đội Tình Nguyện D2L</h1>
    <p>Điền thông tin tình nguyện viên để nhận phân công nhiệm vụ và kiểm tra xem trước.</p>
</div>

<!-- Form Đăng Ký (POST) -->
<form class="registration-form volunteer-form" method="post">
    <!-- 1. Chọn Chiến dịch / Hoạt động -->
    <label class="form-field" for="event_id">
        <span>Chiến dịch tình nguyện tham gia <strong class="required">*</strong></span>
        <select id="event_id" name="event_id">
            <option value="">— Chọn chiến dịch tình nguyện —</option>
            <?php foreach ($events as $event): ?>
                <?php 
                    $isSelected = (string)$event['id'] === (string)$selectedEventId;
                    $remaining = max(0, $event['capacity'] - $event['registered']);
                ?>
                <option value="<?= $event['id'] ?>" <?= $isSelected ? 'selected' : '' ?>>
                    <?= e($event['title']) ?> (Còn <?= $remaining ?> suất)
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <!-- 2. Họ và tên Tình nguyện viên -->
    <label class="form-field" for="name">
        <span>Họ và tên Tình nguyện viên <strong class="required">*</strong></span>
        <input id="name" name="name" maxlength="120" placeholder="Ví dụ: Nguyễn Văn An"
               value="<?= e(field($_POST, 'name')) ?>" required>
    </label>

    <!-- 3. Email liên hệ -->
    <label class="form-field" for="email">
        <span>Địa chỉ Email sinh viên / TNV <strong class="required">*</strong></span>
        <input type="email" id="email" name="email" placeholder="Ví dụ: volunteer@hcmus.edu.vn"
               value="<?= e(field($_POST, 'email')) ?>" required>
    </label>

    <div class="form-actions">
        <button type="submit" class="btn-primary">Kiểm tra & Xem trước phân công</button>
        <a href="/" class="btn-cancel">Quay lại danh sách</a>
    </div>
</form>

<!-- Khu vực Hiển thị Kết quả -->
<?php if ($result !== null): ?>
    <?php if ($result['errors']): ?>
        <div class="error-summary" role="alert">
            <h3 class="error-title">⚠️ Thông tin đăng ký chưa hợp lệ</h3>
            <p class="error-messages">Mã lỗi hệ thống: <code><?= e(implode(', ', $result['errors'])) ?></code></p>
            <ul class="error-details">
                <?php if (in_array('EVENT_INVALID', $result['errors'], true)): ?>
                    <li>Vui lòng chọn một chiến dịch tình nguyện hợp lệ từ danh sách.</li>
                <?php endif; ?>
                <?php if (in_array('NAME_INVALID', $result['errors'], true)): ?>
                    <li>Họ và tên không được để trống và không vượt quá 120 ký tự.</li>
                <?php endif; ?>
                <?php if (in_array('EMAIL_INVALID', $result['errors'], true)): ?>
                    <li>Địa chỉ email không đúng định dạng chuẩn hoặc quá dài.</li>
                <?php endif; ?>
            </ul>
            <p>Vui lòng kiểm tra lại thông tin trên form và gửi lại.</p>
        </div>
    <?php else: ?>
        <!-- Hiển thị Thẻ Nhiệm Vụ TNV thành công -->
        <section class="preview mission-pass" role="status">
            <div class="pass-header">
                <span class="pass-badge">XÁC NHẬN ĐĂNG KÝ THÀNH CÔNG</span>
                <h2>THẺ ĐIỀU ĐỘNG TÌNH NGUYỆN VIÊN (BẢN XEM TRƯỚC)</h2>
            </div>
            
            <!-- Dòng trạng thái chuẩn Lab 03 để đảm bảo kiểm thử -->
            <p class="status-message">PREVIEW_OK: <?= e($result['data']['name']) ?> — <?= e($result['data']['email']) ?></p>

            <div class="pass-body">
                <p><strong>Mã chiến dịch:</strong> #<?= (int)$result['data']['event_id'] ?></p>
                <p><strong>Họ và tên TNV:</strong> <?= e($result['data']['name']) ?></p>
                <p><strong>Email tiếp nhận nhiệm vụ:</strong> <?= e($result['data']['email']) ?></p>
                <p class="pass-note"><em>Lưu ý: Đây là bản xem trước thông tin kiểm tra máy chủ, chưa ghi vào cơ sở dữ liệu chính thức theo yêu cầu thực hành Lab 03.</em></p>
            </div>
        </section>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/../templates/footer.php'; ?>
