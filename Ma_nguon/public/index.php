<?php
require __DIR__ . '/../src/Input.php';
require __DIR__ . '/../src/EventFilter.php';
$events = require __DIR__ . '/../data/events.php';

// Đọc 3 tham số từ GET (Chuẩn Lab 03)
$query = field($_GET, 'q');
$category = field($_GET, 'category');
$availableOnly = field($_GET, 'available') === '1';

// Lọc danh sách hoạt động với đủ 3 điều kiện
$visible = filterEvents($events, $query, $category, $availableOnly);

require_once __DIR__ . '/../templates/event-presentation.php';
require __DIR__ . '/../templates/header.php';
?>

<!-- ========================================================
     1. BANNER HERO FULL NGANG - CHUYỂN SLIDE TỰ ĐỘNG
     ======================================================== -->
<section class="banner_hero_slider">
    <!-- SLIDE 1: LOGO D2L TEAM & SỨ MỆNH (ĐỒNG BỘ 100% KÍCH THƯỚC FULL BANNER) -->
    <div class="slide_banner slide_logo_toan_man active" style="background-image: url('/assets/images/banner_logo_d2l.jpg');">
        <a href="#danh_sach_chien_dich" class="lop_link_toan_slide" title="Đội tình nguyện D2L Team - Sứ Mệnh Kết Nối & Tuổi Trẻ Cống Hiến">
            <span class="sr_only">Khám phá các chiến dịch tình nguyện của D2L Team</span>
        </a>
    </div>


    <!-- SLIDE 2: CHIẾN DỊCH PHỔ CẬP TIN HỌC VÙNG CAO -->
    <div class="slide_banner" style="background-image: url('/assets/images/banner_tnv_1.jpg');">
        <div class="lop_phu_slide">
            <div class="noi_dung_slide">
                <span class="huy_hieu_slide">CHIẾN DỊCH MÙA HÈ XANH</span>
                <h1 class="tieu_de_slide">Phổ Cập Kỹ Thuật Số & Trao Tặng Máy Tính</h1>
                <p class="mo_ta_slide">
                    Đội hình TNV D2L mang phòng máy tính, mở lớp phổ cập tin học và thiết bị học tập cho trẻ em nghèo vùng cao.
                </p>
                <div class="cum_nut_slide">
                    <a href="#danh_sach_chien_dich" class="btn-primary nut_cta_lon">🚀 Khám Phá Dự Án</a>
                    <a href="/register.php" class="btn-detail nut_cta_trang">✍️ Đăng Ký Tham Gia</a>
                </div>
            </div>
        </div>
    </div>
    <!-- SLIDE 3: CHIẾN DỊCH THẮP SÁNG BẢN LÀNG -->
    <div class="slide_banner" style="background-image: url('/assets/images/banner_tnv_2.jpg');">
        <div class="lop_phu_slide">
            <div class="noi_dung_slide">
                <span class="huy_hieu_slide huy_hieu_mint">THẮP SÁNG BẢN LÀNG 2026</span>
                <h1 class="tieu_de_slide">Lắp Điện Năng Lượng & Áo Ấm Vùng Xa</h1>
                <p class="mo_ta_slide">
                    Đội hình xung kích kỹ thuật lắp đặt đèn đường năng lượng mặt trời và sơn sửa điểm trường vùng cao.
                </p>
                <div class="cum_nut_slide">
                    <a href="#danh_sach_chien_dich" class="btn-primary nut_cta_lon">🌟 Xem Danh Sách Hoạt Động</a>
                    <a href="/register.php" class="btn-detail nut_cta_trang">🤝 Tham Gia Đội Hình</a>
                </div>
            </div>
        </div>
    </div>
    <!-- Nút điều khiển chuyển slide trái / phải -->
    <button class="nut_chuyen_trai" aria-label="Slide trước">&#10094;</button>
    <button class="nut_chuyen_phai" aria-label="Slide tiếp theo">&#10095;</button>
    <!-- 3 Chấm tròn điều khiển -->
    <div class="cac_cham_dieu_khien">
        <span class="cham_chuyen_slide active"></span>
        <span class="cham_chuyen_slide"></span>
        <span class="cham_chuyen_slide"></span>
    </div>
