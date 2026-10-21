<?php
require __DIR__ . '/../src/Input.php';
$loi = [];
$thanh_cong = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ho_ten = field($_POST, 'ho_ten');
    $mssv = field($_POST, 'mssv');
    $email = field($_POST, 'email');
    $mat_khau = field($_POST, 'mat_khau');
    $xac_nhan_mk = field($_POST, 'xac_nhan_mk');

    if ($ho_ten === '') { $loi[] = 'Họ và tên không được để trống.'; }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $loi[] = 'Email không hợp lệ.'; }
    if (strlen($mat_khau) < 6) { $loi[] = 'Mật khẩu phải từ 6 ký tự trở lên.'; }
    if ($mat_khau !== $xac_nhan_mk) { $loi[] = 'Xác nhận mật khẩu không trùng khớp.'; }

    if (empty($loi)) {
        $thanh_cong = true;
    }
}

require __DIR__ . '/../templates/header.php';
?>

<?php if ($thanh_cong): ?>
    <!-- BẢNG THÔNG BÁO THÀNH CÔNG TO Ở CHÍNH GIỮA (ĐÃ BỎ HEADER PHÍA TRÊN) -->
    <div class="khung_thanh_cong_to">
        <div class="the_thong_bao_to">
            <span class="nhan_xac_nhan_tnv">XÁC NHẬN THÀNH VIÊN MỚI</span>
            <h2>Đăng Ký Tài Khoản Thành Công!</h2>
            <p class="loi_chuc_mung">
                Chào mừng bạn <strong><?= e($ho_ten) ?></strong> đã chính thức gia nhập lực lượng<br>
                <strong>Đội Tình Nguyện D2L Team</strong>.
            </p>

            <div class="hop_thong_tin_nhanh">
                <span><strong>Họ và tên:</strong> <?= e($ho_ten) ?></span>
                <?php if ($mssv !== ''): ?>
                    <span><strong>MSSV:</strong> <?= e($mssv) ?></span>
                <?php endif; ?>
                <span><strong>Email:</strong> <?= e($email) ?></span>
            </div>

            <div class="cum_nut_hanh_dong">
                <a href="/dang_nhap.php" class="btn-primary nut_to_ro">🔑 Đăng Nhập Ngay</a>
                <a href="/" class="btn-detail nut_to_ro">📋 Xem Các Chiến Dịch</a>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- FORM ĐĂNG KÝ (CHỈ HIỆN KHI CHƯA ĐĂNG KÝ THÀNH CÔNG) -->
    <div class="khung_xac_thuc">
        <div class="the_tai_khoan">
            <div class="dau_the_tk">
                <h2>Gia Nhập Đội Tình Nguyện D2L</h2>
                <p>Đăng ký tài khoản để ghi danh và nhận phân công nhiệm vụ</p>
            </div>

            <?php if (!empty($loi)): ?>
                <div class="error-summary hop_bao_loi" style="margin-bottom: 15px;">
                    <ul style="margin: 0; padding-left: 20px;">
                        <?php foreach ($loi as $item): ?>
                            <li><?= e($item) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" class="form_xac_thuc">
                <label class="form-field" for="ho_ten">
                    <span>Họ và tên <strong class="required">*</strong></span>
                    <input id="ho_ten" name="ho_ten" placeholder="Ví dụ: Lê Minh Trí" value="<?= e(field($_POST, 'ho_ten')) ?>" required>
                </label>

                <div class="hang_doi">
                    <label class="form-field" for="mssv">
                        <span>Mã số sinh viên (MSSV)</span>
                        <input id="mssv" name="mssv" placeholder="Ví dụ: 21120000" value="<?= e(field($_POST, 'mssv')) ?>">
                    </label>

                    <label class="form-field" for="email">
                        <span>Địa chỉ Email <strong class="required">*</strong></span>
                        <input type="email" id="email" name="email" placeholder="email@sinhvien.edu.vn" value="<?= e(field($_POST, 'email')) ?>" required>
                    </label>
                </div>

                <div class="hang_doi">
                    <label class="form-field" for="mat_khau">
                        <span>Mật khẩu <strong class="required">*</strong></span>
                        <input type="password" id="mat_khau" name="mat_khau" placeholder="Tối thiểu 6 ký tự" required>
                    </label>

                    <label class="form-field" for="xac_nhan_mk">
                        <span>Xác nhận mật khẩu <strong class="required">*</strong></span>
                        <input type="password" id="xac_nhan_mk" name="xac_nhan_mk" placeholder="Nhập lại mật khẩu" required>
                    </label>
                </div>

                <button type="submit" class="btn-primary nut_toan_chieu">Đăng Ký Thành Viên</button>

                <p class="chuyen_huong_tk">
                    Đã có tài khoản? <a href="/dang_nhap.php">Đăng nhập tại đây</a>
                </p>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../templates/footer.php'; ?>
