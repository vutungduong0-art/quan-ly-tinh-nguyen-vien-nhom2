<?php
require __DIR__ . '/../src/Input.php';
$thong_bao = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = field($_POST, 'email');
    $mat_khau = field($_POST, 'mat_khau');

    if ($email === '' || $mat_khau === '') {
        $thong_bao = 'Vui lòng nhập đầy đủ Email và Mật khẩu!';
    } else {
        $thong_bao = 'SUCCESS';
    }
}

require __DIR__ . '/../templates/header.php';
?>

<div class="khung_xac_thuc">
    <div class="the_tai_khoan">
        <div class="dau_the_tk">
            <h2>Đăng Nhập Hệ Thống</h2>
            <p>Dành cho Tình nguyện viên & Ban điều phối D2L Team</p>
        </div>

        <?php if ($thong_bao === 'SUCCESS'): ?>
            <div class="thong_bao_thanh_cong">
                <p>🎉 <strong>Đăng nhập thành công!</strong> Chào mừng bạn quay trở lại đội hình.</p>
                <p><a href="/" class="btn-primary" style="display:inline-block; margin-top:10px;">Về trang chiến dịch</a></p>
            </div>
        <?php else: ?>
            <?php if ($thong_bao !== ''): ?>
                <div class="error-summary hop_bao_loi" style="margin-bottom: 15px;">
                    <p><?= e($thong_bao) ?></p>
                </div>
            <?php endif; ?>

            <form method="post" class="form_xac_thuc">
                <label class="form-field" for="email">
                    <span>Email</span>
                    <input type="email" id="email" name="email" placeholder="ví dụ: volunteer@d2l.edu.vn" value="<?= e(field($_POST, 'email')) ?>" required>
                </label>

                <label class="form-field" for="mat_khau">
                    <span>Mật khẩu</span>
                    <input type="password" id="mat_khau" name="mat_khau" placeholder="Nhập mật khẩu" required>
                </label>

                <div class="tuy_chon_phu">
                    <label class="o_danh_dau">
                        <input type="checkbox" name="ghi_nho"> Ghi nhớ đăng nhập
                    </label>
                    <a href="#" class="quen_mk">Quên mật khẩu?</a>
                </div>

                <button type="submit" class="btn-primary nut_toan_chieu">Đăng Nhập Ngay</button>

                <p class="chuyen_huong_tk">
                    Chưa có tài khoản? <a href="/dang_ky_tk.php">Đăng ký thành viên mới</a>
                </p>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