</section>

<!-- ========================================================
     2. DẢI THỐNG KÊ NHANH CỦA ĐỘI TÌNH NGUYỆN D2L
     ======================================================== -->
<div class="bang_thong_ke_nhanh">
    <div class="luoi_thong_ke">
        <div class="the_so_lieu">
            <strong>03+</strong>
            <span>Chiến Dịch Lớn</span>
        </div>
        <div class="the_so_lieu">
            <strong>35+</strong>
            <span>Chỉ Tiêu Tuyển Quân</span>
        </div>
        <div class="the_so_lieu">
            <strong>100%</strong>
            <span>Nhiệt Huyết Xung Kích</span>
        </div>
        <div class="the_so_lieu">
            <strong>Vùng Xa</strong>
            <span>Địa Bàn Trọng Điểm</span>
        </div>
    </div>
</div>

<!-- ========================================================
     3. TỔNG QUAN VỀ 3 TRỤ CỘT HOẠT ĐỘNG CHÍNH
     ======================================================== -->
<section class="khu_vuc_tru_cot">
    <div class="tieu_de_phan_doan">
        <h2>Trụ Cột Hoạt Động Của D2L Team</h2>
        <p>3 sứ mệnh cốt lõi xuyên suốt các chiến dịch tình nguyện của đội</p>
    </div>

    <div class="luoi_tru_cot">
        <div class="the_tru_cot">
            <div class="icon_tru_cot">💻</div>
            <h3>Phổ Cập Kỹ Thuật Số</h3>
            <p>Xây dựng phòng máy tính, mở lớp phổ cập tin học và kỹ năng số căn bản cho các em học sinh vùng đồng bào khó khăn.</p>
        </div>

        <div class="the_tru_cot">
            <div class="icon_tru_cot">🎒</div>
            <h3>Áo Ấm & Cùng Em Đến Trường</h3>
            <p>Quyên góp chăn ấm, áo khoác mùa đông, cải tạo không gian lớp học và trao học bổng tiếp bước học sinh nghèo vượt khó.</p>
        </div>

        <div class="the_tru_cot">
            <div class="icon_tru_cot">💡</div>
            <h3>Thắp Sáng Bản Làng</h3>
            <p>Đội hình kỹ thuật tình nguyện lắp đặt hệ thống đèn năng lượng mặt trời, kéo nguồn điện an toàn cho các điểm trường xa xôi.</p>
        </div>
    </div>
</section>

<!-- ========================================================
     4. KHU VỰC BỘ LỌC VÀ DANH SÁCH CHIẾN DỊCH (CHUẨN LAB 03)
     ======================================================== -->
<div id="danh_sach_chien_dich" class="page-heading page-heading--list page-search">
    <h2>Danh Sách Chiến Dịch Đang Tuyển Quân</h2>
    <p>Tra cứu và chọn hoạt động phù hợp với chuyên môn của bạn để đăng ký điều động.</p>
</div>

<!-- Form Lọc Đa Năng 3 Điều Kiện (GET - Chuẩn Lab 03) -->
<form class="search-form volunteer-filter-bar" method="get">
    <div class="filter-inputs">
        <!-- 1. Từ khóa -->
        <label class="form-field" for="q">
            <span>Từ khóa chiến dịch</span>
            <input id="q" name="q" placeholder="Nhập tên chiến dịch, hoạt động..." value="<?= e($query) ?>">
        </label>

        <!-- 2. Lĩnh vực hoạt động -->
        <label class="form-field" for="category">
            <span>Lĩnh vực chuyên môn</span>
            <select id="category" name="category">
                <option value="">— Tất cả lĩnh vực —</option>
                <option value="tech" <?= $category === 'tech' ? 'selected' : '' ?>>Công nghệ & Tin học cộng đồng (tech)</option>
                <option value="design" <?= $category === 'design' ? 'selected' : '' ?>>Truyền thông & Gây quỹ (design)</option>
            </select>
        </label>

        <!-- 3. Chỉ còn nhận TNV -->
        <label class="form-field form-field--checkbox o_danh_dau" for="available">
            <input type="checkbox" id="available" name="available" value="1" <?= $availableOnly ? 'checked' : '' ?>>
            <span>Chỉ xem hoạt động đang tuyển TNV</span>
        </label>
    </div>

    <div class="filter-actions" style="margin-top: 15px; display: flex; align-items: center;">
        <button type="submit" class="btn-primary">Tìm kiếm chiến dịch</button>
        <?php if ($query !== '' || $category !== '' || $availableOnly): ?>
            <a href="/#danh_sach_chien_dich" class="btn-reset">Đặt lại bộ lọc</a>
        <?php endif; ?>
    </div>
</form>

<p class="result-count">Đang hiển thị: <strong><?= count($visible) ?></strong> hoạt động phù hợp</p>

<!-- Lưới Thẻ Chiến Dịch Hoạt Động (Chuẩn Lab 03) -->
<section class="event-list" aria-label="Danh sách hoạt động tình nguyện">
<?php foreach ($visible as $event): ?>
    <?php 
        $content = uiContent((int)$event['id']); 
        $remaining = max(0, $event['capacity'] - $event['registered']);
        $isFull = $remaining === 0;
        $percent = $event['capacity'] > 0 ? min(100, round(($event['registered'] / $event['capacity']) * 100)) : 0;
    ?>
    <article class="event-item event-card">
        <figure class="event-cover">
            <img src="<?= e(uiImage($content['image'])) ?>" alt="<?= e($content['alt']) ?>" width="960" height="540">
            <figcaption class="event-badge <?= $isFull ? 'badge--full' : 'badge--open' ?>">
                <?= $isFull ? 'Đã đủ quân số' : 'Đang tuyển TNV' ?>
            </figcaption>
        </figure>
        <div class="event-card-content">
            <div class="event-category-tag"><?= e(strtoupper($event['category'])) ?></div>
            <h3 class="event-title"><?= e($event['title']) ?></h3>
            <p class="event-date">📅 <?= e(uiDate($content['start'])) ?></p>
            <p class="event-venue">📍 <?= e($content['venue']) ?></p>
            <p class="event-summary"><?= e($content['summary']) ?></p>

            <!-- Thanh tiến độ tuyển TNV -->
            <div class="volunteer-progress">
                <div class="progress-info">
                    <span>Quân số: <strong><?= (int)$event['registered'] ?>/<?= (int)$event['capacity'] ?></strong> TNV</span>
                    <span><?= $isFull ? '<span style="color:#ef4444;">Đã đủ</span>' : 'Còn <strong>' . $remaining . '</strong> suất' ?></span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?= $percent ?>%;"></div>
                </div>
            </div>

            <div class="event-card-actions" style="display: flex; gap: 10px; margin-top: 15px;">
                <a class="btn-detail" href="/event.php?id=<?= (int)$event['id'] ?>">Xem chi tiết</a>
                <?php if (!$isFull): ?>
                    <a class="btn-register" href="/register.php?event_id=<?= (int)$event['id'] ?>">Đăng ký tham gia</a>
                <?php else: ?>
                    <button class="btn-detail" style="opacity: 0.5; cursor: not-allowed;" disabled>Đã đủ chỉ tiêu</button>
                <?php endif; ?>
            </div>
        </div>
    </article>
<?php endforeach; ?>
</section>

<?php if (!$visible): ?>
    <div class="empty-state" style="text-align: center; padding: 40px; background: #ffffff; border: 1.5px dashed #cbd5e1; border-radius: 14px; margin-top: 20px;">
        <p style="font-size: 2.5rem; margin-bottom: 10px;">🔍</p>
        <h3 style="color: var(--chu_dam); margin-bottom: 6px;">Không tìm thấy hoạt động tình nguyện nào phù hợp.</h3>
        <span style="color: var(--chu_nhe);">Thử bỏ bớt điều kiện lọc hoặc tìm kiếm với từ khóa khác.</span>
        <p><a href="/#danh_sach_chien_dich" class="btn-primary" style="display:inline-block; margin-top: 15px;">Xem tất cả chiến dịch</a></p>
    </div>
<?php endif; ?>

<!-- Nạp tệp kịch bản chuyển động Banner tự động -->
<script src="/assets/js/banner_chuyen_dong.js?v=<?= time() ?>"></script>

<?php require __DIR__ . '/../templates/footer.php'; ?>
